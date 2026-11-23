<?php
require_once 'header.php';
require_once 'menu.php';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Reglas de vacaciones</h4>

        <button type="button" class="btn btn-primary" id="btnNuevaRegla">
            Nueva regla
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaReglasVacaciones"
                       class="table table-striped table-bordered dt-responsive nowrap"
                       style="width:100%">

                    <thead>
                        <tr>
                            <th>Antigüedad desde</th>
                            <th>Antigüedad hasta</th>
                            <th>Días asignados</th>
                            <th>Estado</th>
                            <th>Fecha de registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody id="listaReglasVacaciones"></tbody>

                </table>
            </div>
        </div>
    </div>

</div>

<!-- MODAL REGLA -->
<div class="modal fade" id="ModalReglaVacaciones" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalRegla">Nueva regla</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form id="formReglaVacaciones">

                <div class="modal-body">

                    <input type="hidden" id="regla_id" name="idregla">

                    <div class="form-group">
                        <label for="antiguedad_desde">Antigüedad desde (años)</label>
                        <input type="number"
                               class="form-control"
                               id="antiguedad_desde"
                               name="antiguedad_desde"
                               min="0"
                               step="1"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="antiguedad_hasta">Antigüedad hasta (años)</label>
                        <input type="number"
                               class="form-control"
                               id="antiguedad_hasta"
                               name="antiguedad_hasta"
                               min="0"
                               step="1"
                               placeholder="Sin límite">
                    </div>

                    <div class="form-group">
                        <label for="dias_asignados">Días asignados</label>
                        <input type="number"
                               class="form-control"
                               id="dias_asignados"
                               name="dias_asignados"
                               min="0.01"
                               step="0.01"
                               required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="btnGuardarRegla">
                        Guardar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="js/reglas_vacaciones.js?v=<?= time() ?>"></script>

