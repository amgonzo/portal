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

function responderContacto($status, $msg, $data = null) {
    $respuesta = ['status' => $status, 'msg' => $msg];

    if ($data !== null) {
        $respuesta['data'] = $data;
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

$transaccion = false;

try {
    $userAuth = validarTokenAPI($mysqli ?? null);
    validarPermisoEndpoint($mysqli, $userAuth);

    $idUsuarioLog = (int)($userAuth['idusuario'] ?? 0);

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

    // =====================================================
    // OBTENER DATOS DE CONTACTO
    // =====================================================

    if ($action === 'obtener') {

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            responderContacto('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_GET['idempleado'] ?? 0);

        if ($idEmpleado <= 0) {
            responderContacto('error', 'Empleado inválido.');
        }

        $stmt = $mysqli->prepare("
            SELECT idempleado
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$existe) {
            responderContacto('error', 'Empleado no encontrado.');
        }

        $stmt = $mysqli->prepare("
            SELECT
                idempleado,
                email,
                telefono,
                celular,
                domicilio,
                localidad,
                provincia,
                codigo_postal,
                entre_calles,
                referencia_domicilio
            FROM rrhh_datos_contacto
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $datos = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$datos) {
            $datos = [
                'idempleado' => $idEmpleado,
                'email' => '',
                'telefono' => '',
                'celular' => '',
                'domicilio' => '',
                'localidad' => '',
                'provincia' => '',
                'codigo_postal' => '',
                'entre_calles' => '',
                'referencia_domicilio' => ''
            ];
        }

        responderContacto('ok', 'Datos de contacto obtenidos.', $datos);
    }

    // =====================================================
    // GUARDAR DATOS DE CONTACTO
    // =====================================================

    if ($action === 'guardar') {

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            responderContacto('error', 'Método no permitido.');
        }

        $idEmpleado = (int)($_POST['idempleado'] ?? 0);

        if ($idEmpleado <= 0) {
            responderContacto('error', 'Empleado inválido.');
        }

        $campos = [
            'email',
            'telefono',
            'celular',
            'domicilio',
            'localidad',
            'provincia',
            'codigo_postal',
            'entre_calles',
            'referencia_domicilio'
        ];

        $datos = [];

        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $datos[$campo] = $valor !== '' ? $valor : null;
        }

        if (
            $datos['email'] !== null &&
            !filter_var($datos['email'], FILTER_VALIDATE_EMAIL)
        ) {
            responderContacto('error', 'El email ingresado no es válido.');
        }

        $stmt = $mysqli->prepare("
            SELECT idempleado
            FROM empleados
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $existe = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$existe) {
            responderContacto('error', 'Empleado no encontrado.');
        }

        $stmt = $mysqli->prepare("
            SELECT *
            FROM rrhh_datos_contacto
            WHERE idempleado = ?
            LIMIT 1
        ");

        $stmt->bind_param('i', $idEmpleado);
        $stmt->execute();

        $anterior = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $mysqli->begin_transaction();
        $transaccion = true;

        $stmt = $mysqli->prepare("
            INSERT INTO rrhh_datos_contacto (
                idempleado,
                email,
                telefono,
                celular,
                domicilio,
                localidad,
                provincia,
                codigo_postal,
                entre_calles,
                referencia_domicilio
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)

            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                telefono = VALUES(telefono),
                celular = VALUES(celular),
                domicilio = VALUES(domicilio),
                localidad = VALUES(localidad),
                provincia = VALUES(provincia),
                codigo_postal = VALUES(codigo_postal),
                entre_calles = VALUES(entre_calles),
                referencia_domicilio = VALUES(referencia_domicilio)
        ");

        $stmt->bind_param(
            'isssssssss',
            $idEmpleado,
            $datos['email'],
            $datos['telefono'],
            $datos['celular'],
            $datos['domicilio'],
            $datos['localidad'],
            $datos['provincia'],
            $datos['codigo_postal'],
            $datos['entre_calles'],
            $datos['referencia_domicilio']
        );

        $stmt->execute();
        $stmt->close();

        $mysqli->commit();
        $transaccion = false;

        @registrarLog(
            $mysqli,
            'guardar_datos_contacto',
            'rrhh_datos_contacto',
            $idEmpleado,
            $idUsuarioLog,
            $anterior,
            $datos
        );

        responderContacto('ok', 'Datos de contacto guardados correctamente.');
    }

    responderContacto('error', 'Acción no válida.');

} catch (Throwable $e) {

    if ($transaccion && isset($mysqli) && $mysqli instanceof mysqli) {
        @$mysqli->rollback();
    }

    error_log('API RRHH datos contacto: ' . $e->getMessage());

    responderContacto(
        'error',
        $e instanceof Exception
            ? $e->getMessage()
            : 'Error al procesar la operación.'
    );
}