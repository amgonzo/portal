// Variable global para controlar la instancia del modal activo de forma segura
let modalTicketInstancia = null;

$(document).ready(function() {
    // 0. VALIDACIÓN DE SEGURIDAD SSO INICIAL
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    // Verificar token y permisos contra la API central del SSO
    $.ajax({
        url: API_BASE + "/sso/auth/me.php",
        type: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === 'ok' && res.usuario) {
                // Ocultar elementos si no tienen el permiso correspondiente
                const permisos = res.permisos || [];
                if (!permisos.includes('cajas_sincronizar')) {
                    $('[data-permiso="cajas_sincronizar"]').hide();
                }
            } else {
                localStorage.clear();
                window.location.href = 'index.php';
            }
        },
        error: function(xhr) {
            if (xhr.status === 401 || xhr.status === 0) {
                localStorage.clear();
                window.location.href = 'index.php';
            }
        }
    });

    if (document.getElementById("tabla-consumos-body")) {
        cargarDatosDashboard();
    }
    
    // 1. Carga inicial de datos
    if (document.getElementById("tabla-consumos-body")) {
        cargarDatosDashboard();
    }

    // 2. Evento de click para sincronizar (CORREGIDO: Incluye el token Bearer)
    $('#btnSincronizar').on('click', function() {
        const $btn = $(this);
        const originalHTML = $btn.html();

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Sincronizando...');

        $.ajax({
            url: API_BASE + '/ctacte/compras/sincronizar_compras.php',
            type: 'POST',
            dataType: 'json',
            headers: { 
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '') 
            },
            success: function(response) {
                if (response.status === 'ok') {
                    toast(response.msg, 'success');
                    cargarDatosDashboard();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Sincronización',
                        text: response.msg || 'No se pudo completar el proceso.',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: '#0d6efd'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error("Error AJAX Sincronizar:", xhr.responseText); 
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Red o Servidor',
                    text: 'El servidor devolvió una respuesta no válida (posible error 404 o 500).',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#dc3545'
                });
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHTML);
            }
        });
    });

    // 3. Intercepta el click en CUALQUIER botón de cerrar del modal
    $(document).on('click', '[data-bs-dismiss="modal"]', function() {
        if (modalTicketInstancia) {
            modalTicketInstancia.hide();
        }
    });
});

// Función principal para traer y renderizar dinámicamente las métricas y alertas
function cargarDatosDashboard() {
    fetch(API_BASE + "/ctacte/compras/obtener_datos_dashboard.php", {
        type: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("El servidor devolvió HTML en vez de JSON:", text);
            throw new Error("La API no devolvió un JSON válido. Comprobá la ruta.");
        }
    })
    .then(res => {
        if (res.status === "ok") {
            const d = res.data;

            // -------------------------------------------------------------
            // 1. Tarjetas Superiores
            // -------------------------------------------------------------
            if (document.getElementById("fecha-ultima-carga")) {
                document.getElementById("fecha-ultima-carga").textContent = d.ultima_sincronizacion;
            }
            if (document.getElementById("card-consumo-total")) {
                document.getElementById("card-consumo-total").textContent = formatearMoneda(d.consumo_total_mes);
            }
            if (document.getElementById("card-alertas-limite")) {
                document.getElementById("card-alertas-limite").innerHTML = `${d.alertas_limite_count} <span class="fs-6 fw-normal text-muted">asociados</span>`;
            }
            if (document.getElementById("card-empleados-uso")) {
                document.getElementById("card-empleados-uso").innerHTML = `${d.empleados_activos} <span class="fs-5 text-muted fw-normal">/ ${d.total_empleados}</span>`;
            }
            if (document.getElementById("progreso-empleados")) {
                const porcentajeEmpleados = d.total_empleados > 0 ? (d.empleados_activos / d.total_empleados) * 100 : 0;
                document.getElementById("progreso-empleados").style.width = `${porcentajeEmpleados}%`;
            }

            // -------------------------------------------------------------
            // 2. Tabla de Últimos Consumos
            // -------------------------------------------------------------
            const tablaBody = document.getElementById("tabla-consumos-body");
            if (tablaBody) {
                tablaBody.innerHTML = "";

                if (d.ultimos_consumos && d.ultimos_consumos.length > 0) {
                    d.ultimos_consumos.forEach(c => {
                        tablaBody.innerHTML += `
                            <tr>
                                <td class="fw-semibold text-primary">${c.id_compra}</td>
                                <td>${c.empleado}</td>
                                <td class="text-muted">${c.dni}</td>
                                <td><small>${c.fecha}</small></td>
                                <td class="text-end fw-bold text-dark">${formatearMoneda(c.monto)}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Ver detalle" onclick="verDetalleTicket('${c.id_url}')">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    tablaBody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No se registraron consumos recientemente.</td></tr>`;
                }
            }

            // -------------------------------------------------------------
            // 3. Panel de Riesgo de Límite
            // -------------------------------------------------------------
            const contenedorAlertas = document.getElementById("contenedor-alertas-lista");
            if (contenedorAlertas) {
                contenedorAlertas.innerHTML = "";

                if (d.alertas_lista && d.alertas_lista.length > 0) {
                    d.alertas_lista.forEach(a => {
                        let htmlBarrasVerticales = "";
                        if (Array.isArray(a.historial_meses) && a.historial_meses.length > 0) {
                            htmlBarrasVerticales = a.historial_meses.map(h => {
                                let tresLetras = h.mes.substring(0, 3).toUpperCase();
                                let colorBarra = h.porc >= 85 ? 'bg-danger' : 'bg-primary';
                                let altoBarra = Math.max(Math.min(h.porc, 100), 10);

                                return `
                                    <div class="d-flex flex-column align-items-center" style="width: 22px;" title="${h.mes}: ${formatearMoneda(h.consumido, 0)} (${h.porc}%)">
                                        <div class="w-100 bg-body-tertiary rounded-top d-flex align-items-end" style="height: 38px; padding: 2px;">
                                            <div class="w-100 ${colorBarra} rounded-top" style="height: ${altoBarra}%;"></div>
                                        </div>
                                        <span class="text-muted fw-bold mt-1" style="font-size: 0.62rem; line-height: 1;">${tresLetras}</span>
                                    </div>
                                `;
                            }).join('');
                        }

                        contenedorAlertas.innerHTML += `
                            <div class="p-3 bg-body-tertiary rounded-3 border-start border-danger border-3 mb-2 shadow-sm">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div style="flex: 1; padding-right: 10px;">
                                        <div class="mb-1">
                                            <span class="badge bg-dark me-1" style="font-size: 0.70rem;">Leg. ${a.legajo}</span>
                                            <span class="badge bg-danger text-white" style="font-size: 0.70rem;">${a.porc}% Uso</span>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.88rem;">${a.nombre}</h6>
                                        <div class="text-muted small" style="font-size: 0.75rem;">
                                            Consumido: <strong class="text-dark">${formatearMoneda(a.consumido, 0)}</strong>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-end gap-1 ps-2 border-start">
                                        ${htmlBarrasVerticales}
                                    </div>
                                </div>
                                <div class="progress mt-2" style="height: 5px;">
                                    <div class="progress-bar bg-danger" style="width: ${Math.min(a.porc, 100)}%"></div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    contenedorAlertas.innerHTML = `<p class="text-center text-muted small py-3 mb-0">No hay asociados con riesgo de límite de cupo.</p>`;
                }
            }

        } else {
            console.error("Error al cargar métricas del Dashboard:", res.msg);
        }
    })
    .catch(error => console.error("Error de conexión:", error));
}

// Función del Modal de Tickets
function verDetalleTicket(idTicket) {
    document.getElementById('modal-empleado').innerText = "Cargando...";
    document.getElementById('modal-dni').innerText = "DNI: ...";
    document.getElementById('modal-id').innerText = "...";
    document.getElementById('modal-fecha').innerText = "...";
    document.getElementById('modal-total').innerText = "$0,00";
    document.getElementById('modal-tabla-productos').innerHTML = `<tr><td colspan="4" class="text-center text-muted py-3">Buscando renglones del ticket...</td></tr>`;

    const modalElement = document.getElementById('modalTicket');
    if (!modalTicketInstancia) {
        modalTicketInstancia = new bootstrap.Modal(modalElement);
    }
    modalTicketInstancia.show();

    fetch(API_BASE + `/ctacte/compras/obtener_detalle_ticket.php?id=${idTicket}`, {
        type: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Error de parseo en detalle ticket:", text);
            throw new Error("Respuesta inválida del servidor");
        }
    })
    .then(res => {
        if (res.status === "ok") {
            const d = res.data;

            document.getElementById('modal-empleado').innerText = d.empleado;
            document.getElementById('modal-dni').innerText = "DNI: " + d.dni;
            document.getElementById('modal-id').innerText = d.ticket;
            document.getElementById('modal-fecha').innerText = d.fecha;
            document.getElementById('modal-total').innerText = formatearMoneda(d.total);

            const tabla = document.getElementById('modal-tabla-productos');
            tabla.innerHTML = "";

            if (d.items && d.items.length > 0) {
                d.items.forEach(p => {
                    let subtotal = p.importe; 
                    let precioUnitario = p.cantidad > 0 ? (p.importe / p.cantidad) : 0;

                    let fila = `<tr>
                        <td class="fw-semibold text-dark">${p.descripcion}</td>
                        <td class="text-center fw-bold">${p.cantidad}</td>
                        <td class="text-end text-muted">${formatearMoneda(precioUnitario)}</td>
                        <td class="text-end fw-bold text-dark">${formatearMoneda(subtotal)}</td>
                    </tr>`;
                    
                    tabla.innerHTML += fila;
                });
            } else {
                tabla.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No hay detalles de productos para este ticket.</td></tr>`;
            }

        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error de Lectura',
                text: res.msg || 'No se pudo cargar el detalle del ticket.',
                confirmButtonText: 'Cerrar'
            });
            modalTicketInstancia.hide();
        }
    })
    .catch(error => {
        console.error("Error al traer detalle:", error);
        Swal.fire({
            icon: 'error',
            title: 'Error de Red',
            text: 'Hubo un inconveniente al consultar con el servidor centralizado.',
            confirmButtonText: 'Aceptar'
        });
        modalTicketInstancia.hide();
    });
}

// Formateador de moneda
function formatearMoneda(valor, decimales = 2) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(valor);
}

$(document).ready(function() {
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }
    
    // Opcional: Mostrar el nombre del usuario logueado en el menú de arriba dinámicamente
    const usuario = JSON.parse(localStorage.getItem('usuario_actual') || '{}');
    if (usuario.nombreapellido || usuario.username) {
        $('#nombre-usuario-ui').text(usuario.nombreapellido || usuario.username);
    }
});

function logout() {
    localStorage.removeItem('sso_token');
    localStorage.removeItem('usuario_actual');
    localStorage.removeItem('sso_app_activa');
    window.location.href = 'index.php';
}