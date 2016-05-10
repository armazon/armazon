<?php

namespace Armazon\Nucleo;

/**
 * Clase que agrega más funcionalidad a la excepcion normal.
 */
class Excepcion extends \Exception
{
    protected $detalle;

    public function __construct($mensaje, $detalle = null, $codigo = 0, \Exception $previous = null)
    {
        $this->detalle = $detalle;

        parent::__construct($mensaje, $codigo, $previous);
    }

    /**
     * @return bool
     */
    final public function tieneDetalle()
    {
        return !empty($this->detalle);
    }

    final public function obtenerDetalle()
    {
        return $this->detalle;
    }
}
