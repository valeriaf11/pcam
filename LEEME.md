# PCAM - Contenedor de Archivos (PHP, MVC)

Explorador web de archivos con acceso automático por IP, inicio de sesión con usuario/contraseña y modo invitado.

## Instalación (XAMPP / Apache)

1. Copia la carpeta `pcam` dentro de `htdocs` (queda `htdocs/pcam`).
2. En `php.ini` activa las extensiones: `pdo_sqlite`, `sqlite3`, `zip`, `fileinfo` (y `openssl` si el correo usa TLS). Reinicia Apache.
3. Activa `mod_rewrite` y `AllowOverride All` en Apache (para que funcionen los `.htaccess`).
4. Entra a `http://servidor/pcam/` (redirige a `/pcam/public/`).
5. Si instalas en otra ruta, cambia `RewriteBase` en `public/.htaccess`. La URL base se calcula sola en `public/index.php`.
6. Las carpetas `archivos/`, `basededatos/`, `logs/` y `tmp/` necesitan permisos de escritura para Apache.
7. Requiere PHP 7.4 o superior (probado con PHP 8.5).

## Usuarios de ejemplo

| Usuario | Contraseña | Rol | IP | Carpeta inicial |
|---|---|---|---|---|
| admin | Admin1234 | Administrador | 127.0.0.1 | / (todo) |
| usuario | Usuario1234 | Usuario | (sin IP) | /Operacion |

Cambia estas contraseñas en cuanto instales (menú Usuarios). Las contraseñas se guardan cifradas con `password_hash`; si importas una base anterior con contraseñas en texto plano, se convierten solas en el primer inicio de sesión correcto.

## Cómo funciona el acceso

1. Al abrir la página, `AuthController::login()` busca la IP del equipo (`Red::ipCliente()`) en la tabla `usuarios`.
2. Si la IP está registrada, el usuario entra directo sin pedir login.
3. Si no, se muestra el login: puede entrar con usuario/contraseña o como invitado.
4. Al pulsar "Salir" no se vuelve a entrar automáticamente por IP hasta cerrar el navegador (así se puede cambiar de cuenta).
5. Después de 5 intentos fallidos, el login se bloquea 5 minutos (configurable en `config.xml`, sección `seguridad`).
6. Si el servidor está detrás de un proxy, pon `confiar_proxy` en 1; si no, déjalo en 0 para que nadie falsifique su IP con la cabecera `X-Forwarded-For`.

Para dar acceso automático a un equipo: menú Usuarios, Editar, escribe la IP del equipo (se muestra en el login como "IP de este equipo").

## Roles y permisos

| Permiso | Administrador | Usuario | Invitado |
|---|---|---|---|
| Ver y navegar | Sí (todo) | Sí (desde su carpeta inicial) | Sí (solo `nivel_invitado`, por defecto /Publico) |
| Descargar / ZIP | Sí | Sí | Si `invitado_descargar` = 1 |
| Subir archivos | Sí | Sí | No |
| Crear, renombrar, borrar | Sí | Sí | No |
| Administrar usuarios | Sí | No | No |
| Configuración general | Sí | No | No |

Los permisos se validan en el servidor (`Sesion::requerir()`); el JavaScript solo oculta botones.

## Estructura MVC

```
pcam/
  public/            Único directorio visible desde el navegador
    index.php        Front controller (enrutador): index.php?controller=X&action=Y
    css/ js/ img/    Estilos, scripts e imágenes
    plugins/         jQuery, Bootstrap, jsTree, DataTables (locales, sin internet)
  app/
    controllers/     Lógica de cada pantalla/acción
    models/          Acceso a la base de datos (Database, Usuario)
    views/           HTML (auth/, contenido/, layouts/)
    helpers/         Sesion, Rutas, Respuesta, Red
  config/            config.xml + Config.php (lectura/escritura de la configuración)
  basededatos/       schema.sql y sqlite_pcam.db (se crea sola si no existe)
  archivos/          Contenedor: aquí van tus documentos (se incluyen ejemplos)
  logs/              Errores de PHP (php_errores.log)
  tmp/               ZIP temporales de descarga
  vendor/            PHPMailer (Composer)
```

### Rutas disponibles

| controller | action | Qué hace |
|---|---|---|
| auth | login, autenticar, invitado, logout | Acceso |
| recobrar | formulario, enviar | Envía una contraseña temporal por correo |
| contenido | index, carpetas, listar, ver | Explorador, árbol, lista de archivos, ver/descargar un archivo |
| archivos | crearCarpeta, renombrar, borrar | Administración de contenido |
| subidas | subir | Subida de archivos (bloquea .php, .exe, etc.) |
| descargas | generarZip, descargarZip | Descarga de varios elementos en ZIP |
| usuarios | listar, guardar, borrar, carpetas | Usuarios admitidos |
| configuracion | obtener, guardar | Configuración general |

## Errores que se corrigieron

- `CONFIG_PATH` apuntaba a `/configuracion` y se requería `Config.php` con otro nombre.
- Ruta de la base de datos incorrecta y base vacía, sin tablas.
- El enrutador solo atendía `auth`; el resto de controladores nunca se ejecutaba.
- Los nombres de acciones del JavaScript no coincidían con los de los controladores.
- La clase `UsuariosController` estaba en `UsuarioController.php` (el autoload no la encontraba).
- Rutas de vistas incorrectas y variable `$carpetas_json` indefinida.
- Hojas de estilo, jsTree e iconos referenciados que no existían; DataTables fuera de `public` y cargado dos veces.
- Inyección SQL (consultas concatenadas); ahora todas son consultas preparadas.
- Contraseñas en texto plano; ahora `password_hash`.
- Suplantación de IP con `X-Forwarded-For` (daba acceso automático a cualquiera).
- Path traversal (`../`) en rutas, visor y ZIP; ahora todo se valida contra la raíz del contenedor/usuario.
- Separadores `\\` fijos de Windows; ahora rutas portables.
- Cabecera JSON enviada en todas las páginas; enlaces que exponían la ruta del disco; XSS en nombres de archivos.
- `vendor/` expuesto dentro de `public/`.
- Dos controladores de login duplicados, uno con `admin/1234` escrito en el código.
- Sin protección CSRF en las acciones que modifican datos (se agregó token).

## Imágenes

Los PNG originales de `public/img/inicio/` venían dañados dentro del zip y no se pudieron recuperar, por eso se pusieron iconos SVG de ejemplo con los mismos nombres. Si tienes los originales, cópialos a esa carpeta y en `app/views/contenido/index.php` cambia `$ext = '.svg';` por `$ext = '.png';`.

Los logos (`gobierno_mexico.png`, `zotgm.png`, `usuariocfe.png`, `fondocfe.png`) son los originales y se configuran en `config/config.xml`.
