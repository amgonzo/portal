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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

$mysqli = conectarDB('CTACTE_');

if (empty($_GET['id'])) {
    echo json_encode(["status" => "error", "msg" => "Falta el identificador del ticket."]);
    exit;
}

$partes = explode('_', $_GET['id']);
if (count($partes) !== 2) {
    echo json_encode(["status" => "error", "msg" => "Formato de ticket inválido."]);
    exit;
}

$punto_venta_id = (int)$partes[0];
$venta_id = (int)$partes[1];

try {
    $stmt_cab = $mysqli->prepare("
        SELECT 
            cc.punto_venta_id,
            cc.venta_id,
            cc.dni_empleado,
            cc.fecha_compra,
            cc.importe_total,
            CONCAT(e.apellido, ' ', e.nombre) AS empleado
        FROM compras_cabecera cc
        INNER JOIN fichajes.empleados e ON cc.dni_empleado = e.documento
        WHERE cc.punto_venta_id = ? AND cc.venta_id = ?
    ");
    $stmt_cab->bind_param("ii", $punto_venta_id, $venta_id);
    $stmt_cab->execute();
    $cabecera = $stmt_cab->get_result()->fetch_assoc();
    $stmt_cab->close();

    if (!$cabecera) {
        echo json_encode(["status" => "error", "msg" => "No se encontró el ticket solicitado."]);
        exit;
    }

    $stmt_det = $mysqli->prepare("
        SELECT articulo_id, descripcion, cantidad, importe_renglon 
        FROM compras_detalles 
        WHERE punto_venta_id = ? AND venta_id = ?
    ");
    $stmt_det->bind_param("ii", $punto_venta_id, $venta_id);
    $stmt_det->execute();
    $result_det = $stmt_det->get_result();

    $detalles = [];
    while ($row = $result_det->fetch_assoc()) {
        $detalles[] = [
            'articulo_id' => $row['articulo_id'],
            'descripcion' => trim($row['descripcion']),
            'cantidad' => (float)$row['cantidad'],
            'importe' => (float)$row['importe_renglon']
        ];
    }
    $stmt_det->close();

    echo json_encode([
        "status" => "ok",
        "data" => [
            "ticket" => $cabecera['punto_venta_id'] . '-' . $cabecera['venta_id'],
            "empleado" => trim($cabecera['empleado']),
            "dni" => $cabecera['dni_empleado'],
            "fecha" => date('d/m/Y H:i', strtotime($cabecera['fecha_compra'])),
            "total" => (float)$cabecera['importe_total'],
            "items" => $detalles
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => "Error al obtener detalles: " . $e->getMessage()]);
}

$mysqli->close();