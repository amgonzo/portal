
<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

    $datos = json_decode(file_get_contents('php://input'), true);

    if (!is_array($datos)) {
        throw new Exception('Los datos enviados no son válidos.');
    }

    $idautorizacion = filter_var(
        $datos['idautorizacion'] ?? null,
        FILTER_VALIDATE_INT
    );

    $accion = trim((string) ($datos['accion'] ?? ''));
    $observaciones = trim((string) ($datos['observaciones'] ?? ''));

    if (!$idautorizacion || $idautorizacion < 1) {
        throw new Exception('La autorización indicada no es válida.');
    }

    if (!in_array($accion, ['autorizar', 'rechazar'], true)) {
        throw new Exception('La acción indicada no es válida.');
    }

    if ($accion === 'rechazar' && $observaciones === '') {
        throw new Exception('Indicá el motivo del rechazo.');
    }

    $idusuario = (int) (
        $userAuth['idusuario']
        ?? $userAuth['idUsuario']
        ?? 0
    );

    if ($idusuario < 1) {
        throw new Exception(
            'No se pudo identificar al usuario autenticado.'
        );
    }

    $mysqli->begin_transaction();

    /*
     * Bloquear y validar la solicitud.
     */
    $stmt = $mysqli->prepare("
        SELECT *
        FROM horas_compensacion_autorizaciones
        WHERE idautorizacion = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $idautorizacion);
    $stmt->execute();

    $autorizacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$autorizacion) {
        throw new Exception('No se encontró la solicitud.');
    }

    if ($autorizacion['estado'] !== 'pendiente') {
        throw new Exception(
            'La solicitud ya fue resuelta y no se puede volver a procesar.'
        );
    }

    /*
     * Verificar que la compensación esté habilitada.
     */
    $config = $mysqli->query("
        SELECT tratamiento_horas_extra
        FROM configuracion_empresa
        ORDER BY idconfig ASC
        LIMIT 1
    ")->fetch_assoc();

    if (!$config) {
        throw new Exception(
            'No se encontró la configuración de la empresa.'
        );
    }

    if ($config['tratamiento_horas_extra'] !== 'compensacion') {
        throw new Exception(
            'La compensación de horas no está habilitada para esta empresa.'
        );
    }

    $tipoOperacion = $autorizacion['tipo_operacion'];

    if (!in_array(
        $tipoOperacion,
        ['generacion', 'utilizacion'],
        true
    )) {
        throw new Exception('El tipo de operación no es válido.');
    }

    /*
     * Validar los datos de la solicitud.
     */
    $idempleado = (int) $autorizacion['idempleado'];
    $minutos = (int) $autorizacion['minutos_autorizados'];
    $fecha = $autorizacion['fecha_operacion'];

    if ($idempleado < 1 || $minutos < 1 || !$fecha) {
        throw new Exception(
            'La solicitud contiene datos incompletos.'
        );
    }

    /*
     * Validaciones adicionales para una generación.
     */
    if ($tipoOperacion === 'generacion') {
        $idjornada = (int) ($autorizacion['idjornada'] ?? 0);
        $idprocesamiento = (int) (
            $autorizacion['idprocesamiento'] ?? 0
        );
        $tipoTramo = $autorizacion['tipo_tramo_origen'] ?? '';

        if (
            $idjornada < 1 ||
            $idprocesamiento < 1 ||
            !in_array(
                $tipoTramo,
                ['extra_programada', 'extra_automatica'],
                true
            )
        ) {
            throw new Exception(
                'La solicitud de generación contiene datos incompletos.'
            );
        }
        
        /*
        * Evitar autorizar una generación si ya existe un lote
        * para el mismo procesamiento y tipo de tramo.
        */
        $stmt = $mysqli->prepare("
            SELECT idlote
            FROM horas_compensacion_lotes
            WHERE idprocesamiento = ?
            AND tipo_tramo_origen = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->bind_param(
            'is',
            $idprocesamiento,
            $tipoTramo
        );

        $stmt->execute();
        $loteExistente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($loteExistente) {
            throw new Exception(
                'Ya existe un lote para este procesamiento y tipo de tramo. '
                . 'No se puede autorizar nuevamente esta generación.'
            );
        }

    }

    /*
     * Resolver la operación mediante el helper.
     *
     * El helper genera el lote o consume el saldo según
     * el tipo de operación. No repetir esas operaciones aquí.
     */
    $resultado = compensacionHorasResolverAutorizacion(
        $mysqli,
        (int) $idautorizacion,
        $accion,
        $idusuario
    );

    /*
     * Guardar las observaciones sin perder el motivo del rechazo.
     */
    if ($accion === 'rechazar') {
        $observacionesFinales = $observaciones;
    } else {
        $observacionesFinales = $observaciones !== ''
            ? $observaciones
            : (
                $tipoOperacion === 'generacion'
                    ? 'Generación autorizada.'
                    : 'Utilización de horas compensatorias autorizada.'
            );
    }

    $stmt = $mysqli->prepare("
        UPDATE horas_compensacion_autorizaciones
        SET observaciones = ?
        WHERE idautorizacion = ?
    ");

    $stmt->bind_param(
        'si',
        $observacionesFinales,
        $idautorizacion
    );

    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    if ($accion === 'rechazar') {
        $mensaje = 'Solicitud rechazada correctamente.';
    } elseif ($tipoOperacion === 'generacion') {
        $mensaje = 'Generación autorizada correctamente.';
    } else {
        $mensaje = 'Utilización autorizada y saldo actualizado correctamente.';
    }

    echo json_encode([
        'status' => 'ok',
        'msg' => $mensaje,
        'data' => [
            'idautorizacion' => (int) $idautorizacion,
            'tipo_operacion' => $tipoOperacion,
            'estado' => $resultado['estado'],
            'resultado' => $resultado['resultado']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        try {
            $mysqli->rollback();
        } catch (Throwable $rollbackError) {
        }
    }

    if (http_response_code() < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
