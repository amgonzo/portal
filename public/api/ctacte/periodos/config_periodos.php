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
header('Content-Type: application/json');

// 2. Validar token y permisos (usa la conexión al SSO, lo cual es correcto)
$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// 3. SOBRESCRIBIR $mysqli conectándolo a la base de datos de CTACTE_ para las consultas del módulo
$mysqli = conectarDB('CTACTE_');

$action = $_GET['action'] ?? '';

try {
    if ($action === 'listar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            throw new Exception("Método no permitido para listar.");
        }

        $res = $mysqli->query("SELECT * FROM config_periodos_reglas ORDER BY mes_periodo ASC");
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        echo json_encode(["status" => "ok", "data" => $data]);
        exit;
    }

    if ($action === 'editar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception("Método no permitido para editar.");
        }

        $mes_periodo       = (int)($_POST['mes_periodo'] ?? 0);
        $dia_inicio        = (int)($_POST['dia_inicio'] ?? 0);
        $mes_inicio        = (int)($_POST['mes_inicio'] ?? 0);
        $resta_anio_inicio = isset($_POST['resta_anio_inicio']) && $_POST['resta_anio_inicio'] == '1' ? 1 : 0;
        $dia_fin           = (int)($_POST['dia_fin'] ?? 0);
        $mes_fin           = (int)($_POST['mes_fin'] ?? 0);
        $descripcion       = trim($_POST['descripcion'] ?? '');

        if ($mes_periodo < 1 || $mes_periodo > 12) {
            throw new Exception("Mes de período no válido.");
        }

        if ($dia_inicio < 1 || $dia_inicio > 31 || $dia_fin < 1 || $dia_fin > 31) {
            throw new Exception("Los días deben estar en el rango de 1 a 31.");
        }

        if ($mes_inicio < 1 || $mes_inicio > 12 || $mes_fin < 1 || $mes_fin > 12) {
            throw new Exception("Los meses seleccionados no son válidos.");
        }

        $stmt = $mysqli->prepare("
            UPDATE config_periodos_reglas 
            SET dia_inicio = ?, 
                mes_inicio = ?, 
                resta_anio_inicio = ?, 
                dia_fin = ?, 
                mes_fin = ?, 
                descripcion = ? 
            WHERE mes_periodo = ?
        ");

        $stmt->bind_param("iiiiisi", $dia_inicio, $mes_inicio, $resta_anio_inicio, $dia_fin, $mes_fin, $descripcion, $mes_periodo);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al actualizar la base de datos.");
        }

        $stmt->close();
        echo json_encode(["status" => "ok", "msg" => "Regla de período actualizada correctamente."]);
        exit;
    }

    echo json_encode(["status" => "error", "msg" => "Acción no válida."]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "msg" => $e->getMessage()]);
}

$mysqli->close();