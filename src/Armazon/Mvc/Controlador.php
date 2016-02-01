<?php

namespace Armazon\Mvc;

use Armazon\Http\Peticion;
use Armazon\Http\Respuesta;
use Armazon\Http\Ruta;
use Armazon\Nucleo\Aplicacion;

/**
 * Capa Controlador del patrón MVC.
 */
class Controlador
{
    /** @var Aplicacion */
    protected $app;

    /** @var Peticion */
    protected $peticion;
    /** @var Respuesta  */
    protected $respuesta;
    /** @var Vista  */
    protected $vista;
    protected $parametros;

    public function __construct(Aplicacion $app, Peticion $peticion, Respuesta $respuesta)
    {
        $this->app = $app;
        $this->peticion = $peticion;
        $this->respuesta = $respuesta;
    }

    public function registrarVista(Vista &$vista)
    {
        $this->vista = $vista;
    }

    /**
     * Evento que corre antes de ejecutar la acción solicitada.
     *
     * @param string $controlador Nombre del controlador
     * @param string $accion Nombre del método del controlador
     *
     * @return Ruta|Respuesta
     */
    public function alIniciar(string $controlador, string $accion)
    {
    }

    /**
     * Evento que corre despues de ejecutar la acción solicitada.
     *
     * @param mixed $resultado Resultado de ejecución de la acción solicitada
     *
     * @return Respuesta
     */
    public function alTerminar(&$resultado)
    {
        // TODO: Revisar bien el evento alTerminar del Controlador, debería recibir y retornar rutas o respuestas o algo así.
        if (empty($resultado) && $this->vista->fueRenderizado()) {
            $contenido = $this->vista->obtenerContenido();
            $this->respuesta->definirContenido($contenido);
            return $this->respuesta;
        }

        return $resultado;
    }
}
