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
//
// IMPORTANTE:
// La empresa se determina mediante obtenerEmpresaActual()
// utilizando el contexto/header X-EMPRESA-ID.
//
// NO usamos $_SESSION['idempresa'].
//
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
        'leer_huellas',
        'enrolar_huella'
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
         * Además verificamos que ese lector
         * esté asignado al empleado.
         */

        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.ip,
                l.puerto,
                l.ubicacion,
                l.tipo_uso
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
                'Error al preparar consulta de lector: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'ii',
            $idLector,
            $idEmpleado
        );

    } else {

        /*
         * Para leer huellas mantenemos el comportamiento
         * existente: utilizar el lector predeterminado.
         */

        $stmt = $mysqli->prepare("
            SELECT
                l.idlector,
                l.nombre,
                l.ip,
                l.puerto,
                l.ubicacion,
                l.tipo_uso
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
    }

    // =========================================================
    // EJECUTAR CONSULTA DEL LECTOR
    // =========================================================

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new Exception(
            'Error al consultar lector: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $lector = $resultado->fetch_assoc();

    $stmt->close();

    if (!$lector) {

        if ($accion === 'enrolar_huella') {

            throw new Exception(
                'El lector seleccionado no está activo o no está asignado al empleado.'
            );

        } else {

            throw new Exception(
                'El empleado no tiene un lector activo asignado.'
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
        'marca' => 'zkteco',
        'ip' => $lector['ip'],
        'puerto' => (int)$lector['puerto']
    ];

    // Para enrolamiento enviamos el dedo seleccionado.

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

    // =========================================================
    // CREAR TAREA PARA EL AGENTE
    // =========================================================

    $stmt = $mysqli->prepare("
        INSERT INTO tareas_agente (
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
            'pendiente',
            0,
            NULL,
            NOW()
        )
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar creación de tarea: ' .
            $mysqli->error
        );
    }

    $idLectorReal = (int)$lector['idlector'];

    $stmt->bind_param(
        'iiss',
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
        ]
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