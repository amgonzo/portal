<?php

/**
 * Módulo de persistencia biométrica.
 *
 * Este archivo NO es un endpoint HTTP.
 *
 * Su responsabilidad es recibir el resultado de un
 * enrolamiento y guardar la plantilla biométrica
 * en empleados_datos_biometricos.
 */


/**
 * Guarda una huella obtenida desde el resultado
 * de una tarea de enrolamiento.
 *
 * La transacción es responsabilidad del llamador.
 *
 * @param mysqli $dbEmpresa
 * @param int    $idEmpleado
 * @param string $respuestaJson
 *
 * @return array
 *
 * @throws Exception
 */
function guardarHuellaDesdeResultado(
    mysqli $dbEmpresa,
    int $idEmpleado,
    string $respuestaJson
): array {

    // =====================================================
    // VALIDAR EMPLEADO
    // =====================================================

    if ($idEmpleado <= 0) {

        throw new Exception(
            'Empleado inválido para guardar la huella.'
        );
    }

    // =====================================================
    // DECODIFICAR RESPUESTA
    // =====================================================

    $respuesta =
        json_decode(
            $respuestaJson,
            true
        );

    if (!is_array($respuesta)) {

        throw new Exception(
            'La respuesta del enrolamiento no tiene un formato válido.'
        );
    }

    // =====================================================
    // VALIDAR RESULTADO
    // =====================================================

    if (
        empty($respuesta['ok'])
    ) {

        throw new Exception(
            'El enrolamiento de la huella no fue exitoso.'
        );
    }

    // =====================================================
    // DATOS DE LA HUELLA
    // =====================================================

    $idDedo = isset($respuesta['dedo_lector'])
        ? (int)$respuesta['dedo_lector']
        : (
            isset($respuesta['iddedo'])
                ? (int)$respuesta['iddedo']
                : -1
        );

    if (
        $idDedo < 0 ||
        $idDedo > 9
    ) {

        throw new Exception(
            'El dedo recibido desde el lector no es válido.'
        );
    }

    // =====================================================
    // USUARIO DEL LECTOR
    // =====================================================

    $idUsuario = isset($respuesta['usuario_lector'])
        ? (int)$respuesta['usuario_lector']
        : 0;

    if ($idUsuario <= 0) {

        /*
         * Compatibilidad con respuestas que puedan venir
         * usando "usuario".
         */
        $idUsuario = isset($respuesta['usuario'])
            ? (int)$respuesta['usuario']
            : 0;
    }

    if ($idUsuario <= 0) {

        throw new Exception(
            'No se recibió el usuario del lector.'
        );
    }

    // =====================================================
    // VALIDAR PLANTILLA
    // =====================================================

    if (
        !isset($respuesta['template'])
    ) {

        throw new Exception(
            'La respuesta no contiene la plantilla de huella.'
        );
    }

    $template =
        $respuesta['template'];

    /*
     * zklib / Node devuelve el Buffer serializado
     * por JSON de esta forma:
     *
     * {
     *     "type": "Buffer",
     *     "data": [76,7,83,...]
     * }
     */

    if (
        !is_array($template) ||
        !isset($template['data']) ||
        !is_array($template['data'])
    ) {

        throw new Exception(
            'La plantilla de huella no tiene un formato Buffer válido.'
        );
    }

    $bytes =
        $template['data'];

    if (
        count($bytes) === 0
    ) {

        throw new Exception(
            'La plantilla de huella está vacía.'
        );
    }

    // =====================================================
    // CONVERTIR BUFFER A BINARIO
    // =====================================================

    $binario = '';

    foreach ($bytes as $byte) {

        $byte = (int)$byte;

        if (
            $byte < 0 ||
            $byte > 255
        ) {

            throw new Exception(
                'La plantilla contiene un byte inválido.'
            );
        }

        $binario .= chr($byte);
    }

    if (
        $binario === ''
    ) {

        throw new Exception(
            'No se pudo reconstruir la plantilla biométrica.'
        );
    }

    // =====================================================
    // CONVERTIR A BASE64
    // =====================================================
    //
    // datos es LONGTEXT.
    //
    // Guardamos la plantilla en Base64 para evitar
    // problemas con bytes binarios dentro de UTF-8.
    //

    $datosBase64 =
        base64_encode(
            $binario
        );

    if (
        $datosBase64 === false ||
        $datosBase64 === ''
    ) {

        throw new Exception(
            'No se pudo codificar la plantilla biométrica.'
        );
    }

    // =====================================================
    // VERIFICAR EMPLEADO
    // =====================================================

    $stmt = $dbEmpresa->prepare("
        SELECT
            idempleado,
            documento,
            activo
        FROM empleados
        WHERE idempleado = ?
        LIMIT 1
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar consulta de empleado: ' .
            $dbEmpresa->error
        );
    }

    $stmt->bind_param(
        'i',
        $idEmpleado
    );

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'Error al consultar empleado: ' .
            $error
        );
    }

    $resultado =
        $stmt->get_result();

    $empleado =
        $resultado->fetch_assoc();

    $stmt->close();

    if (!$empleado) {

        throw new Exception(
            'El empleado asociado a la huella no existe.'
        );
    }

    if (
        (int)$empleado['activo'] !== 1
    ) {

        throw new Exception(
            'El empleado asociado a la huella está inactivo.'
        );
    }

    // =====================================================
    // DESACTIVAR HUELLA ACTIVA ANTERIOR
    // =====================================================
    //
    // Permitimos conservar el histórico.
    //
    // Si se vuelve a enrolar el mismo dedo:
    //
    // anterior -> activo = 0
    // nueva    -> activo = 1
    //

    $stmt = $dbEmpresa->prepare("
        UPDATE empleados_datos_biometricos
        SET
            activo = 0
        WHERE idempleado = ?
          AND tipo = 'huella'
          AND dedo = ?
          AND activo = 1
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar desactivación de huella anterior: ' .
            $dbEmpresa->error
        );
    }

    $dedoTexto =
        (string)$idDedo;

    $stmt->bind_param(
        'is',
        $idEmpleado,
        $dedoTexto
    );

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'No se pudo desactivar la huella anterior: ' .
            $error
        );
    }

    $stmt->close();

    // =====================================================
    // INSERTAR NUEVA HUELLA
    // =====================================================

    $tipo =
        'huella';

    $stmt = $dbEmpresa->prepare("
        INSERT INTO empleados_datos_biometricos
        (
            idempleado,
            tipo,
            dedo,
            datos,
            idusuario,
            fecha_carga,
            activo
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            1
        )
    ");

    if (!$stmt) {

        throw new Exception(
            'Error al preparar guardado de huella: ' .
            $dbEmpresa->error
        );
    }

    $stmt->bind_param(
        'isssi',
        $idEmpleado,
        $tipo,
        $dedoTexto,
        $datosBase64,
        $idUsuario
    );

    if (!$stmt->execute()) {

        $error =
            $stmt->error;

        $stmt->close();

        throw new Exception(
            'No se pudo guardar la huella: ' .
            $error
        );
    }

    $idBiometrico =
        (int)$stmt->insert_id;

    $stmt->close();

    // =====================================================
    // RESULTADO
    // =====================================================

    return [

        'ok' => true,

        'idbiometrico' =>
            $idBiometrico,

        'idempleado' =>
            $idEmpleado,

        'documento' =>
            $empleado['documento'],

        'tipo' =>
            $tipo,

        'dedo' =>
            $idDedo,

        'idusuario' =>
            $idUsuario,

        'bytes' =>
            strlen($binario),

        'datos_base64' =>
            strlen($datosBase64)

    ];
}