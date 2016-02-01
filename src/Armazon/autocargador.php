<?php

$dir_base = __DIR__;

spl_autoload_register(function($clase) use ($dir_base) {
    // Verificamos si la clase a cargar pertene a Armazon para procesarla
    if (strpos($clase, 'Armazon') === 0) {

        // Quitamos la base de Armazon del camino de la clase
        $clase = substr($clase, 8);

        // Preparamos nombre del archivo que puede contener la clase
        $archivo = $dir_base . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $clase) . '.php';

        // Cargamos el archivo de la clase en caso de existir
        if (is_file($archivo)) {
            include $archivo;

            return true;
        } else {
            return false;
        }

    }

    return false;
});
