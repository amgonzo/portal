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
// api/ctacte/reasignar_comprobante.php
header('Content-Type: application/json');


// 1. Validar primero con la función de auth
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Validar permiso automático por endpoint y método según la tabla de permisos
validarPermisoEndpoint($mysqli, $userAuth);

// 3. Luego conectar a la base de datos de ctacte
$mysqli = conectarDB('CTACTE_');

try {
    $pv_id     = intval($_POST['punto_venta_id'] ?? 0);
    $venta_id  = intval($_POST['venta_id'] ?? 0);
    $nuevo_dni = trim($_POST['nuevo_dni'] ?? '');

    if (!$pv_id || !$venta_id || $nuevo_dni === '') {
        throw new Exception("Datos incompletos para la reasignación.");
    }

    $mysqli->begin_transaction();

    // 1. Obtener la fila completa de la cabecera antes del cambio
    $stmt = $mysqli->prepare("SELECT * FROM compras_cabecera WHERE punto_venta_id = ? AND venta_id = ?");
    $stmt->bind_param("ii", $pv_id, $venta_id);
    $stmt->execute();
    $datosAntes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$datosAntes) {
        throw new Exception("No se encontró el comprobante.");
    }

    $fecha_compra = $datosAntes['fecha_compra'];
    $dni_anterior = $datosAntes['dni_empleado'];

    if ($dni_anterior === $nuevo_dni) {
        throw new Exception("El ticket ya está asignado a esa persona.");
    }

    // 2. Reasignar el nuevo DNI en la cabecera
    $stmt_upd = $mysqli->prepare("UPDATE compras_cabecera SET dni_empleado = ? WHERE punto_venta_id = ? AND venta_id = ?");
    $stmt_upd->bind_param("sii", $nuevo_dni, $pv_id, $venta_id);
    $stmt_upd->execute();
    $stmt_upd->close();

    // 3. Recalcular consumos
    $recalcularConsumo = function($dni, $fecha) use ($mysqli) {
        if (empty($dni) || $dni === '0' || $dni === 'SIN_USUARIO') return;

        $stmt = $mysqli->prepare("
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
                  AND DATE(c.fecha_compra) BETWEEN el.periodo_desde AND el.periodo_hasta
            ), 0.00) 
            WHERE el.dni = ? 
              AND DATE(?) BETWEEN el.periodo_desde AND el.periodo_hasta
        ");
        $stmt->bind_param("sss", $dni, $dni, $fecha);
        $stmt->execute();
        $stmt->close();
    };

    $recalcularConsumo($dni_anterior, $fecha_compra);
    $recalcularConsumo($nuevo_dni, $fecha_compra);

    // 4. AUDITORÍA: Integrada respetando el formato de 7 parámetros
    if (function_exists('registrarLog')) {
        $datosDespues = $datosAntes;
        $datosDespues['dni_empleado'] = $nuevo_dni;

        $idUsuarioLog = $userAuth['idusuario'] ?? null;

        registrarLog(
            $mysqli, 
            'reasignar_comprobante', 
            'compras_cabecera', 
            $venta_id, 
            $idUsuarioLog, 
            $datosAntes, 
            $datosDespues
        );
    }

    $mysqli->commit();
    echo json_encode(["status" => "ok", "msg" => "Comprobante reasignado y saldos actualizados."]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();