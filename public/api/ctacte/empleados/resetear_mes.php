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
// api/ctacte/resetear_limites.php
header('Content-Type: application/json');

// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$mysqli = conectarDB('CTACTE_');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$mes  = isset($_POST['mes']) ? (int)$_POST['mes'] : (int)date('n');
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : (int)date('Y');

if ($mes < 1 || $mes > 12 || $anio < 2000) {
    echo json_encode(["status" => "error", "msg" => "Parámetros de período inválidos."]);
    exit;
}

try {
    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);

    // 1. Evitar tocar un período cerrado
    $stmtCerrado = $mysqli->prepare("SELECT 1 FROM empleados_limites WHERE periodo_codigo = ? AND cerrado = 1 LIMIT 1");
    $stmtCerrado->bind_param("s", $periodoCodigo);
    $stmtCerrado->execute();
    $stmtCerrado->store_result();
    if ($stmtCerrado->num_rows > 0) {
        $stmtCerrado->close();
        echo json_encode(["status" => "error", "msg" => "El período $periodoCodigo está cerrado y no se puede recalcular."]);
        exit;
    }
    $stmtCerrado->close();

    // 2. Obtener fechas de inicio y fin desde config_periodos_reglas
    $resRegla = $mysqli->query("SELECT * FROM config_periodos_reglas WHERE mes_periodo = $mes");
    $regla = $resRegla ? $resRegla->fetch_assoc() : null;

    $regla = $regla ?? [
        'dia_inicio' => 1, 'mes_inicio' => $mes, 'resta_anio_inicio' => 0,
        'dia_fin' => 31, 'mes_fin' => $mes
    ];

    $anioInicio = $anio - (int)$regla['resta_anio_inicio'];
    $mesFin     = (int)$regla['mes_fin'];
    $diaFin     = (int)$regla['dia_fin'];

    $ultimoDiaReal = (int)date('t', strtotime(sprintf('%04d-%02d-01', $anio, $mesFin)));
    if ($diaFin > $ultimoDiaReal) {
        $diaFin = $ultimoDiaReal;
    }

    $periodoDesde = sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']);
    $periodoHasta = sprintf('%04d-%02d-%02d 23:59:59', $anio, $mesFin, $diaFin);

    // 3. Recalcular el consumo real de las compras para el período especificado únicamente
    $query = "
        UPDATE empleados_limites el
        SET el.consumido_mes_actual = COALESCE((
            SELECT SUM(c.importe_total)
            FROM compras_cabecera c
            WHERE CAST(c.dni_empleado AS CHAR) = CAST(el.dni AS CHAR)
              AND c.fecha_compra BETWEEN ? AND ?
        ), 0.00)
        WHERE el.periodo_codigo = ? AND el.cerrado = 0
    ";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $periodoDesde, $periodoHasta, $periodoCodigo);
    $stmt->execute();
    $afectados = $stmt->affected_rows;
    $stmt->close();

    if (function_exists('registrarLog')) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        registrarLog($mysqli, 'resetear_limites_periodo', 'empleados_limites', $periodoCodigo, $idUsuarioLog, null, [
            "mes" => $mes, 
            "anio" => $anio, 
            "periodo_codigo" => $periodoCodigo, 
            "registros_actualizados" => $afectados
        ]);
    }

    echo json_encode([
        "status" => "ok", 
        "msg" => "Consumos del período $periodoCodigo recalculados correctamente ($afectados registros actualizados)."
    ]);

} catch (Exception $e) {
    if (function_exists('registrarLog') && isset($mysqli) && $mysqli->connect_errno == 0) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        @registrarLog($mysqli, 'error_resetear_limites', 'sistema', null, $idUsuarioLog, null, ["error" => $e->getMessage()]);
    }
    echo json_encode(["status" => "error", "msg" => "Error al recalcular: " . $e->getMessage()]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}