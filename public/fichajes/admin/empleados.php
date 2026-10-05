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

                        <h5 class="modal-title"
                            id="ModalHuellasEmpleadoLabel">

                            <i class="fas fa-fingerprint me-2"></i>
                            Huellas del empleado

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

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Relojes asignados
                            </label>

                            <div id="listaLectoresEmpleado">
                                <div class="text-center text-muted py-2">
                                    Cargando relojes...
                                </div>
                            </div>

                            <div class="form-text">
                                Puede seleccionar uno o varios relojes. El reloj marcado como predeterminado será utilizado para enviar el empleado.
                            </div>
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

    <!-- MODAL ASIGNAR CICLO -->
    <div class="modal fade" id="ModalCicloEmpleado" tabindex="-1" aria-labelledby="ModalCicloEmpleadoLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">

            <form id="formCicloEmpleado">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">

                        <h5 class="modal-title" id="ModalCicloEmpleadoLabel">
                            Asignar Ciclo
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
                            id="ciclo_empleado_id"
                            name="idempleado">

                        <input
                            type="hidden"
                            id="ciclo_empleado_asignacion_id"
                            name="idempleado_ciclo">

                        <div class="alert alert-light border mb-4">
                            <div class="fw-semibold">
                                Empleado
                            </div>

                            <div id="cicloEmpleadoNombre" class="text-muted">
                                -
                            </div>
                        </div>

                        <div class="mb-3">

                            <label
                                for="ciclo_empleado_ciclo"
                                class="form-label fw-semibold">
                                Ciclo <span class="text-danger">*</span>
                            </label>

                            <select
                                id="ciclo_empleado_ciclo"
                                name="idciclo"
                                class="form-select"
                                required>

                                <option value="">
                                    Seleccione un ciclo
                                </option>

                            </select>

                        </div>

                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label
                                    for="ciclo_empleado_fecha_desde"
                                    class="form-label fw-semibold">
                                    Fecha desde <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="date"
                                    id="ciclo_empleado_fecha_desde"
                                    name="fecha_desde"
                                    class="form-control"
                                    required>

                            </div>

                            <div class="col-md-6 mb-3">

                                <label
                                    for="ciclo_empleado_fecha_hasta"
                                    class="form-label fw-semibold">
                                    Fecha hasta
                                </label>

                                <input
                                    type="date"
                                    id="ciclo_empleado_fecha_hasta"
                                    name="fecha_hasta"
                                    class="form-control">

                                <div class="form-text">
                                    Dejar vacío si no tiene fecha de finalización.
                                </div>

                            </div>

                        </div>

                        <hr>

                        <h6 class="fw-bold mb-3">
                            Historial de ciclos
                        </h6>

                        <div class="table-responsive">

                            <table class="table table-sm table-hover align-middle">

                                <thead>
                                    <tr>
                                        <th>Ciclo</th>
                                        <th>Desde</th>
                                        <th>Hasta</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>

                                <tbody id="listaCiclosEmpleado">

                                    <tr>
                                        <td
                                            colspan="4"
                                            class="text-center text-muted">
                                            Sin asignaciones.
                                        </td>
                                    </tr>

                                </tbody>

                            </table>

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
                            type="submit"
                            id="btnGuardarCicloEmpleado"
                            class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Guardar Ciclo
                        </button>

                    </div>

                </div>

            </form>

        </div>
    </div>

    <!-- =========================================================
        MODAL HUELLAS DEL EMPLEADO
    ========================================================= -->

    <div class="modal fade"
        id="ModalHuellasEmpleado"
        tabindex="-1"
        aria-labelledby="ModalHuellasEmpleadoLabel"
        aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-scrollable">

            <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title" id="ModalHuellasEmpleadoLabel">
                    <i class="fas fa-fingerprint me-2"></i>
                    Huellas del empleado
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>

            </div>

            <div class="modal-body">

                <input
                    type="hidden"
                    id="huellasIdEmpleado">

                <!-- EMPLEADO -->

                <div class="alert alert-light border mb-4">

                    <div class="fw-semibold">
                        Empleado
                    </div>

                    <div
                        id="huellasNombreEmpleado"
                        class="text-muted">
                        -
                    </div>

                </div>


                <!-- LECTOR -->

                <div class="mb-3">
                    <label for="huella_idlector" class="form-label fw-semibold">
                        Lector
                    </label>

                    <select
                        id="huella_idlector"
                        class="form-select">
                        <option value="">
                            Seleccione un lector
                        </option>
                    </select>
                </div>

                <div
                    id="huellasEstadoLector"
                    class="alert alert-secondary py-2 mb-3">
                    Seleccione un lector para consultar las huellas.
                </div>


                <!-- ESTADO DEL LECTOR -->

                <div
                    id="huellasEstadoLector"
                    class="alert alert-secondary">

                    Seleccione un lector para consultar
                    las huellas.

                </div>


                <hr>


                <!-- HUELLAS -->

                <h6 class="fw-bold mb-3">
                    Huellas registradas
                </h6>

                <div
                    class="row"
                    id="listaHuellasEmpleado">


                    <!-- DEDO 0 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Pulgar derecho
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 0
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 1 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Índice derecho
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 1
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 2 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Medio derecho
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 2
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 3 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Anular derecho
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 3
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 4 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Meñique derecho
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 4
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 5 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Pulgar izquierdo
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 5
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 6 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Índice izquierdo
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 6
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 7 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Medio izquierdo
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 7
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 8 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Anular izquierdo
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 8
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>


                    <!-- DEDO 9 -->

                    <div class="col-md-6 mb-3">

                        <div class="border rounded p-3">

                            <div class="d-flex justify-content-between align-items-center">

                                <div>

                                    <div class="fw-semibold">
                                        Meñique izquierdo
                                    </div>

                                    <div class="small text-muted">
                                        Dedo 9
                                    </div>

                                </div>

                                <span class="badge bg-secondary">
                                    Libre
                                </span>

                            </div>

                            <div class="mt-3">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary">

                                    <i class="fas fa-plus me-1"></i>
                                    Registrar

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Cerrar

                </button>

            </div>

        </div>

    </div>

</div>

    <!-- Script JS -->
    <script src="js/empleados.js?v=<?php echo time(); ?>"></script>

</body>

</html>