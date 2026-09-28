<?php
/**
 * ============================================================================
 * public/index.php  -  FRONT CONTROLLER (única puerta de entrada del sistema)
 * ============================================================================
 * PATRÓN MVC:
 *   Navegador -> public/index.php?controller=X&action=Y
 *             -> app/controllers/XController.php  (lógica / permisos)
 *             -> app/models/*.php                  (base de datos)
 *             -> app/views/*.php                   (HTML)
 *
 * CORRECCIONES:
 *  - CONFIG_PATH apuntaba a "/configuracion" (no existe) -> ahora "/config".
 *  - Solo existía la ruta "auth"; los demás controladores (contenido,
 *    descargas, subidas, usuarios, configuración...) nunca se podían llamar
 *    y salía "El controlador no existe". Ahora hay una TABLA DE RUTAS con
 *    todos los controladores y las acciones permitidas (lista blanca).
 *  - Los errores de PHP ya no se imprimen en pantalla (rompían el JSON de
 *    las peticiones AJAX); se guardan en /logs/php_errores.log.
 *
 * FLUJO DE ENTRADA:
 *   1) Se abre la página -> auth/login
 *   2) Si la IP del equipo está en la tabla usuarios -> entra directo (sin login)
 *   3) Si no, se muestra el login: usuario/contraseña  o  "Entrar como invitado"
 *   4) Ya dentro se muestra el explorador (contenido/index)
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// 1. Constantes de rutas del proyecto
// ---------------------------------------------------------------------------
define('ROOT_PATH', str_replace('\\', '/', dirname(__DIR__)));     // .../pcam
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('MODEL_PATH', APP_PATH . '/models');
define('CONTROLLER_PATH', APP_PATH . '/controllers');
define('HELPER_PATH', APP_PATH . '/helpers');
define('CONFIG_PATH', ROOT_PATH . '/config');

// URL base de /public (ej. "/pcam/public"); funciona en cualquier carpeta
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE_URL', rtrim($base, '/'));

// ---------------------------------------------------------------------------
// 2. Manejo de errores: no mostrar en pantalla, guardar en log
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(ROOT_PATH . '/logs')) {
    @mkdir(ROOT_PATH . '/logs', 0775, true);
}
ini_set('error_log', ROOT_PATH . '/logs/php_errores.log');
date_default_timezone_set('America/Mexico_City');
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// 3. Carga de clases (config, helpers, modelos y controladores)
// ---------------------------------------------------------------------------
require CONFIG_PATH . '/Config.php';
require HELPER_PATH . '/Respuesta.php';
require HELPER_PATH . '/Sesion.php';
require HELPER_PATH . '/Rutas.php';
require HELPER_PATH . '/Red.php';

// Autocarga: busca la clase en /app/models y /app/controllers
spl_autoload_register(function (string $clase): void {
    foreach ([MODEL_PATH, CONTROLLER_PATH] as $carpeta) {
        $archivo = $carpeta . '/' . $clase . '.php';
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});

// Autoload de Composer (PHPMailer). Se movió de /public/vendor a /vendor
// para que no quede expuesto en la web.
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
}

Sesion::iniciar();

// ---------------------------------------------------------------------------
// 4. TABLA DE RUTAS:  'nombre en la URL' => [Clase, [acciones permitidas]]
//    Si agregas una acción nueva a un controlador, agrégala también aquí.
// ---------------------------------------------------------------------------
$rutas = [
    'auth'          => ['AuthController', ['login', 'autenticar', 'invitado', 'logout']],
    'recobrar'      => ['RecobrarCredencialesController', ['formulario', 'enviar']],
    'contenido'     => ['ContenidoController', ['index', 'carpetas', 'listar', 'ver']],
    'archivos'      => ['AdministrarContenidoController', ['crearCarpeta', 'renombrar', 'borrar']],
    'descargas'     => ['DownloadFilesController', ['generarZip', 'descargarZip']],
    'subidas'       => ['UploadFilesController', ['subir']],
    'usuarios'      => ['UsuarioController', ['listar', 'guardar', 'borrar', 'carpetas']],
    'configuracion' => ['ConfiguracionController', ['obtener', 'guardar']],
];

// Rutas "limpias" (ver .htaccess):  /pcam/public/login, /pcam/public/contenido ...
$alias = [
    'login'      => ['auth', 'login'],
    'autenticar' => ['auth', 'autenticar'],
    'invitado'   => ['auth', 'invitado'],
    'logout'     => ['auth', 'logout'],
    'inicio'     => ['contenido', 'index'],
    'contenido'  => ['contenido', 'index'],
    'recobrar'   => ['recobrar', 'formulario'],
];

$controlador = $_GET['controller'] ?? '';
$accion = $_GET['action'] ?? '';

if ($controlador === '' && !empty($_GET['url'])) {
    $limpia = trim((string) $_GET['url'], '/');
    [$controlador, $accion] = $alias[$limpia] ?? ['', ''];
}
if ($controlador === '') {
    [$controlador, $accion] = ['auth', 'login']; // página por defecto
}

// ---------------------------------------------------------------------------
// 5. Despacho
// ---------------------------------------------------------------------------
try {
    if (!isset($rutas[$controlador])) {
        http_response_code(404);
        exit('El controlador solicitado no existe.');
    }
    [$clase, $acciones] = $rutas[$controlador];
    if ($accion === '') {
        $accion = $acciones[0];
    }
    if (!in_array($accion, $acciones, true)) {
        http_response_code(404);
        exit('La acción solicitada no existe.');
    }
    (new $clase())->$accion();
} catch (Throwable $ex) {
    error_log('[PCAM] ' . $ex->getMessage() . ' en ' . $ex->getFile() . ':' . $ex->getLine());
    $esAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
    if ($esAjax) {
        Respuesta::error('Ocurrió un error en el servidor: ' . $ex->getMessage(), 500);
    }
    http_response_code(500);
    echo '<h3 style="font-family:sans-serif;color:#9b2226">Ocurrió un error en el servidor</h3>'
        . '<p style="font-family:sans-serif">' . e($ex->getMessage()) . '</p>'
        . '<p style="font-family:sans-serif">Revisa el archivo <b>logs/php_errores.log</b>.</p>';
}
