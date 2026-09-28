<?php

/**
 * ============================================================================
 * DownloadFilesController.php
 * Descarga directa de archivos en su formato original
 * ============================================================================
 *
 * Ejemplos:
 * reporte.pdf  -> descarga reporte.pdf
 * datos.xlsx   -> descarga datos.xlsx
 * archivo.docx -> descarga archivo.docx
 *
 * No se genera ZIP.
 */

class DownloadFilesController
{
    /**
     * Descarga un archivo directamente.
     */
    public function descargar(): void
    {
        // El usuario debe tener permiso de descarga.
        Sesion::requerir('descargar', false);

        // Datos enviados desde JavaScript mediante GET.
        $ruta = (string) ($_GET['ruta'] ?? '');
        $nombre = (string) ($_GET['archivo'] ?? '');

        // No permitir nombre vacío.
        if ($nombre === '') {
            http_response_code(400);
            exit('No se especificó ningún archivo.');
        }

        /*
         * Rutas::absoluta() obtiene la ruta real del archivo
         * dentro de la carpeta permitida para el usuario.
         *
         * Ejemplo:
         *
         * ruta = /Reportes
         * nombre = reporte.pdf
         *
         * podría obtener:
         *
         * C:/xampp/htdocs/pcam/storage/Reportes/reporte.pdf
         */
        $archivo = Rutas::absoluta($ruta, $nombre);

        // Si la ruta no es válida o el archivo no existe.
        if ($archivo === null || !is_file($archivo)) {
            http_response_code(404);
            exit('El archivo no existe o no está disponible.');
        }

        /*
         * Detectar el tipo del archivo.
         *
         * PDF  -> application/pdf
         * PNG  -> image/png
         * TXT  -> text/plain
         * etc.
         */
        $tipoMime = 'application/octet-stream';

        if (function_exists('finfo_open')) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {

                $detectado = finfo_file($finfo, $archivo);

                if ($detectado !== false) {
                    $tipoMime = $detectado;
                }

                finfo_close($finfo);
            }
        }

        // Nombre que verá el usuario al descargar.
        $nombreDescarga = basename($archivo);

        /*
         * Limpiar cualquier salida previa.
         *
         * Esto evita que warnings, espacios u otro HTML
         * dañen el archivo descargado.
         */
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Indicar el tipo de archivo.
        header('Content-Type: ' . $tipoMime);

        // Indicar tamaño.
        header('Content-Length: ' . filesize($archivo));

        // attachment obliga al navegador a descargarlo.
        header(
            'Content-Disposition: attachment; filename="' .
            str_replace('"', '', $nombreDescarga) .
            '"'
        );

        // Evitar guardar copias antiguas en caché.
        header('Cache-Control: no-store');

        // Enviar el archivo al navegador.
        readfile($archivo);

        exit;
    }
}