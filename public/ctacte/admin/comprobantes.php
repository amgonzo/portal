<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Gestión de Comprobantes - <?php echo $empresa; ?></title>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container-fluid px-4 mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Gestión y Reasignación de Comprobantes</h2>
                <p class="text-muted mb-0">Administración de tickets sin usuario o reasignación entre empleados.</p>
            </div>
        </div>

        <!-- PANEL DE BÚSQUEDA Y FILTROS -->
        <div class="card shadow-sm p-3 mb-4 bg-body-tertiary">
            <div class="row g-3 align-items-end">
                <!-- Buscador por número de ticket -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Búsqueda rápida (Ticket/PV):</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="filtro_ticket" class="form-control" placeholder="Ej: 0107-00099275" onkeyup="filtrarPorTicketEnDataTables()">
                    </div>
                </div>

                <!-- Filtro por Período -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Filtrar por Período:</label>
                    <select id="filtro_periodo" class="form-select" onchange="cargarFacturas()">
                        <option value="">-- Todos los períodos --</option>
                    </select>
                </div>

                <!-- Filtro por Empleado -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Filtrar por Empleado Origen:</label>
                    <select id="filtro_empleado" class="form-select" onchange="cargarFacturas()">
                        <option value="0" selected>Solo Compras Sin Usuario (DNI 0)</option>
                        <option value="todos">-- Todos los empleados --</option>
                    </select>
                </div>

                <!-- Filtro Mostrar Anulados -->
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Estado Comprobantes:</label>
                    <select id="filtro_anulados" class="form-select" onchange="cargarFacturas()">
                        <option value="0" selected>Ocultar Anulados</option>
                        <option value="1">Solo Comprobantes Anulados</option>
                        <option value="todos">Mostrar Todos (Incluir Anulados)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- TABLA DATATABLES -->
        <div class="card shadow-sm p-3">
            <table id="tablaPendientes" class="table table-striped table-bordered table-hover w-100 align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Comprobante / Titular</th>
                        <th>Importe Total</th>
                        <th style="width: 35%;">Detalle de Ítems</th>
                        <th class="text-center" style="width: 180px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaPendientes">
                    <!-- Cargado dinámicamente por JS y DataTables -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL REASIGNAR PERSONA -->
    <div class="modal fade" id="modalReasignar" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form id="formReasignar" onsubmit="guardarReasignacion(event)">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold">Reasignar Compra</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="reasignar_pv_id" name="punto_venta_id">
                        <input type="hidden" id="reasignar_venta_id" name="venta_id">

                        <div class="mb-3">
                            <label class="fw-bold">Detalle del Comprobante:</label>
                            <div id="info_ticket" class="p-2 bg-light border rounded"></div>
                        </div>

                        <div class="form-group mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="fw-bold mb-0">Seleccionar Empleado Destino:</label>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="chk_incluir_inactivos" onchange="cargarComboPersonas(this.checked)">
                                    <label class="form-check-label small text-muted" for="chk_incluir_inactivos">Incluir inactivos</label>
                                </div>
                            </div>
                            <select id="select_dni_nuevo" name="nuevo_dni" class="form-select" required>
                                <option value="">-- Cargando personas... --</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-body-tertiary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar y Reasignar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- LIBRERÍAS DE JS -->
    <script src="<?php echo versionar('js/comprobantes.js'); ?>"></script>
</body>
</html>