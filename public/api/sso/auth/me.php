<?php

// =========================================================
// 1. CARGAR RUTAS CENTRALES
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// =========================================================
// 2. CARGAR COMPOSER
// =========================================================

require_once $rutas['autoload'];

// =========================================================
// 3. CARGAR .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();

} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

// =========================================================
// 4. CONEXIÓN
// =========================================================

require_once $rutas['conexion'];

header('Content-Type: application/json');

// =========================================================
// 5. OBTENER TOKEN DEL HEADER
// =========================================================

$authHeader = '';

if (function_exists('getallheaders')) {

    $headers = getallheaders();

    $authHeader =
        $headers['Authorization']
        ?? $headers['authorization']
        ?? '';
}

if (
    empty($authHeader) &&
    isset($_SERVER['HTTP_AUTHORIZATION'])
) {

    $authHeader =
        $_SERVER['HTTP_AUTHORIZATION'];
}

$token = trim(
    str_replace(
        'Bearer ',
        '',
        $authHeader
    )
);

// =========================================================
// 6. VALIDAR TOKEN
// =========================================================

if (empty($token)) {

    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "msg" => "No autorizado"
    ]);

    exit;
}

// =========================================================
// 7. BUSCAR USUARIO
// =========================================================

$sql = "
    SELECT
        u.idusuario,
        u.nombreapellido,
        u.username,
        u.email,
        u.token_expira,
        u.idtiposistema,
        uts.nombre AS nombre_tipo_sistema,
        uts.clave AS tipo_sistema
    FROM usuarios u

    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema

    WHERE u.token = ?
      AND u.baja = 0

    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "Error SQL"
    ]);

    exit;
}

$stmt->bind_param(
    "s",
    $token
);

$stmt->execute();

$user =
    $stmt
        ->get_result()
        ->fetch_assoc();

$stmt->close();

// =========================================================
// 8. USUARIO NO ENCONTRADO
// =========================================================

if (!$user) {

    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "msg" => "Token inválido"
    ]);

    exit;
}

// =========================================================
// 9. VALIDAR EXPIRACIÓN
// =========================================================

if (
    !empty($user['token_expira']) &&
    strtotime($user['token_expira']) < time()
) {

    $stmtClear = $mysqli->prepare("
        UPDATE usuarios
        SET
            token = NULL,
            token_expira = NULL
        WHERE idusuario = ?
    ");

    $stmtClear->bind_param(
        "i",
        $user['idusuario']
    );

    $stmtClear->execute();

    $stmtClear->close();

    http_response_code(401);

    echo json_encode([
        "status" => "error",
        "msg" => "Sesión expirada por inactividad"
    ]);

    exit;
}

// =========================================================
// 10. RENOVAR TOKEN
// =========================================================

$minutosInactividad = 30;

$stmtRenew = $mysqli->prepare("
    UPDATE usuarios
    SET
        token_expira =
            DATE_ADD(NOW(), INTERVAL ? MINUTE)
    WHERE idusuario = ?
");

$stmtRenew->bind_param(
    "ii",
    $minutosInactividad,
    $user['idusuario']
);

$stmtRenew->execute();

$stmtRenew->close();

// =========================================================
// 11. DETERMINAR TIPO DE SISTEMA
// =========================================================

$tipoSistema =
    strtoupper(
        trim(
            $user['tipo_sistema']
        )
    );

$esRoot =
    ($tipoSistema === 'ROOT');

// =========================================================
// 12. OBTENER EMPRESA ACTIVA
// =========================================================
//
// Prioridad:
// 1. Header X-EMPRESA-ID
// 2. GET idempresa
// 3. POST idempresa
//
// =========================================================

$idEmpresa = 0;

if (
    isset($_SERVER['HTTP_X_EMPRESA_ID']) &&
    is_numeric($_SERVER['HTTP_X_EMPRESA_ID'])
) {

    $idEmpresa =
        (int)$_SERVER['HTTP_X_EMPRESA_ID'];

} elseif (
    isset($_GET['idempresa']) &&
    is_numeric($_GET['idempresa'])
) {

    $idEmpresa =
        (int)$_GET['idempresa'];

} elseif (
    isset($_POST['idempresa']) &&
    is_numeric($_POST['idempresa'])
) {

    $idEmpresa =
        (int)$_POST['idempresa'];
}

// =========================================================
// 13. OBTENER APLICACIÓN ACTUAL
// =========================================================
//
// Prioridad:
// 1. Header X-APLICACION-ID
// 2. GET idaplicacion
// 3. POST idaplicacion
//
// =========================================================

$idAplicacion = 0;
$slugAplicacion = '';

if (
    isset($_SERVER['HTTP_X_APLICACION_ID']) &&
    is_numeric($_SERVER['HTTP_X_APLICACION_ID'])
) {

    $idAplicacion =
        (int)$_SERVER['HTTP_X_APLICACION_ID'];

} elseif (
    isset($_GET['idaplicacion']) &&
    is_numeric($_GET['idaplicacion'])
) {

    $idAplicacion =
        (int)$_GET['idaplicacion'];

} elseif (
    isset($_POST['idaplicacion']) &&
    is_numeric($_POST['idaplicacion'])
) {

    $idAplicacion =
        (int)$_POST['idaplicacion'];
}


// =========================================================
// 14. RESOLVER APLICACIÓN
// =========================================================
//
// La aplicación ahora se obtiene por ID enviado
// en X-APLICACION-ID.
//
// =========================================================

if ($idAplicacion > 0) {

    $stmtApp = $mysqli->prepare("
        SELECT
            idaplicacion,
            slug
        FROM aplicaciones
        WHERE idaplicacion = ?
          AND activo = 1
        LIMIT 1
    ");

    if ($stmtApp) {

        $stmtApp->bind_param(
            "i",
            $idAplicacion
        );

        $stmtApp->execute();

        $app =
            $stmtApp
                ->get_result()
                ->fetch_assoc();

        $stmtApp->close();

        if ($app) {

            $idAplicacion =
                (int)$app['idaplicacion'];

            $slugAplicacion =
                $app['slug'];

        } else {

            $idAplicacion = 0;
            $slugAplicacion = '';

        }
    }
}

// =========================================================
// 15. OBTENER PERMISOS
// =========================================================

$permisos = [];

// =========================================================
// ROOT
// =========================================================
//
// ROOT recibe todos los permisos.
//
// =========================================================

if ($esRoot) {

    $sqlP = "
        SELECT DISTINCT
            p.clavepermiso
        FROM permisos p
        WHERE p.clavepermiso IS NOT NULL
          AND p.clavepermiso <> ''
        ORDER BY p.clavepermiso
    ";

    $stmtP =
        $mysqli->prepare($sqlP);

    if ($stmtP) {

        $stmtP->execute();

        $resP =
            $stmtP
                ->get_result();

        while (
            $p = $resP->fetch_assoc()
        ) {

            $permisos[] =
                $p['clavepermiso'];
        }

        $stmtP->close();
    }

// =========================================================
// USUARIO NORMAL
// =========================================================

} else {

    // -----------------------------------------------------
    // Sin empresa o aplicación no hay permisos
    // -----------------------------------------------------

    if (
        $idEmpresa > 0 &&
        $idAplicacion > 0
    ) {

        $sqlP = "
            SELECT DISTINCT
                p.clavepermiso

            FROM usuarios_roles_apps ura

            INNER JOIN permisos_rol pr
                ON pr.idtipousuario =
                   ura.idtipousuario

            INNER JOIN permisos p
                ON p.idpermiso =
                   pr.idpermiso

            INNER JOIN aplicaciones_permisos ap
                ON ap.idaplicacion =
                   ura.idaplicacion
               AND ap.idpermiso =
                   p.idpermiso

            WHERE ura.idusuario = ?
              AND ura.idempresa = ?
              AND ura.idaplicacion = ?
        ";

        $stmtP =
            $mysqli->prepare($sqlP);

        if ($stmtP) {

            $stmtP->bind_param(
                "iii",
                $user['idusuario'],
                $idEmpresa,
                $idAplicacion
            );

            $stmtP->execute();

            $resP =
                $stmtP
                    ->get_result();

            while (
                $p = $resP->fetch_assoc()
            ) {

                if (
                    !empty(
                        $p['clavepermiso']
                    )
                ) {

                    $permisos[] =
                        $p['clavepermiso'];
                }
            }

            $stmtP->close();
        }
    }
}

// =========================================================
// 16. OBTENER ROLES DE APLICACIÓN
// =========================================================

$roles = [];

if (!$esRoot) {

    if (
        $idEmpresa > 0 &&
        $idAplicacion > 0
    ) {

        $sqlR = "
            SELECT DISTINCT
                r.clave,
                r.descripcion,
                r.nivel

            FROM tiposusuario r

            INNER JOIN usuarios_roles_apps ura
                ON r.idtipousuario =
                   ura.idtipousuario

            WHERE ura.idusuario = ?
              AND ura.idempresa = ?
              AND ura.idaplicacion = ?

            ORDER BY r.nivel DESC
        ";

        $stmtR =
            $mysqli->prepare($sqlR);

        if ($stmtR) {

            $stmtR->bind_param(
                "iii",
                $user['idusuario'],
                $idEmpresa,
                $idAplicacion
            );

            $stmtR->execute();

            $resR =
                $stmtR
                    ->get_result();

            while (
                $r = $resR->fetch_assoc()
            ) {

                $roles[] = [

                    "clave" =>
                        $r['clave'],

                    "nombre" =>
                        $r['descripcion'],

                    "nivel" =>
                        (int)$r['nivel']
                ];
            }

            $stmtR->close();
        }
    }
}

// =========================================================
// 17. RESPUESTA
// =========================================================

echo json_encode([

    "status" => "ok",

    "usuario" => [

        "id" =>
            (int)$user['idusuario'],

        "nombre" =>
            $user['nombreapellido'],

        "username" =>
            $user['username'],

        "email" =>
            $user['email'],

        "idtiposistema" =>
            (int)$user['idtiposistema'],

        "tipo_sistema" =>
            $user['tipo_sistema'],

        "nombre_tipo_sistema" =>
            $user['nombre_tipo_sistema'],

        "root" =>
            $esRoot
    ],

    "empresa_activa" =>
        $idEmpresa,

    "aplicacion_activa" =>
        $idAplicacion,

    "slug_aplicacion" =>
        $slugAplicacion,

    "permisos" =>
        $permisos,

    "roles" =>
        $roles
]);