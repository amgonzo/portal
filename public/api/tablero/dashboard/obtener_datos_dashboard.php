
<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');

try {
    // =========================================================
    // 1. AUTENTICACIÓN Y CONEXIÓN A LA EMPRESA
    // =========================================================

    $userAuth = validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        throw new Exception('No se seleccionó una empresa.');
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // =========================================================
    // 2. VALIDAR FECHAS
    // =========================================================

    $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
    $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

    $validarFecha = static function ($fecha) {
        $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);

        return $objeto && $objeto->format('Y-m-d') === $fecha;
    };

    if (!$validarFecha($fechaDesde) || !$validarFecha($fechaHasta)) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Formato de fecha inválido. Utilice AAAA-MM-DD.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if ($fechaDesde > $fechaHasta) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'La fecha desde no puede ser posterior a la fecha hasta.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // =========================================================
    // 3. OBTENER RESUMEN DEL PERÍODO
    // =========================================================

    $sqlResumen = "
        SELECT
            COALESCE(SUM(recaudacion), 0) AS recaudacion,
            COALESCE(SUM(costo), 0) AS costo,
            COALESCE(SUM(diferencia), 0) AS diferencia,
            COALESCE(SUM(q_tk), 0) AS tickets,
            COALESCE(SUM(cant_prod), 0) AS unidades,

            CASE
                WHEN SUM(costo) <> 0
                THEN (SUM(recaudacion) / SUM(costo) - 1) * 100
                ELSE 0
            END AS marcacion,

            CASE
                WHEN SUM(q_tk) <> 0
                THEN SUM(recaudacion) / SUM(q_tk)
                ELSE 0
            END AS ticket_promedio,

            CASE
                WHEN SUM(cant_prod) <> 0
                THEN SUM(recaudacion) / SUM(cant_prod)
                ELSE 0
            END AS producto_promedio,

            CASE
                WHEN SUM(q_tk) <> 0
                THEN SUM(cant_prod) / SUM(q_tk)
                ELSE 0
            END AS productos_por_ticket,

            CASE
                WHEN SUM(recaudacion) <> 0
                THEN (SUM(diferencia) / SUM(recaudacion)) * 100
                ELSE 0
            END AS porcentaje_s_vta

        FROM dashboard_ventas_diarias
        WHERE fecha >= ?
          AND fecha <= ?
    ";

    $stmtResumen = $mysqli->prepare($sqlResumen);
    $stmtResumen->bind_param('ss', $fechaDesde, $fechaHasta);
    $stmtResumen->execute();

    $resumen = $stmtResumen->get_result()->fetch_assoc();
    $stmtResumen->close();

    // =========================================================
    // 4. OBTENER DETALLE DIARIO
    // =========================================================

    $sqlDias = "
        SELECT
            fecha,
            recaudacion,
            costo,
            diferencia,
            marcacion,
            q_tk,
            tk_prom,
            cant_prod,
            prod_prom,
            prod_x_tk,
            porcentaje_s_vta
        FROM dashboard_ventas_diarias
        WHERE fecha >= ?
          AND fecha <= ?
        ORDER BY fecha ASC
    ";

    $stmtDias = $mysqli->prepare($sqlDias);
    $stmtDias->bind_param('ss', $fechaDesde, $fechaHasta);
    $stmtDias->execute();

    $resultadoDias = $stmtDias->get_result();
    $dias = [];

    while ($fila = $resultadoDias->fetch_assoc()) {
        $dias[] = [
            'fecha' => $fila['fecha'],
            'recaudacion' => (float)$fila['recaudacion'],
            'costo' => (float)$fila['costo'],
            'diferencia' => (float)$fila['diferencia'],
            'marcacion' => (float)$fila['marcacion'],
            'q_tk' => (int)$fila['q_tk'],
            'tk_prom' => (float)$fila['tk_prom'],
            'cant_prod' => (float)$fila['cant_prod'],
            'prod_prom' => (float)$fila['prod_prom'],
            'prod_x_tk' => (float)$fila['prod_x_tk'],
            'porcentaje_s_vta' => (float)$fila['porcentaje_s_vta']
        ];
    }

    $stmtDias->close();

    // =========================================================
    // 5. OBTENER ÚLTIMA SINCRONIZACIÓN
    // =========================================================

    $clave = 'ultima_sincronizacion_dashboard_ventas';

    $stmtUltima = $mysqli->prepare("
        SELECT valor
        FROM configuracion
        WHERE clave = ?
        LIMIT 1
    ");

    $stmtUltima->bind_param('s', $clave);
    $stmtUltima->execute();

    $filaUltima = $stmtUltima->get_result()->fetch_assoc();
    $stmtUltima->close();

    $ultimaSincronizacion = $filaUltima['valor'] ?? null;

    // =========================================================
    // 6. RESPUESTA PARA EL TABLERO
    // =========================================================

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'resumen' => [
                'recaudacion' => (float)($resumen['recaudacion'] ?? 0),
                'costo' => (float)($resumen['costo'] ?? 0),
                'diferencia' => (float)($resumen['diferencia'] ?? 0),
                'tickets' => (int)($resumen['tickets'] ?? 0),
                'q_tk' => (int)($resumen['tickets'] ?? 0),
                'unidades' => (float)($resumen['unidades'] ?? 0),
                'cant_prod' => (float)($resumen['unidades'] ?? 0),
                'marcacion' => (float)($resumen['marcacion'] ?? 0),
                'ticket_promedio' => (float)($resumen['ticket_promedio'] ?? 0),
                'tk_prom' => (float)($resumen['ticket_promedio'] ?? 0),
                'producto_promedio' => (float)($resumen['producto_promedio'] ?? 0),
                'prod_prom' => (float)($resumen['producto_promedio'] ?? 0),
                'productos_por_ticket' => (float)($resumen['productos_por_ticket'] ?? 0),
                'prod_x_tk' => (float)($resumen['productos_por_ticket'] ?? 0),
                'porcentaje_s_vta' => (float)($resumen['porcentaje_s_vta'] ?? 0)
            ],
            'dias' => $dias,
            'cantidad_dias' => count($dias),
            'ultima_sincronizacion' => $ultimaSincronizacion,
            'estado_sincronizacion' => $ultimaSincronizacion
                ? 'Sincronizado'
                : 'Sin sincronizaciones registradas'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

} catch (Throwable $e) {
    error_log(
        'Error obteniendo dashboard de ventas: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Error al obtener datos del dashboard: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}