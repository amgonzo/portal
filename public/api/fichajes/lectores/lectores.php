<?php

// =========================================================
// CONFIGURACIÓN CENTRAL
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Continuamos si no existe .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');


// =========================================================
// RESPUESTA JSON
// =========================================================

function responder(array $respuesta, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}


// =========================================================
// AUTENTICACIÓN
// =========================================================

try {

    $userAuth = validarTokenAPI($mysqli);

    if (!$userAuth) {
        responder([
            'status' => 'error',
            'message' => 'Sesión no válida.'
        ], 401);
    }

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        responder([
            'status' => 'error',
            'message' => 'No se pudo determinar la empresa activa.'
        ], 400);
    }

    $idEmpresa = (int)$empresa['idempresa'];

} catch (Throwable $e) {

    responder([
        'status' => 'error',
        'message' => 'Error de autenticación.',
        'detalle' => $e->getMessage()
    ], 500);
}


// =========================================================
// ACCIÓN
// =========================================================

$action = $_GET['action'] ?? $_POST['action'] ?? 'listar';


// =========================================================
// AGENTES
//
// Los agentes pertenecen a la BD central.
// Los lectores pertenecen a la BD de la empresa.
//
// Por eso primero obtenemos los agentes desde la conexión
// central y después cambiamos a la BD de la empresa.
// =========================================================

if ($action === 'agentes') {

    try {

        $stmt = $mysqli->prepare("
            SELECT
                idagente,
                agent_id,
                nombre
            FROM agentes
            WHERE idempresa = ?
              AND activo = 1
            ORDER BY nombre ASC
        ");

        if (!$stmt) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la consulta de agentes.'
            ], 500);
        }

        $stmt->bind_param('i', $idEmpresa);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            responder([
                'status' => 'error',
                'message' => 'No se pudieron consultar los Agents.',
                'detalle' => $error
            ], 500);
        }

        $resultado = $stmt->get_result();

        $agentes = [];

        while ($fila = $resultado->fetch_assoc()) {

            $agentes[] = [
                'idagente' => (int)$fila['idagente'],
                'agent_id' => $fila['agent_id'],
                'nombre'   => $fila['nombre']
            ];
        }

        $stmt->close();

        responder([
            'status' => 'ok',
            'data' => $agentes
        ]);

    } catch (Throwable $e) {

        responder([
            'status' => 'error',
            'message' => 'Error al consultar los Agents.',
            'detalle' => $e->getMessage()
        ], 500);
    }
}


// =========================================================
// OBTENER AGENTES PARA MOSTRAR SUS DATOS JUNTO A LOS
// LECTORES.
//
// Guardamos un mapa:
// idagente => datos del agente
// =========================================================

$agentesMapa = [];

try {

    $stmtAgentes = $mysqli->prepare("
        SELECT
            idagente,
            agent_id,
            nombre
        FROM agentes
        WHERE idempresa = ?
    ");

    if ($stmtAgentes) {

        $stmtAgentes->bind_param('i', $idEmpresa);

        if ($stmtAgentes->execute()) {

            $resultadoAgentes = $stmtAgentes->get_result();

            while ($agente = $resultadoAgentes->fetch_assoc()) {

                $idAgente = (int)$agente['idagente'];

                $agentesMapa[$idAgente] = [
                    'idagente' => $idAgente,
                    'agent_id' => $agente['agent_id'],
                    'nombre'   => $agente['nombre']
                ];
            }
        }

        $stmtAgentes->close();
    }

} catch (Throwable $e) {

    // Si no se pueden obtener los agentes, continuamos.
    // Los lectores igualmente pueden ser consultados.
}


// =========================================================
// CONEXIÓN A LA BD DE LA EMPRESA
// =========================================================

try {

    $mysqli = conectarDBEmpresa(
        $mysqli,
        $idEmpresa,
        'DATOS'
    );

} catch (Throwable $e) {

    responder([
        'status' => 'error',
        'message' => 'No se pudo conectar con la base de datos de la empresa.',
        'detalle' => $e->getMessage()
    ], 500);
}


// =========================================================
// LISTAR RELOJES
// =========================================================

if ($action === 'listar') {

    try {

        $sql = "
            SELECT
                idlector,
                idagente,
                nombre,
                marca,
                modelo,
                ip,
                puerto,
                ubicacion,
                tipo_uso,
                predeterminado,
                activo
            FROM lectores
            ORDER BY nombre ASC
        ";

        $resultado = $mysqli->query($sql);

        if (!$resultado) {

            responder([
                'status' => 'error',
                'message' => 'No se pudieron consultar los relojes.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $lectores = [];

        while ($fila = $resultado->fetch_assoc()) {

            $idAgente = $fila['idagente'] !== null
                ? (int)$fila['idagente']
                : null;

            $agenteNombre = null;
            $agentId = null;

            if ($idAgente !== null && isset($agentesMapa[$idAgente])) {

                $agenteNombre = $agentesMapa[$idAgente]['nombre'];
                $agentId = $agentesMapa[$idAgente]['agent_id'];
            }

            $lectores[] = [
                'idlector'       => (int)$fila['idlector'],
                'idagente'       => $idAgente,
                'nombre'         => $fila['nombre'],
                'marca'          => $fila['marca'],
                'modelo'         => $fila['modelo'],
                'ip'             => $fila['ip'],
                'puerto'         => (int)$fila['puerto'],
                'ubicacion'      => $fila['ubicacion'],
                'tipo_uso'       => $fila['tipo_uso'],
                'predeterminado' => (int)$fila['predeterminado'],
                'activo'         => (int)$fila['activo'],
                'agente_nombre'  => $agenteNombre,
                'agent_id'       => $agentId
            ];
        }

        responder([
            'status' => 'ok',
            'data' => $lectores
        ]);

    } catch (Throwable $e) {

        responder([
            'status' => 'error',
            'message' => 'Error al listar los relojes.',
            'detalle' => $e->getMessage()
        ], 500);
    }
}


// =========================================================
// CREAR RELOJ
// =========================================================

if ($action === 'crear') {

    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $puerto = (int)($_POST['puerto'] ?? 0);
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $tipoUso = trim($_POST['tipo_uso'] ?? '');
    $idAgente = $_POST['idagente'] ?? null;
    $predeterminado = !empty($_POST['predeterminado']) ? 1 : 0;


    // -----------------------------------------------------
    // VALIDACIONES
    // -----------------------------------------------------

    if ($nombre === '') {
        responder([
            'status' => 'error',
            'message' => 'El nombre del reloj es obligatorio.'
        ], 400);
    }

    if ($marca === '') {
        responder([
            'status' => 'error',
            'message' => 'La marca del reloj es obligatoria.'
        ], 400);
    }

    if ($ip === '') {
        responder([
            'status' => 'error',
            'message' => 'La dirección IP es obligatoria.'
        ], 400);
    }

    if ($puerto <= 0 || $puerto > 65535) {
        responder([
            'status' => 'error',
            'message' => 'El puerto no es válido.'
        ], 400);
    }


    // -----------------------------------------------------
    // AGENTE
    // -----------------------------------------------------

    if ($idAgente === '' || $idAgente === null) {
        $idAgente = null;
    } else {
        $idAgente = (int)$idAgente;

        if (!isset($agentesMapa[$idAgente])) {
            responder([
                'status' => 'error',
                'message' => 'El Agent seleccionado no pertenece a la empresa activa.'
            ], 400);
        }
    }


    // -----------------------------------------------------
    // SI ES PREDETERMINADO, QUITAMOS EL PREDETERMINADO
    // DE LOS DEMÁS RELOJES
    // -----------------------------------------------------

    if ($predeterminado === 1) {

        $mysqli->query("
            UPDATE lectores
            SET predeterminado = 0
        ");

        if ($mysqli->errno) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo actualizar el reloj predeterminado.',
                'detalle' => $mysqli->error
            ], 500);
        }
    }


    // -----------------------------------------------------
    // INSERTAR
    // -----------------------------------------------------

    if ($idAgente === null) {

        $stmt = $mysqli->prepare("
            INSERT INTO lectores
            (
                idagente,
                nombre,
                marca,
                modelo,
                ip,
                puerto,
                ubicacion,
                tipo_uso,
                predeterminado,
                activo
            )
            VALUES
            (
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                1
            )
        ");

        if (!$stmt) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la creación del reloj.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmt->bind_param(
            'ssssissi',
            $nombre,
            $marca,
            $modelo,
            $ip,
            $puerto,
            $ubicacion,
            $tipoUso,
            $predeterminado
        );

    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO lectores
            (
                idagente,
                nombre,
                marca,
                modelo,
                ip,
                puerto,
                ubicacion,
                tipo_uso,
                predeterminado,
                activo
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                1
            )
        ");

        if (!$stmt) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la creación del reloj.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmt->bind_param(
            'issssissi',
            $idAgente,
            $nombre,
            $marca,
            $modelo,
            $ip,
            $puerto,
            $ubicacion,
            $tipoUso,
            $predeterminado
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        responder([
            'status' => 'error',
            'message' => 'No se pudo crear el reloj.',
            'detalle' => $error
        ], 500);
    }

    $idLector = $stmt->insert_id;

    $stmt->close();

    responder([
        'status' => 'ok',
        'message' => 'Reloj creado correctamente.',
        'idlector' => (int)$idLector
    ]);
}


// =========================================================
// EDITAR RELOJ
// =========================================================

if ($action === 'editar') {

    $idLector = (int)($_POST['idlector'] ?? 0);

    $nombre = trim($_POST['nombre'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $ip = trim($_POST['ip'] ?? '');
    $puerto = (int)($_POST['puerto'] ?? 0);
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $tipoUso = trim($_POST['tipo_uso'] ?? '');
    $idAgente = $_POST['idagente'] ?? null;
    $predeterminado = !empty($_POST['predeterminado']) ? 1 : 0;


    // -----------------------------------------------------
    // VALIDACIONES
    // -----------------------------------------------------

    if ($idLector <= 0) {
        responder([
            'status' => 'error',
            'message' => 'Reloj no válido.'
        ], 400);
    }

    if ($nombre === '') {
        responder([
            'status' => 'error',
            'message' => 'El nombre del reloj es obligatorio.'
        ], 400);
    }

    if ($marca === '') {
        responder([
            'status' => 'error',
            'message' => 'La marca del reloj es obligatoria.'
        ], 400);
    }

    if ($ip === '') {
        responder([
            'status' => 'error',
            'message' => 'La dirección IP es obligatoria.'
        ], 400);
    }

    if ($puerto <= 0 || $puerto > 65535) {
        responder([
            'status' => 'error',
            'message' => 'El puerto no es válido.'
        ], 400);
    }


    // -----------------------------------------------------
    // VERIFICAR QUE EL RELOJ EXISTA
    // -----------------------------------------------------

    $stmtExiste = $mysqli->prepare("
        SELECT idlector
        FROM lectores
        WHERE idlector = ?
        LIMIT 1
    ");

    if (!$stmtExiste) {
        responder([
            'status' => 'error',
            'message' => 'No se pudo verificar el reloj.'
        ], 500);
    }

    $stmtExiste->bind_param('i', $idLector);
    $stmtExiste->execute();

    $resultadoExiste = $stmtExiste->get_result();

    if (!$resultadoExiste->fetch_assoc()) {

        $stmtExiste->close();

        responder([
            'status' => 'error',
            'message' => 'El reloj no existe.'
        ], 404);
    }

    $stmtExiste->close();


    // -----------------------------------------------------
    // AGENTE
    // -----------------------------------------------------

    if ($idAgente === '' || $idAgente === null) {
        $idAgente = null;
    } else {

        $idAgente = (int)$idAgente;

        if (!isset($agentesMapa[$idAgente])) {
            responder([
                'status' => 'error',
                'message' => 'El Agent seleccionado no pertenece a la empresa activa.'
            ], 400);
        }
    }


    // -----------------------------------------------------
    // PREDETERMINADO
    // -----------------------------------------------------

    if ($predeterminado === 1) {

        $mysqli->query("
            UPDATE lectores
            SET predeterminado = 0
            WHERE idlector <> {$idLector}
        ");

        if ($mysqli->errno) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo actualizar el reloj predeterminado.',
                'detalle' => $mysqli->error
            ], 500);
        }
    }


    // -----------------------------------------------------
    // UPDATE
    // -----------------------------------------------------

    if ($idAgente === null) {

        $stmt = $mysqli->prepare("
            UPDATE lectores
            SET
                idagente = NULL,
                nombre = ?,
                marca = ?,
                modelo = ?,
                ip = ?,
                puerto = ?,
                ubicacion = ?,
                tipo_uso = ?,
                predeterminado = ?
            WHERE idlector = ?
        ");

        if (!$stmt) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la actualización del reloj.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmt->bind_param(
            'ssssissii',
            $nombre,
            $marca,
            $modelo,
            $ip,
            $puerto,
            $ubicacion,
            $tipoUso,
            $predeterminado,
            $idLector
        );

    } else {

        $stmt = $mysqli->prepare("
            UPDATE lectores
            SET
                idagente = ?,
                nombre = ?,
                marca = ?,
                modelo = ?,
                ip = ?,
                puerto = ?,
                ubicacion = ?,
                tipo_uso = ?,
                predeterminado = ?
            WHERE idlector = ?
        ");

        if (!$stmt) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la actualización del reloj.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmt->bind_param(
            'issssissii',
            $idAgente,
            $nombre,
            $marca,
            $modelo,
            $ip,
            $puerto,
            $ubicacion,
            $tipoUso,
            $predeterminado,
            $idLector
        );
    }


    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        responder([
            'status' => 'error',
            'message' => 'No se pudo actualizar el reloj.',
            'detalle' => $error
        ], 500);
    }

    $stmt->close();

    responder([
        'status' => 'ok',
        'message' => 'Reloj actualizado correctamente.'
    ]);
}


// =========================================================
// CAMBIAR ESTADO
// =========================================================

if ($action === 'estado') {

    $idLector = (int)($_POST['idlector'] ?? 0);
    $activo = isset($_POST['activo'])
        ? (int)$_POST['activo']
        : -1;


    if ($idLector <= 0) {
        responder([
            'status' => 'error',
            'message' => 'Reloj no válido.'
        ], 400);
    }

    if ($activo !== 0 && $activo !== 1) {
        responder([
            'status' => 'error',
            'message' => 'Estado no válido.'
        ], 400);
    }


    $stmt = $mysqli->prepare("
        UPDATE lectores
        SET activo = ?
        WHERE idlector = ?
    ");

    if (!$stmt) {
        responder([
            'status' => 'error',
            'message' => 'No se pudo preparar el cambio de estado.',
            'detalle' => $mysqli->error
        ], 500);
    }

    $stmt->bind_param(
        'ii',
        $activo,
        $idLector
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        responder([
            'status' => 'error',
            'message' => 'No se pudo cambiar el estado del reloj.',
            'detalle' => $error
        ], 500);
    }

    if ($stmt->affected_rows === 0) {

        $stmt->close();

        responder([
            'status' => 'error',
            'message' => 'El reloj no existe o el estado ya era el indicado.'
        ], 404);
    }

    $stmt->close();

    responder([
        'status' => 'ok',
        'message' => $activo === 1
            ? 'Reloj activado correctamente.'
            : 'Reloj desactivado correctamente.'
    ]);
}

// =========================================================
// SINCRONIZAR EMPLEADOS CON UN RELOJ
//
// Crea una única tarea para el Agent asociado al reloj.
// La tarea contiene todos los empleados activos de la empresa.
// =========================================================

if ($action === 'sincronizar_empleados') {

    $idLector = (int)($_POST['idlector'] ?? 0);

    if ($idLector <= 0) {
        responder([
            'status' => 'error',
            'message' => 'Reloj no válido.'
        ], 400);
    }

    try {

        // -------------------------------------------------
        // OBTENER RELOJ
        // -------------------------------------------------

        $stmtLector = $mysqli->prepare("
            SELECT
                idlector,
                idagente,
                nombre,
                marca,
                modelo,
                ip,
                puerto,
                activo
            FROM lectores
            WHERE idlector = ?
            LIMIT 1
        ");

        if (!$stmtLector) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la consulta del reloj.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmtLector->bind_param(
            'i',
            $idLector
        );

        if (!$stmtLector->execute()) {

            $error = $stmtLector->error;
            $stmtLector->close();

            responder([
                'status' => 'error',
                'message' => 'No se pudo consultar el reloj.',
                'detalle' => $error
            ], 500);
        }

        $resultadoLector = $stmtLector->get_result();
        $lector = $resultadoLector->fetch_assoc();

        $stmtLector->close();

        if (!$lector) {
            responder([
                'status' => 'error',
                'message' => 'El reloj no existe.'
            ], 404);
        }

        // -------------------------------------------------
        // RELOJ ACTIVO
        // -------------------------------------------------

        if ((int)$lector['activo'] !== 1) {
            responder([
                'status' => 'error',
                'message' => 'El reloj está inactivo.'
            ], 400);
        }

        // -------------------------------------------------
        // AGENT
        // -------------------------------------------------

        if (
            $lector['idagente'] === null ||
            (int)$lector['idagente'] <= 0
        ) {
            responder([
                'status' => 'error',
                'message' => 'El reloj no tiene un Agent asignado.'
            ], 400);
        }

        $idAgente = (int)$lector['idagente'];

        if (!isset($agentesMapa[$idAgente])) {
            responder([
                'status' => 'error',
                'message' => 'El Agent asignado al reloj no pertenece a la empresa activa.'
            ], 400);
        }

        // -------------------------------------------------
        // EVITAR DOS SINCRONIZACIONES SIMULTÁNEAS
        // -------------------------------------------------

        $stmtPendiente = $mysqli->prepare("
            SELECT idtarea
            FROM tareas_agente
            WHERE idlector = ?
              AND accion = 'sincronizar_empleados'
              AND estado IN ('pendiente', 'procesando')
            ORDER BY idtarea DESC
            LIMIT 1
        ");

        if (!$stmtPendiente) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo verificar si existe una sincronización en curso.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmtPendiente->bind_param(
            'i',
            $idLector
        );

        if (!$stmtPendiente->execute()) {

            $error = $stmtPendiente->error;
            $stmtPendiente->close();

            responder([
                'status' => 'error',
                'message' => 'No se pudo verificar la sincronización existente.',
                'detalle' => $error
            ], 500);
        }

        $resultadoPendiente = $stmtPendiente->get_result();
        $tareaExistente = $resultadoPendiente->fetch_assoc();

        $stmtPendiente->close();

        if ($tareaExistente) {
            responder([
                'status' => 'error',
                'message' => 'Ya existe una sincronización pendiente o en proceso para este reloj.',
                'idtarea' => (int)$tareaExistente['idtarea']
            ], 400);
        }

        // -------------------------------------------------
        // EMPLEADOS ACTIVOS
        // -------------------------------------------------

        $resultadoEmpleados = $mysqli->query("
            SELECT
                idempleado,
                documento,
                nombre,
                apellido,
                tarjeta
            FROM empleados
            WHERE activo = 1
            ORDER BY apellido ASC, nombre ASC
        ");

        if (!$resultadoEmpleados) {
            responder([
                'status' => 'error',
                'message' => 'No se pudieron consultar los empleados activos.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $empleados = [];

        while ($empleado = $resultadoEmpleados->fetch_assoc()) {

            $empleados[] = [
                'idempleado' => (int)$empleado['idempleado'],
                'documento'  => trim((string)$empleado['documento']),
                'nombre'     => trim((string)$empleado['nombre']),
                'apellido'   => trim((string)$empleado['apellido']),
                'tarjeta'    => $empleado['tarjeta'] !== null
                    ? trim((string)$empleado['tarjeta'])
                    : null
            ];
        }

        // -------------------------------------------------
        // DATOS DE LA TAREA
        // -------------------------------------------------

        $datosTarea = [
            'marca'      => $lector['marca'],
            'modelo'     => $lector['modelo'],
            'ip'         => $lector['ip'],
            'puerto'     => (int)$lector['puerto'],
            'idempleado' => null,
            'empleados'  => $empleados
        ];

        $datosJSON = json_encode(
            $datosTarea,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        if ($datosJSON === false) {
            responder([
                'status' => 'error',
                'message' => 'No se pudieron preparar los datos de sincronización.'
            ], 500);
        }

        // -------------------------------------------------
        // CREAR TAREA
        //
        // idempleado queda NULL porque es una tarea masiva.
        // -------------------------------------------------

        $stmtTarea = $mysqli->prepare("
            INSERT INTO tareas_agente
            (
                idagente,
                idlector,
                idempleado,
                accion,
                prioridad,
                datos,
                estado
            )
            VALUES
            (
                ?,
                ?,
                NULL,
                'sincronizar_empleados',
                20,
                ?,
                'pendiente'
            )
        ");

        if (!$stmtTarea) {
            responder([
                'status' => 'error',
                'message' => 'No se pudo preparar la tarea de sincronización.',
                'detalle' => $mysqli->error
            ], 500);
        }

        $stmtTarea->bind_param(
            'iis',
            $idAgente,
            $idLector,
            $datosJSON
        );

        if (!$stmtTarea->execute()) {

            $error = $stmtTarea->error;
            $stmtTarea->close();

            responder([
                'status' => 'error',
                'message' => 'No se pudo crear la tarea de sincronización.',
                'detalle' => $error
            ], 500);
        }

        $idTarea = $stmtTarea->insert_id;

        $stmtTarea->close();

        responder([
            'status' => 'ok',
            'message' => 'Sincronización de empleados enviada correctamente.',
            'idtarea' => (int)$idTarea,
            'idlector' => $idLector,
            'empleados' => count($empleados)
        ]);

    } catch (Throwable $e) {

        responder([
            'status' => 'error',
            'message' => 'Error al generar la sincronización de empleados.',
            'detalle' => $e->getMessage()
        ], 500);
    }
}

// =========================================================
// ACCIÓN NO RECONOCIDA
// =========================================================

responder([
    'status' => 'error',
    'message' => 'Acción no válida.'
], 400);