<?php
// /api/jornadas/jornada_detalle.php

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];


function responderDetalle(string $status, string $msg = '', $data = null): void
{
    echo json_encode([
        'status' => $status,
        'msg'    => $msg,
        'data'   => $data
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function consultarDetalle(mysqli $db, string $sql, string $tipos = '', array $params = []): array
{
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Error preparando consulta: ' . $db->error);
    }

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$params);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Error ejecutando consulta: ' . $error);
    }

    $result = $stmt->get_result();
    $filas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $filas;
}


/**
 * Obtiene el límite de madrugada correspondiente al horario efectivo
 * del empleado en una fecha concreta. Considera horario especial o ciclo.
 */
function jornadaLimiteMarcaMadrugada(mysqli $db, int $idempleado, string $fecha): ?string
{
    $idHorario = 0;
    $tolerancia = 0;

    $stmt = $db->prepare("
        SELECT h.idhorario, h.tolerancia_minutos
        FROM empleado_horarios_especiales ehi
        INNER JOIN horarios h ON h.idhorario = ehi.idhorario
        WHERE ehi.idempleado = ? AND ehi.fecha = ? AND h.activo = 1
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('is', $idempleado, $fecha);
    if (!$stmt->execute()) { $stmt->close(); return null; }
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($r) {
        $idHorario = (int)$r['idhorario'];
        $tolerancia = (int)$r['tolerancia_minutos'];
    }

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

try {

    // AUTENTICACIÓN Y BASE DE DATOS DE LA EMPRESA
    $userAuth = validarTokenAPI($mysqli);

    if (!$userAuth) {
        responderDetalle('error', 'No autorizado.');
    }

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa) {
        responderDetalle(
            'error',
            'No se pudo determinar la empresa actual.'
        );
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // PARÁMETROS DE LA CONSULTA
    $idempleado = filter_input(
        INPUT_GET,
        'idempleado',
        FILTER_VALIDATE_INT
    );

    $fecha = trim($_GET['fecha'] ?? '');

    if (!$idempleado || $idempleado <= 0) {
        responderDetalle('error', 'El id del empleado no es válido.');
    }

    $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);

    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
        responderDetalle('error', 'La fecha debe tener formato YYYY-MM-DD.');
    }

    /*
     * EMPLEADO
     */
    $empleados = consultarDetalle(
        $mysqli,
        "SELECT idempleado, documento, nombre, apellido, activo
         FROM empleados
         WHERE idempleado = ?
         LIMIT 1",
        'i',
        [$idempleado]
    );

    if (!$empleados) {
        responderDetalle('error', 'No se encontró el empleado.');
    }

    $empleado = $empleados[0];

    /*
     * ÚLTIMO RESULTADO GUARDADO DE LA JORNADA
     */
    $jornadas = consultarDetalle(
        $mysqli,
        "SELECT
            idjornada,
            idempleado,
            fecha,
            idhorario,
            minutos_trabajados,
            minutos_descanso,
            minutos_descanso_exceso,
            minutos_atraso,
            minutos_salida_anticipada,
            minutos_extra,
            estado,
            fecha_calculo
         FROM jornadas
         WHERE idempleado = ?
           AND fecha = ?
         ORDER BY idjornada DESC
         LIMIT 1",
        'is',
        [$idempleado, $fecha]
    );

    $jornada = $jornadas[0] ?? null;
    $idjornada = $jornada ? (int)$jornada['idjornada'] : 0;
    $idhorario = $jornada && $jornada['idhorario'] !== null
        ? (int)$jornada['idhorario']
        : 0;

    /*
     * HORARIO ASIGNADO AL RESULTADO
     */
    $horario = null;

    if ($idhorario > 0) {
        $horarios = consultarDetalle(
            $mysqli,
            "SELECT
                idhorario,
                nombre,
                tolerancia_minutos,
                cruza_medianoche
             FROM horarios
             WHERE idhorario = ?
             LIMIT 1",
            'i',
            [$idhorario]
        );

        $horario = $horarios[0] ?? null;

        if ($horario) {
            $horario['tramos'] = consultarDetalle(
                $mysqli,
                "SELECT
                    idhorario_tramo,
                    orden,
                    hora_desde,
                    hora_hasta,
                    minutos_teoricos
                 FROM horario_tramos
                 WHERE idhorario = ?
                 ORDER BY orden",
                'i',
                [$idhorario]
            );

            $horario['descansos'] = consultarDetalle(
                $mysqli,
                "SELECT
                    idhorario_descanso,
                    orden,
                    minutos_permitidos
                 FROM horario_descansos
                 WHERE idhorario = ?
                   AND activo = 1
                 ORDER BY orden",
                'i',
                [$idhorario]
            );
        }
    }

    /*
     * TRAMOS REALES CALCULADOS
     * Las fechas completas contemplan turnos que cruzan medianoche.
     */
    $tramos = [];
    $descansos = [];

    if ($idjornada > 0) {
        $tramos = consultarDetalle(
            $mysqli,
            "SELECT
                orden,
                tipo_tramo,
                hora_entrada,
                hora_salida,
                fecha_hora_entrada,
                fecha_hora_salida,
                minutos_trabajados
             FROM jornada_tramos
             WHERE idjornada = ?
             ORDER BY orden",
            'i',
            [$idjornada]
        );

        $descansos = consultarDetalle(
            $mysqli,
            "SELECT
                orden,
                hora_desde,
                hora_hasta,
                minutos_reales,
                minutos_permitidos,
                minutos_exceso
             FROM jornada_descansos
             WHERE idjornada = ?
             ORDER BY orden",
            'i',
            [$idjornada]
        );
    }

    /*
     * MARCAS UTILIZADAS POR EL CÁLCULO
     */
    $marcasUtilizadas = [];

    if ($idjornada > 0) {
        $marcasUtilizadas = consultarDetalle(
            $mysqli,
            "SELECT
                jm.orden,
                jm.tipo,
                jm.idmarca_reloj,
                jm.idmarca_manual,
                CASE
                    WHEN jm.idmarca_reloj IS NOT NULL THEN 'reloj'
                    WHEN jm.idmarca_manual IS NOT NULL THEN 'manual'
                END AS origen,
                COALESCE(mr.fecha, mm.fecha) AS fecha,
                COALESCE(mr.hora, mm.hora) AS hora,
                COALESCE(mr.idlector, NULL) AS idlector
             FROM jornada_marcas jm
             LEFT JOIN marcas_reloj mr
                ON mr.idmarca = jm.idmarca_reloj
             LEFT JOIN marcas_manuales mm
                ON mm.idmarca_manual = jm.idmarca_manual
             WHERE jm.idjornada = ?
             ORDER BY jm.orden",
            'i',
            [$idjornada]
        );
    }

    
    /*
     * MARCAS REGISTRADAS DEL DÍA SELECCIONADO
     * Si cruza medianoche, incluir las marcas del día siguiente
     * solamente hasta la salida + tolerancia + 60 segundos.
     */
    $incluirDiaSiguiente =
        (int)($horario['cruza_medianoche'] ?? 0) === 1;

    $fechaSiguiente = (clone $fechaObj)
        ->modify('+1 day')
        ->format('Y-m-d');

    $limiteMadrugada = null;

    if ($incluirDiaSiguiente && !empty($horario['tramos'])) {
        foreach ($horario['tramos'] as $tramoHorario) {
            if (
                $tramoHorario['hora_hasta'] <
                $tramoHorario['hora_desde']
            ) {
                $horaLimite = $tramoHorario['hora_hasta'];
                $partes = array_map(
                    'intval',
                    explode(':', $horaLimite)
                );

                $segundos =
                    ($partes[0] * 3600) +
                    ($partes[1] * 60) +
                    ($partes[2] ?? 0) +
                    ((int)$horario['tolerancia_minutos'] * 60) +
                    60;

                $segundos = min(86399, $segundos);

                $limiteMadrugada = sprintf(
                    '%02d:%02d:%02d',
                    intdiv($segundos, 3600),
                    intdiv($segundos % 3600, 60),
                    $segundos % 60
                );
            }
        }
    }

    $filtroFechasReloj = $incluirDiaSiguiente && $limiteMadrugada !== null
        ? '(mr.fecha = ? OR (mr.fecha = ? AND mr.hora <= ?))'
        : 'mr.fecha = ?';

    $filtroFechasManual = $incluirDiaSiguiente && $limiteMadrugada !== null
        ? '(mm.fecha = ? OR (mm.fecha = ? AND mm.hora <= ?))'
        : 'mm.fecha = ?';

    // Resolver el límite del turno que comenzó el día anterior ANTES de
    // preparar los parámetros SQL que lo utilizan.
    $fechaAnterior = (clone $fechaObj)
        ->modify('-1 day')
        ->format('Y-m-d');

    $limiteAnterior = jornadaLimiteMarcaMadrugada(
        $mysqli,
        $idempleado,
        $fechaAnterior
    );

    $excluirMadrugadaAnterior = $limiteAnterior !== null;

    if ($incluirDiaSiguiente && $limiteMadrugada !== null) {
        // 7 parámetros por cada SELECT del UNION: i + sss + iss.
        $tiposMarcas = 'isssississsiss';
        $paramsMarcas = [
            $idempleado, $fecha, $fechaSiguiente, $limiteMadrugada,
            (int)$excluirMadrugadaAnterior, $fecha, $limiteAnterior,
            $idempleado, $fecha, $fechaSiguiente, $limiteMadrugada,
            (int)$excluirMadrugadaAnterior, $fecha, $limiteAnterior
        ];
    } else {
        // 5 parámetros por cada SELECT del UNION: i + s + iss.
        $tiposMarcas = 'isissisiss';
        $paramsMarcas = [
            $idempleado, $fecha,
            (int)$excluirMadrugadaAnterior, $fecha, $limiteAnterior,
            $idempleado, $fecha,
            (int)$excluirMadrugadaAnterior, $fecha, $limiteAnterior
        ];
    }

    $marcasRegistradas = consultarDetalle(
        $mysqli,
        "SELECT *
         FROM (
            SELECT
                mr.idmarca AS idmarca,
                'reloj' AS origen,
                mr.idlector AS idlector,
                mr.fecha,
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
              AND $filtroFechasReloj
                AND (
                    ? = 0
                    OR mr.fecha <> ?
                    OR mr.hora > ?
                )
              AND TIMESTAMP(mr.fecha, mr.hora) <= NOW()

            UNION ALL

            SELECT
                mm.idmarca_manual AS idmarca,
                'manual' AS origen,
                NULL AS idlector,
                mm.fecha,
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
              AND $filtroFechasManual
                AND (
                    ? = 0
                    OR mm.fecha <> ?
                    OR mm.hora > ?
                )
              AND TIMESTAMP(mm.fecha, mm.hora) <= NOW()
         ) AS marcas
         ORDER BY fecha, hora, idmarca",
        $tiposMarcas,
        $paramsMarcas
    );


    /*
     * PERMISOS DE SALIDA
     */
    $permisos = consultarDetalle(
        $mysqli,
        "SELECT *
         FROM permisos_salida
         WHERE idempleado = ?
           AND fecha = ?
         ORDER BY hora_desde, idpermiso",
        'is',
        [$idempleado, $fecha]
    );

    /*
     * HORAS EXTRA PROGRAMADAS
     */
    $extras = consultarDetalle(
        $mysqli,
        "SELECT
            idhorario_extra,
            fecha,
            hora_desde,
            hora_hasta,
            observaciones
         FROM empleado_horarios_extra
         WHERE idempleado = ?
           AND fecha = ?
           AND activo = 1
         ORDER BY hora_desde, idhorario_extra",
        'is',
        [$idempleado, $fecha]
    );

    /*
     * AUTORIZACIONES DE USO DE HORAS COMPENSATORIAS
     */
    $utilizacionesCompensatorias = consultarDetalle(
        $mysqli,
        "SELECT
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
         ORDER BY idautorizacion",
        'is',
        [$idempleado, $fecha]
    );

    /*
     * RESPUESTA JSON
     */
    responderDetalle('ok', '', [
        'empleado' => [
            'idempleado' => (int)$empleado['idempleado'],
            'documento'  => $empleado['documento'],
            'nombre'     => $empleado['nombre'],
            'apellido'   => $empleado['apellido'],
            'activo'     => (int)$empleado['activo']
        ],
        'fecha' => $fecha,
        'jornada' => $jornada,
        'horario' => $horario,
        'tramos' => $tramos,
        'descansos' => $descansos,
        'marcas_utilizadas' => $marcasUtilizadas,
        'marcas_registradas' => $marcasRegistradas,
        'permisos' => $permisos,
        'extras' => $extras,
        'utilizaciones_compensatorias' => $utilizacionesCompensatorias
    ]);

} catch (Throwable $e) {
    error_log('jornada_detalle.php: ' . $e->getMessage());

    responderDetalle(
        'error',
        'No se pudo obtener el detalle de la jornada.'
    );
}
