<?php
/**
 * ============================================================================
 * DownloadFilesController.php  -  Descarga de archivos seleccionados en ZIP
 * ============================================================================
 * Paso 1  POST descargas/generarZip   {"ruta": "/Manuales", "archivos": ["a.pdf","Carpeta"]}
 *         -> crea el ZIP en /tmp del proyecto y regresa {"ok":true,"token":"..."}
 * Paso 2  GET  descargas/descargarZip&token=...
 *         -> envía el ZIP al navegador y lo borra del servidor.
 *
 * Nombre del ZIP: <archivo_zip><nombre> + fecha opcional (config.xml).
 * Permiso: 'descargar' (Admin, Usuario y, si está permitido, Invitado).
 *
 * CORRECCIONES:
 *  - El JS llamaba "generarZipFile"/"downloadZipFile" y el controlador tenía
 *    "generateZipFiles"/"downloadZipFiles" (nunca coincidían).
 *  - downloadZipFiles recibía el NOMBRE del zip desde el navegador: se podía
 *    descargar cualquier archivo del servidor (Path Traversal). Ahora se usa
 *    un token aleatorio guardado en la sesión.
 *  - Las carpetas seleccionadas ahora se agregan completas (recursivo).
 */

class DownloadFilesController
{
    /** Carpeta temporal para los ZIP */
    private function carpetaTmp(): string
    {
        $dir = ROOT_PATH . '/tmp';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Limpieza: borra ZIPs de más de 1 hora que se quedaron sin descargar
        foreach (glob($dir . '/pcam_*.zip') ?: [] as $viejo) {
            if (filemtime($viejo) < time() - 3600) {
                @unlink($viejo);
            }
        }
        return $dir;
    }

    public function generarZip(): void
    {
        Sesion::requerir('descargar');
        Sesion::validarCsrf();
        if (!class_exists('ZipArchive')) {
            Respuesta::error('Falta la extensión zip de PHP (actívala en php.ini: extension=zip).', 500);
        }

        $d = Respuesta::entradaJson();
        $ruta = (string) ($d['ruta'] ?? '');
        $archivos = is_array($d['archivos'] ?? null) ? $d['archivos'] : [];
        if (!$archivos) {
            Respuesta::error('No hay archivos seleccionados.');
        }

        $token = bin2hex(random_bytes(16));
        $zipRuta = $this->carpetaTmp() . '/pcam_' . $token . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            Respuesta::error('No se pudo crear el ZIP.', 500);
        }

        $agregados = 0;
        foreach ($archivos as $nombre) {
            $abs = Rutas::absoluta($ruta, (string) $nombre);
            if ($abs === null || !file_exists($abs)) {
                continue;
            }
            if (is_dir($abs)) {
                $agregados += $this->agregarCarpeta($zip, $abs, basename($abs));
            } else {
                $zip->addFile($abs, basename($abs));
                $agregados++;
            }
        }
        $zip->close();

        if ($agregados === 0) {
            @unlink($zipRuta);
            Respuesta::error('Los elementos seleccionados no existen o están vacíos.');
        }

        // Nombre final que verá el usuario
        $config = Config::load();
        $nombreZip = $config->archivo_zip_nombre
            . ($config->archivo_zip_agregar_fecha === '1' ? '_' . date('Y-m-d_H-i') : '')
            . '.' . $config->archivo_zip_ext;

        $_SESSION['zips'][$token] = ['ruta' => $zipRuta, 'nombre' => $nombreZip];
        Respuesta::ok(['token' => $token, 'nombre' => $nombreZip, 'total' => $agregados]);
    }

    /** Agrega una carpeta completa al ZIP. Regresa cuántos archivos agregó */
    private function agregarCarpeta(ZipArchive $zip, string $dir, string $prefijo): int
    {
        $n = 0;
        $zip->addEmptyDir($prefijo);
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }
            $ruta = $dir . '/' . $item;
            if (is_dir($ruta) && !is_link($ruta)) {
                $n += $this->agregarCarpeta($zip, $ruta, $prefijo . '/' . $item);
            } elseif (is_file($ruta)) {
                $zip->addFile($ruta, $prefijo . '/' . $item);
                $n++;
            }
        }
        return max($n, 1);
    }

    public function descargarZip(): void
    {
        Sesion::requerir('descargar', false);
        $token = (string) ($_GET['token'] ?? '');
        $info = $_SESSION['zips'][$token] ?? null;

        if (!$info || !is_file($info['ruta'])) {
            http_response_code(404);
            exit('El archivo ZIP ya no está disponible. Vuelve a generarlo.');
        }
        unset($_SESSION['zips'][$token]);
        session_write_close(); // libera la sesión mientras se descarga

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($info['ruta']));
        header('Content-Disposition: attachment; filename="' . $info['nombre'] . '"');
        header('Cache-Control: no-store');
        readfile($info['ruta']);
        @unlink($info['ruta']);
        exit;
    }
}
