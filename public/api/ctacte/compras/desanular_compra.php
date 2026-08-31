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
// api/ctacte/desanular_compra.php
header('Content-Type: application/json');

// 1. Validar primero con la función de auth
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Validar permiso automático por endpoint y método según la tabla de permisos
validarPermisoEndpoint($mysqli, $userAuth);

// 3. Luego conectar a la base de datos de ctacte
$mysqli = conectarDB('CTACTE_');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    $pv_id    = intval($_POST['punto_venta_id'] ?? 0);
    $venta_id = intval($_POST['venta_id'] ?? 0);

    if ($pv_id <= 0 || $venta_id <= 0) {
        throw new Exception("Parámetros de comprobante inválidos.");
    }

    $mysqli->begin_transaction();

    // 1. Obtener estado previo del comprobante
    $stmt = $mysqli->prepare("SELECT * FROM compras_cabecera WHERE punto_venta_id = ? AND venta_id = ?");
    $stmt->bind_param("ii", $pv_id, $venta_id);
    $stmt->execute();
    $datosAntes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$datosAntes) {
        throw new Exception("El comprobante no existe.");
    }

    if (!isset($datosAntes['anulado']) || intval($datosAntes['anulado']) === 0) {
        throw new Exception("El comprobante no se encuentra anulado.");
    }

    // 2. Validar si el período del empleado está cerrado
    $fecha_compra = $datosAntes['fecha_compra'];
    $dni_empleado = $datosAntes['dni_empleado'];

    $stmt_c = $mysqli->prepare("
        SELECT cerrado 
        FROM empleados_limites 
        WHERE dni = ? 
          AND ? BETWEEN periodo_desde AND periodo_hasta 
        LIMIT 1
    ");
    $stmt_c->bind_param("ss", $dni_empleado, $fecha_compra);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if ($res_c && intval($res_c['cerrado']) === 1) {
        throw new Exception("No se puede desanular: el período correspondiente se encuentra cerrado.");
    }

    // 3. Restaurar comprobante (anulado = 0, motivo_anulacion = NULL)
    $stmt_u = $mysqli->prepare("
        UPDATE compras_cabecera 
        SET anulado = 0, motivo_anulacion = NULL 
        WHERE punto_venta_id = ? AND venta_id = ?
    ");
    $stmt_u->bind_param("ii", $pv_id, $venta_id);
    $stmt_u->execute();
    $stmt_u->close();

    // 4. Recalcular el saldo del empleado sumando nuevamente este comprobante
    if (!empty($dni_empleado) && $dni_empleado !== '0' && $dni_empleado !== 'SIN_USUARIO') {
        $stmt_rec = $mysqli->prepare("
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
                  AND COALESCE(c.anulado, 0) = 0
                  AND DATE(c.fecha_compra) BETWEEN el.periodo_desde AND el.periodo_hasta
            ), 0.00) 
            WHERE el.dni = ? 
              AND DATE(?) BETWEEN el.periodo_desde AND el.periodo_hasta
        ");
        $stmt_rec->bind_param("sss", $dni_empleado, $dni_empleado, $fecha_compra);
        $stmt_rec->execute();
        $stmt_rec->close();
    }

    // 5. Registro de auditoría corregido para usar dataantes y datadespues
    if (function_exists('registrarLog')) {
        $datosDespues = $datosAntes;
        $datosDespues['anulado'] = 0;
        $datosDespues['motivo_anulacion'] = null;

        $idUsuarioLog = $userAuth['idusuario'] ?? null;

        registrarLog(
            $mysqli, 
            'desanular_comprobante', 
            'compras_cabecera', 
            $venta_id, 
            $idUsuarioLog, 
            $datosAntes,   // <--- Va a `dataantes`
            $datosDespues  // <--- Va a `datadespues`
        );
    }

    $mysqli->commit();
    echo json_encode(["status" => "ok", "msg" => "Comprobante restaurado y consumos actualizados."]);

} catch (Exception $e) {
    if (isset($mysqli)) $mysqli->rollback();
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();