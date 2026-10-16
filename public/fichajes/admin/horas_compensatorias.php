
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Horas Compensatorias - <?php echo $empresa; ?></title>
</head>

<body>

    <?php include 'menu.php'; ?>

    <div class="container-fluid px-4" style="margin-top: 30px;">

        <!-- =====================================================
             ENCABEZADO
             ===================================================== -->

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fas fa-business-time text-primary me-2"></i>
                    Horas compensatorias
                </h2>

                <p class="text-muted mb-0">
                    Saldos, generaciones, utilizaciones y vencimientos por empleado.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button
                    type="button"
                    id="btnActualizarHoras"
                    class="btn btn-outline-primary fw-semibold"
                >
                    <i class="fas fa-sync-alt me-2" id="iconoActualizarHoras"></i>
                    Actualizar
                </button>

                <button
                    type="button"
                    id="btnVerAutorizaciones"
                    class="btn btn-primary fw-semibold"
                    data-permiso="horas_compensatorias_autorizar"
                >
                    <i class="fas fa-clipboard-check me-2"></i>
                    Autorizaciones
                    <span
                        id="badgeAutorizacionesPendientes"
                        class="badge bg-light text-primary ms-1"
                    >0</span>
                </button>
                <button
                    type="button"
                    id="btnProcesarVencimientos"
                    class="btn btn-outline-warning fw-semibold d-none"
                    data-permiso="horas_compensatorias_autorizar"
                >
                    <i class="fas fa-hourglass-end me-2"></i>
                    Revisar vencimientos
                    <span
                        id="badgeVencimientosPendientes"
                        class="badge bg-warning text-dark ms-1"
                    >0</span>
                </button>
            </div>
        </div>

        <!-- =====================================================
             RESUMEN GENERAL
             ===================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100"
                     style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Empleados con saldo
                            </h6>
                            <h3 id="totalEmpleadosConSaldo" class="fw-bold text-dark mb-0">—</h3>
                        </div>
                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="fas fa-users" style="font-size: 1.8rem;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100"
                     style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Saldo disponible
                            </h6>
                            <h3 id="totalSaldoDisponible" class="fw-bold text-success mb-0">—</h3>
                        </div>
                        <div class="bg-success-subtle text-success rounded-3 p-3">
                            <i class="fas fa-hourglass-half" style="font-size: 1.8rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2">Minutos disponibles de compensación</small>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100"
                     style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Generado
                            </h6>
                            <h3 id="totalHorasGeneradas" class="fw-bold text-primary mb-0">—</h3>
                        </div>
                        <div class="bg-primary-subtle text-primary rounded-3 p-3">
                            <i class="fas fa-plus-circle" style="font-size: 1.8rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2">Minutos generados vigentes</small>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm bg-white p-3 h-100"
                     style="border-radius: 12px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted text-uppercase fw-semibold small mb-2">
                                Pendientes de autorización
                            </h6>
                            <h3 id="totalAutorizacionesPendientes"
                                class="fw-bold text-warning mb-0">—</h3>
                        </div>
                        <div class="bg-warning-subtle text-warning rounded-3 p-3">
                            <i class="fas fa-clipboard-list" style="font-size: 1.8rem;"></i>
                        </div>
                    </div>
                    <small class="text-muted mt-2">Operaciones pendientes de resolución</small>
                </div>
            </div>

        </div>

        <!-- =====================================================
             FILTROS
             ===================================================== -->

        <div class="card border-0 shadow-sm bg-white mb-4"
             style="border-radius: 12px;">

            <div class="card-body">
                <div class="row g-3 align-items-end">

                    <div class="col-12 col-md-5">
                        <label for="filtroEmpleadoHoras" class="form-label fw-semibold">
                            Empleado
                        </label>
                        <input
                            type="text"
                            id="filtroEmpleadoHoras"
                            class="form-control"
                            placeholder="Buscar por nombre, apellido o documento..."
                        >
                    </div>

                    <div class="col-12 col-md-3">
                        <label for="filtroEstadoSaldo" class="form-label fw-semibold">
                            Estado del saldo
                        </label>
                        <select id="filtroEstadoSaldo" class="form-select">
                            <option value="">Todos</option>
                            <option value="disponible">Con saldo disponible</option>
                            <option value="agotado">Saldo agotado</option>
                            <option value="vencido">Con saldo vencido</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2">
                        <div class="form-check mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="filtroSoloConSaldo"
                            >
                            <label class="form-check-label" for="filtroSoloConSaldo">
                                Solo con saldo
                            </label>
                        </div>
                    </div>

                    <div class="col-12 col-md-2">
                        <button
                            type="button"
                            id="btnLimpiarFiltrosHoras"
                            class="btn btn-outline-secondary w-100"
                        >
                            <i class="fas fa-eraser me-2"></i>
                            Limpiar
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- =====================================================
             LISTADO POR EMPLEADO
             ===================================================== -->

        <div class="card border-0 shadow-sm bg-white mb-4"
             style="border-radius: 12px;">

            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="fw-bold mb-1">Saldos por empleado</h5>
                        <p class="text-muted small mb-0">
                            Detalle de horas generadas, utilizadas, vencidas y disponibles.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card-body px-4">

                <div id="estadoCargaHoras" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Cargando saldos de empleados...</p>
                </div>

                <div class="table-responsive d-none" id="contenedorTablaHoras">
                    <table
                        id="tablaHorasCompensatorias"
                        class="table table-hover align-middle w-100"
                    >
                        <thead class="table-light">
                            <tr>
                                <th>Empleado</th>
                                <th class="text-end">Generado</th>
                                <th class="text-end">Utilizado</th>
                                <th class="text-end">Vencido</th>
                                <th class="text-end">Disponible</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="tbodyHorasCompensatorias">
                        </tbody>
                    </table>
                </div>

                <div id="sinDatosHoras" class="text-center py-5 d-none">
                    <i class="fas fa-hourglass-start text-muted mb-3"
                       style="font-size: 2.5rem;"></i>
                    <h6 class="fw-bold">No hay saldos para mostrar</h6>
                    <p class="text-muted mb-0">
                        Cuando se generen horas compensatorias aparecerán aquí.
                    </p>
                </div>

            </div>
        </div>

        <!-- =====================================================
             HISTORIAL DE MOVIMIENTOS
             ===================================================== -->

        <div class="card border-0 shadow-sm bg-white mb-4"
             id="panelHistorialHoras"
             style="border-radius: 12px;">

            <div class="card-header bg-white border-0 pt-4 px-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="fw-bold mb-1">Historial de movimientos</h5>
                        <p class="text-muted small mb-0">
                            Generaciones, utilizaciones, vencimientos, ajustes y reversiones.
                        </p>
                    </div>

                    <span id="empleadoHistorialSeleccionado"
                          class="badge bg-secondary-subtle text-secondary">
                        Todos los empleados
                    </span>
                </div>
            </div>

            <div class="card-body px-4">

                <div id="estadoCargaMovimientos" class="text-center py-3 d-none">
                    <div class="spinner-border spinner-border-sm text-primary"
                         role="status"></div>
                    <span class="text-muted ms-2">Cargando movimientos...</span>
                </div>

                <div class="table-responsive">
                    <table
                        id="tablaMovimientosHoras"
                        class="table table-hover align-middle w-100"
                    >
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Empleado</th>
                                <th>Movimiento</th>
                                <th class="text-end">Minutos</th>
                                <th>Estado</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>

                        <tbody id="tbodyMovimientosHoras">
                        </tbody>
                    </table>
                </div>

                <div id="sinMovimientosHoras" class="text-center py-4 d-none">
                    <p class="text-muted mb-0">
                        No hay movimientos para los filtros seleccionados.
                    </p>
                </div>

            </div>
        </div>

    </div>

    <!-- =====================================================
         MODAL DETALLE DE EMPLEADO
         ===================================================== -->

    <div
        class="modal fade"
        id="modalDetalleHoras"
        tabindex="-1"
        aria-labelledby="tituloModalDetalleHoras"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold" id="tituloModalDetalleHoras">
                            Detalle de horas compensatorias
                        </h5>
                        <div class="text-muted small" id="subtituloModalDetalleHoras"></div>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

                <div class="modal-body">

                    <div class="row g-3 mb-4">

                        <div class="col-12 col-md-3">
                            <div class="rounded-3 bg-light p-3 h-100">
                                <small class="text-muted d-block">Generado</small>
                                <h5 class="fw-bold mb-0" id="detalleGenerado">—</h5>
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <div class="rounded-3 bg-light p-3 h-100">
                                <small class="text-muted d-block">Utilizado</small>
                                <h5 class="fw-bold mb-0" id="detalleUtilizado">—</h5>
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <div class="rounded-3 bg-light p-3 h-100">
                                <small class="text-muted d-block">Vencido</small>
                                <h5 class="fw-bold mb-0" id="detalleVencido">—</h5>
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <div class="rounded-3 bg-success-subtle p-3 h-100">
                                <small class="text-success d-block">Disponible</small>
                                <h5 class="fw-bold text-success mb-0"
                                    id="detalleDisponible">—</h5>
                            </div>
                        </div>

                    </div>

                    <h6 class="fw-bold mb-3">Lotes de compensación</h6>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha de origen</th>
                                    <th>Origen</th>
                                    <th class="text-end">Generado</th>
                                    <th class="text-end">Disponible</th>
                                    <th>Vencimiento</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody id="tbodyLotesEmpleado">
                            </tbody>
                        </table>
                    </div>

                    <div id="sinLotesEmpleado" class="text-center text-muted py-3 d-none">
                        Este empleado no tiene lotes de compensación.
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold mb-3">Movimientos del empleado</h6>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Movimiento</th>
                                    <th class="text-end">Minutos</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>

                            <tbody id="tbodyDetalleMovimientos">
                            </tbody>
                        </table>
                    </div>

                    <div id="sinDetalleMovimientos"
                         class="text-center text-muted py-3 d-none">
                        Este empleado no tiene movimientos.
                    </div>

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- =====================================================
         MODAL AUTORIZACIONES
         ===================================================== -->

    <div
        class="modal fade"
        id="modalAutorizacionesHoras"
        tabindex="-1"
        aria-labelledby="tituloModalAutorizacionesHoras"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="tituloModalAutorizacionesHoras">
                        Autorizaciones de horas compensatorias
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

                <div class="modal-body">

                    <div class="alert alert-info">
                        Aquí se mostrarán las solicitudes pendientes de generación o utilización
                        que requieren autorización según la configuración de la empresa.
                    </div>

                    <div class="table-responsive">
                        <table
                            id="tablaAutorizacionesHoras"
                            class="table table-hover align-middle w-100"
                        >
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Empleado</th>
                                    <th>Operación</th>
                                    <th class="text-end">Minutos</th>
                                    <th>Motivo</th>
                                    <th>Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>

                            <tbody id="tbodyAutorizacionesHoras">
                            </tbody>
                        </table>
                    </div>

                    <div id="sinAutorizacionesHoras"
                         class="text-center text-muted py-4 d-none">
                        No hay autorizaciones pendientes.
                    </div>

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <script src="<?php echo versionar('js/horas_compensatorias.js'); ?>"></script>

</body>
</html>
