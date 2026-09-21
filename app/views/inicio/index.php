<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <title>PCAM</title>

    <link
        rel="stylesheet"
        href="css/sistema.css"
    >

</head>


<body>

    <h1>
        Bienvenido a PCAM
    </h1>


    <p>

        Usuario:

        <strong>

            <?= htmlspecialchars($_SESSION['usuario']) ?>

        </strong>

    </p>


    <a href="index.php?controller=auth&action=logout">

        Cerrar sesión

    </a>

</body>

</html>