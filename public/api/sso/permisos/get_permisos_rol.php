<?php

// =========================================================
// 1. CARGAR RUTAS Y DEPENDENCIAS
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];

header('Content-Type: application/json; charset=utf-8');

try {

    // =========================================================
    // 2. VALIDAR TOKEN Y SESIÓN
    // =========================================================

    $userAuth = validarTokenAPI($mysqli);

    $id_usuario = intval($userAuth['idusuario']);

    // =========================================================
    // 3. PARÁMETROS
    // =========================================================

    $idApp  = $_GET['idaplicacion'] ?? '';
    $idTipo = $_GET['idtipousuario'] ?? '';

    if (!$idApp || !$idTipo) {

        echo json_encode([
            "status" => "error",
            "msg" => "Faltan parámetros obligatorios"
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    $idApp  = intval($idApp);
    $idTipo = intval($idTipo);

    // =========================================================
    // 4. DETERMINAR SI EL USUARIO ACTUAL ES ROOT
    //
    // NO se utiliza un ID fijo.
    // Puede haber cualquier cantidad de usuarios ROOT.
    // Se determina mediante usuarios_tipo_sistema.clave
    // =========================================================

    $esAuditorSesion = false;

    $stmtRoot = $mysqli->prepare("
        SELECT 1
        FROM usuarios u
        INNER JOIN usuarios_tipo_sistema uts
            ON uts.idtiposistema = u.idtiposistema
        WHERE u.idusuario = ?
          AND UPPER(TRIM(uts.clave)) = 'ROOT'
        LIMIT 1
    ");

    if (!$stmtRoot) {
        throw new Exception(
            "Error preparando consulta de tipo de sistema: " . $mysqli->error
        );
    }

    $stmtRoot->bind_param("i", $id_usuario);
    $stmtRoot->execute();

    if ($stmtRoot->get_result()->num_rows > 0) {
        $esAuditorSesion = true;
    }

    $stmtRoot->close();

    // =========================================================
    // 5. SI NO ES ROOT, COMPROBAR SI TIENE ROL SUPER_ADMIN
    //
    // SUPER_ADMIN sigue siendo un rol de aplicación.
    // No se reemplaza por ROOT.
    // =========================================================

    if (!$esAuditorSesion) {

        $sqlRolesUser = "
            SELECT idtipousuario
            FROM usuarios_roles_apps
            WHERE idusuario = ?
        ";

        $stmtRU = $mysqli->prepare($sqlRolesUser);

        if (!$stmtRU) {
            throw new Exception(
                "Error preparando consulta de roles: " . $mysqli->error
            );
        }

        $stmtRU->bind_param("i", $id_usuario);
        $stmtRU->execute();

        $resRU = $stmtRU->get_result();

        while ($rowR = $resRU->fetch_assoc()) {

            $idRol = intval($rowR['idtipousuario']);

            $stmtSuper = $mysqli->prepare("
                SELECT 1
                FROM tiposusuario
                WHERE idtipousuario = ?
                  AND UPPER(TRIM(clave)) = 'SUPER_ADMIN'
                LIMIT 1
            ");

            if (!$stmtSuper) {
                $stmtRU->close();

                throw new Exception(
                    "Error preparando consulta de SUPER_ADMIN: " . $mysqli->error
                );
            }

            $stmtSuper->bind_param("i", $idRol);
            $stmtSuper->execute();

            if ($stmtSuper->get_result()->num_rows > 0) {

                $esAuditorSesion = true;

                $stmtSuper->close();

                break;
            }

            $stmtSuper->close();
        }

        $stmtRU->close();
    }

    // =========================================================
    // 6. OBTENER TODOS LOS PERMISOS DE LA APLICACIÓN
    // =========================================================

    $wherePermisos = "
        WHERE ap.idaplicacion = ?
    ";

    /*
     * ROOT y SUPER_ADMIN pueden visualizar
     * los permisos relacionados con auditoría.
     *
     * Los demás usuarios no los visualizan.
     */
    if (!$esAuditorSesion) {

        $wherePermisos .= "
            AND p.clavepermiso NOT LIKE '%auditoria%'
            AND p.clavepermiso NOT LIKE '%audit%'
        ";
    }

    $sqlTodos = "
        SELECT
            p.idpermiso,
            p.clavepermiso,
            p.endpoint,
            p.metodo,
            p.descripcion
        FROM permisos p
        INNER JOIN aplicaciones_permisos ap
            ON p.idpermiso = ap.idpermiso
        $wherePermisos
        ORDER BY p.clavepermiso ASC
    ";

    $stmtTodos = $mysqli->prepare($sqlTodos);

    if (!$stmtTodos) {
        throw new Exception(
            "Error preparando consulta de permisos: " . $mysqli->error
        );
    }

    $stmtTodos->bind_param("i", $idApp);
    $stmtTodos->execute();

    $resTodos = $stmtTodos->get_result();

    $permisos = [];

    while ($row = $resTodos->fetch_assoc()) {

        $permisos[] = $row;
    }

    $stmtTodos->close();

    // =========================================================
    // 7. OBTENER PERMISOS ASIGNADOS AL ROL
    //    PARA LA APLICACIÓN SELECCIONADA
    // =========================================================

    $sqlAsignados = "
        SELECT
            pr.idpermiso
        FROM permisos_rol pr
        INNER JOIN aplicaciones_permisos ap
            ON pr.idpermiso = ap.idpermiso
        WHERE pr.idtipousuario = ?
          AND ap.idaplicacion = ?
    ";

    $stmtAsig = $mysqli->prepare($sqlAsignados);

    if (!$stmtAsig) {
        throw new Exception(
            "Error preparando consulta de permisos asignados: " . $mysqli->error
        );
    }

    $stmtAsig->bind_param(
        "ii",
        $idTipo,
        $idApp
    );

    $stmtAsig->execute();

    $resAsignados = $stmtAsig->get_result();

    $asignados = [];

    while ($row = $resAsignados->fetch_assoc()) {

        $asignados[] = intval($row['idpermiso']);
    }

    $stmtAsig->close();

    // =========================================================
    // 8. RESPUESTA
    // =========================================================

    echo json_encode([
        "status" => "ok",
        "data" => [
            "todos" => $permisos,
            "asignados" => $asignados
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// =========================================================
// 9. CERRAR CONEXIÓN
// =========================================================

if (isset($mysqli) && $mysqli instanceof mysqli) {
    $mysqli->close();
}