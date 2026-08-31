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

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');

if (empty($token)) {
    echo json_encode(["status" => "error", "msg" => "token_invalido"]);
    exit;
}

// Validar token del usuario
$stmt = $mysqli->prepare("SELECT idusuario FROM usuarios WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["status" => "error", "msg" => "sesion_expirada"]);
    exit;
}

// Obtener únicamente las apps activas que tiene asignadas
$sqlApps = "SELECT DISTINCT a.idaplicacion, a.nombre, a.slug, a.url_base, a.icono 
            FROM aplicaciones a 
            INNER JOIN usuarios_roles_apps ura ON a.idaplicacion = ura.idaplicacion 
            WHERE ura.idusuario = ? 
              AND a.activo = 1";

$stmtApps = $mysqli->prepare($sqlApps);
$stmtApps->bind_param("i", $user['idusuario']);
$stmtApps->execute();
$resApps = $stmtApps->get_result();

$aplicaciones = [];
while ($app = $resApps->fetch_assoc()) {
    $aplicaciones[] = $app;
}

echo json_encode([
    "status" => "ok",
    "aplicaciones" => $aplicaciones
]);