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

$mysqli = conectarDB('CTACTE_');
/**
 * Helper para obtener rango de fechas exacto de un período según config_periodos_reglas
 */
function obtenerRangoPeriodoReporte($mysqli, $mes, $anio) {
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

    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);
    $periodoDesde  = sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']);
    $periodoHasta  = sprintf('%04d-%02d-%02d 23:59:59', $anio, $mesFin, $diaFin);

    return [$periodoCodigo, $periodoDesde, $periodoHasta];
}

$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'estado_cuenta') {
    $dni  = trim($_GET['dni'] ?? '');
    $mes  = intval($_GET['mes'] ?? date('n'));
    $anio = intval($_GET['anio'] ?? date('Y'));

    if (empty($dni)) {
        echo json_encode(["status" => "empty", "msg" => "DNI no proporcionado"]);
        exit();
    }

    list($periodoCodigo, $periodoDesde, $periodoHasta) = obtenerRangoPeriodoReporte($mysqli, $mes, $anio);

    // Consulta filtrando por el rango de fechas de corte real del período
    $stmt = $mysqli->prepare("
        SELECT venta_id 
        FROM compras_cabecera 
        WHERE CAST(dni_empleado AS CHAR) = CAST(? AS CHAR)
          AND fecha_compra BETWEEN ? AND ?
        LIMIT 1
    ");
    $stmt->bind_param("sss", $dni, $periodoDesde, $periodoHasta);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        echo json_encode(["status" => "ok"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    $stmt->close();
    exit();

} elseif ($tipo === 'resumen_mensual') {
    $mes  = intval($_GET['mes'] ?? date('n'));
    $anio = intval($_GET['anio'] ?? date('Y'));

    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);

    // Consulta filtrando por el código de período operativo
    $stmt = $mysqli->prepare("
        SELECT el.dni
        FROM empleados_limites el 
        WHERE el.periodo_codigo = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $periodoCodigo);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        echo json_encode(["status" => "ok"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    $stmt->close();
    exit();

    } elseif ($tipo === 'consumo_quincenal_articulos') {
    $dni  = trim($_GET['dni'] ?? '');
    $mes  = intval($_GET['mes'] ?? date('n'));
    $anio = intval($_GET['anio'] ?? date('Y'));

    if (empty($dni)) {
        echo json_encode(["status" => "empty", "msg" => "DNI no proporcionado"]);
        exit();
    }

    list($periodoCodigo, $periodoDesde, $periodoHasta) = obtenerRangoPeriodoReporte($mysqli, $mes, $anio);

    $stmt = $mysqli->prepare("
        SELECT cc.venta_id 
        FROM compras_cabecera cc
        WHERE CAST(cc.dni_empleado AS CHAR) = CAST(? AS CHAR)
          AND cc.fecha_compra BETWEEN ? AND ?
          AND COALESCE(cc.anulado, 0) = 0
        LIMIT 1
    ");
    $stmt->bind_param("sss", $dni, $periodoDesde, $periodoHasta);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        echo json_encode(["status" => "ok"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    $stmt->close();
    exit();

    } elseif ($tipo === 'consumo_por_rango_dni') {
    $mes  = intval($_GET['mes'] ?? date('n'));
    $anio = intval($_GET['anio'] ?? date('Y'));

    list($periodoCodigo, $periodoDesde, $periodoHasta) = obtenerRangoPeriodoReporte($mysqli, $mes, $anio);

    $stmt = $mysqli->prepare("
        SELECT cc.venta_id 
        FROM compras_cabecera cc
        INNER JOIN personas p ON CAST(cc.dni_empleado AS CHAR) = CAST(p.dni AS CHAR)
        WHERE cc.fecha_compra BETWEEN ? AND ?
          AND COALESCE(cc.anulado, 0) = 0
        LIMIT 1
    ");
    $stmt->bind_param("ss", $periodoDesde, $periodoHasta);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        echo json_encode(["status" => "ok"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    $stmt->close();
    exit();
    
} else {
    // Para otros reportes genéricos por rango de fechas
    $desde = ($_GET['desde'] ?? '') !== '' ? $_GET['desde'] . " 00:00:00" : '';
    $hasta = ($_GET['hasta'] ?? '') !== '' ? $_GET['hasta'] . " 23:59:59" : '';

    echo json_encode(["status" => "ok"]);
    exit();
}