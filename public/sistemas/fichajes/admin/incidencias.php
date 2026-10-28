<!DOCTYPE HTML>
<html lang="es">

<head>

    <?php include 'header.php'; ?>

    <title>Incidencias - <?php echo $empresa; ?></title>

</head>

<body>

    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <!-- =====================================================
             ENCABEZADO
        ====================================================== -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-0">
                    Incidencias
                </h2>

                <p class="text-muted mb-0">
                    Problemas informados por los Agents de fichajes.
                </p>

            </div>

            <button
                type="button"
                id="btnActualizarIncidencias"
                class="btn btn-primary fw-semibold shadow-sm">

                <i class="fas fa-sync-alt me-2"></i>
                Actualizar

            </button>

        </div>


        <!-- =====================================================
             RESUMEN
        ====================================================== -->

        <div class="row g-3 mb-4">

            <!-- ABIERTAS -->

            <div class="col-12 col-md-6 col-xl-3">

                <div class="card border-0 shadow-sm rounded-3 h-100">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <div class="text-muted small fw-semibold mb-2">
                                    Abiertas
                                </div>

                                <div
                                    id="incidencias-abiertas"
                                    class="fs-2 fw-bold text-danger">
                                    0
                                </div>

                                <div class="text-muted small mt-1">
                                    Requieren atención
                                </div>

                            </div>

                            <div class="text-danger fs-3">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- RECONOCIDAS -->

            <div class="col-12 col-md-6 col-xl-3">

                <div class="card border-0 shadow-sm rounded-3 h-100">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <div class="text-muted small fw-semibold mb-2">
                                    Reconocidas
                                </div>

                                <div
                                    id="incidencias-reconocidas"
                                    class="fs-2 fw-bold text-warning">
                                    0
                                </div>

                                <div class="text-muted small mt-1">
                                    En seguimiento
                                </div>

                            </div>

                            <div class="text-warning fs-3">
                                <i class="fas fa-eye"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- RESUELTAS -->

            <div class="col-12 col-md-6 col-xl-3">

                <div class="card border-0 shadow-sm rounded-3 h-100">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <div class="text-muted small fw-semibold mb-2">
                                    Resueltas
                                </div>

                                <div
                                    id="incidencias-resueltas"
                                    class="fs-2 fw-bold text-success">
                                    0
                                </div>

                                <div class="text-muted small mt-1">
                                    Historial solucionado
                                </div>

                            </div>

                            <div class="text-success fs-3">
                                <i class="fas fa-check-circle"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ESTADO AGENT -->

            <div class="col-12 col-md-6 col-xl-3">

                <div class="card border-0 shadow-sm rounded-3 h-100">

                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between align-items-start">

                            <div>

                                <div class="text-muted small fw-semibold mb-2">
                                    Estado del Agent
                                </div>

                                <div
                                    id="estado-agent"
                                    class="fs-4 fw-bold text-secondary">
                                    -
                                </div>

                                <div
                                    id="texto-estado-agent"
                                    class="text-muted small mt-1">
                                    Consultando...
                                </div>

                            </div>

                            <div
                                id="icono-estado-agent"
                                class="text-secondary fs-3">
                                <i class="fas fa-plug"></i>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             TABLA DE INCIDENCIAS
        ====================================================== -->

        <div class="card border-0 shadow-sm rounded-3 p-3">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="fw-bold mb-0">
                        Listado de incidencias
                    </h5>

                    <p class="text-muted small mb-0">
                        Incidencias informadas por los Agents de esta empresa.
                    </p>

                </div>

            </div>

            <div class="table-responsive">

                <table
                    id="tablaIncidencias"
                    class="table table-hover align-middle mb-0 w-100">

                    <thead class="table-light">

                        <tr>

                            <th>Fecha</th>

                            <th>Agent</th>

                            <th>Severidad</th>

                            <th>Código</th>

                            <th>Mensaje</th>

                            <th class="text-center">
                                Ocurrencias
                            </th>

                            <th>Última ocurrencia</th>

                            <th>Estado</th>

                            <th class="text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>

                    <tbody id="listaIncidencias"></tbody>

                </table>

            </div>

        </div>

    </div>


    <script src="js/incidencias.js?v=<?php echo time(); ?>"></script>

</body>

</html>