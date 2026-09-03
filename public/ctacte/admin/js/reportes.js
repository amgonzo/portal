$(document).ready(function() {
    cargarReportes();
    cargarCategoriasFiltro();
});

// Función auxiliar para inyectar el token en las llamadas AJAX tal como en usuarios
function getAuthHeaders() {
    const token = localStorage.getItem('sso_token');
    return token ? { 'Authorization': 'Bearer ' + token } : {};
}

function cargarReportes() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/ctacte/reportes/listar_reportes.php",
        headers: getAuthHeaders(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if(respuesta.status === "ok") {
                let html = "";
                respuesta.data.forEach(r => {
                    let badgeClass = 'badge-primary';
                    if(r.categoria === 'Cuentas') badgeClass = 'badge-info';
                    if(r.categoria === 'Ctacte') badgeClass = 'badge-success';

                    html += `<tr>
                        <td><span class="badge ${badgeClass}">${r.categoria}</span></td>
                        <td><b>${r.nombre}</b></td>
                        <td>${r.descripcion}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info" onclick="abrirModalReporte('${r.id}', '${r.nombre}')">
                                <i class="fas fa-cog"></i> Configurar
                            </button>
                        </td>
                    </tr>`;
                });
                $("#listaReportes").html(html);
            }
        },
        error: function(xhr) {
            if (xhr.status === 401 || xhr.status === 403) {
                Swal.fire('Sesión expirada', 'Por favor, volvé a iniciar sesión.', 'error').then(() => {
                    window.location.href = '/login.php';
                });
            }
        }
    });
}

// Función encargada de cargar las personas respetando el switch de inactivos
function cargarPersonasReporte(incluirInactivos = false) {
    const url = API_BASE + `/ctacte/personas/obtener_personas_combo.php${incluirInactivos ? '?todos=1' : ''}`;

    $.ajax({
        type: "GET",
        url: url,
        headers: getAuthHeaders(),
        success: function(res) {
            const personas = (typeof res === 'string') ? JSON.parse(res) : res;
            let options = '<option value="">-- Seleccionar Persona --</option>';
            if (Array.isArray(personas)) {
                personas.forEach(p => {
                    const labelInactivo = Number(p.activo) === 0 ? ' (INACTIVO)' : '';
                    options += `<option value="${p.dni}">${p.apellido}, ${p.nombre}${labelInactivo} (DNI: ${p.dni})</option>`;
                });
            }
            $("#rep_categoria").html(options);
        }
    });
}

function abrirModalReporte(tipo, nombre) {
    $("#reporte_tipo").val(tipo);
    $("#tituloModalReporte").text(nombre);

    // RESET: Mostramos fechas por defecto y ocultamos el resto
    $("#div_fechas").show();
    $("#div_mes_anio").hide();
    $("#div_categoria").hide();
    $("#div_switch_inactivos").hide();
    $("#chk_inactivos_rep").prop('checked', false);

    // 1. Caso Resumen Mensual: Muestra Mes y Año
    if (tipo === 'resumen_mensual') {
        $("#div_fechas").hide();
        $("#div_mes_anio").show();

        const hoy = new Date();
        $("#rep_mes").val(hoy.getMonth() + 1);
        $("#rep_anio").val(hoy.getFullYear());

    // 2. Estado de Cuenta
    } else if (tipo === 'estado_cuenta') {
        $("#div_fechas").hide();
        $("#div_mes_anio").show();
        $("#div_categoria").show();
        $("#lbl_categoria").text('Seleccionar Persona');
        $("#div_switch_inactivos").show();

        cargarPersonasReporte(false);

        const hoy = new Date();
        $("#rep_mes").val(hoy.getMonth() + 1);
        $("#rep_anio").val(hoy.getFullYear());

    } else if (tipo === 'recibo_pago') {
        $("#div_fechas").hide();
        $("#div_mes_anio").show();
        $("#div_categoria").show();
        $("#lbl_categoria").text('Seleccionar Persona');
        $("#div_switch_inactivos").show();

        // Cargar selector de personas (inicia en false)
        cargarPersonasReporte(false);

        const hoy = new Date();
        $("#rep_mes").val(hoy.getMonth() + 1);
        $("#rep_anio").val(hoy.getFullYear());

    // 3. Padrón por Categorías
    } else if (tipo === 'padron_categorias') {
        $("#div_fechas").hide();
        $("#div_categoria").show();
        $("#lbl_categoria").text('Filtrar por Categoría');
        cargarCategoriasFiltro(); 

    // 4. Consumo Quincenal de Artículos (Requiere persona y mes/año)
    } else if (tipo === 'consumo_quincenal_articulos') {
        $("#div_fechas").hide();
        $("#div_mes_anio").show();
        $("#div_categoria").show();
        $("#lbl_categoria").text('Seleccionar Persona');
        $("#div_switch_inactivos").show();

        cargarPersonasReporte(false);

        const hoy = new Date();
        $("#rep_mes").val(hoy.getMonth() + 1);
        $("#rep_anio").val(hoy.getFullYear());
    
    // 5. Consumo por Rango DNI (Solo mes y año)
    } else if (tipo === 'consumo_por_rango_dni') {
        $("#div_fechas").hide();
        $("#div_mes_anio").show();
        $("#div_categoria").hide(); 
        $("#div_switch_inactivos").hide();

        const hoy = new Date();
        $("#rep_mes").val(hoy.getMonth() + 1);
        $("#rep_anio").val(hoy.getFullYear());
    }

    $("#ModalReporte").modal("show");
}

function cargarCategoriasFiltro() {
    $.ajax({
        type: "GET",
        url: API_BASE + '/ctacte/categorias/categorias.php?action=listar',
        headers: getAuthHeaders(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if (respuesta.status === "ok") {
                let options = '<option value="TODAS">Todas las categorías</option>';
                respuesta.data.forEach(cat => {
                    options += `<option value="${cat.nombrecategoria}">${cat.nombrecategoria}</option>`;
                });
                $("#rep_categoria").html(options);
            }
        }
    });
}

function generarPDF() {
    const tipo = $("#reporte_tipo").val();
    const urlParams = new URLSearchParams();
    urlParams.append('tipo', tipo);

    // Mapeo ordenado y limpio de parámetros según el tipo de reporte
    if (tipo === 'resumen_mensual' || tipo === 'consumo_por_rango_dni') {
        urlParams.append('mes', $("#rep_mes").val());
        urlParams.append('anio', $("#rep_anio").val());

    } else if (tipo === 'estado_cuenta' || tipo === 'consumo_quincenal_articulos' || tipo === 'recibo_pago') {
        const dni = $("#rep_categoria").val();
        if (!dni) {
            Swal.fire('Atención', 'Debe seleccionar una persona', 'warning');
            return;
        }
        urlParams.append('dni', dni);
        urlParams.append('mes', $("#rep_mes").val());
        urlParams.append('anio', $("#rep_anio").val());

    } else if (tipo === 'aptos_por_medico' || tipo === 'padron_categorias') {
        urlParams.append('cat', $("#rep_categoria").val());

    } else {
        // Por defecto para reportes basados en rangos de fechas (Desde / Hasta)
        urlParams.append('desde', $("#rep_desde").val());
        urlParams.append('hasta', $("#rep_hasta").val());
    }

    // Pasamos también el token por parámetro para que el window.open del PDF no pierda la sesión
    const token = localStorage.getItem('sso_token');
    if (token) {
        urlParams.append('token', token);
    }

    const queryParams = urlParams.toString();

    const loadingToast = Swal.fire({
        title: 'Buscando datos...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        type: "GET",
        url: API_BASE + `/ctacte/reportes/generar_reporte.php?${queryParams}`,
        headers: getAuthHeaders(),
        dataType: "json",
        success: function(res) {
            loadingToast.close(); 

            if (res.status === "empty") {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin resultados',
                    text: 'No se encontraron registros para el período seleccionado.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'Entendido'
                });
            } else if (res.status === "ok") {
                const urlPdf = API_BASE + `/ctacte/reportes/ver_pdf_reporte.php?${queryParams}`;
                window.open(urlPdf, '_blank');
            }
        },
        error: function(xhr, status, error) {
            loadingToast.close();
            console.error("Respuesta cruda del servidor:", xhr.responseText);

            if (xhr.status === 401 || xhr.status === 403) {
                Swal.fire('Sesión expirada', 'Por favor, volvé a iniciar sesión.', 'error').then(() => {
                    window.location.href = '/login.php';
                });
            } else {
                Swal.fire('Error del servidor', 'Revisá la consola (F12) para ver el detalle técnico.', 'error');
            }
        }
    });
}