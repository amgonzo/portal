<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];
require_once $rutas['conexion'];
//require_once $rutas['middleware'];

header('Content-Type: application/json; charset=utf-8');

try {

    $input = json_decode(file_get_contents('php://input'), true);

    if (!is_array($input)) {
        throw new Exception('Datos inválidos');
    }

    $idempleado = isset($input['idempleado'])
        ? (int)$input['idempleado']
        : 0;

    $accion = isset($input['accion'])
        ? trim($input['accion'])
        : '';

    $idlectores = $input['idlectores'] ?? [];

    if ($idempleado <= 0) {
        throw new Exception('Empleado inválido');
    }

    if ($accion === '') {
        throw new Exception('Acción obligatoria');
    }

    if (!is_array($idlectores) || count($idlectores) === 0) {
        throw new Exception('Debe indicar al menos un reloj');
    }

    /*
     * Acciones permitidas
     */
    $accionesPermitidas = [
        'crear_empleado',
        'actualizar_empleado',
        'eliminar_empleado',
        'capturar_huella',
        'capturar_rostro',
        'eliminar_biometria',
        'sincronizar_empleados'
    ];

    if (!in_array($accion, $accionesPermitidas, true)) {
        throw new Exception('Acción no permitida');
    }

    /*
     * Usuario autenticado
     */
    $idusuario = isset($_SESSION['idusuario'])
        ? (int)$_SESSION['idusuario']
        : null;

    /*
     * Verificar empleado
     */
    $stmt = $mysqli->prepare("
        SELECT idempleado
        FROM empleados
        WHERE idempleado = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $idempleado);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if (!$resultado->fetch_assoc()) {
        throw new Exception('El empleado no existe');
    }

    $stmt->close();

    /*
     * Insertar una tarea por cada reloj
     */
    $stmt = $mysqli->prepare("
        INSERT INTO tareas_agente
        (
            idlector,
            idempleado,
            accion,
            datos,
            estado
        )
        VALUES (?, ?, ?, ?, 'pendiente')
    ");

    if (!$stmt) {
        throw new Exception('No se pudo preparar la tarea');
    }

    $creadas = [];

    foreach ($idlectores as $idlector) {

        $idlector = (int)$idlector;

        if ($idlector <= 0) {
            continue;
        }

        /*
         * Verificar que el reloj existe
         */
        $check = $mysqli->prepare("
            SELECT idlector
            FROM lectores
            WHERE idlector = ?
            LIMIT 1
        ");

        $check->bind_param('i', $idlector);
        $check->execute();

        $res = $check->get_result();

        if (!$res->fetch_assoc()) {
            $check->close();
            continue;
        }

        $check->close();

        /*
         * Datos adicionales de la tarea
         */
        $datos = json_encode(
            [
                'idempleado' => $idempleado
            ],
            JSON_UNESCAPED_UNICODE
        );

        $stmt->bind_param(
            'iiss',
            $idlector,
            $idempleado,
            $accion,
            $datos
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al crear una tarea');
        }

        $creadas[] = [
            'idtarea' => $stmt->insert_id,
            'idlector' => $idlector,
            'idempleado' => $idempleado,
            'accion' => $accion
        ];
    }

    $stmt->close();

    if (count($creadas) === 0) {
        throw new Exception('No se pudo crear ninguna tarea');
    }

    echo json_encode([
        'status' => 'ok',
        'msg' => 'Tareas creadas correctamente',
        'tareas' => $creadas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'msg' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}