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
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

function responderTipoLicencia(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {

    $userAuth = validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        throw new Exception('No se pudo determinar la empresa activa.');
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    $action = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? ($_GET['action'] ?? $_POST['action'] ?? '')
        : ($_GET['action'] ?? '');

    switch ($action) {

        case 'listar':

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Método no permitido.');
            }

            $stmt = $mysqli->prepare("
                SELECT
                    idtipo_licencia,
                    nombre,
                    requiere_documentacion,
                    descuenta_vacaciones,
                    activo
                FROM rrhh_tipos_licencia
                ORDER BY nombre ASC
            ");

            if (!$stmt || !$stmt->execute()) {
                throw new Exception('Error al consultar tipos de licencia.');
            }

            $resultado = $stmt->get_result();
            $datos = [];

            while ($row = $resultado->fetch_assoc()) {
                $row['idtipo_licencia'] = (int)$row['idtipo_licencia'];
                $row['requiere_documentacion'] = (int)$row['requiere_documentacion'];
                $row['descuenta_vacaciones'] = (int)$row['descuenta_vacaciones'];
                $row['activo'] = (int)$row['activo'];

                $datos[] = $row;
            }

            $stmt->close();

            responderTipoLicencia(200, [
                'status' => 'ok',
                'data' => $datos
            ]);

            break;

        case 'crear':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $nombre = trim($_POST['nombre'] ?? '');
            $requiereDocumentacion = (int)($_POST['requiere_documentacion'] ?? 0);
            $descuentaVacaciones = (int)($_POST['descuenta_vacaciones'] ?? 0);

            if ($nombre === '') {
                throw new Exception('Debe indicar el nombre del tipo de licencia.');
            }

            if (mb_strlen($nombre) > 100) {
                throw new Exception('El nombre no puede superar los 100 caracteres.');
            }

            if (!in_array($requiereDocumentacion, [0, 1], true)) {
                throw new Exception('Valor de documentación inválido.');
            }

            if (!in_array($descuentaVacaciones, [0, 1], true)) {
                throw new Exception('Valor de descuento de vacaciones inválido.');
            }

            $stmt = $mysqli->prepare("
                INSERT INTO rrhh_tipos_licencia
                (
                    nombre,
                    requiere_documentacion,
                    descuenta_vacaciones,
                    activo
                )
                VALUES (?, ?, ?, 1)
            ");

            if (!$stmt) {
                throw new Exception('Error al preparar la inserción: ' . $mysqli->error);
            }

            $stmt->bind_param(
                'sii',
                $nombre,
                $requiereDocumentacion,
                $descuentaVacaciones
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $codigoError = $stmt->errno;
                $stmt->close();

                if ($codigoError === 1062) {
                    throw new Exception('Ya existe un tipo de licencia con ese nombre.');
                }

                throw new Exception('No se pudo crear el tipo de licencia: ' . $error);
            }

            $idNuevo = $stmt->insert_id;
            $stmt->close();

            if (function_exists('registrarLog')) {
                @registrarLog(
                    $mysqli,
                    'crear_tipo_licencia',
                    'rrhh_tipos_licencia',
                    $idNuevo,
                    $userAuth['idusuario'] ?? null,
                    null,
                    [
                        'nombre' => $nombre,
                        'requiere_documentacion' => $requiereDocumentacion,
                        'descuenta_vacaciones' => $descuentaVacaciones
                    ]
                );
            }

            responderTipoLicencia(200, [
                'status' => 'ok',
                'msg' => 'Tipo de licencia creado correctamente.',
                'idtipo_licencia' => (int)$idNuevo
            ]);

            break;

        case 'editar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $id = (int)($_POST['idtipo_licencia'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $requiereDocumentacion = (int)($_POST['requiere_documentacion'] ?? 0);
            $descuentaVacaciones = (int)($_POST['descuenta_vacaciones'] ?? 0);

            if ($id <= 0) {
                throw new Exception('ID de tipo de licencia inválido.');
            }

            if ($nombre === '') {
                throw new Exception('El nombre es obligatorio.');
            }

            if (mb_strlen($nombre) > 100) {
                throw new Exception('El nombre no puede superar los 100 caracteres.');
            }

            if (!in_array($requiereDocumentacion, [0, 1], true)) {
                throw new Exception('Valor de documentación inválido.');
            }

            if (!in_array($descuentaVacaciones, [0, 1], true)) {
                throw new Exception('Valor de descuento de vacaciones inválido.');
            }

            $stmt = $mysqli->prepare("
                SELECT *
                FROM rrhh_tipos_licencia
                WHERE idtipo_licencia = ?
                LIMIT 1
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$anterior) {
                throw new Exception('Tipo de licencia no encontrado.');
            }

            $stmt = $mysqli->prepare("
                UPDATE rrhh_tipos_licencia
                SET
                    nombre = ?,
                    requiere_documentacion = ?,
                    descuenta_vacaciones = ?
                WHERE idtipo_licencia = ?
            ");

            if (!$stmt) {
                throw new Exception('Error al preparar actualización: ' . $mysqli->error);
            }

            $stmt->bind_param(
                'siii',
                $nombre,
                $requiereDocumentacion,
                $descuentaVacaciones,
                $id
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $codigoError = $stmt->errno;
                $stmt->close();

                if ($codigoError === 1062) {
                    throw new Exception('Ya existe otro tipo de licencia con ese nombre.');
                }

                throw new Exception('No se pudo actualizar: ' . $error);
            }

            $stmt->close();

            if (function_exists('registrarLog')) {
                @registrarLog(
                    $mysqli,
                    'editar_tipo_licencia',
                    'rrhh_tipos_licencia',
                    $id,
                    $userAuth['idusuario'] ?? null,
                    $anterior,
                    [
                        'nombre' => $nombre,
                        'requiere_documentacion' => $requiereDocumentacion,
                        'descuenta_vacaciones' => $descuentaVacaciones
                    ]
                );
            }

            responderTipoLicencia(200, [
                'status' => 'ok',
                'msg' => 'Tipo de licencia actualizado correctamente.'
            ]);

            break;

        case 'estado':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $id = (int)($_POST['idtipo_licencia'] ?? 0);
            $activo = (int)($_POST['activo'] ?? -1);

            if ($id <= 0) {
                throw new Exception('ID de tipo de licencia inválido.');
            }

            if (!in_array($activo, [0, 1], true)) {
                throw new Exception('Estado inválido.');
            }

            $stmt = $mysqli->prepare("
                SELECT *
                FROM rrhh_tipos_licencia
                WHERE idtipo_licencia = ?
                LIMIT 1
            ");

            $stmt->bind_param('i', $id);
            $stmt->execute();

            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$anterior) {
                throw new Exception('Tipo de licencia no encontrado.');
            }

            $stmt = $mysqli->prepare("
                UPDATE rrhh_tipos_licencia
                SET activo = ?
                WHERE idtipo_licencia = ?
            ");

            $stmt->bind_param('ii', $activo, $id);

            if (!$stmt->execute()) {
                throw new Exception('No se pudo cambiar el estado: ' . $stmt->error);
            }

            $stmt->close();

            if (function_exists('registrarLog')) {
                @registrarLog(
                    $mysqli,
                    $activo === 1 ? 'alta_tipo_licencia' : 'baja_tipo_licencia',
                    'rrhh_tipos_licencia',
                    $id,
                    $userAuth['idusuario'] ?? null,
                    $anterior,
                    ['activo' => $activo]
                );
            }

            responderTipoLicencia(200, [
                'status' => 'ok',
                'msg' => $activo === 1
                    ? 'Tipo de licencia activado correctamente.'
                    : 'Tipo de licencia desactivado correctamente.'
            ]);

            break;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Throwable $e) {

    error_log('[RRHH Tipos Licencia] ' . $e->getMessage());

    responderTipoLicencia(500, [
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}