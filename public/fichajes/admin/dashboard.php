<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Panel de Fichajes - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <!-- ========================================== -->
        <!-- ENCABEZADO                                -->
        <!-- ========================================== -->

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-0">Fichajes</h2>
                <p class="text-muted mb-0">
                    Resumen de la jornada actual para
                    <strong class="text-dark">
                        <?php
                        setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');
                        echo strftime('%A, %d de %B de %Y');
                        ?>
                    </strong>.
                </p>
            </div>

            <div data-permiso="fichajes_sincronizar">
                <button
                    type="button"
                    id="btnSincronizarFichajes"
                    class="btn btn-primary fw-semibold shadow-sm">
                    <i id="icono-sync" class="bi bi-arrow-clockwise me-2"></i>
                    <span id="texto-sync">Actualizar Fichajes</span>
                </button>
            </div>
        </div>


        <!-- ========================================== -->
        <!-- TARJETAS DEL DASHBOARD                     -->
        <!-- ========================================== -->

        <div class="row g-3 mb-4">

            <!-- Empleados activos -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                <span data-diccionario="empleado_plural"></span> activos
                            </h6>
                            <h3 id="card-empleados-activos" class="fw-bold text-dark mb-0">0</h3>
                        </div>

                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="bi bi-people-fill"
                               style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>

                    <div class="mt-2">
                        <small class="text-muted">
                            <span data-diccionario="empleado_plural"></span> habilitados hoy
                        </small>
                    </div>
                </div>
            </div>

            <!-- Marcas de hoy -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Marcas de hoy
                            </h6>
                            <h3 id="card-marcas-hoy" class="fw-bold text-success mb-0">0</h3>
                        </div>

                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="bi bi-fingerprint"
                               style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>

                    <div class="mt-2">
                        <small class="text-muted">Registros totales en la jornada</small>
                    </div>
                </div>
            </div>

            <!-- Jornadas pendientes -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Jornadas pendientes
                            </h6>
                            <h3 id="card-jornadas-pendientes" class="fw-bold text-warning mb-0">0</h3>
                        </div>

                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="bi bi-hourglass-split"
                               style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>

                    <div class="mt-2">
                        <small class="text-warning fw-semibold">
                            <i class="bi bi-clock me-1"></i>
                            Necesitan procesamiento
                        </small>
                    </div>
                </div>
            </div>

            <!-- Jornadas con revisión -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100 rounded-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Con revisión
                            </h6>
                            <h3 id="card-jornadas-revision" class="fw-bold text-danger mb-0">0</h3>
                        </div>

                        <div class="bg-danger-subtle text-danger rounded-3 p-3">
                            <i class="bi bi-exclamation-octagon"
                               style="font-size: 2rem; display: block; line-height: 1;"></i>
                        </div>
                    </div>

                    <div class="mt-2">
                        <small class="text-danger fw-semibold">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Requieren revisión
                        </small>
                    </div>
                </div>
            </div>

        </div>


        <!-- ========================================== -->
        <!-- ACCIONES RÁPIDAS                           -->
        <!-- Se ubican antes de las tablas para         -->
        <!-- facilitar el acceso en pantallas chicas.  -->
        <!-- ========================================== -->

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white mb-4">

            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-lightning-charge-fill text-warning fs-5"></i>
                <h5 class="fw-bold mb-0">Acciones rápidas</h5>
            </div>

            <p class="text-muted small mb-3">
                Accesos directos para la gestión diaria de fichajes.
            </p>

            <div class="row g-2">

                <!-- Resolver incidencias -->
                <div class="col-12 col-sm-6 col-xl-4">
                    <a
                        href="revisar_incidencias.php"
                        class="btn btn-outline-danger w-100 h-100 text-start d-flex align-items-center justify-content-between gap-2 p-3">

                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-shield-exclamation fs-4"></i>
                            <span>
                                <span class="d-block fw-semibold">Resolver incidencias</span>
                                <small class="d-block text-muted">Revisar casos ambiguos</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <!-- Procesar jornadas -->
                <div class="col-12 col-sm-6 col-xl-4">
                    <a
                        href="procesar_jornadas.php"
                        class="btn btn-outline-warning w-100 h-100 text-start d-flex align-items-center justify-content-between gap-2 p-3">

                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-gear fs-4"></i>
                            <span>
                                <span class="d-block fw-semibold">Procesar jornadas</span>
                                <small class="d-block text-muted">Gestionar jornadas pendientes</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

                <!-- Reportes -->
                <div class="col-12 col-sm-6 col-xl-4">
                    <a
                        href="reportes_diarios.php"
                        class="btn btn-outline-secondary w-100 h-100 text-start d-flex align-items-center justify-content-between gap-2 p-3">

                        <span class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-earmark-text fs-4"></i>
                            <span>
                                <span class="d-block fw-semibold">Reportes diarios</span>
                                <small class="d-block text-muted">Consultar el detalle completo</small>
                            </span>
                        </span>

                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>

            </div>
        </div>


        <!-- ========================================== -->
        <!-- CONTENIDO PRINCIPAL                        -->
        <!-- ========================================== -->

        <div class="row g-4">

            <!-- ====================================== -->
            <!-- ÚLTIMAS MARCAS                         -->
            <!-- ====================================== -->

            <div class="col-12 col-xl-8 mb-4">

                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white h-100">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-clock-history me-2 text-muted"></i>
                            Últimas marcas
                        </h5>

                        <span class="badge bg-light text-dark border">
                            Últimos registros
                        </span>
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
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <span class="spinner-border spinner-border-sm me-2"
                                              role="status"
                                              aria-hidden="true"></span>
                                        Cargando últimas marcas...
                                    </td>
                                </tr>
                            </tbody>

                        </table>
                    </div>

                </div>
            </div>


            <!-- ====================================== -->
            <!-- INCIDENCIAS                            -->
            <!-- ====================================== -->

            <div class="col-12 col-xl-4 mb-4">

                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white">

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-shield-exclamation me-2 text-danger"></i>
                            Incidencias
                        </h5>

                        <span id="badge-incidencias-total" class="badge bg-danger">
                            0
                        </span>
                    </div>

                    <p class="text-muted small mb-3">
                        Problemas informados por los Agents.
                    </p>


                    <!-- Estado del Agent -->

                    <div
                        id="estado-agent-panel"
                        class="d-flex align-items-center justify-content-between border rounded-3 p-3 mb-3">

                        <div>
                            <div class="fw-semibold">Estado del Agent</div>

                            <small id="texto-estado-agent" class="text-muted">
                                Consultando...
                            </small>
                        </div>

                        <span id="indicador-estado-agent" class="badge bg-secondary">
                            -
                        </span>

                    </div>


                    <!-- Resumen de incidencias -->

                    <div class="row g-2 mb-3">

                        <div class="col-6">
                            <div class="border rounded-3 p-3 text-center h-100">
                                <div id="incidencias-abiertas" class="fs-3 fw-bold text-danger">
                                    0
                                </div>
                                <small class="text-muted">Abiertas</small>
                            </div>
                        </div>

                        <div class="col-6">
                            <div class="border rounded-3 p-3 text-center h-100">
                                <div id="incidencias-reconocidas" class="fs-3 fw-bold text-warning">
                                    0
                                </div>
                                <small class="text-muted">Reconocidas</small>
                            </div>
                        </div>

                    </div>


                    <!-- Acceso al listado -->

                    <a
                        href="incidencias.php"
                        class="btn btn-outline-danger w-100 d-flex justify-content-between align-items-center">

                        <span>
                            <i class="bi bi-list-check me-2"></i>
                            Ver incidencias
                        </span>

                        <i class="bi bi-chevron-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </div>


    <!-- ========================================== -->
    <!-- SCRIPTS                                    -->
    <!-- ========================================== -->

    <script src="<?php echo versionar('js/dashboard.js'); ?>"></script>

</body>
</html>