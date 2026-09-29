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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
   EMPRESA
   ========================================================= */

$idEmpresa = isset($_GET['idempresa'])
    ? intval($_GET['idempresa'])
    : 0;

if ($idEmpresa <= 0) {
    http_response_code(400);

    echo json_encode([
        "status" => "error",
        "msg" => "empresa_no_seleccionada"
    ]);

    exit();
}

/* =========================================================
   VERIFICAR EMPRESA
   ========================================================= */

$sqlEmpresa = "
    SELECT
        idempresa,
        nombre,
        activo
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
   APLICACIONES
   =========================================================
   Traemos todas las aplicaciones activas y marcamos
   cuáles están asignadas a la empresa.
   ========================================================= */

$sql = "
    SELECT
        a.idaplicacion,
        a.nombre,
        a.slug,
        a.url_base,
        a.icono,
        CASE
            WHEN ea.idempresa_aplicacion IS NOT NULL
                 AND ea.activo = 1
            THEN 1
            ELSE 0
        END AS asignada
    FROM aplicaciones a
    LEFT JOIN empresas_aplicaciones ea
        ON ea.idaplicacion = a.idaplicacion
       AND ea.idempresa = ?
    WHERE a.activo = 1
    ORDER BY a.nombre ASC
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_prepare_aplicaciones: " . $mysqli->error
    ]);

    exit();
}

$stmt->bind_param("i", $idEmpresa);
$stmt->execute();

$res = $stmt->get_result();

$aplicaciones = [];

while ($fila = $res->fetch_assoc()) {

    $aplicaciones[] = [
        "idaplicacion" => intval($fila["idaplicacion"]),
        "nombre"       => $fila["nombre"],
        "slug"          => $fila["slug"],
        "url_base"      => $fila["url_base"],
        "icono"         => $fila["icono"],
        "asignada"      => intval($fila["asignada"])
    ];
}

$stmt->close();

echo json_encode([
    "status" => "ok",
    "data" => [
        "empresa" => [
            "idempresa" => intval($empresa["idempresa"]),
            "nombre"    => $empresa["nombre"],
            "activo"    => intval($empresa["activo"])
        ],
        "aplicaciones" => $aplicaciones
    ]
]);

$mysqli->close();