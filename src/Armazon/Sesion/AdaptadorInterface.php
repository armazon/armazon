<?php

namespace Armazon\Sesion;

/**
 * Interface para adaptadores del componente sesion.
 */
interface AdaptadorInterface
{
    /**
     * AdaptadorInterface constructor.
     *
     * @param array $conf
     */
    public function __construct(array $conf);

    /**
     * Cierra la sesión.
     *
     * @return bool
     */
    public function cerrar();

    /**
     * Destruye una sesión.
     *
     * @param string $id  El identificador de sesión que será destruido.
     *
     * @return bool
     */
    public function destruir($id);

    /**
     * Limpia viejas sesiones.
     *
     * @param int $tiempoVida  Las sesiones que no han actualizado en los últimos segundos de $tiempoVida serán eliminados.
     *
     * @return bool
     */
    public function gc($tiempoVida);

    /**
     * Inicializa sesión abriendo la conexión al adaptador.
     *
     * @param string $nombre  Nombre de la sesión a inicializar
     *
     * @return bool
     */
    public function abrir($nombre);

    /**
     * Recupera los datos de la sesión.
     *
     * @param string $id  El identificador de sesión.
     *
     * @return array
     */
    public function leer($id);

    /**
     * Escribe los datos de sesión.
     *
     * @param string $id  El identificador de sesión.
     * @param array $datos  Los datos de la sesión.
     *
     * @return bool
     */
    public function escribir($id, array $datos);
}
