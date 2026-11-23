<?php

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

try {

    $userAuth = validarTokenAPI($mysqli);
    //validarPermisoEndpoint($mysqli, $userAuth);

    $idEmpresa = intval($_GET['idempresa'] ?? 0);

    if ($idEmpresa <= 0) {
        throw new Exception('Empresa no válida.');
    }

    $sql = "
        SELECT
            ai.id,
            ao.nombre AS origen,
            ad.nombre AS destino,
            ai.activo
        FROM aplicaciones_integraciones ai

        INNER JOIN aplicaciones ao
            ON ao.idaplicacion = ai.idaplicacion_origen

        INNER JOIN aplicaciones ad
            ON ad.idaplicacion = ai.idaplicacion_destino

        INNER JOIN empresas_aplicaciones eao
            ON eao.idempresa = ai.idempresa
            AND eao.idaplicacion = ai.idaplicacion_origen
            AND eao.activo = 1

        INNER JOIN empresas_aplicaciones ead
            ON ead.idempresa = ai.idempresa
            AND ead.idaplicacion = ai.idaplicacion_destino
            AND ead.activo = 1

        WHERE ai.idempresa = ?
          AND ao.activo = 1
          AND ad.activo = 1

        ORDER BY ao.nombre, ad.nombre
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();

    $resultado = $stmt->get_result();

    $data = [];

    while ($fila = $resultado->fetch_assoc()) {
        $data[] = $fila;
    }

    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}