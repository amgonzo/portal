<?php
// api/ctacte/obtener_datos_dashboard.php
header('Content-Type: application/json');

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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

$mysqli = conectarDB('CTACTE_');

/**
 * Función Helper: Obtiene las fechas exactas (desde/hasta) y el periodo_codigo
 * correspondiente a la fecha de hoy según la tabla 'config_periodos_reglas'.
 */
function obtenerPeriodoActualDashboard($mysqli, $fechaHoyStr) {
    $fecha = new DateTime($fechaHoyStr);
    $dia   = (int)$fecha->format('d');
    $mes   = (int)$fecha->format('m');
    $anio  = (int)$fecha->format('Y');

    // Evaluamos si cae entre el 26 y 31 de Diciembre (Periodo Enero año siguiente)
    if ($mes === 12 && $dia >= 26) {
        $mesPeriodo  = 1;
        $anioPeriodo = $anio + 1;
    } else {
        $mesPeriodo  = $mes;
        $anioPeriodo = $anio;
    }

    $stmt = $mysqli->prepare("SELECT * FROM config_periodos_reglas WHERE mes_periodo = ?");
    $stmt->bind_param("i", $mesPeriodo);
    $stmt->execute();
    $res = $stmt->get_result();
    $regla = $res->fetch_assoc();
    $stmt->close();

    $regla = $regla ?? [
        'dia_inicio' => 1, 'mes_inicio' => $mesPeriodo, 'resta_anio_inicio' => 0,
        'dia_fin' => 31, 'mes_fin' => $mesPeriodo
    ];

    $anioInicio = $anioPeriodo - (int)$regla['resta_anio_inicio'];
    $diaFin     = (int)$regla['dia_fin'];

    $periodoCodigo = sprintf('%04d-%02d', $anioPeriodo, $mesPeriodo);
    $periodoDesde  = sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']);
    $periodoHasta  = sprintf('%04d-%02d-%02d 23:59:59', $anioPeriodo, $regla['mes_fin'], $diaFin);

    $mesesNombres = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    $nombrePeriodo = ($mesesNombres[$mesPeriodo] ?? "Mes $mesPeriodo") . " " . $anioPeriodo;

    return [
        'periodo_codigo' => $periodoCodigo,
        'periodo_desde'  => $periodoDesde,
        'periodo_hasta'  => $periodoHasta,
        'nombre_periodo' => $nombrePeriodo,
        'mes'            => $mesPeriodo,
        'anio'           => $anioPeriodo
    ];
}

try {
    // 1. Obtenemos el Período Operativo de HOY
    $hoy = date('Y-m-d');
    $pAct = obtenerPeriodoActualDashboard($mysqli, $hoy);

    // 2. Obtener fecha de última sincronización
    $stmt_sync = $mysqli->query("SELECT valor FROM configuracion WHERE clave = 'ultima_sincronizacion_cajas'");
    $row_sync = $stmt_sync ? $stmt_sync->fetch_assoc() : null;
    $ultima_sincro = !empty($row_sync['valor']) 
        ? date('d/m/Y H:i', strtotime($row_sync['valor'])) . ' hs' 
        : 'Nunca sincronizado';

    // 3. Consolidado Mensual: Suma compras entre 'periodo_desde' y 'periodo_hasta'
    $stmt_cons = $mysqli->prepare("
        SELECT SUM(importe_total) AS total 
        FROM compras_cabecera 
        WHERE fecha_compra BETWEEN ? AND ?
    ");
    $stmt_cons->bind_param("ss", $pAct['periodo_desde'], $pAct['periodo_hasta']);
    $stmt_cons->execute();
    $res_cons = $stmt_cons->get_result()->fetch_assoc();
    $consumo_total_mes = (float)($res_cons['total'] ?? 0.00);
    $stmt_cons->close();

    // 4. Contadores de empleados (Total vs con uso en el período actual)
    $res_total = $mysqli->query("SELECT COUNT(*) AS total FROM fichajes.empleados WHERE baja IS NULL OR baja = 0");
    $total_empleados = (int)($res_total->fetch_assoc()['total'] ?? 0);

    $stmt_emp_act = $mysqli->prepare("
        SELECT COUNT(DISTINCT cc.dni_empleado) AS activos 
        FROM compras_cabecera cc
        INNER JOIN fichajes.empleados e ON cc.dni_empleado = e.documento
        WHERE cc.fecha_compra BETWEEN ? AND ? 
          AND (e.baja IS NULL OR e.baja = 0)
    ");
    $stmt_emp_act->bind_param("ss", $pAct['periodo_desde'], $pAct['periodo_hasta']);
    $stmt_emp_act->execute();
    $empleados_activos = (int)($stmt_emp_act->get_result()->fetch_assoc()['activos'] ?? 0);
    $stmt_emp_act->close();

    // 5. Empleados excediendo límites (> 85% del cupo en el 'periodo_codigo' actual)
    $stmt_alertas_count = $mysqli->prepare("
        SELECT COUNT(*) AS alertas 
        FROM empleados_limites el
        INNER JOIN fichajes.empleados e ON el.dni = e.documento
        WHERE el.activo = 1 
          AND el.periodo_codigo = ? 
          AND (e.baja IS NULL OR e.baja = 0)
          AND el.limite_mensual > 0
          AND (el.consumido_mes_actual / el.limite_mensual) >= 0.85
    ");
    $stmt_alertas_count->bind_param("s", $pAct['periodo_codigo']);
    $stmt_alertas_count->execute();
    $alertas_limite_count = (int)($stmt_alertas_count->get_result()->fetch_assoc()['alertas'] ?? 0);
    $stmt_alertas_count->close();

    // 6. Últimos 10 Consumos Recibidos (Tabla principal)
    $ultimos_consumos = [];
    $query_consumos = "
        SELECT 
            cc.punto_venta_id,
            cc.venta_id,
            cc.nro_comprobante,
            CONCAT(e.apellido, ' ', e.nombre) AS empleado,
            cc.dni_empleado AS dni,
            cc.fecha_compra AS fecha,
            cc.importe_total AS monto
        FROM compras_cabecera cc
        INNER JOIN fichajes.empleados e ON cc.dni_empleado = e.documento
        ORDER BY cc.fecha_compra DESC
        LIMIT 10
    ";
    $res_consumos = $mysqli->query($query_consumos);
    if ($res_consumos) {
        while ($c = $res_consumos->fetch_assoc()) {
            $ultimos_consumos[] = [
                'id_compra' => $c['punto_venta_id'] . '-' . $c['nro_comprobante'],
                'id_url' => $c['punto_venta_id'] . '_' . $c['venta_id'],
                'nro_comprobante' => $c['nro_comprobante'],
                'empleado' => !empty(trim($c['empleado'])) ? trim($c['empleado']) : 'Empleado Desconocido',
                'dni' => $c['dni'],
                'fecha' => date('d/m/Y H:i', strtotime($c['fecha'])),
                'monto' => (float)$c['monto']
            ];
        }
    }

    // 7. Lista de Empleados en Riesgo Límite + HISTORIAL POR 'periodo_codigo'
    $alertas_lista = [];
    $stmt_lista_alertas = $mysqli->prepare("
        SELECT 
            el.dni,
            e.idempleado as legajo,
            CONCAT(e.apellido, ' ', e.nombre) AS nombre,
            el.consumido_mes_actual AS consumido,
            el.limite_mensual AS limite,
            ROUND((el.consumido_mes_actual / el.limite_mensual) * 100, 1) AS porc
        FROM empleados_limites el
        INNER JOIN fichajes.empleados e ON el.dni = e.documento
        WHERE el.activo = 1 
          AND el.periodo_codigo = ? 
          AND (e.baja IS NULL OR e.baja = 0)
          AND el.limite_mensual > 0
          AND (el.consumido_mes_actual / el.limite_mensual) >= 0.85
        ORDER BY porc DESC
        LIMIT 5
    ");
    $stmt_lista_alertas->bind_param("s", $pAct['periodo_codigo']);
    $stmt_lista_alertas->execute();
    $res_lista_alertas = $stmt_lista_alertas->get_result();

    $meses_nombres = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    if ($res_lista_alertas) {
        // Subquery para buscar el historial de períodos anteriores
        $stmt_hist = $mysqli->prepare("
            SELECT mes, anio, periodo_codigo, consumido_mes_actual AS consumido, limite_mensual AS limite,
                   ROUND((consumido_mes_actual / limite_mensual) * 100, 1) AS porc
            FROM empleados_limites
            WHERE dni = ? 
              AND periodo_codigo < ?
            ORDER BY periodo_codigo DESC
            LIMIT 3
        ");

        while ($a = $res_lista_alertas->fetch_assoc()) {
            $dni_asociado = $a['dni'];
            $historial_meses = [];

            $stmt_hist->bind_param("ss", $dni_asociado, $pAct['periodo_codigo']);
            $stmt_hist->execute();
            $res_hist = $stmt_hist->get_result();

            while ($h = $res_hist->fetch_assoc()) {
                $num_mes = (int)$h['mes'];
                $nombre_m = $meses_nombres[$num_mes] ?? "Mes $num_mes";
                
                $historial_meses[] = [
                    'mes' => $nombre_m . ' ' . $h['anio'],
                    'consumido' => (float)$h['consumido'],
                    'limite' => (float)$h['limite'],
                    'porc' => (float)$h['porc']
                ];
            }

            $alertas_lista[] = [
                'legajo' => $a['legajo'] ?? 'S/L',
                'nombre' => trim($a['nombre']),
                'consumido' => (float)$a['consumido'],
                'limite' => (float)$a['limite'],
                'porc' => (float)$a['porc'],
                'historial_meses' => $historial_meses
            ];
        }
        $stmt_hist->close();
    }
    $stmt_lista_alertas->close();

    echo json_encode([
        "status" => "ok",
        "data" => [
            "periodo_nombre" => $pAct['nombre_periodo'],
            "ultima_sincronizacion" => $ultima_sincro,
            "consumo_total_mes" => $consumo_total_mes,
            "total_empleados" => $total_empleados,
            "empleados_activos" => $empleados_activos,
            "alertas_limite_count" => $alertas_limite_count,
            "ultimos_consumos" => $ultimos_consumos,
            "alertas_lista" => $alertas_lista
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg" => "Error al estructurar métricas: " . $e->getMessage()
    ]);
}

$mysqli->close();