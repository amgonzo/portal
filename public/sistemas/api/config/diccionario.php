<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================================
// DICCIONARIO DE LA EMPRESA ACTUAL
// ============================================================

// 1. Cargar rutas
// 1. Cargar rutas y comprobar ubicación real
$rutaRutas = __DIR__ . '/rutas.php';

if (!file_exists($rutaRutas)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'status' => 'error',
        'archivo_ejecutado' => __FILE__,
        'directorio_actual' => __DIR__,
        'ruta_rutas_buscada' => $rutaRutas
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$rutas = require $rutaRutas;
// 2. Cargar Composer
require_once $rutas['autoload'];

// 3. Cargar .env de la API
try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos

}

// 4. Cargar conexión, middleware y contexto
require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');

try {

    // ========================================================
    // AUTENTICAR USUARIO
    // ========================================================

    $userAuth = validarTokenAPI($mysqli ?? null);

    // ========================================================
    // OBTENER EMPRESA ACTUAL
    // ========================================================

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa) {

        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'empresa_no_encontrada'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // ========================================================
    // CONECTAR A LA BASE DE DATOS DE LA EMPRESA
    // ========================================================

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // ========================================================
    // CARGAR DICCIONARIO
    // ========================================================

    $diccionario = [];

    $sql = "
        SELECT
            clave,
            valor
        FROM diccionario
        WHERE activo = 1
        ORDER BY clave
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new Exception(
            'No se pudo consultar el diccionario'
        );
    }

    while ($fila = $resultado->fetch_assoc()) {

        $diccionario[$fila['clave']] = $fila['valor'];
    }

    // ========================================================
    // RESPUESTA
    // ========================================================

    echo json_encode([
        'status' => 'ok',
        'idempresa' => (int)$empresa['idempresa'],
        'diccionario' => $diccionario
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}