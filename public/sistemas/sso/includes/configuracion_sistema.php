<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Configuración del Sistema - <?php echo $empresa; ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">
                Configuración del Sistema
            </h2>

            <p class="text-muted mb-0">
                Configuración general del sistema de Fichajes y sus Agents.
            </p>
        </div>

        <button
            type="button"
            class="btn btn-primary fw-semibold shadow-sm"
            id="btnNuevaConfiguracion"
            onclick="abrirNuevaConfiguracion()">

            <i class="fas fa-plus me-2"></i>
            Nueva configuración

        </button>

    </div>


    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">

            <table
                id="tablaConfiguracion"
                class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">

                    <tr>
                        <th>Configuración</th>
                        <th>Descripción</th>
                        <th class="text-center">Valor</th>
                        <th class="text-center">Tipo</th>
                        <th>Última modificación</th>
                        <th class="text-center">Acciones</th>
                    </tr>

                </thead>

                <tbody id="listaConfiguracion">
                    <!-- Carga dinámica -->
                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ========================================================= -->
<!-- MODAL NUEVA / EDITAR CONFIGURACIÓN -->
<!-- ========================================================= -->

<div class="modal fade" id="ModalConfiguracion" tabindex="-1">

    <div class="modal-dialog">

        <form id="formConfiguracion">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5
                        class="modal-title"
                        id="tituloModalConfiguracion">

                        Nueva configuración

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
                        id="config_id"
                        name="idconfiguracion">


                    <!-- CLAVE -->

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Clave
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="config_clave"
                            name="clave"
                            class="form-control"
                            maxlength="100"
                            required
                            placeholder="Ej.: agent_online_timeout">

                        <small class="form-text text-muted">
                            Identificador interno de la configuración.
                        </small>

                    </div>


                    <!-- DESCRIPCIÓN -->

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Descripción
                        </label>

                        <input
                            type="text"
                            id="config_descripcion"
                            name="descripcion"
                            class="form-control"
                            maxlength="255"
                            placeholder="Descripción de la configuración">

                    </div>


                    <!-- VALOR -->

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Valor
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="config_valor"
                            name="valor"
                            class="form-control"
                            maxlength="255"
                            required>

                    </div>


                    <!-- TIPO -->

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Tipo
                            <span class="text-danger">*</span>
                        </label>

                        <select
                            id="config_tipo"
                            name="tipo"
                            class="form-select"
                            required>

                            <option value="texto">
                                Texto
                            </option>

                            <option value="entero">
                                Entero
                            </option>

                            <option value="decimal">
                                Decimal
                            </option>

                            <option value="booleano">
                                Booleano
                            </option>

                        </select>

                    </div>


                    <div
                        id="avisoAplicacionConfiguracion"
                        class="alert alert-info d-none">

                        <i class="fas fa-info-circle me-1"></i>

                        Al guardar un cambio se generará automáticamente
                        una tarea para actualizar la configuración de los
                        Agents activos.

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
                        type="button"
                        class="btn btn-primary"
                        id="btnGuardarConfiguracion"
                        onclick="guardarConfiguracion()">

                        <i class="fas fa-save me-2"></i>
                        Guardar

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<script src="js/configuracion_sistema.js?v=<?php echo time(); ?>"></script>

</body>
</html>