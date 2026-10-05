<?php

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['contexto'];
require_once $rutas['configuracion'];

header('Content-Type: application/json; charset=utf-8');

$valor = obtenerConfiguracionSistema(
    $mysqli,
    'agent_online_timeout',
    90
);

/*
|--------------------------------------------------------------------------
| RESPUESTA
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| TOKEN / USUARIO
|--------------------------------------------------------------------------
*/

$userAuth = validarTokenAPI($mysqli);


/*
|--------------------------------------------------------------------------
| MÉTODO
|--------------------------------------------------------------------------
*/

$metodo = strtoupper(
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);


/*
|--------------------------------------------------------------------------
| POST - REINTENTAR FICHADA
|--------------------------------------------------------------------------
*/

if ($metodo === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | LEER JSON
    |--------------------------------------------------------------------------
    */

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        $input = [];
    }

    $accion = trim(
        $input['accion'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDAR PERMISO
    |--------------------------------------------------------------------------
    */

    validarPermisoEndpoint(
        $mysqli,
        $userAuth
    );


    /*
    |--------------------------------------------------------------------------
    | EMPRESA ACTUAL
    |--------------------------------------------------------------------------
    */

    $empresa = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    if (!$empresa) {

        responder(
            'error',
            [
                'msg' => 'No se pudo determinar la empresa actual.'
            ],
            400
        );
    }

    $idEmpresa = (int)$empresa['idempresa'];


    /*
    |--------------------------------------------------------------------------
    | ACCIÓN: REINTENTAR FICHADA
    |--------------------------------------------------------------------------
    */

    if ($accion !== 'reintentar_fichada') {

        responder(
            'error',
            [
                'msg' => 'Acción no válida.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ID INCIDENCIA
    |--------------------------------------------------------------------------
    */

    $idIncidencia = (int)(
        $input['idincidencia'] ?? 0
    );

    if ($idIncidencia <= 0) {

        responder(
            'error',
            [
                'msg' => 'ID de incidencia inválido.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OBTENER INCIDENCIA + AGENT
    |--------------------------------------------------------------------------
    */

    $stmt = $mysqli->prepare("
        SELECT
            i.idincidencia,
            i.idagente,
            i.idempresa,
            i.idlector,
            i.estado,
            i.contexto,
            a.nombre AS agente_nombre,
            a.activo AS agente_activo
        FROM incidencias_agentes i

        INNER JOIN agentes a
            ON a.idagente = i.idagente

        WHERE i.idincidencia = ?
          AND i.idempresa = ?

        LIMIT 1
    ");

    if (!$stmt) {

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo preparar la consulta de la incidencia.'
            ],
            500
        );
    }

    $stmt->bind_param(
        'ii',
        $idIncidencia,
        $idEmpresa
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo consultar la incidencia.',
                'detalle' =>
                    $error
            ],
            500
        );
    }

    $incidencia = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if (!$incidencia) {

        responder(
            'error',
            [
                'msg' => 'Incidencia no encontrada.'
            ],
            404
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR ESTADO
    |--------------------------------------------------------------------------
    |
    | Solamente se puede reintentar una incidencia
    | que todavía esté abierta o reconocida.
    |
    */

    if (
        !in_array(
            $incidencia['estado'],
            ['abierta', 'reconocida'],
            true
        )
    ) {

        responder(
            'error',
            [
                'msg' =>
                    'La incidencia ya está resuelta.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR AGENT
    |--------------------------------------------------------------------------
    */

    if ((int)$incidencia['agente_activo'] !== 1) {

        responder(
            'error',
            [
                'msg' =>
                    'El Agent asociado a la incidencia está inactivo.'
            ],
            400
        );
    }


    $idAgente = (int)$incidencia['idagente'];

    $idLector = $incidencia['idlector'] !== null
        ? (int)$incidencia['idlector']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | OBTENER ID FICHADA
    |--------------------------------------------------------------------------
    */

    $idfichada = 0;

    if (!empty($incidencia['contexto'])) {

        $contexto = json_decode(
            $incidencia['contexto'],
            true
        );

        if (is_array($contexto)) {

            $idfichada = (int)(
                $contexto['idfichada'] ?? 0
            );
        }
    }


    if ($idfichada <= 0) {

        responder(
            'error',
            [
                'msg' =>
                    'La incidencia no contiene una fichada válida para reintentar.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CONECTAR A LA BD DE LA EMPRESA
    |--------------------------------------------------------------------------
    */

    $mysqliEmpresa = conectarDBEmpresa(
        $mysqli,
        $idEmpresa,
        'DATOS'
    );

    if (!$mysqliEmpresa) {

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo conectar a la base de datos de la empresa.'
            ],
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR LECTOR
    |--------------------------------------------------------------------------
    */

    if ($idLector <= 0) {

        responder(
            'error',
            [
                'msg' =>
                    'La incidencia no tiene un lector asociado.'
            ],
            400
        );
    }


    $stmt = $mysqliEmpresa->prepare("
        SELECT
            idlector,
            nombre,
            idagente,
            activo
        FROM lectores
        WHERE idlector = ?
        LIMIT 1
    ");

    if (!$stmt) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo consultar el lector.'
            ],
            500
        );
    }

    $stmt->bind_param(
        'i',
        $idLector
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();
        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo consultar el lector.',
                'detalle' =>
                    $error
            ],
            500
        );
    }

    $lector = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if (!$lector) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'El lector asociado a la incidencia no existe.'
            ],
            404
        );
    }


    if ((int)$lector['activo'] !== 1) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'El lector asociado a la incidencia está inactivo.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFICAR QUE EL LECTOR PERTENEZCA AL AGENT
    |--------------------------------------------------------------------------
    */

    if (
        $lector['idagente'] === null ||
        (int)$lector['idagente'] !== $idAgente
    ) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'El lector no está asignado al Agent de la incidencia.'
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EVITAR DUPLICAR TAREA PENDIENTE / EN PROCESO
    |--------------------------------------------------------------------------
    */

    $stmt = $mysqliEmpresa->prepare("
        SELECT
            idtarea
        FROM tareas_agente
        WHERE idagente = ?
          AND idlector = ?
          AND accion = 'reintentar_fichada'
          AND estado IN ('pendiente', 'procesando')
          AND JSON_UNQUOTE(
                JSON_EXTRACT(datos, '$.idfichada')
              ) = ?
        LIMIT 1
    ");

    if (!$stmt) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo verificar si ya existe una tarea de reintento.'
            ],
            500
        );
    }

    $stmt->bind_param(
        'iii',
        $idAgente,
        $idLector,
        $idfichada
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();
        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo verificar las tareas existentes.',
                'detalle' =>
                    $error
            ],
            500
        );
    }

    $tareaExistente = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if ($tareaExistente) {

        $mysqliEmpresa->close();

        responder(
            'ok',
            [
                'msg' =>
                    'Ya existe una solicitud de reintento pendiente para esta fichada.',
                'idtarea' =>
                    (int)$tareaExistente['idtarea']
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DATOS DE LA TAREA
    |--------------------------------------------------------------------------
    */

    $datosTarea = json_encode(
        [
            'idfichada' =>
                $idfichada,

            'idincidencia' =>
                $idIncidencia
        ],
        JSON_UNESCAPED_UNICODE
    );

    if ($datosTarea === false) {

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudieron preparar los datos de la tarea.'
            ],
            500
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREAR TAREA PARA EL AGENT
    |--------------------------------------------------------------------------
    */

    $accion = 'reintentar_fichada';
    $estado = 'pendiente';
    $intentos = 0;

    $stmt = $mysqliEmpresa->prepare("
        INSERT INTO tareas_agente
        (
            idagente,
            idlector,
            accion,
            datos,
            estado,
            intentos
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {

        $error = $mysqliEmpresa->error;

        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo preparar la creación de la tarea.',
                'detalle' =>
                    $error
            ],
            500
        );
    }

    $stmt->bind_param(
        'iisssi',
        $idAgente,
        $idLector,
        $accion,
        $datosTarea,
        $estado,
        $intentos
    );

    if (!$stmt->execute()) {

        $error = $stmt->error;

        $stmt->close();
        $mysqliEmpresa->close();

        responder(
            'error',
            [
                'msg' =>
                    'No se pudo crear la tarea de reintento.',
                'detalle' =>
                    $error
            ],
            500
        );
    }

    $idTarea = (int)$stmt->insert_id;

    $stmt->close();
    $mysqliEmpresa->close();


    /*
    |--------------------------------------------------------------------------
    | RESPUESTA
    |--------------------------------------------------------------------------
    */

    responder(
        'ok',
        [
            'msg' =>
                'Solicitud de reintento enviada al Agent.',
            'idtarea' =>
                $idTarea,
            'idincidencia' =>
                $idIncidencia,
            'idfichada' =>
                $idfichada,
            'idagente' =>
                $idAgente,
            'idlector' =>
                $idLector
        ]
    );
}


/*
|--------------------------------------------------------------------------
| SOLO GET A PARTIR DE ACÁ
|--------------------------------------------------------------------------
*/

if ($metodo !== 'GET') {

    responder(
        'error',
        [
            'msg' => 'Método no permitido.'
        ],
        405
    );
}


/*
|--------------------------------------------------------------------------
| VALIDAR PERMISO GET
|--------------------------------------------------------------------------
*/

validarPermisoEndpoint(
    $mysqli,
    $userAuth
);


/*
|--------------------------------------------------------------------------
| EMPRESA ACTUAL
|--------------------------------------------------------------------------
*/

$empresa = obtenerEmpresaActual(
    $mysqli,
    $userAuth
);

if (!$empresa) {

    responder(
        'error',
        [
            'msg' => 'No se pudo determinar la empresa actual.'
        ],
        400
    );
}

$idEmpresa = (int)$empresa['idempresa'];


/*
|--------------------------------------------------------------------------
| AGENTES
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare("
    SELECT
        idagente,
        nombre,
        agent_id,
        activo,
        ultimo_acceso,
        ultima_actividad,
        ultimo_error
    FROM agentes
    WHERE idempresa = ?
    ORDER BY nombre ASC
");

if (!$stmt) {

    responder(
        'error',
        [
            'msg' => 'No se pudo preparar la consulta de Agents.'
        ],
        500
    );
}

$stmt->bind_param(
    'i',
    $idEmpresa
);

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    responder(
        'error',
        [
            'msg' => 'No se pudo consultar los Agents.',
            'detalle' => $error
        ],
        500
    );
}

$resultadoAgentes = $stmt->get_result();

$agentes = [];

while ($agente = $resultadoAgentes->fetch_assoc()) {

    $online = false;

    if (
        (int)$agente['activo'] === 1 &&
        !empty($agente['ultima_actividad'])
    ) {

        $timestamp = strtotime(
            $agente['ultima_actividad']
        );

        if (
            $timestamp !== false &&
            (time() - $timestamp) <= $valor
        ) {
            $online = true;
        }
    }

    $agentes[] = [

        'idagente' =>
            (int)$agente['idagente'],

        'nombre' =>
            $agente['nombre'],

        'agent_id' =>
            $agente['agent_id'],

        'activo' =>
            (int)$agente['activo'] === 1,

        'online' =>
            $online,

        'ultimo_acceso' =>
            $agente['ultimo_acceso'],

        'ultima_actividad' =>
            $agente['ultima_actividad'],

        'ultimo_error' =>
            $agente['ultimo_error']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| RESUMEN DE INCIDENCIAS
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare("
    SELECT
        COUNT(*) AS total,

        SUM(
            CASE
                WHEN estado = 'abierta'
                THEN 1
                ELSE 0
            END
        ) AS abiertas,

        SUM(
            CASE
                WHEN estado = 'reconocida'
                THEN 1
                ELSE 0
            END
        ) AS reconocidas,

        SUM(
            CASE
                WHEN estado = 'resuelta'
                THEN 1
                ELSE 0
            END
        ) AS resueltas

    FROM incidencias_agentes

    WHERE idempresa = ?
");

if (!$stmt) {

    responder(
        'error',
        [
            'msg' =>
                'No se pudo preparar el resumen de incidencias.'
        ],
        500
    );
}

$stmt->bind_param(
    'i',
    $idEmpresa
);

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    responder(
        'error',
        [
            'msg' =>
                'No se pudo consultar el resumen de incidencias.',
            'detalle' =>
                $error
        ],
        500
    );
}

$resumen = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| INCIDENCIAS ABIERTAS / RECONOCIDAS
|--------------------------------------------------------------------------
*/

$stmt = $mysqli->prepare("
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

        a.nombre AS agente_nombre

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

            WHEN 'critica'
                THEN 1

            WHEN 'error'
                THEN 2

            WHEN 'advertencia'
                THEN 3

            WHEN 'info'
                THEN 4

            ELSE 5

        END,

        i.ultima_ocurrencia DESC

    LIMIT 20
");

if (!$stmt) {

    responder(
        'error',
        [
            'msg' =>
                'No se pudo preparar el listado de incidencias.'
        ],
        500
    );
}

$stmt->bind_param(
    'i',
    $idEmpresa
);

if (!$stmt->execute()) {

    $error = $stmt->error;

    $stmt->close();

    responder(
        'error',
        [
            'msg' =>
                'No se pudo consultar las incidencias.',
            'detalle' =>
                $error
        ],
        500
    );
}

$resultadoIncidencias =
    $stmt->get_result();

$incidencias = [];

while (
    $incidencia =
        $resultadoIncidencias->fetch_assoc()
) {

    $incidencias[] = [

        'idincidencia' =>
            (int)$incidencia['idincidencia'],

        'idagente' =>
            (int)$incidencia['idagente'],

        'idempresa' =>
            (int)$incidencia['idempresa'],

        'idlector' =>
            $incidencia['idlector'] !== null
                ? (int)$incidencia['idlector']
                : null,

        'tipo' =>
            $incidencia['tipo'],

        'codigo' =>
            $incidencia['codigo'],

        'severidad' =>
            $incidencia['severidad'],

        'estado' =>
            $incidencia['estado'],

        'mensaje' =>
            $incidencia['mensaje'],

        'detalle' =>
            $incidencia['detalle'],

        'contexto' =>
            $incidencia['contexto'],

        'cantidad_ocurrencias' =>
            (int)$incidencia['cantidad_ocurrencias'],

        'primera_ocurrencia' =>
            $incidencia['primera_ocurrencia'],

        'ultima_ocurrencia' =>
            $incidencia['ultima_ocurrencia'],

        'agente_nombre' =>
            $incidencia['agente_nombre']
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| NORMALIZAR RESUMEN
|--------------------------------------------------------------------------
*/

$resumen = [

    'total' =>
        (int)($resumen['total'] ?? 0),

    'abiertas' =>
        (int)($resumen['abiertas'] ?? 0),

    'reconocidas' =>
        (int)($resumen['reconocidas'] ?? 0),

    'resueltas' =>
        (int)($resumen['resueltas'] ?? 0)
];


/*
|--------------------------------------------------------------------------
| ESTADO GENERAL DEL AGENT
|--------------------------------------------------------------------------
*/

$hayAgentOnline = false;

foreach ($agentes as $agente) {

    if ($agente['online']) {

        $hayAgentOnline = true;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| RESPUESTA GET
|--------------------------------------------------------------------------
*/

responder(
    'ok',
    [

        'empresa' => [

            'idempresa' =>
                $idEmpresa,

            'nombre' =>
                $empresa['nombre'] ?? ''
        ],

        'agente_online' =>
            $hayAgentOnline,

        'agentes' =>
            $agentes,

        'resumen' =>
            $resumen,

        'incidencias' =>
            $incidencias
    ]
    );