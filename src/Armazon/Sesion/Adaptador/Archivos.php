<?php

namespace Armazon\Sesion\Adaptador;

use Armazon\Sesion\AdaptadorInterface;

class Archivos implements AdaptadorInterface
{
    private $dir;
    private $nombre;

    /**
     * @inheritDoc
     */
    public function __construct(array $conf)
    {
        if (!isset($conf['dir'])) {
            throw new \Exception("El adaptador de sesión 'Archivos' necesita la configuración 'dir'.");
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
        $archivo = $this->dir . DIRECTORY_SEPARATOR . $this->nombre . '_' . $id;

        if (file_exists($archivo)) {
            if (unlink($archivo)) {
                return true;
            }
        }

        return false;
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
    public function abrir($nombre)
    {
        $this->nombre = $nombre;

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
        $archivo = $this->dir . DIRECTORY_SEPARATOR . $this->nombre . '_' . $id;

        if (file_exists($archivo)) {
            return unserialize(file_get_contents($archivo));
        }

        return [];
    }

    /**
     * @inheritDoc
     */
    public function escribir($id, array $datos)
    {
        $archivo = $this->dir . DIRECTORY_SEPARATOR . $this->nombre . '_' . $id;

        return file_put_contents($archivo, serialize($datos)) !== false;
    }

}