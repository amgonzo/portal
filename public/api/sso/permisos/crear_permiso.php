<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

$idaplicacion = $_POST['idaplicacion'] ?? null; 
$clave        = $_POST['clave'] ?? '';
$endpoint     = $_POST['endpoint'] ?? null;
$metodo       = $_POST['metodo'] ?? 'ALL';
$desc         = $_POST['descripcion'] ?? '';

if (!$idaplicacion || !$clave) {
    exit(json_encode(["status" => "error", "msg" => "Faltan datos obligatorios (Aplicación y Clave)"]));
}

$mysqli->begin_transaction();

try {
    // 1. Verificamos si la clave ya existe vinculada a esa aplicación mediante la tabla intermedia
    $check = $mysqli->prepare("
        SELECT p.idpermiso 
        FROM permisos p 
        JOIN aplicaciones_permisos ap ON p.idpermiso = ap.idpermiso 
        WHERE p.clavepermiso = ? AND ap.idaplicacion = ?
    ");
    $check->bind_param("si", $clave, $idaplicacion);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        throw new Exception("La clave ya existe para esta aplicación");
    }

    // 2. Insertamos el permiso en la tabla base (respetando tu esquema sin idaplicacion)
    $stmt = $mysqli->prepare("INSERT INTO permisos (clavepermiso, endpoint, metodo, descripcion) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $clave, $endpoint, $metodo, $desc);
    $stmt->execute();

    $idNuevo = $mysqli->insert_id;

    // 3. Insertamos la relación en la tabla intermedia aplicaciones_permisos
    $stmtAppPerm = $mysqli->prepare("INSERT INTO aplicaciones_permisos (idaplicacion, idpermiso) VALUES (?, ?)");
    $stmtAppPerm->bind_param("ii", $idaplicacion, $idNuevo);
    $stmtAppPerm->execute();

    $mysqli->commit();

    // 4. Auditoría
    registrarLog($mysqli, 'alta_permiso', 'permisos', $idNuevo, null, $_POST);
    
    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}