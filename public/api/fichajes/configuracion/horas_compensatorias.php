<?php

// api/fichajes/configuracion/horas_compensatorias.php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

try {
    // Validar método HTTP
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';

    if (!in_array($metodo, ['GET', 'POST'], true)) {
        http_response_code(405);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Método no permitido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Validar autenticación y permisos
    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );

    // Obtener empresa activa
    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    if (
        !isset($empresa['idempresa']) ||
        (int)$empresa['idempresa'] <= 0
    ) {
        throw new Exception(
            'No se pudo determinar la empresa activa.'
        );
    }

    // Conectar a la base de datos de la empresa
    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // Consultar configuración existente
    if ($metodo === 'GET') {
        $stmt = $mysqli->prepare("
            SELECT
                idconfig,
                minutos_minimos_extra,
                tipo_extra_fuera_horario,
                tratamiento_horas_extra,
                modo_generacion_compensacion,
                vigencia_saldo_compensacion,
                meses_vigencia_compensacion,
                modo_vencimiento_compensacion
            FROM configuracion_empresa
            ORDER BY idconfig ASC
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception(
                'Error al preparar la consulta de configuración.'
            );
        }

        if (!$stmt->execute()) {
            throw new Exception(
                'Error al consultar la configuración.'
            );
        }

        $resultado = $stmt->get_result();
        $configuracion = $resultado->fetch_assoc();

        $stmt->close();

        if (!$configuracion) {
            throw new Exception(
                'No existe una configuración para la empresa activa.'
            );
        }

        echo json_encode([
            'status' => 'ok',
            'data' => $configuracion
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Leer datos enviados en formato JSON
    $datos = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($datos)) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Los datos enviados no son válidos.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Validar campos obligatorios
    $camposObligatorios = [
        'minutos_minimos_extra',
        'tipo_extra_fuera_horario',
        'tratamiento_horas_extra',
        'modo_generacion_compensacion',
        'vigencia_saldo_compensacion',
        'meses_vigencia_compensacion',
        'modo_vencimiento_compensacion'
    ];

    foreach ($camposObligatorios as $campo) {
        if (!array_key_exists($campo, $datos)) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'msg' => 'Falta el campo obligatorio: ' . $campo
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    // Validar valores numéricos
    $minutosMinimos = filter_var(
        $datos['minutos_minimos_extra'],
        FILTER_VALIDATE_INT
    );

    $mesesVigencia = filter_var(
        $datos['meses_vigencia_compensacion'],
        FILTER_VALIDATE_INT
    );

    if (
        $minutosMinimos === false ||
        $minutosMinimos < 0
    ) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Los minutos mínimos deben ser un entero mayor o igual a cero.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    if (
        $mesesVigencia === false ||
        $mesesVigencia < 1
    ) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Los meses de vigencia deben ser un entero mayor o igual a uno.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Validar opciones permitidas
    $opciones = [
        'tipo_extra_fuera_horario' => [
            'ninguna',
            'despues',
            'antes',
            'antes_despues'
        ],
        'tratamiento_horas_extra' => [
            'ninguna',
            'pago',
            'compensacion'
        ],
        'modo_generacion_compensacion' => [
            'automatico',
            'autorizado'
        ],
        'vigencia_saldo_compensacion' => [
            'mismo_mes',
            'meses'
        ],
        'modo_vencimiento_compensacion' => [
            'automatico',
            'revision'
        ]
    ];

    foreach ($opciones as $campo => $valoresPermitidos) {
        if (
            !is_string($datos[$campo]) ||
            !in_array(
                $datos[$campo],
                $valoresPermitidos,
                true
            )
        ) {
            http_response_code(400);

            echo json_encode([
                'status' => 'error',
                'msg' => 'El valor enviado para ' . $campo . ' no es válido.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }
    }

    // Obtener el registro de configuración existente
    $stmt = $mysqli->prepare("
        SELECT idconfig
        FROM configuracion_empresa
        ORDER BY idconfig ASC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Error al preparar la consulta de configuración.'
        );
    }

    if (!$stmt->execute()) {
        throw new Exception(
            'Error al consultar la configuración existente.'
        );
    }

    $resultado = $stmt->get_result();
    $registro = $resultado->fetch_assoc();

    $stmt->close();

    if (!$registro) {
        throw new Exception(
            'No existe una configuración para la empresa activa.'
        );
    }

    $idconfig = (int)$registro['idconfig'];

    // Actualizar exclusivamente la configuración existente
    $stmt = $mysqli->prepare("
        UPDATE configuracion_empresa
        SET
            minutos_minimos_extra = ?,
            tipo_extra_fuera_horario = ?,
            tratamiento_horas_extra = ?,
            modo_generacion_compensacion = ?,
            vigencia_saldo_compensacion = ?,
            meses_vigencia_compensacion = ?,
            modo_vencimiento_compensacion = ?
        WHERE idconfig = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Error al preparar la actualización de configuración.'
        );
    }

    $stmt->bind_param(
        'issssisi',
        $minutosMinimos,
        $datos['tipo_extra_fuera_horario'],
        $datos['tratamiento_horas_extra'],
        $datos['modo_generacion_compensacion'],
        $datos['vigencia_saldo_compensacion'],
        $mesesVigencia,
        $datos['modo_vencimiento_compensacion'],
        $idconfig
    );

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'Error al guardar la configuración.'
        );
    }

    $stmt->close();

    // Devolver la configuración actualizada
    $stmt = $mysqli->prepare("
        SELECT
            idconfig,
            minutos_minimos_extra,
            tipo_extra_fuera_horario,
            tratamiento_horas_extra,
            modo_generacion_compensacion,
            vigencia_saldo_compensacion,
            meses_vigencia_compensacion,
            modo_vencimiento_compensacion
        FROM configuracion_empresa
        WHERE idconfig = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'La configuración se guardó, pero no se pudo consultar el resultado.'
        );
    }

    $stmt->bind_param('i', $idconfig);

    if (!$stmt->execute()) {
        $stmt->close();

        throw new Exception(
            'La configuración se guardó, pero no se pudo consultar el resultado.'
        );
    }

    $resultado = $stmt->get_result();
    $configuracion = $resultado->fetch_assoc();

    $stmt->close();

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Configuración guardada correctamente.',
        'data' => $configuracion
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}