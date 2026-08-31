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

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

// 1. Validar token y sesión activa (Única llamada limpia y centralizada)
$userAuth = validarTokenAPI($mysqli);

// 2. Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

$tarea = $_POST['tarea'] ?? '';
$id    = $_POST['id'] ?? '';

if (!$tarea || !$id) {
    echo json_encode(["status" => "error", "msg" => "datos_incompletos"]);
    exit();
}

// 3. CAPTURAR ESTADO PREVIO (Para la auditoría)
$res = $mysqli->query("SELECT idusuario, username, baja FROM usuarios WHERE idusuario = " . intval($id));
$datosAntes = $res->fetch_assoc();

if (!$datosAntes) {
    echo json_encode(["status" => "error", "msg" => "usuario_no_encontrado"]);
    exit();
}

// 4. DETERMINAR NUEVO ESTADO
$nuevoEstado = ($tarea === 'baja') ? 1 : 0;

// 5. EJECUTAR UPDATE
$stmt = $mysqli->prepare("UPDATE usuarios SET baja = ? WHERE idusuario = ?");
$stmt->bind_param("ii", $nuevoEstado, $id);

if ($stmt->execute()) {
    // 6. REGISTRO DE AUDITORÍA
    registrarLog(
        $mysqli, 
        $tarea,          
        'usuarios',      
        $id,             
        $datosAntes,     
        ['baja' => $nuevoEstado] 
    );

    echo json_encode(["status" => "ok"]);
} else {
    echo json_encode(["status" => "error", "msg" => "error_db: " . $mysqli->error]);
}

$stmt->close();
$mysqli->close();