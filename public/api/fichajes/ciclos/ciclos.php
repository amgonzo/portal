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
       OBTENER UN CICLO
    ----------------------------------------------------- */

    if (isset($_GET['idciclo']) && $_GET['idciclo'] !== '') {

        $idciclo = (int)$_GET['idciclo'];

        $stmt = $mysqli->prepare("
            SELECT
                idciclo,
                nombre,
                cantidad_semanas,
                activo
            FROM ciclos
            WHERE idciclo = ?
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
                'message' => 'Ciclo no encontrado'
            ]);

            exit;
        }


        $stmt = $mysqli->prepare("
            SELECT
                cd.idciclo_detalle,
                cd.idciclo,
                cd.semana,
                cd.dia_semana,
                cd.idhorario,
                h.nombre AS horario_nombre
            FROM ciclo_detalle cd
            LEFT JOIN horarios h
                ON h.idhorario = cd.idhorario
            WHERE cd.idciclo = ?
            ORDER BY
                cd.semana ASC,
                cd.dia_semana ASC
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

        $detalle = [];

        while ($fila = $resultado->fetch_assoc()) {

            $fila['idciclo_detalle'] =
                (int)$fila['idciclo_detalle'];

            $fila['idciclo'] =
                (int)$fila['idciclo'];

            $fila['semana'] =
                (int)$fila['semana'];

            $fila['dia_semana'] =
                (int)$fila['dia_semana'];

            $fila['idhorario'] =
                $fila['idhorario'] !== null
                    ? (int)$fila['idhorario']
                    : null;

            $detalle[] = $fila;
        }

        $stmt->close();

        $ciclo['idciclo'] =
            (int)$ciclo['idciclo'];

        $ciclo['cantidad_semanas'] =
            (int)$ciclo['cantidad_semanas'];

        $ciclo['activo'] =
            (int)$ciclo['activo'];

        $ciclo['detalle'] = $detalle;


        echo json_encode([
            'status' => 'ok',
            'data' => $ciclo
        ]);

        exit;
    }


    /* -----------------------------------------------------
       LISTADO
    ----------------------------------------------------- */

    $stmt = $mysqli->prepare("
        SELECT
            idciclo,
            nombre,
            cantidad_semanas,
            activo
        FROM ciclos
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

        $fila['activo'] =
            (int)$fila['activo'];

        $datos[] = $fila;
    }

    $stmt->close();


    echo json_encode([
        'status' => 'ok',
        'data' => $datos
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
       CAMBIAR ESTADO
    ----------------------------------------------------- */

    if (
        isset($datos['accion']) &&
        $datos['accion'] === 'estado'
    ) {

        $idciclo = isset($datos['idciclo'])
            ? (int)$datos['idciclo']
            : 0;

        $activo = isset($datos['activo'])
            ? (int)$datos['activo']
            : 0;

        if ($idciclo <= 0) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Ciclo inválido'
            ]);

            exit;
        }

        if ($activo !== 0 && $activo !== 1) {

            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'message' => 'Estado inválido'
            ]);

            exit;
        }


        $stmt = $mysqli->prepare("
            UPDATE ciclos
            SET activo = ?
            WHERE idciclo = ?
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'ii',
            $activo,
            $idciclo
        );

        $stmt->execute();

        $stmt->close();


        echo json_encode([
            'status' => 'ok',
            'message' => 'Estado actualizado'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       GUARDAR CICLO
    ----------------------------------------------------- */

    $idciclo = isset($datos['idciclo']) &&
               $datos['idciclo'] !== null &&
               $datos['idciclo'] !== ''
        ? (int)$datos['idciclo']
        : 0;

    $nombre = isset($datos['nombre'])
        ? trim($datos['nombre'])
        : '';

    $cantidadSemanas = isset($datos['cantidad_semanas'])
        ? (int)$datos['cantidad_semanas']
        : 0;

    $detalle = isset($datos['detalle']) &&
               is_array($datos['detalle'])
        ? $datos['detalle']
        : [];


    if ($nombre === '') {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'Debe ingresar el nombre del ciclo'
        ]);

        exit;
    }


    if (
        $cantidadSemanas < 1 ||
        $cantidadSemanas > 52
    ) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'message' => 'La cantidad de semanas debe estar entre 1 y 52'
        ]);

        exit;
    }


    /* -----------------------------------------------------
       VALIDAR DETALLE
    ----------------------------------------------------- */

    $detalleNormalizado = [];

    foreach ($detalle as $fila) {

        $semana = isset($fila['semana'])
            ? (int)$fila['semana']
            : 0;

        $diaSemana = isset($fila['dia_semana'])
            ? (int)$fila['dia_semana']
            : 0;

        $idhorario = array_key_exists(
            'idhorario',
            $fila
        ) && $fila['idhorario'] !== null &&
          $fila['idhorario'] !== ''
            ? (int)$fila['idhorario']
            : null;


        if (
            $semana < 1 ||
            $semana > $cantidadSemanas
        ) {
            continue;
        }


        if (
            $diaSemana < 1 ||
            $diaSemana > 7
        ) {
            continue;
        }


        $clave = $semana . '-' . $diaSemana;

        $detalleNormalizado[$clave] = [
            'semana' => $semana,
            'dia_semana' => $diaSemana,
            'idhorario' => $idhorario
        ];
    }


    $mysqli->begin_transaction();

    try {

        /* -------------------------------------------------
           INSERTAR / ACTUALIZAR CABECERA
        ------------------------------------------------- */

        if ($idciclo > 0) {

            $stmt = $mysqli->prepare("
                UPDATE ciclos
                SET
                    nombre = ?,
                    cantidad_semanas = ?
                WHERE idciclo = ?
            ");

            if (!$stmt) {
                throw new Exception($mysqli->error);
            }

            $stmt->bind_param(
                'sii',
                $nombre,
                $cantidadSemanas,
                $idciclo
            );

            $stmt->execute();

            $stmt->close();

        } else {

            $activo = 1;

            $stmt = $mysqli->prepare("
                INSERT INTO ciclos
                (
                    nombre,
                    cantidad_semanas,
                    activo
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$stmt) {
                throw new Exception($mysqli->error);
            }

            $stmt->bind_param(
                'sii',
                $nombre,
                $cantidadSemanas,
                $activo
            );

            $stmt->execute();

            $idciclo =
                (int)$mysqli->insert_id;

            $stmt->close();
        }


        /* -------------------------------------------------
           VALIDAR HORARIOS
        ------------------------------------------------- */

        foreach ($detalleNormalizado as $fila) {

            if ($fila['idhorario'] === null) {
                continue;
            }

            $stmt = $mysqli->prepare("
                SELECT idhorario
                FROM horarios
                WHERE idhorario = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception($mysqli->error);
            }

            $stmt->bind_param(
                'i',
                $fila['idhorario']
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $existe = $resultado->fetch_assoc();

            $stmt->close();

            if (!$existe) {

                throw new Exception(
                    'Uno de los horarios seleccionados no existe'
                );
            }
        }


        /* -------------------------------------------------
           BORRAR DETALLE ACTUAL
        ------------------------------------------------- */

        $stmt = $mysqli->prepare("
            DELETE FROM ciclo_detalle
            WHERE idciclo = ?
        ");

        if (!$stmt) {
            throw new Exception($mysqli->error);
        }

        $stmt->bind_param(
            'i',
            $idciclo
        );

        $stmt->execute();

        $stmt->close();


        /* -------------------------------------------------
           INSERTAR DETALLE
        ------------------------------------------------- */

        $stmt = $mysqli->prepare("
            INSERT INTO ciclo_detalle
            (
                idciclo,
                semana,
                dia_semana,
                idhorario
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


        foreach ($detalleNormalizado as $fila) {

            $semana = $fila['semana'];

            $diaSemana = $fila['dia_semana'];

            $idhorario = $fila['idhorario'];

            $stmt->bind_param(
                'iiii',
                $idciclo,
                $semana,
                $diaSemana,
                $idhorario
            );

            $stmt->execute();
        }

        $stmt->close();


        $mysqli->commit();


        echo json_encode([
            'status' => 'ok',
            'message' => 'Ciclo guardado correctamente',
            'idciclo' => $idciclo
        ]);

        exit;

    } catch (Throwable $e) {

        $mysqli->rollback();

        throw $e;
    }
}


/* =========================================================
   MÉTODO NO PERMITIDO
========================================================= */

http_response_code(405);

echo json_encode([
    'status' => 'error',
    'message' => 'Método no permitido'
]);