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

header('Content-Type: application/json');

// Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);
$id_usuario = $userAuth['idusuario'];

$idApp  = $_GET['idaplicacion'] ?? '';
$idTipo = $_GET['idtipousuario'] ?? '';

// Determinar el rol/tipo de usuario principal del usuario logueado actual para restricciones de auditoría
$sqlRolesUser = "SELECT idtipousuario FROM usuarios_roles_apps WHERE idusuario = ?";
$stmtRU = $mysqli->prepare($sqlRolesUser);
$stmtRU->bind_param("i", $id_usuario);
$stmtRU->execute();
$resRU = $stmtRU->get_result();

$esAuditorSesion = false;
$id_auditor = 99;

while($rowR = $resRU->fetch_assoc()) {
    if ($rowR['idtipousuario'] == $id_auditor) {
        $esAuditorSesion = true;
        break;
    }
}

if (!$idApp || !$idTipo) {
    echo json_encode(["status" => "error", "msg" => "Faltan parámetros obligatorios"]);
    exit;
}

// 1. Obtener todos los permisos pertenecientes a la APLICACIÓN seleccionada mediante la tabla intermedia `aplicaciones_permisos`
$wherePermisos = " WHERE ap.idaplicacion = ? ";
if (!$esAuditorSesion) {
    $wherePermisos .= " AND p.clavepermiso NOT LIKE '%auditoria%' AND p.clavepermiso NOT LIKE '%audit%' ";
}

$sqlTodos = "SELECT p.idpermiso, p.clavepermiso, p.endpoint, p.metodo, p.descripcion 
             FROM permisos p 
             INNER JOIN aplicaciones_permisos ap ON p.idpermiso = ap.idpermiso 
             $wherePermisos 
             ORDER BY p.clavepermiso ASC";

$stmtTodos = $mysqli->prepare($sqlTodos);
$stmtTodos->bind_param("i", $idApp);
$stmtTodos->execute();
$resTodos = $stmtTodos->get_result();

$permisos = [];
while ($row = $resTodos->fetch_assoc()) {
    $permisos[] = $row;
}

// 2. Obtener los permisos ASIGNADOS a este rol exclusivamente para esta APLICACIÓN
$sqlAsignados = "SELECT pr.idpermiso 
                 FROM permisos_rol pr 
                 INNER JOIN aplicaciones_permisos ap ON pr.idpermiso = ap.idpermiso 
                 WHERE pr.idtipousuario = ? AND ap.idaplicacion = ?";
$stmtAsig = $mysqli->prepare($sqlAsignados);
$stmtAsig->bind_param("ii", $idTipo, $idApp);
$stmtAsig->execute();
$resAsignados = $stmtAsig->get_result();

$asignados = [];
while ($row = $resAsignados->fetch_assoc()) {
    $asignados[] = (int)$row['idpermiso'];
}

echo json_encode([
    "status" => "ok",
    "data" => [
        "todos" => $permisos,
        "asignados" => $asignados
    ]
]);