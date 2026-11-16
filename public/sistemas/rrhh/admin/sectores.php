<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Sectores - <?= htmlspecialchars($empresa ?? 'RRHH') ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">Gestión de Sectores</h2>
            <p class="text-muted mb-0">
                Administración de los sectores de la organización.
            </p>
        </div>

        <button
            class="btn btn-primary fw-semibold shadow-sm"
            id="btnNuevoSector"
            onclick="abrirNuevoSector()">
            Nuevo Sector
        </button>

    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">

            <table id="tablaSectores"
                   class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">
                    <tr>
                        <th>Sector</th>
                        <th>Descripción</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody id="listaSectores"></tbody>

            </table>

        </div>

    </div>

</div>

<!-- MODAL CREAR / EDITAR -->

<div class="modal fade" id="ModalSector" tabindex="-1">

    <div class="modal-dialog">

        <form id="formSector">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">Nuevo Sector</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input type="hidden"
                           id="edit_sector_id"
                           name="idsector">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Nombre del sector
                            <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               id="sector_nombre"
                               name="nombre"
                               class="form-control"
                               maxlength="120"
                               required>

                    </div>

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Descripción
                        </label>

                        <textarea id="sector_descripcion"
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
                            id="btnGuardarSector"
                            class="btn btn-primary">
                        Guardar Sector
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script src="js/sectores.js?v=<?= time() ?>"></script>

</body>
</html>