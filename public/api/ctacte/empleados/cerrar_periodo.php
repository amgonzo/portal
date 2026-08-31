<?php
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];


try {
    // 3. Cargamos el .env usando la ruta definida en rutas.php
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
// api/ctacte/cerrar_periodo.php
header('Content-Type: application/json');


// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$mysqli = conectarDB('CTACTE_');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$mes  = isset($_POST['mes']) ? (int)$_POST['mes'] : 0;
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : 0;

if ($mes < 1 || $mes > 12 || $anio < 2000) {
    echo json_encode(["status" => "error", "msg" => "Período inválido."]);
    exit;
}

try {
    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);
    $datosAntes = ["estado_previo" => "abierto", "periodo_codigo" => $periodoCodigo];

    $stmt = $mysqli->prepare("UPDATE empleados_limites SET cerrado = 1 WHERE periodo_codigo = ? OR (mes = ? AND anio = ?)");
    $stmt->bind_param("sii", $periodoCodigo, $mes, $anio);
    $stmt->execute();
    $filasAfectadas = $stmt->affected_rows;
    $stmt->close();

    if (function_exists('registrarLog')) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        registrarLog($mysqli, 'cerrar_periodo_compras', 'empleados_limites', $periodoCodigo, $idUsuarioLog, $datosAntes, ['estado_nuevo' => 'cerrado_masivo', 'filas' => $filasAfectadas]);
    }

    echo json_encode(["status" => "ok", "msg" => "Período $periodoCodigo cerrado correctamente."]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}