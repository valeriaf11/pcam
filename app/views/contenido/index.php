<?php
/**
 * ============================================================================
 * contenido/index.php  -  EXPLORADOR DE ARCHIVOS (pantalla principal)
 * ============================================================================
 * Estructura de la pantalla:
 *   [ header institucional ]
 *   [ barra de herramientas: Inicio | Carpetas | Iconos | Detalles | Subir nivel |
 *     ruta actual | Subir archivos* | Nueva carpeta* | Descargar | Usuarios** |
 *     Configuración** | Salir ]
 *   [ árbol de carpetas (jsTree) ] [ contenido de la carpeta (iconos o tabla) ]
 *   [ pie: usuario, rol, forma de acceso ]
 *      *  solo Administrador y Usuario        ** solo Administrador
 *
 * Variables (desde ContenidoController::index): $config, $nombre, $rol,
 * $metodo, $permisos, $csrf
 *
 * Los permisos se aplican DOS veces: aquí (no se dibujan los botones) y en
 * cada controlador (aunque alguien llame la URL a mano, se rechaza).
 *
 * CORRECCIONES respecto a views/usuarios/contenido.php:
 *  - $carpetas_json no existía (el árbol ahora se pide por AJAX).
 *  - css/contenido.css, jquery_plugins/jsTree e images/32x32/* no existían.
 *  - DataTables estaba fuera de /public (el navegador no podía cargarlo).
 *  - Se cargaba DataTables dos veces (local + CDN de otra versión).
 */

// ---------------------------------------------------------------------------
// ICONOS DE LA BARRA. Tus PNG de public/img/inicio llegaron DAÑADOS dentro del
// zip (se guardaron como texto y ya no son imágenes). Se dejaron íconos SVG de
// EJEMPLO con el mismo nombre. Cuando recuperes tus originales, cópialos a
// public/img/inicio/ y cambia aquí ".svg" por ".png".
// ---------------------------------------------------------------------------
$ext = '.svg';
$iconos = [
    'inicio'      => 'img/inicio/inicio' . $ext,        // ir a la carpeta raíz
    'expandir'    => 'img/inicio/expandir' . $ext,      // mostrar/ocultar árbol
    'iconos'      => 'img/inicio/VerIcono' . $ext,      // vista de iconos
    'detalles'    => 'img/inicio/verdetalles' . $ext,   // vista de detalles (tabla)
    'subirnivel'  => 'img/inicio/subirnivel' . $ext,    // carpeta padre
    'descargar'   => 'img/inicio/BajarArchivos' . $ext, // descargar seleccionados (ZIP)
    'subir'       => 'img/inicio/SubirArchivos' . $ext, // subir archivos
    'nueva'       => 'img/inicio/folder_add' . $ext,    // nueva carpeta
    'usuarios'    => 'img/inicio/usuarios' . $ext,      // administrar usuarios
    'config'      => 'img/inicio/confi' . $ext,         // configuración general
    'logout'      => 'img/inicio/logout' . $ext,        // salir
];
$nombresRol = [1 => 'Administrador', 2 => 'Usuario', 3 => 'Invitado'];
$nombresMetodo = ['ip' => 'Acceso automático por IP', 'credenciales' => 'Usuario y contraseña', 'invitado' => 'Invitado'];

if (!isset($config)) {
    $config = (object) [
        'canvas_tipo_fondo' => '0',
        'imagen_fondo' => '',
        'canvas_tipo_fondo_opacity' => 1,
        'canvas_tipo_fondo_background_color' => '#ffffff',
        'titulo_pagina' => 'PCAM',
    ];
}

// Fondo del área de contenido (canvas del config.xml)
$fondoEstilo = '';
if (($config->canvas_tipo_fondo ?? '0') === '1') {
    $fondoEstilo = "background-image:url('" . asset($config->imagen_fondo ?? '') . "');opacity:" . (float) ($config->canvas_tipo_fondo_opacity ?? 1) . ';';
} elseif (($config->canvas_tipo_fondo ?? '0') === '2') {
    $fondoEstilo = 'background:' . e($config->canvas_tipo_fondo_background_color ?? '#ffffff') . ';opacity:' . (float) ($config->canvas_tipo_fondo_opacity ?? 1) . ';';
}
?>
<!DOCTYPE html>
<html lang="es">
<?php vista('layouts/head', [
    'tituloPagina' => $config->titulo_pagina ?? 'PCAM',
    'estilos' => ['plugins/jsTree/themes/default/style.min.css', 'plugins/DataTables/datatables.min.css', 'css/contenido.css'],
    'conBootstrap' => true,
]); ?>
<body class="pagina-contenido">

<?php vista('layouts/header'); ?>
<?php vista('layouts/cargador'); ?>

<main class="explorador">

    <!-- ================= BARRA DE HERRAMIENTAS ================= -->
    <nav class="barra-herramientas">
        <div class="grupo">
            <button type="button" class="btn-herr" id="btn_home_file" title="Inicio"><img src="<?= asset($iconos['inicio']) ?>" alt=""><span>Inicio</span></button>
            <button type="button" class="btn-herr" id="btn_folders_show" title="Mostrar / ocultar carpetas"><img src="<?= asset($iconos['expandir']) ?>" alt=""><span>Carpetas</span></button>
            <button type="button" class="btn-herr activo" id="btn_view_icons" title="Ver iconos"><img src="<?= asset($iconos['iconos']) ?>" alt=""><span>Iconos</span></button>
            <button type="button" class="btn-herr" id="btn_view_details" title="Ver detalles"><img src="<?= asset($iconos['detalles']) ?>" alt=""><span>Detalles</span></button>
            <button type="button" class="btn-herr" id="btn_up_level" title="Subir nivel"><img src="<?= asset($iconos['subirnivel']) ?>" alt=""><span>Subir nivel</span></button>
        </div>

        <!-- Ruta de la carpeta actual (solo lectura) -->
        <input type="text" id="barra_ruta" class="barra-ruta" value="/" readonly aria-label="Ruta actual">

        <div class="grupo">
            <?php if ($permisos['subir']): ?>
                <button type="button" class="btn-herr" id="btn_upload_file" title="Subir archivos (también puedes arrastrarlos)"><img src="<?= asset($iconos['subir']) ?>" alt=""><span>Subir</span></button>
                <input type="file" id="input_subir_archivos" multiple hidden>
            <?php endif; ?>
            <?php if ($permisos['administrar']): ?>
                <button type="button" class="btn-herr" id="btn_folder_add_bar" title="Nueva carpeta"><img src="<?= asset($iconos['nueva']) ?>" alt=""><span>Nueva</span></button>
            <?php endif; ?>
            <?php if ($permisos['descargar']): ?>
                <button type="button" class="btn-herr" id="btn_download_file" title="Descargar archivo seleccionado"><img src="<?= asset($iconos['descargar']) ?>" alt=""><span>Descargar</span></button>
            <?php endif; ?>
            <?php if ($permisos['usuarios']): ?>
                <button type="button" class="btn-herr" id="btn_config_list_users" title="Usuarios"><img src="<?= asset($iconos['usuarios']) ?>" alt=""><span>Usuarios</span></button>
            <?php endif; ?>
            <?php if ($permisos['configuracion']): ?>
                <button type="button" class="btn-herr" id="btn_config" title="Configuración general"><img src="<?= asset($iconos['config']) ?>" alt=""><span>Config.</span></button>
            <?php endif; ?>
            <a class="btn-herr" href="<?= e(url('auth', 'logout')) ?>" title="Salir"><img src="<?= asset($iconos['logout']) ?>" alt=""><span>Salir</span></a>
        </div>
    </nav>

    <!-- ================= ÁREA PRINCIPAL ================= -->
    <section class="area-trabajo">
        <!-- Árbol de carpetas (se llena con contenido/carpetas) -->
        <aside class="panel-arbol" id="panel_arbol">
            <div id="jstree"></div>
        </aside>

        <!-- Contenido de la carpeta seleccionada -->
        <div class="panel-contenido" id="panel_contenido">
            <div class="fondo-canvas" style="<?= $fondoEstilo ?>"></div>
            <?php if ($permisos['subir']): ?>
                <div class="zona-soltar" id="zona_soltar">Suelta aquí los archivos para subirlos a esta carpeta</div>
            <?php endif; ?>
            <div id="contenedor_items" class="contenedor-items"></div>
        </div>
    </section>

    <!-- ================= PIE ================= -->
    <footer class="pie-explorador">
        <span><b><?= e($nombre) ?></b> &middot; <?= e($nombresRol[$rol] ?? '') ?></span>
        <span><?= e($nombresMetodo[$metodo] ?? '') ?></span>
        <span id="info_seleccion">0 seleccionados</span>
    </footer>
</main>

<?php if ($permisos['administrar']): ?>
<!-- ================= MENÚ CONTEXTUAL (clic derecho) ================= -->
<ul id="menu_contenedor" class="menu-contextual" role="menu">
    <li data-accion="nueva_carpeta"><img src="<?= asset('img/inicio/folder_add.svg') ?>" alt="">Nueva carpeta</li>
    <li data-accion="renombrar" class="req-item"><img src="<?= asset('img/inicio/rename.svg') ?>" alt="">Renombrar</li>
    <li data-accion="borrar" class="req-item"><img src="<?= asset('img/inicio/delete.svg') ?>" alt="">Borrar</li>
</ul>

<!-- Modal: nombre de carpeta / renombrar -->
<div class="modal fade" id="modalNombre" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form_nombre">
            <div class="modal-header modal-pcam"><h5 class="modal-title" id="modalNombreTitulo">Nombre</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="text" class="form-control" id="tb_nombre_item" maxlength="200" required>
                <small class="text-muted">No uses los caracteres \ / : * ? " &lt; &gt; |</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-pcam">Aceptar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($permisos['configuracion']): ?>
<!-- ================= MODAL: CONFIGURACIÓN GENERAL ================= -->
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="form_config">
            <div class="modal-header modal-pcam"><h5 class="modal-title">Configuración General</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <h6 class="seccion">Títulos del encabezado</h6>
                <?php foreach (['titulo_pagina' => 'Título de la página', 'titulo1' => 'Título 1', 'titulo2' => 'Título 2', 'titulo3' => 'Título 3'] as $k => $txt): ?>
                    <div class="row g-2 mb-2 align-items-center">
                        <label class="col-sm-3 col-form-label" for="cfg_<?= $k ?>"><?= $txt ?></label>
                        <div class="col-sm-7"><input class="form-control" id="cfg_<?= $k ?>" name="<?= $k ?>" maxlength="150"></div>
                        <div class="col-sm-2"><input type="color" class="form-control form-control-color w-100" name="<?= $k ?>_color" title="Color"></div>
                    </div>
                <?php endforeach; ?>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="cfg_sombras" name="sombras_texto"><label class="form-check-label" for="cfg_sombras">Sombra en los títulos</label></div>

                <h6 class="seccion">Fondo del área de archivos</h6>
                <div class="row g-2 mb-3">
                    <div class="col-sm-4"><label class="form-label">Tipo</label>
                        <select class="form-select" name="canvas_tipo_fondo"><option value="0">Ninguno</option><option value="1">Imagen</option><option value="2">Color</option></select></div>
                    <div class="col-sm-4"><label class="form-label">Opacidad</label>
                        <select class="form-select" name="canvas_tipo_fondo_opacity"><?php for ($i = 1; $i <= 10; $i++): ?><option value="<?= $i / 10 ?>"><?= $i * 10 ?>%</option><?php endfor; ?></select></div>
                    <div class="col-sm-4"><label class="form-label">Color</label>
                        <input type="color" class="form-control form-control-color w-100" name="canvas_tipo_fondo_background_color"></div>
                </div>

                <h6 class="seccion">Acceso</h6>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="cfg_privado" name="contenedor_privado"><label class="form-check-label" for="cfg_privado">Contenedor privado (oculta "Entrar como invitado")</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="cfg_inv_desc" name="invitado_descargar"><label class="form-check-label" for="cfg_inv_desc">El invitado puede descargar</label></div>
                <div class="mb-3"><label class="form-label">Carpeta que ve el invitado</label><select class="form-select sel-carpetas" name="nivel_invitado"></select></div>

                <h6 class="seccion">Archivo ZIP de descarga</h6>
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-sm-7"><input class="form-control" name="archivo_zip_nombre" maxlength="80" placeholder="ContenedorArchivos"></div>
                    <div class="col-sm-5"><div class="form-check"><input class="form-check-input" type="checkbox" id="cfg_zip_fecha" name="archivo_zip_agregar_fecha"><label class="form-check-label" for="cfg_zip_fecha">Agregar fecha al nombre</label></div></div>
                </div>

                <h6 class="seccion">Servidor de correo (recuperar acceso)</h6>
                <div class="row g-2">
                    <div class="col-sm-5"><input class="form-control" name="servidor_smtp_ip" placeholder="IP / servidor"></div>
                    <div class="col-sm-2"><input class="form-control" name="servidor_smtp_port" type="number" min="1" max="65535" placeholder="25"></div>
                    <div class="col-sm-5"><input class="form-control" name="servidor_smtp_remitente" type="email" placeholder="noreply@cfe.mx"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-pcam">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($permisos['usuarios']): ?>
<!-- ================= MODAL: LISTA DE USUARIOS ================= -->
<div class="modal fade" id="configUserListModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header modal-pcam"><h5 class="modal-title">Usuarios admitidos (<span id="num_lista_usuarios">0</span>)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Los usuarios con <b>IP</b> registrada entran automáticamente desde ese equipo, sin escribir contraseña.</p>
                <button type="button" class="btn btn-pcam btn-sm mb-2" id="btn_agregar_usuario">+ Agregar usuario</button>
                <div class="table-responsive">
                    <table id="config_table_users" class="table table-sm table-striped w-100">
                        <thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Correo</th><th>IP</th><th>Rol</th><th>Nivel inicial</th><th>Acciones</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL: ALTA / EDICIÓN DE USUARIO ================= -->
<div class="modal fade" id="configUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="form_usuario" autocomplete="off">
            <div class="modal-header modal-pcam"><h5 class="modal-title" id="configUserModalLabel">Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="mb-2"><label class="form-label">Nombre completo</label><input class="form-control" name="nombre" maxlength="100" required></div>
                <div class="mb-2"><label class="form-label">Usuario</label><input class="form-control" name="username" maxlength="40" required pattern="[A-Za-z0-9._\-]{3,40}"></div>
                <div class="mb-2"><label class="form-label">Contraseña <small class="text-muted" id="ayuda_password">(vacío = no cambiar)</small></label><input class="form-control" name="password" type="password" maxlength="100" autocomplete="new-password"></div>
                <div class="mb-2"><label class="form-label">Correo</label><input class="form-control" name="email" type="email" maxlength="120"></div>
                <div class="mb-2"><label class="form-label">IP del equipo (acceso automático)</label><input class="form-control" name="ip" maxlength="45" placeholder="Ej. 10.26.5.120 (vacío = siempre pide login)"></div>
                <div class="row g-2">
                    <div class="col-5"><label class="form-label">Rol</label><select class="form-select" name="rol_id"><option value="1">Administrador</option><option value="2">Usuario</option><option value="3">Invitado</option></select></div>
                    <div class="col-7"><label class="form-label">Nivel inicial (carpeta)</label><select class="form-select sel-carpetas" name="nivel_inicial"></select></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-pcam">Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Aviso flotante (toast) -->
<div class="aviso-flotante" id="aviso_flotante" role="alert"></div>

<!-- Datos para JavaScript: rutas del sistema, permisos y token CSRF -->

<script>
    window.PCAM_CONFIG = <?= json_encode([
        'csrf' => $csrf,
        'rol' => $rol,
        'permisos' => $permisos,

        'urls' => [
            'carpetas' => url('contenido', 'carpetas'),
            'listar' => url('contenido', 'listar'),
            'ver' => url('contenido', 'ver'),

            'crearCarpeta' => url('archivos', 'crearCarpeta'),
            'renombrar' => url('archivos', 'renombrar'),
            'borrar' => url('archivos', 'borrar'),

            'descargar' => url('descargas', 'descargar'),

            'subir' => url('subidas', 'subir'),

            'usuariosListar' => url('usuarios', 'listar'),
            'usuariosGuardar' => url('usuarios', 'guardar'),
            'usuariosBorrar' => url('usuarios', 'borrar'),
            'usuariosCarpetas' => url('usuarios', 'carpetas'),

            'configObtener' => url('configuracion', 'obtener'),
            'configGuardar' => url('configuracion', 'guardar'),

            'login' => url('auth', 'login'),

            'iconosTipos' => asset('img/tipos/'),
            'dtIdioma' => asset('plugins/DataTables/datatable_es-ES.json'),
        ],

    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>

<script src="<?= asset('plugins/jquery/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= asset('plugins/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('plugins/jsTree/jstree.min.js') ?>"></script>
<script src="<?= asset('plugins/DataTables/datatables.min.js') ?>"></script>
<script src="<?= asset('js/extensiones.js?v=2') ?>"></script>
<script src="<?= asset('js/contenido.js?v=3') ?>"></script>

</body>
</html>