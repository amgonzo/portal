<?php
include 'header.php';
include 'menu.php';
?>

<div class="container-fluid px-4 mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Turnos</h2>
            <p class="text-muted mb-0">
                Administración de ciclos, horarios especiales y horas extra programadas.
            </p>
        </div>
    </div>

    <!-- =========================================================
         PESTAÑAS
    ========================================================== -->

    <ul class="nav nav-tabs mb-4" id="tabsTurnos" role="tablist">

        <li class="nav-item" role="presentation">
            <button
                class="nav-link active"
                id="tab-ciclos"
                data-bs-toggle="tab"
                data-bs-target="#panel-ciclos"
                type="button"
                role="tab">

                <i class="fas fa-sync-alt me-2"></i>
                Asignación de ciclos

            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                id="tab-especiales"
                data-bs-toggle="tab"
                data-bs-target="#panel-especiales"
                type="button"
                role="tab">

                <i class="fas fa-calendar-day me-2"></i>
                Horarios especiales

            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button
                class="nav-link"
                id="tab-extra"
                data-bs-toggle="tab"
                data-bs-target="#panel-extra"
                type="button"
                role="tab">

                <i class="fas fa-business-time me-2"></i>
                Horas extra programadas

            </button>
        </li>

    </ul>


    <div class="tab-content">


        <!-- =====================================================
             ASIGNACIÓN DE CICLOS
        ====================================================== -->

        <div
            class="tab-pane fade show active"
            id="panel-ciclos"
            role="tabpanel">

            <div class="card shadow-sm border-0">

                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">

                    <div>
                        <h5 class="mb-1 fw-bold">
                            Asignación de ciclos
                        </h5>

                        <small class="text-muted">
                            Define qué ciclo utiliza cada empleado y durante qué período.
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar"
                        onclick="nuevoCicloEmpleado()">

                        <i class="fas fa-plus me-2"></i>
                        Nueva asignación

                    </button>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table
                            id="tablaCiclosEmpleado"
                            class="table table-striped table-hover align-middle w-100">

                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Documento</th>
                                    <th>Ciclo</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             HORARIOS ESPECIALES
        ====================================================== -->

        <div
            class="tab-pane fade"
            id="panel-especiales"
            role="tabpanel">

            <div class="card shadow-sm border-0">

                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">

                    <div>
                        <h5 class="mb-1 fw-bold">
                            Horarios especiales
                        </h5>

                        <small class="text-muted">
                            Un horario especial reemplaza el horario habitual del empleado para esa fecha.
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar"
                        onclick="nuevoHorarioEspecial()">

                        <i class="fas fa-plus me-2"></i>
                        Nuevo horario especial

                    </button>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table
                            id="tablaHorariosEspeciales"
                            class="table table-striped table-hover align-middle w-100">

                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Documento</th>
                                    <th>Fecha</th>
                                    <th>Horario</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             HORAS EXTRA PROGRAMADAS
        ====================================================== -->

        <div
            class="tab-pane fade"
            id="panel-extra"
            role="tabpanel">

            <div class="card shadow-sm border-0">

                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">

                    <div>
                        <h5 class="mb-1 fw-bold">
                            Horas extra programadas
                        </h5>

                        <small class="text-muted">
                            Trabajo adicional programado sin reemplazar el horario habitual.
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar"
                        onclick="nuevoHorarioExtra()">

                        <i class="fas fa-plus me-2"></i>
                        Nueva hora extra

                    </button>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table
                            id="tablaHorariosExtra"
                            class="table table-striped table-hover align-middle w-100">

                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Documento</th>
                                    <th>Fecha</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Observaciones</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     MODAL ASIGNACIÓN DE CICLO
============================================================= -->

<div
    class="modal fade"
    id="ModalCicloEmpleado"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="tituloModalCiclo">
                    Nueva asignación de ciclo
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <form id="formCicloEmpleado">

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_empleado_ciclo_id"
                        value="0">

                    <div class="row g-3">

                        <div class="col-md-8">

                            <label class="form-label">
                                Empleado
                            </label>

                            <select
                                id="ciclo_idempleado"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un empleado
                                </option>

                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Ciclo
                            </label>

                            <select
                                id="ciclo_idciclo"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un ciclo
                                </option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Fecha desde
                            </label>

                            <input
                                type="date"
                                id="ciclo_fecha_desde"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Fecha hasta
                            </label>

                            <input
                                type="date"
                                id="ciclo_fecha_hasta"
                                class="form-control">

                            <small class="text-muted">
                                Dejar vacío para una asignación sin fecha de finalización.
                            </small>

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
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar">

                        <i class="fas fa-save me-2"></i>
                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ============================================================
     MODAL HORARIO ESPECIAL
============================================================= -->

<div
    class="modal fade"
    id="ModalHorarioEspecial"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="tituloModalEspecial">
                    Nuevo horario especial
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <form id="formHorarioEspecial">

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_horario_especial_id"
                        value="0">

                    <div class="row g-3">

                        <div class="col-md-8">

                            <label class="form-label">
                                Empleado
                            </label>

                            <select
                                id="especial_idempleado"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un empleado
                                </option>

                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Fecha
                            </label>

                            <input
                                type="date"
                                id="especial_fecha"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-12">

                            <label class="form-label">
                                Horario
                            </label>

                            <select
                                id="especial_idhorario"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un horario
                                </option>

                            </select>

                            <small class="text-muted">
                                Este horario reemplazará el horario habitual del empleado para esta fecha.
                            </small>

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
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar">

                        <i class="fas fa-save me-2"></i>
                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ============================================================
     MODAL HORA EXTRA
============================================================= -->

<div
    class="modal fade"
    id="ModalHorarioExtra"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="tituloModalExtra">
                    Nueva hora extra programada
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <form id="formHorarioExtra">

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_horario_extra_id"
                        value="0">

                    <div class="row g-3">

                        <div class="col-md-8">

                            <label class="form-label">
                                Empleado
                            </label>

                            <select
                                id="extra_idempleado"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un empleado
                                </option>

                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Fecha
                            </label>

                            <input
                                type="date"
                                id="extra_fecha"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora desde
                            </label>

                            <input
                                type="text"
                                id="extra_hora_desde"
                                class="form-control"
                                placeholder="HH:mm"
                                maxlength="5"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora hasta
                            </label>

                            <input
                                type="text"
                                id="extra_hora_hasta"
                                class="form-control"
                                placeholder="HH:mm"
                                maxlength="5"
                                required>

                        </div>

                        <div class="col-12">

                            <label class="form-label">
                                Observaciones
                            </label>

                            <textarea
                                id="extra_observaciones"
                                class="form-control"
                                rows="3"
                                maxlength="255"></textarea>

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
                        class="btn btn-primary"
                        data-permiso="turnos_gestionar">

                        <i class="fas fa-save me-2"></i>
                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="js/turnos.js?v=<?php echo time(); ?>"></script>