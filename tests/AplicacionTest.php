<?php

class AplicacionTest extends \PHPUnit_Framework_TestCase
{
    public function testCrearAplicacion()
    {
        $app = \Armazon\Nucleo\Aplicacion::instanciar();

        $this->assertInstanceOf('\Armazon\Nucleo\Aplicacion', $app);
    }
}