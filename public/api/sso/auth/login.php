<?php

// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer
require_once $rutas['autoload'];

try {
    // 3. Cargamos .env
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

// ===============================
// 📥 DATOS
// ===============================

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$u = trim($input['username'] ?? '');
$p = trim($input['password'] ?? '');
$empresaSlug = trim($input['empresa'] ?? '');

// ===============================
// 🔴 VALIDAR DATOS
// ===============================

if (empty($u) || empty($p)) {

    registrarLog(
        $mysqli,
        'login_fallido_datos_incompletos',
        'usuarios',
        null,
        null,
        ['user_intentado' => $u]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "datos"
    ]);

    exit;
}

// ===============================
// 🔐 BUSCAR USUARIO
// ===============================

$sql = "
    SELECT
        u.idusuario,
        u.nombreapellido,
        u.password,
        u.baja
    FROM usuarios u
    WHERE u.username = ?
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

$stmt->bind_param("s", $u);
$stmt->execute();

$res = $stmt->get_result();

$user = $res->fetch_assoc();

$stmt->close();

// ===============================
// ❌ USUARIO NO EXISTE
// ===============================

if (!$user) {

    echo json_encode([
        "status" => "error",
        "msg" => "usuario"
    ]);

    exit;
}

// ===============================
// ❌ USUARIO DADO DE BAJA
// ===============================

if ((int)$user['baja'] === 1) {

    registrarLog(
        $mysqli,
        'login_bloqueado_baja',
        'usuarios',
        $user['idusuario'],
        null,
        ['user' => $u]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "baja"
    ]);

    exit;
}

// ===============================
// ❌ PASSWORD INCORRECTA
// ===============================

if (!password_verify($p, $user['password'])) {

    registrarLog(
        $mysqli,
        'login_fallido_pass',
        'usuarios',
        $user['idusuario'],
        null,
        ['user' => $u]
    );

    echo json_encode([
        "status" => "error",
        "msg" => "password"
    ]);

    exit;
}

// ===============================
// 👑 DETECTAR SUPER_ADMIN
// ===============================

$sqlSuperAdmin = "
    SELECT 1
    FROM usuarios_roles_apps ura
    INNER JOIN tiposusuario tu
        ON tu.idtipousuario = ura.idtipousuario
    WHERE ura.idusuario = ?
      AND UPPER(TRIM(tu.clave)) = 'SUPER_ADMIN'
    LIMIT 1
";

$stmtSuperAdmin = $mysqli->prepare($sqlSuperAdmin);

$esSuperAdmin = false;

if ($stmtSuperAdmin) {

    $stmtSuperAdmin->bind_param(
        "i",
        $user['idusuario']
    );

    $stmtSuperAdmin->execute();

    $resSuperAdmin = $stmtSuperAdmin->get_result();

    $esSuperAdmin = $resSuperAdmin->num_rows > 0;

    $stmtSuperAdmin->close();
}

// ===============================
// 🏢 OBTENER EMPRESAS DEL USUARIO
// ===============================
//
// La empresa enviada por el formulario es OPCIONAL.
//
// - Si viene una empresa:
//      se valida que el usuario tenga acceso.
// - Si no viene:
//      se buscan las empresas asignadas.
//      1 empresa  -> se selecciona automáticamente.
//      varias     -> se devuelven para selector.
//      0          -> error.
//
// SUPER_ADMIN:
//      puede entrar sin empresa.
//

$empresaData = null;
$idEmpresa = null;


// ---------------------------------------------------------
// 1. Obtener empresas asignadas al usuario
// ---------------------------------------------------------

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

$empresas = [];

while ($emp = $resEmp->fetch_assoc()) {

    $empresas[] = $emp;
}

$stmtEmp->close();


// ---------------------------------------------------------
// 2. SUPER_ADMIN
// ---------------------------------------------------------

if ($esSuperAdmin) {

    /*
     * Si SUPER_ADMIN indicó una empresa,
     * la validamos.
     *
     * Si no indicó ninguna, puede continuar
     * sin empresa.
     */

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


// ---------------------------------------------------------
// 3. USUARIO NORMAL
// ---------------------------------------------------------

else {

    // ---------------------------------------------
    // No tiene empresas
    // ---------------------------------------------

    if (empty($empresas)) {

        echo json_encode([
            "status" => "error",
            "msg" => "sin_empresas"
        ]);

        exit;
    }


    // ---------------------------------------------
    // Se indicó empresa explícitamente
    // ---------------------------------------------

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


    // ---------------------------------------------
    // No se indicó empresa
    // ---------------------------------------------

    else {

        // Una sola empresa:
        // entra directamente.

        if (count($empresas) === 1) {

            $empresaData = $empresas[0];

            $idEmpresa = (int)$empresaData['idempresa'];

        }

        // Varias empresas:
        // NO entra todavía.
        // El frontend deberá mostrar selector.

        else {

            echo json_encode([
                "status" => "ok",
                "seleccionar_empresa" => true,
                "token" => null,
                "usuario" => [
                    "idusuario" => $user['idusuario'],
                    "nombre" => $user['nombreapellido'],
                    "super_admin" => false
                ],
                "empresas" => $empresas
            ]);

            exit;
        }
    }
}

// ===============================
// 🚀 OBTENER APLICACIONES ASIGNADAS
// ===============================

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

$aplicaciones = [];

while ($app = $resApps->fetch_assoc()) {
    $aplicaciones[] = $app;
}

$stmtApps->close();

// ❌ Sin aplicaciones
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

// ===============================
// 🛡 ROL Y PERMISOS SSO
// ===============================

$idTipoUsuarioSSO = 0;

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

// ===============================
// 🔑 PERMISOS SSO
// ===============================

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

// ===============================
// 🔐 GENERAR TOKEN
// ===============================

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

// ===============================
// ✅ AUDITORÍA LOGIN
// ===============================

registrarLog(
    $mysqli,
    'login_ok',
    'usuarios',
    $user['idusuario'],
    $user['idusuario']
);

// ===============================
// 📤 RESPUESTA
// ===============================

echo json_encode([
    "status" => "ok",

    "token" => $token,

    "usuario" => [
        "idusuario" => $user['idusuario'],
        "nombre" => $user['nombreapellido'],
        "idtipousuario" => $idTipoUsuarioSSO,
        "super_admin" => $esSuperAdmin
    ],

    "empresa" => $empresaData,

    "aplicaciones" => $aplicaciones,

    "empresas" => $empresas,

    "permisos" => $permisosSSO
]);