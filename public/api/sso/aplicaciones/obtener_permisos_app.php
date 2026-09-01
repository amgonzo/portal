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

$idaplicacion = intval($_GET['idaplicacion'] ?? 0);

if ($idaplicacion > 0) {
    // 👈 Usamos JOIN con la tabla intermedia aplicaciones_permisos
    $sql = "SELECT p.idpermiso, p.clavepermiso, p.descripcion 
            FROM permisos p
            INNER JOIN aplicaciones_permisos ap ON p.idpermiso = ap.idpermiso
            WHERE ap.idaplicacion = ?";   
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $idaplicacion);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $permisos = [];
    while ($row = $resultado->fetch_assoc()) {
        $permisos[] = $row;
    }

    echo json_encode($permisos);
} else {
    echo json_encode([]);
}
$mysqli->close();