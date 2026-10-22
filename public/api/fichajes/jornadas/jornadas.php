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


/**
 * Indica si el horario efectivo del empleado en una fecha cruza medianoche.
 * Prioridad: horario especial; si no existe, horario del ciclo asignado.
 */
function jornadaCruzaMedianoche(mysqli $db, int $idempleado, string $fecha): bool
{
    // 1. Horario especial para esa fecha.
    $stmt = $db->prepare("
        SELECT h.cruza_medianoche
        FROM empleado_horarios_especiales ehi
        INNER JOIN horarios h ON h.idhorario = ehi.idhorario
        WHERE ehi.idempleado = ?
          AND ehi.fecha = ?
          AND h.activo = 1
        LIMIT 1
    ");

    if ($stmt) {
        $stmt->bind_param('is', $idempleado, $fecha);
        if ($stmt->execute()) {
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($fila !== null) {
                return (int)$fila['cruza_medianoche'] === 1;
            }
        } else {
            $stmt->close();
        }
    }

    // 2. Ciclo vigente para esa fecha.
    $stmt = $db->prepare("
        SELECT ec.idciclo, ec.fecha_desde, c.cantidad_semanas
        FROM empleado_ciclos ec
        INNER JOIN ciclos c ON c.idciclo = ec.idciclo
        WHERE ec.idempleado = ?
          AND ec.fecha_desde <= ?
          AND (ec.fecha_hasta IS NULL OR ec.fecha_hasta >= ?)
          AND c.activo = 1
        ORDER BY ec.fecha_desde DESC
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('iss', $idempleado, $fecha, $fecha);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $ciclo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ciclo) {
        return false;
    }

    $cantidadSemanas = max(1, (int)$ciclo['cantidad_semanas']);
    $dias = (int)((new DateTime($ciclo['fecha_desde']))->diff(new DateTime($fecha))->format('%r%a'));
    $semana = ((int)floor($dias / 7) % $cantidadSemanas) + 1;
    $diaSemana = (int)(new DateTime($fecha))->format('N'); // lunes=1 ... domingo=7

    $stmt = $db->prepare("
        SELECT h.cruza_medianoche
        FROM ciclo_detalle cd
        INNER JOIN horarios h ON h.idhorario = cd.idhorario
        WHERE cd.idciclo = ?
          AND cd.semana = ?
          AND cd.dia_semana = ?
          AND h.activo = 1
        LIMIT 1
    ");

    if (!$stmt) {
        return false;
    }

    $idciclo = (int)$ciclo['idciclo'];
    $stmt->bind_param('iii', $idciclo, $semana, $diaSemana);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $fila !== null && (int)$fila['cruza_medianoche'] === 1;
}

/**
 * Devuelve la hora límite para asignar marcas de madrugada al turno
 * nocturno iniciado en $fecha. Incluye tolerancia y 60 segundos, igual
 * que la regla que ya usaba el endpoint. Devuelve null si no cruza medianoche.
 */
function jornadaLimiteMarcaMadrugada(mysqli $db, int $idempleado, string $fecha): ?string
{
    $idHorario = 0;
    $tolerancia = 0;

    // Primero prevalece el horario especial de esa fecha.
    $stmt = $db->prepare("
        SELECT h.idhorario, h.tolerancia_minutos
        FROM empleado_horarios_especiales ehi
        INNER JOIN horarios h ON h.idhorario = ehi.idhorario
        WHERE ehi.idempleado = ? AND ehi.fecha = ? AND h.activo = 1
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('is', $idempleado, $fecha);
        if ($stmt->execute()) {
            $r = $stmt->get_result()->fetch_assoc();
            if ($r) {
                $idHorario = (int)$r['idhorario'];
                $tolerancia = (int)$r['tolerancia_minutos'];
            }
        }
        $stmt->close();
    }

    // Si no hay horario especial, resolver el horario del ciclo vigente.
    if ($idHorario <= 0) {
        $stmt = $db->prepare("
            SELECT ec.idciclo, ec.fecha_desde, c.cantidad_semanas
            FROM empleado_ciclos ec
            INNER JOIN ciclos c ON c.idciclo = ec.idciclo
            WHERE ec.idempleado = ? AND ec.fecha_desde <= ?
              AND (ec.fecha_hasta IS NULL OR ec.fecha_hasta >= ?)
              AND c.activo = 1
            ORDER BY ec.fecha_desde DESC LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param('iss', $idempleado, $fecha, $fecha);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $ciclo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$ciclo) return null;

        $cantidadSemanas = max(1, (int)$ciclo['cantidad_semanas']);
        $dias = (int)((new DateTime($ciclo['fecha_desde']))->diff(new DateTime($fecha))->format('%r%a'));
        $semana = ((int)floor($dias / 7) % $cantidadSemanas) + 1;
        $diaSemana = (int)(new DateTime($fecha))->format('N');
        $idCiclo = (int)$ciclo['idciclo'];

        $stmt = $db->prepare("
            SELECT h.idhorario, h.tolerancia_minutos
            FROM ciclo_detalle cd
            INNER JOIN horarios h ON h.idhorario = cd.idhorario
            WHERE cd.idciclo = ? AND cd.semana = ? AND cd.dia_semana = ?
              AND h.activo = 1
            LIMIT 1
        ");
        if (!$stmt) return null;
        $stmt->bind_param('iii', $idCiclo, $semana, $diaSemana);
        if (!$stmt->execute()) { $stmt->close(); return null; }
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$r) return null;
        $idHorario = (int)$r['idhorario'];
        $tolerancia = (int)$r['tolerancia_minutos'];
    }

    $stmt = $db->prepare("
        SELECT hora_hasta
        FROM horario_tramos
        WHERE idhorario = ? AND hora_hasta < hora_desde
        ORDER BY orden DESC LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $idHorario);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $tramo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$tramo || empty($tramo['hora_hasta'])) return null;

    $partes = explode(':', $tramo['hora_hasta']);
    $segundos = ((int)$partes[0] * 3600) + ((int)$partes[1] * 60) + (int)($partes[2] ?? 0);
    $segundos += max(0, $tolerancia) * 60 + 60;
    $segundos = min(86399, $segundos);
    return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
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
            SELECT idempleado, fecha, COUNT(*) AS cantidad_marcas
            FROM (
                SELECT mr.idempleado, mr.fecha, mr.idmarca AS idmarca
                FROM marcas_reloj mr
                WHERE TIMESTAMP(mr.fecha, mr.hora) <= NOW()
                  AND NOT EXISTS (
                    SELECT 1 FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_reloj = mr.idmarca AND mi.fecha_recuperacion IS NULL
                )
                UNION ALL
                SELECT mm.idempleado, mm.fecha, mm.idmarca_manual AS idmarca
                FROM marcas_manuales mm
                WHERE TIMESTAMP(mm.fecha, mm.hora) <= NOW()
                  AND NOT EXISTS (
                    SELECT 1 FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_manual = mm.idmarca_manual AND mi.fecha_recuperacion IS NULL
                )
            ) marcas_validas
            GROUP BY idempleado, fecha
        ) x ON x.idempleado = e.idempleado
        LEFT JOIN jornadas j
            ON j.idempleado = x.idempleado
            AND j.fecha = x.fecha
        WHERE e.activo = 1
          AND (
              j.idjornada IS NULL
              OR j.estado IN ('pendiente', 'requiere_revision')
          )
        ORDER BY x.fecha DESC, e.apellido ASC, e.nombre ASC
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
        $idEmpleadoFila = (int)$row['idempleado'];
        $fechaFila = $row['fecha'];
        $cantidadMarcas = (int)$row['cantidad_marcas'];

        // Las marcas de madrugada que están dentro del límite del turno
        // anterior no se cuentan como marcas de la fecha física actual.
        $fechaAnterior = (new DateTime($fechaFila))->modify('-1 day')->format('Y-m-d');
        $limiteAnterior = jornadaLimiteMarcaMadrugada($mysqli, $idEmpleadoFila, $fechaAnterior);

        if ($limiteAnterior !== null) {
            $stmtMadrugadaAnterior = $mysqli->prepare("
                SELECT COUNT(*) AS cantidad FROM (
                    SELECT mr.idmarca
                    FROM marcas_reloj mr
                    WHERE mr.idempleado = ? AND mr.fecha = ? AND mr.hora <= ?
                      AND TIMESTAMP(mr.fecha, mr.hora) <= NOW()
                      AND NOT EXISTS (SELECT 1 FROM marcas_invalidaciones mi WHERE mi.idmarca_reloj = mr.idmarca AND mi.fecha_recuperacion IS NULL)
                    UNION ALL
                    SELECT mm.idmarca_manual AS idmarca
                    FROM marcas_manuales mm
                    WHERE mm.idempleado = ? AND mm.fecha = ? AND mm.hora <= ?
                      AND TIMESTAMP(mm.fecha, mm.hora) <= NOW()
                      AND NOT EXISTS (SELECT 1 FROM marcas_invalidaciones mi WHERE mi.idmarca_manual = mm.idmarca_manual AND mi.fecha_recuperacion IS NULL)
                ) m
            ");
            if ($stmtMadrugadaAnterior) {
                $stmtMadrugadaAnterior->bind_param('ississ', $idEmpleadoFila, $fechaFila, $limiteAnterior, $idEmpleadoFila, $fechaFila, $limiteAnterior);
                if ($stmtMadrugadaAnterior->execute()) {
                    $rMadrugada = $stmtMadrugadaAnterior->get_result()->fetch_assoc();
                    $cantidadMarcas -= (int)($rMadrugada['cantidad'] ?? 0);
                }
                $stmtMadrugadaAnterior->close();
            }
        }

        // Si no queda ninguna marca propia de esta fecha, no listar una jornada vacía.
        // Esto también evita mostrar el día 20 antes de que ocurra la entrada de las 17:27.
        if ($cantidadMarcas <= 0) {
            continue;
        }

        // Para un turno nocturno, sumar solo las marcas de madrugada del día siguiente
        // que estén dentro del horario de salida + tolerancia y que ya hayan ocurrido.
        $limiteActual = jornadaLimiteMarcaMadrugada($mysqli, $idEmpleadoFila, $fechaFila);
        if ($limiteActual !== null) {
            $fechaSiguiente = (new DateTime($fechaFila))->modify('+1 day')->format('Y-m-d');
            $stmtSiguientes = $mysqli->prepare("
                SELECT COUNT(*) AS cantidad FROM (
                    SELECT mr.idmarca
                    FROM marcas_reloj mr
                    WHERE mr.idempleado = ? AND mr.fecha = ? AND mr.hora <= ?
                      AND TIMESTAMP(mr.fecha, mr.hora) <= NOW()
                      AND NOT EXISTS (SELECT 1 FROM marcas_invalidaciones mi WHERE mi.idmarca_reloj = mr.idmarca AND mi.fecha_recuperacion IS NULL)
                    UNION ALL
                    SELECT mm.idmarca_manual AS idmarca
                    FROM marcas_manuales mm
                    WHERE mm.idempleado = ? AND mm.fecha = ? AND mm.hora <= ?
                      AND TIMESTAMP(mm.fecha, mm.hora) <= NOW()
                      AND NOT EXISTS (SELECT 1 FROM marcas_invalidaciones mi WHERE mi.idmarca_manual = mm.idmarca_manual AND mi.fecha_recuperacion IS NULL)
                ) m
            ");
            if ($stmtSiguientes) {
                $stmtSiguientes->bind_param('ississ', $idEmpleadoFila, $fechaSiguiente, $limiteActual, $idEmpleadoFila, $fechaSiguiente, $limiteActual);
                if ($stmtSiguientes->execute()) {
                    $rSiguientes = $stmtSiguientes->get_result()->fetch_assoc();
                    $cantidadMarcas += (int)($rSiguientes['cantidad'] ?? 0);
                }
                $stmtSiguientes->close();
            }
        }

        $data[] = [
            'idempleado'      => $idEmpleadoFila,
            'documento'       => $row['documento'],
            'empleado'        => $row['apellido'] . ', ' . $row['nombre'],
            'fecha'           => $fechaFila,
            'cantidad_marcas' => $cantidadMarcas,
            'estado'          => $row['estado']
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
    TODAS LAS MARCAS: VÁLIDAS E INVALIDADAS
    ===================================================== */

    $marcas = [];

    $stmt = $mysqli->prepare("
        SELECT
            mr.idmarca AS idmarca,
            'reloj' AS origen,
            mr.idlector AS idlector,
            mr.hora,
            mi.idinvalidacion,
            mi.motivo,
            CASE
                WHEN mi.idinvalidacion IS NULL THEN 0
                ELSE 1
            END AS invalidada
        FROM marcas_reloj mr
        LEFT JOIN marcas_invalidaciones mi
            ON mi.idmarca_reloj = mr.idmarca
            AND mi.fecha_recuperacion IS NULL
        WHERE mr.idempleado = ?
        AND mr.fecha = ?
        AND TIMESTAMP(mr.fecha, mr.hora) <= NOW()

        UNION ALL

        SELECT
            mm.idmarca_manual AS idmarca,
            'manual' AS origen,
            NULL AS idlector,
            mm.hora,
            mi.idinvalidacion,
            mi.motivo,
            CASE
                WHEN mi.idinvalidacion IS NULL THEN 0
                ELSE 1
            END AS invalidada
        FROM marcas_manuales mm
        LEFT JOIN marcas_invalidaciones mi
            ON mi.idmarca_manual = mm.idmarca_manual
            AND mi.fecha_recuperacion IS NULL
        WHERE mm.idempleado = ?
        AND mm.fecha = ?
        AND TIMESTAMP(mm.fecha, mm.hora) <= NOW()

        ORDER BY hora ASC, idmarca ASC
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando la consulta de fichajes: ' . $mysqli->error
        );
    }

    $stmt->bind_param(
        'isis',
        $idempleado,
        $fecha,
        $idempleado,
        $fecha
    );

    if (!$stmt->execute()) {
        responderJornada(
            'error',
            'Error consultando los fichajes: ' . $stmt->error
        );
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $marcas[] = [
            'idmarca'       => (int)$row['idmarca'],
            'origen'        => $row['origen'],
            'idlector'      => $row['idlector'] !== null
                ? (int)$row['idlector']
                : null,
            'hora'          => substr($row['hora'], 0, 5),
            'invalidada'    => (int)$row['invalidada'] === 1,
            'idinvalidacion' => $row['idinvalidacion'] !== null
                ? (int)$row['idinvalidacion']
                : null,
            'motivo'        => $row['motivo']
        ];
    }

    $stmt->close();


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
    UTILIZACIONES DE HORAS COMPENSATORIAS
    ===================================================== */

    $utilizacionesCompensatorias = [];

    $sqlUtilizaciones = "
        SELECT
            idautorizacion,
            tipo_operacion,
            fecha_operacion,
            minutos_autorizados,
            estado,
            motivo,
            observaciones
        FROM horas_compensacion_autorizaciones
        WHERE idempleado = ?
        AND fecha_operacion = ?
        AND tipo_operacion = 'utilizacion'
        AND estado = 'autorizada'
        ORDER BY idautorizacion ASC
    ";

    $stmt = $mysqli->prepare($sqlUtilizaciones);

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando utilizaciones compensatorias: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        'is',
        $idempleado,
        $fecha
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        responderJornada(
            'error',
            'Error consultando utilizaciones compensatorias: ' .
            $error
        );
    }

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $utilizacionesCompensatorias[] = [
            'idautorizacion' =>
                (int)$row['idautorizacion'],

            'tipo_operacion' =>
                $row['tipo_operacion'],

            'fecha_operacion' =>
                $row['fecha_operacion'],

            'minutos_autorizados' =>
                (int)$row['minutos_autorizados'],

            'estado' =>
                $row['estado'],

            'motivo' =>
                $row['motivo'],

            'observaciones' =>
                $row['observaciones']
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

    /* MARCAS DEL DÍA SIGUIENTE SI EL HORARIO CRUZA MEDIANOCHE */
    if ($horario && !empty($horario['idhorario'])) {
        $cruzaMedianoche = false;
        $stmtCruce = $mysqli->prepare("
            SELECT cruza_medianoche
            FROM horarios
            WHERE idhorario = ?
            LIMIT 1
        ");

        if ($stmtCruce) {
            $idHorarioCruce = (int)$horario['idhorario'];
            $stmtCruce->bind_param('i', $idHorarioCruce);
            if ($stmtCruce->execute()) {
                $filaCruce = $stmtCruce->get_result()->fetch_assoc();
                $cruzaMedianoche = !empty($filaCruce)
                    && (int)$filaCruce['cruza_medianoche'] === 1;
            }
            $stmtCruce->close();
        }

        if ($cruzaMedianoche) {
            // Las marcas de madrugada del día seleccionado pueden pertenecer
            // al turno nocturno iniciado el día anterior. Quitarlas del detalle
            // actual usando el horario efectivo de esa fecha anterior.
            $fechaAnterior = (new DateTime($fecha))->modify('-1 day')->format('Y-m-d');
            $limiteAnterior = jornadaLimiteMarcaMadrugada($mysqli, $idempleado, $fechaAnterior);
            if ($limiteAnterior !== null) {
                $marcas = array_values(array_filter($marcas, function ($marca) use ($limiteAnterior) {
                    return $marca['hora'] > substr($limiteAnterior, 0, 5);
                }));
            }

            $fechaSiguiente = (new DateTime($fecha))->modify('+1 day')->format('Y-m-d');

            $consultasMarcasSiguientes = [
                [
                    'sql' => "SELECT mr.idmarca AS idmarca, mr.idlector AS idlector, mr.hora AS hora
                              FROM marcas_reloj mr
                              WHERE mr.idempleado = ? AND mr.fecha = ?
                                AND TIMESTAMP(mr.fecha, mr.hora) <= NOW()
                                AND TIME_TO_SEC(mr.hora) <= COALESCE((
                                    SELECT TIME_TO_SEC(ht.hora_hasta) + (h.tolerancia_minutos * 60) + 60
                                    FROM horario_tramos ht
                                    INNER JOIN horarios h ON h.idhorario = ht.idhorario
                                    WHERE ht.idhorario = ? AND ht.hora_hasta < ht.hora_desde
                                    ORDER BY ht.orden DESC LIMIT 1
                                ), 0)
                                AND NOT EXISTS (
                                    SELECT 1 FROM marcas_invalidaciones mi
                                    WHERE mi.idmarca_reloj = mr.idmarca AND mi.fecha_recuperacion IS NULL
                                )
                              ORDER BY mr.hora ASC, mr.idmarca ASC",
                    'origen' => 'reloj',
                    'incluye_horario' => true
                ],
                [
                    'sql' => "SELECT mm.idmarca_manual AS idmarca, NULL AS idlector, mm.hora AS hora
                              FROM marcas_manuales mm
                              WHERE mm.idempleado = ? AND mm.fecha = ?
                                AND TIMESTAMP(mm.fecha, mm.hora) <= NOW()
                                AND TIME_TO_SEC(mm.hora) <= COALESCE((
                                    SELECT TIME_TO_SEC(ht.hora_hasta) + (h.tolerancia_minutos * 60) + 60
                                    FROM horario_tramos ht
                                    INNER JOIN horarios h ON h.idhorario = ht.idhorario
                                    WHERE ht.idhorario = ? AND ht.hora_hasta < ht.hora_desde
                                    ORDER BY ht.orden DESC LIMIT 1
                                ), 0)
                                AND NOT EXISTS (
                                    SELECT 1 FROM marcas_invalidaciones mi
                                    WHERE mi.idmarca_manual = mm.idmarca_manual AND mi.fecha_recuperacion IS NULL
                                )
                              ORDER BY mm.hora ASC, mm.idmarca_manual ASC",
                    'origen' => 'manual',
                    'incluye_horario' => true
                ]
            ];

            foreach ($consultasMarcasSiguientes as $consulta) {
                $stmtSig = $mysqli->prepare($consulta['sql']);
                if (!$stmtSig) {
                    continue;
                }

                $idHorarioConsulta = (int)$horario['idhorario'];
                $stmtSig->bind_param('isi', $idempleado, $fechaSiguiente, $idHorarioConsulta);
                if ($stmtSig->execute()) {
                    $resSig = $stmtSig->get_result();
                    while ($fila = $resSig->fetch_assoc()) {
                        $marcas[] = [
                            'idmarca' => (int)$fila['idmarca'],
                            'origen' => $consulta['origen'],
                            'idlector' => $fila['idlector'] !== null ? (int)$fila['idlector'] : null,
                            'hora' => substr($fila['hora'], 0, 5),
                            'fecha' => $fechaSiguiente
                        ];
                    }
                }
                $stmtSig->close();
            }

            // En el orden del turno, las marcas posteriores a medianoche van al final.
            usort($marcas, function ($a, $b) {
                $madrugadaA = $a['hora'] < '12:00' ? 1 : 0;
                $madrugadaB = $b['hora'] < '12:00' ? 1 : 0;

                if ($madrugadaA !== $madrugadaB) {
                    return $madrugadaA <=> $madrugadaB;
                }
                if ($a['hora'] === $b['hora']) {
                    return $a['idmarca'] <=> $b['idmarca'];
                }
                return strcmp($a['hora'], $b['hora']);
            });
        }
    }

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

            'utilizaciones_compensatorias' =>
                $utilizacionesCompensatorias,
                
            'extras' =>
                $extras
        ]
    );
}



/* =====================================================
   INVALIDAR FICHAJE
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $accion === 'invalidar_marca'
) {
    $origen = trim($body['origen'] ?? '');
    $idmarca = (int)($body['idmarca'] ?? 0);
    $motivo = trim($body['motivo'] ?? '');

    if (
        !in_array($origen, ['reloj', 'manual'], true) ||
        $idmarca <= 0 ||
        $motivo === ''
    ) {
        responderJornada(
            'error',
            'Debe indicar una marca válida y el motivo de la invalidación.'
        );
    }

    if (mb_strlen($motivo) > 255) {
        responderJornada(
            'error',
            'El motivo no puede superar los 255 caracteres.'
        );
    }

    $idusuario = (int)(
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

    $campoId = $origen === 'reloj'
        ? 'idmarca_reloj'
        : 'idmarca_manual';

    $tablaMarca = $origen === 'reloj'
        ? 'marcas_reloj'
        : 'marcas_manuales';

    $campoMarca = $origen === 'reloj'
        ? 'idmarca'
        : 'idmarca_manual';

    // Verificar que la marca exista.
    $stmt = $mysqli->prepare("
        SELECT {$campoMarca}
        FROM {$tablaMarca}
        WHERE {$campoMarca} = ?
        LIMIT 1
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando la consulta de la marca.'
        );
    }

    $stmt->bind_param('i', $idmarca);
    $stmt->execute();

    $existe = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if (!$existe) {
        responderJornada(
            'error',
            'El fichaje indicado no existe.'
        );
    }

    // No permitir invalidaciones duplicadas.
    $stmt = $mysqli->prepare("
        SELECT idinvalidacion
        FROM marcas_invalidaciones
        WHERE {$campoId} = ?
        AND fecha_recuperacion IS NULL
        LIMIT 1
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error verificando la invalidación.'
        );
    }

    $stmt->bind_param('i', $idmarca);
    $stmt->execute();

    $yaInvalidada = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($yaInvalidada) {
        responderJornada(
            'error',
            'Este fichaje ya está invalidado.'
        );
    }

    // Registrar la invalidación sin borrar el fichaje original.
    $sql = "
        INSERT INTO marcas_invalidaciones (
            {$campoId},
            idusuario,
            motivo
        )
        VALUES (?, ?, ?)
    ";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando la invalidación.'
        );
    }

    $stmt->bind_param(
        'iis',
        $idmarca,
        $idusuario,
        $motivo
    );

    if (!$stmt->execute()) {
        $stmt->close();

        responderJornada(
            'error',
            'No se pudo registrar la invalidación.'
        );
    }

    $stmt->close();

    responderJornada(
        'ok',
        'Fichaje invalidado correctamente.'
    );
}


/* =====================================================
   RECUPERAR FICHAJE INVALIDADO
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $accion === 'recuperar_marca'
) {
    $idinvalidacion = (int)(
        $body['idinvalidacion'] ?? 0
    );

    $motivoRecuperacion = trim(
        $body['motivo'] ?? ''
    );

    $idusuario = (int)(
        $userAuth['idusuario']
        ?? $userAuth['id']
        ?? 0
    );

    if (!$idinvalidacion || !$idusuario) {
        responderJornada(
            'error',
            'Datos de recuperación inválidos.'
        );
    }

    if ($motivoRecuperacion === '') {
        responderJornada(
            'error',
            'Debe indicar el motivo de la recuperación.'
        );
    }

    if (mb_strlen($motivoRecuperacion) > 255) {
        responderJornada(
            'error',
            'El motivo no puede superar los 255 caracteres.'
        );
    }

    $stmt = $mysqli->prepare("
        UPDATE marcas_invalidaciones
        SET
            idusuario_recuperacion = ?,
            fecha_recuperacion = NOW(),
            motivo_recuperacion = ?
        WHERE idinvalidacion = ?
          AND fecha_recuperacion IS NULL
    ");

    if (!$stmt) {
        responderJornada(
            'error',
            'Error preparando la recuperación.'
        );
    }

    $stmt->bind_param(
        'isi',
        $idusuario,
        $motivoRecuperacion,
        $idinvalidacion
    );

    if (!$stmt->execute()) {
        $stmt->close();

        responderJornada(
            'error',
            'No se pudo recuperar el fichaje.'
        );
    }

    $recuperada = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$recuperada) {
        responderJornada(
            'error',
            'La invalidación no existe o ya fue recuperada.'
        );
    }

    responderJornada(
        'ok',
        'Fichaje recuperado correctamente.'
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