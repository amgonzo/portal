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

            <h2 class="fw-bold mb-1">
                Definición de Ciclos
            </h2>

            <p class="text-muted mb-0">
                Administración de ciclos y asignación semanal de horarios.
            </p>

        </div>

        <div data-permiso="ciclos_gestionar">

            <button
                type="button"
                class="btn btn-primary fw-semibold shadow-sm"
                onclick="nuevoCiclo()">

                <i class="fas fa-plus me-2"></i>
                Nuevo Ciclo

            </button>

        </div>

    </div>


    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    id="tablaCiclos"
                    class="table table-hover table-striped align-middle w-100">

                    <thead>

                        <tr>

                            <th>
                                Nombre
                            </th>

                            <th>
                                Semanas
                            </th>

                            <th>
                                Estado
                            </th>

                            <th class="text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>

                    <tbody id="listaCiclos"></tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MODAL CICLO
========================================================= -->

<div
    class="modal fade"
    id="ModalCiclo"
    tabindex="-1"
    aria-labelledby="ModalCicloLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 1400px;">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="ModalCicloLabel">

                    Nuevo Ciclo

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>

            </div>


            <form id="formCiclo">

                <div class="modal-body" style="max-height: calc(100vh - 180px); overflow-y: auto;">

                    <input
                        type="hidden"
                        id="edit_ciclo_id"
                        name="idciclo"
                        value="">


                    <!-- =================================================
                         DATOS GENERALES
                    ================================================== -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-8">

                            <label
                                for="nombreCiclo"
                                class="form-label fw-semibold">

                                Nombre

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="nombreCiclo"
                                name="nombre"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label
                                for="cantidadSemanas"
                                class="form-label fw-semibold">

                                Cantidad de semanas

                            </label>

                            <select
                                class="form-select"
                                id="cantidadSemanas"
                                name="cantidad_semanas"
                                required>

                                <?php for ($i = 1; $i <= 4; $i++): ?>

                                    <option value="<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                        <?php echo $i === 1 ? 'semana' : 'semanas'; ?>
                                    </option>

                                <?php endfor; ?>

                            </select>

                        </div>

                    </div>


                    <!-- =================================================
                         CONFIGURACIÓN DEL CICLO
                    ================================================== -->

                    <div class="card border">

                        <div class="card-header">

                            <div>

                                <h6 class="mb-0 fw-bold">
                                    Configuración del ciclo
                                </h6>

                                <small class="text-muted">
                                    Defina el horario correspondiente a cada día de cada semana.
                                </small>

                            </div>

                        </div>


                        <div class="card-body">

                            <div id="contenedorSemanas"></div>

                            <div
                                id="mensajeSinSemanas"
                                class="text-muted text-center py-3"
                                style="display: none;">

                                No hay semanas definidas.

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
                        id="btnGuardarCiclo"
                        class="btn btn-primary">

                        <i class="fas fa-save me-2"></i>
                        Guardar Ciclo

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script src="js/ciclos.js?v=<?php echo time(); ?>"></script>

</body>
</html>