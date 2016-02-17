<?php

namespace Armazon\Mvc;

use Armazon\Nucleo\Aplicacion;

/**
 * Capa Vista del patrón MVC.
 */
class Vista extends \stdClass
{
    /** @var Aplicacion */
    private $app;

    private $procesadores = [];
    protected $dirVistas;
    protected $contenido;
    protected $plantilla;
    protected $renderizado = false;

    public function __construct(Aplicacion $app)
    {
        // Inyectamos la instancia de aplicación
        $this->app = $app;

        // Definimos el directorio de las vistas
        $this->dirVistas = $app->obtenerDirApp() . DIRECTORY_SEPARATOR . 'vistas';

        // Registramos procesador para usar variables asignadas
        $this->procesadores['v'] = function ($texto) {
            if (isset($this->{$texto})) {
                $texto = $this->{$texto};
            }

            return $texto;
        };

        // Registramos procesador para escapar los textos
        $this->procesadores['e'] = function ($texto) {
            return htmlspecialchars($texto, ENT_COMPAT | ENT_DISALLOWED | ENT_HTML5);
        };

        // Registramos procesador para convertir texto a titulo
        $this->procesadores['t'] = function ($texto) {
            $texto = mb_convert_case($texto, MB_CASE_TITLE);
            $palabras_omitidas = array(
                ' de ', ' un ', ' una ', ' uno ', ' el ', ' la ', ' lo ', ' las ', ' los ', ' de ', ' y ',
                ' o ', ' ni ', ' pero ', ' es ', ' e ', ' si ', ' entonces ', ' sino ', ' cuando ', ' al ',
                ' desde ', ' por ', ' para ', ' en ', ' off ', ' dentro ', ' afuera ', ' sobre ', ' a ',
                ' adentro ', ' con ', ' su ');
            return trim(str_ireplace($palabras_omitidas, $palabras_omitidas, $texto . ' '));
        };

        // Registramos procesador para convertir texto a mayúsculas
        $this->procesadores['u'] = function ($texto) {
            return mb_convert_case($texto, MB_CASE_UPPER);
        };

        // Registramos procesador para convertir texto a minúsculas
        $this->procesadores['l'] = function ($texto) {
            return mb_convert_case($texto, MB_CASE_LOWER);
        };
    }

    public function definirDirVistas($directorio)
    {
        $temp = realpath($directorio);

        if ($temp === false) {
            throw new \InvalidArgumentException('El directorio solicitado para las vistas no existe.');
        } else {
            $this->dirVistas = $temp;
        }
    }

    public function definirPlantilla($plantilla)
    {
        $this->plantilla = $plantilla;
    }

    public function obtenerPlantilla()
    {
        return $this->plantilla;
    }

    public function importarVariables(array &$variables)
    {
        foreach ($variables as $llave => $valor) {
            $this->{$llave} = $valor;
        }
    }

    private function cargarContenido(string $vista)
    {
        // Preparamos ubicación del archivo de la vista
        $vista = $this->dirVistas . DIRECTORY_SEPARATOR . $vista . '.phtml';

        // Validamos si existe el archivo
        if (!file_exists($vista)) {
            throw new \InvalidArgumentException('No existe la vista "' . $vista . '" en el directorio de vistas.');
        }

        // Devolvemos contenido a traves del contenido del buffer generado al incluir el archivo
        ob_start();
        include $vista;
        return ob_get_clean();
    }

    public function obtenerContenido()
    {
        return $this->contenido;
    }

    public function fueRenderizado()
    {
        return $this->renderizado;
    }

    /**
     * Registra un procesador de textos en la vista.
     *
     * @param string $id Identificador del procesador
     * @param callable $fn Función que procesará el valor
     */
    public function registrarProcesador(string $id, callable $fn)
    {
        $this->procesadores[$id] = $fn;
    }

    /**
     * Aplica el procesador solicitado al texto.
     *
     * @param string $procesador
     * @param string $texto
     *
     * @return string
     */
    private function procesarValor(string $procesador, string $texto): string
    {
        if (isset($this->procesadores[$procesador])) {
            $texto = $this->procesadores[$procesador]($texto);
        }

        return $texto;
    }

    /**
     * Convierte una vista con códigos php y etiquetas propias a puro HTML.
     *
     * @param string $vista
     *
     * @return string
     */
    public function renderizar(string $vista): string
    {
        $this->contenido = $this->cargarContenido($vista);

        if (isset($this->plantilla)) {
            $this->contenido = $this->cargarContenido('plantillas/' . $this->plantilla);
        }

        if (strpos($this->contenido, '{{') !== false) {

            preg_match_all('#\{\{([^\{\}]+)\}\}#u', $this->contenido, $ocurrencias);

            foreach ($ocurrencias[1] as $ocurrencia) {
                // Extraemos los procesadores encontrados en la ocurrencia
                $procesadores = explode('|', $ocurrencia);

                // Extraemos el valor de los procesadores
                $valor = array_pop($procesadores);

                // Aplicamos procesadores al valor en caso necesario
                if (!empty($procesadores)) {
                    foreach ($procesadores as $procesador) {
                        $valor = $this->procesarValor($procesador, $valor);
                    }
                } else {
                    $valor = $this->procesarValor('v', $valor);
                }

                $this->contenido = str_replace('{{' . $ocurrencia . '}}', $valor, $this->contenido);
            }
        }

        $this->renderizado = true;

        return $this->contenido;
    }
}
