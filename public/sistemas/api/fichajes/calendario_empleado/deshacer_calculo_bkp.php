<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Throwable $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];
require_once $rutas['auditoria'];

/**
 * Respuesta JSON.
 */
function responder($status, $mensaje = '', $datos = [], $codigoHttp = null)
{
    http_response_code(
        $codigoHttp ?? ($status === 'ok' ? 200 : 400)
    );

    echo json_encode(
        array_merge(
            [
                'status'  => $status,
                'mensaje' => $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/**
 * Ejecutar una consulta preparada.
 */
function ejecutarConsulta($mysqli, $sql, $tipos = '', ...$parametros)
{
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            'Error preparando consulta: ' . $mysqli->error
        );
    }

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$parametros);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error ejecutando consulta: ' . $error
        );
    }

    return $stmt;
}

try {

    /*
     * =========================================================
     * AUTENTICACIÓN Y CONEXIÓN
     * =========================================================
     */

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );

    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    /*
     * =========================================================
     * VALIDAR MÉTODO
     * =========================================================
     */

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(
            'error',
            'Método HTTP no permitido.',
            [],
            405
        );
    }

    /*
     * =========================================================
     * LEER DATOS
     * =========================================================
     */

    $entrada = file_get_contents('php://input');
    $datos = json_decode($entrada, true);

    if (!is_array($datos)) {
        responder(
            'error',
            'Los datos recibidos no son válidos.',
            [],
            400
        );
    }

    $idEmpleado = filter_var(
        $datos['idempleado'] ?? null,
        FILTER_VALIDATE_INT
    );

    $fecha = trim(
        (string)($datos['fecha'] ?? '')
    );

    if (!$idEmpleado || $idEmpleado <= 0) {
        responder(
            'error',
            'Empleado inválido.',
            [],
            400
        );
    }

    $fechaObjeto = DateTime::createFromFormat(
        '!Y-m-d',
        $fecha
    );

    if (
        !$fechaObjeto ||
        $fechaObjeto->format('Y-m-d') !== $fecha
    ) {
        responder(
            'error',
            'Fecha inválida.',
            [],
            400
        );
    }

    /*
     * =========================================================
     * VERIFICAR EMPLEADO
     * =========================================================
     */

    $stmt = ejecutarConsulta(
        $mysqli,
        "
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
        LIMIT 1
        ",
        'i',
        $idEmpleado
    );

    $resultado = $stmt->get_result();
    $empleado = $resultado->fetch_assoc();
    $stmt->close();

    if (!$empleado) {
        responder(
            'error',
            'No se encontró el empleado.',
            [],
            404
        );
    }

    /*
     * =========================================================
     * DESHACER CÁLCULO
     * =========================================================
     *
     * Se conserva:
     * - El registro de empleados_calendario.
     * - Las marcas del reloj.
     * - Las marcas manuales.
     * - Las relaciones de jornada_marcas.
     * - Las asistencias manuales con idprocesamiento NULL.
     * - El historial del procesamiento, marcado como revertido.
     *
     * Se elimina:
     * - Las asistencias automáticas del procesamiento vigente.
     * - Los tramos calculados de la jornada.
     * - Los descansos calculados de la jornada.
     *
     * La jornada vuelve al estado pendiente.
     */

    $mysqli->begin_transaction();

    try {

        /*
         * Bloquear la jornada del empleado y del día.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            SELECT
                idjornada,
                estado
            FROM jornadas
            WHERE idempleado = ?
              AND fecha = ?
            LIMIT 1
            FOR UPDATE
            ",
            'is',
            $idEmpleado,
            $fecha
        );

        $resultado = $stmt->get_result();
        $jornada = $resultado->fetch_assoc();
        $stmt->close();

        if (!$jornada) {
            $mysqli->commit();

            responder(
                'ok',
                'No existe una jornada calculada para ese día. No se realizaron cambios.',
                [
                    'idempleado' => $idEmpleado,
                    'fecha' => $fecha,
                    'deshecho' => false
                ]
            );
        }

        $idJornada = (int)$jornada['idjornada'];

        /*
         * Buscar el procesamiento vigente.
         * Se bloquea para evitar deshacerlo dos veces
         * simultáneamente.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            SELECT
                idprocesamiento,
                numero
            FROM jornadas_procesamientos
            WHERE idjornada = ?
              AND estado = 'vigente'
            ORDER BY numero DESC
            LIMIT 1
            FOR UPDATE
            ",
            'i',
            $idJornada
        );

        $resultado = $stmt->get_result();
        $procesamiento = $resultado->fetch_assoc();
        $stmt->close();

        if (!$procesamiento) {
            $mysqli->commit();

            responder(
                'ok',
                'Este día no tiene un cálculo vigente para deshacer. No se realizaron cambios.',
                [
                    'idempleado' => $idEmpleado,
                    'fecha' => $fecha,
                    'deshecho' => false
                ]
            );
        }

        $idProcesamiento = (int)$procesamiento['idprocesamiento'];

        /*
         * 1. Eliminar únicamente las asistencias automáticas
         *    generadas por el procesamiento vigente.
         *
         * Las asistencias manuales tienen idprocesamiento NULL
         * y no se eliminan.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            DELETE FROM empleados_calendario_asistencias
            WHERE idprocesamiento = ?
            ",
            'i',
            $idProcesamiento
        );

        $asistenciasEliminadas = $stmt->affected_rows;
        $stmt->close();

        /*
         * 2. Eliminar los tramos calculados de la jornada.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            DELETE FROM jornada_tramos
            WHERE idjornada = ?
            ",
            'i',
            $idJornada
        );

        $tramosEliminados = $stmt->affected_rows;
        $stmt->close();

        /*
         * 3. Eliminar los descansos calculados de la jornada.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            DELETE FROM jornada_descansos
            WHERE idjornada = ?
            ",
            'i',
            $idJornada
        );

        $descansosEliminados = $stmt->affected_rows;
        $stmt->close();

        /*
         * 4. Conservar el historial y marcar el procesamiento
         *    vigente como revertido.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            UPDATE jornadas_procesamientos
            SET estado = 'revertido'
            WHERE idprocesamiento = ?
              AND estado = 'vigente'
            ",
            'i',
            $idProcesamiento
        );

        if ($stmt->affected_rows !== 1) {
            $stmt->close();

            throw new Exception(
                'El procesamiento dejó de estar vigente. No se pudo deshacer el cálculo.'
            );
        }

        $stmt->close();

        /*
         * 5. Reiniciar los resultados de la jornada.
         *
         * Se conservan el horario asignado y las marcas
         * originales vinculadas a la jornada.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            UPDATE jornadas
            SET
                minutos_trabajados = 0,
                minutos_descanso = 0,
                minutos_descanso_exceso = 0,
                minutos_atraso = 0,
                minutos_salida_anticipada = 0,
                minutos_extra = 0,
                estado = 'pendiente',
                fecha_calculo = NULL
            WHERE idjornada = ?
            ",
            'i',
            $idJornada
        );

        $stmt->close();

        /*
         * Confirmar todos los cambios.
         */

        $mysqli->commit();

        responder(
            'ok',
            'Se deshizo el cálculo del día. La jornada quedó pendiente y se conservaron las marcas y las asistencias manuales.',
            [
                'idempleado' => $idEmpleado,
                'fecha' => $fecha,
                'idjornada' => $idJornada,
                'idprocesamiento' => $idProcesamiento,
                'deshecho' => true,
                'asistencias_eliminadas' => $asistenciasEliminadas,
                'tramos_eliminados' => $tramosEliminados,
                'descansos_eliminados' => $descansosEliminados
            ]
        );

    } catch (Throwable $e) {

        try {
            $mysqli->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                'deshacer_calculo.php rollback: ' .
                $rollbackError->getMessage()
            );
        }

        throw $e;
    }

} catch (Throwable $e) {

    error_log(
        'deshacer_calculo.php: ' .
        $e->getMessage()
    );

    responder(
        'error',
        'No se pudo deshacer el cálculo del día. Revisá el registro de errores del servidor.',
        [],
        500
    );
}