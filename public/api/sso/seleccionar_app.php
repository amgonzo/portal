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

$input = json_decode(file_get_contents('php://input'), true);
$app_slug = trim($input['app_slug'] ?? '');
$token = trim($input['token'] ?? ''); // 👈 Recibimos el token desde el JS

if (empty($app_slug) || empty($token)) {
    echo json_encode(["status" => "error", "msg" => "datos_incompletos"]);
    exit;
}

// 1. VALIDAR TOKEN: ¿Quién es este usuario realmente?
$stmt = $mysqli->prepare("SELECT idusuario FROM usuarios WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(["status" => "error", "msg" => "sesion_expirada"]);
    exit;
}
$idusuario = $user['idusuario'];

// ... (El resto de tu lógica SQL original sigue igual) ...

// 1. Obtener la App seleccionada
$sqlApp = "SELECT idaplicacion, nombre, url_base FROM aplicaciones WHERE slug = ? AND activo = 1 LIMIT 1";
$stmtApp = $mysqli->prepare($sqlApp);
$stmtApp->bind_param("s", $app_slug);
$stmtApp->execute();
$app = $stmtApp->get_result()->fetch_assoc();

if (!$app) {
    echo json_encode(["status" => "error", "msg" => "app_invalida"]);
    exit;
}

$idaplicacion = (int)$app['idaplicacion'];

// 2. Verificar Rol del usuario en esta App
$sqlRol = "SELECT tu.idtipousuario, tu.descripcion AS rolnombre
           FROM usuarios_roles_apps ura
           INNER JOIN tiposusuario tu ON ura.idtipousuario = tu.idtipousuario
           WHERE ura.idusuario = ? AND ura.idaplicacion = ? LIMIT 1";

$stmtRol = $mysqli->prepare($sqlRol);
$stmtRol->bind_param("ii", $idusuario, $idaplicacion);
$stmtRol->execute();
$rolInfo = $stmtRol->get_result()->fetch_assoc();

if (!$rolInfo) {
    registrarLog($mysqli, 'acceso_denegado_app', 'aplicaciones', $idaplicacion, $idusuario, ['slug' => $app_slug]);
    echo json_encode(["status" => "error", "msg" => "sin_acceso_app"]);
    exit;
}

// 3. Cargar Permisos específicos
$permisos = [];
$sqlP = "SELECT p.clavepermiso 
         FROM permisos p 
         INNER JOIN permisos_rol pr ON p.idpermiso = pr.idpermiso 
         INNER JOIN aplicaciones_permisos ap ON p.idpermiso = ap.idpermiso 
         WHERE pr.idtipousuario = ? AND ap.idaplicacion = ?";

$stmtP = $mysqli->prepare($sqlP);
$stmtP->bind_param("ii", $rolInfo['idtipousuario'], $idaplicacion);
$stmtP->execute();
$resP = $stmtP->get_result();

while ($rowP = $resP->fetch_assoc()) {
    $permisos[] = $rowP['clavepermiso'];
}

// Establecer contexto en sesión
$_SESSION['app_activa'] = $app_slug;
$_SESSION['idaplicacion'] = $idaplicacion;
$_SESSION['idtipousuario'] = $rolInfo['idtipousuario'];
$_SESSION['permisos'] = $permisos;

registrarLog($mysqli, 'ingreso_app', 'aplicaciones', $idaplicacion, $idusuario, ['slug' => $app_slug]);

echo json_encode([
    "status" => "ok",
    "app" => [
        "nombre" => $app['nombre'],
        "url_base" => $app['url_base'],
        "rol" => $rolInfo['rolnombre'],
        "tipo" => $rolInfo['idtipousuario'],
        "permisos" => $permisos
    ]
]);