<?php

namespace Armazon\Bd;

use Armazon\Cache\AdaptadorInterface;
use Armazon\Nucleo\Aplicacion;

/**
 * Envoltura de PDO para trabajar Bases de Datos Relacionales.
 */
class Relacional
{
    /** @var Aplicacion */
    private $app;

    /** @var \PDO */
    private $componentePdo;

    /** @var AdaptadorInterface */
    private $componenteCache;

    private $sentencia;
    private $cacheActivado = false;
    private $cacheTtl;
    private $cacheLlave;
    private $cacheSobrescribir = false;
    private $cacheResultado;
    private $depurar = false;

    /**
     * Constructor con configuraciones.
     *
     * @param Aplicacion $app
     * @param array $config
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct(Aplicacion $app, array $config)
    {
        // Inyectamos la instancia de aplicación
        $this->app = $app;

        // Verificamos configuraciones requeridas
        if (
            !isset($config['usuario'],
            $config['contrasena'],
            $config['dsn'][0][0],
            $config['dsn'][0][1])
        ) {
            throw new \InvalidArgumentException('Faltan configuraciones requeridas o algunas son inválidas.', 4006);
        }

        if (!empty($config['depurar'])) {
            $this->depurar = true;
        }

        // Calculamos orden de conexiones segun peso de cada dsn
        $pesos = [];
        foreach ($config['dsn'] as $llave => $dsn) {
            $pesos[$llave] = $dsn[1];
        }
        unset($llave, $dsn);
        $pesos_total = array_sum($pesos);
        foreach ($pesos as $llave => $peso) {
            $pesos[$llave] = ($peso / $pesos_total) * mt_rand(1, 5);
        }
        unset($llave, $peso);
        arsort($pesos);
        $pesos = array_keys($pesos);

        // Nos conectamos al servidor disponible segun orden
        $conectado = false;
        while (!$conectado && count($pesos)) {
            $llave = array_shift($pesos);
            try {
                $this->componentePdo = new \PDO(
                    $config['dsn'][$llave][0],
                    $config['usuario'],
                    $config['contrasena'],
                    [\PDO::ATTR_TIMEOUT => 1]
                );
                $conectado = true;
            } catch (\PDOException $e) {
                if ($e->getCode() == 1045) {
                    throw new \RuntimeException('Acceso denegado a la base de datos relacional.');
                }
            }
        }

        if ($conectado) {
            if ($this->depurar) {
                $this->reportar('Abrimos conexion a la de base de datos relacional.');
            }

            // Ejecutamos el comando inicial en caso necesario
            if (isset($config['comando_inicial'])) {
                $this->componentePdo->exec($config['comando_inicial']);
            }
        } else {
            throw new \RuntimeException('No se pudo abrir conexion a la base de datos.');
        }
    }

    /**
     * Cierra la conexión al servidor antes de destruir la instancia. Por precaución.
     */
    public function __destruct()
    {
        $this->componentePdo = null;
    }

    /**
     * Registra un mensaje visualmente detallado.
     *
     * @param string $mensaje
     * @param mixed $detalle
     */
    private function reportar(string $mensaje, $detalle = null)
    {
        echo '<div style="font-weight: bold; font-family: verdana, arial, helvetica, sans-serif; '
            . 'font-size: 13px; line-height: 16px; color: #000; background-color: #E6E6FF; border: solid 1px #99F; '
            . 'padding: 4px 6px; margin: 10px; position: relative;">' . $mensaje . "<br>"
            . '<pre style="font-weight: normal; font-family: monospace; font-size: 12px;">'
            . print_r($detalle) . '</pre></div>';
    }

    /**
     * Selecciona la base de datos interna.
     *
     * @param string $basedatos
     *
     * @return Relacional
     *
     * @throws \RuntimeException
     */
    public function seleccionarBd(string $basedatos): self
    {
        if ($this->componentePdo->exec('USE ' . $basedatos) !== false) {
            return $this;
        } else {
            throw new \RuntimeException('No se pudo seleccionar base de datos.');
        }
    }

    /**
     * Prepara valor segun tipo especificado.
     *
     * @param mixed $valor Valor a preparar
     * @param string $tipo Tipo de valor pasado: bol, txt, num, def, auto
     *
     * @return string Retorna valor escapado para MySQL
     */
    public function prepararValor($valor, string $tipo = 'auto'): string
    {
        if (is_array($valor)) {

            if (count($valor) == 0) return 'NULL';

            foreach ($valor as $llave => $v) {
                $valor[$llave] = $this->prepararValor($v, $tipo);
            }

            return $valor;

        } else {

            if ('auto' == $tipo) {
                if (is_numeric($valor)) {
                    $tipo = 'num';
                } else {
                    $tipo = 'txt';
                }
            }

            // Retornamos valor boleano
            if ($tipo == 'bol') {
                return ($valor) ? '1' : '0';
            }

            // Retornamos valor nulo
            if ($valor === null || $valor === false) {
                return 'NULL';
            }

            // Retornamos valor textual
            if ($tipo == 'txt') {
                return $this->componentePdo->quote($valor);
            }

            // Retornamos valor numerico
            if ($tipo == 'num') {
                if ($valor === '') return 'NULL';

                return strval(floatval($valor));
            }

            return $valor;
        }
    }

    /**
     * Consulta la sentencia interna y devuelve registros encontrados en el formato solicitado.
     *
     * @param string $indizar_por Campo que desea como índice de registros
     * @param string $agrupar_por Campo que desea como agrupación de registros
     * @param string $clase Nombre de clase que servirá como resultado
     * @param array $clase_args Argumentos para clase a servir
     *
     * @return array|bool
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function obtener(string $indizar_por = null, string $agrupar_por = null, string $clase = null, array $clase_args = [])
    {
        // Se obtiene resultados desde cache según el caso
        if ($this->cacheActivado && $this->cacheResultado !== false) {
            return $this->cacheResultado;
        }

        // Validamos la sentencia a ejecutar
        if (!isset($this->sentencia)) {
            throw new \InvalidArgumentException('La sentencia a consultar se encuentra vacia.');
        }

        // Consultamos la sentencia para obtener datos
        $ti = microtime(true);
        $resultado = $this->componentePdo->query($this->sentencia);

        if ($resultado === false) {

            if ($this->depurar) {
                throw new \RuntimeException('La sentencia [ ' . $this->sentencia . ' ] dió el siguiente error: '
                    . $this->componentePdo->errorInfo() . '.');
            }

            return false;

        } else {

            $final = [];

            if (isset($clase) && $clase !== '') {
                $fila = $resultado->fetchObject($clase, $clase_args);

                if (isset($agrupar_por)) {
                    if (!isset($fila->{$agrupar_por})) {
                        throw new \InvalidArgumentException('El campo de agrupación no está presente en los resultados.');
                    }

                    if (isset($indizar_por)) {
                        if (!isset($fila->{$indizar_por})) {
                            throw new \InvalidArgumentException('El campo de indización no está presente en los resultados.');
                        }

                        while ($fila) {
                            $final[$fila->{$agrupar_por}][$fila->{$indizar_por}] = $fila;
                            $fila = $resultado->fetchObject($clase, $clase_args);
                        }
                    } else {
                        while ($fila) {
                            $final[$fila->{$agrupar_por}][] = $fila;
                            $fila = $resultado->fetchObject($clase, $clase_args);
                        }
                    }
                } else {
                    if (isset($indizar_por)) {
                        if (!isset($fila->{$indizar_por})) {
                            throw new \InvalidArgumentException('El campo de indización no está presente en los resultados.');
                        }

                        while ($fila) {
                            $final[$fila->{$indizar_por}] = $fila;
                            $fila = $resultado->fetchObject($clase, $clase_args);
                        }
                    } else {
                        while ($fila) {
                            $final[] = $fila;
                            $fila = $resultado->fetchObject($clase, $clase_args);
                        }
                    }
                }
            } else {
                $fila = $resultado->fetch(\PDO::FETCH_ASSOC);

                if (isset($agrupar_por)) {
                    if (!isset($fila[$agrupar_por])) {
                        throw new \InvalidArgumentException('El campo de agrupación no está presente en los resultados.');
                    }

                    if (isset($indizar_por)) {
                        if (!isset($fila[$indizar_por])) {
                            throw new \InvalidArgumentException('El campo de indización no está presente en los resultados.');
                        }

                        while ($fila) {
                            $final[$fila[$agrupar_por]][$fila[$indizar_por]] = $fila;
                            $fila = $resultado->fetch(\PDO::FETCH_ASSOC);
                        }
                    } else {
                        while ($fila) {
                            $final[$fila[$agrupar_por]][] = $fila;
                            $fila = $resultado->fetch(\PDO::FETCH_ASSOC);
                        }
                    }
                } else {
                    if (isset($indizar_por)) {
                        if (!isset($fila[$indizar_por])) {
                            throw new \InvalidArgumentException('El campo de indización no está presente en los resultados.');
                        }

                        while ($fila) {
                            $final[$fila[$indizar_por]] = $fila;
                            $fila = $resultado->fetch(\PDO::FETCH_ASSOC);
                        }
                    } else {
                        while ($fila) {
                            $final[] = $fila;
                            $fila = $resultado->fetch(\PDO::FETCH_ASSOC);
                        }
                    }
                }
            }

            // Liberamos recursos de conexión o consulta
            $resultado->closeCursor();
            $resultado = null;

            if ($this->depurar) {
                $this->reportar('Obtenemos registros desde BD en ' . round((microtime(true) - $ti) * 1000, 3)
                    . ' ms. [indice: ' . $indizar_por . ', grupo: ' . $agrupar_por . ']', $this->sentencia);
            }

            // Se guarda resultados en cache según el caso
            if ($this->cacheActivado) {
                $this->componenteCache->guardar($this->cacheLlave, $final, $this->cacheTtl);

                if ($this->depurar) {
                    $this->reportar('Guardamos resultados en cache "' . $this->cacheLlave . '" por ' . $this->cacheTtl . ' seg.');
                }
            }

            return $final;

        }
    }

    /**
     * Devuelve el primer registro generado por la consulta de la sentencia interna.
     *
     * @param string $clase Nombre de clase que servirá como formato de registro
     * @param array $clase_args Argumentos para instanciar clase formato
     *
     * @return array|bool
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function obtenerPrimero(string $clase = null, array $clase_args = [])
    {
        $resultado = $this->obtener(null, null, $clase, $clase_args);

        if ($resultado !== false) {
            if ($this->depurar) {
                $this->reportar('Extraemos primer registro de consulta anterior.', $this->sentencia);
            }

            return array_shift($resultado);
        } else {
            return false;
        }
    }

    /**
     * Ejecuta la sentencia interna.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function ejecutar(): bool
    {
        // Validamos la sentencia a ejecutar
        if (!isset($this->sentencia)) {
            throw new \InvalidArgumentException('La sentencia a ejecutar se encuentra vacia.');
        }

        // Ejecutamos la sentencia
        $ti = microtime(true);
        $resultado = $this->componentePdo->exec($this->sentencia);

        if ($resultado === false) {
            if ($this->depurar) {
                throw new \RuntimeException('La sentencia [ ' . $this->sentencia . ' ] dió el siguiente error: ' . $this->componentePdo->errorInfo() . '.', 103);
            }

            return false;
        } else {
            if ($this->depurar) {
                $this->reportar('Ejecutamos desde BD la siguiente consulta (' . round((microtime(true) - $ti) * 1000, 3) . ' ms.) :' .
                    '<pre style="font-weight: normal; font-family: monospace; font-size: 12px;">' . $this->sentencia . '</pre>');
            }

            return true;
        }
    }

    /**
     * Obtiene el Id del último registro insertado.
     *
     * @return string
     */
    public function ultimoIdInsertado(): string
    {
        return $this->componentePdo->lastInsertId();
    }


    // METODOS PARA MANEJAR SUBCOMPONENTE PDO DE FORMA EXTERNA ---------------------------------------------------------

    /**
     * Prepara la sentencia interna para su uso exterior.
     *
     * @throws \Exception
     *
     * @return \PDOStatement
     */
    public function preparar(): \PDOStatement
    {
        // Validamos la sentencia a preparar
        if (!isset($this->sentencia)) {
            throw new \InvalidArgumentException('La sentencia a preparar se encuentra vacia.');
        }

        return $this->componentePdo->prepare($this->sentencia);
    }

    /**
     * Devuelve el subcomponente PDO para su uso externo.
     *
     * @return \PDO
     */
    public function obtenerPdo(): \PDO
    {
        return $this->componentePdo;
    }

    // METODOS PARA MANEJAR CACHE DE CONSULTAS -------------------------------------------------------------------------

    /**
     * Registra el componente de cache para aplicar sobre consultas.
     *
     * @param AdaptadorInterface $cache
     */
    public function registrarCache(AdaptadorInterface $cache)
    {
        $this->componenteCache = $cache;
    }

    /**
     * Implementa cache a la sentencia interna.
     *
     * @param string $llave
     * @param int $ttl Tiempo de vida en segundos
     * @param bool $sobrescribir
     *
     * @return Relacional
     *
     * @throws \RuntimeException
     */
    public function cache(string $llave, int $ttl = 3600, bool $sobrescribir = false): self
    {
        if (!isset($this->componenteCache)) {
            throw new \RuntimeException('Pretende usar el componente de cache pero no fue registrado.');
        }

        $this->cacheActivado = true;
        $this->cacheTtl = $ttl;
        $this->cacheLlave = $llave;
        $this->cacheSobrescribir = $sobrescribir;
        $this->cacheResultado = null;

        if (!$this->cacheSobrescribir) {
            if ($this->depurar) {
                $this->reportar('Obtenemos datos desde cache "' . $llave . '" con expiraci&oacute;n de ' . $ttl . ' seg.');
            }

            $this->cacheResultado = $this->componenteCache->obtener($llave);
        }

        return $this;
    }


    // METODOS PARA MANEJAR TRANSACCIONES ------------------------------------------------------------------------------

    /**
     * Inicia una transaccion.
     *
     * @return bool
     */
    public function iniciarTransaccion(): bool
    {
        return $this->componentePdo->beginTransaction();
    }

    /**
     * Termina la transaccion activa.
     *
     * @return bool
     */
    public function terminarTransaccion(): bool
    {
        return $this->componentePdo->commit();
    }

    /**
     * Verifica si hay transaccion activa.
     *
     * @return bool
     */
    public function enTransaccion(): bool
    {
        return $this->componentePdo->inTransaction();
    }

    /**
     * Cancela la transaccion activa y revierte todos sus ejecuciones previas.
     *
     * @return bool
     */
    public function cancelarTransaccion(): bool
    {
        return $this->componentePdo->rollback();
    }


    // METODOS PARA MANEJAR LA SENTENCIA INTERNA -----------------------------------------------------------------------


    /**
     * Introduce directamente la sentencia interna.
     *
     * @param string $sentencia
     *
     * @return Relacional
     *
     * @throws \RuntimeException si la sentencia es invalida
     */
    public function sql(string $sentencia): self
    {
        $this->reiniciarConsulta();

        $this->sentencia = $sentencia;

        return $this;
    }

    /**
     * Reinicia los datos sobre consultas previas.
     */
    private function reiniciarConsulta()
    {
        $this->sentencia = null;
        $this->cacheActivado = false;
        $this->cacheTtl = null;
        $this->cacheLlave = null;
        $this->cacheResultado = null;
    }

    /**
     * Inicia la sentencia interna con SELECT.
     *
     * @param string|array $campos
     * @param string $tabla
     *
     * @return Relacional
     */
    public function seleccionar($campos, string $tabla): self
    {
        $this->reiniciarConsulta();

        if (is_array($campos)) {
            $this->sentencia = 'SELECT `' . implode('`, `', $campos) . '`';
        } else {
            $this->sentencia = 'SELECT ' . $campos;
        }
        $this->sentencia .= ' FROM ' . $tabla;

        return $this;
    }

    /**
     * Implementa JOIN a la sentencia interna.
     *
     * @param string $tabla Tabla nueva que desea agregar
     * @param string $campo1 Campo usado como relación en la tabla previa
     * @param string $campo2 Campo usado como relación en la tabla nueva
     * @param string $modo Modo de unión (INNER, INNER LEFT, OUTTER, etc.)
     *
     * @return Relacional
     */
    public function unirTabla(string $tabla, string $campo1, string $campo2, string $modo = 'INNER'): self
    {
        $this->sentencia .= " $modo JOIN $tabla ON `$campo1` = `$campo2`";

        return $this;
    }

    /**
     * Prepara los campos que filtran la sentencia interna.
     *
     * @param array|null $params Campos parametrizados
     *
     * @return Relacional
     */
    public function donde(array $params = null): self
    {
        if ($params) {
            $terminos_sql = [];

            foreach ($params as $llave => $valor) {
                // Limpiamos la llave
                $llave = trim($llave);

                // Extraemos operador
                if (false === ($temp = strpos($llave, ' '))) {
                    $restante = $llave;
                } else {
                    $restante = substr($llave, 0, $temp);
                    $operador = substr($llave, $temp + 1);
                }
                if (empty($operador)) {
                    if (is_array($valor)) {
                        $operador = 'IN';
                    } else {
                        $operador = '=';
                    }
                }

                // Extramos campo y su tipo
                if (false !== ($temp = strpos($restante, '|'))) {
                    $tipo = substr($restante, $temp + 1);
                    $campo = substr($restante, 0, $temp);
                } else {
                    $campo = $restante;
                }
                if (empty($tipo)) $tipo = 'auto';

                // Preparamos el valor segun el tipo de campo
                if (is_array($valor)) {
                    $valor_preparado = '(' . implode(',', $this->prepararValor($valor, $tipo)) . ')';
                } else {
                    $valor_preparado = $this->prepararValor($valor, $tipo);
                }

                // Escribimos el termino dentro de los filtros
                $terminos_sql[] = "$campo $operador $valor_preparado";

                // Limpiamos las variables repetitivas
                unset($restante, $campo, $operador, $tipo);
            }

            // Escribimos todos los terminos en formato SQL
            $this->sentencia .= ' WHERE ' . implode(' AND ', $terminos_sql);

        }

        return $this;
    }

    /**
     * Implementa ORDER BY a la sentencia interna.
     *
     * @param string $orden
     *
     * @return Relacional
     */
    public function ordenarPor(string $orden): self
    {
        $this->sentencia .= ' ORDER BY ' . trim($orden);

        return $this;
    }

    /**
     * Implementa GROUP BY a la sentencia interna.
     *
     * @param string $grupo
     *
     * @return Relacional
     */
    public function agruparPor(string $grupo): self
    {
        $this->sentencia .= ' GROUP BY ' . trim($grupo);

        return $this;
    }

    /**
     * Implementa LIMIT a la sentencia interna.
     *
     * @param int $pos
     * @param int $limite
     *
     * @return Relacional
     */
    public function limitar(int $pos, int $limite = null): self
    {
        if ($limite) {
            $this->sentencia .= ' LIMIT ' . $pos . ',' . $limite;
        } else {
            $this->sentencia .= ' LIMIT ' . $pos;
        }

        return $this;
    }

    /**
     * Inicia la sentencia interna con UPDATE.
     *
     * @param string $tabla
     * @param array $params Campos parametrizados
     *
     * @return Relacional
     */
    public function actualizar(string $tabla, array $params): self
    {
        $this->reiniciarConsulta();

        $terminos_sql = [];
        foreach ($params as $llave => $valor) {
            // Extramos campo y su tipo
            $temp = explode('|', $llave);
            $campo = $temp[0];
            if (isset($temp[1])) {
                $tipo = $temp[1];
            } else {
                $tipo = 'auto';
            }

            $terminos_sql[] = '`' . $campo . '` = ' . $this->prepararValor($valor, $tipo);
        }

        $this->sentencia = 'UPDATE ' . $tabla . ' SET ' . implode(', ', $terminos_sql);

        return $this;
    }

    /**
     * Inicia la sentencia interna con INSERT.
     *
     * @param string $tabla Tabla
     * @param array $params Campos parametrizados
     *
     * @return Relacional
     */
    public function insertar(string $tabla, array $params): self
    {
        $this->reiniciarConsulta();

        $columnas = [];
        $valores = [];

        foreach ($params as $llave => $valor) {
            // Extramos campo y su tipo
            $temp = explode('|', $llave);
            $campo = $temp[0];
            if (isset($temp[1])) {
                $tipo = $temp[1];
            } else {
                $tipo = 'auto';
            }

            $columnas[] = '`' . $campo . '`';
            $valores[] = $this->prepararValor($valor, $tipo);
        }

        $this->sentencia = 'INSERT INTO ' . $tabla . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')';

        return $this;
    }

    /**
     * Inicia la sentencia interna con DELETE FROM.
     *
     * @param string $tabla
     *
     * @return Relacional
     */
    public function eliminar(string $tabla): self
    {
        $this->reiniciarConsulta();

        $this->sentencia = 'DELETE FROM ' . $tabla;

        return $this;
    }

    /**
     * Devuelve la sentencia interna hasta el momento.
     *
     * @param bool $formato_html
     *
     * @return string
     */
    public function obtenerSql(bool $formato_html = false): string
    {
        if ($formato_html) {
            return '<code style="font-weight: bold; '
            . 'font-size: 13px; line-height: 16px; color: #000; background-color: #E6E6FF; border: solid 1px #99F; '
            . 'padding: 4px 6px; margin: 10px; position: relative;">' . $this->sentencia . '</code>';
        } else {
            return $this->sentencia;
        }
    }
}
