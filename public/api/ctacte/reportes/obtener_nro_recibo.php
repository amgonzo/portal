<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
// 1. Cargamos nuestro archivo central de rutas
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// 2. Cargamos Composer usando la clave del array
require_once $rutas['autoload'];


try {
    // 3. Cargamos el .env usando la ruta definida en rutas.php
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Manejo silencioso si no hay .env
}

require_once $rutas['conexion'];
// funciones_mssql.php o al inicio de tu script

/**
 * Consulta directamente a la base de MSSQL y devuelve el NRO_REPORTE o null.
 * 
 * @param int $venta_id ID de la venta de referencia
 * @param string $dni Documento del contacto/empleado
 * @return string|null
 */
function obtenerNroReciboDesdeMssql($venta_id, $dni) {
    if (intval($venta_id) <= 0 || empty(trim($dni))) {
        return null;
    }

    try {
        $dbname_ms3 = $_ENV['DB_NAME_MS3'] ?? 'TuBaseMSSQL';
        $user_ms3   = $_ENV['DB_USER_MS3'] ?? 'TuUsuario';
        $pass_ms3   = $_ENV['DB_PASS_MS3'] ?? 'TuPassword';

        $dsn_ms3 = "sqlsrv:Server=192.168.0.238,1433;Database=$dbname_ms3;Encrypt=no;TrustServerCertificate=yes";
        $pdo_mssql = new PDO($dsn_ms3, $user_ms3, $pass_ms3, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sql = "
            SELECT TOP 1
                reporte.NRO_COMPROBANTE AS NRO_REPORTE
            FROM VENTA v
            INNER JOIN CUENTA_CORRIENTE cc
                ON cc.VENTA_ID = v.VENTA_ID
                AND cc.NRO_COMPROBANTE = CONVERT(VARCHAR(50), v.NRO_COMPROBANTE)
            INNER JOIN CONTACTO c
                ON c.CONTACTO_ID = cc.CONTACTO_ID
                AND c.DOCUMENTO = v.CONTACTO_DOCUMENTO
            INNER JOIN CUENTA_CORRIENTE reporte
                ON reporte.VENTA_ID = 0
                AND reporte.CONTACTO_ID = cc.CONTACTO_ID
                AND reporte.TIPO_COMPROBANTE_AFIP_ID = 'RECIBO_PAGO'
                AND reporte.FECHA >= DATEFROMPARTS(
                    YEAR(cc.FECHA_VENCIMIENTO),
                    MONTH(cc.FECHA_VENCIMIENTO),
                    1
                )
                AND reporte.FECHA < DATEADD(
                    MONTH,
                    1,
                    DATEFROMPARTS(
                        YEAR(cc.FECHA_VENCIMIENTO),
                        MONTH(cc.FECHA_VENCIMIENTO),
                        1
                    )
                )
            WHERE v.VENTA_ID = :venta_id
              AND v.CONTACTO_DOCUMENTO = :dni;
        ";

        $stmt = $pdo_mssql->prepare($sql);
        $stmt->execute([
            ':venta_id' => intval($venta_id),
            ':dni'      => trim($dni)
        ]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['NRO_REPORTE'])) {
            return trim($row['NRO_REPORTE']);
        }

        return null;

    } catch (Exception $e) {
        // Podés loguear el error si querés: error_log($e->getMessage());
        return null;
    }
}