-- ============================================================================
-- schema.sql  -  Estructura de la base de datos SQLite de PCAM
-- ============================================================================
-- Se ejecuta AUTOMÁTICAMENTE la primera vez que la app se conecta y no
-- encuentra la tabla "usuarios" (ver app/models/Database.php).
-- También se puede ejecutar a mano:
--     sqlite3 basededatos/sqlite_pcam.db < basededatos/schema.sql
-- ============================================================================

-- Roles del sistema (NO cambiar los id, el código depende de ellos)
CREATE TABLE IF NOT EXISTS roles (
    id     INTEGER PRIMARY KEY,
    nombre TEXT NOT NULL UNIQUE
);

INSERT OR IGNORE INTO roles (id, nombre) VALUES
    (1, 'Administrador'),  -- todo
    (2, 'Usuario'),        -- ver, descargar, subir y administrar archivos
    (3, 'Invitado');       -- solo ver (y descargar si se permite)

-- Usuarios admitidos
--   ip            : si la IP del equipo coincide, entra SIN login (automático)
--   password      : se guarda con password_hash() (bcrypt), nunca en texto plano
--   nivel_inicial : subcarpeta del contenedor que puede ver ("" = todo)
--   editable      : 0 = no se puede borrar desde la página (admin principal)
CREATE TABLE IF NOT EXISTS usuarios (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre        TEXT    NOT NULL,
    username      TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password      TEXT    NOT NULL,
    email         TEXT    DEFAULT '',
    ip            TEXT    DEFAULT '',
    rol_id        INTEGER NOT NULL DEFAULT 2 REFERENCES roles(id),
    nivel_inicial TEXT    DEFAULT '',
    editable      INTEGER NOT NULL DEFAULT 1,
    creado        TEXT    DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_usuarios_ip ON usuarios(ip);

-- ----------------------------------------------------------------------------
-- DATOS DE EJEMPLO  (CÁMBIALOS en cuanto entres por primera vez)
--   admin   / Admin1234   -> Administrador, entra solo desde 127.0.0.1 (el propio servidor)
--   usuario / Usuario1234 -> Usuario, solo ve la carpeta /Operacion
-- Los hashes son password_hash('Admin1234') y password_hash('Usuario1234').
-- ----------------------------------------------------------------------------
INSERT OR IGNORE INTO usuarios (id, nombre, username, password, email, ip, rol_id, nivel_inicial, editable) VALUES
    (1, 'Administrador del sistema', 'admin',
     '$2y$12$Fa8LZbJtx.v5vFG4APu29eZDHujUzc9zFft9Dt42lJMu7LmrvlL0i', 'admin@cfe.mx', '127.0.0.1', 1, '', 0),
    (2, 'Usuario de ejemplo', 'usuario',
     '$2y$12$Rq0xrkp5pJNWy2fuenbrj.my8agZ3YGSiLVcQb/nd5G3NgdEzddx2', 'usuario@cfe.mx', '', 2, '/Operacion', 1);
