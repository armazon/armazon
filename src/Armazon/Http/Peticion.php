<?php

namespace Armazon\Http;

/**
 * Envoltura de petición http.
 */
class Peticion
{
    public $metodo = 'GET';
    public $esquema = 'http';
    public $host;
    public $puerto = '80';
    public $base;
    public $uri;
    public $camino;
    public $fragmento;
    public $consulta;
    public $cliente_ip;
    public $cabeceras = [];
    public $parametros_get = [];
    public $parametros = [];
    public $galletas;
    public $archivos;

    /**
     * Saca los parámetros de cadenas en formato formulario url.
     *
     * @param string $cadena
     *
     * @return array
     */
    public function sacarParametros(string $cadena): array
    {
        $resultado = [];

        if (strpos($cadena, '?') !== false) {
            $cadena = parse_url($cadena, PHP_URL_QUERY);
        }

        # Separamos los parametros
        $pares = explode('&', $cadena);

        foreach ($pares as $par) {
            $temp = explode('=', $par, 2);
            $nombre = rawurldecode($temp[0]);
            $temp2 = strpos($nombre, '[]');
            if ($temp2 !== false) {
                $nombre = substr($nombre, 0, $temp2);
            }
            $valor = (isset($temp[1])) ? rawurldecode($temp[1]) : '';

            # Si el nombre ya existe pegamos el valor como matriz
            if (isset($resultado[$nombre])) {
                # Pegamos varios valores en una matriz
                if (is_array($resultado[$nombre])) {
                    $resultado[$nombre][] = $valor;
                } else {
                    $resultado[$nombre] = [$resultado[$nombre], $valor];
                }

                # de lo contrario, sólo pegamos el valor simple
            } else {
                $resultado[$nombre] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * @return self
     */
    public static function crearDesdeGlobales()
    {
        // Preparamos Metodo

        if (isset($_SERVER['REQUEST_METHOD'])) {
            $metodo = $_SERVER['REQUEST_METHOD'];
        } else {
            $metodo = 'GET';
        }


        // Preparamos URL

        $url = '';
        if (!empty($_SERVER['HTTPS'])) {
            if ($_SERVER['HTTPS'] !== 'off') {
                $url .= 'https';
            } else {
                $url .= 'http';
            }
        } else {
            if ($_SERVER['SERVER_PORT'] == 443) {
                $url .= 'https';
            } else {
                $url .= 'http';
            }
        }
        $url .= '://';
        if (isset($_SERVER['HTTP_HOST'])) {
            $url .= $_SERVER['HTTP_HOST'];
        } else {
            $url .= $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT'];
        }
        $url .= $_SERVER['REQUEST_URI'];


        // Preparamos parámetros según método

        $parametros = [];
        if ($metodo == 'POST') {
            $parametros = $_POST;
        } elseif ($metodo != 'GET') {
            $temp = file_get_contents('php://input');
            if (function_exists('mb_parse_str')) {
                mb_parse_str($temp, $parametros);
            } else {
                parse_str($temp, $parametros);
            }
        }


        // Preparamos cabeceras

        $cabeceras = [];
        foreach ($_SERVER as $nombre => $valor) {
            if (substr($nombre, 0, 5) == 'HTTP_') {
                $nombre = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($nombre, 5)))));
                $cabeceras[$nombre] = $valor;
            } else if ($nombre == 'CONTENT_TYPE') {
                $cabeceras['Content-Type'] = $valor;
            } else if ($nombre == 'CONTENT_LENGTH') {
                $cabeceras['Content-Length'] = $valor;
            }
        }


        // Iniciamos instancia de petición con los datos ya preparados

        $peticion = new Peticion($url, $metodo, $parametros, $cabeceras, $_COOKIE, $_FILES);


        // Preparamos IP del cliente

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $peticion->cliente_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
            $peticion->cliente_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $peticion->cliente_ip = $_SERVER['REMOTE_ADDR'];
        }


        // Retornamos instancia iniciada

        return $peticion;
    }

    public function __construct(
        string $url,
        string $metodo = 'GET',
        array $parametros = null,
        array $cabeceras = null,
        array $galletas = null,
        array $archivos = null)
    {
        // Procesamos URL
        $url_procesada = parse_url($url);

        // Preparamos Método
        $this->metodo = $metodo;

        // Preparamos Esquema
        if (isset($url_procesada['scheme'])) {
            $this->esquema = $url_procesada['scheme'];
        } else {
            $this->esquema = 'http';
        }

        // Preparamos Host
        if (isset($url_procesada['host'])) {
            $this->host = $url_procesada['host'];
        }

        // Preparamos Puerto
        if (isset($url_procesada['port'])) {
            $this->puerto = $url_procesada['port'];
        } else {
            $this->puerto = 80;
        }

        // Preparamos Camino
        $this->camino = rawurldecode($url_procesada['path']);

        // Preparamos Fragmento
        if (isset($url_procesada['fragment'])) {
            $this->fragmento = $url_procesada['fragment'];
        }

        // Preparamos Query
        if (isset($url_procesada['query'])) {
            $this->consulta = $url_procesada['query'];
            $this->parametros_get = $this->sacarParametros($url_procesada['query']);
        }

        // Preparamos Uri
        $this->uri = $this->camino;
        if (isset($this->consulta)) {
            $this->uri .= '?' . $this->consulta;
        }
        if (isset($this->fragmento)) {
            $this->uri .= '#' . $this->fragmento;
        }

        // Preparamos cabeceras
        if (isset($cabeceras)) {
            $this->cabeceras = $cabeceras;
        }

        // Preparamos parametros
        if (isset($parametros)) {
            $this->parametros = $parametros;
        }

        // Preparamos galletas
        if (isset($galletas)) {
            $this->galletas = $galletas;
        }

        // Preparamos archivos
        if (isset($archivos)) {
            $this->archivos = $archivos;
        }
    }

    /**
     * Define manualmente un cabecera http a la petición.
     *
     * @param string $nombre
     * @param string $valor
     */
    public function definirCabecera(string $nombre, string $valor)
    {
        $cabeceras[$nombre] = $valor;
    }

    /**
     * Obtiene cabecera solicitada dentro de la petición, en caso de exitir.
     *
     * @param string $nombre
     * @param mixed $por_defecto
     *
     * @return mixed
     */
    public function obtenerCabecera(string $nombre, $por_defecto = null)
    {
        return isset($this->cabeceras[$nombre]) ? $this->cabeceras[$nombre] : $por_defecto;
    }

    /**
     * Detecta si la petición se hizo a traves de ajax.
     *
     * @return bool
     */
    public function esAjax(): bool
    {
        if (isset($this->cabeceras['X-Requested-With']) && strtolower($this->cabeceras['X-Requested-With']) == 'xmlhttprequest') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Detecta si la petición es segura.
     *
     * @return bool
     */
    public function esSegura(): bool
    {
        if ($this->esquema == 'https' || $this->puerto == 443) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Detecta el idioma del navegador del usuario.
     *
     * @param string $por_defecto
     *
     * @return string
     */
    public function detectarIdioma(string $por_defecto = 'es'): string
    {
        if (isset($this->cabeceras['Accept-Language'])) {
            // TODO: Revisar la función locale_accept_from_http en el componente Peticion, creo que no funciona sin la extensión Intl.
            if (empty($idioma = locale_accept_from_http($this->cabeceras['Accept-Language']))) {
                return $idioma;
            }
        }

        return $por_defecto;
    }

    /**
     * Valida y sanea un valor según el filtro aplicado.
     *
     * @param mixed $valor
     * @param string $filtro
     *
     * @return mixed
     */
    public function filtrarVar($valor, string $filtro)
    {
        if ($filtro == 'email') {
            $dot_string = '(?:[A-Za-z0-9!#$%&*+=?^_`{|}~\'\\/-]|(?<!\\.|\\A)\\.(?!\\.|@))';
            $quoted_string = '(?:\\\\\\\\|\\\\"|\\\\?[A-Za-z0-9!#$%&*+=?^_`{|}~()<>[\\]:;@,. \'\\/-])';
            $ipv4_part = '(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])';
            $ipv6_part = '(?:[A-fa-f0-9]{1,4})';
            $fqdn_part = '(?:[A-Za-z](?:[A-Za-z0-9-]{0,61}?[A-Za-z0-9])?)';
            $ipv4 = "(?:(?:{$ipv4_part}\\.){3}{$ipv4_part})";
            $ipv6 = '(?:' .
                "(?:(?:{$ipv6_part}:){7}(?:{$ipv6_part}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){6}(?::{$ipv6_part}|:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){5}(?:(?::{$ipv6_part}){1,2}|:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){4}(?:(?::{$ipv6_part}){1,3}|(?::{$ipv6_part})?:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){3}(?:(?::{$ipv6_part}){1,4}|(?::{$ipv6_part}){0,2}:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){2}(?:(?::{$ipv6_part}){1,5}|(?::{$ipv6_part}){0,3}:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6_part}:){1}(?:(?::{$ipv6_part}){1,6}|(?::{$ipv6_part}){0,4}:{$ipv4}|:))" . '|' .
                "(?::(?:(?::{$ipv6_part}){1,7}|(?::{$ipv6_part}){0,5}:{$ipv4}|:))" .
                ')';
            $fqdn = "(?:(?:{$fqdn_part}\\.)+?{$fqdn_part})";
            $local = "({$dot_string}++|(\"){$quoted_string}++\")";
            $domain = "({$fqdn}|\\[{$ipv4}]|\\[{$ipv6}]|\\[{$fqdn}])";
            $pattern = "/\\A{$local}@{$domain}\\z/";
            return preg_match($pattern, $valor, $matches) &&
            (
                !empty($matches[2]) && !isset($matches[1][66]) && !isset($matches[0][256]) ||
                !isset($matches[1][64]) && !isset($matches[0][254])
            );
        }

        if ($filtro == 'entero') {
            return filter_var($valor, FILTER_VALIDATE_INT);
        }

        if ($filtro == 'flotante') {
            if (filter_var($valor, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND)) {
                return filter_var($valor, FILTER_SANITIZE_NUMBER_FLOAT);
            } else {
                return false;
            }
        }

        if ($filtro == 'ip') {
            return filter_var($valor, FILTER_VALIDATE_IP);
        }

        if ($filtro == 'url') {
            return filter_var($valor, FILTER_VALIDATE_URL);
        }

        if ($filtro == 'booleano') {
            return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
        }

        if ($filtro == 'texto_limpio') {
            return trim(filter_var($valor, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW));
        }

        return $valor;
    }

    /**
     * Devuelve el valor del parámetro GET llamado.
     * Si el parámetro GET no existe, se devolverá el segundo parámetro de este método.
     *
     * @param string $nombre  Nombre del parámetro
     * @param mixed $por_defecto  El valor del parámetro por defecto
     * @param string $filtro  Aplica un filtro al valor obtenido
     * @see obtenerParam
     *
     * @return bool|mixed  El valor del parámetro o falso en caso de fallar el filtro
     */
    public function obtenerGet($nombre, $por_defecto = null, string $filtro = null)
    {
        if (isset($this->parametros_get[$nombre])) {
            if (isset($filtro)) {
                return $this->filtrarVar($this->parametros_get[$nombre], $filtro);
            } else {
                return $this->parametros_get[$nombre];
            }
        } else {
            return $por_defecto;
        }
    }

    /**
     * Devuelve el valor del parámetro llamado.
     * Si el parámetro POST no existe, se devolverá el segundo parámetro de este método.
     *
     * @param string $nombre  Nombre del parámetro
     * @param mixed $por_defecto  El valor del parámetro por defecto
     * @param string $filtro  Aplica un filtro al valor obtenido
     * @see obtenerGet
     *
     * @return mixed  El valor del parámetro o falso en caso de fallar el filtro
     */
    public function obtenerParam(string $nombre, $por_defecto = null, string $filtro = null)
    {
        if (isset($this->parametros[$nombre])) {
            if (isset($filtro)) {
                return $this->filtrarVar($this->parametros[$nombre], $filtro);
            } else {
                return $this->parametros[$nombre];
            }
        } else {
            return $por_defecto;
        }
    }
}
