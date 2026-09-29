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
   VERIFICAR TIPO DE SISTEMA
   ROOT ES GLOBAL
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
   LISTAR EMPRESAS
   ROOT PUEDE VER TODAS LAS EMPRESAS
   ACTIVAS E INACTIVAS
   ========================================================= */

$sql = "
    SELECT
        e.idempresa,
        e.nombre,
        e.razon_social,
        e.cuit,
        e.slug,
        e.db_nombre,
        e.activo,

        GROUP_CONCAT(
            CASE
                WHEN ea.activo = 1
                THEN a.nombre
                ELSE NULL
            END
            ORDER BY a.nombre
            SEPARATOR '|||'
        ) AS aplicaciones

    FROM empresas e

    LEFT JOIN empresas_aplicaciones ea
        ON ea.idempresa = e.idempresa

    LEFT JOIN aplicaciones a
        ON a.idaplicacion = ea.idaplicacion
       AND a.activo = 1

    GROUP BY
        e.idempresa,
        e.nombre,
        e.razon_social,
        e.cuit,
        e.slug,
        e.db_nombre,
        e.activo

    ORDER BY e.nombre ASC
";

$res = $mysqli->query($sql);

if (!$res) {
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "error_db: " . $mysqli->error
    ]);

    $mysqli->close();
    exit();
}

$empresas = [];

while ($fila = $res->fetch_assoc()) {

    $empresas[] = [
        'idempresa' => intval($fila['idempresa']),
        'nombre' => $fila['nombre'],
        'razon_social' => $fila['razon_social'],
        'cuit' => $fila['cuit'],
        'slug' => $fila['slug'],
        'db_nombre' => $fila['db_nombre'],
        'activo' => intval($fila['activo']),

        'aplicaciones' => !empty($fila['aplicaciones'])
            ? explode('|||', $fila['aplicaciones'])
            : []
    ];
}

echo json_encode([
    "status" => "ok",
    "data" => $empresas
]);

$mysqli->close();