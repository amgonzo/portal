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

// 2. Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$id       = intval($input['idaplicacion'] ?? 0);
$nombre   = trim($input['nombre'] ?? '');
$slug     = trim($input['slug'] ?? '');
$url_base = trim($input['url_base'] ?? '');
$icono    = trim($input['icono'] ?? 'fa-solid fa-cubes'); // 👈 Capturamos el icono con un default por seguridad
$activo   = intval($input['activo'] ?? 1);

if (empty($nombre) || empty($slug) || empty($url_base)) {
    echo json_encode(["status" => "error", "msg" => "Complete los campos obligatorios"]);
    exit;
}

if ($id > 0) {
    $stmtAnt = $mysqli->prepare("SELECT * FROM aplicaciones WHERE idaplicacion = ?");
    $stmtAnt->bind_param("i", $id);
    $stmtAnt->execute();
    $dataAntes = $stmtAnt->get_result()->fetch_assoc();

    // 👈 Actualizamos la columna icono en el UPDATE
    $stmt = $mysqli->prepare("UPDATE aplicaciones SET nombre = ?, slug = ?, url_base = ?, icono = ?, activo = ? WHERE idaplicacion = ?");
    $stmt->bind_param("ssssii", $nombre, $slug, $url_base, $icono, $activo, $id);
    
    if ($stmt->execute()) {
        registrarLog($mysqli, 'update', 'aplicaciones', $id, null, ['antes' => $dataAntes, 'despues' => $input]);
        echo json_encode(["status" => "ok", "msg" => "Aplicación actualizada con éxito"]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error al actualizar en la base de datos"]);
    }
} else {
    // 👈 Insertamos la columna icono en el INSERT
    $stmt = $mysqli->prepare("INSERT INTO aplicaciones (nombre, slug, url_base, icono, activo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $nombre, $slug, $url_base, $icono, $activo);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        registrarLog($mysqli, 'insert', 'aplicaciones', $newId, null, $input);
        echo json_encode(["status" => "ok", "msg" => "Aplicación registrada con éxito"]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error al registrar la aplicación"]);
    }
}

$mysqli->close();