<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

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

function responder($status, $msg, $data = null) {
    echo json_encode([
        'status' => $status,
        'msg' => $msg,
        'data' => $data
    ]);
    exit;
}

try {
    $userAuth = validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);
    $mysqli = conectarDBEmpresa($mysqli, (int)$empresa['idempresa'], 'DATOS');

    $accion = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($accion) {

        case 'listar':

            $sql = "SELECT
                        idregla,
                        antiguedad_desde,
                        antiguedad_hasta,
                        dias_asignados,
                        activo,
                        fecha_registro
                    FROM rrhh_vacaciones_reglas
                    ORDER BY antiguedad_desde ASC";

            $resultado = $mysqli->query($sql);

            if (!$resultado) {
                throw new Exception($mysqli->error);
            }

            $reglas = [];

            while ($fila = $resultado->fetch_assoc()) {
                $reglas[] = $fila;
            }

            responder('ok', 'Reglas obtenidas correctamente', $reglas);
            break;

        case 'crear':

            $desde = filter_var($_POST['antiguedad_desde'] ?? null, FILTER_VALIDATE_INT);
            $hasta = ($_POST['antiguedad_hasta'] ?? '') === ''
                ? null
                : filter_var($_POST['antiguedad_hasta'], FILTER_VALIDATE_INT);

            $dias = $_POST['dias_asignados'] ?? '';

            if ($desde === false || $desde === null || $desde < 0) {
                responder('error', 'La antigüedad desde no es válida');
            }

            if ($hasta !== null && ($hasta === false || $hasta < $desde)) {
                responder('error', 'La antigüedad hasta debe ser mayor o igual a desde');
            }

            if (!is_numeric($dias) || (float)$dias <= 0) {
                responder('error', 'La cantidad de días no es válida');
            }

            $dias = (float)$dias;

            $stmt = $mysqli->prepare(
                "INSERT INTO rrhh_vacaciones_reglas
                    (antiguedad_desde, antiguedad_hasta, dias_asignados)
                 VALUES (?, ?, ?)"
            );

            $stmt->bind_param('iid', $desde, $hasta, $dias);

            if (!$stmt->execute()) {
                if ($stmt->errno === 1062) {
                    responder('error', 'Ya existe una regla con esa antigüedad inicial');
                }

                throw new Exception($stmt->error);
            }

            $idNuevo = $mysqli->insert_id;
            $idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

            registrarLog(
                $mysqli,
                'crear_regla_vacaciones',
                'rrhh_vacaciones_reglas',
                $idNuevo,
                $idUsuarioLog,
                null,
                [
                    'antiguedad_desde' => $desde,
                    'antiguedad_hasta' => $hasta,
                    'dias_asignados' => $dias
                ]
            );

            responder('ok', 'Regla creada correctamente');
            break;

        case 'editar':

            $id = filter_var($_POST['idregla'] ?? null, FILTER_VALIDATE_INT);
            $desde = filter_var($_POST['antiguedad_desde'] ?? null, FILTER_VALIDATE_INT);

            $hasta = ($_POST['antiguedad_hasta'] ?? '') === ''
                ? null
                : filter_var($_POST['antiguedad_hasta'], FILTER_VALIDATE_INT);

            $dias = $_POST['dias_asignados'] ?? '';

            if (!$id || $desde === false || $desde === null || $desde < 0) {
                responder('error', 'Los datos de la regla no son válidos');
            }

            if ($hasta !== null && ($hasta === false || $hasta < $desde)) {
                responder('error', 'La antigüedad hasta debe ser mayor o igual a desde');
            }

            if (!is_numeric($dias) || (float)$dias <= 0) {
                responder('error', 'La cantidad de días no es válida');
            }

            $dias = (float)$dias;

            $stmt = $mysqli->prepare(
                "SELECT * FROM rrhh_vacaciones_reglas WHERE idregla = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $anterior = $stmt->get_result()->fetch_assoc();

            if (!$anterior) {
                responder('error', 'La regla no existe');
            }

            $stmt = $mysqli->prepare(
                "UPDATE rrhh_vacaciones_reglas
                 SET antiguedad_desde = ?,
                     antiguedad_hasta = ?,
                     dias_asignados = ?
                 WHERE idregla = ?"
            );

            $stmt->bind_param('iidi', $desde, $hasta, $dias, $id);

            if (!$stmt->execute()) {
                if ($stmt->errno === 1062) {
                    responder('error', 'Ya existe una regla con esa antigüedad inicial');
                }

                throw new Exception($stmt->error);
            }

            $idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

            registrarLog(
                $mysqli,
                'editar_regla_vacaciones',
                'rrhh_vacaciones_reglas',
                $id,
                $idUsuarioLog,
                $anterior,
                [
                    'antiguedad_desde' => $desde,
                    'antiguedad_hasta' => $hasta,
                    'dias_asignados' => $dias
                ]
            );

            responder('ok', 'Regla actualizada correctamente');
            break;

        case 'estado':

            $id = filter_var($_POST['idregla'] ?? null, FILTER_VALIDATE_INT);
            $activo = filter_var($_POST['activo'] ?? null, FILTER_VALIDATE_INT);

            if (!$id || !in_array($activo, [0, 1], true)) {
                responder('error', 'Datos de estado no válidos');
            }

            $stmt = $mysqli->prepare(
                "SELECT * FROM rrhh_vacaciones_reglas WHERE idregla = ?"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();

            $anterior = $stmt->get_result()->fetch_assoc();

            if (!$anterior) {
                responder('error', 'La regla no existe');
            }

            $stmt = $mysqli->prepare(
                "UPDATE rrhh_vacaciones_reglas SET activo = ? WHERE idregla = ?"
            );
            $stmt->bind_param('ii', $activo, $id);

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }

            $idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

            registrarLog(
                $mysqli,
                'cambiar_estado_regla_vacaciones',
                'rrhh_vacaciones_reglas',
                $id,
                $idUsuarioLog,
                $anterior,
                ['activo' => $activo]
            );

            responder('ok', 'Estado actualizado correctamente');
            break;

        default:
            responder('error', 'Acción no válida');
    }

} catch (Throwable $e) {
    error_log('API reglas vacaciones: ' . $e->getMessage());
    responder('error', 'Error interno al procesar la solicitud');
}