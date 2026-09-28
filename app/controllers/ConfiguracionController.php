<?php
/**
 * ============================================================================
 * ConfiguracionController.php  -  "Configuración General" (solo Admin)
 * ============================================================================
 * Rutas (JSON):
 *   configuracion/obtener  GET   -> valores actuales del config.xml
 *   configuracion/guardar  POST  {titulo1, titulo1_color, ..., contenedor_privado, ...}
 *
 * CORRECCIÓN: el JS mandaba true/false y se guardaba "true"/"false" en el
 * XML, pero el resto del sistema esperaba "1"/"0". Config::guardar() ya
 * convierte y valida todo.
 */

class ConfiguracionController
{
    public function __construct()
    {
        Sesion::requerir('configuracion');
    }

    public function obtener(): void
    {
        $c = Config::load(true);
        // Solo se regresan los campos editables (no rutas internas del disco)
        Respuesta::ok(['config' => [
            'titulo_pagina' => $c->titulo_pagina,
            'titulo1' => $c->titulo1,
            'titulo2' => $c->titulo2,
            'titulo3' => $c->titulo3,
            'titulo_pagina_color' => $c->titulo_pagina_color,
            'titulo1_color' => $c->titulo1_color,
            'titulo2_color' => $c->titulo2_color,
            'titulo3_color' => $c->titulo3_color,
            'sombras_texto' => $c->sombras_texto,
            'canvas_tipo_fondo' => $c->canvas_tipo_fondo,
            'canvas_tipo_fondo_opacity' => $c->canvas_tipo_fondo_opacity,
            'canvas_tipo_fondo_background_color' => $c->canvas_tipo_fondo_background_color,
            'contenedor_privado' => $c->contenedor_privado,
            'nivel_invitado' => $c->nivel_invitado,
            'invitado_descargar' => $c->invitado_descargar,
            'archivo_zip_nombre' => $c->archivo_zip_nombre,
            'archivo_zip_agregar_fecha' => $c->archivo_zip_agregar_fecha,
            'servidor_smtp_ip' => $c->servidor_smtp_ip,
            'servidor_smtp_port' => $c->servidor_smtp_port,
            'servidor_smtp_remitente' => $c->servidor_smtp_remitente,
        ]]);
    }

    public function guardar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Respuesta::error('Método no permitido.', 405);
        }
        Sesion::validarCsrf();
        $datos = Respuesta::entradaJson();

        // La carpeta del invitado debe existir dentro del contenedor
        if (isset($datos['nivel_invitado'])) {
            $abs = Rutas::absoluta((string) $datos['nivel_invitado'], null, Rutas::raizContenedor());
            if ($abs === null || !is_dir($abs)) {
                Respuesta::error('La carpeta del invitado no existe.');
            }
        }

        Config::guardar($datos);
        Respuesta::ok(['mensaje' => 'Configuración guardada. La página se recargará.']);
    }
}
