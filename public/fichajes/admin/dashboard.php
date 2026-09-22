<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Panel de Fichajes - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">
        
        <!-- ========================================== -->
        <!-- 📌 ENCABEZADO Y TÍTULO                     -->
        <!-- ========================================== -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Fichajes</h2>
                <p class="text-muted mb-0">
                    Resumen de la jornada actual para 
                    <strong class="text-dark">
                        <?php 
                            setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');
                            echo strftime('%A, %d de %B de %Y'); // Ejemplo: Lunes, 21 de Septiembre de 2026
                        ?>
                    </strong>.
                </p>
            </div>
            <div data-permiso="fichajes_sincronizar">
                <button type="button" id="btnSincronizarFichajes" class="btn btn-primary fw-semibold shadow-sm">
                    <i id="icono-sync" class="bi bi-arrow-clockwise me-2"></i> <span id="texto-sync">Actualizar Fichajes</span>
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 📊 TARJETAS DEL DASHBOARD (MÉTRICAS)       -->
        <!-- ========================================== -->
        <div class="row g-3 mb-4"> 
        <!-- 1. Empleados activos --> 
        <div class="col-12 col-sm-6 col-xl-3"> 
            <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);"> 
                <div class="d-flex align-items-center justify-content-between"> 
                    <div> 
                        <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                            <span data-diccionario="empleado_plural"></span> activos
                        </h6> 
                        <h3 id="card-empleados-activos" class="fw-bold text-dark mb-0">0</h3> 
                    </div> 
                    <div class="bg-primary-subtle text-primary rounded-3 p-3"> 
                        <i class="bi bi-people-fill" style="font-size: 2rem; display: block; line-height: 1;"></i> 
                    </div> 
                </div> 
                <div class="mt-2">
                    <small class="text-muted">
                        <span data-diccionario="empleado_plural"></span> habilitados hoy
                    </small>
                </div> 
            </div> 
        </div>
            <!-- 2. Marcas de hoy -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Marcas de Hoy</h6>
                            <h3 id="card-marcas-hoy" class="fw-bold text-success mb-0">0</h3>
                        </div>
                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="bi bi-fingerprint" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-muted">Registros totales en la jornada</small></div>
                </div>
            </div>

            <!-- 3. Jornadas pendientes -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Jornadas Pendientes</h6>
                            <h3 id="card-jornadas-pendientes" class="fw-bold text-warning mb-0">0</h3>
                        </div>
                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="bi bi-hourglass-split" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-warning fw-semibold"><i class="bi bi-clock"></i> Necesitan procesamiento</small></div>
                </div>
            </div>

            <!-- 4. Jornadas con revisión -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100" style="border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">Con Revisión</h6>
                            <h3 id="card-jornadas-revision" class="fw-bold text-danger mb-0">0</h3>
                        </div>
                        <div class="bg-danger-subtle text-danger rounded-3 p-3">
                            <i class="bi bi-exclamation-octagon" style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>
                    <div class="mt-2"><small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle"></i> Requieren revisión</small></div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- 📑 CONTENIDO PRINCIPAL (ÚLTIMAS MARCAS)    -->
        <!-- ========================================== -->
        <div class="row g-4">
            
            <!-- Listado / Tabla de Últimas Marcas -->
            <div class="col-12 col-lg-8 mb-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-muted"></i> Últimas Marcas</h5>
                        <span class="badge bg-light text-dark border">Últimos registros</span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Hora</th>
                                    <th>Empleado</th>
                                    <th>Tipo / Origen</th>
                                    <th>Lector</th>
                                    <th class="text-center">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-ultimas-marcas-body">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Cargando últimas marcas recientes...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panel Lateral de Acceso Rápido / Accesos Directos -->
            <div class="col-12 col-lg-4 mb-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-lightning-charge me-2 text-warning"></i> Acciones Rápidas</h5>
                    <p class="text-muted small mb-3">Accesos directos para la gestión diaria de fichajes.</p>
                    
                    <div class="d-flex flex-column gap-2">
                        <a href="revisar_incidencias.php" class="btn btn-outline-danger text-start d-flex justify-content-between align-items-center p-3">
                            <div>
                                <i class="bi bi-shield-exclamation me-2"></i> Resolver Casos Ambiguos
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="procesar_jornadas.php" class="btn btn-outline-warning text-start d-flex justify-content-between align-items-center p-3">
                            <div>
                                <i class="bi bi-gear me-2"></i> Procesar Jornadas Pendientes
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="reportes_diarios.php" class="btn btn-outline-secondary text-start d-flex justify-content-between align-items-center p-3">
                            <div>
                                <i class="bi bi-file-earmark-text me-2"></i> Ver Reporte Completo
                            </div>
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Scripts y Modales -->
    <?php include "modal/modal_detalle_marca.php"; ?> 
    <script src="js/dashboard_fichajes.js?v=<?php echo time(); ?>"></script>
</body>

</html>