<?php
header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 1. RUTAS Y COMPOSER
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

// =========================================================
// 2. CARGAR .ENV
// =========================================================

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

// =========================================================
// 3. CONFIGURACIÓN CENTRAL
// =========================================================

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

// =========================================================
// FUNCIONES AUXILIARES
// =========================================================

function responder($status, $msg, $data = null, $idempleado = null)
{
    $r = [
        'status' => $status,
        'msg' => $msg
    ];

    if ($data !== null) {
        $r['data'] = $data;
    }

    if ($idempleado !== null) {
        $r['idempleado'] = (int)$idempleado;
    }

    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}

function idLaboral($valor)
{
    $id = (int)$valor;
    return $id > 0 ? $id : null;
}

function fechaValida($fecha, $campo)
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);

    if (!$d || $d->format('Y-m-d') !== $fecha) {
        throw new Exception("La fecha de $campo no es válida.");
    }

    return $fecha;
}

// =========================================================
// VALIDAR SECTOR Y PUESTO
// =========================================================

function validarAsignaciones($mysqli, $idSector, $idPuesto)
{
    if ($idSector !== null) {
        $stmt = $mysqli->prepare("
            SELECT idsector
            FROM core_sectores
            WHERE idsector = ?
              AND activo = 1
            LIMIT 1
        ");

        $stmt->bind_param('i', $idSector);
        $stmt->execute();

        $ok = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$ok) {
            throw new Exception('El sector seleccionado no es válido.');
        }
    }

    if ($idPuesto !== null) {
        $stmt = $mysqli->prepare("
            SELECT idpuesto
            FROM core_puestos
            WHERE idpuesto = ?
              AND activo = 1
            LIMIT 1
        ");

        $stmt->bind_param('i', $idPuesto);
        $stmt->execute();

        $ok = $stmt->get_result()->num_rows > 0;

        $stmt->close();

        if (!$ok) {
            throw new Exception('El puesto seleccionado no es válido.');
        }
    }
}

// =========================================================
// OBTENER EMPLEADO
// =========================================================

function obtenerEmpleado($mysqli, $idEmpleado)
{
    $stmt = $mysqli->prepare("
        SELECT
            e.idempleado,
            e.documento,
            e.nombre,
            e.apellido,
            e.fecha_inicio,
            e.activo,
            e.fecha_baja,
            e.motivo_baja,

            l.idlegajo,
            l.numero_legajo,
            l.tipo_contrato,
            l.categoria,
            l.observaciones AS observaciones_legajo,

            h.idhistorial,
            h.idsector,
            h.idpuesto,
            h.fecha_desde,
            h.fecha_hasta,

            s.nombre AS sector,
            p.nombre AS puesto

        FROM empleados e

        LEFT JOIN rrhh_legajos l
            ON l.idempleado = e.idempleado

        LEFT JOIN rrhh_historial_laboral h
            ON h.idempleado = e.idempleado
           AND h.fecha_hasta IS NULL

        LEFT JOIN core_sectores s
            ON s.idsector = h.idsector

        LEFT JOIN core_puestos p
            ON p.idpuesto = h.idpuesto

        WHERE e.idempleado = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $idEmpleado);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    return $fila;
}

// =========================================================
// GUARDAR HISTORIAL LABORAL
// =========================================================

function guardarHistorial(
    $mysqli,
    $idEmpleado,
    $idSector,
    $idPuesto,
    $fechaDesde
) {
    $stmt = $mysqli->prepare("
        SELECT
            idhistorial,
            idsector,
            idpuesto,
            fecha_desde
        FROM rrhh_historial_laboral
        WHERE idempleado = ?
          AND fecha_hasta IS NULL
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $idEmpleado);
    $stmt->execute();

    $actual = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($actual) {
        $sectorActual = $actual['idsector'] !== null
            ? (int)$actual['idsector']
            : null;

        $puestoActual = $actual['idpuesto'] !== null
            ? (int)$actual['idpuesto']
            : null;

        // No hubo cambios de sector ni puesto.
        if (
            $sectorActual === $idSector &&
            $puestoActual === $idPuesto
        ) {
            return;
        }

        if ($fechaDesde <= $actual['fecha_desde']) {
            throw new Exception(
                'La fecha del cambio debe ser posterior al inicio de la asignación vigente.'
            );
        }

        // Cerrar asignación anterior.
        $fechaHasta = (new DateTime($fechaDesde))
            ->modify('-1 day')
            ->format('Y-m-d');

        $idHistorial = (int)$actual['idhistorial'];

        $stmt = $mysqli->prepare("
            UPDATE rrhh_historial_laboral
            SET fecha_hasta = ?
            WHERE idhistorial = ?
              AND fecha_hasta IS NULL
        ");

        $stmt->bind_param(
            'si',
            $fechaHasta,
            $idHistorial
        );

        $stmt->execute();
        $stmt->close();
    }

    // Si ambos valores son NULL, solamente se cierra la asignación anterior.
    if ($idSector === null && $idPuesto === null) {
        return;
    }

    // Crear nueva asignación.
    $stmt = $mysqli->prepare("
        INSERT INTO rrhh_historial_laboral
            (
                idempleado,
                idsector,
                idpuesto,
                fecha_desde
            )
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'iiis',
        $idEmpleado,
        $idSector,
        $idPuesto,
        $fechaDesde
    );

    $stmt->execute();
    $stmt->close();
}

function guardarHistorialContractual(
    $mysqli,
    $idEmpleado,
    $tipoContrato,
    $categoria,
    $observaciones,
    $fechaDesde
) {
    $stmt = $mysqli->prepare("
        SELECT
            idhistorial,
            tipo_contrato,
            categoria,
            observaciones,
            fecha_desde
        FROM rrhh_historial_contractual
        WHERE idempleado = ?
          AND fecha_hasta IS NULL
        ORDER BY fecha_desde DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $idEmpleado);
    $stmt->execute();

    $actual = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($actual) {

        if (
            $actual['tipo_contrato'] === $tipoContrato &&
            $actual['categoria'] === $categoria &&
            $actual['observaciones'] === $observaciones
        ) {
            return;
        }

        if ($fechaDesde <= $actual['fecha_desde']) {
            throw new Exception(
                'La fecha del cambio contractual debe ser posterior al inicio del contrato vigente.'
            );
        }

        $fechaHasta = (new DateTime($fechaDesde))
            ->modify('-1 day')
            ->format('Y-m-d');

        $idHistorial = (int)$actual['idhistorial'];

        $stmt = $mysqli->prepare("
            UPDATE rrhh_historial_contractual
            SET fecha_hasta = ?
            WHERE idhistorial = ?
        ");

        $stmt->bind_param('si', $fechaHasta, $idHistorial);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $mysqli->prepare("
        INSERT INTO rrhh_historial_contractual (
            idempleado,
            tipo_contrato,
            categoria,
            observaciones,
            fecha_desde
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'issss',
        $idEmpleado,
        $tipoContrato,
        $categoria,
        $observaciones,
        $fechaDesde
    );

    $stmt->execute();
    $stmt->close();

}

// =========================================================
// EJECUCIÓN PRINCIPAL
// =========================================================

$transaccion = false;

try {

    // -----------------------------------------------------
    // AUTENTICACIÓN Y PERMISOS
    // -----------------------------------------------------

    $userAuth = validarTokenAPI($mysqli ?? null);

    validarPermisoEndpoint($mysqli, $userAuth);

    $idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

    // -----------------------------------------------------
    // EMPRESA ACTIVA
    // -----------------------------------------------------

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        throw new Exception('No se pudo determinar la empresa activa.');
    }

    // -----------------------------------------------------
    // CONEXIÓN A BASE DATOS DE LA EMPRESA
    // -----------------------------------------------------

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // -----------------------------------------------------
    // DETERMINAR ACCIÓN
    // -----------------------------------------------------

    $action = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_GET['action'] ?? $_POST['action'] ?? '')
        : ($_GET['action'] ?? '');

    // =====================================================
    // DATOS INICIALES
    // =====================================================

    if ($action === 'datos_iniciales') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responder('error', 'Método no permitido.');
        }

        $sectores = [];

        $res = $mysqli->query("
            SELECT idsector, nombre
            FROM core_sectores
            WHERE activo = 1
            ORDER BY nombre
        ");

        while ($fila = $res->fetch_assoc()) {
            $sectores[] = $fila;
        }

        $puestos = [];

        $res = $mysqli->query("
            SELECT idpuesto, nombre
            FROM core_puestos
            WHERE activo = 1
            ORDER BY nombre
        ");

        while ($fila = $res->fetch_assoc()) {
            $puestos[] = $fila;
        }

        responder('ok', 'Datos iniciales obtenidos.', [
            'sectores' => $sectores,
            'puestos' => $puestos
        ]);
    }

    // =====================================================
    // LISTAR EMPLEADOS
    // =====================================================

    if ($action === 'listar') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responder('error', 'Método no permitido.');
        }

        $sql = "
            SELECT
                e.idempleado,
                e.documento,
                e.nombre,
                e.apellido,
                e.fecha_inicio,
                e.activo,
                e.fecha_baja,
                e.motivo_baja,

                l.idlegajo,
                l.numero_legajo,
                l.tipo_contrato,
                l.categoria,
                l.observaciones,

                h.idhistorial,
                h.idsector,
                h.idpuesto,
                h.fecha_desde,

                s.nombre AS sector,
                p.nombre AS puesto,

                hc.fecha_desde AS fecha_desde_contrato

            FROM empleados e

            LEFT JOIN rrhh_legajos l
                ON l.idempleado = e.idempleado

            LEFT JOIN rrhh_historial_laboral h
                ON h.idempleado = e.idempleado
               AND h.fecha_hasta IS NULL

            LEFT JOIN core_sectores s
                ON s.idsector = h.idsector

            LEFT JOIN core_puestos p
                ON p.idpuesto = h.idpuesto

            LEFT JOIN rrhh_historial_contractual hc
                    ON hc.idempleado = e.idempleado
                AND hc.fecha_hasta IS NULL
                
            ORDER BY e.apellido, e.nombre
        ";

        $res = $mysqli->query($sql);

        $empleados = [];

        while ($fila = $res->fetch_assoc()) {
            $fila['idempleado'] = (int)$fila['idempleado'];
            $fila['activo'] = (int)$fila['activo'];

            $empleados[] = $fila;
        }

        responder('ok', 'Empleados obtenidos.', $empleados);
    }

    // =====================================================
    // HISTORIAL LABORAL
    // =====================================================

    if ($action === 'historial') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responder('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_GET['idempleado'] ?? 0);

        if ($idEmpleado <= 0) {
            responder('error', 'Empleado inválido.');
        }

        $stmt = $mysqli->prepare("
            SELECT
                h.idhistorial,
                h.fecha_desde,
                h.fecha_hasta,
                h.idsector,
                h.idpuesto,
                s.nombre AS sector,
                p.nombre AS puesto

            FROM rrhh_historial_laboral h

            LEFT JOIN core_sectores s
                ON s.idsector = h.idsector

            LEFT JOIN core_puestos p
                ON p.idpuesto = h.idpuesto

            WHERE h.idempleado = ?

            ORDER BY
                h.fecha_desde DESC,
                h.idhistorial DESC
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $historial = [];

        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $historial[] = $fila;
        }

        $stmt->close();

        responder('ok', 'Historial obtenido.', $historial);
    }

    if ($action === 'historial_contractual') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responder('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_GET['idempleado'] ?? 0);

        if ($idEmpleado <= 0) {
            responder('error', 'Empleado inválido.');
        }

        $stmt = $mysqli->prepare("
            SELECT
                idhistorial,
                tipo_contrato,
                categoria,
                fecha_desde,
                fecha_hasta,
                observaciones
            FROM rrhh_historial_contractual
            WHERE idempleado = ?
            ORDER BY fecha_desde DESC, idhistorial DESC
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $stmt->close();

        responder('ok', 'Historial contractual obtenido.', $historial);
    }
    
    // =====================================================
    // CREAR EMPLEADO
    // =====================================================

    if ($action === 'crear') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder('error', 'Método no permitido.');
        }

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');

        if (
            $documento === '' ||
            $nombre === '' ||
            $apellido === ''
        ) {
            responder(
                'error',
                'Documento, nombre y apellido son obligatorios.'
            );
        }

        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');

        $fechaInicio = $fechaInicio !== ''
            ? fechaValida($fechaInicio, 'inicio')
            : null;

        $fechaDesdeContrato = trim($_POST['fecha_desde_contrato'] ?? '');

        if ($fechaDesdeContrato === '') {
            $fechaDesdeContrato = $fechaInicio ?: date('Y-m-d');
        }

        $fechaDesdeContrato = fechaValida(
            $fechaDesdeContrato,
            'inicio contractual'
        );

        $numeroLegajo = trim($_POST['numero_legajo'] ?? '');
        $numeroLegajo = $numeroLegajo !== ''
            ? $numeroLegajo
            : null;

        $tipoContrato = trim($_POST['tipo_contrato'] ?? '');
        $tipoContrato = $tipoContrato !== ''
            ? $tipoContrato
            : null;

        $categoria = trim($_POST['categoria'] ?? '');
        $categoria = $categoria !== ''
            ? $categoria
            : null;

        $observaciones = trim($_POST['observaciones'] ?? '');
        $observaciones = $observaciones !== ''
            ? $observaciones
            : null;

        $idSector = idLaboral($_POST['idsector'] ?? null);
        $idPuesto = idLaboral($_POST['idpuesto'] ?? null);

        $fechaDesde = trim($_POST['fecha_desde'] ?? '');

        if ($fechaDesde === '') {
            $fechaDesde = $fechaInicio ?: date('Y-m-d');
        }

        $fechaDesde = fechaValida($fechaDesde, 'asignación');

        validarAsignaciones(
            $mysqli,
            $idSector,
            $idPuesto
        );

        $mysqli->begin_transaction();
        $transaccion = true;

        // Crear empleado.
        $stmt = $mysqli->prepare("
            INSERT INTO empleados
                (
                    documento,
                    nombre,
                    apellido,
                    fecha_inicio,
                    activo
                )
            VALUES (?, ?, ?, ?, 1)
        ");

        $stmt->bind_param(
            'ssss',
            $documento,
            $nombre,
            $apellido,
            $fechaInicio
        );

        $stmt->execute();

        $idEmpleado = (int)$mysqli->insert_id;

        $stmt->close();

        // Crear legajo.
        $stmt = $mysqli->prepare("
            INSERT INTO rrhh_legajos
                (
                    idempleado,
                    numero_legajo,
                    tipo_contrato,
                    categoria,
                    observaciones
                )
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            'issss',
            $idEmpleado,
            $numeroLegajo,
            $tipoContrato,
            $categoria,
            $observaciones
        );

        $stmt->execute();

        $idLegajo = (int)$mysqli->insert_id;

        $stmt->close();

        guardarHistorialContractual(
            $mysqli,
            $idEmpleado,
            $tipoContrato,
            $categoria,
            $observaciones,
            $fechaDesdeContrato
        );

        // Guardar asignación laboral.
        guardarHistorial(
            $mysqli,
            $idEmpleado,
            $idSector,
            $idPuesto,
            $fechaDesde
        );

        $mysqli->commit();
        $transaccion = false;

        $nuevo = obtenerEmpleado($mysqli, $idEmpleado);

        @registrarLog(
            $mysqli,
            'crear_empleado',
            'empleados',
            $idEmpleado,
            $idUsuarioLog,
            null,
            $nuevo
        );

        @registrarLog(
            $mysqli,
            'crear_legajo',
            'rrhh_legajos',
            $idLegajo,
            $idUsuarioLog,
            null,
            $nuevo
        );

        responder(
            'ok',
            'Empleado creado correctamente.',
            null,
            $idEmpleado
        );
    }

    // =====================================================
    // EDITAR EMPLEADO
    // =====================================================

    if ($action === 'editar') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_POST['idempleado'] ?? 0);

        if ($idEmpleado <= 0) {
            responder('error', 'Empleado inválido.');
        }

        $documento = trim($_POST['documento'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');

        if (
            $documento === '' ||
            $nombre === '' ||
            $apellido === ''
        ) {
            responder(
                'error',
                'Documento, nombre y apellido son obligatorios.'
            );
        }

        $fechaInicio = trim($_POST['fecha_inicio'] ?? '');

        $fechaInicio = $fechaInicio !== ''
            ? fechaValida($fechaInicio, 'inicio')
            : null;

        $fechaDesdeContrato = trim($_POST['fecha_desde_contrato'] ?? '');

        if ($fechaDesdeContrato === '') {
            $fechaDesdeContrato = date('Y-m-d');
        }

        $fechaDesdeContrato = fechaValida(
            $fechaDesdeContrato,
            'inicio contractual'
        );

        $numeroLegajo = trim($_POST['numero_legajo'] ?? '');
        $numeroLegajo = $numeroLegajo !== ''
            ? $numeroLegajo
            : null;

        $tipoContrato = trim($_POST['tipo_contrato'] ?? '');
        $tipoContrato = $tipoContrato !== ''
            ? $tipoContrato
            : null;

        $categoria = trim($_POST['categoria'] ?? '');
        $categoria = $categoria !== ''
            ? $categoria
            : null;

        $observaciones = trim($_POST['observaciones'] ?? '');
        $observaciones = $observaciones !== ''
            ? $observaciones
            : null;

        $idSector = idLaboral($_POST['idsector'] ?? null);
        $idPuesto = idLaboral($_POST['idpuesto'] ?? null);

        $fechaDesde = trim($_POST['fecha_desde'] ?? '');

        $fechaDesde = $fechaDesde !== ''
            ? fechaValida($fechaDesde, 'asignación')
            : date('Y-m-d');

        validarAsignaciones(
            $mysqli,
            $idSector,
            $idPuesto
        );

        $anterior = obtenerEmpleado($mysqli, $idEmpleado);

        if (!$anterior) {
            responder('error', 'Empleado no encontrado.');
        }

        $mysqli->begin_transaction();
        $transaccion = true;

        // Actualizar datos del empleado.
        $stmt = $mysqli->prepare("
            UPDATE empleados
            SET
                documento = ?,
                nombre = ?,
                apellido = ?,
                fecha_inicio = ?
            WHERE idempleado = ?
        ");

        $stmt->bind_param(
            'ssssi',
            $documento,
            $nombre,
            $apellido,
            $fechaInicio,
            $idEmpleado
        );

        $stmt->execute();
        $stmt->close();

        // Buscar legajo existente.
        $stmt = $mysqli->prepare("
            SELECT idlegajo
            FROM rrhh_legajos
            WHERE idempleado = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $legajo = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($legajo) {

            $idLegajo = (int)$legajo['idlegajo'];

            // Actualizar legajo.
            $stmt = $mysqli->prepare("
                UPDATE rrhh_legajos
                SET
                    numero_legajo = ?,
                    tipo_contrato = ?,
                    categoria = ?,
                    observaciones = ?
                WHERE idlegajo = ?
            ");

            $stmt->bind_param(
                'ssssi',
                $numeroLegajo,
                $tipoContrato,
                $categoria,
                $observaciones,
                $idLegajo
            );

            $stmt->execute();
            $stmt->close();

        } else {

            // Crear legajo si todavía no existe.
            $stmt = $mysqli->prepare("
                INSERT INTO rrhh_legajos
                    (
                        idempleado,
                        numero_legajo,
                        tipo_contrato,
                        categoria,
                        observaciones
                    )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'issss',
                $idEmpleado,
                $numeroLegajo,
                $tipoContrato,
                $categoria,
                $observaciones
            );

            $stmt->execute();

            $idLegajo = (int)$mysqli->insert_id;

            $stmt->close();
        }

        guardarHistorialContractual(
            $mysqli,
            $idEmpleado,
            $tipoContrato,
            $categoria,
            $observaciones,
            $fechaDesdeContrato
        );

        // Actualizar historial laboral.
        guardarHistorial(
            $mysqli,
            $idEmpleado,
            $idSector,
            $idPuesto,
            $fechaDesde
        );

        $mysqli->commit();
        $transaccion = false;

        $nuevo = obtenerEmpleado($mysqli, $idEmpleado);

        @registrarLog(
            $mysqli,
            'editar_empleado',
            'empleados',
            $idEmpleado,
            $idUsuarioLog,
            $anterior,
            $nuevo
        );

        @registrarLog(
            $mysqli,
            'editar_legajo',
            'rrhh_legajos',
            $idLegajo,
            $idUsuarioLog,
            $anterior,
            $nuevo
        );

        responder('ok', 'Empleado actualizado correctamente.');
    }

    // =====================================================
    // CAMBIAR ESTADO
    // =====================================================

    if ($action === 'estado') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responder('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_POST['idempleado'] ?? 0);
        $activo = (int)($_POST['activo'] ?? -1);

        $fechaBaja = trim($_POST['fecha_baja'] ?? '');
        $motivoBaja = trim($_POST['motivo_baja'] ?? '');

        if (
            $idEmpleado <= 0 ||
            !in_array($activo, [0, 1], true)
        ) {
            responder('error', 'Datos de estado inválidos.');
        }

        if ($activo === 0) {
            if ($fechaBaja === '' || $motivoBaja === '') {
                responder('error', 'La fecha y el motivo de baja son obligatorios.');
            }

            $fechaBaja = fechaValida($fechaBaja, 'baja');
        } else {
            $fechaBaja = null;
            $motivoBaja = null;
        }

        $stmt = $mysqli->prepare("
            SELECT *
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $anterior = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (!$anterior) {
            responder('error', 'Empleado no encontrado.');
        }

        $mysqli->begin_transaction();
        $transaccion = true;

        $stmt = $mysqli->prepare("
            UPDATE empleados
            SET
                activo = ?,
                fecha_baja = ?,
                motivo_baja = ?
            WHERE idempleado = ?
        ");

        $stmt->bind_param(
            'issi',
            $activo,
            $fechaBaja,
            $motivoBaja,
            $idEmpleado
        );

        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        $transaccion = false;

        @registrarLog(
            $mysqli,
            $activo ? 'alta_empleado' : 'baja_empleado',
            'empleados',
            $idEmpleado,
            $idUsuarioLog,
            $anterior,
            [
                'activo' => $activo,
                'fecha_baja' => $fechaBaja,
                'motivo_baja' => $motivoBaja
            ]
        );

        responder(
            'ok',
            $activo
                ? 'Empleado dado de alta.'
                : 'Empleado dado de baja.'
        );
    }

    responder('error', 'Acción no válida.');

} catch (Throwable $e) {

    if (
        $transaccion &&
        isset($mysqli) &&
        $mysqli instanceof mysqli
    ) {
        @$mysqli->rollback();
    }

    error_log('API RRHH empleados: ' . $e->getMessage());

    if (
        $e instanceof mysqli_sql_exception &&
        (int)$e->getCode() === 1062
    ) {
        responder(
            'error',
            'El documento o número de legajo ya está registrado.'
        );
    }

    responder(
        'error',
        $e instanceof Exception
            ? $e->getMessage()
            : 'Error al procesar la operación.'
    );
}

