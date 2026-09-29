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
    // 8. ID DE EMPRESA
    // =====================================================
    //
    // ROOT selecciona la empresa desde el modal.
    //
    // Se acepta:
    //
    //   X-EMPRESA-ID
    //
    // o:
    //
    //   ?idempresa=
    //
    // =====================================================

    $idEmpresa = 0;

    $headers = getallheaders();

    foreach ($headers as $nombre => $valor) {

        if (strtoupper($nombre) === 'X-EMPRESA-ID') {

            $idEmpresa = intval($valor);

            break;
        }
    }


    if (
        $idEmpresa <= 0 &&
        isset($_GET['idempresa'])
    ) {

        $idEmpresa = intval(
            $_GET['idempresa']
        );
    }


    // =====================================================
    // 9. EMPRESA OBLIGATORIA
    // =====================================================

    if ($idEmpresa <= 0) {

        echo json_encode([
            "status" => "error",
            "msg" => "Debe seleccionar una empresa"
        ]);

        exit;
    }


    // =====================================================
    // 10. VERIFICAR EMPRESA
    // =====================================================

    $stmtEmpresa = $mysqli->prepare("
        SELECT idempresa
        FROM empresas
        WHERE idempresa = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmtEmpresa) {

        throw new Exception(
            "Error preparando consulta de empresa"
        );
    }

    $stmtEmpresa->bind_param(
        "i",
        $idEmpresa
    );

    $stmtEmpresa->execute();

    $resEmpresa =
        $stmtEmpresa->get_result();

    if ($resEmpresa->num_rows === 0) {

        $stmtEmpresa->close();

        echo json_encode([
            "status" => "error",
            "msg" => "Empresa no encontrada"
        ]);

        exit;
    }

    $stmtEmpresa->close();


    // =====================================================
    // 11. APLICACIONES DE LA EMPRESA
    // =====================================================
    //
    // Las aplicaciones disponibles para asignar no salen
    // directamente de "aplicaciones".
    //
    // Se determinan mediante:
    //
    //     empresas_aplicaciones
    //             ↓
    //        aplicaciones
    //
    // Ejemplo:
    //
    // Desarrollo
    //    → Fichajes
    //
    // Supermercado La Amistad
    //    → Cuenta Corriente SSO
    //
    // =====================================================

    $sql = "

        SELECT DISTINCT

            a.idaplicacion,
            a.nombre,
            a.slug,
            a.url_base,
            a.activo,
            a.icono

        FROM empresas_aplicaciones ea

        INNER JOIN aplicaciones a
            ON a.idaplicacion =
               ea.idaplicacion

        WHERE ea.idempresa = ?

          AND ea.activo = 1

          AND a.activo = 1

        ORDER BY a.nombre ASC
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Error preparando consulta de aplicaciones"
        );
    }


    $stmt->bind_param(
        "i",
        $idEmpresa
    );


    $stmt->execute();

    $res = $stmt->get_result();


    $aplicaciones = [];


    // =====================================================
    // 12. ARMAR RESULTADO
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
    // 13. RESPUESTA
    // =====================================================

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