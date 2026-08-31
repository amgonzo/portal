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
require_once $rutas['auditoria'];
// api/ctacte/obtener_compras_filtradas.php
header('Content-Type: application/json');
// Ajustá si tu auth está en otra subcarpeta (ej. /sso/auth/)

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit;
}

// 1. Validar token y sesión activa
$userAuth = validarTokenAPI($mysqli ?? null);

// 2. Validar permiso por endpoint y método automáticamente
validarPermisoEndpoint($mysqli, $userAuth);

// 3. Luego conectar a la base de datos de ctacte
$mysqli = conectarDB('CTACTE_');

try {
    $periodo  = trim($_GET['periodo'] ?? '');
    $dni      = trim($_GET['dni'] ?? '');
    $ticket   = trim($_GET['ticket'] ?? '');
    $anulados = trim($_GET['anulados'] ?? '0');

    $where = ["1=1"];
    $params = [];
    $types  = "";

    if ($anulados === '0') {
        $where[] = "COALESCE(cc.anulado, 0) = 0";
    } elseif ($anulados === '1') {
        $where[] = "COALESCE(cc.anulado, 0) = 1";
    }

    if (!empty($ticket)) {
        $term = "%" . $ticket . "%";
        $where[] = "(CAST(cc.venta_id AS CHAR) LIKE ? OR CONCAT(LPAD(cc.punto_venta_id, 4, '0'), '-', LPAD(cc.venta_id, 8, '0')) LIKE ?)";
        $params[] = $term;
        $params[] = $term;
        $types .= "ss";
    }

    if (!empty($periodo)) {
        $where[] = "DATE_FORMAT(cc.fecha_compra, '%Y-%m') = ?";
        $params[] = $periodo;
        $types .= "s";
    }

    if ($dni === '0' || $dni === 'sin_usuario') {
        $where[] = "(cc.dni_empleado = '0' OR cc.dni_empleado = 'SIN_USUARIO' OR cc.dni_empleado IS NULL OR cc.dni_empleado = '')";
    } elseif (!empty($dni) && $dni !== 'todos') {
        $where[] = "TRIM(cc.dni_empleado) = ?";
        $params[] = $dni;
        $types .= "s";
    }

    $whereSql = implode(" AND ", $where);

    $sql = "
        SELECT 
            cc.punto_venta_id,
            cc.venta_id,
            cc.fecha_compra,
            cc.importe_total,
            cc.dni_empleado,
            COALESCE(cc.anulado, 0) AS anulado,
            COALESCE(cc.motivo_anulacion, '') AS motivo_anulacion,
            COALESCE(el.cerrado, 0) AS es_cerrado,
            COALESCE(NULLIF(CONCAT(p.apellido, ', ', p.nombre), ', '), 'SIN ASIGNAR') AS titular_actual,
            COALESCE(
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        CASE 
                            WHEN cd.cantidad = FLOOR(cd.cantidad) 
                                THEN CONCAT(CAST(cd.cantidad AS SIGNED), ' U')
                            ELSE CONCAT(TRIM(TRAILING '0' FROM ROUND(cd.cantidad, 3)), ' kg')
                        END,
                        ' - ', 
                        cd.descripcion
                    ) SEPARATOR ', '
                ), 
                'Sin detalles'
            ) AS detalles_resumen
        FROM compras_cabecera cc
        LEFT JOIN personas p 
            ON CAST(cc.dni_empleado AS CHAR) = CAST(p.dni AS CHAR) 
           AND cc.dni_empleado NOT IN ('0', '', 'SIN_USUARIO')
        LEFT JOIN empleados_limites el 
            ON el.periodo_codigo = DATE_FORMAT(cc.fecha_compra, '%Y-%m')
        LEFT JOIN compras_detalles cd 
            ON cc.punto_venta_id = cd.punto_venta_id 
           AND cc.venta_id = cd.venta_id
        WHERE $whereSql
        GROUP BY cc.punto_venta_id, cc.venta_id, cc.fecha_compra, cc.importe_total, cc.dni_empleado, cc.anulado, cc.motivo_anulacion, es_cerrado, p.apellido, p.nombre
        ORDER BY cc.fecha_compra DESC
        LIMIT 500
    ";

    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $compras = [];
    while ($row = $res->fetch_assoc()) {
        $compras[] = [
            'punto_venta_id'   => (int)$row['punto_venta_id'],
            'venta_id'         => (int)$row['venta_id'],
            'fecha_compra'     => date('d/m/Y H:i', strtotime($row['fecha_compra'])),
            'importe_total'    => number_format((float)$row['importe_total'], 2, ',', '.'),
            'titular_actual'   => $row['titular_actual'],
            'dni_empleado'     => $row['dni_empleado'],
            'anulado'          => (int)$row['anulado'],
            'motivo_anulacion' => $row['motivo_anulacion'],
            'es_cerrado'       => (int)$row['es_cerrado'],
            'detalles_resumen' => $row['detalles_resumen']
        ];
    }

    echo json_encode($compras);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();