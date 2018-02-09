<?php

namespace Armazon\I18n;

/**
 * Traductor de textos que usa arreglos como fuentes de traducciones.
 */
class Traductor
{
    private $traducciones = [];
    private $dir_base;
    private $idioma;

    public function __construct($dirBase)
    {
        if (!is_dir($dirBase)) {
            throw new \InvalidArgumentException("No existe el directorio definido '{$dirBase}'.");
        }

        $this->dir_base = realpath($dirBase);
    }

    /**
     * Carga y define el idioma argumentado a través del archivo con el mismo nombre del idioma.
     *
     * @param string $idioma
     */
    public function cargarIdioma($idioma)
    {
        // Preparamos camino del archivo usando el directorio base
        $archivo = $this->dir_base . DIRECTORY_SEPARATOR . $idioma . '.php';

        // Validamos presencia de arhivo del idioma
        if (!is_readable($archivo)) {
            throw new \InvalidArgumentException("No existe el archivo del idioma '{$archivo}'.");
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
    public function definirIdioma($idioma)
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
    public function t($texto)
    {
        // Cambiamos valor en caso de encontrar traducción
        if (isset($this->traducciones[$this->idioma][$texto])) {
            $texto = $this->traducciones[$this->idioma][$texto];
        }

        return $texto;
    }
}
