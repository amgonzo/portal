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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Método no permitido.'
    ]);

    exit;
}

$entrada = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($entrada)) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Datos inválidos.'
    ]);

    exit;
}

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

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);

    exit;
}

if ($accion === '') {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Debe indicar una acción.'
    ]);

    exit;
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

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    if ((int)$agente['activo'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'msg' => 'El agente está desactivado.'
        ]);

        exit;
    }

    if (
        !password_verify(
            $token,
            $agente['token_hash']
        )
    ) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    $idAgente = (int)$agente['idagente'];
    $idEmpresa = (int)$agente['idempresa'];

    // =====================================================
    // ACTUALIZAR ACTIVIDAD REAL DEL AGENT
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
            // BUSCAR LA TAREA DE MAYOR PRIORIDAD
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

                echo json_encode([
                    'status' => 'ok',
                    'hay_tarea' => false,
                    'tarea' => null
                ]);

                exit;
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

                throw new Exception(
                    'La tarea ya no está disponible.'
                );
            }

            $stmt->close();

            $tarea['estado'] = 'procesando';

            $tarea['intentos'] =
                (int)$tarea['intentos'] + 1;

            $tarea['prioridad'] =
                (int)$tarea['prioridad'];

            $dbEmpresa->commit();

            $dbEmpresa->close();

            echo json_encode([
                'status' => 'ok',
                'hay_tarea' => true,
                'tarea' => $tarea
            ]);

            exit;

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

            throw new Exception(
                'ID de tarea inválido.'
            );
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

            throw new Exception(
                'Estado de resultado inválido.'
            );
        }

        $dbEmpresa = conectarDBEmpresa(
            $mysqli,
            $idEmpresa,
            'DATOS'
        );

        try {

            if (
                is_array($respuesta) ||
                is_object($respuesta)
            ) {

                $respuesta = json_encode(
                    $respuesta,
                    JSON_UNESCAPED_UNICODE
                );

            } else {

                $respuesta = (string)$respuesta;
            }

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
                $respuesta,
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

            $actualizada =
                $stmt->affected_rows;

            $stmt->close();

            if ($actualizada !== 1) {

                throw new Exception(
                    'La tarea no existe, pertenece a otro Agent o ya fue procesada.'
                );
            }

            $dbEmpresa->close();

            echo json_encode([
                'status' => 'ok',
                'msg' => 'Resultado de tarea registrado correctamente.'
            ]);

            exit;

        } catch (Exception $e) {

            if (
                isset($dbEmpresa) &&
                $dbEmpresa instanceof mysqli
            ) {
                @$dbEmpresa->close();
            }

            throw $e;
        }
    }

    // =====================================================
    // ACCIÓN NO VÁLIDA
    // =====================================================

    throw new Exception(
        'Acción de agente no válida.'
    );

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}