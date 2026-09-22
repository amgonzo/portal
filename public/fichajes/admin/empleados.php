<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Empleados - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    Gestión de <span data-diccionario="empleado_plural">Empleados</span>
                </h2>

                <p class="text-muted mb-0">
                    Administración de personal y tarjetas de fichaje.
                </p>
            </div>

            <button
                class="btn btn-primary fw-semibold shadow-sm"
                name="btnNuevoEmpleado"
                id="btnNuevoEmpleado"
                onclick="abrirNuevoEmpleado()">
                <i class="fas fa-user-plus me-2"></i>
                Nuevo <span data-diccionario="empleado_singular">Empleado</span>
            </button>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
            <div class="table-responsive">
                <table id="tablaEmpleados" class="table table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th>Documento</th>
                            <th>Nombre y Apellido</th>
                            <th>Tarjeta</th>
                            <th>Fecha Inicio</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="listaEmpleados">
                        <!-- Carga dinámica vía JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL CREAR / EDITAR EMPLEADO -->
    <div class="modal fade" id="ModalEmpleado" tabindex="-1">
        <div class="modal-dialog">

            <form id="formEmpleado">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            Crear Nuevo <span data-diccionario="empleado_singular">Empleado</span>
                        </h5>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            aria-label="Close">
                        </button>
                    </div>

                    <div class="modal-body">

                        <input
                            type="hidden"
                            id="edit_empleado_id"
                            name="idempleado">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Documento <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="emp_documento"
                                name="documento"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Nombre <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="emp_nombre"
                                name="nombre"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Apellido <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="emp_apellido"
                                name="apellido"
                                class="form-control"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Número de Tarjeta
                            </label>

                            <input
                                type="text"
                                id="emp_tarjeta"
                                name="tarjeta"
                                class="form-control"
                                placeholder="Opcional">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Fecha de Inicio
                            </label>

                            <input
                                type="date"
                                id="emp_fecha_inicio"
                                name="fecha_inicio"
                                class="form-control">
                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                            Cerrar
                        </button>

                        <button
                            type="button"
                            id="btnGuardarEmpleado"
                            class="btn btn-primary"
                            onclick="guardarEmpleado()">
                            Guardar
                        </button>

                    </div>

                </div>

            </form>

        </div>
    </div>

    <!-- Script JS -->
    <script src="js/empleados.js?v=<?php echo time(); ?>"></script>

</body>

</html>