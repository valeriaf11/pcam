<?php

session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('MODEL_PATH', APP_PATH . '/models');
define('CONTROLLER_PATH', APP_PATH . '/controllers');
define('CONFIG_PATH', ROOT_PATH . '/configuracion');

$controller = $_GET['controller'] ?? 'auth';
$action = $_GET['action'] ?? 'login';


switch ($controller) {

    case 'auth':

        require_once CONTROLLER_PATH . '/AuthController.php';

        $authController = new AuthController();

        if (method_exists($authController, $action)) {

            $authController->$action();

        } else {

            echo "La acción no existe.";

        }

        break;


    default:

        echo "El controlador no existe.";
        break;
}