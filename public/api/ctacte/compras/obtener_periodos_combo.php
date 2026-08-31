<?php
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];


try {
    // 3. Cargamos el .env usando la ruta definida en rutas.php
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

// api/ctacte/obtener_periodos_combo.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

$mysqli = conectarDB('CTACTE_');

try {
    $res = $mysqli->query("
        SELECT DISTINCT DATE_FORMAT(cc.fecha_compra, '%Y-%m') AS periodo 
        FROM compras_cabecera cc
        LEFT JOIN empleados_limites el ON el.periodo_codigo = DATE_FORMAT(cc.fecha_compra, '%Y-%m')
        WHERE COALESCE(el.cerrado, 0) != 1
        ORDER BY periodo DESC
    ");

    $periodos = [];
    while ($row = $res->fetch_assoc()) {
        $periodos[] = $row['periodo'];
    }

    echo json_encode($periodos);
} catch (Exception $e) {
    echo json_encode([]);
}
$mysqli->close();