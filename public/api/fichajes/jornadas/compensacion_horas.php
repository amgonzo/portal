<?php
/**
 * /jornadas/compensacion_horas.php
 *
 * Módulo independiente para administrar horas compensatorias.
 *
 * IMPORTANTE:
 * - No calcula fichadas ni modifica jornadas.
 * - No altera la lógica de horas extra existentes.
 * - No hace COMMIT ni ROLLBACK: la transacción queda a cargo
 *   del proceso que invoque estas funciones.
 * - Para generar saldo, el cálculo deberá informar explícitamente
 *   los minutos compensables de cada tipo de tramo.
 */

if (!defined('COMPENSACION_HORAS_CARGADO')) {
    define('COMPENSACION_HORAS_CARGADO', true);
}

/**
 * Obtiene la configuración de compensación de la empresa actual.
 */
function compensacionHorasObtenerConfiguracion(mysqli $mysqli): array
{
    $sql = "
        SELECT
            tratamiento_horas_extra,
            modo_generacion_compensacion,
            vigencia_saldo_compensacion,
            meses_vigencia_compensacion
        FROM configuracion_empresa
        ORDER BY idconfig DESC
        LIMIT 1
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new RuntimeException(
            'No se pudo consultar la configuración de compensación: ' .
            $mysqli->error
        );
    }

    $config = $resultado->fetch_assoc();
    $resultado->free();

    if (!$config) {
        return [
            'tratamiento_horas_extra' => 'ninguna',
            'modo_generacion_compensacion' => 'autorizado',
            'vigencia_saldo_compensacion' => 'mismo_mes',
            'meses_vigencia_compensacion' => 1
        ];
    }

    return $config;
}
/**
 * Informa si la compensación está habilitada.
 */
function compensacionHorasEstaActiva(mysqli $mysqli): bool
{
    $config = compensacionHorasObtenerConfiguracion($mysqli);

    return $config['tratamiento_horas_extra'] === 'compensacion';
}

/**
 * Calcula la fecha de vencimiento de un lote.
 *
 * mismo_mes: último día del mes de origen.
 * meses: último día del mes que corresponda a la vigencia configurada.
 */
function compensacionHorasCalcularVencimiento(
    string $fechaOrigen,
    array $config
): ?string {
    if (($config['vigencia_saldo_compensacion'] ?? 'mismo_mes') === 'mismo_mes') {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaOrigen);

        if (!$fecha) {
            throw new InvalidArgumentException('La fecha de origen no es válida.');
        }

        return $fecha->modify('last day of this month')->format('Y-m-d');
    }

    $meses = max(
        1,
        (int)($config['meses_vigencia_compensacion'] ?? 1)
    );

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaOrigen);

    if (!$fecha) {
        throw new InvalidArgumentException('La fecha de origen no es válida.');
    }

    return $fecha
        ->modify('+' . $meses . ' months')
        ->modify('last day of this month')
        ->format('Y-m-d');
}

/**
 * Registra una generación de saldo en un lote.
 *
 * Se llama únicamente cuando la generación es automática
 * o cuando una autorización de generación fue aprobada.
 */
function compensacionHorasCrearLote(
    mysqli $mysqli,
    int $idEmpleado,
    int $idJornada,
    int $idProcesamiento,
    string $fechaOrigen,
    string $tipoTramo,
    int $minutos,
    ?int $idAutorizacion = null
): int {
    if ($minutos <= 0) {
        throw new InvalidArgumentException(
            'Los minutos a compensar deben ser mayores que cero.'
        );
    }

    if (!in_array(
        $tipoTramo,
        ['extra_programada', 'extra_automatica'],
        true
    )) {
        throw new InvalidArgumentException('El tipo de tramo no es válido.');
    }

    // Impide crear dos lotes del mismo tipo para el mismo procesamiento.
    $stmt = $mysqli->prepare("
        SELECT idlote
        FROM horas_compensacion_lotes
        WHERE idprocesamiento = ?
          AND tipo_tramo_origen = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('is', $idProcesamiento, $tipoTramo);
    $stmt->execute();
    $existente = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existente) {
        return (int)$existente['idlote'];
    }

    $config = compensacionHorasObtenerConfiguracion($mysqli);

    $fechaVencimiento = compensacionHorasCalcularVencimiento(
        $fechaOrigen,
        $config
    );

    $stmt = $mysqli->prepare("
        INSERT INTO horas_compensacion_lotes (
            idempleado,
            idjornada,
            idprocesamiento,
            idautorizacion,
            fecha_origen,
            tipo_tramo_origen,
            minutos_generados,
            minutos_disponibles,
            fecha_vencimiento,
            estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible')
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param(
        'iiiissiis',
        $idEmpleado,
        $idJornada,
        $idProcesamiento,
        $idAutorizacion,
        $fechaOrigen,
        $tipoTramo,
        $minutos,
        $minutos,
        $fechaVencimiento
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new RuntimeException(
            'No se pudo crear el lote de compensación: ' . $error
        );
    }

    $idLote = (int)$mysqli->insert_id;
    $stmt->close();

    $tipoMovimiento = 'generacion';
    $observaciones = 'Generación de saldo por horas extra.';

    $stmt = $mysqli->prepare("
        INSERT INTO horas_compensacion_movimientos (
            idempleado,
            idlote,
            idjornada,
            idprocesamiento,
            idautorizacion,
            tipo_movimiento,
            minutos,
            estado,
            observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'vigente', ?)
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param(
        'iiiiisis',
        $idEmpleado,
        $idLote,
        $idJornada,
        $idProcesamiento,
        $idAutorizacion,
        $tipoMovimiento,
        $minutos,
        $observaciones
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new RuntimeException(
            'No se pudo registrar el movimiento de generación: ' . $error
        );
    }

    $stmt->close();

    return $idLote;
}

/**
 * Registra las horas compensables informadas por el cálculo.
 *
 * Cada elemento de $tramos debe incluir:
 * [
 *     'tipo_tramo' => 'extra_programada' | 'extra_automatica',
 *     'minutos_compensables' => int
 * ]
 *
 * El cálculo existente será responsable de determinar esos minutos.
 * Esta función no decide qué horas se pagan y cuáles se compensan.
 */
function compensacionHorasRegistrarGeneracion(
    mysqli $mysqli,
    int $idEmpleado,
    int $idJornada,
    int $idProcesamiento,
    string $fecha,
    array $tramos,
    ?int $idUsuario = null
): array {
    $config = compensacionHorasObtenerConfiguracion($mysqli);

    if (!in_array(
        $config['tratamiento_horas_extra'],
        ['compensacion', 'pago_compensacion'],
        true
    )) {
        return [
            'activa' => false,
            'mensaje' => 'La compensación de horas está desactivada.',
            'minutos' => 0,
            'autorizaciones' => [],
            'lotes' => []
        ];
    }

    $totales = [
        'extra_programada' => 0,
        'extra_automatica' => 0
    ];

    foreach ($tramos as $tramo) {
        $tipo = $tramo['tipo_tramo'] ?? '';
        $minutos = (int)($tramo['minutos_compensables'] ?? 0);

        if (!array_key_exists($tipo, $totales)) {
            continue;
        }

        if ($minutos > 0) {
            $totales[$tipo] += $minutos;
        }
    }

    $totalMinutos = array_sum($totales);

    if ($totalMinutos <= 0) {
        return [
            'activa' => true,
            'mensaje' => 'No hay minutos compensables para registrar.',
            'minutos' => 0,
            'autorizaciones' => [],
            'lotes' => []
        ];
    }

    $autorizaciones = [];
    $lotes = [];

    foreach ($totales as $tipo => $minutos) {
        if ($minutos <= 0) {
            continue;
        }

        // Comprueba si este procesamiento ya generó un lote.
        $stmt = $mysqli->prepare("
            SELECT idlote
            FROM horas_compensacion_lotes
            WHERE idprocesamiento = ?
              AND tipo_tramo_origen = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param('is', $idProcesamiento, $tipo);
        $stmt->execute();
        $loteExistente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($loteExistente) {
            $lotes[] = (int)$loteExistente['idlote'];
            continue;
        }

        // Evita duplicar una solicitud pendiente para el mismo procesamiento.
        $stmt = $mysqli->prepare("
            SELECT idautorizacion, estado
            FROM horas_compensacion_autorizaciones
            WHERE idprocesamiento = ?
              AND tipo_tramo_origen = ?
              AND tipo_operacion = 'generacion'
              AND estado IN ('pendiente', 'autorizada')
            ORDER BY idautorizacion DESC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param('is', $idProcesamiento, $tipo);
        $stmt->execute();
        $autorizacionExistente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($autorizacionExistente) {
            $autorizaciones[] = (int)$autorizacionExistente['idautorizacion'];
            continue;
        }

        if ($config['tratamiento_horas_extra'] !== 'compensacion') {
            return [
                'activa' => false,
                'mensaje' => 'La compensación de horas está desactivada.',
                'minutos' => 0,
                'autorizaciones' => [],
                'lotes' => []
            ];
        }

        // En modo autorizado no se acredita saldo hasta la aprobación.
        $tipoOperacion = 'generacion';
        $estado = 'pendiente';
        $motivo = 'Solicitud de generación de horas compensatorias.';

        $stmt = $mysqli->prepare("
            INSERT INTO horas_compensacion_autorizaciones (
                idempleado,
                tipo_operacion,
                fecha_operacion,
                minutos_autorizados,
                estado,
                idjornada,
                idprocesamiento,
                tipo_tramo_origen,
                idusuario_solicita,
                motivo
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param(
            'issisiisis',
            $idEmpleado,
            $tipoOperacion,
            $fecha,
            $minutos,
            $estado,
            $idJornada,
            $idProcesamiento,
            $tipo,
            $idUsuario,
            $motivo
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'No se pudo crear la solicitud de compensación: ' . $error
            );
        }

        $autorizaciones[] = (int)$mysqli->insert_id;
        $stmt->close();
    }

    return [
        'activa' => true,
        'mensaje' => 'Procesamiento de compensación realizado.',
        'minutos' => $totalMinutos,
        'autorizaciones' => $autorizaciones,
        'lotes' => $lotes
    ];
}

/**
 * Obtiene el saldo disponible de un empleado.
 *
 * No incluye lotes vencidos, aunque todavía no se haya ejecutado
 * la tarea que los marca formalmente como vencidos.
 */
function compensacionHorasObtenerSaldo(
    mysqli $mysqli,
    int $idEmpleado,
    ?string $fecha = null
): array {
    $fecha = $fecha ?: date('Y-m-d');

    $stmt = $mysqli->prepare("
        SELECT
            COALESCE(SUM(minutos_disponibles), 0) AS minutos_disponibles,
            COUNT(*) AS cantidad_lotes
        FROM horas_compensacion_lotes
        WHERE idempleado = ?
          AND estado = 'disponible'
          AND minutos_disponibles > 0
          AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= ?)
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('is', $idEmpleado, $fecha);
    $stmt->execute();

    $saldo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $minutos = (int)($saldo['minutos_disponibles'] ?? 0);

    return [
        'idempleado' => $idEmpleado,
        'minutos_disponibles' => $minutos,
        'horas' => intdiv($minutos, 60),
        'minutos' => $minutos % 60,
        'cantidad_lotes' => (int)($saldo['cantidad_lotes'] ?? 0)
    ];
}

/**
 * Consume saldo siguiendo FIFO: primero se utilizan los lotes
 * más antiguos. Se recomienda invocarla dentro de una transacción.
 */
function compensacionHorasConsumirSaldo(
    mysqli $mysqli,
    int $idEmpleado,
    int $minutosSolicitados,
    string $fecha,
    ?int $idJornada = null,
    ?int $idProcesamiento = null,
    ?int $idAutorizacion = null,
    ?int $idUsuario = null
): array {
    if ($minutosSolicitados <= 0) {
        throw new InvalidArgumentException(
            'Los minutos solicitados deben ser mayores que cero.'
        );
    }

    $stmt = $mysqli->prepare("
        SELECT idlote, minutos_disponibles
        FROM horas_compensacion_lotes
        WHERE idempleado = ?
          AND estado = 'disponible'
          AND minutos_disponibles > 0
          AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= ?)
        ORDER BY fecha_origen ASC, idlote ASC
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('is', $idEmpleado, $fecha);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $lotes = [];

    while ($fila = $resultado->fetch_assoc()) {
        $lotes[] = $fila;
    }

    $stmt->close();

    $disponible = 0;

    foreach ($lotes as $lote) {
        $disponible += (int)$lote['minutos_disponibles'];
    }

    if ($disponible < $minutosSolicitados) {
        throw new RuntimeException(
            'Saldo insuficiente. Disponible: ' . $disponible .
            ' minutos; solicitado: ' . $minutosSolicitados . ' minutos.'
        );
    }

    $restantes = $minutosSolicitados;
    $movimientos = [];

    foreach ($lotes as $lote) {
        if ($restantes <= 0) {
            break;
        }

        $idLote = (int)$lote['idlote'];
        $saldoLote = (int)$lote['minutos_disponibles'];
        $consumir = min($saldoLote, $restantes);

        $nuevoSaldo = $saldoLote - $consumir;
        $nuevoEstado = $nuevoSaldo === 0 ? 'agotado' : 'disponible';

        $stmt = $mysqli->prepare("
            UPDATE horas_compensacion_lotes
            SET minutos_disponibles = ?,
                estado = ?
            WHERE idlote = ?
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param('isi', $nuevoSaldo, $nuevoEstado, $idLote);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        $stmt->close();

        $tipoMovimiento = 'utilizacion';
        $observaciones = 'Utilización de saldo compensatorio.';

        $stmt = $mysqli->prepare("
            INSERT INTO horas_compensacion_movimientos (
                idempleado,
                idlote,
                idjornada,
                idprocesamiento,
                idautorizacion,
                tipo_movimiento,
                minutos,
                estado,
                idusuario,
                observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'vigente', ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param(
            'iiiiisiis',
            $idEmpleado,
            $idLote,
            $idJornada,
            $idProcesamiento,
            $idAutorizacion,
            $tipoMovimiento,
            $consumir,
            $idUsuario,
            $observaciones
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        $movimientos[] = (int)$mysqli->insert_id;
        $stmt->close();

        $restantes -= $consumir;
    }

    return [
        'minutos_consumidos' => $minutosSolicitados,
        'minutos_restantes_solicitud' => $restantes,
        'movimientos' => $movimientos
    ];
}

/**
 * Solicita una utilización que deberá autorizarse.
 *
 * La solicitud no descuenta saldo hasta que se aprueba.
 */
function compensacionHorasSolicitarUtilizacion(
    mysqli $mysqli,
    int $idEmpleado,
    int $minutos,
    string $fecha,
    ?int $idJornada = null,
    ?int $idUsuario = null,
    ?string $motivo = null,
    ?string $observaciones = null
): int {
    if ($minutos <= 0) {
        throw new InvalidArgumentException(
            'Los minutos solicitados deben ser mayores que cero.'
        );
    }

    $saldo = compensacionHorasObtenerSaldo($mysqli, $idEmpleado, $fecha);

    if ($saldo['minutos_disponibles'] < $minutos) {
        throw new RuntimeException(
            'El empleado no tiene saldo suficiente para solicitar esta utilización.'
        );
    }

    $tipoOperacion = 'utilizacion';
    $estado = 'pendiente';

    $stmt = $mysqli->prepare("
        INSERT INTO horas_compensacion_autorizaciones (
            idempleado,
            tipo_operacion,
            fecha_operacion,
            minutos_autorizados,
            estado,
            idjornada,
            idusuario_solicita,
            motivo,
            observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param(
        'issisiiss',
        $idEmpleado,
        $tipoOperacion,
        $fecha,
        $minutos,
        $estado,
        $idJornada,
        $idUsuario,
        $motivo,
        $observaciones
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    $idAutorizacion = (int)$mysqli->insert_id;
    $stmt->close();

    return $idAutorizacion;
}

/**
 * Aprueba o rechaza una solicitud.
 *
 * Para utilizar este método, el llamador debe haber iniciado
 * una transacción y debe hacer COMMIT solo si todo finaliza bien.
 */
function compensacionHorasResolverAutorizacion(
    mysqli $mysqli,
    int $idAutorizacion,
    string $decision,
    int $idUsuarioAutoriza
): array {
    if (!in_array($decision, ['autorizar', 'rechazar'], true)) {
        throw new InvalidArgumentException(
            'La decisión debe ser autorizar o rechazar.'
        );
    }

    $stmt = $mysqli->prepare("
        SELECT *
        FROM horas_compensacion_autorizaciones
        WHERE idautorizacion = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('i', $idAutorizacion);
    $stmt->execute();

    $autorizacion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$autorizacion) {
        throw new RuntimeException('La autorización solicitada no existe.');
    }

    if ($autorizacion['estado'] !== 'pendiente') {
        throw new RuntimeException(
            'La solicitud ya fue resuelta y no se puede volver a procesar.'
        );
    }

    $nuevoEstado = $decision === 'autorizar' ? 'autorizada' : 'rechazada';

    if ($decision === 'autorizar') {
        $idEmpleado = (int)$autorizacion['idempleado'];
        $minutos = (int)$autorizacion['minutos_autorizados'];
        $fecha = $autorizacion['fecha_operacion'];
        $idJornada = $autorizacion['idjornada'] !== null
            ? (int)$autorizacion['idjornada']
            : null;

        if ($autorizacion['tipo_operacion'] === 'generacion') {
            if (empty($autorizacion['idprocesamiento']) ||
                empty($autorizacion['tipo_tramo_origen'])) {
                throw new RuntimeException(
                    'La autorización de generación no tiene procesamiento o tipo de tramo asociado.'
                );
            }

            $idLote = compensacionHorasCrearLote(
                $mysqli,
                $idEmpleado,
                $idJornada ?? 0,
                (int)$autorizacion['idprocesamiento'],
                $fecha,
                $autorizacion['tipo_tramo_origen'],
                $minutos,
                $idAutorizacion
            );

            $resultadoOperacion = ['idlote' => $idLote];
        } else {
            $resultadoOperacion = compensacionHorasConsumirSaldo(
                $mysqli,
                $idEmpleado,
                $minutos,
                $fecha,
                $idJornada,
                null,
                $idAutorizacion,
                $idUsuarioAutoriza
            );
        }
    } else {
        $resultadoOperacion = [];
    }

    $stmt = $mysqli->prepare("
        UPDATE horas_compensacion_autorizaciones
        SET estado = ?,
            idusuario_autoriza = ?,
            fecha_resolucion = NOW()
        WHERE idautorizacion = ?
          AND estado = 'pendiente'
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param(
        'sii',
        $nuevoEstado,
        $idUsuarioAutoriza,
        $idAutorizacion
    );

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    if ($stmt->affected_rows !== 1) {
        $stmt->close();

        throw new RuntimeException(
            'No se pudo actualizar la autorización; verificá si fue resuelta por otro usuario.'
        );
    }

    $stmt->close();

    return [
        'idautorizacion' => $idAutorizacion,
        'estado' => $nuevoEstado,
        'resultado' => $resultadoOperacion
    ];
}


/**
 * Vence los saldos cuya fecha de vencimiento ya pasó.
 *
 * Ejecutar dentro de una transacción.
 *
 * @param mysqli $mysqli
 * @param string|null $fecha Fecha de referencia (Y-m-d).
 * @param string $modoVencimiento automatico o revision.
 */
function compensacionHorasProcesarVencimientos(
    mysqli $mysqli,
    ?string $fecha = null,
    string $modoVencimiento = 'automatico'
): array {
    $fecha = $fecha ?: date('Y-m-d');

    if (!in_array($modoVencimiento, ['automatico', 'revision', 'manual'], true)) {
        throw new InvalidArgumentException('El modo de vencimiento no es válido.');
    }

    if ($modoVencimiento === 'revision') {
        return [
            'procesados' => 0,
            'minutos_vencidos' => 0,
            'pendientes_revision' => 0
        ];
    }

    $stmt = $mysqli->prepare("
        SELECT
            idlote,
            idempleado,
            idjornada,
            idprocesamiento,
            minutos_disponibles
        FROM horas_compensacion_lotes
        WHERE estado = 'disponible'
          AND minutos_disponibles > 0
          AND fecha_vencimiento IS NOT NULL
          AND fecha_vencimiento < ?
        ORDER BY fecha_vencimiento ASC, idlote ASC
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('s', $fecha);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    $resultado = $stmt->get_result();
    $lotes = [];

    while ($fila = $resultado->fetch_assoc()) {
        $lotes[] = $fila;
    }

    $stmt->close();

    $procesados = 0;
    $minutosVencidos = 0;

    foreach ($lotes as $lote) {
        $idLote = (int) $lote['idlote'];
        $idEmpleado = (int) $lote['idempleado'];
        $minutos = (int) $lote['minutos_disponibles'];

        if ($minutos <= 0) {
            continue;
        }

        $stmt = $mysqli->prepare("
            UPDATE horas_compensacion_lotes
            SET minutos_disponibles = 0,
                estado = 'vencido'
            WHERE idlote = ?
              AND estado = 'disponible'
              AND minutos_disponibles > 0
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param('i', $idLote);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        $actualizado = $stmt->affected_rows === 1;
        $stmt->close();

        if (!$actualizado) {
            continue;
        }

        $tipoMovimiento = 'vencimiento';

        $observaciones = $modoVencimiento === 'manual'
            ? 'Vencimiento manual tras revisión del saldo compensatorio.'
            : 'Vencimiento automático del saldo compensatorio.';

        $idJornada = $lote['idjornada'] !== null
            ? (int) $lote['idjornada']
            : null;

        $idProcesamiento = $lote['idprocesamiento'] !== null
            ? (int) $lote['idprocesamiento']
            : null;

        $stmt = $mysqli->prepare("
            INSERT INTO horas_compensacion_movimientos (
                idempleado,
                idlote,
                idjornada,
                idprocesamiento,
                tipo_movimiento,
                minutos,
                estado,
                observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, 'vigente', ?)
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param(
            'iiiisis',
            $idEmpleado,
            $idLote,
            $idJornada,
            $idProcesamiento,
            $tipoMovimiento,
            $minutos,
            $observaciones
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        $stmt->close();

        $procesados++;
        $minutosVencidos += $minutos;
    }

    return [
        'procesados' => $procesados,
        'minutos_vencidos' => $minutosVencidos,
        'pendientes_revision' => 0
    ];
}

/**
 * Revierte los movimientos de compensación vinculados a un procesamiento.
 *
 * Debe llamarse dentro de la misma transacción en la que se revierte
 * el procesamiento de jornada.
 */

function compensacionHorasRevertirProcesamiento(
    mysqli $mysqli,
    int $idProcesamiento,
    ?int $idUsuario = null,
    string $motivoAnulacion = ''
): array {

    $motivoAnulacion = trim($motivoAnulacion);

    if ($motivoAnulacion === '') {
        throw new InvalidArgumentException(
            'El motivo de la anulación es obligatorio.'
        );
    }
    /*
     * 1. Bloquear y anular las autorizaciones vinculadas
     *    al procesamiento que se está revirtiendo.
     */
    $stmt = $mysqli->prepare("
        SELECT idautorizacion, estado
        FROM horas_compensacion_autorizaciones
        WHERE idprocesamiento = ?
          AND estado IN ('pendiente', 'autorizada')
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('i', $idProcesamiento);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    $resultado = $stmt->get_result();
    $autorizaciones = [];

    while ($fila = $resultado->fetch_assoc()) {
        $autorizaciones[] = $fila;
    }

    $stmt->close();

    
    $autorizacionesAnuladas = 0;

    if (!empty($autorizaciones)) {
        $stmt = $mysqli->prepare("
            UPDATE horas_compensacion_autorizaciones
            SET estado = 'anulada',
                fecha_resolucion = NOW(),
                observaciones = CASE
                    WHEN NULLIF(TRIM(observaciones), '') IS NULL
                        THEN ?
                    ELSE CONCAT(
                        observaciones,
                        '\nMotivo de anulación: ',
                        ?
                    )
                END
            WHERE idprocesamiento = ?
              AND estado IN ('pendiente', 'autorizada')
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param(
            'ssi',
            $motivoAnulacion,
            $motivoAnulacion,
            $idProcesamiento
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        $autorizacionesAnuladas = $stmt->affected_rows;
        $stmt->close();
    }

    /*
     * 2. Buscar los movimientos vigentes del procesamiento.
     */
    $stmt = $mysqli->prepare("
        SELECT *
        FROM horas_compensacion_movimientos
        WHERE idprocesamiento = ?
          AND estado = 'vigente'
          AND tipo_movimiento IN (
              'generacion',
              'utilizacion',
              'vencimiento'
          )
        ORDER BY idmovimiento DESC
        FOR UPDATE
    ");

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('i', $idProcesamiento);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException($error);
    }

    $resultado = $stmt->get_result();
    $movimientos = [];

    while ($fila = $resultado->fetch_assoc()) {
        $movimientos[] = $fila;
    }

    $stmt->close();

    $revertidos = 0;

    foreach ($movimientos as $movimiento) {
        $idMovimiento = (int)$movimiento['idmovimiento'];

        $idLote = $movimiento['idlote'] !== null
            ? (int)$movimiento['idlote']
            : null;

        $idEmpleado = (int)$movimiento['idempleado'];
        $minutos = (int)$movimiento['minutos'];
        $tipo = $movimiento['tipo_movimiento'];

        /*
         * 3. Revertir generaciones de lotes.
         */
        if ($idLote !== null && $tipo === 'generacion') {
            $stmt = $mysqli->prepare("
                SELECT idmovimiento
                FROM horas_compensacion_movimientos
                WHERE idlote = ?
                  AND tipo_movimiento = 'utilizacion'
                  AND estado = 'vigente'
                  AND (
                      idprocesamiento IS NULL
                      OR idprocesamiento <> ?
                  )
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new RuntimeException($mysqli->error);
            }

            $stmt->bind_param('ii', $idLote, $idProcesamiento);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException($error);
            }

            $utilizacionExterna = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($utilizacionExterna) {
                throw new RuntimeException(
                    'No se puede revertir el procesamiento: el lote de ' .
                    'compensación #' . $idLote .
                    ' tiene horas utilizadas por otro procesamiento.'
                );
            }

            $estadoLote = 'revertido';

            $stmt = $mysqli->prepare("
                UPDATE horas_compensacion_lotes
                SET minutos_disponibles = 0,
                    estado = ?
                WHERE idlote = ?
            ");

            if (!$stmt) {
                throw new RuntimeException($mysqli->error);
            }

            $stmt->bind_param('si', $estadoLote, $idLote);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException($error);
            }

            $stmt->close();
        }

        /*
         * 4. Restaurar el saldo de utilizaciones y vencimientos.
         */
        if (
            $idLote !== null &&
            in_array($tipo, ['utilizacion', 'vencimiento'], true)
        ) {
            $stmt = $mysqli->prepare("
                SELECT
                    minutos_disponibles,
                    minutos_generados,
                    fecha_vencimiento,
                    estado
                FROM horas_compensacion_lotes
                WHERE idlote = ?
                LIMIT 1
                FOR UPDATE
            ");

            if (!$stmt) {
                throw new RuntimeException($mysqli->error);
            }

            $stmt->bind_param('i', $idLote);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new RuntimeException($error);
            }

            $lote = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($lote) {
                $hoy = date('Y-m-d');

                $noVencido =
                    $lote['fecha_vencimiento'] === null ||
                    $lote['fecha_vencimiento'] >= $hoy;

                if ($noVencido && $lote['estado'] !== 'revertido') {
                    $saldoActual = (int)$lote['minutos_disponibles'];
                    $saldoMaximo = (int)$lote['minutos_generados'];

                    $nuevoSaldo = min(
                        $saldoMaximo,
                        $saldoActual + $minutos
                    );

                    $nuevoEstado = $nuevoSaldo > 0
                        ? 'disponible'
                        : 'agotado';

                    $stmt = $mysqli->prepare("
                        UPDATE horas_compensacion_lotes
                        SET minutos_disponibles = ?,
                            estado = ?
                        WHERE idlote = ?
                    ");

                    if (!$stmt) {
                        throw new RuntimeException($mysqli->error);
                    }

                    $stmt->bind_param(
                        'isi',
                        $nuevoSaldo,
                        $nuevoEstado,
                        $idLote
                    );

                    if (!$stmt->execute()) {
                        $error = $stmt->error;
                        $stmt->close();
                        throw new RuntimeException($error);
                    }

                    $stmt->close();

                    $tipoReversion = $tipo === 'utilizacion'
                        ? 'reversion_utilizacion'
                        : 'reversion_vencimiento';

                    $idJornada = $movimiento['idjornada'] !== null
                        ? (int)$movimiento['idjornada']
                        : null;

                    $idAutorizacion = $movimiento['idautorizacion'] !== null
                        ? (int)$movimiento['idautorizacion']
                        : null;

                    $observaciones =
                        'Reversión del movimiento #' . $idMovimiento;

                    $stmt = $mysqli->prepare("
                        INSERT INTO horas_compensacion_movimientos (
                            idempleado,
                            idlote,
                            idjornada,
                            idprocesamiento,
                            idautorizacion,
                            idmovimiento_referencia,
                            tipo_movimiento,
                            minutos,
                            estado,
                            idusuario,
                            observaciones
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?,
                            'vigente', ?, ?
                        )
                    ");

                    if (!$stmt) {
                        throw new RuntimeException($mysqli->error);
                    }

                    $stmt->bind_param(
                        'iiiiiisiis',
                        $idEmpleado,
                        $idLote,
                        $idJornada,
                        $idProcesamiento,
                        $idAutorizacion,
                        $idMovimiento,
                        $tipoReversion,
                        $minutos,
                        $idUsuario,
                        $observaciones
                    );

                    if (!$stmt->execute()) {
                        $error = $stmt->error;
                        $stmt->close();
                        throw new RuntimeException($error);
                    }

                    $stmt->close();
                }
            }
        }

        /*
         * 5. Marcar el movimiento original como revertido.
         */
        $stmt = $mysqli->prepare("
            UPDATE horas_compensacion_movimientos
            SET estado = 'revertido'
            WHERE idmovimiento = ?
              AND estado = 'vigente'
        ");

        if (!$stmt) {
            throw new RuntimeException($mysqli->error);
        }

        $stmt->bind_param('i', $idMovimiento);

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException($error);
        }

        if ($stmt->affected_rows === 1) {
            $revertidos++;
        }

        $stmt->close();
    }

    return [
        'idprocesamiento' => $idProcesamiento,
        'autorizaciones_anuladas' => $autorizacionesAnuladas,
        'movimientos_revertidos' => $revertidos
    ];
}