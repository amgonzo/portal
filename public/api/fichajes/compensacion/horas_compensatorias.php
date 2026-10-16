<?php
// api/fichajes/compensacion/horas_compensatorias.php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];
require_once $rutas['compensacion_horas'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function responderHorasCompensatorias($data)
{
    echo json_encode([
        'status' => 'ok',
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function obtenerMinutosPorTipo($mysqli, $tipoMovimiento, $idempleado = null)
{
    if ($idempleado !== null) {
        $sql = "
            SELECT COALESCE(SUM(ABS(minutos)), 0) AS total
            FROM horas_compensacion_movimientos
            WHERE tipo_movimiento = ?
              AND estado = 'vigente'
              AND idempleado = ?
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('si', $tipoMovimiento, $idempleado);
    } else {
        $sql = "
            SELECT COALESCE(SUM(ABS(minutos)), 0) AS total
            FROM horas_compensacion_movimientos
            WHERE tipo_movimiento = ?
              AND estado = 'vigente'
        ";

        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('s', $tipoMovimiento);
    }

    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($resultado['total'] ?? 0);
}

function obtenerResumenEmpleadoHoras($mysqli, $idempleado)
{
    $sql = "
        SELECT
            COALESCE((
                SELECT SUM(l.minutos_generados)
                FROM horas_compensacion_lotes l
                WHERE l.idempleado = e.idempleado
                  AND l.estado <> 'revertido'
            ), 0) AS minutos_generados,

            COALESCE((
                SELECT SUM(l.minutos_disponibles)
                FROM horas_compensacion_lotes l
                WHERE l.idempleado = e.idempleado
                  AND l.estado = 'disponible'
            ), 0) AS minutos_disponibles

        FROM empleados e
        WHERE e.idempleado = ?
    ";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $idempleado);
    $stmt->execute();

    $resumen = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resumen) {
        throw new Exception('No se encontró el empleado.');
    }

    $resumen['minutos_generados'] = (int) $resumen['minutos_generados'];
    $resumen['minutos_disponibles'] = (int) $resumen['minutos_disponibles'];
    $resumen['minutos_utilizados'] = obtenerMinutosPorTipo(
        $mysqli,
        'utilizacion',
        $idempleado
    );
    $resumen['minutos_vencidos'] = obtenerMinutosPorTipo(
        $mysqli,
        'vencimiento',
        $idempleado
    );

    return $resumen;
}

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';

    if (!in_array($metodo, ['GET', 'POST'], true)) {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }

    $userAuth = validarTokenAPI($mysqli);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int) $empresa['idempresa'],
        'DATOS'
    );

    $datosPost = [];

    if ($metodo === 'POST') {
        $contenido = file_get_contents('php://input');
        $datosPost = json_decode($contenido, true);

        if (!is_array($datosPost)) {
            $datosPost = [];
        }
    }

    $accion = trim(
        (string) (
            $_GET['accion']
            ?? $datosPost['accion']
            ?? ''
        )
    );

    if (
        $metodo === 'POST' &&
        $accion === 'procesar_vencimientos'
    ) {
        $stmt = $mysqli->prepare("
            SELECT tratamiento_horas_extra,
                modo_vencimiento_compensacion
            FROM configuracion_empresa
            ORDER BY idconfig ASC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->execute();
        $configuracion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$configuracion ||
            $configuracion['tratamiento_horas_extra'] !== 'compensacion'
        ) {
            throw new RuntimeException(
                'Las horas compensatorias no están habilitadas.'
            );
        }

        if (
            $configuracion['modo_vencimiento_compensacion'] !== 'revision'
        ) {
            throw new RuntimeException(
                'El procesamiento manual solo está disponible en modo revisión.'
            );
        }

        $mysqli->begin_transaction();

        try {
            $resultadoVencimientos =
                compensacionHorasProcesarVencimientos(
                    $mysqli,
                    null,
                    'automatico'
                );

            $mysqli->commit();

            responderHorasCompensatorias([
                'mensaje' => 'Vencimientos procesados correctamente.',
                'procesados' => $resultadoVencimientos['procesados'],
                'minutos_vencidos' => $resultadoVencimientos['minutos_vencidos']
            ]);
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }

    if (
        $metodo === 'POST' &&
        $accion === 'procesar_vencimientos'
    ) {
        $stmt = $mysqli->prepare("
            SELECT tratamiento_horas_extra,
                modo_vencimiento_compensacion
            FROM configuracion_empresa
            ORDER BY idconfig ASC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->execute();
        $configuracion = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$configuracion ||
            $configuracion['tratamiento_horas_extra'] !== 'compensacion'
        ) {
            throw new RuntimeException(
                'Las horas compensatorias no están habilitadas.'
            );
        }

        if (
            $configuracion['modo_vencimiento_compensacion'] !== 'revision'
        ) {
            throw new RuntimeException(
                'El procesamiento manual solo está disponible en modo revisión.'
            );
        }

        $mysqli->begin_transaction();

        try {
            $resultadoVencimientos =
                compensacionHorasProcesarVencimientos(
                    $mysqli,
                    null,
                    'manual'
                );

            $mysqli->commit();

            responderHorasCompensatorias([
                'mensaje' => 'Vencimientos procesados correctamente.',
                'procesados' => $resultadoVencimientos['procesados'],
                'minutos_vencidos' => $resultadoVencimientos['minutos_vencidos']
            ]);
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }
    }
    
    /*
    * SOLICITAR UTILIZACIÓN DE HORAS COMPENSATORIAS
    * La solicitud queda pendiente y no descuenta saldo
    * hasta que otro usuario la autorice.
    */
    if ($metodo === 'POST' && $accion === 'solicitar_utilizacion') {

        $stmt = $mysqli->prepare("
            SELECT tratamiento_horas_extra
            FROM configuracion_empresa
            ORDER BY idconfig ASC
            LIMIT 1
        ");

        $stmt->execute();
        $config = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$config ||
            $config['tratamiento_horas_extra'] !== 'compensacion'
        ) {
            throw new RuntimeException(
                'Las horas compensatorias no están habilitadas.'
            );
        }

        $idempleado = filter_var(
            $datosPost['idempleado'] ?? null,
            FILTER_VALIDATE_INT
        );

        $fecha = trim((string) ($datosPost['fecha'] ?? ''));
        $horaDesde = trim((string) ($datosPost['hora_desde'] ?? ''));
        $horaHasta = trim((string) ($datosPost['hora_hasta'] ?? ''));
        $motivo = trim((string) ($datosPost['motivo'] ?? ''));

        $idusuario = (int) (
            $userAuth['idusuario']
            ?? $userAuth['idUsuario']
            ?? 0
        );

        if (!$idempleado || $idempleado < 1) {
            throw new InvalidArgumentException(
                'El empleado indicado no es válido.'
            );
        }

        $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);

        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException(
                'La fecha indicada no es válida.'
            );
        }

        if (
            !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $horaDesde) ||
            !preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $horaHasta)
        ) {
            throw new InvalidArgumentException(
                'Debe indicar la hora desde y la hora hasta.'
            );
        }

        $desde = strtotime($horaDesde);
        $hasta = strtotime($horaHasta);

        if ($desde === false || $hasta === false || $hasta <= $desde) {
            throw new InvalidArgumentException(
                'La hora hasta debe ser posterior a la hora desde.'
            );
        }

        $minutos = (int) (($hasta - $desde) / 60);

        if ($minutos < 1) {
            throw new InvalidArgumentException(
                'La duración debe ser mayor que cero.'
            );
        }

        if ($idusuario < 1) {
            throw new RuntimeException(
                'No se pudo identificar al usuario autenticado.'
            );
        }

        $stmt = $mysqli->prepare("
            SELECT idempleado
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idempleado);
        $stmt->execute();

        $empleadoExiste = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$empleadoExiste) {
            throw new RuntimeException(
                'No se encontró el empleado indicado.'
            );
        }

        $mysqli->begin_transaction();

        try {
            $idAutorizacion = compensacionHorasSolicitarUtilizacion(
                $mysqli,
                (int) $idempleado,
                $minutos,
                $fecha,
                null,
                $idusuario,
                $motivo !== ''
                    ? mb_substr($motivo, 0, 255)
                    : 'Salida anticipada',
                'Solicitud desde Permisos de salida. Horario: '
                    . $horaDesde . ' a ' . $horaHasta
            );

            $mysqli->commit();
        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        responderHorasCompensatorias([
            'mensaje' =>
                'Solicitud enviada para autorización. El saldo solo se descontará si se aprueba.',
            'idautorizacion' => $idAutorizacion,
            'minutos_solicitados' => $minutos,
            'estado' => 'pendiente'
        ]);
    }

    switch ($accion) {

        /*
         * RESUMEN GENERAL
         */
        case 'resumen':

            $sql = "
                SELECT
                    COUNT(DISTINCT CASE
                        WHEN l.minutos_disponibles > 0
                         AND l.estado = 'disponible'
                        THEN l.idempleado
                    END) AS empleados_con_saldo,

                    COALESCE(SUM(CASE
                        WHEN l.estado = 'disponible'
                        THEN l.minutos_disponibles
                        ELSE 0
                    END), 0) AS minutos_disponibles,

                    COALESCE(SUM(CASE
                        WHEN l.estado <> 'revertido'
                        THEN l.minutos_generados
                        ELSE 0
                    END), 0) AS minutos_generados

                FROM horas_compensacion_lotes l
            ";

            $resultado = $mysqli->query($sql)->fetch_assoc();

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS total
                FROM horas_compensacion_autorizaciones
                WHERE estado = 'pendiente'
            ");

            $stmt->execute();
            $pendientes = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            responderHorasCompensatorias([
                'empleados_con_saldo' => (int) $resultado['empleados_con_saldo'],
                'minutos_disponibles' => (int) $resultado['minutos_disponibles'],
                'minutos_generados' => (int) $resultado['minutos_generados'],
                'autorizaciones_pendientes' => (int) ($pendientes['total'] ?? 0)
            ]);

            break;

        /*
         * SALDOS POR EMPLEADO
         */
        case 'saldos':

            $sql = "
                SELECT
                    e.idempleado,
                    e.apellido,
                    e.nombre,

                    COALESCE((
                        SELECT SUM(l.minutos_generados)
                        FROM horas_compensacion_lotes l
                        WHERE l.idempleado = e.idempleado
                          AND l.estado <> 'revertido'
                    ), 0) AS minutos_generados,

                    COALESCE((
                        SELECT SUM(ABS(m.minutos))
                        FROM horas_compensacion_movimientos m
                        WHERE m.idempleado = e.idempleado
                          AND m.tipo_movimiento = 'utilizacion'
                          AND m.estado = 'vigente'
                    ), 0) AS minutos_utilizados,

                    COALESCE((
                        SELECT SUM(ABS(m.minutos))
                        FROM horas_compensacion_movimientos m
                        WHERE m.idempleado = e.idempleado
                          AND m.tipo_movimiento = 'vencimiento'
                          AND m.estado = 'vigente'
                    ), 0) AS minutos_vencidos,

                    COALESCE((
                        SELECT SUM(l.minutos_disponibles)
                        FROM horas_compensacion_lotes l
                        WHERE l.idempleado = e.idempleado
                          AND l.estado = 'disponible'
                    ), 0) AS minutos_disponibles

                FROM empleados e

                WHERE EXISTS (
                    SELECT 1
                    FROM horas_compensacion_lotes l
                    WHERE l.idempleado = e.idempleado
                )
                OR EXISTS (
                    SELECT 1
                    FROM horas_compensacion_movimientos m
                    WHERE m.idempleado = e.idempleado
                )

                ORDER BY e.apellido, e.nombre
            ";

            $resultado = $mysqli->query($sql);
            $datos = [];

            while ($fila = $resultado->fetch_assoc()) {
                $fila['idempleado'] = (int) $fila['idempleado'];
                $fila['minutos_generados'] = (int) $fila['minutos_generados'];
                $fila['minutos_utilizados'] = (int) $fila['minutos_utilizados'];
                $fila['minutos_vencidos'] = (int) $fila['minutos_vencidos'];
                $fila['minutos_disponibles'] = (int) $fila['minutos_disponibles'];

                $datos[] = $fila;
            }

            responderHorasCompensatorias($datos);

            break;

        /*
         * DETALLE DE UN EMPLEADO
         */
        case 'detalle':

            $idempleado = filter_input(
                INPUT_GET,
                'idempleado',
                FILTER_VALIDATE_INT
            );

            if (!$idempleado || $idempleado < 1) {
                throw new Exception('El empleado indicado no es válido.');
            }

            $stmt = $mysqli->prepare("
                SELECT idempleado, apellido, nombre
                FROM empleados
                WHERE idempleado = ?
                LIMIT 1
            ");

            $stmt->bind_param('i', $idempleado);
            $stmt->execute();

            $empleado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$empleado) {
                throw new Exception('No se encontró el empleado.');
            }

            $resumen = obtenerResumenEmpleadoHoras(
                $mysqli,
                $idempleado
            );

            $stmt = $mysqli->prepare("
                SELECT
                    idlote,
                    idempleado,
                    idjornada,
                    idprocesamiento,
                    idautorizacion,
                    fecha_origen,
                    tipo_tramo_origen,
                    minutos_generados,
                    minutos_disponibles,
                    fecha_vencimiento,
                    estado,
                    fecha_creacion
                FROM horas_compensacion_lotes
                WHERE idempleado = ?
                ORDER BY fecha_origen DESC, idlote DESC
            ");

            $stmt->bind_param('i', $idempleado);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $lotes = [];

            while ($fila = $resultado->fetch_assoc()) {
                $fila['idlote'] = (int) $fila['idlote'];
                $fila['minutos_generados'] = (int) $fila['minutos_generados'];
                $fila['minutos_disponibles'] = (int) $fila['minutos_disponibles'];

                $lotes[] = $fila;
            }

            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT
                    idmovimiento,
                    idlote,
                    idjornada,
                    idprocesamiento,
                    idautorizacion,
                    idmovimiento_referencia,
                    tipo_movimiento,
                    minutos,
                    fecha_movimiento,
                    estado,
                    idusuario,
                    observaciones
                FROM horas_compensacion_movimientos
                WHERE idempleado = ?
                ORDER BY fecha_movimiento DESC, idmovimiento DESC
                LIMIT 500
            ");

            $stmt->bind_param('i', $idempleado);
            $stmt->execute();

            $resultado = $stmt->get_result();
            $movimientos = [];

            while ($fila = $resultado->fetch_assoc()) {
                $fila['idmovimiento'] = (int) $fila['idmovimiento'];
                $fila['minutos'] = (int) $fila['minutos'];

                $movimientos[] = $fila;
            }

            $stmt->close();

            responderHorasCompensatorias([
                'empleado' => $empleado,
                'resumen' => $resumen,
                'lotes' => $lotes,
                'movimientos' => $movimientos
            ]);

            break;

        /*
         * MOVIMIENTOS GENERALES
         */
        case 'movimientos':

            $sql = "
                SELECT
                    m.idmovimiento,
                    m.idempleado,
                    e.apellido,
                    e.nombre,
                    m.idlote,
                    m.idjornada,
                    m.idprocesamiento,
                    m.idautorizacion,
                    m.tipo_movimiento,
                    m.minutos,
                    m.fecha_movimiento,
                    m.estado,
                    m.idusuario,
                    m.observaciones

                FROM horas_compensacion_movimientos m

                INNER JOIN empleados e
                    ON e.idempleado = m.idempleado

                ORDER BY m.fecha_movimiento DESC, m.idmovimiento DESC
                LIMIT 1000
            ";

            $resultado = $mysqli->query($sql);
            $datos = [];

            while ($fila = $resultado->fetch_assoc()) {
                $fila['idmovimiento'] = (int) $fila['idmovimiento'];
                $fila['idempleado'] = (int) $fila['idempleado'];
                $fila['minutos'] = (int) $fila['minutos'];

                $datos[] = $fila;
            }

            responderHorasCompensatorias($datos);

            break;

        /*
         * AUTORIZACIONES
         */
        case 'autorizaciones':

            $sql = "
                SELECT
                    a.idautorizacion,
                    a.idempleado,
                    e.apellido,
                    e.nombre,
                    a.tipo_operacion,
                    a.fecha_operacion,
                    a.minutos_autorizados,
                    a.estado,
                    a.idjornada,
                    a.idprocesamiento,
                    a.tipo_tramo_origen,
                    a.idusuario_solicita,
                    a.idusuario_autoriza,
                    a.fecha_solicitud,
                    a.fecha_resolucion,
                    a.motivo,
                    a.observaciones

                FROM horas_compensacion_autorizaciones a

                INNER JOIN empleados e
                    ON e.idempleado = a.idempleado

                ORDER BY
                    CASE WHEN a.estado = 'pendiente' THEN 0 ELSE 1 END,
                    a.fecha_solicitud DESC,
                    a.idautorizacion DESC

                LIMIT 1000
            ";

            $resultado = $mysqli->query($sql);
            $datos = [];

            while ($fila = $resultado->fetch_assoc()) {
                $fila['idautorizacion'] = (int) $fila['idautorizacion'];
                $fila['idempleado'] = (int) $fila['idempleado'];
                $fila['minutos_autorizados'] = (int) $fila['minutos_autorizados'];

                $datos[] = $fila;
            }

            responderHorasCompensatorias($datos);

            break;

        case 'pendientes_vencimiento':

    $fechaActual = date('Y-m-d');

    $stmt = $mysqli->prepare("
        SELECT
            COUNT(*) AS cantidad_lotes,
            COUNT(DISTINCT idempleado) AS cantidad_empleados,
            COALESCE(SUM(minutos_disponibles), 0) AS minutos_pendientes
        FROM horas_compensacion_lotes
        WHERE estado = 'disponible'
          AND minutos_disponibles > 0
          AND fecha_vencimiento IS NOT NULL
          AND fecha_vencimiento < ?
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('s', $fechaActual);
    $stmt->execute();

    $pendientes = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    responderHorasCompensatorias([
        'cantidad_lotes' => (int) $pendientes['cantidad_lotes'],
        'cantidad_empleados' => (int) $pendientes['cantidad_empleados'],
        'minutos_pendientes' => (int) $pendientes['minutos_pendientes']
    ]);

    break;
        default:
            http_response_code(400);
            throw new Exception('La acción solicitada no es válida.');
    }

} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}