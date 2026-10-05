<!DOCTYPE html>
<html lang="es">

<head>
    <?php require_once __DIR__ . '/header.php'; ?>
</head>

<body>

<?php require_once __DIR__ . '/menu.php'; ?>

<div class="container-fluid px-4 mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Definición de Horarios</h2>
            <p class="text-muted mb-0">
                Administración de horarios, tramos y descansos.
            </p>
        </div>

        <div data-permiso="horarios_gestionar">
            <button
                type="button"
                class="btn btn-primary fw-semibold shadow-sm"
                onclick="nuevoHorario()">
                <i class="fas fa-plus me-2"></i>
                Nuevo Horario
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    id="tablaHorarios"
                    class="table table-hover table-striped align-middle w-100">

                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tolerancia</th>
                            <th>Minutos trabajados</th>
                            <th>Tramos</th>
                            <th>Descansos</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="listaHorarios"></tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MODAL HORARIO
========================================================= -->

<div
    class="modal fade"
    id="ModalHorario"
    tabindex="-1"
    aria-labelledby="ModalHorarioLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="ModalHorarioLabel">
                    Nuevo Horario
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>

            </div>

            <form id="formHorario">

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_horario_id"
                        name="idhorario"
                        value="">

                    <!-- =================================================
                         DATOS GENERALES
                    ================================================== -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-6">

                            <label
                                for="nombre"
                                class="form-label fw-semibold">
                                Nombre
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="nombre"
                                name="nombre"
                                maxlength="100"
                                required>

                        </div>

                        <div class="col-md-3">

                            <label
                                for="tolerancia_minutos"
                                class="form-label fw-semibold">
                                Tolerancia
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    class="form-control"
                                    id="tolerancia_minutos"
                                    name="tolerancia_minutos"
                                    min="0"
                                    step="1"
                                    value="0">

                                <span class="input-group-text">
                                    minutos
                                </span>

                            </div>

                        </div>

                        <div class="col-md-3">

                            <label
                                for="minutos_trabajo"
                                class="form-label fw-semibold">
                                Minutos trabajados
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    class="form-control"
                                    id="minutos_trabajo"
                                    name="minutos_trabajo"
                                    value="0"
                                    readonly>

                                <span class="input-group-text">
                                    minutos
                                </span>

                            </div>

                            <div class="form-text">
                                Se calcula automáticamente según los tramos.
                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         TRAMOS
                    ================================================== -->

                    <div class="card border mb-4">

                        <div class="card-header d-flex justify-content-between align-items-center">

                            <div>
                                <h6 class="mb-0 fw-bold">
                                    Tramos de trabajo
                                </h6>

                                <small class="text-muted">
                                    Defina desde qué hora hasta qué hora se trabaja.
                                </small>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                onclick="agregarTramo()">

                                <i class="fas fa-plus me-1"></i>
                                Agregar tramo

                            </button>

                        </div>

                        <div class="card-body">

                            <div class="table-responsive">

                                <table class="table table-sm align-middle mb-0">

                                    <thead>

                                        <tr>
                                            <th style="width: 80px;">
                                                Orden
                                            </th>

                                            <th>
                                                Desde
                                            </th>

                                            <th>
                                                Hasta
                                            </th>

                                            <th>
                                                Minutos
                                            </th>

                                            <th
                                                class="text-center"
                                                style="width: 80px;">
                                                Acción
                                            </th>
                                        </tr>

                                    </thead>

                                    <tbody id="listaTramos"></tbody>

                                </table>

                            </div>

                            <div
                                id="mensajeSinTramos"
                                class="text-muted text-center py-3">

                                No hay tramos definidos.

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         DESCANSOS
                    ================================================== -->

                    <div class="card border">

                        <div class="card-header d-flex justify-content-between align-items-center">

                            <div>
                                <h6 class="mb-0 fw-bold">
                                    Descansos permitidos
                                </h6>

                                <small class="text-muted">
                                    Cantidad y duración máxima de cada descanso.
                                </small>
                            </div>

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                onclick="agregarDescanso()">

                                <i class="fas fa-plus me-1"></i>
                                Agregar descanso

                            </button>

                        </div>

                        <div class="card-body">

                            <div class="table-responsive">

                                <table class="table table-sm align-middle mb-0">

                                    <thead>

                                        <tr>

                                            <th style="width: 80px;">
                                                Orden
                                            </th>

                                            <th>
                                                Minutos permitidos
                                            </th>

                                            <th
                                                class="text-center"
                                                style="width: 80px;">
                                                Acción
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody id="listaDescansos"></tbody>

                                </table>

                            </div>

                            <div
                                id="mensajeSinDescansos"
                                class="text-muted text-center py-3">

                                No hay descansos definidos.

                            </div>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancelar

                    </button>

                    <button
                        type="submit"
                        id="btnGuardarHorario"
                        class="btn btn-primary">

                        <i class="fas fa-save me-2"></i>
                        Guardar Horario

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="js/horarios.js?v=<?php echo time(); ?>"></script>

</body>
</html>