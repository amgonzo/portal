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
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
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

$agentId = trim(
    (string)($entrada['agent_id'] ?? '')
);

$token = trim(
    (string)($entrada['token'] ?? '')
);

$accion = strtolower(
    trim(
        (string)($entrada['accion'] ?? '')
    )
);

// =========================================================
// 6. VALIDAR DATOS
// =========================================================

if ($agentId === '' || $token === '') {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);

    exit;
}

// =========================================================
// 7. BUSCAR AGENTE
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
            'Error al preparar consulta: ' . $mysqli->error
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
            'Error al consultar agente: ' . $error
        );
    }

    $resultado = $stmt->get_result();

    $agente = $resultado->fetch_assoc();

    $stmt->close();

    // =====================================================
    // 8. AGENTE NO EXISTE
    // =====================================================

    if (!$agente) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    // =====================================================
    // 9. AGENTE INACTIVO
    // =====================================================

    if ((int)$agente['activo'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'msg' => 'El agente está desactivado.'
        ]);

        exit;
    }

    // =====================================================
    // 10. VERIFICAR TOKEN
    // =====================================================

    if (!password_verify(
        $token,
        $agente['token_hash']
    )) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    // =====================================================
    // 11. ACTUALIZAR ACTIVIDAD
    //
    // LOGIN NORMAL:
    // - ultimo_acceso
    // - ultima_actividad
    //
    // HEARTBEAT:
    // - solamente ultima_actividad
    // =====================================================

    if ($accion === 'heartbeat') {

        $stmt = $mysqli->prepare("
            UPDATE agentes
            SET
                ultima_actividad = NOW(),
                ultimo_error = NULL
            WHERE idagente = ?
        ");

    } else {

        $stmt = $mysqli->prepare("
            UPDATE agentes
            SET
                ultimo_acceso = NOW(),
                ultima_actividad = NOW(),
                ultimo_error = NULL
            WHERE idagente = ?
        ");
    }

    if ($stmt) {

        $idAgente = (int)$agente['idagente'];

        $stmt->bind_param(
            'i',
            $idAgente
        );

        $stmt->execute();

        $stmt->close();
    }

    // =====================================================
    // 12. RESPUESTA HEARTBEAT
    // =====================================================

    if ($accion === 'heartbeat') {

        echo json_encode([
            'status' => 'ok',
            'accion' => 'heartbeat',
            'idagente' => (int)$agente['idagente'],
            'fecha' => date('Y-m-d H:i:s')
        ]);

        exit;
    }

    // =====================================================
    // 13. RESPUESTA AUTENTICACIÓN NORMAL
    // =====================================================

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Agente autenticado correctamente.',
        'agente' => [
            'idagente' => (int)$agente['idagente'],
            'agent_id' => $agente['agent_id'],
            'nombre' => $agente['nombre'],
            'idempresa' => (int)$agente['idempresa']
        ]
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Error interno al autenticar el agente.'
    ]);
}