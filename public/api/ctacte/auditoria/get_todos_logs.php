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
require_once $rutas['middleware'];
require_once $rutas['auditoria_core'];
require_once $rutas['contexto'];
header('Content-Type: application/json');

// 1. Guardamos la conexión original de SSO para la autenticación y permisos
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$empresa = obtenerEmpresaActual($mysqli, $userAuth);
$mysqli = conectarBase($empresa['db_nombre']);


// Ejecutamos la consulta pasándole la conexión de ctacte
echo json_encode(ejecutarGetTodosLogs($mysqli));
/*<?php
 

require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../sso/auth/auth_middleware.php';
require_once __DIR__ . '/../../utils/auditoria_core.php';

header('Content-Type: application/json');

// 1. Validar token primero de forma segura
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Conectar a la base de datos de CTACTE_ ANTES de usarla
$empresa = obtenerEmpresaActual($mysqli, $userAuth);
$mysqli = conectarBase($empresa['db_nombre']); 

// 3. Validar permisos si corresponde y ejecutar
validarPermisoEndpoint($mysqli, $userAuth);

echo json_encode(ejecutarGetTodosLogs($mysqli));*/