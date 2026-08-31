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

// api/ctacte/obtener_limites.php
header('Content-Type: application/json');


// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$mysqli = conectarDB('CTACTE_');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$mes = (!empty($_REQUEST['mes'])) ? (int)$_REQUEST['mes'] : (int)date('n');
$anio = (!empty($_REQUEST['anio'])) ? (int)$_REQUEST['anio'] : (int)date('Y');

try {
    $p0 = sprintf('%04d-%02d', $anio, $mes);

    $dt0 = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $anio, $mes));
    
    $dt1 = (clone $dt0)->modify('-1 month');
    $p1 = $dt1->format('Y-m');

    $dt2 = (clone $dt0)->modify('-2 month');
    $p2 = $dt2->format('Y-m');

    $query_status = "SELECT 1 FROM empleados_limites WHERE periodo_codigo = ? AND cerrado = 1 LIMIT 1";
    $stmt_status = $mysqli->prepare($query_status);
    $stmt_status->bind_param("s", $p0);
    $stmt_status->execute();
    $stmt_status->store_result();
    $is_cerrado = ($stmt_status->num_rows > 0);
    $stmt_status->close();

    $query = "
        SELECT 
            p.dni, 
            CONCAT(p.apellido, ', ', p.nombre) AS nombre,
            p.origen,
            c.nombre AS categoria_nombre,
            COALESCE(el0.limite_mensual, c.limite_mensual) AS limite_mensual,
            COALESCE(el0.consumido_mes_actual, 0.00) AS consumido_mes_actual,
            COALESCE(el1.consumido_mes_actual, 0.00) AS consumido_mes_m1,
            COALESCE(el2.consumido_mes_actual, 0.00) AS consumido_mes_m2,
            COALESCE(el0.activo, p.activo) AS activo
        FROM personas p
        INNER JOIN categorias c ON p.idcategoria = c.idcategoria
        LEFT JOIN empleados_limites el0 
            ON CAST(p.dni AS CHAR) = CAST(el0.dni AS CHAR)
            AND el0.periodo_codigo = ?
        LEFT JOIN empleados_limites el1 
            ON CAST(p.dni AS CHAR) = CAST(el1.dni AS CHAR)
            AND el1.periodo_codigo = ?
        LEFT JOIN empleados_limites el2 
            ON CAST(p.dni AS CHAR) = CAST(el2.dni AS CHAR)
            AND el2.periodo_codigo = ?
        WHERE (p.activo = 1 OR el0.dni IS NOT NULL)
        AND CAST(p.dni AS CHAR) != '0'
        AND CAST(p.dni AS CHAR) != 'SIN_USUARIO'
        ORDER BY p.apellido ASC, p.nombre ASC
    ";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("sss", $p0, $p1, $p2); 
    $stmt->execute();
    $res = $stmt->get_result();

    $datos = [];
    while ($row = $res->fetch_assoc()) {
        $limite = (float)$row['limite_mensual'];
        $c0 = (float)$row['consumido_mes_actual'];
        $c1 = (float)$row['consumido_mes_m1'];
        $c2 = (float)$row['consumido_mes_m2'];

        $row['limite_mensual'] = $limite;
        $row['consumido_mes_actual'] = $c0;
        $row['saldo_disponible'] = max(0, $limite - $c0);
        $row['historico_consumos'] = [$c2, $c1, $c0];

        unset($row['consumido_mes_m1'], $row['consumido_mes_m2']);

        $datos[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "status" => "ok", 
        "is_cerrado" => $is_cerrado, 
        "data" => $datos
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "msg" => "Error en el servidor: " . $e->getMessage()
    ]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}