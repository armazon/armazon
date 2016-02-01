<?php

namespace Armazon\Http;

/**
 * Envoltura de respuesta http.
 *
 * @version 0.1
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
    public $cabeceras = [
        'X-Powered-By' => 'Armazon'
    ];
    private $codificacion = 'utf-8';
    private $estado_http = 200;
    private $tipo = 'html';
    private $enviado = false;
    private $contenido;

    public function definirCabecera($nombre, $valor)
    {
        $this->cabeceras[$nombre] = $valor;
    }

    /**
     * @param string $codificacion
     */
    public function definirCodificacion($codificacion) {
        $this->$codificacion = $codificacion;
    }

    /**
     * @param int $estado
     */
    public function definirEstadoHttp($estado) {
        $this->estado_http = $estado;
    }

    /**
     * @param string $contenido
     */
    public function definirContenido($contenido) {
        $this->contenido = $contenido;
    }

    public function obtenerContenido() {
        return $this->contenido;
    }

    public function definirTipo($tipo)
    {
        $this->tipo = $tipo;
    }

    /**
     * Envia la respuesta al navegador
     *
     * @return bool
     */
    public function enviar()
    {
        if ($this->enviado) {
            return false;
        }

        http_response_code($this->estado_http);

        foreach ($this->cabeceras as $cabecera => $valor) {
            header($cabecera . ': ' . $valor);
        }

        header('Content-Type: ' . $this->tipos_mime[$this->tipo] . '; charset=' . $this->codificacion);

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
     * Envia un archivo al usuario.
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
     * @param int $estado_http
     * @return self
     */
    public function redirigir($url, $estado_http = 302)
    {
        $this->definirCabecera('Location', $url);
        $this->definirEstadoHttp($estado_http);
        $this->definirContenido(null);
        return $this;
    }
}
