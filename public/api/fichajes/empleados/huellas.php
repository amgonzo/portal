<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');

// =========================================================
// RUTAS
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
    // Si no existe .env continuamos.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

// =========================================================
// AUTENTICACIÓN Y PERMISOS
// =========================================================

$userAuth = validarTokenAPI(
    $mysqli ?? null
);

validarPermisoEndpoint(
    $mysqli,
    $userAuth
);

// =========================================================
// DETERMINAR EMPRESA ACTUAL
// =========================================================

$empresa = obtenerEmpresaActual(
    $mysqli,
    $userAuth
);

if (
    !is_array($empresa) ||
    empty($empresa['idempresa'])
) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'No se pudo determinar la empresa.'
    ]);

    exit;
}

$idEmpresa = (int)$empresa['idempresa'];

// =========================================================
// CONECTAR A LA BASE DE DATOS DE LA EMPRESA
// =========================================================

$mysqli = conectarDBEmpresa(
    $mysqli,
    $idEmpresa,
    'DATOS'
);

if (
    !$mysqli ||
    !($mysqli instanceof mysqli)
) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => 'No se pudo conectar a la base de datos de la empresa.'
    ]);

    exit;
}

try {

    // =========================================================
    // MÉTODO
    // =========================================================

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        http_response_code(405);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Método no permitido.'
        ]);

        exit;
    }

    // =========================================================
    // DATOS RECIBIDOS
    // =========================================================

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

    $accion = trim(
        (string)($entrada['accion'] ?? '')
    );

    $idEmpleado = (int)(
        $entrada['idempleado'] ?? 0
    );

    $idLector = (int)(
        $entrada['idlector'] ?? 0
    );

    $idDedo = (int)(
        $entrada['iddedo'] ?? -1
    );

    // =========================================================
    // VALIDAR ACCIÓN
    // =========================================================

    if ($accion === '') {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Debe indicar una acción.'
        ]);

        exit;
    }

    // =========================================================
    // VALIDAR EMPLEADO
    // =========================================================

    if ($idEmpleado <= 0) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Empleado inválido.'
        ]);

        exit;
    }

    // =========================================================
    // ACCIONES PERMITIDAS
    // =========================================================

    $accionesPermitidas = [
        'listar_huellas',
        'leer_huellas',
        'enrolar_huella',
        'estado_tarea',
        'sincronizar_empleado',
        'eliminar_empleado'
    ];

    if (
        !in_array(
            $accion,
            $accionesPermitidas,
            true
        )
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Acción de huellas no válida.'
        ]);

        exit;
    }

    
    // =========================================================
    // SINCRONIZAR UN EMPLEADO EN TODOS SUS LECTORES
    // =========================================================

    if ($accion === 'sincronizar_empleado') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Método no permitido.');
        }

        // -----------------------------------------------------
        // DATOS DEL EMPLEADO
        // -----------------------------------------------------

        $stmt = $mysqli->prepare("
            SELECT
                idempleado,
                documento,
                nombre,
                apellido,
                tarjeta,
                activo
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta del empleado: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $empleado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$empleado) {
            throw new Exception('El empleado no existe.');
        }

        if ((int)$empleado['activo'] !== 1) {
            throw new Exception('El empleado está inactivo.');
        }

        if (trim((string)$empleado['documento']) === '') {
            throw new Exception('El empleado no tiene documento.');
        }

        // -----------------------------------------------------
        // TODAS LAS HUELLAS ACTIVAS GUARDADAS
        // -----------------------------------------------------

        $stmt = $mysqli->prepare("
            SELECT
                dedo AS iddedo,
                datos
            FROM empleados_datos_biometricos
            WHERE idempleado = ?
              AND tipo = 'huella'
              AND activo = 1
            ORDER BY CAST(dedo AS UNSIGNED) ASC
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta de huellas: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $resultadoHuellas = $stmt->get_result();
        $huellas = [];

        while ($huella = $resultadoHuellas->fetch_assoc()) {

            $plantilla = trim((string)$huella['datos']);
            $dedo = (int)$huella['iddedo'];

            if ($plantilla === '') {
                continue;
            }

            if ($dedo < 0 || $dedo > 9) {
                continue;
            }

            $huellas[] = [
                'iddedo' => $dedo,
                'datos'  => $plantilla
            ];
        }

        $stmt->close();

        // -----------------------------------------------------
        // TODOS LOS LECTORES ACTIVOS ASIGNADOS
        // -----------------------------------------------------

        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.marca,
                l.modelo,
                l.ip,
                l.puerto,
                l.idagente
            FROM empleados_lectores el
            INNER JOIN lectores l
                ON l.idlector = el.idlector
            WHERE el.idempleado = ?
              AND el.activo = 1
              AND l.activo = 1
            ORDER BY
                l.predeterminado DESC,
                l.idlector ASC
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta de lectores: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $resultadoLectores = $stmt->get_result();
        $lectores = [];

        while ($lector = $resultadoLectores->fetch_assoc()) {
            $lectores[] = $lector;
        }

        $stmt->close();

        if (!$lectores) {
            throw new Exception(
                'El empleado no tiene lectores activos asignados.'
            );
        }

        // -----------------------------------------------------
        // CREAR UNA TAREA POR CADA LECTOR
        // -----------------------------------------------------

        $tareasCreadas = [];
        $tareasExistentes = [];

        $mysqli->begin_transaction();

        try {

            foreach ($lectores as $lector) {

                $idLectorActual = (int)$lector['idlector'];
                $idAgente = (int)$lector['idagente'];

                if ($idAgente <= 0) {
                    throw new Exception(
                        'El lector "' . $lector['nombre'] .
                        '" no tiene un agente asignado.'
                    );
                }

                if (
                    empty($lector['ip']) ||
                    empty($lector['puerto'])
                ) {
                    throw new Exception(
                        'El lector "' . $lector['nombre'] .
                        '" no tiene IP o puerto configurado.'
                    );
                }

                // Evitar tareas duplicadas en el mismo lector.
                $stmtPendiente = $mysqli->prepare("
                    SELECT idtarea
                    FROM tareas_agente
                    WHERE idlector = ?
                      AND idempleado = ?
                      AND accion = 'sincronizar_empleado'
                      AND estado IN ('pendiente', 'procesando')
                    ORDER BY idtarea DESC
                    LIMIT 1
                ");

                if (!$stmtPendiente) {
                    throw new Exception(
                        'No se pudo comprobar si existe una tarea pendiente: ' .
                        $mysqli->error
                    );
                }

                $stmtPendiente->bind_param(
                    'ii',
                    $idLectorActual,
                    $idEmpleado
                );

                $stmtPendiente->execute();

                $tareaExistente = $stmtPendiente
                    ->get_result()
                    ->fetch_assoc();

                $stmtPendiente->close();

                if ($tareaExistente) {
                    $tareasExistentes[] = [
                        'idlector' => $idLectorActual,
                        'idtarea'  => (int)$tareaExistente['idtarea'],
                        'lector'   => $lector['nombre']
                    ];

                    continue;
                }

                // El Worker recibe los datos del usuario y
                // todas las plantillas guardadas.
                $datosTarea = [
                    'marca'      => $lector['marca'],
                    'modelo'     => $lector['modelo'],
                    'ip'         => $lector['ip'],
                    'puerto'     => (int)$lector['puerto'],
                    'idempleado' => $idEmpleado,
                    'documento'  => trim((string)$empleado['documento']),
                    'nombre'     => $empleado['nombre'],
                    'apellido'   => $empleado['apellido'],
                    'tarjeta'    => $empleado['tarjeta'],
                    'huellas'    => $huellas
                ];

                $datosJSON = json_encode(
                    $datosTarea,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

                if ($datosJSON === false) {
                    throw new Exception(
                        'No se pudieron preparar los datos para el lector "' .
                        $lector['nombre'] . '".'
                    );
                }

                $stmtTarea = $mysqli->prepare("
                    INSERT INTO tareas_agente (
                        idagente,
                        idlector,
                        idempleado,
                        accion,
                        datos,
                        estado,
                        intentos,
                        respuesta,
                        fecha_creacion
                    )
                    VALUES (
                        ?, ?, ?,
                        'sincronizar_empleado',
                        ?,
                        'pendiente',
                        0,
                        NULL,
                        NOW()
                    )
                ");

                if (!$stmtTarea) {
                    throw new Exception(
                        'No se pudo preparar la tarea para "' .
                        $lector['nombre'] . '": ' . $mysqli->error
                    );
                }

                $stmtTarea->bind_param(
                    'iiis',
                    $idAgente,
                    $idLectorActual,
                    $idEmpleado,
                    $datosJSON
                );

                if (!$stmtTarea->execute()) {
                    $error = $stmtTarea->error;
                    $stmtTarea->close();

                    throw new Exception(
                        'No se pudo crear la tarea para "' .
                        $lector['nombre'] . '": ' . $error
                    );
                }

                $idTarea = (int)$stmtTarea->insert_id;
                $stmtTarea->close();

                $tareasCreadas[] = [
                    'idtarea' => $idTarea,
                    'idlector' => $idLectorActual,
                    'lector' => $lector['nombre'],
                    'huellas' => count($huellas)
                ];
            }

            $mysqli->commit();

        } catch (Throwable $e) {

            $mysqli->rollback();
            throw $e;
        }

        echo json_encode([
            'status' => 'ok',
            'msg' => 'Sincronización individual solicitada.',
            'idempleado' => $idEmpleado,
            'lectores_asignados' => count($lectores),
            'huellas_enviadas' => count($huellas),
            'tareas_creadas' => count($tareasCreadas),
            'tareas_existentes' => count($tareasExistentes),
            'tareas' => $tareasCreadas,
            'pendientes_previas' => $tareasExistentes
        ]);

        exit;
    }

    
    // =========================================================
    // ELIMINAR EMPLEADO DE TODOS SUS LECTORES ACTIVOS
    // =========================================================

    if ($accion === 'eliminar_empleado') {

        // Obtener el empleado aunque ya esté dado de baja.
        $stmt = $mysqli->prepare("
            SELECT
                idempleado,
                documento
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta del empleado: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $empleado = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$empleado) {
            throw new Exception('El empleado no existe.');
        }

        $documento = trim((string)$empleado['documento']);

        if ($documento === '') {
            throw new Exception('El empleado no tiene documento.');
        }

        // Obtener todos los lectores activos asignados.
        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.marca,
                l.modelo,
                l.ip,
                l.puerto,
                l.idagente
            FROM empleados_lectores el
            INNER JOIN lectores l
                ON l.idlector = el.idlector
            WHERE el.idempleado = ?
              AND el.activo = 1
              AND l.activo = 1
            ORDER BY l.idlector ASC
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta de lectores: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $lectores = [];

        while ($lector = $resultado->fetch_assoc()) {
            $lectores[] = $lector;
        }

        $stmt->close();

        if (!$lectores) {
            throw new Exception(
                'El empleado no tiene lectores activos asignados.'
            );
        }

        $tareasCreadas = [];
        $tareasExistentes = [];

        $mysqli->begin_transaction();

        try {

            foreach ($lectores as $lector) {

                $idLectorActual = (int)$lector['idlector'];
                $idAgente = (int)$lector['idagente'];

                if ($idAgente <= 0) {
                    throw new Exception(
                        'El lector "' . $lector['nombre'] .
                        '" no tiene un agente asignado.'
                    );
                }

                if (empty($lector['ip']) || empty($lector['puerto'])) {
                    throw new Exception(
                        'El lector "' . $lector['nombre'] .
                        '" no tiene IP o puerto configurado.'
                    );
                }

                // Evitar solicitudes duplicadas pendientes.
                $stmtPendiente = $mysqli->prepare("
                    SELECT idtarea
                    FROM tareas_agente
                    WHERE idlector = ?
                      AND idempleado = ?
                      AND accion = 'eliminar_empleado'
                      AND estado IN ('pendiente', 'procesando')
                    ORDER BY idtarea DESC
                    LIMIT 1
                ");

                if (!$stmtPendiente) {
                    throw new Exception(
                        'No se pudo comprobar si existe una tarea pendiente: ' .
                        $mysqli->error
                    );
                }

                $stmtPendiente->bind_param(
                    'ii',
                    $idLectorActual,
                    $idEmpleado
                );

                $stmtPendiente->execute();

                $existente = $stmtPendiente
                    ->get_result()
                    ->fetch_assoc();

                $stmtPendiente->close();

                if ($existente) {
                    $tareasExistentes[] = [
                        'idtarea' => (int)$existente['idtarea'],
                        'idlector' => $idLectorActual,
                        'lector' => $lector['nombre']
                    ];

                    continue;
                }

                $datosTarea = [
                    'marca' => $lector['marca'],
                    'modelo' => $lector['modelo'],
                    'ip' => $lector['ip'],
                    'puerto' => (int)$lector['puerto'],
                    'idempleado' => $idEmpleado,
                    'documento' => $documento
                ];

                $datosJSON = json_encode(
                    $datosTarea,
                    JSON_UNESCAPED_UNICODE |
                    JSON_UNESCAPED_SLASHES
                );

                if ($datosJSON === false) {
                    throw new Exception(
                        'No se pudieron preparar los datos para el lector "' .
                        $lector['nombre'] . '".'
                    );
                }

                $stmtTarea = $mysqli->prepare("
                    INSERT INTO tareas_agente (
                        idagente,
                        idlector,
                        idempleado,
                        accion,
                        datos,
                        estado,
                        intentos,
                        respuesta,
                        fecha_creacion
                    )
                    VALUES (
                        ?, ?, ?,
                        'eliminar_empleado',
                        ?,
                        'pendiente',
                        0,
                        NULL,
                        NOW()
                    )
                ");

                if (!$stmtTarea) {
                    throw new Exception(
                        'No se pudo preparar la tarea para "' .
                        $lector['nombre'] . '": ' . $mysqli->error
                    );
                }

                $stmtTarea->bind_param(
                    'iiis',
                    $idAgente,
                    $idLectorActual,
                    $idEmpleado,
                    $datosJSON
                );

                if (!$stmtTarea->execute()) {
                    $error = $stmtTarea->error;
                    $stmtTarea->close();

                    throw new Exception(
                        'No se pudo crear la tarea para "' .
                        $lector['nombre'] . '": ' . $error
                    );
                }

                $tareasCreadas[] = [
                    'idtarea' => (int)$stmtTarea->insert_id,
                    'idlector' => $idLectorActual,
                    'lector' => $lector['nombre']
                ];

                $stmtTarea->close();
            }

            $mysqli->commit();

        } catch (Throwable $e) {
            $mysqli->rollback();
            throw $e;
        }

        echo json_encode([
            'status' => 'ok',
            'msg' => 'Solicitud de eliminación en lectores creada.',
            'idempleado' => $idEmpleado,
            'lectores_asignados' => count($lectores),
            'tareas_creadas' => count($tareasCreadas),
            'tareas_existentes' => count($tareasExistentes),
            'tareas' => $tareasCreadas,
            'pendientes_previas' => $tareasExistentes
        ]);

        exit;
    }

    // =========================================================
    // ACCIÓN: LISTAR HUELLAS REGISTRADAS
    // =========================================================

    if ($accion === 'listar_huellas') {

        $stmt = $mysqli->prepare("
            SELECT
                idbiometrico,
                idempleado,
                tipo,
                dedo AS iddedo,
                idusuario,
                fecha_carga,
                activo
            FROM empleados_datos_biometricos
            WHERE idempleado = ?
            AND tipo = 'huella'
            AND activo = 1
            ORDER BY CAST(dedo AS UNSIGNED) ASC
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudieron preparar las huellas registradas: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'i',
            $idEmpleado
        );

        if (!$stmt->execute()) {

            $error = $stmt->error;
            $stmt->close();

            throw new Exception(
                'No se pudieron consultar las huellas registradas: ' .
                $error
            );
        }

        $resultadoHuellas = $stmt->get_result();

        $huellas = [];

        while ($huella = $resultadoHuellas->fetch_assoc()) {

            $huellas[] = [
                'idbiometrico' => (int)$huella['idbiometrico'],
                'idempleado'   => (int)$huella['idempleado'],
                'tipo'         => $huella['tipo'],
                'iddedo'       => (int)$huella['iddedo'],
                'idusuario'    => (int)$huella['idusuario'],
                'fecha_carga'  => $huella['fecha_carga'],
                'activo'       => (int)$huella['activo']
            ];
        }

        $stmt->close();

        echo json_encode([
            'status' => 'ok',
            'idempleado' => $idEmpleado,
            'huellas' => $huellas
        ]);

        exit;
    }
    // =========================================================
    // ACCIÓN: ESTADO DE TAREA
    // =========================================================

    if ($accion === 'estado_tarea') {

        $idTarea = (int)(
            $entrada['idtarea'] ?? 0
        );

        if ($idTarea <= 0) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'msg' => 'ID de tarea inválido.'
            ]);

            exit;
        }

        $stmt = $mysqli->prepare("
            SELECT
                idtarea,
                idagente,
                idlector,
                idempleado,
                accion,
                estado,
                intentos,
                respuesta,
                fecha_creacion,
                fecha_procesamiento,
                fecha_completada
            FROM tareas_agente
            WHERE idtarea = ?
              AND idempleado = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'No se pudo preparar la consulta de estado de tarea: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'ii',
            $idTarea,
            $idEmpleado
        );

        if (!$stmt->execute()) {

            $error = $stmt->error;
            $stmt->close();

            throw new Exception(
                'No se pudo consultar el estado de la tarea: ' .
                $error
            );
        }

        $tarea = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$tarea) {

            http_response_code(404);

            echo json_encode([
                'status' => 'error',
                'msg' => 'La tarea no existe o no pertenece al empleado.'
            ]);

            exit;
        }

        $respuesta = null;

        if (!empty($tarea['respuesta'])) {

            $respuestaDecodificada = json_decode(
                $tarea['respuesta'],
                true
            );

            if (json_last_error() === JSON_ERROR_NONE) {
                $respuesta = $respuestaDecodificada;
            } else {
                $respuesta = $tarea['respuesta'];
            }
        }

        // -----------------------------------------------------
        // Si la tarea terminó correctamente, devolver huellas
        // activas del empleado.
        // -----------------------------------------------------

        $huellas = [];

        if ($tarea['estado'] === 'completada') {

            $stmt = $mysqli->prepare("
                SELECT
                    idbiometrico,
                    idempleado,
                    tipo,
                    dedo AS iddedo,
                    idusuario,
                    fecha_carga,
                    activo
                FROM empleados_datos_biometricos
                WHERE idempleado = ?
                  AND tipo = 'huella'
                  AND activo = 1
                ORDER BY CAST(dedo AS UNSIGNED) ASC
            ");

            if (!$stmt) {
                throw new Exception(
                    'No se pudieron consultar las huellas registradas: ' .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                'i',
                $idEmpleado
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    'No se pudieron consultar las huellas registradas: ' .
                    $error
                );
            }

            $resultadoHuellas = $stmt->get_result();

            while ($huella = $resultadoHuellas->fetch_assoc()) {

                $huella['idbiometrico'] =
                    (int)$huella['idbiometrico'];

                $huella['idempleado'] =
                    (int)$huella['idempleado'];

                $huella['iddedo'] =
                    (int)$huella['iddedo'];

                $huella['idusuario'] =
                    (int)$huella['idusuario'];

                $huella['activo'] =
                    (int)$huella['activo'];

                $huellas[] = $huella;
            }

            $stmt->close();
        }

        echo json_encode([
            'status' => 'ok',
            'idtarea' => (int)$tarea['idtarea'],
            'idagente' => (int)$tarea['idagente'],
            'idlector' => (int)$tarea['idlector'],
            'idempleado' => (int)$tarea['idempleado'],
            'accion' => $tarea['accion'],
            'estado' => $tarea['estado'],
            'intentos' => (int)$tarea['intentos'],
            'respuesta' => $respuesta,
            'huellas' => $huellas,
            'fecha_creacion' => $tarea['fecha_creacion'],
            'fecha_procesamiento' => $tarea['fecha_procesamiento'],
            'fecha_completada' => $tarea['fecha_completada']
        ]);

        exit;
    }

    // =========================================================
    // VALIDACIONES PARA ENROLAMIENTO
    // =========================================================

    if ($accion === 'enrolar_huella') {

        if ($idLector <= 0) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'msg' => 'Debe indicar un lector.'
            ]);

            exit;
        }

        if (
            $idDedo < 0 ||
            $idDedo > 9
        ) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'msg' => 'Dedo inválido.'
            ]);

            exit;
        }
    }

    // =========================================================
    // VERIFICAR EMPLEADO
    // =========================================================

    $stmt = $mysqli->prepare("
        SELECT
            idempleado,
            documento,
            nombre,
            apellido,
            activo
        FROM empleados
        WHERE idempleado = ?
        LIMIT 1
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar consulta de empleado: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'i',
        $idEmpleado
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error al consultar empleado: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $empleado = $resultado->fetch_assoc();

    $stmt->close();

    if (!$empleado) {

        throw new Exception(
            'El empleado no existe.'
        );
    }

    if (
        (int)$empleado['activo'] !== 1
    ) {

        throw new Exception(
            'El empleado está inactivo.'
        );
    }

    // =========================================================
    // BUSCAR LECTOR
    // =========================================================

    if ($accion === 'enrolar_huella') {

        /*
         * Para enrolar utilizamos exactamente
         * el lector seleccionado en el modal.
         *
         * El agente pertenece al lector.
         */

        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.ip,
                l.puerto,
                l.ubicacion,
                l.tipo_uso,
                l.idagente
            FROM lectores l
            INNER JOIN empleados_lectores el
                ON el.idlector = l.idlector
            WHERE l.idlector = ?
              AND el.idempleado = ?
              AND el.activo = 1
              AND l.activo = 1
            LIMIT 1
        ");

        if (!$stmt) {

            throw new Exception(
                'Error al consultar el lector seleccionado: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'ii',
            $idLector,
            $idEmpleado
        );

        if (!$stmt->execute()) {

            $error = $stmt->error;
            $stmt->close();

            throw new Exception(
                'Error al consultar el lector seleccionado: ' .
                $error
            );
        }

        $lector = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$lector) {

            throw new Exception(
                'El lector seleccionado no está activo o no está asignado al empleado.'
            );
        }

        if (
            (int)$lector['idagente'] <= 0
        ) {

            throw new Exception(
                'El lector seleccionado no tiene un agente asignado.'
            );
        }

    } else {

        /*
         * Para leer huellas utilizamos el lector
         * predeterminado del empleado.
         */

        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.ip,
                l.puerto,
                l.ubicacion,
                l.tipo_uso,
                l.idagente
            FROM empleados_lectores el
            INNER JOIN lectores l
                ON l.idlector = el.idlector
            WHERE el.idempleado = ?
              AND el.activo = 1
              AND l.activo = 1
            ORDER BY
                l.predeterminado DESC,
                l.idlector ASC
            LIMIT 1
        ");

        if (!$stmt) {

            throw new Exception(
                'Error al preparar consulta de lector: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'i',
            $idEmpleado
        );

        if (!$stmt->execute()) {

            $error = $stmt->error;
            $stmt->close();

            throw new Exception(
                'Error al consultar lector: ' .
                $error
            );
        }

        $lector = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();

        if (!$lector) {

            throw new Exception(
                'El empleado no tiene un lector activo asignado.'
            );
        }

        if (
            (int)$lector['idagente'] <= 0
        ) {

            throw new Exception(
                'El lector no tiene un agente asignado.'
            );
        }
    }

    // =========================================================
    // VALIDAR DATOS DEL LECTOR
    // =========================================================

    if (
        empty($lector['ip']) ||
        empty($lector['puerto'])
    ) {

        throw new Exception(
            'El lector seleccionado no tiene IP o puerto configurado.'
        );
    }

    // =========================================================
    // DATOS PARA EL AGENTE
    // =========================================================

    $datos = [
        'marca'     => 'zkteco',
        'ip'        => $lector['ip'],
        'puerto'    => (int)$lector['puerto'],
        'documento' => $empleado['documento']
    ];

    if ($accion === 'enrolar_huella') {

        $datos['iddedo'] = $idDedo;
    }

    $datosJson = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    if ($datosJson === false) {

        throw new Exception(
            'No se pudieron preparar los datos de la tarea.'
        );
    }

    $idAgenteReal = (int)$lector['idagente'];
    $idLectorReal = (int)$lector['idlector'];

    // =========================================================
    // CREAR TAREA PARA EL AGENTE
    // =========================================================

    $stmt = $mysqli->prepare("
        INSERT INTO tareas_agente (
            idagente,
            idlector,
            idempleado,
            accion,
            datos,
            estado,
            intentos,
            respuesta,
            fecha_creacion
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            'pendiente',
            0,
            NULL,
            NOW()
        )
    ");

    if (!$stmt) {

        throw new Exception(
            'No se pudo preparar la creación de la tarea: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'iiiss',
        $idAgenteReal,
        $idLectorReal,
        $idEmpleado,
        $accion,
        $datosJson
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'No se pudo crear la tarea: ' .
            $error
        );
    }

    $idTarea = (int)$stmt->insert_id;

    $stmt->close();

    // =========================================================
    // RESPUESTA
    // =========================================================

    if ($accion === 'enrolar_huella') {

        $mensaje =
            'Solicitud de registro de huella creada correctamente.';

    } else {

        $mensaje =
            'Tarea de lectura de huellas creada correctamente.';
    }

    echo json_encode([
        'status' => 'ok',
        'msg' => $mensaje,
        'idtarea' => $idTarea,
        'idempleado' => $idEmpleado,
        'iddedo' => $accion === 'enrolar_huella'
            ? $idDedo
            : null,
        'empresa' => [
            'idempresa' => $idEmpresa
        ],
        'lector' => [
            'idlector' => $idLectorReal,
            'nombre' => $lector['nombre'],
            'ip' => $lector['ip'],
            'puerto' => (int)$lector['puerto']
        ],
        'idagente' => $idAgenteReal
    ]);

    exit;

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);

    exit;
}