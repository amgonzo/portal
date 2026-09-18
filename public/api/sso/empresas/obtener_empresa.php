<?php
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$userAuth = validarTokenAPI($mysqli);
validarPermisoEndpoint($mysqli, $userAuth);

$idempresa = intval($_GET['id'] ?? 0);

if (!$idempresa) {
    echo json_encode(["status" => "error", "msg" => "ID de empresa no proporcionado"]);
    exit;
}

// Obtener datos principales de la empresa
$stmt = $mysqli->prepare("SELECT * FROM empresas WHERE idempresa = ?");
$stmt->bind_param("i", $idempresa);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(["status" => "error", "msg" => "Empresa no encontrada"]);
    exit;
}

$empresa = $resultado->fetch_assoc();
$empresa['idempresa'] = intval($empresa['idempresa']);
$empresa['activo'] = intval($empresa['activo']);

// Obtener usuarios vinculados a esta empresa
$stmtUsr = $mysqli->prepare("SELECT u.idusuario, u.nombreapellido, u.username 
                             FROM usuarios_empresas ue 
                             JOIN usuarios u ON ue.idusuario = u.idusuario 
                             WHERE ue.idempresa = ? AND ue.activo = 1");
$stmtUsr->bind_param("i", $idempresa);
$stmtUsr->execute();
$resUsr = $stmtUsr->get_result();

$empresa['usuarios'] = [];
while ($row = $resUsr->fetch_assoc()) {
    $row['idusuario'] = intval($row['idusuario']);
    $empresa['usuarios'][] = $row;
}

echo json_encode([
    "status" => "ok",
    "data" => $empresa
]);

$mysqli->close();