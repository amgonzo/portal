<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| VALIDAR TOKEN
|--------------------------------------------------------------------------
*/

$userAuth = validarTokenAPI($mysqli);

$idUsuarioAuth =
    intval($userAuth['idusuario']);


/*
|--------------------------------------------------------------------------
| DETERMINAR TIPO DE SISTEMA
|--------------------------------------------------------------------------
|
| ROOT:
|   Usuario global de la plataforma.
|
| USER:
|   Usuario normal, cuyo acceso se determina
|   por empresa + aplicación + rol.
|
*/

$sqlTipoSistema = "
    SELECT
        uts.clave AS tipo_sistema
    FROM usuarios u

    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema

    WHERE u.idusuario = ?
      AND u.baja = 0

    LIMIT 1
";

$stmtTipo =
    $mysqli->prepare($sqlTipoSistema);

if (!$stmtTipo) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "No se pudo determinar el tipo de usuario"
    ]);

    exit;
}

$stmtTipo->bind_param(
    "i",
    $idUsuarioAuth
);

$stmtTipo->execute();

$resTipo =
    $stmtTipo->get_result();

$filaTipo =
    $resTipo->fetch_assoc();

$stmtTipo->close();

$tipoSistema =
    strtoupper(
        trim(
            $filaTipo['tipo_sistema'] ?? ''
        )
    );

$esRoot =
    ($tipoSistema === 'ROOT');


/*
|--------------------------------------------------------------------------
| PERMISO
|--------------------------------------------------------------------------
|
| ROOT tiene todos los permisos desde me.php,
| por lo tanto no necesita tener un rol en
| usuarios_roles_apps para acceder a este endpoint.
|
| USER sí debe pasar por la validación normal.
|
*/

if (!$esRoot) {

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );
}


/*
|--------------------------------------------------------------------------
| EMPRESA ACTUAL
|--------------------------------------------------------------------------
*/

$empresaActual = null;

$idEmpresaFiltro = 0;


/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
|
| ROOT puede trabajar:
|
|   - sin empresa seleccionada -> todas
|   - con X-EMPRESA-ID -> una empresa
|
*/

if ($esRoot) {

    $headers = getallheaders();

    $headerEmpresa =
        $headers['X-EMPRESA-ID']
        ?? $headers['x-empresa-id']
        ?? '';

    $idEmpresaFiltro =
        intval($headerEmpresa);

    /*
     * Compatibilidad:
     * también aceptamos idempresa por GET.
     */

    if (
        $idEmpresaFiltro <= 0 &&
        isset($_GET['idempresa']) &&
        intval($_GET['idempresa']) > 0
    ) {

        $idEmpresaFiltro =
            intval($_GET['idempresa']);
    }


    /*
     * Si ROOT seleccionó una empresa,
     * obtenemos sus datos.
     */

    if ($idEmpresaFiltro > 0) {

        $stmtEmp =
            $mysqli->prepare("
                SELECT
                    idempresa,
                    nombre

                FROM empresas

                WHERE idempresa = ?
                  AND activo = 1

                LIMIT 1
            ");

        if ($stmtEmp) {

            $stmtEmp->bind_param(
                "i",
                $idEmpresaFiltro
            );

            $stmtEmp->execute();

            $resEmp =
                $stmtEmp->get_result();

            if (
                $rowEmp =
                    $resEmp->fetch_assoc()
            ) {

                $empresaActual = [

                    'idempresa' =>
                        intval(
                            $rowEmp['idempresa']
                        ),

                    'nombre' =>
                        $rowEmp['nombre']
                ];

            } else {

                http_response_code(404);

                echo json_encode([
                    "status" => "error",
                    "msg" => "Empresa inexistente o inactiva"
                ]);

                $stmtEmp->close();
                exit;
            }

            $stmtEmp->close();
        }
    }


} else {

    /*
     * USER
     *
     * La empresa sale del contexto actual.
     */

    $empresaActual =
        obtenerEmpresaActual(
            $mysqli,
            $userAuth
        );

    if (!$empresaActual) {

        http_response_code(400);

        echo json_encode([
            "status" => "error",
            "msg" => "No se pudo determinar la empresa actual"
        ]);

        exit;
    }

    $idEmpresaFiltro =
        intval(
            $empresaActual['idempresa']
        );
}


/*
|--------------------------------------------------------------------------
| LISTADO
|--------------------------------------------------------------------------
|
| ROOT SIN EMPRESA:
|   Ve todos los usuarios y todos sus accesos.
|
| ROOT CON EMPRESA:
|   Ve solamente usuarios de esa empresa.
|
| USER:
|   Ve solamente usuarios de su empresa actual.
|
*/


if (
    $esRoot &&
    $idEmpresaFiltro === 0
) {

    /*
     * ROOT SIN EMPRESA
     *
     * Todos los usuarios.
     */

    $sql = "
        SELECT

            u.idusuario,
            u.nombreapellido,
            u.username,
            u.email,
            u.baja,

            ura.idtipousuario,
            ura.idaplicacion,

            tu.descripcion AS rolnombre,
            a.nombre AS nombre_app,

            e.idempresa,
            e.nombre AS nombre_empresa

        FROM usuarios u

        LEFT JOIN usuarios_roles_apps ura
            ON ura.idusuario = u.idusuario

        LEFT JOIN tiposusuario tu
            ON tu.idtipousuario =
               ura.idtipousuario

        LEFT JOIN aplicaciones a
            ON a.idaplicacion =
               ura.idaplicacion

        LEFT JOIN empresas e
            ON e.idempresa =
               ura.idempresa

        ORDER BY
            u.nombreapellido ASC,
            e.nombre ASC,
            a.nombre ASC
    ";

    $stmt =
        $mysqli->prepare($sql);

} else {

    /*
     * ROOT CON EMPRESA
     * o
     * USER
     *
     * Solamente usuarios pertenecientes
     * a esa empresa.
     */

    $sql = "
        SELECT

            u.idusuario,
            u.nombreapellido,
            u.username,
            u.email,
            u.baja,

            ura.idtipousuario,
            ura.idaplicacion,

            tu.descripcion AS rolnombre,
            a.nombre AS nombre_app,

            e.idempresa,
            e.nombre AS nombre_empresa

        FROM usuarios u

        INNER JOIN usuarios_empresas ue

            ON ue.idusuario =
               u.idusuario

           AND ue.idempresa = ?

           AND ue.activo = 1

        LEFT JOIN usuarios_roles_apps ura

            ON ura.idusuario =
               u.idusuario

           AND ura.idempresa = ?

        LEFT JOIN tiposusuario tu
            ON tu.idtipousuario =
               ura.idtipousuario

        LEFT JOIN aplicaciones a
            ON a.idaplicacion =
               ura.idaplicacion

        LEFT JOIN empresas e
            ON e.idempresa =
               ura.idempresa

        ORDER BY
            u.nombreapellido ASC,
            a.nombre ASC
    ";

    $stmt =
        $mysqli->prepare($sql);

    $stmt->bind_param(
        "ii",
        $idEmpresaFiltro,
        $idEmpresaFiltro
    );
}


/*
|--------------------------------------------------------------------------
| EJECUTAR
|--------------------------------------------------------------------------
*/

$stmt->execute();

$res =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| ARMAR RESPUESTA
|--------------------------------------------------------------------------
*/

$usuariosMap = [];


while (
    $fila =
        $res->fetch_assoc()
) {

    $idUser =
        intval(
            $fila['idusuario']
        );


    if (
        !isset(
            $usuariosMap[$idUser]
        )
    ) {

        $usuariosMap[$idUser] = [

            'idusuario' =>
                $idUser,

            'nombreapellido' =>
                $fila['nombreapellido'],

            'username' =>
                $fila['username'],

            'email' =>
                $fila['email'],

            'baja' =>
                intval(
                    $fila['baja']
                ),

            'accesos' => []
        ];
    }


    /*
     * Agregar acceso
     */

    if (
        $fila['idaplicacion'] !== null
    ) {

        $usuariosMap[$idUser]['accesos'][] = [

            'idaplicacion' =>
                intval(
                    $fila['idaplicacion']
                ),

            'idtipousuario' =>
                intval(
                    $fila['idtipousuario']
                ),

            'nombre_app' =>
                $fila['nombre_app'],

            'rolnombre' =>
                $fila['rolnombre'],

            'idempresa' =>
                $fila['idempresa'] !== null
                    ? intval(
                        $fila['idempresa']
                    )
                    : null,

            'nombre_empresa' =>
                $fila['nombre_empresa']
        ];
    }
}


/*
|--------------------------------------------------------------------------
| RESPUESTA
|--------------------------------------------------------------------------
*/

echo json_encode([

    "status" => "ok",

    "empresa" =>
        $empresaActual
            ? [

                "idempresa" =>
                    intval(
                        $empresaActual['idempresa']
                    ),

                "nombre" =>
                    $empresaActual['nombre']

            ]
            : null,

    /*
     * Mantenemos el nombre por compatibilidad
     * con el JS actual.
     *
     * Pero ahora significa ROOT.
     */

    "root" =>
        $esRoot,

    "super_admin" =>
        false,

    "data" =>
        array_values(
            $usuariosMap
        )
]);


$stmt->close();

$mysqli->close();