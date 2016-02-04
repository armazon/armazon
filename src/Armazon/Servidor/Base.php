<?php

namespace Armazon\Servidor;

use Armazon\Nucleo\Aplicacion;

/**
 * La base para preparar un servidor de aplicación.
 *
 * @package Armazon\Servidor
 */
abstract class Base
{
    public $host = '0.0.0.0';
    public $puerto = 9100;
    public $nombre = 'armazon';
    public $archivoPid;
    public $usuario = 'www-data';
    public $grupo = 'www-data';

    /** @var Aplicacion */
    public $app;

    public function __construct(Aplicacion $app)
    {
        // Preparamos la aplicación para su futura ejecución
        $app->preparar();

        // Inyectamos la aplicación a servir
        $this->app = $app;
    }
}