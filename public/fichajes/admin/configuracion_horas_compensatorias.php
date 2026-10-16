<?php

// =========================================================
// CONFIGURACIÓN DE HORAS COMPENSATORIAS
// =========================================================

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Configuración de horas compensatorias - <?php echo $empresa; ?></title>
</head>

<body>

    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <!-- ========================================== -->
        <!-- ENCABEZADO                                -->
        <!-- ========================================== -->

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-0">
                    <i class="bi bi-clock-history me-2 text-primary"></i>
                    Configuración de horas compensatorias
                </h2>

                <p class="text-muted mb-0">
                    Definición de las reglas de generación, vigencia y vencimiento de los saldos.
                </p>
            </div>

            <a href="dashboard.php" class="btn btn-outline-secondary fw-semibold">
                <i class="bi bi-arrow-left me-2"></i>
                Volver
            </a>
        </div>

        <!-- ========================================== -->
        <!-- ESTADO                                     -->
        <!-- ========================================== -->

        <div
            id="alertaConfiguracionHoras"
            class="alert d-none"
            role="alert">
        </div>

        <!-- ========================================== -->
        <!-- FORMULARIO                                 -->
        <!-- ========================================== -->

        <form id="formConfiguracionHoras" autocomplete="off">

            <!-- TRATAMIENTO DE HORAS EXTRA -->

            <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white mb-4">

                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-gear-fill text-primary fs-5"></i>
                    <h5 class="fw-bold mb-0">Tratamiento de horas extra</h5>
                </div>

                <p class="text-muted small mb-4">
                    Define cómo se tratarán las horas extra detectadas durante el cálculo de las jornadas.
                </p>

                <div class="row g-3">

                    <div class="col-12 col-md-6">
                        <label for="tratamiento_horas_extra" class="form-label fw-semibold">
                            Tratamiento
                        </label>

                        <select
                            id="tratamiento_horas_extra"
                            name="tratamiento_horas_extra"
                            class="form-select"
                            required>
                            <option value="ninguna">Ninguna</option>
                            <option value="pago">Pago</option>
                            <option value="compensacion">Compensación</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="tipo_extra_fuera_horario" class="form-label fw-semibold">
                            Horas extra fuera del horario
                        </label>

                        <select
                            id="tipo_extra_fuera_horario"
                            name="tipo_extra_fuera_horario"
                            class="form-select"
                            required>
                            <option value="ninguna">Ninguna</option>
                            <option value="despues">Después del horario</option>
                            <option value="antes">Antes del horario</option>
                            <option value="antes_despues">Antes y después del horario</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="minutos_minimos_extra" class="form-label fw-semibold">
                            Minutos mínimos de horas extra
                        </label>

                        <input
                            type="number"
                            id="minutos_minimos_extra"
                            name="minutos_minimos_extra"
                            class="form-control"
                            min="0"
                            step="1"
                            required>

                        <div class="form-text">
                            Umbral mínimo para considerar horas extra.
                        </div>
                    </div>

                </div>
            </div>

            <!-- GENERACIÓN Y VENCIMIENTO -->

            <div
                id="panelReglasCompensacion"
                class="card border-0 shadow-sm rounded-3 p-3 p-md-4 bg-white mb-4">

                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-hourglass-split text-warning fs-5"></i>
                    <h5 class="fw-bold mb-0">Generación y vigencia del saldo</h5>
                </div>

                <p class="text-muted small mb-4">
                    Configuración de la generación de lotes compensatorios y del vencimiento de los minutos disponibles.
                </p>

                <div class="row g-3">

                    <div class="col-12 col-md-6">
                        <label for="modo_generacion_compensacion" class="form-label fw-semibold">
                            Generación de horas compensatorias
                        </label>

                        <select
                            id="modo_generacion_compensacion"
                            name="modo_generacion_compensacion"
                            class="form-select"
                            required>
                            <option value="automatico">Automática</option>
                            <option value="autorizado">Requiere autorización</option>
                        </select>

                        <div class="form-text">
                            Las horas se generan desde el cálculo de la jornada; no se cargan minutos manualmente.
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="vigencia_saldo_compensacion" class="form-label fw-semibold">
                            Vigencia del saldo
                        </label>

                        <select
                            id="vigencia_saldo_compensacion"
                            name="vigencia_saldo_compensacion"
                            class="form-select"
                            required>
                            <option value="mismo_mes">Hasta fin del mismo mes</option>
                            <option value="meses">Cantidad de meses</option>
                        </select>
                    </div>

                    <div
                        class="col-12 col-md-6"
                        id="contenedorMesesVigencia">

                        <label for="meses_vigencia_compensacion" class="form-label fw-semibold">
                            Meses de vigencia
                        </label>

                        <input
                            type="number"
                            id="meses_vigencia_compensacion"
                            name="meses_vigencia_compensacion"
                            class="form-control"
                            min="1"
                            step="1"
                            required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="modo_vencimiento_compensacion" class="form-label fw-semibold">
                            Tratamiento del vencimiento
                        </label>

                        <select
                            id="modo_vencimiento_compensacion"
                            name="modo_vencimiento_compensacion"
                            class="form-select"
                            required>
                            <option value="automatico">Automático</option>
                            <option value="revision">Requiere revisión</option>
                        </select>
                    </div>

                </div>

                <div class="alert alert-info border-0 rounded-3 mt-4 mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Los saldos se administran mediante lotes y movimientos históricos.
                    Las utilizaciones deben consumir primero los lotes más antiguos (FIFO).
                    Las anulaciones y reversiones deben conservar sus antecedentes.
                </div>

            </div>

            <!-- ====================================== -->
            <!-- ACCIONES                               -->
            <!-- ====================================== -->

            <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">

                <button
                    type="button"
                    id="btnRecargarConfiguracionHoras"
                    class="btn btn-outline-secondary fw-semibold">
                    <i class="bi bi-arrow-clockwise me-2"></i>
                    Recargar
                </button>

                <button
                    type="submit"
                    id="btnGuardarConfiguracionHoras"
                    class="btn btn-primary fw-semibold">
                    <i class="bi bi-save me-2"></i>
                    Guardar configuración
                </button>

            </div>

        </form>

    </div>

    <!-- ========================================== -->
    <!-- SCRIPTS                                    -->
    <!-- ========================================== -->

    <script src="<?php echo versionar('js/configuracion_horas_compensatorias.js'); ?>"></script>

</body>
</html>