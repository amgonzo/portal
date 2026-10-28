<?php
include 'header.php';
include 'menu.php';
?>

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">Jornadas</h2>
            <div class="text-muted">
                Jornadas pendientes de procesamiento.
            </div>
        </div>

        <div class="d-flex gap-2">
            <button
                type="button"
                class="btn btn-outline-secondary"
                id="btnActualizarJornadas"
                data-permiso="jornadas_gestionar">
                <i class="fas fa-sync-alt me-1"></i>
                Actualizar
            </button>

            <button
                type="button"
                class="btn btn-primary"
                id="btnCalcularJornadas"
                data-permiso="jornadas_gestionar">
                <i class="fas fa-calculator me-1"></i>
                Calcular jornadas
            </button>
        </div>
    </div>
    <!-- Aviso de autorizaciones pendientes de horas compensatorias -->
    <div
        id="avisoAutorizacionesCompensacion"
        class="alert alert-warning d-none mb-3"
        role="alert"
        aria-live="polite"
    >
        <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-triangle me-2 mt-1"></i>

            <div>
                <strong>Hay horas compensatorias pendientes de autorización.</strong>

                <div id="textoAvisoAutorizacionesCompensacion" class="mt-1">
                    Hay solicitudes que deben revisarse.
                </div>

                <small>
                    Revisá las autorizaciones antes de calcular las jornadas.
                </small>
            </div>
        </div>
    </div>
    <div class="card shadow-sm">
        <div class="card-body">

            <div class="table-responsive">
                <table
                    class="table table-hover align-middle w-100"
                    id="tablaJornadasPendientes">

                    <thead>
                        <tr>
                            <th style="width: 28px;"></th>
                            <th>Empleado</th>
                            <th>Documento</th>
                            <th>Fecha</th>
                            <th class="text-center">Marcas</th>
                            <th>Estado</th>
                            <th class="text-center">Detalle</th>
                        </tr>
                    </thead>

                    <tbody></tbody>

                </table>
            </div>

        </div>
    </div>

</div>


<!-- =========================================================
     MODAL CALCULAR JORNADAS
========================================================= -->

<div
    class="modal fade"
    id="ModalCalcularJornadas"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calculator me-2"></i>
                    Calcular jornadas
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>
            </div>

            <div class="modal-body">

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    Se procesarán todas las jornadas pendientes
                    correspondientes a la fecha seleccionada.
                </div>

                <div class="mb-3">
                    <label
                        for="jornada_fecha_calculo"
                        class="form-label">
                        Fecha a procesar
                    </label>

                    <select
                        class="form-select"
                        id="jornada_fecha_calculo">
                        <option value="">Seleccione una fecha pendiente</option>
                    </select>
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
                    id="btnEjecutarCalculo">
                    <i class="fas fa-calculator me-1"></i>
                    Calcular
                </button>

            </div>

        </div>

    </div>
</div>


<!-- =========================================================
     MODAL FICHAJE MANUAL
========================================================= -->

<div
    class="modal fade"
    id="ModalMarcaManual"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    <i class="fas fa-hand-pointer me-2"></i>
                    Agregar fichaje manual
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>

            </div>

            <div class="modal-body">

                <input
                    type="hidden"
                    id="marca_manual_idempleado">

                <input
                    type="hidden"
                    id="marca_manual_fecha">

                <div class="mb-3">

                    <label class="form-label">
                        Empleado
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="marca_manual_empleado"
                        readonly>

                </div>

                <div class="mb-3">
                    <label for="marca_manual_fecha_mostrar" class="form-label">
                        Fecha
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        id="marca_manual_fecha_mostrar"
                        required>
                </div>

                <div class="mb-3">

                    <label
                        for="marca_manual_hora"
                        class="form-label">
                        Hora
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="marca_manual_hora"
                        maxlength="5"
                        autocomplete="off"
                        placeholder="HH:mm">

                    <div class="form-text">
                        Ejemplos: 8, 800, 11:03, 1103.
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
                    type="button"
                    class="btn btn-primary"
                    id="btnGuardarMarcaManual">
                    <i class="fas fa-save me-1"></i>
                    Guardar fichaje
                </button>

            </div>

        </div>

    </div>
</div>

<script src="js/jornadas.js?v=<?php echo time(); ?>"></script>