<?php

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================================================
    // CONFIGURACIÓN
    // =========================================================

    $rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

    require_once $rutas['autoload'];

    try {
        $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
        $dotenv->load();
    } catch (Exception $e) {
        // El entorno puede estar cargado previamente.
    }

    require_once $rutas['conexion'];
    require_once $rutas['middleware'];
    require_once $rutas['contexto'];

    // =========================================================
    // MÉTODO
    // =========================================================

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Método no permitido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // =========================================================
    // AUTENTICACIÓN Y PERMISOS
    // =========================================================

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint($mysqli, $userAuth);

    // =========================================================
    // EMPRESA ACTIVA
    // =========================================================

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'No se pudo determinar la empresa activa.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // =========================================================
    // BASE DE DATOS DE LA EMPRESA
    // =========================================================

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int) $empresa['idempresa'],
        'DATOS'
    );

    // =========================================================
    // ÚLTIMAS MARCAS VÁLIDAS
    // Reloj + manuales
    // =========================================================

    $sql = "
        SELECT
            marcas.idmarca,
            marcas.idempleado,
            marcas.fecha,
            marcas.hora,
            marcas.origen,
            marcas.idlector,
            e.documento,
            e.apellido,
            e.nombre

        FROM (

            SELECT
                mr.idmarca,
                mr.idempleado,
                mr.fecha,
                mr.hora,
                'reloj' AS origen,
                mr.idlector

            FROM marcas_reloj mr

            WHERE NOT EXISTS (
                SELECT 1
                FROM marcas_invalidaciones mi
                WHERE mi.idmarca_reloj = mr.idmarca
            )

            UNION ALL

            SELECT
                mm.idmarca_manual AS idmarca,
                mm.idempleado,
                mm.fecha,
                mm.hora,
                'manual' AS origen,
                NULL AS idlector

            FROM marcas_manuales mm

            WHERE NOT EXISTS (
                SELECT 1
                FROM marcas_invalidaciones mi
                WHERE mi.idmarca_manual = mm.idmarca_manual
            )

        ) AS marcas

        INNER JOIN empleados e
            ON e.idempleado = marcas.idempleado

        ORDER BY
            marcas.fecha DESC,
            marcas.hora DESC,
            marcas.idmarca DESC

        LIMIT 10
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar las últimas marcas: ' . $mysqli->error
        );
    }

    // =========================================================
    // PREPARAR RESPUESTA
    // =========================================================

    $marcas = [];

    while ($row = $resultado->fetch_assoc()) {

        $marcas[] = [
            'idmarca' => (int) $row['idmarca'],
            'idempleado' => (int) $row['idempleado'],
            'documento' => $row['documento'],
            'empleado' => trim(
                $row['apellido'] . ', ' . $row['nombre']
            ),
            'fecha' => $row['fecha'],
            'hora' => substr($row['hora'], 0, 5),
            'origen' => $row['origen'],
            'idlector' => $row['idlector'] !== null
                ? (int) $row['idlector']
                : null
        ];
    }

    $resultado->free();

    // =========================================================
    // RESPUESTA
    // =========================================================

    echo json_encode([
        'status' => 'ok',
        'msg' => '',
        'data' => $marcas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    error_log(
        'Dashboard últimas marcas: ' . $e->getMessage()
    );

    echo json_encode([
        'status' => 'error',
        'msg' => 'No se pudieron consultar las últimas marcas.'
    ], JSON_UNESCAPED_UNICODE);
}