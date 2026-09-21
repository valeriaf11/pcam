<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>PCAM - Inicio de sesión</title>

    <link
        rel="stylesheet"
        href="css/login.css"
    >

</head>


<body>


<div class="login-container">

    <div class="login-box">

        <img
            src="img/zotgm.png"
            class="logo"
            alt="Logo"
        >


        <h1>PCAM</h1>

        <h2>Inicio de sesión</h2>


        <?php

        if (isset($_SESSION['error'])) {

            echo '<div class="error">'
                . htmlspecialchars($_SESSION['error'])
                . '</div>';

            unset($_SESSION['error']);
        }

        ?>


        <form
            method="POST"
            action="index.php?controller=auth&action=autenticar"
        >


            <label for="usuario">
                Usuario
            </label>

            <input
                type="text"
                id="usuario"
                name="usuario"
                placeholder="Ingresa tu usuario"
                required
            >


            <label for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Ingresa tu contraseña"
                required
            >


            <button type="submit">
                Iniciar sesión
            </button>


        </form>

    </div>

</div>


</body>

</html>