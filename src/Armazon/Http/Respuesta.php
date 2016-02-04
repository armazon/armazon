<?php

namespace Armazon\Http;

/**
 * Envoltura de respuesta http.
 */
class Respuesta
{
    private $tipos_mime = [
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
        'X-Powered-By' => 'Armazon'
    ];
    protected $codificacion = 'utf-8';
    protected $estadoHttp = 200;
    protected $tipoContenido = 'html';
    protected $contenido;
    protected $enviado = false;

    /**
     * Agrega una cabecera a la respuesta.
     *
     * @param string $nombre
     * @param string $valor
     */
    public function definirCabecera(string $nombre, string $valor)
    {
        $this->cabeceras[$nombre] = $valor;
    }

    /**
     * Devuelve las cabeceras de la respuesta.
     *
     * @return array
     */
    public function obtenerCabeceras(): array
    {
        return $this->cabeceras;
    }

    /**
     * @param string $codificacion
     */
    public function definirCodificacion($codificacion) {
        $this->$codificacion = $codificacion;
    }

    public function obtenerCodificacion(): string
    {
        return $this->codificacion;
    }

    public function definirEstadoHttp(int $estado) {
        $this->estadoHttp = $estado;
    }

    public function obtenerEstadoHttp():int {
        return $this->estadoHttp;
    }

    public function definirContenido(string $contenido) {
        $this->contenido = $contenido;
    }

    public function obtenerContenido(): string {
        return $this->contenido;
    }

    public function definirTipoContenido($tipo)
    {
        $this->tipoContenido = $tipo;
    }

    public function obtenerTipoContenido(): string
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

        foreach ($this->cabeceras as $cabecera => $valor) {
            header($cabecera . ': ' . $valor);
        }

        header('Content-Type: ' . $this->tipos_mime[$this->tipoContenido] . '; charset=' . $this->codificacion);

        if (!empty($this->contenido)) {
            echo $this->contenido;
        }

        return $this->enviado = true;
    }

    public function fueEnviado(): bool
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
