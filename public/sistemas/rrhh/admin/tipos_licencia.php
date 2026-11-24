<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Tipos de Licencia - <?= htmlspecialchars($empresa ?? 'RRHH') ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">Gestión de Tipos de Licencia</h2>
            <p class="text-muted mb-0">
                Administración de los tipos de licencia.
            </p>
        </div>

        <button
            class="btn btn-primary fw-semibold shadow-sm"
            onclick="abrirNuevoTipoLicencia()">
            Nuevo Tipo de Licencia
        </button>

    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">

            <table id="tablaTiposLicencia"
                   class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th class="text-center">Requiere documentación</th>
                        <th class="text-center">Descuenta vacaciones</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>

                <tbody id="listaTiposLicencia"></tbody>

            </table>

        </div>

    </div>

</div>

<!-- MODAL CREAR / EDITAR -->

<div class="modal fade" id="ModalTipoLicencia" tabindex="-1">

    <div class="modal-dialog">

        <form id="formTipoLicencia">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">Nuevo Tipo de Licencia</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <input type="hidden"
                           id="edit_tipo_licencia_id"
                           name="idtipo_licencia">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Nombre
                            <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               id="tipo_licencia_nombre"
                               name="nombre"
                               class="form-control"
                               maxlength="100"
                               required>

                    </div>

                    <div class="form-check mb-3">

                        <input class="form-check-input"
                               type="checkbox"
                               id="requiere_documentacion"
                               name="requiere_documentacion">

                        <label class="form-check-label"
                               for="requiere_documentacion">
                            Requiere documentación
                        </label>

                    </div>

                    <div class="form-check mb-3">

                        <input class="form-check-input"
                               type="checkbox"
                               id="descuenta_vacaciones"
                               name="descuenta_vacaciones">

                        <label class="form-check-label"
                               for="descuenta_vacaciones">
                            Descuenta vacaciones
                        </label>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="button"
                            id="btnGuardarTipoLicencia"
                            class="btn btn-primary">
                        Guardar
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script src="js/tipos_licencia.js?v=<?= time() ?>"></script>

</body>
</html>