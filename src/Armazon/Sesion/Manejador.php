<?php

namespace Armazon\Sesion;

/**
 * Envoltura para sesion
 */
class Manejador
{
    /** @var AdaptadorInterface */
    private $adaptador;
    private $conf = array(
        'auto_iniciar' => true,
        'validar_ttl' => false, // segundos
        'validar_ip' => false,
        'validar_agente' => false,
        'auto_regenerar' => false,
    );
    private $iniciado = false;
    private $datos;
    private $id;
    private $nombre = 'ssid';


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
            $semilla = uniqid('', true);
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

        if (!$this->adaptador->inicializar()) {
            throw new \RuntimeException('Hubo un error al inicializar el adaptador de sesión.');
        }

        if (!$this->id) {
            $this->id = $this->generarId();
        }

        $this->adaptador->inicializar();
        
        $this->datos = $this->adaptador->leer($this->id);

        // Validamos tiempo de espera en caso solicitado
        if ($this->conf['validar_ttl']) {
            if (isset($this->datos['__ttl']) && $this->datos['__ttl'] < time()) {
                throw new \RuntimeException('La sesión ha expirado.');
            }
            $this->datos['__ttl'] = time() + $this->conf['validar_ttl'];
        }

        $this->iniciado = true;

        if ($this->conf['auto_regenerar']) {
            $this->regenerarId();
        }

        return true;
    }

    /**
     * Termina la sesion actual y guarda los datos en ella.
     */
    public function cerrar()
    {
        if ($this->iniciado) {
            $this->adaptador->escribir($this->id, $this->datos);
            $this->adaptador->cerrar();
            $this->iniciado = false;
        }
    }

    /**
     * Libera y destruye totalmente la sesion y sus variables.
     */
    public function destruir()
    {
        if ($this->iniciado) {
            $this->adaptador->destruir($this->id);
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
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

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
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

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
     * Obtiene una variable de sesion.
     *
     * @param string $llave
     * @param mixed $valorAlterno
     * @return mixed
     */
    public function obtener($llave, $valorAlterno = null)
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

        return isset($this->datos[$llave]) ? $this->datos[$llave] : $valorAlterno;
    }

    /**
     * Guarda una variable de session.
     *
     * @param string $llave Nombre
     * @param mixed $valor Valor
     */
    public function guardar($llave, $valor)
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

        $this->datos[$llave] = $valor;
    }

    /**
     * Elimina una variable de sesion.
     *
     * @param string $llave
     *
     * @return mixed el valor de la variable eliminada o nulo si no existe variable
     */
    public function eliminar($llave)
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

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
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

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
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

        return isset($this->datos[$llave]);
    }

    /**
     * Actualiza el identificador de sesión actual con uno generado más reciente.
     *
     * @return string Devueve el identificador nuevo
     */
    public function regenerarId()
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

        $this->adaptador->destruir($this->id);
        $this->id = $this->generarId();
        $this->adaptador->escribir($this->id, $this->datos);

        return $this->id;
    }

    /**
     * Define el identificador de sesión
     *
     * @param string $id
     */
    public function definirId($id)
    {
        $this->id = $id;
    }

    /**
     * Obtiene el identificador de sesión actual.
     *
     * @return string
     */
    public function obtenerId()
    {
        if (!$this->iniciado) {
            if ($this->conf['auto_iniciar']) {
                $this->iniciar();
            } else {
                throw new \RuntimeException('La sesión no fue iniciada.');
            }
        }

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


    public function estaIniciada()
    {
        return $this->iniciado;
    }
}
