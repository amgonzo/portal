
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Tareas del agente - <?= htmlspecialchars($empresa) ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fas fa-tasks me-2"></i>
                Tareas del agente
            </h2>

            <p class="text-muted mb-0">
                Consulta y seguimiento de las operaciones ejecutadas por los agentes.
            </p>
        </div>

        <button type="button" class="btn btn-primary fw-semibold"
                id="btnActualizarTareas">
            <i class="fas fa-sync-alt me-2"></i>
            Actualizar
        </button>
    </div>

    <div class="row g-3 mb-4">

        <!-- TOTAL DE TAREAS -->
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 rounded-3 p-3"
                style="background-color: #e8f1ff; border-left: 4px solid #3b82f6 !important; box-shadow: 0 2px 8px rgba(59,130,246,.10);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold" style="color: #2458a6;">
                        Total de tareas
                    </span>
                    <i class="fas fa-layer-group fa-lg" style="color: #3b82f6;"></i>
                </div>
                <div class="fs-2 fw-bold" style="color: #2458a6;" id="totalTareas">0</div>
                <div class="small" style="color: #5276aa;">Todas las operaciones</div>
            </div>
        </div>

        <!-- PENDIENTES -->
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 rounded-3 p-3"
                style="background-color: #e3f7eb; border-left: 4px solid #22a06b !important; box-shadow: 0 2px 8px rgba(34,160,107,.10);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold" style="color: #187744;">
                        Pendientes
                    </span>
                    <i class="fas fa-clock fa-lg" style="color: #22a06b;"></i>
                </div>
                <div class="fs-2 fw-bold" style="color: #187744;" id="totalPendientes">0</div>
                <div class="small" style="color: #39865d;">Esperando ejecución</div>
            </div>
        </div>

        <!-- EN PROCESO -->
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 rounded-3 p-3"
                style="background-color: #fff3d9; border-left: 4px solid #e6a817 !important; box-shadow: 0 2px 8px rgba(230,168,23,.10);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold" style="color: #956000;">
                        En proceso
                    </span>
                    <i class="fas fa-sync-alt fa-lg" style="color: #c58b08;"></i>
                </div>
                <div class="fs-2 fw-bold" style="color: #956000;" id="totalProcesando">0</div>
                <div class="small" style="color: #a47b2a;">En ejecución</div>
            </div>
        </div>

        <!-- CON ERROR -->
        <div class="col-6 col-md-3">
            <div class="card h-100 border-0 rounded-3 p-3"
                style="background-color: #fde8e8; border-left: 4px solid #dc3545 !important; box-shadow: 0 2px 8px rgba(220,53,69,.10);">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold" style="color: #b4232d;">
                        Con error
                    </span>
                    <i class="fas fa-exclamation-triangle fa-lg" style="color: #dc3545;"></i>
                </div>
                <div class="fs-2 fw-bold" style="color: #b4232d;" id="totalErrores">0</div>
                <div class="small" style="color: #b5474f;">Requieren revisión</div>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 mb-4">
        <div class="row g-3 align-items-end">

            <div class="col-md-4">
                <label for="filtroEstadoTarea" class="form-label fw-semibold">
                    Estado
                </label>

                <select id="filtroEstadoTarea" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="procesando">En proceso</option>
                    <option value="completada">Completada</option>
                    <option value="error">Con error</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="filtroAccionTarea" class="form-label fw-semibold">
                    Acción
                </label>

                <select id="filtroAccionTarea" class="form-select">
                    <option value="">Todas las acciones</option>
                </select>
            </div>

            <div class="col-md-4">
                <button type="button" id="btnLimpiarFiltrosTareas"
                        class="btn btn-outline-secondary w-100">
                    <i class="fas fa-eraser me-2"></i>
                    Limpiar filtros
                </button>
            </div>

        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">
            <table id="tablaTareas"
                   class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Acción</th>
                        <th>Reloj</th>
                        <th>Asociado</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Intentos</th>
                        <th>Creación</th>
                        <th>Finalización</th>
                        <th class="text-center">Detalle</th>
                    </tr>
                </thead>

                <tbody></tbody>

            </table>
        </div>

    </div>

</div>


<!-- DETALLE DE TAREA -->

<div class="modal fade" id="modalDetalleTarea" tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle me-2"></i>
                    Detalle de la tarea
                    <span id="detalleIdTarea"></span>
                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">

                <div id="detalleResumen" class="mb-3"></div>

                <h6 class="fw-bold">Datos de la tarea</h6>
                <pre id="detalleDatos"
                     class="bg-body-tertiary border rounded p-3 small"
                     style="white-space: pre-wrap; overflow-wrap: anywhere;">Sin datos</pre>

                <h6 class="fw-bold mt-4">Respuesta del agente</h6>
                <pre id="detalleRespuesta"
                     class="bg-body-tertiary border rounded p-3 small"
                     style="white-space: pre-wrap; overflow-wrap: anywhere;">Sin respuesta</pre>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
</div>

<script src="js/tareas_agente.js?v=<?= time() ?>"></script>

</body>
</html>