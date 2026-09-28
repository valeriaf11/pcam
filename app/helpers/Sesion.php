<?php
/**
 * ============================================================================
 * Sesion.php  -  Manejo de sesión, roles, permisos y token CSRF
 * ============================================================================
 * ROLES (tabla "roles" de la base de datos):
 *   1 = Administrador : ve todo, sube/renombra/borra archivos y carpetas,
 *                       administra usuarios y la configuración general.
 *   2 = Usuario       : ve su nivel de carpetas, descarga, sube, renombra y
 *                       borra archivos/carpetas.
 *   3 = Invitado      : SOLO ve (y opcionalmente descarga) la carpeta
 *                       configurada en <nivel_invitado> del config.xml.
 *
 * VARIABLES DE SESIÓN QUE SE USAN EN TODO EL SISTEMA:
 *   $_SESSION['LOGIN_OK']       true si hay alguien dentro
 *   $_SESSION['usuario_id']     id del usuario (0 para invitado)
 *   $_SESSION['nombre_usuario'] nombre que se muestra en pantalla
 *   $_SESSION['rol_id']         1, 2 o 3
 *   $_SESSION['nivel_inicial']  subcarpeta raíz que puede ver este usuario
 *   $_SESSION['metodo_acceso']  'ip' | 'credenciales' | 'invitado'
 *   $_SESSION['csrf']           token anti-CSRF para formularios y AJAX
 */

class Sesion
{
    public const ROL_ADMIN = 1;
    public const ROL_USUARIO = 2;
    public const ROL_INVITADO = 3;

    /** Arranca la sesión con cookies seguras (se llama una sola vez en public/index.php) */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('PCAMSESSID');
        session_set_cookie_params([
            'lifetime' => 0,          // la sesión dura mientras el navegador esté abierto
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,       // JavaScript no puede leer la cookie
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Guarda en la sesión los datos del usuario que acaba de entrar.
     * @param array  $usuario  registro de la tabla usuarios (o datos del invitado)
     * @param string $metodo   'ip', 'credenciales' o 'invitado'
     */
    public static function iniciarUsuario(array $usuario, string $metodo): void
    {
        session_regenerate_id(true); // evita "session fixation"
        $_SESSION['LOGIN_OK'] = true;
        $_SESSION['usuario_id'] = (int) ($usuario['id'] ?? 0);
        $_SESSION['nombre_usuario'] = (string) ($usuario['nombre'] ?? 'Invitado');
        $_SESSION['rol_id'] = (int) ($usuario['rol_id'] ?? self::ROL_INVITADO);
        $_SESSION['nivel_inicial'] = Rutas::normalizar((string) ($usuario['nivel_inicial'] ?? ''));
        $_SESSION['metodo_acceso'] = $metodo;
        unset($_SESSION['sin_autologin'], $_SESSION['intentos_login'], $_SESSION['bloqueo_hasta']);
    }

    /** Cierra la sesión por completo y abre una nueva "limpia" */
    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::iniciar();
        session_regenerate_id(true);
    }

    public static function estaLogueado(): bool
    {
        return !empty($_SESSION['LOGIN_OK']);
    }

    public static function rol(): int
    {
        return (int) ($_SESSION['rol_id'] ?? 0);
    }

    public static function esAdmin(): bool
    {
        return self::rol() === self::ROL_ADMIN;
    }

    /* ---------------------------------------------------------------------
     * PERMISOS: aquí se define qué puede hacer cada rol.
     * Si quieres que el invitado vea/haga más o menos cosas, cámbialo aquí.
     * ------------------------------------------------------------------- */
    public static function permisos(): array
    {
        $rol = self::rol();
        $config = Config::load();
        return [
            'ver'            => in_array($rol, [1, 2, 3], true),
            'descargar'      => in_array($rol, [1, 2], true) || ($rol === 3 && $config->invitado_descargar === '1'),
            'subir'          => in_array($rol, [1, 2], true),
            'administrar'    => in_array($rol, [1, 2], true), // crear/renombrar/borrar
            'usuarios'       => $rol === 1,
            'configuracion'  => $rol === 1,
        ];
    }

    public static function puede(string $permiso): bool
    {
        return !empty(self::permisos()[$permiso]);
    }

    /**
     * Protege una acción: si no hay sesión, o no tiene el permiso, la corta.
     * @param string|null $permiso  clave de permisos() o null = solo estar logueado
     * @param bool        $json     true para peticiones AJAX (responde JSON)
     */
    public static function requerir(?string $permiso = null, bool $json = true): void
    {
        if (!self::estaLogueado()) {
            if ($json) {
                Respuesta::error('Tu sesión terminó. Vuelve a entrar.', 401);
            }
            Respuesta::redirigir(url('auth', 'login'));
        }
        if ($permiso !== null && !self::puede($permiso)) {
            if ($json) {
                Respuesta::error('No tienes permiso para realizar esta acción.', 403);
            }
            http_response_code(403);
            exit('No tienes permiso para ver esta página.');
        }
    }

    /* ---------------------------------------------------------------------
     * CSRF: token que se manda en cada formulario / petición AJAX para
     * que otra página no pueda hacer acciones en nombre del usuario.
     * ------------------------------------------------------------------- */
    public static function csrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function validarCsrf(bool $json = true): void
    {
        $enviado = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($enviado) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $enviado)) {
            if ($json) {
                Respuesta::error('La página expiró, recárgala e intenta de nuevo.', 419);
            }
            Sesion::flash('error', 'La página expiró, intenta de nuevo.');
            Respuesta::redirigir(url('auth', 'login'));
        }
    }

    /** Mensajes de una sola lectura (se muestran una vez y se borran) */
    public static function flash(string $clave, ?string $valor = null): ?string
    {
        if ($valor !== null) {
            $_SESSION['flash'][$clave] = $valor;
            return null;
        }
        $v = $_SESSION['flash'][$clave] ?? null;
        unset($_SESSION['flash'][$clave]);
        return $v;
    }
}
