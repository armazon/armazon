<?php

namespace Armazon\Servidor;

use Armazon\Http\Peticion;

class Swoole extends Base
{
    public $usarDemonio = true;
    public $archivoRegistros;

    private function detectarNucleosCPU(): int
    {
        $cantidad_cpu = 1;
        if (is_file('/proc/cpuinfo')) {
            $cpu_info = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpu_info, $matches);
            $cantidad_cpu = count($matches[0]);
        } else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process) {
                fgets($process);
                $cantidad_cpu = intval(fgets($process));
                pclose($process);
            }
        } else {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process) {
                $output = stream_get_contents($process);
                preg_match('/hw.ncpu: (\d+)/', $output, $matches);
                if ($matches) {
                    $cantidad_cpu = intval($matches[1][0]);
                }
                pclose($process);
            }
        }

        return $cantidad_cpu;
    }

    public function alIniciar($servidor)
    {
        cli_set_process_title($this->nombre . ': master');

        if ($this->archivoPid) {
            file_put_contents($this->archivoPid, $servidor->master_pid);
        }
    }

    public function alDetener()
    {
        if ($this->archivoPid) {
            unlink($this->archivoPid);
        }
    }

    public function alIniciarManager($servidor)
    {
        cli_set_process_title($this->nombre . ': manager');
    }

    public function alIniciarTrabajador()
    {
        cli_set_process_title($this->nombre . ': worker');
    }

    public function alDetenerTrabajador()
    {}

    public function alAsignarTarea($servidor, $idTarea, $deId, $datos)
    {}

    public function alTerminarTarea($servidor, $idTarea, $datos)
    {}

    public function alRecibirPeticion(\swoole_http_request $req, \swoole_http_response $res)
    {
        try {
            // Instanciamos la petición
            $peticion = Peticion::crearDesdeSwoole($req);

            // Obtenemos la respuesta procesando la petición
            $respuesta = $this->app->procesarPetición($peticion);

            // Obtenemos contenido
            $contenido = $respuesta->obtenerContenido();

            foreach ($respuesta->obtenerCabeceras() as $nombre => $valor) {
                $nombre = str_replace(' ', '-', ucwords(str_replace('-', ' ', $nombre)));
                $res->header($nombre, $valor);
            }
            $res->header('Content-Length', strlen($contenido));
            $res->header('Server', 'Armazon HTTP Server');

            // TODO: Implementar la devolución de las galletas

            // Definimos estado http
            $res->status($respuesta->obtenerEstadoHttp());

            // Enviamos la respuesta al navegador
            $res->end($contenido);
        } catch (\Throwable $e) {
            $res->status(500);
            $res->end('<pre>'. var_export($e, true) .'</pre>');
        }
    }

    public function iniciar()
    {
        // Instanciamos servidor Swoole
        $servidor = new \swoole_http_server($this->host, $this->puerto, SWOOLE_PROCESS);

        // Asignamos los eventos del servidor
        $servidor->on('start', [$this, 'alIniciar']);
        $servidor->on('shutdown', [$this, 'alDetener']);
        $servidor->on('managerStart', [$this, 'alIniciarManager']);
        $servidor->on('workerStart', [$this, 'alIniciarTrabajador']);
        $servidor->on('workerStop', [$this, 'alDetenerTrabajador']);
        $servidor->on('request', [$this, 'alRecibirPeticion']);

        if (method_exists($this, 'alAsignarTarea')) {
            $servidor->on('task', [$this, 'alAsignarTarea']);
        }
        if (method_exists($this, 'alTerminarTarea')) {
            $servidor->on('finish', [$this, 'alTerminarTarea']);
        }

        // Detectamos la cantidad de nucleos en el procesador
        $numCpu = $this->detectarNucleosCPU();

        // Preparamos configuraciones del servidor
        $config = [];
        $config['max_request'] = 1000;
        $config['daemonize'] = $this->usarDemonio;
        $config['worker_num'] = $numCpu;
        $config['user'] = $this->usuario;
        $config['group'] = $this->grupo;
        $config['task_worker_max'] = $numCpu;

        if (isset($this->archivoRegistros)) {
            $config['log_file'] = $this->archivoRegistros;
        }

        // Pasamos configuraciones a servidor
        $servidor->set($config);

        // Iniciamos el servidor
        return $servidor->start();
    }
}
