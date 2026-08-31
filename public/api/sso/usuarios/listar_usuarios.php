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
//require_once $rutas['auditoria'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli);

// 2. Validar si el rol tiene permiso para este endpoint y método
validarPermisoEndpoint($mysqli, $userAuth);

// 3. Lógica principal del listado
$id_auditor = 99; 
$esAuditor = ($userAuth['idtipousuario'] == $id_auditor);

// Si deseas aplicar algún filtro adicional para el auditor o roles, lo puedes colocar aquí.

$sql = "SELECT 
            u.idusuario,
            u.nombreapellido,
            u.username,
            u.email,
            u.baja,
            ura.idtipousuario,
            ura.idaplicacion,
            tu.descripcion AS rolnombre,
            a.nombre AS nombre_app
        FROM usuarios u
        LEFT JOIN usuarios_roles_apps ura ON u.idusuario = ura.idusuario
        LEFT JOIN tiposusuario tu ON ura.idtipousuario = tu.idtipousuario
        LEFT JOIN aplicaciones a ON ura.idaplicacion = a.idaplicacion
        ORDER BY u.nombreapellido ASC";

$res = $mysqli->query($sql);

if ($res) {
    $usuariosMap = [];

    while ($fila = $res->fetch_assoc()) {
        $idUser = intval($fila['idusuario']);

        if (!isset($usuariosMap[$idUser])) {
            $usuariosMap[$idUser] = [
                'idusuario' => $idUser,
                'nombreapellido' => $fila['nombreapellido'],
                'username' => $fila['username'],
                'email' => $fila['email'],
                'baja' => intval($fila['baja']),
                'accesos' => []
            ];
        }

        if ($fila['idaplicacion'] !== null) {
            $usuariosMap[$idUser]['accesos'][] = [
                'idaplicacion' => intval($fila['idaplicacion']),
                'idtipousuario' => intval($fila['idtipousuario']),
                'nombre_app' => $fila['nombre_app'],
                'rolnombre' => $fila['rolnombre']
            ];
        }
    }

    echo json_encode([
        "status" => "ok",
        "data" => array_values($usuariosMap)
    ]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => "error_db: " . $mysqli->error]);
}

$mysqli->close();