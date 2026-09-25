$(function () {
 

});

function submitConCredenciales(){
    document.getElementById("loginForm").action = "?controller=login&action=loginUser";
    document.getElementById("loginForm").submit();
}

function submitInvitado(){
    document.getElementById("loginForm").action = "?controller=login&action=nologin";
    document.getElementById("loginForm").submit();
}
