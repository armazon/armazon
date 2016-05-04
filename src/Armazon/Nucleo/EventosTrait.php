<?php

namespace Armazon\Nucleo;

trait EventosTrait
{
    private $eventos = [];

    public function registrarEvento($nombre, callable $definicion, $encadenar = false)
    {
        if (!$encadenar || !isset($this->eventos[$nombre])) {
            $this->eventos[$nombre] = array();
        }

        $this->eventos[$nombre][] = $definicion;
    }

    public function ejecutarEvento($nombre, array $argumentos = [], $eliminarAlEjecutar = false)
    {
        if (isset($this->eventos[$nombre])) {
            foreach ($this->eventos[$nombre] as $definicion) {
                call_user_func_array($definicion, $argumentos);
            }

            if ($eliminarAlEjecutar) {
                unset($this->eventos[$nombre]);
            }
        }
    }
}
