<?php
header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 1. RUTAS Y COMPOSER
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

// =========================================================
// 2. CARGAR .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();

} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

// =========================================================
// 3. CONFIGURACIÓN CENTRAL
// =========================================================

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

// =========================================================
// 4. RESPUESTA JSON
// =========================================================

function responderPuesto(int $codigo, array $datos): void
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// =========================================================
// 5. EJECUCIÓN
// =========================================================

try {

    // AUTENTICACIÓN

    $userAuth = validarTokenAPI($mysqli ?? null);

    validarPermisoEndpoint($mysqli, $userAuth);

    // EMPRESA ACTIVA

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        throw new Exception('No se pudo determinar la empresa activa.');
    }

    // CONEXIÓN A BASE DE DATOS DEL CLIENTE

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // ACCIÓN

    $action = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_GET['action'] ?? $_POST['action'] ?? '')
        : ($_GET['action'] ?? '');

    switch ($action) {

        // =================================================
        // LISTAR
        // =================================================

        case 'listar':

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Método no permitido.');
            }

            $stmt = $mysqli->prepare("
                SELECT
                    idpuesto,
                    nombre,
                    descripcion,
                    activo
                FROM core_puestos
                ORDER BY nombre ASC
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar consulta: ' . $mysqli->error
                );
            }

            if (!$stmt->execute()) {
                throw new Exception(
                    'Error al consultar puestos: ' . $stmt->error
                );
            }

            $resultado = $stmt->get_result();

            $datos = [];

            while ($row = $resultado->fetch_assoc()) {

                $row['idpuesto'] = (int)$row['idpuesto'];
                $row['activo'] = (int)$row['activo'];

                $datos[] = $row;
            }

            $stmt->close();

            responderPuesto(200, [
                'status' => 'ok',
                'data' => $datos
            ]);

            break;

        // =================================================
        // CREAR
        // =================================================

        case 'crear':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $nombre = trim($_POST['nombre'] ?? '');

            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($nombre === '') {
                throw new Exception('Debe indicar el nombre del puesto.');
            }

            if (mb_strlen($nombre) > 120) {
                throw new Exception('El nombre no puede superar los 120 caracteres.');
            }

            if (mb_strlen($descripcion) > 255) {
                throw new Exception('La descripción no puede superar los 255 caracteres.');
            }

            $descripcion = $descripcion !== '' ? $descripcion : null;

            $stmt = $mysqli->prepare("
                INSERT INTO core_puestos
                (
                    nombre,
                    descripcion,
                    activo
                )
                VALUES (?, ?, 1)
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar inserción: ' . $mysqli->error
                );
            }

            $stmt->bind_param(
                'ss',
                $nombre,
                $descripcion
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $codigoError = $stmt->errno;

                $stmt->close();

                if ($codigoError === 1062) {
                    throw new Exception('Ya existe un puesto con ese nombre.');
                }

                throw new Exception(
                    'No se pudo crear el puesto: ' . $error
                );
            }

            $idNuevo = $stmt->insert_id;

            $stmt->close();

            // AUDITORÍA

            if (function_exists('registrarLog')) {

                $idUsuarioLog = $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    'crear_puesto',
                    'core_puestos',
                    $idNuevo,
                    $idUsuarioLog,
                    null,
                    [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion
                    ]
                );
            }

            responderPuesto(200, [
                'status' => 'ok',
                'msg' => 'Puesto creado correctamente.',
                'idpuesto' => (int)$idNuevo
            ]);

            break;

        // =================================================
        // EDITAR
        // =================================================

        case 'editar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $idpuesto = (int)($_POST['idpuesto'] ?? 0);

            $nombre = trim($_POST['nombre'] ?? '');

            $descripcion = trim($_POST['descripcion'] ?? '');

            if ($idpuesto <= 0) {
                throw new Exception('ID de puesto inválido.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre del puesto es obligatorio.');
            }

            if (mb_strlen($nombre) > 120) {
                throw new Exception('El nombre no puede superar los 120 caracteres.');
            }

            if (mb_strlen($descripcion) > 255) {
                throw new Exception('La descripción no puede superar los 255 caracteres.');
            }

            $descripcion = $descripcion !== '' ? $descripcion : null;

            // BUSCAR REGISTRO ORIGINAL

            $stmt = $mysqli->prepare("
                SELECT *
                FROM core_puestos
                WHERE idpuesto = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar consulta: ' . $mysqli->error
                );
            }

            $stmt->bind_param('i', $idpuesto);

            if (!$stmt->execute()) {
                throw new Exception(
                    'Error al consultar puesto: ' . $stmt->error
                );
            }

            $puestoAnterior = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$puestoAnterior) {
                throw new Exception('Puesto no encontrado.');
            }

            // ACTUALIZAR

            $stmt = $mysqli->prepare("
                UPDATE core_puestos
                SET
                    nombre = ?,
                    descripcion = ?
                WHERE idpuesto = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar actualización: ' . $mysqli->error
                );
            }

            $stmt->bind_param(
                'ssi',
                $nombre,
                $descripcion,
                $idpuesto
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $codigoError = $stmt->errno;

                $stmt->close();

                if ($codigoError === 1062) {
                    throw new Exception('Ya existe otro puesto con ese nombre.');
                }

                throw new Exception(
                    'No se pudo actualizar el puesto: ' . $error
                );
            }

            $stmt->close();

            // AUDITORÍA

            if (function_exists('registrarLog')) {

                $idUsuarioLog = $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    'editar_puesto',
                    'core_puestos',
                    $idpuesto,
                    $idUsuarioLog,
                    $puestoAnterior,
                    [
                        'nombre' => $nombre,
                        'descripcion' => $descripcion
                    ]
                );
            }

            responderPuesto(200, [
                'status' => 'ok',
                'msg' => 'Puesto actualizado correctamente.'
            ]);

            break;

        // =================================================
        // CAMBIAR ESTADO
        // =================================================

        case 'estado':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $idpuesto = (int)($_POST['idpuesto'] ?? 0);

            $activo = (int)($_POST['activo'] ?? -1);

            if ($idpuesto <= 0) {
                throw new Exception('ID de puesto inválido.');
            }

            if ($activo !== 0 && $activo !== 1) {
                throw new Exception('Estado inválido.');
            }

            // BUSCAR REGISTRO

            $stmt = $mysqli->prepare("
                SELECT *
                FROM core_puestos
                WHERE idpuesto = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar consulta: ' . $mysqli->error
                );
            }

            $stmt->bind_param('i', $idpuesto);

            if (!$stmt->execute()) {
                throw new Exception(
                    'Error al consultar puesto: ' . $stmt->error
                );
            }

            $puestoAnterior = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (!$puestoAnterior) {
                throw new Exception('Puesto no encontrado.');
            }

            // ACTUALIZAR ESTADO

            $stmt = $mysqli->prepare("
                UPDATE core_puestos
                SET activo = ?
                WHERE idpuesto = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    'Error al preparar actualización: ' . $mysqli->error
                );
            }

            $stmt->bind_param(
                'ii',
                $activo,
                $idpuesto
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    'No se pudo cambiar el estado: ' . $stmt->error
                );
            }

            $stmt->close();

            // AUDITORÍA

            if (function_exists('registrarLog')) {

                $idUsuarioLog = $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    $activo === 1 ? 'alta_puesto' : 'baja_puesto',
                    'core_puestos',
                    $idpuesto,
                    $idUsuarioLog,
                    $puestoAnterior,
                    [
                        'activo' => $activo
                    ]
                );
            }

            $mensaje = $activo === 1
                ? 'Puesto activado correctamente.'
                : 'Puesto desactivado correctamente.';

            responderPuesto(200, [
                'status' => 'ok',
                'msg' => $mensaje
            ]);

            break;

        default:

            throw new Exception('Acción no válida.');
    }

} catch (Throwable $e) {

    error_log('[RRHH Puestos] ' . $e->getMessage());

    responderPuesto(500, [
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);

}