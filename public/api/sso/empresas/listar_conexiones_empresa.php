<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit();
}

/* =========================================================
   AUTENTICACIÓN
   ========================================================= */

$userAuth = validarTokenAPI($mysqli);

/* =========================================================
   VERIFICAR TIPO DE SISTEMA
   ROOT ES GLOBAL
   ========================================================= */

$sqlTipo = "
    SELECT
        u.idtiposistema,
        uts.clave AS tipo_sistema
    FROM usuarios u
    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema
    WHERE u.idusuario = ?
      AND u.baja = 0
    LIMIT 1
";

$stmtTipo = $mysqli->prepare($sqlTipo);

if (!$stmtTipo) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_tipo: " . $mysqli->error
    ]);

    exit();
}

$stmtTipo->bind_param("i", $userAuth['idusuario']);
$stmtTipo->execute();

$resTipo = $stmtTipo->get_result();
$tipoUsuario = $resTipo->fetch_assoc();

$stmtTipo->close();

if (!$tipoUsuario) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "usuario_no_valido"
    ]);

    exit();
}

$tipoSistema = strtoupper(trim($tipoUsuario['tipo_sistema']));

if ($tipoSistema !== 'ROOT') {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "acceso_denegado"
    ]);

    exit();
}

/* =========================================================
   ID EMPRESA
   ========================================================= */

$idEmpresa = filter_input(
    INPUT_GET,
    'idempresa',
    FILTER_VALIDATE_INT
);

if (!$idEmpresa || $idEmpresa <= 0) {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "idempresa_invalido"
    ]);

    exit();
}

/* =========================================================
   VERIFICAR EMPRESA
   ========================================================= */

$sqlEmpresa = "
    SELECT
        idempresa,
        nombre
    FROM empresas
    WHERE idempresa = ?
    LIMIT 1
";

$stmtEmpresa = $mysqli->prepare($sqlEmpresa);

if (!$stmtEmpresa) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_empresa: " . $mysqli->error
    ]);

    exit();
}

$stmtEmpresa->bind_param("i", $idEmpresa);
$stmtEmpresa->execute();

$resEmpresa = $stmtEmpresa->get_result();
$empresa = $resEmpresa->fetch_assoc();

$stmtEmpresa->close();

if (!$empresa) {

    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "msg" => "empresa_no_existe"
    ]);

    exit();
}

/* =========================================================
   LISTAR CONEXIONES
   ========================================================= */

$sql = "
    SELECT
        idempresa_conexion,
        idempresa,
        clave,
        nombre,
        tipo,
        host,
        puerto,
        db_nombre,
        db_usuario,
        activo,
        created_at,
        updated_at
    FROM empresas_conexiones
    WHERE idempresa = ?
    ORDER BY nombre ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_conexiones: " . $mysqli->error
    ]);

    exit();
}

$stmt->bind_param("i", $idEmpresa);
$stmt->execute();

$res = $stmt->get_result();

$conexiones = [];

while ($fila = $res->fetch_assoc()) {

    $conexiones[] = [
        'idempresa_conexion' => intval($fila['idempresa_conexion']),
        'idempresa'          => intval($fila['idempresa']),
        'clave'              => $fila['clave'],
        'nombre'             => $fila['nombre'],
        'tipo'               => $fila['tipo'],
        'host'               => $fila['host'],
        'puerto'             => intval($fila['puerto']),
        'db_nombre'          => $fila['db_nombre'],
        'db_usuario'         => $fila['db_usuario'],
        'activo'             => intval($fila['activo']),
        'created_at'         => $fila['created_at'],
        'updated_at'         => $fila['updated_at']
    ];
}

$stmt->close();

/* =========================================================
   RESPUESTA
   ========================================================= */

echo json_encode([
    "status" => "ok",
    "empresa" => [
        "idempresa" => intval($empresa['idempresa']),
        "nombre" => $empresa['nombre']
    ],
    "conexiones" => $conexiones
]);

$mysqli->close();