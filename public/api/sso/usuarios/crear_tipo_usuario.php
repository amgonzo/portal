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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. 🛡️ Validar si el rol tiene permiso para este endpoint y método mediante el middleware centralizado
validarPermisoEndpoint($mysqli, $userAuth);

$descripcion = trim($_POST['nombre'] ?? '');

if (!$descripcion) {
    echo json_encode(["status" => "error", "msg" => "Nombre del rol requerido"]);
    exit;
}

// Evitar duplicar roles globales por nombre
$check = $mysqli->prepare("SELECT idtipousuario FROM tiposusuario WHERE descripcion = ?");
$check->bind_param("s", $descripcion);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    exit(json_encode(["status" => "error", "msg" => "El tipo de usuario ya existe"]));
}

$sql = "INSERT INTO tiposusuario (descripcion) VALUES (?)";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("s", $descripcion);

if ($stmt->execute()) {
    $idNuevo = $mysqli->insert_id;

    registrarLog($mysqli, 'alta_rol', 'tiposusuario', $idNuevo, null, ['descripcion' => $descripcion]);
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => $mysqli->error]);
}

$stmt->close();
$mysqli->close();