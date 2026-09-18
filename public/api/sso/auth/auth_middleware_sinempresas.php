<?php
// /api/sso/auth/auth_middleware.php

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

if (!isset($mysqli)) {
    require_once $rutas['conexion'];
}


/**
 * Obtiene el Authorization: Bearer ...
 */
function obtenerTokenBearer()
{
    $authHeader = '';

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $headers = getallheaders();
        $authHeader = $headers['Authorization']
            ?? $headers['authorization']
            ?? '';
    }

    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }

    return trim($authHeader);
}


/**
 * Valida el token y devuelve los datos básicos del usuario.
 */
function validarTokenAPI($mysqli)
{
    $token = obtenerTokenBearer();

    if (empty($token)) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "msg" => "No autorizado - Token ausente"
        ]);
        exit;
    }

    $stmt = $mysqli->prepare("
        SELECT
            u.idusuario,
            u.username,
            u.nombreapellido,
            u.email,
            u.token_expira
        FROM usuarios u
        WHERE u.token = ?
          AND u.baja = 0
        LIMIT 1
    ");

    $stmt->bind_param("s", $token);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "msg" => "Token inválido"
        ]);
        exit;
    }

    $userAuth = $resultado->fetch_assoc();

    /*
     * Verificar expiración
     */
    if (
        !empty($userAuth['token_expira']) &&
        strtotime($userAuth['token_expira']) < time()
    ) {
        $stmtClear = $mysqli->prepare("
            UPDATE usuarios
            SET token = NULL,
                token_expira = NULL
            WHERE idusuario = ?
        ");

        $stmtClear->bind_param(
            "i",
            $userAuth['idusuario']
        );

        $stmtClear->execute();

        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "msg" => "Sesión expirada"
        ]);
        exit;
    }

    /*
     * Renovar sesión
     */
    $minutosInactividad = 30;

    $stmtRenew = $mysqli->prepare("
        UPDATE usuarios
        SET token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE)
        WHERE idusuario = ?
    ");

    $stmtRenew->bind_param(
        "ii",
        $minutosInactividad,
        $userAuth['idusuario']
    );

    $stmtRenew->execute();


    /*
     * Obtener roles del usuario
     */
    $stmtRoles = $mysqli->prepare("
        SELECT DISTINCT
            ura.idtipousuario,
            tu.descripcion AS rol_descripcion
        FROM usuarios_roles_apps ura
        INNER JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario
        WHERE ura.idusuario = ?
    ");

    $stmtRoles->bind_param(
        "i",
        $userAuth['idusuario']
    );

    $stmtRoles->execute();

    $resRoles = $stmtRoles->get_result();

    $roles = [];

    while ($rol = $resRoles->fetch_assoc()) {
        $roles[] = [
            'idtipousuario' => intval($rol['idtipousuario']),
            'descripcion' => $rol['rol_descripcion']
        ];
    }

    $userAuth['roles'] = $roles;

    return $userAuth;
}

function validarPermisoEndpoint($mysqli, $userAuth)
{
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $metodoActual = strtoupper($_SERVER['REQUEST_METHOD']);

    $roles = $userAuth['roles'] ?? [];

    if (empty($roles)) {
        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Rol no definido"
        ]);

        exit;
    }

    /*
     * Normalizamos la URI quitando /api si existe.
     *
     * Ejemplo:
     *
     * /api/sso/usuarios/listar_usuarios.php
     *
     * se convierte en:
     *
     * /usuarios/listar_usuarios.php
     */
    $endpoint = preg_replace(
        '#^/api/sso#',
        '',
        $requestUri
    );

    /*
     * Buscar los permisos de todos los roles
     * del usuario.
     */
    $permisoEncontrado = false;

    $stmt = $mysqli->prepare("
        SELECT
            p.endpoint,
            UPPER(p.metodo) AS metodo
        FROM permisos p
        INNER JOIN permisos_rol pr
            ON pr.idpermiso = p.idpermiso
        WHERE pr.idtipousuario = ?
    ");

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando validación de permiso"
        ]);

        exit;
    }

    foreach ($roles as $rol) {

        $idtipousuario = intval(
            $rol['idtipousuario'] ?? 0
        );

        if ($idtipousuario <= 0) {
            continue;
        }

        $stmt->bind_param(
            "i",
            $idtipousuario
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        while ($permiso = $resultado->fetch_assoc()) {

            $patron = trim(
                $permiso['endpoint'] ?? ''
            );

            $metodoPermiso = strtoupper(
                trim($permiso['metodo'] ?? '')
            );

            /*
             * Si el permiso no tiene endpoint,
             * no lo usamos para controlar una URL.
             */
            if ($patron === '') {
                continue;
            }

            /*
             * Convertimos:
             *
             * /usuarios/*
             *
             * en una expresión regular.
             */
            $regex = '#^' .
                str_replace(
                    '\*',
                    '.*',
                    preg_quote($patron, '#')
                ) .
                '$#';

            $endpointCoincide = preg_match(
                $regex,
                $endpoint
            );

            /*
             * El método puede ser:
             *
             * GET
             * POST
             * PUT
             * DELETE
             * ALL
             */
            $metodoCoincide =
                ($metodoPermiso === 'ALL') ||
                ($metodoPermiso === $metodoActual);

            if ($endpointCoincide && $metodoCoincide) {
                $permisoEncontrado = true;
                break 2;
            }
        }
    }

    $stmt->close();

    if (!$permisoEncontrado) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Sin permiso para este endpoint"
        ]);

        exit;
    }

    return true;
}