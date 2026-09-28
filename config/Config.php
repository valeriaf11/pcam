<?php
/**
 * ============================================================================
 * Config.php  -  Lectura y guardado de config/config.xml
 * ============================================================================
 * CORRECCIONES respecto a la versión anterior:
 *  - El archivo se llamaba "config.php" pero todos lo pedían como "Config.php"
 *    (en Linux eso truena porque distingue mayúsculas). Ahora se llama Config.php.
 *  - CONFIG_PATH apuntaba a "/configuracion" pero la carpeta es "/config".
 *  - Si el XML no cargaba solo se hacía "echo" y luego fallaba todo; ahora
 *    se lanza una excepción clara.
 *  - Se resuelven las rutas relativas (ruta_base y ruta_bd) contra ROOT_PATH
 *    para que funcione igual en Windows (XAMPP) y en Linux.
 *  - guardar() valida y convierte los valores (true/false -> 1/0, colores, etc.)
 *    y escapa correctamente los caracteres especiales del XML.
 *  - Se guarda con bloqueo de archivo (LOCK_EX) para no corromper el XML.
 *
 * USO:
 *     $config = Config::load();
 *     echo $config->titulo1;
 */

class Config
{
    /** @var object|null  Caché de la configuración ya leída en esta petición */
    private static $cache = null;

    /** Ruta absoluta del XML */
    private static function rutaXml(): string
    {
        return CONFIG_PATH . '/config.xml';
    }

    /** Lee el XML y regresa un SimpleXMLElement */
    private static function leerXml(): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file(self::rutaXml());
        if ($xml === false) {
            throw new RuntimeException('No se pudo cargar el archivo de configuración: ' . self::rutaXml());
        }
        return $xml;
    }

    /**
     * Convierte una ruta relativa (a la raíz del proyecto) en absoluta.
     * Si ya es absoluta (C:/..., D:\..., /var/...) la deja igual.
     */
    public static function rutaAbsoluta(string $ruta): string
    {
        $ruta = str_replace('\\', '/', trim($ruta));
        if ($ruta === '') {
            return ROOT_PATH;
        }
        $esAbsoluta = ($ruta[0] === '/') || preg_match('#^[A-Za-z]:/#', $ruta);
        $absoluta = $esAbsoluta ? $ruta : ROOT_PATH . '/' . $ruta;
        return rtrim($absoluta, '/');
    }

    /**
     * Carga toda la configuración en un objeto plano.
     * Los nombres de propiedades se conservan igual que en la versión original
     * para no romper nada que ya los use.
     */
    public static function load(bool $recargar = false): object
    {
        if (self::$cache !== null && !$recargar) {
            return self::$cache;
        }

        $xml = self::leerXml();
        $c = (object) [];

        // ---- Sección APP (solo lectura desde la página) --------------------
        $c->app_header_color_fondo = (string) $xml->app->header->color_fondo ?: '#ffffff';
        $c->logo_izquierdo = (string) $xml->app->imagenes->logo_izquierdo ?: 'img/gobierno_mexico.png';
        $c->logo_derecho   = (string) $xml->app->imagenes->logo_derecho ?: 'img/zotgm.png';
        $c->imagen_login   = (string) $xml->app->imagenes->imagen_login ?: 'img/usuariocfe.png';
        $c->imagen_fondo   = (string) $xml->app->imagenes->imagen_fondo ?: 'img/fondocfe.png';
        $c->confiar_proxy      = (string) $xml->app->seguridad->confiar_proxy === '1';
        $c->max_intentos_login = max(1, (int) ($xml->app->seguridad->max_intentos_login ?: 5));
        $c->minutos_bloqueo    = max(1, (int) ($xml->app->seguridad->minutos_bloqueo ?: 5));

        // ---- Textos -------------------------------------------------------
        $c->titulo_pagina = (string) $xml->pagina->titulos->titulo_pagina;
        $c->titulo1 = (string) $xml->pagina->titulos->titulo1;
        $c->titulo2 = (string) $xml->pagina->titulos->titulo2;
        $c->titulo3 = (string) $xml->pagina->titulos->titulo3;

        // ---- Colores de los textos ---------------------------------------
        $c->titulo_pagina_color = (string) $xml->pagina->texto_color->titulo_pagina_color;
        $c->titulo1_color = (string) $xml->pagina->texto_color->titulo1_color;
        $c->titulo2_color = (string) $xml->pagina->texto_color->titulo2_color;
        $c->titulo3_color = (string) $xml->pagina->texto_color->titulo3_color;
        $c->sombras_texto = (string) $xml->pagina->sombras_texto;

        // ---- Fondo del área de contenido (canvas) ------------------------
        $c->canvas_tipo_fondo = (string) $xml->canvas->tipo_fondo;
        $c->canvas_tipo_fondo_opacity = (string) $xml->canvas->tipo_fondo_opacity;
        $c->canvas_tipo_fondo_background_color = (string) $xml->canvas->tipo_fondo_background_color;

        // ---- Contenedor de archivos --------------------------------------
        $c->contenedor_privado = (string) $xml->contenedor->privado;
        $c->contenedor_ruta_base = (string) $xml->contenedor->ruta_base;               // tal como está en el XML
        $c->contenedor_ruta_base_abs = self::rutaAbsoluta($c->contenedor_ruta_base);   // ruta absoluta real
        $c->nivel_invitado = (string) $xml->contenedor->nivel_invitado;
        $c->invitado_descargar = (string) $xml->contenedor->invitado_descargar;

        // ---- Base de datos -----------------------------------------------
        $c->base_datos_sqlite = self::rutaAbsoluta((string) $xml->bd_sqlite->ruta_bd);

        // ---- Archivo ZIP de descarga -------------------------------------
        $c->archivo_zip_nombre = (string) $xml->archivo_zip->nombre ?: 'ContenedorArchivos';
        $c->archivo_zip_ext = (string) $xml->archivo_zip->ext ?: 'zip';
        $c->archivo_zip_agregar_fecha = (string) $xml->archivo_zip->agregar_fecha;

        // ---- Servidor de correo (recuperar datos de acceso) --------------
        $c->servidor_smtp_ip = (string) $xml->servidor_smtp->ip;
        $c->servidor_smtp_port = (string) $xml->servidor_smtp->port;
        $c->servidor_smtp_remitente = (string) $xml->servidor_smtp->remitente;

        self::$cache = $c;
        return $c;
    }

    /* ---------------------------------------------------------------------
     * Utilidades de validación usadas por guardar()
     * ------------------------------------------------------------------- */
    private static function bool01($v): string
    {
        return ($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on') ? '1' : '0';
    }

    private static function color($v, string $defecto): string
    {
        $v = trim((string) $v);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : $defecto;
    }

    private static function texto($v, int $max = 150): string
    {
        $v = trim(strip_tags((string) $v));
        return mb_substr($v, 0, $max);
    }

    /** Asigna texto a un nodo del XML escapando &, <, > correctamente */
    private static function set(SimpleXMLElement $nodo, string $valor): void
    {
        $dom = dom_import_simplexml($nodo);
        $dom->nodeValue = '';
        $dom->appendChild($dom->ownerDocument->createTextNode($valor));
    }

    /**
     * Guarda la configuración editable desde la página.
     * $datos es un arreglo/objeto con los mismos nombres que regresa load().
     */
    public static function guardar($datos): void
    {
        $d = (object) $datos;
        $actual = self::load(true);
        $xml = self::leerXml();

        // Textos
        self::set($xml->pagina->titulos->titulo_pagina, self::texto($d->titulo_pagina ?? $actual->titulo_pagina));
        self::set($xml->pagina->titulos->titulo1, self::texto($d->titulo1 ?? $actual->titulo1));
        self::set($xml->pagina->titulos->titulo2, self::texto($d->titulo2 ?? $actual->titulo2));
        self::set($xml->pagina->titulos->titulo3, self::texto($d->titulo3 ?? $actual->titulo3));

        // Colores
        self::set($xml->pagina->texto_color->titulo_pagina_color, self::color($d->titulo_pagina_color ?? '', $actual->titulo_pagina_color));
        self::set($xml->pagina->texto_color->titulo1_color, self::color($d->titulo1_color ?? '', $actual->titulo1_color));
        self::set($xml->pagina->texto_color->titulo2_color, self::color($d->titulo2_color ?? '', $actual->titulo2_color));
        self::set($xml->pagina->texto_color->titulo3_color, self::color($d->titulo3_color ?? '', $actual->titulo3_color));
        self::set($xml->pagina->sombras_texto, self::bool01($d->sombras_texto ?? $actual->sombras_texto));

        // Canvas
        $tipo = (string) ($d->canvas_tipo_fondo ?? $actual->canvas_tipo_fondo);
        self::set($xml->canvas->tipo_fondo, in_array($tipo, ['0', '1', '2'], true) ? $tipo : '1');
        $op = (float) ($d->canvas_tipo_fondo_opacity ?? $actual->canvas_tipo_fondo_opacity);
        self::set($xml->canvas->tipo_fondo_opacity, (string) max(0.1, min(1, round($op, 1))));
        self::set($xml->canvas->tipo_fondo_background_color, self::color($d->canvas_tipo_fondo_background_color ?? '', $actual->canvas_tipo_fondo_background_color));

        // Contenedor
        self::set($xml->contenedor->privado, self::bool01($d->contenedor_privado ?? $actual->contenedor_privado));
        self::set($xml->contenedor->invitado_descargar, self::bool01($d->invitado_descargar ?? $actual->invitado_descargar));
        if (isset($d->nivel_invitado)) {
            self::set($xml->contenedor->nivel_invitado, Rutas::normalizar((string) $d->nivel_invitado));
        }

        // ZIP (el nombre no puede llevar caracteres inválidos para archivo)
        $zip = preg_replace('/[\\\\\/:*?"<>|]+/', '', self::texto($d->archivo_zip_nombre ?? $actual->archivo_zip_nombre, 80));
        self::set($xml->archivo_zip->nombre, $zip !== '' ? $zip : 'ContenedorArchivos');
        self::set($xml->archivo_zip->agregar_fecha, self::bool01($d->archivo_zip_agregar_fecha ?? $actual->archivo_zip_agregar_fecha));

        // SMTP
        self::set($xml->servidor_smtp->ip, self::texto($d->servidor_smtp_ip ?? $actual->servidor_smtp_ip, 100));
        $puerto = (int) ($d->servidor_smtp_port ?? $actual->servidor_smtp_port);
        self::set($xml->servidor_smtp->port, (string) (($puerto > 0 && $puerto < 65536) ? $puerto : 25));
        $rem = trim((string) ($d->servidor_smtp_remitente ?? $actual->servidor_smtp_remitente));
        self::set($xml->servidor_smtp->remitente, filter_var($rem, FILTER_VALIDATE_EMAIL) ? $rem : $actual->servidor_smtp_remitente);

        // Escritura segura (con bloqueo) del XML
        if (file_put_contents(self::rutaXml(), $xml->asXML(), LOCK_EX) === false) {
            throw new RuntimeException('No se pudo escribir config.xml (revisa permisos de la carpeta config).');
        }
        self::$cache = null; // limpiar caché para que la siguiente lectura tome lo nuevo
    }
}
