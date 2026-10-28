<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


// =====================================
// 🔐 NOMBRE DE SESIÓN DESDE .ENV
// =====================================

$sessionName = $_ENV['SESSION_NAME'] ?? 'PHPSESSID';

session_name($sessionName);
ini_set('session.gc_maxlifetime', 7200);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// ⏱ CONTROL DE INACTIVIDAD (8h)
// ===============================
$timeout = 7200; // 8 horas

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    $_SESSION = [];
    session_destroy();

    http_response_code(401);
    echo json_encode(["status" => "error", "msg" => "session_expired"]);
    exit;
}

// renovar actividad
$_SESSION['last_activity'] = time();

// 2. Cargar el autoload (Asegurate que la ruta sea correcta hacia vendor)
require_once __DIR__ . '/../vendor/autoload.php';

// Aseguramos nombre de empresa siempre en sesión (fall back seguro)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    // IMPORTANTE: Si la sesión está vacía, la llenamos con el .env
    if (!isset($_SESSION['MostrarNombre'])) {
        $_SESSION['NombreEmpresa'] = $_ENV['APP_NAME'] ?? 'Mi Sistema';
        $_SESSION['MostrarNombre'] = filter_var($_ENV['MOSTRAR_APP_NAME'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
    
    // Creamos una variable local para que el <title> del dashboard no de error
    $empresa = $_SESSION['NombreEmpresa'];

} catch (Exception $e) {
    $empresa = "Mi Sistema";
}

require __DIR__ . '/../conexion.php';

// ===============================
// 🔎 DETECTAR AJAX
// ===============================
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// ===============================
// 🔴 NO HAY SESIÓN
// ===============================
if (empty($_SESSION['idusuario'])) {

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["status" => "error", "msg" => "no_session"]);
        exit;
    }

    header("Location: /auth/login.php");
    exit();
}

$idusuario = $_SESSION['idusuario'];
$idtipousuario = $_SESSION['idtipousuario'];
$nombreusuario = $_SESSION['nombreusuario'];

//$token = $_SESSION['token'];

// ===============================
// 🔍 VALIDAR TOKEN EN DB
// ===============================
/*
$stmt = $mysqli->prepare("SELECT idusuario, idtipousuario, nombreapellido FROM usuarios WHERE token=? LIMIT 1");

if (!$stmt) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(["status" => "error", "msg" => "sql_error"]);
        exit;
    }
    exit("Error interno");
}

$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

// ===============================
// ❌ TOKEN INVÁLIDO
// ===============================
if (!$user = $res->fetch_assoc()) {

    $_SESSION = [];
    session_destroy();

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["status" => "error", "msg" => "token_invalido"]);
        exit;
    }

    header("Location: /auth/login.php");
    exit();
}

// ===============================
// ✅ RENOVAR SESIÓN
// ===============================
$_SESSION['idusuario'] = $user['idusuario'];
$_SESSION['idtipousuario'] = $user['idtipousuario'];
$_SESSION['nombreusuario'] = $user['nombreapellido'];
*/