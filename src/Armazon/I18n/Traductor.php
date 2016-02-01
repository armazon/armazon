<?php

namespace Armazon\I18n;

use Armazon\Nucleo\Aplicacion;

/**
 * Traductor de textos que usa arreglos como fuentes de traducciones.
 */
class Traductor
{
    /** @var Aplicacion */
    private $app;

    private $traducciones = [];
    private $dir_traducciones;
    private $idioma;

    public function __construct(Aplicacion $app)
    {
        // Inyectamos la instancia de aplicación
        $this->app = $app;

        // Definimos el directorio de las vistas
        $this->dir_traducciones = $app->obtenerDirApp() . DIRECTORY_SEPARATOR . 'traducciones';
    }

    /**
     * Define el directorio donde se ubican las traducciones.
     *
     * @param string $directorio
     */
    public function definirDirTraducciones(string $directorio)
    {
        $temp = realpath($directorio);

        if ($temp === false) {
            throw new \InvalidArgumentException('El directorio argumentado para las traduciones no existe.');
        } else {
            $this->dir_traducciones = $temp;
        }
    }

    /**
     * Carga y define el idioma argumentado a través del archivo con el mismo nombre del idioma.
     *
     * @param string $idioma
     */
    public function cargarIdioma(string $idioma)
    {
        // Preparamos camino del archivo usando el directorio base
        $archivo = $this->dir_traducciones . DIRECTORY_SEPARATOR . $idioma . '.php';

        // Validamos presencia de arhivo del idioma
        if (!is_readable($archivo)) {
            throw new \InvalidArgumentException('No existe el archivo del idioma "' . $archivo . '".');
        }

        // Cargamos las traducciones presentes en el arhivo del idioma
        $this->traducciones[$idioma] = include $archivo;

        // Definimos el idioma cargado
        $this->idioma = $idioma;
    }

    /**
     * Define el idioma argumentado.
     *
     * @param string $idioma
     */
    public function definirIdioma(string $idioma)
    {
        $this->idioma = $idioma;
    }

    /**
     * Devuelve la traducción del texto en caso de existir en el idioma cargado.
     *
     * @param $texto
     *
     * @return string
     */
    public function t(string $texto): string
    {
        // Cambiamos valor en caso de encontrar traducción
        if (isset($this->traducciones[$this->idioma][$texto])) {
            $texto = $this->traducciones[$this->idioma][$texto];
        }

        return $texto;
    }
}
