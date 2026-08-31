<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');
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

// api/ctacte/categorias.php
header('Content-Type: application/json');
// <--- Agregado para auditoría

// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de CTACTE_
$mysqli = conectarDB('CTACTE_');

// 3. Capturar la acción de forma segura separando GET y POST
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_GET['action'] ?? $_POST['action'] ?? '') : ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // 1. LISTAR CATEGORÍAS (Con total de personas)
        // ---------------------------------------------------------------------
        case 'listar':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $sql = "
                SELECT 
                    c.idcategoria, 
                    c.nombre, 
                    c.limite_mensual, 
                    c.es_default,
                    COUNT(p.dni) AS total_personas
                FROM categorias c
                LEFT JOIN personas p ON c.idcategoria = p.idcategoria AND p.activo = 1
                GROUP BY c.idcategoria, c.nombre, c.limite_mensual, c.es_default
                ORDER BY c.es_default DESC, c.idcategoria ASC
            ";
            $res = $mysqli->query($sql);
            $datos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['limite_mensual'] = (float)$row['limite_mensual'];
                    $row['es_default'] = (bool)$row['es_default'];
                    $row['total_personas'] = (int)$row['total_personas'];
                    $datos[] = $row;
                }
            }
            echo json_encode(["status" => "ok", "data" => $datos]);
            break;

        // ---------------------------------------------------------------------
        // 2. CREAR CATEGORÍA
        // ---------------------------------------------------------------------
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $nombre = trim($_POST['nombre'] ?? '');
            $limite = (float)($_POST['limite_mensual'] ?? 0);

            if (empty($nombre)) {
                throw new Exception("El nombre de la categoría es obligatorio.");
            }

            $stmt = $mysqli->prepare("INSERT INTO categorias (nombre, limite_mensual, es_default) VALUES (?, ?, 0)");
            $stmt->bind_param("sd", $nombre, $limite);
            $stmt->execute();
            $idNuevo = $stmt->insert_id;
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'crear_categoria', 
                    'categorias', 
                    $idNuevo, 
                    $idUsuarioLog,
                    null,
                    ["nombre" => $nombre, "limite_mensual" => $limite]
                );
            }

            echo json_encode(["status" => "ok", "msg" => "Categoría creada con éxito."]);
            break;

        // ---------------------------------------------------------------------
        // 3. EDITAR CATEGORÍA
        // ---------------------------------------------------------------------
        case 'editar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idcategoria = (int)($_POST['idcategoria'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            $limite = (float)($_POST['limite_mensual'] ?? 0);

            $res = $mysqli->query("SELECT * FROM categorias WHERE idcategoria = $idcategoria");
            $cat = $res ? $res->fetch_assoc() : null;
            
            if (!$cat) {
                throw new Exception("Categoría no encontrada.");
            }
            if ($cat['es_default']) {
                throw new Exception("La categoría por defecto no se puede editar.");
            }

            $stmt = $mysqli->prepare("UPDATE categorias SET nombre = ?, limite_mensual = ? WHERE idcategoria = ?");
            $stmt->bind_param("sdi", $nombre, $limite, $idcategoria);
            $stmt->execute();
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'editar_categoria', 
                    'categorias', 
                    $idcategoria, 
                    $idUsuarioLog,
                    $cat, 
                    ["nombre" => $nombre, "limite_mensual" => $limite]
                );
            }

            echo json_encode(["status" => "ok", "msg" => "Categoría actualizada."]);
            break;

        // ---------------------------------------------------------------------
        // 4. ELIMINAR CATEGORÍA Y REASIGNAR A DEFAULT
        // ---------------------------------------------------------------------
        case 'eliminar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idcategoria = (int)($_POST['idcategoria'] ?? 0);

            $res = $mysqli->query("SELECT * FROM categorias WHERE idcategoria = $idcategoria");
            $cat = $res ? $res->fetch_assoc() : null;

            if (!$cat) {
                throw new Exception("Categoría no encontrada.");
            }
            if ($cat['es_default']) {
                throw new Exception("La categoría por defecto no se puede eliminar.");
            }

            $mysqli->begin_transaction();

            $mysqli->query("UPDATE personas SET idcategoria = 1 WHERE idcategoria = $idcategoria");
            $mysqli->query("DELETE FROM categorias WHERE idcategoria = $idcategoria");

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'eliminar_categoria', 
                    'categorias', 
                    $idcategoria, 
                    $idUsuarioLog,
                    $cat, 
                    ["accion" => "eliminada_y_personas_reasignadas_a_default"]
                );
            }

            $mysqli->commit();

            echo json_encode(["status" => "ok", "msg" => "Categoría eliminada y personas reasignadas a la categoría default."]);
            break;

        // ---------------------------------------------------------------------
        // 5. LISTAR PERSONAS PARA ASIGNACIÓN
        // ---------------------------------------------------------------------
        case 'listar_personas_asignacion':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $sql = "
                SELECT 
                    p.dni, 
                    CONCAT(p.apellido, ', ', p.nombre) AS nombre_completo,
                    p.idcategoria,
                    c.nombre AS categoria_nombre,
                    c.es_default
                FROM personas p
                INNER JOIN categorias c ON p.idcategoria = c.idcategoria
                WHERE p.activo = 1
                ORDER BY c.es_default DESC, p.apellido ASC
            ";
            $res = $mysqli->query($sql);
            $datos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $datos[] = [
                        "dni" => $row['dni'],
                        "nombre" => $row['nombre_completo'],
                        "idcategoria" => (int)$row['idcategoria'],
                        "categoria_nombre" => $row['categoria_nombre'],
                        "es_default" => (bool)$row['es_default'],
                        "estado_categoria" => ($row['es_default']) ? "Sin Categoría (Default)" : "Categoría: " . $row['categoria_nombre']
                    ];
                }
            }
            echo json_encode(["status" => "ok", "data" => $datos]);
            break;

        // ---------------------------------------------------------------------
        // 6. ASIGNAR PERSONAS A UNA CATEGORÍA
        // ---------------------------------------------------------------------
        case 'asignar_persona':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $dni = trim($_POST['dni'] ?? '');
            $nueva_categoria_id = (int)($_POST['idcategoria'] ?? 0);

            if (empty($dni) || $nueva_categoria_id <= 0) {
                throw new Exception("Parámetros inválidos.");
            }

            $resAntes = $mysqli->query("SELECT idcategoria FROM personas WHERE dni = '$dni'");
            $personaAntes = $resAntes ? $resAntes->fetch_assoc() : null;

            $stmt = $mysqli->prepare("UPDATE personas SET idcategoria = ? WHERE dni = ?");
            $stmt->bind_param("is", $nueva_categoria_id, $dni);
            $stmt->execute();
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'asignar_categoria_persona', 
                    'personas', 
                    $dni, 
                    $idUsuarioLog,
                    $personaAntes, 
                    ["nueva_categoria_id" => $nueva_categoria_id]
                );
            }

            echo json_encode(["status" => "ok", "msg" => "Persona reasignada de categoría con éxito."]);
            break;

        // ---------------------------------------------------------------------
        // 7. APLICAR LÍMITES DE CATEGORÍAS A UN MES/AÑO ESPECÍFICO
        // ---------------------------------------------------------------------
        case 'aplicar_limites_mes':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $anio = (int)($_POST['anio'] ?? 0);
            $mes = (int)($_POST['mes'] ?? 0);

            if ($anio <= 2000 || $mes < 1 || $mes > 12) {
                throw new Exception("Año o mes inválidos.");
            }

            // 1. Control de Período Cerrado
            $res_cerrado = $mysqli->query("SELECT 1 FROM empleados_limites WHERE mes = $mes AND anio = $anio AND cerrado = 1 LIMIT 1");
            if ($res_cerrado && $res_cerrado->num_rows > 0) {
                throw new Exception("El período seleccionado se encuentra cerrado y no se puede modificar.");
            }

            // 2. Obtener Regla de Fechas desde config_periodos_reglas
            $res_regla = $mysqli->query("SELECT * FROM config_periodos_reglas WHERE mes_periodo = $mes");
            $regla = $res_regla ? $res_regla->fetch_assoc() : null;

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

            $mysqli->begin_transaction();

            $sql = "
                INSERT INTO empleados_limites 
                (dni, mes, anio, periodo_codigo, periodo_desde, periodo_hasta, limite_mensual, consumido_mes_actual, activo)
                SELECT 
                    p.dni, 
                    ? AS mes, 
                    ? AS anio,
                    ? AS periodo_codigo,
                    ? AS periodo_desde,
                    ? AS periodo_hasta,
                    c.limite_mensual,
                    COALESCE((
                        SELECT SUM(
                            CASE 
                                WHEN UPPER(cc.tipo_comprobante) LIKE '%NOTA_CREDITO%' 
                                  OR UPPER(cc.tipo_comprobante) LIKE '%CREDITO%' 
                                THEN -cc.importe_total 
                                ELSE cc.importe_total 
                            END
                        ) 
                        FROM compras_cabecera cc 
                        WHERE CONVERT(cc.dni_empleado USING utf8mb4) = CONVERT(p.dni USING utf8mb4)
                          AND COALESCE(cc.anulado, 0) = 0
                          AND cc.fecha_compra BETWEEN ? AND ?
                    ), 0.00) AS consumido_mes_actual,
                    1 AS activo
                FROM personas p
                INNER JOIN categorias c ON p.idcategoria = c.idcategoria
                WHERE p.activo = 1
                ON DUPLICATE KEY UPDATE 
                    limite_mensual = VALUES(limite_mensual),
                    periodo_codigo = VALUES(periodo_codigo),
                    periodo_desde  = VALUES(periodo_desde),
                    periodo_hasta  = VALUES(periodo_hasta),
                    consumido_mes_actual = VALUES(consumido_mes_actual)
            ";

            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error al preparar consulta SQL: " . $mysqli->error);
            }

            $stmt->bind_param("iisssss", $mes, $anio, $periodoCodigo, $periodoDesde, $periodoHasta, $periodoDesde, $periodoHasta);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al ejecutar actualización de límites: " . $stmt->error);
            }

            $filas_afectadas = $stmt->affected_rows;
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'aplicar_limites_categoria_mes', 
                    'empleados_limites', 
                    "$periodoCodigo", 
                    $idUsuarioLog,
                    null, 
                    ["mes" => $mes, "anio" => $anio, "periodo_codigo" => $periodoCodigo, "filas_afectadas" => $filas_afectadas]
                );
            }

            $mysqli->commit();

            echo json_encode([
                "status" => "ok", 
                "msg" => "Se actualizaron los límites de los empleados para el período {$periodoCodigo}."
            ]);
            break;
        
        // ---------------------------------------------------------------------
        // 8. LISTAR PERSONAS DE UNA CATEGORÍA ESPECÍFICA
        // ---------------------------------------------------------------------
        case 'listar_personas_por_categoria':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $idcategoria = (int)($_GET['idcategoria'] ?? 0);

            if ($idcategoria <= 0) {
                throw new Exception("Categoría no válida.");
            }

            $stmt = $mysqli->prepare("
                SELECT dni, CONCAT(apellido, ', ', nombre) AS nombre_completo 
                FROM personas 
                WHERE idcategoria = ? AND activo = 1 
                ORDER BY apellido ASC, nombre ASC
            ");
            $stmt->bind_param("i", $idcategoria);
            $stmt->execute();
            $res = $stmt->get_result();

            $personas = [];
            while ($row = $res->fetch_assoc()) {
                $personas[] = $row;
            }
            $stmt->close();

            echo json_encode(["status" => "ok", "data" => $personas]);
            break;
            
        default:
            throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->connect_errno == 0) {
        @$mysqli->rollback();
    }
    
    // Auditoría de errores en acciones críticas si corresponde
    if (function_exists('registrarLog') && isset($mysqli) && $mysqli->connect_errno == 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        @registrarLog($mysqli, 'error_accion_' . ($action ?: 'desconocida'), 'sistema', null, $idUsuarioLog, null, ["error" => $e->getMessage()]);
    }

    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}