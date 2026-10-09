
$(document).ready(function () {
    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    // Validación SSO y permisos
    $.ajax({
        url: API_BASE + "/sso/auth/me.php",
        type: "GET",
        headers: obtenerHeadersSSO(),
        success: function (response) {
            const res = typeof response === "string" ? JSON.parse(response) : response;

            if (res.status === "ok" && res.usuario) {
                const permisos = res.permisos || [];

                if (!permisos.includes("cajas_sincronizar")) {
                    $('[data-permiso="cajas_sincronizar"]').hide();
                }

                inicializarDashboard();
            } else {
                localStorage.removeItem("sso_token");
                window.location.href = window.SSO_LOGIN_URL;
            }
        },
        error: function (xhr) {
            if (xhr.status === 401 || xhr.status === 403) {
                localStorage.removeItem("sso_token");
                window.location.href = window.SSO_LOGIN_URL;
            } else {
                mostrarError("No se pudo validar la sesión SSO.");
            }
        }
    });
});

function inicializarDashboard() {
    establecerFechasIniciales();
    cargarDatosDashboard();

    $("#btnConsultar").on("click", function () {
        cargarDatosDashboard();
    });

    $("#btnMesActual").on("click", function () {
        establecerFechasIniciales();
        cargarDatosDashboard();
    });

    $("#btnSincronizar").on("click", function () {
        sincronizarVentas();
    });
}

function establecerFechasIniciales() {
    const ahora = new Date();
    const primerDia = new Date(ahora.getFullYear(), ahora.getMonth(), 1);

    $("#fecha_desde").val(formatearFechaInput(primerDia));
    $("#fecha_hasta").val(formatearFechaInput(ahora));
}

function formatearFechaInput(fecha) {
    const anio = fecha.getFullYear();
    const mes = String(fecha.getMonth() + 1).padStart(2, "0");
    const dia = String(fecha.getDate()).padStart(2, "0");

    return `${anio}-${mes}-${dia}`;
}

function cargarDatosDashboard() {
    const desde = $("#fecha_desde").val();
    const hasta = $("#fecha_hasta").val();

    if (!desde || !hasta) {
        mostrarError("Seleccioná las fechas del período.");
        return;
    }

    if (desde > hasta) {
        mostrarError("La fecha inicial no puede ser posterior a la fecha final.");
        return;
    }

    $("#btnConsultar").prop("disabled", true);

    $.ajax({
        url: API_BASE + "/dashboard/ventas/obtener_datos_dashboard.php",
        type: "GET",
        dataType: "json",
        data: {
            fecha_desde: desde,
            fecha_hasta: hasta
        },
        headers: obtenerHeadersSSO(),

        success: function (res) {
            if (res.status !== "ok") {
                mostrarError(res.msg || "No se pudieron obtener las ventas.");
                return;
            }

            renderizarDashboard(res.data || {});
        },

        error: function (xhr) {
            console.error("Error al consultar dashboard:", xhr.responseText);
            mostrarError("No se pudieron consultar los datos del dashboard.");
        },

        complete: function () {
            $("#btnConsultar").prop("disabled", false);
        }
    });
}

function renderizarDashboard(d) {
    const resumen = d.resumen || {};
    const dias = Array.isArray(d.dias) ? d.dias : [];

    $("#card-recaudacion").text(formatearMoneda(resumen.recaudacion));
    $("#card-costo").text(formatearMoneda(resumen.costo));
    $("#card-diferencia").text(formatearMoneda(resumen.diferencia));
    $("#card-marcacion").text(formatearPorcentaje(resumen.marcacion));
    $("#card-tickets").text(formatearNumero(resumen.q_tk, 0));
    $("#card-ticket-promedio").text(formatearMoneda(resumen.tk_prom));
    $("#card-unidades").text(formatearNumero(resumen.cant_prod, 2));
    $("#card-porcentaje-venta").text(formatearPorcentaje(resumen.porcentaje_s_vta));

    $("#fecha-ultima-carga").text(d.ultima_sincronizacion || "Sin sincronizaciones");
    $("#estado-ultima-carga").text(d.estado_sincronizacion || "Sin información");
    $("#cantidad-dias").text(`${dias.length} días`);

    renderizarTablaDiaria(dias, resumen);
}

function renderizarTablaDiaria(dias, resumen) {
    const tbody = $("#tabla-ventas-body");
    const footer = $("#tabla-ventas-footer");

    tbody.empty();
    footer.empty();

    if (!dias.length) {
        tbody.html(`
            <tr>
                <td colspan="12" class="text-center py-4 text-muted">
                    No hay ventas para el período seleccionado.
                </td>
            </tr>
        `);

        footer.html(`
            <tr>
                <td colspan="12" class="text-center text-muted py-2">
                    Sin datos para mostrar
                </td>
            </tr>
        `);

        return;
    }

    dias.forEach(function (dia) {
        tbody.append(`
            <tr>
                <td>${escaparHTML(dia.fecha)}</td>
                <td>${escaparHTML(dia.dia || "")}</td>
                <td class="text-end">${formatearMoneda(dia.recaudacion)}</td>
                <td class="text-end">${formatearMoneda(dia.costo)}</td>
                <td class="text-end fw-semibold">${formatearMoneda(dia.diferencia)}</td>
                <td class="text-end">${formatearPorcentaje(dia.marcacion)}</td>
                <td class="text-end">${formatearNumero(dia.q_tk, 0)}</td>
                <td class="text-end">${formatearMoneda(dia.tk_prom)}</td>
                <td class="text-end">${formatearNumero(dia.cant_prod, 2)}</td>
                <td class="text-end">${formatearMoneda(dia.prod_prom)}</td>
                <td class="text-end">${formatearNumero(dia.prod_x_tk, 2)}</td>
                <td class="text-end">${formatearPorcentaje(dia.porcentaje_s_vta)}</td>
            </tr>
        `);
    });

    footer.html(`
        <tr>
            <td colspan="2">Total del período</td>
            <td class="text-end">${formatearMoneda(resumen.recaudacion)}</td>
            <td class="text-end">${formatearMoneda(resumen.costo)}</td>
            <td class="text-end">${formatearMoneda(resumen.diferencia)}</td>
            <td class="text-end">${formatearPorcentaje(resumen.marcacion)}</td>
            <td class="text-end">${formatearNumero(resumen.q_tk, 0)}</td>
            <td class="text-end">${formatearMoneda(resumen.tk_prom)}</td>
            <td class="text-end">${formatearNumero(resumen.cant_prod, 2)}</td>
            <td class="text-end">${formatearMoneda(resumen.prod_prom)}</td>
            <td class="text-end">${formatearNumero(resumen.prod_x_tk, 2)}</td>
            <td class="text-end">${formatearPorcentaje(resumen.porcentaje_s_vta)}</td>
        </tr>
    `);
}

function sincronizarVentas() {
    const $btn = $("#btnSincronizar");
    const htmlOriginal = $btn.html();

    $btn.prop("disabled", true).html(`
        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
        Sincronizando...
    `);

    $.ajax({
        url: API_BASE + "/dashboard/ventas/sincronizar_ventas.php",
        type: "POST",
        dataType: "json",
        headers: obtenerHeadersSSO(),

        success: function (res) {
            if (res.status === "ok") {
                if (typeof toast === "function") {
                    toast(res.msg || "Sincronización completada.", "success");
                }

                cargarDatosDashboard();
            } else {
                mostrarError(res.msg || "No se pudo completar la sincronización.");
            }
        },

        error: function (xhr) {
            console.error("Error de sincronización:", xhr.responseText);
            mostrarError("Error de conexión o del servidor al sincronizar las ventas.");
        },

        complete: function () {
            $btn.prop("disabled", false).html(htmlOriginal);
        }
    });
}

function formatearMoneda(valor) {
    return new Intl.NumberFormat("es-AR", {
        style: "currency",
        currency: "ARS",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(Number(valor) || 0);
}

function formatearNumero(valor, decimales = 2) {
    return new Intl.NumberFormat("es-AR", {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales
    }).format(Number(valor) || 0);
}

function formatearPorcentaje(valor) {
    return `${formatearNumero(valor, 2)}%`;
}

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, function (caracter) {
        return {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        }[caracter];
    });
}

function mostrarError(mensaje) {
    if (typeof Swal !== "undefined") {
        Swal.fire({
            icon: "error",
            title: "Dashboard de ventas",
            text: mensaje,
            confirmButtonText: "Aceptar",
            confirmButtonColor: "#0d6efd"
        });
    } else {
        alert(mensaje);
    }
}