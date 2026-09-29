<?php

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
| Permiso
|--------------------------------------------------------------------------
*/

validarPermisoEndpoint($mysqli, $userAuth);


/*
|--------------------------------------------------------------------------
| ID usuario
|--------------------------------------------------------------------------
*/

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {

    echo json_encode([
        "status" => "error",
        "msg" => "Falta ID"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Determinar si el usuario autenticado es ROOT
|--------------------------------------------------------------------------
*/

$esRoot = !empty($userAuth['es_root']);


/*
|--------------------------------------------------------------------------
| Empresa seleccionada
|--------------------------------------------------------------------------
|
| ROOT:
|   - X-EMPRESA-ID
|   - o GET idempresa
|
| USER:
|   - empresa actual mediante contexto
|
*/

$idEmpresaActual = 0;

if ($esRoot) {

    $headers = getallheaders();

    $headerEmpresa = 0;

    foreach ($headers as $nombre => $valor) {

        if (strtoupper($nombre) === 'X-EMPRESA-ID') {

            $headerEmpresa = intval($valor);

            break;
        }
    }

    if ($headerEmpresa > 0) {

        $idEmpresaActual = $headerEmpresa;

    } elseif (
        isset($_GET['idempresa']) &&
        intval($_GET['idempresa']) > 0
    ) {

        $idEmpresaActual = intval($_GET['idempresa']);
    }

} else {

    $empresaActual = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    $idEmpresaActual = intval(
        $empresaActual['idempresa']
    );
}


/*
|--------------------------------------------------------------------------
| Empresa obligatoria
|--------------------------------------------------------------------------
*/

if ($idEmpresaActual <= 0) {

    echo json_encode([
        "status" => "error",
        "msg" => "Debe seleccionar una empresa"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Verificar que la empresa exista y esté activa
|--------------------------------------------------------------------------
*/

$sqlEmpresa = "
    SELECT
        idempresa,
        nombre
    FROM empresas
    WHERE idempresa = ?
      AND activo = 1
    LIMIT 1
";

$stmtEmpresa = $mysqli->prepare($sqlEmpresa);

$stmtEmpresa->bind_param(
    "i",
    $idEmpresaActual
);

$stmtEmpresa->execute();

$resEmpresa = $stmtEmpresa->get_result();

if ($resEmpresa->num_rows === 0) {

    $stmtEmpresa->close();

    echo json_encode([
        "status" => "error",
        "msg" => "Empresa no encontrada"
    ]);

    exit;
}

$stmtEmpresa->close();


/*
|--------------------------------------------------------------------------
| Obtener usuario
|--------------------------------------------------------------------------
|
| IMPORTANTE:
|
| El usuario se busca SOLAMENTE por idusuario.
|
| No se exige que ya exista en usuarios_empresas.
|
| Esto permite:
|
|   usuario existente
|        ↓
|   seleccionar empresa
|        ↓
|   agregar usuario a empresa
|
*/

$sql = "
    SELECT
        u.idusuario,
        u.nombreapellido,
        u.username,
        u.email
    FROM usuarios u
    WHERE u.idusuario = ?
      AND u.baja = 0
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();

$res = $stmt->get_result();

if ($res->num_rows === 0) {

    $stmt->close();
    $mysqli->close();

    echo json_encode([
        "status" => "error",
        "msg" => "No encontrado"
    ]);

    exit;
}

$row = $res->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Obtener accesos que YA tiene en esa empresa
|--------------------------------------------------------------------------
|
| Si todavía no pertenece a la empresa:
|
|   accesos = []
|
| Si ya pertenece:
|
|   se devuelven sus aplicaciones/roles actuales.
|
*/

$sqlAccesos = "
    SELECT
        ura.idaplicacion,
        ura.idtipousuario
    FROM usuarios_roles_apps ura
    WHERE ura.idusuario = ?
      AND ura.idempresa = ?
    ORDER BY ura.idaplicacion
";

$stmtAccesos = $mysqli->prepare($sqlAccesos);

$stmtAccesos->bind_param(
    "ii",
    $id,
    $idEmpresaActual
);

$stmtAccesos->execute();

$resAccesos = $stmtAccesos->get_result();

$accesos = [];

while ($acceso = $resAccesos->fetch_assoc()) {

    $accesos[] = [

        'idaplicacion' =>
            intval($acceso['idaplicacion']),

        'idtipousuario' =>
            intval($acceso['idtipousuario'])
    ];
}

$stmtAccesos->close();


/*
|--------------------------------------------------------------------------
| Verificar si ya pertenece a la empresa
|--------------------------------------------------------------------------
*/

$sqlPertenece = "
    SELECT 1
    FROM usuarios_empresas
    WHERE idusuario = ?
      AND idempresa = ?
      AND activo = 1
    LIMIT 1
";

$stmtPertenece = $mysqli->prepare($sqlPertenece);

$stmtPertenece->bind_param(
    "ii",
    $id,
    $idEmpresaActual
);

$stmtPertenece->execute();

$resPertenece = $stmtPertenece->get_result();

$perteneceEmpresa =
    ($resPertenece->num_rows > 0);

$stmtPertenece->close();


/*
|--------------------------------------------------------------------------
| Respuesta
|--------------------------------------------------------------------------
*/

echo json_encode([
    "status" => "ok",

    "data" => [

        "idusuario" =>
            intval($row['idusuario']),

        "nombreapellido" =>
            $row['nombreapellido'],

        "username" =>
            $row['username'],

        "email" =>
            $row['email'],

        "idempresa" =>
            $idEmpresaActual,

        "pertenece_empresa" =>
            $perteneceEmpresa,

        "accesos" =>
            $accesos
    ]
]);

$mysqli->close();