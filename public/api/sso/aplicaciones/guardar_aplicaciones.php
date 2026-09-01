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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$userAuth = validarTokenAPI($mysqli);
validarPermisoEndpoint($mysqli, $userAuth);

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$id       = intval($input['idaplicacion'] ?? 0);
$nombre   = trim($input['nombre'] ?? '');
$slug     = trim($input['slug'] ?? '');
$url_base = trim($input['url_base'] ?? '');
$icono    = trim($input['icono'] ?? 'fa-solid fa-cubes');
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

    $stmt = $mysqli->prepare("UPDATE aplicaciones SET nombre = ?, slug = ?, url_base = ?, icono = ?, activo = ? WHERE idaplicacion = ?");
    $stmt->bind_param("ssssii", $nombre, $slug, $url_base, $icono, $activo, $id);
    
    if ($stmt->execute()) {
        registrarLog($mysqli, 'update', 'aplicaciones', $id, null, ['antes' => $dataAntes, 'despues' => $input]);
        echo json_encode(["status" => "ok", "msg" => "Aplicación actualizada con éxito"]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error al actualizar en la base de datos"]);
    }
} else {
    $stmt = $mysqli->prepare("INSERT INTO aplicaciones (nombre, slug, url_base, icono, activo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $nombre, $slug, $url_base, $icono, $activo);
    
    if ($stmt->execute()) {
        $newId = $stmt->insert_id;

        // -----------------------------------------------------------------
        // 🚀 CLONAR PERMISOS USANDO LA TABLA INTERMEDIA (aplicaciones_permisos)
        // -----------------------------------------------------------------
        $permisosAClonar = $input['permisos_seleccionados'] ?? [];
        
        if (!empty($permisosAClonar) && is_array($permisosAClonar)) {
            // 1. Buscamos los permisos originales de la app modelo
            $placeholders = implode(',', array_fill(0, count($permisosAClonar), '?'));
            $sqlOrig = "SELECT idpermiso FROM permisos WHERE idpermiso IN ($placeholders)";
            $stmtOrig = $mysqli->prepare($sqlOrig);
            
            $tipos = str_repeat('i', count($permisosAClonar));
            $stmtOrig->bind_param($tipos, ...$permisosAClonar);
            $stmtOrig->execute();
            $resultadoPermisos = $stmtOrig->get_result();
            
            // 2. Insertamos la relación en la tabla puente aplicaciones_permisos
            $stmtInsertPuente = $mysqli->prepare("INSERT IGNORE INTO aplicaciones_permisos (idaplicacion, idpermiso) VALUES (?, ?)");
            
            while ($p = $resultadoPermisos->fetch_assoc()) {
                $idPermisoOriginal = $p['idpermiso'];
                $stmtInsertPuente->bind_param("ii", $newId, $idPermisoOriginal);
                $stmtInsertPuente->execute();
            }
        }
        // -----------------------------------------------------------------

        registrarLog($mysqli, 'insert', 'aplicaciones', $newId, null, $input);
        echo json_encode(["status" => "ok", "msg" => "Aplicación registrada con éxito"]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error al registrar la aplicación"]);
    }
}

$mysqli->close();