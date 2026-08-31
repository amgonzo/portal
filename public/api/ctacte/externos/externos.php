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
// api/ctacte/externos.php
header('Content-Type: application/json');

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
        // 1. LISTAR EXTERNOS (Con nombre de categoría y porcentaje)
        // ---------------------------------------------------------------------
        case 'listar':
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $sql = "
                SELECT 
                    p.dni, 
                    p.nombre, 
                    p.apellido, 
                    p.idcategoria, 
                    p.porcentaje_descuento,
                    p.activo,
                    COALESCE(c.nombre, 'Sin Categoría') AS categoria_nombre
                FROM personas p
                LEFT JOIN categorias c ON p.idcategoria = c.idcategoria
                WHERE p.origen = 'manual'
                ORDER BY p.activo DESC, p.apellido ASC, p.nombre ASC
            ";
            $res = $mysqli->query($sql);
            $datos = [];
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $row['activo'] = (int)$row['activo'];
                    $row['porcentaje_descuento'] = (float)($row['porcentaje_descuento'] ?? 0);
                    $datos[] = $row;
                }
            }
            echo json_encode(["status" => "ok", "data" => $datos]);
            break;

        // ---------------------------------------------------------------------
        // 2. CREAR / EDITAR EXTERNO
        // ---------------------------------------------------------------------
        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $dni = trim($_POST['dni'] ?? '');
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $idcategoria = (int)($_POST['idcategoria'] ?? 0);
            $porcentaje = isset($_POST['porcentaje_descuento']) ? (float)$_POST['porcentaje_descuento'] : 0.00;
            $es_edicion = (int)($_POST['es_edicion'] ?? 0);

            if (empty($dni) || empty($nombre) || empty($apellido)) {
                throw new Exception("DNI, Nombre y Apellido son obligatorios.");
            }

            if ($idcategoria <= 0) {
                // Si no eligió categoría, buscar la default
                $resDef = $mysqli->query("SELECT idcategoria FROM categorias WHERE es_default = 1 LIMIT 1");
                if ($resDef && $rowDef = $resDef->fetch_assoc()) {
                    $idcategoria = (int)$rowDef['idcategoria'];
                }
            }

            if ($es_edicion === 0) {
                // Verificar duplicados
                $stmtCheck = $mysqli->prepare("SELECT dni FROM personas WHERE dni = ? LIMIT 1");
                $stmtCheck->bind_param("s", $dni);
                $stmtCheck->execute();
                if ($stmtCheck->get_result()->num_rows > 0) {
                    throw new Exception("El DNI ingresado ya existe en el sistema.");
                }
                $stmtCheck->close();

                // Insertar con origen = manual y porcentaje_descuento
                $stmt = $mysqli->prepare("INSERT INTO personas (dni, nombre, apellido, idcategoria, porcentaje_descuento, origen, activo) VALUES (?, ?, ?, ?, ?, 'manual', 1)");
                $stmt->bind_param("sssid", $dni, $nombre, $apellido, $idcategoria, $porcentaje);
                $stmt->execute();
                $stmt->close();

                // LOG AUDITORÍA CREACIÓN
                if (function_exists('registrarLog')) {
                    $idUsuarioLog = $userAuth['idusuario'] ?? null;
                    registrarLog(
                        $mysqli, 
                        'crear_externo', 
                        'personas', 
                        $dni, 
                        $idUsuarioLog, 
                        null, 
                        [
                            "dni" => $dni, 
                            "nombre" => $nombre, 
                            "apellido" => $apellido, 
                            "idcategoria" => $idcategoria, 
                            "porcentaje_descuento" => $porcentaje,
                            "origen" => "manual"
                        ]
                    );
                }

                $msg = "Persona externa registrada correctamente.";
            } else {
                // Obtener datos anteriores para la auditoría antes de actualizar
                $resAntes = $mysqli->query("SELECT dni, nombre, apellido, idcategoria, porcentaje_descuento, activo FROM personas WHERE dni = '$dni' AND origen = 'manual'");
                $antes = $resAntes ? $resAntes->fetch_assoc() : null;

                if (!$antes) {
                    throw new Exception("Persona externa no encontrada para editar.");
                }

                // Actualizar
                $stmt = $mysqli->prepare("UPDATE personas SET nombre = ?, apellido = ?, idcategoria = ?, porcentaje_descuento = ? WHERE dni = ? AND origen = 'manual'");
                $stmt->bind_param("ssids", $nombre, $apellido, $idcategoria, $porcentaje, $dni);
                $stmt->execute();
                $stmt->close();

                // LOG AUDITORÍA EDICIÓN
                if (function_exists('registrarLog')) {
                    $idUsuarioLog = $userAuth['idusuario'] ?? null;
                    registrarLog(
                        $mysqli, 
                        'editar_externo', 
                        'personas', 
                        $dni, 
                        $idUsuarioLog, 
                        $antes, 
                        [
                            "nombre" => $nombre, 
                            "apellido" => $apellido, 
                            "idcategoria" => $idcategoria,
                            "porcentaje_descuento" => $porcentaje
                        ]
                    );
                }

                $msg = "Datos del externo actualizados con éxito.";
            }

            echo json_encode(["status" => "ok", "msg" => $msg]);
            break;

        // ---------------------------------------------------------------------
        // 3. CAMBIAR ESTADO (ALTA / BAJA LÓGICA)
        // ---------------------------------------------------------------------
        case 'cambiar_estado':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $dni = trim($_POST['dni'] ?? '');
            $nuevo_estado = (int)($_POST['estado'] ?? 0);

            if (empty($dni)) {
                throw new Exception("DNI no válido.");
            }

            // Capturar el estado anterior para la auditoría
            $resAntes = $mysqli->query("SELECT dni, activo FROM personas WHERE dni = '$dni' AND origen = 'manual'");
            $antes = $resAntes ? $resAntes->fetch_assoc() : null;

            if (!$antes) {
                throw new Exception("Persona externa no encontrada.");
            }

            $stmt = $mysqli->prepare("UPDATE personas SET activo = ? WHERE dni = ? AND origen = 'manual'");
            $stmt->bind_param("is", $nuevo_estado, $dni);
            $stmt->execute();
            $stmt->close();

            $accionLog = ($nuevo_estado === 1) ? 'activar_externo' : 'desactivar_externo';

            // LOG AUDITORÍA CAMBIO DE ESTADO
            if (function_exists('registrarLog')) {
                $idUsuarioLog = $userAuth['idusuario'] ?? null;
                registrarLog(
                    $mysqli, 
                    $accionLog, 
                    'personas', 
                    $dni, 
                    $idUsuarioLog, 
                    $antes, 
                    ["nuevo_estado_activo" => $nuevo_estado]
                );
            }

            $accionTexto = ($nuevo_estado === 1) ? "activó" : "dio de baja";
            echo json_encode(["status" => "ok", "msg" => "Se {$accionTexto} a la persona externa correctamente."]);
            break;

        default:
            throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->connect_errno == 0) {
        @$mysqli->rollback();
    }
    
    if (function_exists('registrarLog') && isset($mysqli) && $mysqli->connect_errno == 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $idUsuarioLog = $userAuth['idusuario'] ?? null;
        @registrarLog($mysqli, 'error_accion_' . ($action ?: 'desconocida'), 'sistema', null, $idUsuarioLog, null, ["error" => $e->getMessage()]);
    }

    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

if (isset($mysqli) && $mysqli->connect_errno == 0) {
    $mysqli->close();
}