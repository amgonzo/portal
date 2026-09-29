<?php
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

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
   AUTENTICACIÓN Y PERMISOS
   ========================================================= */

$userAuth = validarTokenAPI($mysqli);
validarPermisoEndpoint($mysqli, $userAuth);

/* =========================================================
   ID EMPRESA
   ========================================================= */

$idempresa = intval($_GET['id'] ?? 0);

if ($idempresa <= 0) {

    echo json_encode([
        "status" => "error",
        "msg" => "ID de empresa no proporcionado"
    ]);

    exit;
}

/* =========================================================
   OBTENER EMPRESA
   ========================================================= */

$stmt = $mysqli->prepare("
    SELECT
        idempresa,
        nombre,
        razon_social,
        cuit,
        slug,
        db_nombre,
        activo
    FROM empresas
    WHERE idempresa = ?
");

$stmt->bind_param("i", $idempresa);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "msg" => "Empresa no encontrada"
    ]);

    $mysqli->close();
    exit;
}

$empresa = $resultado->fetch_assoc();

$empresa['idempresa'] =
    intval($empresa['idempresa']);

$empresa['activo'] =
    intval($empresa['activo']);

/* =========================================================
   RESPUESTA
   ========================================================= */

echo json_encode([
    "status" => "ok",
    "data" => $empresa
]);

$mysqli->close();