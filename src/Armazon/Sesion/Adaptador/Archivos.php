<?php

namespace Armazon\Sesion\Adaptador;

use Armazon\Sesion\AdaptadorInterface;

class Archivos implements AdaptadorInterface
{
    public $dir;

    /**
     * @inheritDoc
     */
    public function __construct(array $conf)
    {
        if (!isset($conf['dir'])) {
            throw new \Exception("El adaptador de sesión 'Archivos' necesita la configuración de ruta.");
        }

        $this->dir = $conf['dir'];
    }


    /**
     * @inheritDoc
     */
    public function cerrar()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function destruir($id)
    {
        $archivo = "{$this->dir}/sess_{$id}";

        if (file_exists($archivo)) {
            unlink($archivo);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function gc($tiempoVida)
    {
        foreach (glob("{$this->dir}/sess_*") as $file) {
            if (filemtime($file) + $tiempoVida < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function inicializar()
    {
        if (!is_dir($this->dir)) {
            if (!@mkdir($this->dir, 0777)) {
                return false;
            }
        }

        if (!is_writable($this->dir)) {
            return false;
        }

        $this->dir = realpath($this->dir);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function leer($id)
    {
        $archivo = $this->dir . '/sess_' . $id;

        if (file_exists($archivo)) {
            return unserialize(file_get_contents($archivo));
        }

        return array();
    }

    /**
     * @inheritDoc
     */
    public function escribir($id, array $datos)
    {
        return file_put_contents($this->dir . '/sess_' . $id, serialize($datos)) !== false;
    }

}