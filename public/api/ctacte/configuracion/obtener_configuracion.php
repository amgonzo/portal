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

// api/configuracion/obtener_configuracion.php
header('Content-Type: application/json');

// 2. Validar token y permisos (usa la conexión al SSO, lo cual es correcto)
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 3. SOBRESCRIBIR $mysqli conectándolo a la base de datos de CTACTE_ para las consultas del módulo
$mysqli = conectarDB('CTACTE_');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Método no permitido.");
    }

    // Traemos todas las configuraciones clave-valor de la tabla
    $result = $mysqli->query("SELECT clave, valor FROM configuracion");
    
    $config = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config[$row['clave']] = $row['valor'];
        }
    }

    // Formateamos la fecha de sincronización
    $ultima_sincro = !empty($config['ultima_sincronizacion_cajas']) 
        ? date('d/m/Y H:i', strtotime($config['ultima_sincronizacion_cajas'])) . ' hs' 
        : 'Nunca';

    echo json_encode([
        "status" => "ok",
        "data" => [
            "ultima_sincronizacion" => $ultima_sincro,
            "porcentaje_descuento_default" => $config['porcentaje_descuento_default'] ?? '30'
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();