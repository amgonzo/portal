<?php
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli);
validarPermisoEndpoint($mysqli, $userAuth);

$idempresa = intval($_POST['id'] ?? 0);
$tarea     = $_POST['tarea'] ?? ''; // 'alta' o 'baja'

if (!$idempresa || !in_array($tarea, ['alta', 'baja'])) {
    echo json_encode(["status" => "error", "msg" => "Parámetros inválidos"]);
    exit;
}

$nuevoEstado = ($tarea === 'alta') ? 1 : 0;

$datosAntes = $mysqli->query("SELECT * FROM empresas WHERE idempresa = $idempresa")->fetch_assoc();

$stmt = $mysqli->prepare("UPDATE empresas SET activo = ? WHERE idempresa = ?");
$stmt->bind_param("ii", $nuevoEstado, $idempresa);

if ($stmt->execute()) {
    registrarLog($mysqli, 'cambiar_estado_empresa', 'empresas', $idempresa, $datosAntes, $_POST);
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => "No se pudo actualizar el estado"]);
}

$mysqli->close();