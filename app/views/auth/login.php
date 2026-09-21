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


        <div class="login-izquierda">

            <img
                src="img/usuariocfe.png"
                class="logo"
                alt="Usuario CFE"
            >

        </div>


        <div class="login-derecha">


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



            <!-- FORMULARIO -->

            <form
                method="POST"
                action="index.php?controller=auth&action=autenticar"
            >


                <!-- USUARIO -->

                <div class="campo">

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

                </div>



                <!-- CONTRASEÑA -->

                <div class="campo">

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

                </div>



               <div class="botones-login">

    <button
        type="button"
        class="btn-invitado"
    >
        Entrar como invitado
    </button>

    <button
        type="submit"
        class="btn-entrar"
    >
        Entrar
    </button>

</div>


            </form>


        </div>

    </div>

</div>


</body>

</html>