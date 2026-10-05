<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Relojes - <?php echo $empresa; ?></title>
</head>

<body>

    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-0">
                    Gestión de Relojes
                </h2>

                <p class="text-muted mb-0">
                    Administración de los relojes de fichaje.
                </p>

            </div>

            <button
                class="btn btn-primary fw-semibold shadow-sm"
                name="btnNuevoReloj"
                id="btnNuevoReloj"
                onclick="abrirNuevoReloj()">

                <i class="fas fa-clock me-2"></i>

                Nuevo Reloj

            </button>

        </div>


        <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

            <div class="table-responsive">

                <table
                    id="tablaRelojes"
                    class="table table-hover align-middle mb-0 w-100">

                    <thead class="table-light">

                        <tr>

                            <th>Nombre</th>

                            <th>Marca</th>

                            <th>Modelo</th>

                            <th>IP</th>

                            <th>Puerto</th>

                            <th>Ubicación</th>

                            <th>Agent</th>

                            <th class="text-center">
                                Estado
                            </th>

                            <th class="text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>

                    <tbody id="listaRelojes">

                        <!-- Carga dinámica vía JS -->

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- MODAL CREAR / EDITAR RELOJ -->

    <div
        class="modal fade"
        id="ModalReloj"
        tabindex="-1">

        <div class="modal-dialog">

            <form id="formReloj">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">

                        <h5 class="modal-title">
                            Crear Nuevo Reloj
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
                            id="edit_reloj_id"
                            name="idlector">


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Nombre <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="reloj_nombre"
                                name="nombre"
                                class="form-control"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Marca <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="reloj_marca"
                                name="marca"
                                class="form-control"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Modelo
                            </label>

                            <input
                                type="text"
                                id="reloj_modelo"
                                name="modelo"
                                class="form-control"
                                placeholder="Opcional">

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Dirección IP <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                id="reloj_ip"
                                name="ip"
                                class="form-control"
                                placeholder="192.168.1.100"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Puerto <span class="text-danger">*</span>
                            </label>

                            <input
                                type="number"
                                id="reloj_puerto"
                                name="puerto"
                                class="form-control"
                                value="4370"
                                min="1"
                                max="65535"
                                required>

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Ubicación
                            </label>

                            <input
                                type="text"
                                id="reloj_ubicacion"
                                name="ubicacion"
                                class="form-control"
                                placeholder="Opcional">

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Tipo de Uso
                            </label>

                            <input
                                type="text"
                                id="reloj_tipo_uso"
                                name="tipo_uso"
                                class="form-control"
                                placeholder="Opcional">

                        </div>


                        <div class="mb-3">

                            <label class="form-label fw-semibold">
                                Agent
                            </label>

                            <select
                                id="reloj_idagente"
                                name="idagente"
                                class="form-select">

                                <option value="">
                                    Sin Agent
                                </option>

                            </select>

                        </div>


                        <div class="form-check">

                            <input
                                type="checkbox"
                                id="reloj_predeterminado"
                                name="predeterminado"
                                value="1"
                                class="form-check-input">

                            <label
                                class="form-check-label fw-semibold"
                                for="reloj_predeterminado">

                                Reloj predeterminado

                            </label>

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
                            id="btnGuardarReloj"
                            class="btn btn-primary"
                            onclick="guardarReloj()">

                            Guardar

                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <!-- Script JS -->

    <script src="js/relojes.js?v=<?php echo time(); ?>"></script>

</body>

</html>