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

require __DIR__ . '/../../../vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__. '/../../..');
    $dotenv->load();
} catch (Exception $e) {
    if (function_exists('registrarLog')) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        registrarLog($mysqli, 'sincronizacion_compras', 'configuracion', null, $idUsuarioLog, null, ["error" => "No se pudo cargar la configuración dotenv: " . $e->getMessage()]);
    }
    echo json_encode(["status" => "error", "msg" => "Error crítico: No se pudo cargar la configuración."]);
    exit;
}


/**
 * Convierte cualquier formato numérico de SQL Server a un float limpio para MySQL.
 * Evita que "300,00" se interprete como "30000" o que "1.500,50" rompa.
 */
function limpiarNumeroParaMySQL($valor) {
    if ($valor === null || $valor === '') {
        return 0.00;
    }
    
    if (is_numeric($valor) && !is_string($valor)) {
        return (float)$valor;
    }

    $valor = trim($valor);

    // Si tiene comas y puntos (ej: "1.500,50")
    if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
        if (strrpos($valor, ',') > strrpos($valor, '.')) {
            $valor = str_replace('.', '', $valor); // Quitar miles
            $valor = str_replace(',', '.', $valor); // Cambiar decimal
        } else {
            $valor = str_replace(',', '', $valor); // Formato US "1,500.50"
        }
    } 
    // Si solo tiene comas (ej: "1500,50" o "300,00")
    else if (strpos($valor, ',') !== false) {
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

try {
    // =========================================================================
    // 1.A. SINCRONIZAR ALTAS Y BAJAS DESDE TABLA EXTERNA DE FICHAJES
    // =========================================================================
    
    // 1. Insertar Empleados Nuevos que no existan en nuestra tabla local 'personas'
    // Se les asigna por defecto idcategoria = 1 (La categoría base/más baja)
    $mysqli->query("
        INSERT INTO personas (dni, apellido, nombre, idcategoria, origen, activo)
        SELECT documento, apellido, nombre, 1, 'fichajes', 1 
        FROM fichajes.empleados
        WHERE (baja IS NULL OR baja = 0) 
          AND documento NOT IN (SELECT dni FROM personas)
    ");

    // 2. Procesar las bajas: Desactivar localmente si el empleado fue dado de baja en fichajes
    $mysqli->query("
        UPDATE personas p
        INNER JOIN fichajes.empleados e ON p.dni = e.documento
        SET p.activo = 0
        WHERE e.baja = 1 AND p.origen = 'fichajes'
    ");


    // =========================================================================
    // 2. CONEXIÓN A LA BASE DE DATOS DE CAJAS (MSSQL SERVER VIA PDO)
    // =========================================================================
    $host_ms3 = $_ENV['DB_HOST_MS3'];
    $port_ms3 = "1433";          
    $dbname_ms3 = $_ENV['DB_NAME_MS3'];
    $user_ms3 =  $_ENV['DB_USER_MS3'];
    $pass_ms3 = $_ENV['DB_PASS_MS3'];

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
        
    if (!$pdo_mssql) {
        throw new Exception("No se pudo establecer conexión con el servidor de cajas.");
    }

    // =========================================================================
    // 3. OBTENER LA FECHA DE LA ÚLTIMA SINCRONIZACIÓN DESDE CONFIGURACIÓN
    // =========================================================================
    $fecha_desde = date('Y-m-01 00:00:00'); 

    $stmt_last = $mysqli->prepare("SELECT valor FROM configuracion WHERE clave = 'ultima_sincronizacion_cajas'");
    $stmt_last->execute();
    $res_last = $stmt_last->get_result();
    $row_last = $res_last->fetch_assoc();
    $stmt_last->close();

    if (!empty($row_last['valor'])) {
        $fecha_desde = date('Ymd H:i:s', strtotime($row_last['valor']));
    } else {
        $fecha_desde = date('Ym01 00:00:00');
    }

    $ahora_sincronizacion = date('Y-m-d H:i:s');

    // =========================================================================
    // 4. PROCESO DE SINCRONIZACIÓN (TRANSACCIÓN LOCAL EN MYSQL)
    // =========================================================================
    
    // Consulta de SQL Server
    $query_mssql = "SELECT
                        v.VENTA_ID,
                        v.PUNTO_VENTA_ID,
                        v.CONTACTO_ID,
                        v.CONTACTO_DOCUMENTO,
                        v.FECHA,
                        v.IMPORTE,
                        a.DESCRIPCION,
                        vd.ARTICULO_ID,
                        vd.CANTIDAD,
                        vd.IMPORTE AS DETALLE_IMPORTE,
                        SUM(vd.IMPORTE) OVER (PARTITION BY v.VENTA_ID) AS TOTAL_TICKET
                    FROM VENTA v
                    INNER JOIN VENTA_DETALLE vd 
                        ON v.VENTA_ID = vd.VENTA_ID
                        AND v.PUNTO_VENTA_ID = vd.PUNTO_VENTA_ID
                    INNER JOIN ARTICULO a
                        ON a.ARTICULO_ID = vd.ARTICULO_ID   
                    WHERE v.FECHA > :fecha_desde
                      AND v.CONDICION_VENTA = 'CTACTE'
                    ORDER BY v.FECHA ASC";

    $stmt_mssql = $pdo_mssql->prepare($query_mssql);
    $stmt_mssql->execute([
        ':fecha_desde' => $fecha_desde
    ]);

    $tickets_agrupados = [];
    $ventas_ids = []; 

    while ($row = $stmt_mssql->fetch()) {
        $pv_id = $row['PUNTO_VENTA_ID'];
        $v_id = $row['VENTA_ID'];
        $clave_ticket = $pv_id . '_' . $v_id;

        // SANITIZAMOS EL NÚMERO DE ENTRADA
        $importe_total_limpio = limpiarNumeroParaMySQL($row['TOTAL_TICKET']);

        if (!isset($tickets_agrupados[$clave_ticket])) {
            
            // Evaluamos si el DNI viene vacío o huérfano desde las cajas (consumo interno sin asociar)
            $dni_limpio = trim($row['CONTACTO_DOCUMENTO']);
            if (empty($dni_limpio)) {
                $dni_limpio = 'CONSUMO_INTERNO';
            }

            $tickets_agrupados[$clave_ticket] = [
                'punto_venta_id' => $pv_id,
                'venta_id' => $v_id,
                'dni_empleado' => $dni_limpio,
                'fecha_compra' => date('Y-m-d H:i:s', strtotime($row['FECHA'])),
                'importe_total' => $importe_total_limpio, 
                'detalles' => []
            ];
            $ventas_ids[] = (int)$v_id;
        }

        $tickets_agrupados[$clave_ticket]['detalles'][] = [
            'articulo_id' => $row['ARTICULO_ID'],
            'descripcion' => $row['DESCRIPCION'],
            'cantidad' => limpiarNumeroParaMySQL($row['CANTIDAD']),
            'importe_renglon' => limpiarNumeroParaMySQL($row['DETALLE_IMPORTE'])
        ];
    }

    // Si no hay tickets nuevos en el servidor de cajas, terminamos rápido
    if (empty($tickets_agrupados)) {
        echo json_encode([
            "status" => "ok",
            "msg" => "El sistema ya está al día. No se encontraron nuevos consumos."
        ]);
        $pdo_mssql = null;
        $mysqli->close();
        exit;
    }

    // --- OPTIMIZACIÓN 1: Obtener duplicados masivamente ---
    $duplicados_existentes = [];
    $ids_string = implode(',', $ventas_ids);
    $res_dup = $mysqli->query("SELECT punto_venta_id, venta_id FROM compras_cabecera WHERE venta_id IN ($ids_string)");
    if ($res_dup) {
        while ($dup = $res_dup->fetch_assoc()) {
            $duplicados_existentes[$dup['punto_venta_id'] . '_' . $dup['venta_id']] = true;
        }
    }

    // --- OPTIMIZACIÓN 2: Pre-cargar Personas de la tabla local en memoria ---
    $mapa_personas_local = [];
    $res_per = $mysqli->query("SELECT dni FROM personas");
    if ($res_per) {
        while ($p = $res_per->fetch_assoc()) {
            $mapa_personas_local[$p['dni']] = true;
        }
    }

    // Iniciamos la transacción MySQL
    $mysqli->begin_transaction();

    // Preparamos los statements fijos
    
    // Statement de emergencia: Si el DNI de la compra no está en nuestra tabla local, se crea al vuelo como origen 'manual'
    $stmt_ins_persona_emergencia = $mysqli->prepare("
        INSERT INTO personas (dni, apellido, nombre, idcategoria, origen, activo) 
        VALUES (?, 'Externo / Interno', 'Sincronizado al vuelo', 1, 'manual', 1) 
        ON DUPLICATE KEY UPDATE dni=dni
    ");

    // NUEVO STATEMENT DINÁMICO: Hereda el límite_mensual real configurado en la tabla de categorías
    $stmt_init = $mysqli->prepare("
        INSERT INTO empleados_limites (dni, mes, anio, limite_mensual, consumido_mes_actual, activo) 
        VALUES (
            ?, ?, ?, 
            COALESCE((SELECT c.limite_mensual FROM personas p INNER JOIN categorias c ON p.idcategoria = c.idcategoria WHERE p.dni = ?), 0.00), 
            0.00, 
            1
        ) 
        ON DUPLICATE KEY UPDATE dni=dni
    ");
    
    // Ajusté la subconsulta de arriba para que busque dinámicamente en 'categorias' vinculando por el 'idcategoria' de la persona
    $stmt_init = $mysqli->prepare("
        INSERT INTO empleados_limites (dni, mes, anio, limite_mensual, consumido_mes_actual, activo) 
        VALUES (
            ?, ?, ?, 
            COALESCE((SELECT c.limite_mensual FROM personas p INNER JOIN categorias c ON p.idcategoria = c.idcategoria WHERE p.dni = ?), 0.00), 
            0.00, 
            1
        ) 
        ON DUPLICATE KEY UPDATE dni=dni
    ");

    $stmt_ins_cab = $mysqli->prepare("INSERT INTO compras_cabecera (punto_venta_id, venta_id, dni_empleado, fecha_compra, importe_total) VALUES (?, ?, ?, ?, ?)");
    $stmt_ins_det = $mysqli->prepare("INSERT INTO compras_detalles (punto_venta_id, venta_id, articulo_id, descripcion, cantidad, importe_renglon) VALUES (?, ?, ?, ?, ?, ?)");
    
    $stmt_upd = $mysqli->prepare("
        UPDATE empleados_limites el 
        SET el.consumido_mes_actual = COALESCE((
            SELECT SUM(c.importe_total) 
            FROM compras_cabecera c 
            WHERE c.dni_empleado = ? 
            AND MONTH(c.fecha_compra) = ?
            AND YEAR(c.fecha_compra) = ?
        ), 0.00) 
        WHERE el.dni = ? AND el.mes = ? AND el.anio = ?
    ");

    $registros_nuevos = 0;

    // Procesamos cada ticket agrupado
    foreach ($tickets_agrupados as $clave_ticket => $ticket) {
        if (isset($duplicados_existentes[$clave_ticket])) {
            continue;
        }

        $dni = $ticket['dni_empleado'];

        // Si el DNI que viene de la caja no existe en nuestra tabla unificada local (externos o gastos no catalogados)
        if (!isset($mapa_personas_local[$dni])) {
            $stmt_ins_persona_emergencia->bind_param("s", $dni);
            $stmt_ins_persona_emergencia->execute();
            $mapa_personas_local[$dni] = true; // Lo registramos en el mapa temporal de ejecución
        }

        $pv_id = $ticket['punto_venta_id'];
        $v_id = $ticket['venta_id'];
        $monto_total = $ticket['importe_total'];
        $fecha = $ticket['fecha_compra'];
        
        // 1. EXTRAER EL MES Y AÑO DE LA COMPRA REAL
        $timestamp_compra = strtotime($fecha);
        $compra_mes = (int)date('n', $timestamp_compra);
        $compra_anio = (int)date('Y', $timestamp_compra);

        // 2. INICIALIZAR EL LÍMITE CON EL MES Y AÑO NUEVOS (Heredando dinámicamente el cupo de su categoría)
        $stmt_init->bind_param("siis", $dni, $compra_mes, $compra_anio, $dni);
        $stmt_init->execute();

        // 3. INSERCIÓN DE CABECERA
        $stmt_ins_cab->bind_param("iissd", $pv_id, $v_id, $dni, $fecha, $monto_total);
        $stmt_ins_cab->execute();

        // 4. BUCLE DE DETALLES
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

        // 5. RECALCULAR EL CONSUMIDO CON LOS NUEVOS PARÁMETROS
        $stmt_upd->bind_param("siiisi", $dni, $compra_mes, $compra_anio, $dni, $compra_mes, $compra_anio);
        $stmt_upd->execute();

        $registros_nuevos++;
    }

    // Cerramos los statements preparados
    $stmt_ins_persona_emergencia->close();
    $stmt_init->close();
    $stmt_ins_cab->close();
    $stmt_ins_det->close();
    $stmt_upd->close();

    // =========================================================================
    // 5. ACTUALIZAR LA FECHA DE LA ÚLTIMA SINCRONIZACIÓN EXITOSA
    // =========================================================================
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

    // Registro de auditoría exitosa de sincronización
    if (function_exists('registrarLog')) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        $datosSincro = ["fecha_ejecucion" => $ahora_sincronizacion, "registros_importados" => $registros_nuevos];
        registrarLog($mysqli, 'sincronizacion_compras', 'configuracion', null, $idUsuarioLog, null, $datosSincro);
    }

    echo json_encode([
        "status" => "ok",
        "msg" => $registros_nuevos > 0 
            ? "Sincronización completada. Se importaron $registros_nuevos nuevos tickets con sus respectivos detalles." 
            : "El sistema ya está al día. No se encontraron nuevos consumos."
    ]);

} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    // Registro de auditoría de error
    if (function_exists('registrarLog') && isset($mysqli)) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        registrarLog($mysqli, 'sincronizacion_compras', 'configuracion', null, $idUsuarioLog, null, ["error" => $e->getMessage()]);
    }

    echo json_encode([
        "status" => "error",
        "msg" => "Error de sincronización: " . $e->getMessage()
    ]);
}

$mysqli->close();