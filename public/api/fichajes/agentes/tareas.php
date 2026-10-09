<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['biometria'];

// =====================================================
// FUNCIÓN DE RESPUESTA HTTP
// =====================================================

function responderJSON(
    int $httpCode,
    array $datos
): never {

    http_response_code($httpCode);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// =====================================================
// MÉTODO HTTP
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responderJSON(405, [
        'status' => 'error',
        'msg' => 'Método no permitido.'
    ]);
}

// =====================================================
// LEER ENTRADA
// =====================================================

$entrada = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($entrada)) {

    responderJSON(400, [
        'status' => 'error',
        'msg' => 'Datos inválidos.'
    ]);
}

// =====================================================
// DATOS DEL AGENT
// =====================================================

$agentId = trim(
    (string)($entrada['agent_id'] ?? '')
);

$token = trim(
    (string)($entrada['token'] ?? '')
);

$accion = trim(
    (string)($entrada['accion'] ?? '')
);

if ($agentId === '' || $token === '') {

    responderJSON(400, [
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);
}

if ($accion === '') {

    responderJSON(400, [
        'status' => 'error',
        'msg' => 'Debe indicar una acción.'
    ]);
}

try {

    // =====================================================
    // AUTENTICAR AGENTE
    // =====================================================

    $stmt = $mysqli->prepare("
        SELECT
            idagente,
            agent_id,
            nombre,
            token_hash,
            idempresa,
            activo
        FROM agentes
        WHERE agent_id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar autenticación: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        's',
        $agentId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new Exception(
            'Error al autenticar agente: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $agente = $resultado->fetch_assoc();

    $stmt->close();

    if (!$agente) {

        responderJSON(401, [
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);
    }

    if ((int)$agente['activo'] !== 1) {

        responderJSON(403, [
            'status' => 'error',
            'msg' => 'El agente está desactivado.'
        ]);
    }

    if (
        !password_verify(
            $token,
            $agente['token_hash']
        )
    ) {

        responderJSON(401, [
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);
    }

    $idAgente = (int)$agente['idagente'];
    $idEmpresa = (int)$agente['idempresa'];

    // =====================================================
    // ACTUALIZAR ACTIVIDAD DEL AGENT
    // =====================================================

    $stmt = $mysqli->prepare("
        UPDATE agentes
        SET
            ultima_actividad = NOW(),
            ultimo_error = NULL
        WHERE idagente = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $idAgente
        );

        $stmt->execute();

        $stmt->close();
    }

    // =====================================================
    // OBTENER TAREA
    // =====================================================

    if ($accion === 'obtener') {

        $dbEmpresa = conectarDBEmpresa(
            $mysqli,
            $idEmpresa,
            'DATOS'
        );

        try {

            $dbEmpresa->begin_transaction();

            // -------------------------------------------------
            // BUSCAR TAREA
            // -------------------------------------------------

            $stmt = $dbEmpresa->prepare("
                SELECT
                    idtarea,
                    idagente,
                    idlector,
                    idempleado,
                    accion,
                    prioridad,
                    datos,
                    estado,
                    intentos,
                    respuesta,
                    fecha_creacion,
                    fecha_procesamiento,
                    fecha_completada
                FROM tareas_agente
                WHERE estado = 'pendiente'
                  AND idagente = ?
                ORDER BY
                    prioridad DESC,
                    fecha_creacion ASC,
                    idtarea ASC
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar consulta de tareas: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'i',
                $idAgente
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                throw new Exception(
                    'Error al consultar tareas: ' .
                    $error
                );
            }

            $resultado = $stmt->get_result();

            $tarea = $resultado->fetch_assoc();

            $stmt->close();

            // -------------------------------------------------
            // NO HAY TAREA
            // -------------------------------------------------

            if (!$tarea) {

                $dbEmpresa->commit();
                $dbEmpresa->close();

                responderJSON(200, [
                    'status' => 'ok',
                    'hay_tarea' => false,
                    'tarea' => null
                ]);
            }

            $idTarea = (int)$tarea['idtarea'];

            // -------------------------------------------------
            // TOMAR TAREA
            // -------------------------------------------------

            $stmt = $dbEmpresa->prepare("
                UPDATE tareas_agente
                SET
                    estado = 'procesando',
                    intentos = intentos + 1,
                    fecha_procesamiento = NOW()
                WHERE idtarea = ?
                  AND idagente = ?
                  AND estado = 'pendiente'
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar toma de tarea: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'ii',
                $idTarea,
                $idAgente
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                throw new Exception(
                    'No se pudo tomar la tarea: ' .
                    $error
                );
            }

            if ($stmt->affected_rows !== 1) {

                $stmt->close();

                responderJSON(409, [
                    'status' => 'error',
                    'msg' => 'La tarea ya no está disponible.'
                ]);
            }

            $stmt->close();

            $tarea['estado'] = 'procesando';

            $tarea['intentos'] =
                (int)$tarea['intentos'] + 1;

            $tarea['prioridad'] =
                (int)$tarea['prioridad'];

            $dbEmpresa->commit();
            $dbEmpresa->close();

            responderJSON(200, [
                'status' => 'ok',
                'hay_tarea' => true,
                'tarea' => $tarea
            ]);

        } catch (Exception $e) {

            if (
                isset($dbEmpresa) &&
                $dbEmpresa instanceof mysqli
            ) {
                @$dbEmpresa->rollback();
                @$dbEmpresa->close();
            }

            throw $e;
        }
    }

    // =====================================================
    // INFORMAR RESULTADO
    // =====================================================

    if ($accion === 'resultado') {

        $idTarea = (int)(
            $entrada['idtarea'] ?? 0
        );

        $estado = trim(
            (string)(
                $entrada['estado'] ?? ''
            )
        );

        $respuesta =
            $entrada['respuesta'] ?? null;

        if ($idTarea <= 0) {

            responderJSON(400, [
                'status' => 'error',
                'msg' => 'ID de tarea inválido.'
            ]);
        }

        if (
            !in_array(
                $estado,
                [
                    'completada',
                    'error'
                ],
                true
            )
        ) {

            responderJSON(400, [
                'status' => 'error',
                'msg' => 'Estado de resultado inválido.'
            ]);
        }

        $dbEmpresa = conectarDBEmpresa(
            $mysqli,
            $idEmpresa,
            'DATOS'
        );

        try {

            // =================================================
            // RESPUESTA JSON
            // =================================================

            if (
                is_array($respuesta) ||
                is_object($respuesta)
            ) {

                $respuestaJson = json_encode(
                    $respuesta,
                    JSON_UNESCAPED_UNICODE
                );

            } else {

                $respuestaJson = (string)$respuesta;
            }

            if ($respuestaJson === false) {

                throw new Exception(
                    'No se pudo convertir la respuesta de la tarea.'
                );
            }

            // =================================================
            // TRANSACCIÓN
            // =================================================

            $dbEmpresa->begin_transaction();

            // =================================================
            // OBTENER Y BLOQUEAR TAREA
            // =================================================

            $stmt = $dbEmpresa->prepare("
                SELECT
                    idtarea,
                    idempleado,
                    accion,
                    estado
                FROM tareas_agente
                WHERE idtarea = ?
                  AND idagente = ?
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar consulta de tarea: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'ii',
                $idTarea,
                $idAgente
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                throw new Exception(
                    'Error al consultar tarea: ' .
                    $error
                );
            }

            $resultadoTarea =
                $stmt->get_result();

            $tarea =
                $resultadoTarea->fetch_assoc();

            $stmt->close();

            if (!$tarea) {

                /*
                 * No existe o no pertenece al Agent.
                 */

                $dbEmpresa->rollback();
                $dbEmpresa->close();

                responderJSON(404, [
                    'status' => 'error',
                    'msg' => 'La tarea no existe o pertenece a otro Agent.'
                ]);
            }

            if (
                $tarea['estado'] !== 'procesando'
            ) {

                $estadoActual =
                    $tarea['estado'];

                $dbEmpresa->rollback();
                $dbEmpresa->close();

                responderJSON(409, [
                    'status' => 'error',
                    'msg' =>
                        'La tarea ya fue procesada o no está en estado procesando.',
                    'idtarea' => $idTarea,
                    'estado_actual' => $estadoActual
                ]);
            }

            // =================================================
            // PROCESAR HUELLA
            // =================================================

            $resultadoHuella = null;

            if (
                $estado === 'completada' &&
                $tarea['accion'] === 'enrolar_huella'
            ) {

                $resultadoHuella =
                    guardarHuellaDesdeResultado(
                        $dbEmpresa,
                        (int)$tarea['idempleado'],
                        $respuestaJson
                    );

                if (
                    !is_array($resultadoHuella) ||
                    empty($resultadoHuella['ok'])
                ) {

                    throw new Exception(
                        'No se pudo guardar la huella biométrica.'
                    );
                }
            }

            // =================================================
            // ACTUALIZAR TAREA
            // =================================================

            $stmt = $dbEmpresa->prepare("
                UPDATE tareas_agente
                SET
                    estado = ?,
                    respuesta = ?,
                    fecha_completada = NOW()
                WHERE idtarea = ?
                  AND idagente = ?
                  AND estado = 'procesando'
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar actualización de tarea: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'ssii',
                $estado,
                $respuestaJson,
                $idTarea,
                $idAgente
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                throw new Exception(
                    'No se pudo actualizar la tarea: ' .
                    $error
                );
            }

            if ($stmt->affected_rows !== 1) {

                $stmt->close();

                throw new Exception(
                    'La tarea ya no está disponible para actualizar.'
                );
            }

            $stmt->close();

            // =================================================
            // CONFIRMAR
            // =================================================

            $dbEmpresa->commit();
            $dbEmpresa->close();

            responderJSON(200, [
                'status' => 'ok',
                'msg' => 'Resultado de tarea registrado correctamente.',
                'idtarea' => $idTarea,
                'estado' => $estado,
                'biometria' => $resultadoHuella
            ]);

        } catch (Exception $e) {

            /*
             * Deshacer cualquier modificación biométrica.
             */

            if (
                isset($dbEmpresa) &&
                $dbEmpresa instanceof mysqli
            ) {

                @$dbEmpresa->rollback();
            }

            /*
             * Si el Agent informó completada pero no pudimos
             * guardar la huella, dejamos la tarea en error.
             */

            if (
                isset($dbEmpresa) &&
                $dbEmpresa instanceof mysqli
            ) {

                try {

                    $mensajeError =
                        $e->getMessage();

                    $respuestaError = json_encode([
                        'ok' => false,
                        'error_biometria' => true,
                        'msg' => $mensajeError,
                        'respuesta_agent' => $respuesta
                    ], JSON_UNESCAPED_UNICODE);

                    $dbEmpresa->begin_transaction();

                    $stmtError = $dbEmpresa->prepare("
                        UPDATE tareas_agente
                        SET
                            estado = 'error',
                            respuesta = ?,
                            fecha_completada = NOW()
                        WHERE idtarea = ?
                          AND idagente = ?
                          AND estado = 'procesando'
                    ");

                    if ($stmtError) {

                        $stmtError->bind_param(
                            'sii',
                            $respuestaError,
                            $idTarea,
                            $idAgente
                        );

                        $stmtError->execute();

                        $stmtError->close();
                    }

                    $dbEmpresa->commit();

                } catch (Exception $errorActualizacion) {

                    @$dbEmpresa->rollback();
                }

                @$dbEmpresa->close();
            }

            /*
             * Error interno del procesamiento.
             *
             * El Agent recibe 500 porque la operación solicitada
             * no pudo completarse correctamente.
             */

            responderJSON(500, [
                'status' => 'error',
                'msg' => $e->getMessage(),
                'idtarea' => $idTarea
            ]);
        }
    }

    // =====================================================
    // ACCIÓN NO VÁLIDA
    // =====================================================

    responderJSON(400, [
        'status' => 'error',
        'msg' => 'Acción de agente no válida.'
    ]);

} catch (Exception $e) {

    /*
     * Cualquier excepción no controlada es un error interno.
     */

    responderJSON(500, [
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}