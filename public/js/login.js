/* =============================================================================
   login.js  -  Comportamiento de la página de inicio de sesión
   =============================================================================
   - Botón "Ver" para mostrar/ocultar la contraseña.
   - Evita doble envío del formulario (doble clic en "Entrar").
   (Las funciones viejas submitConCredenciales/submitInvitado mandaban a
    acciones que ya no existían; ahora el formulario y el enlace de invitado
    apuntan directo a las rutas correctas desde PHP.)
   ============================================================================= */
document.addEventListener('DOMContentLoaded', function () {
    var btnVer = document.getElementById('btn_ver_password');
    var inputPass = document.getElementById('password');

    if (btnVer && inputPass) {
        btnVer.addEventListener('click', function () {
            var oculto = inputPass.type === 'password';
            inputPass.type = oculto ? 'text' : 'password';
            btnVer.textContent = oculto ? 'Ocultar' : 'Ver';
        });
    }

    var form = document.querySelector('.login-derecha form');
    if (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Entrando...';
            }
        });
    }
});
