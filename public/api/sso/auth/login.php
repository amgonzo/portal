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
// 4. CONEXIÓN Y AUDITORÍA
// =========================================================

require_once $rutas['conexion'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

// =========================================================
// 📥 DATOS
// =========================================================

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$email = trim($input['email'] ?? '');
$p      = trim($input['password'] ?? '');
$empresaSlug = trim($input['empresa'] ?? '');

// =========================================================
// 🔴 VALIDAR DATOS
// =========================================================

if (empty($email) || empty($p)) {

    registrarLog(
        $mysqli,
        'login_fallido_datos_incompletos',
        'usuarios',
        null,
        null,
        ['email_intentado' => $email]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "datos"
    ]);

    exit;
}

// =========================================================
// 🔐 BUSCAR USUARIO POR EMAIL
// =========================================================

$sql = "
    SELECT
        u.idusuario,
        u.username,
        u.email,
        u.nombreapellido,
        u.password,
        u.baja,
        u.idtiposistema,
        uts.clave AS tipo_sistema
    FROM usuarios u
    INNER JOIN usuarios_tipo_sistema uts
        ON uts.idtiposistema = u.idtiposistema
    WHERE u.email = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "msg" => "sql_error"
    ]);

    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();

$res = $stmt->get_result();

$user = $res->fetch_assoc();

$stmt->close();

// =========================================================
// ❌ USUARIO NO EXISTE
// =========================================================

if (!$user) {

    echo json_encode([
        "status" => "error",
        "msg" => "usuario"
    ]);

    exit;
}

// =========================================================
// ❌ USUARIO DADO DE BAJA
// =========================================================

if ((int)$user['baja'] === 1) {

    registrarLog(
        $mysqli,
        'login_bloqueado_baja',
        'usuarios',
        $user['idusuario'],
        null,
        ['username' => $user['username']]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "baja"
    ]);

    exit;
}

// =========================================================
// ❌ PASSWORD INCORRECTA
// =========================================================

if (!password_verify($p, $user['password'])) {

    registrarLog(
        $mysqli,
        'login_fallido_pass',
        'usuarios',
        $user['idusuario'],
        null,
        ['username' => $user['username']]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "password"
    ]);

    exit;
}

// =========================================================
// 👑 TIPO DE USUARIO DEL SISTEMA
// =========================================================

$esRoot = (
    strtoupper(trim($user['tipo_sistema'])) === 'ROOT'
);

// =========================================================
// 🏢 EMPRESAS
// =========================================================

$empresaData = null;
$idEmpresa = null;
$empresas = [];

// =========================================================
// ROOT
// =========================================================

if ($esRoot) {

    // -----------------------------------------------------
    // ROOT → TODAS LAS EMPRESAS ACTIVAS
    // -----------------------------------------------------

    $sqlEmpresas = "
        SELECT
            e.idempresa,
            e.nombre,
            e.razon_social,
            e.slug
        FROM empresas e
        WHERE e.activo = 1
        ORDER BY e.nombre ASC
    ";

    $stmtEmp = $mysqli->prepare($sqlEmpresas);

    if (!$stmtEmp) {

        echo json_encode([
            "status" => "error",
            "msg" => "sql_error"
        ]);

        exit;
    }

    $stmtEmp->execute();

    $resEmp = $stmtEmp->get_result();

    while ($emp = $resEmp->fetch_assoc()) {
        $empresas[] = $emp;
    }

    $stmtEmp->close();

    // -----------------------------------------------------
    // Si vino una empresa, la validamos.
    // ROOT no está obligado a indicar empresa.
    // -----------------------------------------------------

    if ($empresaSlug !== '') {

        $sqlEmpresa = "
            SELECT
                idempresa,
                nombre,
                razon_social,
                slug
            FROM empresas
            WHERE slug = ?
              AND activo = 1
            LIMIT 1
        ";

        $stmtEmpresa = $mysqli->prepare($sqlEmpresa);

        if (!$stmtEmpresa) {

            echo json_encode([
                "status" => "error",
                "msg" => "sql_error"
            ]);

            exit;
        }

        $stmtEmpresa->bind_param(
            "s",
            $empresaSlug
        );

        $stmtEmpresa->execute();

        $resEmpresa = $stmtEmpresa->get_result();

        $empresaData = $resEmpresa->fetch_assoc();

        $stmtEmpresa->close();

        if (!$empresaData) {

            echo json_encode([
                "status" => "error",
                "msg" => "empresa_inexistente"
            ]);

            exit;
        }

        $idEmpresa = (int)$empresaData['idempresa'];
    }

}

// =========================================================
// USER NORMAL
// =========================================================

else {

    // -----------------------------------------------------
    // Obtener solamente las empresas asignadas
    // -----------------------------------------------------

    $sqlEmpresas = "
        SELECT
            e.idempresa,
            e.nombre,
            e.razon_social,
            e.slug
        FROM empresas e
        INNER JOIN usuarios_empresas ue
            ON e.idempresa = ue.idempresa
        WHERE ue.idusuario = ?
          AND ue.activo = 1
          AND e.activo = 1
        ORDER BY e.nombre ASC
    ";

    $stmtEmp = $mysqli->prepare($sqlEmpresas);

    if (!$stmtEmp) {

        echo json_encode([
            "status" => "error",
            "msg" => "sql_error"
        ]);

        exit;
    }

    $stmtEmp->bind_param(
        "i",
        $user['idusuario']
    );

    $stmtEmp->execute();

    $resEmp = $stmtEmp->get_result();

    while ($emp = $resEmp->fetch_assoc()) {
        $empresas[] = $emp;
    }

    $stmtEmp->close();

    // -----------------------------------------------------
    // Sin empresas
    // -----------------------------------------------------

    if (empty($empresas)) {

        echo json_encode([
            "status" => "error",
            "msg" => "sin_empresas"
        ]);

        exit;
    }

    // -----------------------------------------------------
    // Empresa indicada
    // -----------------------------------------------------

    if ($empresaSlug !== '') {

        foreach ($empresas as $empresa) {

            if ($empresa['slug'] === $empresaSlug) {

                $empresaData = $empresa;

                $idEmpresa = (int)$empresa['idempresa'];

                break;
            }
        }

        if (!$empresaData) {

            echo json_encode([
                "status" => "error",
                "msg" => "sin_acceso_empresa"
            ]);

            exit;
        }
    }

    // -----------------------------------------------------
    // Sin empresa indicada
    // -----------------------------------------------------

    else {

        // Una empresa → entra directamente.

        if (count($empresas) === 1) {

            $empresaData = $empresas[0];

            $idEmpresa = (int)$empresaData['idempresa'];
        }

        // Varias → selector posterior.

        else {

            $empresaData = null;
            $idEmpresa = null;
        }
    }
}

// =========================================================
// 🚀 APLICACIONES
// =========================================================
//
// ROOT:
//     No necesita usuarios_roles_apps.
//     Puede acceder a las aplicaciones activas.
//
// USER:
//     Solamente las aplicaciones asignadas.
//
// =========================================================

$aplicaciones = [];

if ($esRoot) {

    // -----------------------------------------------------
    // ROOT → APLICACIONES ACTIVAS
    // -----------------------------------------------------

    $sqlApps = "
        SELECT DISTINCT
            a.idaplicacion,
            a.nombre,
            a.slug,
            a.url_base
        FROM aplicaciones a
        WHERE a.activo = 1
        ORDER BY a.idaplicacion
    ";

    $stmtApps = $mysqli->prepare($sqlApps);

    if (!$stmtApps) {

        echo json_encode([
            "status" => "error",
            "msg" => "sql_error"
        ]);

        exit;
    }

    $stmtApps->execute();

    $resApps = $stmtApps->get_result();

    while ($app = $resApps->fetch_assoc()) {
        $aplicaciones[] = $app;
    }

    $stmtApps->close();

} else {

    // -----------------------------------------------------
    // USER → aplicaciones asignadas
    // -----------------------------------------------------

    $sqlApps = "
        SELECT DISTINCT
            a.idaplicacion,
            a.nombre,
            a.slug,
            a.url_base
        FROM aplicaciones a
        INNER JOIN usuarios_roles_apps ura
            ON a.idaplicacion = ura.idaplicacion
        WHERE ura.idusuario = ?
          AND a.activo = 1
        ORDER BY a.idaplicacion
    ";

    $stmtApps = $mysqli->prepare($sqlApps);

    if (!$stmtApps) {

        echo json_encode([
            "status" => "error",
            "msg" => "sql_error"
        ]);

        exit;
    }

    $stmtApps->bind_param(
        "i",
        $user['idusuario']
    );

    $stmtApps->execute();

    $resApps = $stmtApps->get_result();

    while ($app = $resApps->fetch_assoc()) {
        $aplicaciones[] = $app;
    }

    $stmtApps->close();

    // -----------------------------------------------------
    // USER sin aplicaciones
    // -----------------------------------------------------

    if (empty($aplicaciones)) {

        registrarLog(
            $mysqli,
            'login_sin_permiso_app',
            'usuarios_roles_apps',
            null,
            $user['idusuario']
        );

        echo json_encode([
            "status" => "error",
            "msg" => "sin_acceso_app"
        ]);

        exit;
    }
}

// =========================================================
// 🛡 ROL Y PERMISOS SSO
// =========================================================

$idTipoUsuarioSSO = 0;

// =========================================================
// ROOT
// =========================================================
//
// ROOT no tiene rol en usuarios_roles_apps.
//
// Para el sistema central no necesitamos asignarle
// SUPER_ADMIN mediante una aplicación.
//
// =========================================================

if ($esRoot) {

    $idTipoUsuarioSSO = 1;

}

// =========================================================
// USER
// =========================================================

else {

    $sqlSSO = "
        SELECT ura.idtipousuario
        FROM usuarios_roles_apps ura
        INNER JOIN aplicaciones a
            ON ura.idaplicacion = a.idaplicacion
        WHERE ura.idusuario = ?
          AND (
                a.slug = 'sso_central'
                OR a.slug = 'sso'
                OR a.idaplicacion = 1
              )
        LIMIT 1
    ";

    $stmtSSO = $mysqli->prepare($sqlSSO);

    if ($stmtSSO) {

        $stmtSSO->bind_param(
            "i",
            $user['idusuario']
        );

        $stmtSSO->execute();

        $resSSO = $stmtSSO->get_result();

        if ($rowSSO = $resSSO->fetch_assoc()) {
            $idTipoUsuarioSSO = (int)$rowSSO['idtipousuario'];
        }

        $stmtSSO->close();
    }
}

// =========================================================
// 🔑 PERMISOS SSO
// =========================================================

$permisosSSO = [];

if ($idTipoUsuarioSSO > 0) {

    $sqlPerms = "
        SELECT DISTINCT p.clavepermiso
        FROM permisos p
        INNER JOIN permisos_rol pr
            ON p.idpermiso = pr.idpermiso
        WHERE pr.idtipousuario = ?
    ";

    $stmtPerms = $mysqli->prepare($sqlPerms);

    if ($stmtPerms) {

        $stmtPerms->bind_param(
            "i",
            $idTipoUsuarioSSO
        );

        $stmtPerms->execute();

        $resPerms = $stmtPerms->get_result();

        while ($pRow = $resPerms->fetch_assoc()) {

            if (!empty($pRow['clavepermiso'])) {
                $permisosSSO[] = $pRow['clavepermiso'];
            }
        }

        $stmtPerms->close();
    }
}

// =========================================================
// 🔐 GENERAR TOKEN
// =========================================================

$token = bin2hex(random_bytes(32));

$minutosInactividad = 30;

$stmtT = $mysqli->prepare("
    UPDATE usuarios
    SET
        token = ?,
        token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE),
        ultimologin = NOW()
    WHERE idusuario = ?
");

if ($stmtT) {

    $stmtT->bind_param(
        "sii",
        $token,
        $minutosInactividad,
        $user['idusuario']
    );

    $stmtT->execute();

    $stmtT->close();
}

// =========================================================
// ✅ AUDITORÍA LOGIN
// =========================================================

registrarLog(
    $mysqli,
    'login_ok',
    'usuarios',
    $user['idusuario'],
    $user['idusuario']
);

// =========================================================
// 📤 RESPUESTA
// =========================================================

echo json_encode([
    "status" => "ok",

    "token" => $token,

    "usuario" => [
        "idusuario" => $user['idusuario'],
        "username" => $user['username'],
        "email" => $user['email'],
        "nombre" => $user['nombreapellido'],
        "idtiposistema" => (int)$user['idtiposistema'],
        "tipo_sistema" => $user['tipo_sistema'],
        "idtipousuario" => $idTipoUsuarioSSO,
        "super_admin" => $esRoot
    ],

    "empresa" => $empresaData,

    "aplicaciones" => $aplicaciones,

    "empresas" => $empresas,

    "permisos" => $permisosSSO
]);