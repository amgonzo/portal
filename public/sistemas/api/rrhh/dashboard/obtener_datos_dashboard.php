<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede estar cargado previamente.
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');

// =========================================================
// FUNCIONES AUXILIARES
// =========================================================

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

function obtenerFecha(string $valor, string $predeterminada): string
{
    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);

    return ($fecha && $fecha->format('Y-m-d') === $valor)
        ? $valor
        : $predeterminada;
}

// =========================================================
// EJECUCIÓN DEL ENDPOINT
// =========================================================

try {

    // =====================================================
    // 1. AUTENTICACIÓN Y CONEXIÓN A LA EMPRESA
    // =====================================================

    $userAuth = validarTokenAPI($mysqli ?? null);

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        throw new Exception('No se seleccionó una empresa.');
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // =====================================================
    // 2. VALIDAR FECHAS
    // =====================================================

    $hoy = date('Y-m-d');
    $primerDiaMes = date('Y-m-01');

    $desde = obtenerFecha(
        $_GET['desde'] ?? '',
        $primerDiaMes
    );

    $hasta = obtenerFecha(
        $_GET['hasta'] ?? '',
        $hoy
    );

    if ($desde > $hasta) {
        responder(400, [
            'status' => 'error',
            'mensaje' => 'La fecha desde no puede ser posterior a la fecha hasta.'
        ]);
    }

    // =====================================================
    // 3. INDICADORES
    // =====================================================

    $sqlIndicadores = "
        SELECT
            (SELECT COUNT(*)
             FROM empleados
             WHERE activo = 1) AS empleados_activos,

            (SELECT COUNT(*)
             FROM rrhh_solicitudes_ausencia
             WHERE estado = 'pendiente') AS solicitudes_pendientes,

            (SELECT COUNT(*)
             FROM rrhh_documentos
             WHERE activo = 1
               AND fecha_vencimiento BETWEEN CURDATE()
               AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ) AS documentos_por_vencer,

            (SELECT COUNT(*)
             FROM rrhh_solicitudes_ausencia
             WHERE estado = 'aprobada'
               AND tipo = 'vacaciones'
               AND fecha_desde BETWEEN CURDATE()
               AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ) AS vacaciones_proximas,

            (SELECT COUNT(*)
             FROM rrhh_legajos) AS legajos_registrados,

            (SELECT COUNT(*)
             FROM empleados
             WHERE fecha_inicio BETWEEN ? AND ?
            ) AS altas_periodo,

            (SELECT COUNT(*)
             FROM empleados
             WHERE activo = 0) AS empleados_inactivos
    ";

    $stmt = $mysqli->prepare($sqlIndicadores);

    if (!$stmt) {
        throw new RuntimeException(
            'No se pudo preparar la consulta de indicadores: ' . $mysqli->error
        );
    }

    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();

    $indicadores = $stmt->get_result()->fetch_assoc() ?: [];

    $stmt->close();

    foreach ($indicadores as $clave => $valor) {
        $indicadores[$clave] = (int)$valor;
    }

    // =====================================================
    // 4. SOLICITUDES PENDIENTES
    // =====================================================

    $sqlSolicitudes = "
        SELECT
            s.idsolicitud,
            e.documento,
            e.nombre,
            e.apellido,
            CASE
                WHEN s.tipo = 'vacaciones' THEN 'Vacaciones'
                ELSE COALESCE(tl.nombre, 'Licencia')
            END AS tipo_descripcion,
            s.fecha_desde,
            s.fecha_hasta,
            s.fecha_solicitud
        FROM rrhh_solicitudes_ausencia s
        INNER JOIN empleados e
            ON e.idempleado = s.idempleado
        LEFT JOIN rrhh_tipos_licencia tl
            ON tl.idtipo_licencia = s.idtipo_licencia
        WHERE s.estado = 'pendiente'
        ORDER BY s.fecha_solicitud ASC
        LIMIT 10
    ";

    $result = $mysqli->query($sqlSolicitudes);

    if (!$result) {
        throw new RuntimeException(
            'Error consultando solicitudes pendientes: ' . $mysqli->error
        );
    }

    $solicitudes = [];

    while ($fila = $result->fetch_assoc()) {
        $solicitudes[] = $fila;
    }

    $result->free();

    // =====================================================
    // 5. DOCUMENTOS POR VENCER
    // =====================================================

    $sqlDocumentos = "
        SELECT
            d.iddocumento,
            e.nombre,
            e.apellido,
            d.tipo_documento,
            d.titulo,
            d.fecha_vencimiento
        FROM rrhh_documentos d
        INNER JOIN empleados e
            ON e.idempleado = d.idempleado
        WHERE d.activo = 1
          AND d.fecha_vencimiento BETWEEN CURDATE()
              AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY d.fecha_vencimiento ASC
        LIMIT 10
    ";

    $result = $mysqli->query($sqlDocumentos);

    if (!$result) {
        throw new RuntimeException(
            'Error consultando documentos por vencer: ' . $mysqli->error
        );
    }

    $documentos = [];

    while ($fila = $result->fetch_assoc()) {
        $documentos[] = $fila;
    }

    $result->free();

    // =====================================================
    // 6. VACACIONES Y LICENCIAS PRÓXIMAS
    // =====================================================

    $sqlAusencias = "
        SELECT
            s.idsolicitud,
            e.nombre,
            e.apellido,
            s.tipo,
            CASE
                WHEN s.tipo = 'vacaciones' THEN 'Vacaciones'
                ELSE COALESCE(tl.nombre, 'Licencia')
            END AS tipo_descripcion,
            s.fecha_desde,
            s.fecha_hasta
        FROM rrhh_solicitudes_ausencia s
        INNER JOIN empleados e
            ON e.idempleado = s.idempleado
        LEFT JOIN rrhh_tipos_licencia tl
            ON tl.idtipo_licencia = s.idtipo_licencia
        WHERE s.estado = 'aprobada'
          AND s.fecha_desde <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
          AND s.fecha_hasta >= CURDATE()
        ORDER BY s.fecha_desde ASC
        LIMIT 10
    ";

    $result = $mysqli->query($sqlAusencias);

    if (!$result) {
        throw new RuntimeException(
            'Error consultando próximas ausencias: ' . $mysqli->error
        );
    }

    $ausencias = [];

    while ($fila = $result->fetch_assoc()) {
        $ausencias[] = $fila;
    }

    $result->free();

    // =====================================================
    // 7. PERSONAL ACTIVO POR SECTOR
    // =====================================================

    $sqlSectores = "
        SELECT
            COALESCE(cs.nombre, 'Sin sector asignado') AS sector,
            COUNT(e.idempleado) AS cantidad
        FROM rrhh_legajos l
        INNER JOIN empleados e
            ON e.idempleado = l.idempleado
           AND e.activo = 1
        LEFT JOIN core_sectores cs
            ON cs.idsector = l.idsector
           AND cs.activo = 1
        GROUP BY cs.idsector, cs.nombre

        UNION ALL

        SELECT
            'Sin legajo registrado' AS sector,
            COUNT(*) AS cantidad
        FROM empleados e
        LEFT JOIN rrhh_legajos l
            ON l.idempleado = e.idempleado
        WHERE e.activo = 1
          AND l.idempleado IS NULL
        HAVING COUNT(*) > 0

        ORDER BY cantidad DESC
    ";

    $result = $mysqli->query($sqlSectores);

    if (!$result) {
        throw new RuntimeException(
            'Error consultando personal por sector: ' . $mysqli->error
        );
    }

    $sectores = [];

    while ($fila = $result->fetch_assoc()) {
        $sectores[] = [
            'sector' => $fila['sector'],
            'cantidad' => (int)$fila['cantidad']
        ];
    }

    $result->free();

    // =====================================================
    // 8. RESPUESTA DEL DASHBOARD
    // =====================================================

    responder(200, [
        'status' => 'ok',

        'periodo' => [
            'desde' => $desde,
            'hasta' => $hasta
        ],

        'indicadores' => $indicadores,

        'solicitudes_pendientes' => $solicitudes,

        'documentos_por_vencer' => $documentos,

        'proximas_ausencias' => $ausencias,

        'personal_sectores' => $sectores
    ]);

} catch (Throwable $e) {

    error_log(
        '[RRHH Dashboard] ' . $e->getMessage()
    );

    responder(500, [
        'status' => 'error',
        'mensaje' => 'No se pudieron obtener los datos del dashboard.'
    ]);
}