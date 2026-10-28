<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Reglas de Períodos - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <div>
                <h2>Configuración de Reglas de Períodos</h2>
                <p class="text-muted small">Defina el rango de días y meses reales que conforman cada mes operativo de liquidación.</p>
            </div>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaReglas" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th style="width: 15%;">Mes Operativo</th>
                        <th>Inicio del Período</th>
                        <th>Fin del Período</th>
                        <th>Descripción</th>
                        <th style="width: 10%;" class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaReglas">
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL EDITAR REGLA DE PERÍODO -->
    <div class="modal fade" id="ModalRegla" tabindex="-1">
        <div class="modal-dialog">
            <form id="formRegla">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalReglaTitulo">Editar Regla de Período</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="edit_mes_periodo" name="mes_periodo">

                        <div class="mb-3">
                            <label class="font-weight-bold">Mes Operativo Afectado:</label>
                            <input type="text" id="regla_mes_nombre" class="form-control font-weight-bold bg-body-tertiary" readonly>
                        </div>

                        <hr>
                        <h6 class="text-primary font-weight-bold"><i class="fas fa-calendar-alt"></i> Apertura / Inicio del Período</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Día Inicio:</label>
                                <input type="number" min="1" max="31" id="regla_dia_inicio" name="dia_inicio" class="form-control" required>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Mes Inicio Calendario:</label>
                                <select id="regla_mes_inicio" name="mes_inicio" class="form-control" required>
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="form-check-input" id="regla_resta_anio_inicio" name="resta_anio_inicio" value="1">
                                <label class="form-check-label text-danger font-weight-bold" for="regla_resta_anio_inicio">
                                    Corresponde al Año Anterior (Ej: Diciembre de un año atrás)
                                </label>
                            </div>
                        </div>

                        <hr>
                        <h6 class="text-success font-weight-bold"><i class="fas fa-calendar-check"></i> Cierre / Fin del Período</h6>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Día Fin:</label>
                                <input type="number" min="1" max="31" id="regla_dia_fin" name="dia_fin" class="form-control" required>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Mes Fin Calendario:</label>
                                <select id="regla_mes_fin" name="mes_fin" class="form-control" required>
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                        </div>

                        <hr>
                        <div class="mb-3">
                            <label>Descripción / Observación:</label>
                            <input type="text" id="regla_descripcion" name="descripcion" class="form-control" placeholder="Ej: Enero del 26/12 al 31/01">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="guardarRegla()">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- LIBRERÍAS DE JS -->
    <script src="<?php echo versionar('js/config_periodos.js'); ?>"></script>
</body>
</html>