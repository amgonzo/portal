
<?php

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================================================
    // CONFIGURACIÓN
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
    // AUTENTICACIÓN Y EMPRESA
    // =========================================================

    $userAuth = validarTokenAPI($mysqli);

    if (!$userAuth) {
        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'No autorizado.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    validarPermisoEndpoint($mysqli, $userAuth);

    $empresa = obtenerEmpresaActual($mysqli, $userAuth);

    if (!$empresa || empty($empresa['idempresa'])) {
        http_response_code(400);

        echo json_encode([
            'status' => 'error',
            'msg' => 'No se pudo determinar la empresa activa.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $mysqli = conectarDBEmpresa(
        $mysqli,
        (int)$empresa['idempresa'],
        'DATOS'
    );

    // =========================================================
    // 1. EMPLEADOS ACTIVOS
    // =========================================================

    $sql = "
        SELECT COUNT(*) AS total
        FROM empleados
        WHERE activo = 1
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar empleados activos: ' . $mysqli->error
        );
    }

    $empleadosActivos = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // 2. MARCAS VÁLIDAS DE HOY
    //    Reloj + manuales, excluyendo las invalidadas.
    // =========================================================

    $sql = "
        SELECT
            (
                SELECT COUNT(*)
                FROM marcas_reloj mr
                WHERE mr.fecha = CURDATE()
                  AND NOT EXISTS (
                      SELECT 1
                      FROM marcas_invalidaciones mi
                      WHERE mi.idmarca_reloj = mr.idmarca
                  )
            )
            +
            (
                SELECT COUNT(*)
                FROM marcas_manuales mm
                WHERE mm.fecha = CURDATE()
                  AND NOT EXISTS (
                      SELECT 1
                      FROM marcas_invalidaciones mi
                      WHERE mi.idmarca_manual = mm.idmarca_manual
                  )
            ) AS total
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar marcas de hoy: ' . $mysqli->error
        );
    }

    $marcasHoy = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // 3. JORNADAS PENDIENTES
    //
    // Cuenta todas las fechas con marcas válidas de empleados
    // activos que no tengan jornada creada o cuya jornada
    // siga pendiente.
    //
    // No cuenta jornadas calculadas ni jornadas que requieren
    // revisión: estas últimas tienen su propio contador.
    // =========================================================

    $sql = "
        SELECT COUNT(*) AS total
        FROM (
            SELECT
                m.idempleado,
                m.fecha

            FROM (
                SELECT
                    mr.idempleado,
                    mr.fecha

                FROM marcas_reloj mr

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_reloj = mr.idmarca
                )

                UNION ALL

                SELECT
                    mm.idempleado,
                    mm.fecha

                FROM marcas_manuales mm

                WHERE NOT EXISTS (
                    SELECT 1
                    FROM marcas_invalidaciones mi
                    WHERE mi.idmarca_manual = mm.idmarca_manual
                )

            ) m

            INNER JOIN empleados e
                ON e.idempleado = m.idempleado
               AND e.activo = 1

            GROUP BY
                m.idempleado,
                m.fecha

        ) pendientes

        LEFT JOIN jornadas j
            ON j.idempleado = pendientes.idempleado
           AND j.fecha = pendientes.fecha

        WHERE
            j.idjornada IS NULL
            OR j.estado = 'pendiente'
    ";

    $resultado = $mysqli->query($sql);

    if (!$resultado) {
        throw new Exception(
            'Error al consultar jornadas pendientes: ' . $mysqli->error
        );
    }

    $jornadasPendientes = (int)$resultado->fetch_assoc()['total'];

    // =========================================================
    // 4. JORNADAS QUE REQUIEREN REVISIÓN
    //
    // Todas las fechas, no solamente hoy.
    // =========================================================

    $sql = "
        SELECT COUNT(*) AS total
        FROM jornadas
        WHERE estado = 'requiere_revision'
    ";

    $resultado = $mysqli->query($sql);

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