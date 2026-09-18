<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);

    exit;
}

$userAuth = validarTokenAPI($mysqli ?? null);

validarPermisoEndpoint($mysqli, $userAuth);

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

$mysqli = conectarBase($empresa['db_nombre']);

try {

    $sql = "
        SELECT
            iddiccionario,
            clave,
            valor,
            descripcion,
            activo
        FROM diccionario
        ORDER BY clave ASC
    ";

    $res = $mysqli->query($sql);

    if (!$res) {
        throw new Exception($mysqli->error);
    }

    $registros = [];

    while ($fila = $res->fetch_assoc()) {

        $registros[] = [
            'iddiccionario' => intval($fila['iddiccionario']),
            'clave'         => $fila['clave'],
            'valor'         => $fila['valor'],
            'descripcion'   => $fila['descripcion'],
            'activo'        => intval($fila['activo'])
        ];
    }

    echo json_encode([
        "status" => "ok",
        "data"   => $registros
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => "Error al obtener el diccionario: " . $e->getMessage()
    ]);

} finally {

    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
}