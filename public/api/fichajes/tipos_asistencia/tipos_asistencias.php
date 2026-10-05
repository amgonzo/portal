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


// =========================================================
// RESPUESTA
// =========================================================

function responderTipoAsistencia(
    array $datos,
    int $codigo = 200
): never {

    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


// =========================================================
// COLOR
// =========================================================

function validarColorAsistencia(string $color): bool
{
    $color = trim($color);

    if ($color === '') {
        return false;
    }

    if (preg_match('/^#[0-9A-Fa-f]{3}$/', $color)) {
        return true;
    }

    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        return true;
    }

    // Permitir nombres CSS existentes en los datos históricos
    if (preg_match('/^[a-zA-Z]+$/', $color)) {
        return true;
    }

    return false;
}


// =========================================================
// AUTENTICACIÓN
// =========================================================

try {

    $userAuth = validarTokenAPI($mysqli);

    if (!$userAuth) {

        responderTipoAsistencia([
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


    if (!$empresa) {

        responderTipoAsistencia([
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

    responderTipoAsistencia([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], 500);

}


// =========================================================
// MÉTODO
// =========================================================

$metodo = $_SERVER['REQUEST_METHOD'];


// =========================================================
// GET
// =========================================================

if ($metodo === 'GET') {

    try {

        $idTipo = isset($_GET['idtipo_asistencia'])
            ? (int)$_GET['idtipo_asistencia']
            : 0;


        // =====================================================
        // OBTENER UNO
        // =====================================================

        if ($idTipo > 0) {

            $stmt = $mysqli->prepare("
                SELECT
                    idtipo_asistencia,
                    nombre,
                    descripcion,
                    abreviatura,
                    color_fondo,
                    color_texto,
                    computa_trabajo,
                    computa_ausencia,
                    modificable,
                    activo,
                    visible_maestro
                FROM tipos_asistencia
                WHERE idtipo_asistencia = ?
                LIMIT 1
            ");


            if (!$stmt) {

                responderTipoAsistencia([
                    'status' => 'error',
                    'msg' => 'No se pudo preparar la consulta.',
                    'detalle' => $mysqli->error
                ], 500);

            }


            $stmt->bind_param(
                'i',
                $idTipo
            );


            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responderTipoAsistencia([
                    'status' => 'error',
                    'msg' => 'No se pudo consultar el tipo de asistencia.',
                    'detalle' => $error
                ], 500);

            }


            $resultado = $stmt->get_result();

            $tipo = $resultado->fetch_assoc();

            $stmt->close();


            if (!$tipo) {

                responderTipoAsistencia([
                    'status' => 'error',
                    'msg' => 'El tipo de asistencia no existe.'
                ], 404);

            }


            responderTipoAsistencia([
                'status' => 'ok',
                'data' => [
                    'idtipo_asistencia' =>
                        (int)$tipo['idtipo_asistencia'],

                    'nombre' =>
                        $tipo['nombre'],

                    'descripcion' =>
                        $tipo['descripcion'],

                    'abreviatura' =>
                        $tipo['abreviatura'],

                    'color_fondo' =>
                        $tipo['color_fondo'],

                    'color_texto' =>
                        $tipo['color_texto'],

                    'computa_trabajo' =>
                        (int)$tipo['computa_trabajo'],

                    'computa_ausencia' =>
                        (int)$tipo['computa_ausencia'],

                    'modificable' =>
                        (int)$tipo['modificable'],

                    'activo' =>
                        (int)$tipo['activo'],

                    'visible_maestro' =>
                        (int)$tipo['visible_maestro']
                ]
            ]);

        }


        // =====================================================
        // LISTAR
        // =====================================================

        $resultado = $mysqli->query("
            SELECT
                idtipo_asistencia,
                nombre,
                descripcion,
                abreviatura,
                color_fondo,
                color_texto,
                computa_trabajo,
                computa_ausencia,
                modificable,
                activo,
                visible_maestro
            FROM tipos_asistencia
            ORDER BY
                activo DESC,
                nombre ASC
        ");


        if (!$resultado) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Error al consultar los tipos de asistencia.',
                'detalle' => $mysqli->error
            ], 500);

        }


        $tipos = [];


        while ($fila = $resultado->fetch_assoc()) {

            $tipos[] = [

                'idtipo_asistencia' =>
                    (int)$fila['idtipo_asistencia'],

                'nombre' =>
                    $fila['nombre'],

                'descripcion' =>
                    $fila['descripcion'],

                'abreviatura' =>
                    $fila['abreviatura'],

                'color_fondo' =>
                    $fila['color_fondo'],

                'color_texto' =>
                    $fila['color_texto'],

                'computa_trabajo' =>
                    (int)$fila['computa_trabajo'],

                'computa_ausencia' =>
                    (int)$fila['computa_ausencia'],

                'modificable' =>
                    (int)$fila['modificable'],

                'activo' =>
                    (int)$fila['activo'],

                'visible_maestro' =>
                    (int)$fila['visible_maestro']

            ];

        }


        $resultado->free();


        responderTipoAsistencia([
            'status' => 'ok',
            'data' => $tipos
        ]);


    } catch (Throwable $e) {

        responderTipoAsistencia([
            'status' => 'error',
            'msg' => $e->getMessage()
        ], 500);

    }

}


// =========================================================
// POST
// =========================================================

if ($metodo === 'POST') {

    $entrada = json_decode(
        file_get_contents('php://input'),
        true
    );


    if (!is_array($entrada)) {

        responderTipoAsistencia([
            'status' => 'error',
            'msg' => 'Datos inválidos.'
        ], 400);

    }


    $accion = $entrada['accion'] ?? '';


    // =====================================================
    // GUARDAR
    // =====================================================

    if ($accion === 'guardar') {

        $idTipo = !empty($entrada['idtipo_asistencia'])
            ? (int)$entrada['idtipo_asistencia']
            : 0;


        $nombre = trim(
            (string)($entrada['nombre'] ?? '')
        );


        $descripcion = trim(
            (string)($entrada['descripcion'] ?? '')
        );


        $abreviatura = trim(
            (string)($entrada['abreviatura'] ?? '')
        );


        $colorFondo = trim(
            (string)($entrada['color_fondo'] ?? '')
        );


        $colorTexto = trim(
            (string)($entrada['color_texto'] ?? '')
        );


        $computaTrabajo = isset(
            $entrada['computa_trabajo']
        )
            ? (int)$entrada['computa_trabajo']
            : 0;


        $computaAusencia = isset(
            $entrada['computa_ausencia']
        )
            ? (int)$entrada['computa_ausencia']
            : 0;


        $modificable = isset(
            $entrada['modificable']
        )
            ? (int)$entrada['modificable']
            : 0;


        $visibleMaestro = isset(
            $entrada['visible_maestro']
        )
            ? (int)$entrada['visible_maestro']
            : 0;


        // -------------------------------------------------
        // VALIDACIONES
        // -------------------------------------------------

        if ($nombre === '') {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Debe ingresar el nombre del tipo de asistencia.'
            ], 400);

        }


        if (mb_strlen($nombre) > 100) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'El nombre no puede superar los 100 caracteres.'
            ], 400);

        }


        if ($abreviatura === '') {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Debe ingresar la abreviatura.'
            ], 400);

        }


        if (mb_strlen($abreviatura) > 10) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'La abreviatura no puede superar los 10 caracteres.'
            ], 400);

        }


        if (mb_strlen($descripcion) > 255) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'La descripción no puede superar los 255 caracteres.'
            ], 400);

        }


        if (!validarColorAsistencia($colorFondo)) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'El color de fondo no es válido.'
            ], 400);

        }


        if (!validarColorAsistencia($colorTexto)) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'El color del texto no es válido.'
            ], 400);

        }


        if (
            $computaTrabajo !== 0 &&
            $computaTrabajo !== 1
        ) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Valor inválido para computa_trabajo.'
            ], 400);

        }


        if (
            $computaAusencia !== 0 &&
            $computaAusencia !== 1
        ) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Valor inválido para computa_ausencia.'
            ], 400);

        }


        if (
            $modificable !== 0 &&
            $modificable !== 1
        ) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Valor inválido para modificable.'
            ], 400);

        }


        if (
            $visibleMaestro !== 0 &&
            $visibleMaestro !== 1
        ) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Valor inválido para visible_maestro.'
            ], 400);

        }


        // -------------------------------------------------
        // TRANSACCIÓN
        // -------------------------------------------------

        $mysqli->begin_transaction();


        try {

            // =============================================
            // NUEVO
            // =============================================

            if ($idTipo <= 0) {

                $stmt = $mysqli->prepare("
                    SELECT idtipo_asistencia
                    FROM tipos_asistencia
                    WHERE LOWER(TRIM(nombre))
                        =
                        LOWER(TRIM(?))
                    LIMIT 1
                ");


                if (!$stmt) {
                    throw new Exception(
                        'No se pudo verificar el nombre.'
                    );
                }


                $stmt->bind_param(
                    's',
                    $nombre
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudo verificar el nombre: ' .
                        $stmt->error
                    );

                }


                $resultado = $stmt->get_result();

                $existe = $resultado->fetch_assoc();

                $stmt->close();


                if ($existe) {

                    throw new Exception(
                        'Ya existe un tipo de asistencia con ese nombre.'
                    );

                }


                $activo = 1;


                $stmt = $mysqli->prepare("
                    INSERT INTO tipos_asistencia
                    (
                        nombre,
                        descripcion,
                        abreviatura,
                        color_fondo,
                        color_texto,
                        computa_trabajo,
                        computa_ausencia,
                        modificable,
                        visible_maestro,
                        activo
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");


                if (!$stmt) {
                    throw new Exception(
                        'No se pudo preparar la creación del tipo de asistencia.'
                    );
                }


                $stmt->bind_param(
                    'sssssiiiii',
                    $nombre,
                    $descripcion,
                    $abreviatura,
                    $colorFondo,
                    $colorTexto,
                    $computaTrabajo,
                    $computaAusencia,
                    $modificable,
                    $visibleMaestro,
                    $activo
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        'No se pudo crear el tipo de asistencia: ' .
                        $stmt->error
                    );

                }


                $idNuevo = (int)$stmt->insert_id;

                $stmt->close();


                $mysqli->commit();


                responderTipoAsistencia([
                    'status' => 'ok',
                    'msg' => 'Tipo de asistencia creado correctamente.',
                    'idtipo_asistencia' => $idNuevo
                ]);

            }


            // =============================================
            // EDITAR
            // =============================================

            $stmt = $mysqli->prepare("
                SELECT
                    idtipo_asistencia,
                    modificable
                FROM tipos_asistencia
                WHERE idtipo_asistencia = ?
                FOR UPDATE
            ");


            if (!$stmt) {
                throw new Exception(
                    'No se pudo consultar el tipo de asistencia.'
                );
            }


            $stmt->bind_param(
                'i',
                $idTipo
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    'No se pudo consultar el tipo de asistencia: ' .
                    $stmt->error
                );

            }


            $resultado = $stmt->get_result();

            $actual = $resultado->fetch_assoc();

            $stmt->close();


            if (!$actual) {

                throw new Exception(
                    'El tipo de asistencia no existe.'
                );

            }


            if ((int)$actual['modificable'] !== 1) {

                throw new Exception(
                    'Este tipo de asistencia es del sistema y no puede ser modificado.'
                );

            }


            // =============================================
            // DUPLICADO
            // =============================================

            $stmt = $mysqli->prepare("
                SELECT idtipo_asistencia
                FROM tipos_asistencia
                WHERE LOWER(TRIM(nombre))
                    =
                    LOWER(TRIM(?))
                  AND idtipo_asistencia <> ?
                LIMIT 1
            ");


            if (!$stmt) {
                throw new Exception(
                    'No se pudo verificar el nombre.'
                );
            }


            $stmt->bind_param(
                'si',
                $nombre,
                $idTipo
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    'No se pudo verificar el nombre: ' .
                    $stmt->error
                );

            }


            $resultado = $stmt->get_result();

            $existe = $resultado->fetch_assoc();

            $stmt->close();


            if ($existe) {

                throw new Exception(
                    'Ya existe otro tipo de asistencia con ese nombre.'
                );

            }


            // =============================================
            // ACTUALIZAR
            // =============================================

            $stmt = $mysqli->prepare("
                UPDATE tipos_asistencia
                SET
                    nombre = ?,
                    descripcion = ?,
                    abreviatura = ?,
                    color_fondo = ?,
                    color_texto = ?,
                    computa_trabajo = ?,
                    computa_ausencia = ?,
                    modificable = ?,
                    visible_maestro = ?
                WHERE idtipo_asistencia = ?
            ");


            if (!$stmt) {
                throw new Exception(
                    'No se pudo preparar la actualización.'
                );
            }


            $stmt->bind_param(
                'sssssiiiii',
                $nombre,
                $descripcion,
                $abreviatura,
                $colorFondo,
                $colorTexto,
                $computaTrabajo,
                $computaAusencia,
                $modificable,
                $visibleMaestro,
                $idTipo
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    'No se pudo actualizar el tipo de asistencia: ' .
                    $stmt->error
                );

            }


            $stmt->close();


            $mysqli->commit();


            responderTipoAsistencia([
                'status' => 'ok',
                'msg' => 'Tipo de asistencia actualizado correctamente.',
                'idtipo_asistencia' => $idTipo
            ]);


        } catch (Throwable $e) {

            $mysqli->rollback();

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => $e->getMessage()
            ], 400);

        }

    }


    // =====================================================
    // CAMBIAR ESTADO
    // =====================================================

    if ($accion === 'cambiar_estado') {

        $idTipo = (int)(
            $entrada['idtipo_asistencia'] ?? 0
        );


        $activo = isset($entrada['activo'])
            ? (int)$entrada['activo']
            : -1;


        if ($idTipo <= 0) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Tipo de asistencia inválido.'
            ], 400);

        }


        if ($activo !== 0 && $activo !== 1) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'Estado inválido.'
            ], 400);

        }


        $stmt = $mysqli->prepare("
            UPDATE tipos_asistencia
            SET activo = ?
            WHERE idtipo_asistencia = ?
        ");


        if (!$stmt) {

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'No se pudo preparar la actualización.',
                'detalle' => $mysqli->error
            ], 500);

        }


        $stmt->bind_param(
            'ii',
            $activo,
            $idTipo
        );


        if (!$stmt->execute()) {

            $error = $stmt->error;

            $stmt->close();

            responderTipoAsistencia([
                'status' => 'error',
                'msg' => 'No se pudo cambiar el estado.',
                'detalle' => $error
            ], 500);

        }


        $stmt->close();


        responderTipoAsistencia([
            'status' => 'ok',
            'msg' => $activo === 1
                ? 'Tipo de asistencia activado correctamente.'
                : 'Tipo de asistencia desactivado correctamente.'
        ]);

    }


    responderTipoAsistencia([
        'status' => 'error',
        'msg' => 'Acción no válida.'
    ], 400);

}


// =========================================================
// MÉTODO NO PERMITIDO
// =========================================================

responderTipoAsistencia([
    'status' => 'error',
    'msg' => 'Método no permitido.'
], 405);