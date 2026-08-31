let tablaData = null;
let timerBusqueda = null;

document.addEventListener("DOMContentLoaded", function() {
    inicializarDataTable();
    cargarPeriodos();
    cargarComboPersonas();
    cargarFacturas();
});

function inicializarDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaPendientes')) {
        $('#tablaPendientes').DataTable().destroy();
    }

    tablaData = $('#tablaPendientes').DataTable({
        language: {
            processing:     "Procesando...",
            search:         "Buscar:",
            lengthMenu:     "Mostrar _MENU_ registros",
            info:           "Mostrando _START_ a _END_ de _TOTAL_ comprobantes",
            infoEmpty:      "Mostrando 0 a 0 de 0 comprobantes",
            infoFiltered:   "(filtrado de _MAX_ comprobantes en total)",
            loadingRecords: "Cargando comprobantes...",
            zeroRecords:    "No se encontraron comprobantes",
            emptyTable:     "No hay comprobantes disponibles con los filtros aplicados",
            paginate: {
                first:      "Primero",
                previous:   "Anterior",
                next:       "Siguiente",
                last:       "Último"
            }
        },
        pageLength: 10,
        responsive: true,
        order: [[0, 'desc']],
        dom: 'rtip'
    });
}

function cargarPeriodos() {
    fetch(API_BASE + '/ctacte/compras/obtener_periodos_combo.php', {
        method: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
        .then(res => res.json())
        .then(periodos => {
            let options = '<option value="">-- Todos los períodos abiertos --</option>';
            if (Array.isArray(periodos)) {
                periodos.forEach(p => {
                    options += `<option value="${p}">${p}</option>`;
                });
            }
            document.getElementById('filtro_periodo').innerHTML = options;
        })
        .catch(err => console.error("Error al cargar períodos:", err));
}

function cargarComboPersonas() {
    fetch(API_BASE + '/ctacte/personas/obtener_personas_combo.php?todos=1', {
        method: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
        .then(res => res.json())
        .then(personas => {
            let optionsModal = '<option value="">-- Seleccionar Empleado --</option>';
            optionsModal += '<option value="0">⚠️ Devolver a Sin Usuario (DNI 0)</option>';

            let optionsFiltro = '<option value="0" selected>Solo Compras Sin Usuario (DNI 0)</option>';
            optionsFiltro += '<option value="todos">-- Todos los empleados --</option>';

            if (Array.isArray(personas)) {
                personas.forEach(p => {
                    const labelInactivo = Number(p.activo) === 0 ? ' (INACTIVO)' : '';
                    const opt = `<option value="${p.dni}">${p.apellido}, ${p.nombre}${labelInactivo} (DNI: ${p.dni})</option>`;
                    
                    optionsModal += opt;
                    
                    if (Number(p.activo) === 1) {
                        optionsFiltro += opt;
                    }
                });
            }

            document.getElementById('select_dni_nuevo').innerHTML = optionsModal;
            document.getElementById('filtro_empleado').innerHTML = optionsFiltro;
        })
        .catch(err => console.error("Error al cargar personas:", err));
}

function cargarFacturas() {
    const periodo  = document.getElementById('filtro_periodo').value;
    const dni      = document.getElementById('filtro_empleado').value;
    const ticket   = document.getElementById('filtro_ticket').value.trim();
    const verAnulados = document.getElementById('filtro_anulados').value;

    fetch(API_BASE + `/ctacte/compras/obtener_compras_filtradas.php?periodo=${periodo}&dni=${dni}&ticket=${encodeURIComponent(ticket)}&anulados=${verAnulados}`, {
        method: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
        .then(res => res.json())
        .then(data => {
            tablaData.clear();

            if (Array.isArray(data) && data.length > 0) {
                const puedoReasignar = tienePermiso('comprobantes_reasignar');
                const puedoAnular    = tienePermiso('comprobantes_anular');

                data.forEach((c, index) => {
                    const esAnulado = Number(c.anulado) === 1;

                    if (verAnulados === '0' && esAnulado) return;
                    if (verAnulados === '1' && !esAnulado) return;

                    const pvFormateado = String(c.punto_venta_id).padStart(4, '0');
                    const ticketFormateado = String(c.venta_id).padStart(8, '0');
                    const numeroTicketCompuesto = `${pvFormateado}-${ticketFormateado}`;

                    const articulosHTML = procesarYFormatearDetalles(c.detalles_resumen, index);
                    
                    const esSinUsuario = c.dni_empleado === '0' || c.dni_empleado === 'SIN_USUARIO' || !c.dni_empleado;
                    const badgeTitular = esSinUsuario 
                        ? `<span class="badge bg-warning text-dark"><i class="fas fa-user-slash me-1"></i>Sin Usuario</span>`
                        : `<span class="badge bg-info text-dark"><i class="fas fa-user me-1"></i>${c.titular_actual}</span>`;

                    const badgeAnulado = esAnulado 
                        ? `<span class="badge bg-danger ms-1" title="Motivo: ${c.motivo_anulacion || 'Sin motivo'}"><i class="fas fa-ban me-1"></i>ANULADO</span>` 
                        : '';

                    const colComprobante = `
                        <div class="d-flex flex-column gap-1">
                            <div>
                                <span class="badge bg-light text-dark border font-monospace fs-6">
                                    <i class="fas fa-receipt me-1 text-primary"></i>${numeroTicketCompuesto}
                                </span>
                                ${badgeAnulado}
                            </div>
                            ${badgeTitular}
                        </div>`;

                    const esCerrado = Number(c.es_cerrado) === 1;
                    let colBoton = '';

                    if (esAnulado) {
                        const puedoDesanular = tienePermiso('comprobantes_desanular');

                        colBoton = `
                            <div class="d-flex flex-column align-items-center gap-1">
                                <button class="btn btn-sm btn-outline-danger shadow-sm w-100" disabled title="Motivo: ${c.motivo_anulacion || 'Sin especificación'}">
                                    <i class="fas fa-ban me-1"></i> Anulado
                                </button>
                                ${puedoDesanular ? `
                                    <button class="btn btn-sm btn-outline-success shadow-sm" title="Desanular y restaurar comprobante" onclick="desanularComprobante(${c.punto_venta_id}, ${c.venta_id}, '${numeroTicketCompuesto}')">
                                        <i class="fas fa-undo me-1"></i> Desanular
                                    </button>
                                ` : ''}
                            </div>`;
                    } else if (esCerrado) {
                        colBoton = `
                            <div class="text-center">
                                <button class="btn btn-sm btn-secondary shadow-sm" disabled title="Período cerrado">
                                    <i class="fas fa-lock me-1"></i> Período Cerrado
                                </button>
                            </div>`;
                    } else {
                        let botonesHTML = '';

                        if (puedoReasignar) {
                            botonesHTML += `
                                <button class="btn btn-sm btn-primary shadow-sm" title="Reasignar" onclick="abrirModalReasignar(${c.punto_venta_id}, ${c.venta_id}, '${numeroTicketCompuesto}', '${c.importe_total}')">
                                    <i class="fas fa-user-edit me-1"></i> Reasignar
                                </button>`;
                        }

                        if (puedoAnular) {
                            botonesHTML += `
                                <button class="btn btn-sm btn-outline-danger shadow-sm" title="Anular comprobante" onclick="anularComprobante(${c.punto_venta_id}, ${c.venta_id}, '${numeroTicketCompuesto}')">
                                    <i class="fas fa-trash-alt me-1"></i> Anular
                                </button>`;
                        }

                        colBoton = botonesHTML !== '' 
                            ? `<div class="d-flex gap-1 justify-content-center">${botonesHTML}</div>` 
                            : `<div class="text-center text-muted small">—</div>`;
                    }

                    const importeHTML = esAnulado 
                        ? `<span class="text-decoration-line-through text-muted">$${c.importe_total}</span>` 
                        : `<strong class="text-success">$${c.importe_total}</strong>`;

                    tablaData.row.add([
                        c.fecha_compra,
                        colComprobante,
                        importeHTML,
                        articulosHTML,
                        colBoton
                    ]);
                });
            }

            tablaData.draw();
        })
        .catch(err => console.error("Error al cargar comprobantes:", err));
}

function anularComprobante(pv_id, venta_id, ticketFormateado) {
    Swal.fire({
        title: 'Anular Comprobante',
        html: `Vas a anular el ticket <strong class="font-monospace text-primary">${ticketFormateado}</strong>.<br><br>Ingresá el motivo de la anulación:`,
        input: 'textarea',
        inputPlaceholder: 'Ej: Factura duplicada, cargado por error, devolución...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-ban me-1"></i>Confirmar Anulación',
        cancelButtonText: 'Cancelar',
        preConfirm: (motivo) => {
            if (!motivo || !motivo.trim()) {
                Swal.showValidationMessage('Es obligatorio detallar el motivo de la anulación');
                return false;
            }
            return motivo.trim();
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('punto_venta_id', pv_id);
            formData.append('venta_id', venta_id);
            formData.append('motivo', result.value);

            fetch(API_BASE + '/ctacte/compras/anular_compra.php', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
                },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    cargarFacturas();
                    toast(res.msg || 'El comprobante se anuló correctamente.', 'success');
                } else {
                    toast(res.msg || 'No se pudo anular el comprobante.', 'error');
                }
            })
            .catch(err => {
                console.error("Error al anular:", err);
                toast('Error de conexión con el servidor.', 'error');
            });
        }
    });
}

function desanularComprobante(pv_id, venta_id, ticketFormateado) {
    Swal.fire({
        title: '¿Desanular Comprobante?',
        html: `Se restaurará el ticket <strong class="font-monospace text-primary">${ticketFormateado}</strong> y se borrará el motivo de anulación registrado.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-undo me-1"></i> Sí, Desanular',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('punto_venta_id', pv_id);
            formData.append('venta_id', venta_id);

            fetch(API_BASE + '/ctacte/compras/desanular_compra.php', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
                },
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'ok') {
                    cargarFacturas();
                    toast(res.msg || 'El comprobante fue restaurado correctamente.', 'success');
                } else {
                    toast(res.msg || 'No se pudo desanular el comprobante.', 'error');
                }
            })
            .catch(err => {
                console.error("Error al desanular:", err);
                toast('Error de conexión con el servidor.', 'error');
            });
        }
    });
}

function filtrarTabla() {
    clearTimeout(timerBusqueda);
    timerBusqueda = setTimeout(() => {
        cargarFacturas();
    }, 300);
}

function procesarYFormatearDetalles(detallesStr, rowId) {
    if (!detallesStr || detallesStr === 'Sin detalles') {
        return '<span class="text-muted small">Sin detalles</span>';
    }

    const itemsRaw = detallesStr.split(', ');
    const mapaArticulos = {};

    itemsRaw.forEach(item => {
        const itemLimpio = item.trim();
        if (!itemLimpio) return;

        const pos = itemLimpio.indexOf(' - ');
        if (pos !== -1) {
            const cantConUnidad = itemLimpio.substring(0, pos).trim();
            const desc = itemLimpio.substring(pos + 3).trim();
            if (desc) {
                mapaArticulos[desc] = cantConUnidad;
            }
        } else {
            mapaArticulos[itemLimpio] = '1 U';
        }
    });

    const listaAgrupada = Object.entries(mapaArticulos);
    if (listaAgrupada.length === 0) return '<span class="text-muted small">Sin ítems válidos</span>';

    const LIMITE_VISIBLES = 4;
    const visibles = listaAgrupada.slice(0, LIMITE_VISIBLES);
    const ocultos = listaAgrupada.slice(LIMITE_VISIBLES);

    let html = '<ul class="list-unstyled mb-0 d-flex flex-column gap-1">';
    visibles.forEach(([desc, cantUnidad]) => {
        html += `
            <li class="d-flex align-items-center">
                <span class="badge bg-secondary me-2">${cantUnidad}</span>
                <span class="text-dark small fw-medium text-truncate" style="max-width: 320px;" title="${desc}">${desc}</span>
            </li>`;
    });

    if (ocultos.length > 0) {
        const collapseId = `detalles_collapse_${rowId}`;
        html += `<div class="collapse mt-1" id="${collapseId}">`;
        ocultos.forEach(([desc, cantUnidad]) => {
            html += `
                <li class="d-flex align-items-center mb-1">
                    <span class="badge bg-secondary me-2">${cantUnidad}</span>
                    <span class="text-dark small fw-medium text-truncate" style="max-width: 320px;" title="${desc}">${desc}</span>
                </li>`;
        });
        html += `</div>`;

        html += `
            <a class="text-primary small text-decoration-none fw-bold mt-1 d-inline-block" 
               data-bs-toggle="collapse" 
               href="#${collapseId}" 
               role="button" 
               aria-expanded="false" 
               aria-controls="${collapseId}">
                <i class="fas fa-chevron-down me-1"></i>Ver ${ocultos.length} ítem(s) más...
            </a>`;
    }

    html += '</ul>';
    return html;
}

function toggleTextoBoton(btn, cantidadOcultos) {
    setTimeout(() => {
        const estaAbierto = btn.getAttribute('aria-expanded') === 'true';
        btn.innerHTML = estaAbierto 
            ? `<i class="fas fa-chevron-up me-1"></i>Ocultar ítems` 
            : `<i class="fas fa-chevron-down me-1"></i>Ver ${cantidadOcultos} ítem(s) más...`;
    }, 50);
}

function abrirModalReasignar(pv_id, venta_id, ticketFormateado, monto) {
    document.getElementById('reasignar_pv_id').value = pv_id;
    document.getElementById('reasignar_venta_id').value = venta_id;
    document.getElementById('info_ticket').innerHTML = `Ticket: <strong class="font-monospace text-primary">${ticketFormateado}</strong> — Monto: <strong class="text-success">$${monto}</strong>`;
    
    var modal = new bootstrap.Modal(document.getElementById('modalReasignar'));
    modal.show();
}

function guardarReasignacion(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('formReasignar'));

    fetch(API_BASE + '/ctacte/compras/reasignar_compra.php', {
        method: 'POST',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        },
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'ok') {
            const modalEl = document.getElementById('modalReasignar');
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
            if (modalInstance) {
                modalInstance.hide();
            }

            cargarFacturas();
            toast(res.msg || 'El comprobante fue reasignado correctamente.', 'success');
        } else {
            toast(res.msg || 'No se pudo reasignar el comprobante.', 'error');
        }
    })
    .catch(err => {
        console.error("Error en reasignación:", err);
        toast('Error de conexión con el servidor.', 'error');
    });
}

function filtrarPorTicketEnDataTables() {
    const term = document.getElementById('filtro_ticket').value.trim();
    if (tablaData) {
        tablaData.search(term).draw();
    }
}