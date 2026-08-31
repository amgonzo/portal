<?php
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];


try {
    // 3. Cargamos el .env usando la ruta definida en rutas.php
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

// ===============================
// 📥 DATOS (Soporta POST o JSON)
// ===============================
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$u = trim($input['username'] ?? '');
$p = trim($input['password'] ?? '');

// 🔴 Validar datos
if (empty($u) || empty($p)) {
    registrarLog($mysqli, 'login_fallido_datos_incompletos', 'usuarios', null, null, ['user_intentado' => $u]);
    echo json_encode([
        "status" => "error",
        "msg" => "datos"
    ]);
    exit;
}

// ===============================
// 🔐 LOGIN (Búsqueda de Usuario Central)
// ===============================
$sql = "SELECT idusuario, nombreapellido, password, baja 
        FROM usuarios 
        WHERE username = ? 
        LIMIT 1";

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

// ❌ Usuario no existe
if (!$user = $res->fetch_assoc()) {
    echo json_encode([
        "status" => "error",
        "msg" => "usuario"
    ]);
    exit;
}

// ❌ Usuario dado de baja
if ((int)$user['baja'] === 1) {
    registrarLog($mysqli, 'login_bloqueado_baja', 'usuarios', $user['idusuario'], null, ['user' => $u]);
    echo json_encode([
        "status" => "error",
        "msg" => "baja"
    ]);
    exit;
}

// ❌ Password incorrecta
if (!password_verify($p, $user['password'])) {
    registrarLog($mysqli, 'login_fallido_pass', 'usuarios', $user['idusuario'], null, ['user' => $u]);
    echo json_encode([
        "status" => "error",
        "msg" => "password"
    ]);
    exit;
}

// ===============================
// 🚀 OBTENER APLICACIONES ASIGNADAS
// ===============================
$sqlApps = "SELECT DISTINCT a.idaplicacion, a.nombre, a.slug, a.url_base 
            FROM aplicaciones a 
            INNER JOIN usuarios_roles_apps ura ON a.idaplicacion = ura.idaplicacion 
            WHERE ura.idusuario = ? 
              AND a.activo = 1";
              //AND a.slug != 'sso_central'";

$stmtApps = $mysqli->prepare($sqlApps);

if (!$stmtApps) {
    echo json_encode([
        "status" => "error",
        "msg" => "sql_error"
    ]);
    exit;
}

$stmtApps->bind_param("i", $user['idusuario']);
$stmtApps->execute();
$resApps = $stmtApps->get_result();

$aplicaciones = [];
while ($app = $resApps->fetch_assoc()) {
    $aplicaciones[] = $app;
}

// ❌ El usuario no tiene ninguna aplicación asignada
if (empty($aplicaciones)) {
    registrarLog($mysqli, 'login_sin_permiso_app', 'usuarios_roles_apps', null, $user['idusuario']);
    echo json_encode([
        "status" => "error",
        "msg" => "sin_acceso_app"
    ]);
    exit;
}

// ===============================
// 🛡 ROL Y PERMISOS ESPECÍFICOS PARA EL SSO CENTRAL
// ===============================
$idTipoUsuarioSSO = 0;

$sqlSSO = "SELECT ura.idtipousuario 
           FROM usuarios_roles_apps ura
           INNER JOIN aplicaciones a ON ura.idaplicacion = a.idaplicacion
           WHERE ura.idusuario = ? AND (a.slug = 'sso_central' OR a.slug = 'sso' OR a.idaplicacion = 1)
           LIMIT 1";

$stmtSSO = $mysqli->prepare($sqlSSO);
if ($stmtSSO) {
    $stmtSSO->bind_param("i", $user['idusuario']);
    $stmtSSO->execute();
    $resSSO = $stmtSSO->get_result();
    if ($rowSSO = $resSSO->fetch_assoc()) {
        $idTipoUsuarioSSO = (int)$rowSSO['idtipousuario'];
    }
}

$permisosSSO = [];
if ($idTipoUsuarioSSO > 0) {
    $sqlPerms = "SELECT DISTINCT p.clavepermiso 
                 FROM permisos p 
                 INNER JOIN permisos_rol pr ON p.idpermiso = pr.idpermiso 
                 WHERE pr.idtipousuario = ?";
    $stmtPerms = $mysqli->prepare($sqlPerms);
    if ($stmtPerms) {
        $stmtPerms->bind_param("i", $idTipoUsuarioSSO);
        $stmtPerms->execute();
        $resPerms = $stmtPerms->get_result();
        while ($pRow = $resPerms->fetch_assoc()) {
            if (!empty($pRow['clavepermiso'])) {
                $permisosSSO[] = $pRow['clavepermiso'];
            }
        }
    }
}

// ===============================
// 🔐 GENERACIÓN DE TOKEN DE ACCESO (Con Expiración)
// ===============================
$token = bin2hex(random_bytes(32));
$minutosInactividad = 30; // Tiempo de expiración por inactividad

$stmtT = $mysqli->prepare("UPDATE usuarios SET token = ?, token_expira = DATE_ADD(NOW(), INTERVAL ? MINUTE), ultimologin = NOW() WHERE idusuario = ?");

if ($stmtT) {
    $stmtT->bind_param("sii", $token, $minutosInactividad, $user['idusuario']);
    $stmtT->execute();
}

// ✅ AUDITORÍA: LOGIN EXITOSO
registrarLog($mysqli, 'login_ok', 'usuarios', $user['idusuario'], $user['idusuario']);

// ===============================
// 📤 RESPUESTA (Devolvemos Token y datos básicos al Frontend)
// ===============================
echo json_encode([
    "status" => "ok",
    "token" => $token,
    "usuario" => [
        "idusuario"     => $user['idusuario'],
        "nombre"        => $user['nombreapellido'],
        "idtipousuario" => $idTipoUsuarioSSO
    ],
    "aplicaciones" => $aplicaciones,
    "permisos"     => $permisosSSO // Opcional enviarlo acá para cachear de entrada en JS
]);