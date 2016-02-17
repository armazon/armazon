<?php

namespace Armazon\Http;

/**
 * Enrutador de alto rendimiento basado en arbol de rutas.
 * No usa expresiones regulares y trata las rutas estáticas en formato llave-valor.
 */
class Enrutador
{
    private $rutas_simples = [];
    private $arbol_rutas = ['ramas' => []];
    private $uri_base = '/';

    /**
     * Convierte el URI en un formato adecuado para trabajar.
     *
     * @param string $uri
     *
     * @return string
     */
    private function normalizarUri(string $uri): string
    {
        // Excluimos los queries del URI
        if (($temp = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $temp);
        }

        // Agregamos / al inicio en caso necesario
        if (strpos($uri, '/') !== 0) {
            $uri = '/' . $uri;
        }

        // Quitamos / al final del URI
        if ($uri !== '/' && substr($uri, -1, 1) == '/') {
            $uri = substr($uri, 0, -1);
        }

        return $uri;
    }

    /**
     * Agrega una nueva ruta al arbol de rutas.
     *
     * @param string|array $metodo
     * @param string $ruta
     * @param mixed $accion
     * @param array $parametros
     * @param int $estado_http
     */
    public function agregarRuta($metodo, string $ruta, $accion, array $parametros = null, int $estado_http = 200)
    {
        // Normalizamos el uri de la ruta argumentada
        $ruta = $this->normalizarUri($ruta);

        // Limpiamos y dividimos la ruta en partes
        $partes = explode('/', trim($ruta, '/ '));

        // Contamos las partes resultantes
        $conteo_partes = count($partes);

        // Declaramos la rama base como actual
        $rama_actual = &$this->arbol_rutas;

        // Preparamos variable que clasifica la ruta como simple
        $es_simple = true;

        // Verificamos si hay suficientes partes para recorrerlas
        if (!($conteo_partes === 1 && $partes[0] === '')) {

            // Recorremos las partes para actualizar la rama actual
            for ($i = 0; $i < $conteo_partes; $i++) {

                if (substr($partes[$i], 0, 1) == ':') {
                    $es_simple = false;
                    if (!isset($rama_actual['ramas']['*'])) {
                        $rama_actual['ramas']['*'] = ['ramas' => [], 'nombre' => substr($partes[$i], 1)];
                    }

                    $rama_actual = &$rama_actual['ramas']['*'];
                } else {
                    if (!isset($rama_actual['ramas'][$partes[$i]])) {
                        $rama_actual['ramas'][$partes[$i]] = ['ramas' => []];
                    }

                    $rama_actual = &$rama_actual['ramas'][$partes[$i]];
                }
            }

        }

        // Preparamos el metodo argumentado
        $metodo = (array)$metodo;

        // Insertamos la ruta a la rama actual
        $rama_actual['ruta'] = $ruta;

        // Preparamos las acciones de la rama actual
        if (!isset($rama_actual['x'])) {
            $rama_actual['x'] = [];
        }

        // Recorremos los metodos para agregar las respectivas acciones
        foreach ($metodo as $m) {
            // Agregamos ruta simple en caso necesario
            if ($es_simple) {
                $this->rutas_simples[$ruta][strtoupper($m)]['accion'] = $accion;
                $this->rutas_simples[$ruta][strtoupper($m)]['estado_http'] = $estado_http;
                if (isset($parametros)) {
                    $this->rutas_simples[$ruta][strtoupper($m)]['parametros'] = $parametros;
                }
            } else {
                $rama_actual['x'][strtoupper($m)]['accion'] = $accion;
                $rama_actual['x'][strtoupper($m)]['estado_http'] = $estado_http;
                if (isset($parametros)) {
                    $rama_actual['x'][strtoupper($m)]['parametros'] = $parametros;
                }
            }
        }

        // Liberamos memoria
        unset($rama_actual);
    }

    /**
     * Agrega una nueva ruta sobre Estado HTTP.
     *
     * @param int $estado_http
     * @param mixed $accion
     */
    public function agregarRutaEstadoHttp(int $estado_http, $accion)
    {
        $this->rutas_simples[$estado_http]['accion'] = $accion;
        $this->rutas_simples[$estado_http]['estado_http'] = $estado_http;
    }

    /**
     * Obtiene el arbol de las rutas.
     *
     * @return array
     */
    public function obtenerArbolRutas(): array
    {
        return $this->arbol_rutas;
    }

    /**
     * Obtiene las rutas estáticas y las de estado http.
     *
     * @return array
     */
    public function obtenerRutasSimples(): array
    {
        return $this->rutas_simples;
    }

    /**
     * Importa las rutas desde un arreglo.
     *
     * @param array $rutas
     */
    public function importarRutas(array $rutas)
    {
        foreach ($rutas as $ruta) {
            if (isset($ruta[4])) {
                $this->agregarRuta($ruta[0], $ruta[1], $ruta[2], $ruta[3], $ruta[4]);
            } elseif (isset($ruta[3])) {
                $this->agregarRuta($ruta[0], $ruta[1], $ruta[2], $ruta[3]);
            } elseif (is_int($ruta[0])) {
                $this->agregarRutaEstadoHttp($ruta[0], $ruta[1]);
            } else {
                $this->agregarRuta($ruta[0], $ruta[1], $ruta[2]);
            }
        }
    }

    /**
     * Define la base que excluiremos del URI a buscar.
     *
     * @param string $uri
     */
    public function definirUriBase(string $uri)
    {
        $this->uri_base = $this->normalizarUri($uri);
    }

    /**
     * Convierte respuesta del método buscar en formato oficial de ruta.
     *
     * @param $valor
     * @param int $estado_http
     *
     * @return Ruta
     */
    private function prepararRuta($valor, int $estado_http = null): Ruta
    {
        // Instanciamos ruta
        $ruta = new Ruta();

        if (is_int($valor)) {
            $ruta->tipo = 'estado_http';
            $ruta->estadoHttp = $valor;
        }

        if (is_int($valor['accion'])) {
            $ruta->tipo = 'estado_http';
            $ruta->estadoHttp = $valor['accion'];
        }


        // Verificamos si el valor representa a una vista
        if (strpos($valor['accion'], '#') === 0) {
            $ruta->tipo = 'vista';
            $ruta->accion = substr($valor['accion'], 1);
            $ruta->estadoHttp = $valor['estado_http'];
        }

        // Verificamos si el valor representa a una redirección
        if (strpos($valor['accion'], '=') === 0) {
            $ruta->tipo = 'redir';
            $ruta->accion = substr($valor['accion'], 1);
            if ($valor['estado_http'] == 303) {
                $ruta->estadoHttp = 303;
            } else {
                $ruta->estadoHttp = 302;
            }
        }

        // Verificamos si el valor representa un llamado a controlador
        if (strpos($valor['accion'], '@') !== false) {
            $ruta->tipo = 'llamado';
            $ruta->estadoHttp = $valor['estado_http'];
            $ruta->accion = $valor['accion'];

            if (isset($valor['parametros'])) {
                $ruta->parametros = $valor['parametros'];
            }
        }

        if (isset($estado_http)) {
            $ruta->estadoHttp = $estado_http;
        }

        return $ruta;
    }

    /**
     * Busca y devuelve una ruta que atienda al URI argumentado.
     *
     * @param string $metodo
     * @param string|int $uri
     *
     * @return Ruta
     */
    public function buscar(string $metodo, $uri): Ruta
    {
//        echo "buscando - {$uri}<br/>";
        // Verificamos si el argumento URI es un estado http
        if (is_int($uri)) {
            // Validamos si el estado http argumentado tiene ruta
            if (isset($this->rutas_simples[$uri])) {
                return $this->prepararRuta($this->rutas_simples[$uri]);
            }

            // Devolvemos ruta predeterminada en caso de no encontrar ruta definida
            return $this->prepararRuta(404);
        }

        // Preparamos método
        $metodo = strtoupper($metodo);

        // Normalizamos el URI
        $uri = $this->normalizarUri($uri);

        // Validamos si realmente debemos trabajar la base del URI
        if ($this->uri_base !== '/') {
            // Validamos si el URI inicia con la base definida
            if (strpos($uri, $this->uri_base) !== 0) {
                return $this->prepararRuta(404);
            }

            // Excluimos la base del URI
            $uri = substr($uri, strlen($this->uri_base));
            if ($uri === '') {
                $uri = '/';
            }
        }

        // Probamos ruteo simple para evitar recorrido de arbol
        if (isset($this->rutas_simples[$uri])) {

            // Validamos si existe acción con el metodo argumentado
            if (!isset($this->rutas_simples[$uri][$metodo])) {
                return $this->prepararRuta(405);
            }

            $this->rutas_simples[$uri][$metodo]['ruta'] = $uri;

            // Devolvemos ruta encontrada
            return $this->prepararRuta($this->rutas_simples[$uri][$metodo]);
        }

        // Limpiamos y dividimos el URI en partes
        $partes = explode('/', trim($uri, '/ '));

        // Contamos las partes resultantes
        $conteo_partes = count($partes);

        // Declaramos la rama base como actual
        $rama_actual = $this->arbol_rutas;

        // Iniciamos parametros para ramas dinámicas
        $parametros = [];

        // Verificamos si hay suficientes partes para recorrerlas
        if (!($conteo_partes === 1 && $partes[0] === '')) {
            // Recorremos las partes para encontrar y actualizar la rama actual
            for ($i = 0; $i < $conteo_partes; $i++) {

                if (isset($rama_actual['ramas'][$partes[$i]])) { // Verificamos si existe rama estatica
                    $rama_actual = $rama_actual['ramas'][$partes[$i]];

                } elseif (isset($rama_actual['ramas']['*'])) { // Verificamos si existe rama dinámica
                    $rama_actual = $rama_actual['ramas']['*'];
                    $parametros[$rama_actual['nombre']] = $partes[$i];

                } else {
                    // Devolvemos ruta NO ENCONTRADO en caso de no encontrar rama
                    return $this->prepararRuta(404);
                }
            }
        }

        // Verificamos si la rama actual tiene accion que podamos llamar
        if (!isset($rama_actual['x'])) {
            // Devolvemos ruta NO ENCONTRADO
            return $this->prepararRuta(404);
        }

        // Verificamos si la rama actual tiene el metodo solicitado
        if (!isset($rama_actual['x'][$metodo])) {
            // Devolvemos ruta METODO NO PERMITIDO
            return $this->prepararRuta(405);
        }

        // Si la ruta ya tenia parametros entonces mezclamos con los parametros dinámicos encontrados
        if (isset($rama_actual['x'][$metodo]['parametros'])) {
            $rama_actual['x'][$metodo]['parametros'] = array_merge($parametros, $rama_actual['x'][$metodo]['parametros']);
        } else {
            $rama_actual['x'][$metodo]['parametros'] = $parametros;
        }

        $rama_actual['x'][$metodo]['ruta'] = $rama_actual['ruta'];
        return $this->prepararRuta($rama_actual['x'][$metodo]);
    }
}
