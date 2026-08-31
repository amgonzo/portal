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

try {
    $incluir_todos = isset($_GET['todos']) && $_GET['todos'] == '1';

    $sql = "
        SELECT dni, apellido, nombre, activo 
        FROM personas 
        WHERE dni != '0' 
          AND dni != 'SIN_USUARIO'
    ";

    // Si no piden todos, filtramos solo activos
    if (!$incluir_todos) {
        $sql .= " AND activo = 1";
    }

    $sql .= " ORDER BY apellido ASC, nombre ASC";

    $res = $mysqli->query($sql);
    $personas = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $personas[] = $row;
        }
    }

    echo json_encode($personas);

} catch (Exception $e) {
    echo json_encode([]);
}

$mysqli->close();