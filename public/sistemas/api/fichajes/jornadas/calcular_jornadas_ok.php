<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================================================
   CONEXIÓN Y AUTENTICACIÓN
========================================================= */

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Throwable $e) {
    // Puede que el entorno ya esté cargado.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];
require_once $rutas['compensacion_horas'];

/* =========================================================
   RESPUESTA JSON
========================================================= */

function responderCalculo(
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
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* =========================================================
   UTILIDADES
========================================================= */

function fechaValidaCalculo(string $fecha): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $fecha);

    return $dt !== false && $dt->format('Y-m-d') === $fecha;
}

function minutosHora(?string $hora): ?int
{
    if ($hora === null || $hora === '') {
        return null;
    }

    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $hora)) {
        return null;
    }

    $partes = explode(':', $hora);

    return ((int)$partes[0] * 60) + (int)$partes[1];
}

function horaSQL(int $minutos): string
{
    $minutos = max(0, min(1439, $minutos));

    return sprintf(
        '%02d:%02d:00',
        intdiv($minutos, 60),
        $minutos % 60
    );
}

function minutosIntervalo(int $desde, int $hasta): int
{
    return max(0, $hasta - $desde);
}

function interseccionMinutos(
    int $desde1,
    int $hasta1,
    int $desde2,
    int $hasta2
): int {
    return max(
        0,
        min($hasta1, $hasta2) - max($desde1, $desde2)
    );
}

function consultarFilas(mysqli $db, string $sql, string $tipos = '', array $params = []): array
{
    $stmt = $db->prepare($sql);

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();
    $filas = $result->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    return $filas;
}

function consultarFila(mysqli $db, string $sql, string $tipos = '', array $params = []): ?array
{
    $filas = consultarFilas($db, $sql, $tipos, $params);

    return $filas[0] ?? null;
}

function ejecutarSQL(mysqli $db, string $sql, string $tipos = '', array $params = []): int
{
    $stmt = $db->prepare($sql);

    if ($tipos !== '') {
        $stmt->bind_param($tipos, ...$params);
    }

    $stmt->execute();

    $afectadas = $stmt->affected_rows;

    $stmt->close();

    return $afectadas;
}


/* =========================================================
   GUARDAR EVENTOS AUTOMÁTICOS EN EL CALENDARIO DEL EMPLEADO

   empleados_calendario:
       Un registro por empleado y fecha.
       Se conservan los datos manuales del operador.

   empleados_calendario_asistencias:
       Eventos automáticos vinculados al procesamiento.

   idprocesamiento:
       Identifica el cálculo que generó cada evento.
========================================================= */

function guardarEventosCalendarioEmpleado(
    mysqli $db,
    int $idempleado,
    string $fecha,
    ?array $horario,
    array $marcas,
    array $calculo,
    array $permisos,
    int $idprocesamiento
): void {

    /*
     * No modificar fechas anteriores al ingreso del empleado.
     */
    $empleado = consultarFila(
        $db,
        "SELECT fecha_inicio
         FROM empleados
         WHERE idempleado = ?
         LIMIT 1",
        'i',
        [$idempleado]
    );

    if (!$empleado) {
        return;
    }

    if (
        !empty($empleado['fecha_inicio']) &&
        $fecha < $empleado['fecha_inicio']
    ) {
        return;
    }

    /*
     * Buscar el registro del calendario individual.
     * La tabla admite un solo registro por empleado y fecha.
     */
    $calendario = consultarFila(
        $db,
        "
        SELECT
            idempleado_calendario,
            idtipo_asistencia
        FROM empleados_calendario
        WHERE idempleado = ?
          AND fecha = ?
        LIMIT 1
        FOR UPDATE
        ",
        'is',
        [$idempleado, $fecha]
    );

    /*
     * Si no existe, crear el registro base.
     *
     * Si ya existe, NO modificar sus datos manuales.
     */
    if (!$calendario) {
        ejecutarSQL(
            $db,
            "
            INSERT INTO empleados_calendario (
                idempleado,
                fecha,
                idhorario
            )
            VALUES (?, ?, ?)
            ",
            'isi',
            [
                $idempleado,
                $fecha,
                $horario !== null
                    ? (int)$horario['idhorario']
                    : null
            ]
        );

        $idCalendario = (int)$db->insert_id;
        $tipoManualCalendario = null;

    } else {
        $idCalendario = (int)$calendario['idempleado_calendario'];

        $tipoManualCalendario =
            $calendario['idtipo_asistencia'] !== null
                ? (int)$calendario['idtipo_asistencia']
                : null;
    }

    /*
     * Tipos automáticos con ID fijo.
     */
    $ID_PRESENTE = 1;
    $ID_TARDE = 3;
    $ID_SALIDA_ANTICIPADA = 6;
    $ID_LECTURA_INCOMPLETA = 7;
    $ID_PERMISO_SALIDA = 9;

    /*
     * Ausente y Franco se resuelven por abreviatura.
     */
    $filaAusente = consultarFila(
        $db,
        "
        SELECT idtipo_asistencia
        FROM tipos_asistencia
        WHERE abreviatura = 'A'
        LIMIT 1
        "
    );

    $idAusente = $filaAusente
        ? (int)$filaAusente['idtipo_asistencia']
        : null;

    $filaFranco = consultarFila(
        $db,
        "
        SELECT idtipo_asistencia
        FROM tipos_asistencia
        WHERE abreviatura = 'FR'
        LIMIT 1
        "
    );

    $idFranco = $filaFranco
        ? (int)$filaFranco['idtipo_asistencia']
        : null;

    /*
     * Comprobar si el operador ya cargó Ausente o Franco
     * como evento manual en la tabla de asistencias.
     *
     * Los eventos manuales tienen idprocesamiento NULL.
     */
    $tiposManuales = consultarFilas(
        $db,
        "
        SELECT idtipo_asistencia
        FROM empleados_calendario_asistencias
        WHERE idempleado_calendario = ?
          AND idprocesamiento IS NULL
        ",
        'i',
        [$idCalendario]
    );

    $tiposManualesIds = array_map(
        static fn($fila) => (int)$fila['idtipo_asistencia'],
        $tiposManuales
    );

    $ausenciaManual =
        ($idAusente !== null && $tipoManualCalendario === $idAusente)
        ||
        ($idAusente !== null && in_array(
            $idAusente,
            $tiposManualesIds,
            true
        ));

    $francoManual =
        ($idFranco !== null && $tipoManualCalendario === $idFranco)
        ||
        ($idFranco !== null && in_array(
            $idFranco,
            $tiposManualesIds,
            true
        ));

    /*
     * Construir los eventos automáticos del cálculo actual.
     */
    $eventos = [];

    $minutosTrabajados = (int)(
        $calculo['minutos_trabajados'] ?? 0
    );

    $minutosAtraso = (int)(
        $calculo['minutos_atraso'] ?? 0
    );

    $minutosSalidaAnticipada = (int)(
        $calculo['minutos_salida_anticipada'] ?? 0
    );

    /*
     * Presente, Tarde y Salida Anticipada.
     */
    if ($minutosTrabajados > 0) {
        $eventos[] = $ID_PRESENTE;

        if ($minutosAtraso > 0) {
            $eventos[] = $ID_TARDE;
        }

        if ($minutosSalidaAnticipada > 0) {
            $eventos[] = $ID_SALIDA_ANTICIPADA;
        }
    }

    /*
     * Cantidad impar de marcas: lectura incompleta.
     */
    if (count($marcas) % 2 !== 0) {
        $eventos[] = $ID_LECTURA_INCOMPLETA;
    }

    /*
     * Hay permisos de salida registrados.
     */
    if (count($permisos) > 0) {
        $eventos[] = $ID_PERMISO_SALIDA;
    }

    /*
     * Sin trabajo, marcas ni permisos:
     *
     * - Horario Franco: Franco.
     * - Otro horario: Ausente.
     * - Sin horario: no generar evento.
     *
     * Si el operador ya cargó Ausente o Franco,
     * no duplicar ese evento automáticamente.
     */
    if (
        $minutosTrabajados === 0 &&
        count($marcas) === 0 &&
        count($permisos) === 0
    ) {
        if ($horario !== null) {
            $nombreHorario = mb_strtoupper(
                trim((string)($horario['nombre'] ?? '')),
                'UTF-8'
            );

            $esFranco =
                strpos($nombreHorario, 'FRANCO') !== false
                || $nombreHorario === 'FR';

            if ($esFranco) {
                if ($idFranco !== null && !$francoManual) {
                    $eventos[] = $idFranco;
                }
            } else {
                if ($idAusente !== null && !$ausenciaManual) {
                    $eventos[] = $idAusente;
                }
            }
        }
    }

    /*
     * Eliminar repeticiones dentro del mismo cálculo.
     */
    $eventos = array_values(
        array_unique(array_map('intval', $eventos))
    );

    /*
     * Insertar los eventos asociados al procesamiento actual.
     *
     * No se borran eventos anteriores:
     * quedan vinculados a su procesamiento histórico.
     *
     * Los eventos manuales tampoco se modifican.
     */
    foreach ($eventos as $idtipo) {
        ejecutarSQL(
            $db,
            "
            INSERT INTO empleados_calendario_asistencias (
                idempleado_calendario,
                idprocesamiento,
                idtipo_asistencia,
                hora_desde,
                hora_hasta,
                observaciones
            )
            VALUES (?, ?, ?, NULL, NULL, NULL)
            ",
            'iii',
            [
                $idCalendario,
                $idprocesamiento,
                $idtipo
            ]
        );
    }
}

/* =========================================================
   RESOLVER HORARIO DEL EMPLEADO

   Precedencia:
   1. Horario especial.
   2. Ciclo asignado al empleado.
========================================================= */

function resolverHorarioCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha
): ?array {

    $especial = consultarFila(
        $db,
        "
        SELECT
            ehi.idhorario,
            h.nombre,
            h.tolerancia_minutos,
            h.cruza_medianoche,
            'Horario especial' AS origen,
            'Horario especial asignado para esta fecha' AS detalle_origen

        FROM empleado_horarios_especiales ehi

        INNER JOIN horarios h
            ON h.idhorario = ehi.idhorario

        WHERE
            ehi.idempleado = ?
            AND ehi.fecha = ?
            AND h.activo = 1

        LIMIT 1
        ",
        'is',
        [$idempleado, $fecha]
    );

    if ($especial) {
        $horario = $especial;
    } else {
        $ciclo = consultarFila(
            $db,
            "
            SELECT
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

            ORDER BY ec.fecha_desde DESC

            LIMIT 1
            ",
            'iss',
            [$idempleado, $fecha, $fecha]
        );

        if (!$ciclo) {
            return null;
        }

        $cantidadSemanas = max(
            1,
            (int)$ciclo['cantidad_semanas']
        );

        $inicio = new DateTime($ciclo['fecha_desde']);
        $dia = new DateTime($fecha);

        $diasTranscurridos = (int)$inicio->diff($dia)->format('%a');

        $semana = (int)(floor($diasTranscurridos / 7))
            % $cantidadSemanas + 1;

        $diaSemana = (int)$dia->format('N');

        $horarioCiclo = consultarFila(
            $db,
            "
            SELECT
                h.idhorario,
                h.nombre,
                h.tolerancia_minutos,
                h.cruza_medianoche

            FROM ciclo_detalle cd

            INNER JOIN horarios h
                ON h.idhorario = cd.idhorario

            WHERE
                cd.idciclo = ?
                AND cd.semana = ?
                AND cd.dia_semana = ?
                AND h.activo = 1

            LIMIT 1
            ",
            'iii',
            [
                (int)$ciclo['idciclo'],
                $semana,
                $diaSemana
            ]
        );

        if (!$horarioCiclo) {
            return null;
        }

        $horario = [
            'idhorario' => $horarioCiclo['idhorario'],
            'nombre' => $horarioCiclo['nombre'],
            'tolerancia_minutos' => $horarioCiclo['tolerancia_minutos'],
            'origen' => 'Ciclo',
            'cruza_medianoche' => (int)$horarioCiclo['cruza_medianoche'],
            'detalle_origen' =>
                'Ciclo: ' . $ciclo['ciclo_nombre'] .
                ' — Semana ' . $semana
        ];
    }

    $idhorario = (int)$horario['idhorario'];

    $tramos = consultarFilas(
        $db,
        "
        SELECT
            idhorario_tramo,
            orden,
            hora_desde,
            hora_hasta,
            minutos_teoricos

        FROM horario_tramos

        WHERE idhorario = ?

        ORDER BY orden
        ",
        'i',
        [$idhorario]
    );

    $descansos = consultarFilas(
        $db,
        "
        SELECT
            idhorario_descanso,
            orden,
            minutos_permitidos

        FROM horario_descansos

        WHERE
            idhorario = ?
            AND activo = 1

        ORDER BY orden
        ",
        'i',
        [$idhorario]
    );

    $horario['idhorario'] = $idhorario;
    $horario['tolerancia_minutos'] = (int)$horario['tolerancia_minutos'];
    $horario['tramos'] = [];
    $horario['descansos'] = $descansos;

    $offsetDia = 0;
    $ultimoDesde = null;

    foreach ($tramos as $tramo) {
        $desdeOriginal = minutosHora($tramo['hora_desde']);
        $hastaOriginal = minutosHora($tramo['hora_hasta']);

        if ($desdeOriginal === null || $hastaOriginal === null) {
            $horario['tramos_invalidos'] = true;
            continue;
        }

        $desde = $desdeOriginal + $offsetDia;
        $hasta = $hastaOriginal + $offsetDia;

        if (!empty($horario['cruza_medianoche'])) {
            /*
            * Si el inicio retrocede respecto del tramo anterior,
            * pertenece al día siguiente.
            */
            if ($ultimoDesde !== null && $desde < $ultimoDesde) {
                $offsetDia += 1440;

                $desde = $desdeOriginal + $offsetDia;
                $hasta = $hastaOriginal + $offsetDia;
            }

            /*
            * Si este tramo cruza medianoche, extender su final.
            */
            if ($hasta <= $desde) {
                $hasta += 1440;
            }
        } elseif ($hasta <= $desde) {
            // Los horarios normales no pueden cruzar medianoche.
            $horario['tramos_invalidos'] = true;
            continue;
        }

        $horario['tramos'][] = [
            'idhorario_tramo' => (int)$tramo['idhorario_tramo'],
            'orden' => (int)$tramo['orden'],
            'desde' => $desde,
            'hasta' => $hasta,
            'hora_desde' => $tramo['hora_desde'],
            'hora_hasta' => $tramo['hora_hasta'],
            'minutos_teoricos' => (int)$tramo['minutos_teoricos']
        ];

        $ultimoDesde = $desde;
    }

    usort(
        $horario['tramos'],
        fn($a, $b) => $a['desde'] <=> $b['desde']
    );

    $horario['cruza_medianoche'] = (int)($horario['cruza_medianoche'] ?? 0);

    return $horario;
}

/* =========================================================
   OBTENER MARCAS VÁLIDAS
========================================================= */

function obtenerMarcasCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha
): array {

    $marcas = consultarFilas(
        $db,
        "
        SELECT
            mr.idmarca AS idmarca,
            NULL AS idmarca_manual,
            mr.idmarca AS idmarca_reloj,
            mr.idlector,
            mr.hora,
            'reloj' AS origen

        FROM marcas_reloj mr

        WHERE
            mr.idempleado = ?
            AND mr.fecha = ?

            AND NOT EXISTS (
                SELECT 1

                FROM marcas_invalidaciones mi

                WHERE mi.idmarca_reloj = mr.idmarca
            )

        UNION ALL

        SELECT
            mm.idmarca_manual AS idmarca,
            mm.idmarca_manual AS idmarca_manual,
            NULL AS idmarca_reloj,
            NULL AS idlector,
            mm.hora,
            'manual' AS origen

        FROM marcas_manuales mm

        WHERE
            mm.idempleado = ?
            AND mm.fecha = ?

            AND NOT EXISTS (
                SELECT 1

                FROM marcas_invalidaciones mi

                WHERE mi.idmarca_manual = mm.idmarca_manual
            )

        ORDER BY hora, idmarca
        ",
        'isis',
        [$idempleado, $fecha, $idempleado, $fecha]
    );

    $resultado = [];

    foreach ($marcas as $marca) {
        $minutos = minutosHora($marca['hora']);

        if ($minutos === null) {
            continue;
        }

        $resultado[] = [
            'idmarca' => (int)$marca['idmarca'],
            'idmarca_reloj' => $marca['idmarca_reloj'] !== null
                ? (int)$marca['idmarca_reloj']
                : null,
            'idmarca_manual' => $marca['idmarca_manual'] !== null
                ? (int)$marca['idmarca_manual']
                : null,
            'idlector' => $marca['idlector'] !== null
                ? (int)$marca['idlector']
                : null,
            'hora' => $marca['hora'],
            'minutos' => $minutos,
            'origen' => $marca['origen']
        ];
    }

    usort(
        $resultado,
        fn($a, $b) =>
            ($a['minutos'] <=> $b['minutos'])
            ?: ($a['idmarca'] <=> $b['idmarca'])
    );

    return $resultado;
}

/*********************************** */


function obtenerMarcasNocturnasCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha,
    array $horario
): array {

    $fechaSiguiente = (new DateTimeImmutable($fecha))
        ->modify('+1 day')
        ->format('Y-m-d');

    $filas = consultarFilas(
        $db,
        "
        SELECT
            mr.idmarca AS idmarca,
            NULL AS idmarca_manual,
            mr.idmarca AS idmarca_reloj,
            mr.idlector,
            mr.fecha AS fecha_marca,
            mr.hora,
            'reloj' AS origen
        FROM marcas_reloj mr
        WHERE mr.idempleado = ?
          AND mr.fecha IN (?, ?)
          AND NOT EXISTS (
              SELECT 1
              FROM marcas_invalidaciones mi
              WHERE mi.idmarca_reloj = mr.idmarca
          )

        UNION ALL

        SELECT
            mm.idmarca_manual AS idmarca,
            mm.idmarca_manual AS idmarca_manual,
            NULL AS idmarca_reloj,
            NULL AS idlector,
            mm.fecha AS fecha_marca,
            mm.hora,
            'manual' AS origen
        FROM marcas_manuales mm
        WHERE mm.idempleado = ?
          AND mm.fecha IN (?, ?)
          AND NOT EXISTS (
              SELECT 1
              FROM marcas_invalidaciones mi
              WHERE mi.idmarca_manual = mm.idmarca_manual
          )

        ORDER BY fecha_marca, hora, idmarca
        ",
        'ississ',
        [
            $idempleado,
            $fecha,
            $fechaSiguiente,
            $idempleado,
            $fecha,
            $fechaSiguiente
        ]
    );

    $tramosHorario = $horario['tramos'] ?? [];

    if (count($tramosHorario) === 0) {
        return [];
    }

    $inicioHorario = min(
        array_column($tramosHorario, 'desde')
    );

    $finHorario = max(
        array_column($tramosHorario, 'hasta')
    );

    // Se permiten marcas anticipadas y posteriores al horario.
    $limiteInicio = max(0, $inicioHorario - 240);
    $limiteFin = $finHorario + 240;

    $resultado = [];

    foreach ($filas as $fila) {
        $minutos = minutosHora($fila['hora']);

        if ($minutos === null) {
            continue;
        }

        $esDiaSiguiente = (
            $fila['fecha_marca'] === $fechaSiguiente
        );

        if ($esDiaSiguiente) {
            $minutos += 1440;
        }

        // Evitar incorporar marcas ajenas al turno nocturno.
        if (
            $minutos < $limiteInicio ||
            $minutos > $limiteFin
        ) {
            continue;
        }

        $resultado[] = [
            'idmarca' => (int)$fila['idmarca'],
            'idmarca_reloj' =>
                $fila['idmarca_reloj'] !== null
                    ? (int)$fila['idmarca_reloj']
                    : null,
            'idmarca_manual' =>
                $fila['idmarca_manual'] !== null
                    ? (int)$fila['idmarca_manual']
                    : null,
            'idlector' =>
                $fila['idlector'] !== null
                    ? (int)$fila['idlector']
                    : null,
            'fecha_marca' => $fila['fecha_marca'],
            'hora' => $fila['hora'],
            'minutos' => $minutos,
            'origen' => $fila['origen']
        ];
    }

    usort(
        $resultado,
        fn($a, $b) =>
            ($a['minutos'] <=> $b['minutos'])
            ?: ($a['idmarca'] <=> $b['idmarca'])
    );

    return $resultado;
}

/* =========================================================
   INTERVALOS DE HORAS EXTRA PROGRAMADAS
========================================================= */

function obtenerExtrasCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha,
    bool $cruzaMedianoche = false
): array {

    $filas = consultarFilas(
        $db,
        "
        SELECT
            idhorario_extra,
            hora_desde,
            hora_hasta

        FROM empleado_horarios_extra

        WHERE
            idempleado = ?
            AND fecha = ?
            AND activo = 1

        ORDER BY hora_desde
        ",
        'is',
        [$idempleado, $fecha]
    );

    $extras = [];

    foreach ($filas as $fila) {
        $desde = minutosHora($fila['hora_desde']);
        $hasta = minutosHora($fila['hora_hasta']);

        if (
            $desde === null ||
            $hasta === null ||
            (
                $hasta <= $desde &&
                !$cruzaMedianoche
            )
        ) {
            continue;
        }

        if ($cruzaMedianoche && $hasta <= $desde) {
            $hasta += 1440;
        }

        $extras[] = [
            'idhorario_extra' => (int)$fila['idhorario_extra'],
            'desde' => $desde,
            'hasta' => $hasta
        ];
    }

    return $extras;
}

/* =========================================================
   PERMISOS
========================================================= */

function obtenerPermisosCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha
): array {

    $filas = consultarFilas(
        $db,
        "
        SELECT
            idpermiso,
            hora_desde,
            hora_hasta,
            motivo

        FROM permisos_salida

        WHERE
            idempleado = ?
            AND fecha = ?

        ORDER BY hora_desde, idpermiso
        ",
        'is',
        [$idempleado, $fecha]
    );

    $permisos = [];

    foreach ($filas as $fila) {
        $desde = minutosHora($fila['hora_desde']);
        $hasta = minutosHora($fila['hora_hasta']);

        $permisos[] = [
            'idpermiso' => (int)$fila['idpermiso'],
            'desde' => $desde,
            'hasta' => $hasta,
            'motivo' => $fila['motivo']
        ];
    }

    return $permisos;
}

/* =========================================================
   CONFIGURACIÓN DE EXTRAS
========================================================= */

function obtenerConfiguracionCalculo(mysqli $db): array
{
    $fila = consultarFila(
        $db,
        "
        SELECT
            minutos_minimos_extra,
            tipo_extra_fuera_horario,
            tratamiento_horas_extra
        FROM configuracion_empresa
        ORDER BY idconfig DESC
        LIMIT 1
        "
    );

    $tipoExtra = $fila['tipo_extra_fuera_horario'] ?? 'ninguna';

    if (!in_array(
        $tipoExtra,
        ['ninguna', 'antes', 'despues', 'antes_despues'],
        true
    )) {
        $tipoExtra = 'ninguna';
    }

    $tratamiento = $fila['tratamiento_horas_extra'] ?? 'ninguna';

    if (!in_array(
        $tratamiento,
        ['ninguna', 'pago', 'compensacion', 'pago_compensacion'],
        true
    )) {
        $tratamiento = 'ninguna';
    }

    return [
        'minutos_minimos_extra' => max(
            0,
            (int)($fila['minutos_minimos_extra'] ?? 0)
        ),
        'tipo_extra_fuera_horario' => $tipoExtra,
        'tratamiento_horas_extra' => $tratamiento
    ];
}

/* =========================================================
   CLASIFICAR PARES DE MARCAS
========================================================= */

function calcularIntervalosCalculo(
    array $marcas,
    ?array $horario,
    array $permisos,
    array $extras,
    array $configuracion
): array {

    $tramos = [];
    $descansos = [];
    $asociacionesPermisos = [];
    $interpretaciones = [];
    $observaciones = [];

    $minutosTrabajados = 0;
    $minutosDescanso = 0;
    $minutosDescansoExceso = 0;
    $minutosExtra = 0;
    $minutosAtraso = 0;
    $minutosSalidaAnticipada = 0;

    $requiereRevision = false;

    if ($horario && !empty($horario['tramos_invalidos'])) {
        $requiereRevision = true;
        $observaciones[] = 'El horario contiene tramos inválidos.';
    }

    if (count($marcas) % 2 !== 0) {
        $requiereRevision = true;
        $observaciones[] = 'Hay una marca sin pareja de entrada o salida.';
    }

    if (!$horario && count($marcas) > 0) {
        $requiereRevision = true;
        $observaciones[] = 'Hay marcas, pero no se pudo resolver el horario.';
    }

    $huecos = [];

    if ($horario) {
        $tramosHorario = $horario['tramos'];

        for ($i = 0; $i < count($tramosHorario) - 1; $i++) {
            $desde = $tramosHorario[$i]['hasta'];
            $hasta = $tramosHorario[$i + 1]['desde'];

            if ($hasta > $desde) {
                $huecos[] = [
                    'desde' => $desde,
                    'hasta' => $hasta,
                    'orden' => count($huecos) + 1
                ];
            }
        }
    }

    $descansosHorario = $horario['descansos'] ?? [];

    for ($i = 0; $i < count($marcas) - 1; $i += 2) {
        $entrada = $marcas[$i];
        $salida = $marcas[$i + 1];

        $desde = $entrada['minutos'];
        $hasta = $salida['minutos'];

        if ($hasta <= $desde) {
            $requiereRevision = true;
            $observaciones[] =
                'Se encontró una salida anterior o igual a su entrada.';

            continue;
        }

        $duracion = $hasta - $desde;

        $tipoIntervalo = 'trabajo';
        $huecoDetectado = null;
        $permisoDetectado = null;

        foreach ($permisos as $permiso) {
            if (
                $permiso['desde'] !== null &&
                $permiso['hasta'] !== null &&
                $permiso['hasta'] > $permiso['desde']
            ) {
                $solapamiento = interseccionMinutos(
                    $desde,
                    $hasta,
                    $permiso['desde'],
                    $permiso['hasta']
                );

                $cercaDesde = abs($desde - $permiso['desde']) <= 120;
                $cercaHasta = abs($hasta - $permiso['hasta']) <= 120;

                if (
                    $solapamiento > 0 &&
                    ($cercaDesde || $cercaHasta)
                ) {
                    if ($permisoDetectado !== null) {
                        $requiereRevision = true;
                        $observaciones[] =
                            'Un intervalo coincide con más de un permiso.';
                    } else {
                        $permisoDetectado = $permiso;
                    }
                }
            }
        }

        if ($permisoDetectado !== null) {
            $tipoIntervalo = 'permiso';

            $asociacionesPermisos[] = [
                'idpermiso' => $permisoDetectado['idpermiso'],
                'salida' => $entrada,
                'regreso' => $salida
            ];
        }

        if ($tipoIntervalo === 'trabajo') {
            foreach ($huecos as $hueco) {
                $solapamiento = interseccionMinutos(
                    $desde,
                    $hasta,
                    $hueco['desde'],
                    $hueco['hasta']
                );

                $cercaInicio = abs($desde - $hueco['desde']) <= 120;
                $cercaFin = abs($hasta - $hueco['hasta']) <= 120;

                if (
                    $solapamiento > 0 &&
                    $cercaInicio &&
                    $cercaFin
                ) {
                    $huecoDetectado = $hueco;
                    $tipoIntervalo = 'descanso';
                    break;
                }

                if (
                    $desde < $hueco['desde'] &&
                    $hasta > $hueco['hasta']
                ) {
                    $requiereRevision = true;
                    $observaciones[] =
                        'Un intervalo de marcas atraviesa un descanso programado sin delimitarlo.';
                }
            }
        }

        if ($tipoIntervalo === 'descanso') {
            $ordenDescanso = (int)$huecoDetectado['orden'];

            $permitidos = 0;

            foreach ($descansosHorario as $descansoHorario) {
                if ((int)$descansoHorario['orden'] === $ordenDescanso) {
                    $permitidos = (int)$descansoHorario['minutos_permitidos'];
                    break;
                }
            }

            $exceso = max(0, $duracion - $permitidos);

            $descansos[] = [
                'orden' => count($descansos) + 1,
                'hora_desde' => $desde,
                'hora_hasta' => $hasta,
                'minutos_reales' => $duracion,
                'minutos_permitidos' => $permitidos,
                'minutos_exceso' => $exceso,
                'marca_inicio' => $entrada,
                'marca_fin' => $salida
            ];

            $minutosDescanso += $duracion;
            $minutosDescansoExceso += $exceso;

            $interpretaciones[] = [
                'marca' => $entrada,
                'tipo' => 'inicio_descanso'
            ];

            $interpretaciones[] = [
                'marca' => $salida,
                'tipo' => 'fin_descanso'
            ];

            continue;
        }

        if ($tipoIntervalo === 'permiso') {
            $interpretaciones[] = [
                'marca' => $entrada,
                'tipo' => 'salida'
            ];

            $interpretaciones[] = [
                'marca' => $salida,
                'tipo' => 'entrada'
            ];

            continue;
        }

        $tramos[] = [
            'orden' => count($tramos) + 1,
            'desde' => $desde,
            'hasta' => $hasta,
            'minutos' => $duracion,
            'marca_inicio' => $entrada,
            'marca_fin' => $salida
        ];

        $minutosTrabajados += $duracion;

        $interpretaciones[] = [
            'marca' => $entrada,
            'tipo' => 'entrada'
        ];

        $interpretaciones[] = [
            'marca' => $salida,
            'tipo' => 'salida'
        ];
    }

    if ($horario && count($horario['tramos']) > 0 && count($marcas) > 0) {
        $primeraEntrada = $marcas[0]['minutos'];
        $ultimoIndice = count($marcas) - 1;
        $ultimaMarca = $marcas[$ultimoIndice]['minutos'];

        $primerInicio = $horario['tramos'][0]['desde'];
        $ultimoFin = $horario['tramos'][
            count($horario['tramos']) - 1
        ]['hasta'];

        $tolerancia = (int)$horario['tolerancia_minutos'];

        $minutosAtraso = max(
            0,
            $primeraEntrada - $primerInicio - $tolerancia
        );

        $minutosSalidaAnticipada = max(
            0,
            $ultimoFin - $ultimaMarca
        );
    }

    $tramosOriginales = $tramos;
    $tramosClasificados = [];

    $minutosExtraProgramada = 0;
    $minutosExtraAutomatica = 0;

    $horariosHabituales = $horario['tramos'] ?? [];

    $inicioHorario = null;
    $finHorario = null;

    if (count($horariosHabituales) > 0) {
        $inicioHorario = min(
            array_column($horariosHabituales, 'desde')
        );

        $finHorario = max(
            array_column($horariosHabituales, 'hasta')
        );
    }

    $tipoExtra = $configuracion['tipo_extra_fuera_horario']
        ?? 'ninguna';

    $permitirAntes = in_array(
        $tipoExtra,
        ['antes', 'antes_despues'],
        true
    );

    $permitirDespues = in_array(
        $tipoExtra,
        ['despues', 'antes_despues'],
        true
    );

    foreach ($tramosOriginales as $tramoOriginal) {
        $desdeTramo = (int)$tramoOriginal['desde'];
        $hastaTramo = (int)$tramoOriginal['hasta'];

        if ($hastaTramo <= $desdeTramo) {
            continue;
        }

        $limites = [$desdeTramo, $hastaTramo];

        foreach ($horariosHabituales as $ht) {
            foreach (['desde', 'hasta'] as $campo) {
                $limite = (int)$ht[$campo];

                if ($limite > $desdeTramo && $limite < $hastaTramo) {
                    $limites[] = $limite;
                }
            }
        }

        foreach ($extras as $extra) {
            foreach (['desde', 'hasta'] as $campo) {
                $limite = (int)$extra[$campo];

                if ($limite > $desdeTramo && $limite < $hastaTramo) {
                    $limites[] = $limite;
                }
            }
        }

        $limites = array_values(array_unique($limites));
        sort($limites, SORT_NUMERIC);

        for ($i = 0; $i < count($limites) - 1; $i++) {
            $desde = (int)$limites[$i];
            $hasta = (int)$limites[$i + 1];
            $duracion = $hasta - $desde;

            if ($duracion <= 0) {
                continue;
            }

            $medio = ($desde + $hasta) / 2;
            $esHorarioHabitual = false;

            foreach ($horariosHabituales as $ht) {
                if (
                    $medio >= $ht['desde'] &&
                    $medio < $ht['hasta']
                ) {
                    $esHorarioHabitual = true;
                    break;
                }
            }

            $esExtraProgramada = false;

            foreach ($extras as $extra) {
                if (
                    $medio >= $extra['desde'] &&
                    $medio < $extra['hasta']
                ) {
                    $esExtraProgramada = true;
                    break;
                }
            }

            if ($esHorarioHabitual) {
                $tipoTramo = 'normal';
            } elseif ($esExtraProgramada) {
                $tipoTramo = 'extra_programada';
                $minutosExtraProgramada += $duracion;
            } else {
                $esAntes = (
                    $inicioHorario !== null &&
                    $hasta <= $inicioHorario
                );

                $esDespues = (
                    $finHorario !== null &&
                    $desde >= $finHorario
                );

                $esExtraAutomatica =
                    ($esAntes && $permitirAntes) ||
                    ($esDespues && $permitirDespues);

                if ($esExtraAutomatica) {
                    $tipoTramo = 'extra_automatica';
                    $minutosExtraAutomatica += $duracion;
                } else {
                    $tipoTramo = 'normal';
                }
            }

            $tramosClasificados[] = [
                'orden' => count($tramosClasificados) + 1,
                'tipo_tramo' => $tipoTramo,
                'desde' => $desde,
                'hasta' => $hasta,
                'minutos' => $duracion,
                'marca_inicio' => $tramoOriginal['marca_inicio'],
                'marca_fin' => $tramoOriginal['marca_fin']
            ];
        }
    }

    $minimos = (int)(
        $configuracion['minutos_minimos_extra'] ?? 0
    );

    if ($minutosExtraAutomatica < $minimos) {
        foreach ($tramosClasificados as &$tramoClasificado) {
            if ($tramoClasificado['tipo_tramo'] === 'extra_automatica') {
                $tramoClasificado['tipo_tramo'] = 'normal';
            }
        }
        unset($tramoClasificado);

        $minutosExtraAutomatica = 0;
    }

    $minutosExtraProgramada = 0;
    $minutosExtraAutomatica = 0;
    $minutosNormales = 0;
    $minutosClasificados = 0;

    foreach ($tramosClasificados as $tramoClasificado) {
        $minutosClasificados += $tramoClasificado['minutos'];

        switch ($tramoClasificado['tipo_tramo']) {
            case 'extra_programada':
                $minutosExtraProgramada += $tramoClasificado['minutos'];
                break;

            case 'extra_automatica':
                $minutosExtraAutomatica += $tramoClasificado['minutos'];
                break;

            default:
                $minutosNormales += $tramoClasificado['minutos'];
                break;
        }
    }

    if ($minutosClasificados !== $minutosTrabajados) {
        $requiereRevision = true;
        $observaciones[] =
            'La suma de los tramos clasificados no coincide con los minutos trabajados.';
    }

    $tramos = $tramosClasificados;

    $minutosExtra = min(
        $minutosTrabajados,
        $minutosExtraProgramada + $minutosExtraAutomatica
    );

    if (count($marcas) === 0 && $horario) {
        $requiereRevision = true;
        $observaciones[] = 'La jornada tiene horario asignado, pero no registra marcas.';
    }

    if (!$horario && count($marcas) === 0 && count($extras) === 0) {
        $observaciones[] = 'No hay horario ni marcas para calcular.';
    }

    return [
        'tramos' => $tramos,
        'descansos' => $descansos,
        'asociaciones_permisos' => $asociacionesPermisos,
        'interpretaciones' => $interpretaciones,
        'minutos_trabajados' => $minutosTrabajados,
        'minutos_descanso' => $minutosDescanso,
        'minutos_descanso_exceso' => $minutosDescansoExceso,
        'minutos_atraso' => $minutosAtraso,
        'minutos_salida_anticipada' => $minutosSalidaAnticipada,
        'minutos_extra' => $minutosExtra,
        'requiere_revision' => $requiereRevision,
        'observaciones' => array_values(array_unique($observaciones))
    ];
}

/*********************************** */
function calcularIntervalosNocturnos(
    array $marcas,
    ?array $horario,
    array $permisos,
    array $extras,
    array $configuracion
): array {

    if (!$horario || empty($horario['cruza_medianoche'])) {
        throw new InvalidArgumentException(
            'El cálculo nocturno requiere un horario marcado para cruzar medianoche.'
        );
    }

    
    /*
    * Normalizar permisos a la misma escala de minutos
    * que las marcas y los tramos del turno nocturno.
    */
    $inicioTurno = (int)($horario['tramos'][0]['desde'] ?? 0);

    foreach ($permisos as &$permiso) {

        if (
            $permiso['desde'] === null ||
            $permiso['hasta'] === null
        ) {
            continue;
        }

        /*
        * Si el permiso comienza antes de la hora de inicio
        * del turno, corresponde a la madrugada siguiente.
        * Se trasladan ambas horas al día siguiente.
        */
        if ($permiso['desde'] < $inicioTurno) {

            $permiso['desde'] += 1440;
            $permiso['hasta'] += 1440;

        } elseif ($permiso['hasta'] <= $permiso['desde']) {

            /*
            * Si comienza antes de medianoche y termina
            * después, solamente se traslada la finalización.
            */
            $permiso['hasta'] += 1440;
        }
    }

    unset($permiso);


    /*
     * Normalizar horas extra programadas.
     */
    foreach ($extras as &$extra) {
        if ($extra['hasta'] <= $extra['desde']) {
            $extra['hasta'] += 1440;
        }
    }
    unset($extra);

    /*
     * Reutilizar las reglas de clasificación existentes:
     * descansos, permisos, atrasos y horas extra.
     */
    $calculo = calcularIntervalosCalculo(
        $marcas,
        $horario,
        $permisos,
        $extras,
        $configuracion
    );

    /*
     * Indicar que las horas de los tramos están expresadas
     * en una escala continua, que puede superar 1439 minutos.
     * Se utilizará al guardar los DATETIME.
     */
    $calculo['cruza_medianoche'] = true;

    return $calculo;
}

/* =========================================================
   GUARDAR JORNADA Y DETALLES
========================================================= */

function guardarJornadaCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha,
    ?array $horario,
    array $marcas,
    array $calculo,
    int $idusuario,
    array $permisos
): array {

    $db->begin_transaction();

    try {
        $jornadaExistente = consultarFila(
            $db,
            "
            SELECT idjornada, estado
            FROM jornadas
            WHERE idempleado = ?
            AND fecha = ?
            LIMIT 1
            FOR UPDATE
            ",
            'is',
            [$idempleado, $fecha]
        );

        if ($jornadaExistente) {
            $idjornada = (int)$jornadaExistente['idjornada'];

            
            // No reemplazar una jornada que ya tiene un procesamiento vigente.
            
            $procesamientoVigente = consultarFila(
                $db,
                "
                SELECT idprocesamiento
                FROM jornadas_procesamientos
                WHERE idjornada = ?
                AND estado = 'vigente'
                LIMIT 1
                FOR UPDATE
                ",
                'i',
                [$idjornada]
            );

            if (
                $procesamientoVigente
                && $jornadaExistente['estado'] !== 'requiere_revision'
            ) {
                $db->commit();

                return [
                    'idjornada' => $idjornada,
                    'idprocesamiento' =>
                        (int)$procesamientoVigente['idprocesamiento'],
                    'omitida' => true,
                    'motivo' =>
                        'La jornada tiene un procesamiento vigente y no requiere revisión.'
                ];
            }

            $procesamientoAnterior = consultarFila(
                $db,
                "
                SELECT COALESCE(MAX(numero), 0) AS ultimo
                FROM jornadas_procesamientos
                WHERE idjornada = ?
                ",
                'i',
                [$idjornada]
            );

            $numero = (int)$procesamientoAnterior['ultimo'] + 1;

            /*
            * Si la jornada está en revisión, cerrar el procesamiento
            * vigente anterior antes de generar el nuevo.
            * Se conserva el historial del procesamiento anterior.
            */
            if ($procesamientoVigente) {
                $idProcesamientoAnterior =
                    (int)$procesamientoVigente['idprocesamiento'];

                /*
                * Revertir primero los movimientos compensatorios.
                * Si el saldo ya fue utilizado por otro procesamiento,
                * la función de reversión corregida impedirá perderlo.
                */
                compensacionHorasRevertirProcesamiento(
                    $db,
                    $idProcesamientoAnterior,
                    $idusuario,
                    'Reversión automática por reprocesamiento de jornada.'
                );

                /*
                * Anular solicitudes de generación todavía pendientes.
                * Las autorizaciones ya resueltas se conservan como historial.
                */
                ejecutarSQL(
                    $db,
                    "
                    UPDATE horas_compensacion_autorizaciones
                    SET estado = 'anulada',
                        observaciones = CONCAT(
                            COALESCE(observaciones, ''),
                            CASE
                                WHEN COALESCE(observaciones, '') = '' THEN ''
                                ELSE '\n'
                            END,
                            'Anulada por reprocesamiento de jornada.'
                        )
                    WHERE idprocesamiento = ?
                    AND tipo_operacion = 'generacion'
                    AND estado = 'pendiente'
                    ",
                    'i',
                    [$idProcesamientoAnterior]
                );

                /*
                * Cerrar el procesamiento anterior solamente después
                * de revertir sus movimientos.
                */
                ejecutarSQL(
                    $db,
                    "
                    UPDATE jornadas_procesamientos
                    SET estado = 'revertido',
                        fecha_fin = NOW()
                    WHERE idprocesamiento = ?
                    AND estado = 'vigente'
                    ",
                    'i',
                    [$idProcesamientoAnterior]
                );
            }
/*
            ejecutarSQL(
                $db,
                "
                UPDATE jornadas_procesamientos

                SET estado = 'revertido',
                    fecha_fin = NOW()

                WHERE
                    idjornada = ?
                    AND estado = 'vigente'
                ",
                'i',
                [$idjornada]
            );
*/
            ejecutarSQL(
                $db,
                "
                UPDATE jornadas

                SET
                    idhorario = ?,
                    minutos_trabajados = ?,
                    minutos_descanso = ?,
                    minutos_descanso_exceso = ?,
                    minutos_atraso = ?,
                    minutos_salida_anticipada = ?,
                    minutos_extra = ?,
                    estado = 'pendiente',
                    fecha_calculo = NULL

                WHERE idjornada = ?
                ",
                'iiiiiiii',
                [
                    $horario ? (int)$horario['idhorario'] : null,
                    $calculo['minutos_trabajados'],
                    $calculo['minutos_descanso'],
                    $calculo['minutos_descanso_exceso'],
                    $calculo['minutos_atraso'],
                    $calculo['minutos_salida_anticipada'],
                    $calculo['minutos_extra'],
                    $idjornada
                ]
            );

            ejecutarSQL(
                $db,
                "DELETE FROM jornada_tramos WHERE idjornada = ?",
                'i',
                [$idjornada]
            );

            ejecutarSQL(
                $db,
                "DELETE FROM jornada_descansos WHERE idjornada = ?",
                'i',
                [$idjornada]
            );

            ejecutarSQL(
                $db,
                "DELETE FROM jornada_marcas WHERE idjornada = ?",
                'i',
                [$idjornada]
            );
        } else {
            $numero = 1;

            ejecutarSQL(
                $db,
                "
                INSERT INTO jornadas (
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
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NULL)
                ",
                'isiiiiiii',
                [
                    $idempleado,
                    $fecha,
                    $horario ? (int)$horario['idhorario'] : null,
                    $calculo['minutos_trabajados'],
                    $calculo['minutos_descanso'],
                    $calculo['minutos_descanso_exceso'],
                    $calculo['minutos_atraso'],
                    $calculo['minutos_salida_anticipada'],
                    $calculo['minutos_extra']
                ]
            );

            $idjornada = (int)$db->insert_id;
        }

        ejecutarSQL(
            $db,
            "
            INSERT INTO jornadas_procesamientos (
                idjornada,
                numero,
                idusuario,
                fecha_inicio,
                estado
            )
            VALUES (?, ?, ?, NOW(), 'en_proceso')
            ",
            'iii',
            [$idjornada, $numero, $idusuario]
        );

        $idprocesamiento = (int)$db->insert_id;

        /*
        * GENERACIÓN DE HORAS COMPENSATORIAS
        *
        * Solo se genera saldo si la jornada quedó correctamente calculada.
        * Las jornadas que requieren revisión no generan saldo todavía.
        * No se modifica la clasificación ni la cantidad de minutos original.
        */
        if (empty($calculo['requiere_revision'])) {
            $configCompensacion = compensacionHorasObtenerConfiguracion($db);

            $tratamiento = $configCompensacion['tratamiento_horas_extra']
                ?? 'ninguna';

            $tramosCompensacion = [];

            foreach ($calculo['tramos'] as $tramo) {
                $tipoTramo = $tramo['tipo_tramo'] ?? 'normal';

                $minutosCompensables = 0;

                if (
                    in_array(
                        $tratamiento,
                        ['compensacion', 'pago_compensacion'],
                        true
                    )
                    && in_array(
                        $tipoTramo,
                        ['extra_programada', 'extra_automatica'],
                        true
                    )
                ) {
                    $minutosCompensables = max(
                        0,
                        (int)($tramo['minutos'] ?? 0)
                    );
                }

                $tramo['minutos_compensables'] = $minutosCompensables;
                $tramosCompensacion[] = $tramo;
            }

            compensacionHorasRegistrarGeneracion(
                $db,
                $idempleado,
                $idjornada,
                $idprocesamiento,
                $fecha,
                $tramosCompensacion,
                $idusuario
            );
        }

        foreach ($calculo['tramos'] as $tramo) {
            $tipoTramo = $tramo['tipo_tramo'] ?? 'normal';

            if (!in_array(
                $tipoTramo,
                ['normal', 'extra_programada', 'extra_automatica'],
                true
            )) {
                throw new RuntimeException(
                    'Tipo de tramo inválido: ' . $tipoTramo
                );
            }

            $minutosDesde = (int)$tramo['desde'];
            $minutosHasta = (int)$tramo['hasta'];

            $fechaHoraEntrada = (new DateTime(
                $fecha . ' 00:00:00'
            ))->modify(
                ($minutosDesde >= 0 ? '+' : '') .
                $minutosDesde .
                ' minutes'
            )->format('Y-m-d H:i:s');

            $fechaHoraSalida = (new DateTime(
                $fecha . ' 00:00:00'
            ))->modify(
                ($minutosHasta >= 0 ? '+' : '') .
                $minutosHasta .
                ' minutes'
            )->format('Y-m-d H:i:s');

            ejecutarSQL(
                $db,
                "
                INSERT INTO jornada_tramos (
                    idjornada,
                    orden,
                    tipo_tramo,
                    hora_entrada,
                    hora_salida,
                    fecha_hora_entrada,
                    fecha_hora_salida,
                    minutos_trabajados
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ",
                'iisssssi',
                [
                    $idjornada,
                    $tramo['orden'],
                    $tipoTramo,
                    horaSQL(($minutosDesde % 1440 + 1440) % 1440),
                    horaSQL(($minutosHasta % 1440 + 1440) % 1440),
                    $fechaHoraEntrada,
                    $fechaHoraSalida,
                    $tramo['minutos']
                ]
            );
        }

        foreach ($calculo['descansos'] as $descanso) {
            ejecutarSQL(
                $db,
                "
                INSERT INTO jornada_descansos (
                    idjornada,
                    orden,
                    hora_desde,
                    hora_hasta,
                    minutos_reales,
                    minutos_permitidos,
                    minutos_exceso
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ",
                'iissiii',
                [
                    $idjornada,
                    $descanso['orden'],
                    horaSQL(
                        ((int)$descanso['hora_desde'] % 1440 + 1440) % 1440
                    ),
                    horaSQL(
                        ((int)$descanso['hora_hasta'] % 1440 + 1440) % 1440
                    ),
                    $descanso['minutos_reales'],
                    $descanso['minutos_permitidos'],
                    $descanso['minutos_exceso']
                ]
            );
        }

        $permisosFecha = consultarFilas(
            $db,
            "
            SELECT idpermiso

            FROM permisos_salida

            WHERE
                idempleado = ?
                AND fecha = ?
            ",
            'is',
            [$idempleado, $fecha]
        );

        foreach ($permisosFecha as $permiso) {
            ejecutarSQL(
                $db,
                "DELETE FROM permiso_marcas WHERE idpermiso = ?",
                'i',
                [(int)$permiso['idpermiso']]
            );
        }

        foreach ($calculo['asociaciones_permisos'] as $asociacion) {
            foreach (
                [
                    'salida' => $asociacion['salida'],
                    'regreso' => $asociacion['regreso']
                ] as $tipo => $marca
            ) {
                ejecutarSQL(
                    $db,
                    "
                    INSERT INTO permiso_marcas (
                        idpermiso,
                        idmarca_reloj,
                        idmarca_manual,
                        tipo
                    )
                    VALUES (?, ?, ?, ?)
                    ",
                    'iiis',
                    [
                        $asociacion['idpermiso'],
                        $marca['idmarca_reloj'],
                        $marca['idmarca_manual'],
                        $tipo
                    ]
                );
            }
        }

        $ordenMarca = 0;

        foreach ($calculo['interpretaciones'] as $interpretacion) {
            $marca = $interpretacion['marca'];

            $ordenMarca++;

            ejecutarSQL(
                $db,
                "
                INSERT INTO jornada_marcas (
                    idjornada,
                    idmarca_reloj,
                    idmarca_manual,
                    tipo,
                    orden
                )
                VALUES (?, ?, ?, ?, ?)
                ",
                'iiisi',
                [
                    $idjornada,
                    $marca['idmarca_reloj'],
                    $marca['idmarca_manual'],
                    $interpretacion['tipo'],
                    $ordenMarca
                ]
            );
        }

        // Registrar los eventos inmutables correspondientes en el calendario del empleado
        guardarEventosCalendarioEmpleado(
            $db,
            $idempleado,
            $fecha,
            $horario,
            $marcas,
            $calculo,
            $permisos,
            $idprocesamiento
        );

        $estadoFinal = $calculo['requiere_revision']
            ? 'requiere_revision'
            : 'calculada';

        ejecutarSQL(
            $db,
            "
            UPDATE jornadas

            SET
                estado = ?,
                fecha_calculo = NOW()

            WHERE idjornada = ?
            ",
            'si',
            [$estadoFinal, $idjornada]
        );

        ejecutarSQL(
            $db,
            "
            UPDATE jornadas_procesamientos

            SET
                estado = 'vigente',
                fecha_fin = NOW()

            WHERE idprocesamiento = ?
            ",
            'i',
            [$idprocesamiento]
        );

        $db->commit();

        return [
            'idjornada' => $idjornada,
            'idprocesamiento' => $idprocesamiento,
            'numero_procesamiento' => $numero,
            'estado' => $estadoFinal
        ];

    } catch (Throwable $e) {
        $db->rollback();

        throw $e;
    }
}

/* =========================================================
   AUTENTICACIÓN Y EMPRESA
========================================================= */

$userAuth = validarTokenAPI($mysqli);

if (!$userAuth) {
    responderCalculo('error', 'No autorizado.');
}

validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

if (!$empresa) {
    responderCalculo(
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
   SOLICITUD
========================================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCalculo(
        'error',
        'Este endpoint requiere una solicitud POST.'
    );
}

$body = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($body)) {
    responderCalculo('error', 'El cuerpo de la solicitud no es válido.');
}

$accion = $body['accion'] ?? '';

if ($accion !== 'calcular') {
    responderCalculo('error', 'Acción no válida.');
}

$fecha = trim((string)($body['fecha'] ?? ''));

if (!fechaValidaCalculo($fecha)) {
    responderCalculo('error', 'La fecha seleccionada no es válida.');
}

$idusuario = (int)(
    $userAuth['idusuario']
    ?? $userAuth['id']
    ?? 0
);

if ($idusuario <= 0) {
    responderCalculo(
        'error',
        'No se pudo identificar al usuario que ejecuta el cálculo.'
    );
}

/* =========================================================
   EJECUCIÓN POR FECHA
========================================================= */

try {
    $configuracion = obtenerConfiguracionCalculo($mysqli);

    /*
     * PRIMERA PASADA:
     * Empleados con fichajes válidos que están pendientes
     * en la fecha seleccionada.
     *
     * Se excluyen los procesamientos vigentes.
     */
    $empleados = consultarFilas(
        $mysqli,
        "
        SELECT
            e.idempleado, e.nombre, e.apellido
        FROM empleados e
        INNER JOIN (
            SELECT
                m.idempleado,
                m.fecha
            FROM (
                SELECT mr.idempleado, mr.fecha
                FROM marcas_reloj mr
                WHERE mr.fecha = ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM marcas_invalidaciones mi
                      WHERE mi.idmarca_reloj = mr.idmarca
                  )

                UNION ALL

                SELECT mm.idempleado, mm.fecha
                FROM marcas_manuales mm
                WHERE mm.fecha = ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM marcas_invalidaciones mi
                      WHERE mi.idmarca_manual = mm.idmarca_manual
                  )
            ) m
            GROUP BY m.idempleado, m.fecha
        ) x
            ON x.idempleado = e.idempleado
        LEFT JOIN jornadas j
            ON j.idempleado = x.idempleado
           AND j.fecha = x.fecha
        WHERE e.activo = 1
          AND (
              j.idjornada IS NULL
              OR j.estado IN ('pendiente', 'requiere_revision')
          )
          AND NOT EXISTS (
                SELECT 1
                FROM jornadas_procesamientos jp
                INNER JOIN jornadas jv
                    ON jv.idjornada = jp.idjornada
                WHERE jv.idempleado = e.idempleado
                AND jv.fecha = x.fecha
                AND jp.estado = 'vigente'
                AND jv.estado <> 'requiere_revision'
            )
        ORDER BY e.idempleado
        ",
        'ss',
        [$fecha, $fecha]
    );

    $procesadas = 0;
    $calculadas = 0;
    $revision = 0;
    $omitidas = 0;
    $empleadosYaProcesados = [];
    $errores = [];

    $jornadasRevisionDetalle = [];

    foreach ($empleados as $empleadoFila) {
        $idempleado = (int)$empleadoFila['idempleado'];

        try {
            /*
             * Segunda comprobación de seguridad:
             * un procesamiento vigente no se toca.
             */
            
            $procesamientoVigente = consultarFila(
                $mysqli,
                "
                SELECT
                    jp.idprocesamiento,
                    j.estado AS estado_jornada
                FROM jornadas j
                INNER JOIN jornadas_procesamientos jp
                    ON jp.idjornada = j.idjornada
                WHERE j.idempleado = ?
                AND j.fecha = ?
                AND jp.estado = 'vigente'
                LIMIT 1
                ",
                'is',
                [$idempleado, $fecha]
            );

            if (
                $procesamientoVigente
                && $procesamientoVigente['estado_jornada'] !== 'requiere_revision'
            ) {
                $omitidas++;

                $empleadosYaProcesados[] = [
                    'idempleado' => $idempleado,
                    'motivo' =>
                        'La jornada tiene un procesamiento vigente y no requiere revisión.'
                ];

                continue;
            }

            $horario = resolverHorarioCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            if ($horario && !empty($horario['cruza_medianoche'])) {
                $marcas = obtenerMarcasNocturnasCalculo(
                    $mysqli,
                    $idempleado,
                    $fecha,
                    $horario
                );
            } else {
                $marcas = obtenerMarcasCalculo(
                    $mysqli,
                    $idempleado,
                    $fecha
                );
            }

            $extras = obtenerExtrasCalculo(
                $mysqli,
                $idempleado,
                $fecha,
                $horario && !empty($horario['cruza_medianoche'])
            );

            $permisos = obtenerPermisosCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            /*
            * Mantener la validación actual de marcas.
            */
            if (count($marcas) === 0) {
                continue;
            }

            if ($horario && !empty($horario['cruza_medianoche'])) {
                $calculo = calcularIntervalosNocturnos(
                    $marcas,
                    $horario,
                    $permisos,
                    $extras,
                    $configuracion
                );
            } else {
                $calculo = calcularIntervalosCalculo(
                    $marcas,
                    $horario,
                    $permisos,
                    $extras,
                    $configuracion
                );
            }

            $guardado = guardarJornadaCalculo(
                $mysqli,
                $idempleado,
                $fecha,
                $horario,
                $marcas,
                $calculo,
                $idusuario,
                $permisos
            );
            
            if (!empty($guardado['omitida'])) {
                $omitidas++;
                $empleadosYaProcesados[] = [
                    'idempleado' => $idempleado,
                    'motivo' => $guardado['motivo']
                ];
                continue;
            }

            if (!empty($calculo['requiere_revision'])) {
                $jornadasRevisionDetalle[] = [
                    'idempleado' => $idempleado,
                    'empleado' => trim(
                        ($empleadoFila['apellido'] ?? '') . ', ' .
                        ($empleadoFila['nombre'] ?? '')
                    ),
                    'fecha' => $fecha,
                    'estado' => 'requiere_revision',
                    'motivos' => $calculo['observaciones'] ?? [
                        'La jornada requiere revisión, pero no se identificó un motivo específico.'
                    ]
                ];
            }

            $procesadas++;

            if ($guardado['estado'] === 'requiere_revision') {
                $revision++;
            } else {
                $calculadas++;
            }

        } catch (Throwable $e) {
            error_log(
                'Error calculando jornada. Empleado: ' .
                $idempleado .
                ', fecha: ' . $fecha .
                '. ' . $e->getMessage()
            );

            $errores[] = [
                'idempleado' => $idempleado,
                'empleado' => trim(
                    ($empleadoFila['apellido'] ?? '') . ', ' .
                    ($empleadoFila['nombre'] ?? '')
                ),
                'fecha' => $fecha,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /*
     * SEGUNDA PASADA:
     * Para empleados activos sin fichajes válidos, reutilizar
     * la función existente que determina FR/A.
     *
     * No crear jornadas ni procesamientos.
     */
    $empleadosSinMarcas = consultarFilas(
        $mysqli,
        "
        SELECT e.idempleado, e.nombre, e.apellido
        FROM empleados e
        WHERE e.activo = 1
          AND NOT EXISTS (
              SELECT 1
              FROM marcas_reloj mr
              WHERE mr.idempleado = e.idempleado
                AND mr.fecha = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_reloj = mr.idmarca
                )
          )
          AND NOT EXISTS (
              SELECT 1
              FROM marcas_manuales mm
              WHERE mm.idempleado = e.idempleado
                AND mm.fecha = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_manual = mm.idmarca_manual
                )
          )
        ORDER BY e.idempleado
        ",
        'ss',
        [$fecha, $fecha]
    );

    $calendariosOmitidos = 0;

    foreach ($empleadosSinMarcas as $empleadoFila) {
        $idempleado = (int)$empleadoFila['idempleado'];

        try {
            /*
             * Nunca modificar el calendario de un día
             * que ya tiene un procesamiento vigente.
             */
            $procesamientoVigente = consultarFila(
                $mysqli,
                "
                SELECT jp.idprocesamiento
                FROM jornadas j
                INNER JOIN jornadas_procesamientos jp
                    ON jp.idjornada = j.idjornada
                WHERE j.idempleado = ?
                  AND j.fecha = ?
                  AND jp.estado = 'vigente'
                LIMIT 1
                ",
                'is',
                [$idempleado, $fecha]
            );

            if ($procesamientoVigente) {
                $calendariosOmitidos++;
                continue;
            }

            /*
             * Buscar el registro individual del día.
             */
            $calendario = consultarFila(
                $mysqli,
                "
                SELECT
                    ec.idempleado_calendario,
                    ec.idtipo_asistencia
                FROM empleados_calendario ec
                WHERE ec.idempleado = ?
                  AND ec.fecha = ?
                LIMIT 1
                ",
                'is',
                [$idempleado, $fecha]
            );

            /*
             * Si existe el registro, comprobar si ya tiene
             * eventos. Si hay eventos, no hacer nada.
             */
            if ($calendario) {
                $eventoExistente = consultarFila(
                    $mysqli,
                    "
                    SELECT idtipo_asistencia
                    FROM empleados_calendario_asistencias
                    WHERE idempleado_calendario = ?
                    LIMIT 1
                    ",
                    'i',
                    [(int)$calendario['idempleado_calendario']]
                );

                if (
                    $eventoExistente
                    || $calendario['idtipo_asistencia'] !== null
                ) {
                    $calendariosOmitidos++;
                    continue;
                }
            }

            /*
             * Si no hay horario, no inventar FR ni A.
             */
            $horario = resolverHorarioCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            if (!$horario) {
                continue;
            }

            $nombreHorario = mb_strtoupper(
                trim((string)($horario['nombre'] ?? '')),
                'UTF-8'
            );

            $esFranco =
                strpos($nombreHorario, 'FRANCO') !== false
                || $nombreHorario === 'FR';

            $filaTipo = consultarFila(
                $mysqli,
                "
                SELECT idtipo_asistencia
                FROM tipos_asistencia
                WHERE abreviatura = ?
                LIMIT 1
                ",
                's',
                [$esFranco ? 'FR' : 'A']
            );

            if (!$filaTipo) {
                continue;
            }

            $mysqli->begin_transaction();

            try {
                /*
                 * Crear el registro base solo si falta.
                 */
                if (!$calendario) {
                    ejecutarSQL(
                        $mysqli,
                        "
                        INSERT INTO empleados_calendario (
                            idempleado,
                            fecha,
                            idhorario
                        )
                        VALUES (?, ?, ?)
                        ",
                        'isi',
                        [
                            $idempleado,
                            $fecha,
                            (int)$horario['idhorario']
                        ]
                    );

                    $idCalendario = (int)$mysqli->insert_id;
                } else {
                    $idCalendario = (int)$calendario[
                        'idempleado_calendario'
                    ];
                }

                /*
                 * Volver a comprobar que no se haya agregado
                 * información al calendario durante la revisión.
                 */
                $eventoExistente = consultarFila(
                    $mysqli,
                    "
                    SELECT idtipo_asistencia
                    FROM empleados_calendario_asistencias
                    WHERE idempleado_calendario = ?
                    LIMIT 1
                    FOR UPDATE
                    ",
                    'i',
                    [$idCalendario]
                );

                if ($eventoExistente) {
                    $mysqli->commit();
                    $calendariosOmitidos++;
                    continue;
                }

                ejecutarSQL(
                    $mysqli,
                    "
                    UPDATE empleados_calendario
                    SET idhorario = ?
                    WHERE idempleado_calendario = ?
                      AND idtipo_asistencia IS NULL
                    ",
                    'ii',
                    [
                        (int)$horario['idhorario'],
                        $idCalendario
                    ]
                );

                ejecutarSQL(
                    $mysqli,
                    "
                    INSERT INTO empleados_calendario_asistencias (
                        idempleado_calendario,
                        idprocesamiento,
                        idtipo_asistencia,
                        hora_desde,
                        hora_hasta,
                        observaciones
                    )
                    VALUES (?, NULL, ?, NULL, NULL, NULL)
                    ",
                    'ii',
                    [
                        $idCalendario,
                        (int)$filaTipo['idtipo_asistencia']
                    ]
                );

                $mysqli->commit();

            } catch (Throwable $e) {
                $mysqli->rollback();
                throw $e;
            }

        } catch (Throwable $e) {
            error_log(
                'Error revisando calendario sin fichajes. Empleado: ' .
                $idempleado .
                ', fecha: ' . $fecha .
                '. ' . $e->getMessage()
            );

            $errores[] = [
                'idempleado' => $idempleado,
                'empleado' => trim(
                    ($empleadoFila['apellido'] ?? '') . ', ' .
                    ($empleadoFila['nombre'] ?? '')
                ),
                'fecha' => $fecha,
                'mensaje' => $e->getMessage()
            ];
        }
    }

    $mensaje = 'Proceso de cálculo finalizado.';

    if ($omitidas > 0) {
        $mensaje .= ' Se omitieron ' . $omitidas .
            ' jornadas que ya estaban procesadas.';
    }

    responderCalculo(
        'ok',
        $mensaje,
        [
            'fecha' => $fecha,
            'empleados_detectados' => count($empleados),
            'jornadas_procesadas' => $procesadas,
            'jornadas_calculadas' => $calculadas,
            'jornadas_revision' => $revision,
            'jornadas_omitidas' => $omitidas,
            'calendarios_omitidos' => $calendariosOmitidos,
            'empleados_ya_procesados' => $empleadosYaProcesados,
            'jornadas_revision_detalle' => $jornadasRevisionDetalle,
            'errores' => $errores
        ]
    );

} catch (Throwable $e) {
    error_log(
        'Error general en calcular_jornadas.php: ' .
        $e->getMessage()
    );

    responderCalculo(
        'error',
        'Ocurrió un error al ejecutar el cálculo de jornadas.'
    );
}