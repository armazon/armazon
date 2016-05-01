<?php

namespace Armazon\Cache;

/**
 * Adaptador Memcached para componente caché.
 */
class Memcached implements AdaptadorInterface
{
    /** @var \Memcached */
    private $gestor;

    private $opciones = array(
        'compression'           => \Memcached::OPT_COMPRESSION,     // Activa o desactiva la compresión de la conexión.
        'serializer'            => \Memcached::OPT_SERIALIZER,      // Define el serializador a utilizar para serializar los valores no escalares.
        'prefix_key'            => \Memcached::OPT_PREFIX_KEY,      // Esto puede ser usado para crear un "dominio" de las llaves de elementos.
        'hash'                  => \Memcached::OPT_HASH,            // Especifica el algoritmo hash utilizado para las llaves de elementos.
        'distribution'          => \Memcached::OPT_DISTRIBUTION,    // Especifica el método de distribución de llaves de elementos a los servidores.
        'libketama_compatible'  => \Memcached::OPT_LIBKETAMA_COMPATIBLE, // Activa o desactiva la compatibilidad con el comportamiento libketama. Activar siempre.
        'binary_protocol'       => \Memcached::OPT_BINARY_PROTOCOL, // Activa el uso del protocolo binario.
        'no_block'              => \Memcached::OPT_NO_BLOCK,        // Activa o desactiva la E/S asincrónica Este es el transporte más rápido disponible para las funciones de almacenamiento.
        'tcp_nodelay'           => \Memcached::OPT_TCP_NODELAY,     // Activa o desactiva la característica de no-delay para la conexión de zócalos.
        'connect_timeout'       => 20,                              // En el modo de no-bloqueo esto define el tiempo de espera durante la conexión del zócalo, en ms.
        'retry_timeout'         => \Memcached::OPT_RETRY_TIMEOUT,
        'send_timeout'          => 1000,                            // Tiempo de espera para escritura al zócalo, en ms.
        'recv_timeout'          => \100,                            // Tiempo de espera de la lectura del zócalo, en ms.
        'poll_timeout'          => \Memcached::OPT_POLL_TIMEOUT,    // Tiempo de espera para el sondeo completo de conexiones, en ms.
        'cache_lookups'         => \Memcached::OPT_CACHE_LOOKUPS,   // Activa o desactiva el almacenamiento en caché de consultas de DNS.
        'server_failure_limit'  => \Memcached::OPT_SERVER_FAILURE_LIMIT, // Define el límite de fracasso de los intentos de conexión al servidor. El servidor se retirará después de esta cantidad de fallos de conexión continuos.
    );

    /**
     * Crea una instancia del adaptador y pasa configuraciones.
     *
     * @param array $config Configuraciones
     */
    public function __construct(array $config)
    {
        $this->gestor = new \Memcached();

        foreach($config as $llave => $valor) {
            if (isset($this->opciones[$llave])) {
                $this->gestor->setOptions($this->opciones[$llave], $valor);
            }
        }

        $this->gestor->addServers($config['servidores']);
    }

    /**
     * Guarda los datos de la llave en el servidor cache.
     *
     * @param string $llave
     * @param mixed $valor
     * @param int $expiracion Tiempo en segundos (por defecto es 0 que significa ilimitado)
     *
     * @return bool
     */
    public function guardar($llave, $valor, $expiracion = 0)
    {
        return $this->gestor->set($llave, $valor, $expiracion);
    }

    /**
     * Obtiene el valor de la llave argumentada.
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
     * @return bool
     */
    public function eliminar($llave)
    {
        return $this->gestor->delete($llave);
    }

    /**
     * Limpia el cache del gestor.
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
        $this->gestor->get($llave);
        if ($this->gestor->getResultCode() == \Memcached::RES_NOTFOUND) {
            return false;
        } else {
            return true;
        }
    }
}
