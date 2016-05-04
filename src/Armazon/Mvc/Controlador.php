<?php

namespace Armazon\Mvc;

use Armazon\Http\Peticion;
use Armazon\Http\Respuesta;
use Armazon\Http\Ruta;
use Armazon\Nucleo\Aplicacion;
use Armazon\Nucleo\EventosTrait;

/**
 * Capa Controlador del patrón MVC.
 */
abstract class Controlador
{
    use EventosTrait;

    /** @var Aplicacion */
    protected $app;
    /** @var Peticion */
    protected $peticion;
    /** @var Respuesta  */
    protected $respuesta;
    /** @var Ruta */
    public $ruta;
    /** @var Vista */
    public $vista;

    public function __construct(Aplicacion $app, Peticion $peticion, Respuesta $respuesta, Ruta $ruta)
    {
        $this->app = $app;
        $this->peticion = $peticion;
        $this->respuesta = $respuesta;
        $this->ruta = $ruta;
    }

    /**
     * Inicializa el controlador justo despues de construirse.
     */
    public function inicializar()
    {}
}
