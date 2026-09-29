<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json');

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

$stmtTipo->bind_param("i", $userAuth['idusuario']);
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

$tipoSistema = strtoupper(trim($tipoUsuario['tipo_sistema']));

if ($tipoSistema !== 'ROOT') {
    http_response_code(403);

    echo json_encode([
        "status" => "error",
        "msg" => "acceso_denegado"
    ]);

    exit();
}

/* =========================================================
   DATOS RECIBIDOS
   ========================================================= */

$idEmpresa = isset($_POST['idempresa'])
    ? intval($_POST['idempresa'])
    : 0;

if ($idEmpresa <= 0) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "empresa_no_seleccionada"
    ]);

    exit();
}

$aplicaciones = $_POST['aplicaciones'] ?? [];

if (!is_array($aplicaciones)) {
    $aplicaciones = [];
}

/* Convertir a enteros y eliminar duplicados */

$idsAplicaciones = [];

foreach ($aplicaciones as $idAplicacion) {

    $idAplicacion = intval($idAplicacion);

    if ($idAplicacion > 0) {
        $idsAplicaciones[$idAplicacion] = true;
    }
}

$idsAplicaciones = array_keys($idsAplicaciones);

/* =========================================================
   VERIFICAR EMPRESA
   ========================================================= */

$sqlEmpresa = "
    SELECT idempresa
    FROM empresas
    WHERE idempresa = ?
    LIMIT 1
";

$stmtEmpresa = $mysqli->prepare($sqlEmpresa);

if (!$stmtEmpresa) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_empresa: " . $mysqli->error
    ]);

    exit();
}

$stmtEmpresa->bind_param("i", $idEmpresa);
$stmtEmpresa->execute();

$resEmpresa = $stmtEmpresa->get_result();
$empresa = $resEmpresa->fetch_assoc();

$stmtEmpresa->close();

if (!$empresa) {
    http_response_code(404);

    echo json_encode([
        "status" => "error",
        "msg" => "empresa_no_encontrada"
    ]);

    exit();
}

/* =========================================================
   TRANSACCIÓN
   ========================================================= */

$mysqli->begin_transaction();

try {

    /* =====================================================
       DESACTIVAR TODAS LAS APLICACIONES ACTUALES
       ===================================================== */

    $sqlDesactivar = "
        UPDATE empresas_aplicaciones
        SET activo = 0
        WHERE idempresa = ?
    ";

    $stmtDesactivar = $mysqli->prepare($sqlDesactivar);

    if (!$stmtDesactivar) {
        throw new Exception(
            "error_prepare_desactivar: " . $mysqli->error
        );
    }

    $stmtDesactivar->bind_param("i", $idEmpresa);

    if (!$stmtDesactivar->execute()) {
        throw new Exception(
            "error_desactivar: " . $stmtDesactivar->error
        );
    }

    $stmtDesactivar->close();

    /* =====================================================
       ACTIVAR / CREAR LAS SELECCIONADAS
       ===================================================== */

    if (!empty($idsAplicaciones)) {

        $sqlAplicacion = "
            INSERT INTO empresas_aplicaciones
                (idempresa, idaplicacion, activo)
            SELECT
                ?,
                a.idaplicacion,
                1
            FROM aplicaciones a
            WHERE a.idaplicacion = ?
              AND a.activo = 1
            ON DUPLICATE KEY UPDATE
                activo = 1
        ";

        $stmtAplicacion = $mysqli->prepare($sqlAplicacion);

        if (!$stmtAplicacion) {
            throw new Exception(
                "error_prepare_aplicacion: " . $mysqli->error
            );
        }

        foreach ($idsAplicaciones as $idAplicacion) {

            $stmtAplicacion->bind_param(
                "ii",
                $idEmpresa,
                $idAplicacion
            );

            if (!$stmtAplicacion->execute()) {
                throw new Exception(
                    "error_guardar_aplicacion: " .
                    $stmtAplicacion->error
                );
            }
        }

        $stmtAplicacion->close();
    }

    /* =====================================================
       CONFIRMAR
       ===================================================== */

    $mysqli->commit();

    echo json_encode([
        "status" => "ok",
        "msg" => "Aplicaciones actualizadas correctamente"
    ]);

} catch (Exception $e) {

    $mysqli->rollback();

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}

$mysqli->close();