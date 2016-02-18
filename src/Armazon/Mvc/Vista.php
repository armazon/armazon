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

    protected $filtros = [];
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

        // Registramos procesador para convertir texto a titulo
        $this->filtros['t'] = function ($texto) {
            $texto = mb_convert_case($texto, MB_CASE_TITLE);
            $palabras_omitidas = array(
                ' de ', ' un ', ' una ', ' uno ', ' el ', ' la ', ' lo ', ' las ', ' los ', ' de ', ' y ',
                ' o ', ' ni ', ' pero ', ' es ', ' e ', ' si ', ' entonces ', ' sino ', ' cuando ', ' al ',
                ' desde ', ' por ', ' para ', ' en ', ' dentro ', ' afuera ', ' sobre ', ' a ',
                ' adentro ', ' con ', ' su ', ' of ', ' a ', ' an ', ' the ', ' and ', ' or ', ' but ', ' is ',
                ' if ', ' then ', ' when ', ' until ', ' by ', ' for ', ' in ', ' off ', ' with ');
            return trim(str_ireplace($palabras_omitidas, $palabras_omitidas, $texto . ' '));
        };

        // Registramos procesador para convertir texto a mayúsculas
        $this->filtros['u'] = function ($texto) {
            return mb_convert_case($texto, MB_CASE_UPPER);
        };

        // Registramos procesador para convertir texto a minúsculas
        $this->filtros['l'] = function ($texto) {
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
     * Registra un filtro de textos en la vista.
     *
     * @param string $id Identificador del filtro
     * @param callable $fn Función que filtrará el valor
     */
    public function registrarFiltro(string $id, callable $fn)
    {
        $this->filtros[$id] = $fn;
    }

    /**
     * Aplica el filtro solicitado al texto.
     *
     * @param string $filtro
     * @param string $texto
     *
     * @return string
     */
    private function filtrarValor(string $filtro, string $texto): string
    {
        if (isset($this->filtros[$filtro])) {
            $texto = $this->filtros[$filtro]($texto);
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

        // Procesamos los textos dinámicos en caso de ser detectados
        if (strpos($this->contenido, '{{') !== false) {

            preg_match_all('#\{\{([^\{\}]+)\}\}#u', $this->contenido, $ocurrencias);

            foreach ($ocurrencias[1] as $ocurrencia) {
                // Extraemos los filtros encontrados en la ocurrencia
                $filtros = explode('|', $ocurrencia);

                // Extraemos el valor de los filtros
                $texto = array_shift($filtros);

                // Sustituimos si existe una variable en la vista con el nombre del texto
                if (isset($this->{$texto})) {
                    $texto = $this->{$texto};
                }

                // Aplicamos filtros al valor en caso necesario
                if (count($filtros)) {
                    foreach ($filtros as $filtro) {
                        $texto = $this->filtrarValor($filtro, $texto);
                    }
                }

                // Escapamos el texto por cuestiones de seguridad
                $texto = htmlspecialchars($texto, ENT_COMPAT | ENT_DISALLOWED | ENT_HTML5);

                $this->contenido = str_replace('{{' . $ocurrencia . '}}', $texto, $this->contenido);
            }
        }

        $this->renderizado = true;

        return $this->contenido;
    }
}
