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

        $authHeader = trim(
            $_SERVER['HTTP_AUTHORIZATION']
        );

    } elseif (function_exists('getallheaders')) {

        $headers = getallheaders();

        $authHeader =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? '';
    }

    if (stripos($authHeader, 'Bearer ') === 0) {

        return trim(
            substr($authHeader, 7)
        );
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


    // --------------------------------------------------------
    // Buscar usuario por token
    // --------------------------------------------------------

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

    if (!$stmt) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando validación del token"
        ]);

        exit;
    }

    $stmt->bind_param(
        "s",
        $token
    );

    $stmt->execute();

    $resultado = $stmt->get_result();


    // --------------------------------------------------------
    // Token inexistente
    // --------------------------------------------------------

    if ($resultado->num_rows === 0) {

        $stmt->close();

        http_response_code(401);

        echo json_encode([
            "status" => "error",
            "msg" => "Token inválido"
        ]);

        exit;
    }


    $userAuth = $resultado->fetch_assoc();

    $stmt->close();


    // --------------------------------------------------------
    // Verificar expiración
    // --------------------------------------------------------

    if (
        !empty($userAuth['token_expira']) &&
        strtotime($userAuth['token_expira']) < time()
    ) {

        $stmtClear = $mysqli->prepare("
            UPDATE usuarios
            SET
                token = NULL,
                token_expira = NULL
            WHERE idusuario = ?
        ");

        if ($stmtClear) {

            $stmtClear->bind_param(
                "i",
                $userAuth['idusuario']
            );

            $stmtClear->execute();

            $stmtClear->close();
        }

        http_response_code(401);

        echo json_encode([
            "status" => "error",
            "msg" => "Sesión expirada"
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Renovar sesión por actividad
    // --------------------------------------------------------

    $minutosInactividad = 30;

    $stmtRenew = $mysqli->prepare("
        UPDATE usuarios
        SET token_expira = DATE_ADD(
            NOW(),
            INTERVAL ? MINUTE
        )
        WHERE idusuario = ?
    ");

    if ($stmtRenew) {

        $stmtRenew->bind_param(
            "ii",
            $minutosInactividad,
            $userAuth['idusuario']
        );

        $stmtRenew->execute();

        $stmtRenew->close();
    }


    // --------------------------------------------------------
    // Obtener roles del usuario
    // --------------------------------------------------------

    $stmtRoles = $mysqli->prepare("
        SELECT DISTINCT
            ura.idtipousuario,
            tu.descripcion AS rol_descripcion,
            tu.clave AS rol_clave
        FROM usuarios_roles_apps ura
        INNER JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario
        WHERE ura.idusuario = ?
    ");

    if (!$stmtRoles) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error obteniendo roles del usuario"
        ]);

        exit;
    }

    $stmtRoles->bind_param(
        "i",
        $userAuth['idusuario']
    );

    $stmtRoles->execute();

    $resRoles = $stmtRoles->get_result();

    $roles = [];

    while ($rol = $resRoles->fetch_assoc()) {

        $roles[] = [

            'idtipousuario' =>
                intval($rol['idtipousuario']),

            'descripcion' =>
                $rol['rol_descripcion'],

            'clave' =>
                $rol['rol_clave']
        ];
    }

    $stmtRoles->close();

    $userAuth['roles'] = $roles;


    return $userAuth;
}


/**
 * Valida que el usuario tenga permiso para el endpoint actual.
 *
 * IMPORTANTE:
 *
 * La URI puede pertenecer a cualquier aplicación:
 *
 * /api/sso/...
 * /api/ctacte/...
 * /api/...
 *
 * Por eso no quitamos solamente /api/sso.
 *
 * Se normaliza siempre quitando únicamente /api.
 */
function validarPermisoEndpoint($mysqli, $userAuth)
{
    $requestUri = parse_url(
        $_SERVER['REQUEST_URI'],
        PHP_URL_PATH
    );

    $metodoActual = strtoupper(
        $_SERVER['REQUEST_METHOD']
    );

    $roles = $userAuth['roles'] ?? [];


    // --------------------------------------------------------
    // Usuario sin roles
    // --------------------------------------------------------

    if (empty($roles)) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Rol no definido"
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Normalizar endpoint
    // --------------------------------------------------------
    //
    // Ejemplo:
    //
    // /api/sso/usuarios/listar_usuarios.php
    //
    // queda:
    //
    // /sso/usuarios/listar_usuarios.php
    //
    //
    // /api/ctacte/dashboard/metricas.php
    //
    // queda:
    //
    // /ctacte/dashboard/metricas.php
    //
    // --------------------------------------------------------

    // --------------------------------------------------------
// Normalizar endpoint según la aplicación
// --------------------------------------------------------
//
// SSO:
// /api/sso/usuarios/listar_tipos_usuario.php
//      ↓
// /usuarios/listar_tipos_usuario.php
//
// CtaCte:
// /api/ctacte/compras/obtener_datos_dashboard.php
//      ↓
// /compras/obtener_datos_dashboard.php
//
// --------------------------------------------------------

if (preg_match('#^/api/sso(/.*)$#', $requestUri, $match)) {

    $endpoint = $match[1];

} elseif (preg_match('#^/api/ctacte(/.*)$#', $requestUri, $match)) {

    $endpoint = $match[1];

} else {

    $endpoint = preg_replace(
        '#^/api#',
        '',
        $requestUri
    );
}

if ($endpoint === '') {
    $endpoint = '/';
}

$endpoint = '/' . ltrim($endpoint, '/');


    // --------------------------------------------------------
    // Buscar permisos de los roles
    // --------------------------------------------------------

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


    // --------------------------------------------------------
    // Revisar todos los roles
    // --------------------------------------------------------

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
                trim(
                    $permiso['metodo'] ?? ''
                )
            );


            // ------------------------------------------------
            // Permiso sin endpoint
            // ------------------------------------------------

            if ($patron === '') {
                continue;
            }


            // ------------------------------------------------
            // Normalizar endpoint guardado en BD
            // ------------------------------------------------

            if ($patron[0] !== '/') {
                $patron = '/' . $patron;
            }


            // ------------------------------------------------
            // Convertir * a regex
            // ------------------------------------------------
            //
            // Ejemplo:
            //
            // /ctacte/dashboard/*
            //
            // se convierte en:
            //
            // ^/ctacte/dashboard/.*$
            //
            // ------------------------------------------------

            $regex = '#^' .
                str_replace(
                    '\*',
                    '.*',
                    preg_quote(
                        $patron,
                        '#'
                    )
                ) .
                '$#';


            $endpointCoincide = preg_match(
                $regex,
                $endpoint
            );


            // ------------------------------------------------
            // Validar método HTTP
            // ------------------------------------------------

            $metodoCoincide =
                ($metodoPermiso === 'ALL') ||
                ($metodoPermiso === $metodoActual);


            // ------------------------------------------------
            // Permiso encontrado
            // ------------------------------------------------

            if (
                $endpointCoincide &&
                $metodoCoincide
            ) {

                $permisoEncontrado = true;

                break 2;
            }
        }
    }

    $stmt->close();


    // --------------------------------------------------------
    // Sin permiso
    // --------------------------------------------------------

    if (!$permisoEncontrado) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Sin permiso para este endpoint",
            "endpoint" => $endpoint,
            "metodo" => $metodoActual
        ]);

        exit;
    }


    return true;
}