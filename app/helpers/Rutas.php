<?php
/**
 * ============================================================================
 * Rutas.php  -  Manejo SEGURO de rutas del contenedor de archivos
 * ============================================================================
 * PROBLEMA QUE CORRIGE:
 *   Antes las rutas se armaban así:  ruta_base . nivel_inicial . ruta . "\\" . nombre
 *   - Usaba "\\" (solo sirve en Windows).
 *   - Nadie revisaba ".." -> cualquiera podía pedir "..\..\config" y leer,
 *     renombrar o BORRAR archivos fuera del contenedor (Path Traversal).
 *
 * CÓMO FUNCIONA AHORA:
 *   - Del navegador SOLO llegan rutas RELATIVAS con "/" (ej. "/Manuales/2026").
 *   - normalizar() limpia la ruta y quita ".", "..", "\\" y dobles "/".
 *   - raizUsuario() = ruta_base + nivel_inicial del usuario en sesión
 *     (el usuario nunca puede subir más arriba de su raíz).
 *   - absoluta() une raíz + ruta relativa y comprueba con realpath() que
 *     el resultado siga DENTRO de la raíz.
 */

class Rutas
{
    /**
     * Limpia una ruta relativa: "\\a\\..\\b//c/" -> "/b/c"
     * Regresa "" para la raíz.
     */
    public static function normalizar(string $ruta): string
    {
        $ruta = str_replace('\\', '/', $ruta);
        $partes = [];
        foreach (explode('/', $ruta) as $p) {
            $p = trim($p);
            if ($p === '' || $p === '.' || $p === '..') {
                continue; // se descartan: no se permite subir de nivel
            }
            $partes[] = $p;
        }
        return $partes ? '/' . implode('/', $partes) : '';
    }

    /** Valida un nombre de archivo o carpeta (sin rutas, sin caracteres prohibidos) */
    public static function nombreValido(string $nombre): bool
    {
        $nombre = trim($nombre);
        if ($nombre === '' || $nombre === '.' || $nombre === '..' || mb_strlen($nombre) > 200) {
            return false;
        }
        // Caracteres no válidos en Windows + separadores + caracteres de control
        if (preg_match('/[\\\\\/:*?"<>|\x00-\x1F]/u', $nombre)) {
            return false;
        }
        // No se permiten archivos ocultos (.htaccess, .git, etc.)
        return $nombre[0] !== '.';
    }

    /** Raíz del contenedor completo (ruta_base del config.xml), creada si no existe */
    public static function raizContenedor(): string
    {
        $base = Config::load()->contenedor_ruta_base_abs;
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        $real = realpath($base);
        if ($real === false) {
            throw new RuntimeException('La carpeta del contenedor no existe: ' . $base);
        }
        return str_replace('\\', '/', $real);
    }

    /** Raíz que puede ver el usuario en sesión = ruta_base + nivel_inicial */
    public static function raizUsuario(): string
    {
        $raiz = self::raizContenedor();
        $nivel = self::normalizar((string) ($_SESSION['nivel_inicial'] ?? ''));
        $ruta = $raiz . $nivel;
        $real = realpath($ruta);
        if ($real === false || !is_dir($real)) {
            // Si el nivel del usuario ya no existe, se crea vacío para no romper la vista
            @mkdir($ruta, 0775, true);
            $real = realpath($ruta) ?: $raiz;
        }
        return str_replace('\\', '/', $real);
    }

    /**
     * Convierte una ruta relativa del usuario en absoluta y verifica que esté
     * dentro de su raíz. Si $nombre viene, lo agrega al final (validándolo).
     * @return string|null  ruta absoluta o null si es inválida/fuera de la raíz
     */
    public static function absoluta(string $rutaRelativa, ?string $nombre = null, ?string $raiz = null): ?string
    {
        $raiz = $raiz ?? self::raizUsuario();
        $ruta = $raiz . self::normalizar($rutaRelativa);
        if ($nombre !== null) {
            if (!self::nombreValido($nombre)) {
                return null;
            }
            $ruta .= '/' . trim($nombre);
        }
        return self::dentroDe($raiz, $ruta) ? $ruta : null;
    }

    /** Comprueba que $ruta (exista o no) quede dentro de $raiz */
    public static function dentroDe(string $raiz, string $ruta): bool
    {
        $raiz = rtrim(str_replace('\\', '/', $raiz), '/');
        // Si existe se resuelve con realpath (resuelve enlaces simbólicos);
        // si no existe se revisa la carpeta padre.
        $comprobar = file_exists($ruta) ? realpath($ruta) : realpath(dirname($ruta));
        if ($comprobar === false) {
            return false;
        }
        $comprobar = str_replace('\\', '/', $comprobar);
        $iguales = PHP_OS_FAMILY === 'Windows'
            ? (strcasecmp($comprobar, $raiz) === 0 || stripos($comprobar . '/', $raiz . '/') === 0)
            : ($comprobar === $raiz || strpos($comprobar . '/', $raiz . '/') === 0);
        return $iguales;
    }

    /** Ruta relativa (a la raíz del usuario) de una ruta absoluta */
    public static function relativa(string $absoluta, ?string $raiz = null): string
    {
        $raiz = $raiz ?? self::raizUsuario();
        $absoluta = str_replace('\\', '/', $absoluta);
        return self::normalizar(substr($absoluta, strlen($raiz)));
    }
}
