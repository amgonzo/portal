<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];
require_once $rutas['auditoria'];


// =========================================================
// AUTENTICACIÓN
// =========================================================

$userAuth = validarTokenAPI($mysqli);

validarPermisoEndpoint(
    $mysqli,
    $userAuth
);


// =========================================================
// EMPRESA
// =========================================================

$empresa = obtenerEmpresaActual(
    $mysqli,
    $userAuth
);


$mysqli = conectarDBEmpresa(
    $mysqli,
    (int)$empresa['idempresa'],
    'DATOS'
);


// =========================================================
// USUARIO PARA AUDITORÍA
// =========================================================

$idUsuarioLog =
    (int)$userAuth['idusuario'];


// =========================================================
// FUNCIÓN RESPUESTA
// =========================================================

function responder($status, $msg = '', $data = null)
{
    echo json_encode([
        'status' => $status,
        'msg'    => $msg,
        'data'   => $data
    ]);

    exit;
}


// =========================================================
// GET - LEER CALENDARIO
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $anio =
        isset($_GET['anio'])
            ? (int)$_GET['anio']
            : 0;


    if (
        $anio < 2000 ||
        $anio > 2100
    ) {

        responder(
            'error',
            'Año inválido.'
        );

    }


    $fechaDesde =
        $anio . '-01-01';


    $fechaHasta =
        $anio . '-12-31';


    $sql = "
        SELECT
            cm.idcalendario,
            cm.fecha,
            cm.idtipo_asistencia,
            ta.nombre,
            ta.abreviatura,
            ta.color_fondo,
            ta.color_texto
        FROM calendario_maestro cm
        INNER JOIN tipos_asistencia ta
            ON ta.idtipo_asistencia =
               cm.idtipo_asistencia
        WHERE cm.fecha BETWEEN ? AND ?
          AND cm.activo = 1
        ORDER BY cm.fecha
    ";


    $stmt =
        $mysqli->prepare($sql);


    if (!$stmt) {

        responder(
            'error',
            'No se pudo preparar la consulta del calendario.'
        );

    }


    $stmt->bind_param(
        'ss',
        $fechaDesde,
        $fechaHasta
    );


    if (!$stmt->execute()) {

        $stmt->close();

        responder(
            'error',
            'No se pudo consultar el calendario.'
        );

    }


    $resultado =
        $stmt->get_result();


    $datos = [];


    while ($fila = $resultado->fetch_assoc()) {

        $datos[] = $fila;

    }


    $stmt->close();


    responder(
        'ok',
        '',
        $datos
    );

}


// =========================================================
// POST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder(
        'error',
        'Método no permitido.'
    );

}


$entrada =
    json_decode(
        file_get_contents('php://input'),
        true
    );


if (!is_array($entrada)) {

    responder(
        'error',
        'Datos inválidos.'
    );

}


$accion =
    trim(
        $entrada['accion'] ?? ''
    );


// =========================================================
// VALIDAR FECHA
// =========================================================

$fecha =
    trim(
        $entrada['fecha'] ?? ''
    );


$fechaObj =
    DateTime::createFromFormat(
        'Y-m-d',
        $fecha
    );


if (
    !$fechaObj ||
    $fechaObj->format('Y-m-d') !== $fecha
) {

    responder(
        'error',
        'Fecha inválida.'
    );

}


// =========================================================
// GUARDAR
// =========================================================

if ($accion === 'guardar') {

    $idTipoAsistencia =
        isset(
            $entrada['idtipo_asistencia']
        )
            ? (int)$entrada['idtipo_asistencia']
            : 0;


    if ($idTipoAsistencia <= 0) {

        responder(
            'error',
            'Debe indicar un tipo de asistencia.'
        );

    }


    // ---------------------------------------------------------
    // VERIFICAR QUE EL TIPO ESTÉ ACTIVO Y DISPONIBLE
    // EN EL CALENDARIO MAESTRO
    // ---------------------------------------------------------

    $stmtTipo =
        $mysqli->prepare("
            SELECT
                idtipo_asistencia,
                nombre,
                abreviatura,
                color_fondo,
                color_texto
            FROM tipos_asistencia
            WHERE idtipo_asistencia = ?
              AND activo = 1
              AND visible_maestro = 1
            LIMIT 1
        ");


    if (!$stmtTipo) {

        responder(
            'error',
            'No se pudo validar el tipo de asistencia.'
        );

    }


    $stmtTipo->bind_param(
        'i',
        $idTipoAsistencia
    );


    $stmtTipo->execute();


    $tipo =
        $stmtTipo
            ->get_result()
            ->fetch_assoc();


    $stmtTipo->close();


    if (!$tipo) {

        responder(
            'error',
            'El tipo de asistencia no está disponible para el calendario maestro.'
        );

    }


    // ---------------------------------------------------------
    // BUSCAR REGISTRO EXISTENTE
    // ---------------------------------------------------------

    $stmtExiste =
        $mysqli->prepare("
            SELECT
                idcalendario,
                fecha,
                idtipo_asistencia,
                descripcion,
                activo
            FROM calendario_maestro
            WHERE fecha = ?
            LIMIT 1
        ");


    if (!$stmtExiste) {

        responder(
            'error',
            'No se pudo consultar el registro existente.'
        );

    }


    $stmtExiste->bind_param(
        's',
        $fecha
    );


    $stmtExiste->execute();


    $registroAnterior =
        $stmtExiste
            ->get_result()
            ->fetch_assoc();


    $stmtExiste->close();


    // ---------------------------------------------------------
    // TRANSACCIÓN
    // ---------------------------------------------------------

    $mysqli->begin_transaction();


    try {

        if ($registroAnterior) {

            $idCalendario =
                (int)$registroAnterior[
                    'idcalendario'
                ];


            $stmtUpdate =
                $mysqli->prepare("
                    UPDATE calendario_maestro
                    SET
                        idtipo_asistencia = ?,
                        activo = 1
                    WHERE idcalendario = ?
                ");


            if (!$stmtUpdate) {

                throw new Exception(
                    'No se pudo preparar la modificación.'
                );

            }


            $stmtUpdate->bind_param(
                'ii',
                $idTipoAsistencia,
                $idCalendario
            );


            if (!$stmtUpdate->execute()) {

                $stmtUpdate->close();

                throw new Exception(
                    'No se pudo modificar el calendario.'
                );

            }


            $stmtUpdate->close();


            // -------------------------------------------------
            // AUDITORÍA MODIFICACIÓN
            // -------------------------------------------------

            registrarLog(
                $mysqli,
                'modificar_calendario_maestro',
                'calendario_maestro',
                $idCalendario,
                $idUsuarioLog,
                [
                    'fecha' =>
                        $registroAnterior['fecha'],

                    'idtipo_asistencia' =>
                        (int)$registroAnterior[
                            'idtipo_asistencia'
                        ],

                    'activo' =>
                        (int)$registroAnterior[
                            'activo'
                        ]
                ],
                [
                    'fecha' =>
                        $fecha,

                    'idtipo_asistencia' =>
                        $idTipoAsistencia,

                    'activo' =>
                        1
                ]
            );


        } else {

            // -------------------------------------------------
            // INSERTAR
            // -------------------------------------------------

            $stmtInsert =
                $mysqli->prepare("
                    INSERT INTO calendario_maestro (
                        fecha,
                        idtipo_asistencia,
                        activo
                    )
                    VALUES (?, ?, 1)
                ");


            if (!$stmtInsert) {

                throw new Exception(
                    'No se pudo preparar el alta.'
                );

            }


            $stmtInsert->bind_param(
                'si',
                $fecha,
                $idTipoAsistencia
            );


            if (!$stmtInsert->execute()) {

                $stmtInsert->close();

                throw new Exception(
                    'No se pudo guardar el calendario.'
                );

            }


            $idCalendario =
                (int)$mysqli->insert_id;


            $stmtInsert->close();


            // -------------------------------------------------
            // AUDITORÍA ALTA
            // -------------------------------------------------

            registrarLog(
                $mysqli,
                'crear_calendario_maestro',
                'calendario_maestro',
                $idCalendario,
                $idUsuarioLog,
                null,
                [
                    'fecha' =>
                        $fecha,

                    'idtipo_asistencia' =>
                        $idTipoAsistencia,

                    'activo' =>
                        1
                ]
            );

        }


        $mysqli->commit();


        responder(
            'ok',
            'Asistencia guardada correctamente.',
            [
                'idcalendario' =>
                    $idCalendario,

                'fecha' =>
                    $fecha,

                'idtipo_asistencia' =>
                    $idTipoAsistencia,

                'nombre' =>
                    $tipo['nombre'],

                'abreviatura' =>
                    $tipo['abreviatura'],

                'color_fondo' =>
                    $tipo['color_fondo'],

                'color_texto' =>
                    $tipo['color_texto']
            ]
        );


    } catch (Throwable $e) {

        $mysqli->rollback();


        registrarLog(
            $mysqli,
            'error_guardar_calendario_maestro',
            'sistema',
            null,
            $idUsuarioLog,
            null,
            [
                'error' =>
                    $e->getMessage(),

                'fecha' =>
                    $fecha,

                'idtipo_asistencia' =>
                    $idTipoAsistencia
            ]
        );


        responder(
            'error',
            $e->getMessage()
        );

    }

}


// =========================================================
// ELIMINAR
// =========================================================

if ($accion === 'eliminar') {

    // ---------------------------------------------------------
    // BUSCAR REGISTRO
    // ---------------------------------------------------------

    $stmtExiste =
        $mysqli->prepare("
            SELECT
                idcalendario,
                fecha,
                idtipo_asistencia,
                descripcion,
                activo
            FROM calendario_maestro
            WHERE fecha = ?
            LIMIT 1
        ");


    if (!$stmtExiste) {

        responder(
            'error',
            'No se pudo consultar la asistencia.'
        );

    }


    $stmtExiste->bind_param(
        's',
        $fecha
    );


    $stmtExiste->execute();


    $registroAnterior =
        $stmtExiste
            ->get_result()
            ->fetch_assoc();


    $stmtExiste->close();


    if (!$registroAnterior) {

        responder(
            'ok',
            'No había una asistencia registrada para ese día.'
        );

    }


    $idCalendario =
        (int)$registroAnterior[
            'idcalendario'
        ];


    // ---------------------------------------------------------
    // ELIMINACIÓN FÍSICA
    // ---------------------------------------------------------

    $mysqli->begin_transaction();


    try {

        $stmtDelete =
            $mysqli->prepare("
                DELETE FROM calendario_maestro
                WHERE idcalendario = ?
            ");


        if (!$stmtDelete) {

            throw new Exception(
                'No se pudo preparar la eliminación.'
            );

        }


        $stmtDelete->bind_param(
            'i',
            $idCalendario
        );


        if (!$stmtDelete->execute()) {

            $stmtDelete->close();

            throw new Exception(
                'No se pudo eliminar la asistencia.'
            );

        }


        $stmtDelete->close();


        // -----------------------------------------------------
        // AUDITORÍA BAJA
        // -----------------------------------------------------

        registrarLog(
            $mysqli,
            'eliminar_calendario_maestro',
            'calendario_maestro',
            $idCalendario,
            $idUsuarioLog,
            [
                'fecha' =>
                    $registroAnterior['fecha'],

                'idtipo_asistencia' =>
                    (int)$registroAnterior[
                        'idtipo_asistencia'
                    ],

                'descripcion' =>
                    $registroAnterior[
                        'descripcion'
                    ],

                'activo' =>
                    (int)$registroAnterior[
                        'activo'
                    ]
            ],
            null
        );


        $mysqli->commit();


        responder(
            'ok',
            'Asistencia eliminada correctamente.'
        );


    } catch (Throwable $e) {

        $mysqli->rollback();


        registrarLog(
            $mysqli,
            'error_eliminar_calendario_maestro',
            'sistema',
            null,
            $idUsuarioLog,
            null,
            [
                'error' =>
                    $e->getMessage(),

                'fecha' =>
                    $fecha,

                'idcalendario' =>
                    $idCalendario
            ]
        );


        responder(
            'error',
            $e->getMessage()
        );

    }

}


// =========================================================
// ACCIÓN DESCONOCIDA
// =========================================================

responder(
    'error',
    'Acción no válida.'
);