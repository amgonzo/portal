<?php

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

header('Content-Type: application/json; charset=utf-8');

try {

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    $metodo = $_SERVER['REQUEST_METHOD'];

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */

    if ($metodo === 'GET') {

        $accion = $_GET['accion'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | LISTAR LECTORES ACTIVOS
        |--------------------------------------------------------------------------
        */

        if ($accion === 'lectores') {

            $sql = "
                SELECT
                    idlector,
                    nombre,
                    ip,
                    puerto,
                    ubicacion,
                    predeterminado
                FROM lectores
                WHERE activo = 1
                ORDER BY predeterminado DESC, nombre ASC
            ";

            $resultado = $mysqli->query($sql);

            if (!$resultado) {
                throw new Exception(
                    'Error al consultar los lectores: ' . $mysqli->error
                );
            }

            $lectores = [];

            while ($fila = $resultado->fetch_assoc()) {
                $lectores[] = $fila;
            }

            echo json_encode([
                'status' => 'ok',
                'data' => $lectores
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | LECTORES ASIGNADOS A UN EMPLEADO
        |--------------------------------------------------------------------------
        */

        if ($accion === 'empleado') {

            $idempleado = (int)($_GET['idempleado'] ?? 0);

            if ($idempleado <= 0) {
                throw new Exception('Empleado inválido.');
            }

            $stmt = $mysqli->prepare("
                SELECT
                    el.idlector
                FROM empleados_lectores el
                INNER JOIN lectores l
                    ON l.idlector = el.idlector
                WHERE el.idempleado = ?
                  AND el.activo = 1
                  AND l.activo = 1
                ORDER BY
                    l.predeterminado DESC,
                    l.nombre ASC
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error preparando consulta: ' . $mysqli->error
                );
            }

            $stmt->bind_param('i', $idempleado);

            $stmt->execute();

            $resultado = $stmt->get_result();

            $lectores = [];

            while ($fila = $resultado->fetch_assoc()) {
                $lectores[] = (int)$fila['idlector'];
            }

            $stmt->close();

            echo json_encode([
                'status' => 'ok',
                'data' => $lectores
            ]);

            exit;
        }

        throw new Exception('Acción GET no válida.');
    }

    /*
    |--------------------------------------------------------------------------
    | POST - ASIGNAR LECTORES
    |--------------------------------------------------------------------------
    */

    if ($metodo === 'POST') {

        $entrada = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($entrada)) {
            $entrada = $_POST;
        }

        $idempleado = (int)($entrada['idempleado'] ?? 0);

        if ($idempleado <= 0) {
            throw new Exception('Empleado inválido.');
        }

        $lectores = $entrada['lectores'] ?? [];

        if (!is_array($lectores)) {
            $lectores = [];
        }

        $lectores = array_values(
            array_unique(
                array_map('intval', $lectores)
            )
        );

        /*
        |--------------------------------------------------------------------------
        | VERIFICAR EMPLEADO
        |--------------------------------------------------------------------------
        */

        $stmt = $mysqli->prepare("
            SELECT idempleado
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Error preparando consulta de empleado: ' . $mysqli->error
            );
        }

        $stmt->bind_param('i', $idempleado);

        $stmt->execute();

        $resultado = $stmt->get_result();

        if (!$resultado->fetch_assoc()) {
            $stmt->close();

            throw new Exception('El empleado no existe.');
        }

        $stmt->close();

        /*
        |--------------------------------------------------------------------------
        | VALIDAR LECTORES ACTIVOS
        |--------------------------------------------------------------------------
        */

        if (count($lectores) > 0) {

            $placeholders = implode(
                ',',
                array_fill(0, count($lectores), '?')
            );

            $tipos = str_repeat('i', count($lectores));

            $sql = "
                SELECT idlector
                FROM lectores
                WHERE activo = 1
                  AND idlector IN ($placeholders)
            ";

            $stmt = $mysqli->prepare($sql);

            if (!$stmt) {
                throw new Exception(
                    'Error preparando validación de lectores: ' .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                $tipos,
                ...$lectores
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $lectoresValidos = [];

            while ($fila = $resultado->fetch_assoc()) {
                $lectoresValidos[] = (int)$fila['idlector'];
            }

            $stmt->close();

            sort($lectores);
            sort($lectoresValidos);

            if ($lectores !== $lectoresValidos) {
                throw new Exception(
                    'Uno o más lectores seleccionados no están activos.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | GUARDAR ASIGNACIONES
        |--------------------------------------------------------------------------
        */

        $mysqli->begin_transaction();

        try {

            /*
             * Desactivar las asignaciones actuales.
             */
            $stmt = $mysqli->prepare("
                UPDATE empleados_lectores
                SET activo = 0
                WHERE idempleado = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error preparando actualización: ' .
                    $mysqli->error
                );
            }

            $stmt->bind_param('i', $idempleado);

            $stmt->execute();

            $stmt->close();

            /*
             * Reactivar o insertar las asignaciones seleccionadas.
             *
             * La tabla usa PK compuesta:
             * (idempleado, idlector)
             */
            if (count($lectores) > 0) {

                $stmtBuscar = $mysqli->prepare("
                    SELECT
                        idempleado,
                        idlector
                    FROM empleados_lectores
                    WHERE idempleado = ?
                      AND idlector = ?
                    LIMIT 1
                ");

                $stmtInsertar = $mysqli->prepare("
                    INSERT INTO empleados_lectores (
                        idempleado,
                        idlector,
                        activo,
                        idusuario,
                        fecha_carga
                    )
                    VALUES (?, ?, 1, ?, NOW())
                ");

                $stmtActualizar = $mysqli->prepare("
                    UPDATE empleados_lectores
                    SET
                        activo = 1,
                        idusuario = ?,
                        fecha_carga = NOW()
                    WHERE idempleado = ?
                      AND idlector = ?
                ");

                if (
                    !$stmtBuscar ||
                    !$stmtInsertar ||
                    !$stmtActualizar
                ) {
                    throw new Exception(
                        'Error preparando operaciones de lectores.'
                    );
                }

                $idusuario = (int)(
                    $userAuth['idusuario']
                    ?? $userAuth['id']
                    ?? 0
                );

                foreach ($lectores as $idlector) {

                    /*
                     * Buscar por la PK compuesta.
                     */
                    $stmtBuscar->bind_param(
                        'ii',
                        $idempleado,
                        $idlector
                    );

                    $stmtBuscar->execute();

                    $resultado = $stmtBuscar->get_result();

                    $existente = $resultado->fetch_assoc();

                    if ($existente) {

                        /*
                         * Ya existe la relación:
                         * solamente se vuelve a activar.
                         */
                        $stmtActualizar->bind_param(
                            'iii',
                            $idusuario,
                            $idempleado,
                            $idlector
                        );

                        $stmtActualizar->execute();

                    } else {

                        /*
                         * No existe la relación: se inserta.
                         */
                        $stmtInsertar->bind_param(
                            'iii',
                            $idempleado,
                            $idlector,
                            $idusuario
                        );

                        $stmtInsertar->execute();
                    }
                }

                $stmtBuscar->close();
                $stmtInsertar->close();
                $stmtActualizar->close();
            }

            $mysqli->commit();

        } catch (Throwable $e) {

            $mysqli->rollback();

            throw $e;
        }

        echo json_encode([
            'status' => 'ok',
            'message' => 'Lectores asignados correctamente.'
        ]);

        exit;
    }

    throw new Exception('Método no permitido.');

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}