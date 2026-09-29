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
require_once $rutas['auditoria'];
require_once $rutas['contexto'];

header('Content-Type: application/json');


/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/

$userAuth = validarTokenAPI($mysqli);

validarPermisoEndpoint($mysqli, $userAuth);


/*
|--------------------------------------------------------------------------
| ¿Es SUPER_ADMIN?
|--------------------------------------------------------------------------
*/

$esSuperAdmin = false;

$sqlSuperAdmin = "
    SELECT 1
    FROM usuarios_roles_apps ura
    INNER JOIN tiposusuario tu
        ON tu.idtipousuario = ura.idtipousuario
    WHERE ura.idusuario = ?
      AND UPPER(TRIM(tu.clave)) = 'SUPER_ADMIN'
    LIMIT 1
";

$stmtSuperAdmin = $mysqli->prepare($sqlSuperAdmin);

$idUsuarioAuth = intval($userAuth['idusuario']);

$stmtSuperAdmin->bind_param(
    "i",
    $idUsuarioAuth
);

$stmtSuperAdmin->execute();

$resSuperAdmin = $stmtSuperAdmin->get_result();

$esSuperAdmin = ($resSuperAdmin->num_rows > 0);

$stmtSuperAdmin->close();


/*
|--------------------------------------------------------------------------
| Empresa actual
|--------------------------------------------------------------------------
*/

$idEmpresaActual = 0;

if (!$esSuperAdmin) {

    $empresaActual = obtenerEmpresaActual(
        $mysqli,
        $userAuth
    );

    $idEmpresaActual = intval(
        $empresaActual['idempresa']
    );

} else {

    $headers = getallheaders();

    if (isset($headers['X-EMPRESA-ID'])) {

        $idEmpresaActual = intval(
            $headers['X-EMPRESA-ID']
        );

    } elseif (isset($_POST['idempresa'])) {

        $idEmpresaActual = intval(
            $_POST['idempresa']
        );

    } elseif (isset($_GET['idempresa'])) {

        $idEmpresaActual = intval(
            $_GET['idempresa']
        );
    }
}


if ($idEmpresaActual <= 0) {

    echo json_encode([
        "status" => "error",
        "msg" => "Debe seleccionar una empresa"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Datos recibidos
|--------------------------------------------------------------------------
*/

$id      = $_POST['id'] ?? '';
$nombre  = trim($_POST['nombre'] ?? '');
$email   = trim($_POST['email'] ?? '');
$login   = trim($_POST['login'] ?? '');
$clave   = trim($_POST['clave'] ?? '');
$accesos = json_decode(
    $_POST['accesos'] ?? '[]',
    true
);

if (!$login || !$nombre || (!$id && empty($accesos))) {
    echo json_encode([
        "status" => "error",
        "msg" => "Datos o accesos incompletos"
    ]);
    exit;
}


if (!$id && empty($clave)) {

    echo json_encode([
        "status" => "error",
        "msg" => "Clave obligatoria"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Username único
|--------------------------------------------------------------------------
*/

$sqlCheck = "
    SELECT idusuario
    FROM usuarios
    WHERE username = ?
";

$stmtCheck = $mysqli->prepare($sqlCheck);

$stmtCheck->bind_param(
    "s",
    $login
);

$stmtCheck->execute();

$result = $stmtCheck->get_result();

if ($result->num_rows > 0) {

    $row = $result->fetch_assoc();

    if (!$id || $row['idusuario'] != $id) {

        echo json_encode([
            "status" => "error",
            "msg" => "El username ya existe"
        ]);

        exit;
    }
}

$stmtCheck->close();


/*
|--------------------------------------------------------------------------
| Transacción
|--------------------------------------------------------------------------
*/

$mysqli->begin_transaction();

try {

    /*
    |--------------------------------------------------------------------------
    | Editar usuario
    |--------------------------------------------------------------------------
    */

    if ($id) {

        $datosAntes = $mysqli
            ->query(
                "SELECT * FROM usuarios WHERE idusuario = " . intval($id)
            )
            ->fetch_assoc();


        if (!empty($clave)) {

            $hash = password_hash(
                $clave,
                PASSWORD_DEFAULT
            );

            $stmt = $mysqli->prepare("
                UPDATE usuarios
                SET
                    nombreapellido = ?,
                    username = ?,
                    password = ?,
                    email = ?
                WHERE idusuario = ?
            ");

            $stmt->bind_param(
                "ssssi",
                $nombre,
                $login,
                $hash,
                $email,
                $id
            );

        } else {

            $stmt = $mysqli->prepare("
                UPDATE usuarios
                SET
                    nombreapellido = ?,
                    username = ?,
                    email = ?
                WHERE idusuario = ?
            ");

            $stmt->bind_param(
                "sssi",
                $nombre,
                $login,
                $email,
                $id
            );
        }

        $stmt->execute();
        $stmt->close();

        $idUsuario = intval($id);


        /*
        |--------------------------------------------------------------------------
        | Eliminar solamente las asignaciones de ESTA empresa
        |--------------------------------------------------------------------------
        */

        $stmtDeleteURA = $mysqli->prepare("
            DELETE FROM usuarios_roles_apps
            WHERE idusuario = ?
              AND idempresa = ?
        ");

        $stmtDeleteURA->bind_param(
            "ii",
            $idUsuario,
            $idEmpresaActual
        );

        $stmtDeleteURA->execute();
        $stmtDeleteURA->close();


        registrarLog(
            $mysqli,
            'edit_usuario',
            'usuarios',
            $idUsuario,
            $datosAntes,
            $_POST
        );


    /*
    |--------------------------------------------------------------------------
    | Nuevo usuario
    |--------------------------------------------------------------------------
    */

    } else {

        $hash = password_hash(
            $clave,
            PASSWORD_DEFAULT
        );

        $stmt = $mysqli->prepare("
            INSERT INTO usuarios
                (
                    nombreapellido,
                    username,
                    password,
                    email,
                    baja
                )
            VALUES
                (?, ?, ?, ?, 0)
        ");

        $stmt->bind_param(
            "ssss",
            $nombre,
            $login,
            $hash,
            $email
        );

        $stmt->execute();

        $idUsuario = $mysqli->insert_id;

        $stmt->close();


        registrarLog(
            $mysqli,
            'alta_usuario',
            'usuarios',
            $idUsuario,
            null,
            $_POST
        );
    }


   /*
|--------------------------------------------------------------------------
| Asociar usuario a la empresa
|--------------------------------------------------------------------------
|
| Si no quedan aplicaciones, significa que el usuario deja de
| pertenecer a ESTA empresa.
|
*/

if (empty($accesos)) {

    $stmtEmpresa = $mysqli->prepare("
        DELETE FROM usuarios_empresas
        WHERE idusuario = ?
          AND idempresa = ?
    ");

    $stmtEmpresa->bind_param(
        "ii",
        $idUsuario,
        $idEmpresaActual
    );

    $stmtEmpresa->execute();
    $stmtEmpresa->close();

} else {

    $stmtEmpresa = $mysqli->prepare("
        INSERT INTO usuarios_empresas
            (
                idusuario,
                idempresa,
                activo
            )
        VALUES
            (?, ?, 1)
        ON DUPLICATE KEY UPDATE
            activo = 1
    ");

    $stmtEmpresa->bind_param(
        "ii",
        $idUsuario,
        $idEmpresaActual
    );

    $stmtEmpresa->execute();
    $stmtEmpresa->close();
}


    /*
    |--------------------------------------------------------------------------
    | Asignar aplicaciones y roles de ESTA empresa
    |--------------------------------------------------------------------------
    */

    $stmtURA = $mysqli->prepare("
        INSERT INTO usuarios_roles_apps
            (
                idusuario,
                idempresa,
                idtipousuario,
                idaplicacion
            )
        VALUES
            (?, ?, ?, ?)
    ");

    foreach ($accesos as $acc) {

        $idApp = intval(
            $acc['idaplicacion']
        );

        $idRol = intval(
            $acc['idtipousuario']
        );

        $stmtURA->bind_param(
            "iiii",
            $idUsuario,
            $idEmpresaActual,
            $idRol,
            $idApp
        );

        $stmtURA->execute();
    }

    $stmtURA->close();


    /*
    |--------------------------------------------------------------------------
    | Confirmar
    |--------------------------------------------------------------------------
    */

    $mysqli->commit();

    echo json_encode([
        "status" => "ok"
    ]);

} catch (Exception $e) {

    $mysqli->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => "Error DB: " . $e->getMessage()
    ]);
}