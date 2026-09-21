<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>PCAM - Login</title>
</head>

<body>

    <form method="POST"
          action="index.php?controller=auth&action=autenticar">

        <label>Usuario:</label>
        <input type="text"
               name="usuario"
               required>

        <label>Contraseña:</label>
        <input type="password"
               name="password"
               required>

        <button type="submit">
            Entrar
        </button>

    </form>

</body>
</html>