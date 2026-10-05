<?php

// =========================================================
// CONFIGURACIÓN
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // El entorno puede ya estar cargado.
}

require_once $rutas['conexion'];
require_once $rutas['configuracion'];

header('Content-Type: application/json; charset=utf-8');

$valor = obtenerConfiguracionSistema(
    $mysqli,
    'agent_online_timeout',
    90
);

// =========================================================
// RESPUESTA
// =========================================================

function responder($status, $datos = [], $httpCode = 200)
{
    http_response_code($httpCode);

    echo json_encode(
        array_merge(
            ['status' => $status],
            $datos
        ),
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


// =========================================================
// MÉTODO
// =========================================================

$metodo = $_SERVER['REQUEST_METHOD'];


// =========================================================
// POST DEL AGENT
//
// IMPORTANTE:
// Este bloque conserva el funcionamiento del código
// original que ya funcionaba.
//
// El Agent se autentica con:
// agent_id + token
//
// NO utiliza el token SSO.
// =========================================================

if ($metodo === 'POST') {

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        responder(
            'error',
            ['msg' => 'JSON inválido.'],
            400
        );
    }


    $agentId = trim(
        (string)($input['agent_id'] ?? '')
    );

    $agentToken = (string)(
        $input['token'] ?? ''
    );


    /*
     * Si vienen agent_id + token, estamos ante una
     * llamada del Agent.
     *
     * Se mantiene el comportamiento original.
     */
    if ($agentId !== '' && $agentToken !== '') {

        // =================================================
        // DATOS DEL AGENT
        // =================================================

        $stmt = $mysqli->prepare(
            "
            SELECT
                idagente,
                agent_id,
                nombre,
                idempresa,
                activo,
                token_hash
            FROM agentes
            WHERE agent_id = ?
            LIMIT 1
            "
        );

        if (!$stmt) {
            responder(
                'error',
                ['msg' => 'Error preparando autenticación.'],
                500
            );
        }

        $stmt->bind_param(
            's',
            $agentId
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $agente = $resultado->fetch_assoc();

        $stmt->close();


        if (!$agente) {
            responder(
                'error',
                ['msg' => 'Agent no encontrado.'],
                401
            );
        }


        if ((int)$agente['activo'] !== 1) {
            responder(
                'error',
                ['msg' => 'El Agent está desactivado.'],
                403
            );
        }


        if (!password_verify(
            $agentToken,
            $agente['token_hash']
        )) {
            responder(
                'error',
                ['msg' => 'Token de Agent inválido.'],
                401
            );
        }


        // =================================================
        // ACTUALIZAR ACTIVIDAD DEL AGENT
        // =================================================

        $stmt = $mysqli->prepare(
            "
            UPDATE agentes
            SET
                ultimo_acceso = NOW(),
                ultima_actividad = NOW(),
                ultimo_error = NULL
            WHERE idagente = ?
            "
        );

        if ($stmt) {

            $idAgente = (int)$agente['idagente'];

            $stmt->bind_param(
                'i',
                $idAgente
            );

            $stmt->execute();

            $stmt->close();
        }


        // =================================================
        // ACCIÓN DEL AGENT
        // =================================================

        $accion = strtolower(
            trim(
                (string)($input['accion'] ?? '')
            )
        );


        // =================================================
        // INFORMAR INCIDENCIA
        // =================================================

        if ($accion === 'informar') {

            $tipo = trim(
                (string)($input['tipo'] ?? '')
            );

            $codigo = trim(
                (string)($input['codigo'] ?? '')
            );

            $severidad = strtolower(
                trim(
                    (string)($input['severidad'] ?? 'error')
                )
            );

            $mensaje = trim(
                (string)($input['mensaje'] ?? '')
            );

            $detalle = $input['detalle'] ?? null;

            $contexto = $input['contexto'] ?? null;

            $idlector =
                isset($input['idlector']) &&
                $input['idlector'] !== ''
                    ? (int)$input['idlector']
                    : null;


            // -------------------------------------------------
            // VALIDACIONES
            // -------------------------------------------------

            if ($tipo === '') {
                responder(
                    'error',
                    ['msg' => 'Falta tipo de incidencia.'],
                    400
                );
            }

            if ($codigo === '') {
                responder(
                    'error',
                    ['msg' => 'Falta código de incidencia.'],
                    400
                );
            }

            if ($mensaje === '') {
                responder(
                    'error',
                    ['msg' => 'Falta mensaje de incidencia.'],
                    400
                );
            }


            $severidadesPermitidas = [
                'info',
                'advertencia',
                'error',
                'critica'
            ];

            if (!in_array(
                $severidad,
                $severidadesPermitidas,
                true
            )) {
                $severidad = 'error';
            }


            // -------------------------------------------------
            // CONVERTIR DETALLE
            // -------------------------------------------------

            if (
                is_array($detalle) ||
                is_object($detalle)
            ) {

                $detalle = json_encode(
                    $detalle,
                    JSON_UNESCAPED_UNICODE
                );

            } elseif ($detalle !== null) {

                $detalle = (string)$detalle;
            }


            // -------------------------------------------------
            // CONVERTIR CONTEXTO
            // -------------------------------------------------

            if (
                is_array($contexto) ||
                is_object($contexto)
            ) {

                $contexto = json_encode(
                    $contexto,
                    JSON_UNESCAPED_UNICODE
                );

            } elseif ($contexto !== null) {

                $contexto = (string)$contexto;
            }


            // -------------------------------------------------
            // BUSCAR INCIDENCIA ACTIVA
            // -------------------------------------------------

            $stmt = $mysqli->prepare(
                "
                SELECT
                    idincidencia
                FROM incidencias_agentes
                WHERE idagente = ?
                  AND codigo = ?
                  AND estado IN ('abierta', 'reconocida')
                ORDER BY idincidencia DESC
                LIMIT 1
                "
            );

            if (!$stmt) {
                responder(
                    'error',
                    [
                        'msg' =>
                            'Error preparando consulta de incidencia.'
                    ],
                    500
                );
            }

            $idAgente = (int)$agente['idagente'];

            $stmt->bind_param(
                'is',
                $idAgente,
                $codigo
            );

            $stmt->execute();

            $resultado = $stmt->get_result();

            $incidencia = $resultado->fetch_assoc();

            $stmt->close();


            // -------------------------------------------------
            // ACTUALIZAR INCIDENCIA EXISTENTE
            // -------------------------------------------------

            if ($incidencia) {

                $idIncidencia =
                    (int)$incidencia['idincidencia'];

                $stmt = $mysqli->prepare(
                    "
                    UPDATE incidencias_agentes
                    SET
                        idempresa = ?,
                        idlector = ?,
                        tipo = ?,
                        severidad = ?,
                        mensaje = ?,
                        detalle = ?,
                        contexto = ?,
                        cantidad_ocurrencias =
                            cantidad_ocurrencias + 1,
                        ultima_ocurrencia = NOW()
                    WHERE idincidencia = ?
                    "
                );

                if (!$stmt) {
                    responder(
                        'error',
                        [
                            'msg' =>
                                'Error preparando actualización de incidencia.'
                        ],
                        500
                    );
                }

                $idEmpresa =
                    (int)$agente['idempresa'];

                $stmt->bind_param(
                    'iisssssi',
                    $idEmpresa,
                    $idlector,
                    $tipo,
                    $severidad,
                    $mensaje,
                    $detalle,
                    $contexto,
                    $idIncidencia
                );

                if (!$stmt->execute()) {

                    $error = $stmt->error;

                    $stmt->close();

                    responder(
                        'error',
                        [
                            'msg' =>
                                'No se pudo actualizar la incidencia.',
                            'detalle' => $error
                        ],
                        500
                    );
                }

                $stmt->close();


                responder(
                    'ok',
                    [
                        'accion' => 'actualizada',
                        'idincidencia' => $idIncidencia
                    ]
                );
            }


            // -------------------------------------------------
            // CREAR NUEVA INCIDENCIA
            // -------------------------------------------------

            $stmt = $mysqli->prepare(
                "
                INSERT INTO incidencias_agentes
                (
                    idagente,
                    idempresa,
                    idlector,
                    tipo,
                    codigo,
                    severidad,
                    estado,
                    mensaje,
                    detalle,
                    contexto
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, 'abierta', ?, ?, ?
                )
                "
            );

            if (!$stmt) {
                responder(
                    'error',
                    [
                        'msg' =>
                            'Error preparando creación de incidencia.'
                    ],
                    500
                );
            }

            $idEmpresa =
                (int)$agente['idempresa'];

            $stmt->bind_param(
                'iiissssss',
                $idAgente,
                $idEmpresa,
                $idlector,
                $tipo,
                $codigo,
                $severidad,
                $mensaje,
                $detalle,
                $contexto
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responder(
                    'error',
                    [
                        'msg' =>
                            'No se pudo crear la incidencia.',
                        'detalle' => $error
                    ],
                    500
                );
            }

            $idIncidencia =
                (int)$stmt->insert_id;

            $stmt->close();


            responder(
                'ok',
                [
                    'accion' => 'creada',
                    'idincidencia' => $idIncidencia
                ]
            );
        }


        // =================================================
        // RECUPERAR INCIDENCIA
        // =================================================

        if ($accion === 'recuperar') {

            $codigo = trim(
                (string)($input['codigo'] ?? '')
            );

            if ($codigo === '') {
                responder(
                    'error',
                    ['msg' => 'Falta código de incidencia.'],
                    400
                );
            }


            $resolucion = trim(
                (string)(
                    $input['resolucion'] ??
                    'El problema se recuperó automáticamente.'
                )
            );


            $stmt = $mysqli->prepare(
                "
                UPDATE incidencias_agentes
                SET
                    estado = 'resuelta',
                    fecha_resolucion = NOW(),
                    resolucion = ?
                WHERE idagente = ?
                  AND codigo = ?
                  AND estado IN ('abierta', 'reconocida')
                "
            );

            if (!$stmt) {
                responder(
                    'error',
                    [
                        'msg' =>
                            'Error preparando recuperación.'
                    ],
                    500
                );
            }

            $idAgente =
                (int)$agente['idagente'];

            $stmt->bind_param(
                'sis',
                $resolucion,
                $idAgente,
                $codigo
            );

            if (!$stmt->execute()) {

                $error = $stmt->error;

                $stmt->close();

                responder(
                    'error',
                    [
                        'msg' =>
                            'No se pudo recuperar la incidencia.',
                        'detalle' => $error
                    ],
                    500
                );
            }

            $afectadas =
                $stmt->affected_rows;

            $stmt->close();


            responder(
                'ok',
                [
                    'accion' => 'recuperada',
                    'cantidad' => $afectadas
                ]
            );
        }


        responder(
            'error',
            [
                'msg' =>
                    'Acción inválida. Use informar o recuperar.'
            ],
            400
        );
    }


    // =====================================================
    // POST SSO
    //
    // Si no vienen agent_id + token, el POST pertenece
    // al usuario del portal.
    // =====================================================

    require_once $rutas['middleware'];
    require_once $rutas['contexto'];


    $userAuth =
        validarTokenAPI($mysqli);


    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    $empresa =
        obtenerEmpresaActual(
            $mysqli,
            $userAuth
        );


    if (
        !$empresa ||
        empty($empresa['idempresa'])
    ) {

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo determinar la empresa activa.'
            ],
            403
        );
    }


    $idEmpresa =
        (int)$empresa['idempresa'];


    // =====================================================
    // ACCIÓN SSO
    // =====================================================

    $accion = trim(
        (string)(
            $input['accion'] ?? ''
        )
    );


    if ($accion !== 'reintentar_fichada') {

        responder(
            'error',
            [
                'msg' =>
                    'Acción no válida.'
            ],
            400
        );
    }


    // =====================================================
    // ID INCIDENCIA
    // =====================================================

    $idIncidencia = filter_var(
        $input['idincidencia'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $idIncidencia === false ||
        $idIncidencia <= 0
    ) {

        responder(
            'error',
            [
                'msg' =>
                    'Incidencia inválida.'
            ],
            400
        );
    }


    try {

        // =================================================
        // OBTENER INCIDENCIA
        // =================================================

        $stmt = $mysqli->prepare(
            "
            SELECT
                i.idincidencia,
                i.idagente,
                i.idempresa,
                i.idlector,
                i.estado,
                i.codigo,
                i.contexto,
                a.activo AS agente_activo,
                a.agent_id,
                a.nombre AS agent_nombre
            FROM incidencias_agentes i
            INNER JOIN agentes a
                ON a.idagente = i.idagente
            WHERE i.idincidencia = ?
              AND i.idempresa = ?
            LIMIT 1
            "
        );

        if (!$stmt) {
            throw new Exception(
                'Error preparando incidencia.'
            );
        }

        $stmt->bind_param(
            'ii',
            $idIncidencia,
            $idEmpresa
        );

        $stmt->execute();

        $resultado =
            $stmt->get_result();

        $incidencia =
            $resultado->fetch_assoc();

        $stmt->close();


        if (!$incidencia) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia no existe o no pertenece a la empresa activa.'
                ],
                404
            );
        }


        // =================================================
        // ESTADO
        // =================================================

        if (
            !in_array(
                $incidencia['estado'],
                [
                    'abierta',
                    'reconocida'
                ],
                true
            )
        ) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia ya no se encuentra pendiente de resolución.'
                ],
                409
            );
        }


        // =================================================
        // AGENT
        // =================================================

        $idAgente =
            (int)$incidencia['idagente'];

        if ($idAgente <= 0) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia no tiene un Agent válido asignado.'
                ],
                409
            );
        }


        if (
            (int)$incidencia['agente_activo'] !== 1
        ) {

            responder(
                'error',
                [
                    'msg' =>
                        'El Agent asociado a la incidencia está desactivado.'
                ],
                409
            );
        }


        // =================================================
        // CONTEXTO
        // =================================================

        $contexto = json_decode(
            $incidencia['contexto'] ?? '',
            true
        );

        if (!is_array($contexto)) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia no contiene un contexto válido.'
                ],
                409
            );
        }


        // =================================================
        // FICHADA
        // =================================================

        $idFichada = filter_var(
            $contexto['idfichada'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $idFichada === false ||
            $idFichada <= 0
        ) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia no identifica una fichada válida.'
                ],
                409
            );
        }


        // =================================================
        // LECTOR
        // =================================================

        $idLector = filter_var(
            $incidencia['idlector'] ?? null,
            FILTER_VALIDATE_INT
        );

        if (
            $idLector === false ||
            $idLector <= 0
        ) {

            $idLector = filter_var(
                $contexto['idlector'] ?? null,
                FILTER_VALIDATE_INT
            );
        }

        if (
            $idLector === false ||
            $idLector <= 0
        ) {

            responder(
                'error',
                [
                    'msg' =>
                        'La incidencia no identifica un lector válido.'
                ],
                409
            );
        }


        // =================================================
        // DB EMPRESA
        // =================================================

        $mysqliEmpresa =
            conectarDBEmpresa(
                $mysqli,
                $idEmpresa,
                'DATOS'
            );


        // =================================================
        // VALIDAR LECTOR -> AGENT
        // =================================================

        $stmtLector =
            $mysqliEmpresa->prepare(
                "
                SELECT
                    idlector
                FROM lectores
                WHERE idlector = ?
                  AND idagente = ?
                  AND activo = 1
                LIMIT 1
                "
            );

        if (!$stmtLector) {
            throw new Exception(
                'Error preparando validación del lector.'
            );
        }

        $stmtLector->bind_param(
            'ii',
            $idLector,
            $idAgente
        );

        $stmtLector->execute();

        $resultadoLector =
            $stmtLector->get_result();

        $lector =
            $resultadoLector->fetch_assoc();

        $stmtLector->close();


        if (!$lector) {

            responder(
                'error',
                [
                    'msg' =>
                        'El lector de la incidencia no está asignado al Agent correspondiente.'
                ],
                409
            );
        }


        // =================================================
        // DATOS TAREA
        // =================================================

        $datosTarea = [
            'idfichada' =>
                (int)$idFichada,

            'idincidencia' =>
                (int)$idIncidencia
        ];

        $datosJson = json_encode(
            $datosTarea,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


        // =================================================
        // EVITAR DUPLICADOS
        // =================================================

        $stmtDuplicada =
            $mysqliEmpresa->prepare(
                "
                SELECT
                    idtarea
                FROM tareas_agente
                WHERE idagente = ?
                  AND accion = 'reintentar_fichada'
                  AND estado IN (
                      'pendiente',
                      'procesando'
                  )
                  AND JSON_VALID(datos) = 1
                  AND CAST(
                      JSON_UNQUOTE(
                          JSON_EXTRACT(
                              datos,
                              '$.idfichada'
                          )
                      ) AS UNSIGNED
                  ) = ?
                LIMIT 1
                "
            );

        if (!$stmtDuplicada) {
            throw new Exception(
                'Error preparando validación de tarea duplicada.'
            );
        }

        $stmtDuplicada->bind_param(
            'ii',
            $idAgente,
            $idFichada
        );

        $stmtDuplicada->execute();

        $resultadoDuplicada =
            $stmtDuplicada->get_result();

        $tareaExistente =
            $resultadoDuplicada->fetch_assoc();

        $stmtDuplicada->close();


        if ($tareaExistente) {

            responder(
                'ok',
                [
                    'msg' =>
                        'El reintento ya fue enviado al Agent.',

                    'idincidencia' =>
                        (int)$idIncidencia,

                    'idfichada' =>
                        (int)$idFichada,

                    'idagente' =>
                        $idAgente,

                    'idtarea' =>
                        (int)$tareaExistente['idtarea'],

                    'ya_existia' =>
                        true
                ]
            );
        }


        // =================================================
        // CREAR TAREA
        // =================================================

        $stmtTarea =
            $mysqliEmpresa->prepare(
                "
                INSERT INTO tareas_agente
                (
                    idagente,
                    idlector,
                    idempleado,
                    accion,
                    datos,
                    estado
                )
                VALUES
                (
                    ?,
                    ?,
                    NULL,
                    'reintentar_fichada',
                    ?,
                    'pendiente'
                )
                "
            );

        if (!$stmtTarea) {
            throw new Exception(
                'Error preparando creación de tarea.'
            );
        }

        $stmtTarea->bind_param(
            'iis',
            $idAgente,
            $idLector,
            $datosJson
        );

        if (!$stmtTarea->execute()) {

            $error =
                $stmtTarea->error;

            $stmtTarea->close();

            throw new Exception(
                'No se pudo crear la tarea: ' .
                $error
            );
        }

        $idTarea =
            (int)$stmtTarea->insert_id;

        $stmtTarea->close();


        // =================================================
        // RESPUESTA
        // =================================================

        responder(
            'ok',
            [
                'msg' =>
                    'El reintento fue enviado al Agent.',

                'idincidencia' =>
                    (int)$idIncidencia,

                'idfichada' =>
                    (int)$idFichada,

                'idagente' =>
                    $idAgente,

                'idtarea' =>
                    $idTarea,

                'ya_existia' =>
                    false
            ]
        );


    } catch (Throwable $e) {

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo crear el reintento.',
                'detalle' =>
                    $e->getMessage()
            ],
            500
        );
    }
}


// =========================================================
// GET DEL USUARIO SSO
// =========================================================

if ($metodo === 'GET') {

    require_once $rutas['middleware'];
    require_once $rutas['contexto'];


    $userAuth =
        validarTokenAPI($mysqli);


    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    $empresa =
        obtenerEmpresaActual(
            $mysqli,
            $userAuth
        );


    if (
        !$empresa ||
        empty($empresa['idempresa'])
    ) {

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo determinar la empresa activa.'
            ],
            403
        );
    }


    $idEmpresa =
        (int)$empresa['idempresa'];


    try {

        // =================================================
        // AGENTS
        // =================================================

        $stmtAgents =
            $mysqli->prepare(
                "
                SELECT
                    a.idagente,
                    a.agent_id,
                    a.nombre,
                    a.activo,
                    a.ultimo_acceso,
                    a.ultima_actividad,
                    a.ultimo_error
                FROM agentes a
                WHERE a.idempresa = ?
                ORDER BY a.nombre ASC
                "
            );

        if (!$stmtAgents) {
            throw new Exception(
                'Error preparando consulta de Agents.'
            );
        }

        $stmtAgents->bind_param(
            'i',
            $idEmpresa
        );

        $stmtAgents->execute();

        $resultadoAgents =
            $stmtAgents->get_result();

        $agents = [];

        $ahora = time();

        $agenteOnline = false;

        while (
            $agent =
            $resultadoAgents->fetch_assoc()
        ) {

            $ultimaActividad =
                !empty($agent['ultima_actividad'])
                    ? strtotime(
                        $agent['ultima_actividad']
                    )
                    : 0;

            $online =
                (
                    (int)$agent['activo'] === 1 &&
                    $ultimaActividad > 0 &&
                    (
                        $ahora -
                        $ultimaActividad
                    ) <= $valor
                )
                    ? 1
                    : 0;

            $agent['online'] =
                $online;

            if ($online === 1) {
                $agenteOnline = true;
            }

            $agent['activo'] =
                (bool)$agent['activo'];

            $agents[] =
                $agent;
        }

        $stmtAgents->close();


        // =================================================
        // RESUMEN
        // =================================================

        $summary = [
            'total' => 0,
            'abiertas' => 0,
            'reconocidas' => 0,
            'resueltas' => 0
        ];

        $stmtSummary =
            $mysqli->prepare(
                "
                SELECT
                    estado,
                    COUNT(*) AS cantidad
                FROM incidencias_agentes
                WHERE idempresa = ?
                GROUP BY estado
                "
            );

        if (!$stmtSummary) {
            throw new Exception(
                'Error preparando resumen de incidencias.'
            );
        }

        $stmtSummary->bind_param(
            'i',
            $idEmpresa
        );

        $stmtSummary->execute();

        $resultadoSummary =
            $stmtSummary->get_result();

        while (
            $fila =
            $resultadoSummary->fetch_assoc()
        ) {

            $estado =
                $fila['estado'];

            if (
                array_key_exists(
                    $estado,
                    $summary
                )
            ) {

                $summary[$estado] =
                    (int)$fila['cantidad'];
            }
        }

        $summary['total'] =
            $summary['abiertas'] +
            $summary['reconocidas'] +
            $summary['resueltas'];

        $stmtSummary->close();


        // =================================================
        // INCIDENCIAS ACTIVAS
        // =================================================

        $stmtIncidencias =
            $mysqli->prepare(
                "
                SELECT
                    i.idincidencia,
                    i.idagente,
                    i.idempresa,
                    i.idlector,
                    i.tipo,
                    i.codigo,
                    i.severidad,
                    i.estado,
                    i.mensaje,
                    i.detalle,
                    i.contexto,
                    i.cantidad_ocurrencias,
                    i.primera_ocurrencia,
                    i.ultima_ocurrencia,
                    i.fecha_reconocida,
                    i.idusuario_reconocida,
                    a.agent_id,
                    a.nombre AS agente_nombre,
                    a.nombre AS agent_nombre
                FROM incidencias_agentes i
                INNER JOIN agentes a
                    ON a.idagente = i.idagente
                WHERE i.idempresa = ?
                  AND i.estado IN (
                      'abierta',
                      'reconocida'
                  )
                ORDER BY
                    CASE i.severidad
                        WHEN 'critica' THEN 1
                        WHEN 'error' THEN 2
                        WHEN 'advertencia' THEN 3
                        WHEN 'info' THEN 4
                        ELSE 5
                    END,
                    i.ultima_ocurrencia DESC
                LIMIT 100
                "
            );

        if (!$stmtIncidencias) {
            throw new Exception(
                'Error preparando listado de incidencias.'
            );
        }

        $stmtIncidencias->bind_param(
            'i',
            $idEmpresa
        );

        $stmtIncidencias->execute();

        $resultadoIncidencias =
            $stmtIncidencias->get_result();

        $incidencias = [];

        while (
            $incidencia =
            $resultadoIncidencias->fetch_assoc()
        ) {

            // ---------------------------------------------
            // ID FICHADA DESDE CONTEXTO
            // ---------------------------------------------

            $incidencia['idfichada'] =
                null;

            if (
                !empty(
                    $incidencia['contexto']
                )
            ) {

                $contexto =
                    json_decode(
                        $incidencia['contexto'],
                        true
                    );

                if (
                    is_array($contexto) &&
                    isset(
                        $contexto['idfichada']
                    )
                ) {

                    $idFichada =
                        filter_var(
                            $contexto['idfichada'],
                            FILTER_VALIDATE_INT
                        );

                    if (
                        $idFichada !== false &&
                        $idFichada > 0
                    ) {

                        $incidencia['idfichada'] =
                            (int)$idFichada;
                    }
                }
            }


            $incidencias[] =
                $incidencia;
        }

        $stmtIncidencias->close();


        // =================================================
        // RESPUESTA
        //
        // SE MANTIENEN LOS NOMBRES DEL API ORIGINAL:
        // agente_online
        // agentes
        // resumen
        // incidencias
        // =================================================

        echo json_encode(
            [
                'status' => 'ok',

                'empresa' => [
                    'idempresa' =>
                        $idEmpresa,

                    'nombre' =>
                        $empresa['nombre'] ?? ''
                ],

                'agente_online' =>
                    $agenteOnline,

                'agentes' =>
                    $agents,

                'resumen' =>
                    $summary,

                'incidencias' =>
                    $incidencias
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;


    } catch (Throwable $e) {

        responder(
            'error',
            [
                'msg' =>
                    'Error consultando incidencias.',
                'detalle' =>
                    $e->getMessage()
            ],
            500
        );
    }
}


// =========================================================
// MÉTODO NO PERMITIDO
// =========================================================

responder(
    'error',
    [
        'msg' =>
            'Método no permitido.'
    ],
    405
);