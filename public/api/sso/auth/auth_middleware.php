<?php
// config/auth_middleware.php

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

if (!isset($mysqli)) {
    require_once $rutas['conexion'];
}

function validarTokenAPI($mysqli) {
    $authHeader = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    $token = trim(str_replace('Bearer ', '', $authHeader));

    if (empty($token)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "msg" => "No autorizado - Token ausente"]);
        exit;
    }

    $stmtAuth = $mysqli->prepare("
        SELECT u.idusuario, u.username, u.nombreapellido, u.token_expira, ura.idtipousuario 
        FROM usuarios u 
        LEFT JOIN usuarios_roles_apps ura ON u.idusuario = ura.idusuario 
        WHERE u.token = ? AND u.baja = 0 
        LIMIT 1
    ");
    $stmtAuth->bind_param("s", $token);
    $stmtAuth->execute();
    $resAuth = $stmtAuth->get_result();

    if ($resAuth->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["status" => "error", "msg" => "Token inválido o expirado"]);
        exit;
    }

    $userAuth = $resAuth->fetch_assoc();

    if (!empty($userAuth['token_expira']) && strtotime($userAuth['token_expira']) < time()) {
        $stmtClear = $mysqli->prepare("UPDATE usuarios SET token = NULL, token_expira = NULL WHERE idusuario = ?");
        $stmtClear->bind_param("i", $userAuth['idusuario']);
        $stmtClear->execute();

        http_response_code(401);
        echo json_encode(["status" => "error", "msg" => "Sesión expirada por inactividad"]);
        exit;
    }

    $minutosInactividad = 30;
    $stmtRenew = $mysqli->prepare("UPDATE usuarios SET token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE idusuario = ?");
    $stmtRenew->bind_param("ii", $minutosInactividad, $userAuth['idusuario']);
    $stmtRenew->execute();

    return $userAuth;
}

function validarPermisoEndpoint($mysqli, $userAuth) {

    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $metodoActual = strtoupper($_SERVER['REQUEST_METHOD']);

    $idtipousuario = $userAuth['idtipousuario'] ?? null;

    if (!$idtipousuario) {
        http_response_code(403);
        echo json_encode(["status" => "error", "msg" => "Acceso denegado - Rol no definido"]);
        exit;
    }
/*
    $sql = "SELECT 1 
            FROM permisos_rol pr 
            INNER JOIN permisos p ON pr.idpermiso = p.idpermiso 
            WHERE pr.idtipousuario = ? 
            AND (p.metodo = 'ALL' || p.metodo = ?)
            AND (
                p.endpoint = ? 
                OR (p.endpoint LIKE '%*%' AND ? LIKE CONCAT('%', REPLACE(p.endpoint, '*', ''), '%'))
            )
            LIMIT 1";
*/
    $sql = "SELECT 1 
            FROM permisos_rol pr 
            INNER JOIN permisos p ON pr.idpermiso = p.idpermiso 
            WHERE pr.idtipousuario = ? 
            AND (p.metodo = 'ALL' || p.metodo = ?)
            AND (
                p.endpoint = ? 
                OR (p.endpoint LIKE '%*%' AND ? LIKE CONCAT('%', REPLACE(p.endpoint, '*', ''), '%'))
            )
            LIMIT 1";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("isss", $idtipousuario, $metodoActual, $requestUri, $requestUri);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        http_response_code(403);
        echo json_encode([
            "status" => "error", 
            "msg" => "Acceso denegado - No tenés permisos para ejecutar {$metodoActual} en este endpoint ({$requestUri})"
        ]);
        exit;
    }
}