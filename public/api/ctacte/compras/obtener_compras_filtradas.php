<?php

// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer
require_once $rutas['autoload'];

try {
    // 3. Cargamos .env
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "msg" => "metodo_no_permitido"
    ]);
    exit;
}

// =====================================================
// AUTENTICACIÓN Y PERMISOS
// =====================================================

$userAuth = validarTokenAPI($mysqli ?? null);

validarPermisoEndpoint($mysqli, $userAuth);

// =====================================================
// CONEXIÓN A BASE CTACTE
// =====================================================

$empresa = obtenerEmpresaActual($mysqli, $userAuth);
$mysqli = conectarBase($empresa['db_nombre']);

try {

    // =================================================
    // FILTROS
    // =================================================

    $periodo  = trim($_GET['periodo'] ?? '');
    $dni      = trim($_GET['dni'] ?? '');
    $ticket   = trim($_GET['ticket'] ?? '');
    $anulados = trim($_GET['anulados'] ?? '0');

    $where = ["1=1"];
    $params = [];
    $types = "";

    // -------------------------------------------------
    // ANULADOS
    // -------------------------------------------------

    if ($anulados === '0') {

        $where[] = "COALESCE(cc.anulado, 0) = 0";

    } elseif ($anulados === '1') {

        $where[] = "COALESCE(cc.anulado, 0) = 1";
    }

    // -------------------------------------------------
    // COMPROBANTE
    //
    // IMPORTANTE:
    // El número que muestra la aplicación es
    // nro_comprobante, NO venta_id.
    // -------------------------------------------------

    if ($ticket !== '') {

        $term = "%" . $ticket . "%";

        $where[] = "
            CAST(cc.nro_comprobante AS CHAR) LIKE ?
        ";

        $params[] = $term;
        $types .= "s";
    }

    // -------------------------------------------------
    // PERÍODO
    // -------------------------------------------------

    if ($periodo !== '') {

        $where[] = "
            DATE_FORMAT(cc.fecha_compra, '%Y-%m') = ?
        ";

        $params[] = $periodo;
        $types .= "s";
    }

    // -------------------------------------------------
    // PERSONA / DNI
    // -------------------------------------------------

    if ($dni !== '' && $dni !== 'todos') {

        if ($dni === '0' || $dni === 'sin_usuario') {

            $where[] = "(
                cc.dni_empleado = '0'
                OR cc.dni_empleado = 'SIN_USUARIO'
                OR cc.dni_empleado = ''
            )";

        } else {

            $where[] = "
                TRIM(cc.dni_empleado) COLLATE utf8mb4_unicode_ci = ?
            ";

            $params[] = trim($dni);
            $types .= "s";
        }
    }

    $whereSql = implode(" AND ", $where);

    // =================================================
    // CONSULTA
    // =================================================

    $sql = "
        SELECT

            cc.punto_venta_id,
            cc.venta_id,
            cc.dni_empleado,
            cc.nro_comprobante,
            cc.tipo_comprobante,
            cc.fecha_compra,
            cc.importe_total,
            cc.anulado,
            cc.motivo_anulacion,

            p.apellido,
            p.nombre,

            el.cerrado,

            GROUP_CONCAT(
                DISTINCT CONCAT(
                    cd.descripcion,
                    ' x',
                    cd.cantidad
                )
                ORDER BY cd.descripcion
                SEPARATOR ', '
            ) AS detalles_resumen

        FROM compras_cabecera cc

        LEFT JOIN personas p
            ON TRIM(p.dni) COLLATE utf8mb4_unicode_ci =
               TRIM(cc.dni_empleado) COLLATE utf8mb4_unicode_ci

        LEFT JOIN empleados_limites el
            ON el.dni COLLATE utf8mb4_unicode_ci =
               cc.dni_empleado COLLATE utf8mb4_unicode_ci
            AND el.periodo_codigo COLLATE utf8mb4_unicode_ci =
                DATE_FORMAT(cc.fecha_compra, '%Y-%m')
                COLLATE utf8mb4_unicode_ci

        LEFT JOIN compras_detalles cd
            ON cd.punto_venta_id = cc.punto_venta_id
            AND cd.venta_id = cc.venta_id

        WHERE $whereSql

        GROUP BY
            cc.punto_venta_id,
            cc.venta_id,
            cc.dni_empleado,
            cc.nro_comprobante,
            cc.tipo_comprobante,
            cc.fecha_compra,
            cc.importe_total,
            cc.anulado,
            cc.motivo_anulacion,
            p.apellido,
            p.nombre,
            el.cerrado

        ORDER BY cc.fecha_compra DESC

        LIMIT 500
    ";

    // =================================================
    // PREPARAR CONSULTA
    // =================================================

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception(
            "Error preparando consulta: " . $mysqli->error
        );
    }

    // =================================================
    // PARÁMETROS
    // =================================================

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    // =================================================
    // EJECUTAR
    // =================================================

    if (!$stmt->execute()) {
        throw new Exception(
            "Error ejecutando consulta: " . $stmt->error
        );
    }

    $res = $stmt->get_result();

    // =================================================
    // ARMAR RESPUESTA
    // =================================================

    $compras = [];

    while ($row = $res->fetch_assoc()) {

        // ---------------------------------------------
        // TITULAR ACTUAL
        // ---------------------------------------------

        $titularActual = '';

        if (
            !empty($row['apellido']) ||
            !empty($row['nombre'])
        ) {

            $titularActual = trim(
                ($row['apellido'] ?? '') . ' ' .
                ($row['nombre'] ?? '')
            );
        }

        // ---------------------------------------------
        // CERRADO
        // ---------------------------------------------

        $esCerrado = !empty($row['cerrado'])
            ? (int)$row['cerrado']
            : 0;

        // ---------------------------------------------
        // RESPUESTA
        // ---------------------------------------------

        $compras[] = [

            'punto_venta_id' => (int)$row['punto_venta_id'],

            'venta_id' => (int)$row['venta_id'],

            'nro_comprobante' => (int)$row['nro_comprobante'],

            'tipo_comprobante' => $row['tipo_comprobante'],

            'fecha_compra' => date(
                'd/m/Y H:i',
                strtotime($row['fecha_compra'])
            ),

            'importe_total' => number_format(
                (float)$row['importe_total'],
                2,
                ',',
                '.'
            ),

            'titular_actual' => $titularActual,

            'dni_empleado' => $row['dni_empleado'],

            'anulado' => (int)$row['anulado'],

            'motivo_anulacion' => $row['motivo_anulacion'],

            'es_cerrado' => $esCerrado,

            'detalles_resumen' =>
                $row['detalles_resumen'] ?? ''
        ];
    }

    // =================================================
    // RESPUESTA JSON
    // =================================================

    echo json_encode(
        $compras,
        JSON_UNESCAPED_UNICODE
    );

    $stmt->close();

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$mysqli->close();