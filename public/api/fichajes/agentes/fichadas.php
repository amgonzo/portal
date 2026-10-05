<?php

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

// =========================================================
// 1. RUTAS Y COMPOSER
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

// =========================================================
// 2. .ENV
// =========================================================

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Throwable $e) {
}

// =========================================================
// 3. CONEXIÓN CENTRAL
// =========================================================

require_once $rutas['conexion'];

// =========================================================
// 4. SOLO POST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Método no permitido.'
    ]);

    exit;
}

// =========================================================
// 5. LEER JSON
// =========================================================

$entrada = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($entrada)) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Datos inválidos.'
    ]);

    exit;
}

$agentId = trim(
    (string)($entrada['agent_id'] ?? '')
);

$token = trim(
    (string)($entrada['token'] ?? '')
);

$fichadas = $entrada['fichadas'] ?? null;

// =========================================================
// 6. VALIDAR DATOS
// =========================================================

if ($agentId === '' || $token === '') {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Agent ID y token son obligatorios.'
    ]);

    exit;
}

if (!is_array($fichadas)) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => 'Debe indicar las fichadas.'
    ]);

    exit;
}

if (count($fichadas) === 0) {

    echo json_encode([
        'status' => 'ok',
        'cantidad' => 0,
        'insertadas' => 0,
        'ya_existian' => 0,
        'confirmadas' => [],
        'errores' => []
    ]);

    exit;
}

// =========================================================
// 7. AUTENTICAR AGENTE
// =========================================================

try {

    $stmt = $mysqli->prepare("
        SELECT
            idagente,
            agent_id,
            nombre,
            token_hash,
            idempresa,
            activo
        FROM agentes
        WHERE agent_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            'Error al preparar autenticación: ' .
            $mysqli->error
        );
    }

    $stmt->bind_param(
        's',
        $agentId
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;
        $stmt->close();

        throw new Exception(
            'Error al autenticar agente: ' . $error
        );
    }

    $resultado = $stmt->get_result();

    $agente = $resultado->fetch_assoc();

    $stmt->close();

    if (!$agente) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    if ((int)$agente['activo'] !== 1) {

        http_response_code(403);

        echo json_encode([
            'status' => 'error',
            'msg' => 'El agente está desactivado.'
        ]);

        exit;
    }

    if (!password_verify(
        $token,
        $agente['token_hash']
    )) {

        http_response_code(401);

        echo json_encode([
            'status' => 'error',
            'msg' => 'Credenciales de agente inválidas.'
        ]);

        exit;
    }

    $idAgente = (int)$agente['idagente'];
    $idEmpresa = (int)$agente['idempresa'];

    // =====================================================
    // 8. ACTUALIZAR ACTIVIDAD DEL AGENTE
    // =====================================================

    $stmt = $mysqli->prepare("
        UPDATE agentes
        SET
            ultima_actividad = NOW(),
            ultimo_error = NULL
        WHERE idagente = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            'i',
            $idAgente
        );

        $stmt->execute();
        $stmt->close();
    }

    // =====================================================
    // 9. CONECTAR BD DE LA EMPRESA
    // =====================================================

    $dbEmpresa = conectarDBEmpresa(
        $mysqli,
        $idEmpresa,
        'DATOS'
    );

    if (
        !$dbEmpresa ||
        !($dbEmpresa instanceof mysqli)
    ) {
        throw new Exception(
            'No se pudo conectar a la base de datos de la empresa.'
        );
    }

    $confirmadas = [];
    $errores = [];

    $insertadas = 0;
    $yaExistian = 0;

    // =====================================================
    // 10. PROCESAR CADA FICHADA
    // =====================================================

    foreach ($fichadas as $fichada) {

        $idfichada = (int)(
            $fichada['idfichada'] ?? 0
        );

        $deviceUserId = trim(
            (string)(
                $fichada['deviceUserId'] ??
                ''
            )
        );

        $idlector = (int)(
            $fichada['idlector'] ?? 0
        );

        $fecha = trim(
            (string)(
                $fichada['fecha'] ??
                ''
            )
        );

        $hora = trim(
            (string)(
                $fichada['hora'] ??
                ''
            )
        );

        try {

            // =================================================
            // VALIDAR DATOS
            // =================================================

            if (
                $idfichada <= 0 ||
                $deviceUserId === '' ||
                $idlector <= 0 ||
                $fecha === '' ||
                $hora === ''
            ) {

                throw new Exception(
                    'La fichada contiene datos obligatorios incompletos.'
                );
            }

            // =================================================
            // BUSCAR EMPLEADO
            // =================================================

            $stmt = $dbEmpresa->prepare("
                SELECT
                    idempleado
                FROM empleados
                WHERE documento = ?
                LIMIT 1
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar búsqueda de empleado: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                's',
                $deviceUserId
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    'Error al buscar empleado: ' . $error
                );
            }

            $resultadoEmpleado =
                $stmt->get_result();

            $empleado =
                $resultadoEmpleado->fetch_assoc();

            $stmt->close();

            if (!$empleado) {

                throw new Exception(
                    "No existe empleado con documento {$deviceUserId}."
                );
            }

            $idempleado =
                (int)$empleado['idempleado'];

            // =================================================
            // COMPROBAR SI YA EXISTE
            // =================================================

            $stmt = $dbEmpresa->prepare("
                SELECT
                    idmarca
                FROM marcas_reloj
                WHERE idempleado = ?
                  AND idlector = ?
                  AND fecha = ?
                  AND hora = ?
                LIMIT 1
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar comprobación de ficha: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'iiss',
                $idempleado,
                $idlector,
                $fecha,
                $hora
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    'Error al comprobar ficha existente: ' .
                    $error
                );
            }

            $resultadoMarca =
                $stmt->get_result();

            $marcaExistente =
                $resultadoMarca->fetch_assoc();

            $stmt->close();

            // =================================================
            // YA EXISTÍA
            // =================================================

            if ($marcaExistente) {

                $yaExistian++;

                $confirmadas[] = [
                    'idfichada' => $idfichada,
                    'idmarca' =>
                        (int)$marcaExistente['idmarca']
                ];

                continue;
            }

            // =================================================
            // INSERTAR MARCA
            // =================================================

            $stmt = $dbEmpresa->prepare("
                INSERT INTO marcas_reloj
                (
                    idempleado,
                    idlector,
                    fecha,
                    hora,
                    fecha_recepcion
                )
                VALUES (?, ?, ?, ?, NOW())
            ");

            if (!$stmt) {

                throw new Exception(
                    'Error al preparar inserción de marca: ' .
                    $dbEmpresa->error
                );
            }

            $stmt->bind_param(
                'iiss',
                $idempleado,
                $idlector,
                $fecha,
                $hora
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;
                $stmt->close();

                throw new Exception(
                    'Error al insertar marca: ' . $error
                );
            }

            $idmarca =
                (int)$stmt->insert_id;

            $stmt->close();

            $insertadas++;

            $confirmadas[] = [
                'idfichada' => $idfichada,
                'idmarca' => $idmarca
            ];

        } catch (Throwable $e) {

            // =================================================
            // ERROR SOLAMENTE EN ESTA FICHADA
            // =================================================

            $errores[] = [
                'idfichada' => $idfichada,
                'deviceUserId' => $deviceUserId,
                'idlector' => $idlector,
                'fecha' => $fecha,
                'hora' => $hora,
                'error' => $e->getMessage()
            ];
        }
    }

    $dbEmpresa->close();

    // =====================================================
    // 11. RESPUESTA SIEMPRE OK CUANDO LA PETICIÓN
    //     FUE PROCESADA
    // =====================================================

    echo json_encode([
        'status' => 'ok',

        'cantidad' =>
            count($confirmadas) +
            count($errores),

        'insertadas' =>
            $insertadas,

        'ya_existian' =>
            $yaExistian,

        'confirmadas' =>
            $confirmadas,

        'errores' =>
            $errores
    ], JSON_UNESCAPED_UNICODE);

    exit;

} catch (Throwable $e) {

    if (
        isset($dbEmpresa) &&
        $dbEmpresa instanceof mysqli
    ) {
        @$dbEmpresa->close();
    }

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

    exit;
}