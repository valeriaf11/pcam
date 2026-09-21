<?php

require_once MODEL_PATH . '/Usuario.php';

use App\Models\Usuario;

class AuthController
{

    public function login()
    {

        // Si ya inició sesión, no mostrar login otra vez
        if (isset($_SESSION['usuario'])) {

            header('Location: index.php?controller=auth&action=inicio');
            exit;
        }

        require VIEW_PATH . '/auth/login.php';
    }


    public function autenticar()
    {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

            header('Location: index.php');
            exit;
        }


        $usuario = $_POST['usuario'] ?? '';
        $password = $_POST['password'] ?? '';


        if (empty($usuario) || empty($password)) {

            $_SESSION['error'] = 'Completa todos los campos.';

            header('Location: index.php');
            exit;
        }

    
        $modeloUsuario = new Usuario();

        $usuarioValido = $modeloUsuario->validar(
            $usuario,
            $password
        );


        if ($usuarioValido) {

            $_SESSION['usuario'] = $usuario;

            header(
                'Location: index.php?controller=auth&action=inicio'
            );

            exit;

        }


        $_SESSION['error'] = 'Usuario o contraseña incorrectos.';

        header('Location: index.php');
        exit;
    }


    public function inicio()
    {

        // Proteger la página
        if (!isset($_SESSION['usuario'])) {

            header('Location: index.php');
            exit;
        }

        require VIEW_PATH . '/inicio/index.php';
    }


    public function logout()
    {

        session_unset();
        session_destroy();

        header('Location: index.php');
        exit;
    }
}