<?php
// =========================================================
// DASHBOARD SSO RRHH
// =========================================================
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/menu.php';
?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <!-- ENCABEZADO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-0">Panel de Recursos Humanos</h2>
            <p class="text-muted mb-0">
                Resumen de <span data-diccionario="empleado_plural"></span>,
                legajos y documentación.
            </p>
        </div>

        <a href="empleados.php" class="btn btn-primary fw-semibold shadow-sm">
            <i class="bi bi-people me-2"></i>
            <span data-diccionario="empleado_plural"></span>
        </a>
    </div>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-4">
        <div class="row g-3 align-items-end">

            <div class="col-12 col-sm-6 col-xl-3">
                <label for="fecha_desde" class="form-label fw-semibold">Desde</label>
                <input type="date" id="fecha_desde" class="form-control">
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <label for="fecha_hasta" class="form-label fw-semibold">Hasta</label>
                <input type="date" id="fecha_hasta" class="form-control">
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <button type="button" id="btnConsultar" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>Consultar período
                </button>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <button type="button" id="btnMesActual" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-calendar-month me-2"></i>Mes actual
                </button>
            </div>

        </div>
    </div>

    <!-- INDICADORES -->
    <div class="row g-3 mb-4">

        <!-- EMPLEADOS ACTIVOS -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            <span data-diccionario="empleado_plural"></span> activos
                        </h6>
                        <h3 id="card-empleados-activos" class="fw-bold text-dark mb-0">0</h3>
                    </div>

                    <div class="bg-primary-subtle text-primary rounded-3 p-3">
                        <i class="bi bi-people-fill fs-2 d-block"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Personal actualmente activo</small>
                </div>
            </div>
        </div>

        <!-- DOCUMENTOS -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            Documentos por vencer
                        </h6>
                        <h3 id="card-documentos-vencer" class="fw-bold text-danger mb-0">0</h3>
                    </div>

                    <div class="bg-danger-subtle text-danger rounded-3 p-3">
                        <i class="bi bi-file-earmark-excel fs-2 d-block"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Vencimiento próximo</small>
                </div>
            </div>
        </div>

        <!-- LEGAJOS -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            Legajos registrados
                        </h6>
                        <h3 id="card-legajos-completos" class="fw-bold text-info mb-0">0</h3>
                    </div>

                    <div class="bg-info-subtle text-info rounded-3 p-3">
                        <i class="bi bi-folder2-open fs-2 d-block"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Legajos registrados</small>
                </div>
            </div>
        </div>

        <!-- ALTAS -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            Altas del período
                        </h6>
                        <h3 id="card-altas-periodo" class="fw-bold text-success mb-0">0</h3>
                    </div>

                    <div class="bg-success-subtle text-success rounded-3 p-3">
                        <i class="bi bi-person-plus fs-2 d-block"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Nuevos ingresos</small>
                </div>
            </div>
        </div>

        <!-- INACTIVOS -->
        <div class="col-12 col-sm-6 col-xl">
            <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            Empleados inactivos
                        </h6>
                        <h3 id="card-bajas-periodo" class="fw-bold text-warning mb-0">0</h3>
                    </div>

                    <div class="bg-warning-subtle text-warning rounded-3 p-3">
                        <i class="bi bi-person-dash fs-2 d-block"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Personal no activo</small>
                </div>
            </div>
        </div>

    </div>

    <!-- TABLAS -->
    <div class="row g-4">

        <!-- DOCUMENTACIÓN POR VENCER -->
        <div class="col-12 col-xl-7 mb-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white h-100">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">
                            <i class="bi bi-file-earmark-exclamation text-danger me-2"></i>
                            Documentación por vencer
                        </h5>
                        <small class="text-muted">
                            Documentos próximos a su fecha de vencimiento.
                        </small>
                    </div>

                    <a href="documentos.php" class="btn btn-sm btn-outline-secondary">
                        Ver documentos
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th><span data-diccionario="empleado_singular"></span></th>
                                <th>Documento</th>
                                <th>Vencimiento</th>
                            </tr>
                        </thead>

                        <tbody id="tabla-documentos-vencer">
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">
                                    Sin datos para mostrar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- PERSONAL POR SECTOR -->
        <div class="col-12 col-xl-5 mb-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white h-100">

                <div class="mb-3">
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Personal por sector
                    </h5>
                    <small class="text-muted">
                        Cantidad de <span data-diccionario="empleado_plural"></span>
                        activos por sector.
                    </small>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sector</th>
                                <th class="text-end">
                                    <span data-diccionario="empleado_plural"></span> activos
                                </th>
                            </tr>
                        </thead>

                        <tbody id="tabla-personal-sectores">
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">
                                    Sin datos para mostrar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>

</div>

<script src="js/dashboard.js?v=<?= time() ?>"></script>