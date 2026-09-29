<?php

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
    // Continuamos aunque no exista .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| MÉTODO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg"    => "metodo_no_permitido"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
*/

$userAuth = validarTokenAPI($mysqli);

$idusuario = intval(
    $userAuth['idusuario']
);


/*
|--------------------------------------------------------------------------
| DETERMINAR TIPO DE USUARIO
|--------------------------------------------------------------------------
|
| ROOT:
|   Usuario global de la plataforma.
|
| USER:
|   Usuario normal, limitado por sus empresas y roles.
|
|--------------------------------------------------------------------------
*/

$stmtTipo = $mysqli->prepare("
    SELECT
        u.idtiposistema,
        ts.clave AS tipo_sistema
    FROM usuarios u
    INNER JOIN usuarios_tipo_sistema ts
        ON ts.idtiposistema = u.idtiposistema
    WHERE u.idusuario = ?
      AND u.baja = 0
    LIMIT 1
");

if (!$stmtTipo) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg"    => "error_validando_tipo_usuario"
    ]);

    exit;
}

$stmtTipo->bind_param(
    "i",
    $idusuario
);

$stmtTipo->execute();

$resTipo = $stmtTipo->get_result();

$tipoUsuario = $resTipo->fetch_assoc();

$stmtTipo->close();


if (!$tipoUsuario) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg"    => "usuario_invalido"
    ]);

    exit;
}


$esRoot = (
    strtoupper(trim($tipoUsuario['tipo_sistema'])) === 'ROOT'
);


/*
|--------------------------------------------------------------------------
| DETERMINAR EMPRESA ACTUAL
|--------------------------------------------------------------------------
|
| ROOT:
|   Usa X-EMPRESA-ID porque puede cambiar de empresa.
|
| USER:
|   Se obtiene mediante obtenerEmpresaActual().
|
|--------------------------------------------------------------------------
*/

$idEmpresa = 0;


/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/

if ($esRoot) {

    $headers = function_exists('getallheaders')
        ? getallheaders()
        : [];

    $idEmpresaHeader = 0;


    foreach ($headers as $nombre => $valor) {

        if (
            strtoupper(trim($nombre))
            === 'X-EMPRESA-ID'
        ) {

            $idEmpresaHeader = intval(
                $valor
            );

            break;
        }
    }


    /*
     * También soportamos servidores donde
     * Apache/PHP expone el header como HTTP_X_EMPRESA_ID.
     */

    if ($idEmpresaHeader <= 0) {

        $idEmpresaHeader = intval(
            $_SERVER['HTTP_X_EMPRESA_ID'] ?? 0
        );
    }


    if ($idEmpresaHeader <= 0) {

        http_response_code(400);

        echo json_encode([
            "status" => "error",
            "msg"    => "empresa_requerida"
        ]);

        exit;
    }


    /*
     * Validamos que la empresa exista y esté activa.
     */

    $stmtEmpresa = $mysqli->prepare("
        SELECT
            idempresa,
            nombre
        FROM empresas
        WHERE idempresa = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmtEmpresa) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg"    => "error_validando_empresa"
        ]);

        exit;
    }

    $stmtEmpresa->bind_param(
        "i",
        $idEmpresaHeader
    );

    $stmtEmpresa->execute();

    $resEmpresa = $stmtEmpresa->get_result();

    $empresa = $resEmpresa->fetch_assoc();

    $stmtEmpresa->close();


    if (!$empresa) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg"    => "empresa_invalida"
        ]);

        exit;
    }


    $idEmpresa = intval(
        $empresa['idempresa']
    );
}


/*
|--------------------------------------------------------------------------
| USER NORMAL
|--------------------------------------------------------------------------
*/

else {

    $empresaActual = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );


    if (
        !$empresaActual ||
        empty($empresaActual['idempresa'])
    ) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg"    => "empresa_no_disponible"
        ]);

        exit;
    }


    $idEmpresa = intval(
        $empresaActual['idempresa']
    );
}


/*
|--------------------------------------------------------------------------
| OBTENER APLICACIONES
|--------------------------------------------------------------------------
|
| La relación de empresa con aplicación ahora está
| separada de la relación del usuario con su rol.
|
| ROOT:
|   empresa
|      -> empresas_aplicaciones
|      -> aplicaciones
|
| USER:
|   empresa
|      -> empresas_aplicaciones
|      -> aplicaciones
|      -> usuarios_roles_apps
|
| De esta forma una aplicación debe estar habilitada
| para la empresa y, para un USER, además debe tener
| una asignación de rol.
|
|--------------------------------------------------------------------------
*/


if ($esRoot) {

    /*
     * ROOT puede acceder a las aplicaciones habilitadas
     * para la empresa seleccionada.
     */

    $sqlApps = "

        SELECT DISTINCT

            a.idaplicacion,
            a.nombre,
            a.slug,
            a.url_base,
            a.icono

        FROM empresas_aplicaciones ea

        INNER JOIN aplicaciones a
            ON a.idaplicacion = ea.idaplicacion

        WHERE ea.idempresa = ?
          AND ea.activo = 1
          AND a.activo = 1

        ORDER BY a.idaplicacion

    ";

    $stmtApps = $mysqli->prepare(
        $sqlApps
    );


    if (!$stmtApps) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg"    => "error_obteniendo_aplicaciones"
        ]);

        exit;
    }


    $stmtApps->bind_param(
        "i",
        $idEmpresa
    );
}


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

else {

    /*
     * USER solamente ve una aplicación si:
     *
     * 1. La empresa tiene habilitada la aplicación.
     * 2. El usuario tiene un rol asignado en esa
     *    empresa y aplicación.
     */

    $sqlApps = "

        SELECT DISTINCT

            a.idaplicacion,
            a.nombre,
            a.slug,
            a.url_base,
            a.icono

        FROM empresas_aplicaciones ea

        INNER JOIN aplicaciones a
            ON a.idaplicacion = ea.idaplicacion

        INNER JOIN usuarios_roles_apps ura
            ON ura.idaplicacion = a.idaplicacion
           AND ura.idempresa = ea.idempresa
           AND ura.idusuario = ?

        WHERE ea.idempresa = ?
          AND ea.activo = 1
          AND a.activo = 1

        ORDER BY a.idaplicacion

    ";

    $stmtApps = $mysqli->prepare(
        $sqlApps
    );


    if (!$stmtApps) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg"    => "error_obteniendo_aplicaciones"
        ]);

        exit;
    }


    $stmtApps->bind_param(
        "ii",
        $idusuario,
        $idEmpresa
    );
}


/*
|--------------------------------------------------------------------------
| EJECUTAR
|--------------------------------------------------------------------------
*/

$stmtApps->execute();

$resApps = $stmtApps->get_result();


$aplicaciones = [];


while (
    $app = $resApps->fetch_assoc()
) {

    $aplicaciones[] = $app;
}


$stmtApps->close();


/*
|--------------------------------------------------------------------------
| RESPUESTA
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => "ok",

    "idempresa" => $idEmpresa,

    "aplicaciones" => $aplicaciones

], JSON_UNESCAPED_UNICODE);


$mysqli->close();