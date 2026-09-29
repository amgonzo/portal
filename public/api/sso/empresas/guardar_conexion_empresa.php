<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

$secretos = require $rutas['secretos'];

if (!is_array($secretos) || empty($secretos['DB_ENCRYPTION_KEY'])) {
    throw new RuntimeException(
        'No está configurada DB_ENCRYPTION_KEY.'
    );
}

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/utils/db_crypto.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit();
}

/* =========================================================
   AUTENTICACIÓN
   ========================================================= */

$userAuth = validarTokenAPI($mysqli);

/* =========================================================
   VERIFICAR ROOT
   ========================================================= */

$sqlTipo = "
    SELECT
        u.idtiposistema,
        uts.clave AS tipo_sistema
    FROM usuarios u
    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema
    WHERE u.idusuario = ?
      AND u.baja = 0
    LIMIT 1
";

$stmtTipo = $mysqli->prepare($sqlTipo);

if (!$stmtTipo) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_tipo: " . $mysqli->error
    ]);

    exit();
}

$stmtTipo->bind_param(
    "i",
    $userAuth['idusuario']
);

$stmtTipo->execute();

$resTipo = $stmtTipo->get_result();
$tipoUsuario = $resTipo->fetch_assoc();

$stmtTipo->close();

if (!$tipoUsuario) {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "usuario_no_valido"
    ]);

    exit();
}

$tipoSistema = strtoupper(
    trim($tipoUsuario['tipo_sistema'])
);

if ($tipoSistema !== 'ROOT') {

    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "acceso_denegado"
    ]);

    exit();
}

/* =========================================================
   DATOS
   ========================================================= */

$idEmpresa = filter_input(
    INPUT_POST,
    'idempresa',
    FILTER_VALIDATE_INT
);

$idConexion = filter_input(
    INPUT_POST,
    'idempresa_conexion',
    FILTER_VALIDATE_INT
);

$clave = strtoupper(
    trim($_POST['clave'] ?? '')
);

$nombre = trim($_POST['nombre'] ?? '');
$tipo = strtoupper(trim($_POST['tipo'] ?? 'MYSQL'));
$host = trim($_POST['host'] ?? '');
$puerto = filter_var(
    $_POST['puerto'] ?? 3306,
    FILTER_VALIDATE_INT
);

$dbNombre = trim($_POST['db_nombre'] ?? '');
$dbUsuario = trim($_POST['db_usuario'] ?? '');
$dbPassword = $_POST['db_password'] ?? '';

$activo = isset($_POST['activo'])
    ? (int) $_POST['activo']
    : 1;

/* =========================================================
   VALIDACIONES
   ========================================================= */

if (!$idEmpresa || $idEmpresa <= 0) {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "idempresa_invalido"
    ]);

    exit();
}

if ($clave === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "clave_requerida"
    ]);

    exit();
}

if ($nombre === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "nombre_requerido"
    ]);

    exit();
}

if ($tipo !== 'MYSQL') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "tipo_no_soportado"
    ]);

    exit();
}

if ($host === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "host_requerido"
    ]);

    exit();
}

if (!$puerto || $puerto <= 0 || $puerto > 65535) {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "puerto_invalido"
    ]);

    exit();
}

if ($dbNombre === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "db_nombre_requerido"
    ]);

    exit();
}

if ($dbUsuario === '') {

    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "db_usuario_requerido"
    ]);

    exit();
}

$activo = $activo === 1 ? 1 : 0;

/* =========================================================
   VERIFICAR EMPRESA
   ========================================================= */

$stmtEmpresa = $mysqli->prepare("
    SELECT idempresa
    FROM empresas
    WHERE idempresa = ?
    LIMIT 1
");

if (!$stmtEmpresa) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_empresa: " . $mysqli->error
    ]);

    exit();
}

$stmtEmpresa->bind_param(
    "i",
    $idEmpresa
);

$stmtEmpresa->execute();

$resEmpresa = $stmtEmpresa->get_result();

if (!$resEmpresa->fetch_assoc()) {

    $stmtEmpresa->close();

    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "msg" => "empresa_no_existe"
    ]);

    exit();
}

$stmtEmpresa->close();

/* =========================================================
   NUEVA CONEXIÓN
   ========================================================= */

if (!$idConexion) {

    if ($dbPassword === '') {

        http_response_code(400);

        echo json_encode([
            "status" => "error",
            "msg" => "password_requerido"
        ]);

        exit();
    }

    $passwordCifrada = cifrarPasswordBD(
        $dbPassword,
        $secretos
    );

    $sql = "
        INSERT INTO empresas_conexiones (
            idempresa,
            clave,
            nombre,
            tipo,
            host,
            puerto,
            db_nombre,
            db_usuario,
            db_password,
            activo
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        if ($mysqli->errno == 1062) {

            http_response_code(409);

            echo json_encode([
                "status" => "error",
                "msg" => "Ya existe una conexión con esa clave para esta empresa."
            ]);

            exit();
        }

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "error_prepare_insert: " . $mysqli->error
        ]);

        exit();
    }

    $stmt->bind_param(
        "isssissssi",
        $idEmpresa,
        $clave,
        $nombre,
        $tipo,
        $host,
        $puerto,
        $dbNombre,
        $dbUsuario,
        $passwordCifrada,
        $activo
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $errno = $stmt->errno;

        $stmt->close();

        if ($errno == 1062 || $mysqli->errno == 1062) {

            http_response_code(409);

            echo json_encode([
                "status" => "error",
                "msg" => "Ya existe una conexión con esa clave para esta empresa."
            ]);

            exit();
        }

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "error_insert: " . $error
        ]);

        exit();
    }

    $nuevoId = $stmt->insert_id;

    $stmt->close();

    echo json_encode([
        "status" => "ok",
        "msg" => "conexion_creada",
        "idempresa_conexion" => $nuevoId
    ]);

    $mysqli->close();

    exit();
}

/* =========================================================
   ACTUALIZAR CONEXIÓN
   ========================================================= */

$stmtExiste = $mysqli->prepare("
    SELECT
        idempresa_conexion,
        db_password
    FROM empresas_conexiones
    WHERE idempresa_conexion = ?
      AND idempresa = ?
    LIMIT 1
");

if (!$stmtExiste) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_conexion: " . $mysqli->error
    ]);

    exit();
}

$stmtExiste->bind_param(
    "ii",
    $idConexion,
    $idEmpresa
);

$stmtExiste->execute();

$resExiste = $stmtExiste->get_result();
$conexionExistente = $resExiste->fetch_assoc();

$stmtExiste->close();

if (!$conexionExistente) {

    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "msg" => "conexion_no_existe"
    ]);

    exit();
}

/*
 * Si no se escribió una nueva contraseña,
 * conservamos la contraseña cifrada actual.
 */
if ($dbPassword !== '') {

     $passwordCifrada = cifrarPasswordBD(
        $dbPassword,
        $secretos
    );

} else {

    $passwordCifrada = $conexionExistente['db_password'];
}

$sql = "
    UPDATE empresas_conexiones
    SET
        clave = ?,
        nombre = ?,
        tipo = ?,
        host = ?,
        puerto = ?,
        db_nombre = ?,
        db_usuario = ?,
        db_password = ?,
        activo = ?
    WHERE idempresa_conexion = ?
      AND idempresa = ?
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_update: " . $mysqli->error
    ]);

    exit();
}

$stmt->bind_param(
    "ssssisssiii",
    $clave,
    $nombre,
    $tipo,
    $host,
    $puerto,
    $dbNombre,
    $dbUsuario,
    $passwordCifrada,
    $activo,
    $idConexion,
    $idEmpresa
);

if (!$stmt->execute()) {

    $error = $stmt->error;
    $errno = $stmt->errno;

    $stmt->close();

    if ($errno == 1062 || $mysqli->errno == 1062) {

        http_response_code(409);

        echo json_encode([
            "status" => "error",
            "msg" => "Ya existe una conexión con esa clave para esta empresa."
        ]);

        exit();
    }

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_update: " . $error
    ]);

    exit();
}

$stmt->close();

echo json_encode([
    "status" => "ok",
    "msg" => "conexion_actualizada",
    "idempresa_conexion" => $idConexion
]);

$mysqli->close();