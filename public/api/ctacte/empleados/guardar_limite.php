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
// api/ctacte/guardar_limite.php
header('Content-Type: application/json');

// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$mysqli = conectarDB('CTACTE_');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}

$dni    = trim($_POST['dni'] ?? '');
$limite = floatval($_POST['limite_mensual'] ?? 0);
$activo = isset($_POST['activo']) ? 1 : 0;

$mes  = isset($_POST['mes']) ? (int)$_POST['mes'] : (int)date('n');
$anio = isset($_POST['anio']) ? (int)$_POST['anio'] : (int)date('Y');

if (empty($dni) || $mes < 1 || $mes > 12 || $anio < 2000) {
    echo json_encode(["status" => "error", "msg" => "Datos de entrada inválidos."]);
    exit;
}

try {
    // 1. Obtener la regla del período
    $resRegla = $mysqli->query("SELECT * FROM config_periodos_reglas WHERE mes_periodo = $mes");
    $regla = $resRegla ? $resRegla->fetch_assoc() : null;

    $regla = $regla ?? [
        'dia_inicio' => 1, 'mes_inicio' => $mes, 'resta_anio_inicio' => 0,
        'dia_fin' => 31, 'mes_fin' => $mes
    ];

    $anioInicio = $anio - (int)$regla['resta_anio_inicio'];
    $mesFin     = (int)$regla['mes_fin'];
    $diaFin     = (int)$regla['dia_fin'];

    $ultimoDiaReal = (int)date('t', strtotime(sprintf('%04d-%02d-01', $anio, $mesFin)));
    if ($diaFin > $ultimoDiaReal) {
        $diaFin = $ultimoDiaReal;
    }

    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);
    $periodoDesde  = sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']);
    $periodoHasta  = sprintf('%04d-%02d-%02d 23:59:59', $anio, $mesFin, $diaFin);

    // 2. AUDITORÍA: Capturar estado previo
    $datosAntes = null;
    $stmtPre = $mysqli->prepare("SELECT * FROM empleados_limites WHERE CAST(dni AS CHAR) = CAST(? AS CHAR) AND periodo_codigo = ?");
    $stmtPre->bind_param("ss", $dni, $periodoCodigo);
    $stmtPre->execute();
    $resPre = $stmtPre->get_result();
    if ($resPre && $resPre->num_rows > 0) {
        $datosAntes = $resPre->fetch_assoc();
    }
    $stmtPre->close();

    // 3. Insertar o actualizar garantizando periodo_codigo y calculando consumo actual real
    $stmt = $mysqli->prepare("
        INSERT INTO empleados_limites 
        (dni, mes, anio, periodo_codigo, periodo_desde, periodo_hasta, limite_mensual, consumido_mes_actual, activo) 
        VALUES (
            ?, ?, ?, ?, ?, ?, ?, 
            COALESCE((
                SELECT SUM(c.importe_total) 
                FROM compras_cabecera c 
                WHERE CAST(c.dni_empleado AS CHAR) = CAST(? AS CHAR) 
                  AND c.fecha_compra BETWEEN ? AND ?
            ), 0.00), 
            ?
        ) 
        ON DUPLICATE KEY UPDATE 
            limite_mensual = VALUES(limite_mensual), 
            activo = VALUES(activo),
            periodo_codigo = VALUES(periodo_codigo),
            periodo_desde  = VALUES(periodo_desde),
            periodo_hasta  = VALUES(periodo_hasta)
    ");
    
    $stmt->bind_param("siisssdsssi", 
        $dni, $mes, $anio, $periodoCodigo, $periodoDesde, $periodoHasta, $limite, 
        $dni, $periodoDesde, $periodoHasta, 
        $activo
    );

    if ($stmt->execute()) {
        if (function_exists('registrarLog')) {
            $idUsuarioLog = $userAuth['idusuario'] ?? null;
            registrarLog($mysqli, 'guardar_limite_empleado', 'empleados_limites', $dni, $idUsuarioLog, $datosAntes, $_POST);
        }

        echo json_encode(["status" => "ok", "msg" => "Límite guardado correctamente."]);
    } else {
        echo json_encode(["status" => "error", "msg" => "Error al guardar: " . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        "status" => "error", 
        "msg" => "Error interno del servidor: " . $e->getMessage()
    ]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}