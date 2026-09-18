<?php
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli);
validarPermisoEndpoint($mysqli, $userAuth);

$idempresa    = $_POST['idempresa'] ?? '';
$nombre       = trim($_POST['nombre'] ?? '');
$razon_social = trim($_POST['razon_social'] ?? '');
$cuit         = trim($_POST['cuit'] ?? '');
$slug         = trim($_POST['slug'] ?? '');
$db_nombre    = trim($_POST['db_nombre'] ?? '');
$usuarios     = json_decode($_POST['usuarios'] ?? '[]', true);

if (!$nombre || !$slug || !$db_nombre) {
    echo json_encode(["status" => "error", "msg" => "Faltan campos obligatorios (Nombre, Slug y Base de Datos)"]);
    exit;
}

// Validar que el slug sea único
$stmtCheck = $mysqli->prepare("SELECT idempresa FROM empresas WHERE slug = ?");
$stmtCheck->bind_param("s", $slug);
$stmtCheck->execute();
$result = $stmtCheck->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (!$idempresa || $row['idempresa'] != $idempresa) {
        echo json_encode(["status" => "error", "msg" => "El slug de la empresa ya está en uso"]);
        exit;
    }
}

$mysqli->begin_transaction();

try {
    if ($idempresa) {
        // EDITAR EMPRESA
        $datosAntes = $mysqli->query("SELECT * FROM empresas WHERE idempresa = " . intval($idempresa))->fetch_assoc();

        $stmt = $mysqli->prepare("UPDATE empresas SET nombre = ?, razon_social = ?, cuit = ?, slug = ?, db_nombre = ? WHERE idempresa = ?");
        $stmt->bind_param("sssssi", $nombre, $razon_social, $cuit, $slug, $db_nombre, $idempresa);
        $stmt->execute();
        
        $idEmpresaGuardada = $idempresa;

        // Limpiar relaciones viejas de usuarios-empresas
        $mysqli->query("DELETE FROM usuarios_empresas WHERE idempresa = " . intval($idEmpresaGuardada));

        registrarLog($mysqli, 'edit_empresa', 'empresas', $idEmpresaGuardada, $datosAntes, $_POST);
    } else {
        // NUEVA EMPRESA
        $stmt = $mysqli->prepare("INSERT INTO empresas (nombre, razon_social, cuit, slug, db_nombre, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssss", $nombre, $razon_social, $cuit, $slug, $db_nombre);
        $stmt->execute();

        $idEmpresaGuardada = $mysqli->insert_id;

        registrarLog($mysqli, 'alta_empresa', 'empresas', $idEmpresaGuardada, null, $_POST);
    }

    // Insertar nuevas relaciones en usuarios_empresas
    if (!empty($usuarios)) {
        $stmtUE = $mysqli->prepare("INSERT INTO usuarios_empresas (idusuario, idempresa, activo) VALUES (?, ?, 1)");
        foreach ($usuarios as $idUsuario) {
            $idUsrInt = intval($idUsuario);
            $stmtUE->bind_param("ii", $idUsrInt, $idEmpresaGuardada);
            $stmtUE->execute();
        }
    }

    $mysqli->commit();
    echo json_encode(["status" => "ok"]);

} catch (Exception $e) {
    $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => "Error DB: " . $e->getMessage()]);
}

$mysqli->close();