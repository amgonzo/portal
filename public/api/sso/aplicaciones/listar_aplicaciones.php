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


    $idUsuario = intval(
        $userAuth['idusuario']
    );


    // =====================================================
    // 8. DETERMINAR SUPER_ADMIN
    // =====================================================

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


    // =====================================================
    // 9. SUPER_ADMIN
    // =====================================================
    //
    // El Super Admin puede ver todas las aplicaciones.
    //
    // =====================================================

    if ($esSuperAdmin) {

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

    } else {

        // =================================================
        // 10. USUARIO NORMAL
        // =================================================
        //
        // Solamente puede ver las aplicaciones que tiene
        // asignadas él mismo.
        //
        // Esto evita que un Administrador Empresa pueda
        // asignar a otro usuario una aplicación que él no
        // tiene.
        //
        // =================================================

        $sql = "
            SELECT DISTINCT

                a.idaplicacion,
                a.nombre,
                a.slug,
                a.url_base,
                a.activo,
                a.icono

            FROM usuarios_roles_apps ura

            INNER JOIN aplicaciones a
                ON a.idaplicacion = ura.idaplicacion

            WHERE ura.idusuario = ?
              AND a.activo = 1

            ORDER BY a.nombre ASC
        ";

        $stmt = $mysqli->prepare($sql);

        $stmt->bind_param(
            "i",
            $idUsuario
        );
    }


    if (!$stmt) {

        throw new Exception(
            "Error preparando consulta de aplicaciones"
        );
    }


    $stmt->execute();

    $res = $stmt->get_result();

    $aplicaciones = [];


    while ($row = $res->fetch_assoc()) {

        $aplicaciones[] = [

            "idaplicacion" =>
                intval($row['idaplicacion']),

            "nombre" =>
                $row['nombre'],

            "slug" =>
                $row['slug'],

            "url_base" =>
                $row['url_base'],

            "activo" =>
                intval($row['activo']),

            "icono" =>
                $row['icono'] ??
                'fa-solid fa-cubes'
        ];
    }


    echo json_encode([

        "status" =>
            "ok",

        "data" =>
            $aplicaciones

    ]);


    $stmt->close();


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([

        "status" =>
            "error",

        "msg" =>
            $e->getMessage()

    ]);

} finally {

    if (
        isset($mysqli) &&
        $mysqli instanceof mysqli
    ) {
        $mysqli->close();
    }
}