<?php

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

$transaccionIniciada = false;

try {

    $userAuth = validarTokenAPI($mysqli);
    validarPermisoEndpoint($mysqli, $userAuth);

    $idEmpresa = intval($_POST['idempresa'] ?? 0);
    $accion = $_POST['accion'] ?? 'guardar';

    if ($idEmpresa <= 0) {
        throw new Exception('Empresa no válida.');
    }

    $mysqli->begin_transaction();
    $transaccionIniciada = true;

    if (($_POST['accion'] ?? '') === 'eliminar') {

        $idIntegracion = intval($_POST['idintegracion'] ?? 0);

        if ($idIntegracion <= 0) {
            throw new Exception('Integración no válida.');
        }

        $stmt = $mysqli->prepare("
            DELETE FROM aplicaciones_integraciones
            WHERE id = ?
            AND idempresa = ?
        ");

        $stmt->bind_param('ii', $idIntegracion, $idEmpresa);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('No se encontró la integración.');
        }

        $stmt->close();

        $mysqli->commit();
        $transaccionIniciada = false;

        echo json_encode([
            'status' => 'ok',
            'msg' => 'Integración eliminada correctamente.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // CAMBIAR ESTADO DE UNA INTEGRACIÓN

    if ($accion === 'estado') {

        $idIntegracion = intval($_POST['idintegracion'] ?? 0);
        $activo = intval($_POST['activo'] ?? -1);

        if ($idIntegracion <= 0 || !in_array($activo, [0, 1], true)) {
            throw new Exception('Datos de integración no válidos.');
        }

        $stmt = $mysqli->prepare("
            UPDATE aplicaciones_integraciones
            SET activo = ?
            WHERE id = ?
            AND idempresa = ?
        ");

        $stmt->bind_param('iii', $activo, $idIntegracion, $idEmpresa);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {

            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT id
                FROM aplicaciones_integraciones
                WHERE id = ? AND idempresa = ?
            ");

            $stmt->bind_param('ii', $idIntegracion, $idEmpresa);
            $stmt->execute();

            $existe = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existe) {
                throw new Exception('No se encontró la integración.');
            }

        } else {
            $stmt->close();
        }

        $mysqli->commit();
        $transaccionIniciada = false;

        echo json_encode([
            'status' => 'ok',
            'msg' => 'Estado actualizado correctamente.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // AGREGAR UNA INTEGRACIÓN NUEVA
    if ($accion === 'agregar') {

        $origen = intval($_POST['idaplicacion_origen'] ?? 0);
        $destino = intval($_POST['idaplicacion_destino'] ?? 0);

        if ($origen <= 0 || $destino <= 0) {
            throw new Exception('Seleccioná ambas aplicaciones.');
        }

        if ($origen === $destino) {
            throw new Exception('El origen y el destino deben ser diferentes.');
        }

        // Verificar que ambas aplicaciones estén habilitadas
        // para esta empresa y activas globalmente.

        $stmt = $mysqli->prepare("
            SELECT COUNT(*) AS total
            FROM empresas_aplicaciones ea
            INNER JOIN aplicaciones a
                ON a.idaplicacion = ea.idaplicacion
            WHERE ea.idempresa = ?
              AND ea.idaplicacion IN (?, ?)
              AND ea.activo = 1
              AND a.activo = 1
        ");

        $stmt->bind_param('iii', $idEmpresa, $origen, $destino);
        $stmt->execute();

        $total = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($total !== 2) {
            throw new Exception(
                'Ambas aplicaciones deben estar habilitadas para esta empresa.'
            );
        }

        // Evitar duplicados dentro de la misma empresa.

        $stmt = $mysqli->prepare("
            SELECT id
            FROM aplicaciones_integraciones
            WHERE idempresa = ?
              AND idaplicacion_origen = ?
              AND idaplicacion_destino = ?
            LIMIT 1
        ");

        $stmt->bind_param('iii', $idEmpresa, $origen, $destino);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($existe) {
            throw new Exception('Esta integración ya existe para la empresa.');
        }

        // Crear la integración directamente para la empresa.

        $stmt = $mysqli->prepare("
            INSERT INTO aplicaciones_integraciones (
                idempresa,
                idaplicacion_origen,
                idaplicacion_destino,
                activo
            ) VALUES (?, ?, ?, 1)
        ");

        $stmt->bind_param('iii', $idEmpresa, $origen, $destino);
        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        $transaccionIniciada = false;

        echo json_encode([
            'status' => 'ok',
            'msg' => 'Integración agregada correctamente.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // GUARDAR LOS SWITCHES EXISTENTES

    $integraciones = $_POST['integraciones'] ?? [];

    if (!is_array($integraciones)) {
        throw new Exception('Integraciones no válidas.');
    }

    $stmt = $mysqli->prepare("
        UPDATE aplicaciones_integraciones
        SET activo = 0
        WHERE idempresa = ?
    ");

    $stmt->bind_param('i', $idEmpresa);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("
        UPDATE aplicaciones_integraciones ai

        INNER JOIN empresas_aplicaciones eao
            ON eao.idempresa = ai.idempresa
            AND eao.idaplicacion = ai.idaplicacion_origen
            AND eao.activo = 1

        INNER JOIN empresas_aplicaciones ead
            ON ead.idempresa = ai.idempresa
            AND ead.idaplicacion = ai.idaplicacion_destino
            AND ead.activo = 1

        INNER JOIN aplicaciones ao
            ON ao.idaplicacion = ai.idaplicacion_origen
            AND ao.activo = 1

        INNER JOIN aplicaciones ad
            ON ad.idaplicacion = ai.idaplicacion_destino
            AND ad.activo = 1

        SET ai.activo = 1

        WHERE ai.idempresa = ?
          AND ai.id = ?
    ");

    foreach ($integraciones as $idIntegracion) {

        $idIntegracion = intval($idIntegracion);

        if ($idIntegracion <= 0) {
            continue;
        }

        $stmt->bind_param('ii', $idEmpresa, $idIntegracion);
        $stmt->execute();
    }

    $stmt->close();

    $mysqli->commit();
    $transaccionIniciada = false;

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Integraciones actualizadas correctamente.'
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        $mysqli->rollback();
    }

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}