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

// Validar token y sesión activa (sin validar endpoint por ahora)
$userAuth = validarTokenAPI($mysqli);

$idaplicacion = $_POST['idaplicacion'] ?? null; 
$clave        = $_POST['clave'] ?? '';
$endpoint     = $_POST['endpoint'] ?? null; // 👈 NUEVO
$metodo       = $_POST['metodo'] ?? 'ALL';  // 👈 NUEVO
$desc         = $_POST['descripcion'] ?? '';

// Quitamos la descripción como obligatoria por si querés cargar rápido
if (!$idaplicacion || !$clave) {
    exit(json_encode(["status" => "error", "msg" => "Faltan datos obligatorios (Aplicación y Clave)"]));
}

// 1. Verificamos que no exista la clave DENTRO DE LA MISMA APLICACIÓN
$check = $mysqli->prepare("SELECT idpermiso FROM permisos WHERE clavepermiso = ? AND idaplicacion = ?");
$check->bind_param("si", $clave, $idaplicacion);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    exit(json_encode(["status" => "error", "msg" => "La clave ya existe para esta aplicación"]));
}

// 2. Insertamos vinculando a la aplicación y los nuevos campos
$stmt = $mysqli->prepare("INSERT INTO permisos (idaplicacion, clavepermiso, endpoint, metodo, descripcion) VALUES (?, ?, ?, ?, ?)");
// "issss" = integer, string, string, string, string
$stmt->bind_param("issss", $idaplicacion, $clave, $endpoint, $metodo, $desc);

if ($stmt->execute()) {
    $idNuevo = $mysqli->insert_id;

    // 3. Log de Auditoría
    registrarLog($mysqli, 'alta_permiso', 'permisos', $idNuevo, null, $_POST);
    
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => $mysqli->error]);
}