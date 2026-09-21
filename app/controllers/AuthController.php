class AuthController
{
    public function login()
    {
        require VIEW_PATH . '/auth/login.php';
    }

    public function autenticar()
    {
        $usuario = $_POST['usuario'] ?? '';
        $password = $_POST['password'] ?? '';

        // Aquí después llamaremos al modelo Usuario
        // para comprobar usuario y contraseña.
    }

    public function logout()
    {
        session_destroy();

        header(
            'Location: index.php?controller=auth&action=login'
        );

        exit;
    }
}