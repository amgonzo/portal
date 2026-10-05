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


/* =========================================================
   AUTENTICACIÓN Y EMPRESA
========================================================= */

try {

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

} catch (Throwable $e) {

    http_response_code(401);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);

    exit;
}


/* =========================================================
   MÉTODO
========================================================= */

$metodo = $_SERVER['REQUEST_METHOD'];


/* =========================================================
   GET
========================================================= */

if ($metodo === 'GET') {

    /* -----------------------------------------------------
       LISTAR CICLOS ACTIVOS
    ----------------------------------------------------- */

    if (
        isset($_GET['accion']) &&
        $_GET['accion'] === 'ciclos'
    ) {

        $stmt = $mysqli->prepare("
            SELECT
                idciclo,
                nombre,
                cantidad_semanas
            FROM ciclos
            WHERE activo = 1
            ORDER BY nombre ASC
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->execute();

        $resultado = $stmt->get_result();

        $datos = [];

        while ($fila = $resultado->fetch_assoc()) {

            $fila['idciclo'] =
                (int)$fila['idciclo'];

            $fila['cantidad_semanas'] =
                (int)$fila['cantidad_semanas'];

            $datos[] = $fila;
        }

        $stmt->close();

        echo json_encode([
            'status' => 'ok',
            'data' => $datos
        ]);

        exit;
    }


    /* -----------------------------------------------------
       LISTAR ASIGNACIONES DE UN EMPLEADO
    ----------------------------------------------------- */

    if (
        isset($_GET['idempleado']) &&
        $_GET['idempleado'] !== ''
    ) {

        $idempleado = (int)$_GET['idempleado'];

        if ($idempleado <= 0) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Empleado inválido'
            ]);

            exit;
        }


        $stmt = $mysqli->prepare("
            SELECT
                ec.idempleado_ciclo,
                ec.idempleado,
                ec.idciclo,
                c.nombre AS ciclo_nombre,
                c.cantidad_semanas,
                ec.fecha_desde,
                ec.fecha_hasta
            FROM empleado_ciclos ec
            INNER JOIN ciclos c
                ON c.idciclo = ec.idciclo
            WHERE ec.idempleado = ?
            ORDER BY
                ec.fecha_desde DESC,
                ec.idempleado_ciclo DESC
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'i',
            $idempleado
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $datos = [];

        while ($fila = $resultado->fetch_assoc()) {

            $fila['idempleado_ciclo'] =
                (int)$fila['idempleado_ciclo'];

            $fila['idempleado'] =
                (int)$fila['idempleado'];

            $fila['idciclo'] =
                (int)$fila['idciclo'];

            $fila['cantidad_semanas'] =
                (int)$fila['cantidad_semanas'];

            $datos[] = $fila;
        }

        $stmt->close();


        echo json_encode([
            'status' => 'ok',
            'data' => $datos
        ]);

        exit;
    }


    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => 'Falta indicar la operación'
    ]);

    exit;
}


/* =========================================================
   POST
========================================================= */

if ($metodo === 'POST') {

    $contenido = file_get_contents('php://input');

    $datos = json_decode(
        $contenido,
        true
    );

    if (!is_array($datos)) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Datos inválidos'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       GUARDAR ASIGNACIÓN
    ----------------------------------------------------- */

    if (
        !isset($datos['accion']) ||
        $datos['accion'] !== 'guardar'
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Acción inválida'
        ]);

        exit;
    }


    $idempleado = isset($datos['idempleado'])
        ? (int)$datos['idempleado']
        : 0;

    $idciclo = isset($datos['idciclo'])
        ? (int)$datos['idciclo']
        : 0;

    $fechaDesde = isset($datos['fecha_desde'])
        ? trim($datos['fecha_desde'])
        : '';

    $fechaHasta = isset($datos['fecha_hasta']) &&
                  $datos['fecha_hasta'] !== ''
        ? trim($datos['fecha_hasta'])
        : null;

    $idempleadoCiclo =
        isset($datos['idempleado_ciclo']) &&
        $datos['idempleado_ciclo'] !== ''
            ? (int)$datos['idempleado_ciclo']
            : 0;


    if ($idempleado <= 0) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Empleado inválido'
        ]);

        exit;
    }


    if ($idciclo <= 0) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Debe seleccionar un ciclo'
        ]);

        exit;
    }


    if ($fechaDesde === '') {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Debe indicar la fecha desde'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       VALIDAR FECHAS
    ----------------------------------------------------- */

    $fechaDesdeObj = DateTime::createFromFormat(
        'Y-m-d',
        $fechaDesde
    );

    if (
        !$fechaDesdeObj ||
        $fechaDesdeObj->format('Y-m-d') !== $fechaDesde
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'La fecha desde no es válida'
        ]);

        exit;
    }


    if ($fechaHasta !== null) {

        $fechaHastaObj = DateTime::createFromFormat(
            'Y-m-d',
            $fechaHasta
        );

        if (
            !$fechaHastaObj ||
            $fechaHastaObj->format('Y-m-d') !== $fechaHasta
        ) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'La fecha hasta no es válida'
            ]);

            exit;
        }


        if ($fechaHasta < $fechaDesde) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'La fecha hasta no puede ser anterior a la fecha desde'
            ]);

            exit;
        }
    }


    /* -----------------------------------------------------
       VALIDAR EMPLEADO
    ----------------------------------------------------- */

    $stmt = $mysqli->prepare("
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param(
        'i',
        $idempleado
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $empleado = $resultado->fetch_assoc();

    $stmt->close();


    if (!$empleado) {

        http_response_code(404);

        echo json_encode([
            'status' => 'error',
            'message' => 'Empleado no encontrado'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       VALIDAR CICLO
    ----------------------------------------------------- */

    $stmt = $mysqli->prepare("
        SELECT
            idciclo,
            nombre
        FROM ciclos
        WHERE idciclo = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    $stmt->bind_param(
        'i',
        $idciclo
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $ciclo = $resultado->fetch_assoc();

    $stmt->close();


    if (!$ciclo) {

        http_response_code(404);

        echo json_encode([
            'status' => 'error',
            'message' => 'El ciclo seleccionado no existe o está inactivo'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       VALIDAR SUPERPOSICIÓN
    ----------------------------------------------------- */

    if ($fechaHasta === null) {

        $stmt = $mysqli->prepare("
            SELECT
                idempleado_ciclo
            FROM empleado_ciclos
            WHERE idempleado = ?
              AND idempleado_ciclo <> ?
              AND (
                    fecha_hasta IS NULL
                    OR fecha_hasta >= ?
              )
              AND fecha_desde <= ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'iiss',
            $idempleado,
            $idempleadoCiclo,
            $fechaDesde,
            $fechaDesde
        );

    } else {

        $stmt = $mysqli->prepare("
            SELECT
                idempleado_ciclo
            FROM empleado_ciclos
            WHERE idempleado = ?
              AND idempleado_ciclo <> ?
              AND fecha_desde <= ?
              AND (
                    fecha_hasta IS NULL
                    OR fecha_hasta >= ?
              )
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'iiss',
            $idempleado,
            $idempleadoCiclo,
            $fechaHasta,
            $fechaDesde
        );
    }


    $stmt->execute();

    $resultado = $stmt->get_result();

    $superposicion = $resultado->fetch_assoc();

    $stmt->close();


    if ($superposicion) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Las fechas indicadas se superponen con otra asignación de ciclo del empleado'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       INSERTAR / ACTUALIZAR
    ----------------------------------------------------- */

    if ($idempleadoCiclo > 0) {

        $stmt = $mysqli->prepare("
            UPDATE empleado_ciclos
            SET
                idciclo = ?,
                fecha_desde = ?,
                fecha_hasta = ?
            WHERE idempleado_ciclo = ?
              AND idempleado = ?
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'issii',
            $idciclo,
            $fechaDesde,
            $fechaHasta,
            $idempleadoCiclo,
            $idempleado
        );

        $stmt->execute();

        $stmt->close();

        $mensaje = 'Asignación de ciclo actualizada correctamente';

    } else {

        $stmt = $mysqli->prepare("
            INSERT INTO empleado_ciclos
            (
                idempleado,
                idciclo,
                fecha_desde,
                fecha_hasta
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'iiss',
            $idempleado,
            $idciclo,
            $fechaDesde,
            $fechaHasta
        );

        $stmt->execute();

        $idempleadoCiclo =
            (int)$mysqli->insert_id;

        $stmt->close();

        $mensaje = 'Ciclo asignado correctamente';
    }


    echo json_encode([
        'status' => 'ok',
        'message' => $mensaje,
        'idempleado_ciclo' => $idempleadoCiclo
    ]);

    exit;
}


/* =========================================================
   MÉTODO NO PERMITIDO
========================================================= */

http_response_code(405);

echo json_encode([
    'status' => 'error',
    'message' => 'Método no permitido'
]);