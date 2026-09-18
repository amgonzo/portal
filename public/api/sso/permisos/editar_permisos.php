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

$idpermiso    = $_POST['idpermiso'] ?? null;
$idaplicacion = $_POST['idaplicacion'] ?? null;
$clave        = trim($_POST['clave'] ?? '');
$endpoint     = $_POST['endpoint'] ?? null;
$metodo       = $_POST['metodo'] ?? 'ALL';
$desc         = $_POST['descripcion'] ?? '';

if (!$idpermiso || !$idaplicacion || !$clave) {
    exit(json_encode(["status" => "error", "msg" => "Faltan datos obligatorios"]));
}

$mysqli->begin_transaction();

try {
    // 1. Verificar que la clave no pertenezca a OTRO permiso diferente en la misma app
    $check = $mysqli->prepare("
        SELECT p.idpermiso 
        FROM permisos p 
        JOIN aplicaciones_permisos ap ON p.idpermiso = ap.idpermiso 
        WHERE ap.idaplicacion = ? AND p.clavepermiso = ? AND p.idpermiso != ?
    ");
    $check->bind_param("isi", $idaplicacion, $clave, $idpermiso);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        throw new Exception("La clave ya existe para esta aplicación");
    }

    // 2. Actualizar los datos base del permiso
    $stmt = $mysqli->prepare("UPDATE permisos SET clavepermiso = ?, endpoint = ?, metodo = ?, descripcion = ? WHERE idpermiso = ?");
    $stmt->bind_param("ssssi", $clave, $endpoint, $metodo, $desc, $idpermiso);
    $stmt->execute();

    // 3. Actualizar la relación en la tabla intermedia por si cambió de aplicación
    $stmtRel = $mysqli->prepare("UPDATE aplicaciones_permisos SET idaplicacion = ? WHERE idpermiso = ?");
    $stmtRel->bind_param("ii", $idaplicacion, $idpermiso);
    $stmtRel->execute();

    $mysqli->commit();

    registrarLog($mysqli, 'editar_permiso', 'permisos', $idpermiso, null, $_POST);
    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}