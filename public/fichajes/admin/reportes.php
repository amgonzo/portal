<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Reportes - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
    <div class="d-flex justify-content-between mb-4">
        <h2>Reportes y Listados</h2>
    </div>
    <div class="card shadow-sm p-3">
        <table id="tablaReportes" class="table table-striped table-bordered table-hover w-100">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Nombre del Reporte</th>
                    <th>Descripción</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="listaReportes">
                </tbody>
        </table>
    </div>
</div>

    <div class="modal fade" id="ModalReporte" tabindex="-1">
        <div class="modal-dialog">
            <form id="formReporte">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="tituloModalReporte">Configurar Reporte</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="reporte_tipo" name="tipo">

                        <!-- Rango de Fechas tradicional -->
                        <div id="div_fechas">
                            <div class="mb-3">
                                <label>Desde Fecha</label>
                                <input type="date" id="rep_desde" name="desde" class="form-control" value="<?= date('Y-m-01'); ?>">
                            </div>
                            <div class="mb-3">
                                <label>Hasta Fecha</label>
                                <input type="date" id="rep_hasta" name="hasta" class="form-control" value="<?= date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <!-- SELECTOR DE MES Y AÑO (Para Resumen Mensual) -->
                        <div id="div_mes_anio" style="display:none;">
                            <div class="mb-3">
                                <label>Mes</label>
                                <select id="rep_mes" name="mes" class="form-control">
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Año</label>
                                <select id="rep_anio" name="anio" class="form-control">
                                    <?php 
                                    $anioActual = date('Y');
                                    for ($a = $anioActual; $a >= $anioActual - 5; $a--) {
                                        echo "<option value='$a'>$a</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- Categoría / Select aux -->
                        <div class="mb-3" id="div_categoria" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label id="lbl_categoria" class="fw-bold mb-0">Filtrar por Categoría</label>
                                <div class="form-check form-switch mb-0" id="div_switch_inactivos" style="display:none;">
                                    <input class="form-check-input" type="checkbox" id="chk_inactivos_rep" onchange="cargarPersonasReporte(this.checked)">
                                    <label class="form-check-label small text-muted" for="chk_inactivos_rep">Incluir inactivos</label>
                                </div>
                            </div>
                            <select id="rep_categoria" class="form-control"></select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-primary" onclick="generarPDF()">
                            <i class="fas fa-file-pdf"></i> Generar PDF
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="<?php echo versionar('js/reportes.js'); ?>"></script>
</body>
</html>