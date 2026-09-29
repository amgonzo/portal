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
$tarea = $_POST['tarea'] ?? '';

if ($idempresa <= 0 || !in_array($tarea, ['alta', 'baja'], true)) {
    echo json_encode([
        "status" => "error",
        "msg" => "Parámetros inválidos"
    ]);
    exit;
}

$stmtAntes = $mysqli->prepare("
    SELECT *
    FROM empresas
    WHERE idempresa = ?
");

$stmtAntes->bind_param("i", $idempresa);
$stmtAntes->execute();

$resultadoAntes = $stmtAntes->get_result();

if ($resultadoAntes->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "msg" => "La empresa no existe"
    ]);
    exit;
}

$datosAntes = $resultadoAntes->fetch_assoc();

$nuevoEstado = ($tarea === 'alta') ? 1 : 0;

$stmt = $mysqli->prepare("
    UPDATE empresas
    SET activo = ?
    WHERE idempresa = ?
");

$stmt->bind_param("ii", $nuevoEstado, $idempresa);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "msg" => "No se pudo actualizar el estado"
    ]);
    $mysqli->close();
    exit;
}

registrarLog(
    $mysqli,
    'cambiar_estado_empresa',
    'empresas',
    $idempresa,
    $datosAntes,
    $_POST
);

echo json_encode([
    "status" => "ok"
]);

$mysqli->close();