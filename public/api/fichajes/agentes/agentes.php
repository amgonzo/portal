<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');

// =========================================================
// 1. RUTAS Y COMPOSER
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

// =========================================================
// 2. CARGAR .ENV
// =========================================================

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

// =========================================================
// 3. CONFIGURACIÓN CENTRAL
// =========================================================

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

// =========================================================
// 4. VALIDAR USUARIO SSO Y PERMISOS
// =========================================================

$userAuth = validarTokenAPI($mysqli ?? null);
validarPermisoEndpoint($mysqli, $userAuth);

// =========================================================
// 5. OBTENER EMPRESA ACTIVA
// =========================================================

$empresa = obtenerEmpresaActual($mysqli, $userAuth);

if (!$empresa) {
    throw new Exception("No se pudo determinar la empresa activa.");
}

$idempresa = (int)$empresa['idempresa'];

// IMPORTANTE:
// NO conectamos con conectarDBEmpresa().
// La tabla agentes está en la base central SSO.

// =========================================================
// 6. OBTENER ACCIÓN
// =========================================================

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_GET['action'] ?? $_POST['action'] ?? '')
    : ($_GET['action'] ?? '');

try {

    switch ($action) {

        // =====================================================
        // 1. LISTAR AGENTES
        // =====================================================

        case 'listar':

            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                throw new Exception("Método no permitido.");
            }

            $stmt = $mysqli->prepare("
                SELECT
                    a.idagente,
                    a.agent_id,
                    a.nombre,
                    a.idempresa,
                    a.activo,
                    a.ultimo_acceso,
                    a.ultima_actividad,
                    a.ultimo_error,
                    a.fecha_creacion,
                    e.nombre AS empresa_nombre
                FROM agentes a
                INNER JOIN empresas e
                    ON e.idempresa = a.idempresa
                WHERE a.idempresa = ?
                ORDER BY a.nombre ASC
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar consulta: " . $mysqli->error
                );
            }

            $stmt->bind_param("i", $idempresa);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "Error al consultar agentes: " . $error
                );
            }

            $resultado = $stmt->get_result();

            $datos = [];

            while ($row = $resultado->fetch_assoc()) {

                $row['idagente'] = (int)$row['idagente'];
                $row['idempresa'] = (int)$row['idempresa'];
                $row['activo'] = (int)$row['activo'];

                $datos[] = $row;
            }

            $stmt->close();

            echo json_encode([
                "status" => "ok",
                "data" => $datos
            ]);

            break;


        // =====================================================
        // 2. CREAR AGENTE
        // =====================================================

        case 'crear':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $nombre = trim($_POST['nombre'] ?? '');

            if ($nombre === '') {
                throw new Exception(
                    "Debe indicar el nombre del agente."
                );
            }

            // -------------------------------------------------
            // Generar Agent ID
            // -------------------------------------------------

            $agentId = 'AGT-' . strtoupper(
                bin2hex(random_bytes(8))
            );

            // -------------------------------------------------
            // Generar token
            // -------------------------------------------------

            $token = bin2hex(
                random_bytes(32)
            );

            // -------------------------------------------------
            // Guardar solamente el hash
            // -------------------------------------------------

            $tokenHash = password_hash(
                $token,
                PASSWORD_DEFAULT
            );

            if (!$tokenHash) {
                throw new Exception(
                    "No se pudo generar la credencial del agente."
                );
            }

            // -------------------------------------------------
            // Insertar agente
            // -------------------------------------------------

            $stmt = $mysqli->prepare("
                INSERT INTO agentes
                (
                    agent_id,
                    nombre,
                    token_hash,
                    idempresa,
                    activo
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    1
                )
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar inserción: " . $mysqli->error
                );
            }

            $stmt->bind_param(
                "sssi",
                $agentId,
                $nombre,
                $tokenHash,
                $idempresa
            );

            if (!$stmt->execute()) {

                if ($stmt->errno === 1062) {
                    $stmt->close();

                    throw new Exception(
                        "El Agent ID generado ya existe. Intente nuevamente."
                    );
                }

                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "No se pudo crear el agente: " . $error
                );
            }

            $idNuevo = $stmt->insert_id;

            $stmt->close();

            // -------------------------------------------------
            // Auditoría
            // -------------------------------------------------

            if (function_exists('registrarLog')) {

                $idUsuarioLog =
                    $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    'crear_agente',
                    'agentes',
                    $idNuevo,
                    $idUsuarioLog,
                    null,
                    [
                        "nombre" => $nombre,
                        "agent_id" => $agentId,
                        "idempresa" => $idempresa
                    ]
                );
            }

            // -------------------------------------------------
            // IMPORTANTE:
            // El token solamente se devuelve ahora.
            // -------------------------------------------------

            echo json_encode([
                "status" => "ok",
                "msg" => "Agente creado con éxito.",
                "idagente" => (int)$idNuevo,
                "agent_id" => $agentId,
                "token" => $token
            ]);

            break;


        // =====================================================
        // 3. EDITAR AGENTE
        // =====================================================

        case 'editar':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idagente = (int)(
                $_POST['idagente'] ?? 0
            );

            $nombre = trim(
                $_POST['nombre'] ?? ''
            );

            if ($idagente <= 0) {
                throw new Exception(
                    "ID de agente inválido."
                );
            }

            if ($nombre === '') {
                throw new Exception(
                    "El nombre del agente es obligatorio."
                );
            }

            // -------------------------------------------------
            // Verificar que pertenezca a la empresa
            // -------------------------------------------------

            $stmt = $mysqli->prepare("
                SELECT *
                FROM agentes
                WHERE idagente = ?
                  AND idempresa = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar consulta: " . $mysqli->error
                );
            }

            $stmt->bind_param(
                "ii",
                $idagente,
                $idempresa
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "Error al consultar el agente: " . $error
                );
            }

            $resultado = $stmt->get_result();
            $agenteAnterior = $resultado->fetch_assoc();

            $stmt->close();

            if (!$agenteAnterior) {
                throw new Exception(
                    "Agente no encontrado."
                );
            }

            // -------------------------------------------------
            // Actualizar
            // -------------------------------------------------

            $stmt = $mysqli->prepare("
                UPDATE agentes
                SET nombre = ?
                WHERE idagente = ?
                  AND idempresa = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar actualización: " .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                "sii",
                $nombre,
                $idagente,
                $idempresa
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "No se pudo actualizar el agente: " . $error
                );
            }

            $stmt->close();

            // -------------------------------------------------
            // Auditoría
            // -------------------------------------------------

            if (function_exists('registrarLog')) {

                $idUsuarioLog =
                    $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    'editar_agente',
                    'agentes',
                    $idagente,
                    $idUsuarioLog,
                    $agenteAnterior,
                    [
                        "nombre" => $nombre
                    ]
                );
            }

            echo json_encode([
                "status" => "ok",
                "msg" => "Agente actualizado correctamente."
            ]);

            break;


        // =====================================================
        // 4. CAMBIAR ESTADO
        // =====================================================

        case 'estado':

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception("Método no permitido.");
            }

            $idagente = (int)(
                $_POST['idagente'] ?? 0
            );

            $activo = (int)(
                $_POST['activo'] ?? -1
            );

            if ($idagente <= 0) {
                throw new Exception(
                    "ID de agente inválido."
                );
            }

            if ($activo !== 0 && $activo !== 1) {
                throw new Exception(
                    "Estado inválido."
                );
            }

            // -------------------------------------------------
            // Buscar agente
            // -------------------------------------------------

            $stmt = $mysqli->prepare("
                SELECT *
                FROM agentes
                WHERE idagente = ?
                  AND idempresa = ?
                LIMIT 1
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar consulta: " .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                "ii",
                $idagente,
                $idempresa
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "Error al consultar el agente: " . $error
                );
            }

            $resultado = $stmt->get_result();
            $agenteAnterior = $resultado->fetch_assoc();

            $stmt->close();

            if (!$agenteAnterior) {
                throw new Exception(
                    "Agente no encontrado."
                );
            }

            // -------------------------------------------------
            // Actualizar estado
            // -------------------------------------------------

            $stmt = $mysqli->prepare("
                UPDATE agentes
                SET activo = ?
                WHERE idagente = ?
                  AND idempresa = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    "Error al preparar actualización: " .
                    $mysqli->error
                );
            }

            $stmt->bind_param(
                "iii",
                $activo,
                $idagente,
                $idempresa
            );

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    "No se pudo cambiar el estado: " . $error
                );
            }

            $stmt->close();

            // -------------------------------------------------
            // Auditoría
            // -------------------------------------------------

            if (function_exists('registrarLog')) {

                $idUsuarioLog =
                    $userAuth['idusuario'] ?? null;

                @registrarLog(
                    $mysqli,
                    $activo === 1
                        ? 'alta_agente'
                        : 'baja_agente',
                    'agentes',
                    $idagente,
                    $idUsuarioLog,
                    $agenteAnterior,
                    [
                        "activo" => $activo
                    ]
                );
            }

            $msg = $activo === 1
                ? "Agente activado correctamente."
                : "Agente desactivado correctamente.";

            echo json_encode([
                "status" => "ok",
                "msg" => $msg
            ]);

            break;


        // =====================================================
        // ACCIÓN NO VÁLIDA
        // =====================================================

        default:

            throw new Exception(
                "Acción no válida."
            );
    }

} catch (Exception $e) {

    if (
        isset($mysqli) &&
        $mysqli instanceof mysqli &&
        $mysqli->connect_errno == 0
    ) {
        @$mysqli->rollback();
    }

    // ---------------------------------------------------------
    // Registrar error
    // ---------------------------------------------------------

    if (
        function_exists('registrarLog') &&
        isset($mysqli) &&
        $mysqli instanceof mysqli &&
        $mysqli->connect_errno == 0 &&
        $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {

        $idUsuarioLog =
            $userAuth['idusuario'] ?? null;

        @registrarLog(
            $mysqli,
            'error_accion_' . ($action ?: 'desconocida'),
            'sistema',
            null,
            $idUsuarioLog,
            null,
            [
                "error" => $e->getMessage()
            ]
        );
    }

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}

if (
    isset($mysqli) &&
    $mysqli instanceof mysqli &&
    $mysqli->connect_errno == 0
) {
    $mysqli->close();
}