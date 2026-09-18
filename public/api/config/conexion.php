<?php
// conexion.php actualizado para soportar conexión general (SSO) o dinámica por empresa

function conectarDB($dbNombreOPre = '') {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    $db   = '';

    if (!empty($dbNombreOPre)) {
        // Si el parámetro coincide con un prefijo de entorno (ej: 'OTRA_')
        if (isset($_ENV[$dbNombreOPre . 'DB_NAME'])) {
            $host = $_ENV[$dbNombreOPre . 'DB_HOST'] ?? $host;
            $db   = $_ENV[$dbNombreOPre . 'DB_NAME'];
            $user = $_ENV[$dbNombreOPre . 'DB_USER'] ?? $user;
            $pass = $_ENV[$dbNombreOPre . 'DB_PASS'] ?? $pass;
        } else {
            // Si no es un prefijo, asumimos que es el nombre directo de la base de datos (ej: 'super_3_ctacte')
            $db = $dbNombreOPre;
        }
    } else {
        // Por defecto usa las credenciales principales del .env (SSO)
        $db = $_ENV['DB_NAME'] ?? '';
    }

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

// Por defecto la conexión general del SSO al incluir este archivo
$mysqli = conectarDB(''); 
date_default_timezone_set('America/Argentina/Buenos_Aires');
?>