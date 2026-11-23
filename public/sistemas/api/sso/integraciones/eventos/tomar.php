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

    if ($idEvento <= 0) {
        throw new Exception('Datos incompletos.');
    }

    validarAplicacionEmpresa($mysqli, $idEmpresa, $destino);

    // UPDATE condicional: solamente un operador puede tomar el evento.
    $stmt = $mysqli->prepare("
        UPDATE integraciones_eventos
        SET
            estado = 'en_proceso',
            idusuario_asignado = ?,
            fecha_asignacion = NOW()
        WHERE idevento = ?
          AND idempresa = ?
          AND idaplicacion_destino = ?
          AND estado = 'pendiente'
          AND idusuario_asignado IS NULL
    ");

    if (!$stmt) {
        throw new Exception('No se pudo preparar la asignación: ' . $mysqli->error);
    }

    $stmt->bind_param('iiii', $idUsuarioLog, $idEvento, $idEmpresa, $destino);
    $stmt->execute();
    $tomado = $stmt->affected_rows === 1;
    $stmt->close();

    if (!$tomado) {
        throw new Exception('El evento ya fue tomado o no está disponible.');
    }

    registrarAuditoriaEvento($mysqli, 'tomar_evento_integracion', $idEvento, $idUsuarioLog);

    responderEvento('ok', 'Evento asignado correctamente.');

} catch (Throwable $e) {
    responderEvento('error', $e->getMessage(), null, 400);
}
