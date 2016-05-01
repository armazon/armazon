<?php

namespace Armazon\Cache;

/**
 * Interface para adaptadores del componente caché.
 */
interface AdaptadorInterface {

    /**
     * Crea una instancia del Adaptador y pasa configuraciones.
     *
     * @param array $config Configuraciones
     */
    public function __construct(array $config);

    /**
     * Guarda los datos de la llave en el servidor cache.
     *
     * @param string $llave
     * @param string $valor
     * @param int $expiracion Tiempo en segundos (por defecto es 0 que significa ilimitado)
     *
     * @return bool
     */
    public function guardar($llave, $valor, $expiracion = 0);

    /**
     * Obtiene el valor de la llave.
     *
     * @param string $llave
     *
     * @return mixed
     */
    public function obtener($llave);

    /**
     * Elimina llave del cache y su valor.
     *
     * @param string $llave
     *
     * @return bool
     */
    public function eliminar($llave);

    /**
     * Verifica si la llave existe.
     *
     * @param string $llave
     *
     * @return bool
     */
    public function existe($llave);
}
