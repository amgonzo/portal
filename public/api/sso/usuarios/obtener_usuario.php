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
//require_once $rutas['auditoria'];

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

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(["status" => "error", "msg" => "Falta ID"]);
    exit;
}

$sql = "SELECT 
            u.idusuario, 
            u.nombreapellido, 
            u.username, 
            u.email,
            ura.idtipousuario,
            ura.idaplicacion
        FROM usuarios u
        LEFT JOIN usuarios_roles_apps ura ON u.idusuario = ura.idusuario
        WHERE u.idusuario = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

$userData = null;

while ($row = $res->fetch_assoc()) {
    if (!$userData) {
        $userData = [
            'idusuario' => intval($row['idusuario']),
            'nombreapellido' => $row['nombreapellido'],
            'username' => $row['username'],
            'email' => $row['email'],
            'accesos' => []
        ];
    }

    if ($row['idaplicacion'] !== null) {
        $userData['accesos'][] = [
            'idaplicacion' => intval($row['idaplicacion']),
            'idtipousuario' => intval($row['idtipousuario'])
        ];
    }
}

if ($userData) {
    echo json_encode(["status" => "ok", "data" => $userData]);
} else {
    echo json_encode(["status" => "error", "msg" => "No encontrado"]);
}

$stmt->close();
$mysqli->close();