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
require_once $rutas['auditoria'];

function responder($status, $mensaje = '', $datos = [])
{
    http_response_code(
        $status === 'ok' ? 200 : 400
    );

    echo json_encode(
        array_merge(
            [
                'status' => $status,
                'mensaje' => $mensaje
            ],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {

    $userAuth = validarTokenAPI($mysqli);

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

    $metodo = $_SERVER['REQUEST_METHOD'];


    /*
     * =========================================================
     * GET
     * =========================================================
     */

    if ($metodo === 'GET') {

        $accion = $_GET['accion'] ?? 'calendario';


        /*
         * =====================================================
         * OBTENER EMPLEADO
         * =====================================================
         */

        if ($accion === 'empleado') {

            $idEmpleado = filter_input(
                INPUT_GET,
                'idempleado',
                FILTER_VALIDATE_INT
            );

            if (!$idEmpleado || $idEmpleado <= 0) {
                responder(
                    'error',
                    'Empleado inválido.'
                );
            }

            $stmt = $mysqli->prepare(
                "
                SELECT
                    idempleado,
                    documento,
                    nombre,
                    apellido,
                    tarjeta,
                    fecha_inicio,
                    activo
                FROM empleados
                WHERE idempleado = ?
                LIMIT 1
                "
            );

            if (!$stmt) {
                throw new Exception(
                    'Error preparando consulta de empleado: ' .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                'i',
                $idEmpleado
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $empleado = $resultado->fetch_assoc();

            $stmt->close();

            if (!$empleado) {
                responder(
                    'error',
                    'No se encontró el empleado.'
                );
            }

            responder(
                'ok',
                '',
                [
                    'empleado' => $empleado
                ]
            );
        }


        /*
         * =====================================================
         * CALENDARIO
         * =====================================================
         */

        $idEmpleado = filter_input(
            INPUT_GET,
            'idempleado',
            FILTER_VALIDATE_INT
        );

        $anio = filter_input(
            INPUT_GET,
            'anio',
            FILTER_VALIDATE_INT
        );

        if (!$idEmpleado || $idEmpleado <= 0) {
            responder(
                'error',
                'Empleado inválido.'
            );
        }

        if (
            !$anio ||
            $anio < 2000 ||
            $anio > 2100
        ) {
            responder(
                'error',
                'Año inválido.'
            );
        }


        /*
         * Verificar empleado
         */

        $stmt = $mysqli->prepare(
            "
            SELECT
                idempleado
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
            "
        );

        $stmt->bind_param(
            'i',
            $idEmpleado
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $empleado = $resultado->fetch_assoc();

        $stmt->close();

        if (!$empleado) {
            responder(
                'error',
                'No se encontró el empleado.'
            );
        }


        $fechaDesde = $anio . '-01-01';
        $fechaHasta = $anio . '-12-31';


        /*
         * Obtener calendario.
         *
         * Una fila de empleados_calendario representa
         * un día del empleado.
         */

        $stmt = $mysqli->prepare(
            "
            SELECT
                ec.idempleado_calendario,
                ec.idempleado,
                ec.fecha,
                ec.idhorario,
                ec.idtipo_asistencia,
                ec.idmarca_entrada,
                ec.idmarca_salida,
                ec.observaciones,

                ta.nombre AS nombre_asistencia,
                ta.abreviatura,
                ta.color_fondo,
                ta.color_texto

            FROM empleados_calendario ec

            LEFT JOIN tipos_asistencia ta
                ON ta.idtipo_asistencia =
                   ec.idtipo_asistencia

            WHERE ec.idempleado = ?
              AND ec.fecha BETWEEN ? AND ?

            ORDER BY ec.fecha
            "
        );

        if (!$stmt) {
            throw new Exception(
                'Error preparando calendario: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'iss',
            $idEmpleado,
            $fechaDesde,
            $fechaHasta
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $calendario = [];

        while ($fila = $resultado->fetch_assoc()) {

            $calendario[] = $fila;
        }

        $stmt->close();


        /*
         * =====================================================
         * ASISTENCIAS HORARIAS
         * =====================================================
         *
         * Las devolvemos separadas para no perderlas.
         */

        $stmt = $mysqli->prepare(
            "
            SELECT
                eca.idcalendario_asistencia,
                eca.idempleado_calendario,
                eca.idtipo_asistencia,
                eca.hora_desde,
                eca.hora_hasta,
                eca.observaciones,

                ta.nombre AS nombre_asistencia,
                ta.abreviatura,
                ta.color_fondo,
                ta.color_texto

            FROM empleados_calendario_asistencias eca

            INNER JOIN empleados_calendario ec
                ON ec.idempleado_calendario =
                   eca.idempleado_calendario

            LEFT JOIN tipos_asistencia ta
                ON ta.idtipo_asistencia =
                   eca.idtipo_asistencia

            WHERE ec.idempleado = ?
              AND ec.fecha BETWEEN ? AND ?

            ORDER BY
                ec.fecha,
                eca.hora_desde,
                eca.idcalendario_asistencia
            "
        );

        if (!$stmt) {
            throw new Exception(
                'Error preparando asistencias del calendario: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'iss',
            $idEmpleado,
            $fechaDesde,
            $fechaHasta
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $asistencias = [];

        while ($fila = $resultado->fetch_assoc()) {

            $asistencias[] = $fila;
        }

        $stmt->close();


        responder(
            'ok',
            '',
            [
                'idempleado' => $idEmpleado,
                'anio' => $anio,
                'calendario' => $calendario,
                'asistencias' => $asistencias
            ]
        );
    }


    /*
     * =========================================================
     * POST
     * =========================================================
     */

    if ($metodo === 'POST') {

        $entrada = file_get_contents(
            'php://input'
        );

        $datos = json_decode(
            $entrada,
            true
        );

        if (!is_array($datos)) {
            responder(
                'error',
                'Datos inválidos.'
            );
        }

        $accion = $datos['accion'] ?? '';

        $idEmpleado = isset($datos['idempleado'])
            ? (int)$datos['idempleado']
            : 0;

        if (
            !$idEmpleado ||
            $idEmpleado <= 0
        ) {
            responder(
                'error',
                'Empleado inválido.'
            );
        }


        /*
         * Verificar empleado.
         */

        $stmt = $mysqli->prepare(
            "
            SELECT
                idempleado,
                activo
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
            "
        );

        $stmt->bind_param(
            'i',
            $idEmpleado
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $empleado = $resultado->fetch_assoc();

        $stmt->close();

        if (!$empleado) {
            responder(
                'error',
                'No se encontró el empleado.'
            );
        }


        /*
         * =====================================================
         * GUARDAR ASISTENCIA
         * =====================================================
         */

        if ($accion === 'guardar') {

            $fecha = trim(
                (string)(
                    $datos['fecha'] ?? ''
                )
            );

            $idTipo = isset(
                $datos['idtipo_asistencia']
            )
                ? (int)$datos['idtipo_asistencia']
                : 0;


            if (
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha
                )
            ) {
                responder(
                    'error',
                    'Fecha inválida.'
                );
            }


            $fechaObjeto =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $fecha
                );


            if (
                !$fechaObjeto ||
                $fechaObjeto->format('Y-m-d') !== $fecha
            ) {
                responder(
                    'error',
                    'Fecha inválida.'
                );
            }


            if (
                !$idTipo ||
                $idTipo <= 0
            ) {
                responder(
                    'error',
                    'Tipo de asistencia inválido.'
                );
            }


            /*
             * Solo tipos activos.
             */

            $stmt = $mysqli->prepare(
                "
                SELECT
                    idtipo_asistencia,
                    nombre,
                    abreviatura,
                    color_fondo,
                    color_texto,
                    activo
                FROM tipos_asistencia
                WHERE idtipo_asistencia = ?
                  AND activo = 1
                LIMIT 1
                "
            );

            $stmt->bind_param(
                'i',
                $idTipo
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $tipo = $resultado->fetch_assoc();

            $stmt->close();


            if (!$tipo) {
                responder(
                    'error',
                    'El tipo de asistencia no está activo.'
                );
            }


            /*
             * Buscar día existente.
             */

            $stmt = $mysqli->prepare(
                "
                SELECT
                    idempleado_calendario
                FROM empleados_calendario
                WHERE idempleado = ?
                  AND fecha = ?
                LIMIT 1
                "
            );

            $stmt->bind_param(
                'is',
                $idEmpleado,
                $fecha
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $existente = $resultado->fetch_assoc();

            $stmt->close();


            $mysqli->begin_transaction();

            try {

                if ($existente) {

                    $idCalendario =
                        (int)$existente[
                            'idempleado_calendario'
                        ];


                    /*
                     * Solo modificamos la asistencia.
                     *
                     * Horario, marcas y observaciones
                     * permanecen intactos.
                     */

                    $stmt = $mysqli->prepare(
                        "
                        UPDATE empleados_calendario
                        SET idtipo_asistencia = ?
                        WHERE idempleado_calendario = ?
                        "
                    );

                    $stmt->bind_param(
                        'ii',
                        $idTipo,
                        $idCalendario
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            $stmt->error
                        );
                    }

                    $stmt->close();

                } else {

                    /*
                     * No existe el día.
                     *
                     * Creamos solamente la asistencia.
                     */

                    $stmt = $mysqli->prepare(
                        "
                        INSERT INTO empleados_calendario
                        (
                            idempleado,
                            fecha,
                            idtipo_asistencia
                        )
                        VALUES (?, ?, ?)
                        "
                    );

                    $stmt->bind_param(
                        'isi',
                        $idEmpleado,
                        $fecha,
                        $idTipo
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            $stmt->error
                        );
                    }

                    $idCalendario =
                        $stmt->insert_id;

                    $stmt->close();
                }


                $mysqli->commit();

            } catch (Throwable $e) {

                $mysqli->rollback();

                throw $e;
            }


            /*
             * Devolver registro actualizado.
             */

            $stmt = $mysqli->prepare(
                "
                SELECT
                    ec.idempleado_calendario,
                    ec.idempleado,
                    ec.fecha,
                    ec.idhorario,
                    ec.idtipo_asistencia,
                    ec.idmarca_entrada,
                    ec.idmarca_salida,
                    ec.observaciones,

                    ta.nombre AS nombre_asistencia,
                    ta.abreviatura,
                    ta.color_fondo,
                    ta.color_texto

                FROM empleados_calendario ec

                LEFT JOIN tipos_asistencia ta
                    ON ta.idtipo_asistencia =
                       ec.idtipo_asistencia

                WHERE ec.idempleado_calendario = ?
                LIMIT 1
                "
            );

            $stmt->bind_param(
                'i',
                $idCalendario
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $fila = $resultado->fetch_assoc();

            $stmt->close();


            responder(
                'ok',
                '',
                [
                    'calendario' => $fila
                ]
            );
        }


        /*
         * =====================================================
         * QUITAR ASISTENCIA
         * =====================================================
         */

        if ($accion === 'eliminar') {

            $fecha = trim(
                (string)(
                    $datos['fecha'] ?? ''
                )
            );


            if (
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fecha
                )
            ) {
                responder(
                    'error',
                    'Fecha inválida.'
                );
            }


            /*
             * Buscar registro del día.
             */

            $stmt = $mysqli->prepare(
                "
                SELECT
                    idempleado_calendario,
                    idhorario,
                    idmarca_entrada,
                    idmarca_salida,
                    observaciones,
                    idtipo_asistencia
                FROM empleados_calendario
                WHERE idempleado = ?
                  AND fecha = ?
                LIMIT 1
                "
            );

            $stmt->bind_param(
                'is',
                $idEmpleado,
                $fecha
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $fila = $resultado->fetch_assoc();

            $stmt->close();


            if (!$fila) {

                responder(
                    'ok',
                    'No había una asistencia registrada.'
                );

            }


            $idCalendario =
                (int)$fila[
                    'idempleado_calendario'
                ];


            $mysqli->begin_transaction();

            try {

                /*
                 * Primero quitamos únicamente
                 * la asistencia principal.
                 */

                $stmt = $mysqli->prepare(
                    "
                    UPDATE empleados_calendario
                    SET idtipo_asistencia = NULL
                    WHERE idempleado_calendario = ?
                    "
                );

                $stmt->bind_param(
                    'i',
                    $idCalendario
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        $stmt->error
                    );
                }

                $stmt->close();


                /*
                 * Verificar si existen asistencias
                 * horarias relacionadas.
                 */

                $stmt = $mysqli->prepare(
                    "
                    SELECT COUNT(*) AS cantidad
                    FROM empleados_calendario_asistencias
                    WHERE idempleado_calendario = ?
                    "
                );

                $stmt->bind_param(
                    'i',
                    $idCalendario
                );

                $stmt->execute();

                $resultado = $stmt->get_result();

                $cantidadAsistencias =
                    (int)$resultado
                        ->fetch_assoc()['cantidad'];

                $stmt->close();


                /*
                 * Si no queda ningún dato asociado
                 * al día, eliminamos la fila.
                 */

                $sinOtrosDatos =
                    empty($fila['idhorario']) &&
                    empty($fila['idmarca_entrada']) &&
                    empty($fila['idmarca_salida']) &&
                    (
                        $fila['observaciones'] === null ||
                        trim(
                            (string)$fila['observaciones']
                        ) === ''
                    ) &&
                    $cantidadAsistencias === 0;


                if ($sinOtrosDatos) {

                    $stmt = $mysqli->prepare(
                        "
                        DELETE FROM empleados_calendario
                        WHERE idempleado_calendario = ?
                        "
                    );

                    $stmt->bind_param(
                        'i',
                        $idCalendario
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            $stmt->error
                        );
                    }

                    $stmt->close();
                }


                $mysqli->commit();

            } catch (Throwable $e) {

                $mysqli->rollback();

                throw $e;
            }


            responder(
                'ok',
                'Asistencia eliminada correctamente.'
            );
        }


        responder(
            'error',
            'Acción no válida.'
        );
    }


    responder(
        'error',
        'Método HTTP no permitido.'
    );


} catch (Throwable $e) {

    error_log(
        'calendario_empleado.php: ' .
        $e->getMessage()
    );

    responder(
        'error',
        $e->getMessage()
    );
}