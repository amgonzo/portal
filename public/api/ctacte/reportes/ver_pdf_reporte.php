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
require_once $rutas['middleware'];

$mysqli = conectarDB('CTACTE_');

/**
 * Helper para obtener las fechas del período operativo desde config_periodos_reglas
 */
function obtenerFechasPeriodoPDF($mysqli, $mes, $anio) {
    $resRegla = $mysqli->query("SELECT * FROM config_periodos_reglas WHERE mes_periodo = $mes");
    $regla = $resRegla ? $resRegla->fetch_assoc() : null;

    $regla = $regla ?? [
        'dia_inicio' => 1, 'mes_inicio' => $mes, 'resta_anio_inicio' => 0,
        'dia_fin' => 31, 'mes_fin' => $mes
    ];

    $anioInicio = $anio - (int)$regla['resta_anio_inicio'];
    $mesFin     = (int)$regla['mes_fin'];
    $diaFin     = (int)$regla['dia_fin'];

    $ultimoDiaReal = (int)date('t', strtotime(sprintf('%04d-%02d-01', $anio, $mesFin)));
    if ($diaFin > $ultimoDiaReal) {
        $diaFin = $ultimoDiaReal;
    }

    $periodoCodigo = sprintf('%04d-%02d', $anio, $mes);
    $periodoDesde  = sprintf('%04d-%02d-%02d 00:00:00', $anioInicio, $regla['mes_inicio'], $regla['dia_inicio']);
    $periodoHasta  = sprintf('%04d-%02d-%02d 23:59:59', $anio, $mesFin, $diaFin);

    return [$periodoCodigo, $periodoDesde, $periodoHasta];
}

$tipo  = $_GET['tipo']  ?? '';
$desde = ($_GET['desde'] ?? '') !== '' ? $_GET['desde'] . " 00:00:00" : '';
$hasta = ($_GET['hasta'] ?? '') !== '' ? $_GET['hasta'] . " 23:59:59" : '';

$sql = "";
$titulo = "";
$subtituloPersona = "";
$categoria = $_GET['cat'] ?? 'TODAS';

switch ($tipo) {

    case 'estado_cuenta':
        $dni  = trim($_GET['dni'] ?? '');
        $mes  = intval($_GET['mes'] ?? date('n'));
        $anio = intval($_GET['anio'] ?? date('Y'));

        list($periodoCodigo, $periodoDesde, $periodoHasta) = obtenerFechasPeriodoPDF($mysqli, $mes, $anio);

        $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
        $nombreMes = $meses[$mes] ?? $mes;

        // Datos del titular para la cabecera
        $stmtPer = $mysqli->prepare("SELECT apellido, nombre FROM personas WHERE CONVERT(dni USING utf8mb4) = CONVERT(? USING utf8mb4)");
        $stmtPer->bind_param("s", $dni);
        $stmtPer->execute();
        $persona = $stmtPer->get_result()->fetch_assoc();
        $stmtPer->close();

        $nombreCompleto = $persona ? mb_strtoupper($persona['apellido'] . ', ' . $persona['nombre']) : "DNI: $dni";

        $titulo = "ESTADO DE CUENTA - PERÍODO $nombreMes $anio";
        $subtituloPersona = "Asociado: $nombreCompleto | DNI: $dni";

        // Query incluyendo campos de anulación
        $sql = "SELECT 
                    DATE_FORMAT(fecha_compra, '%d/%m/%Y %H:%i') AS 'Fecha', 
                    CONCAT(LPAD(punto_venta_id, 4, '0'), '-', LPAD(venta_id, 8, '0')) AS 'N° Comprobante', 
                    COALESCE(tipo_comprobante, 'FACTURA') AS tipo_comprobante,
                    importe_total AS 'Monto',
                    COALESCE(anulado, 0) AS anulado,
                    COALESCE(motivo_anulacion, '') AS motivo_anulacion
                FROM compras_cabecera
                WHERE CONVERT(dni_empleado USING utf8mb4) = CONVERT('$dni' USING utf8mb4)
                  AND fecha_compra BETWEEN '$periodoDesde' AND '$periodoHasta'
                ORDER BY fecha_compra ASC";

        // Traemos datos de límites y porcentaje del período operativo
        $resConf = $mysqli->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_descuento_default' LIMIT 1");
        $pctDefault = ($resConf && $rowConf = $resConf->fetch_assoc()) ? floatval($rowConf['valor']) : 30.00;

        $sqlLimites = "SELECT 
                        COALESCE(el.limite_mensual, c.limite_mensual, 0) AS limite_mensual,
                        COALESCE(p.porcentaje_descuento, $pctDefault) AS porcentaje
                       FROM personas p
                       LEFT JOIN empleados_limites el 
                           ON CONVERT(p.dni USING utf8mb4) = CONVERT(el.dni USING utf8mb4) 
                           AND el.periodo_codigo = '$periodoCodigo'
                       LEFT JOIN categorias c ON p.idcategoria = c.idcategoria
                       WHERE CONVERT(p.dni USING utf8mb4) = CONVERT('$dni' USING utf8mb4) LIMIT 1";
        $resLim = $mysqli->query($sqlLimites);
        $datosLim = $resLim ? $resLim->fetch_assoc() : null;

        $limiteMensual = floatval($datosLim['limite_mensual'] ?? 0);
        $porcentajeDesc = floatval($datosLim['porcentaje'] ?? $pctDefault);
        break;

    case 'resumen_mensual':
        $mes  = intval($_GET['mes'] ?? date('n'));
        $anio = intval($_GET['anio'] ?? date('Y'));

        list($periodoCodigo, $periodoDesde, $periodoHasta) = obtenerFechasPeriodoPDF($mysqli, $mes, $anio);

        $meses = [1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio', 7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'];
        $nombreMes = $meses[$mes] ?? $mes;

        $titulo = "RESUMEN MENSUAL DE GASTOS - PERÍODO $nombreMes $anio";

        $resConf = $mysqli->query("SELECT valor FROM configuracion WHERE clave = 'porcentaje_descuento_default' LIMIT 1");
        $pctDefault = ($resConf && $row = $resConf->fetch_assoc()) ? floatval($row['valor']) : 30.00;

        // Calculamos el Consumido descartando los comprobantes anulados en tiempo real
        $sql = "SELECT 
                    CONCAT(p.apellido, ', ', p.nombre) AS 'Nombre Completo',
                    p.dni AS 'DNI',
                    COALESCE(el.limite_mensual, c.limite_mensual, 0) AS 'Límite Mensual',
                    COALESCE(consumos.total_consumido, 0) AS 'Consumido',
                    COALESCE(p.porcentaje_descuento, $pctDefault) AS '%',
                    ROUND(
                        LEAST(
                            COALESCE(consumos.total_consumido, 0), 
                            COALESCE(el.limite_mensual, c.limite_mensual, 0)
                        ) * ((100 - COALESCE(p.porcentaje_descuento, $pctDefault)) / 100), 2
                    ) AS 'Con Desc',
                    GREATEST(
                        COALESCE(consumos.total_consumido, 0) - COALESCE(el.limite_mensual, c.limite_mensual, 0), 
                        0
                    ) AS 'Saldo Excedido',
                    ROUND(
                        (
                            LEAST(
                                COALESCE(consumos.total_consumido, 0), 
                                COALESCE(el.limite_mensual, c.limite_mensual, 0)
                            ) * ((100 - COALESCE(p.porcentaje_descuento, $pctDefault)) / 100)
                        ) + GREATEST(
                            COALESCE(consumos.total_consumido, 0) - COALESCE(el.limite_mensual, c.limite_mensual, 0), 
                            0
                        ), 2
                    ) AS 'Total a Descontar'
                FROM personas p
                LEFT JOIN empleados_limites el 
                    ON CONVERT(p.dni USING utf8mb4) = CONVERT(el.dni USING utf8mb4) AND el.periodo_codigo = '$periodoCodigo'
                LEFT JOIN categorias c ON p.idcategoria = c.idcategoria
                LEFT JOIN (
                    SELECT 
                        dni_empleado, 
                        SUM(
                            CASE 
                                WHEN UPPER(tipo_comprobante) LIKE '%CREDITO%' OR UPPER(tipo_comprobante) LIKE '%NC%' THEN -importe_total 
                                ELSE importe_total 
                            END
                        ) AS total_consumido
                    FROM compras_cabecera
                    WHERE fecha_compra BETWEEN '$periodoDesde' AND '$periodoHasta'
                      AND COALESCE(anulado, 0) = 0
                    GROUP BY dni_empleado
                ) consumos ON CONVERT(p.dni USING utf8mb4) = CONVERT(consumos.dni_empleado USING utf8mb4)
                WHERE COALESCE(consumos.total_consumido, 0) > 0 OR el.periodo_codigo IS NOT NULL
                ORDER BY p.apellido ASC, p.nombre ASC";
        break;

    default:
        die("Tipo de reporte no configurado.");
}

$res = $mysqli->query($sql);

if (!$res || $res->num_rows === 0) {
    ob_end_clean();
    echo "
    <div style='font-family:sans-serif; text-align:center; padding:50px; border:2px solid #ccc; border-radius:10px; margin:50px;'>
        <h2 style='color:#e74c3c;'>Sin registros encontrados</h2>
        <p style='font-size:18px;'>No se encontraron movimientos para el reporte: <b>".str_replace('_',' ',$tipo)."</b></p>
        <hr>
        <button onclick='window.close()' style='padding:10px 20px; cursor:pointer;'>Cerrar Ventana</button>
    </div>";
    exit;
}

try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(10, 15, 10);
    $pdf->AddPage();

    $ruta_logo = "../img/logo.png";
    if (!file_exists($ruta_logo)) {
        $ruta_logo = "../../img/logo.png";
    }
    if (file_exists($ruta_logo)) {
        $pdf->Image($ruta_logo, 10, 10, 30, 0, 'PNG');
    }

    $pdf->SetY(12);
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->Cell(0, 8, $titulo, 0, 1, 'C');

    if (!empty($subtituloPersona)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, $subtituloPersona, 0, 1, 'C');
    }

    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, "Fecha de generación: " . date('d/m/Y H:i'), 0, 1, 'C');
    $pdf->Ln(4);

    if ($tipo === 'estado_cuenta') {
        
        $html = '
        <table border="0.5" cellpadding="4" style="font-size: 8.5pt;">
            <thead>
                <tr style="background-color: #333; color: white; font-weight: bold; text-align: center;">
                    <th width="6%">#</th>
                    <th width="22%">FECHA</th>
                    <th width="22%">N° COMPROBANTE</th>
                    <th width="18%">TIPO</th>
                    <th width="16%">IMPORTE</th>
                    <th width="16%">SALDO ACUM.</th>
                </tr>
            </thead>
            <tbody>';

        $i = 1;
        $saldoAcumulado = 0;

        while ($row = $res->fetch_assoc()) {
            $monto = floatval($row['Monto']);
            $tipoComp = strtoupper(trim($row['tipo_comprobante']));
            $esAnulado = (int)$row['anulado'] === 1;
            
            $esNotaCredito = (strpos($tipoComp, 'NOTA_CREDITO') !== false || strpos($tipoComp, 'CREDITO') !== false || strpos($tipoComp, 'NC') !== false);

            if ($esAnulado) {
                // SI ESTÁ ANULADO: NO suma ni resta al saldo acumulado
                $motivo = !empty($row['motivo_anulacion']) ? ' (' . $row['motivo_anulacion'] . ')' : '';
                $etiquetaTipo = '<span style="color: #d9534f; font-weight: bold;">ANULADO</span>';
                $montoFormateado = '<span style="text-decoration: line-through; color: #777;">$ ' . number_format($monto, 2, ',', '.') . '</span>';
                $estiloMonto = '';
            } else if ($esNotaCredito) {
                $saldoAcumulado -= $monto;
                $montoFormateado = '-$ ' . number_format($monto, 2, ',', '.');
                $etiquetaTipo = '<span style="color: #c00000; font-weight: bold;">NOTA CRÉDITO</span>';
                $estiloMonto = 'color: #c00000; font-weight: bold;';
            } else {
                $saldoAcumulado += $monto;
                $montoFormateado = '$ ' . number_format($monto, 2, ',', '.');
                $etiquetaTipo = 'FACTURA';
                $estiloMonto = '';
            }

            $html .= '<tr>
                <td width="6%" style="text-align: center;">' . $i . '</td>
                <td width="22%" style="text-align: center;">' . $row['Fecha'] . '</td>
                <td width="22%" style="text-align: center;">' . $row['N° Comprobante'] . '</td>
                <td width="18%" style="text-align: center;">' . $etiquetaTipo . '</td>
                <td width="16%" style="text-align: right; ' . $estiloMonto . '">' . $montoFormateado . '</td>
                <td width="16%" style="text-align: right; font-weight: bold;">$ ' . number_format($saldoAcumulado, 2, ',', '.') . '</td>
            </tr>';
            $i++;
        }

        $montoCubierto = min(max($saldoAcumulado, 0), $limiteMensual);
        $saldoExcedido = max($saldoAcumulado - $limiteMensual, 0);
        $descTotal     = round($montoCubierto * ((100 - $porcentajeDesc) / 100), 2);
        $totalAPagar   = $descTotal + $saldoExcedido;

        $html .= '
            <tr style="background-color: #f8f9fa; font-weight: bold;">
                <td colspan="4" style="text-align: right;">TOTAL CONSUMIDO EN EL MES:</td>
                <td colspan="2" style="text-align: right; font-size: 9pt;">
                    $ ' . number_format($saldoAcumulado, 2, ',', '.') . '
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">LÍMITE MENSUAL ASIGNADO:</td>
                <td colspan="2" style="text-align: right;">
                    $ ' . number_format($limiteMensual, 2, ',', '.') . '
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">PORCENTAJE DE DESCUENTO:</td>
                <td colspan="2" style="text-align: right;">
                    ' . number_format($porcentajeDesc, 0) . '%
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">CON DESC:</td>
                <td colspan="2" style="text-align: right;">
                    $ ' . number_format($descTotal, 2, ',', '.') . '
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">SALDO EXCEDIDO:</td>
                <td colspan="2" style="text-align: right; color: ' . ($saldoExcedido > 0 ? '#c00000' : '#000') . ';">
                    $ ' . number_format($saldoExcedido, 2, ',', '.') . '
                </td>
            </tr>
            <tr style="background-color: #e9ecef; font-weight: bold; font-size: 9.5pt;">
                <td colspan="4" style="text-align: right;">TOTAL FINAL A DESCONTAR:</td>
                <td colspan="2" style="text-align: right; color: #a00000;">
                    $ ' . number_format($totalAPagar, 2, ',', '.') . '
                </td>
            </tr>
        </tbody>
        </table>';

    } else {

        $columnas = [];
        $primer_fila = $res->fetch_assoc();
        foreach ($primer_fila as $key => $value) {
            $columnas[] = $key;
        }

        $ancho_nro = 4; 
        $ancho_resto = (100 - $ancho_nro) / count($columnas);

        $html = '<table border="0.5" cellpadding="3" style="font-size: 7.5pt;">';
        
        $html .= '<tr style="background-color: #444; color: white; font-weight: bold; text-align: center;">';
        $html .= '<th width="'.$ancho_nro.'%">#</th>';
        foreach ($columnas as $col) {
            $html .= '<th width="'.$ancho_resto.'%">'.mb_strtoupper($col).'</th>';
        }
        $html .= '</tr>';

        function formatear($campo, $valor) {
            if ($campo == 'DNI' || $campo == 'DOCUMENTO') return number_format($valor, 0, ',', '.');
            if ($campo == '%') return number_format(floatval($valor), 0) . '%';
            return mb_strtoupper($valor);
        }

        $res->data_seek(0);
        $i = 1;
        while ($row = $res->fetch_assoc()) {
            $html .= '<tr>';
            $html .= '<td width="'.$ancho_nro.'%" style="text-align: center;">' . $i . '</td>';
            
            foreach ($columnas as $col) {
                $val = formatear($col, $row[$col] ?? '-');
                $align = 'left'; 
                
                if (in_array(mb_strtoupper($col), ['LÍMITE MENSUAL', 'CONSUMIDO', 'CON DESC', 'SALDO EXCEDIDO', 'TOTAL A DESCONTAR', 'MONTO', 'IMPORTE', 'TOTAL'])) {
                    $align = 'right';
                    if (is_numeric($row[$col])) {
                        $val = '$ ' . number_format($row[$col], 2, ',', '.');
                    }
                } 
                elseif (in_array(mb_strtoupper($col), ['DNI', 'DOCUMENTO', 'FECHA', '%'])) {
                    $align = 'center';
                }

                $html .= '<td width="'.$ancho_resto.'%" style="text-align: '.$align.';">'.$val.'</td>';
            }
            
            $html .= '</tr>';
            $i++;
        }

        $html .= '</table>';
    }

    $pdf->writeHTML($html, true, false, true, false, '');

    if (ob_get_length()) ob_end_clean();
    $pdf->Output('reporte.pdf', 'I');

} catch (Exception $e) {
    ob_end_clean();
    echo "Error: " . $e->getMessage();
}