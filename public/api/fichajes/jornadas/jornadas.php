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
   RESPUESTA
========================================================= */

function responderJornada(
    string $status,
    string $msg = '',
    $data = null
): void {

    echo json_encode(
        [
            'status' => $status,
            'msg'    => $msg,
            'data'   => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/* =========================================================
   AUTH
========================================================= */

$userAuth = validarTokenAPI($mysqli);

if (!$userAuth) {
    responderJornada(
        'error',
        'No autorizado.'
    );
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

    responderJornada(
        'error',
        'No se pudo determinar la empresa actual.'
    );
}

$mysqli = conectarDBEmpresa(
    $mysqli,
    (int)$empresa['idempresa'],
    'DATOS'
);


/* =========================================================
   ACCIÓN
========================================================= */

$accion = $_GET['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($body)) {
        $body = [];
    }

    $accion = $body['accion'] ?? '';
}


/* =========================================================
   LISTAR PENDIENTES
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    $accion === 'listar_pendientes'
) {

    /*
     * Se consideran únicamente marcas válidas.
     *
     * Una marca invalidada no participa de la cantidad
     * pendiente.
     */

    $sql = "
        SELECT
            e.idempleado,
            e.documento,
            e.apellido,
            e.nombre,
            x.fecha,
            x.cantidad_marcas,
            COALESCE(j.estado, 'pendiente') AS estado

        FROM empleados e

        INNER JOIN (

            SELECT
                idempleado,
                fecha,
                COUNT(*) AS cantidad_marcas

            FROM (

                SELECT
                    mr.idempleado,
                    mr.fecha,
                    mr.idmarca AS idmarca

                FROM marcas_reloj mr

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_reloj = mr.idmarca
                )

                UNION ALL

                SELECT
                    mm.idempleado,
                    mm.fecha,
                    mm.idmarca_manual AS idmarca

                FROM marcas_manuales mm

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_manual = mm.idmarca_manual
                )

            ) marcas_validas

            GROUP BY
                idempleado,
                fecha

        ) x
            ON x.idempleado = e.idempleado

        LEFT JOIN jornadas j
            ON j.idempleado = x.idempleado
            AND j.fecha = x.fecha

        WHERE
            e.activo = 1
            AND (
                j.idjornada IS NULL
                OR j.estado IN (
                    'pendiente',
                    'requiere_revision'
                )
            )

        ORDER BY
            x.fecha DESC,
            e.apellido ASC,
            e.nombre ASC
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {

        responderJornada(
            'error',
            'Error preparando la consulta de jornadas pendientes: ' .
            $mysqli->error
        );
    }

    if (!$stmt->execute()) {

        responderJornada(
            'error',
            'Error consultando las jornadas pendientes: ' .
            $stmt->error
        );
    }

    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {

        $data[] = [
            'idempleado'     => (int)$row['idempleado'],
            'documento'      => $row['documento'],
            'empleado'       =>
                $row['apellido'] .
                ', ' .
                $row['nombre'],
            'fecha'          => $row['fecha'],
            'cantidad_marcas'=> (int)$row['cantidad_marcas'],
            'estado'         => $row['estado']
        ];
    }

    $stmt->close();

    responderJornada(
        'ok',
        '',
        $data
    );
}


/* =========================================================
   DETALLE DE JORNADA
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    $accion === 'detalle'
) {

    $idempleado = (int)(
        $_GET['idempleado'] ?? 0
    );

    $fecha = trim(
        $_GET['fecha'] ?? ''
    );

    if (!$idempleado || !$fecha) {

        responderJornada(
            'error',
            'Faltan datos obligatorios.'
        );
    }

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $fecha
        )
    ) {

        responderJornada(
            'error',
            'La fecha no es válida.'
        );
    }


    /* =====================================================
       EMPLEADO
    ===================================================== */

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
        responderJornada(
            'error',
            'Error preparando empleado: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'i',
        $idempleado
    );

    $stmt->execute();

    $empleado = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    if (!$empleado) {

        responderJornada(
            'error',
            'No se encontró el empleado.'
        );
    }


    /* =====================================================
       MARCAS DE RELOJ
    ===================================================== */

    $marcas = [];

    $stmt = $mysqli->prepare("
        SELECT
            mr.idmarca,
            mr.idlector,
            mr.hora

        FROM marcas_reloj mr

        WHERE
            mr.idempleado = ?
            AND mr.fecha = ?

            AND NOT EXISTS (
                SELECT 1

                FROM marcas_invalidaciones mi

                WHERE
                    mi.idmarca_reloj = mr.idmarca
            )

        ORDER BY
            mr.hora ASC,
            mr.idmarca ASC
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando marcas de reloj: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $marcas[] = [
            'idmarca'  => (int)$row['idmarca'],
            'origen'   => 'reloj',
            'idlector' => (int)$row['idlector'],
            'hora'     => substr($row['hora'], 0, 5)
        ];
    }

    $stmt->close();


    /* =====================================================
       MARCAS MANUALES
    ===================================================== */

    $stmt = $mysqli->prepare("
        SELECT
            mm.idmarca_manual,
            mm.hora

        FROM marcas_manuales mm

        WHERE
            mm.idempleado = ?
            AND mm.fecha = ?

            AND NOT EXISTS (
                SELECT 1

                FROM marcas_invalidaciones mi

                WHERE
                    mi.idmarca_manual =
                        mm.idmarca_manual
            )

        ORDER BY
            mm.hora ASC,
            mm.idmarca_manual ASC
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando marcas manuales: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $marcas[] = [
            'idmarca'  =>
                (int)$row['idmarca_manual'],
            'origen'   => 'manual',
            'idlector' => null,
            'hora'     => substr($row['hora'], 0, 5)
        ];
    }

    $stmt->close();


    /*
     * Como las marcas vienen de dos tablas,
     * volvemos a ordenar cronológicamente.
     */

    usort(
        $marcas,
        function ($a, $b) {

            if ($a['hora'] === $b['hora']) {
                return $a['idmarca'] <=> $b['idmarca'];
            }

            return strcmp(
                $a['hora'],
                $b['hora']
            );
        }
    );


    /* =====================================================
       HORARIO
    ===================================================== */

    $horario = null;


    /*
     * 1. HORARIO ESPECIAL
     */

    $stmt = $mysqli->prepare("
        SELECT
            ehi.idhorario_especial,
            ehi.idhorario,
            h.nombre,
            h.tolerancia_minutos

        FROM empleado_horarios_especiales ehi

        INNER JOIN horarios h
            ON h.idhorario = ehi.idhorario

        WHERE
            ehi.idempleado = ?
            AND ehi.fecha = ?
            AND h.activo = 1

        LIMIT 1
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando horario especial: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    $stmt->execute();

    $especial = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if ($especial) {

        $horario = [
            'idhorario' =>
                (int)$especial['idhorario'],
            'nombre' =>
                $especial['nombre'],
            'tolerancia_minutos' =>
                (int)$especial['tolerancia_minutos'],
            'origen' =>
                'Horario especial',
            'detalle_origen' =>
                'Horario especial definido para esta fecha.',
            'tramos' => [],
            'descansos' => []
        ];

    } else {

        /*
         * 2. CICLO
         *
         * dia_semana:
         * lunes=1 ... domingo=7
         *
         * semana:
         * se calcula desde fecha_desde.
         */

        $stmt = $mysqli->prepare("
            SELECT
                ec.idempleado_ciclo,
                ec.idciclo,
                ec.fecha_desde,
                c.nombre AS ciclo_nombre,
                c.cantidad_semanas

            FROM empleado_ciclos ec

            INNER JOIN ciclos c
                ON c.idciclo = ec.idciclo

            WHERE
                ec.idempleado = ?
                AND ec.fecha_desde <= ?
                AND (
                    ec.fecha_hasta IS NULL
                    OR ec.fecha_hasta >= ?
                )
                AND c.activo = 1

            ORDER BY
                ec.fecha_desde DESC

            LIMIT 1
        ");

        if (!$stmt) {
            responderJornada(
                'error',
                'Error preparando ciclo: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'iss',
            $idempleado,
            $fecha,
            $fecha
        );

        $stmt->execute();

        $ciclo = $stmt
            ->get_result()
            ->fetch_assoc();

        $stmt->close();


        if ($ciclo) {

            $cantidadSemanas =
                max(
                    1,
                    (int)$ciclo['cantidad_semanas']
                );

            /*
             * Semana del ciclo.
             */

            $stmt = $mysqli->prepare("
                SELECT
                    DATEDIFF(?, ?) AS dias
            ");

            $stmt->bind_param(
                'ss',
                $fecha,
                $ciclo['fecha_desde']
            );

            $stmt->execute();

            $dias = (int)(
                $stmt
                    ->get_result()
                    ->fetch_assoc()['dias']
            );

            $stmt->close();

            $semana =
                (int)(
                    floor($dias / 7)
                ) % $cantidadSemanas + 1;


            /*
             * Lunes=1 ... Domingo=7
             */

            $diaSemana = (int)(
                (new DateTime($fecha))
                    ->format('N')
            );


            $stmt = $mysqli->prepare("
                SELECT
                    cd.idhorario,
                    h.nombre,
                    h.tolerancia_minutos

                FROM ciclo_detalle cd

                INNER JOIN horarios h
                    ON h.idhorario = cd.idhorario

                WHERE
                    cd.idciclo = ?
                    AND cd.semana = ?
                    AND cd.dia_semana = ?
                    AND h.activo = 1

                LIMIT 1
            ");

            if (!$stmt) {
                responderJornada(
                    'error',
                    'Error preparando detalle de ciclo: ' .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                'iii',
                $ciclo['idciclo'],
                $semana,
                $diaSemana
            );

            $stmt->execute();

            $horarioCiclo = $stmt
                ->get_result()
                ->fetch_assoc();

            $stmt->close();


            if ($horarioCiclo) {

                $horario = [
                    'idhorario' =>
                        (int)$horarioCiclo['idhorario'],

                    'nombre' =>
                        $horarioCiclo['nombre'],

                    'tolerancia_minutos' =>
                        (int)$horarioCiclo[
                            'tolerancia_minutos'
                        ],

                    'origen' =>
                        'Ciclo',

                    'detalle_origen' =>
                        'Ciclo: ' .
                        $ciclo['ciclo_nombre'] .
                        ' — Semana ' .
                        $semana,

                    'tramos' => [],
                    'descansos' => []
                ];
            }
        }
    }


    /* =====================================================
       TRAMOS DEL HORARIO
    ===================================================== */

    if ($horario) {

        $idhorario =
            (int)$horario['idhorario'];

        $stmt = $mysqli->prepare("
            SELECT
                idhorario_tramo,
                orden,
                hora_desde,
                hora_hasta,
                minutos_teoricos

            FROM horario_tramos

            WHERE idhorario = ?

            ORDER BY orden ASC
        ");

        if (!$stmt) {
            responderJornada(
                'error',
                'Error preparando tramos: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'i',
            $idhorario
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $horario['tramos'][] = [
                'idhorario_tramo' =>
                    (int)$row['idhorario_tramo'],

                'orden' =>
                    (int)$row['orden'],

                'hora_desde' =>
                    substr($row['hora_desde'], 0, 5),

                'hora_hasta' =>
                    substr($row['hora_hasta'], 0, 5),

                'minutos_teoricos' =>
                    (int)$row['minutos_teoricos']
            ];
        }

        $stmt->close();


        /*
         * DESCANSOS
         */

        $stmt = $mysqli->prepare("
            SELECT
                idhorario_descanso,
                orden,
                minutos_permitidos

            FROM horario_descansos

            WHERE
                idhorario = ?
                AND activo = 1

            ORDER BY orden ASC
        ");

        if (!$stmt) {
            responderJornada(
                'error',
                'Error preparando descansos: ' .
                $mysqli->error
            );
        }

        $stmt->bind_param(
            'i',
            $idhorario
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $horario['descansos'][] = [
                'idhorario_descanso' =>
                    (int)$row['idhorario_descanso'],

                'orden' =>
                    (int)$row['orden'],

                'minutos_permitidos' =>
                    (int)$row['minutos_permitidos']
            ];
        }

        $stmt->close();
    }


    /* =====================================================
       PERMISOS
    ===================================================== */

    $permisos = [];

    $stmt = $mysqli->prepare("
        SELECT
            idpermiso,
            hora_desde,
            hora_hasta,
            motivo

        FROM permisos_salida

        WHERE
            idempleado = ?
            AND fecha = ?

        ORDER BY
            hora_desde ASC,
            idpermiso ASC
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando permisos: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $permisos[] = [
            'idpermiso' =>
                (int)$row['idpermiso'],

            'hora_desde' =>
                $row['hora_desde']
                    ? substr($row['hora_desde'], 0, 5)
                    : null,

            'hora_hasta' =>
                $row['hora_hasta']
                    ? substr($row['hora_hasta'], 0, 5)
                    : null,

            'motivo' =>
                $row['motivo']
        ];
    }

    $stmt->close();


    /* =====================================================
       EXTRA PROGRAMADA
    ===================================================== */

    $extras = [];

    $stmt = $mysqli->prepare("
        SELECT
            idhorario_extra,
            hora_desde,
            hora_hasta,
            observaciones

        FROM empleado_horarios_extra

        WHERE
            idempleado = ?
            AND fecha = ?
            AND activo = 1

        ORDER BY
            hora_desde ASC,
            idhorario_extra ASC
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando horas extra: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $extras[] = [
            'idhorario_extra' =>
                (int)$row['idhorario_extra'],

            'hora_desde' =>
                substr($row['hora_desde'], 0, 5),

            'hora_hasta' =>
                substr($row['hora_hasta'], 0, 5),

            'observaciones' =>
                $row['observaciones']
        ];
    }

    $stmt->close();


    /* =====================================================
       RESPUESTA
    ===================================================== */

    responderJornada(
        'ok',
        '',
        [
            'empleado' => [
                'idempleado' =>
                    (int)$empleado['idempleado'],

                'documento' =>
                    $empleado['documento'],

                'nombre' =>
                    $empleado['nombre'],

                'apellido' =>
                    $empleado['apellido']
            ],

            'fecha' =>
                $fecha,

            'marcas' =>
                $marcas,

            'horario' =>
                $horario,

            'permisos' =>
                $permisos,

            'extras' =>
                $extras
        ]
    );
}


/* =========================================================
   GUARDAR MARCA MANUAL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $accion === 'guardar_marca_manual'
) {

    $idempleado = (int)(
        $body['idempleado'] ?? 0
    );

    $fecha = trim(
        $body['fecha'] ?? ''
    );

    $hora = trim(
        $body['hora'] ?? ''
    );


    if (!$idempleado || !$fecha || !$hora) {

        responderJornada(
            'error',
            'Faltan datos obligatorios.'
        );
    }


    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $fecha
        )
    ) {

        responderJornada(
            'error',
            'La fecha no es válida.'
        );
    }


    if (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $hora
        )
    ) {

        responderJornada(
            'error',
            'La hora no es válida. Utilice HH:mm.'
        );
    }


    /* =====================================================
       EMPLEADO
    ===================================================== */

    $stmt = $mysqli->prepare("
        SELECT
            idempleado

        FROM empleados

        WHERE
            idempleado = ?
            AND activo = 1

        LIMIT 1
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando empleado: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'i',
        $idempleado
    );

    $stmt->execute();

    $existeEmpleado =
        $stmt
            ->get_result()
            ->num_rows > 0;

    $stmt->close();

    if (!$existeEmpleado) {

        responderJornada(
            'error',
            'El empleado no existe o está inactivo.'
        );
    }


    /* =====================================================
       USUARIO
    ===================================================== */

    $idusuario =
        (int)(
            $userAuth['idusuario']
            ?? $userAuth['id']
            ?? 0
        );

    if (!$idusuario) {

        responderJornada(
            'error',
            'No se pudo determinar el usuario actual.'
        );
    }


    /* =====================================================
       INSERTAR
    ===================================================== */

    $stmt = $mysqli->prepare("
        INSERT INTO marcas_manuales (
            idempleado,
            fecha,
            hora,
            idusuario
        )
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {

        responderJornada(
            'error',
            'Error preparando fichaje manual: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'issi',
        $idempleado,
        $fecha,
        $hora,
        $idusuario
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        responderJornada(
            'error',
            'No se pudo guardar el fichaje manual: ' .
            $error
        );
    }

    $idmarcaManual =
        (int)$stmt->insert_id;

    $stmt->close();


    responderJornada(
        'ok',
        'Fichaje manual guardado correctamente.',
        [
            'idmarca_manual' =>
                $idmarcaManual
        ]
    );
}


/* =========================================================
   ACCIÓN NO ENCONTRADA
========================================================= */

responderJornada(
    'error',
    'Acción no válida.'
);