<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];

try {
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
    exit();
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

$sql = "SELECT 
            e.idempresa,
            e.nombre,
            e.razon_social,
            e.cuit,
            e.slug,
            e.db_nombre,
            e.activo,
            ue.idusuario,
            u.nombreapellido,
            u.username
        FROM empresas e
        LEFT JOIN usuarios_empresas ue ON e.idempresa = ue.idempresa AND ue.activo = 1
        LEFT JOIN usuarios u ON ue.idusuario = u.idusuario
        ORDER BY e.nombre ASC";

$res = $mysqli->query($sql);

if ($res) {
    $empresasMap = [];

    while ($fila = $res->fetch_assoc()) {
        $idEmp = intval($fila['idempresa']);

        if (!isset($empresasMap[$idEmp])) {
            $empresasMap[$idEmp] = [
                'idempresa' => $idEmp,
                'nombre' => $fila['nombre'],
                'razon_social' => $fila['razon_social'],
                'cuit' => $fila['cuit'],
                'slug' => $fila['slug'],
                'db_nombre' => $fila['db_nombre'],
                'activo' => intval($fila['activo']),
                'usuarios' => []
            ];
        }

        if ($fila['idusuario'] !== null) {
            $empresasMap[$idEmp]['usuarios'][] = [
                'idusuario' => intval($fila['idusuario']),
                'nombreapellido' => $fila['nombreapellido'],
                'username' => $fila['username']
            ];
        }
    }

    echo json_encode([
        "status" => "ok",
        "data" => array_values($empresasMap)
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "error_db: " . $mysqli->error]);
}

$mysqli->close();