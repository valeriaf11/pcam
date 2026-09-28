<?php
/**
 * ============================================================================
 * AuthController.php  -  Entrada al sistema (IP automática, login, invitado)
 * ============================================================================
 * Rutas:
 *   ?controller=auth&action=login       Página inicial. Si la IP del equipo
 *                                        está en la tabla usuarios -> entra solo.
 *   ?controller=auth&action=autenticar  POST usuario + password.
 *   ?controller=auth&action=invitado    Entra como invitado (rol 3).
 *   ?controller=auth&action=logout      Cierra la sesión.
 *
 * CORRECCIONES:
 *  - Existían DOS controladores haciendo lo mismo (AuthController con un
 *    usuario fijo admin/1234 y LoginController con la BD). Se unificó todo
 *    aquí y ahora sí se valida contra la BD.
 *  - LoginController hacía require de "views/contenido.php" y "views/login.php"
 *    (rutas que no existen). Ahora se usan las vistas correctas.
 *  - Al cerrar sesión, el acceso por IP volvía a meter al usuario de
 *    inmediato. Ahora, tras "Salir", se muestra el login (por si quiere entrar
 *    con otra cuenta); al abrir de nuevo el navegador vuelve a entrar por IP.
 *  - Límite de intentos fallidos para evitar ataques de fuerza bruta.
 */

class AuthController
{
    /** GET: página inicial */
    public function login(): void
    {
        // 1) ¿Ya tiene sesión? -> al explorador
        if (Sesion::estaLogueado()) {
            Respuesta::redirigir(url('contenido', 'index'));
        }

        $ip = Red::ipCliente();

        // 2) Acceso AUTOMÁTICO por IP registrada (no pide login)
        if (empty($_SESSION['sin_autologin'])) {
            $usuario = (new Usuario())->buscarPorIP($ip);
            if ($usuario) {
                Sesion::iniciarUsuario($usuario, 'ip');
                Respuesta::redirigir(url('contenido', 'index'));
            }
        }

        // 3) Mostrar formulario de login
        $config = Config::load();
        vista('auth/login', [
            'error' => Sesion::flash('error'),
            'aviso' => Sesion::flash('aviso'),
            'mostrarInvitado' => $config->contenedor_privado !== '1',
            'ip' => $ip,
            'csrf' => Sesion::csrf(),
        ]);
    }

    /** POST: valida usuario y contraseña contra la BD */
    public function autenticar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Respuesta::redirigir(url('auth', 'login'));
        }
        Sesion::validarCsrf(false);
        $config = Config::load();

        // ¿Está bloqueado por demasiados intentos?
        $bloqueo = (int) ($_SESSION['bloqueo_hasta'] ?? 0);
        if ($bloqueo > time()) {
            $min = (int) ceil(($bloqueo - time()) / 60);
            Sesion::flash('error', "Demasiados intentos fallidos. Espera $min minuto(s).");
            Respuesta::redirigir(url('auth', 'login'));
        }

        $username = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            Sesion::flash('error', 'Escribe tu usuario y contraseña.');
            Respuesta::redirigir(url('auth', 'login'));
        }

        $usuario = (new Usuario())->verificarCredenciales($username, $password);

        if (!$usuario) {
            $_SESSION['intentos_login'] = (int) ($_SESSION['intentos_login'] ?? 0) + 1;
            if ($_SESSION['intentos_login'] >= $config->max_intentos_login) {
                $_SESSION['bloqueo_hasta'] = time() + $config->minutos_bloqueo * 60;
                $_SESSION['intentos_login'] = 0;
            }
            Sesion::flash('error', 'Usuario o contraseña incorrectos.');
            Respuesta::redirigir(url('auth', 'login'));
        }

        Sesion::iniciarUsuario($usuario, 'credenciales');
        Respuesta::redirigir(url('contenido', 'index'));
    }

    /** GET: entrar como invitado (solo si el contenedor NO es privado) */
    public function invitado(): void
    {
        $config = Config::load();
        if ($config->contenedor_privado === '1') {
            Sesion::flash('error', 'El acceso como invitado está deshabilitado.');
            Respuesta::redirigir(url('auth', 'login'));
        }

        Sesion::iniciarUsuario([
            'id' => 0,
            'nombre' => 'Invitado',
            'rol_id' => Sesion::ROL_INVITADO,
            'nivel_inicial' => $config->nivel_invitado, // solo ve esta carpeta
        ], 'invitado');

        Respuesta::redirigir(url('contenido', 'index'));
    }

    /** GET: cerrar sesión */
    public function logout(): void
    {
        Sesion::cerrar();
        $_SESSION['sin_autologin'] = true; // no volver a entrar por IP en esta sesión del navegador
        Sesion::flash('aviso', 'Sesión cerrada correctamente.');
        Respuesta::redirigir(url('auth', 'login'));
    }
}
