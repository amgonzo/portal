<?php

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================================================
    // CONFIGURACIÓN GENERAL
    // =========================================================

    $rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

    require_once $rutas['autoload'];

    try {
        $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
        $dotenv->load();
    } catch (Exception $e) {
        // El entorno puede estar cargado previamente.
    }

    require_once $rutas['conexion'];
    require_once $rutas['middleware'];
    require_once $rutas['contexto'];

    // =========================================================
    // MÉTODO
    // =========================================================

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Método no permitido.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // =========================================================
    // AUTENTICACIÓN
    // =========================================================

    $userAuth = validarTokenAPI($mysqli);

    // =========================================================
    // PERMISOS
    // =========================================================

    validarPermisoEndpoint($mysqli, $userAuth);

    // =========================================================
    // EMPRESA ACTIVA
    // =========================================================

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'No se pudo determinar la empresa activa.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // =========================================================
    // CONEXIÓN A LA BASE DE DATOS DE LA EMPRESA
    // =========================================================

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // =========================================================
    // 1. EMPLEADOS ACTIVOS
    // =========================================================

    $sqlEmpleados = "
        SELECT COUNT(*) AS total
        FROM empleados
        WHERE activo = 1
    ";

    $resultado = $mysqli->query($sqlEmpleados);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar empleados activos: ' . $mysqli->error
        );
    }

    $empleadosActivos = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // 2. MARCAS DE HOY
    //    Se cuentan tanto las marcas del reloj como las manuales
    // =========================================================

    $sqlMarcasReloj = "
        SELECT COUNT(*) AS total
        FROM marcas_reloj
        WHERE fecha = CURDATE()
    ";

    $resultado = $mysqli->query($sqlMarcasReloj);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar marcas del reloj: ' . $mysqli->error
        );
    }

    $marcasReloj = (int)$resultado->fetch_assoc()['total'];

    $sqlMarcasManuales = "
        SELECT COUNT(*) AS total
        FROM marcas_manuales
        WHERE fecha = CURDATE()
    ";

    $resultado = $mysqli->query($sqlMarcasManuales);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar marcas manuales: ' . $mysqli->error
        );
    }

    $marcasManuales = (int)$resultado->fetch_assoc()['total'];

    $marcasHoy = $marcasReloj + $marcasManuales;

    // =========================================================
    // 3. JORNADAS PENDIENTES
    // =========================================================

    $sqlJornadasPendientes = "
        SELECT COUNT(*) AS total
        FROM jornadas
        WHERE fecha = CURDATE()
          AND estado = 'pendiente'
    ";

    $resultado = $mysqli->query($sqlJornadasPendientes);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar jornadas pendientes: ' . $mysqli->error
        );
    }

    $jornadasPendientes = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // 4. JORNADAS CON REVISIÓN
    // =========================================================

    $sqlJornadasRevision = "
        SELECT COUNT(*) AS total
        FROM jornadas
        WHERE fecha = CURDATE()
          AND estado = 'requiere_revision'
    ";

    $resultado = $mysqli->query($sqlJornadasRevision);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar jornadas con revisión: ' . $mysqli->error
        );
    }

    $jornadasRevision = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // RESPUESTA
    // =========================================================

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'empleados_activos' => $empleadosActivos,
            'marcas_hoy' => $marcasHoy,
            'jornadas_pendientes' => $jornadasPendientes,
            'jornadas_revision' => $jornadasRevision
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}