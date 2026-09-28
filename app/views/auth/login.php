<?php
/**
 * ============================================================================
 * auth/login.php  -  Vista de INICIO DE SESIÓN
 * ============================================================================
 * Se muestra SOLO cuando la IP del equipo NO está registrada en la BD
 * (si está registrada, AuthController::login() deja pasar directo).
 *
 * Variables que llegan desde AuthController::login():
 *   $error            mensaje de error (usuario/contraseña incorrectos, etc.)
 *   $aviso            mensaje informativo (ej. "Sesión cerrada")
 *   $mostrarInvitado  true si el contenedor es público (config privado = 0)
 *   $ip               IP detectada del equipo (se muestra como ayuda)
 *   $csrf             token de seguridad del formulario
 *
 * CORRECCIONES: el formulario apuntaba a "/pcam/public/autenticar" (ruta fija,
 * truena si el proyecto está en otra carpeta o sin mod_rewrite); ahora usa url().
 */
$config = Config::load();
?>
<!DOCTYPE html>
<html lang="es">
<?php vista('layouts/head', ['tituloPagina' => 'Inicio de sesión', 'estilos' => ['css/login.css']]); ?>
<body>

<?php vista('layouts/header'); ?>

<div class="login-container">
    <div class="login-box">

        <!-- IMAGEN (izquierda) -->
        <div class="login-izquierda">
            <img src="<?= asset($config->imagen_login) ?>" class="logo" alt="Usuario CFE">
        </div>

        <!-- FORMULARIO (derecha) -->
        <div class="login-derecha">
            <h1>Inicio de sesión</h1>

            <?php if (!empty($error)): ?>
                <div class="error"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($aviso)): ?>
                <div class="aviso"><?= e($aviso) ?></div>
            <?php endif; ?>

            <!-- Envía usuario y contraseña a AuthController::autenticar() -->
            <form method="POST" action="<?= e(url('auth', 'autenticar')) ?>" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

                <!-- USUARIO -->
                <div class="campo">
                    <label for="usuario">Usuario:</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ingresa tu usuario"
                           maxlength="60" required autofocus>
                </div>

                <!-- CONTRASEÑA (con botón para mostrarla) -->
                <div class="campo">
                    <label for="password">Contraseña:</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña"
                               maxlength="100" required>
                        <button type="button" class="ver-password" id="btn_ver_password" title="Mostrar / ocultar">Ver</button>
                    </div>
                </div>

                <!-- BOTONES -->
                <div class="botones-login">
                    <?php if ($mostrarInvitado): ?>
                        <!-- Invitado: entra sin contraseña y solo ve la carpeta pública -->
                        <a class="boton btn-invitado" href="<?= e(url('auth', 'invitado')) ?>">Entrar como invitado</a>
                    <?php endif; ?>
                    <button type="submit" class="btn-entrar">Entrar</button>
                </div>

                <a class="enlace-recobrar" href="<?= e(url('recobrar', 'formulario')) ?>">¿Olvidaste tus datos de acceso?</a>
            </form>

            <!-- Ayuda: IP con la que el administrador puede registrar este equipo para acceso automático -->
            <div class="ip-detectada">IP de este equipo: <?= e($ip) ?></div>
        </div>

    </div>
</div>

<script src="<?= asset('js/login.js?v=2') ?>"></script>
</body>
</html>
