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


// ============================================================
// RESPUESTA
// ============================================================

function responderPermisos(
    array $datos,
    int $codigo = 200
): never {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ============================================================
// AUTENTICACIÓN
// ============================================================

$userAuth = validarTokenAPI($mysqli);

if (!$userAuth) {

    responderPermisos([
        'status' => 'error',
        'msg' => 'No autorizado.'
    ], 401);

}

validarPermisoEndpoint(
    $mysqli,
    $userAuth
);

$empresa = obtenerEmpresaActual(
    $mysqli,
    $userAuth
);

$mysqli = conectarDBEmpresa(
    $mysqli,
    (int)$empresa['idempresa'],
    'DATOS'
);


// ============================================================
// ACCIÓN
// ============================================================

$accion = $_GET['accion'] ?? '';

$entrada = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($entrada)) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);

    }

    $accion = $entrada['accion'] ?? '';

}


// ============================================================
// DATOS INICIALES
// ============================================================

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
        ORDER BY
            apellido,
            nombre
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderPermisos([
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


    responderPermisos([
        'status' => 'ok',

        'data' => [
            'empleados' => $empleados
        ]
    ]);

}


// ============================================================
// LISTAR
// ============================================================

if ($accion === 'listar') {

    $datos = [];


    $sql = "
        SELECT
            p.idpermiso,
            p.idempleado,
            p.fecha,
            p.hora_desde,
            p.hora_hasta,
            p.motivo,
            p.idusuario,
            p.fecha_carga,

            e.documento,
            e.nombre,
            e.apellido

        FROM permisos_salida p

        INNER JOIN empleados e
            ON e.idempleado = p.idempleado

        ORDER BY
            p.fecha DESC,
            e.apellido,
            e.nombre,
            p.hora_desde
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Error al consultar los permisos.'
        ], 500);

    }


    $stmt->execute();

    $resultado = $stmt->get_result();


    while ($fila = $resultado->fetch_assoc()) {

        /*
         * La tabla permisos_salida no tiene activo en el esquema
         * actual. Los permisos existentes se consideran vigentes
         * como registros históricos.
         *
         * Para no inventar una columna que no existe,
         * el estado se informa como Registrado.
         */

        $fila['activo'] = 1;

        $fila['empleado'] =
            $fila['apellido'] .
            ', ' .
            $fila['nombre'];


        $fila['fecha'] =
            $fila['fecha']
                ? date(
                    'd/m/Y',
                    strtotime($fila['fecha'])
                )
                : '';


        $fila['hora_desde'] =
            $fila['hora_desde']
                ? substr(
                    $fila['hora_desde'],
                    0,
                    5
                )
                : '';


        $fila['hora_hasta'] =
            $fila['hora_hasta']
                ? substr(
                    $fila['hora_hasta'],
                    0,
                    5
                )
                : '';


        $fila['motivo'] =
            $fila['motivo'] ?? '';


        $fila['estado'] =
            '<span class="badge bg-primary">Registrado</span>';


        $datos[] = $fila;

    }

    $stmt->close();


    responderPermisos([
        'status' => 'ok',
        'data' => $datos
    ]);

}


// ============================================================
// OBTENER
// ============================================================

if ($accion === 'obtener') {

    $id = (int)(
        $_GET['id'] ?? 0
    );


    if ($id <= 0) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Permiso inválido.'
        ], 400);

    }


    $sql = "
        SELECT
            idpermiso,
            idempleado,
            fecha,
            hora_desde,
            hora_hasta,
            motivo,
            idusuario,
            fecha_carga

        FROM permisos_salida

        WHERE idpermiso = ?

        LIMIT 1
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Error al consultar el permiso.'
        ], 500);

    }


    $stmt->bind_param(
        'i',
        $id
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $fila = $resultado->fetch_assoc();

    $stmt->close();


    if (!$fila) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'No se encontró el permiso.'
        ], 404);

    }


    responderPermisos([
        'status' => 'ok',
        'data' => $fila
    ]);

}


// ============================================================
// GUARDAR
// ============================================================

if ($accion === 'guardar') {

    $id = (int)(
        $entrada['idpermiso'] ?? 0
    );

    $idempleado = (int)(
        $entrada['idempleado'] ?? 0
    );

    $fecha = trim(
        (string)(
            $entrada['fecha'] ?? ''
        )
    );

    $horaDesde = $entrada['hora_desde'] ?? null;
    $horaHasta = $entrada['hora_hasta'] ?? null;

    $motivo = trim(
        (string)(
            $entrada['motivo'] ?? ''
        )
    );


    /*
     * Convertir strings vacíos a NULL.
     */

    if (
        $horaDesde === '' ||
        $horaDesde === null
    ) {
        $horaDesde = null;
    }

    if (
        $horaHasta === '' ||
        $horaHasta === null
    ) {
        $horaHasta = null;
    }


    // ---------------------------------------------------------
    // Validaciones básicas
    // ---------------------------------------------------------

    if (
        $idempleado <= 0 ||
        $fecha === ''
    ) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Faltan datos obligatorios.'
        ], 400);

    }


    if (
        mb_strlen($motivo) > 255
    ) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'El motivo no puede superar los 255 caracteres.'
        ], 400);

    }


    /*
     * Deben venir ambas horas o ninguna.
     */

    if (
        ($horaDesde !== null && $horaHasta === null) ||
        ($horaDesde === null && $horaHasta !== null)
    ) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Debe indicar las dos horas o dejar ambas vacías.'
        ], 400);

    }


    /*
     * Validar formato de hora.
     */

    if ($horaDesde !== null) {

        if (
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $horaDesde
            ) ||
            !preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $horaHasta
            )
        ) {

            responderPermisos([
                'status' => 'error',
                'msg' => 'Las horas deben tener formato HH:mm.'
            ], 400);

        }


        if ($horaDesde === $horaHasta) {

            responderPermisos([
                'status' => 'error',
                'msg' => 'La hora desde y la hora hasta no pueden ser iguales.'
            ], 400);

        }


        if ($horaHasta < $horaDesde) {

            responderPermisos([
                'status' => 'error',
                'msg' => 'La hora hasta no puede ser anterior a la hora desde.'
            ], 400);

        }

    }


    // ---------------------------------------------------------
    // Validar empleado
    // ---------------------------------------------------------

    $sql = "
        SELECT
            idempleado

        FROM empleados

        WHERE idempleado = ?
          AND activo = 1

        LIMIT 1
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Error al validar el empleado.'
        ], 500);

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

        responderPermisos([
            'status' => 'error',
            'msg' => 'El empleado no existe o está inactivo.'
        ], 400);

    }


    // ---------------------------------------------------------
    // Verificar duplicado exacto
    // ---------------------------------------------------------

    $sql = "
        SELECT
            idpermiso

        FROM permisos_salida

        WHERE idempleado = ?
          AND fecha = ?
          AND idpermiso <> ?

          AND (
                (hora_desde IS NULL AND hora_hasta IS NULL)

                OR

                (
                    hora_desde <=> ?
                    AND
                    hora_hasta <=> ?
                )
          )

        LIMIT 1
    ";


    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Error al validar permisos existentes.'
        ], 500);

    }


    $stmt->bind_param(
        'isiss',
        $idempleado,
        $fecha,
        $id,
        $horaDesde,
        $horaHasta
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    $duplicado = $resultado->fetch_assoc();

    $stmt->close();


    if ($duplicado) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Ya existe un permiso igual para ese empleado y fecha.'
        ], 400);

    }


    // ---------------------------------------------------------
    // Obtener usuario
    // ---------------------------------------------------------

    $idusuario = (int)(
        $userAuth['idusuario'] ??
        $userAuth['id'] ??
        0
    );


    if ($idusuario <= 0) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'No se pudo identificar al usuario.'
        ], 500);

    }


    // ---------------------------------------------------------
    // ACTUALIZAR
    // ---------------------------------------------------------

    if ($id > 0) {

        $sql = "
            UPDATE permisos_salida

            SET
                idempleado = ?,
                fecha = ?,
                hora_desde = ?,
                hora_hasta = ?,
                motivo = NULLIF(?, '')

            WHERE idpermiso = ?
        ";


        $stmt = $mysqli->prepare($sql);

        if (!$stmt) {

            responderPermisos([
                'status' => 'error',
                'msg' => 'Error al preparar la actualización.'
            ], 500);

        }


        $stmt->bind_param(
            'issssi',
            $idempleado,
            $fecha,
            $horaDesde,
            $horaHasta,
            $motivo,
            $id
        );


        $ok = $stmt->execute();

        $stmt->close();


        if (!$ok) {

            responderPermisos([
                'status' => 'error',
                'msg' => 'No se pudo actualizar el permiso.'
            ], 500);

        }


        responderPermisos([
            'status' => 'ok',
            'msg' => 'Permiso actualizado correctamente.',
            'idpermiso' => $id
        ]);

    }


    // ---------------------------------------------------------
    // CREAR
    // ---------------------------------------------------------

    $sql = "
        INSERT INTO permisos_salida (
            idempleado,
            fecha,
            hora_desde,
            hora_hasta,
            motivo,
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

    if (!$stmt) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'Error al preparar la creación del permiso.'
        ], 500);

    }


    $stmt->bind_param(
        'issssi',
        $idempleado,
        $fecha,
        $horaDesde,
        $horaHasta,
        $motivo,
        $idusuario
    );


    $ok = $stmt->execute();

    $nuevoId = $stmt->insert_id;

    $stmt->close();


    if (!$ok) {

        responderPermisos([
            'status' => 'error',
            'msg' => 'No se pudo crear el permiso.'
        ], 500);

    }


    responderPermisos([
        'status' => 'ok',
        'msg' => 'Permiso creado correctamente.',
        'idpermiso' => $nuevoId
    ]);

}


// ============================================================
// CAMBIAR ESTADO
// ============================================================

if ($accion === 'cambiar_estado') {

    /*
     * IMPORTANTE:
     *
     * La tabla permisos_salida actual que tenemos definida NO
     * posee campo "activo".
     *
     * Por lo tanto no se modifica la estructura ni se inventa
     * una columna acá.
     *
     * Esta acción queda rechazada hasta que se defina
     * explícitamente una política de baja para permisos.
     */

    responderPermisos([
        'status' => 'error',
        'msg' => 'Los permisos actuales no tienen estado activo/inactivo.'
    ], 400);

}


// ============================================================
// ACCIÓN NO VÁLIDA
// ============================================================

responderPermisos([
    'status' => 'error',
    'msg' => 'Acción no válida.'
], 400);