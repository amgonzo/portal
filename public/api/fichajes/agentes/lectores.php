<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Método no permitido.'
    ]);

    exit;
}

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

if ($agentId === '' || $token === '') {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);

    exit;
}

try {

    // =====================================================
    // AUTENTICAR AGENT
    // =====================================================

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

    if (!$agente) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de Agent inválidas.'
        ]);

        exit;
    }

    if ((int)$agente['activo'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'msg' => 'El Agent está desactivado.'
        ]);

        exit;
    }

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
    // CONECTAR DB DE LA EMPRESA
    // =====================================================

    $dbEmpresa = conectarDBEmpresa(
        $mysqli,
        $idEmpresa,
        'DATOS'
    );

    
    try {

        // =================================================
        // OBTENER LECTORES ACTIVOS DEL AGENT
        // =================================================

        $stmt = $dbEmpresa->prepare("
            SELECT
                idlector,
                nombre,
                marca,
                modelo,
                ip,
                puerto,
                ubicacion,
                tipo_uso,
                predeterminado,
                activo,
                idagente
            FROM lectores
            WHERE idagente = ?
              AND activo = 1
            ORDER BY
                predeterminado DESC,
                idlector ASC
        ");

        if (!$stmt) {

            throw new Exception(
                'Error al preparar consulta de lectores: ' .
                $dbEmpresa->error
            );
        }

        $stmt->bind_param(
            'i',
            $idAgente
        );

        if (!$stmt->execute()) {

            $error = $stmt->error;

            $stmt->close();

            throw new Exception(
                'Error al consultar lectores: ' .
                $error
            );
        }

        $resultado = $stmt->get_result();

        $lectores = [];

        while ($lector = $resultado->fetch_assoc()) {

            $lectores[] = [
                'idlector' => (int)$lector['idlector'],
                'nombre' => $lector['nombre'],
                'marca' => $lector['marca'],
                'modelo' => $lector['modelo'],
                'ip' => $lector['ip'],
                'puerto' => (int)$lector['puerto'],
                'ubicacion' => $lector['ubicacion'],
                'tipo_uso' => $lector['tipo_uso'],
                'predeterminado' => (int)$lector['predeterminado'],
                'activo' => (int)$lector['activo'],
                'idagente' => (int)$lector['idagente']
            ];
        }

        $stmt->close();

        $dbEmpresa->close();

        echo json_encode([
            'status' => 'ok',
            'idagente' => $idAgente,
            'idempresa' => $idEmpresa,
            'cantidad' => count($lectores),
            'lectores' => $lectores
        ], JSON_UNESCAPED_UNICODE);

        exit;

    } catch (Exception $e) {

        if (
            isset($dbEmpresa) &&
            $dbEmpresa instanceof mysqli
        ) {
            @$dbEmpresa->close();
        }

        throw $e;
    }

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ]);
}