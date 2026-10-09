<?php

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

try {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($metodo, ['GET', 'POST'], true)) {
        http_response_code(405);
        throw new Exception('Método no permitido.');
    }

    $userAuth = validarTokenAPI($mysqli);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa) {
        throw new Exception('No se pudo determinar la empresa activa.');
    }

    $idempresa = (int)$empresa['idempresa'];

    /*
     * Las tareas pertenecen a la base de datos de Fichajes
     * de la empresa activa.
     */
    $db = conectarDBEmpresa($mysqli, $idempresa, 'DATOS');

    if (!$db instanceof mysqli) {
        throw new Exception('No se pudo conectar con la base de Fichajes.');
    }

    if ($metodo === 'GET') {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'listar':

                $sql = "
                    SELECT
                        t.idtarea,
                        t.idagente,
                        t.idlector,
                        t.idempleado,
                        t.accion,
                        t.prioridad,
                        t.estado,
                        t.intentos,
                        t.fecha_creacion,
                        t.fecha_procesamiento,
                        t.fecha_completada,
                        l.nombre AS lector_nombre,
                        CONCAT(e.apellido, ', ', e.nombre) AS empleado_nombre
                    FROM tareas_agente t
                    LEFT JOIN lectores l
                        ON l.idlector = t.idlector
                    LEFT JOIN empleados e
                        ON e.idempleado = t.idempleado
                    ORDER BY t.idtarea DESC
                    LIMIT 1000
                ";

                $resultado = $db->query($sql);

                if (!$resultado) {
                    throw new Exception('Error al consultar tareas.');
                }

                $datos = [];

                while ($fila = $resultado->fetch_assoc()) {
                    $fila['idtarea'] = (int)$fila['idtarea'];
                    $fila['idagente'] = $fila['idagente'] !== null
                        ? (int)$fila['idagente'] : null;
                    $fila['idlector'] = $fila['idlector'] !== null
                        ? (int)$fila['idlector'] : null;
                    $fila['idempleado'] = $fila['idempleado'] !== null
                        ? (int)$fila['idempleado'] : null;
                    $fila['prioridad'] = (int)$fila['prioridad'];
                    $fila['intentos'] = (int)$fila['intentos'];

                    $datos[] = $fila;
                }

                $resultado->free();

                responder([
                    'status' => 'ok',
                    'data' => $datos
                ]);

                break;

            case 'detalle':

                $idtarea = filter_input(
                    INPUT_GET,
                    'idtarea',
                    FILTER_VALIDATE_INT
                );

                if (!$idtarea || $idtarea <= 0) {
                    throw new Exception('ID de tarea inválido.');
                }

                $stmt = $db->prepare("
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
                    WHERE idtarea = ?
                    LIMIT 1
                ");

                if (!$stmt) {
                    throw new Exception('No se pudo preparar la consulta del detalle.');
                }

                $stmt->bind_param('i', $idtarea);
                $stmt->execute();

                $resultado = $stmt->get_result();
                $fila = $resultado->fetch_assoc();

                $stmt->close();

                if (!$fila) {
                    http_response_code(404);
                    throw new Exception('La tarea no existe.');
                }

                $fila['datos'] = decodificarYFiltrarJson($fila['datos']);
                $fila['respuesta'] = decodificarYFiltrarJson($fila['respuesta']);

                responder([
                    'status' => 'ok',
                    'data' => $fila
                ]);

                break;

            default:
                throw new Exception('Acción de consulta no válida.');
        }
    }

    /*
     * Acciones administrativas por POST.
     * Admite JSON y formularios tradicionales.
     */
    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($entrada)) {
        $entrada = $_POST;
    }

    $action = $entrada['action'] ?? '';
    $idtarea = filter_var(
        $entrada['idtarea'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$idtarea || $idtarea <= 0) {
        throw new Exception('ID de tarea inválido.');
    }

    switch ($action) {
        case 'cancelar':

            /*
             * Solo cancela tareas que todavía están pendientes.
             * La condición evita cancelar una tarea que el agente
             * ya haya tomado para procesarla.
             */
            $stmt = $db->prepare("
                UPDATE tareas_agente
                SET estado = 'cancelada'
                WHERE idtarea = ?
                  AND estado = 'pendiente'
            ");

            if (!$stmt) {
                throw new Exception('No se pudo preparar la cancelación.');
            }

            $stmt->bind_param('i', $idtarea);
            $stmt->execute();

            $afectadas = $stmt->affected_rows;
            $stmt->close();

            if ($afectadas !== 1) {
                $estadoActual = obtenerEstadoTarea($db, $idtarea);

                if ($estadoActual === null) {
                    http_response_code(404);
                    throw new Exception('La tarea no existe.');
                }

                if ($estadoActual === 'procesando') {
                    http_response_code(409);
                    throw new Exception(
                        'La tarea ya está en proceso. La cancelación de tareas en ejecución todavía no está implementada.'
                    );
                }

                http_response_code(409);
                throw new Exception(
                    'La tarea ya no está pendiente. Actualizá el listado para consultar su estado actual.'
                );
            }

            responder([
                'status' => 'ok',
                'msg' => "La tarea #{$idtarea} fue cancelada."
            ]);

            break;

        case 'reintentar':

            /*
             * Solo permite reintentar tareas con error.
             * Conserva el contador de intentos y la respuesta anterior
             * hasta que el agente informe un nuevo resultado.
             */
            $stmt = $db->prepare("
                UPDATE tareas_agente
                SET
                    estado = 'pendiente',
                    fecha_procesamiento = NULL,
                    fecha_completada = NULL
                WHERE idtarea = ?
                  AND estado = 'error'
            ");

            if (!$stmt) {
                throw new Exception('No se pudo preparar el reintento.');
            }

            $stmt->bind_param('i', $idtarea);
            $stmt->execute();

            $afectadas = $stmt->affected_rows;
            $stmt->close();

            if ($afectadas !== 1) {
                $estadoActual = obtenerEstadoTarea($db, $idtarea);

                if ($estadoActual === null) {
                    http_response_code(404);
                    throw new Exception('La tarea no existe.');
                }

                http_response_code(409);
                throw new Exception(
                    'Solo se pueden reintentar tareas con error. Estado actual: ' .
                    $estadoActual . '.'
                );
            }

            responder([
                'status' => 'ok',
                'msg' => "La tarea #{$idtarea} volvió a la cola de pendientes.",
                'idtarea' => $idtarea
            ]);

            break;

        default:
            throw new Exception('Acción administrativa no válida.');
    }

} catch (Throwable $e) {
    if (http_response_code() < 400) {
        http_response_code(400);
    }

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

function responder(array $datos): void
{
    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

function obtenerEstadoTarea(mysqli $db, int $idtarea): ?string
{
    $stmt = $db->prepare("
        SELECT estado
        FROM tareas_agente
        WHERE idtarea = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('No se pudo consultar el estado de la tarea.');
    }

    $stmt->bind_param('i', $idtarea);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();

    $stmt->close();

    return $fila['estado'] ?? null;
}

function decodificarYFiltrarJson($valor)
{
    if ($valor === null || $valor === '') {
        return null;
    }

    $decodificado = json_decode($valor, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $valor;
    }

    return filtrarDatosBiometricos($decodificado);
}

function filtrarDatosBiometricos($valor)
{
    if (!is_array($valor)) {
        return $valor;
    }

    $resultado = [];

    foreach ($valor as $clave => $contenido) {
        if (
            is_string($clave) &&
            preg_match('/huella|fingerprint|template|biometr/i', $clave)
        ) {
            $resultado[$clave] = '[DATO BIOMÉTRICO OCULTO]';
            continue;
        }

        $resultado[$clave] = is_array($contenido)
            ? filtrarDatosBiometricos($contenido)
            : $contenido;
    }

    return $resultado;
}