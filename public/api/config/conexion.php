<?php
// conexion.php simplificado (sin recargar vendor ni dotenv porque ya vienen cargados)

function conectarDB($prefijo = '') {
    // Lee las variables de entorno que ya cargó el index o login previamente
    $host = $_ENV[$prefijo . 'DB_HOST'] ?? $_ENV['DB_HOST'] ?? 'localhost';
    $db   = $_ENV[$prefijo . 'DB_NAME'] ?? $_ENV['DB_NAME'] ?? '';
    $user = $_ENV[$prefijo . 'DB_USER'] ?? $_ENV['DB_USER'] ?? '';
    $pass = $_ENV[$prefijo . 'DB_PASS'] ?? $_ENV['DB_PASS'] ?? '';

    $mysqli = new mysqli($host, $user, $pass, $db);

    if ($mysqli->connect_error) {
        http_response_code(500);
        die(json_encode(["status" => "error", "msg" => "Error DB: " . $mysqli->connect_error]));
    }

    $mysqli->set_charset("utf8mb4");
    $mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysqli->query("SET time_zone = '-03:00';");
    
    return $mysqli;
}

// Por defecto la conexión general (SSO)
$mysqli = conectarDB(''); 
date_default_timezone_set('America/Argentina/Buenos_Aires');
?>