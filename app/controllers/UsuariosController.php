<?php
/** Administración de usuarios: solo rol Administrador. */
class UsuariosController
{
    private Usuario $usuarios;
    public function __construct(array $config) { $this->usuarios = new Usuario($config['db_path']); }

    public function index(): void
    {
        $this->admin();
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $registros = $this->usuarios->obtenerTodos();
        $flash = $_SESSION['flash'] ?? null; $error = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash'], $_SESSION['flash_error']);
        require VIEW_PATH . '/usuarios/index.php';
    }

    public function guardar(): void
    {
        $this->admin(); $this->post();
        $id = (int)($_POST['id'] ?? 0);
        $data = $this->datos();
        if ($id === 0 && $data['password'] === '') throw new RuntimeException('La contraseña es obligatoria para un usuario nuevo.');
        try {
            if ($id > 0) $this->usuarios->actualizar($id, $data); else $this->usuarios->crear($data);
            $_SESSION['flash'] = $id > 0 ? 'Usuario actualizado.' : 'Usuario creado.';
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = str_contains(strtolower($e->getMessage()), 'unique') ? 'El username ya existe.' : 'No se pudo guardar el usuario.';
        }
        header('Location: index.php?controller=usuarios&action=index'); exit;
    }

    public function eliminar(): void
    {
        $this->admin(); $this->post();
        $id=(int)($_POST['id'] ?? 0);
        if ($id === (int)($_SESSION['usuario']['id'] ?? 0)) {
            $_SESSION['flash_error']='No puedes eliminar tu propio usuario mientras tienes la sesión abierta.';
        } elseif ($id>0) {
            $this->usuarios->borrar($id); $_SESSION['flash']='Usuario eliminado.';
        }
        header('Location: index.php?controller=usuarios&action=index'); exit;
    }

    private function datos(): array
    {
        $nombre=trim((string)($_POST['nombre'] ?? '')); $username=trim((string)($_POST['username'] ?? ''));
        if ($nombre==='' || $username==='') throw new RuntimeException('Nombre y username son obligatorios.');
        $ip=trim((string)($_POST['ip'] ?? ''));
        if ($ip!=='' && !filter_var($ip,FILTER_VALIDATE_IP)) throw new RuntimeException('La IP no es válida.');
        $nivel=trim(str_replace(['..','\\'],['','/'],(string)($_POST['nivel_inicial'] ?? '')), '/');
        return ['nombre'=>$nombre,'username'=>$username,'password'=>(string)($_POST['password'] ?? ''),'email'=>trim((string)($_POST['email'] ?? '')),
            'ip'=>$ip,'rol_id'=>in_array((int)($_POST['rol_id'] ?? 2),[1,2],true)?(int)$_POST['rol_id']:2,
            'nivel_inicial'=>$nivel,'activo'=>isset($_POST['activo'])?1:0];
    }
    private function admin(): void { if ((int)($_SESSION['usuario']['rol_id'] ?? 0)!==1) { http_response_code(403); exit('Acceso solo para administradores.'); } }
    private function post(): void { if ($_SERVER['REQUEST_METHOD']!=='POST' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'],(string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Solicitud inválida.'); }
}
