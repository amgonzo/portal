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
require_once $rutas['auditoria'];
// api/configuracion/guardar_configuracion.php
header('Content-Type: application/json');


// 2. Validar token y permisos (usa la conexión al SSO, lo cual es correcto)
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 3. SOBRESCRIBIR $mysqli conectándolo a la base de datos de CTACTE_ para las consultas del módulo
$mysqli = conectarDB('CTACTE_');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    $porcentaje = isset($_POST['porcentaje_descuento_default']) ? floatval($_POST['porcentaje_descuento_default']) : null;

    if ($porcentaje === null || $porcentaje < 0 || $porcentaje > 100) {
        throw new Exception("El porcentaje de descuento debe ser un valor válido entre 0 y 100.");
    }

    // Guardar / Actualizar clave porcentaje_descuento_default
    $stmt = $mysqli->prepare("
        INSERT INTO configuracion (clave, valor) 
        VALUES ('porcentaje_descuento_default', ?) 
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ");
    
    $val_str = (string)$porcentaje;
    $stmt->bind_param("s", $val_str);
    $stmt->execute();
    $stmt->close();

    // Auditoría opcional
    if (function_exists('registrarLog')) {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        registrarLog($mysqli, 'guardar_configuracion', 'configuracion', 'global', $idUsuarioLog, null, [
            'porcentaje_descuento_default' => $porcentaje
        ]);
    }

    echo json_encode(["status" => "ok", "msg" => "Configuración guardada correctamente."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();