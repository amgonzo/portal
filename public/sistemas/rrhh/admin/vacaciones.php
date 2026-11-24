<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Vacaciones - <?= htmlspecialchars($empresa ?? 'RRHH') ?></title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container-fluid px-4 py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="fw-bold mb-1">Vacaciones</h2><p class="text-muted mb-0">Saldos anuales y períodos registrados.</p></div>
        <div class="d-flex gap-2 align-items-end"><div><label for="vac_anio" class="form-label fw-semibold mb-1">Año</label><select id="vac_anio" class="form-select"></select></div><button id="btnVacActualizar" class="btn btn-outline-primary">Actualizar</button></div>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm rounded-3 p-3"><div class="text-muted small">Empleados</div><div class="fs-3 fw-bold" id="vacTotalEmpleados">0</div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm rounded-3 p-3"><div class="text-muted small">Días asignados</div><div class="fs-3 fw-bold" id="vacTotalAsignados">0</div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm rounded-3 p-3"><div class="text-muted small">Días utilizados</div><div class="fs-3 fw-bold" id="vacTotalUtilizados">0</div></div></div>
        <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm rounded-3 p-3"><div class="text-muted small">Saldo disponible</div><div class="fs-3 fw-bold" id="vacTotalSaldo">0</div></div></div>
    </div>
    <div class="card border-0 shadow-sm rounded-3 p-3">
        <div class="row g-3 mb-3 align-items-end"><div class="col-12 col-md-5"><label for="vac_buscar" class="form-label fw-semibold">Buscar empleado</label><input type="search" id="vac_buscar" class="form-control" placeholder="Apellido, nombre o documento"></div><div class="col-12 col-md-7 text-md-end"><span class="text-muted small">El saldo se calcula según la regla de antigüedad. Los períodos cuentan días hábiles de lunes a viernes.</span></div></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Empleado</th><th>Documento</th><th class="text-end">Asignados</th><th class="text-end">Ajustes</th><th class="text-end">Utilizados</th><th class="text-end">Saldo disponible</th><th class="text-center">Acciones</th></tr></thead><tbody id="vacListaEmpleados"><tr><td colspan="7" class="text-center text-muted py-4">Cargando...</td></tr></tbody></table></div>
    </div>
</div>
<div class="modal fade" id="ModalVacaciones" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form id="formVacaciones"><div class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title">Generar período de vacaciones</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="vac_idempleado" name="idempleado">
        <div class="mb-3"><label class="form-label fw-semibold">Empleado</label><input id="vac_empleado" class="form-control" readonly></div>
        <div class="alert alert-info py-2 mb-3"><div>Saldo disponible: <strong id="vac_saldo_modal">0</strong> días</div><div class="small" id="vac_regla_info"></div></div>
        <div class="row g-3"><div class="col-6"><label for="vac_fecha_desde" class="form-label fw-semibold">Desde</label><input type="date" id="vac_fecha_desde" name="fecha_desde" class="form-control" required></div><div class="col-6"><label for="vac_fecha_hasta" class="form-label fw-semibold">Hasta</label><input type="date" id="vac_fecha_hasta" name="fecha_hasta" class="form-control" required></div></div>
        <div class="mt-3"><label class="form-label fw-semibold">Días hábiles del período</label><input id="vac_dias_periodo" class="form-control" readonly value="0"><div class="form-text">Se cuentan lunes a viernes; feriados no se descuentan automáticamente.</div></div>
        <div class="mt-3"><label for="vac_motivo" class="form-label fw-semibold">Observaciones</label><textarea id="vac_motivo" name="motivo" class="form-control" maxlength="500" rows="2"></textarea></div>
    </div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" id="btnVacGuardar" class="btn btn-primary">Confirmar período</button></div>
</div></form></div></div>
<script src="js/vacaciones.js?v=<?= time() ?>"></script>
</body></html>