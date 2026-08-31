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

header('Content-Type: application/json');

// 1. Obtener Token del Header
$authHeader = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}
if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}

$token = trim(str_replace('Bearer ', '', $authHeader));

if (empty($token)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "msg" => "No autorizado"]);
    exit;
}

// 2. Buscar usuario por Token (incluyendo el campo de expiración)
$sql = "SELECT u.idusuario, u.nombreapellido, u.username, u.email, u.token_expira 
        FROM usuarios u 
        WHERE u.token = ? AND u.baja = 0";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(401);
    echo json_encode(["status" => "error", "msg" => "Token inválido"]);
    exit;
}

// 2.1. Validar si el token expiró por tiempo de inactividad
if (!empty($user['token_expira']) && strtotime($user['token_expira']) < time()) {
    // El token venció: lo limpiamos de la base de datos
    $stmtClear = $mysqli->prepare("UPDATE usuarios SET token = NULL, token_expira = NULL WHERE idusuario = ?");
    $stmtClear->bind_param("i", $user['idusuario']);
    $stmtClear->execute();

    http_response_code(401);
    echo json_encode(["status" => "error", "msg" => "Sesión expirada por inactividad"]);
    exit;
}

// 2.2. ¡Renovar el token! (Extiende la sesión 30 minutos más desde este preciso instante)
$minutosInactividad = 30;
$stmtRenew = $mysqli->prepare("UPDATE usuarios SET token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE idusuario = ?");
$stmtRenew->bind_param("ii", $minutosInactividad, $user['idusuario']);
$stmtRenew->execute();

// 3. Obtener permisos a través de la tabla intermedia usuarios_roles_apps y permisos_rol
$permisos = [];
$sqlP = "SELECT DISTINCT p.clavepermiso 
         FROM permisos p 
         JOIN permisos_rol pr ON p.idpermiso = pr.idpermiso
         JOIN usuarios_roles_apps ura ON pr.idtipousuario = ura.idtipousuario
         WHERE ura.idusuario = ?";

$stmtP = $mysqli->prepare($sqlP);
$stmtP->bind_param("i", $user['idusuario']);
$stmtP->execute();
$resP = $stmtP->get_result();
while($p = $resP->fetch_assoc()) { 
    $permisos[] = $p['clavepermiso']; 
}

// 4. Responder con los datos reales y la estructura correcta
echo json_encode([
    "status" => "ok",
    "usuario" => [
        "id" => $user['idusuario'],
        "nombre" => $user['nombreapellido'],
        "username" => $user['username']
    ],
    "permisos" => $permisos
]);