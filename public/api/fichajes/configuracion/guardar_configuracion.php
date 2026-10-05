<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }


    /*
     * =========================================================
     * AUTENTICACIÓN Y PERMISOS
     * =========================================================
     */

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    /*
     * =========================================================
     * DATOS RECIBIDOS
     * =========================================================
     */

    $idConfiguracion = isset($_POST['idconfiguracion'])
        ? (int)$_POST['idconfiguracion']
        : 0;

    $clave = trim(
        (string)($_POST['clave'] ?? '')
    );

    $valor = trim(
        (string)($_POST['valor'] ?? '')
    );

    $descripcion = trim(
        (string)($_POST['descripcion'] ?? '')
    );

    $tipo = trim(
        (string)($_POST['tipo'] ?? '')
    );


    if ($clave === '') {
        throw new Exception(
            'La clave es obligatoria.'
        );
    }

    if ($valor === '') {
        throw new Exception(
            'El valor es obligatorio.'
        );
    }

    if (!in_array(
        $tipo,
        ['texto', 'entero', 'decimal', 'booleano'],
        true
    )) {
        throw new Exception(
            'El tipo de configuración no es válido.'
        );
    }


    /*
     * =========================================================
     * VALIDAR VALOR SEGÚN TIPO
     * =========================================================
     */

    switch ($tipo) {

        case 'entero':

            if (
                filter_var(
                    $valor,
                    FILTER_VALIDATE_INT
                ) === false
            ) {
                throw new Exception(
                    'El valor debe ser un número entero.'
                );
            }

            $valor = (string)(int)$valor;

            break;


        case 'decimal':

            if (!is_numeric($valor)) {
                throw new Exception(
                    'El valor debe ser numérico.'
                );
            }

            $valor = (string)(float)$valor;

            break;


        case 'booleano':

            $valorNormalizado = strtolower(
                trim($valor)
            );

            if (
                in_array(
                    $valorNormalizado,
                    ['1', 'true', 'si', 'sí', 'yes'],
                    true
                )
            ) {
                $valor = '1';

            } elseif (
                in_array(
                    $valorNormalizado,
                    ['0', 'false', 'no'],
                    true
                )
            ) {
                $valor = '0';

            } else {
                throw new Exception(
                    'El valor booleano debe ser verdadero o falso.'
                );
            }

            break;
    }


    /*
     * =========================================================
     * BUSCAR CONFIGURACIÓN ACTUAL
     * =========================================================
     */

    if ($idConfiguracion > 0) {

        $stmt = $mysqli->prepare("
            SELECT
                idconfiguracion,
                clave,
                valor,
                descripcion,
                tipo
            FROM configuracion_sistema
            WHERE idconfiguracion = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Error al preparar consulta.'
            );
        }

        $stmt->bind_param(
            'i',
            $idConfiguracion
        );

    } else {

        $stmt = $mysqli->prepare("
            SELECT
                idconfiguracion,
                clave,
                valor,
                descripcion,
                tipo
            FROM configuracion_sistema
            WHERE clave = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Error al preparar consulta.'
            );
        }

        $stmt->bind_param(
            's',
            $clave
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error al consultar configuración: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $configActual = $resultado->fetch_assoc();

    $stmt->close();


    /*
     * =========================================================
     * ACTUALIZAR O CREAR
     * =========================================================
     */

    $valorAnterior = null;
    $huboCambio = true;

    if ($configActual) {

        $valorAnterior = (string)$configActual['valor'];

        $huboCambio =
            $valorAnterior !== $valor ||
            (string)$configActual['descripcion'] !== $descripcion ||
            (string)$configActual['tipo'] !== $tipo;


        $idConfiguracion =
            (int)$configActual['idconfiguracion'];


        $stmt = $mysqli->prepare("
            UPDATE configuracion_sistema
            SET
                clave = ?,
                valor = ?,
                descripcion = ?,
                tipo = ?
            WHERE idconfiguracion = ?
        ");

        if (!$stmt) {
            throw new Exception(
                'Error al preparar actualización: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'ssssi',
            $clave,
            $valor,
            $descripcion,
            $tipo,
            $idConfiguracion
        );

    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO configuracion_sistema
            (
                clave,
                valor,
                descripcion,
                tipo
            )
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new Exception(
                'Error al preparar inserción: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'ssss',
            $clave,
            $valor,
            $descripcion,
            $tipo
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'No se pudo guardar la configuración: ' .
            $error
        );
    }


    if (!$configActual) {
        $idConfiguracion =
            (int)$mysqli->insert_id;
    }

    $stmt->close();


    /*
     * =========================================================
     * SI NO CAMBIÓ NADA
     * =========================================================
     */

    if (!$huboCambio) {

        echo json_encode([
            'status' => 'ok',
            'msg' => 'La configuración no tenía cambios.',
            'tarea_generada' => false
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
     * =========================================================
     * BUSCAR AGENTS ACTIVOS
     * =========================================================
     */

    $resultadoAgentes = $mysqli->query("
        SELECT
            idagente,
            idempresa
        FROM agentes
        WHERE activo = 1
        ORDER BY idagente ASC
    ");

    if (!$resultadoAgentes) {

        throw new Exception(
            'No se pudieron consultar los Agents: ' .
            $mysqli->error
        );
    }


    $agentes = [];

    while ($agente = $resultadoAgentes->fetch_assoc()) {

        $agentes[] = [
            'idagente' => (int)$agente['idagente'],
            'idempresa' => (int)$agente['idempresa']
        ];
    }


    /*
     * =========================================================
     * CREAR TAREAS EN LAS BD DE CADA EMPRESA
     * =========================================================
     */

    $tareasGeneradas = 0;


    foreach ($agentes as $agente) {

        $idAgente = $agente['idagente'];
        $idEmpresa = $agente['idempresa'];


        $dbEmpresa = conectarDBEmpresa(
            $mysqli,
            $idEmpresa,
            'DATOS'
        );


        if (
            !$dbEmpresa ||
            !($dbEmpresa instanceof mysqli)
        ) {
            continue;
        }


        $datosTarea = json_encode([
            'clave' => $clave,
            'motivo' => 'configuracion_actualizada'
        ], JSON_UNESCAPED_UNICODE);


        $stmtTarea = $dbEmpresa->prepare("
            INSERT INTO tareas_agente
            (
                idagente,
                accion,
                prioridad,
                datos
            )
            VALUES (?, 'actualizar_configuracion', 90, ?)
        ");


        if (!$stmtTarea) {

            $dbEmpresa->close();

            continue;
        }


        $stmtTarea->bind_param(
            'is',
            $idAgente,
            $datosTarea
        );


        if ($stmtTarea->execute()) {
            $tareasGeneradas++;
        }


        $stmtTarea->close();
        $dbEmpresa->close();
    }


    /*
     * =========================================================
     * RESPUESTA
     * =========================================================
     */

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Configuración guardada correctamente.',
        'idconfiguracion' => $idConfiguracion,
        'tarea_generada' => true,
        'tareas_generadas' => $tareasGeneradas
    ], JSON_UNESCAPED_UNICODE);


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}