<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Límites de Empleados - <?php echo $empresa; ?></title>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Límites de Asociados</h2>
                <p class="text-muted mb-0">Gestioná los cupos de compra mensuales y los saldos de cuenta corriente.</p>
            </div>
            
            <!-- 📅 SECCIÓN DE FILTROS DE PERÍODO Y CIERRE -->
            <div class="d-flex align-items-center bg-body-tertiary p-2 rounded shadow-sm border">
                <div class="mb-0 me-2">
                    <select id="filtroMes" class="form-control form-control-sm" onchange="cargarTablaLimites()">
                        <?php
                        $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                        $mes_actual = date('n');
                        foreach ($meses as $num => $nombre) {
                            $valor = $num + 1;
                            $selected = ($valor == $mes_actual) ? 'selected' : '';
                            echo "<option value='$valor' $selected>$nombre</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-0 me-3">
                    <select id="filtroAnio" class="form-control form-control-sm" onchange="cargarTablaLimites()">
                        <?php
                        $anio_actual = date('Y');
                        for ($a = $anio_actual; $a >= $anio_actual - 2; $a--) {
                            echo "<option value='$a'>$a</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Botón para Cerrar el Mes Seleccionado -->
                <button class="btn btn-sm btn-danger" id="btnCerrarMes" onclick="confirmarCierreMes()">
                    <i class="fas fa-lock"></i> Cerrar Período
                </button>
            </div>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaLimites" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Nombre y Apellido</th>
                        <th>Cupo Autorizado</th>
                        <th>Consumido Actual</th>
                        <th>Saldo Disponible</th>
                        <th class="text-center">Tendencia</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaLimites">
                    <!-- Se carga dinámicamente por AJAX pasando Mes y Año -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📝 MODAL EDITAR LÍMITE (Sintaxis Bootstrap 4 compatible) -->
    <div class="modal fade" id="ModalLimite" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form id="formLimite">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title fw-bold">Modificar Cupo de Compra</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="edit_dni" name="dni">
                        <!-- Campos ocultos para mantener el contexto del período al guardar -->
                        <input type="hidden" id="edit_mes" name="mes">
                        <input type="hidden" id="edit_anio" name="anio">

                        <div class="form-group mb-3">
                            <label class="fw-bold">Empleado</label>
                            <input type="text" id="edit_nombre" class="form-control-plaintext fs-5 fw-semibold text-primary" readonly>
                        </div>

                        <div class="form-group mb-3">
                            <label class="fw-bold">Límite Mensual ($)</label>
                            <input type="number" step="0.01" id="edit_monto_limite" name="limite_mensual" class="form-control form-control-lg" required>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="edit_activo" name="activo" value="1">
                                <label class="form-check-label fw-bold" for="edit_activo">Habilitado para Compras CTACTE</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-body-tertiary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
        <script src="<?php echo versionar('js/empleados_limites.js'); ?>"></script>
</body>
</html>