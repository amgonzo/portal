<?php

try {
    require_once __DIR__ . '/_bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido.');
    }

    $destino = obtenerIdAplicacionPorSlug(
        $mysqli,
        $_GET['destino'] ?? ''
    );
    
    $limite = (int)($_GET['limite'] ?? 200);
    $limite = max(1, min($limite, 500));

    validarAplicacionEmpresa($mysqli, $idEmpresa, $destino);

    $stmt = $mysqli->prepare("
        SELECT
            ev.*,
            ao.nombre AS aplicacion_origen,
            ad.nombre AS aplicacion_destino
        FROM integraciones_eventos ev
        INNER JOIN aplicaciones ao
            ON ao.idaplicacion = ev.idaplicacion_origen
        INNER JOIN aplicaciones ad
            ON ad.idaplicacion = ev.idaplicacion_destino
        WHERE ev.idempresa = ?
          AND ev.idaplicacion_destino = ?
          AND ev.estado IN ('resuelto', 'error')
        ORDER BY ev.fecha_resolucion DESC, ev.fecha_creacion DESC
        LIMIT ?
    ");

    if (!$stmt) {
        throw new Exception('No se pudo preparar el historial: ' . $mysqli->error);
    }

    $stmt->bind_param('iii', $idEmpresa, $destino, $limite);
    $stmt->execute();

    $resultado = $stmt->get_result();
    $eventos = [];

    while ($fila = $resultado->fetch_assoc()) {
        if (!empty($fila['datos'])) {
            $fila['datos'] = json_decode($fila['datos'], true);
        }
        $eventos[] = $fila;
    }

    $stmt->close();

    responderEvento('ok', 'Historial obtenido correctamente.', $eventos);

} catch (Throwable $e) {
    responderEvento('error', $e->getMessage(), null, 400);
}
