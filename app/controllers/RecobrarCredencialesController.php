<?php
/**
 * ============================================================================
 * RecobrarCredencialesController.php  -  Recuperar usuario/contraseña por correo
 * ============================================================================
 * Rutas:
 *   ?controller=recobrar&action=formulario   muestra el formulario
 *   ?controller=recobrar&action=enviar       POST email -> manda correo
 *
 * FUNCIONAMIENTO:
 *   1. Busca el correo en la tabla usuarios.
 *   2. Genera una contraseña temporal aleatoria.
 *   3. Envía un correo (PHPMailer, servidor SMTP del config.xml) con el
 *      usuario y la contraseña temporal.
 *   4. SOLO si el correo se envió bien, guarda la nueva contraseña (hash).
 *   Siempre responde el mismo mensaje para no revelar qué correos existen.
 *
 * CORRECCIÓN: hacía require 'vendor/autoload.php' con ruta relativa (fallaba);
 * ahora el autoload se carga una sola vez en public/index.php.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class RecobrarCredencialesController
{
    public function formulario(): void
    {
        vista('auth/recobrar', [
            'error' => Sesion::flash('error'),
            'aviso' => Sesion::flash('aviso'),
            'csrf' => Sesion::csrf(),
        ]);
    }

    public function enviar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Respuesta::redirigir(url('recobrar', 'formulario'));
        }
        Sesion::validarCsrf(false);

        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Sesion::flash('error', 'Escribe un correo válido.');
            Respuesta::redirigir(url('recobrar', 'formulario'));
        }

        $modelo = new Usuario();
        $usuario = $modelo->buscarPorEmail($email);

        if ($usuario) {
            $temporal = $this->generarPassword();
            try {
                $this->enviarCorreo($usuario, $temporal);
                $modelo->cambiarPassword((int) $usuario['id'], $temporal);
            } catch (Throwable $ex) {
                error_log('[PCAM] Error al enviar correo: ' . $ex->getMessage());
                Sesion::flash('error', 'No se pudo enviar el correo. Contacta al administrador.');
                Respuesta::redirigir(url('recobrar', 'formulario'));
            }
        }

        Sesion::flash('aviso', 'Si el correo está registrado, recibirás tus datos de acceso en unos minutos.');
        Respuesta::redirigir(url('auth', 'login'));
    }

    /** Contraseña temporal de 10 caracteres (sin caracteres que se confunden) */
    private function generarPassword(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $pass = '';
        for ($i = 0; $i < 10; $i++) {
            $pass .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pass;
    }

    private function enviarCorreo(array $usuario, string $temporal): void
    {
        if (!class_exists(PHPMailer::class)) {
            throw new RuntimeException('PHPMailer no está instalado (falta la carpeta vendor).');
        }
        $config = Config::load();

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $config->servidor_smtp_ip;
        $mail->Port = (int) $config->servidor_smtp_port;
        $mail->SMTPAuth = false;         // el relay interno no pide usuario
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = 15;

        $mail->setFrom($config->servidor_smtp_remitente, $config->titulo3 . ' - ' . $config->titulo_pagina);
        $mail->addAddress($usuario['email'], $usuario['nombre']);
        $mail->isHTML(true);
        $mail->Subject = 'Datos de acceso - ' . $config->titulo_pagina;
        $mail->Body = '<p>Hola <b>' . e($usuario['nombre']) . '</b>,</p>'
            . '<p>Estos son tus datos de acceso al ' . e($config->titulo_pagina) . ':</p>'
            . '<p>Usuario: <b>' . e($usuario['username']) . '</b><br>'
            . 'Contraseña temporal: <b>' . e($temporal) . '</b></p>'
            . '<p>Pide al administrador que la cambie por una nueva.</p>';
        $mail->AltBody = "Usuario: {$usuario['username']}\nContraseña temporal: $temporal";

        try {
            $mail->send();
        } catch (MailException $ex) {
            throw new RuntimeException($mail->ErrorInfo);
        }
    }
}
