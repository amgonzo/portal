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

// Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

$idApp    = $_POST['idaplicacion'] ?? null;
$idTipo   = $_POST['idtipousuario'] ?? null;
$permisos = $_POST['permisos'] ?? [];

if (!$idApp || !$idTipo) {
    echo json_encode(["status" => "error", "msg" => "Faltan datos obligatorios"]);
    exit;
}

// 1. Capturar estado anterior para auditoría[cite: 14]
$antes = [];
$sqlAntes = "SELECT pr.idpermiso 
             FROM permisos_rol pr 
             INNER JOIN permisos p ON pr.idpermiso = p.idpermiso 
             WHERE pr.idtipousuario = ? AND p.idaplicacion = ?";
$stmtAntes = $mysqli->prepare($sqlAntes);
$stmtAntes->bind_param("ii", $idTipo, $idApp);
$stmtAntes->execute();
$resAntes = $stmtAntes->get_result();
while ($row = $resAntes->fetch_assoc()) {
    $antes[] = (int)$row['idpermiso'];
}

// 2. Limpiar permisos existentes de ESA aplicación para ese rol (sin bloqueos de auditoría por ahora)[cite: 14]
$sqlDelete = "DELETE pr FROM permisos_rol pr 
              INNER JOIN permisos p ON pr.idpermiso = p.idpermiso 
              WHERE pr.idtipousuario = ? AND p.idaplicacion = ?";
$stmtDelete = $mysqli->prepare($sqlDelete);
$stmtDelete->bind_param("ii", $idTipo, $idApp);
$stmtDelete->execute();

// 3. Insertar la nueva selección[cite: 14]
if (!empty($permisos) && is_array($permisos)) {
    $stmtInsert = $mysqli->prepare("INSERT INTO permisos_rol (idtipousuario, idpermiso) VALUES (?, ?)");

    foreach ($permisos as $idP) {
        $idP = (int)$idP;
        if ($idP > 0) {
            $stmtInsert->bind_param("ii", $idTipo, $idP);
            $stmtInsert->execute();
        }
    }
}

// 4. Log de auditoría[cite: 14]
registrarLog(
    $mysqli, 
    'editar_permisos_rol', 
    'permisos_rol', 
    $idTipo, 
    ['idaplicacion' => $idApp, 'ids_permisos' => $antes], 
    ['idaplicacion' => $idApp, 'ids_permisos' => $permisos]
);

echo json_encode(["status" => "ok"]);