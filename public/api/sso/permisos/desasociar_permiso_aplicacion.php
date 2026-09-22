<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {}

require_once $rutas['conexion'];
require_once $rutas['middleware'];
require_once $rutas['auditoria'];

header('Content-Type: application/json');

$userAuth = validarTokenAPI($mysqli);

$idpermiso    = $_POST['idpermiso'] ?? null;
$idaplicacion = $_POST['idaplicacion'] ?? null;

if (!$idpermiso || !$idaplicacion) {
    exit(json_encode([
        "status" => "error",
        "msg" => "Faltan datos obligatorios"
    ]));
}

$mysqli->begin_transaction();

try {

    /*
     * Verificar que el permiso exista
     */
    $checkPermiso = $mysqli->prepare("
        SELECT idpermiso, clavepermiso
        FROM permisos
        WHERE idpermiso = ?
        LIMIT 1
    ");

    $checkPermiso->bind_param("i", $idpermiso);
    $checkPermiso->execute();

    $permiso = $checkPermiso->get_result()->fetch_assoc();

    if (!$permiso) {
        throw new Exception("El permiso no existe");
    }

    /*
     * Verificar que la aplicación exista
     */
    $checkApp = $mysqli->prepare("
        SELECT idaplicacion, nombre
        FROM aplicaciones
        WHERE idaplicacion = ?
        LIMIT 1
    ");

    $checkApp->bind_param("i", $idaplicacion);
    $checkApp->execute();

    $app = $checkApp->get_result()->fetch_assoc();

    if (!$app) {
        throw new Exception("La aplicación no existe");
    }

    /*
     * Verificar que realmente exista la asociación
     */
    $checkRel = $mysqli->prepare("
        SELECT 1
        FROM aplicaciones_permisos
        WHERE idaplicacion = ?
          AND idpermiso = ?
        LIMIT 1
    ");

    $checkRel->bind_param("ii", $idaplicacion, $idpermiso);
    $checkRel->execute();

    if ($checkRel->get_result()->num_rows === 0) {
        throw new Exception("El permiso no está asociado a esta aplicación");
    }

    /*
     * Verificar si el permiso está asignado a algún rol
     * que actualmente se utiliza en esta aplicación.
     *
     * No se elimina permisos_rol porque esa relación es global
     * y puede ser utilizada por otra aplicación.
     */
    $checkRol = $mysqli->prepare("
        SELECT COUNT(*) AS cantidad
        FROM permisos_rol pr
        INNER JOIN usuarios_roles_apps ura
            ON ura.idtipousuario = pr.idtipousuario
           AND ura.idaplicacion = ?
        WHERE pr.idpermiso = ?
    ");

    $checkRol->bind_param("ii", $idaplicacion, $idpermiso);
    $checkRol->execute();

    $resultadoRol = $checkRol->get_result()->fetch_assoc();

    if ((int)$resultadoRol['cantidad'] > 0) {
        throw new Exception(
            "No se puede desasociar este permiso porque está asignado a un rol utilizado en esta aplicación. Quitalo primero del rol."
        );
    }

    /*
     * Eliminar SOLAMENTE la relación aplicación-permiso.
     *
     * NO se elimina:
     * - permisos
     * - permisos_rol
     */
    $stmt = $mysqli->prepare("
        DELETE FROM aplicaciones_permisos
        WHERE idaplicacion = ?
          AND idpermiso = ?
    ");

    $stmt->bind_param("ii", $idaplicacion, $idpermiso);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception("No se pudo desasociar el permiso");
    }

    $mysqli->commit();

    registrarLog(
        $mysqli,
        'desasociar_permiso_aplicacion',
        'aplicaciones_permisos',
        $idpermiso,
        [
            'idpermiso' => $idpermiso,
            'idaplicacion' => $idaplicacion
        ],
        null
    );

    echo json_encode([
        "status" => "ok"
    ]);

} catch (Exception $e) {

    $mysqli->rollback();

    echo json_encode([
        "status" => "error",
        "msg" => $e->getMessage()
    ]);
}