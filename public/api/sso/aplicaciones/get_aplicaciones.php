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

// ⚠️ CORREGIDO: El Content-Type correcto para JSON
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

try {
    // 1. Validar token y sesión activa
    $userAuth = validarTokenAPI($mysqli);

    // 2. Validar si el rol tiene permiso para este endpoint y método
    validarPermisoEndpoint($mysqli, $userAuth);

    // 3. Traer aplicaciones con su icono
    $sql = "SELECT idaplicacion, nombre, slug, url_base, activo, icono 
            FROM aplicaciones 
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
            "activo"       => (int)$row['activo'],
            "icono"        => $row['icono'] ?? 'fa-solid fa-cubes'
        ];
    }

    echo json_encode([
        "status" => "ok",
        "data"   => $aplicaciones
    ]);

} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "msg"    => $e->getMessage()
    ]);
} finally {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}