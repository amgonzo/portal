<?php

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Throwable $e) {
    // Se continúa si el entorno ya está cargado.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

$userAuth = validarTokenAPI($mysqli ?? null);
//validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

if (empty($empresa['idempresa'])) {
    throw new Exception('No se pudo determinar la empresa activa.');
}

$idEmpresa = (int)$empresa['idempresa'];
$idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

function responderEvento($status, $msg, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);

    $respuesta = [
        'status' => $status,
        'msg' => $msg
    ];

    if ($data !== null) {
        $respuesta['data'] = $data;
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

function registrarAuditoriaEvento($mysqli, $accion, $idevento, $idUsuario, $anterior = null, $nuevo = null)
{
    if (function_exists('registrarLog')) {
        @registrarLog(
            $mysqli,
            $accion,
            'integraciones_eventos',
            $idevento,
            $idUsuario,
            $anterior,
            $nuevo
        );
    }
}

function validarAplicacionEmpresa($mysqli, $idEmpresa, $idAplicacion)
{
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS total
        FROM empresas_aplicaciones ea
        INNER JOIN aplicaciones a
            ON a.idaplicacion = ea.idaplicacion
        WHERE ea.idempresa = ?
          AND ea.idaplicacion = ?
          AND ea.activo = 1
          AND a.activo = 1
    ");

    if (!$stmt) {
        throw new Exception('No se pudo validar la aplicación: ' . $mysqli->error);
    }

    $stmt->bind_param('ii', $idEmpresa, $idAplicacion);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    if ($total !== 1) {
        throw new Exception('La aplicación no está habilitada para esta empresa.');
    }
}

function validarIntegracionActiva($mysqli, $idEmpresa, $origen, $destino)
{
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) AS total
        FROM aplicaciones_integraciones
        WHERE idempresa = ?
          AND idaplicacion_origen = ?
          AND idaplicacion_destino = ?
          AND activo = 1
    ");

    if (!$stmt) {
        throw new Exception('No se pudo validar la integración: ' . $mysqli->error);
    }

    $stmt->bind_param('iii', $idEmpresa, $origen, $destino);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    return $total === 1;
}

function obtenerIdAplicacionPorSlug($mysqli, $slug)
{
    $slug = trim($slug);

    if ($slug === '') {
        throw new Exception('Debe indicar la aplicación.');
    }

    $stmt = $mysqli->prepare("
        SELECT idaplicacion
        FROM aplicaciones
        WHERE slug = ?
          AND activo = 1
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception('No se pudo consultar la aplicación.');
    }

    $stmt->bind_param('s', $slug);
    $stmt->execute();

    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        throw new Exception('La aplicación indicada no existe o está inactiva.');
    }

    return (int)$fila['idaplicacion'];
}
