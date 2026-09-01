<?php
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
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

$idpermiso = $_POST['idpermiso'] ?? null;
$clave     = trim($_POST['clave'] ?? '');
$endpoint  = $_POST['endpoint'] ?? null;
$metodo    = $_POST['metodo'] ?? 'ALL';
$desc      = $_POST['descripcion'] ?? '';

if (!$idpermiso || !$clave) {
    exit(json_encode(["status" => "error", "msg" => "Faltan datos obligatorios (ID y Clave)"]));
}

// 1. Obtener a qué aplicación pertenece el permiso que estamos editando
$stmtApp = $mysqli->prepare("SELECT idaplicacion FROM permisos WHERE idpermiso = ? LIMIT 1");
$stmtApp->bind_param("i", $idpermiso);
$stmtApp->execute();
$resApp = $stmtApp->get_result()->fetch_assoc();

if (!$resApp) {
    exit(json_encode(["status" => "error", "msg" => "El permiso no existe"]));
}
$idaplicacion = $resApp['idaplicacion'];

// 2. Verificar que la clave no esté repetida DENTRO DE LA MISMA APLICACIÓN
$check = $mysqli->prepare("SELECT idpermiso FROM permisos WHERE idaplicacion = ? AND clavepermiso = ? AND idpermiso != ?");
$check->bind_param("isi", $idaplicacion, $clave, $idpermiso);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    exit(json_encode(["status" => "error", "msg" => "Ya existe otro permiso con esa misma clave en esta aplicación"]));
}

// 3. Actualizar el permiso
$sql = "UPDATE permisos SET clavepermiso = ?, endpoint = ?, metodo = ?, descripcion = ? WHERE idpermiso = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssssi", $clave, $endpoint, $metodo, $desc, $idpermiso);

if ($stmt->execute()) {
    registrarLog($mysqli, 'editar_permiso', 'permisos', $idpermiso, null, $_POST);
    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => $mysqli->error]);
}