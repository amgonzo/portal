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

    $estado = trim($_GET['estado'] ?? '');
    $limite = (int)($_GET['limite'] ?? 100);
    $limite = max(1, min($limite, 300));

    validarAplicacionEmpresa($mysqli, $idEmpresa, $destino);

    $sql = "
        SELECT
            ev.idevento,
            ev.idaplicacion_origen,
            ao.nombre AS aplicacion_origen,
            ev.idaplicacion_destino,
            ad.nombre AS aplicacion_destino,
            ev.tipo_evento,
            ev.entidad,
            ev.identidad,
            ev.titulo,
            ev.descripcion,
            ev.datos,
            ev.estado,
            ev.idusuario_asignado,
            ev.fecha_asignacion,
            ev.idusuario_resolvio,
            ev.fecha_resolucion,
            ev.resultado,
            ev.fecha_creacion,
            ev.fecha_actualizacion,
            u.nombreapellido AS usuario_asignado
        FROM integraciones_eventos ev
        INNER JOIN aplicaciones ao
            ON ao.idaplicacion = ev.idaplicacion_origen
        INNER JOIN aplicaciones ad
            ON ad.idaplicacion = ev.idaplicacion_destino
        LEFT JOIN usuarios u
            ON u.idusuario = ev.idusuario_asignado
        WHERE ev.idempresa = ?
          AND ev.idaplicacion_destino = ?
    ";

    if ($estado !== '') {
        $estadosValidos = ['pendiente', 'en_proceso', 'resuelto', 'error'];

        if (!in_array($estado, $estadosValidos, true)) {
            throw new Exception('Estado no válido.');
        }

        $sql .= " AND ev.estado = ?";
    }

    $sql .= " ORDER BY
        CASE ev.estado
            WHEN 'pendiente' THEN 1
            WHEN 'en_proceso' THEN 2
            WHEN 'error' THEN 3
            ELSE 4
        END,
        ev.fecha_creacion DESC
        LIMIT ?";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception('No se pudo preparar el listado: ' . $mysqli->error);
    }

    if ($estado !== '') {
        $stmt->bind_param('iisi', $idEmpresa, $destino, $estado, $limite);
    } else {
        $stmt->bind_param('iii', $idEmpresa, $destino, $limite);
    }

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

    responderEvento('ok', 'Eventos obtenidos correctamente.', $eventos);

} catch (Throwable $e) {
    responderEvento('error', $e->getMessage(), null, 400);
}
