<?php
// /api/sso/auth/empresa_context.php

/**
 * Conecta con la base de datos de una empresa
 * utilizando una conexión configurada en empresas_conexiones.
 *
 * $mysqliSso       = conexión a portal_sso
 * $idEmpresa       = empresa seleccionada
 * $nombreConexion  = nombre de la conexión ("Principal", "MS3", "IA", etc.)
 */
/*
function conectarBase(
    mysqli $mysqliSso,
    int $idEmpresa,
    string $nombreConexion = 'Principal'
) {
    return conectarDBEmpresa(
        $mysqliSso,
        $idEmpresa,
        $nombreConexion
    );
}
*/

/**
 * Determina la empresa actualmente seleccionada
 * y verifica que el usuario tenga autorización.
 *
 * ROOT:
 *     Puede acceder a cualquier empresa activa.
 *
 * Usuario normal:
 *     Solamente puede acceder a las empresas que tenga
 *     asignadas en usuarios_empresas.
 *
 * Si el usuario normal tiene una sola empresa asignada
 * y no hay una empresa seleccionada en header,
 * se utiliza automáticamente esa empresa.
 */
function obtenerEmpresaActual(mysqli $mysqliSso, array $userAuth)
{
    $idUsuario = intval($userAuth['idusuario']);


    /* =========================================================
     * 1. DETERMINAR TIPO DE SISTEMA
     * ========================================================= */

    $esRoot = false;

    $sqlTipo = "
        SELECT uts.clave
        FROM usuarios u
        INNER JOIN usuarios_tipo_sistema uts
            ON uts.idtiposistema = u.idtiposistema
        WHERE u.idusuario = $idUsuario
          AND u.baja = 0
        LIMIT 1
    ";

    $resultadoTipo = $mysqliSso->query($sqlTipo);

    if (!$resultadoTipo) {

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "msg" => "Error consultando tipo de sistema: " . $mysqliSso->error
        ]);

        exit;
    }

    $filaTipo = $resultadoTipo->fetch_assoc();

    if ($filaTipo) {

        $esRoot =
            strtoupper(trim($filaTipo['clave'])) === 'ROOT';
    }


    /* =========================================================
     * 2. EMPRESA SELECCIONADA
     * ========================================================= */

    $idEmpresa = $_SERVER['HTTP_X_EMPRESA_ID'] ?? null;
    $idEmpresa = intval($idEmpresa);


    /* =========================================================
     * 3. ROOT
     * ========================================================= */

    if ($esRoot) {

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


    /* =========================================================
     * 4. USUARIO NORMAL SIN EMPRESA SELECCIONADA
     * ========================================================= */

    if ($idEmpresa <= 0) {

        $stmt = $mysqliSso->prepare("
            SELECT
                e.idempresa,
                e.nombre,
                e.razon_social,
                e.cuit,
                e.slug,
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


        /* =====================================================
         * Usuario sin empresas
         * ===================================================== */

        if (count($empresas) === 0) {

            http_response_code(403);

            echo json_encode([
                "status" => "error",
                "msg" => "El usuario no tiene una empresa asignada"
            ]);

            exit;
        }


        /* =====================================================
         * Una sola empresa
         * ===================================================== */

        if (count($empresas) === 1) {

            return $empresas[0];
        }


        /* =====================================================
         * Varias empresas
         * ===================================================== */

        http_response_code(403);

        echo json_encode([
            "status" => "error",
            "msg" => "Debe seleccionar una empresa"
        ]);

        exit;
    }


    /* =========================================================
     * 5. USUARIO NORMAL CON EMPRESA SELECCIONADA
     * ========================================================= */

    $stmt = $mysqliSso->prepare("
        SELECT
            e.idempresa,
            e.nombre,
            e.razon_social,
            e.cuit,
            e.slug,
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