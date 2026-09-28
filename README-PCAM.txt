PCAM - PROYECTO CORREGIDO
========================

1. Copia la carpeta "pcam" dentro de C:\xampp\htdocs\
2. Asegúrate de tener habilitadas las extensiones PHP: pdo_sqlite y sqlite3.
3. Abre Apache en XAMPP.
4. Entra a: http://localhost/pcam/public/

Usuario inicial para pruebas:
  Usuario: admin
  Contraseña: CFE2026!

IMPORTANTE: cambia esta contraseña antes de usar el sistema en producción.

ACCESO AUTOMÁTICO POR IP
------------------------
El sistema revisa REMOTE_ADDR. Si coincide con el campo "ip" de un usuario activo,
crea la sesión sin pedir usuario ni contraseña.

Para probarlo, registra la IP real del equipo en basededatos/sqlite_pcam.db.
No se dejó 127.0.0.1 en el usuario inicial para que puedas seguir viendo el login
cuando trabajas desde localhost.

ROLES
-----
1 Administrador: ver, descargar, subir, crear carpetas, renombrar y eliminar.
2 Usuario: ver, descargar y subir dentro de su nivel_inicial.
3 Invitado: solo ver y descargar dentro de storage/Publico.

ESTRUCTURA MVC
--------------
app/models/Usuario.php        -> consultas de usuarios/SQLite
app/models/Archivo.php        -> lectura y operaciones seguras de archivos
app/controllers/AuthController.php -> login, invitado, IP y logout
app/controllers/ContenidoController.php -> listado/descarga/subida/administración
app/views/auth/login.php      -> vista del login
app/views/contenido/index.php -> vista del contenedor
app/views/layouts/header.php  -> encabezado reutilizable
public/index.php              -> Front Controller / rutas
public/css/                   -> estilos
storage/                      -> archivos que PCAM muestra

SEGURIDAD IMPLEMENTADA
----------------------
- consultas preparadas para autenticación
- password_hash/password_verify
- sesión regenerada al iniciar
- token CSRF para formularios
- control de permisos por rol
- validación de rutas para impedir salir de storage
- no se confía automáticamente en X-Forwarded-For
