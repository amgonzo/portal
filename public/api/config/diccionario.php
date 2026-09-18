<?php

// ============================================================
// DICCIONARIO DE LA EMPRESA ACTUAL
// ============================================================

// 1. Cargar rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

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

    $mysqli = conectarBase($empresa['db_nombre']);

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