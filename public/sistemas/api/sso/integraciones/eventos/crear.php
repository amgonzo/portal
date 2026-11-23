<?php

try {
    require_once __DIR__ . '/_bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido.');
    }

    $origen = obtenerIdAplicacionPorSlug(
        $mysqli,
        $_POST['origen'] ?? ''
    );

    $destino = obtenerIdAplicacionPorSlug(
        $mysqli,
        $_POST['destino'] ?? ''
    );
    
    $tipo = trim($_POST['tipo_evento'] ?? '');
    $entidad = trim($_POST['entidad'] ?? '');
    $identidad = trim($_POST['identidad'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $datosRaw = $_POST['datos'] ?? null;

    if ($origen <= 0 || $destino <= 0 || $tipo === '' || $entidad === '' || $identidad === '' || $titulo === '') {
        throw new Exception('Faltan datos obligatorios.');
    }

    if ($origen === $destino) {
        throw new Exception('La aplicación de origen y destino no pueden ser iguales.');
    }

    validarAplicacionEmpresa($mysqli, $idEmpresa, $origen);
    validarAplicacionEmpresa($mysqli, $idEmpresa, $destino);

    // Si no existe integración activa, no se genera ningún evento.
    if (!validarIntegracionActiva($mysqli, $idEmpresa, $origen, $destino)) {
        responderEvento('ok', 'No existe una integración activa. No se generó ningún evento.', [
            'generado' => false
        ]);
    }

    $datosJson = null;

    if ($datosRaw !== null && $datosRaw !== '') {
        $datos = is_string($datosRaw) ? json_decode($datosRaw, true) : $datosRaw;

        if (json_last_error() !== JSON_ERROR_NONE && is_string($datosRaw)) {
            throw new Exception('El campo datos no contiene JSON válido.');
        }

        $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE);

        if ($datosJson === false) {
            throw new Exception('No se pudieron preparar los datos del evento.');
        }
    }

    $stmt = $mysqli->prepare("
        INSERT INTO integraciones_eventos
        (
            idempresa,
            idaplicacion_origen,
            idaplicacion_destino,
            tipo_evento,
            entidad,
            identidad,
            titulo,
            descripcion,
            datos
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        throw new Exception('No se pudo preparar el evento: ' . $mysqli->error);
    }

    $stmt->bind_param(
        'iiissssss',
        $idEmpresa,
        $origen,
        $destino,
        $tipo,
        $entidad,
        $identidad,
        $titulo,
        $descripcion,
        $datosJson
    );

    if (!$stmt->execute()) {
        throw new Exception('No se pudo registrar el evento: ' . $stmt->error);
    }

    $idEvento = (int)$stmt->insert_id;
    $stmt->close();

    registrarAuditoriaEvento($mysqli, 'crear_evento_integracion', $idEvento, $idUsuarioLog, null, [
        'tipo_evento' => $tipo,
        'entidad' => $entidad,
        'identidad' => $identidad,
        'origen' => $origen,
        'destino' => $destino
    ]);

    responderEvento('ok', 'Evento registrado correctamente.', [
        'generado' => true,
        'idevento' => $idEvento
    ]);

} catch (Throwable $e) {
    responderEvento('error', $e->getMessage(), null, 400);
}
