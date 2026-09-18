<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json');


/*
|--------------------------------------------------------------------------
| Método
|--------------------------------------------------------------------------
*/

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
| Autenticación
|--------------------------------------------------------------------------
*/

$userAuth = validarTokenAPI($mysqli);


/*
|--------------------------------------------------------------------------
| Permiso del endpoint
|--------------------------------------------------------------------------
*/

validarPermisoEndpoint($mysqli, $userAuth);


/*
|--------------------------------------------------------------------------
| ¿Es SUPER_ADMIN?
|--------------------------------------------------------------------------
*/

$esSuperAdmin = false;

$sqlSuperAdmin = "
    SELECT 1
    FROM usuarios_roles_apps ura
    INNER JOIN tiposusuario tu
        ON tu.idtipousuario = ura.idtipousuario
    WHERE ura.idusuario = ?
      AND UPPER(TRIM(tu.clave)) = 'SUPER_ADMIN'
    LIMIT 1
";

$stmtSuperAdmin = $mysqli->prepare($sqlSuperAdmin);

$idUsuarioAuth = intval($userAuth['idusuario']);

$stmtSuperAdmin->bind_param(
    "i",
    $idUsuarioAuth
);

$stmtSuperAdmin->execute();

$resSuperAdmin = $stmtSuperAdmin->get_result();

$esSuperAdmin = ($resSuperAdmin->num_rows > 0);

$stmtSuperAdmin->close();


/*
|--------------------------------------------------------------------------
| Empresa actual / Filtro por Empresa
|--------------------------------------------------------------------------
|
| Si es SUPER_ADMIN, puede venir un parámetro ?idempresa=X opcionalmente.
| Si es usuario normal, está obligado a usar su empresa actual de sesión.
|
*/

$empresaActual = null;
$idEmpresaFiltro = 0;

if (!$esSuperAdmin) {

    $empresaActual = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    $idEmpresaFiltro = intval(
        $empresaActual['idempresa']
    );

} else {
    // 1. PRIMERO intentamos leer la empresa desde el Header HTTP que manda el JavaScript
    $headers = getallheaders();
    $headerEmpresa = isset($headers['X-EMPRESA-ID'])
    ? intval($headers['X-EMPRESA-ID'])
    : 0;

    // 2. Si no viene en el header, revisamos si vino por GET (por compatibilidad)
    if ($headerEmpresa > 0) {
        $idEmpresaFiltro = $headerEmpresa;
    } elseif (isset($_GET['idempresa']) && intval($_GET['idempresa']) > 0) {
        $idEmpresaFiltro = intval($_GET['idempresa']);
    }

    // 3. Si tenemos un ID de empresa válido, buscamos su nombre para la respuesta
    if ($idEmpresaFiltro > 0) {
        $stmtEmp = $mysqli->prepare("SELECT idempresa, nombre FROM empresas WHERE idempresa = ? LIMIT 1");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $idEmpresaFiltro);
            $stmtEmp->execute();
            $resEmp = $stmtEmp->get_result();
            if ($rowEmp = $resEmp->fetch_assoc()) {
                $empresaActual = [
                    'idempresa' => intval($rowEmp['idempresa']),
                    'nombre' => $rowEmp['nombre']
                ];
            }
            $stmtEmp->close();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Listado
|--------------------------------------------------------------------------
*/

if ($esSuperAdmin && $idEmpresaFiltro === 0) {

    /*
     * SUPER_ADMIN sin filtro de empresa: ve TODOS los usuarios del sistema.
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
            a.nombre AS nombre_app

        FROM usuarios u

        LEFT JOIN usuarios_roles_apps ura
            ON ura.idusuario = u.idusuario

        LEFT JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario

        LEFT JOIN aplicaciones a
            ON a.idaplicacion = ura.idaplicacion

        ORDER BY u.nombreapellido ASC
    ";

    $stmt = $mysqli->prepare($sql);

} else {

    /*
     * Usuarios normales O SUPER_ADMIN filtrando por una empresa específica:
     * SOLO usuarios relacionados con esa empresa.
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
            a.nombre AS nombre_app

        FROM usuarios u

        INNER JOIN usuarios_empresas ue
            ON ue.idusuario = u.idusuario
           AND ue.idempresa = ?
           AND ue.activo = 1

        LEFT JOIN usuarios_roles_apps ura
            ON ura.idusuario = u.idusuario

        LEFT JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario

        LEFT JOIN aplicaciones a
            ON a.idaplicacion = ura.idaplicacion

        ORDER BY u.nombreapellido ASC
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        "i",
        $idEmpresaFiltro
    );
}


/*
|--------------------------------------------------------------------------
| Ejecutar listado
|--------------------------------------------------------------------------
*/

$stmt->execute();

$res = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Armar respuesta
|--------------------------------------------------------------------------
*/

$usuariosMap = [];

while ($fila = $res->fetch_assoc()) {

    $idUser = intval(
        $fila['idusuario']
    );

    if (!isset($usuariosMap[$idUser])) {

        $usuariosMap[$idUser] = [
            'idusuario' => $idUser,
            'nombreapellido' => $fila['nombreapellido'],
            'username' => $fila['username'],
            'email' => $fila['email'],
            'baja' => intval($fila['baja']),
            'accesos' => []
        ];
    }


    if ($fila['idaplicacion'] !== null) {

        $usuariosMap[$idUser]['accesos'][] = [

            'idaplicacion' =>
                intval($fila['idaplicacion']),

            'idtipousuario' =>
                intval($fila['idtipousuario']),

            'nombre_app' =>
                $fila['nombre_app'],

            'rolnombre' =>
                $fila['rolnombre']
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Respuesta
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "ok",

    "empresa" => $empresaActual ? [
        "idempresa" =>
            intval($empresaActual['idempresa']),

        "nombre" =>
            $empresaActual['nombre']
    ] : null,

    "super_admin" =>
        $esSuperAdmin,

    "data" =>
        array_values($usuariosMap)
]);


$stmt->close();
$mysqli->close();