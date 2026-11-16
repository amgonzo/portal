<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerFecha(string $valor, string $predeterminada): string
{
    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);

    return ($fecha && $fecha->format('Y-m-d') === $valor)
        ? $valor
        : $predeterminada;
}

try {

    // AUTENTICACIÓN Y EMPRESA

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

    // FECHAS

    $hoy = date('Y-m-d');

    $desde = obtenerFecha(
        $_GET['desde'] ?? '',
        date('Y-m-01')
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

    // INDICADORES

    $sqlIndicadores = "
        SELECT
            (SELECT COUNT(*)
             FROM empleados
             WHERE activo = 1) AS empleados_activos,

            (SELECT COUNT(*)
             FROM rrhh_documentos
             WHERE activo = 1
               AND fecha_vencimiento BETWEEN CURDATE()
               AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ) AS documentos_por_vencer,

            (SELECT COUNT(DISTINCT idempleado)
             FROM rrhh_legajos
            ) AS legajos_registrados,

            (SELECT COUNT(*)
             FROM empleados
             WHERE fecha_inicio BETWEEN ? AND ?
            ) AS altas_periodo,

            (SELECT COUNT(*)
             FROM empleados
             WHERE activo = 0
            ) AS empleados_inactivos
    ";

    $stmt = $mysqli->prepare($sqlIndicadores);

    if (!$stmt) {
        throw new RuntimeException($mysqli->error);
    }

    $stmt->bind_param('ss', $desde, $hasta);
    $stmt->execute();

    $indicadores = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    foreach ($indicadores as $clave => $valor) {
        $indicadores[$clave] = (int)$valor;
    }

    // DOCUMENTOS POR VENCER

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
        throw new RuntimeException($mysqli->error);
    }

    $documentos = [];

    while ($fila = $result->fetch_assoc()) {
        $documentos[] = $fila;
    }

    $result->free();

    // PERSONAL ACTIVO POR SECTOR
    // El sector vigente se obtiene del historial laboral.

    $sqlSectores = "
        SELECT
            COALESCE(cs.nombre, 'Sin sector asignado') AS sector,
            COUNT(e.idempleado) AS cantidad
        FROM empleados e

        LEFT JOIN rrhh_historial_laboral h
            ON h.idhistorial = (
                SELECT h2.idhistorial
                FROM rrhh_historial_laboral h2
                WHERE h2.idempleado = e.idempleado
                  AND h2.fecha_hasta IS NULL
                ORDER BY h2.fecha_desde DESC, h2.idhistorial DESC
                LIMIT 1
            )

        LEFT JOIN core_sectores cs
            ON cs.idsector = h.idsector
           AND cs.activo = 1

        WHERE e.activo = 1

        GROUP BY cs.idsector, cs.nombre
        ORDER BY cantidad DESC
    ";

    $result = $mysqli->query($sqlSectores);

    if (!$result) {
        throw new RuntimeException($mysqli->error);
    }

    $sectores = [];

    while ($fila = $result->fetch_assoc()) {
        $sectores[] = [
            'sector' => $fila['sector'],
            'cantidad' => (int)$fila['cantidad']
        ];
    }

    $result->free();

    // RESPUESTA

    responder(200, [
        'status' => 'ok',

        'periodo' => [
            'desde' => $desde,
            'hasta' => $hasta
        ],

        'indicadores' => $indicadores,

        'documentos_por_vencer' => $documentos,

        'personal_sectores' => $sectores
    ]);

} catch (Throwable $e) {

    error_log('[RRHH Dashboard] ' . $e->getMessage());

    responder(500, [
        'status' => 'error',
        'mensaje' => 'No se pudieron obtener los datos del dashboard.'
    ]);
}