<?php
/**
 * ============================================================================
 * Red.php  -  Obtener la IP real del cliente
 * ============================================================================
 * Esta IP es la que se busca en la tabla "usuarios" (columna ip) para dejar
 * entrar AUTOMÁTICAMENTE, sin pedir usuario ni contraseña.
 *
 * CORRECCIÓN DE SEGURIDAD:
 *   La versión anterior tomaba primero HTTP_CLIENT_IP / HTTP_X_FORWARDED_FOR.
 *   Esos encabezados los manda el navegador y se pueden FALSIFICAR, así que
 *   cualquiera podía hacerse pasar por la IP del administrador.
 *   Ahora solo se usa REMOTE_ADDR, salvo que en config.xml se active
 *   <confiar_proxy>1</confiar_proxy> (solo si hay un proxy de confianza).
 */

class Red
{
    public static function ipCliente(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (Config::load()->confiar_proxy && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // El primer valor de la lista es el cliente original
            $candidata = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($candidata, FILTER_VALIDATE_IP)) {
                $ip = $candidata;
            }
        }
        return self::normalizar($ip);
    }

    /** Unifica formatos: "::1" -> "127.0.0.1", "::ffff:10.0.0.5" -> "10.0.0.5" */
    public static function normalizar(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '::1') {
            return '127.0.0.1';
        }
        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return substr($ip, 7);
        }
        return $ip;
    }
}
