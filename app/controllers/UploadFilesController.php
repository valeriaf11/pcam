<?php
/**
 * ============================================================================
 * UploadFilesController.php  -  Subir archivos (arrastrar y soltar o botón)
 * ============================================================================
 * Solo roles 1 y 2.  Ruta: subidas/subir  (POST multipart/form-data)
 *   campos:  ruta_destino = "/Manuales"   archivos[] = (uno o varios archivos)
 *   encabezado: X-CSRF-Token
 *
 * - Si ya existe un archivo con el mismo nombre, se agrega la fecha/hora al
 *   nombre (como hacía la versión original) para no sobrescribir.
 * - Se bloquean extensiones ejecutables (php, phtml, exe, bat...).
 *
 * CORRECCIÓN: el JS llamaba "?controller=uploadFiles" sin action y el
 * controlador no estaba ruteado; además no validaba errores de subida.
 */

class UploadFilesController
{
    /** Extensiones prohibidas */
    private const BLOQUEADAS = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'cgi', 'pl', 'asp', 'aspx', 'jsp', 'exe', 'bat', 'cmd', 'com', 'msi', 'vbs', 'ps1', 'sh', 'htaccess'];

    public static function esExtensionBloqueada(string $nombre): bool
    {
        // Revisa TODAS las extensiones: "virus.php.pdf" también se bloquea
        $partes = explode('.', strtolower($nombre));
        array_shift($partes);
        return (bool) array_intersect($partes, self::BLOQUEADAS);
    }

    public function subir(): void
    {
        Sesion::requerir('subir');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Respuesta::error('Método no permitido.', 405);
        }
        // Si el archivo supera post_max_size, PHP deja $_POST vacío
        if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Respuesta::error('Los archivos exceden el tamaño máximo permitido por el servidor (' . ini_get('post_max_size') . ').', 413);
        }
        Sesion::validarCsrf();

        $destino = Rutas::absoluta((string) ($_POST['ruta_destino'] ?? ''));
        if ($destino === null || !is_dir($destino)) {
            Respuesta::error('La carpeta destino no existe.', 404);
        }
        if (empty($_FILES['archivos'])) {
            Respuesta::error('No se recibieron archivos.');
        }

        $f = $_FILES['archivos'];
        $total = is_array($f['name']) ? count($f['name']) : 1;
        $subidos = [];
        $errores = [];

        for ($i = 0; $i < $total; $i++) {
            $nombre = is_array($f['name']) ? $f['name'][$i] : $f['name'];
            $tmp = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
            $err = is_array($f['error']) ? $f['error'][$i] : $f['error'];
            $nombre = basename(str_replace('\\', '/', (string) $nombre));

            if ($err !== UPLOAD_ERR_OK) {
                $errores[] = "$nombre: " . $this->mensajeError((int) $err);
                continue;
            }
            if (!Rutas::nombreValido($nombre)) {
                $errores[] = "$nombre: nombre no válido.";
                continue;
            }
            if (self::esExtensionBloqueada($nombre)) {
                $errores[] = "$nombre: tipo de archivo no permitido.";
                continue;
            }

            $final = $destino . '/' . $nombre;
            if (file_exists($final)) {
                // nombre_(2026-09-25_16-35-10).ext
                $info = pathinfo($nombre);
                $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
                $nombre = $info['filename'] . '_(' . date('Y-m-d_H-i-s') . ')' . $ext;
                $final = $destino . '/' . $nombre;
            }

            if (@move_uploaded_file($tmp, $final)) {
                $subidos[] = $nombre;
            } else {
                $errores[] = "$nombre: no se pudo guardar (revisa permisos).";
            }
        }

        Respuesta::json([
            'ok' => count($subidos) > 0,
            'subidos' => $subidos,
            'errores' => $errores,
            'mensaje' => count($subidos) . ' archivo(s) subido(s).' . ($errores ? ' Con errores: ' . implode(' | ', $errores) : ''),
        ], $subidos ? 200 : 400);
    }

    private function mensajeError(int $codigo): string
    {
        switch ($codigo) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'excede el tamaño máximo (' . ini_get('upload_max_filesize') . ').';
            case UPLOAD_ERR_PARTIAL:
                return 'se subió incompleto.';
            case UPLOAD_ERR_NO_FILE:
                return 'no se envió archivo.';
            default:
                return 'error del servidor (' . $codigo . ').';
        }
    }
}
