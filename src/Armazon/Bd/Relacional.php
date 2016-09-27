<?php

namespace Armazon\Bd;

use Armazon\Nucleo\Excepcion;

/**
 * Envoltura de PDO para trabajar con Bases de Datos Relacionales.
 */
class Relacional
{
    /** @var \PDO */
    private $pdo;
    private $sentencia;
    private $depurar = false;
    private $arrojarExcepciones = false;

    /**
     * Constructor con configuraciones.
     *
     * @param array $config
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config)
    {
        // Verificamos configuraciones requeridas
        if (!isset($config['usuario'], $config['contrasena'], $config['dsn'])) {
            throw new \InvalidArgumentException('Faltan configuraciones requeridas o algunas son inválidas.', 4006);
        }

        if (!empty($config['arrojar_excepciones'])) {
            $this->arrojarExcepciones = true;
        }

        if (!empty($config['depurar'])) {
            $this->depurar = true;
        }

        // Convertimos DSN en arreglo según el caso
        $config['dsn'] = (array)$config['dsn'];

        // Aleatorizamos el orden de los DSN
        if (count($config['dsn']) > 1) {
            shuffle($config['dsn']);
        }

        // Nos conectamos al servidor disponible segun orden
        $conectado = false;
        while (!$conectado && count($config['dsn'])) {
            $dsn = array_shift($config['dsn']);
            try {
                $this->pdo = new \PDO($dsn, $config['usuario'], $config['contrasena'], array(\PDO::ATTR_TIMEOUT => 1));
                $conectado = true;
            } catch (\PDOException $e) {
                if ($this->depurar) {
                    throw $e;
                }
            }
        }

        if ($conectado) {
            // Ejecutamos el comando inicial en caso necesario
            if (isset($config['comando_inicial'])) {
                $this->pdo->exec($config['comando_inicial']);
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
        $this->pdo = null;
    }

    /**
     * Selecciona la base de datos interna.
     *
     * @param string $basedatos
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function seleccionarBd($basedatos)
    {
        if ($this->pdo->exec('USE ' . $basedatos) === false) {
            if ($this->arrojarExcepciones) {
                throw new \RuntimeException('No se pudo seleccionar base de datos.');
            }

            return false;
        }

        return true;
    }

    /**
     * Prepara valor segun tipo especificado.
     *
     * @param mixed $valor Valor a preparar
     * @param string $tipo Tipo de valor pasado: bol, txt, num, def
     * @param bool $permiteVacio Define si permite cadena de texto vacio en vez de nulo
     *
     * @return string Retorna valor escapado para MySQL
     */
    public function prepararValor($valor, $tipo = 'txt', $permiteVacio = false)
    {
        if (is_array($valor)) {
            if (empty($valor)) {
                return 'NULL';
            }

            foreach ($valor as $llave => $v) {
                $valor[$llave] = $this->prepararValor($v, $tipo);
            }

            return $valor;
        }

        // Retornamos valor boleano según el tipo
        if ($tipo == 'bol' || $tipo == 'bool') {
            return ($valor) ? '1' : '0';
        }

        // Detectamos y retornamos valor nulo
        if ($valor === null || $valor === false) {
            return 'NULL';
        }
        if (!$permiteVacio && $valor === '') {
            return 'NULL';
        }

        // Retornamos valor numerico según el tipo
        if ($tipo == 'num' || $tipo == 'int') {
            if ($valor === '') return 'NULL';

            return strval(floatval($valor));
        }

        // Retornamos valor textual como valor predeterminado
        return $this->pdo->quote($valor);
    }

    /**
     * Devuelve la información de error generada por la última sentencia ejecutada.
     *
     * @return array
     */
    public function obtenerError()
    {
        return $this->pdo->errorInfo();
    }

    /**
     * Consulta la sentencia interna y devuelve registros encontrados en el formato solicitado.
     *
     * @param string $indice Campo que desea como índice de registros
     * @param string $agrupacion Campo que desea como agrupación de registros
     * @param string $clase Nombre de clase que servirá como resultado
     * @param array $claseArgs Argumentos para clase a servir
     *
     * @return array|bool
     *
     * @throws Excepcion
     * @throws \InvalidArgumentException
     */
    public function obtener($indice = null, $agrupacion = null, $clase = null, array $claseArgs = [])
    {
        // Validamos la sentencia a ejecutar
        if (empty($this->sentencia)) {
            if ($this->arrojarExcepciones) {
                throw new \InvalidArgumentException('La sentencia a consultar se encuentra vacia.');
            }

            return false;
        }

        // Consultamos la sentencia para obtener datos
        $resultado = $this->pdo->query($this->sentencia);

        if ($resultado === false) {
            if ($this->arrojarExcepciones) {
                throw new Excepcion('La sentencia consultada tuvo un error interno.', [
                    'sentencia' => $this->sentencia,
                    'codigo' => $this->pdo->errorCode(),
                    'info' => $this->pdo->errorInfo(),
                ]);
            }

            return false;
        }

        $final = [];
        $conteo = 0;
        $agrupacionX = '##';

        if (!empty($clase)) {  // Obtenemos objetos

            if ($registro = $resultado->fetchObject($clase, $claseArgs)) {
                // Se valida existencia de campos requeridos según argumentos introducidos
                if (!empty($agrupacion) && !property_exists($registro, $agrupacion)) {
                    throw new Excepcion('El campo de agrupación no está presente en los registros.', [
                        'campo' => $agrupacion,
                    ]);
                }
                if (!empty($indice) && !property_exists($registro, $indice)) {
                    throw new Excepcion('El campo de indización no está presente en los registros.', [
                        'campo' => $agrupacion,
                    ]);
                }

                // Recorremos los registros y los convertimos en objetos
                while ($registro) {
                    if (!empty($agrupacion)) {
                        $agrupacionX = $registro->{$agrupacion};
                    }

                    if (empty($indice)) {
                        $indiceX = $conteo++;
                    } else {
                        $indiceX = $registro->{$indice};
                    }

                    $final[$agrupacionX][$indiceX] = $registro;
                    $registro = $resultado->fetchObject($clase, $claseArgs);
                }
            }

        } else {  // Obtenemos arreglos

            if ($registro = $resultado->fetch(\PDO::FETCH_ASSOC)) {
                // Se valida existencia de campos requeridos según argumentos introducidos
                if (!empty($agrupacion) && !array_key_exists($agrupacion, $registro)) {
                    throw new Excepcion('El campo de agrupación no está presente en los registros.', [
                        'campo' => $agrupacion,
                    ]);
                }
                if (!empty($indice) && !array_key_exists($indice, $registro)) {
                    throw new Excepcion('El campo de indización no está presente en los registros.', [
                        'campo' => $indice,
                    ]);
                }

                // Recorremos los registros y los convertimos en arreglos asociativos
                while ($registro) {
                    if (!empty($agrupacion)) {
                        $agrupacionX = $registro[$agrupacion];
                    }

                    if (empty($indice)) {
                        $indiceX = $conteo++;
                    } else {
                        $indiceX = $registro[$indice];
                    }

                    $final[$agrupacionX][$indiceX] = $registro;
                    $registro = $resultado->fetch(\PDO::FETCH_ASSOC);
                }
            }

        }

        // Se libera recursos consumidos
        $resultado->closeCursor();
        $resultado = null;

        // Se quita la agrupación temporal
        if (empty($agrupacion) && !empty($final)) {
            $final = $final['##'];
        }

        return $final;
    }

    /**
     * Devuelve el primer registro generado por la consulta de la sentencia interna.
     *
     * @param string $clase Nombre de clase que servirá como formato de registro
     * @param array $claseArgs Argumentos para instanciar clase formato
     *
     * @return array|bool
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function obtenerPrimero($clase = null, array $claseArgs = [])
    {
        $resultado = $this->obtener(null, null, $clase, $claseArgs);

        if ($resultado !== false) {
            return array_shift($resultado);
        }

        return false;
    }

    /**
     * Ejecuta la sentencia interna.
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     * @throws Excepcion
     */
    public function ejecutar()
    {
        // Validamos la sentencia a ejecutar
        if (empty($this->sentencia)) {
            if ($this->arrojarExcepciones) {
                throw new \InvalidArgumentException('La sentencia a ejecutar se encuentra vacia.');
            }

            return false;
        }

        // Ejecutamos la sentencia
        $resultado = $this->pdo->exec($this->sentencia);

        if ($resultado === false) {
            if ($this->arrojarExcepciones) {
                throw new Excepcion('La sentencia ejecutada tuvo un error interno.', [
                    'sentencia' => $this->sentencia,
                    'error_codigo' => $this->pdo->errorCode(),
                    'error_info' => $this->pdo->errorInfo(),
                ]);
            }

            return false;
        }

        return true;
    }

    /**
     * Obtiene el Id del último registro insertado.
     *
     * @return string
     */
    public function ultimoIdInsertado()
    {
        return $this->pdo->lastInsertId();
    }

    // METODOS PARA MANEJAR SUBCOMPONENTE PDO DE FORMA EXTERNA ---------------------------------------------------------

    /**
     * Prepara la sentencia interna para su uso exterior.
     *
     * @throws \Exception
     *
     * @return \PDOStatement
     */
    public function preparar()
    {
        // Validamos la sentencia a preparar
        if (empty($this->sentencia)) {
            if ($this->arrojarExcepciones) {
                throw new \InvalidArgumentException('La sentencia a preparar se encuentra vacia.');
            }
        }

        return $this->pdo->prepare($this->sentencia);
    }

    /**
     * Devuelve el subcomponente PDO para su uso externo.
     *
     * @return \PDO
     */
    public function obtenerPdo()
    {
        return $this->pdo;
    }

    // METODOS PARA MANEJAR TRANSACCIONES ------------------------------------------------------------------------------

    /**
     * Inicia una transaccion.
     *
     * @return bool
     */
    public function iniciarTransaccion()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Termina la transaccion activa.
     *
     * @return bool
     */
    public function terminarTransaccion()
    {
        return $this->pdo->commit();
    }

    /**
     * Verifica si hay transaccion activa.
     *
     * @return bool
     */
    public function enTransaccion()
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Cancela la transaccion activa y revierte todos sus ejecuciones previas.
     *
     * @return bool
     */
    public function cancelarTransaccion()
    {
        return $this->pdo->rollBack();
    }

    // METODOS PARA CONSTRUIR SENTENCIA --------------------------------------------------------------------------------

    /**
     * Introduce directamente la sentencia interna.
     *
     * @param string $sentencia
     *
     * @return Relacional
     */
    public function sql($sentencia)
    {
        $this->sentencia = $sentencia;

        return $this;
    }

    /**
     * Inicia la sentencia interna con SELECT.
     *
     * @param string|array $campos
     * @param string $tabla
     *
     * @return Relacional
     */
    public function seleccionar($campos, $tabla)
    {
        if (is_array($campos)) {
            $this->sentencia = 'SELECT ' . implode(', ', $campos);
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
    public function unirTabla($tabla, $campo1, $campo2, $modo = 'INNER')
    {
        $this->sentencia .= " {$modo} JOIN {$tabla} ON {$campo1} = {$campo2}";

        return $this;
    }

    /**
     * Prepara los campos que filtran la sentencia interna.
     *
     * @param array|null $params Campos parametrizados
     *
     * @return Relacional
     */
    public function donde(array $params = null)
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
                if (empty($tipo)) $tipo = 'txt';

                // Preparamos el valor segun el tipo de campo
                if (is_array($valor)) {
                    $valor_preparado = '(' . implode(',', $this->prepararValor($valor, $tipo)) . ')';
                } else {
                    $valor_preparado = $this->prepararValor($valor, $tipo);
                }

                // Escribimos el termino dentro de los filtros
                $terminos_sql[] = "{$campo} {$operador} {$valor_preparado}";

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
    public function ordenarPor($orden)
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
    public function agruparPor($grupo)
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
    public function limitar($pos, $limite = null)
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
    public function actualizar($tabla, array $params)
    {
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

            $terminos_sql[] = $campo . ' = ' . $this->prepararValor($valor, $tipo);
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
    public function insertar($tabla, array $params)
    {
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

            $columnas[] = $campo;
            $valores[] = $this->prepararValor($valor, $tipo);
        }

        $this->sentencia = 'INSERT INTO ' . $tabla . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')';

        return $this;
    }

    /**
     * Inicia la sentencia interna con REPLACE.
     *
     * @param string $tabla Tabla
     * @param array $params Campos parametrizados
     *
     * @return Relacional
     */
    public function remplazar($tabla, array $params)
    {
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

            $columnas[] = $campo;
            $valores[] = $this->prepararValor($valor, $tipo);
        }

        $this->sentencia = 'REPLACE INTO ' . $tabla . ' (' . implode(', ', $columnas) . ') VALUES (' . implode(', ', $valores) . ')';

        return $this;
    }

    /**
     * Inicia la sentencia interna con DELETE FROM.
     *
     * @param string $tabla
     *
     * @return Relacional
     */
    public function eliminar($tabla)
    {
        $this->sentencia = 'DELETE FROM ' . $tabla;

        return $this;
    }

    /**
     * Devuelve la sentencia interna hasta el momento.
     *
     * @param bool $formatoHtml
     *
     * @return string
     */
    public function obtenerSql($formatoHtml = false)
    {
        if ($formatoHtml) {
            return '<code style="font-weight: bold; '
            . 'font-size: 13px; line-height: 16px; color: #000; background-color: #E6E6FF; border: solid 1px #99F; '
            . 'padding: 4px 6px; margin: 10px; position: relative;">' . $this->sentencia . '</code>';
        } else {
            return $this->sentencia;
        }
    }
}
