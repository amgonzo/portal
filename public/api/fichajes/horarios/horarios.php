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
   FUNCIONES
========================================================= */

function responderHorario(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}


function calcularMinutosTramo(
    string $horaDesde,
    string $horaHasta
): int {

    $desde = strtotime($horaDesde);
    $hasta = strtotime($horaHasta);

    if ($desde === false || $hasta === false) {
        return 0;
    }

    $minutosDesde =
        ((int)date('H', $desde) * 60) +
        (int)date('i', $desde);

    $minutosHasta =
        ((int)date('H', $hasta) * 60) +
        (int)date('i', $hasta);

    if ($minutosHasta < $minutosDesde) {
        $minutosHasta += 1440;
    }

    return $minutosHasta - $minutosDesde;
}


/* =========================================================
   AUTENTICACIÓN
========================================================= */

try {

    $userAuth = validarTokenAPI($mysqli);

    if (!$userAuth) {

        responderHorario([
            'status' => 'error',
            'msg' => 'No autorizado.'
        ], 401);

    }


    validarPermisoEndpoint($mysqli, $userAuth);


    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );


    if (!$empresa) {

        responderHorario([
            'status' => 'error',
            'msg' => 'No se pudo determinar la empresa activa.'
        ], 400);

    }


    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );


} catch (Throwable $e) {

    responderHorario([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], 500);

}


/* =========================================================
   MÉTODO
========================================================= */

$metodo = $_SERVER['REQUEST_METHOD'];


/* =========================================================
   GET
========================================================= */

if ($metodo === 'GET') {

    try {

        /*
         * =====================================================
         * OBTENER UN HORARIO PARA EDITAR
         * =====================================================
         */

        $idHorario = isset($_GET['idhorario'])
            ? (int)$_GET['idhorario']
            : 0;


        if ($idHorario > 0) {

            /* =============================================
               HORARIO
            ============================================= */

            $stmt = $mysqli->prepare("
                SELECT
                    idhorario,
                    nombre,
                    tolerancia_minutos,
                    minutos_trabajo,
                    activo
                FROM horarios
                WHERE idhorario = ?
                LIMIT 1
            ");


            if (!$stmt) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudo preparar la consulta del horario.',
                    'detalle' => $mysqli->error
                ], 500);

            }


            $stmt->bind_param(
                'i',
                $idHorario
            );


            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudo consultar el horario.',
                    'detalle' => $error
                ], 500);

            }


            $resultado = $stmt->get_result();

            $horario = $resultado->fetch_assoc();

            $stmt->close();


            if (!$horario) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'El horario no existe.'
                ], 404);

            }


            /* =============================================
               TRAMOS
            ============================================= */

            $stmt = $mysqli->prepare("
                SELECT
                    idhorario_tramo,
                    idhorario,
                    orden,
                    hora_desde,
                    hora_hasta,
                    minutos_teoricos
                FROM horario_tramos
                WHERE idhorario = ?
                ORDER BY orden ASC
            ");


            if (!$stmt) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudo preparar la consulta de tramos.',
                    'detalle' => $mysqli->error
                ], 500);

            }


            $stmt->bind_param(
                'i',
                $idHorario
            );


            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudieron consultar los tramos.',
                    'detalle' => $error
                ], 500);

            }


            $resultado = $stmt->get_result();

            $tramos = [];


            while ($fila = $resultado->fetch_assoc()) {

                $tramos[] = [

                    'idhorario_tramo' =>
                        (int)$fila['idhorario_tramo'],

                    'idhorario' =>
                        (int)$fila['idhorario'],

                    'orden' =>
                        (int)$fila['orden'],

                    'hora_desde' =>
                        $fila['hora_desde'],

                    'hora_hasta' =>
                        $fila['hora_hasta'],

                    'minutos_teoricos' =>
                        (int)$fila['minutos_teoricos']

                ];

            }


            $stmt->close();


            /* =============================================
               DESCANSOS
            ============================================= */

            $stmt = $mysqli->prepare("
                SELECT
                    idhorario_descanso,
                    idhorario,
                    orden,
                    minutos_permitidos,
                    activo
                FROM horario_descansos
                WHERE idhorario = ?
                ORDER BY orden ASC
            ");


            if (!$stmt) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudo preparar la consulta de descansos.',
                    'detalle' => $mysqli->error
                ], 500);

            }


            $stmt->bind_param(
                'i',
                $idHorario
            );


            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responderHorario([
                    'status' => 'error',
                    'msg' => 'No se pudieron consultar los descansos.',
                    'detalle' => $error
                ], 500);

            }


            $resultado = $stmt->get_result();

            $descansos = [];


            while ($fila = $resultado->fetch_assoc()) {

                $descansos[] = [

                    'idhorario_descanso' =>
                        (int)$fila['idhorario_descanso'],

                    'idhorario' =>
                        (int)$fila['idhorario'],

                    'orden' =>
                        (int)$fila['orden'],

                    'minutos_permitidos' =>
                        (int)$fila['minutos_permitidos'],

                    'activo' =>
                        (int)$fila['activo']

                ];

            }


            $stmt->close();


            /* =============================================
               RESPUESTA PARA EDITAR
            ============================================= */

            responderHorario([
                'status' => 'ok',
                'data' => [

                    'idhorario' =>
                        (int)$horario['idhorario'],

                    'nombre' =>
                        $horario['nombre'],

                    'tolerancia_minutos' =>
                        (int)$horario['tolerancia_minutos'],

                    'minutos_trabajo' =>
                        (int)$horario['minutos_trabajo'],

                    'activo' =>
                        (int)$horario['activo'],

                    'tramos' =>
                        $tramos,

                    'descansos' =>
                        $descansos

                ]
            ]);

        }


        /*
         * =====================================================
         * LISTAR TODOS LOS HORARIOS
         * =====================================================
         */

        $sql = "
            SELECT
                h.idhorario,
                h.nombre,
                h.tolerancia_minutos,
                h.minutos_trabajo,
                h.activo,

                (
                    SELECT COUNT(*)
                    FROM horario_tramos ht
                    WHERE ht.idhorario = h.idhorario
                ) AS cantidad_tramos,

                (
                    SELECT COUNT(*)
                    FROM horario_descansos hd
                    WHERE hd.idhorario = h.idhorario
                ) AS cantidad_descansos

            FROM horarios h

            ORDER BY
                h.nombre ASC
        ";


        $resultado = $mysqli->query($sql);


        if (!$resultado) {

            responderHorario([
                'status' => 'error',
                'msg' => 'Error al consultar los horarios.',
                'detalle' => $mysqli->error
            ], 500);

        }


        $horarios = [];


        while ($fila = $resultado->fetch_assoc()) {

            $horarios[] = [

                'idhorario' =>
                    (int)$fila['idhorario'],

                'nombre' =>
                    $fila['nombre'],

                'tolerancia_minutos' =>
                    (int)$fila['tolerancia_minutos'],

                'minutos_trabajo' =>
                    (int)$fila['minutos_trabajo'],

                'activo' =>
                    (int)$fila['activo'],

                'cantidad_tramos' =>
                    (int)$fila['cantidad_tramos'],

                'cantidad_descansos' =>
                    (int)$fila['cantidad_descansos']

            ];

        }


        responderHorario([
            'status' => 'ok',
            'data' => $horarios
        ]);


    } catch (Throwable $e) {

        responderHorario([
            'status' => 'error',
            'msg' => $e->getMessage()
        ], 500);

    }

}


/* =========================================================
   POST
========================================================= */

if ($metodo === 'POST') {

    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );


    if (!is_array($entrada)) {

        responderHorario([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);

    }


    $accion = $entrada['accion'] ?? '';


    /* =====================================================
       GUARDAR
    ===================================================== */

    if ($accion === 'guardar') {

        $idHorario = !empty($entrada['idhorario'])
            ? (int)$entrada['idhorario']
            : 0;

        $nombre = trim(
            (string)($entrada['nombre'] ?? '')
        );

        $tolerancia = isset(
            $entrada['tolerancia_minutos']
        )
            ? (int)$entrada['tolerancia_minutos']
            : 0;

        $tramos = $entrada['tramos'] ?? [];

        $descansos = $entrada['descansos'] ?? [];


        if ($nombre === '') {

            responderHorario([
                'status' => 'error',
                'msg' => 'Debe ingresar el nombre del horario.'
            ], 400);

        }


        if ($tolerancia < 0) {

            responderHorario([
                'status' => 'error',
                'msg' => 'La tolerancia no puede ser negativa.'
            ], 400);

        }


        if (!is_array($tramos) || count($tramos) === 0) {

            responderHorario([
                'status' => 'error',
                'msg' => 'Debe existir al menos un tramo.'
            ], 400);

        }


        if (!is_array($descansos)) {
            $descansos = [];
        }


        /* =================================================
           VALIDAR Y NORMALIZAR TRAMOS
        ================================================= */

        $tramosNormalizados = [];

        foreach ($tramos as $indice => $tramo) {

            $horaDesde = trim(
                (string)($tramo['hora_desde'] ?? '')
            );

            $horaHasta = trim(
                (string)($tramo['hora_hasta'] ?? '')
            );


            if ($horaDesde === '' || $horaHasta === '') {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'Todos los tramos deben tener hora desde y hasta.'
                ], 400);

            }


            $minutosTeoricos =
                calcularMinutosTramo(
                    $horaDesde,
                    $horaHasta
                );


            if ($minutosTeoricos <= 0) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'Uno de los tramos tiene una duración inválida.'
                ], 400);

            }


            $tramosNormalizados[] = [

                'orden' =>
                    $indice + 1,

                'hora_desde' =>
                    $horaDesde,

                'hora_hasta' =>
                    $horaHasta,

                'minutos_teoricos' =>
                    $minutosTeoricos

            ];

        }


        $minutosTrabajo = 0;

        foreach ($tramosNormalizados as $tramo) {

            $minutosTrabajo +=
                $tramo['minutos_teoricos'];

        }


        /* =================================================
           VALIDAR DESCANSOS
        ================================================= */

        $descansosNormalizados = [];

        foreach ($descansos as $indice => $descanso) {

            $minutosPermitidos = isset(
                $descanso['minutos_permitidos']
            )
                ? (int)$descanso['minutos_permitidos']
                : 0;


            if ($minutosPermitidos < 0) {

                responderHorario([
                    'status' => 'error',
                    'msg' => 'Los minutos de descanso no pueden ser negativos.'
                ], 400);

            }


            $descansosNormalizados[] = [

                'orden' =>
                    $indice + 1,

                'minutos_permitidos' =>
                    $minutosPermitidos,

                'activo' =>
                    (
                        !empty($descanso['activo'])
                        ? 1
                        : 0
                    )

            ];

        }


        /* =================================================
           TRANSACCIÓN
        ================================================= */

        $mysqli->begin_transaction();


        try {

            /* =============================================
               HORARIO
            ============================================= */

            if ($idHorario > 0) {

                $stmt = $mysqli->prepare("
                    UPDATE horarios
                    SET
                        nombre = ?,
                        tolerancia_minutos = ?,
                        minutos_trabajo = ?
                    WHERE idhorario = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        'No se pudo preparar la actualización del horario.'
                    );
                }


                $stmt->bind_param(
                    'siii',
                    $nombre,
                    $tolerancia,
                    $minutosTrabajo,
                    $idHorario
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudo actualizar el horario: ' .
                        $stmt->error
                    );

                }


                $stmt->close();


                /* =========================================
                   ELIMINAR DETALLE ACTUAL
                ========================================= */

                $stmt = $mysqli->prepare("
                    DELETE FROM horario_tramos
                    WHERE idhorario = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        'No se pudo preparar la actualización de tramos.'
                    );
                }

                $stmt->bind_param(
                    'i',
                    $idHorario
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudieron actualizar los tramos: ' .
                        $stmt->error
                    );

                }

                $stmt->close();


                $stmt = $mysqli->prepare("
                    DELETE FROM horario_descansos
                    WHERE idhorario = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        'No se pudo preparar la actualización de descansos.'
                    );
                }

                $stmt->bind_param(
                    'i',
                    $idHorario
                );

                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudieron actualizar los descansos: ' .
                        $stmt->error
                    );

                }

                $stmt->close();


                $mensaje =
                    'Horario actualizado correctamente.';

            } else {

                /* =========================================
                   NUEVO HORARIO
                ========================================= */

                $activo = 1;

                $stmt = $mysqli->prepare("
                    INSERT INTO horarios
                    (
                        nombre,
                        tolerancia_minutos,
                        minutos_trabajo,
                        activo
                    )
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception(
                        'No se pudo preparar la creación del horario.'
                    );
                }


                $stmt->bind_param(
                    'siii',
                    $nombre,
                    $tolerancia,
                    $minutosTrabajo,
                    $activo
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudo crear el horario: ' .
                        $stmt->error
                    );

                }


                $idHorario =
                    (int)$mysqli->insert_id;


                $stmt->close();


                $mensaje =
                    'Horario creado correctamente.';

            }


            /* =============================================
               INSERTAR TRAMOS
            ============================================= */

            $stmtTramo = $mysqli->prepare("
                INSERT INTO horario_tramos
                (
                    idhorario,
                    orden,
                    hora_desde,
                    hora_hasta,
                    minutos_teoricos
                )
                VALUES (?, ?, ?, ?, ?)
            ");


            if (!$stmtTramo) {
                throw new Exception(
                    'No se pudo preparar la carga de tramos.'
                );
            }


            foreach ($tramosNormalizados as $tramo) {

                $orden =
                    (int)$tramo['orden'];

                $horaDesde =
                    $tramo['hora_desde'];

                $horaHasta =
                    $tramo['hora_hasta'];

                $minutos =
                    (int)$tramo['minutos_teoricos'];


                $stmtTramo->bind_param(
                    'iissi',
                    $idHorario,
                    $orden,
                    $horaDesde,
                    $horaHasta,
                    $minutos
                );


                if (!$stmtTramo->execute()) {

                    throw new Exception(
                        'No se pudo guardar un tramo: ' .
                        $stmtTramo->error
                    );

                }

            }


            $stmtTramo->close();


            /* =============================================
               INSERTAR DESCANSOS
            ============================================= */

            if (count($descansosNormalizados) > 0) {

                $stmtDescanso = $mysqli->prepare("
                    INSERT INTO horario_descansos
                    (
                        idhorario,
                        orden,
                        minutos_permitidos,
                        activo
                    )
                    VALUES (?, ?, ?, ?)
                ");


                if (!$stmtDescanso) {
                    throw new Exception(
                        'No se pudo preparar la carga de descansos.'
                    );
                }


                foreach ($descansosNormalizados as $descanso) {

                    $orden =
                        (int)$descanso['orden'];

                    $minutos =
                        (int)$descanso['minutos_permitidos'];

                    $activo =
                        (int)$descanso['activo'];


                    $stmtDescanso->bind_param(
                        'iiii',
                        $idHorario,
                        $orden,
                        $minutos,
                        $activo
                    );


                    if (!$stmtDescanso->execute()) {

                        throw new Exception(
                            'No se pudo guardar un descanso: ' .
                            $stmtDescanso->error
                        );

                    }

                }


                $stmtDescanso->close();

            }


            $mysqli->commit();


            responderHorario([
                'status' => 'ok',
                'msg' => $mensaje,
                'idhorario' => $idHorario
            ]);


        } catch (Throwable $e) {

            $mysqli->rollback();

            throw $e;

        }

    }


    /* =====================================================
       CAMBIAR ESTADO
    ===================================================== */

    if ($accion === 'cambiar_estado') {

        $idHorario =
            (int)($entrada['idhorario'] ?? 0);

        $activo =
            isset($entrada['activo'])
                ? (int)$entrada['activo']
                : 0;


        if ($idHorario <= 0) {

            responderHorario([
                'status' => 'error',
                'msg' => 'Horario inválido.'
            ], 400);

        }


        if ($activo !== 0 && $activo !== 1) {

            responderHorario([
                'status' => 'error',
                'msg' => 'Estado inválido.'
            ], 400);

        }


        $stmt = $mysqli->prepare("
            UPDATE horarios
            SET activo = ?
            WHERE idhorario = ?
        ");


        if (!$stmt) {

            responderHorario([
                'status' => 'error',
                'msg' => 'No se pudo preparar la actualización.'
            ], 500);

        }


        $stmt->bind_param(
            'ii',
            $activo,
            $idHorario
        );


        if (!$stmt->execute()) {

            $error = $stmt->error;

            $stmt->close();

            responderHorario([
                'status' => 'error',
                'msg' => 'No se pudo cambiar el estado.',
                'detalle' => $error
            ], 500);

        }


        $stmt->close();


        responderHorario([
            'status' => 'ok',
            'msg' => $activo === 1
                ? 'Horario activado correctamente.'
                : 'Horario desactivado correctamente.'
        ]);

    }


    responderHorario([
        'status' => 'error',
        'msg' => 'Acción no válida.'
    ], 400);

}


/* =========================================================
   MÉTODO NO PERMITIDO
========================================================= */

responderHorario([
    'status' => 'error',
    'msg' => 'Método no permitido.'
], 405);