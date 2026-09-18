<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
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
require_once $rutas['contexto'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli ?? null);

validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

$mysqli = conectarBase($empresa['db_nombre']);

$id = $_GET['id'] ?? $_POST['id'] ?? '';

if (!$id) {
    echo json_encode(["status" => "error", "msg" => "id_no_proporcionado"]);
    exit();
}

$stmt = $mysqli->prepare("SELECT iddiccionario, clave, valor, descripcion, activo FROM diccionario WHERE iddiccionario = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(["status" => "error", "msg" => "termino_no_encontrado"]);
    exit();
}

$data = $resultado->fetch_assoc();

echo json_encode([
    "status" => "ok",
    "data" => $data
]);

$stmt->close();
$mysqli->close();