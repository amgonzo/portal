<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container mt-5">

    <div class="d-flex justify-content-between mb-4">
        <div>
            <h2>Integración de Tipos de Licencia</h2>
            <p class="text-muted">
                Relacioná los tipos de licencia de RRHH con los tipos de asistencia de Fichajes.
            </p>
        </div>

        <button class="btn btn-primary"
                onclick="abrirNuevaRelacion()">
            <i class="fas fa-plus"></i> Nueva Relación
        </button>
    </div>

    <div class="card shadow-sm p-3">

        <div class="table-responsive">
            <table id="tablaIntegracionTipos"
                   class="table table-striped table-bordered table-hover w-100">

                <thead>
                    <tr>
                        <th>Tipo de licencia (RRHH)</th>
                        <th>Tipo de asistencia (Fichajes)</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody id="listaIntegracionTipos"></tbody>

            </table>
        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal fade" id="ModalIntegracionTipos" tabindex="-1">

    <div class="modal-dialog">

        <form id="formIntegracionTipos">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Nueva Relación</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="integracion_id" name="id">

                    <div class="mb-3">
                        <label class="form-label">
                            Tipo de licencia (RRHH)
                        </label>

                        <select class="form-select"
                                id="integracion_licencia"
                                name="idtipo_licencia"
                                required>
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Tipo de asistencia (Fichajes)
                        </label>

                        <select class="form-select"
                                id="integracion_asistencia"
                                name="idtipo_asistencia"
                                required>
                            <option value="">Seleccionar...</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="button"
                            id="btnGuardarIntegracion"
                            class="btn btn-primary"
                            onclick="guardarRelacion()">
                        Guardar
                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script src="js/integracion_tipos_licencia.js?v=<?= time() ?>"></script>

</body>
</html>