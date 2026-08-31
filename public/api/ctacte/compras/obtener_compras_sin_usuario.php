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
require_once $rutas['auditoria'];
// api/ctacte/obtener_compras_sin_usuario.php
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
    $sql = "
        SELECT 
            cc.punto_venta_id,
            cc.venta_id,
            cc.fecha_compra,
            cc.importe_total,
            COALESCE(
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    CASE 
                        WHEN cd.cantidad = FLOOR(cd.cantidad) 
                            THEN CONCAT(CAST(cd.cantidad AS SIGNED), ' U')
                        ELSE CONCAT(TRIM(TRAILING '0' FROM ROUND(cd.cantidad, 3)), ' kg')
                    END,
                    ' - ', 
                    cd.descripcion
                ) SEPARATOR ', '
            ), 
            'Sin detalles'
        ) AS detalles_resumen
        FROM compras_cabecera cc
        LEFT JOIN compras_detalles cd 
            ON cc.punto_venta_id = cd.punto_venta_id 
           AND cc.venta_id = cd.venta_id
        WHERE cc.dni_empleado = '0' 
           OR cc.dni_empleado = 'SIN_USUARIO'
           OR cc.dni_empleado IS NULL
        GROUP BY cc.punto_venta_id, cc.venta_id, cc.fecha_compra, cc.importe_total
        ORDER BY cc.fecha_compra DESC
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception("Error al consultar la base de datos: " . $mysqli->error);
    }

    $pendientes = [];
    while ($row = $res->fetch_assoc()) {
        $pendientes[] = [
            'punto_venta_id'   => (int)$row['punto_venta_id'],
            'venta_id'         => (int)$row['venta_id'],
            'fecha_compra'     => date('d/m/Y H:i', strtotime($row['fecha_compra'])),
            'importe_total'    => number_format((float)$row['importe_total'], 2, ',', '.'),
            'detalles_resumen' => $row['detalles_resumen']
        ];
    }

    echo json_encode($pendientes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();