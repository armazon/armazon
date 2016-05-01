<?php

namespace Armazon\Mvc;

use Armazon\Http\Peticion;
use Armazon\Http\Respuesta;
use Armazon\Http\Ruta;
use Armazon\Nucleo\Aplicacion;

/**
 * Capa Controlador del patrón MVC.
 */
abstract class Controlador
{
    /** @var Aplicacion */
    protected $app;
    /** @var Peticion */
    protected $peticion;
    /** @var Respuesta  */
    protected $respuesta;
    /** @var Vista */
    public $vista;
    public $parametros;

    public function __construct(Aplicacion $app, Peticion $peticion, Respuesta $respuesta)
    {
        $this->app = $app;
        $this->peticion = $peticion;
        $this->respuesta = $respuesta;
    }

    /**
     * Evento que corre antes de ejecutar la acción solicitada.
     *
     * @param string $controlador Nombre del controlador
     * @param string $accion Nombre del método del controlador
     *
     * @return Ruta|Respuesta
     */
    public function alIniciar($controlador, $accion)
    {}
}
