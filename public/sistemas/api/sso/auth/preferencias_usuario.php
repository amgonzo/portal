<?php
// =========================================================
// PREFERENCIAS DEL USUARIO - API CENTRAL SSO
// GET  ?action=obtener
// POST ?action=guardar  (JSON: registros_por_pagina, tema)
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Se permite continuar si el entorno ya está cargado.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json; charset=utf-8');

function responder(int $http, array $respuesta): void
{
    http_response_code($http);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Valida el token SSO y renueva la sesión según el middleware central.
    $userAuth = validarTokenAPI($mysqli);
    $idUsuario = (int)($userAuth['idusuario'] ?? 0);

    if ($idUsuario <= 0) {
        responder(401, [
            'status' => 'error',
            'msg' => 'usuario_no_autenticado'
        ]);
    }

    $action = $_GET['action'] ?? '';

    // -----------------------------------------------------
    // OBTENER PREFERENCIAS
    // -----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'obtener') {
        $sql = "
            SELECT registros_por_pagina, tema
            FROM usuarios_configuracion
            WHERE idusuario = ?
            LIMIT 1
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo preparar la consulta de preferencias.');
        }

        $stmt->bind_param('i', $idUsuario);

        if (!$stmt->execute()) {
            throw new Exception('No se pudieron consultar las preferencias.');
        }

        $resultado = $stmt->get_result();
        $preferencias = $resultado->fetch_assoc();
        $stmt->close();

        // Si todavía no existe configuración, devolver los valores predeterminados.
        if (!$preferencias) {
            $preferencias = [
                'registros_por_pagina' => 10,
                'tema' => 'claro'
            ];
        } else {
            $preferencias['registros_por_pagina'] =
                (int)$preferencias['registros_por_pagina'];
        }

        responder(200, [
            'status' => 'ok',
            'data' => $preferencias
        ]);
    }

    // -----------------------------------------------------
    // GUARDAR PREFERENCIAS
    // -----------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'guardar') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            responder(400, [
                'status' => 'error',
                'msg' => 'json_invalido'
            ]);
        }

        $registros = filter_var(
            $input['registros_por_pagina'] ?? null,
            FILTER_VALIDATE_INT
        );

        $tema = $input['tema'] ?? '';

        if (!in_array($registros, [10, 25, 50, 100], true)) {
            responder(400, [
                'status' => 'error',
                'msg' => 'registros_por_pagina_invalido'
            ]);
        }

        if (!in_array($tema, ['claro', 'oscuro'], true)) {
            responder(400, [
                'status' => 'error',
                'msg' => 'tema_invalido'
            ]);
        }

        $sql = "
            INSERT INTO usuarios_configuracion
                (idusuario, registros_por_pagina, tema)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                registros_por_pagina = VALUES(registros_por_pagina),
                tema = VALUES(tema),
                fecha_actualizacion = CURRENT_TIMESTAMP
        ";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo preparar el guardado de preferencias.');
        }

        $stmt->bind_param('iis', $idUsuario, $registros, $tema);

        if (!$stmt->execute()) {
            throw new Exception('No se pudieron guardar las preferencias.');
        }

        $stmt->close();

        responder(200, [
            'status' => 'ok',
            'msg' => 'Preferencias guardadas correctamente.',
            'data' => [
                'registros_por_pagina' => $registros,
                'tema' => $tema
            ]
        ]);
    }

    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        responder(405, [
            'status' => 'error',
            'msg' => 'metodo_no_permitido'
        ]);
    }

    responder(400, [
        'status' => 'error',
        'msg' => 'accion_no_valida'
    ]);

} catch (Throwable $e) {
    error_log('[preferencias_usuario] ' . $e->getMessage());

    responder(500, [
        'status' => 'error',
        'msg' => 'error_interno_del_servidor'
    ]);
}
