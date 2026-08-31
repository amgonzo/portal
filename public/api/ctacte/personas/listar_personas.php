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

header('Content-Type: application/json');

// 2. Validar token y permisos (usa la conexión al SSO, lo cual es correcto)
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 3. SOBRESCRIBIR $mysqli conectándolo a la base de datos de CTACTE_ para las consultas del módulo
$mysqli = conectarDB('CTACTE_');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

try {
    // Consultamos solo los campos necesarios de las personas activas
    $sql = "SELECT dni, nombre, apellido 
            FROM personas 
            WHERE activo = 1 
            ORDER BY apellido ASC, nombre ASC";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception($mysqli->error);
    }

    $personas = [];
    while ($row = $res->fetch_assoc()) {
        $personas[] = [
            'dni' => $row['dni'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido']
        ];
    }

    echo json_encode([
        "status" => "ok",
        "data" => $personas
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg" => "Error al obtener la lista de personas: " . $e->getMessage()
    ]);
}