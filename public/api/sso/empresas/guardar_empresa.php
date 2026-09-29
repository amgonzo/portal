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

$idempresa    = intval($_POST['idempresa'] ?? 0);
$nombre       = trim($_POST['nombre'] ?? '');
$razon_social = trim($_POST['razon_social'] ?? '');
$cuit         = trim($_POST['cuit'] ?? '');
$slug         = trim($_POST['slug'] ?? '');
$db_nombre    = trim($_POST['db_nombre'] ?? '');

if (!$nombre || !$slug || !$db_nombre) {
    echo json_encode([
        "status" => "error",
        "msg" => "Faltan campos obligatorios (Nombre, Slug y Base de Datos)"
    ]);
    exit;
}

/* =========================================================
   VALIDAR SLUG ÚNICO
   ========================================================= */

$stmtCheck = $mysqli->prepare(
    "SELECT idempresa
     FROM empresas
     WHERE slug = ?"
);

$stmtCheck->bind_param("s", $slug);
$stmtCheck->execute();

$result = $stmtCheck->get_result();

if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    if (!$idempresa || intval($row['idempresa']) !== $idempresa) {

        echo json_encode([
            "status" => "error",
            "msg" => "El slug de la empresa ya está en uso"
        ]);

        exit;
    }
}

/* =========================================================
   TRANSACCIÓN
   ========================================================= */

$mysqli->begin_transaction();

try {

    if ($idempresa > 0) {

        /* =====================================================
           EDITAR EMPRESA
           ===================================================== */

        $stmtAntes = $mysqli->prepare(
            "SELECT *
             FROM empresas
             WHERE idempresa = ?"
        );

        $stmtAntes->bind_param("i", $idempresa);
        $stmtAntes->execute();

        $resultadoAntes = $stmtAntes->get_result();

        if ($resultadoAntes->num_rows === 0) {

            throw new Exception(
                "La empresa no existe"
            );
        }

        $datosAntes =
            $resultadoAntes->fetch_assoc();


        $stmt = $mysqli->prepare(
            "UPDATE empresas
             SET
                nombre = ?,
                razon_social = ?,
                cuit = ?,
                slug = ?,
                db_nombre = ?
             WHERE idempresa = ?"
        );

        $stmt->bind_param(
            "sssssi",
            $nombre,
            $razon_social,
            $cuit,
            $slug,
            $db_nombre,
            $idempresa
        );

        if (!$stmt->execute()) {
            throw new Exception(
                $stmt->error
            );
        }


        $idEmpresaGuardada =
            $idempresa;


        registrarLog(
            $mysqli,
            'edit_empresa',
            'empresas',
            $idEmpresaGuardada,
            $datosAntes,
            $_POST
        );

    } else {

        /* =====================================================
           NUEVA EMPRESA
           ===================================================== */

        $stmt = $mysqli->prepare(
            "INSERT INTO empresas
                (
                    nombre,
                    razon_social,
                    cuit,
                    slug,
                    db_nombre,
                    activo
                )
             VALUES
                (?, ?, ?, ?, ?, 1)"
        );

        $stmt->bind_param(
            "sssss",
            $nombre,
            $razon_social,
            $cuit,
            $slug,
            $db_nombre
        );

        if (!$stmt->execute()) {
            throw new Exception(
                $stmt->error
            );
        }


        $idEmpresaGuardada =
            $mysqli->insert_id;


        registrarLog(
            $mysqli,
            'alta_empresa',
            'empresas',
            $idEmpresaGuardada,
            null,
            $_POST
        );
    }


    $mysqli->commit();

    echo json_encode([
        "status" => "ok",
        "idempresa" => intval($idEmpresaGuardada)
    ]);

} catch (Exception $e) {

    $mysqli->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => "Error DB: " . $e->getMessage()
    ]);
}

$mysqli->close();