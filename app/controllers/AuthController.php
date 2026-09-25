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

    
        $modeloUsuario = new usuario();

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

    public function invitado()
    {
         $_SESSION['LOGIN_OK'] = true;
            $_SESSION['nombre_usuario'] = "Invitado";
            //$_SESSION['ip_usuario'] = $reg_ip['ip'];
            $_SESSION['rol_id'] = "3";//invitado  
            //$_SESSION['ruta_inicial'] = ROOT_PATH.'/'.$config->contenedor_ruta_base . $reg_ip['nivel_inicial'];
            $_SESSION['nivel_inicial'] = "";
            //header("Location: contenido.php");
            require VIEW_PATH . '/contenido.php';
            exit;

        // Si ya inició sesión, no mostrar registro otra vez
        if (isset($_SESSION['usuario'])) {

            header('Location: index.php?controller=auth&action=inicio');
            exit;
        }

        require VIEW_PATH . '/auth/registro.php';
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