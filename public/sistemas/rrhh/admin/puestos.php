<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Puestos - <?= htmlspecialchars($empresa ?? 'RRHH') ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">Gestión de Puestos</h2>
            <p class="text-muted mb-0">
                Administración de los puestos de la organización.
            </p>
        </div>

        <button
            class="btn btn-primary fw-semibold shadow-sm"
            onclick="abrirNuevoPuesto()">
            Nuevo Puesto
        </button>

    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">

            <table id="tablaPuestos"
                   class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">
                    <tr>
                        <th>Puesto</th>
                        <th>Descripción</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody id="listaPuestos"></tbody>

            </table>

        </div>

    </div>

</div>

<!-- MODAL CREAR / EDITAR -->

<div class="modal fade" id="ModalPuesto" tabindex="-1">

    <div class="modal-dialog">

        <form id="formPuesto">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">Nuevo Puesto</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input type="hidden"
                           id="edit_puesto_id"
                           name="idpuesto">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Nombre del puesto
                            <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               id="puesto_nombre"
                               name="nombre"
                               class="form-control"
                               maxlength="120"
                               required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Descripción
                        </label>

                        <textarea id="puesto_descripcion"
                                  name="descripcion"
                                  class="form-control"
                                  maxlength="255"
                                  rows="3"></textarea>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="button"
                            id="btnGuardarPuesto"
                            class="btn btn-primary">
                        Guardar Puesto
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script src="js/puestos.js?v=<?= time() ?>"></script>

</body>
</html>