
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
require_once $rutas['compensacion_horas'];

/*
 * Respuesta JSON.
 */
function responder($status, $mensaje = '', $datos = [], $codigoHttp = null)
{
    http_response_code(
        $codigoHttp ?? ($status === 'ok' ? 200 : 400)
    );

    echo json_encode(
        array_merge([
            'status'  => $status,
            'mensaje' => $mensaje
        ], $datos),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
 * Consulta preparada.
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
     * AUTENTICACIÓN Y EMPRESA.
     */

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    $idUsuario = (int)(
        $userAuth['idusuario']
        ?? $userAuth['id_usuario']
        ?? 0
    );

    /*
     * MÉTODO HTTP.
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
     * DATOS RECIBIDOS.
     */

    $datos = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($datos)) {
        responder('error', 'Los datos recibidos no son válidos.', [], 400);
    }

    $idEmpleado = filter_var(
        $datos['idempleado'] ?? null,
        FILTER_VALIDATE_INT
    );

    $fecha = trim((string)($datos['fecha'] ?? ''));

    if (!$idEmpleado || $idEmpleado <= 0) {
        responder('error', 'Empleado inválido.', [], 400);
    }

    $fechaObjeto = DateTime::createFromFormat('!Y-m-d', $fecha);

    if (
        !$fechaObjeto ||
        $fechaObjeto->format('Y-m-d') !== $fecha
    ) {
        responder('error', 'Fecha inválida.', [], 400);
    }

    
    $motivoAnulacion = trim(
        (string)($datos['motivo_anulacion'] ?? '')
    );

    if ($motivoAnulacion === '') {
        responder(
            'error',
            'Debe ingresar el motivo de la anulación.',
            [],
            400
        );
    }

    if (mb_strlen($motivoAnulacion, 'UTF-8') > 1000) {
        responder(
            'error',
            'El motivo no puede superar los 1000 caracteres.',
            [],
            400
        );
    }

    /*
     * VERIFICAR EMPLEADO.
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

    $empleado = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$empleado) {
        responder('error', 'No se encontró el empleado.', [], 404);
    }

    /*
     * DESHACER CÁLCULO.
     *
     * Se conservan:
     * - empleados_calendario.
     * - Marcas originales del reloj.
     * - Marcas manuales.
     * - Relaciones originales de jornada_marcas.
     * - Asistencias manuales con idprocesamiento NULL.
     * - Historial de procesamientos.
     *
     * Se revierten:
     * - Movimientos de compensación del procesamiento.
     * - Autorizaciones de generación que correspondan.
     * - Lotes y movimientos de saldo mediante la función centralizada.
     * - Asistencias automáticas.
     * - Tramos y descansos calculados.
     */

    $mysqli->begin_transaction();

    try {

        /*
         * 1. Bloquear la jornada.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            SELECT idjornada, estado
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

        $jornada = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$jornada) {
            $mysqli->commit();

            responder(
                'ok',
                'No existe una jornada para ese día. No se realizaron cambios.',
                [
                    'idempleado' => $idEmpleado,
                    'fecha' => $fecha,
                    'deshecho' => false
                ]
            );
        }

        $idJornada = (int)$jornada['idjornada'];

        /*
         * 2. Buscar el procesamiento vigente.
         */

        $stmt = ejecutarConsulta(
            $mysqli,
            "
            SELECT idprocesamiento, numero
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

        $procesamiento = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$procesamiento) {
            $mysqli->commit();

            responder(
                'ok',
                'Este día no tiene un cálculo vigente para deshacer.',
                [
                    'idempleado' => $idEmpleado,
                    'fecha' => $fecha,
                    'idjornada' => $idJornada,
                    'deshecho' => false
                ]
            );
        }

        $idProcesamiento = (int)$procesamiento['idprocesamiento'];

        /*
         * 3. Revertir las compensaciones.
         *
         * No borrar directamente autorizaciones, lotes ni movimientos:
         * la función centralizada debe verificar los usos del saldo
         * y revertir los movimientos de forma coherente.
         *
         * La función debe estar disponible desde compensacion_horas.php.
         */

        //require_once __DIR__ . '/compensacion_horas.php';
        
        compensacionHorasRevertirProcesamiento(
            $mysqli,
            $idProcesamiento,
            $idUsuario,
            $motivoAnulacion
        );


        /*
         * 4. Eliminar asistencias automáticas del procesamiento.
         *
         * Las manuales con idprocesamiento NULL se conservan.
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
         * 5. Eliminar tramos calculados.
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
         * 6. Eliminar descansos calculados.
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
         * 7. Marcar el procesamiento como revertido.
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
         * 8. Reiniciar los resultados de la jornada.
         *
         * No se eliminan las marcas originales ni el calendario maestro.
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
         * 9. Confirmar todo junto.
         */

        $mysqli->commit();

        responder(
            'ok',
            'Se deshizo el cálculo del día y se revirtieron las compensaciones del procesamiento.',
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
        'deshacer_calculo.php: ' . $e->getMessage()
    );

    responder(
        'error',
        'No se pudo deshacer el cálculo del día. Revisá el registro de errores del servidor.',
        [],
        500
    );
}
