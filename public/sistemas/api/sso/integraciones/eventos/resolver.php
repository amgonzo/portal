<?php

try {
    require_once __DIR__ . '/_bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $idEvento = (int)($_POST['idevento'] ?? 0);

    $destino = obtenerIdAplicacionPorSlug(
        $mysqli,
        $_POST['destino'] ?? ''
    );

    $estado = trim($_POST['estado'] ?? 'resuelto');
    $resultado = trim($_POST['resultado'] ?? '');

    if ($idEvento <= 0) {
        throw new Exception('Datos incompletos.');
    }

    if (!in_array($estado, ['resuelto', 'error'], true)) {
        throw new Exception('El estado final debe ser resuelto o error.');
    }

    validarAplicacionEmpresa($mysqli, $idEmpresa, $destino);

    $stmt = $mysqli->prepare("
        SELECT idusuario_asignado, estado
        FROM integraciones_eventos
        WHERE idevento = ?
          AND idempresa = ?
          AND idaplicacion_destino = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('No se pudo consultar el evento: ' . $mysqli->error);
    }

    $stmt->bind_param('iii', $idEvento, $idEmpresa, $destino);
    $stmt->execute();
    $anterior = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$anterior) {
        throw new Exception('Evento no encontrado.');
    }

    if ((int)$anterior['idusuario_asignado'] !== $idUsuarioLog) {
        throw new Exception('Solamente el operador asignado puede resolver este evento.');
    }

    if ($anterior['estado'] !== 'en_proceso') {
        throw new Exception('El evento no está en proceso.');
    }

    $stmt = $mysqli->prepare("
        UPDATE integraciones_eventos
        SET
            estado = ?,
            idusuario_resolvio = ?,
            fecha_resolucion = NOW(),
            resultado = ?
        WHERE idevento = ?
          AND idempresa = ?
          AND idaplicacion_destino = ?
          AND estado = 'en_proceso'
          AND idusuario_asignado = ?
    ");

    if (!$stmt) {
        throw new Exception('No se pudo preparar la resolución: ' . $mysqli->error);
    }

    $stmt->bind_param(
        'sisiiii',
        $estado,
        $idUsuarioLog,
        $resultado,
        $idEvento,
        $idEmpresa,
        $destino,
        $idUsuarioLog
    );

    $stmt->execute();
    $actualizado = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$actualizado) {
        throw new Exception('No se pudo actualizar el evento.');
    }

    registrarAuditoriaEvento($mysqli, 'resolver_evento_integracion', $idEvento, $idUsuarioLog, $anterior, [
        'estado' => $estado,
        'resultado' => $resultado
    ]);

    responderEvento('ok', 'Evento actualizado correctamente.');

} catch (Throwable $e) {
    responderEvento('error', $e->getMessage(), null, 400);
}
