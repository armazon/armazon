<?php

namespace Armazon\Http;

/**
 * Envoltura de respuesta http.
 */
class Respuesta
{
    private $mime = [
        'html' => 'text/html',
        'js' => 'application/x-javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'zip' => 'application/zip',
        'texto' => 'text/plain',
        'css' => 'text/css',
        'word' => 'application/msword',
        'excel' => 'application/vnd.ms-excel',
        'binario' => 'application/octet-stream',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'gzip' => 'application/x-gzip',
        'pdf' => 'application/pdf',
    ];
    protected $cabeceras = [
        'X-Powered-By' => ['Armazon'],
        'Content-Type' => ['text/html'],
    ];
    protected $codificacion = 'utf-8';
    protected $estadoHttp = 200;
    protected $tipoContenido = 'html';
    protected $contenido;
    protected $enviado = false;

    /**
     * Devuelve las cabeceras de la respuesta.
     *
     * @return array
     */
    public function obtenerCabeceras()
    {
        return $this->cabeceras;
    }

    /**
     * Define o reemplaza una cabecera a la respuesta.
     *
     * @param string $nombre
     * @param string $valor
     */
    public function definirCabecera($nombre, $valor)
    {
        $this->cabeceras[$nombre] = [$valor];
    }

    /**
     * Agrega una cabecera a la respuesta.
     *
     * @param string $nombre
     * @param string $valor
     */
    public function agregarCabecera($nombre, $valor)
    {
        if (isset($this->cabeceras[$nombre])) {
            $this->cabeceras[$nombre][] = $valor;
        } else {
            $this->cabeceras[$nombre] = [$valor];
        }

    }

    /**
     * Agrega una galleta a la respuesta.
     *
     * @param $nombre
     * @param $valor
     * @param int $expira
     * @param string $camino
     * @param string $dominio
     * @param bool $seguro
     * @param bool $soloHttp
     */
    public function agregarGalleta($nombre, $valor, $expira = 0, $camino = '/', $dominio = null, $seguro = false, $soloHttp = false)
    {
        $extras = '';
        if ($expira) {
            $extras .= ';Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $expira);
        }
        if ($camino) {
            $extras .= ';Path=' . $camino;
        }
        if ($dominio) {
            $extras .= ';Domain=' . $dominio;
        }
        if ($seguro) {
            $extras .= ';Secure';
        }
        if ($soloHttp) {
            $extras .= ';HttpOnly';
        }

        $this->agregarCabecera('Set-Cookie', "{$nombre}={$valor}{$extras}");
    }

    /**
     * @param string $codificacion
     */
    public function definirCodificacion($codificacion) {
        $this->codificacion = $codificacion;
    }

    /**
     * @return string
     */
    public function obtenerCodificacion()
    {
        return $this->codificacion;
    }

    /**
     * @param int $estado
     */
    public function definirEstadoHttp($estado) {
        $this->estadoHttp = $estado;
    }

    /**
     * @return int
     */
    public function obtenerEstadoHttp() {
        return $this->estadoHttp;
    }

    public function definirContenido($contenido) {
        if ('json' == $this->tipoContenido && !is_string($contenido)) {
            $this->contenido = json_encode($contenido);
        } elseif ('xml' == $this->tipoContenido && $contenido instanceof \SimpleXMLElement) {
            $this->contenido = $contenido->asXML();
        } elseif ('xml' == $this->tipoContenido && $contenido instanceof \DOMDocument) {
            $this->contenido = $contenido->saveXML();
        } else {
            $this->contenido = $contenido;
        }
    }

    /**
     * @return string
     */
    public function obtenerContenido() {
        return $this->contenido;
    }

    public function definirTipoContenido($tipo)
    {
        $this->tipoContenido = $tipo;
        if (isset($this->mime[$tipo])) {
            $this->definirCabecera('Content-Type', $this->mime[$tipo]);
        } else {
            $this->definirCabecera('Content-Type', $tipo);
        }
    }

    /**
     * @return string
     */
    public function obtenerTipoContenido()
    {
        return $this->tipoContenido;
    }

    /**
     * Envia la respuesta al navegador usando la forma clásica.
     *
     * @return bool
     */
    public function enviar()
    {
        if ($this->enviado) {
            return false;
        }

        http_response_code($this->estadoHttp);

        // Enviamos cabeceras
        foreach ($this->cabeceras as $cabecera => $valores) {
            foreach ($valores as $valor) {
                header($cabecera . ': ' . $valor, false);
            }
        }

        if (!empty($this->contenido)) {
            echo $this->contenido;
        }

        return $this->enviado = true;
    }

    /**
     * @return bool
     */
    public function fueEnviado()
    {
        return $this->enviado;
    }

    /**
     * Envia un archivo al navegador.
     *
     * @param string $nombre
     */
    public function enviarArchivo($nombre)
    {
        $this->definirCabecera('Pragma', 'public');
        $this->definirCabecera('Expires', '0');
        $this->definirCabecera('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
        $this->definirCabecera('Content-Length', (function_exists('mb_strlen') ? mb_strlen($this->contenido, '8bit') : strlen($this->contenido)));
        $this->definirCabecera('Content-Disposition', 'attachment; filename="' . $nombre . '"');
        $this->definirCabecera('Content-Transfer-Encoding', 'binary');

        $this->enviar();
    }

    /**
     * Redirige el navegador a la URL especificada.
     *
     * @param string $url
     * @param int $estadoHttp
     * @return self
     */
    public function redirigir($url, $estadoHttp = 302)
    {
        $this->definirCabecera('Location', $url);
        $this->definirEstadoHttp($estadoHttp);
        $this->definirContenido(null);
        return $this;
    }
}
