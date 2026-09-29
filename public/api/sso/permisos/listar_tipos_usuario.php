<?php

// =========================================================
// 1. RUTAS CENTRALES
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';


// =========================================================
// 2. COMPOSER
// =========================================================

require_once $rutas['autoload'];


// =========================================================
// 3. .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
    // Continuamos si no existe .env
}


// =========================================================
// 4. DEPENDENCIAS
// =========================================================

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');


// =========================================================
// 5. MÉTODO
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit;
}


// =========================================================
// 6. AUTENTICACIÓN
// =========================================================

$userAuth = validarTokenAPI($mysqli);


// =========================================================
// 7. PERMISO DEL ENDPOINT
// =========================================================

validarPermisoEndpoint(
    $mysqli,
    $userAuth
);


// =========================================================
// 8. OBTENER TIPOS DE USUARIO
// =========================================================
//
// Este endpoint es exclusivo de Configurar permisos.
//
// tiposusuario es un catálogo GLOBAL de roles de aplicación.
//
// NO se consulta empresa.
// NO se consulta usuarios_roles_apps.
// NO se determina ROOT.
// NO se determina SUPER_ADMIN.
//
// =========================================================

$sql = "
    SELECT
        idtipousuario,
        descripcion,
        clave

    FROM tiposusuario

    ORDER BY idtipousuario ASC
";


$stmt = $mysqli->prepare($sql);


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "Error preparando consulta de tipos de usuario"
    ]);

    $mysqli->close();

    exit;
}


$stmt->execute();

$res = $stmt->get_result();

$tipos = [];


while ($fila = $res->fetch_assoc()) {

    $tipos[] = [
        "idtipousuario" =>
            intval($fila['idtipousuario']),

        "descripcion" =>
            $fila['descripcion'],

        "clave" =>
            $fila['clave']
    ];
}


echo json_encode([
    "status" => "ok",
    "data" => $tipos
], JSON_UNESCAPED_UNICODE);


$stmt->close();
$mysqli->close();