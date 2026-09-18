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

$id          = $_POST['id'] ?? '';
$clave       = trim($_POST['clave'] ?? '');
$valor       = trim($_POST['valor'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

if (!$clave || !$valor) {
    echo json_encode(["status" => "error", "msg" => "Faltan campos obligatorios (Clave y Valor)"]);
    exit;
}

// Validar que la clave sea única (respetando el UNIQUE KEY uq_diccionario_clave)
$sqlCheck = "SELECT iddiccionario FROM diccionario WHERE clave = ?";
$stmtCheck = $mysqli->prepare($sqlCheck);
$stmtCheck->bind_param("s", $clave);
$stmtCheck->execute();
$result = $stmtCheck->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!$id || $row['iddiccionario'] != $id) {
        echo json_encode(["status" => "error", "msg" => "La clave del diccionario ya existe"]);
        exit;
    }
}
$stmtCheck->close();

$mysqli->begin_transaction();

try {
    if ($id) {
        // EDITAR DICCIONARIO
        $datosAntes = $mysqli->query("SELECT * FROM diccionario WHERE iddiccionario = " . intval($id))->fetch_assoc();

        $stmt = $mysqli->prepare("UPDATE diccionario SET clave = ?, valor = ?, descripcion = ? WHERE iddiccionario = ?");
        $stmt->bind_param("sssi", $clave, $valor, $descripcion, $id);
        $stmt->execute();
        
        $idDiccionario = $id;

        registrarLog($mysqli, 'edit_diccionario', 'diccionario', $idDiccionario, $datosAntes, $_POST);
    } else {
        // NUEVO DICCIONARIO (activo por defecto en 1 según estructura SQL)
        $stmt = $mysqli->prepare("INSERT INTO diccionario (clave, valor, descripcion, activo) VALUES (?, ?, ?, 1)");
        $stmt->bind_param("sss", $clave, $valor, $descripcion);
        $stmt->execute();

        $idDiccionario = $mysqli->insert_id;

        registrarLog($mysqli, 'alta_diccionario', 'diccionario', $idDiccionario, null, $_POST);
    }

    $mysqli->commit();
    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => "Error DB: " . $e->getMessage()]);
}

$stmt->close();
$mysqli->close();