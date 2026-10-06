<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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


/* ============================================================
   RESPUESTA
============================================================ */

function responderTurnos(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* ============================================================
   AUTENTICACIÓN
============================================================ */

$userAuth = validarTokenAPI($mysqli);

if (!$userAuth) {
    responderTurnos([
        'status' => 'error',
        'msg' => 'No autorizado.'
    ], 401);
}

validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

$mysqli = conectarDBEmpresa(
    $mysqli,
    (int)$empresa['idempresa'],
    'DATOS'
);


/* ============================================================
   ACCIÓN
============================================================ */

$accion = $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($entrada)) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);
    }

    $accion = $entrada['accion'] ?? '';
}


/* ============================================================
   DATOS INICIALES
============================================================ */

if ($accion === 'datos_iniciales') {

    $empleados = [];

    $sql = "
        SELECT
            idempleado,
            documento,
            nombre,
            apellido
        FROM empleados
        WHERE activo = 1
        ORDER BY apellido, nombre
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar empleados.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $empleados[] = $fila;
    }

    $stmt->close();


    $ciclos = [];

    $sql = "
        SELECT
            idciclo,
            nombre,
            cantidad_semanas
        FROM ciclos
        WHERE activo = 1
        ORDER BY nombre
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar ciclos.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $ciclos[] = $fila;
    }

    $stmt->close();


    $horarios = [];

    $sql = "
        SELECT
            idhorario,
            nombre
        FROM horarios
        WHERE activo = 1
        ORDER BY nombre
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar horarios.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $horarios[] = $fila;
    }

    $stmt->close();


    responderTurnos([
        'status' => 'ok',
        'data' => [
            'empleados' => $empleados,
            'ciclos' => $ciclos,
            'horarios' => $horarios
        ]
    ]);
}


/* ============================================================
   LISTAR ASIGNACIONES DE CICLOS
============================================================ */

if ($accion === 'listar_ciclos') {

    $datos = [];

    $sql = "
        SELECT
            ec.idempleado_ciclo,
            ec.idempleado,
            ec.idciclo,
            ec.fecha_desde,
            ec.fecha_hasta,
            CASE
                WHEN ec.fecha_hasta IS NOT NULL
                     AND ec.fecha_hasta < CURDATE()
                THEN 0
                ELSE 1
            END AS activo,
            e.documento,
            e.nombre,
            e.apellido,
            c.nombre AS nombre_ciclo
        FROM empleado_ciclos ec
        INNER JOIN empleados e
            ON e.idempleado = ec.idempleado
        INNER JOIN ciclos c
            ON c.idciclo = ec.idciclo
        ORDER BY
            e.apellido,
            e.nombre,
            ec.fecha_desde DESC
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar las asignaciones de ciclos.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        $fila['activo'] = (int)$fila['activo'];

        $fila['empleado'] =
            $fila['apellido'] . ', ' . $fila['nombre'];

        $fila['documento'] =
            $fila['documento'];

        $fila['ciclo'] =
            $fila['nombre_ciclo'];

        $fila['estado'] =
            $fila['activo'] === 1
                ? '<span class="badge bg-success">Activa</span>'
                : '<span class="badge bg-secondary">Finalizada</span>';

        $fila['fecha_desde'] =
            $fila['fecha_desde']
            ? date('d/m/Y', strtotime($fila['fecha_desde']))
            : '';

        $fila['fecha_hasta'] =
            $fila['fecha_hasta']
            ? date('d/m/Y', strtotime($fila['fecha_hasta']))
            : 'Sin fecha';

        $datos[] = $fila;
    }

    $stmt->close();

    responderTurnos([
        'status' => 'ok',
        'data' => $datos
    ]);
}


/* ============================================================
   OBTENER CICLO
============================================================ */

if ($accion === 'obtener_ciclo') {

    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Asignación inválida.'
        ], 400);
    }

    $sql = "
        SELECT
            idempleado_ciclo,
            idempleado,
            idciclo,
            fecha_desde,
            fecha_hasta
        FROM empleado_ciclos
        WHERE idempleado_ciclo = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar la asignación.'
        ], 500);
    }

    $stmt->bind_param('i', $id);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $fila = $resultado->fetch_assoc();

    $stmt->close();

    if (!$fila) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se encontró la asignación.'
        ], 404);
    }

    responderTurnos([
        'status' => 'ok',
        'data' => $fila
    ]);
}


/* ============================================================
   GUARDAR CICLO
============================================================ */

if ($accion === 'guardar_ciclo') {

    $id = (int)($entrada['idempleado_ciclo'] ?? 0);
    $idempleado = (int)($entrada['idempleado'] ?? 0);
    $idciclo = (int)($entrada['idciclo'] ?? 0);

    $fechaDesde = trim(
        (string)($entrada['fecha_desde'] ?? '')
    );

    $fechaHasta = trim(
        (string)($entrada['fecha_hasta'] ?? '')
    );

    if (
        $idempleado <= 0 ||
        $idciclo <= 0 ||
        $fechaDesde === ''
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Faltan datos obligatorios.'
        ], 400);
    }

    if (
        $fechaHasta !== '' &&
        $fechaHasta < $fechaDesde
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'La fecha hasta no puede ser anterior a la fecha desde.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar empleado
    --------------------------------------------------------- */

    $sql = "
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $idempleado);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $existeEmpleado = $resultado->fetch_assoc();

    $stmt->close();

    if (!$existeEmpleado) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado no existe o está inactivo.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar ciclo
    --------------------------------------------------------- */

    $sql = "
        SELECT idciclo
        FROM ciclos
        WHERE idciclo = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $idciclo);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $existeCiclo = $resultado->fetch_assoc();

    $stmt->close();

    if (!$existeCiclo) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El ciclo no existe o está inactivo.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar superposición
    --------------------------------------------------------- */

    $sql = "
        SELECT idempleado_ciclo
        FROM empleado_ciclos
        WHERE idempleado = ?
          AND idempleado_ciclo <> ?
          AND fecha_desde <= COALESCE(NULLIF(?, ''), '9999-12-31')
          AND COALESCE(fecha_hasta, '9999-12-31') >= ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'iiss',
        $idempleado,
        $id,
        $fechaHasta,
        $fechaDesde
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $solapamiento = $resultado->fetch_assoc();

    $stmt->close();

    if ($solapamiento) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado ya tiene otro ciclo asignado durante ese período.'
        ], 400);
    }


    /* --------------------------------------------------------
       Guardar
    --------------------------------------------------------- */

    if ($id > 0) {

        $sql = "
            UPDATE empleado_ciclos
            SET
                idempleado = ?,
                idciclo = ?,
                fecha_desde = ?,
                fecha_hasta = NULLIF(?, '')
            WHERE idempleado_ciclo = ?
        ";

        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {
            responderTurnos([
                'status' => 'error',
                'msg' => 'Error al preparar la actualización.'
            ], 500);
        }

        $stmt->bind_param(
            'iissi',
            $idempleado,
            $idciclo,
            $fechaDesde,
            $fechaHasta,
            $id
        );

        $ok = $stmt->execute();

        $stmt->close();

        if (!$ok) {
            responderTurnos([
                'status' => 'error',
                'msg' => 'No se pudo actualizar la asignación.'
            ], 500);
        }

        responderTurnos([
            'status' => 'ok',
            'msg' => 'Asignación actualizada correctamente.',
            'idempleado_ciclo' => $id
        ]);
    }


    $sql = "
        INSERT INTO empleado_ciclos (
            idempleado,
            idciclo,
            fecha_desde,
            fecha_hasta
        )
        VALUES (
            ?,
            ?,
            ?,
            NULLIF(?, '')
        )
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al preparar la creación.'
        ], 500);
    }

    $stmt->bind_param(
        'iiss',
        $idempleado,
        $idciclo,
        $fechaDesde,
        $fechaHasta
    );

    $ok = $stmt->execute();

    $nuevoId = $stmt->insert_id;

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo crear la asignación.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => 'Asignación creada correctamente.',
        'idempleado_ciclo' => $nuevoId
    ]);
}


/* ============================================================
   CAMBIAR ESTADO CICLO
============================================================ */

if ($accion === 'cambiar_estado_ciclo') {

    $id = (int)($entrada['idempleado_ciclo'] ?? 0);
    $activo = (int)($entrada['activo'] ?? 0);

    if ($id <= 0 || !in_array($activo, [0, 1], true)) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);
    }


    /*
     * Una asignación no tiene campo activo.
     * Para finalizarla se utiliza fecha_hasta.
     *
     * Al activar nuevamente se elimina la fecha_hasta.
     */

    if ($activo === 0) {

        $sql = "
            UPDATE empleado_ciclos
            SET fecha_hasta = CURDATE()
            WHERE idempleado_ciclo = ?
        ";

    } else {

        $sql = "
            UPDATE empleado_ciclos
            SET fecha_hasta = NULL
            WHERE idempleado_ciclo = ?
        ";
    }

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al preparar la actualización.'
        ], 500);
    }

    $stmt->bind_param('i', $id);

    $ok = $stmt->execute();

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo actualizar la asignación.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => $activo === 1
            ? 'Asignación activada correctamente.'
            : 'Asignación finalizada correctamente.'
    ]);
}


/* ============================================================
   LISTAR HORARIOS ESPECIALES
============================================================ */

if ($accion === 'listar_especiales') {

    $datos = [];

    $sql = "
        SELECT
            eh.idhorario_especial,
            eh.idempleado,
            eh.fecha,
            eh.idhorario,
            e.documento,
            e.nombre,
            e.apellido,
            h.nombre AS nombre_horario
        FROM empleado_horarios_especiales eh
        INNER JOIN empleados e
            ON e.idempleado = eh.idempleado
        INNER JOIN horarios h
            ON h.idhorario = eh.idhorario
        ORDER BY
            eh.fecha DESC,
            e.apellido,
            e.nombre
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar horarios especiales.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        $fila['empleado'] =
            $fila['apellido'] . ', ' . $fila['nombre'];

        $fila['horario'] =
            $fila['nombre_horario'];

        $fila['estado'] =
            '<span class="badge bg-primary">Programado</span>';

        $fila['fecha'] =
            date('d/m/Y', strtotime($fila['fecha']));

        $datos[] = $fila;
    }

    $stmt->close();

    responderTurnos([
        'status' => 'ok',
        'data' => $datos
    ]);
}


/* ============================================================
   OBTENER HORARIO ESPECIAL
============================================================ */

if ($accion === 'obtener_especial') {

    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Horario especial inválido.'
        ], 400);
    }

    $sql = "
        SELECT
            idhorario_especial,
            idempleado,
            fecha,
            idhorario
        FROM empleado_horarios_especiales
        WHERE idhorario_especial = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $id);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $fila = $resultado->fetch_assoc();

    $stmt->close();

    if (!$fila) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se encontró el horario especial.'
        ], 404);
    }

    responderTurnos([
        'status' => 'ok',
        'data' => $fila
    ]);
}


/* ============================================================
   GUARDAR HORARIO ESPECIAL
============================================================ */

if ($accion === 'guardar_especial') {

    $id = (int)($entrada['idhorario_especial'] ?? 0);
    $idempleado = (int)($entrada['idempleado'] ?? 0);
    $fecha = trim((string)($entrada['fecha'] ?? ''));
    $idhorario = (int)($entrada['idhorario'] ?? 0);

    if (
        $idempleado <= 0 ||
        $idhorario <= 0 ||
        $fecha === ''
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Faltan datos obligatorios.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar empleado
    --------------------------------------------------------- */

    $sql = "
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $idempleado);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $empleado = $resultado->fetch_assoc();

    $stmt->close();

    if (!$empleado) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado no existe o está inactivo.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar horario
    --------------------------------------------------------- */

    $sql = "
        SELECT idhorario
        FROM horarios
        WHERE idhorario = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $idhorario);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $horario = $resultado->fetch_assoc();

    $stmt->close();

    if (!$horario) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El horario no existe o está inactivo.'
        ], 400);
    }


    /* --------------------------------------------------------
       Una sola excepción por empleado/fecha
    --------------------------------------------------------- */

    $sql = "
        SELECT idhorario_especial
        FROM empleado_horarios_especiales
        WHERE idempleado = ?
          AND fecha = ?
          AND idhorario_especial <> ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'isi',
        $idempleado,
        $fecha,
        $id
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $duplicado = $resultado->fetch_assoc();

    $stmt->close();

    if ($duplicado) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado ya tiene un horario especial para esa fecha.'
        ], 400);
    }


    /* --------------------------------------------------------
       Actualizar
    --------------------------------------------------------- */

    if ($id > 0) {

        $sql = "
            UPDATE empleado_horarios_especiales
            SET
                idempleado = ?,
                fecha = ?,
                idhorario = ?
            WHERE idhorario_especial = ?
        ";

        $stmt = $mysqli->prepare($sql);

        $stmt->bind_param(
            'isii',
            $idempleado,
            $fecha,
            $idhorario,
            $id
        );

        $ok = $stmt->execute();

        $stmt->close();

        if (!$ok) {
            responderTurnos([
                'status' => 'error',
                'msg' => 'No se pudo actualizar el horario especial.'
            ], 500);
        }

        responderTurnos([
            'status' => 'ok',
            'msg' => 'Horario especial actualizado correctamente.',
            'idhorario_especial' => $id
        ]);
    }


    /* --------------------------------------------------------
       Crear
    --------------------------------------------------------- */

    $idusuario = (int)(
        $userAuth['idusuario'] ??
        $userAuth['id'] ??
        0
    );

    if ($idusuario <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo identificar al usuario.'
        ], 500);
    }

    $sql = "
        INSERT INTO empleado_horarios_especiales (
            idempleado,
            fecha,
            idhorario,
            idusuario
        )
        VALUES (
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'isii',
        $idempleado,
        $fecha,
        $idhorario,
        $idusuario
    );

    $ok = $stmt->execute();

    $nuevoId = $stmt->insert_id;

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo crear el horario especial.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => 'Horario especial creado correctamente.',
        'idhorario_especial' => $nuevoId
    ]);
}


/* ============================================================
   ELIMINAR HORARIO ESPECIAL
============================================================ */

if ($accion === 'eliminar_especial') {

    $id = (int)($entrada['idhorario_especial'] ?? 0);

    if ($id <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Horario especial inválido.'
        ], 400);
    }

    $sql = "
        DELETE FROM empleado_horarios_especiales
        WHERE idhorario_especial = ?
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $id);

    $ok = $stmt->execute();

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo eliminar el horario especial.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => 'Horario especial eliminado correctamente.'
    ]);
}


/* ============================================================
   LISTAR HORAS EXTRA
============================================================ */

if ($accion === 'listar_extra') {

    $datos = [];

    $sql = "
        SELECT
            he.idhorario_extra,
            he.idempleado,
            he.fecha,
            he.hora_desde,
            he.hora_hasta,
            he.observaciones,
            he.activo,
            e.documento,
            e.nombre,
            e.apellido
        FROM empleado_horarios_extra he
        INNER JOIN empleados e
            ON e.idempleado = he.idempleado
        ORDER BY
            he.fecha DESC,
            e.apellido,
            e.nombre,
            he.hora_desde
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Error al consultar horas extra.'
        ], 500);
    }

    $stmt->execute();

    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {

        $fila['activo'] =
            (int)$fila['activo'];

        $fila['empleado'] =
            $fila['apellido'] . ', ' . $fila['nombre'];

        $fila['fecha'] =
            date('d/m/Y', strtotime($fila['fecha']));

        $fila['hora_desde'] =
            substr($fila['hora_desde'], 0, 5);

        $fila['hora_hasta'] =
            substr($fila['hora_hasta'], 0, 5);

        $fila['observaciones'] =
            $fila['observaciones'] ?? '';

        $fila['estado'] =
            $fila['activo'] === 1
                ? '<span class="badge bg-success">Activa</span>'
                : '<span class="badge bg-secondary">Inactiva</span>';

        $datos[] = $fila;
    }

    $stmt->close();

    responderTurnos([
        'status' => 'ok',
        'data' => $datos
    ]);
}


/* ============================================================
   OBTENER HORA EXTRA
============================================================ */

if ($accion === 'obtener_extra') {

    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Hora extra inválida.'
        ], 400);
    }

    $sql = "
        SELECT
            idhorario_extra,
            idempleado,
            fecha,
            hora_desde,
            hora_hasta,
            observaciones,
            activo
        FROM empleado_horarios_extra
        WHERE idhorario_extra = ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $id);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $fila = $resultado->fetch_assoc();

    $stmt->close();

    if (!$fila) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se encontró la hora extra.'
        ], 404);
    }

    responderTurnos([
        'status' => 'ok',
        'data' => $fila
    ]);
}


/* ============================================================
   GUARDAR HORA EXTRA
============================================================ */

if ($accion === 'guardar_extra') {

    $id = (int)($entrada['idhorario_extra'] ?? 0);
    $idempleado = (int)($entrada['idempleado'] ?? 0);

    $fecha =
        trim((string)($entrada['fecha'] ?? ''));

    $horaDesde =
        trim((string)($entrada['hora_desde'] ?? ''));

    $horaHasta =
        trim((string)($entrada['hora_hasta'] ?? ''));

    $observaciones =
        trim((string)($entrada['observaciones'] ?? ''));


    if (
        $idempleado <= 0 ||
        $fecha === '' ||
        $horaDesde === '' ||
        $horaHasta === ''
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Faltan datos obligatorios.'
        ], 400);
    }


    if (
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaDesde) ||
        !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horaHasta)
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Las horas deben tener formato HH:mm.'
        ], 400);
    }


    if ($horaDesde === $horaHasta) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'La hora desde y la hora hasta no pueden ser iguales.'
        ], 400);
    }


    if (mb_strlen($observaciones) > 255) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Las observaciones no pueden superar los 255 caracteres.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar empleado
    --------------------------------------------------------- */

    $sql = "
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param('i', $idempleado);

    $stmt->execute();

    $resultado = $stmt->get_result();

    $empleado = $resultado->fetch_assoc();

    $stmt->close();

    if (!$empleado) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado no existe o está inactivo.'
        ], 400);
    }


    /* --------------------------------------------------------
       Validar solapamiento de horas extra
    --------------------------------------------------------- */

    $sql = "
        SELECT idhorario_extra
        FROM empleado_horarios_extra
        WHERE idempleado = ?
          AND fecha = ?
          AND activo = 1
          AND idhorario_extra <> ?
          AND hora_desde < ?
          AND hora_hasta > ?
        LIMIT 1
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'isiss',
        $idempleado,
        $fecha,
        $id,
        $horaHasta,
        $horaDesde
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $solapamiento = $resultado->fetch_assoc();

    $stmt->close();

    if ($solapamiento) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'El empleado ya tiene otra hora extra programada que se superpone con ese período.'
        ], 400);
    }


    /* --------------------------------------------------------
       Actualizar
    --------------------------------------------------------- */

    if ($id > 0) {

        $sql = "
            UPDATE empleado_horarios_extra
            SET
                idempleado = ?,
                fecha = ?,
                hora_desde = ?,
                hora_hasta = ?,
                observaciones = ?
            WHERE idhorario_extra = ?
        ";

        $stmt = $mysqli->prepare($sql);

        $stmt->bind_param(
            'issssi',
            $idempleado,
            $fecha,
            $horaDesde,
            $horaHasta,
            $observaciones,
            $id
        );

        $ok = $stmt->execute();

        $stmt->close();

        if (!$ok) {
            responderTurnos([
                'status' => 'error',
                'msg' => 'No se pudo actualizar la hora extra.'
            ], 500);
        }

        responderTurnos([
            'status' => 'ok',
            'msg' => 'Hora extra actualizada correctamente.',
            'idhorario_extra' => $id
        ]);
    }


    /* --------------------------------------------------------
       Crear
    --------------------------------------------------------- */

    $idusuario = (int)(
        $userAuth['idusuario'] ??
        $userAuth['id'] ??
        0
    );

    if ($idusuario <= 0) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo identificar al usuario.'
        ], 500);
    }


    $sql = "
        INSERT INTO empleado_horarios_extra (
            idempleado,
            fecha,
            hora_desde,
            hora_hasta,
            observaciones,
            idusuario
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            ?
        )
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'issssi',
        $idempleado,
        $fecha,
        $horaDesde,
        $horaHasta,
        $observaciones,
        $idusuario
    );

    $ok = $stmt->execute();

    $nuevoId = $stmt->insert_id;

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo crear la hora extra.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => 'Hora extra creada correctamente.',
        'idhorario_extra' => $nuevoId
    ]);
}


/* ============================================================
   CAMBIAR ESTADO HORA EXTRA
============================================================ */

if ($accion === 'cambiar_estado_extra') {

    $id = (int)($entrada['idhorario_extra'] ?? 0);
    $activo = (int)($entrada['activo'] ?? 0);

    if (
        $id <= 0 ||
        !in_array($activo, [0, 1], true)
    ) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);
    }

    $sql = "
        UPDATE empleado_horarios_extra
        SET activo = ?
        WHERE idhorario_extra = ?
    ";

    $stmt = $mysqli->prepare($sql);

    $stmt->bind_param(
        'ii',
        $activo,
        $id
    );

    $ok = $stmt->execute();

    $stmt->close();

    if (!$ok) {
        responderTurnos([
            'status' => 'error',
            'msg' => 'No se pudo cambiar el estado.'
        ], 500);
    }

    responderTurnos([
        'status' => 'ok',
        'msg' => $activo === 1
            ? 'Hora extra activada correctamente.'
            : 'Hora extra desactivada correctamente.'
    ]);
}


/* ============================================================
   ACCIÓN DESCONOCIDA
============================================================ */

responderTurnos([
    'status' => 'error',
    'msg' => 'Acción no válida.'
], 400);