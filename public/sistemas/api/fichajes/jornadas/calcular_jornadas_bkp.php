
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

    foreach ($tramos as $tramo) {
        $desde = minutosHora($tramo['hora_desde']);
        $hasta = minutosHora($tramo['hora_hasta']);

        if (
            $desde === null ||
            $hasta === null ||
            $hasta <= $desde
        ) {
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
    }

    usort(
        $horario['tramos'],
        fn($a, $b) => $a['desde'] <=> $b['desde']
    );

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

/* =========================================================
   INTERVALOS DE HORAS EXTRA PROGRAMADAS
========================================================= */

function obtenerExtrasCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha
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
            $hasta <= $desde
        ) {
            continue;
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
            tipo_extra_fuera_horario
        FROM configuracion_empresa
        ORDER BY idconfig
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

    return [
        'minutos_minimos_extra' => max(
            0,
            (int)($fila['minutos_minimos_extra'] ?? 0)
        ),
        'tipo_extra_fuera_horario' => $tipoExtra
    ];
}
/* =========================================================
   CLASIFICAR PARES DE MARCAS

   Se utilizan pares consecutivos de marcas.
   No se completan marcas faltantes.

   Si el intervalo coincide con un descanso o permiso,
   se excluye de los minutos trabajados.

   Si cruza un descanso teórico sin marcas que delimiten
   correctamente el intervalo, requiere revisión.
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

    /*
     * Construir los intervalos teóricos de descanso a partir
     * de los espacios entre tramos del horario.
     */
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

        /*
         * Determinar si el par representa un permiso.
         * Las horas de permiso son orientativas; la asociación
         * se realiza con el intervalo de marcas más compatible.
         */
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

        /*
         * Detectar descansos programados entre tramos.
         */
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

                /*
                 * El par cruza un hueco de horario, pero las
                 * marcas no delimitan un descanso identificable.
                 */
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

        /*
         * Intervalo de trabajo.
         */
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

    /*
     * Atraso y salida anticipada.
     */
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

    
    /*
     * CLASIFICAR Y DIVIDIR LOS TRAMOS DE TRABAJO
     *
     * Prioridad:
     * 1. Horario habitual: normal.
     * 2. Fuera del horario habitual, dentro de una extra
     *    programada: extra_programada.
     * 3. Fuera del horario habitual y sin extra programada:
     *    extra_automatica, si la configuración lo permite.
     *
     * Los tramos se dividen en cada límite horario relevante.
     */

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

    /*
     * Dividir cada intervalo en segmentos que no crucen
     * límites del horario habitual ni de extras programadas.
     */
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

            /*
             * Determinar si el segmento está dentro del
             * horario habitual. Se usa el punto medio porque
             * el segmento ya está dividido en todos los límites.
             */
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

            /*
             * Detectar si el segmento coincide con una extra
             * programada. Los minutos dentro del horario habitual
             * siempre se clasifican como normales.
             */
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
                /*
                 * Las extras automáticas solo se reconocen
                 * antes del inicio o después del fin de la
                 * jornada habitual, no durante huecos internos.
                 */
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
                    /*
                     * No se reconoce como extra si la configuración
                     * no la permite.
                     */
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

    /*
     * Aplicar el mínimo configurado al total de extras
     * automáticas del día. Si no se alcanza, esos segmentos
     * dejan de computarse como extras automáticas.
     */
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

    /*
     * Recalcular los totales a partir de los segmentos
     * definitivos que se van a guardar en jornada_tramos.
     */
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

    /*
     * Control de integridad: clasificar no puede cambiar
     * los minutos originalmente computados como trabajados.
     */
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

    /*
     * Las marcas no emparejadas no se completan.
     */
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

/* =========================================================
   GUARDAR JORNADA Y DETALLES

   Cada ejecución genera un nuevo procesamiento.
   El procesamiento anterior vigente pasa a revertido.
========================================================= */

function guardarJornadaCalculo(
    mysqli $db,
    int $idempleado,
    string $fecha,
    ?array $horario,
    array $marcas,
    array $calculo,
    int $idusuario
): array {

    $db->begin_transaction();

    try {
        $jornadaExistente = consultarFila(
            $db,
            "
            SELECT idjornada

            FROM jornadas

            WHERE
                idempleado = ?
                AND fecha = ?

            LIMIT 1
            FOR UPDATE
            ",
            'is',
            [$idempleado, $fecha]
        );

        if ($jornadaExistente) {
            $idjornada = (int)$jornadaExistente['idjornada'];

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

            /*
             * Borrar resultados del cálculo anterior.
             */
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

        /*
         * Crear historial de procesamiento.
         */
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
         * Guardar tramos de trabajo.
         */
        
        /*
         * Guardar tramos de trabajo con su clasificación.
         */
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

            ejecutarSQL(
                $db,
                "
                INSERT INTO jornada_tramos (
                    idjornada,
                    orden,
                    tipo_tramo,
                    hora_entrada,
                    hora_salida,
                    minutos_trabajados
                )
                VALUES (?, ?, ?, ?, ?, ?)
                ",
                'iisssi',
                [
                    $idjornada,
                    $tramo['orden'],
                    $tipoTramo,
                    horaSQL($tramo['desde']),
                    horaSQL($tramo['hasta']),
                    $tramo['minutos']
                ]
            );
        }


        /*
         * Guardar descansos reales.
         */
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
                    horaSQL($descanso['hora_desde']),
                    horaSQL($descanso['hora_hasta']),
                    $descanso['minutos_reales'],
                    $descanso['minutos_permitidos'],
                    $descanso['minutos_exceso']
                ]
            );
        }

        /*
         * Reemplazar asociaciones de permisos de esta fecha.
         */
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

        /*
         * Asociar marcas a permisos.
         */
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

        /*
         * Guardar interpretación cronológica de las marcas.
         */
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

        /*
         * Publicar el procesamiento vigente y actualizar
         * el estado final de la jornada.
         */
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
     * Se consideran empleados activos con marcas del día,
     * horario asignado o extra programada.
     *
     * De esta forma también se detectan empleados con horario
     * asignado que no registraron ninguna marca.
     */
    $empleados = consultarFilas(
        $mysqli,
        "
        SELECT DISTINCT e.idempleado

        FROM empleados e

        WHERE
            e.activo = 1

            AND (
                EXISTS (
                    SELECT 1

                    FROM marcas_reloj mr

                    WHERE
                        mr.idempleado = e.idempleado
                        AND mr.fecha = ?
                )

                OR EXISTS (
                    SELECT 1

                    FROM marcas_manuales mm

                    WHERE
                        mm.idempleado = e.idempleado
                        AND mm.fecha = ?
                )

                OR EXISTS (
                    SELECT 1

                    FROM empleado_horarios_especiales ehi

                    WHERE
                        ehi.idempleado = e.idempleado
                        AND ehi.fecha = ?
                )

                OR EXISTS (
                    SELECT 1

                    FROM empleado_ciclos ec

                    WHERE
                        ec.idempleado = e.idempleado
                        AND ec.fecha_desde <= ?
                        AND (
                            ec.fecha_hasta IS NULL
                            OR ec.fecha_hasta >= ?
                        )
                )

                OR EXISTS (
                    SELECT 1

                    FROM empleado_horarios_extra ehe

                    WHERE
                        ehe.idempleado = e.idempleado
                        AND ehe.fecha = ?
                        AND ehe.activo = 1
                )

                OR EXISTS (
                    SELECT 1

                    FROM permisos_salida ps

                    WHERE
                        ps.idempleado = e.idempleado
                        AND ps.fecha = ?
                )
            )

        ORDER BY e.idempleado
        ",
        'sssssss',
        [
            $fecha,
            $fecha,
            $fecha,
            $fecha,
            $fecha,
            $fecha,
            $fecha
        ]
    );

    $procesadas = 0;
    $calculadas = 0;
    $revision = 0;
    $errores = [];

    foreach ($empleados as $empleadoFila) {
        $idempleado = (int)$empleadoFila['idempleado'];

        try {
            $horario = resolverHorarioCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            $marcas = obtenerMarcasCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            $extras = obtenerExtrasCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            $permisos = obtenerPermisosCalculo(
                $mysqli,
                $idempleado,
                $fecha
            );

            /*
             * No crear jornadas vacías para empleados que
             * no tienen horario, marcas, permisos ni extras.
             */
            if (
                !$horario &&
                count($marcas) === 0 &&
                count($extras) === 0 &&
                count($permisos) === 0
            ) {
                continue;
            }

            $calculo = calcularIntervalosCalculo(
                $marcas,
                $horario,
                $permisos,
                $extras,
                $configuracion
            );

            /*
             * Si hay extras programadas pero no se pueden
             * delimitar con marcas, no inventar el trabajo.
             */
            if (count($extras) > 0 && count($marcas) === 0) {
                $calculo['requiere_revision'] = true;

                $calculo['observaciones'][] =
                    'Hay horas extra programadas sin marcas para verificar el trabajo realizado.';
            }

            $guardado = guardarJornadaCalculo(
                $mysqli,
                $idempleado,
                $fecha,
                $horario,
                $marcas,
                $calculo,
                $idusuario
            );

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
                ', fecha: ' .
                $fecha .
                '. ' .
                $e->getMessage()
            );

            $errores[] = [
                'idempleado' => $idempleado,
                'mensaje' => 'No se pudo procesar la jornada de este empleado.'
            ];
        }
    }

    responderCalculo(
        'ok',
        'Proceso de cálculo finalizado.',
        [
            'fecha' => $fecha,
            'empleados_detectados' => count($empleados),
            'jornadas_procesadas' => $procesadas,
            'jornadas_calculadas' => $calculadas,
            'jornadas_revision' => $revision,
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
