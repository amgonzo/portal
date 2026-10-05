<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable(
        $rutas['env_api']
    );
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método no permitido.');
    }

    /*
     * =========================================================
     * AUTENTICACIÓN Y PERMISOS
     * =========================================================
     */

    $userAuth = validarTokenAPI($mysqli);

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    /*
     * =========================================================
     * CONFIGURACIÓN DEL SISTEMA
     *
     * Esta tabla pertenece a portal_sso.
     * NO conectamos a la BD de la empresa.
     * =========================================================
     */

    $stmt = $mysqli->prepare("
        SELECT
            idconfiguracion,
            clave,
            valor,
            descripcion,
            tipo,
            fecha_actualizacion
        FROM configuracion_sistema
        ORDER BY clave ASC
    ");

    if (!$stmt) {
        throw new Exception(
            'Error al preparar consulta: ' .
            $mysqli->error
        );
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error al consultar configuraciones: ' .
            $error
        );
    }

    $resultado = $stmt->get_result();

    $configuraciones = [];

    while ($fila = $resultado->fetch_assoc()) {

        $configuraciones[] = [
            'idconfiguracion' => (int)$fila['idconfiguracion'],
            'clave' => $fila['clave'],
            'valor' => $fila['valor'],
            'descripcion' => $fila['descripcion'],
            'tipo' => $fila['tipo'],
            'fecha_actualizacion' =>
                $fila['fecha_actualizacion']
        ];
    }

    $stmt->close();


    echo json_encode([
        'status' => 'ok',
        'data' => $configuraciones
    ], JSON_UNESCAPED_UNICODE);


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}