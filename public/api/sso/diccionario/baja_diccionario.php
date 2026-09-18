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
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$userAuth = validarTokenAPI($mysqli ?? null);

validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

$mysqli = conectarBase($empresa['db_nombre']);

$tarea = $_POST['tarea'] ?? '';
$id    = $_POST['id'] ?? '';

if (!$tarea || !$id) {
    echo json_encode(["status" => "error", "msg" => "datos_incompletos"]);
    exit();
}

// 3. CAPTURAR ESTADO PREVIO (Para la auditoría)
$res = $mysqli->query("SELECT iddiccionario, clave, valor, activo FROM diccionario WHERE iddiccionario = " . intval($id));
$datosAntes = $res->fetch_assoc();

if (!$datosAntes) {
    echo json_encode(["status" => "error", "msg" => "termino_no_encontrado"]);
    exit();
}

// 4. DETERMINAR NUEVO ESTADO ('alta' -> 1, 'baja' -> 0)
$nuevoEstado = ($tarea === 'alta') ? 1 : 0;

// 5. EJECUTAR UPDATE
$stmt = $mysqli->prepare("UPDATE diccionario SET activo = ? WHERE iddiccionario = ?");
$stmt->bind_param("ii", $nuevoEstado, $id);

if ($stmt->execute()) {
    // 6. REGISTRO DE AUDITORÍA
    registrarLog(
        $mysqli, 
        $tarea,          
        'diccionario',      
        $id,             
        $datosAntes,     
        ['activo' => $nuevoEstado] 
    );

    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => "error_db: " . $mysqli->error]);
}

$stmt->close();
$mysqli->close();