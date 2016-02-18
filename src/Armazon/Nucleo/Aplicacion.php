<?php

namespace Armazon\Nucleo;

use Armazon\Mvc\Controlador;
use Closure;
use Armazon\Bd\Relacional;
use Armazon\Http\Enrutador;
use Armazon\Http\Peticion;
use Armazon\Http\Respuesta;
use Armazon\Http\Ruta;

/**
 * Aplicación Web
 */
class Aplicacion
{
    /** @var self */
    protected static $instancia;

    protected $componentes = [];

    /** @var Peticion */
    protected $peticion;
    /** @var Enrutador */
    protected $enrutador;
    protected $bd_relacional = 'bd';
    private $ambiente = 'desarrollo';
    private $archivo_rutas;
    private $uri_base = '/';
    private $dir_app;
    private $dir_autocargado = [];
    private $zona_tiempo;
    private $codificacion;
    public $nombre = 'armazon';
    private $preparada = false;
    private $erroresHttp = [
        100 => 'Continúa',
        101 => 'Cambiando protocolo',
        200 => 'OK',
        201 => 'Creado',
        202 => 'Aceptado',
        203 => 'Información No Oficial',
        204 => 'Sin Contenido',
        205 => 'Contenido Para Recargar',
        206 => 'Contenido Parcial',
        300 => 'Múltiples Posibilidades',
        301 => 'Mudado Permanentemente',
        302 => 'Mudado Temporalmente',
        303 => 'Vea Otros',
        304 => 'No Modificado',
        305 => 'Utilice un Proxy',
        306 => 'Sin Uso',
        307 => 'Redirección Temporal',
        400 => 'Solicitud Incorrecta',
        401 => 'No Autorizado',
        402 => 'Pago Requerido',
        403 => 'Prohibido',
        404 => 'No Encontrado',
        405 => 'Método No Permitido',
        406 => 'No Aceptable',
        407 => 'Proxy Requerido',
        408 => 'Tiempo de Espera Agotado',
        409 => 'Conflicto',
        410 => 'Ya No Disponible',
        411 => 'Requiere Longitud',
        412 => 'Falló Precondición',
        413 => 'Entidad de Solicitud Demasiado Larga',
        414 => 'URL de Solicitud Demasiado Largo',
        415 => 'Tipo de Medio No Soportado',
        416 => 'Rango Solicitado No Disponible',
        417 => 'Falló Expectativa',
        500 => 'Error Interno de Servidor',
        501 => 'No implementado',
        502 => 'Pasarela Incorrecta',
        503 => 'Servicio No Disponible',
        504 => 'Tiempo de Espera en la Pasarela Agotado',
        505 => 'Versión de HTTP No Soportada',
    ];

    // METODOS PARA EL MANEJO DE LA INSTANCIA --------------------------------------------------------------------------

    protected function __construct()
    {
        spl_autoload_register([$this, 'autoCargadorInterno'], true, true);
    }

    /**
     * Autocarga las clases que se usan durante la ejecución interna de la aplicación.
     *
     * @param string $clase
     *
     * @return bool
     */
    private function autoCargadorInterno(string $clase): bool
    {
        // Quitamos la base de la clase
        $clase = ltrim($clase, '\\');

        foreach ($this->dir_autocargado as $dir) {
            // Preparamos nombre del archivo que puede contener la clase
            $archivo = $dir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $clase) . '.php';

            // Cargamos el archivo de la clase en caso de existir
            if (is_file($archivo)) {
                include $archivo;

                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve instancia única de la aplicación.
     *
     * @return Aplicacion
     */
    public static function instanciar(): self
    {
        if (isset(self::$instancia)) {
            return self::$instancia;
        } else {
            return self::$instancia = new self();
        }
    }

    // METODOS PARA EL MANEJO DE COMPONENTES ---------------------------------------------------------------------------

    /**
     * Registra un componente dentro de la aplicación.
     * Los componentes deben ser definidos usando funciones anónimas.
     *
     * @param string $nombre Nombre del componente
     * @param Closure $definicion Función anónima que devuelve el componente
     * @param bool $singleton Define si solo debe existir una sola instancia del componente
     *
     * @throws \InvalidArgumentException Previene el reemplazo de un componente ya activado
     */
    public function registrarComponente(string $nombre, Closure $definicion, bool $singleton = true)
    {
        if (isset($this->componentes[$nombre]['instancia'])) {
            throw new \InvalidArgumentException("El componente '{$nombre}' ya fue registrado y activado.");
        }

        $this->componentes[$nombre]['definicion'] = $definicion;
        $this->componentes[$nombre]['singleton'] = $singleton;
    }

    /**
     * Activa y devuelve un componente registrado.
     *
     * @param string $nombre Nombre del componente.
     * @param bool $nueva_instancia Define si debe activarse una nueva instancia del componente.
     *
     * @return mixed La instancia del componente.
     * @throws \InvalidArgumentException Si el componente no está registrado
     */
    public function obtenerComponente(string $nombre, bool $nueva_instancia = false)
    {
        if (!isset($this->componentes[$nombre])) {
            throw new \InvalidArgumentException("El componente '{$nombre}' no está registrado.");
        }

        // Devolvemos instancia del componente en caso de haber sido activado o según solicitud
        if (isset($this->componentes[$nombre]['instancia']) && !$nueva_instancia) {
            return $this->componentes[$nombre]['instancia'];
        }

        if (isset($this->componentes[$nombre]['singleton']) && $this->componentes[$nombre]['singleton']) {
            if ($nueva_instancia) {
                return $this->componentes[$nombre]['definicion']($this);
            } else {
                return $this->componentes[$nombre]['instancia'] = $this->componentes[$nombre]['definicion']();
            }
        } else {
            return $this->componentes[$nombre]['definicion']();
        }
    }

    /**
     * Comprueba si un componente está registrado.
     *
     * @param string $nombre Nombre del componente.
     *
     * @return bool
     */
    public function existeComponente($nombre): bool
    {
        return isset($this->componentes[$nombre]);
    }

    /**
     * Anula un componente registrado.
     *
     * @param string $nombre Nombre del componente.
     */
    public function anularComponente($nombre)
    {
        if (isset($this->componentes[$nombre])) {
            unset($this->componentes[$nombre]);
        }
    }

    /**
     * Vincula el nombre del componente Bd/Relacional para el uso en modelos relacionales.
     *
     * @param string $nombre Nombre del componente Bd/Relacional.
     */
    public function vincularBdRelacional(string $nombre)
    {
        $this->bd_relacional = $nombre;
    }

    /**
     * Devuelve el componente Bd/Relacional vinculado.
     *
     * @return Relacional
     */
    public function obtenerBdRelacional(): Relacional
    {
        return $this->obtenerComponente($this->bd_relacional);
    }

    // METODOS PARA EL MANEJO DE OPCIONES ------------------------------------------------------------------------------

    /**
     * Define la Zona Tiempo (huso horario) de la aplicación.
     *
     * @param string $zona_tiempo
     */
    public function definirZonaTiempo(string $zona_tiempo)
    {
        if (!date_default_timezone_set($zona_tiempo)) {
            throw new \InvalidArgumentException('La zona de tiempo introducida es inválida.');
        }

        $this->zona_tiempo = $zona_tiempo;
    }

    /**
     * Devuelve el huso horario de la aplicación.
     *
     * @return string
     */
    public function obtenerZonaTiempo(): string
    {
        return date_default_timezone_get();
    }

    /**
     * Define la codificación de la aplicación.
     *
     * @param string $codificacion
     */
    public function definirCodificacion(string $codificacion)
    {
        mb_internal_encoding($codificacion);

        if ($codificacion == 'UTF-8') {
            mb_detect_order('UTF-8,ISO-8859-1');
        }

        ini_set('default_charset', strtolower($codificacion));

        $this->codificacion = $codificacion;
    }

    /**
     * Devuelve la codificación de la aplicación.
     *
     * @return string
     */
    public function obtenerCodificacion(): string
    {
        return $this->codificacion;
    }

    /**
     * Define la base del URI que segmentará la aplicación
     *
     * @param string $uri
     */
    public function definirUriBase(string $uri)
    {
        // Agregamos / al inicio en caso necesario
        if (strpos($uri, '/') !== 0) {
            $uri = '/' . $uri;
        }

        // Quitamos / al final del URI
        if ($uri !== '/' && substr($uri, -1, 1) == '/') {
            $uri = substr($uri, 0, -1);
        }

        $this->uri_base = $uri;
    }

    /**
     * Define el directorio base de la aplicación.
     *
     * @param string $directorio
     */
    public function definirDirApp(string $directorio)
    {
        $this->dir_app = realpath($directorio);

        $this->dir_autocargado[] = $this->dir_app . DIRECTORY_SEPARATOR . 'controladores';
        $this->dir_autocargado[] = $this->dir_app . DIRECTORY_SEPARATOR . 'modelos';
    }

    /**
     * Devuelve el directorio base de la aplicación.
     *
     * @return string
     */
    public function obtenerDirApp(): string
    {
        return $this->dir_app;
    }

    /**
     * Define el ambiente de corrida.
     * Puede ser desarrollo, produccion o prueba.
     *
     * @param string $ambiente
     */
    public function definirAmbiente(string $ambiente)
    {
        $this->ambiente = $ambiente;
    }

    /**
     * Define el nombre de la aplicación.
     *
     * @param string $nombre
     */
    public function definirNombre(string $nombre)
    {
        $this->nombre = $nombre;
    }

    /**
     * Registra un directorio al autocargador de clases de la aplicación.
     *
     * @param string $directorio
     */
    public function registrarDirAutoCargado(string $directorio)
    {
        $this->dir_autocargado[] = realpath($directorio);
    }

    public function definirArchivoRutas($archivo)
    {
        $this->archivo_rutas = realpath($archivo);
    }

    // METODOS PARA LA EJECUCIÓN DE LA APLICACIÓN ----------------------------------------------------------------------

    /**
     * Prepara la aplicación para procesar las peticiones http.
     */
    public function preparar()
    {
        if (!$this->preparada) {
            // Validamos requisitos míminos
            if (!isset($this->dir_app)) {
                throw new \RuntimeException('Todavía no define directorio base de la aplicacion.');
            }

            // Validamos presencia de rutas
            if (!isset($this->archivo_rutas)) {
                $this->archivo_rutas = $this->dir_app . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rutas.php';
            }
            if (!is_file($this->archivo_rutas)) {
                throw new \RuntimeException('No fue encontrado el archivo de rutas.');
            }

            // Preparamos enrutador
            $this->enrutador = new Enrutador();
            $this->enrutador->definirUriBase($this->uri_base);
            $this->enrutador->importarRutas(require $this->archivo_rutas);

            // Establecemos que la aplicación ya está preparada
            $this->preparada = true;
        }
    }

    /**
     * Despacha una ruta y devuelve una respuesta.
     *
     * @param Peticion $peticion
     * @param Ruta $ruta
     * @param int $estadoHttp
     *
     * @return Respuesta
     * @throws \RuntimeException
     */
    private function despacharRuta(Peticion $peticion, Ruta $ruta, int $estadoHttp = null): Respuesta
    {
        // Preparamos respuesta a devolver
        $respuesta = new Respuesta();

        if (isset($estadoHttp)) {
            $respuesta->definirEstadoHttp($estadoHttp);
        } else {
            $respuesta->definirEstadoHttp($ruta->estadoHttp);
            $estadoHttp = $ruta->estadoHttp;
        }

        // Si la ruta representa una redirección
        if ($ruta->tipo == 'redir') {
            $respuesta->redirigir($ruta->accion, $ruta->estadoHttp);
        }

        // Si la ruta representa una vista
        if ($ruta->tipo == 'vista') {
            $vista = $this->obtenerComponente('vista');
            $vista->definirPlantilla(null);
            $vista->estado_http = $estadoHttp;

            $respuesta->definirContenido($vista->renderizar($ruta->accion));
        }

        // Si la ruta representa un estado http
        if ($ruta->tipo == 'estado_http') {
            $nueva_ruta = $this->enrutador->buscar('get', $ruta->estadoHttp);

            if ('estado_http' != $nueva_ruta->tipo) {
                return $this->despacharRuta($peticion, $nueva_ruta, $ruta->estadoHttp);
            }
        }

        // Si la ruta representa un llamado de acción a un controlador
        if ($ruta->tipo == 'llamado') {
            $respuesta->definirEstadoHttp($ruta->estadoHttp);

            // Procesamos la acción de la ruta
            list($controlador_nombre, $accion_nombre) = explode('@', $ruta->accion);

            // Preparamos posición del archivo del controlador
            $archivo = $this->dir_app . DIRECTORY_SEPARATOR . 'controladores' . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $controlador_nombre) . '.php';

            // Validamos presencia del archivo del controlador
            if (!is_file($archivo)) {
                throw new \RuntimeException("El controlador '{$controlador_nombre}' no existe.");
            }

            // Incluimos el archivo del controlador
            require_once $archivo;

            // Instanciamos el controlador
            $controlador_clase = $controlador_nombre . 'Controlador';
            $controlador = new $controlador_clase($this, $peticion, $respuesta);

            // Traspasamos parametros de la ruta al controlador
            if (isset($ruta->parametros)) {
                $controlador->parametros = $ruta->parametros;
            }

            // Registramos componente vista al controlador
            $vista = $this->obtenerComponente('vista', true);
            $controlador->registrarVista($vista);

            // Ejecutamos evento de inicio en el controlador
            if ($temp = $controlador->alIniciar($controlador_nombre, $accion_nombre)) {
                if ($temp instanceof Respuesta) {
                    return $temp;
                } elseif ($temp instanceof Ruta) {
                    return $this->despacharRuta($peticion, $temp);
                }
            }
            unset($temp);

            // Validamos presencia de accion
            if (!method_exists($controlador, $accion_nombre)) {
                throw new \RuntimeException("La acción '{$accion_nombre}' no existe dentro del controlador.");
            }

            // Ejecutamos accion
            $temp = $controlador->{$accion_nombre}();

            // Ejecutamos evento de terminacion en el controlador
            $temp = $controlador->alTerminar($temp);

            if ($temp instanceof Respuesta) {
                return $temp;
            } elseif ($temp instanceof Ruta) {
                return $this->despacharRuta($peticion, $temp);
            }
        }

        // Devolvemos la respuesta previamente procesada
        return $respuesta;
    }

    /**
     * Genera una respuesta con el mensaje de error
     *
     * @param Peticion $peticion
     * @param int $estadoHttp
     * @param \Throwable $error
     *
     * @return Respuesta
     */
    public function generarRespuestaError(Peticion $peticion, int $estadoHttp = 500, \Throwable $error = null): Respuesta
    {
        // Preparamos respuesta a arrojar
        $respuesta = new Respuesta();
        if (isset($this->erroresHttp[$estadoHttp])) {
            $respuesta->definirContenido('<h1>' . $this->erroresHttp[$estadoHttp] . '</h1>');
        }
        $respuesta->definirEstadoHttp($estadoHttp);

        if ('desarrollo' == $this->ambiente) {
            $temp = '<pre>';
            $temp .= var_export($error, true);
            $temp .= '</pre>';
            $respuesta->definirContenido($temp);
            return $respuesta;
        }

        // Buscamos estado en las rutas
        $ruta = $this->enrutador->buscar($peticion->metodo, $estadoHttp);

        // Cambiamos la respuesta despachando ruta en caso de ser encontrada
        if ('estado_http' != $ruta->tipo && 404 != $ruta->estadoHttp) {
            $respuesta = $this->despacharRuta($peticion, $ruta, $estadoHttp);
        }

        return $respuesta;
    }

    /**
     * Procesa una petición encontrando la ruta aplicable para luego ejecutar la debida acción.
     *
     * @param Peticion $peticion
     *
     * @return Respuesta
     */
    public function procesarPetición(Peticion $peticion): Respuesta
    {
        try {
            // Verificamos si la aplicación está preparada
            if (!$this->preparada) {
                return $this->generarRespuestaError($peticion, 503);
            }

            // Buscamos la ruta a despachar a traves de la peticion
            $ruta = $this->enrutador->buscar($peticion->metodo, $peticion->uri);

            // Despachamos ruta encontrada
            return $this->despacharRuta($peticion, $ruta);

        } catch (\Throwable $e) {
            return $this->generarRespuestaError($peticion, 500, $e);
        }
    }
}
