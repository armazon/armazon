<?php

namespace Armazon\Mvc;

use Armazon\Nucleo\Aplicacion;
use SebastianBergmann\GlobalState\RuntimeException;

/**
 * Capa Modelo del patrón MVC.
 */
abstract class ModeloRelacional extends \stdClass
{
    // TODO: Cambiar estructura estatica a normal debido a falta de limpieza en Swoole y AMPHP, listo pero falta prueba

    public $campos;
    public $nombreTabla;
    public $llavePrimaria = 'id';
    public $llavePrimariaAutonum = true;

    /**
     * Rellena los campos usando un arreglo.
     *
     * @param array $datos
     * @return $this
     */
    public function rellenar(array $datos)
    {
        foreach ($datos as $llave => $valor) {
            $this->$llave = $valor;
        }

        return $this;
    }

    /**
     * Busca primer registro del modelo usando el campo argumentado.
     *
     * @param string $campo
     * @param mixed $valor
     * @param string $tipo
     * @return array|bool
     */
    public static function buscarPrimeroPorCampo(string $campo, $valor, string $tipo = 'auto')
    {
        // Preparamos variables requeridas
        $metadatos = get_class_vars(static::class);

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->seleccionar('*', $metadatos['nombreTabla'])
            ->donde([$campo . '|' . $tipo => $valor])
            ->limitar(1)
            ->obtenerPrimero(static::class);
    }

    /**
     * Busca registros del modelo según el filtro aplicado.
     *
     * @param mixed $filtro
     * @return array|bool
     */
    public static function buscar($filtro = null)
    {
        // Preparamos variables requeridas
        $metadatos = get_class_vars(static::class);
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();

        if (is_int($filtro) || is_string($filtro)) {
            return $bd
                ->seleccionar('*', $metadatos['nombreTabla'])
                ->donde([$metadatos['llavePrimaria'] . '|' . $metadatos['campos'][$metadatos['llavePrimaria']]['tipo'] => $filtro])
                ->limitar(1)
                ->obtenerPrimero(static::class);
        } elseif (is_array($filtro) && count($filtro) > 0) {
            return $bd
                ->seleccionar('*', $metadatos['nombreTabla'])
                ->donde($filtro)
                ->obtener($metadatos['llavePrimaria'], null, static::class);
        }

        return $bd
            ->seleccionar('*', $metadatos['nombreTabla'])
            ->obtener($metadatos['llavePrimaria'], null, static::class);

    }

    /**
     * Obtiene la cantidad de registros que posee el modelo,
     * puede aplicar un filtro a la consulta.
     *
     * @param array|null $filtro
     *
     * @return int|bool
     */
    public static function contar(array $filtro = null)
    {
        // Preparamos variables requeridas
        $metadatos = get_class_vars(static::class);

        $temp = Aplicacion::instanciar()->obtenerBdRelacional()
            ->seleccionar('COUNT(*) as c', $metadatos['nombreTabla'])
            ->donde($filtro)
            ->obtenerPrimero();

        if ($temp === false) {
            return false;
        } else {
            return $temp['c'];
        }
    }

    /**
     * Inserta un registro del modelo usando las propiedades modificadas.
     *
     * @throws \RuntimeException
     */
    public function insertar()
    {
        // Preparamos variables a usar
        $parametros = [];
        $camposFaltantes = [];
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();

        // Hacemos recorrido de campos
        foreach ($this->campos as $campo => $meta) {

            // Verificamos si el campo es llave
            $es_llave = in_array($campo, $this->llavePrimaria);

            // Validamos si el campo es requerido
            if (($meta['requerido'] || $es_llave) && (!isset($this->{$campo}) || $this->{$campo} === '')) {
                if (!($es_llave && $this->llavePrimariaAutonum)) {
                    $camposFaltantes[] = $campo;
                    continue;
                }
            }

            // Agregamos campo a los parametros de inserción
            if (isset($this->{$campo})) {
                $parametros[$campo . '|' . $meta['tipo']] = $this->{$campo};
            } else {
                $parametros[$campo . '|' . $meta['tipo']] = null;
            }
        }

        // Validamos presencia de campos requeridos
        if ($camposFaltantes) {
            throw new \RuntimeException('Faltan los campos requeridos [' . implode(', ', $camposFaltantes) . ']');
        }

        if ($bd->insertar($this->nombreTabla, $parametros)->ejecutar()) {
            if ($this->llavePrimariaAutonum) {
                $this->{$this->llavePrimaria} = $bd->ultimoIdInsertado();
            }

            return true;
        }

        return false;
    }

    /**
     * Actualiza registro del modelo filtrando con llaves.
     *
     * @throws \RuntimeException
     */
    public function actualizar()
    {
        // Preparamos variables a usar
        $filtro = [];
        $parametros = [];
        $camposFaltantes = [];
        $llavePrimaria = (array)$this->llavePrimaria;

        // Hacemos recorrido de campos
        foreach ($this->campos as $campo => $meta) {
            // Verificamos si el campo es llave
            $es_llave = in_array($campo, $llavePrimaria);

            // Validamos si el campo es requerido
            if (($meta['requerido'] || $es_llave) && (!isset($this->{$campo}) || $this->{$campo} === '')) {
                $camposFaltantes[] = $campo;
                continue;
            }

            // Agregamos campo a los parametros de inserción
            if (isset($this->{$campo})) {
                $parametros[$campo . '|' . $meta['tipo']] = $this->{$campo};
            } else {
                $parametros[$campo . '|' . $meta['tipo']] = null;
            }

            // Agregamos campo a los parametros de filtro
            if ($es_llave) {
                $filtro[$campo . '|' . $meta['tipo']] = $this->{$campo};
            }
        }

        // Validamos presencia de campos en filtro
        if (!$filtro) {
            throw new \RuntimeException('Falta rellenar los campos de llave primaria.', 201);
        }

        // Validamos presencia de campos requeridos
        if ($camposFaltantes) {
            throw new \RuntimeException('Faltan los campos requeridos [' . implode(', ', $camposFaltantes) . '].', 202);
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->actualizar($this->nombreTabla, $parametros)
            ->donde($filtro)
            ->ejecutar();
    }

    /**
     * Elimina registros filtrando con propiedades alteradas.
     *
     * @throws \RuntimeException
     */
    public function eliminar()
    {
        // Preparamos variables a usar
        $filtro = [];
        $llavePrimaria = (array)$this->llavePrimaria;

        // Hacemos recorrido de campos
        foreach ($this->campos as $campo => $meta) {
            // Agregamos campo a los parametros de filtro según el caso
            if (in_array($campo, $llavePrimaria) && isset($this->{$campo}) && $this->{$campo} !== '') {
                $filtro[$campo . '|' . $meta['tipo']] = $this->{$campo};
            }
        }

        // Validamos presencia de campos en filtro
        if (!$filtro) {
            throw new \RuntimeException('Falta rellenar los campos de llave primaria.');
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->eliminar($this->nombreTabla)
            ->donde($filtro)
            ->ejecutar();
    }
}
