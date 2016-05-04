<?php

namespace Armazon\Nucleo;

trait EventosTrait
{
    private $eventos = [];

    public function obtenerEventos()
    {
        return $this->eventos;
    }

    public function registrarEvento($nombre, callable $definicion, $encadenar = false)
    {
        if (!$encadenar || !isset($this->eventos[$nombre])) {
            $this->eventos[$nombre] = array();
        }

        $this->eventos[$nombre][] = $definicion;
    }

    public function anularEvento($nombre)
    {
        unset($this->eventos[$nombre]);
    }

    public function ejecutarEvento($nombre, array $argumentos = [])
    {
        $resultado = [];

        if (isset($this->eventos[$nombre])) {
            foreach ($this->eventos[$nombre] as $definicion) {
                $resultado[] = call_user_func_array($definicion, $argumentos);
            }
        }

        if (count($resultado) == 1) {
            return $resultado[0];
        }

        return $resultado;
    }
}
