<?php
// /api/sso/auth/empresa_context.php

/**
 * Conecta con la base de datos de una empresa.
 */
function conectarBase($dbNombre)
{
    if (empty($dbNombre)) {
        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Nombre de base de datos de empresa no definido"
        ]);

        exit;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';

    $mysqliEmpresa = new mysqli(
        $host,
        $user,
        $pass,
        $dbNombre
    );

    if ($mysqliEmpresa->connect_error) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error al conectar con la base de datos de la empresa"
        ]);

        exit;
    }

    $mysqliEmpresa->set_charset("utf8mb4");

    $mysqliEmpresa->query(
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    $mysqliEmpresa->query(
        "SET time_zone = '-03:00'"
    );

    return $mysqliEmpresa;
}


/**
 * Determina la empresa actualmente seleccionada
 * y verifica que el usuario tenga autorización.
 *
 * SUPER_ADMIN:
 *     Puede acceder a cualquier empresa activa.
 *
 * Usuario normal:
 *     Solamente puede acceder a las empresas que tenga
 *     asignadas en usuarios_empresas.
 *
 * Si el usuario normal tiene una sola empresa asignada
 * y no hay una empresa seleccionada en sesión/header,
 * se utiliza automáticamente esa empresa.
 */
function obtenerEmpresaActual(mysqli $mysqliSso, array $userAuth)
{
    $idUsuario = intval($userAuth['idusuario']);


    /*
     * =========================================================
     * 1. DETERMINAR SI ES SUPER_ADMIN
     * =========================================================
     *
     * NO usamos descripcion.
     *
     * La clave correcta está en:
     *
     * tiposusuario.clave
     *
     * =========================================================
     */

    $esSuperAdmin = false;

    $stmtRol = $mysqliSso->prepare("
        SELECT 1
        FROM usuarios_roles_apps ura

        INNER JOIN tiposusuario tu
            ON tu.idtipousuario = ura.idtipousuario

        WHERE ura.idusuario = ?
          AND UPPER(TRIM(tu.clave)) = 'SUPER_ADMIN'

        LIMIT 1
    ");

    if ($stmtRol) {

        $stmtRol->bind_param(
            "i",
            $idUsuario
        );

        $stmtRol->execute();

        $resultadoRol = $stmtRol->get_result();

        $esSuperAdmin = ($resultadoRol->num_rows > 0);

        $stmtRol->close();
    }


    /*
     * =========================================================
     * 2. EMPRESA SELECCIONADA
     * =========================================================
     */
/*
    $idEmpresa = $_SESSION['idempresa'] ?? null;

    if (!$idEmpresa) {
        $idEmpresa = $_SERVER['HTTP_X_EMPRESA_ID'] ?? null;
    }

    $idEmpresa = intval($idEmpresa);
*/
    $idEmpresa = $_SERVER['HTTP_X_EMPRESA_ID'] ?? null;
    $idEmpresa = intval($idEmpresa);

    /*
     * =========================================================
     * 3. SUPER_ADMIN
     * =========================================================
     *
     * SUPER_ADMIN necesita una empresa seleccionada.
     */

    if ($esSuperAdmin) {

        if ($idEmpresa <= 0) {

            http_response_code(403);

            echo json_encode([
                "status" => "error",
                "msg" => "No se seleccionó una empresa"
            ]);

            exit;
        }


        $stmt = $mysqliSso->prepare("
            SELECT
                idempresa,
                nombre,
                razon_social,
                cuit,
                slug,
                db_nombre,
                activo

            FROM empresas

            WHERE idempresa = ?
              AND activo = 1

            LIMIT 1
        ");

        if (!$stmt) {

            http_response_code(500);

            echo json_encode([
                "status" => "error",
                "msg" => "Error preparando consulta de empresa"
            ]);

            exit;
        }

        $stmt->bind_param(
            "i",
            $idEmpresa
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 0) {

            $stmt->close();

            http_response_code(403);

            echo json_encode([
                "status" => "error",
                "msg" => "Empresa inexistente o inactiva"
            ]);

            exit;
        }

        $empresa = $resultado->fetch_assoc();

        $stmt->close();

        return $empresa;
    }


    /*
     * =========================================================
     * 4. USUARIO NORMAL
     * =========================================================
     *
     * Si NO hay empresa seleccionada, buscamos las empresas
     * que tiene asignadas el usuario.
     *
     * Si tiene exactamente UNA empresa:
     *     la usamos automáticamente.
     *
     * Si tiene más de UNA:
     *     debe haber una seleccionada.
     */

    if ($idEmpresa <= 0) {

        $stmt = $mysqliSso->prepare("
            SELECT
                e.idempresa,
                e.nombre,
                e.razon_social,
                e.cuit,
                e.slug,
                e.db_nombre,
                e.activo

            FROM usuarios_empresas ue

            INNER JOIN empresas e
                ON e.idempresa = ue.idempresa

            WHERE ue.idusuario = ?
              AND ue.activo = 1
              AND e.activo = 1

            ORDER BY e.nombre ASC
        ");

        if (!$stmt) {

            http_response_code(500);

            echo json_encode([
                "status" => "error",
                "msg" => "Error preparando consulta de empresas"
            ]);

            exit;
        }

        $stmt->bind_param(
            "i",
            $idUsuario
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        $empresas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $empresas[] = $fila;
        }

        $stmt->close();


        /*
         * Usuario sin empresas.
         */

        if (count($empresas) === 0) {

            http_response_code(403);

            echo json_encode([
                "status" => "error",
                "msg" => "El usuario no tiene una empresa asignada"
            ]);

            exit;
        }


        /*
         * Una sola empresa:
         * la seleccionamos automáticamente.
         */

        if (count($empresas) === 1) {

            return $empresas[0];
        }


        /*
         * Tiene varias empresas:
         * necesita seleccionar una.
         */

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Debe seleccionar una empresa"
        ]);

        exit;
    }


    /*
     * =========================================================
     * 5. USUARIO NORMAL CON EMPRESA SELECCIONADA
     * =========================================================
     *
     * Verificamos que esa empresa realmente pertenezca
     * al usuario.
     */

    $stmt = $mysqliSso->prepare("
        SELECT
            e.idempresa,
            e.nombre,
            e.razon_social,
            e.cuit,
            e.slug,
            e.db_nombre,
            e.activo

        FROM usuarios_empresas ue

        INNER JOIN empresas e
            ON e.idempresa = ue.idempresa

        WHERE ue.idusuario = ?
          AND ue.idempresa = ?
          AND ue.activo = 1
          AND e.activo = 1

        LIMIT 1
    ");

    if (!$stmt) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error preparando consulta de autorización"
        ]);

        exit;
    }

    $stmt->bind_param(
        "ii",
        $idUsuario,
        $idEmpresa
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 0) {

        $stmt->close();

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "No tenés acceso a esta empresa"
        ]);

        exit;
    }

    $empresa = $resultado->fetch_assoc();

    $stmt->close();

    return $empresa;
}