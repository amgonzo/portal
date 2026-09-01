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
require_once $rutas['auditoria'];
// api/ctacte/obtener_compras_filtradas.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Validar permiso por endpoint y método automáticamente
validarPermisoEndpoint($mysqli, $userAuth);

// 3. Luego conectar a la base de datos de ctacte
$mysqli = conectarDB('CTACTE_');


function limpiarNumeroParaMySQL($valor) {
    if ($valor === null || $valor === '') return 0.00;
    if (is_numeric($valor) && !is_string($valor)) return (float)$valor;

    $valor = trim($valor);
    if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
        if (strrpos($valor, ',') > strrpos($valor, '.')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            $valor = str_replace(',', '', $valor);
        }
    } else if (strpos($valor, ',') !== false) {
        $valor = str_replace(',', '.', $valor);
    }
    return (float)$valor;
}

function obtenerPeriodoOperativo($fechaCompraStr, $mapaReglas) {
    $fecha = new DateTime($fechaCompraStr);
    $dia   = (int)$fecha->format('d');
    $mes   = (int)$fecha->format('m');
    $anio  = (int)$fecha->format('Y');

    if ($mes === 12 && $dia >= 26) {
        $mesPeriodo  = 1;
        $anioPeriodo = $anio + 1;
    } else {
        $mesPeriodo  = $mes;
        $anioPeriodo = $anio;
    }

    $regla = $mapaReglas[$mesPeriodo] ?? [
        'dia_inicio' => 1,
        'mes_inicio' => $mesPeriodo,
        'resta_anio_inicio' => 0,
        'dia_fin' => 31,
        'mes_fin' => $mesPeriodo
    ];

    $anioInicio = $anioPeriodo - (int)$regla['resta_anio_inicio'];
    $mesFin     = (int)$regla['mes_fin'];
    $diaFin     = (int)$regla['dia_fin'];

    $ultimoDiaReal = (int)date('t', strtotime(sprintf('%04d-%02d-01', $anioPeriodo, $mesFin)));
    if ($diaFin > $ultimoDiaReal) $diaFin = $ultimoDiaReal;

    return [
        'periodo_codigo' => sprintf('%04d-%02d', $anioPeriodo, $mesPeriodo), 
        'periodo_desde'  => sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']),   
        'periodo_hasta'  => sprintf('%04d-%02d-%02d 23:59:59', $anioPeriodo, $mesFin, $diaFin),   
        'mes'            => $mesPeriodo,    
        'anio'           => $anioPeriodo    
    ];
}

try {
    $mapaReglas = [];
    $resReglas = $mysqli->query("SELECT * FROM config_periodos_reglas");
    if ($resReglas) {
        while ($r = $resReglas->fetch_assoc()) {
            $mapaReglas[(int)$r['mes_periodo']] = $r;
        }
    }

    // =========================================================================
    // PARTE 1: LECTURA Y PROCESAMIENTO PASO A PASO DE PERSONAS DESDE FICHAJES
    // =========================================================================
    
    $res_fichajes = $mysqli->query("SELECT documento, apellido, nombre, baja FROM fichajes.empleados");
    $empleados_fichajes = [];

    if ($res_fichajes) {
        while ($emp = $res_fichajes->fetch_assoc()) {
            $dni = trim($emp['documento']);
            if (empty($dni)) continue;

            if (!isset($empleados_fichajes[$dni])) {
                $empleados_fichajes[$dni] = [];
            }
            $empleados_fichajes[$dni][] = $emp;
        }
    }

    $stmt_check_persona = $mysqli->prepare("SELECT 1 FROM personas WHERE dni = ?");
    $stmt_insert_persona = $mysqli->prepare("INSERT INTO personas (dni, apellido, nombre, idcategoria, origen, activo) VALUES (?, ?, ?, 1, 'fichajes', ?)");
    $stmt_update_persona = $mysqli->prepare("UPDATE personas SET apellido = ?, nombre = ?, activo = ? WHERE dni = ? AND origen = 'fichajes'");

    foreach ($empleados_fichajes as $dni => $registros) {
        $seleccionado = null;
        foreach ($registros as $reg) {
            $es_activo = ($reg['baja'] === null || (int)$reg['baja'] === 0);
            if ($es_activo) {
                $seleccionado = $reg;
                break;
            }
        }

        if ($seleccionado === null) {
            $seleccionado = $registros[0];
        }

        $apellido = $seleccionado['apellido'] ?? '';
        $nombre = $seleccionado['nombre'] ?? '';
        $estado_activo = ($seleccionado['baja'] === null || (int)$seleccionado['baja'] === 0) ? 1 : 0;

        $stmt_check_persona->bind_param("s", $dni);
        $stmt_check_persona->execute();
        $res_exists = $stmt_check_persona->get_result();

        if ($res_exists->num_rows > 0) {
            $stmt_update_persona->bind_param("ssis", $apellido, $nombre, $estado_activo, $dni);
            $stmt_update_persona->execute();
        } else {
            if ($estado_activo === 1) {
                $stmt_insert_persona->bind_param("sssi", $dni, $apellido, $nombre, $estado_activo);
                $stmt_insert_persona->execute();
            }
        }
    }

    $stmt_check_persona->close();
    $stmt_insert_persona->close();
    $stmt_update_persona->close();

    // =========================================================================
    // PARTE 2: MAPA DE PERSONAS VÁLIDAS PARA VALIDAR COMPRAS
    // =========================================================================
    $mapa_personas_validas = [];
    $res_per = $mysqli->query("SELECT dni FROM personas");
    if ($res_per) {
        while ($p = $res_per->fetch_assoc()) {
            $mapa_personas_validas[trim($p['dni'])] = true;
        }
    }
    $mapa_personas_validas['0'] = true;

    // =========================================================================
    // PARTE 3: CONEXIÓN Y CONSULTA A MSSQL (Incluyendo NRO_COMPROBANTE)
    // =========================================================================
    $dbname_ms3 = $_ENV['DB_NAME_MS3'];
    $user_ms3   = $_ENV['DB_USER_MS3'];
    $pass_ms3   = $_ENV['DB_PASS_MS3'];

    $dsn_ms3 = "sqlsrv:Server=192.168.0.238,1433;Database=$dbname_ms3;Encrypt=no;TrustServerCertificate=yes";
    $options_ms3 = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    $pdo_mssql = new PDO($dsn_ms3, $user_ms3, $pass_ms3, $options_ms3);

    $stmt_last = $mysqli->prepare("SELECT valor FROM configuracion WHERE clave = 'ultima_sincronizacion_cajas'");
    $stmt_last->execute();
    $res_last = $stmt_last->get_result();
    $row_last = $res_last->fetch_assoc();
    $stmt_last->close();

    $fecha_desde = !empty($row_last['valor']) ? date('Ymd H:i:s', strtotime($row_last['valor'])) : date('Ym01 00:00:00');
    $ahora_sincronizacion = date('Y-m-d H:i:s');

    $query_mssql = "
        SELECT
            v.SUCURSAL_ID,
            v.PUNTO_VENTA_ID,
            v.VENTA_ID,
            v.TIPO_COMPROBANTE_AFIP_ID,
            v.CONTACTO_DOCUMENTO,
            v.FECHA,
            v.NRO_COMPROBANTE,
            vd.ARTICULO_ID,
            a.DESCRIPCION,
            vd.CANTIDAD,
            vd.IMPORTE AS DETALLE_IMPORTE,
            SUM(vd.IMPORTE) OVER (
                PARTITION BY v.SUCURSAL_ID, v.PUNTO_VENTA_ID, v.VENTA_ID
            ) AS TOTAL_TICKET
        FROM VENTA v
        INNER JOIN VENTA_DETALLE vd 
            ON v.VENTA_ID = vd.VENTA_ID
           AND v.PUNTO_VENTA_ID = vd.PUNTO_VENTA_ID
           AND v.SUCURSAL_ID = vd.SUCURSAL_ID
        INNER JOIN ARTICULO a
            ON a.ARTICULO_ID = vd.ARTICULO_ID   
        WHERE v.FECHA > :fecha_desde_1
          AND v.CONDICION_VENTA = 'CTACTE'

        UNION ALL

        SELECT
            v.SUCURSAL_ID,
            v.PUNTO_VENTA_ID,
            v.VENTA_ID,
            v.TIPO_COMPROBANTE_AFIP_ID,
            v.CONTACTO_DOCUMENTO,
            v.FECHA,
            v.NRO_COMPROBANTE,
            vd.ARTICULO_ID,
            a.DESCRIPCION,
            vd.CANTIDAD,
            vd.IMPORTE AS DETALLE_IMPORTE,
            SUM(vd.IMPORTE) OVER (
                PARTITION BY v.SUCURSAL_ID, v.PUNTO_VENTA_ID, v.VENTA_ID
            ) AS TOTAL_TICKET
        FROM VENTA v
        INNER JOIN VENTA_DETALLE vd 
            ON v.VENTA_ID = vd.VENTA_ID
           AND v.PUNTO_VENTA_ID = vd.PUNTO_VENTA_ID
           AND v.SUCURSAL_ID = vd.SUCURSAL_ID
        INNER JOIN ARTICULO a
            ON a.ARTICULO_ID = vd.ARTICULO_ID 
        INNER JOIN PAGO p 
            ON p.VENTA_ID = v.VENTA_ID 
           AND p.PUNTO_VENTA_ID = v.PUNTO_VENTA_ID
           AND p.SUCURSAL_ID = v.SUCURSAL_ID
        INNER JOIN TARJETA_ENTIDAD te 
            ON te.TARJETA_ENTIDAD_ID = p.TARJETA_ENTIDAD_ID  
        WHERE v.FECHA > :fecha_desde_2
          AND te.DESCRIPCION = 'ASOCIADOS'
          AND v.CONDICION_VENTA = 'DEBITO'

        ORDER BY FECHA ASC";

    $stmt_mssql = $pdo_mssql->prepare($query_mssql);
    $stmt_mssql->execute([
        ':fecha_desde_1' => $fecha_desde,
        ':fecha_desde_2' => $fecha_desde
    ]);

    $tickets_agrupados = [];
    $ventas_ids = []; 

    while ($row = $stmt_mssql->fetch()) {
        $suc_id = $row['SUCURSAL_ID'];
        $pv_id  = $row['PUNTO_VENTA_ID'];
        $v_id   = $row['VENTA_ID'];
        
        $clave_ticket = $suc_id . '_' . $pv_id . '_' . $v_id;

        if (!isset($tickets_agrupados[$clave_ticket])) {
            $dni_raw = trim($row['CONTACTO_DOCUMENTO'] ?? '');
            $dni_limpio = ($dni_raw === '' || $dni_raw === null || intval($dni_raw) === 0) ? '0' : $dni_raw;

            if (!isset($mapa_personas_validas[$dni_limpio])) {
                continue; 
            }

            $tickets_agrupados[$clave_ticket] = [
                'punto_venta_id'   => $pv_id,
                'venta_id'         => $v_id,
                'dni_empleado'     => $dni_limpio,
                'tipo_comprobante' => trim($row['TIPO_COMPROBANTE_AFIP_ID'] ?? 'FACTURA'),
                'nro_comprobante'  => trim($row['NRO_COMPROBANTE'] ?? ''),
                'fecha_compra'     => date('Y-m-d H:i:s', strtotime($row['FECHA'])),
                'importe_total'    => limpiarNumeroParaMySQL($row['TOTAL_TICKET']), 
                'detalles'         => []
            ];
            $ventas_ids[] = (int)$v_id;
        }

        if (isset($tickets_agrupados[$clave_ticket])) {
            $tickets_agrupados[$clave_ticket]['detalles'][] = [
                'articulo_id'     => $row['ARTICULO_ID'],
                'descripcion'     => $row['DESCRIPCION'],
                'cantidad'        => limpiarNumeroParaMySQL($row['CANTIDAD']),
                'importe_renglon' => limpiarNumeroParaMySQL($row['DETALLE_IMPORTE'])
            ];
        }
    }

    if (empty($tickets_agrupados)) {
        echo json_encode(["status" => "ok", "msg" => "El sistema ya está al día. No se encontraron nuevos consumos válidos."]);
        $pdo_mssql = null;
        $mysqli->close();
        exit;
    }

    // =========================================================================
    // PARTE 4: INSERCIÓN DE COMPRAS
    // =========================================================================
    $duplicados_existentes = [];
    $ids_string = implode(',', array_unique($ventas_ids));
    $res_dup = $mysqli->query("SELECT punto_venta_id, venta_id FROM compras_cabecera WHERE venta_id IN ($ids_string)");
    if ($res_dup) {
        while ($dup = $res_dup->fetch_assoc()) {
            $duplicados_existentes[$dup['punto_venta_id'] . '_' . $dup['venta_id']] = true;
        }
    }

    $mysqli->begin_transaction();

    $stmt_init = $mysqli->prepare("
        INSERT INTO empleados_limites (dni, mes, anio, periodo_codigo, periodo_desde, periodo_hasta, limite_mensual, consumido_mes_actual, activo) 
        VALUES (
            ?, ?, ?, ?, ?, ?,
            COALESCE((SELECT c.limite_mensual FROM personas p INNER JOIN categorias c ON p.idcategoria = c.idcategoria WHERE p.dni = ?), 0.00), 
            0.00, 
            1
        ) 
        ON DUPLICATE KEY UPDATE dni=dni
    ");

    // Incluimos nro_comprobante en la sentencia de inserción de la cabecera
    $stmt_ins_cab = $mysqli->prepare("INSERT INTO compras_cabecera (punto_venta_id, venta_id, dni_empleado, tipo_comprobante, nro_comprobante, fecha_compra, importe_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_ins_det = $mysqli->prepare("INSERT INTO compras_detalles (punto_venta_id, venta_id, articulo_id, descripcion, cantidad, importe_renglon) VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt_upd = $mysqli->prepare("
        UPDATE empleados_limites el 
        SET el.consumido_mes_actual = COALESCE((
            SELECT SUM(
                CASE 
                    WHEN UPPER(c.tipo_comprobante) LIKE '%NOTA_CREDITO%' 
                      OR UPPER(c.tipo_comprobante) LIKE '%CREDITO%' 
                    THEN -c.importe_total 
                    ELSE c.importe_total 
                END
            ) 
            FROM compras_cabecera c 
            WHERE c.dni_empleado = ? 
              AND c.fecha_compra BETWEEN el.periodo_desde AND el.periodo_hasta
        ), 0.00) 
        WHERE el.dni = ? AND el.periodo_codigo = ?
    ");

    $registros_nuevos = 0;

    foreach ($tickets_agrupados as $clave_ticket => $ticket) {
        $clave_dup = $ticket['punto_venta_id'] . '_' . $ticket['venta_id'];
        if (isset($duplicados_existentes[$clave_dup])) {
            continue;
        }

        $dni         = $ticket['dni_empleado'];
        $pv_id       = $ticket['punto_venta_id'];
        $v_id        = $ticket['venta_id'];
        $tipo_comp   = $ticket['tipo_comprobante'];
        $nro_comp    = $ticket['nro_comprobante'];
        $monto_total = $ticket['importe_total'];
        $fecha       = $ticket['fecha_compra'];
        
        $p = obtenerPeriodoOperativo($fecha, $mapaReglas);

        if ($dni !== '0') {
            $stmt_init->bind_param("siissss", $dni, $p['mes'], $p['anio'], $p['periodo_codigo'], $p['periodo_desde'], $p['periodo_hasta'], $dni);
            $stmt_init->execute();
        }

        // Parámetros: i = int, s = string, d = double -> "iissssd"
        $stmt_ins_cab->bind_param("iissssd", $pv_id, $v_id, $dni, $tipo_comp, $nro_comp, $fecha, $monto_total);
        $stmt_ins_cab->execute();

        foreach ($ticket['detalles'] as $det) {
            $stmt_ins_det->bind_param(
                "iissdd", 
                $pv_id, 
                $v_id, 
                $det['articulo_id'], 
                $det['descripcion'], 
                $det['cantidad'], 
                $det['importe_renglon']
            );
            $stmt_ins_det->execute();
        }

        if ($dni !== '0') {
            $stmt_upd->bind_param("sss", $dni, $dni, $p['periodo_codigo']);
            $stmt_upd->execute();
        }

        $registros_nuevos++;
    }

    $stmt_init->close();
    $stmt_ins_cab->close();
    $stmt_ins_det->close();
    $stmt_upd->close();

    $stmt_upd_conf = $mysqli->prepare("
        INSERT INTO configuracion (clave, valor) 
        VALUES ('ultima_sincronizacion_cajas', ?) 
        ON DUPLICATE KEY UPDATE valor = ?
    ");
    $stmt_upd_conf->bind_param("ss", $ahora_sincronizacion, $ahora_sincronizacion);
    $stmt_upd_conf->execute();
    $stmt_upd_conf->close();

    $mysqli->commit();
    $pdo_mssql = null;

    echo json_encode([
        "status" => "ok",
        "msg" => $registros_nuevos > 0 
            ? "Sincronización completada. Se importaron $registros_nuevos comprobantes con sus respectivos números." 
            : "El sistema ya está al día. No se encontraron nuevos consumos."
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => "Error de sincronización: " . $e->getMessage()]);
}

$mysqli->close();