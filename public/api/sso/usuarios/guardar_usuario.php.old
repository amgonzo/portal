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

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. 🛡️ Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

$id      = $_POST['id'] ?? '';
$nombre  = trim($_POST['nombre'] ?? '');
$email   = trim($_POST['email'] ?? '');
$login   = trim($_POST['login'] ?? '');
$clave   = trim($_POST['clave'] ?? '');
$accesos = json_decode($_POST['accesos'] ?? '[]', true);

if (!$login || !$nombre || empty($accesos)) {
    echo json_encode(["status" => "error", "msg" => "Datos o accesos incompletos"]);
    exit;
}

if (!$id && empty($clave)) {
    echo json_encode(["status" => "error", "msg" => "Clave obligatoria"]);
    exit;
}

// Validar username único
$sqlCheck = "SELECT idusuario FROM usuarios WHERE username = ?";
$stmtCheck = $mysqli->prepare($sqlCheck);
$stmtCheck->bind_param("s", $login);
$stmtCheck->execute();
$result = $stmtCheck->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!$id || $row['idusuario'] != $id) {
        echo json_encode(["status" => "error", "msg" => "El username ya existe"]);
        exit;
    }
}

$mysqli->begin_transaction();

try {
    if ($id) {
        // EDITAR USUARIO
        $datosAntes = $mysqli->query("SELECT * FROM usuarios WHERE idusuario = $id")->fetch_assoc();

        if (!empty($clave)) {
            $hash = password_hash($clave, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET nombreapellido=?, username=?, password=?, email=? WHERE idusuario=?");
            $stmt->bind_param("ssssi", $nombre, $login, $hash, $email, $id);
        } else {
            $stmt = $mysqli->prepare("UPDATE usuarios SET nombreapellido=?, username=?, email=? WHERE idusuario=?");
            $stmt->bind_param("sssi", $nombre, $login, $email, $id);
        }
        $stmt->execute();
        $idUsuario = $id;

        // Limpiar asignaciones viejas
        $mysqli->query("DELETE FROM usuarios_roles_apps WHERE idusuario = " . intval($idUsuario));

        registrarLog($mysqli, 'edit_usuario', 'usuarios', $idUsuario, $datosAntes, $_POST);
    } else {
        // NUEVO USUARIO
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO usuarios (nombreapellido, username, password, email, baja) VALUES (?, ?, ?, ?, 0)");
        $stmt->bind_param("ssss", $nombre, $login, $hash, $email);
        $stmt->execute();

        $idUsuario = $mysqli->insert_id;

        registrarLog($mysqli, 'alta_usuario', 'usuarios', $idUsuario, null, $_POST);
    }

    // Insertar nuevas relaciones app/rol
    $stmtURA = $mysqli->prepare("INSERT INTO usuarios_roles_apps (idusuario, idtipousuario, idaplicacion) VALUES (?, ?, ?)");
    foreach ($accesos as $acc) {
        $idApp = intval($acc['idaplicacion']);
        $idRol = intval($acc['idtipousuario']);
        $stmtURA->bind_param("iii", $idUsuario, $idRol, $idApp);
        $stmtURA->execute();
    }

    $mysqli->commit();
    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => "Error DB: " . $e->getMessage()]);
}

$mysqli->close();