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

// api/ctacte/obtener_compras_filtradas.php
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

$mysqli = conectarDB('CTACTE_');

try {
    $query = "SELECT valor FROM configuracion WHERE clave = 'ultima_sincronizacion_cajas'";
    $result = $mysqli->query($query);
    
    if ($result && $row = $result->fetch_assoc()) {
        $fecha_raw = $row['valor'];
        // Le damos un formato amigable d/m/Y H:i
        $fecha_formateada = date('d/m/Y H:i', strtotime($fecha_raw)) . ' hs';
        
        echo json_encode([
            "status" => "ok",
            "fecha_raw" => $fecha_raw,
            "fecha_formateada" => $fecha_formateada
        ]);
    } else {
        echo json_encode([
            "status" => "ok",
            "fecha_raw" => null,
            "fecha_formateada" => "Nunca sincronizado"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg" => "Error al obtener la fecha: " . $e->getMessage()
    ]);
}

$mysqli->close();