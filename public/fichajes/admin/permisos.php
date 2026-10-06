<?php
include 'header.php';
include 'menu.php';
?>

<div class="container-fluid px-4 mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">Permisos de salida</h2>

            <p class="text-muted mb-0">
                Administración de permisos de salida de los empleados.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary"
            data-permiso="permisos_gestionar"
            onclick="nuevoPermiso()">

            <i class="fas fa-plus me-2"></i>
            Nuevo permiso

        </button>

    </div>


    <!-- =========================================================
         LISTADO
    ========================================================== -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-transparent">

            <h5 class="mb-1 fw-bold">
                Permisos registrados
            </h5>

            <small class="text-muted">
                Los permisos pueden tener horario definido o quedar abiertos para su interpretación durante el cálculo de la jornada.
            </small>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table
                    id="tablaPermisos"
                    class="table table-striped table-hover align-middle w-100">

                    <thead>

                        <tr>
                            <th>Empleado</th>
                            <th>Documento</th>
                            <th>Fecha</th>
                            <th>Desde</th>
                            <th>Hasta</th>
                            <th>Motivo</th>
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


<!-- ============================================================
     MODAL PERMISO
============================================================= -->

<div
    class="modal fade"
    id="ModalPermiso"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="tituloModalPermiso">

                    Nuevo permiso de salida

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <form id="formPermiso">

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_permiso_id"
                        value="0">


                    <div class="row g-3">


                        <!-- EMPLEADO -->

                        <div class="col-md-8">

                            <label class="form-label">
                                Empleado
                            </label>

                            <select
                                id="permiso_idempleado"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un empleado
                                </option>

                            </select>

                        </div>


                        <!-- FECHA -->

                        <div class="col-md-4">

                            <label class="form-label">
                                Fecha
                            </label>

                            <input
                                type="date"
                                id="permiso_fecha"
                                class="form-control"
                                required>

                        </div>


                        <!-- HORA DESDE -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora desde
                            </label>

                            <input
                                type="text"
                                id="permiso_hora_desde"
                                class="form-control"
                                placeholder="HH:mm"
                                maxlength="5">

                            <small class="text-muted">
                                Opcional. Ejemplo: 11:00
                            </small>

                        </div>


                        <!-- HORA HASTA -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Hora hasta
                            </label>

                            <input
                                type="text"
                                id="permiso_hora_hasta"
                                class="form-control"
                                placeholder="HH:mm"
                                maxlength="5">

                            <small class="text-muted">
                                Opcional. Ejemplo: 12:00
                            </small>

                        </div>


                        <!-- MOTIVO -->

                        <div class="col-12">

                            <label class="form-label">
                                Motivo
                            </label>

                            <textarea
                                id="permiso_motivo"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                placeholder="Motivo del permiso"></textarea>

                        </div>


                        <div class="col-12">

                            <div class="alert alert-info mb-0">

                                <i class="fas fa-info-circle me-2"></i>

                                Si se informa un horario, el sistema podrá utilizarlo
                                para identificar automáticamente las marcas de salida
                                y regreso durante el cálculo de la jornada.

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
                        class="btn btn-primary"
                        data-permiso="permisos_gestionar">

                        <i class="fas fa-save me-2"></i>
                        Guardar

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="js/permisos.js?v=<?php echo time(); ?>"></script>