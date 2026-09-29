<?php
// /api/sso/auth/auth_middleware.php

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

if (!isset($mysqli)) {
    require_once $rutas['conexion'];
}


/**
 * ============================================================
 * OBTENER TOKEN BEARER
 * ============================================================
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
 * ============================================================
 * OBTENER EMPRESA ACTIVA
 * ============================================================
 *
 * Se utiliza el mismo concepto que el resto del sistema:
 *
 * X-EMPRESA-ID
 *
 * Como respaldo se aceptan idempresa por GET/POST.
 *
 * IMPORTANTE:
 * El valor solamente identifica la empresa solicitada.
 * Después se verifica en BD que el usuario realmente
 * tenga acceso a esa empresa.
 */
function obtenerEmpresaActivaMiddleware()
{
    $idEmpresa = 0;

    if (
        isset($_SERVER['HTTP_X_EMPRESA_ID']) &&
        $_SERVER['HTTP_X_EMPRESA_ID'] !== ''
    ) {
        $idEmpresa = intval(
            $_SERVER['HTTP_X_EMPRESA_ID']
        );
    }

    if (
        $idEmpresa <= 0 &&
        isset($_REQUEST['idempresa'])
    ) {
        $idEmpresa = intval(
            $_REQUEST['idempresa']
        );
    }

    return $idEmpresa;
}


/**
 * ============================================================
 * OBTENER APLICACIÓN DESDE LA URL
 * ============================================================
 *
 * Ejemplo:
 *
 * /api/sso/usuarios/listar.php
 *       ↓
 *       sso
 *
 * /api/ctacte/compras/listar.php
 *       ↓
 *       ctacte
 *
 * /api/fichajes/empleados/listar.php
 *       ↓
 *       fichajes
 *
 * El slug se resuelve contra la tabla aplicaciones.
 * No se utilizan IDs de aplicación hardcodeados.
 */
function obtenerAplicacionDesdeRequest($mysqli)
{
    $requestUri = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $partes = explode(
        '/',
        trim($requestUri, '/')
    );

    if (
        count($partes) < 3 ||
        $partes[0] !== 'api'
    ) {
        return null;
    }

    $slug = trim(
        $partes[1]
    );

    if ($slug === '') {
        return null;
    }

    $stmt = $mysqli->prepare("
        SELECT
            idaplicacion,
            nombre,
            slug,
            activo
        FROM aplicaciones
        WHERE slug = ?
        LIMIT 1
    ");

    if (!$stmt) {
        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando identificación de aplicación"
        ]);

        exit;
    }

    $stmt->bind_param(
        "s",
        $slug
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {

        $stmt->close();

        return null;
    }

    $aplicacion = $resultado->fetch_assoc();

    $stmt->close();

    if (intval($aplicacion['activo']) !== 1) {
        return null;
    }

    return $aplicacion;
}


/**
 * ============================================================
 * NORMALIZAR ENDPOINT
 * ============================================================
 *
 * /api/sso/usuarios/listar.php
 *      -> /usuarios/listar.php
 *
 * /api/ctacte/compras/listar.php
 *      -> /compras/listar.php
 *
 * /api/fichajes/empleados/listar.php
 *      -> /empleados/listar.php
 */
function obtenerEndpointActual()
{
    $requestUri = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $partes = explode(
        '/',
        trim($requestUri, '/')
    );

    if (
        count($partes) >= 3 &&
        $partes[0] === 'api'
    ) {

        array_shift($partes); // api
        array_shift($partes); // aplicación

        $endpoint = '/' .
            implode(
                '/',
                $partes
            );

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

    return $endpoint;
}


/**
 * ============================================================
 * VALIDAR TOKEN API
 * ============================================================
 *
 * Esta función solamente autentica al usuario.
 *
 * NO calcula un nivel global.
 *
 * Los roles se resolverán posteriormente considerando:
 *
 * usuario + empresa + aplicación
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
            u.token_expira,
            u.idtiposistema,
            uts.clave AS tipo_sistema
        FROM usuarios u

        INNER JOIN usuarios_tipo_sistema uts
            ON uts.idtiposistema = u.idtiposistema

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
    // Determinar ROOT desde BD
    // --------------------------------------------------------

    $tipoSistema = strtoupper(
        trim(
            $userAuth['tipo_sistema'] ?? ''
        )
    );

    $userAuth['es_root'] =
        ($tipoSistema === 'ROOT');


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
    // ROOT
    // --------------------------------------------------------
    //
    // ROOT no necesita roles de aplicación.
    //
    // No se consulta SUPER_ADMIN.
    // No se consulta nivel.
    // No se consulta permisos_rol.
    //
    // El bypass se determina exclusivamente por:
    //
    // usuarios_tipo_sistema.clave = ROOT
    //
    // --------------------------------------------------------

    if ($userAuth['es_root']) {

        $userAuth['roles'] = [[
            'idtipousuario' => -1,
            'descripcion'   => 'ROOT',
            'clave'         => 'ROOT',
            'nivel'         => 999
        ]];

        return $userAuth;
    }


    // --------------------------------------------------------
    // No cargamos roles globales.
    //
    // MUY IMPORTANTE:
    //
    // Un usuario puede ser:
    //
    // Empresa 1 / Fichajes  / OPERADOR
    // Empresa 1 / CTACTE    / OPERADOR AVANZADO
    // Empresa 2 / Fichajes  / ADMIN
    //
    // Por eso los roles se resolverán en
    // validarPermisoEndpoint() usando:
    //
    // idusuario + idempresa + idaplicacion
    //
    // --------------------------------------------------------

    $userAuth['roles'] = [];

    return $userAuth;
}


/**
 * ============================================================
 * VALIDAR PERMISO DEL ENDPOINT
 * ============================================================
 *
 * Seguridad completa:
 *
 * usuario
 *   +
 * empresa activa
 *   +
 * aplicación actual
 *   +
 * rol asignado
 *   +
 * nivel del rol
 *   +
 * permiso asignado al rol
 *   +
 * nivel mínimo del permiso
 *   +
 * endpoint
 *   +
 * método HTTP
 *
 */
function validarPermisoEndpoint($mysqli, $userAuth)
{
    // --------------------------------------------------------
    // ROOT
    // --------------------------------------------------------

    if (!empty($userAuth['es_root'])) {
        return true;
    }


    // --------------------------------------------------------
    // Método HTTP
    // --------------------------------------------------------

    $metodoActual = strtoupper(
        $_SERVER['REQUEST_METHOD'] ?? 'GET'
    );


    // --------------------------------------------------------
    // Empresa activa
    // --------------------------------------------------------

    $idEmpresa = obtenerEmpresaActivaMiddleware();

    if ($idEmpresa <= 0) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Empresa no seleccionada"
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Verificar que el usuario pertenece a la empresa
    // --------------------------------------------------------

    $stmtEmpresa = $mysqli->prepare("
        SELECT 1
        FROM usuarios_empresas
        WHERE idusuario = ?
        AND idempresa = ?
        AND activo = 1
        LIMIT 1
    ");

    if (!$stmtEmpresa) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando validación de empresa"
        ]);

        exit;
    }

    $stmtEmpresa->bind_param(
        "ii",
        $userAuth['idusuario'],
        $idEmpresa
    );

    $stmtEmpresa->execute();

    $resEmpresa = $stmtEmpresa->get_result();

    $usuarioPerteneceEmpresa =
        ($resEmpresa->num_rows > 0);

    $stmtEmpresa->close();


    if (!$usuarioPerteneceEmpresa) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - El usuario no pertenece a la empresa seleccionada"
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Obtener aplicación desde la URL
    // --------------------------------------------------------

    $aplicacion =
        obtenerAplicacionDesdeRequest(
            $mysqli
        );

    if (!$aplicacion) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Aplicación no válida"
        ]);

        exit;
    }

    $idAplicacion =
        intval(
            $aplicacion['idaplicacion']
        );


    // --------------------------------------------------------
    // Verificar que la aplicación esté habilitada
    // para la empresa
    // --------------------------------------------------------

    $stmtEmpresaApp = $mysqli->prepare("
        SELECT 1
        FROM empresas_aplicaciones
        WHERE idempresa = ?
        AND idaplicacion = ?
        AND activo = 1
        LIMIT 1
    ");

    if (!$stmtEmpresaApp) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando validación de aplicación"
        ]);

        exit;
    }

    $stmtEmpresaApp->bind_param(
        "ii",
        $idEmpresa,
        $idAplicacion
    );

    $stmtEmpresaApp->execute();

    $resEmpresaApp =
        $stmtEmpresaApp->get_result();

    $aplicacionHabilitada =
        ($resEmpresaApp->num_rows > 0);

    $stmtEmpresaApp->close();


    if (!$aplicacionHabilitada) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - La aplicación no está habilitada para la empresa"
        ]);

        exit;
    }


    // --------------------------------------------------------
    // Endpoint actual
    // --------------------------------------------------------

    $endpoint =
        obtenerEndpointActual();


    // --------------------------------------------------------
    // Obtener permisos
    //
    // IMPORTANTE:
    //
    // Acá se filtra simultáneamente:
    //
    // usuario
    // empresa
    // aplicación
    //
    // Y se calcula MAX(tu.nivel) solamente dentro
    // de esa combinación.
    //
    // --------------------------------------------------------

    $stmtPermisos = $mysqli->prepare("
        SELECT
            p.idpermiso,
            p.clavepermiso,
            p.endpoint,
            UPPER(p.metodo) AS metodo,
            p.nivel_minimo,
            MAX(tu.nivel) AS nivel_usuario
        FROM usuarios_roles_apps ura

        INNER JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario

        INNER JOIN permisos_rol pr
            ON pr.idtipousuario = tu.idtipousuario

        INNER JOIN permisos p
            ON p.idpermiso = pr.idpermiso

        INNER JOIN aplicaciones_permisos ap
            ON ap.idaplicacion = ura.idaplicacion
            AND ap.idpermiso = p.idpermiso

        WHERE ura.idusuario = ?
        AND ura.idempresa = ?
        AND ura.idaplicacion = ?

        GROUP BY
            p.idpermiso,
            p.clavepermiso,
            p.endpoint,
            p.metodo,
            p.nivel_minimo
    ");

    if (!$stmtPermisos) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando validación de permiso"
        ]);

        exit;
    }

    $stmtPermisos->bind_param(
        "iii",
        $userAuth['idusuario'],
        $idEmpresa,
        $idAplicacion
    );

    $stmtPermisos->execute();

    $resultado =
        $stmtPermisos->get_result();


    // --------------------------------------------------------
    // Revisar permisos
    // --------------------------------------------------------

    $permisoEncontrado = false;

    $nivelUsuario = 0;

    while ($permiso = $resultado->fetch_assoc()) {

        $patron = trim(
            $permiso['endpoint'] ?? ''
        );

        $metodoPermiso = strtoupper(
            trim(
                $permiso['metodo'] ?? ''
            )
        );

        $nivelMinimo = intval(
            $permiso['nivel_minimo'] ?? 0
        );

        $nivelRol = intval(
            $permiso['nivel_usuario'] ?? 0
        );


        // Guardamos el mayor nivel encontrado
        // exclusivamente para esta empresa/aplicación.

        if ($nivelRol > $nivelUsuario) {
            $nivelUsuario = $nivelRol;
        }


        // ----------------------------------------------------
        // Primero nivel
        // ----------------------------------------------------

        if ($nivelRol < $nivelMinimo) {
            continue;
        }


        // ----------------------------------------------------
        // Permiso sin endpoint
        // ----------------------------------------------------

        if ($patron === '') {
            continue;
        }


        // ----------------------------------------------------
        // Normalizar endpoint
        // ----------------------------------------------------

        if ($patron[0] !== '/') {
            $patron = '/' . $patron;
        }


        // ----------------------------------------------------
        // Convertir * a regex
        // ----------------------------------------------------

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


        $endpointCoincide =
            preg_match(
                $regex,
                $endpoint
            );


        // ----------------------------------------------------
        // Validar método HTTP
        // ----------------------------------------------------

        $metodoCoincide =
            ($metodoPermiso === 'ALL') ||
            ($metodoPermiso === $metodoActual);


        // ----------------------------------------------------
        // Permiso válido
        // ----------------------------------------------------

        if (
            $endpointCoincide &&
            $metodoCoincide
        ) {

            $permisoEncontrado = true;

            break;
        }
    }

    $stmtPermisos->close();


    // --------------------------------------------------------
    // Sin permiso
    // --------------------------------------------------------

    if (!$permisoEncontrado) {

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Acceso denegado - Sin permiso para este endpoint",
            "endpoint" => $endpoint,
            "metodo" => $metodoActual,
            "idempresa" => $idEmpresa,
            "idaplicacion" => $idAplicacion,
            "nivel_usuario" => $nivelUsuario
        ]);

        exit;
    }


    return true;
}