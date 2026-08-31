<!-- ======================================================= -->
<!-- 📋 MODAL DETALLE DE TICKET RECIBIDO (Tu diseño original) -->
<!-- ======================================================= -->
<div class="modal fade" id="modalTicket" tabindex="-1" aria-labelledby="modalTicketLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalTicketLabel">
                    <i class="bi bi-receipt me-2"></i> Detalle de Ticket Recibido
                </h5>
                <!-- Usamos la misma sintaxis que en Usuarios (Bootstrap 4) -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <!-- 📋 Cabecera del Detalle (Corregida para legibilidad) -->
                <div class="row g-3 mb-4 bg-body-tertiary p-3 rounded-3 border">
                    <div class="col-12 col-sm-6">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.75rem;">Empleado</small>
                        <span id="modal-empleado" class="fw-bold text-dark fs-5 d-block"></span>
                        <small id="modal-dni" class="text-secondary fw-semibold d-block mt-1"></small>
                    </div>
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Nro. Ticket</small>
                        <!-- Cambiamos a un badge con fondo oscuro y texto blanco bien visible -->
                        <span id="modal-id" class="badge bg-dark text-white fs-6 py-2 px-3 fw-bold"></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.75rem;">Fecha / Hora</small>
                        <span id="modal-fecha" class="text-dark fw-bold d-block mt-2" style="font-size: 0.9rem;"></span>
                    </div>
                </div>

                <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-box-seam me-2"></i> Artículos en este Consumo</h6>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>Descripción del Producto</th>
                                <th class="text-center" style="width: 100px;">Cantidad</th>
                                <th class="text-end" style="width: 140px;">Precio Unit.</th>
                                <th class="text-end" style="width: 140px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="modal-tabla-productos">
                            <!-- Se llena dinámicamente mediante fetch -->
                        </tbody>
                        <tfoot>
                            <tr class="table-active fs-5">
                                <td colspan="3" class="text-end fw-bold">Monto Total Impactado:</td>
                                <td id="modal-total" class="text-end fw-bold text-success"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary">
                <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cerrar Ventana</button>
            </div>
        </div>
    </div>
</div>