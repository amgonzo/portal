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

header('Content-Type: application/json');

// 1. Validar token y permisos con la conexión de SSO (donde existen los usuarios y roles)
$mysqli_sso = $mysqli ?? conectarDB('SSO'); 
$userAuth = validarTokenAPI($mysqli_sso);
validarPermisoEndpoint($mysqli_sso, $userAuth);

// 2. Conectar a la base de datos de CTACTE_ para buscar el registro auditado
$mysqli_ctacte = conectarDB('CTACTE_'); 

// 3. Ejecutar la búsqueda del registro
echo json_encode(ejecutarGetRegistroCtacte($mysqli_ctacte));

/*
<?php
 

require_once __DIR__ . '/../../../cors.php';
require_once __DIR__ . '/../../../config/conexion.php';
require_once __DIR__ . '/../../sso/auth/auth_middleware.php';
require_once __DIR__ . '/../../utils/auditoria_core.php';

header('Content-Type: application/json');

// 1. Validar token primero de forma segura
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Conectar a la base de datos de CTACTE_ ANTES de usarla
$mysqli = conectarDB('CTACTE_'); 

// 3. Validar permisos si corresponde y ejecutar
validarPermisoEndpoint($mysqli, $userAuth);

echo json_encode(ejecutarGetRegistro($mysqli));*/