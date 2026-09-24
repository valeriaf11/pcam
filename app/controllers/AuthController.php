<?php

require_once MODEL_PATH . '/Usuario.php';

use App\Models\Usuario;

class AuthController
{

    public function login()
    {

        // Si ya inició sesión, no mostrar login otra vez
        if (isset($_SESSION['usuario'])) {

            header('Location: /pcam/public/inicio');
            exit;
        }

        require VIEW_PATH . '/auth/login.php';
    }


    public function autenticar()
    {

        // Solo permitir peticiones POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            header('Location: /pcam/public/');
            exit;
        }


        $usuario = $_POST['usuario'] ?? '';
        $password = $_POST['password'] ?? '';


        // Validar campos vacíos
        if (empty($usuario) || empty($password)) {

            $_SESSION['error'] = 'Completa todos los campos.';

            header('Location: /pcam/public/');
            exit;
        }


        // Crear modelo Usuario
        $modeloUsuario = new Usuario();


        // Validar usuario y contraseña
        $usuarioValido = $modeloUsuario->validar(
            $usuario,
            $password
        );


        // Si los datos son correctos
        if ($usuarioValido) {

            $_SESSION['usuario'] = $usuario;

            header('Location: /pcam/public/inicio');
            exit;
        }


        // Si los datos son incorrectos
        $_SESSION['error'] = 'Usuario o contraseña incorrectos.';

        header('Location: /pcam/public/');
        exit;
    }


    public function inicio()
    {

        // Proteger página de inicio
        if (!isset($_SESSION['usuario'])) {

            header('Location: /pcam/public/');
            exit;
        }


        require VIEW_PATH . '/inicio/index.php';
    }


    public function logout()
    {

        session_unset();
        session_destroy();

        header('Location: /pcam/public/');
        exit;
    }
}