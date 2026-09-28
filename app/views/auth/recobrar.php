<?php
/**
 * ============================================================================
 * auth/recobrar.php  -  "¿Olvidaste tus datos de acceso?"
 * ============================================================================
 * El usuario escribe su correo; si está registrado se le envía por correo su
 * nombre de usuario y una CONTRASEÑA TEMPORAL nueva (ya no se manda la
 * contraseña original porque ahora se guarda cifrada con password_hash).
 * Variables: $error, $aviso, $csrf
 * (Antes estaba en views/layouts/recobrarcredenciales.php e incluía
 *  "/header.php" con una ruta incorrecta.)
 */
$config = Config::load();
?>
<!DOCTYPE html>
<html lang="es">
<?php vista('layouts/head', ['tituloPagina' => 'Recuperar datos de acceso', 'estilos' => ['css/login.css']]); ?>
<body>

<?php vista('layouts/header'); ?>

<div class="login-container">
    <div class="login-box">
        <div class="login-izquierda">
            <img src="<?= asset($config->imagen_login) ?>" class="logo" alt="Usuario CFE">
        </div>

        <div class="login-derecha">
            <h1>Recuperar acceso</h1>

            <?php if (!empty($error)): ?>
                <div class="error"><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($aviso)): ?>
                <div class="aviso"><?= e($aviso) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('recobrar', 'enviar')) ?>">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <div class="campo">
                    <label for="email">Correo:</label>
                    <input type="email" id="email" name="email" placeholder="usuario@cfe.mx" maxlength="120" required autofocus>
                </div>

                <div class="botones-login">
                    <a class="boton btn-invitado" href="<?= e(url('auth', 'login')) ?>">Regresar</a>
                    <button type="submit" class="btn-entrar">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
