<?php
/**
 * ============================================================================
 * ContenidoController.php  -  Explorador de archivos (vista principal)
 * ============================================================================
 * Rutas:
 *   contenido/index     Página del explorador (árbol + archivos).
 *   contenido/carpetas  JSON con el árbol de carpetas para jsTree.
 *   contenido/listar    JSON con los archivos/carpetas de una ruta.
 *   contenido/ver       Abre (o descarga con &descargar=1) UN archivo.
 *
 * Todas las rutas son RELATIVAS a la raíz del usuario (ruta_base + nivel_inicial),
 * así un usuario con nivel "/Operacion" nunca ve nada fuera de esa carpeta
 * y el invitado solo ve la carpeta <nivel_invitado> del config.xml.
 *
 * CORRECCIONES:
 *  - Se usaban separadores "\\" (solo Windows) y no se validaba "..".
 *  - Se mandaba al navegador la ruta completa del disco (ruta_completa) y
 *    los enlaces de los archivos apuntaban ahí (no abrían). Ahora se abren
 *    con contenido/ver, que valida permisos antes de entregar el archivo.
 *  - La vista usaba $carpetas_json sin definir; ahora el árbol se pide por AJAX.
 *  - Los iconos 'images/32x32/folder.png' no existían; ver img/tipos/.
 */

class ContenidoController
{
    /** Página principal del explorador */
    public function index(): void
    {
        Sesion::requerir(null, false);

        vista('contenido/index', [
            'config' => Config::load(),
            'nombre' => $_SESSION['nombre_usuario'] ?? '',
            'rol' => Sesion::rol(),
            'metodo' => $_SESSION['metodo_acceso'] ?? '',
            'permisos' => Sesion::permisos(),
            'csrf' => Sesion::csrf(),
        ]);
    }

    /** JSON: árbol de carpetas (formato jsTree) */
    public function carpetas(): void
    {
        Sesion::requerir('ver');
        $raiz = Rutas::raizUsuario();

        $nombreRaiz = $_SESSION['nivel_inicial'] ? basename($_SESSION['nivel_inicial']) : 'Inicio';
        $arbol = [[
            'text' => $nombreRaiz,
            'icon' => asset('img/tipos/home.svg'),
            'state' => ['opened' => true, 'selected' => true],
            'data' => ['ruta' => ''],
            'children' => $this->arbol($raiz, $raiz, 0),
        ]];
        Respuesta::ok(['arbol' => $arbol]);
    }

    /** Recorre carpetas recursivamente (máx. 15 niveles para evitar ciclos) */
    private function arbol(string $dir, string $raiz, int $profundidad): array
    {
        if ($profundidad > 15) {
            return [];
        }
        $nodos = [];
        foreach ($this->leerDirectorio($dir) as $nombre) {
            $ruta = $dir . '/' . $nombre;
            if (is_dir($ruta) && !is_link($ruta)) {
                $nodos[] = [
                    'text' => $nombre,
                    'icon' => asset('img/tipos/folder.svg'),
                    'data' => ['ruta' => Rutas::relativa($ruta, $raiz)],
                    'children' => $this->arbol($ruta, $raiz, $profundidad + 1),
                ];
            }
        }
        return $nodos;
    }

    /** JSON: contenido de una carpeta.  Entrada: {"ruta": "/Manuales"} */
    public function listar(): void
    {
        Sesion::requerir('ver');
        $entrada = Respuesta::entradaJson();
        $rutaRel = Rutas::normalizar((string) ($entrada['ruta'] ?? ($entrada['ruta_busqueda'] ?? '')));
        $dir = Rutas::absoluta($rutaRel);

        if ($dir === null || !is_dir($dir)) {
            Respuesta::error('La carpeta no existe o no tienes acceso.', 404);
        }

        $items = [];
        foreach ($this->leerDirectorio($dir) as $nombre) {
            $ruta = $dir . '/' . $nombre;
            $esDir = is_dir($ruta);
            $ext = $esDir ? '' : strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            $rel = $rutaRel . '/' . $nombre;
            $items[] = [
                'nombre' => $nombre,
                'tipo' => $esDir ? 'carpeta' : 'archivo',
                'ext' => $ext,
                'fsize' => $esDir ? null : round(filesize($ruta) / 1024, 1), // KB
                'fecha_cre' => date('Y-m-d H:i', filectime($ruta)),
                'fecha_mod' => date('Y-m-d H:i', filemtime($ruta)),
                'mime_type' => $esDir ? 'directory' : $this->mime($ruta),
                'ruta' => $rel,
                // URL para abrir el archivo en otra pestaña (solo archivos)
                'url' => $esDir ? null : url('contenido', 'ver', ['ruta' => $rel]),
            ];
        }

        // Primero carpetas, luego archivos; orden natural por nombre
        usort($items, function ($a, $b) {
            if ($a['tipo'] !== $b['tipo']) {
                return $a['tipo'] === 'carpeta' ? -1 : 1;
            }
            return strnatcasecmp($a['nombre'], $b['nombre']);
        });

        Respuesta::ok(['ruta' => $rutaRel, 'items' => $items]);
    }

    /** Entrega un archivo al navegador (inline, o descarga con &descargar=1) */
    public function ver(): void
    {
        Sesion::requerir('ver', false);
        $rel = Rutas::normalizar((string) ($_GET['ruta'] ?? ''));
        $descargar = !empty($_GET['descargar']);

        if ($descargar && !Sesion::puede('descargar')) {
            http_response_code(403);
            exit('No tienes permiso para descargar archivos.');
        }

        $archivo = $rel !== '' ? Rutas::absoluta(dirname($rel), basename($rel)) : null;
        if ($archivo === null || !is_file($archivo)) {
            http_response_code(404);
            exit('El archivo no existe o no tienes acceso.');
        }

        $mime = $this->mime($archivo);
        // Archivos que pueden ejecutar scripts en el navegador se fuerzan a descarga
        $peligroso = in_array(strtolower(pathinfo($archivo, PATHINFO_EXTENSION)), ['html', 'htm', 'svg', 'xhtml', 'js', 'php'], true);
        $disposicion = ($descargar || $peligroso) ? 'attachment' : 'inline';
        $nombre = basename($archivo);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . ($peligroso ? 'application/octet-stream' : $mime));
        header('Content-Length: ' . filesize($archivo));
        header("Content-Disposition: $disposicion; filename=\"" . str_replace('"', '', $nombre) . "\"; filename*=UTF-8''" . rawurlencode($nombre));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        readfile($archivo);
        exit;
    }

    /* ---------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------- */

    /** Lista nombres de un directorio sin ".", ".." ni archivos ocultos */
    private function leerDirectorio(string $dir): array
    {
        $lista = @scandir($dir);
        if ($lista === false) {
            return [];
        }
        $salida = array_values(array_filter($lista, function ($n) {
            return $n !== '' && $n[0] !== '.' && strcasecmp($n, 'Thumbs.db') !== 0;
        }));
        natcasesort($salida);
        return array_values($salida);
    }

    private function mime(string $archivo): string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($archivo);
            if ($m) {
                return $m;
            }
        }
        return 'application/octet-stream';
    }
}
