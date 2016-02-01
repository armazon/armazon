<?php

namespace Armazon\Http;

/**
 * Formato oficial para representar una ruta http.
 */
class Ruta
{
    public $estado_http = 500;
    public $tipo = 'error';
    public $accion;
    public $parametros;

    /**
     * @param int $estado
     *
     * @return self
     */
    public static function generarEstadoHttp(int $estado): self
    {
        $ruta = new self();
        $ruta->tipo = 'estado_http';
        $ruta->estado_http = $estado;

        return $ruta;
    }
}
