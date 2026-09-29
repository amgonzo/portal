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


try {

    // =====================================================
    // 6. AUTENTICACIÓN
    // =====================================================

    $userAuth = validarTokenAPI($mysqli);


    // =====================================================
    // 7. PERMISO DEL ENDPOINT
    // =====================================================

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    // =====================================================
    // 8. TODAS LAS APLICACIONES GLOBALES
    // =====================================================
    //
    // Este endpoint se utiliza exclusivamente para la
    // configuración GLOBAL de permisos.
    //
    // NO se selecciona empresa.
    //
    // La relación empresas_aplicaciones sigue siendo
    // utilizada por el endpoint de Usuarios.
    //
    // =====================================================

    $sql = "

        SELECT

            idaplicacion,
            nombre,
            slug,
            url_base,
            activo,
            icono

        FROM aplicaciones

        WHERE activo = 1

        ORDER BY nombre ASC
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Error preparando consulta de aplicaciones"
        );
    }


    $stmt->execute();

    $res = $stmt->get_result();


    $aplicaciones = [];


    // =====================================================
    // 9. ARMAR RESULTADO
    // =====================================================

    while ($row = $res->fetch_assoc()) {

        $aplicaciones[] = [

            "idaplicacion" =>
                intval(
                    $row['idaplicacion']
                ),

            "nombre" =>
                $row['nombre'],

            "slug" =>
                $row['slug'],

            "url_base" =>
                $row['url_base'],

            "activo" =>
                intval(
                    $row['activo']
                ),

            "icono" =>
                $row['icono'] ??
                'fa-solid fa-cubes'
        ];
    }


    // =====================================================
    // 10. RESPUESTA
    // =====================================================

    echo json_encode([

        "status" =>
            "ok",

        "data" =>
            $aplicaciones

    ], JSON_UNESCAPED_UNICODE);


    $stmt->close();


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "status" =>
            "error",

        "msg" =>
            $e->getMessage()

    ], JSON_UNESCAPED_UNICODE);

} finally {

    if (
        isset($mysqli) &&
        $mysqli instanceof mysqli
    ) {

        $mysqli->close();
    }
}