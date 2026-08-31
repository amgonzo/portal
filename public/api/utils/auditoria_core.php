<?php
// /api/utils/auditoria_logica.php

function ejecutarGetTodosLogs($mysqli) {
    $sql = "SELECT 
                id,
                idusuario,
                username,
                action,
                tablename,
                idregistro,
                dataantes,
                datadespues,
                ipaddress,
                createdat
            FROM audit_logs 
            ORDER BY createdat DESC 
            LIMIT 500";

    $res = $mysqli->query($sql);

    if (!$res) {
        return ["status" => "error", "msg" => $mysqli->error];
    }

    $logs = [];
    while ($f = $res->fetch_assoc()) {
        $logs[] = $f;
    }

    return ["status" => "ok", "data" => $logs];
}

function ejecutarGetRegistro($mysqli) {
    $tabla = trim($_REQUEST['tabla'] ?? '');
    $idRaw = trim($_REQUEST['id'] ?? '');

    if (!$tabla || !$idRaw) {
        return ["status" => "error", "msg" => "Faltan datos obligatorios"];
    }

    // Mapeo completo de claves primarias según las tablas de las bases
    $pk_mapping = [
        'aplicaciones'        => 'idaplicacion',
        'permisos'            => 'idpermiso',
        'tiposusuario'        => 'idtipousuario',
        'permisos_rol'        => 'idtipousuario',
        'usuarios'            => 'idusuario',
        'usuarios_roles_apps' => 'idusuario',
        'auditoria'           => 'idauditoria',
        'personas'            => 'dni',
        'categorias'          => 'idcategoria',
        'empleados_limites'   => 'dni',
        'compras_cabecera'    => 'venta_id'
    ];

    if (!isset($pk_mapping[$tabla])) { 
        return ["status" => "error", "msg" => "Tabla no mapeada"]; 
    }

    $pk = $pk_mapping[$tabla];

    $columnas_res = $mysqli->query("SHOW COLUMNS FROM `$tabla` ");
    $columnas_reales = [];
    if ($columnas_res) {
        while ($col = $columnas_res->fetch_assoc()) {
            $columnas_reales[] = $col['Field'];
        }
    }

    $select = "t.*";
    $joins = "";

    function tieneCol($col, $lista) { return in_array($col, $lista); }

    if (tieneCol('idaplicacion', $columnas_reales) && $tabla !== 'aplicaciones') {
        $select .= ", app.nombre as nombre_aplicacion, app.slug as slug_aplicacion";
        $joins  .= " LEFT JOIN aplicaciones app ON t.idaplicacion = app.idaplicacion";
    }

    if (tieneCol('idusuario', $columnas_reales) && $tabla !== 'usuarios') {
        $select .= ", u.nombreapellido as nombre_usuario, u.username as user_ref";
        $joins  .= " LEFT JOIN usuarios u ON t.idusuario = u.idusuario";
    }

    if (tieneCol('idtipousuario', $columnas_reales) && $tabla !== 'tiposusuario') {
        $select .= ", tu.descripcion as rol_descripcion";
        $joins  .= " LEFT JOIN tiposusuario tu ON t.idtipousuario = tu.idtipousuario";
    }

    if (tieneCol('idpermiso', $columnas_reales) && $tabla !== 'permisos') {
        $select .= ", p.clavepermiso, p.descripcion as permiso_descripcion";
        $joins  .= " LEFT JOIN permisos p ON t.idpermiso = p.idpermiso";
    }

    $sql  = "SELECT $select FROM `$tabla` t $joins WHERE t.`$pk` = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return ["status" => "error", "msg" => "Error al preparar la consulta SQL"];
    }

    $stmt->bind_param("s", $idRaw);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res ? $res->fetch_assoc() : null;

    if (!$data) {
        return ["status" => "error", "msg" => "No se encontró el registro actual"];
    }

    return ["status" => "ok", "data" => $data];
}

function ejecutarGetRegistroCtacte($mysqli) {
    $tabla = trim($_REQUEST['tabla'] ?? '');
    $idRaw = trim($_REQUEST['id'] ?? '');

    if (!$tabla || !$idRaw) {
        return ["status" => "error", "msg" => "Faltan datos obligatorios"];
    }

    // Mapeo de claves primarias exclusivo para las tablas de CTACTE_
    $pk_mapping_ctacte = [
        'categorias'          => 'idcategoria',
        'personas'            => 'dni',
        'empleados_limites'   => 'dni',
        'compras_cabecera'    => 'venta_id', // Ojo si es compuesta, idealmente manejar el ID principal
        'configuracion'       => 'clave',
        'anios'               => 'anio'
    ];

    if (!isset($pk_mapping_ctacte[$tabla])) { 
        return ["status" => "error", "msg" => "Tabla de Ctacte no mapeada o desconocida"]; 
    }

    $pk = $pk_mapping_ctacte[$tabla];

    // Obtener columnas reales de la tabla en Ctacte
    $columnas_res = $mysqli->query("SHOW COLUMNS FROM `$tabla`");
    $columnas_reales = [];
    if ($columnas_res) {
        while ($col = $columnas_res->fetch_assoc()) {
            $columnas_reales[] = $col['Field'];
        }
    }

    $select = "t.*";
    $joins = "";

    // Relaciones específicas si aplican en Ctacte (ej: categoría en personas)
    if (in_array('idcategoria', $columnas_reales) && $tabla !== 'categorias') {
        $select .= ", c.nombre as nombre_categoria";
        $joins  .= " LEFT JOIN categorias c ON t.idcategoria = c.idcategoria";
    }

    $sql  = "SELECT $select FROM `$tabla` t $joins WHERE t.`$pk` = ? LIMIT 1";
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return ["status" => "error", "msg" => "Error al preparar la consulta SQL: " . $mysqli->error];
    }

    $stmt->bind_param("s", $idRaw);
    $stmt->execute();
    $res  = $stmt->get_result();
    $data = $res ? $res->fetch_assoc() : null;

    if (!$data) {
        return ["status" => "error", "msg" => "No se encontró el registro actual en la base de datos"];
    }

    return ["status" => "ok", "data" => $data];
}