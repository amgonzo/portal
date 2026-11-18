<?php

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

function responderFamiliar($status, $msg, $data = null)
{
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

            $idEmpleado = (int)($_GET['idempleado'] ?? 0);

            if ($idEmpleado <= 0) {
                throw new Exception('Empleado no válido.');
            }

            $stmt = $mysqli->prepare("
                SELECT
                    idfamiliar,
                    idempleado,
                    nombre,
                    apellido,
                    parentesco,
                    documento,
                    fecha_nacimiento,
                    genero,
                    convive,
                    contacto_emergencia,
                    telefono,
                    observaciones
                FROM rrhh_grupo_familiar
                WHERE idempleado = ?
                  AND activo = 1
                ORDER BY apellido, nombre
            ");

            $stmt->bind_param('i', $idEmpleado);
            $stmt->execute();

            $res = $stmt->get_result();
            $datos = [];

            while ($fila = $res->fetch_assoc()) {
                $datos[] = $fila;
            }

            $stmt->close();

            responderFamiliar('ok', 'Familiares obtenidos.', $datos);

        case 'obtener':

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception('Método no permitido.');
            }

            $idFamiliar = (int)($_GET['idfamiliar'] ?? 0);

            if ($idFamiliar <= 0) {
                throw new Exception('Familiar no válido.');
            }

            $stmt = $mysqli->prepare("
                SELECT *
                FROM rrhh_grupo_familiar
                WHERE idfamiliar = ?
                  AND activo = 1
                LIMIT 1
            ");

            $stmt->bind_param('i', $idFamiliar);
            $stmt->execute();

            $familiar = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$familiar) {
                throw new Exception('No se encontró el familiar.');
            }

            responderFamiliar('ok', 'Familiar obtenido.', $familiar);

        case 'crear':
        case 'editar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $idFamiliar = (int)($_POST['idfamiliar'] ?? 0);
            $idEmpleado = (int)($_POST['idempleado'] ?? 0);

            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $parentesco = trim($_POST['parentesco'] ?? '');

            $documento = trim($_POST['documento'] ?? '');
            $documento = $documento !== '' ? $documento : null;

            $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
            $fechaNacimiento = $fechaNacimiento !== ''
                ? $fechaNacimiento
                : null;

            $genero = trim($_POST['genero'] ?? '');
            $genero = $genero !== '' ? $genero : null;

            $telefono = trim($_POST['telefono'] ?? '');
            $telefono = $telefono !== '' ? $telefono : null;

            $observaciones = trim($_POST['observaciones'] ?? '');
            $observaciones = $observaciones !== ''
                ? $observaciones
                : null;

            $convive = isset($_POST['convive']) ? 1 : 0;
            $emergencia = isset($_POST['contacto_emergencia']) ? 1 : 0;

            if ($idEmpleado <= 0 || $nombre === '' ||
                $apellido === '' || $parentesco === '') {
                throw new Exception(
                    'Complete empleado, nombre, apellido y parentesco.'
                );
            }

            if ($fechaNacimiento !== null) {
                $fecha = DateTime::createFromFormat(
                    '!Y-m-d',
                    $fechaNacimiento
                );

                if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento) {
                    throw new Exception('La fecha de nacimiento no es válida.');
                }
            }

            // Verificar que el empleado exista.
            $stmt = $mysqli->prepare("
                SELECT idempleado
                FROM empleados
                WHERE idempleado = ?
                LIMIT 1
            ");

            $stmt->bind_param('i', $idEmpleado);
            $stmt->execute();

            $existeEmpleado = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$existeEmpleado) {
                throw new Exception('El empleado seleccionado no existe.');
            }

            if ($action === 'crear') {

                $stmt = $mysqli->prepare("
                    INSERT INTO rrhh_grupo_familiar (
                        idempleado,
                        nombre,
                        apellido,
                        parentesco,
                        documento,
                        fecha_nacimiento,
                        genero,
                        convive,
                        contacto_emergencia,
                        telefono,
                        observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'issssssiiss',
                    $idEmpleado,
                    $nombre,
                    $apellido,
                    $parentesco,
                    $documento,
                    $fechaNacimiento,
                    $genero,
                    $convive,
                    $emergencia,
                    $telefono,
                    $observaciones
                );

                if (!$stmt->execute()) {
                    throw new Exception('No se pudo registrar el familiar.');
                }

                $stmt->close();

                responderFamiliar('ok', 'Familiar registrado correctamente.');
            }

            if ($idFamiliar <= 0) {
                throw new Exception('Familiar no válido.');
            }

            $stmt = $mysqli->prepare("
                UPDATE rrhh_grupo_familiar
                SET
                    nombre = ?,
                    apellido = ?,
                    parentesco = ?,
                    documento = ?,
                    fecha_nacimiento = ?,
                    genero = ?,
                    convive = ?,
                    contacto_emergencia = ?,
                    telefono = ?,
                    observaciones = ?
                WHERE idfamiliar = ?
                  AND idempleado = ?
                  AND activo = 1
            ");

            $stmt->bind_param(
                'ssssssiissii',
                $nombre,
                $apellido,
                $parentesco,
                $documento,
                $fechaNacimiento,
                $genero,
                $convive,
                $emergencia,
                $telefono,
                $observaciones,
                $idFamiliar,
                $idEmpleado
            );

            if (!$stmt->execute()) {
                throw new Exception('No se pudo actualizar el familiar.');
            }

            $stmt->close();

            responderFamiliar('ok', 'Familiar actualizado correctamente.');

        case 'eliminar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Método no permitido.');
            }

            $idFamiliar = (int)($_POST['idfamiliar'] ?? 0);

            if ($idFamiliar <= 0) {
                throw new Exception('Familiar no válido.');
            }

            $stmt = $mysqli->prepare("
                UPDATE rrhh_grupo_familiar
                SET activo = 0
                WHERE idfamiliar = ?
                  AND activo = 1
            ");

            $stmt->bind_param('i', $idFamiliar);

            if (!$stmt->execute() || $stmt->affected_rows === 0) {
                throw new Exception('No se pudo dar de baja el familiar.');
            }

            $stmt->close();

            responderFamiliar('ok', 'Familiar dado de baja correctamente.');

        default:
            throw new Exception('Acción no válida.');
    }

} catch (Throwable $e) {

    error_log('API RRHH grupo familiar: ' . $e->getMessage());

    http_response_code(400);

    responderFamiliar(
        'error',
        $e instanceof Exception
            ? $e->getMessage()
            : 'Error al procesar la operación.'
    );
}