<?php
/**
 * ============================================================================
 * UsuarioController.php  -  Administración de usuarios admitidos (solo Admin)
 * ============================================================================
 * Rutas (JSON):
 *   usuarios/listar    GET   -> lista de usuarios + roles
 *   usuarios/guardar   POST  {id?, nombre, username, password, email, ip, rol_id, nivel_inicial}
 *                            sin id = nuevo, con id = actualizar
 *   usuarios/borrar    POST  {id}
 *   usuarios/carpetas  GET   -> carpetas del contenedor para el campo "Nivel inicial"
 *
 * El campo IP es el que permite el ACCESO AUTOMÁTICO: si un equipo con esa IP
 * abre la página, entra directamente con este usuario, sin login.
 *
 * CORRECCIONES:
 *  - El archivo se llamaba UsuarioController.php y la clase UsuariosController.
 *  - Las acciones del JS (obtener/nuevo/actualizar/borrar) no coincidían.
 *  - Sin validaciones: IP duplicada, username duplicado, rol inexistente...
 */

class UsuarioController
{
    /** @var Usuario */
    private $modelo;

    public function __construct()
    {
        Sesion::requerir('usuarios');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Sesion::validarCsrf();
        }
        $this->modelo = new Usuario();
    }

    public function listar(): void
    {
        Respuesta::ok([
            'usuarios' => $this->modelo->obtenerTodos(),
            'roles' => $this->modelo->obtenerRoles(),
            'mi_id' => (int) $_SESSION['usuario_id'],
        ]);
    }

    public function guardar(): void
    {
        $d = Respuesta::entradaJson();
        $id = (int) ($d['id'] ?? 0);

        $datos = [
            'nombre' => trim(strip_tags((string) ($d['nombre'] ?? ''))),
            'username' => trim((string) ($d['username'] ?? '')),
            'password' => (string) ($d['password'] ?? ''),
            'email' => trim((string) ($d['email'] ?? '')),
            'ip' => Red::normalizar((string) ($d['ip'] ?? '')),
            'rol_id' => (int) ($d['rol_id'] ?? 2),
            'nivel_inicial' => Rutas::normalizar((string) ($d['nivel_inicial'] ?? '')),
        ];

        // ---- Validaciones ------------------------------------------------
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 100) {
            Respuesta::error('Escribe el nombre (máx. 100 caracteres).');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $datos['username'])) {
            Respuesta::error('El usuario debe tener de 3 a 40 caracteres: letras, números, punto, guion o guion bajo.');
        }
        if ($this->modelo->existe('username', $datos['username'], $id)) {
            Respuesta::error('Ese nombre de usuario ya existe.');
        }
        if ($id === 0 && $datos['password'] === '') {
            Respuesta::error('Escribe una contraseña para el usuario nuevo.');
        }
        if ($datos['password'] !== '' && strlen($datos['password']) < 6) {
            Respuesta::error('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($datos['email'] !== '' && !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
            Respuesta::error('El correo no es válido.');
        }
        if ($datos['ip'] !== '' && !filter_var($datos['ip'], FILTER_VALIDATE_IP)) {
            Respuesta::error('La IP no es válida (ejemplo: 10.26.5.120).');
        }
        if ($this->modelo->existe('ip', $datos['ip'], $id)) {
            Respuesta::error('Esa IP ya está asignada a otro usuario.');
        }
        if (!in_array($datos['rol_id'], [1, 2, 3], true)) {
            Respuesta::error('Rol no válido.');
        }
        $carpeta = Rutas::absoluta($datos['nivel_inicial'], null, Rutas::raizContenedor());
        if ($carpeta === null || !is_dir($carpeta)) {
            Respuesta::error('La carpeta del nivel inicial no existe.');
        }
        // El admin no puede quitarse a sí mismo el rol de administrador
        if ($id === (int) $_SESSION['usuario_id'] && $datos['rol_id'] !== 1) {
            Respuesta::error('No puedes quitarte a ti mismo el rol de Administrador.');
        }

        if ($id === 0) {
            $id = $this->modelo->crear($datos);
            Respuesta::ok(['mensaje' => 'Usuario creado.', 'id' => $id]);
        }

        if (!$this->modelo->buscarPorId($id)) {
            Respuesta::error('El usuario no existe.', 404);
        }
        $this->modelo->actualizar($id, $datos);

        // Si se editó a sí mismo, refrescar datos de la sesión
        if ($id === (int) $_SESSION['usuario_id']) {
            $_SESSION['nombre_usuario'] = $datos['nombre'];
            $_SESSION['nivel_inicial'] = $datos['nivel_inicial'];
        }
        Respuesta::ok(['mensaje' => 'Usuario actualizado.']);
    }

    public function borrar(): void
    {
        $d = Respuesta::entradaJson();
        $id = (int) ($d['id'] ?? 0);
        if ($id === (int) $_SESSION['usuario_id']) {
            Respuesta::error('No puedes borrar tu propio usuario.');
        }
        if (!$this->modelo->borrar($id)) {
            Respuesta::error('No se pudo borrar (el usuario no existe o está protegido).');
        }
        Respuesta::ok(['mensaje' => 'Usuario eliminado.']);
    }

    /** Lista de todas las carpetas del contenedor (para elegir "nivel inicial") */
    public function carpetas(): void
    {
        $raiz = Rutas::raizContenedor();
        $lista = [''];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $it->setMaxDepth(5);
        foreach ($it as $item) {
            if ($item->isDir() && $item->getFilename()[0] !== '.') {
                $lista[] = Rutas::relativa($item->getPathname(), $raiz);
            }
        }
        sort($lista, SORT_NATURAL | SORT_FLAG_CASE);
        Respuesta::ok(['carpetas' => $lista]);
    }
}
