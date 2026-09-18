<?php

// ============================================================
// RUTAS
// ============================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];


// ============================================================
// .ENV
// ============================================================

try {
    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
    // Si ya está cargado, continuamos.
}


// ============================================================
// DEPENDENCIAS
// ============================================================

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');


// ============================================================
// MÉTODO
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit;
}


// ============================================================
// DATOS RECIBIDOS
// ============================================================

$input = json_decode(
    file_get_contents('php://input'),
    true
);

$app_slug = trim(
    $input['app_slug'] ?? ''
);


// ============================================================
// AUTENTICACIÓN
// ============================================================
//
// Ya NO recibimos el token desde JSON.
// validarTokenAPI() lo obtiene del:
//
// Authorization: Bearer TOKEN
//
// ============================================================

$userAuth = validarTokenAPI($mysqli);

$idusuario = intval(
    $userAuth['idusuario']
);


// ============================================================
// VALIDAR APLICACIÓN
// ============================================================

if (empty($app_slug)) {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "app_invalida"
    ]);

    exit;
}


$stmtApp = $mysqli->prepare("
    SELECT
        idaplicacion,
        nombre,
        slug,
        url_base,
        activo

    FROM aplicaciones

    WHERE slug = ?
      AND activo = 1

    LIMIT 1
");

$stmtApp->bind_param(
    "s",
    $app_slug
);

$stmtApp->execute();

$resultadoApp = $stmtApp->get_result();

$app = $resultadoApp->fetch_assoc();

$stmtApp->close();


if (!$app) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "app_invalida"
    ]);

    exit;
}


$idaplicacion = intval(
    $app['idaplicacion']
);


// ============================================================
// VERIFICAR ACCESO DEL USUARIO A LA APLICACIÓN
// ============================================================

$stmtRol = $mysqli->prepare("
    SELECT
        tu.idtipousuario,
        tu.descripcion AS rolnombre

    FROM usuarios_roles_apps ura

    INNER JOIN tiposusuario tu
        ON tu.idtipousuario = ura.idtipousuario

    WHERE ura.idusuario = ?
      AND ura.idaplicacion = ?

    LIMIT 1
");

$stmtRol->bind_param(
    "ii",
    $idusuario,
    $idaplicacion
);

$stmtRol->execute();

$resultadoRol = $stmtRol->get_result();

$rolInfo = $resultadoRol->fetch_assoc();

$stmtRol->close();


if (!$rolInfo) {

    registrarLog(
        $mysqli,
        'acceso_denegado_app',
        'aplicaciones',
        $idaplicacion,
        $idusuario,
        [
            'slug' => $app_slug
        ]
    );

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "sin_acceso_app"
    ]);

    exit;
}


$idtipousuario = intval(
    $rolInfo['idtipousuario']
);


// ============================================================
// CARGAR PERMISOS
// ============================================================

$permisos = [];

$stmtPermisos = $mysqli->prepare("
    SELECT DISTINCT
        p.clavepermiso

    FROM permisos p

    INNER JOIN permisos_rol pr
        ON pr.idpermiso = p.idpermiso

    INNER JOIN aplicaciones_permisos ap
        ON ap.idpermiso = p.idpermiso

    WHERE pr.idtipousuario = ?
      AND ap.idaplicacion = ?
");

$stmtPermisos->bind_param(
    "ii",
    $idtipousuario,
    $idaplicacion
);

$stmtPermisos->execute();

$resultadoPermisos = $stmtPermisos->get_result();

while ($row = $resultadoPermisos->fetch_assoc()) {

    $permisos[] = $row['clavepermiso'];
}

$stmtPermisos->close();


// ============================================================
// AUDITORÍA
// ============================================================

registrarLog(
    $mysqli,
    'ingreso_app',
    'aplicaciones',
    $idaplicacion,
    $idusuario,
    [
        'slug' => $app_slug
    ]
);


// ============================================================
// RESPUESTA
// ============================================================
//
// NO GUARDAMOS NADA EN $_SESSION.
//
// El cliente recibe la información y conserva solamente
// el TOKEN de autenticación.
// ============================================================

echo json_encode([
    "status" => "ok",

    "app" => [
        "idaplicacion" => $idaplicacion,
        "nombre" => $app['nombre'],
        "slug" => $app['slug'],
        "url_base" => $app['url_base'],

        "rol" => $rolInfo['rolnombre'],

        "tipo" => $idtipousuario,

        "permisos" => $permisos
    ]
]);

$mysqli->close();
