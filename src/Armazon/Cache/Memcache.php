<?php

namespace Armazon\Cache;

/**
 * Adaptador Memcache para componente caché.
 */
class Memcache implements AdaptadorInterface
{
    /** @var \Memcache */
    private $gestor;

    /**
     * Crea una instancia del Adaptador y pasa configuraciones.
     *
     * @param array $config Configuraciones
     */
    public function __construct(array $config)
    {
        $this->gestor = new \Memcache();

        foreach ($config['servidores'] as $servidor) {
            $this->gestor->addServer($servidor[0], $servidor[1], true, $servidor[2]);
        }
    }

    /**
     * Guarda los datos de la llave en el gestor cache.
     *
     * @param string $llave
     * @param string $valor
     * @param int $expiracion Tiempo en segundos (por defecto es 0 que significa ilimitado)
     *
     * @return bool
     */
    public function guardar($llave, $valor, $expiracion = 0)
    {
        return $this->gestor->set($llave, $valor, null, $expiracion);
    }

    /**
     * Obtiene el valor de la llave.
     *
     * @param string $llave
     *
     * @return mixed
     */
    public function obtener($llave)
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
    public function eliminar($llave)
    {
        return $this->gestor->delete($llave);
    }

    /**
     * Limpia el cache.
     *
     * @return bool
     */
    public function limpiar()
    {
        return $this->gestor->flush();
    }

    /**
     * Verifica si la llave existe.
     *
     * @param string $llave
     *
     * @return bool
     */
    public function existe($llave)
    {
        $temp = $this->gestor->get(array($llave));

        if (isset($temp)) {
            return true;
        } else {
            return false;
        }
    }
} 
