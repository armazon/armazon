<?php

namespace Armazon\Mvc;

use Armazon\Nucleo\Aplicacion;

/**
 * Capa Modelo del patrón MVC.
 */
abstract class ModeloRelacional extends \stdClass
{
    // TODO: Cambiar completamente la estructura estatica por normal, debido a la falta de limpieza en Swoole

    public $campos;
    public $nombre_tabla;
    public $llave_primaria = 'id';
    public $llave_primaria_autonum = true;

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
        $metadatos = get_class_vars(static::class);

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->seleccionar('*', $metadatos['nombre_tabla'])
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
        $metadatos = get_class_vars(static::class);

        if (is_int($filtro) || is_string($filtro)) {
            return Aplicacion::instanciar()->obtenerBdRelacional()
                ->seleccionar('*', $metadatos['nombre_tabla'])
                ->donde([$metadatos['llave_primaria'] . '|' . $metadatos['campos'][$metadatos['llave_primaria']]['tipo'] => $filtro])
                ->limitar(1)
                ->obtenerPrimero(static::class);
        } elseif (is_array($filtro) && $filtro !== []) {
            return Aplicacion::instanciar()->obtenerBdRelacional()
                ->seleccionar('*', $metadatos['nombre_tabla'])
                ->donde($filtro)
                ->obtener($metadatos['llave_primaria'], null, static::class);
        } else {
            return Aplicacion::instanciar()->obtenerBdRelacional()
                ->seleccionar('*', $metadatos['nombre_tabla'])
                ->obtener($metadatos['llave_primaria'], null, static::class);
        }
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
        $metadatos = get_class_vars(static::class);

        $temp = Aplicacion::instanciar()->obtenerBdRelacional()
            ->seleccionar('COUNT(*) as cantidad', $metadatos['nombre_tabla'])
            ->donde($filtro)
            ->obtenerPrimero();

        if ($temp === false) {
            return false;
        } else {
            return $temp['cantidad'];
        }
    }

    /**
     * Inserta un registro del modelo usando las propiedades modificadas.
     */
    public function insertar()
    {
        // Preparamos variables a usar
        $metadatos = get_class_vars(static::class);
        $parametros = [];
        $campos_faltantes = [];
        $llave_primaria = (array) $metadatos['llave_primaria'];

        // Hacemos recorrido de campos
        foreach ($metadatos['campos'] as $campo => $meta) {

            // Verificamos si el campo es llave
            $es_llave = in_array($campo, $llave_primaria);

            // Validamos si el campo es requerido
            if (($meta['requerido'] || $es_llave) && (!isset($this->{$campo}) || $this->{$campo} === '')) {
                if (!($es_llave && $metadatos['llave_primaria_autonum'])) {
                    $campos_faltantes[] = $campo;
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
        if ($campos_faltantes) {
            throw new \InvalidArgumentException('Faltan los campos requeridos [' . implode(', ', $campos_faltantes) . ']', 202);
        }

        if (Aplicacion::instanciar()->obtenerBdRelacional()->insertar($metadatos['nombre_tabla'], $parametros)->ejecutar()) {
            if (static::$llave_primaria_autonum) {
                $this->{$metadatos['llave_primaria']} = Aplicacion::instanciar()->obtenerBdRelacional()->ultimoIdInsertado();
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Actualiza registro del modelo filtrando con llaves.
     */
    public function actualizar()
    {
        // Preparamos variables a usar
        $filtro = [];
        $parametros = [];
        $campos_faltantes = [];
        $llave_primaria = (array) static::$llave_primaria;

        // Hacemos recorrido de campos
        foreach (static::$campos as $campo => $meta) {
            // Verificamos si el campo es llave
            $es_llave = in_array($campo, $llave_primaria);

            // Validamos si el campo es requerido
            if (($meta['requerido'] || $es_llave) && (!isset($this->{$campo}) || $this->{$campo} === '')) {
                $campos_faltantes[] = $campo;
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
            throw new \InvalidArgumentException('Falta rellenar los campos de llave primaria.', 201);
        }

        // Validamos presencia de campos requeridos
        if ($campos_faltantes) {
            throw new \InvalidArgumentException('Faltan los campos requeridos [' . implode(', ', $campos_faltantes) . '].', 202);
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->actualizar(static::$nombre_tabla, $parametros)
            ->donde($filtro)
            ->ejecutar();
    }

    /**
     * Elimina registros filtrando con propiedades alteradas.
     */
    public function eliminar()
    {
        // Preparamos variables a usar
        $filtro = [];
        $llave_primaria = (array) static::$llave_primaria;

        // Hacemos recorrido de campos
        foreach ($this->campos as $campo => $meta) {
            // Agregamos campo a los parametros de filtro según el caso
            if (in_array($campo, $llave_primaria) && isset($this->{$campo}) && $this->{$campo} !== '') {
                $filtro[$campo . '|' . $meta['tipo']] = $this->{$campo};
            }
        }

        // Validamos presencia de campos en filtro
        if (!$filtro) {
            throw new \InvalidArgumentException('Falta rellenar los campos de llave primaria.', 201);
        }

        return Aplicacion::instanciar()->obtenerBdRelacional()
            ->eliminar(static::$nombre_tabla)
            ->donde($filtro)
            ->ejecutar();
    }
}
