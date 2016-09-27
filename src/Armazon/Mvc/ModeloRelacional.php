<?php

namespace Armazon\Mvc;

use Armazon\Nucleo\Aplicacion;

/**
 * Capa Modelo del patrón MVC.
 */
abstract class ModeloRelacional extends \stdClass
{
    // TODO: Cambiar estructura estatica a normal debido a falta de limpieza en Swoole y AMPHP, listo pero falta prueba

    public $__campos;
    public $__nombreTabla;
    public $__llavePrimaria = 'id';
    public $__llavePrimariaAutonum = true;

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
     * Busca registros del modelo según el filtro aplicado.
     *
     * @param mixed $filtro
     * @return array|bool
     */
    /**
     * @param null $filtro
     * @param null $indice
     * @param null $agrupacion
     * @return array|bool
     */
    public static function buscar($filtro = null, $indice = null, $agrupacion = null)
    {
        // Preparamos variables requeridas
        $metadatos = get_class_vars(static::class);
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();

        // TODO: Cambiar la funcionalidad de escoger un solo elemento

        if (is_int($filtro) || is_string($filtro)) {
            return $bd
                ->seleccionar('*', $metadatos['__nombreTabla'])
                ->donde([$metadatos['__llavePrimaria'] . '|' . $metadatos['__campos'][$metadatos['__llavePrimaria']]['tipo'] => $filtro])
                ->limitar(1)
                ->obtenerPrimero(static::class);
        } elseif (is_array($filtro) && count($filtro) > 0) {
            return $bd
                ->seleccionar('*', $metadatos['__nombreTabla'])
                ->donde($filtro)
                ->obtener($indice, $agrupacion, static::class);
        }

        return $bd
            ->seleccionar('*', $metadatos['__nombreTabla'])
            ->obtener($indice, $agrupacion, static::class);

    }

    /**
     * Busca el primer registro del modelo según el filtro aplicado.
     *
     * @param mixed $filtro
     * @return self
     */
    public static function buscarPrimero($filtro = null)
    {
        // Preparamos variables requeridas
        $metadatos = get_class_vars(static::class);
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();

        if (is_int($filtro) || is_string($filtro)) {
            if (is_array($metadatos['__llavePrimaria'])) {
                throw new \InvalidArgumentException('No se puede realizar busqueda simple sobre llave compuesta');
            }

            return $bd
                ->seleccionar('*', $metadatos['__nombreTabla'])
                ->donde([$metadatos['__llavePrimaria'] . '|' . $metadatos['__campos'][$metadatos['__llavePrimaria']]['tipo'] => $filtro])
                ->limitar(1)
                ->obtenerPrimero(static::class);
        } elseif (is_array($filtro) && count($filtro) > 0) {
            return $bd
                ->seleccionar('*', $metadatos['__nombreTabla'])
                ->donde($filtro)
                ->limitar(1)
                ->obtenerPrimero(static::class);
        }

        return $bd
            ->seleccionar('*', $metadatos['__nombreTabla'])
            ->limitar(1)
            ->obtenerPrimero(static::class);

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
            ->seleccionar('COUNT(*) as c', $metadatos['__nombreTabla'])
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
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();
        $parametros = [];
        $llavePrimaria = (array) $this->__llavePrimaria;

        // Hacemos recorrido de campos
        foreach ($this->__campos as $campo => $meta) {

            // Verificamos si el campo es llave
            $esLlave = in_array($campo, $llavePrimaria);

            // Validamos requerimiento del campo
            if (!empty($meta['requerido']) && $this->campoVacio($campo)) {
                if (!($esLlave && $this->__llavePrimariaAutonum)) {
                    throw new \RuntimeException("Falta el campo requerido '{$campo}'.");
                }
            }

            // Agregamos campo existente a los parametros de inserción
            if (isset($this->{$campo})) {
                $parametros[$campo . '|' . $meta['tipo']] = $this->{$campo};
            }
        }

        if ($bd->insertar($this->__nombreTabla, $parametros)->ejecutar()) {
            if ($this->__llavePrimariaAutonum && !is_array($this->__llavePrimaria)) {
                $this->{$this->__llavePrimaria} = $bd->ultimoIdInsertado();
            }

            return true;
        }

        return false;
    }

    /**
     * Guarda un registro del modelo usando las propiedades modificadas.
     * Si el registro ya existe entonces lo modifica usando las mismas propiedades.
     *
     * @throws \RuntimeException
     */
    public function guardar()
    {
        // Preparamos variables a usar
        $bd = Aplicacion::instanciar()->obtenerBdRelacional();
        $llavePrimaria = (array) $this->__llavePrimaria;

        // Hacemos recorrido de campos en la llave primaria
        $filtro = [];
        foreach ($llavePrimaria as $campo) {
            if ($this->campoVacio($campo) && !$this->__llavePrimariaAutonum) {
                throw new \RuntimeException("Falta el campo requerido '{$campo}'.");
            }

            $filtro[$campo . '|' . $this->__campos[$campo]['tipo']] = $this->{$campo};
        }

        $existe = $bd
            ->seleccionar('*', $this->__nombreTabla)
            ->donde($filtro)
            ->limitar(1)
            ->obtenerPrimero();

        if ($existe) {
            return $this->actualizar();
        }

        return $this->insertar();
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
        $llavePrimaria = (array) $this->__llavePrimaria;

        // Hacemos recorrido de campos para rellenar parametros de actualización
        foreach ($this->__campos as $campo => $meta) {
            // Validamos si el campo cumple con su requerimiento
            if (!empty($meta['requerido']) && $this->campoVacio($campo)) {
                throw new \RuntimeException("Falta el campo requerido '{$campo}'.");
            }

            // Agregamos campo a los parametros de inserción
            if (isset($this->{$campo})) {
                $parametros[$campo . '|' . $meta['tipo']] = $this->{$campo};
            }
        }

        // Hacemos recorrido de llave primaria para rellenar el filtro de consulta
        foreach ($llavePrimaria as $campo) {
            if ($this->campoVacio($campo)) {
                throw new \RuntimeException("Falta rellenar el campo llave '{$campo}'.");
            }

            $filtro[$campo . '|' . $this->__campos[$campo]['tipo']] = $this->{$campo};
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->actualizar($this->__nombreTabla, $parametros)
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
        $llavePrimaria = (array) $this->__llavePrimaria;

        // Hacemos recorrido de llave primaria para rellenar el filtro de consulta
        foreach ($llavePrimaria as $campo) {
            if ($this->campoVacio($campo)) {
                throw new \RuntimeException("Falta rellenar el campo llave '{$campo}'.");
            }

            $filtro[$campo . '|' . $this->__campos[$campo]['tipo']] = $this->{$campo};
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->eliminar($this->__nombreTabla)
            ->donde($filtro)
            ->ejecutar();
    }

    private function campoVacio($campo)
    {
        if (!isset($this->{$campo}) || $this->{$campo} === '') {
            return true;
        }

        return false;
    }
}
