<?php

session_start();

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('CONTROLLER_PATH', APP_PATH . '/controllers');
define('MODEL_PATH', APP_PATH . '/models');
define('CONFIG_PATH', ROOT_PATH . '/configuracion');

require_once VIEW_PATH . '/auth/login.php';