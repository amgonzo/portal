<?php
// =========================================================
// DASHBOARD SSO RRHH
// =========================================================
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/menu.php';
?>

<style>
    .dashboard-header {
        margin-bottom: 24px;
    }

    .dashboard-header h2 {
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .dashboard-header p {
        color: #8492a6;
        margin: 0;
    }

    .dashboard-card {
        background: #fff;
        border: 1px solid #edf0f2;
        border-radius: 9px;
        box-shadow: 0 2px 5px rgba(0,0,0,.06);
        height: 100%;
    }

    .dashboard-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }

    .kpi-card {
        padding: 20px;
        min-height: 135px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .kpi-label {
        color: #8492a6;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 9px;
    }

    .kpi-value {
        font-size: 29px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 10px;
        color: #26384a;
    }

    .kpi-description {
        color: #8492a6;
        font-size: 12px;
    }

    .kpi-icon {
        width: 48px;
        height: 48px;
        min-width: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        font-size: 22px;
    }

    .kpi-primary {
        background: #e8eef5;
        color: #315779;
    }

    .kpi-danger {
        background: #fde9e7;
        color: #dc5146;
    }

    .kpi-success {
        background: #e5f4eb;
        color: #29965b;
    }

    .kpi-info {
        background: #e5f1f8;
        color: #3984ad;
    }

    .kpi-warning {
        background: #fff2dc;
        color: #c58a25;
    }

    .dashboard-panels {
        display: grid;
        grid-template-columns: minmax(0, 7fr) minmax(0, 5fr);
        gap: 18px;
        align-items: stretch;
    }

    .dashboard-panel {
        padding: 20px;
        min-width: 0;
    }

    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .panel-header h5 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .panel-header small {
        color: #8492a6;
        font-size: 12px;
    }

    .dashboard-panel .table {
        margin-bottom: 0;
    }

    .dashboard-panel .table th {
        background: #f0f3f5;
        font-size: 12px;
        white-space: nowrap;
    }

    .dashboard-panel .table td {
        font-size: 13px;
        vertical-align: middle;
    }

    @media (max-width: 1199px) {
        .dashboard-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .dashboard-panels {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 767px) {
        .dashboard-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dashboard-panels {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 420px) {
        .dashboard-kpis {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="container-fluid px-4 py-4">

    <!-- ENCABEZADO -->
    <div class="dashboard-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2>Panel de Recursos Humanos</h2>
            <p>
                Resumen de <span data-diccionario="empleado_plural"></span>,
                legajos y documentación.
            </p>
        </div>

        <a href="empleados.php" class="btn btn-primary">
            <i class="bi bi-people me-2"></i>
            <span data-diccionario="empleado_plural"></span>
        </a>
    </div>

    <!-- FILTROS -->
    <div class="dashboard-card p-3 mb-4">
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

    <!-- INDICADORES -->
    <div class="dashboard-kpis">

        <!-- EMPLEADOS ACTIVOS -->
        <div class="dashboard-card kpi-card">
            <div>
                <div class="kpi-label">
                    <span data-diccionario="empleado_plural"></span> activos
                </div>
                <div id="card-empleados-activos" class="kpi-value">0</div>
                <div class="kpi-description">Personal actualmente activo</div>
            </div>

            <div class="kpi-icon kpi-primary">
                <i class="bi bi-people"></i>
            </div>
        </div>

        <!-- DOCUMENTOS -->
        <div class="dashboard-card kpi-card">
            <div>
                <div class="kpi-label">Documentos por vencer</div>
                <div id="card-documentos-vencer" class="kpi-value">0</div>
                <div class="kpi-description">Vencimiento próximo</div>
            </div>

            <div class="kpi-icon kpi-danger">
                <i class="bi bi-file-earmark-excel"></i>
            </div>
        </div>

        <!-- LEGAJOS -->
        <div class="dashboard-card kpi-card">
            <div>
                <div class="kpi-label">Legajos registrados</div>
                <div id="card-legajos-completos" class="kpi-value">0</div>
                <div class="kpi-description">Legajos registrados</div>
            </div>

            <div class="kpi-icon kpi-info">
                <i class="bi bi-folder2-open"></i>
            </div>
        </div>

        <!-- ALTAS -->
        <div class="dashboard-card kpi-card">
            <div>
                <div class="kpi-label">Altas del período</div>
                <div id="card-altas-periodo" class="kpi-value">0</div>
                <div class="kpi-description">Nuevos ingresos</div>
            </div>

            <div class="kpi-icon kpi-success">
                <i class="bi bi-person-plus"></i>
            </div>
        </div>

        <!-- INACTIVOS -->
        <div class="dashboard-card kpi-card">
            <div>
                <div class="kpi-label">Empleados inactivos</div>
                <div id="card-bajas-periodo" class="kpi-value">0</div>
                <div class="kpi-description">Personal no activo</div>
            </div>

            <div class="kpi-icon kpi-warning">
                <i class="bi bi-person-dash"></i>
            </div>
        </div>

    </div>

    <!-- TABLAS -->
    <div class="dashboard-panels">

        <!-- DOCUMENTACIÓN POR VENCER -->
        <div class="dashboard-card dashboard-panel">

            <div class="panel-header">
                <div>
                    <h5>
                        <i class="bi bi-file-earmark-exclamation text-danger me-2"></i>
                        Documentación por vencer
                    </h5>
                    <small>Documentos próximos a su fecha de vencimiento.</small>
                </div>

                <a href="documentos.php" class="btn btn-sm btn-outline-secondary">
                    Ver documentos
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
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

        <!-- PERSONAL POR SECTOR -->
        <div class="dashboard-card dashboard-panel">

            <div class="panel-header">
                <div>
                    <h5>
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Personal por sector
                    </h5>
                    <small>
                        Cantidad de <span data-diccionario="empleado_plural"></span>
                        activos por sector.
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
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

<script src="js/dashboard.js?v=<?= time() ?>"></script>