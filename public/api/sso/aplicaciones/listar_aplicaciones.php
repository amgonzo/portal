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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. 🛡️ AGREGAR ESTO: Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

try {
    // 3. Traer aplicaciones activas
    $sql = "SELECT idaplicacion, nombre, slug, url_base, activo 
            FROM aplicaciones 
            WHERE activo = 1 
            ORDER BY nombre ASC";

    $stmt = $mysqli->prepare($sql);
    $stmt->execute();
    $res = $stmt->get_result();

    $aplicaciones = [];
    while ($row = $res->fetch_assoc()) {
        $aplicaciones[] = [
            "idaplicacion" => (int)$row['idaplicacion'],
            "nombre"       => $row['nombre'],
            "slug"         => $row['slug'],
            "url_base"     => $row['url_base'],
            "activo"       => (int)$row['activo']
        ];
    }

    echo json_encode([
        "status" => "ok",
        "data"   => $aplicaciones
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg"    => $e->getMessage()
    ]);
}

$mysqli->close();