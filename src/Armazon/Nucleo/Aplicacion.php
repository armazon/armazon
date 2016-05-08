<?php

namespace Armazon\Nucleo;

use Closure;
use Armazon\Mvc\Controlador;
use Armazon\Mvc\Vista;
use Armazon\Bd\Relacional;
use Armazon\Http\Enrutador;
use Armazon\Http\Peticion;
use Armazon\Http\Respuesta;
use Armazon\Http\Ruta;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Aplicación Web
 */
class Aplicacion
{
    use EventosTrait;

    /** @var self */
    protected static $instancia;
    /** @var Enrutador */
    protected $enrutador;
    /** @var Run */
    protected $whoops;

    public $id = 'armazon';
    protected $componentes = [];
    protected $bdRelacional = 'bd';
    protected $ambiente = 'desarrollo';
    protected $archivoRutas;
    protected $uriBase = '/';
    protected $dirApp;
    protected $dirAutoCargado = [];
    protected $zonaTiempo;
    protected $codificacion;
    protected $preparada = false;
    protected $erroresHttp = [
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
        301 => 'Movido Permanentemente',
        302 => 'Movido Temporalmente',
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
    private function autoCargadorInterno($clase)
    {
        // Quitamos la base de la clase
        $clase = ltrim($clase, '\\');

        foreach ($this->dirAutoCargado as $dir) {
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
    public static function instanciar()
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
    public function registrarComponente($nombre, Closure $definicion, $singleton = true)
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
    public function obtenerComponente($nombre, $nueva_instancia = false)
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
    public function existeComponente($nombre)
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
    public function vincularBdRelacional($nombre)
    {
        $this->bdRelacional = $nombre;
    }

    /**
     * Devuelve el componente Bd/Relacional vinculado.
     *
     * @return Relacional
     */
    public function obtenerBdRelacional()
    {
        return $this->obtenerComponente($this->bdRelacional);
    }

    // METODOS PARA EL MANEJO DE OPCIONES ------------------------------------------------------------------------------

    /**
     * Define la Zona Tiempo (huso horario) de la aplicación.
     *
     * @param string $zona_tiempo
     */
    public function definirZonaTiempo($zona_tiempo)
    {
        if (!date_default_timezone_set($zona_tiempo)) {
            throw new \InvalidArgumentException('La zona de tiempo introducida es inválida.');
        }

        $this->zonaTiempo = $zona_tiempo;
    }

    /**
     * Devuelve el huso horario de la aplicación.
     *
     * @return string
     */
    public function obtenerZonaTiempo()
    {
        return date_default_timezone_get();
    }

    /**
     * Define la codificación de la aplicación.
     *
     * @param string $codificacion
     */
    public function definirCodificacion($codificacion)
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
    public function obtenerCodificacion()
    {
        return $this->codificacion;
    }

    /**
     * Define la base del URI que segmentará la aplicación
     *
     * @param string $uri
     */
    public function definirUriBase($uri)
    {
        // Agregamos / al inicio en caso necesario
        if (strpos($uri, '/') !== 0) {
            $uri = '/' . $uri;
        }

        // Quitamos / al final del URI
        if ($uri !== '/' && substr($uri, -1, 1) == '/') {
            $uri = substr($uri, 0, -1);
        }

        $this->uriBase = $uri;
    }

    /**
     * Define el directorio base de la aplicación.
     *
     * @param string $directorio
     */
    public function definirDirApp($directorio)
    {
        $this->dirApp = realpath($directorio);

        $this->dirAutoCargado[] = $this->dirApp . DIRECTORY_SEPARATOR . 'controladores';
        $this->dirAutoCargado[] = $this->dirApp . DIRECTORY_SEPARATOR . 'modelos';
    }

    /**
     * Devuelve el directorio base de la aplicación.
     *
     * @return string
     */
    public function obtenerDirApp()
    {
        return $this->dirApp;
    }

    /**
     * Define el ambiente de corrida.
     * Puede ser desarrollo, produccion o prueba.
     *
     * @param string $ambiente
     */
    public function definirAmbiente($ambiente)
    {
        $this->ambiente = $ambiente;
    }

    /**
     * Define el nombre de la aplicación.
     *
     * @param string $nombre
     */
    public function definirNombre($nombre)
    {
        $this->id = $nombre;
    }

    /**
     * Registra un directorio al autocargador de clases de la aplicación.
     *
     * @param string $directorio
     */
    public function registrarDirAutoCargado($directorio)
    {
        $this->dirAutoCargado[] = realpath($directorio);
    }

    public function definirArchivoRutas($archivo)
    {
        $this->archivoRutas = realpath($archivo);
    }

    // METODOS PARA LA EJECUCIÓN DE LA APLICACIÓN ----------------------------------------------------------------------

    /**
     * Prepara la aplicación para procesar las peticiones http.
     */
    public function preparar()
    {
        if (!$this->preparada) {
            // Validamos requisitos míminos
            if (!isset($this->dirApp)) {
                throw new \RuntimeException('Todavía no define directorio base de la aplicacion.');
            }

            // Validamos presencia de rutas
            if (!isset($this->archivoRutas)) {
                $this->archivoRutas = $this->dirApp . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rutas.php';
            }
            if (!is_file($this->archivoRutas)) {
                throw new \RuntimeException('No fue encontrado el archivo de rutas.');
            }

            // Preparamos enrutador
            $this->enrutador = new Enrutador();
            $this->enrutador->definirUriBase($this->uriBase);
            $this->enrutador->importarRutas(require $this->archivoRutas);

            // Preparamos gestores de errores
            $this->whoops = new Run();
            $this->whoops->pushHandler(new PrettyPageHandler());
            $this->whoops->writeToOutput(false);
            $this->whoops->allowQuit(false);

            set_error_handler(function($nivel, $mensaje, $archivo, $lineaArchivo){
                throw new \ErrorException($mensaje, 0, $nivel, $archivo, $lineaArchivo);
            });
            register_shutdown_function(function(){
                $error = error_get_last();
                if (is_array($error)) {
                    throw new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                }
            });

            // Establecemos que la aplicación ya está preparada
            $this->preparada = true;
        }
    }

    /**
     * Devuelve la instancia del controlador usando su nombre.
     *
     * @param string $nombre
     * @param Peticion $peticion
     * @param Respuesta $respuesta
     * @param Ruta $ruta
     *
     * @return Controlador
     * @throws \RuntimeException
     */
    private function obtenerControlador($nombre, Peticion $peticion, Respuesta $respuesta, Ruta $ruta)
    {
        // Preparamos nombre de clase del controlador
        $clase = $nombre . 'Controlador';

        // Verificamos si la clase del controlador existe si no la cargamos manualmente
        if (!class_exists($clase)) {
            // Preparamos camino del archivo del controlador
            $archivo = $this->dirApp . DIRECTORY_SEPARATOR . 'controladores' . DIRECTORY_SEPARATOR
                . str_replace('\\', DIRECTORY_SEPARATOR, $nombre) . '.php';

            // Validamos presencia del archivo del controlador
            if (!is_file($archivo)) {
                throw new \RuntimeException("El archivo del controlador '{$nombre}' no existe.");
            }

            // Incluimos el archivo del controlador
            require_once $archivo;

            // Verificamos si la clase del controlador pudo incluirse despues de la carga manual
            if (!class_exists($clase)) {
                throw new \RuntimeException("La clase del controlador '{$nombre}' no existe.");
            }
        }

        // Devolvemos controlador
        return new $clase($this, $peticion, $respuesta, $ruta);
    }

    /**
     * Despacha una ruta y devuelve su respuesta.
     *
     * @param Peticion $peticion
     * @param Ruta $ruta
     * @param int $estadoHttp
     *
     * @return Respuesta
     * @throws \RuntimeException
     */
    private function despacharRuta(Peticion $peticion, Ruta $ruta, $estadoHttp = null)
    {
        // Inicializamos respuesta a devolver
        $respuesta = new Respuesta();

        // Preparamos estado http
        if (!isset($estadoHttp)) {
            $estadoHttp = $ruta->estadoHttp;
        }
        $respuesta->definirEstadoHttp($estadoHttp);

        // Si la ruta representa una redirección
        if ($ruta->tipo == 'redir') {
            $respuesta->redirigir($ruta->accion, $estadoHttp);
        }

        // Si la ruta representa una vista
        if ($ruta->tipo == 'vista') {
            $vista = new Vista($this);
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
            // Procesamos la acción de la ruta
            list($controladorNombre, $accionNombre) = explode('@', $ruta->accion);

            // Construimos el controlador
            $controlador = $this->obtenerControlador($controladorNombre, $peticion, $respuesta, $ruta);

            // Validamos presencia de accion
            if (!method_exists($controlador, $accionNombre)) {
                throw new \RuntimeException("La acción '{$accionNombre}' no existe dentro del controlador.");
            }

            // Inicializamos el controlador previamente construido
            $controlador->inicializar();

            // Accionamos evento al iniciar la ejecución de la acción
            if ($this->existeEvento('iniciar_accion')) {
                if ($temp = $controlador->accionarEvento('iniciar_accion', $controladorNombre, $accionNombre)) {
                    if ($temp instanceof Respuesta) {
                        return $temp;
                    } elseif ($temp instanceof Ruta) {
                        return $this->despacharRuta($peticion, $temp);
                    }
                }
                unset($temp);
            }

            // Ejecutamos accion
            $resultado = call_user_func_array([$controlador, $accionNombre], (array) $ruta->parametros);

            // Accionamos evento al terminar la ejecución de la acción
            if ($this->existeEvento('terminar_accion')) {
                if ($temp = $controlador->accionarEvento('terminar_accion', $resultado)) {
                    $resultado = $temp;
                }
            }

            // Procesamos resultado de acción y devolvemos respuesta
            if ($resultado) {
                if ($resultado instanceof Respuesta) {
                    return $resultado;
                } elseif (is_string($resultado)) {
                    $respuesta->definirContenido($resultado);
                } elseif ($resultado instanceof Ruta) {
                    return $this->despacharRuta($peticion, $resultado);
                }
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
    public function generarRespuestaError(Peticion $peticion, $estadoHttp = 500, $error = null)
    {
        // Preparamos respuesta a arrojar
        $respuesta = new Respuesta();
        if (isset($this->erroresHttp[$estadoHttp])) {
            $respuesta->definirContenido('<h1>' . $this->erroresHttp[$estadoHttp] . '</h1>');
        }
        $respuesta->definirEstadoHttp($estadoHttp);

        // Mostramos el detalle del error si el ambiente es desarrollo
        if ('desarrollo' == $this->ambiente) {
            $whoops = new Run();
            $whoops_pph = new PrettyPageHandler();
            $whoops_pph->addDataTable('Petición:', (array) $peticion);
            $whoops->pushHandler($whoops_pph);
            $whoops->writeToOutput(false);
            $whoops->allowQuit(false);

            $respuesta->definirContenido($whoops->handleException($error));
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
    public function procesarPetición(Peticion $peticion)
    {
        try {
            // Verificamos si la aplicación está preparada
            if (!$this->preparada) {
                return $this->generarRespuestaError($peticion, 503);
            }

            // Buscamos la ruta a despachar a traves de la peticion
            $ruta = $this->enrutador->buscar($peticion->metodo, $peticion->uri);

            // Despachamos ruta encontrada
            $respuesta = $this->despacharRuta($peticion, $ruta);

            // Accionamos evento al terminar de procesar petición
            if ($this->existeEvento('terminar_peticion')) {
                $this->accionarEvento('terminar_peticion', $respuesta);
            }

            return $respuesta;

        } catch (\Exception $e) {
            return $this->generarRespuestaError($peticion, 500, $e);
        } catch (\Throwable $e) {
            return $this->generarRespuestaError($peticion, 500, $e);
        }
    }

}
