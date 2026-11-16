<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title><span data-diccionario="empleado_plural"></span> - <?php echo $empresa; ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Gestión de <span data-diccionario="empleado_plural"></span></h2>
            <p class="text-muted mb-0">
                Administración de <span data-diccionario="empleado_plural"></span>, legajos y asignaciones laborales.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary fw-semibold"
            id="btnNuevoEmpleado">
            Nuevo <span data-diccionario="empleado_singular"></span>
        </button>
    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">
            <table id="tablaEmpleados"
                   class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">
                    <tr>
                        <th>Documento</th>
                        <th><span data-diccionario="empleado_singular"></span></th>
                        <th>N.º Legajo</th>
                        <th>Sector</th>
                        <th>Puesto</th>
                        <th>Fecha de inicio</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody></tbody>

            </table>
        </div>

    </div>
</div>


<!-- MODAL EMPLEADO -->
<div class="modal fade" id="ModalEmpleado" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <form id="formEmpleado">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloModalEmpleado">
                        Nuevo <span data-diccionario="empleado_singular"></span>
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="idempleado" id="edit_empleado_id">

                    <!-- DATOS PERSONALES -->
                    <h6 class="fw-bold mb-3">Datos personales</h6>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label for="emp_documento" class="form-label">
                                Documento *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_documento"
                                   name="documento"
                                   required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_nombre" class="form-label">
                                Nombre *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_nombre"
                                   name="nombre"
                                   required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_apellido" class="form-label">
                                Apellido *
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_apellido"
                                   name="apellido"
                                   required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_fecha_inicio" class="form-label">
                                Fecha de ingreso
                            </label>
                            <input type="date"
                                   class="form-control"
                                   id="emp_fecha_inicio"
                                   name="fecha_inicio">
                        </div>

                    </div>

                    <hr>

                    <!-- LEGAJO -->
                    <h6 class="fw-bold mb-3">Datos del legajo</h6>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label for="emp_numero_legajo" class="form-label">
                                Número de legajo
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_numero_legajo"
                                   name="numero_legajo">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_tipo_contrato" class="form-label">
                                Tipo de contrato
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_tipo_contrato"
                                   name="tipo_contrato">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_categoria" class="form-label">
                                Categoría
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="emp_categoria"
                                   name="categoria">
                        </div>

                        <div class="col-12 mb-3">
                            <label for="emp_observaciones" class="form-label">
                                Observaciones
                            </label>
                            <textarea class="form-control"
                                      id="emp_observaciones"
                                      name="observaciones"
                                      rows="2"></textarea>
                        </div>

                    </div>

                    <hr>

                    <!-- ASIGNACIÓN LABORAL -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Asignación laboral vigente</h6>

                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                id="btnVerHistorial">
                            Ver historial
                        </button>
                    </div>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label for="emp_sector" class="form-label">
                                Sector
                            </label>
                            <select class="form-select"
                                    id="emp_sector"
                                    name="idsector">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_puesto" class="form-label">
                                Puesto
                            </label>
                            <select class="form-select"
                                    id="emp_puesto"
                                    name="idpuesto">
                                <option value="">Sin asignar</option>
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="emp_fecha_desde" class="form-label">
                                Vigente desde
                            </label>
                            <input type="date"
                                   class="form-control"
                                   id="emp_fecha_desde"
                                   name="fecha_desde">
                        </div>

                    </div>

                    <div class="form-text">
                        Si cambia el sector o el puesto, se cerrará la asignación
                        anterior y se registrará una nueva en el historial.
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="btnGuardarEmpleado">
                        Guardar <span data-diccionario="empleado_singular"></span>
                    </button>

                </div>

            </div>

        </form>
    </div>
</div>


<!-- MODAL HISTORIAL LABORAL -->
<div class="modal fade" id="ModalHistorialLaboral" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Historial laboral</h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <div class="modal-body">

                <div id="historialEmpleadoNombre"
                     class="fw-semibold mb-3">
                </div>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>Sector</th>
                                <th>Puesto</th>
                                <th>Desde</th>
                                <th>Hasta</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody id="tablaHistorialLaboral">
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    Sin registros.
                                </td>
                            </tr>
                        </tbody>

                    </table>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Cerrar
                </button>
            </div>

        </div>

    </div>
</div>


<script src="js/empleados.js?v=<?php echo time(); ?>"></script>

</body>
</html>