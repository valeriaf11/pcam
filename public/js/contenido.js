/* =============================================================================
   contenido.js  -  Lógica del EXPLORADOR DE ARCHIVOS
   =============================================================================
   Requiere: jQuery, Bootstrap 5, jsTree, DataTables, extensiones.js
   Recibe desde PHP (contenido/index.php) el objeto window.PCAM_CONFIG:
       csrf      token de seguridad que se manda en cada petición
       rol       1 Administrador | 2 Usuario | 3 Invitado
       permisos  {ver, descargar, subir, administrar, usuarios, configuracion}
       urls      direcciones de cada acción del servidor
   IMPORTANTE: ocultar botones aquí es solo "visual"; el servidor vuelve a
   revisar los permisos en cada acción.

   FUNCIONAMIENTO GENERAL
   1. Al cargar se pide el árbol de carpetas (contenido/carpetas) y se dibuja
      con jsTree; luego se lista la carpeta raíz (contenido/listar).
   2. Clic en una carpeta del árbol, doble clic en un ícono o clic en el nombre
      (vista detalles) -> se entra a esa carpeta.
   3. Doble clic en un archivo -> se abre en otra pestaña (contenido/ver).
   4. Se seleccionan archivos (clic, Ctrl+clic o casilla) y "Descargar" genera
      un ZIP (descargas/generarZip) y lo descarga (descargas/descargarZip).
   5. Admin/Usuario: arrastrar archivos o botón "Subir" (subidas/subir),
      clic derecho = menú para crear carpeta, renombrar y borrar (archivos/*).
   6. Admin: botón Usuarios (alta/edición/baja con IP de acceso automático)
      y botón Configuración (títulos, colores, fondo, invitado, ZIP, correo).

   CORRECCIONES respecto a la versión anterior:
   - Las acciones que se llamaban no existían en los controladores
     (getRutasNiveles, generarZipFile, downloadZipFile, folder_add...).
   - Los enlaces usaban la ruta del disco del servidor (C:\...), no abrían.
   - Los nombres de archivo se insertaban en el HTML sin escapar (XSS).
   - Se usaban inputs ocultos (hd_ruta_actual, hd_vista_contenido...) como
     variables; ahora el estado vive en el objeto "estado".
   ============================================================================= */
(function ($) {
    'use strict';

    var CFG = window.PCAM_CONFIG;
    var URL = CFG.urls;
    var PERM = CFG.permisos;

    /* ---------------------------------------------------------------------
     * ESTADO de la pantalla
     * ------------------------------------------------------------------- */
    var estado = {
        ruta: '',              // carpeta actual, relativa a la raíz del usuario ('' = raíz)
        vista: 'iconos',       // 'iconos' | 'detalles'
        items: [],             // lo que regresó contenido/listar
        seleccion: {},         // { nombre: true } archivos/carpetas seleccionados
        itemContexto: null,    // elemento sobre el que se hizo clic derecho
        accionNombre: null,    // 'crear' | 'renombrar' (modal de nombre)
        tablaDetalles: null,   // instancia DataTable de la vista detalles
        tablaUsuarios: null,   // instancia DataTable de usuarios
        carpetasCargadas: false
    };

    /* ---------------------------------------------------------------------
     * UTILIDADES
     * ------------------------------------------------------------------- */

    /** Escapa texto para meterlo en HTML (evita XSS con nombres raros) */
    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /** Pantalla de "Procesando..." */
    function cargando(mostrar, texto) {
        $('#wait_texto').text(texto || 'Procesando...');
        $('#wait').toggleClass('visible', !!mostrar);
    }

    /** Aviso flotante en la esquina */
    var temporizadorAviso = null;
    function aviso(mensaje, esError) {
        var $a = $('#aviso_flotante');
        $a.text(mensaje).toggleClass('error', !!esError).addClass('visible');
        clearTimeout(temporizadorAviso);
        temporizadorAviso = setTimeout(function () { $a.removeClass('visible'); }, esError ? 6000 : 3500);
    }

    /**
     * Llama al servidor. Regresa una Promesa con el JSON.
     *   api(URL.listar, {ruta: '/Manuales'})   -> POST JSON
     *   api(URL.usuariosListar)                -> GET
     *   api(URL.subir, formData)               -> POST multipart
     */
    function api(url, datos) {
        var opciones = {
            method: datos === undefined ? 'GET' : 'POST',
            headers: {
                'X-CSRF-Token': CFG.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        };
        if (datos instanceof FormData) {
            opciones.body = datos;
        } else if (datos !== undefined) {
            opciones.headers['Content-Type'] = 'application/json';
            opciones.body = JSON.stringify(datos);
        }
        return fetch(url, opciones).then(function (resp) {
            if (resp.status === 401) {               // sesión vencida -> al login
                window.location.href = URL.login;
                throw new Error('Tu sesión terminó.');
            }
            return resp.json().catch(function () {
                throw new Error('Respuesta inválida del servidor (' + resp.status + ').');
            }).then(function (json) {
                if (!json.ok) {
                    throw new Error(json.mensaje || 'Ocurrió un error.');
                }
                return json;
            });
        });
    }

    function iconoDe(item) {
        return URL.iconosTipos + (item.tipo === 'carpeta' ? 'folder' : tipoPorExtension(item.ext)) + '.svg';
    }

    function unirRuta(base, nombre) {
        return (base || '') + '/' + nombre;
    }

    function rutaPadre(ruta) {
        var i = ruta.lastIndexOf('/');
        return i <= 0 ? '' : ruta.substring(0, i);
    }

    function formatoTamano(kb) {
        if (kb == null) { return ''; }
        if (kb < 1024) { return kb.toFixed(1) + ' KB'; }
        return (kb / 1024).toFixed(2) + ' MB';
    }

    /* ---------------------------------------------------------------------
     * ÁRBOL DE CARPETAS (jsTree)
     * ------------------------------------------------------------------- */
    function cargarArbol() {
        return api(URL.carpetas).then(function (r) {
            var $arbol = $('#jstree');
            if ($arbol.jstree(true)) {
                // Ya existe: solo se reemplazan los datos
                $arbol.jstree(true).settings.core.data = r.arbol;
                $arbol.jstree(true).refresh(false, true);
                return;
            }
            $arbol.jstree({
                core: {
                    data: r.arbol,
                    multiple: false,
                    themes: { dots: true, icons: true },
                    strings: { 'Loading ...': 'Cargando...' }
                }
            }).on('select_node.jstree', function (e, data) {
                // Clic en una carpeta del árbol -> mostrar su contenido
                var ruta = (data.node.data && data.node.data.ruta) || '';
                if (ruta !== estado.ruta) {
                    navegar(ruta, false);
                }
            }).on('refresh.jstree', function () {
                marcarEnArbol(estado.ruta);
            });
        }).catch(function (err) { aviso(err.message, true); });
    }

    /** Selecciona en el árbol la carpeta actual (sin volver a disparar la carga) */
    function marcarEnArbol(ruta) {
        var arbol = $('#jstree').jstree(true);
        if (!arbol) { return; }
        var nodos = arbol.get_json('#', { flat: true });
        for (var i = 0; i < nodos.length; i++) {
            if (((nodos[i].data && nodos[i].data.ruta) || '') === ruta) {
                arbol.deselect_all(true);
                arbol.select_node(nodos[i].id, true);   // true = sin evento
                arbol._open_to(nodos[i].id);
                var el = document.getElementById(nodos[i].id);
                if (el) { el.scrollIntoView({ block: 'nearest' }); }
                return;
            }
        }
    }

    /* ---------------------------------------------------------------------
     * LISTADO DE LA CARPETA
     * ------------------------------------------------------------------- */
    function navegar(ruta, actualizarArbol) {
        return api(URL.listar, { ruta: ruta }).then(function (r) {
            estado.ruta = r.ruta;
            estado.items = r.items;
            estado.seleccion = {};
            $('#barra_ruta').val(estado.ruta || '/');
            dibujar();
            if (actualizarArbol !== false) { marcarEnArbol(estado.ruta); }
        }).catch(function (err) { aviso(err.message, true); });
    }

    function recargar() {
        return navegar(estado.ruta);
    }

    function dibujar() {
        if (estado.tablaDetalles) {
            estado.tablaDetalles.destroy();
            estado.tablaDetalles = null;
        }
        var $c = $('#contenedor_items').empty();

        if (!estado.items.length) {
            $c.html('<div class="vacio">Esta carpeta está vacía'
                + (PERM.subir ? '.<br>Arrastra archivos aquí o usa el botón "Subir".' : '.') + '</div>');
            actualizarInfoSeleccion();
            return;
        }
        if (estado.vista === 'iconos') { dibujarIconos($c); } else { dibujarDetalles($c); }
        actualizarInfoSeleccion();
    }

    /** Vista de ICONOS (cuadrícula) */
    function dibujarIconos($c) {
        var html = '<div class="grid-iconos">';
        estado.items.forEach(function (it, i) {
            html += '<div class="item-icono" data-idx="' + i + '" title="' + esc(it.nombre) + '">'
                + (PERM.descargar ? '<input type="checkbox" class="form-check-input check" aria-label="Seleccionar">' : '')
                + '<img src="' + iconoDe(it) + '" alt="">'
                + '<span class="nombre">' + esc(it.nombre) + '</span></div>';
        });
        $c.html(html + '</div>');
    }

    /** Vista de DETALLES (tabla con DataTables) */
    function dibujarDetalles($c) {
        var html = '<table class="table table-sm table-hover tabla-detalles w-100"><thead><tr>'
            + '<th style="width:30px">' + (PERM.descargar ? '<input type="checkbox" class="form-check-input" id="check_todos" aria-label="Todos">' : '') + '</th>'
            + '<th>Nombre</th><th>Tipo</th><th>Tamaño</th><th>Creado</th><th>Modificado</th></tr></thead><tbody>';
        estado.items.forEach(function (it, i) {
            html += '<tr data-idx="' + i + '">'
                + '<td>' + (PERM.descargar ? '<input type="checkbox" class="form-check-input check" aria-label="Seleccionar">' : '') + '</td>'
                + '<td><img src="' + iconoDe(it) + '" alt=""><a class="enlace-item">' + esc(it.nombre) + '</a></td>'
                + '<td>' + (it.tipo === 'carpeta' ? 'Carpeta' : esc((it.ext || '').toUpperCase())) + '</td>'
                + '<td data-order="' + (it.fsize || 0) + '">' + formatoTamano(it.fsize) + '</td>'
                + '<td>' + esc(it.fecha_cre) + '</td><td>' + esc(it.fecha_mod) + '</td></tr>';
        });
        $c.html(html + '</tbody></table>');

        estado.tablaDetalles = new DataTable($c.find('table')[0], {
            order: [],                 // respeta el orden del servidor (carpetas primero)
            pageLength: 50,
            lengthMenu: [25, 50, 100, 250],
            columnDefs: [{ orderable: false, targets: 0 }],
            language: { url: URL.dtIdioma }
        });
    }

    /** Abre una carpeta o archivo */
    function abrir(it) {
        if (!it) { return; }
        if (it.tipo === 'carpeta') {
            navegar(it.ruta);
        } else {
            window.open(it.url, '_blank', 'noopener');
        }
    }

    /* ---------------------------------------------------------------------
     * SELECCIÓN
     * ------------------------------------------------------------------- */
    function itemDe(el) {
        var idx = $(el).closest('[data-idx]').data('idx');
        return idx === undefined ? null : estado.items[idx];
    }

    function alternarSeleccion(it, forzar) {
        if (!it) { return; }
        var nuevo = forzar === undefined ? !estado.seleccion[it.nombre] : forzar;
        if (nuevo) { estado.seleccion[it.nombre] = true; } else { delete estado.seleccion[it.nombre]; }
        pintarSeleccion();
    }

    function limpiarSeleccion() {
        estado.seleccion = {};
        pintarSeleccion();
    }

    function pintarSeleccion() {
        $('#contenedor_items [data-idx]').each(function () {
            var it = estado.items[$(this).data('idx')];
            var sel = !!(it && estado.seleccion[it.nombre]);
            $(this).toggleClass('seleccionado', sel).find('.check').prop('checked', sel);
        });
        actualizarInfoSeleccion();
    }

    function actualizarInfoSeleccion() {
        var n = Object.keys(estado.seleccion).length;
        $('#info_seleccion').text(n + ' seleccionado' + (n === 1 ? '' : 's') + ' · ' + estado.items.length + ' elemento' + (estado.items.length === 1 ? '' : 's'));
    }

    /* ---------------------------------------------------------------------
     * DESCARGA EN ZIP
     * ------------------------------------------------------------------- */
    function descargar() {
        var archivos = Object.keys(estado.seleccion);
        if (!archivos.length) {
            aviso('Selecciona uno o más archivos o carpetas para descargar.', true);
            return;
        }
        cargando(true, 'Generando archivo ZIP...');
        api(URL.generarZip, { ruta: estado.ruta, archivos: archivos }).then(function (r) {
            // Descarga directa (el navegador guarda el archivo)
            window.location.href = URL.descargarZip + '&token=' + encodeURIComponent(r.token);
            aviso('Descargando ' + r.nombre + ' (' + r.total + ' archivo(s)).');
        }).catch(function (err) { aviso(err.message, true); })
            .finally(function () { cargando(false); });
    }

    /* ---------------------------------------------------------------------
     * SUBIR ARCHIVOS (solo Admin / Usuario)
     * ------------------------------------------------------------------- */
    function subir(archivos) {
        if (!PERM.subir || !archivos || !archivos.length) { return; }
        var fd = new FormData();
        fd.append('ruta_destino', estado.ruta);
        for (var i = 0; i < archivos.length; i++) {
            fd.append('archivos[]', archivos[i]);
        }
        cargando(true, 'Subiendo ' + archivos.length + ' archivo(s)...');
        api(URL.subir, fd).then(function (r) {
            aviso(r.mensaje, r.errores && r.errores.length > 0);
            recargar();
        }).catch(function (err) { aviso(err.message, true); recargar(); })
            .finally(function () { cargando(false); $('#input_subir_archivos').val(''); });
    }

    function activarArrastrarSoltar() {
        var $panel = $('#panel_contenido');
        var $zona = $('#zona_soltar');
        var contador = 0; // dragenter/dragleave se disparan en cada hijo

        function sonArchivos(e) {
            var tipos = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.types;
            return tipos && Array.prototype.indexOf.call(tipos, 'Files') !== -1;
        }
        $panel.on('dragenter', function (e) {
            if (!sonArchivos(e)) { return; }
            e.preventDefault(); contador++; $zona.addClass('visible');
        }).on('dragover', function (e) {
            if (sonArchivos(e)) { e.preventDefault(); }
        }).on('dragleave', function (e) {
            if (!sonArchivos(e)) { return; }
            contador--; if (contador <= 0) { contador = 0; $zona.removeClass('visible'); }
        }).on('drop', function (e) {
            if (!sonArchivos(e)) { return; }
            e.preventDefault(); contador = 0; $zona.removeClass('visible');
            subir(e.originalEvent.dataTransfer.files);
        });
    }

    /* ---------------------------------------------------------------------
     * MENÚ CONTEXTUAL + CREAR / RENOMBRAR / BORRAR (solo Admin / Usuario)
     * ------------------------------------------------------------------- */
    function mostrarMenu(x, y, it) {
        estado.itemContexto = it;
        var $m = $('#menu_contenedor');
        $m.find('.req-item').toggleClass('deshabilitado', !it);
        $m.css({ display: 'block', left: 0, top: 0 });
        // Que no se salga de la pantalla
        var w = $m.outerWidth(), h = $m.outerHeight();
        $m.css({ left: Math.min(x, window.innerWidth - w - 5), top: Math.min(y, window.innerHeight - h - 5) });
    }

    function ocultarMenu() {
        $('#menu_contenedor').hide();
    }

    function abrirModalNombre(accion, valor) {
        estado.accionNombre = accion;
        $('#modalNombreTitulo').text(accion === 'crear' ? 'Nueva carpeta' : 'Renombrar');
        $('#tb_nombre_item').val(valor);
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalNombre'));
        modal.show();
        setTimeout(function () {
            var input = document.getElementById('tb_nombre_item');
            input.focus();
            // Selecciona el nombre sin la extensión (como Windows)
            var punto = valor.lastIndexOf('.');
            input.setSelectionRange(0, (accion === 'renombrar' && punto > 0) ? punto : valor.length);
        }, 350);
    }

    function guardarNombre(e) {
        e.preventDefault();
        var nombre = $.trim($('#tb_nombre_item').val());
        if (!nombre) { return; }
        var peticion;
        if (estado.accionNombre === 'crear') {
            peticion = api(URL.crearCarpeta, { ruta: estado.ruta, nombre: nombre });
        } else {
            var it = estado.itemContexto;
            if (!it || it.nombre === nombre) { bootstrap.Modal.getInstance(document.getElementById('modalNombre')).hide(); return; }
            peticion = api(URL.renombrar, { ruta: estado.ruta, nombre: it.nombre, nuevo_nombre: nombre });
        }
        peticion.then(function (r) {
            bootstrap.Modal.getInstance(document.getElementById('modalNombre')).hide();
            aviso(r.mensaje);
            return recargar().then(cargarArbol);
        }).catch(function (err) { aviso(err.message, true); });
    }

    function borrar(it) {
        if (!it) { return; }
        var texto = it.tipo === 'carpeta'
            ? '¿Borrar la carpeta "' + it.nombre + '"? (debe estar vacía)'
            : '¿Borrar el archivo "' + it.nombre + '"? Esta acción no se puede deshacer.';
        if (!window.confirm(texto)) { return; }
        api(URL.borrar, { ruta: estado.ruta, nombre: it.nombre }).then(function (r) {
            aviso(r.mensaje);
            return recargar().then(function () { if (it.tipo === 'carpeta') { return cargarArbol(); } });
        }).catch(function (err) { aviso(err.message, true); });
    }

    /* ---------------------------------------------------------------------
     * CARPETAS PARA LOS SELECT (nivel inicial / carpeta del invitado)
     * ------------------------------------------------------------------- */
    function cargarSelectCarpetas() {
        if (estado.carpetasCargadas) { return Promise.resolve(); }
        return api(URL.usuariosCarpetas).then(function (r) {
            var html = r.carpetas.map(function (c) {
                return '<option value="' + esc(c) + '">' + esc(c === '' ? '/ (todo el contenedor)' : c) + '</option>';
            }).join('');
            $('.sel-carpetas').html(html);
            estado.carpetasCargadas = true;
        });
    }

    /* ---------------------------------------------------------------------
     * USUARIOS (solo Administrador)
     * ------------------------------------------------------------------- */
    var listaUsuarios = [];

    function cargarUsuarios() {
        return api(URL.usuariosListar).then(function (r) {
            listaUsuarios = r.usuarios;
            $('#num_lista_usuarios').text(r.usuarios.length);
            if (estado.tablaUsuarios) { estado.tablaUsuarios.destroy(); }
            var html = r.usuarios.map(function (u) {
                return '<tr><td>' + u.id + '</td><td>' + esc(u.nombre) + '</td><td>' + esc(u.username) + '</td>'
                    + '<td>' + esc(u.email) + '</td><td>' + esc(u.ip || '—') + '</td><td>' + esc(u.rol) + '</td>'
                    + '<td>' + esc(u.nivel_inicial || '/') + '</td><td class="text-nowrap">'
                    + '<button class="btn btn-sm btn-outline-success btn-editar-usuario" data-id="' + u.id + '">Editar</button> '
                    + (String(u.editable) === '1' && u.id !== r.mi_id
                        ? '<button class="btn btn-sm btn-outline-danger btn-borrar-usuario" data-id="' + u.id + '">Borrar</button>' : '')
                    + '</td></tr>';
            }).join('');
            $('#config_table_users tbody').html(html);
            estado.tablaUsuarios = new DataTable('#config_table_users', {
                pageLength: 10, order: [[0, 'asc']], columnDefs: [{ orderable: false, targets: 7 }],
                language: { url: URL.dtIdioma }
            });
        }).catch(function (err) { aviso(err.message, true); });
    }

    function abrirFormUsuario(u) {
        cargarSelectCarpetas().then(function () {
            var f = document.getElementById('form_usuario');
            f.reset();
            f.id.value = u ? u.id : '';
            f.nombre.value = u ? u.nombre : '';
            f.username.value = u ? u.username : '';
            f.email.value = u ? (u.email || '') : '';
            f.ip.value = u ? (u.ip || '') : '';
            f.rol_id.value = u ? u.rol_id : 2;
            f.nivel_inicial.value = u ? (u.nivel_inicial || '') : '';
            f.password.required = !u;
            $('#ayuda_password').text(u ? '(vacío = no cambiar)' : '(obligatoria)');
            $('#configUserModalLabel').text(u ? 'Editar usuario' : 'Nuevo usuario');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('configUserModal')).show();
        }).catch(function (err) { aviso(err.message, true); });
    }

    function guardarUsuario(e) {
        e.preventDefault();
        var f = e.target;
        var datos = {
            id: f.id.value ? parseInt(f.id.value, 10) : 0,
            nombre: f.nombre.value, username: f.username.value, password: f.password.value,
            email: f.email.value, ip: f.ip.value, rol_id: parseInt(f.rol_id.value, 10),
            nivel_inicial: f.nivel_inicial.value
        };
        api(URL.usuariosGuardar, datos).then(function (r) {
            bootstrap.Modal.getInstance(document.getElementById('configUserModal')).hide();
            aviso(r.mensaje);
            cargarUsuarios();
        }).catch(function (err) { aviso(err.message, true); });
    }

    function borrarUsuario(id) {
        var u = listaUsuarios.filter(function (x) { return x.id === id; })[0];
        if (!u || !window.confirm('¿Borrar al usuario "' + u.nombre + '"?')) { return; }
        api(URL.usuariosBorrar, { id: id }).then(function (r) {
            aviso(r.mensaje);
            cargarUsuarios();
        }).catch(function (err) { aviso(err.message, true); });
    }

    /* ---------------------------------------------------------------------
     * CONFIGURACIÓN GENERAL (solo Administrador)
     * ------------------------------------------------------------------- */
    function abrirConfig() {
        cargando(true);
        Promise.all([api(URL.configObtener), cargarSelectCarpetas()]).then(function (res) {
            var c = res[0].config;
            var f = document.getElementById('form_config');
            Object.keys(c).forEach(function (k) {
                var campo = f.elements[k];
                if (!campo) { return; }
                if (campo.type === 'checkbox') { campo.checked = c[k] === '1'; } else { campo.value = c[k]; }
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('configModal')).show();
        }).catch(function (err) { aviso(err.message, true); })
            .finally(function () { cargando(false); });
    }

    function guardarConfig(e) {
        e.preventDefault();
        var datos = {};
        Array.prototype.forEach.call(e.target.elements, function (campo) {
            if (!campo.name) { return; }
            datos[campo.name] = campo.type === 'checkbox' ? (campo.checked ? '1' : '0') : campo.value;
        });
        api(URL.configGuardar, datos).then(function (r) {
            aviso(r.mensaje);
            setTimeout(function () { window.location.reload(); }, 900);
        }).catch(function (err) { aviso(err.message, true); });
    }

    /* ---------------------------------------------------------------------
     * EVENTOS
     * ------------------------------------------------------------------- */
    $(function () {
        // --- Barra de herramientas ---
        $('#btn_home_file').on('click', function () { navegar(''); });
        $('#btn_up_level').on('click', function () { if (estado.ruta !== '') { navegar(rutaPadre(estado.ruta)); } });
        $('#btn_folders_show').on('click', function () { $('#panel_arbol').toggleClass('oculto'); $(this).toggleClass('activo'); });
        $('#btn_view_icons').on('click', function () {
            estado.vista = 'iconos'; $(this).addClass('activo'); $('#btn_view_details').removeClass('activo'); dibujar(); pintarSeleccion();
        });
        $('#btn_view_details').on('click', function () {
            estado.vista = 'detalles'; $(this).addClass('activo'); $('#btn_view_icons').removeClass('activo'); dibujar(); pintarSeleccion();
        });
        $('#btn_download_file').on('click', descargar);
        $('#btn_upload_file').on('click', function () { $('#input_subir_archivos').trigger('click'); });
        $('#input_subir_archivos').on('change', function () { subir(this.files); });
        $('#btn_folder_add_bar').on('click', function () { abrirModalNombre('crear', 'Nueva carpeta'); });

        // --- Vista iconos: clic = seleccionar, doble clic = abrir ---
        $('#contenedor_items').on('click', '.item-icono', function (e) {
            var it = itemDe(this);
            if ($(e.target).hasClass('check')) { alternarSeleccion(it, e.target.checked); return; }
            if (!PERM.descargar) { return; }
            if (e.ctrlKey || e.metaKey) { alternarSeleccion(it); return; }
            var yaSolo = estado.seleccion[it.nombre] && Object.keys(estado.seleccion).length === 1;
            estado.seleccion = {};
            if (!yaSolo) { estado.seleccion[it.nombre] = true; }
            pintarSeleccion();
        }).on('dblclick', '.item-icono', function () {
            abrir(itemDe(this));
        });

        // --- Vista detalles: casillas y enlace del nombre ---
        $('#contenedor_items').on('change', 'tr .check', function () {
            alternarSeleccion(itemDe(this), this.checked);
        }).on('click', '.enlace-item', function (e) {
            e.preventDefault(); abrir(itemDe(this));
        }).on('change', '#check_todos', function () {
            var marcar = this.checked;
            estado.items.forEach(function (it) { if (marcar) { estado.seleccion[it.nombre] = true; } });
            if (!marcar) { estado.seleccion = {}; }
            pintarSeleccion();
        });

        // Clic en área vacía = quitar selección
        $('#panel_contenido').on('click', function (e) {
            if (!$(e.target).closest('[data-idx], .dt-container').length) { limpiarSeleccion(); }
        });

        // --- Menú contextual (solo si puede administrar) ---
        if (PERM.administrar) {
            $('#panel_contenido').on('contextmenu', function (e) {
                e.preventDefault();
                var it = itemDe(e.target);
                mostrarMenu(e.clientX, e.clientY, it);
            });
            $(document).on('click scroll', ocultarMenu);
            $(window).on('resize blur', ocultarMenu);
            $(document).on('keydown', function (e) { if (e.key === 'Escape') { ocultarMenu(); } });
            $('#menu_contenedor').on('click', 'li', function (e) {
                e.stopPropagation();
                var accion = $(this).data('accion');
                var it = estado.itemContexto;
                ocultarMenu();
                if (accion === 'nueva_carpeta') { abrirModalNombre('crear', 'Nueva carpeta'); }
                if (accion === 'renombrar' && it) { abrirModalNombre('renombrar', it.nombre); }
                if (accion === 'borrar' && it) { borrar(it); }
            });
            $('#form_nombre').on('submit', guardarNombre);
            if (PERM.subir) { activarArrastrarSoltar(); }
        }

        // --- Usuarios ---
        if (PERM.usuarios) {
            $('#btn_config_list_users').on('click', function () {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('configUserListModal')).show();
                cargarUsuarios();
            });
            $('#btn_agregar_usuario').on('click', function () { abrirFormUsuario(null); });
            $('#config_table_users').on('click', '.btn-editar-usuario', function () {
                var id = $(this).data('id');
                abrirFormUsuario(listaUsuarios.filter(function (x) { return x.id === id; })[0]);
            }).on('click', '.btn-borrar-usuario', function () {
                borrarUsuario($(this).data('id'));
            });
            $('#form_usuario').on('submit', guardarUsuario);
        }

        // --- Configuración ---
        if (PERM.configuracion) {
            $('#btn_config').on('click', abrirConfig);
            $('#form_config').on('submit', guardarConfig);
        }

        // --- Arranque ---
        cargarArbol().then(function () { return navegar(''); });
    });

    // Para depurar desde la consola del navegador:  PCAM.estado
    window.PCAM = { estado: estado, recargar: recargar, cargando: cargando };
})(jQuery);
