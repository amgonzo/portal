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
require_once $rutas['contexto'];

// 1. Validar token y permisos contra el SSO
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 2. Conectar a la base de datos de la empresa actual
$empresa = obtenerEmpresaActual($mysqli, $userAuth);
$mysqli = conectarBase($empresa['db_nombre']);

// 3. Capturar la acción de forma segura separando GET y POST
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_GET['action'] ?? $_POST['action'] ?? '') : ($_GET['action'] ?? '');

try {
    switch ($action) {

        // ---------------------------------------------------------------------
        // 1. LISTAR EMPLEADOS
        // ---------------------------------------------------------------------
        case 'listar':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $sql = "
                SELECT 
                    idempleado, 
                    documento, 
                    nombre, 
                    apellido, 
                    tarjeta, 
                    fecha_inicio, 
                    activo
                FROM empleados
                ORDER BY apellido ASC, nombre ASC
            ";
            $res = $mysqli->query($sql);
            $datos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['idempleado'] = (int)$row['idempleado'];
                    $row['activo'] = (int)$row['activo'];
                    $datos[] = $row;
                }
            }
            echo json_encode(["status" => "ok", "data" => $datos]);
            break;

        // ---------------------------------------------------------------------
        // 2. CREAR EMPLEADO
        // ---------------------------------------------------------------------
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $documento    = trim($_POST['documento'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $apellido     = trim($_POST['apellido'] ?? '');
            $tarjeta      = trim($_POST['tarjeta'] ?? '') !== '' ? trim($_POST['tarjeta']) : null;
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '') !== '' ? trim($_POST['fecha_inicio']) : null;

            if (empty($documento) || empty($nombre) || empty($apellido)) {
                throw new Exception("El documento, nombre y apellido son obligatorios.");
            }

            $stmt = $mysqli->prepare("INSERT INTO empleados (documento, nombre, apellido, tarjeta, fecha_inicio, activo) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $documento, $nombre, $apellido, $tarjeta, $fecha_inicio);
            
            if (!$stmt->execute()) {
                if ($mysqli->errno === 1062) {
                    throw new Exception("Ya existe un empleado registrado con el documento ingresado.");
                }
                throw new Exception("Error al crear el empleado: " . $stmt->error);
            }
            
            $idNuevo = $stmt->insert_id;
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'crear_empleado', 
                    'empleados', 
                    $idNuevo, 
                    $idUsuarioLog,
                    null,
                    ["documento" => $documento, "nombre" => $nombre, "apellido" => $apellido]
                );
            }

            echo json_encode(["status" => "ok", "msg" => "Empleado creado con éxito."]);
            break;

        // ---------------------------------------------------------------------
        // 3. EDITAR EMPLEADO
        // ---------------------------------------------------------------------
        case 'editar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idempleado   = (int)($_POST['idempleado'] ?? 0);
            $documento    = trim($_POST['documento'] ?? '');
            $nombre       = trim($_POST['nombre'] ?? '');
            $apellido     = trim($_POST['apellido'] ?? '');
            $tarjeta      = trim($_POST['tarjeta'] ?? '') !== '' ? trim($_POST['tarjeta']) : null;
            $fecha_inicio = trim($_POST['fecha_inicio'] ?? '') !== '' ? trim($_POST['fecha_inicio']) : null;

            if ($idempleado <= 0) {
                throw new Exception("ID de empleado inválido.");
            }

            if (empty($documento) || empty($nombre) || empty($apellido)) {
                throw new Exception("El documento, nombre y apellido son obligatorios.");
            }

            $res = $mysqli->query("SELECT * FROM empleados WHERE idempleado = $idempleado");
            $emp = $res ? $res->fetch_assoc() : null;
            
            if (!$emp) {
                throw new Exception("Empleado no encontrado.");
            }

            $stmt = $mysqli->prepare("UPDATE empleados SET documento = ?, nombre = ?, apellido = ?, tarjeta = ?, fecha_inicio = ? WHERE idempleado = ?");
            $stmt->bind_param("sssssi", $documento, $nombre, $apellido, $tarjeta, $fecha_inicio, $idempleado);
            
            if (!$stmt->execute()) {
                if ($mysqli->errno === 1062) {
                    throw new Exception("El documento ingresado ya pertenece a otro empleado.");
                }
                throw new Exception("Error al actualizar el empleado: " . $stmt->error);
            }
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    'editar_empleado', 
                    'empleados', 
                    $idempleado, 
                    $idUsuarioLog,
                    $emp, 
                    ["documento" => $documento, "nombre" => $nombre, "apellido" => $apellido]
                );
            }

            echo json_encode(["status" => "ok", "msg" => "Empleado actualizado correctamente."]);
            break;

        // ---------------------------------------------------------------------
        // 4. CAMBIAR ESTADO (ALTA / BAJA)
        // ---------------------------------------------------------------------
        case 'estado':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idempleado = (int)($_POST['idempleado'] ?? 0);
            $activo     = (int)($_POST['activo'] ?? 0);

            if ($idempleado <= 0) {
                throw new Exception("ID de empleado inválido.");
            }

            $res = $mysqli->query("SELECT * FROM empleados WHERE idempleado = $idempleado");
            $emp = $res ? $res->fetch_assoc() : null;

            if (!$emp) {
                throw new Exception("Empleado no encontrado.");
            }

            $stmt = $mysqli->prepare("UPDATE empleados SET activo = ? WHERE idempleado = ?");
            $stmt->bind_param("ii", $activo, $idempleado);
            $stmt->execute();
            $stmt->close();

            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    $activo === 1 ? 'alta_empleado' : 'baja_empleado', 
                    'empleados', 
                    $idempleado, 
                    $idUsuarioLog,
                    $emp, 
                    ["activo" => $activo]
                );
            }

            $msgAccion = $activo === 1 ? "Empleado dado de alta con éxito." : "Empleado dado de baja correctamente.";
            echo json_encode(["status" => "ok", "msg" => $msgAccion]);
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