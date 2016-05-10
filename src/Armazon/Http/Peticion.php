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
    public $uri;
    public $camino;
    public $fragmento;
    public $consulta;
    public $cliente_ip;
    public $cabeceras = [];
    public $parametrosGet = [];
    public $parametros = [];
    public $galletas;
    public $archivos;

    /**
     * Procesa y devuelve los parámetros de la cadena según el formato.
     *
     * @param string $cadena
     * @param string $formato
     * @param array $porDefecto
     *
     * @return array
     */
    public static function procesarParametros(
        $cadena,
        $formato = 'application/x-www-form-urlencoded',
        $porDefecto = []
    ) {
        $resultado = $porDefecto;

        if ('application/x-www-form-urlencoded' == $formato) {
            $resultado = [];

            // Excluimos contenido que no sea usable
            if (strpos($cadena, '?') !== false) {
                $cadena = parse_url($cadena, PHP_URL_QUERY);
            }

            // Separamos los parametros
            $pares = explode('&', $cadena);

            foreach ($pares as $par) {
                $temp = explode('=', $par, 2);
                $nombre = rawurldecode($temp[0]);
                $temp2 = strpos($nombre, '[]');
                if ($temp2 !== false) {
                    $nombre = substr($nombre, 0, $temp2);
                }
                $valor = (isset($temp[1])) ? rawurldecode($temp[1]) : '';

                // Si el nombre ya existe pegamos el valor como matriz
                if (isset($resultado[$nombre])) {
                    # Pegamos varios valores en una matriz
                    if (is_array($resultado[$nombre])) {
                        $resultado[$nombre][] = $valor;
                    } else {
                        $resultado[$nombre] = [$resultado[$nombre], $valor];
                    }

                    // de lo contrario, sólo pegamos el valor simple
                } else {
                    $resultado[$nombre] = $valor;
                }
            }
        }

        if ('application/json' == $formato) {
            $resultado = json_decode($cadena, true);
        }

        return $resultado;
    }

    /**
     * Prepara el formato en que se presenta los archivos subidos.
     *
     * @link http://php.net/manual/es/reserved.variables.files.php#106608
     *
     * @param array $archivos
     * @param bool $inicio
     *
     * @return array
     */
    public static function prepararArchivos(array $archivos, $inicio = true)
    {
        $final = [];
        foreach ($archivos as $nombre => $archivo) {
            // Definimos sub nombre
            $subNombre = $nombre;
            if ($inicio) {
                $subNombre = $archivo['name'];
            }

            $final[$nombre] = $archivo;
            if (is_array($subNombre)) {
                foreach (array_keys($subNombre) as $llave) {
                    $final[$nombre][$llave] = array(
                        'name' => $archivo['name'][$llave],
                        'type' => $archivo['type'][$llave],
                        'tmp_name' => $archivo['tmp_name'][$llave],
                        'error' => $archivo['error'][$llave],
                        'size' => $archivo['size'][$llave],
                    );
                    $final[$nombre] = self::prepararArchivos($final[$nombre], false);
                }
            }
        }

        return $final;
    }

    /**
     * Crea una instancia de petición usando como base las variables globales de PHP.
     *
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
        if ($metodo != 'GET' && isset($_SERVER['HTTP_CONTENT_TYPE'])) {
            $parametros = self::procesarParametros(file_get_contents('php://input'), $_SERVER['HTTP_CONTENT_TYPE'], $_POST);
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

        // Preparamos galletas
        $galletas = [];
        if (!empty($_COOKIE)) {
            $galletas = $_COOKIE;
        }

        // Preparamos archivos
        $archivos = [];
        if (!empty($_FILES)) {
            $archivos = self::prepararArchivos($_FILES);
        }

        // Iniciamos instancia de petición con los datos ya preparados
        $peticion = new Peticion($url, $metodo, $parametros, $cabeceras, $galletas, $archivos);


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

    /**
     * Crea una isntancia de petición usando como base la petición de Swoole.
     *
     * @param \swoole_http_request $req
     *
     * @return self
     */
    public static function crearDesdeSwoole(\swoole_http_request $req)
    {
        // Preparamos Metodo
        $metodo = $req->server['request_method'];

        // Preparamos URL
        $url = 'http';
        if (!empty($req->server['https']) && $req->server['https'] !== 'off') {
            $url .= 's';
        } elseif ($req->server['server_port'] == 443) {
            $url .= 's';
        }

        $url .= '://' . $req->header['host'];
        $url .= $req->server['request_uri'];
        if (isset($req->server['query_string'])) {
            $url .= '?' . $req->server['query_string'];
        }

        // Preparamos parámetros según método
        $parametros = [];
        if (!empty($req->post)) {
            $parametros = $req->post;
        }
        if ($metodo != 'GET' && isset($req->header['content-type'])) {
            $parametros = self::procesarParametros($req->rawContent(), $req->header['content-type'], $parametros);
        }

        // Preparamos cabeceras
        $cabeceras = [];
        foreach($req->header as $nombre => $valor) {
            $nombre = str_replace(' ', '-', ucwords(str_replace('-', ' ', $nombre)));
            if (isset($cabeceras[$nombre])) {
                if (is_array($cabeceras[$nombre])) {
                    $cabeceras[$nombre][] = $valor;
                } else {
                    $cabeceras[$nombre] = [$cabeceras[$nombre], $valor];
                }
            } else {
                $cabeceras[$nombre] = $valor;
            }
        }


        // Preparamos galletas
        $galletas = [];
        if (!empty($req->cookie)) {
            $galletas = $req->cookie;
        }

        // Preparamos arhivos
        $archivos = [];
        if (!empty($req->files)) {
            $archivos = $req->files;
        }

        // Iniciamos instancia de petición con los datos ya preparados
        $peticion = new Peticion($url, $metodo, $parametros, $cabeceras, $galletas, $archivos);

        // Preparamos IP del cliente
        $peticion->cliente_ip = $req->server['remote_addr'];
        if (isset($req->server['x-client-ip'])) {
            $peticion->cliente_ip = $req->server['x-client-ip'];
        } elseif (isset($req->server['x-real-ip'])) {
            $peticion->cliente_ip = $req->server['x-real-ip'];
        } elseif (isset($_SERVER['x-forwarded-for'])) {
            $peticion->cliente_ip = trim(explode(',', $_SERVER['x-forwarded-for'])[0]);
        }

        // Retornamos instancia iniciada
        return $peticion;
    }

    public function __construct(
        $url,
        $metodo = 'GET',
        array $parametros = null,
        array $cabeceras = null,
        array $galletas = null,
        array $archivos = null
    ) {
        // Procesamos URL
        $url_procesada = parse_url($url);

        // Preparamos Método
        $this->metodo = $metodo;

        // Preparamos Esquema
        $this->esquema = 'http';
        if (isset($url_procesada['scheme'])) {
            $this->esquema = $url_procesada['scheme'];
        }

        // Preparamos Host
        if (isset($url_procesada['host'])) {
            $this->host = $url_procesada['host'];
        }

        // Preparamos Puerto
        $this->puerto = 80;
        if (isset($url_procesada['port'])) {
            $this->puerto = $url_procesada['port'];
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
            $this->parametrosGet = self::procesarParametros($url_procesada['query']);
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
    public function definirCabecera($nombre, $valor)
    {
        $this->cabeceras[$nombre] = $valor;
    }

    /**
     * Obtiene cabecera solicitada dentro de la petición, en caso de exitir.
     *
     * @param string $nombre
     * @param mixed $porDefecto
     *
     * @return mixed
     */
    public function obtenerCabecera($nombre, $porDefecto = null)
    {
        return isset($this->cabeceras[$nombre]) ? $this->cabeceras[$nombre] : $porDefecto;
    }

    /**
     * Devuelve las cabeceras de la petición.
     *
     * @return array
     */
    public function obtenerCabeceras()
    {
        return $this->cabeceras;
    }

    /**
     * Detecta si la petición se hizo a traves de ajax.
     *
     * @return bool
     */
    public function esAjax()
    {
        if (isset($this->cabeceras['X-Requested-With']) && strtolower($this->cabeceras['X-Requested-With']) == 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    /**
     * Detecta si la petición es segura.
     *
     * @return bool
     */
    public function esSegura()
    {
        if ($this->esquema == 'https' || $this->puerto == 443) {
            return true;
        }

        return false;
    }

    /**
     * Valida y sanea un valor según el filtro aplicado.
     *
     * @param mixed $valor
     * @param string $filtro
     *
     * @return mixed
     */
    public function filtrarVar($valor, $filtro)
    {
        if ($filtro == 'email') {
            $dotString = '(?:[A-Za-z0-9!#$%&*+=?^_`{|}~\'\\/-]|(?<!\\.|\\A)\\.(?!\\.|@))';
            $quotedString = '(?:\\\\\\\\|\\\\"|\\\\?[A-Za-z0-9!#$%&*+=?^_`{|}~()<>[\\]:;@,. \'\\/-])';
            $ipv4Part = '(?:[0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])';
            $ipv6Part = '(?:[A-fa-f0-9]{1,4})';
            $fqdnPart = '(?:[A-Za-z](?:[A-Za-z0-9-]{0,61}?[A-Za-z0-9])?)';
            $ipv4 = "(?:(?:{$ipv4Part}\\.){3}{$ipv4Part})";
            $ipv6 = '(?:' .
                "(?:(?:{$ipv6Part}:){7}(?:{$ipv6Part}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){6}(?::{$ipv6Part}|:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){5}(?:(?::{$ipv6Part}){1,2}|:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){4}(?:(?::{$ipv6Part}){1,3}|(?::{$ipv6Part})?:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){3}(?:(?::{$ipv6Part}){1,4}|(?::{$ipv6Part}){0,2}:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){2}(?:(?::{$ipv6Part}){1,5}|(?::{$ipv6Part}){0,3}:{$ipv4}|:))" . '|' .
                "(?:(?:{$ipv6Part}:){1}(?:(?::{$ipv6Part}){1,6}|(?::{$ipv6Part}){0,4}:{$ipv4}|:))" . '|' .
                "(?::(?:(?::{$ipv6Part}){1,7}|(?::{$ipv6Part}){0,5}:{$ipv4}|:))" .
                ')';
            $fqdn = "(?:(?:{$fqdnPart}\\.)+?{$fqdnPart})";
            $local = "({$dotString}++|(\"){$quotedString}++\")";
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
            return filter_var($valor, FILTER_VALIDATE_FLOAT);
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
     * @param string $nombre Nombre del parámetro
     * @param mixed $porDefecto El valor del parámetro por defecto
     * @param string $filtro Aplica un filtro al valor obtenido
     * @see obtenerParam
     *
     * @return bool|mixed  El valor del parámetro o falso en caso de fallar el filtro
     */
    public function obtenerGet($nombre, $porDefecto = null, $filtro = null)
    {
        if (isset($this->parametrosGet[$nombre])) {
            if (isset($filtro) && !$this->filtrarVar($this->parametrosGet[$nombre], $filtro)) {
                return false;
            }

            return $this->parametrosGet[$nombre];
        }

        return $porDefecto;
    }

    /**
     * Devuelve el valor del parámetro llamado.
     * Si el parámetro POST no existe, se devolverá el segundo parámetro de este método.
     *
     * @param string $nombre Nombre del parámetro
     * @param mixed $porDefecto El valor del parámetro por defecto
     * @param string $filtro Aplica un filtro al valor obtenido
     * @see obtenerGet
     *
     * @return mixed  El valor del parámetro o falso en caso de fallar el filtro
     */
    public function obtenerParam($nombre, $porDefecto = null, $filtro = null)
    {
        if (isset($this->parametros[$nombre])) {
            if (isset($filtro) && !$this->filtrarVar($this->parametros[$nombre], $filtro)) {
                return false;
            }

            return $this->parametros[$nombre];
        }

        return $porDefecto;
    }
}
