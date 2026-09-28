<?php
/**
 * MODELO ARCHIVO
 * Centraliza el acceso al contenedor y evita salir de la carpeta permitida.
 */
class Archivo
{
    private string $storageRoot;

    public function __construct(string $storageRoot)
    {
        if (!is_dir($storageRoot)) mkdir($storageRoot, 0775, true);
        $real = realpath($storageRoot);
        if ($real === false) throw new RuntimeException('No se pudo preparar el contenedor de archivos.');
        $this->storageRoot = $real;
    }

    public function basePermitida(string $nivel): string
    {
        $nivel = trim(str_replace(['..', '\\'], ['', '/'], $nivel), '/');
        $base = $nivel === '' ? $this->storageRoot : $this->storageRoot . DIRECTORY_SEPARATOR . $nivel;
        if (!is_dir($base)) mkdir($base, 0775, true);
        $real = realpath($base);
        if ($real === false || !$this->dentroDe($real, $this->storageRoot)) {
            throw new RuntimeException('Nivel inicial no válido.');
        }
        return $real;
    }

    public function resolver(string $base, string $relative = '', bool $debeExistir = true): string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') return $base;
        if (str_contains($relative, '..')) throw new RuntimeException('Ruta no permitida.');

        $candidate = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($debeExistir) {
            $real = realpath($candidate);
            if ($real === false || !$this->dentroDe($real, $base)) throw new RuntimeException('Ruta no permitida.');
            return $real;
        }

        $parent = realpath(dirname($candidate));
        if ($parent === false || !$this->dentroDe($parent, $base)) throw new RuntimeException('Ruta no permitida.');
        return $candidate;
    }

    public function listar(string $directory): array
    {
        $items = [];
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $full = $directory . DIRECTORY_SEPARATOR . $name;
            $items[] = [
                'nombre' => $name,
                'tipo' => is_dir($full) ? 'folder' : 'file',
                'tamano' => is_file($full) ? filesize($full) : null,
                'modificado' => date('d/m/Y H:i', filemtime($full) ?: time()),
                'extension' => is_file($full) ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '',
            ];
        }
        usort($items, fn($a,$b) => [$a['tipo'] !== 'folder', strtolower($a['nombre'])] <=> [$b['tipo'] !== 'folder', strtolower($b['nombre'])]);
        return $items;
    }

    public function subir(string $directory, array $upload): void
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No se recibió un archivo válido.');
        $name = basename((string)$upload['name']);
        if ($name === '' || preg_match('/[<>:"|?*]/', $name)) throw new RuntimeException('Nombre de archivo no válido.');
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string)$upload['tmp_name'], $target)) throw new RuntimeException('No se pudo guardar el archivo.');
    }

    public function crearCarpeta(string $directory, string $name): void
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..' || preg_match('/[\\\/:*?"<>|]/', $name)) throw new RuntimeException('Nombre de carpeta no válido.');
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_exists($target) || !mkdir($target, 0775)) throw new RuntimeException('No se pudo crear la carpeta.');
    }

    public function renombrar(string $base, string $relative, string $newName): void
    {
        $source = $this->resolver($base, $relative);
        $newName = trim($newName);
        if ($newName === '' || preg_match('/[\\\/:*?"<>|]/', $newName)) throw new RuntimeException('Nombre nuevo no válido.');
        $target = dirname($source) . DIRECTORY_SEPARATOR . $newName;
        if (file_exists($target) || !rename($source, $target)) throw new RuntimeException('No se pudo renombrar.');
    }

    public function eliminar(string $base, string $relative): void
    {
        $target = $this->resolver($base, $relative);
        if ($target === $base) throw new RuntimeException('No se puede eliminar la raíz.');
        if (is_dir($target)) {
            $children = array_diff(scandir($target) ?: [], ['.','..']);
            if ($children) throw new RuntimeException('La carpeta debe estar vacía antes de eliminarla.');
            if (!rmdir($target)) throw new RuntimeException('No se pudo eliminar la carpeta.');
        } elseif (!unlink($target)) {
            throw new RuntimeException('No se pudo eliminar el archivo.');
        }
    }

    private function dentroDe(string $path, string $base): bool
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $base) || rtrim($path, DIRECTORY_SEPARATOR) === rtrim($base, DIRECTORY_SEPARATOR);
    }
}
