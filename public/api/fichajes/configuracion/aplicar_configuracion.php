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

    if ($idConfiguracion <= 0) {
        throw new Exception(
            'Configuración inválida.'
        );
    }


    /*
     * =========================================================
     * BUSCAR CONFIGURACIÓN
     * =========================================================
     */

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
            'Error al preparar consulta: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'i',
        $idConfiguracion
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error al consultar configuración: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $configuracion = $resultado->fetch_assoc();

    $stmt->close();


    if (!$configuracion) {
        throw new Exception(
            'La configuración seleccionada no existe.'
        );
    }


    /*
     * =========================================================
     * BUSCAR AGENTS ACTIVOS
     *
     * configuracion_sistema está en portal_sso,
     * por lo tanto buscamos los Agents desde esta misma conexión.
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
     * CREAR TAREA PARA CADA AGENT
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
            'idconfiguracion' =>
                (int)$configuracion['idconfiguracion'],

            'clave' =>
                $configuracion['clave'],

            'motivo' =>
                'aplicacion_manual'
        ], JSON_UNESCAPED_UNICODE);


        $stmtTarea = $dbEmpresa->prepare("
            INSERT INTO tareas_agente
            (
                idagente,
                accion,
                prioridad,
                datos
            )
            VALUES
            (
                ?,
                'actualizar_configuracion',
                90,
                ?
            )
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
        'msg' =>
            'Configuración enviada correctamente a los Agents.',
        'idconfiguracion' =>
            (int)$configuracion['idconfiguracion'],
        'clave' =>
            $configuracion['clave'],
        'tareas_generadas' =>
            $tareasGeneradas
    ], JSON_UNESCAPED_UNICODE);


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}