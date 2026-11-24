<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

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

header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200)
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $userAuth = validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {

        case 'opciones':

            $licencias = [];
            $asistencias = [];

            $res = $mysqli->query("
                SELECT idtipo_licencia, nombre
                FROM rrhh_tipos_licencia
                WHERE activo = 1
                ORDER BY nombre
            ");

            if (!$res) {
                throw new Exception('Error consultando tipos de licencia: ' . $mysqli->error);
            }

            while ($row = $res->fetch_assoc()) {
                $licencias[] = $row;
            }

            $res = $mysqli->query("
                SELECT idtipo_asistencia, nombre, abreviatura
                FROM tipos_asistencia
                WHERE activo = 1
                ORDER BY nombre
            ");

            if (!$res) {
                throw new Exception('Error consultando tipos de asistencia: ' . $mysqli->error);
            }

            while ($row = $res->fetch_assoc()) {
                $asistencias[] = $row;
            }

            responder([
                'status' => 'ok',
                'licencias' => $licencias,
                'asistencias' => $asistencias
            ]);

            break;

        case 'listar':

            $sql = "
                SELECT
                    r.id,
                    r.idtipo_licencia,
                    r.idtipo_asistencia,
                    r.activo,
                    l.nombre AS licencia,
                    a.nombre AS asistencia,
                    a.abreviatura
                FROM rrhh_integracion_tipos_asistencia r
                INNER JOIN rrhh_tipos_licencia l
                    ON l.idtipo_licencia = r.idtipo_licencia
                INNER JOIN tipos_asistencia a
                    ON a.idtipo_asistencia = r.idtipo_asistencia
                ORDER BY l.nombre ASC
            ";

            $res = $mysqli->query($sql);

            if (!$res) {
                throw new Exception('Error consultando relaciones: ' . $mysqli->error);
            }

            $datos = [];

            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int)$row['id'];
                $row['idtipo_licencia'] = (int)$row['idtipo_licencia'];
                $row['idtipo_asistencia'] = (int)$row['idtipo_asistencia'];
                $row['activo'] = (int)$row['activo'];

                $datos[] = $row;
            }

            responder([
                'status' => 'ok',
                'data' => $datos
            ]);

            break;

        case 'guardar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $idLicencia = (int)($_POST['idtipo_licencia'] ?? 0);
            $idAsistencia = (int)($_POST['idtipo_asistencia'] ?? 0);

            if ($idLicencia <= 0 || $idAsistencia <= 0) {
                throw new Exception('Debe seleccionar ambos tipos.');
            }

            // Verificar que ambos tipos existan y estén activos.
            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS cantidad
                FROM rrhh_tipos_licencia
                WHERE idtipo_licencia = ? AND activo = 1
            ");
            $stmt->bind_param('i', $idLicencia);
            $stmt->execute();
            $licenciaValida = (int)$stmt->get_result()->fetch_assoc()['cantidad'];
            $stmt->close();

            $stmt = $mysqli->prepare("
                SELECT COUNT(*) AS cantidad
                FROM tipos_asistencia
                WHERE idtipo_asistencia = ? AND activo = 1
            ");
            $stmt->bind_param('i', $idAsistencia);
            $stmt->execute();
            $asistenciaValida = (int)$stmt->get_result()->fetch_assoc()['cantidad'];
            $stmt->close();

            if (!$licenciaValida || !$asistenciaValida) {
                throw new Exception('Uno de los tipos seleccionados no existe o está inactivo.');
            }

            // Recuperar datos anteriores cuando se edita.
            $anterior = null;

            if ($id > 0) {
                $stmt = $mysqli->prepare("
                    SELECT *
                    FROM rrhh_integracion_tipos_asistencia
                    WHERE id = ?
                ");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $anterior = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$anterior) {
                    throw new Exception('Relación no encontrada.');
                }
            }

            // Evitar que una licencia o asistencia se relacione dos veces.
            $stmt = $mysqli->prepare("
                SELECT id
                FROM rrhh_integracion_tipos_asistencia
                WHERE activo = 1
                  AND (idtipo_licencia = ? OR idtipo_asistencia = ?)
                  AND id <> ?
                LIMIT 1
            ");

            $stmt->bind_param('iii', $idLicencia, $idAsistencia, $id);
            $stmt->execute();
            $duplicado = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicado) {
                throw new Exception('Uno de los tipos seleccionados ya está relacionado.');
            }

            if ($id > 0) {

                $stmt = $mysqli->prepare("
                    UPDATE rrhh_integracion_tipos_asistencia
                    SET
                        idtipo_licencia = ?,
                        idtipo_asistencia = ?,
                        activo = 1
                    WHERE id = ?
                ");

                $stmt->bind_param('iii', $idLicencia, $idAsistencia, $id);

                if (!$stmt->execute()) {
                    throw new Exception('No se pudo actualizar la relación: ' . $stmt->error);
                }

                $stmt->close();

                registrarLog(
                    $mysqli,
                    'editar_integracion_tipo_asistencia',
                    'rrhh_integracion_tipos_asistencia',
                    $id,
                    $anterior,
                    $_POST
                );

            } else {

                $stmt = $mysqli->prepare("
                    INSERT INTO rrhh_integracion_tipos_asistencia
                    (
                        idtipo_licencia,
                        idtipo_asistencia,
                        activo
                    )
                    VALUES (?, ?, 1)
                ");

                $stmt->bind_param('ii', $idLicencia, $idAsistencia);

                if (!$stmt->execute()) {
                    if ($stmt->errno === 1062) {
                        throw new Exception('Uno de los tipos seleccionados ya está relacionado.');
                    }

                    throw new Exception('No se pudo crear la relación: ' . $stmt->error);
                }

                $idNuevo = $stmt->insert_id;
                $stmt->close();

                registrarLog(
                    $mysqli,
                    'alta_integracion_tipo_asistencia',
                    'rrhh_integracion_tipos_asistencia',
                    $idNuevo,
                    null,
                    $_POST
                );
            }

            responder([
                'status' => 'ok',
                'msg' => 'Relación guardada correctamente.'
            ]);

            break;

        case 'estado':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $id = (int)($_POST['id'] ?? 0);
            $activo = (int)($_POST['activo'] ?? -1);

            if ($id <= 0 || !in_array($activo, [0, 1], true)) {
                throw new Exception('Datos inválidos.');
            }

            $stmt = $mysqli->prepare("
                SELECT *
                FROM rrhh_integracion_tipos_asistencia
                WHERE id = ?
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $anterior = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$anterior) {
                throw new Exception('Relación no encontrada.');
            }

            $stmt = $mysqli->prepare("
                UPDATE rrhh_integracion_tipos_asistencia
                SET activo = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ii', $activo, $id);

            if (!$stmt->execute()) {
                throw new Exception('No se pudo cambiar el estado.');
            }

            $stmt->close();

            registrarLog(
                $mysqli,
                $activo ? 'activar_integracion_tipo_asistencia' : 'desactivar_integracion_tipo_asistencia',
                'rrhh_integracion_tipos_asistencia',
                $id,
                $anterior,
                ['activo' => $activo]
            );

            responder([
                'status' => 'ok',
                'msg' => $activo
                    ? 'Relación activada correctamente.'
                    : 'Relación desactivada correctamente.'
            ]);

            break;

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Throwable $e) {
    error_log('[Integración tipos asistencia] ' . $e->getMessage());

    responder([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], 500);
}