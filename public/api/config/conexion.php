<?php

// =========================================================
// conexion.php
// Conexión general SSO + conexiones dinámicas por empresa
// =========================================================


/* =========================================================
   RUTAS CENTRALES Y SECRETOS
   ========================================================= */

$rutasArchivo = $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

if (!file_exists($rutasArchivo)) {
    throw new RuntimeException(
        'No se encontró el archivo de rutas: ' . $rutasArchivo
    );
}

$rutas = require $rutasArchivo;

if (!isset($rutas['secretos']) || !file_exists($rutas['secretos'])) {
    throw new RuntimeException(
        'La ruta del archivo de secretos no está definida o no existe.'
    );
}

$secretos = require $rutas['secretos'];

if (!is_array($secretos) || empty($secretos['DB_ENCRYPTION_KEY'])) {
    throw new RuntimeException(
        'No está configurada DB_ENCRYPTION_KEY.'
    );
}


/* =========================================================
   CONEXIÓN GENERAL DEL SISTEMA SSO
   ========================================================= */

/**
 * Conexión general del sistema SSO.
 *
 * Usa exclusivamente las credenciales centrales del .env:
 *
 * DB_HOST
 * DB_NAME
 * DB_USER
 * DB_PASS
 */
function conectarDB(string $dbNombreOPre = ''): mysqli
{
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    $db   = '';

    if (!empty($dbNombreOPre)) {

        // Compatibilidad con el sistema anterior:
        // si se pasa un prefijo de entorno, por ejemplo:
        // CTACTE_
        if (isset($_ENV[$dbNombreOPre . 'DB_NAME'])) {

            $host = $_ENV[$dbNombreOPre . 'DB_HOST'] ?? $host;
            $db   = $_ENV[$dbNombreOPre . 'DB_NAME'];
            $user = $_ENV[$dbNombreOPre . 'DB_USER'] ?? $user;
            $pass = $_ENV[$dbNombreOPre . 'DB_PASS'] ?? $pass;

        } else {

            // Si no es un prefijo, se interpreta
            // directamente como nombre de base.
            $db = $dbNombreOPre;
        }

    } else {

        // Conexión principal SSO
        $db = $_ENV['DB_NAME'] ?? '';
    }

    $mysqli = new mysqli(
        $host,
        $user,
        $pass,
        $db
    );

    if ($mysqli->connect_error) {

        http_response_code(500);

        die(json_encode([
            "status" => "error",
            "msg"    => "Error DB: " . $mysqli->connect_error
        ]));
    }

    configurarConexionDB($mysqli);

    return $mysqli;
}


/* =========================================================
   CONFIGURACIÓN COMÚN DE CONEXIONES
   ========================================================= */

/**
 * Configuración común de cualquier conexión.
 */
function configurarConexionDB(mysqli $mysqli): void
{
    $mysqli->set_charset("utf8mb4");

    $mysqli->query(
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $mysqli->query(
        "SET time_zone = '-03:00'"
    );
}


/* =========================================================
   CONEXIÓN A BD DE EMPRESA
   ========================================================= */

/**
 * Conecta a una base de datos perteneciente a una empresa.
 *
 * La información se obtiene de:
 *
 * empresas_conexiones
 *
 * La contraseña almacenada allí está cifrada.
 *
 * $mysqliSso
 *     Conexión a portal_sso.
 *
 * $idEmpresa
 *     Empresa a la que queremos conectarnos.
 *
 * $claveConexion
 *     Clave lógica de la conexión.
 *     Ejemplos: DATOS, MS3, IA.
 */
function conectarDBEmpresa(
    mysqli $mysqliSso,
    int $idEmpresa,
    string $claveConexion
): mysqli {

    // Permite utilizar los secretos cargados al inicio
    // de este archivo.
    global $secretos;

    require_once __DIR__ . '/../utils/db_crypto.php';

    $sql = "
        SELECT
            idempresa_conexion,
            nombre,
            clave,
            tipo,
            host,
            puerto,
            db_nombre,
            db_usuario,
            db_password
        FROM empresas_conexiones
        WHERE idempresa = ?
          AND clave = ?
          AND activo = 1
        LIMIT 1
    ";

    $stmt = $mysqliSso->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Error preparando consulta de conexión de empresa: '
            . $mysqliSso->error
        );
    }

    $stmt->bind_param(
        'is',
        $idEmpresa,
        $claveConexion
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        throw new RuntimeException(
            'Error consultando conexión de empresa: ' . $error
        );
    }

    $resultado = $stmt->get_result();

    $conexion = $resultado->fetch_assoc();

    $stmt->close();

    if (!$conexion) {

        throw new RuntimeException(
            "No existe una conexión activa con clave "
            . "'{$claveConexion}' para la empresa ID {$idEmpresa}."
        );
    }

    // Por ahora soportamos MySQL/MariaDB mediante mysqli.
    if (strtoupper(trim($conexion['tipo'])) !== 'MYSQL') {

        throw new RuntimeException(
            "Tipo de conexión no soportado: "
            . $conexion['tipo']
        );
    }

    // =====================================================
    // DESCIFRAR CONTRASEÑA
    // =====================================================

    $password = descifrarPasswordBD(
        $conexion['db_password'],
        $secretos
    );

    // =====================================================
    // PUERTO
    // =====================================================

    $puerto = (int) $conexion['puerto'];

    if ($puerto <= 0) {
        $puerto = 3306;
    }

    // =====================================================
    // CONECTAR A BD DE LA EMPRESA
    // =====================================================

    $mysqliEmpresa = new mysqli(
        $conexion['host'],
        $conexion['db_usuario'],
        $password,
        $conexion['db_nombre'],
        $puerto
    );

    if ($mysqliEmpresa->connect_error) {

        throw new RuntimeException(
            "Error conectando a la BD de la empresa "
            . "{$idEmpresa} / {$claveConexion}: "
            . $mysqliEmpresa->connect_error
        );
    }

    configurarConexionDB($mysqliEmpresa);

    return $mysqliEmpresa;
}


/* =========================================================
   CONEXIÓN GENERAL SSO
   ========================================================= */

$mysqli = conectarDB('');


/* =========================================================
   ZONA HORARIA
   ========================================================= */

date_default_timezone_set(
    'America/Argentina/Buenos_Aires'
);