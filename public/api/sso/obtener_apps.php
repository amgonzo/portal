<?php

// --------------------------------------------------------
// Rutas centrales
// --------------------------------------------------------

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';


// --------------------------------------------------------
// Composer
// --------------------------------------------------------

require_once $rutas['autoload'];


// --------------------------------------------------------
// Variables de entorno
// --------------------------------------------------------

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
    // Si no hay .env, continúa.
}


// --------------------------------------------------------
// Conexión
// --------------------------------------------------------

require_once $rutas['conexion'];


// --------------------------------------------------------
// Middleware de autenticación
// --------------------------------------------------------

require_once $rutas['middleware'];


// --------------------------------------------------------
// Respuesta JSON
// --------------------------------------------------------

header('Content-Type: application/json');


// --------------------------------------------------------
// Validar usuario por Bearer Token
// --------------------------------------------------------

$userAuth = validarTokenAPI($mysqli);


// --------------------------------------------------------
// Validar permiso para este endpoint
// --------------------------------------------------------
/*
validarPermisoEndpoint(
    $mysqli,
    $userAuth
);
*/

// --------------------------------------------------------
// Obtener aplicaciones activas asignadas al usuario
// --------------------------------------------------------

$sqlApps = "
    SELECT DISTINCT
        a.idaplicacion,
        a.nombre,
        a.slug,
        a.url_base,
        a.icono
    FROM aplicaciones a
    INNER JOIN usuarios_roles_apps ura
        ON a.idaplicacion = ura.idaplicacion
    WHERE ura.idusuario = ?
      AND a.activo = 1
    ORDER BY a.idaplicacion
";

$stmtApps = $mysqli->prepare($sqlApps);

if (!$stmtApps) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "Error obteniendo aplicaciones"
    ]);

    exit;
}


$stmtApps->bind_param(
    "i",
    $userAuth['idusuario']
);

$stmtApps->execute();

$resApps = $stmtApps->get_result();

$aplicaciones = [];

while ($app = $resApps->fetch_assoc()) {

    $aplicaciones[] = $app;
}

$stmtApps->close();


// --------------------------------------------------------
// Respuesta
// --------------------------------------------------------

echo json_encode([
    "status" => "ok",
    "aplicaciones" => $aplicaciones
]);