
<?php
// api/fichajes/configuracion/estado_horas_compensatorias.php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

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
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Método no permitido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );

    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    if (
        !isset($empresa['idempresa']) ||
        (int)$empresa['idempresa'] <= 0
    ) {
        throw new Exception(
            'No se pudo determinar la empresa activa.'
        );
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    $stmt = $mysqli->prepare("
        SELECT tratamiento_horas_extra
        FROM configuracion_empresa
        ORDER BY idconfig ASC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Error al consultar la configuración de la empresa.'
        );
    }

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'No se pudo consultar la configuración de horas compensatorias.'
        );
    }

    $resultado = $stmt->get_result();
    $configuracion = $resultado->fetch_assoc();

    $stmt->close();

    $tratamiento = $configuracion['tratamiento_horas_extra'] ?? 'ninguna';

    $habilitado = in_array(
        $tratamiento,
        ['compensacion', 'pago_compensacion'],
        true
    );

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'tratamiento_horas_extra' => $tratamiento,
            'habilitado' => $habilitado
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
