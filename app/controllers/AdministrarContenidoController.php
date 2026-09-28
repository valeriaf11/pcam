<?php
/**
 * ============================================================================
 * AdministrarContenidoController.php  -  Crear / renombrar / borrar
 * ============================================================================
 * Solo roles 1 (Administrador) y 2 (Usuario). El invitado NO puede.
 * Se usa desde el menú contextual (clic derecho) del explorador.
 *
 * Rutas (POST JSON + encabezado X-CSRF-Token):
 *   archivos/crearCarpeta  {"ruta": "/Manuales", "nombre": "Nueva carpeta"}
 *   archivos/renombrar     {"ruta": "/Manuales", "nombre": "a.pdf", "nuevo_nombre": "b.pdf"}
 *   archivos/borrar        {"ruta": "/Manuales", "nombre": "a.pdf"}
 *
 * CORRECCIONES:
 *  - header('Content-Type: application/json') estaba al inicio del archivo,
 *    afectando a cualquier página que lo incluyera.
 *  - Métodos con errores de nombre (ranameFile) y rutas con "\\".
 *  - No había validación de permisos ni de "..": se podía borrar cualquier
 *    archivo del servidor.
 */

class AdministrarContenidoController
{
    public function __construct()
    {
        Sesion::requerir('administrar');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Respuesta::error('Método no permitido.', 405);
        }
        Sesion::validarCsrf();
    }

    /** Crea una carpeta dentro de "ruta" */
    public function crearCarpeta(): void
    {
        $d = Respuesta::entradaJson();
        $nombre = trim((string) ($d['nombre'] ?? '')) ?: 'Nueva carpeta';

        $destino = Rutas::absoluta((string) ($d['ruta'] ?? ''), $nombre);
        if ($destino === null) {
            Respuesta::error('Nombre de carpeta no válido. No uses \\ / : * ? " < > |');
        }

        // Si ya existe, se agrega (2), (3)...
        $base = $destino;
        $n = 2;
        while (file_exists($destino)) {
            $destino = $base . " ($n)";
            $n++;
        }

        if (!@mkdir($destino, 0775)) {
            Respuesta::error('No se pudo crear la carpeta (revisa permisos).', 500);
        }
        Respuesta::ok(['mensaje' => 'Carpeta creada.', 'nombre' => basename($destino)]);
    }

    /** Renombra archivo o carpeta */
    public function renombrar(): void
    {
        $d = Respuesta::entradaJson();
        $ruta = (string) ($d['ruta'] ?? '');
        $origen = Rutas::absoluta($ruta, (string) ($d['nombre'] ?? ''));
        $destino = Rutas::absoluta($ruta, (string) ($d['nuevo_nombre'] ?? ''));

        if ($origen === null || !file_exists($origen)) {
            Respuesta::error('El elemento no existe.', 404);
        }
        if ($destino === null) {
            Respuesta::error('Nombre nuevo no válido. No uses \\ / : * ? " < > |');
        }
        if ($origen === Rutas::raizUsuario()) {
            Respuesta::error('No se puede renombrar la carpeta raíz.');
        }
        // En Windows "a.txt" y "A.txt" son el mismo archivo: se permite cambiar mayúsculas
        if (file_exists($destino) && strcasecmp($origen, $destino) !== 0) {
            Respuesta::error('Ya existe un elemento con ese nombre.');
        }
        if (is_file($origen) && $this->extensionBloqueada($destino)) {
            Respuesta::error('Esa extensión no está permitida.');
        }

        if (!@rename($origen, $destino)) {
            Respuesta::error('No se pudo renombrar (¿está abierto por otro programa?).', 500);
        }
        Respuesta::ok(['mensaje' => 'Renombrado correctamente.']);
    }

    /** Borra un archivo o una carpeta VACÍA (por seguridad no borra carpetas con contenido) */
    public function borrar(): void
    {
        $d = Respuesta::entradaJson();
        $objetivo = Rutas::absoluta((string) ($d['ruta'] ?? ''), (string) ($d['nombre'] ?? ''));

        if ($objetivo === null || !file_exists($objetivo)) {
            Respuesta::error('El elemento no existe.', 404);
        }
        if ($objetivo === Rutas::raizUsuario()) {
            Respuesta::error('No se puede borrar la carpeta raíz.');
        }

        if (is_dir($objetivo)) {
            $contenido = array_diff(scandir($objetivo) ?: [], ['.', '..']);
            if ($contenido) {
                Respuesta::error('La carpeta no está vacía. Borra primero su contenido.');
            }
            $ok = @rmdir($objetivo);
        } else {
            $ok = @unlink($objetivo);
        }

        if (!$ok) {
            Respuesta::error('No se pudo borrar (¿está abierto por otro programa?).', 500);
        }
        Respuesta::ok(['mensaje' => 'Eliminado correctamente.']);
    }

    /** Extensiones que no se permiten (podrían ejecutarse en el servidor) */
    private function extensionBloqueada(string $ruta): bool
    {
        return UploadFilesController::esExtensionBloqueada(basename($ruta));
    }
}
