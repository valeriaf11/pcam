<?php

namespace App\Models;
class Usuario
{

    public function validar($usuario, $password)
    {

        // Usuario temporal para probar el sistema

        $usuarioCorrecto = 'admin';
        $passwordCorrecto = '1234';


        if (
            $usuario === $usuarioCorrecto &&
            $password === $passwordCorrecto
        ) {

            return true;

        }


        return false;
    }
}