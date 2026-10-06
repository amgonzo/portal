<?php
include 'header.php';
include 'menu.php';
?>

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1">Jornadas</h3>
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

                    <input
                        type="date"
                        class="form-control"
                        id="jornada_fecha_calculo">
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

                    <label class="form-label">
                        Fecha
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="marca_manual_fecha_mostrar"
                        readonly>

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


<style>

.jornada-detalle {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

.jornada-detalle-titulo {
    font-weight: 600;
    font-size: 15px;
    margin-bottom: 12px;
}

.jornada-seccion {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 12px;
}

.jornada-seccion h6 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}

.jornada-ficha {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 0;
    border-bottom: 1px solid #eee;
}

.jornada-ficha:last-child {
    border-bottom: 0;
}

.jornada-hora {
    width: 60px;
    font-weight: 600;
    font-family: monospace;
    font-size: 14px;
}

.jornada-origen {
    min-width: 110px;
}

.jornada-info {
    font-size: 13px;
    color: #6c757d;
}

.jornada-tramo {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    margin: 2px 4px 2px 0;
    border-radius: 5px;
    background: #f1f3f5;
    font-size: 13px;
}

.jornada-extra {
    border-left: 3px solid #ffc107;
}

.jornada-permiso {
    border-left: 3px solid #0dcaf0;
}

.jornada-sin-horario {
    color: #dc3545;
}

.btn-expandir-jornada {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

</style>

<script src="js/jornadas.js?v=<?php echo time(); ?>"></script>