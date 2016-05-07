<?php

namespace Armazon\Sesion;

/**
 * Envoltura para sesion
 */
class Manejador
{
    /** @var AdaptadorInterface */
    private $adaptador;
    private $conf = [
        'auto_iniciar' => true
    ];
    private $iniciado = false;
    private $datos;
    private $id;
    private $nombre = 'armazon_sess';


    public function __construct(AdaptadorInterface $adaptador, array $conf = null)
    {
        $this->adaptador = $adaptador;

        if (isset($conf)) {
            $this->conf = array_merge($this->conf, $conf);
        };
    }

    /**
     * Genera un identificador nuevo usando una semilla aleatoria
     *
     * @return string
     */
    private function generarId()
    {
        if (function_exists('random_bytes')) {
            $semilla = random_bytes(32);
        } elseif (function_exists('mcrypt_create_iv')) {
            $semilla = mcrypt_create_iv(32, MCRYPT_DEV_URANDOM);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $semilla = openssl_random_pseudo_bytes(32);
        } else {
            $semilla = uniqid('armazon', true);
        }

        return hash('sha256', $semilla);
    }

    /**
     * Inicia la sesión si no se ha iniciado todavía.
     *
     * @throws \RuntimeException
     */
    public function iniciar()
    {
        if ($this->iniciado) {
            return true;
        }

        if (!$this->adaptador->abrir($this->nombre)) {
            throw new \RuntimeException('Hubo un error inicializando el adaptador de sesión.');
        }

        if (!$this->id) {
            $this->id = $this->generarId();
        }

        $this->datos = $this->adaptador->leer($this->id);

        return $this->iniciado = true;
    }

    /**
     * Guarda los datos de la sesión actual para luego cerrarla.
     */
    public function escribirCerrar()
    {
        if (!$this->iniciado) {
            throw new \RuntimeException('La sesión no fue iniciada previamente.');
        }

        $this->adaptador->escribir($this->id, $this->datos);
        $this->adaptador->cerrar();
        $this->iniciado = false;
    }

    /**
     * Libera y destruye la sesion actual y su contenido.
     */
    public function destruir()
    {
        if (!$this->iniciado) {
            throw new \RuntimeException('La sesión no fue iniciada previamente.');
        }

        $this->adaptador->destruir($this->id);
        $this->iniciado = false;
    }

    /**
     * Verifica el estatus de la sesión, si la sesión está cerrada arroja una excepción
     * o inicia la sesión según la configuración de inicio automático.
     */
    private function requerirInicio()
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada previamente.');
            }
        }
    }

    /**
     * Define anuncio importante para el usuario.
     *
     * @param string $mensaje
     * @param string $tipo
     */
    public function anunciar($mensaje, $tipo = 'error')
    {
        $this->requerirInicio();

        $this->datos['__anuncio']['mensaje'] = $mensaje;
        $this->datos['__anuncio']['tipo'] = $tipo;
    }

    /**
     * Obtiene anuncio importante para el usuario.
     *
     * @return array|null
     */
    public function obtenerAnuncio()
    {
        $this->requerirInicio();

        if (isset($this->datos['__anuncio']['mensaje'])) {
            return $this->eliminar('__anuncio');
        } else {
            return null;
        }
    }

    /**
     * Elimina anuncio importante.
     *
     * @return mixed
     */
    public function eliminarAnuncio()
    {
        return $this->eliminar('__anuncio');
    }

    /**
     * Obtiene una variable de la sesion.
     *
     * @param string $llave
     * @param mixed $valorAlterno
     * @return mixed
     */
    public function obtener($llave, $valorAlterno = null)
    {
        $this->requerirInicio();

        return isset($this->datos[$llave]) ? $this->datos[$llave] : $valorAlterno;
    }

    /**
     * Guarda una variable en la session.
     *
     * @param string $llave Nombre
     * @param mixed $valor Valor
     */
    public function guardar($llave, $valor)
    {
        $this->requerirInicio();

        $this->datos[$llave] = $valor;
    }

    /**
     * Elimina una variable en la sesion.
     *
     * @param string $llave
     *
     * @return mixed el valor de la variable eliminada o nulo si no existe variable
     */
    public function eliminar($llave)
    {
        $this->requerirInicio();

        if (isset($this->datos[$llave])) {
            $valor = $this->datos[$llave];
            unset($this->datos[$llave]);
            return $valor;
        } else {
            return null;
        }
    }

    /**
     * Elimina todas las variables de sesion.
     */
    public function limpiar()
    {
        $this->requerirInicio();

        $this->datos = array();
    }

    /**
     * Verifica si existe una variable de sesion.
     *
     * @param string $llave
     *
     * @return boolean
     */
    public function existe($llave)
    {
        $this->requerirInicio();

        return isset($this->datos[$llave]);
    }

    /**
     * Actualiza el identificador de sesión actual con uno generado más reciente.
     *
     * @return string Devueve el identificador nuevo
     */
    public function regenerarId()
    {
        $this->requerirInicio();

        $this->adaptador->destruir($this->id);
        $this->id = $this->generarId();
        $this->adaptador->escribir($this->id, $this->datos);

        return $this->id;
    }

    /**
     * Define el identificador de sesión.
     *
     * @param string $id
     */
    public function definirId($id)
    {
        $this->id = $id;
    }

    /**
     * Obtiene el identificador de sesión.
     *
     * @return string
     */
    public function obtenerId()
    {
        return $this->id;
    }

    /**
     * Define el nombre de sesión.
     *
     * @param string $nombre
     */
    public function definirNombre($nombre)
    {
        $this->nombre = $nombre;
    }

    /**
     * Obtiene el nombre de sesión actual.
     *
     * @return string
     */
    public function obtenerNombre()
    {
        return $this->nombre;
    }

    /**
     * Devuelve el estatus de la sesión.
     *
     * @return bool
     */
    public function estaIniciada()
    {
        return $this->iniciado;
    }
}
