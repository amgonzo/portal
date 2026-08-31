<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Panel - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Tablero de Control</h2>
                <p class="text-muted">
                    Resumen mensual de consumos de personal para 
                    <strong class="text-dark text-capitalize">
                        <?php 
                            // Seteamos el idioma en español para las fechas
                            setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');
                            echo strftime('%B %Y'); // Muestra por ejemplo: "Julio 2026"
                        ?>
                    </strong>.
                </p>
            </div>
            <div data-permiso="cajas_sincronizar">
                <button type="button" id="btnSincronizar" class="btn btn-primary fw-semibold shadow-sm">
                    <i id="icono-sync" class="bi bi-arrow-clockwise me-2"></i> <span id="texto-sync">Sincronizar Cajas Ahora</span>
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 📊 TARJETAS DEL DASHBOARD (MÉTRICAS)       -->
        <!-- ========================================== -->
        <div class="row g-3 mb-4">
            <!-- Consolidado Mensual -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Consolidado Mensual</h6>
                            <h3 id="card-consumo-total" class="fw-bold text-dark mb-0">$0,00</h3>
                        </div>
                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="bi bi-cash-stack" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">A retener en próximos sueldos</small></div>
                </div>
            </div>

            <!-- Empleados Activos -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Asociados con Uso</h6>
                            <h3 id="card-empleados-uso" class="fw-bold text-dark mb-0">0 <span class="fs-5 text-muted fw-normal">/ 0</span></h3>
                        </div>
                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="bi bi-people" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="progress" style="height: 6px;">
                            <div id="progreso-empleados" class="progress-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Excediendo Límites -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Excediendo Límites</h6>
                            <h3 id="card-alertas-limite" class="fw-bold text-danger mb-0">0 <span class="fs-6 fw-normal text-muted">asociados</span></h3>
                        </div>
                        <div class="bg-danger-subtle text-danger rounded-3 p-3">
                            <i class="bi bi-exclamation-triangle" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-danger fw-semibold"><i class="bi bi-arrow-up-short"></i> Superan el 85% del cupo</small></div>
                </div>
            </div>

            <!-- Última Sincronización -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Última Carga Cajas</h6>
                            <h5 id="fecha-ultima-carga" class="fw-bold text-dark mb-0 mt-1">Cargando...</h5>
                        </div>
                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="bi bi-database-check" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted"><span class="badge bg-success-subtle text-success">Conexión MSSQL OK</span></small></div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 📑 CONTENIDO PRINCIPAL                      -->
        <!-- ========================================== -->
        <div class="row g-4">
            
            <!-- Listado de Consumos -->
            <div class="col-12 col-lg-8 mb-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-muted"></i> Últimos Consumos Recibidos</h5>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket Cajas</th>
                                    <th>Empleado</th>
                                    <th>DNI</th>
                                    <th>Fecha Compra</th>
                                    <th class="text-end">Monto Total</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-consumos-body">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Cargando consumos recientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4 mb-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <h5 class="fw-bold mb-1 text-danger"><i class="bi bi-shield-exclamation me-2"></i> Riesgo de Límite</h5>
                    <p class="text-muted small mb-3">Asociados próximos a agotar su crédito mensual disponible.</p>
                    
                    <div id="contenedor-alertas-lista" class="d-flex flex-column gap-3" style="max-height: 550px; overflow-y: auto; padding-right: 4px;">
                        <p class="text-center text-muted small py-3">Cargando alertas de límite...</p>
                    </div>
                </div>
            </div>

    </div>

    <!-- El script JS para manejar todo el flujo de datos dinámico -->
    <?php include "modal/modal_detalle_ticket.php"; ?> 
    <script src="js/dashboard.js?v=<?php echo time(); ?>"></script>
</body>

</html>