<?php

namespace Armazon\Nucleo;

trait EventosTrait
{
    private $eventos = [];

    /**
     * Obtiene el arreglo de eventos enlazados en esta clase.
     *
     * @return array
     */
    public function obtenerEventos()
    {
        return $this->eventos;
    }

    /**
     * Enlaza un evento a una definición de llamado.
     *
     * @param $nombre
     * @param callable $definicion
     * @param bool $encadenar
     */
    public function enlazarEvento($nombre, callable $definicion, $encadenar = false)
    {
        if (!$encadenar || !isset($this->eventos[$nombre])) {
            $this->eventos[$nombre] = [];
        }

        $this->eventos[$nombre][] = $definicion;
    }

    /**
     * Desenlaza un evento.
     *
     * @param $nombre
     */
    public function desenlazarEvento($nombre)
    {
        unset($this->eventos[$nombre]);
    }

    /**
     * Dispara un evento pasando argumentos de forma opcional.
     *
     * @param $nombre
     * 
     * @return mixed
     */
    public function accionarEvento($nombre)
    {
        if (isset($this->eventos[$nombre])) {
            $argumentos = func_get_args();
            array_shift($argumentos);

            if (count($this->eventos[$nombre]) > 1) {
                $resultado = [];
                foreach ($this->eventos[$nombre] as $definicion) {
                    $resultado[] = call_user_func_array($definicion, $argumentos);
                }
                return $resultado;
            }

            return call_user_func_array($this->eventos[$nombre][0], $argumentos);
        }

        return null;
    }

    /**
     * @param $nombre
     *
     * @return bool
     */
    public function existeEvento($nombre)
    {
        if (isset($this->eventos[$nombre])) {
            return true;
        }

        return false;
    }
}
