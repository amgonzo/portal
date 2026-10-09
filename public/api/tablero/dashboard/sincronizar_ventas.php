
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

$idSincronizacion = 0;
$transaccionIniciada = false;

/**
 * Inserta un lote de detalles en MariaDB.
 */
function dashboardVentasInsertarLote(mysqli $mysqli, array $filas): int
{
    if (empty($filas)) {
        return 0;
    }

    $columnas = [
        'venta_id',
        'sucursal_id',
        'item',
        'punto_venta_id',
        'descripcion',
        'fecha',
        'articulo_id',
        'cantidad',
        'importe',
        'importe_costo',
        'importe_descuento',
        'importe_bonificacion'
    ];

    $cantidadColumnas = count($columnas);
    $marcadoresFila = '(' . implode(',', array_fill(0, $cantidadColumnas, '?')) . ')';

    $valoresSQL = implode(
        ',',
        array_fill(0, count($filas), $marcadoresFila)
    );

    $sql = "
        INSERT INTO dashboard_ventas_detalle (
            " . implode(',', $columnas) . "
        ) VALUES $valoresSQL
        ON DUPLICATE KEY UPDATE
            descripcion = VALUES(descripcion),
            fecha = VALUES(fecha),
            articulo_id = VALUES(articulo_id),
            cantidad = VALUES(cantidad),
            importe = VALUES(importe),
            importe_costo = VALUES(importe_costo),
            importe_descuento = VALUES(importe_descuento),
            importe_bonificacion = VALUES(importe_bonificacion),
            fecha_sincronizacion = CURRENT_TIMESTAMP
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            'Error preparando lote de ventas: ' . $mysqli->error
        );
    }

    $tiposFila = 'iiiississsss';
    $tipos = str_repeat($tiposFila, count($filas));
    $parametros = [];

    foreach ($filas as $fila) {
        foreach ($fila as $valor) {
            $parametros[] = $valor;
        }
    }

    $referencias = [];
    $referencias[] = &$tipos;

    foreach ($parametros as $indice => &$valor) {
        $referencias[] = &$valor;
    }
    unset($valor);

    try {
        if (!call_user_func_array(
            [$stmt, 'bind_param'],
            $referencias
        )) {
            throw new Exception(
                'No se pudieron vincular los parámetros del lote.'
            );
        }

        $stmt->execute();
    } finally {
        $stmt->close();
    }

    return count($filas);
}

/**
 * Actualiza el registro de historial de la sincronización.
 */
function dashboardActualizarHistorial(
    mysqli $mysqli,
    int $idSincronizacion,
    string $estado,
    int $registrosDetalle,
    int $registrosResumen,
    string $mensaje
): void {
    $stmt = $mysqli->prepare("
        UPDATE dashboard_sincronizaciones
        SET
            fecha_fin = NOW(),
            estado = ?,
            registros_detalle = ?,
            registros_resumen = ?,
            mensaje = ?
        WHERE id = ?
    ");

    if (!$stmt) {
        throw new Exception(
            'Error preparando actualización del historial: ' . $mysqli->error
        );
    }

    $stmt->bind_param(
        'siisi',
        $estado,
        $registrosDetalle,
        $registrosResumen,
        $mensaje,
        $idSincronizacion
    );

    $stmt->execute();

    if ($stmt->affected_rows < 0) {
        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error actualizando historial: ' . $error
        );
    }

    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'msg' => 'metodo_no_permitido'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

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
    // 2. OBTENER LA ÚLTIMA SINCRONIZACIÓN
    // =========================================================

    $clave = 'ultima_sincronizacion_dashboard_ventas';

    $stmtUltima = $mysqli->prepare("
        SELECT valor
        FROM configuracion
        WHERE clave = ?
        LIMIT 1
    ");

    if (!$stmtUltima) {
        throw new Exception(
            'Error consultando la última sincronización: ' . $mysqli->error
        );
    }

    $stmtUltima->bind_param('s', $clave);
    $stmtUltima->execute();

    $filaUltima = $stmtUltima->get_result()->fetch_assoc();
    $stmtUltima->close();

    $ultimaSincronizacion = $filaUltima['valor'] ?? null;
    $primeraCarga = empty($ultimaSincronizacion);

    if ($primeraCarga) {
        $fechaDesde = date(
            'Y-m-d 00:00:00',
            strtotime('-2 days')
        );
    } else {
        $fechaDesde = date(
            'Y-m-d H:i:s',
            strtotime($ultimaSincronizacion)
        );
    }

    $fechaHasta = date('Y-m-d H:i:s');

    // =========================================================
    // 3. REGISTRAR EL INICIO EN EL HISTORIAL
    // =========================================================

    $fechaDesdeDia = substr($fechaDesde, 0, 10);
    $fechaHastaDia = substr($fechaHasta, 0, 10);

    $stmtHistorial = $mysqli->prepare("
        INSERT INTO dashboard_sincronizaciones (
            fecha_desde,
            fecha_hasta,
            fecha_inicio,
            estado,
            registros_detalle,
            registros_resumen,
            mensaje
        ) VALUES (?, ?, NOW(), 'EN_PROCESO', 0, 0, ?)
    ");

    if (!$stmtHistorial) {
        throw new Exception(
            'Error registrando el inicio de sincronización: ' . $mysqli->error
        );
    }

    $mensajeInicio = 'Sincronización en ejecución';

    $stmtHistorial->bind_param(
        'sss',
        $fechaDesdeDia,
        $fechaHastaDia,
        $mensajeInicio
    );

    $stmtHistorial->execute();
    $idSincronizacion = (int)$mysqli->insert_id;
    $stmtHistorial->close();

    if ($idSincronizacion <= 0) {
        throw new Exception(
            'No se pudo obtener el ID del historial de sincronización.'
        );
    }

    $fechaDesdeSQL = str_replace(' ', 'T', $fechaDesde);
    $fechaHastaSQL = str_replace(' ', 'T', $fechaHasta);

    // Inicializar contadores para poder registrar errores.
    $registrosDetalle = 0;
    $registrosResumen = 0;

    // =========================================================
    // 4. CONEXIÓN A SQL SERVER
    // =========================================================

    $dbname_ms3 = $_ENV['DB_NAME_MS3'];
    $user_ms3   = $_ENV['DB_USER_MS3'];
    $pass_ms3   = $_ENV['DB_PASS_MS3'];

    $dsn_ms3 = "sqlsrv:Server=192.168.0.238,1433;Database=$dbname_ms3;Encrypt=no;TrustServerCertificate=yes";

    $options_ms3 = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo_mssql = new PDO(
        $dsn_ms3,
        $user_ms3,
        $pass_ms3,
        $options_ms3
    );

    // =========================================================
    // 5. CONSULTAR VENTAS
    // =========================================================

    $operadorFecha = $primeraCarga ? '>=' : '>';

    $queryMssql = "
        SELECT
            vd.VENTA_ID,
            vd.SUCURSAL_ID,
            vd.ITEM,
            vd.PUNTO_VENTA_ID,
            vd.DESCRIPCION,
            CONVERT(VARCHAR(19), v.FECHA, 126) AS FECHA,
            vd.ARTICULO_ID,
            vd.CANTIDAD,
            vd.IMPORTE,
            vd.IMPORTE_COSTO,
            ISNULL(vd.IMPORTE_DESCUENTO, 0) AS IMPORTE_DESCUENTO,
            ISNULL(vd.IMPORTE_BONIFICACION, 0) AS IMPORTE_BONIFICACION
        FROM VENTA v
        INNER JOIN VENTA_DETALLE vd
            ON v.VENTA_ID = vd.VENTA_ID
            AND v.SUCURSAL_ID = vd.SUCURSAL_ID
            AND v.PUNTO_VENTA_ID = vd.PUNTO_VENTA_ID
        WHERE v.FECHA $operadorFecha
              CONVERT(datetime, :fecha_desde, 126)
          AND v.FECHA <= CONVERT(datetime, :fecha_hasta, 126)
        ORDER BY v.FECHA, v.VENTA_ID, vd.ITEM
    ";

    $stmtMssql = $pdo_mssql->prepare($queryMssql);

    $stmtMssql->execute([
        ':fecha_desde' => $fechaDesdeSQL,
        ':fecha_hasta' => $fechaHastaSQL
    ]);

    // =========================================================
    // 6. INICIAR TRANSACCIÓN
    // =========================================================

    $mysqli->begin_transaction();
    $transaccionIniciada = true;

    // =========================================================
    // 7. INSERTAR DETALLES EN LOTES DE 250
    // =========================================================

    $fechasAfectadas = [];
    $filasLote = [];
    $tamanoLote = 250;

    while ($row = $stmtMssql->fetch(PDO::FETCH_ASSOC)) {
        $fecha = str_replace('T', ' ', $row['FECHA']);

        $articuloId = $row['ARTICULO_ID'] !== null
            ? (int)$row['ARTICULO_ID']
            : null;

        $filasLote[] = [
            (int)$row['VENTA_ID'],
            (int)$row['SUCURSAL_ID'],
            (int)$row['ITEM'],
            (int)$row['PUNTO_VENTA_ID'],
            (string)($row['DESCRIPCION'] ?? ''),
            $fecha,
            $articuloId,
            (string)$row['CANTIDAD'],
            (string)$row['IMPORTE'],
            (string)$row['IMPORTE_COSTO'],
            (string)$row['IMPORTE_DESCUENTO'],
            (string)$row['IMPORTE_BONIFICACION']
        ];

        $diaAfectado = substr($fecha, 0, 10);
        $fechasAfectadas[$diaAfectado] = true;

        if (count($filasLote) >= $tamanoLote) {
            $registrosDetalle += dashboardVentasInsertarLote(
                $mysqli,
                $filasLote
            );

            $filasLote = [];
        }
    }

    if (!empty($filasLote)) {
        $registrosDetalle += dashboardVentasInsertarLote(
            $mysqli,
            $filasLote
        );
    }

    $stmtMssql = null;

    // =========================================================
    // 8. RECALCULAR LOS RESÚMENES DE LOS DÍAS AFECTADOS
    // =========================================================

    $sqlResumen = "
        INSERT INTO dashboard_ventas_diarias (
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
            porcentaje_s_vta,
            fecha_sincronizacion
        )
        SELECT
            DATE(fecha),
            SUM(importe),
            SUM(importe_costo),
            SUM(importe) - SUM(importe_costo),

            CASE
                WHEN SUM(importe_costo) <> 0
                THEN (
                    SUM(importe) / SUM(importe_costo) - 1
                ) * 100
                ELSE 0
            END,

            COUNT(DISTINCT CONCAT(
                venta_id, ':', sucursal_id, ':', punto_venta_id
            )),

            CASE
                WHEN COUNT(DISTINCT CONCAT(
                    venta_id, ':', sucursal_id, ':', punto_venta_id
                )) > 0
                THEN SUM(importe) / COUNT(DISTINCT CONCAT(
                    venta_id, ':', sucursal_id, ':', punto_venta_id
                ))
                ELSE 0
            END,

            SUM(cantidad),

            CASE
                WHEN SUM(cantidad) <> 0
                THEN SUM(importe) / SUM(cantidad)
                ELSE 0
            END,

            CASE
                WHEN COUNT(DISTINCT CONCAT(
                    venta_id, ':', sucursal_id, ':', punto_venta_id
                )) > 0
                THEN SUM(cantidad) / COUNT(DISTINCT CONCAT(
                    venta_id, ':', sucursal_id, ':', punto_venta_id
                ))
                ELSE 0
            END,

            CASE
                WHEN SUM(importe) <> 0
                THEN (
                    (SUM(importe) - SUM(importe_costo))
                    / SUM(importe)
                ) * 100
                ELSE 0
            END,

            CURRENT_TIMESTAMP

        FROM dashboard_ventas_detalle
        WHERE fecha >= ?
          AND fecha < ?
        GROUP BY DATE(fecha)
        ON DUPLICATE KEY UPDATE
            recaudacion = VALUES(recaudacion),
            costo = VALUES(costo),
            diferencia = VALUES(diferencia),
            marcacion = VALUES(marcacion),
            q_tk = VALUES(q_tk),
            tk_prom = VALUES(tk_prom),
            cant_prod = VALUES(cant_prod),
            prod_prom = VALUES(prod_prom),
            prod_x_tk = VALUES(prod_x_tk),
            porcentaje_s_vta = VALUES(porcentaje_s_vta),
            fecha_sincronizacion = CURRENT_TIMESTAMP
    ";

    $stmtResumen = $mysqli->prepare($sqlResumen);

    if (!$stmtResumen) {
        throw new Exception(
            'Error preparando el resumen diario: ' . $mysqli->error
        );
    }

    foreach (array_keys($fechasAfectadas) as $dia) {
        $diaSiguiente = date(
            'Y-m-d',
            strtotime($dia . ' +1 day')
        );

        $stmtResumen->bind_param('ss', $dia, $diaSiguiente);
        $stmtResumen->execute();

        $registrosResumen++;
    }

    $stmtResumen->close();

    // =========================================================
    // 9. ACTUALIZAR LA ÚLTIMA SINCRONIZACIÓN
    // =========================================================

    $stmtConfig = $mysqli->prepare("
        INSERT INTO configuracion (clave, valor)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ");

    if (!$stmtConfig) {
        throw new Exception(
            'Error preparando la actualización de configuración: ' .
            $mysqli->error
        );
    }

    $stmtConfig->bind_param('ss', $clave, $fechaHasta);
    $stmtConfig->execute();
    $stmtConfig->close();

    // =========================================================
    // 10. CONFIRMAR LOS DATOS DE VENTAS
    // =========================================================

    $mysqli->commit();
    $transaccionIniciada = false;

    // =========================================================
    // 11. CERRAR EL HISTORIAL COMO COMPLETADO
    // =========================================================

    dashboardActualizarHistorial(
        $mysqli,
        $idSincronizacion,
        'OK',
        $registrosDetalle,
        $registrosResumen,
        'Sincronización completada correctamente'
    );

    // =========================================================
    // 12. RESPUESTA
    // =========================================================

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Sincronización de ventas completada.',
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
        'registros_detalle' => $registrosDetalle,
        'registros_resumen' => $registrosResumen,
        'ultima_sincronizacion' => $fechaHasta,
        'id_sincronizacion' => $idSincronizacion
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Revertir únicamente si la transacción de ventas sigue abierta.
    if ($transaccionIniciada && isset($mysqli)) {
        try {
            $mysqli->rollback();
        } catch (Throwable $errorRollback) {
            error_log(
                'Error haciendo rollback: ' . $errorRollback->getMessage()
            );
        }

        $transaccionIniciada = false;
    }

    // Registrar el error en el historial, si ya se había creado.
    if ($idSincronizacion > 0 && isset($mysqli)) {
        try {
            dashboardActualizarHistorial(
                $mysqli,
                $idSincronizacion,
                'ERROR',
                (int)($registrosDetalle ?? 0),
                (int)($registrosResumen ?? 0),
                substr($e->getMessage(), 0, 60000)
            );
        } catch (Throwable $errorHistorial) {
            error_log(
                'Error actualizando historial de sincronización: ' .
                $errorHistorial->getMessage()
            );
        }
    }

    error_log(
        'Error sincronizando dashboard de ventas: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Error de sincronización: ' . $e->getMessage(),
        'id_sincronizacion' => $idSincronizacion ?: null
    ], JSON_UNESCAPED_UNICODE);
}