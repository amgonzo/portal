
<!DOCTYPE HTML>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Dashboard de Ventas - <?php echo $empresa; ?></title>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top:30px;">

        <!-- ENCABEZADO -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Dashboard de Ventas</h2>
                <p class="text-muted mb-0">Resumen de ventas, costos y rentabilidad del supermercado.</p>
            </div>
            <div data-permiso="dashboard_sincronizar">
                <button type="button" id="btnSincronizar" class="btn btn-primary fw-semibold shadow-sm">
                    <i id="icono-sync" class="bi bi-arrow-clockwise me-2"></i>
                    <span id="texto-sync">Sincronizar día cerrado</span>
                </button>
            </div>
        </div>

        <!-- FILTROS -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="fecha_desde" class="form-label fw-semibold">Desde</label>
                    <input type="date" id="fecha_desde" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="fecha_hasta" class="form-label fw-semibold">Hasta</label>
                    <input type="date" id="fecha_hasta" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <button type="button" id="btnConsultar" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i>Consultar período
                    </button>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <button type="button" id="btnMesActual" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-calendar-month me-2"></i>Mes actual
                    </button>
                </div>
            </div>
        </div>

        <!-- TARJETAS PRINCIPALES -->
        <div class="row g-3 mb-4">

            <!-- RECAUDACION -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Recaudación</h6>
                            <h3 id="card-recaudacion" class="fw-bold text-dark mb-0">$0,00</h3>
                        </div>
                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="bi bi-cash-stack" style="font-size:2rem;display:block;line-height:1"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">Importe total vendido</small></div>
                </div>
            </div>

            <!-- GANANCIA BRUTA -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Diferencia bruta</h6>
                            <h3 id="card-diferencia" class="fw-bold text-success mb-0">$0,00</h3>
                        </div>
                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="bi bi-graph-up-arrow" style="font-size:2rem;display:block;line-height:1"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">Recaudación menos costo</small>
                    </div>
                </div>
            </div>

            <!-- TICKETS -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Tickets</h6>
                            <h3 id="card-tickets" class="fw-bold text-dark mb-0">0</h3>
                        </div>
                        <div class="bg-info-subtle text-info rounded-3 p-3">
                            <i class="bi bi-receipt" style="font-size:2rem;display:block;line-height:1"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">Cantidad de operaciones</small></div>
                </div>
            </div>

            <!-- TICKET PROMEDIO -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Ticket promedio</h6>
                            <h3 id="card-ticket-promedio" class="fw-bold text-dark mb-0">$0,00</h3>
                        </div>
                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="bi bi-cart-check" style="font-size:2rem;display:block;line-height:1"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">Venta promedio por ticket</small></div>
                </div>
            </div>

        </div>

        <!-- SEGUNDA FILA DE INDICADORES -->
        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm p-3 h-100 rounded-3">
                    <h6 class="text-muted text-uppercase fw-semibold small">Costo total</h6>
                    <h4 id="card-costo" class="fw-bold mb-0">$0,00</h4>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm p-3 h-100 rounded-3">
                    <h6 class="text-muted text-uppercase fw-semibold small">Marcación</h6>
                    <h4 id="card-marcacion" class="fw-bold mb-0">0,00%</h4>
                    <small class="text-muted">Diferencia sobre costo</small>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm p-3 h-100 rounded-3">
                    <h6 class="text-muted text-uppercase fw-semibold small">Unidades vendidas</h6>
                    <h4 id="card-unidades" class="fw-bold mb-0">0</h4>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm p-3 h-100 rounded-3">
                    <h6 class="text-muted text-uppercase fw-semibold small">% Diferencia sobre venta</h6>
                    <h4 id="card-porcentaje-venta" class="fw-bold mb-0">0,00%</h4>
                </div>
            </div>

        </div>

        <!-- ESTADO DE SINCRONIZACION -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h6 class="fw-bold mb-1">
                        <i class="bi bi-database-check me-2 text-primary"></i>Estado de sincronización
                    </h6>
                    <small class="text-muted">Última importación de datos desde las cajas</small>
                </div>
                <div class="text-sm-end">
                    <div id="fecha-ultima-carga" class="fw-semibold">Sin sincronizaciones registradas</div>
                    <small id="estado-ultima-carga" class="text-muted">Esperando información</small>
                </div>
            </div>
        </div>

        <!-- TABLA RESUMEN DIARIO -->
        <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-calendar3 me-2 text-muted"></i>Resumen diario de ventas
                    </h5>
                    <small class="text-muted">Detalle de los indicadores por cada día del período seleccionado.</small>
                </div>
                <span id="cantidad-dias" class="badge bg-light text-dark">0 días</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <th class="text-end">Recaudación</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Diferencia</th>
                            <th class="text-end">Marcación</th>
                            <th class="text-end">Tickets</th>
                            <th class="text-end">Ticket promedio</th>
                            <th class="text-end">Unidades</th>
                            <th class="text-end">Prod. promedio</th>
                            <th class="text-end">Prod. x ticket</th>
                            <th class="text-end">% s/venta</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-ventas-body">
                        <tr>
                            <td colspan="12" class="text-center py-4 text-muted">
                                Seleccioná un período para consultar las ventas.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light fw-bold" id="tabla-ventas-footer">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-2">
                                Sin datos para mostrar
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>

    <script src="js/dashboard.js?v=<?php echo time(); ?>"></script>
</body>
</html>