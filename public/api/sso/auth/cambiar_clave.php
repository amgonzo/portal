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
header('Content-Type: application/json');

try {
    // 1. Requerir la conexión a la Base de Datos y el Middleware
 // Ajusta la ruta relativa según dónde esté guardado tu middleware

    // 🛡️ 2. Validar token, chequear inactividad (30 min) y renovar automáticamente
    // Si no es válido o expiró, la función frena la ejecución aquí mismo y devuelve un HTTP 401.
    $userAuth = validarTokenAPI($mysqli);
    
    // Mantenemos la variable $IDUsuario que usaba tu código original para no romper nada abajo
    $IDUsuario = $userAuth['idusuario'];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
        exit();
    }

    $inputJSON = json_decode(file_get_contents('php://input'), true);
    $clave = $_POST['clave'] ?? ($inputJSON['clave'] ?? '');

    if (!$clave) {
        http_response_code(400);
        echo json_encode(["status" => "error", "msg" => "clave_vacia"]);
        exit();
    }

    if (strlen($clave) < 6) {
        http_response_code(400);
        echo json_encode(["status" => "error", "msg" => "clave_corta"]);
        exit();
    }

    $hash = password_hash($clave, PASSWORD_DEFAULT);

    // En tu DDL `token` admite NULL, así que se inhabilita limpiamente al cambiar la contraseña
    $stmt = $mysqli->prepare("UPDATE usuarios SET password = ?, token = NULL, token_expira = NULL WHERE idusuario = ?");
    if (!$stmt) {
        throw new Exception("Error prepare SQL: " . $mysqli->error);
    }

    $stmt->bind_param("si", $hash, $IDUsuario);

    if (!$stmt->execute()) {
        throw new Exception("Error execute SQL: " . $stmt->error);
    }

    $stmt->close();
    $mysqli->close();

    echo json_encode(["status" => "ok"]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}