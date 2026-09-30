<?php
/**
 * ============================================================================
 * Respuesta.php  -  Respuestas JSON, redirecciones y funciones globales
 * ============================================================================
 * Todas las peticiones AJAX del explorador responden con el mismo formato:
 *     { "ok": true,  ...datos }            -> todo bien
 *     { "ok": false, "mensaje": "..." }    -> error (con código HTTP 4xx/5xx)
 */

class Respuesta
{
    /** Envía JSON y termina la ejecución */
    public static function json($datos, int $codigo = 200): void
    {
        // Si algún warning ya se imprimió, se limpia para no romper el JSON
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(array $datos = []): void
    {
        self::json(['ok' => true] + $datos);
    }

    public static function error(string $mensaje, int $codigo = 400): void
    {
        self::json(['ok' => false, 'mensaje' => $mensaje], $codigo);
    }

    public static function redirigir(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /** Lee el cuerpo JSON de una petición (fetch con Content-Type: application/json) */
    public static function entradaJson(): array
    {
        $crudo = file_get_contents('php://input');
        $datos = json_decode($crudo ?: '[]', true);
        if (!is_array($datos)) {
            // compatibilidad: también acepta formularios normales o el campo "datos"
            $datos = isset($_POST['datos']) ? (json_decode($_POST['datos'], true) ?: []) : $_POST;
        }
        return $datos;
    }
}

/* ============================================================================
 * FUNCIONES GLOBALES (se usan en vistas y controladores)
 * ========================================================================== */

/**
 * Arma la URL de una acción del sistema.
 *   url('auth','login')              -> /pcam/public/index.php?controller=auth&action=login
 *   url('contenido','ver',['ruta'=>'/a.pdf'])
 */
function url(string $controlador, string $accion = 'index', array $params = []): string
{
    /*
     * Alias de las páginas que queremos mostrar con URL limpia.
     *
     * Ejemplo:
     * auth + login  -> /login
     * contenido + index -> /contenido
     */
    $alias = [
        'auth@login' => 'login',
        'auth@autenticar' => 'autenticar',
        'auth@invitado' => 'invitado',
        'auth@logout' => 'logout',

        'contenido@index' => 'contenido',

        'recobrar@formulario' => 'recobrar',
    ];

    // Crear una clave para buscar el alias.
    $clave = $controlador . '@' . $accion;

    /*
     * Si la ruta tiene un alias limpio,
     * usamos /login, /contenido, /recobrar, etc.
     */
    if (isset($alias[$clave])) {

        $url = BASE_URL . '/' . $alias[$clave];

        // Si además vienen parámetros, se agregan normalmente.
        if (!empty($params)) {
            $url .= '?' . http_build_query(
                $params,
                '',
                '&',
                PHP_QUERY_RFC3986
            );
        }

        return $url;
    }

    /*
     * Las acciones internas que todavía no tienen alias
     * siguen funcionando como antes.
     *
     * Ejemplo:
     * contenido/listar
     * usuarios/guardar
     * descargas/descargar
     */
    $q = array_merge(
        [
            'controller' => $controlador,
            'action' => $accion
        ],
        $params
    );

    return BASE_URL . '/index.php?' .
        http_build_query(
            $q,
            '',
            '&',
            PHP_QUERY_RFC3986
        );
}

/** URL de un recurso estático dentro de /public (css, js, img) */
function asset(string $ruta): string
{
    return BASE_URL . '/' . ltrim($ruta, '/');
}

/** Escapa texto para imprimirlo en HTML (evita XSS) */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Carga una vista pasándole variables:  vista('auth/login', ['error' => '...']) */
function vista(string $__vista, array $__variables = []): void
{
    // Los parámetros llevan "__" para que no choquen con las variables de la vista
    extract($__variables, EXTR_SKIP);
    require VIEW_PATH . '/' . $__vista . '.php';
}
