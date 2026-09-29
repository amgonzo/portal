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
        'status' => 'error',
        'msg' => 'metodo_no_permitido'
    ]);

    exit;
}

$userAuth = validarTokenAPI($mysqli);

/* =========================================================
   VERIFICAR ROOT
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
        'status' => 'error',
        'msg' => 'error_preparando_consulta_usuario'
    ]);

    exit;
}

$stmtTipo->bind_param(
    'i',
    $userAuth['idusuario']
);

$stmtTipo->execute();

$resultadoTipo = $stmtTipo->get_result();
$usuarioTipo = $resultadoTipo->fetch_assoc();

$stmtTipo->close();

if (!$usuarioTipo) {

    http_response_code(403);

    echo json_encode([
        'status' => 'error',
        'msg' => 'usuario_no_valido'
    ]);

    exit;
}

$tipoSistema = strtoupper(
    trim($usuarioTipo['tipo_sistema'] ?? '')
);

if ($tipoSistema !== 'ROOT') {

    http_response_code(403);

    echo json_encode([
        'status' => 'error',
        'msg' => 'solo_root'
    ]);

    exit;
}

/* =========================================================
   ID CONEXIÓN
   ========================================================= */

$idConexion = filter_input(
    INPUT_GET,
    'idempresa_conexion',
    FILTER_VALIDATE_INT
);

if (!$idConexion || $idConexion <= 0) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'id_conexion_invalido'
    ]);

    exit;
}

/* =========================================================
   OBTENER CONEXIÓN
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
        activo
    FROM empresas_conexiones
    WHERE idempresa_conexion = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => 'error_preparando_consulta'
    ]);

    exit;
}

$stmt->bind_param(
    'i',
    $idConexion
);

$stmt->execute();

$resultado = $stmt->get_result();
$conexion = $resultado->fetch_assoc();

$stmt->close();

if (!$conexion) {

    http_response_code(404);

    echo json_encode([
        'status' => 'error',
        'msg' => 'conexion_no_encontrada'
    ]);

    exit;
}

echo json_encode([
    'status' => 'ok',
    'conexion' => $conexion
]);

$mysqli->close();