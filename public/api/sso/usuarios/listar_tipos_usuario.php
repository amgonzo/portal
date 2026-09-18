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
// 8. DETERMINAR SUPER_ADMIN
// =========================================================

$idUsuario = intval(
    $userAuth['idusuario']
);

$esSuperAdmin = false;

$stmtSuperAdmin = $mysqli->prepare("
    SELECT 1

    FROM usuarios_roles_apps ura

    INNER JOIN tiposusuario tu
        ON tu.idtipousuario = ura.idtipousuario

    WHERE ura.idusuario = ?
      AND UPPER(TRIM(tu.clave)) = 'SUPER_ADMIN'

    LIMIT 1
");

if ($stmtSuperAdmin) {

    $stmtSuperAdmin->bind_param(
        "i",
        $idUsuario
    );

    $stmtSuperAdmin->execute();

    $resSuperAdmin =
        $stmtSuperAdmin->get_result();

    $esSuperAdmin =
        ($resSuperAdmin->num_rows > 0);

    $stmtSuperAdmin->close();
}


// =========================================================
// 9. OBTENER ROLES
// =========================================================
//
// SUPER_ADMIN:
//     Puede ver todos.
//
// Usuario normal:
//     NO puede asignar SUPER_ADMIN.
//
// =========================================================

if ($esSuperAdmin) {

    $sql = "
        SELECT
            idtipousuario,
            descripcion,
            clave

        FROM tiposusuario

        ORDER BY idtipousuario ASC
    ";

    $stmt = $mysqli->prepare($sql);

} else {

    $sql = "
        SELECT
            idtipousuario,
            descripcion,
            clave

        FROM tiposusuario

        WHERE UPPER(TRIM(clave)) <> 'SUPER_ADMIN'

        ORDER BY idtipousuario ASC
    ";

    $stmt = $mysqli->prepare($sql);
}


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "Error preparando consulta de roles"
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
]);


$stmt->close();
$mysqli->close();