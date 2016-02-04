<?php

namespace Armazon\Servidor;

use Armazon\Nucleo\Aplicacion;

class Swoole implements ServidorInterface
{
    /** @var Aplicacion */
    public $app;

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

    public function __construct(Aplicacion $app)
    {
        // Inyectamos la aplicación
        $this->app = $app;
    }

    public function iniciar()
    {
        $swoole = new \swoole_http_server($host, $puerto, SWOOLE_PROCESS);

        $swoole->on('request', function(\swoole_http_request $request, \swoole_http_response $response) {
            $response->end("<h1>hello swoole</h1>");
        });
    }

    public function correr()
    {
        // TODO: Implementar metodo correr().
    }
}