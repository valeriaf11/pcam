<?php
/**
 * ============================================================================
 * Usuario.php  -  Modelo de la tabla "usuarios" (SQLite)
 * ============================================================================
 * CORRECCIONES:
 *  - Las consultas nuevo/actualizar/borrar metían los valores directo en el
 *    SQL ("... VALUES ('$nombre', ...)") -> SQL INJECTION. Ahora TODAS usan
 *    consultas preparadas con parámetros.
 *  - Las contraseñas se guardaban en texto plano. Ahora se guardan con
 *    password_hash(). Si en tu BD vieja hay contraseñas en texto plano, al
 *    entrar la primera vez se convierten solas a hash (ver verificarCredenciales).
 *  - La lógica de BD que estaba en AuthController se movió aquí (MVC:
 *    el controlador NO habla directo con la BD, lo hace el modelo).
 */

class Usuario
{
    /** @var PDO */
    private $db;

    /** Columnas que se regresan al navegador (NUNCA el password) */
    private const CAMPOS = 'u.id, u.nombre, u.username, u.email, u.ip, u.rol_id, r.nombre AS rol, u.nivel_inicial, u.editable';

    public function __construct()
    {
        $this->db = Database::conexion();
    }

    /** Busca al usuario dueño de una IP (acceso automático sin login) */
    public function buscarPorIP(string $ip): ?array
    {
        if ($ip === '') {
            return null;
        }
        $st = $this->db->prepare('SELECT ' . self::CAMPOS . ' FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.ip = :ip LIMIT 1');
        $st->execute([':ip' => $ip]);
        return $st->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $st = $this->db->prepare('SELECT ' . self::CAMPOS . ' FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id');
        $st->execute([':id' => $id]);
        return $st->fetch() ?: null;
    }

    public function buscarPorEmail(string $email): ?array
    {
        $st = $this->db->prepare('SELECT ' . self::CAMPOS . ' FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE lower(u.email) = lower(:email) LIMIT 1');
        $st->execute([':email' => trim($email)]);
        return $st->fetch() ?: null;
    }

    /**
     * Valida usuario + contraseña. Regresa el usuario (sin password) o null.
     */
    public function verificarCredenciales(string $username, string $password): ?array
    {
        $st = $this->db->prepare('SELECT id, password FROM usuarios WHERE username = :u COLLATE NOCASE LIMIT 1');
        $st->execute([':u' => trim($username)]);
        $fila = $st->fetch();
        if (!$fila) {
            password_verify($password, '$2y$10$usesomesillystringforsaltxxxxxxxxxxxxxxxxxxxxxxxxxx'); // mismo tiempo de respuesta
            return null;
        }

        $guardado = (string) $fila['password'];
        $esHash = password_get_info($guardado)['algo'] !== null && password_get_info($guardado)['algo'] !== 0;

        if ($esHash) {
            if (!password_verify($password, $guardado)) {
                return null;
            }
            if (password_needs_rehash($guardado, PASSWORD_DEFAULT)) {
                $this->cambiarPassword((int) $fila['id'], $password);
            }
        } else {
            // Contraseña vieja en texto plano: se compara y se migra a hash
            if (!hash_equals($guardado, $password)) {
                return null;
            }
            $this->cambiarPassword((int) $fila['id'], $password);
        }
        return $this->buscarPorId((int) $fila['id']);
    }

    /** Lista para la tabla de "Usuarios" del administrador */
    public function obtenerTodos(): array
    {
        return $this->db->query('SELECT ' . self::CAMPOS . ' FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.id')->fetchAll();
    }

    public function obtenerRoles(): array
    {
        return $this->db->query('SELECT id, nombre FROM roles ORDER BY id')->fetchAll();
    }

    /** ¿Ya existe ese username (o esa IP) en otro usuario? */
    public function existe(string $campo, string $valor, int $excluirId = 0): bool
    {
        if (!in_array($campo, ['username', 'ip', 'email'], true) || $valor === '') {
            return false;
        }
        $st = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE lower($campo) = lower(:v) AND id <> :id");
        $st->execute([':v' => $valor, ':id' => $excluirId]);
        return (int) $st->fetchColumn() > 0;
    }

    public function crear(array $d): int
    {
        $st = $this->db->prepare(
            'INSERT INTO usuarios (nombre, username, password, email, ip, rol_id, nivel_inicial, editable)
             VALUES (:nombre, :username, :password, :email, :ip, :rol_id, :nivel, 1)'
        );
        $st->execute([
            ':nombre' => $d['nombre'],
            ':username' => $d['username'],
            ':password' => password_hash($d['password'], PASSWORD_DEFAULT),
            ':email' => $d['email'],
            ':ip' => $d['ip'],
            ':rol_id' => $d['rol_id'],
            ':nivel' => $d['nivel_inicial'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Actualiza; si $d['password'] viene vacío se conserva la contraseña actual */
    public function actualizar(int $id, array $d): void
    {
        $st = $this->db->prepare(
            'UPDATE usuarios SET nombre = :nombre, username = :username, email = :email, ip = :ip,
                    rol_id = :rol_id, nivel_inicial = :nivel WHERE id = :id'
        );
        $st->execute([
            ':nombre' => $d['nombre'],
            ':username' => $d['username'],
            ':email' => $d['email'],
            ':ip' => $d['ip'],
            ':rol_id' => $d['rol_id'],
            ':nivel' => $d['nivel_inicial'],
            ':id' => $id,
        ]);
        if (!empty($d['password'])) {
            $this->cambiarPassword($id, $d['password']);
        }
    }

    public function cambiarPassword(int $id, string $password): void
    {
        $st = $this->db->prepare('UPDATE usuarios SET password = :p WHERE id = :id');
        $st->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
    }

    /** Borra solo si es editable (el admin principal tiene editable = 0) */
    public function borrar(int $id): bool
    {
        $st = $this->db->prepare('DELETE FROM usuarios WHERE id = :id AND editable = 1');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
