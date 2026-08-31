let tablaLimites = null;

$(document).ready(function() {
    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = '../index.php';
        return;
    }

    initDataTable();
    cargarTablaLimites();

    // Evento de envío del formulario de edición (Migrado a fetch)
    $('#formLimite').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new URLSearchParams(new FormData(this));

        fetch(API_BASE + '/ctacte/empleados/guardar_limite.php', {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || ''),
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: formData.toString()
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Respuesta HTML en guardar_limite:", text);
                throw new Error("El servidor devolvió una respuesta no válida.");
            }
        })
        .then(response => {
            if (response.status === 'ok') {
                toast(response.msg, 'success');
                $('#ModalLimite').modal('hide');
                cargarTablaLimites();
            } else {
                toast(response.msg || "Error al guardar los cambios", "error");
            }
        })
        .catch(error => {
            console.error("Error al guardar límite:", error);
            toast("Error al guardar los cambios", "error");
        });
    });
});

// Helper para dibujar minibarras CSS de los últimos meses
function generarMinigrafico(historico, limiteMensual) {
    if (!historico || !Array.isArray(historico) || historico.length === 0) {
        return '<small class="text-muted">-</small>';
    }

    // Buscamos el valor máximo para escalar la altura de las barras (%)
    const maxVal = Math.max(...historico, limiteMensual || 1);

    let html = '<div class="d-flex align-items-end justify-content-center" style="height: 28px; gap: 4px;" title="Histórico de consumos recientes">';
    
    historico.forEach((monto, index) => {
        const esUltimo = index === (historico.length - 1);
        const porcentajeAlto = Math.min(Math.round((monto / maxVal) * 100), 100);
        
        // Color: Gris para meses viejos; Verde/Rojo para el mes actual según consumo
        let colorClass = 'bg-secondary';
        if (esUltimo) {
            colorClass = (monto >= limiteMensual * 0.85) ? 'bg-danger' : 'bg-success';
        }

        const montoFormateado = formatearMoneda(monto);

        html += `
            <div class="${colorClass} rounded-top" 
                 style="width: 7px; height: ${Math.max(porcentajeAlto, 12)}%; transition: height 0.3s;" 
                 data-bs-toggle="tooltip" 
                 data-placement="top"
                 title="Mes -${historico.length - index - 1}: ${montoFormateado}">
            </div>`;
    });

    html += '</div>';
    return html;
}

function initDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaLimites')) {
        $('#tablaLimites').DataTable().destroy();
    }

    tablaLimites = $('#tablaLimites').DataTable({
        language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron asociados",
            "sEmptyTable":     "Sin datos de límites para el período seleccionado",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sSearch":         "Buscar:",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst":    "Primero",
                "sLast":     "Último",
                "sNext":     "Siguiente",
                "sPrevious": "Anterior"
            }
        },
        responsive: true,
        autoWidth: false
    });
}

function cargarTablaLimites() {
    let mes = $('#filtroMes').val();
    let anio = $('#filtroAnio').val();

    // Petición migrada a fetch con control de respuestas HTML/JSON igual al dashboard
    fetch(API_BASE + `/ctacte/empleados/obtener_limites.php?mes=${mes}&anio=${anio}`, {
        method: 'GET',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        }
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("El servidor devolvió HTML en vez de JSON en obtener_limites:", text);
            throw new Error("La API no devolvió un JSON válido.");
        }
    })
    .then(res => {
        if (res.status === 'ok') {
            
            // Control del botón de cerrar según estado
            if (res.is_cerrado) {
                $('#btnCerrarMes').prop('disabled', true)
                                  .removeClass('btn-danger')
                                  .addClass('btn-secondary')
                                  .html('<i class="fas fa-lock"></i> Período Cerrado');
            } else {
                $('#btnCerrarMes').prop('disabled', false)
                                  .removeClass('btn-secondary')
                                  .addClass('btn-danger')
                                  .html('<i class="fas fa-lock"></i> Cerrar Período');
            }

            tablaLimites.clear();

            res.data.forEach(function(emp) {
                let estado = emp.activo == 1 
                    ? '<span class="badge bg-success">Habilitado</span>' 
                    : '<span class="badge badge-danger">Inhabilitado</span>';

                let colorSaldo = emp.saldo_disponible <= 50000 ? 'text-danger font-weight-bold' : 'text-success';

                let botonAccion = '';
                if (!res.is_cerrado) {
                    botonAccion = `
                        <button class="btn btn-sm btn-warning text-white py-0 px-2" 
                        title="Editar Cupo" 
                        onclick="abrirEditar('${emp.dni}', '${emp.nombre.replace(/'/g, "\\'")}', ${emp.limite_mensual}, ${emp.activo})">
                            <i class="fas fa-edit"></i>
                        </button>
                    `;
                } else {
                    botonAccion = `<span class="text-muted small"><i class="fas fa-lock"></i> Historial</span>`;
                }

                // Generar gráfico de barras para la nueva columna
                let graficoHtml = generarMinigrafico(emp.historico_consumos || [emp.consumido_mes_actual], emp.limite_mensual);

                tablaLimites.row.add([
                    `<strong>${emp.dni}</strong>`,
                    `<span class="font-weight-bold">${emp.nombre}</span>`,
                    formatearMoneda(emp.limite_mensual),
                    `<span class="text-secondary">${formatearMoneda(emp.consumido_mes_actual)}</span>`,
                    `<span class="${colorSaldo}">${formatearMoneda(emp.saldo_disponible)}</span>`,
                    graficoHtml, // 📊 Minibarras
                    `<div class="text-center">${estado}</div>`,
                    `<div class="text-center">${botonAccion}</div>`
                ]);
            });

            tablaLimites.draw();
            
            // Activa Tooltips de Bootstrap para ver los montos al pasar el mouse por la barrita
            if ($.fn.tooltip) {
                $('[data-bs-toggle="tooltip"]').tooltip();
            }

        } else {
            console.error("Error al cargar la tabla de límites:", res.msg);
            if (typeof toast === 'function') {
                toast(res.msg || "Error al cargar la tabla", "error");
            }
        }
    })
    .catch(error => {
        console.error("Error de conexión al obtener los datos:", error);
        if (typeof toast === 'function') {
            toast("Error de conexión al obtener los datos", "error");
        }
    });
}

function abrirEditar(dni, nombre, limite, activo) {
    $('#edit_dni').val(dni);
    $('#edit_nombre').val(nombre);
    $('#edit_monto_limite').val(limite);
    $('#edit_activo').prop('checked', activo == 1);
    
    $('#edit_mes').val($('#filtroMes').val());
    $('#edit_anio').val($('#filtroAnio').val());
    
    $('#ModalLimite').modal('show');
}

function confirmarCierreMes() {
    let mesTexto = $('#filtroMes option:selected').text();
    let mesNum = $('#filtroMes').val();
    let anio = $('#filtroAnio').val();

    Swal.fire({
        title: `¿Cerrar período ${mesTexto} ${anio}?`,
        text: "Esta acción bloqueará de forma definitiva las modificaciones de cupos y congelará el historial de consumos para este mes.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, cerrar período',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.value || result.isConfirmed) { 
            const formData = new URLSearchParams();
            formData.append('mes', mesNum);
            formData.append('anio', anio);

            fetch(API_BASE + '/ctacte/empleados/cerrar_periodo.php', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + (localStorage.getItem('sso_token') || ''),
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: formData.toString()
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error("Respuesta HTML en cerrar_periodo:", text);
                    throw new Error("Respuesta inválida del servidor.");
                }
            })
            .then(response => {
                if (response.status === 'ok') {
                    Swal.fire('¡Cerrado!', response.msg, 'success');
                    cargarTablaLimites();
                } else {
                    Swal.fire('Error', response.msg, 'error');
                }
            })
            .catch(error => {
                console.error("Error al cerrar el período:", error);
                if (typeof toast === 'function') {
                    toast("Error de conexión al cerrar el período", "error");
                }
            });
        }
    });
}

function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS'
    }).format(valor || 0);
}