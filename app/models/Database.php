<?php
/**
 * ============================================================================
 * Database.php  -  Conexión PDO a SQLite (una sola conexión por petición)
 * ============================================================================
 * - La ruta de la BD se toma de config.xml (<bd_sqlite><ruta_bd>).
 *   ANTES apuntaba a "basedatos/contenedorarchivos.db" que no existía; el
 *   archivo real es basededatos/sqlite_pcam.db (y estaba vacío, 0 bytes).
 * - Si las tablas no existen se crean solas con basededatos/schema.sql
 *   (roles + usuarios + un administrador de ejemplo).
 */

class Database
{
    /** @var PDO|null */
    private static $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('Falta la extensión pdo_sqlite de PHP (actívala en php.ini: extension=pdo_sqlite).');
        }

        $ruta = Config::load()->base_datos_sqlite;
        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        self::$pdo = new PDO('sqlite:' . $ruta, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // arreglos asociativos
        ]);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo->exec('PRAGMA busy_timeout = 5000'); // espera si otro proceso está escribiendo

        self::crearTablasSiNoExisten(self::$pdo);
        return self::$pdo;
    }

    /** Ejecuta basededatos/schema.sql si todavía no existe la tabla usuarios */
    private static function crearTablasSiNoExisten(PDO $pdo): void
    {
        $existe = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='usuarios'")->fetchColumn();
        if ($existe) {
            return;
        }
        $sql = file_get_contents(ROOT_PATH . '/basededatos/schema.sql');
        $pdo->exec($sql);
    }
}
