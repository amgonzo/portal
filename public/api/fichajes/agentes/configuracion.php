<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 1. RUTAS Y COMPOSER
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

// =========================================================
// 2. CARGAR .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );

    $dotenv->load();

} catch (Exception $e) {
}

// =========================================================
// 3. CONEXIÓN CENTRAL SSO
// =========================================================

require_once $rutas['conexion'];

// =========================================================
// 4. SOLO POST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Método no permitido.'
    ]);

    exit;
}

// =========================================================
// 5. LEER JSON
// =========================================================

$entrada = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($entrada)) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Datos inválidos.'
    ]);

    exit;
}

// =========================================================
// 6. DATOS DEL AGENT
// =========================================================

$agentId = trim(
    (string)($entrada['agent_id'] ?? '')
);

$token = trim(
    (string)($entrada['token'] ?? '')
);

if ($agentId === '' || $token === '') {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);

    exit;
}

// =========================================================
// 7. AUTENTICAR AGENT
// =========================================================

try {

    $stmt = $mysqli->prepare("
        SELECT
            idagente,
            agent_id,
            nombre,
            token_hash,
            idempresa,
            activo
        FROM agentes
        WHERE agent_id = ?
        LIMIT 1
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar autenticación: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        's',
        $agentId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new Exception(
            'Error al autenticar Agent: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $agente = $resultado->fetch_assoc();

    $stmt->close();

    // =====================================================
    // AGENT NO EXISTE
    // =====================================================

    if (!$agente) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de Agent inválidas.'
        ]);

        exit;
    }

    // =====================================================
    // AGENT INACTIVO
    // =====================================================

    if ((int)$agente['activo'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'msg' => 'El Agent está desactivado.'
        ]);

        exit;
    }

    // =====================================================
    // VERIFICAR TOKEN
    // =====================================================

    if (!password_verify(
        $token,
        $agente['token_hash']
    )) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de Agent inválidas.'
        ]);

        exit;
    }

    $idAgente = (int)$agente['idagente'];
    $idEmpresa = (int)$agente['idempresa'];

    // =====================================================
    // ACTUALIZAR ACTIVIDAD
    // =====================================================

    $stmt = $mysqli->prepare("
        UPDATE agentes
        SET
            ultima_actividad = NOW(),
            ultimo_error = NULL
        WHERE idagente = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $idAgente
        );

        $stmt->execute();

        $stmt->close();
    }

    // =====================================================
    // OBTENER CONFIGURACIÓN CENTRAL
    // =====================================================

    $stmt = $mysqli->prepare("
        SELECT
            clave,
            valor,
            descripcion,
            tipo,
            fecha_actualizacion
        FROM configuracion_sistema
        ORDER BY clave ASC
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar consulta de configuración: ' .
            $mysqli->error
        );
    }

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new Exception(
            'Error al consultar configuración: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $configuracion = [];

    while ($fila = $resultado->fetch_assoc()) {

        $valor = $fila['valor'];

        // =================================================
        // CONVERTIR SEGÚN TIPO
        // =================================================

        switch (strtolower($fila['tipo'])) {

            case 'entero':

                $valor = (int)$valor;

                break;

            case 'decimal':

                $valor = (float)$valor;

                break;

            case 'booleano':

                $valor = in_array(
                    strtolower((string)$valor),
                    ['1', 'true', 'si', 'sí', 'yes'],
                    true
                );

                break;

            default:

                $valor = (string)$valor;

                break;
        }

        $configuracion[$fila['clave']] = $valor;
    }

    $stmt->close();

    // =====================================================
    // RESPUESTA
    // =====================================================

    echo json_encode([
        'status' => 'ok',
        'idagente' => $idAgente,
        'idempresa' => $idEmpresa,
        'configuracion' => $configuracion
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}