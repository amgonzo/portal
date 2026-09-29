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
require_once $rutas['auditoria'];

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
| DATOS RECIBIDOS
|--------------------------------------------------------------------------
*/

$input = json_decode(
    file_get_contents('php://input'),
    true
);

$app_slug = trim(
    $input['app_slug'] ?? ''
);


if ($app_slug === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg"    => "app_invalida"
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
| DETERMINAR TIPO DE SISTEMA
|--------------------------------------------------------------------------
|
| ROOT / USER
|
| El tipo de sistema NO se determina por el ID del usuario
| ni por un rol de aplicación.
|
|--------------------------------------------------------------------------
*/

$stmtTipoSistema = $mysqli->prepare("
    SELECT
        u.idtiposistema,
        uts.nombre,
        uts.clave
    FROM usuarios u
    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema
    WHERE u.idusuario = ?
    LIMIT 1
");


if (!$stmtTipoSistema) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg"    => "error_validando_tipo_sistema"
    ]);

    exit;
}


$stmtTipoSistema->bind_param(
    "i",
    $idusuario
);


$stmtTipoSistema->execute();

$resultadoTipoSistema =
    $stmtTipoSistema->get_result();

$tipoSistema =
    $resultadoTipoSistema->fetch_assoc();

$stmtTipoSistema->close();


if (!$tipoSistema) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg"    => "tipo_sistema_invalido"
    ]);

    exit;
}


$esRoot =
    strtoupper(
        trim($tipoSistema['clave'])
    ) === 'ROOT';


/*
|--------------------------------------------------------------------------
| DETERMINAR EMPRESA
|--------------------------------------------------------------------------
*/

$idEmpresa = 0;


/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
|
| ROOT puede trabajar con la empresa enviada mediante
| X-EMPRESA-ID.
|
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

            $idEmpresaHeader = intval($valor);

            break;
        }
    }


    /*
     * Compatibilidad con Apache/PHP.
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
     * Validamos empresa activa.
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

    $resEmpresa =
        $stmtEmpresa->get_result();

    $empresa =
        $resEmpresa->fetch_assoc();

    $stmtEmpresa->close();


    if (!$empresa) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg"    => "empresa_invalida"
        ]);

        exit;
    }


    $idEmpresa =
        intval($empresa['idempresa']);
}


/*
|--------------------------------------------------------------------------
| USUARIO NORMAL
|--------------------------------------------------------------------------
*/

else {

    /*
     * Obtener la empresa actualmente seleccionada.
     *
     * obtenerEmpresaActual() ya valida la empresa
     * correspondiente al usuario.
     */

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


    $idEmpresa =
        intval($empresaActual['idempresa']);
}


/*
|--------------------------------------------------------------------------
| BUSCAR APLICACIÓN
|--------------------------------------------------------------------------
|
| La aplicación debe estar:
|
|   1. Activa
|   2. Habilitada para la empresa
|
|--------------------------------------------------------------------------
*/

$stmtApp = $mysqli->prepare("
    SELECT
        a.idaplicacion,
        a.nombre,
        a.slug,
        a.url_base,
        a.icono
    FROM aplicaciones a

    INNER JOIN empresas_aplicaciones ea
        ON ea.idaplicacion = a.idaplicacion

    WHERE a.slug = ?
      AND a.activo = 1
      AND ea.idempresa = ?
      AND ea.activo = 1

    LIMIT 1
");


if (!$stmtApp) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg"    => "error_consultando_aplicacion"
    ]);

    exit;
}


$stmtApp->bind_param(
    "si",
    $app_slug,
    $idEmpresa
);


$stmtApp->execute();

$resultadoApp =
    $stmtApp->get_result();

$app =
    $resultadoApp->fetch_assoc();

$stmtApp->close();


if (!$app) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg"    => "app_no_habilitada"
    ]);

    exit;
}


$idaplicacion =
    intval($app['idaplicacion']);


/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
|
| ROOT NO TIENE ROL DE APLICACIÓN.
|
| No consultamos usuarios_roles_apps.
| No asignamos SUPER_ADMIN.
|
|--------------------------------------------------------------------------
*/

if ($esRoot) {

    $rolNombre = 'ROOT';
    $idtipousuario = null;
    $permisos = [];

}


/*
|--------------------------------------------------------------------------
| USUARIO NORMAL
|--------------------------------------------------------------------------
|
| USER necesita un rol específico para:
|
|   usuario + empresa + aplicación
|
|--------------------------------------------------------------------------
*/

else {

    $stmtRol = $mysqli->prepare("

        SELECT
            tu.idtipousuario,
            tu.clave AS rolnombre

        FROM usuarios_roles_apps ura

        INNER JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario

        WHERE ura.idusuario = ?
          AND ura.idempresa = ?
          AND ura.idaplicacion = ?

        LIMIT 1

    ");


    if (!$stmtRol) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg"    => "error_validando_acceso"
        ]);

        exit;
    }


    $stmtRol->bind_param(
        "iii",
        $idusuario,
        $idEmpresa,
        $idaplicacion
    );


    $stmtRol->execute();

    $resultadoRol =
        $stmtRol->get_result();

    $rolInfo =
        $resultadoRol->fetch_assoc();

    $stmtRol->close();


    /*
     * Sin rol = sin acceso.
     */

    if (!$rolInfo) {

        registrarLog(
            $mysqli,
            'acceso_denegado_app',
            'aplicaciones',
            $idaplicacion,
            $idusuario,
            [
                'slug'      => $app_slug,
                'idempresa' => $idEmpresa
            ]
        );


        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg"    => "sin_acceso_app"
        ]);

        exit;
    }


    $idtipousuario =
        intval($rolInfo['idtipousuario']);

    $rolNombre =
        $rolInfo['rolnombre'];


    /*
     * Obtener permisos del rol para esta aplicación.
     */

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


    if (!$stmtPermisos) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg"    => "error_obteniendo_permisos"
        ]);

        exit;
    }


    $stmtPermisos->bind_param(
        "ii",
        $idtipousuario,
        $idaplicacion
    );


    $stmtPermisos->execute();

    $resultadoPermisos =
        $stmtPermisos->get_result();


    while (
        $row = $resultadoPermisos->fetch_assoc()
    ) {

        $permisos[] =
            $row['clavepermiso'];
    }


    $stmtPermisos->close();
}


/*
|--------------------------------------------------------------------------
| AUDITORÍA
|--------------------------------------------------------------------------
*/

registrarLog(
    $mysqli,
    'ingreso_app',
    'aplicaciones',
    $idaplicacion,
    $idusuario,
    [
        'slug'      => $app_slug,
        'idempresa' => $idEmpresa,
        'tipo_sistema' =>
            $tipoSistema['clave']
    ]
);


/*
|--------------------------------------------------------------------------
| RESPUESTA
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => "ok",

    "app" => [

        "idaplicacion" =>
            $idaplicacion,

        "nombre" =>
            $app['nombre'],

        "slug" =>
            $app['slug'],

        "url_base" =>
            $app['url_base'],

        "idempresa" =>
            $idEmpresa,

        "rol" =>
            $rolNombre,

        "tipo" =>
            $idtipousuario,

        "permisos" =>
            $permisos
    ]

], JSON_UNESCAPED_UNICODE);


$mysqli->close();