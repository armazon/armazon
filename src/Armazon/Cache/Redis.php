<?php

namespace Armazon\Cache;

/**
 * Adaptador Redis para componente caché.
 */
class Redis implements AdaptadorInterface
{
    /** @var \Redis */
    private $gestor;

    /**
     * Crea una instancia del adaptador y pasa configuraciones.
     *
     * @param array $config Configuraciones
     *
     * @throws \RuntimeException
     */
    public function __construct(array $config)
    {
        $this->gestor = new \Redis();

        try {
            // Detectamos uso de zócalo y adaptamos la conexión
            if (strpos($config['servidor'], '/') !== false) {
                $temp = $this->gestor->connect($config['servidor']);
            } else {
                $temp = $this->gestor->connect($config['servidor'], $config['puerto'], 0.2);
            }
            if (!$temp) {
                throw new \RuntimeException('No se puede conectar al servicio de cache.');
            }
        } catch (\RedisException $e) {
            throw new \RuntimeException(sprintf('No se puede conectar al servicio de cache. ' . $e->getMessage()));
        }
    }

    /**
     * Guarda los datos de la llave en el servidor cache.
     *
     * @param string $llave
     * @param string $valor
     * @param int $expiracion Tiempo en segundos (por defecto es 0 que significa ilimitado)
     *
     * @return bool
     */
    public function guardar(string $llave, $valor, int $expiracion = 0): bool
    {
        return $this->gestor->set($llave, $valor, $expiracion);
    }

    /**
     * Obtiene el valor de la llave.
     *
     * @param string $llave
     *
     * @return mixed
     */
    public function obtener(string $llave)
    {
        return $this->gestor->get($llave);
    }

    /**
     * Elimina llave del cache.
     *
     * @param string $llave
     *
     * @return bool
     */
    public function eliminar(string $llave): bool
    {
        $this->gestor->delete($llave);

        return true;
    }

    /**
     * Verifica si la llave existe.
     *
     * @param string $llave
     *
     * @return bool
     */
    public function existe(string $llave): bool
    {
        return $this->gestor->exists($llave);
    }
}
