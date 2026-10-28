// =========================================================
// HORAS COMPENSATORIAS
// =========================================================

let tablaHorasCompensatorias = null;
let tablaMovimientosHoras = null;
let tablaAutorizacionesHoras = null;

let empleadosHoras = [];
let movimientosHoras = [];
let lotesEmpleado = [];
let autorizacionesHoras = [];

let modalDetalleHoras = null;
let modalAutorizacionesHoras = null;

let empleadoDetalleActual = null;

const URL_HORAS =
    API_BASE + "/fichajes/compensacion/horas_compensatorias.php";

document.addEventListener("DOMContentLoaded", function () {

    const token = localStorage.getItem("sso_token");

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    if (typeof obtenerHeadersSSO !== "function") {
        toast("No están disponibles las funciones de conexión con SSO.", "error");
        return;
    }

    if (typeof $ === "undefined" || !$.fn.DataTable) {
        toast("No está disponible DataTables.", "error");
        return;
    }

    modalDetalleHoras = new bootstrap.Modal(
        document.getElementById("modalDetalleHoras")
    );

    modalAutorizacionesHoras = new bootstrap.Modal(
        document.getElementById("modalAutorizacionesHoras")
    );

    inicializarTablasHoras();
    registrarEventosHoras();
    cargarHorasCompensatorias();
});

// =========================================================
// TABLAS
// =========================================================

function inicializarTablasHoras() {

    tablaHorasCompensatorias = $("#tablaHorasCompensatorias").DataTable({
        language: {
            processing: "Procesando...",
            lengthMenu: "Mostrar _MENU_ registros",
            zeroRecords: "No se encontraron resultados",
            emptyTable: "Ningún dato disponible",
            info: "Mostrando _START_ a _END_ de _TOTAL_ empleados",
            infoEmpty: "No hay empleados",
            infoFiltered: "(filtrado de _MAX_ registros)",
            search: "Buscar:",
            loadingRecords: "Cargando...",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        },
        responsive: true,
        autoWidth: false,
        pageLength: 25,
        order: [[0, "asc"]],
        columnDefs: [
            { targets: [1, 2, 3, 4], className: "text-end" },
            { targets: [5, 6], className: "text-center" }
        ]
    });

    tablaMovimientosHoras = $("#tablaMovimientosHoras").DataTable({
        language: idiomaDataTableHoras(),
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        order: [[0, "desc"]]
    });

    tablaAutorizacionesHoras = $("#tablaAutorizacionesHoras").DataTable({
        language: idiomaDataTableHoras(),
        responsive: true,
        autoWidth: false,
        pageLength: 10,
        order: [[0, "desc"]]
    });
}

function idiomaDataTableHoras() {
    return {
        processing: "Procesando...",
        lengthMenu: "Mostrar _MENU_ registros",
        zeroRecords: "No se encontraron resultados",
        emptyTable: "Ningún dato disponible",
        info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
        infoEmpty: "No hay registros",
        infoFiltered: "(filtrado de _MAX_ registros)",
        search: "Buscar:",
        loadingRecords: "Cargando...",
        paginate: {
            first: "Primero",
            last: "Último",
            next: "Siguiente",
            previous: "Anterior"
        }
    };
}

// =========================================================
// PETICIÓN API
// =========================================================

async function solicitarHoras(accion, parametros = {}) {

    const url = new URL(URL_HORAS, window.location.origin);

    url.searchParams.set("accion", accion);

    Object.entries(parametros).forEach(([clave, valor]) => {
        if (valor !== null && valor !== undefined && valor !== "") {
            url.searchParams.set(clave, valor);
        }
    });

    const response = await fetch(url.toString(), {
        method: "GET",
        cache: "no-store",
        headers: obtenerHeadersSSO()
    });

    const texto = await response.text();

    let resultado;

    try {
        resultado = JSON.parse(texto);
    } catch (error) {
        console.error("Respuesta inválida de horas compensatorias:", texto);
        throw new Error("El servidor no devolvió JSON válido.");
    }

    if (!response.ok || resultado.status !== "ok") {
        throw new Error(
            resultado.msg ||
            resultado.mensaje ||
            "No se pudo consultar la información de horas compensatorias."
        );
    }

    return resultado;
}

/****************************************** */

async function procesarVencimientosHoras() {
    const confirmacion = await Swal.fire({
        icon: "warning",
        title: "¿Procesar vencimientos?",
        text: "Los saldos vencidos se marcarán como vencidos y dejarán de estar disponibles para compensación. Esta operación quedará registrada en el historial.",
        showCancelButton: true,
        confirmButtonText: "Procesar vencimientos",
        cancelButtonText: "Cancelar",
        confirmButtonColor: "#d97706"
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    const $boton = $("#btnProcesarVencimientos");
    $boton.prop("disabled", true);

    try {
        const url = new URL(URL_HORAS, window.location.origin);
        url.searchParams.set("accion", "procesar_vencimientos");

        const response = await fetch(url.toString(), {
            method: "POST",
            cache: "no-store",
            headers: obtenerHeadersSSO()
        });

        const texto = await response.text();
        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch (error) {
            console.error("Respuesta inválida al procesar vencimientos:", texto);
            throw new Error("El servidor no devolvió JSON válido.");
        }

        if (!response.ok || resultado.status !== "ok") {
            throw new Error(
                resultado.msg ||
                resultado.mensaje ||
                "No se pudieron procesar los vencimientos."
            );
        }

        const datos = resultado.data || {};

        await Swal.fire({
            icon: "success",
            title: "Vencimientos procesados",
            html: `
                <p>${escaparHTML(datos.mensaje || "Proceso completado correctamente.")}</p>
                <p class="mb-1">
                    <strong>Lotes procesados:</strong>
                    ${Number(datos.procesados || 0)}
                </p>
                <p class="mb-0">
                    <strong>Minutos vencidos:</strong>
                    ${Number(datos.minutos_vencidos || 0)}
                </p>
            `,
            confirmButtonText: "Aceptar"
        });

        await Promise.all([
            cargarResumenHoras(),
            cargarSaldosEmpleados(),
            cargarMovimientosHoras(),
            cargarPendientesVencimiento()
        ]);

    } catch (error) {
        console.error("Error al procesar vencimientos:", error);
        toast(error.message, "error");
    } finally {
        $boton.prop("disabled", false);
    }
}

// =========================================================
// CARGA GENERAL
// =========================================================

async function cargarHorasCompensatorias() {

    const btn = $("#btnActualizarHoras");
    const icono = $("#iconoActualizarHoras");

    btn.prop("disabled", true);
    icono.addClass("fa-spin");

    $("#estadoCargaHoras").removeClass("d-none");
    $("#contenedorTablaHoras").addClass("d-none");
    $("#sinDatosHoras").addClass("d-none");

    try {

        await Promise.all([
            cargarResumenHoras(),
            cargarSaldosEmpleados(),
            cargarMovimientosHoras(),
            cargarAutorizacionesHoras(),
            cargarPendientesVencimiento()
        ]);

    } catch (error) {

        console.error("Error al cargar horas compensatorias:", error);
        toast(error.message, "error");

    } finally {

        $("#estadoCargaHoras").addClass("d-none");
        btn.prop("disabled", false);
        icono.removeClass("fa-spin");
    }
}

// =========================================================
// RESUMEN
// =========================================================

async function cargarResumenHoras() {

    const resultado = await solicitarHoras("resumen");
    const datos = resultado.data || {};

    $("#totalEmpleadosConSaldo").text(
        Number(datos.empleados_con_saldo || 0)
    );

    $("#totalSaldoDisponible").text(
        formatearMinutos(datos.minutos_disponibles || 0)
    );

    $("#totalHorasGeneradas").text(
        formatearMinutos(datos.minutos_generados || 0)
    );

    $("#totalAutorizacionesPendientes").text(
        Number(datos.autorizaciones_pendientes || 0)
    );
}


async function cargarPendientesVencimiento() {
    const resultado = await solicitarHoras("pendientes_vencimiento");
    const datos = resultado.data || {};

    const cantidadLotes = Number(datos.cantidad_lotes || 0);

    $("#badgeVencimientosPendientes").text(cantidadLotes);

    $("#btnProcesarVencimientos").toggleClass(
        "d-none",
        cantidadLotes <= 0
    );
}

// =========================================================
// SALDOS POR EMPLEADO
// =========================================================

async function cargarSaldosEmpleados() {

    const resultado = await solicitarHoras("saldos");
    empleadosHoras = Array.isArray(resultado.data)
        ? resultado.data
        : [];

    renderizarSaldosEmpleados();
}

function renderizarSaldosEmpleados() {

    tablaHorasCompensatorias.clear();

    const busqueda = normalizarTexto(
        $("#filtroEmpleadoHoras").val()
    );

    const estadoFiltro = $("#filtroEstadoSaldo").val();
    const soloConSaldo = $("#filtroSoloConSaldo").is(":checked");

    const empleadosFiltrados = empleadosHoras.filter(e => {

        const nombre = normalizarTexto(
            `${e.apellido || ""} ${e.nombre || ""} ${e.documento || ""}`
        );

        const disponible = Number(e.minutos_disponibles || 0);
        const vencido = Number(e.minutos_vencidos || 0);

        if (busqueda && !nombre.includes(busqueda)) {
            return false;
        }

        if (soloConSaldo && disponible <= 0) {
            return false;
        }

        if (estadoFiltro === "disponible" && disponible <= 0) {
            return false;
        }

        if (estadoFiltro === "agotado" && disponible > 0) {
            return false;
        }

        if (estadoFiltro === "vencido" && vencido <= 0) {
            return false;
        }

        return true;
    });

    empleadosFiltrados.forEach(e => {

        const id = Number(e.idempleado);
        const disponible = Number(e.minutos_disponibles || 0);
        const vencido = Number(e.minutos_vencidos || 0);

        let estado = '<span class="badge bg-secondary">Sin saldo</span>';

        if (disponible > 0) {
            estado = '<span class="badge bg-success">Disponible</span>';
        } else if (vencido > 0) {
            estado = '<span class="badge bg-warning text-dark">Vencido</span>';
        }

        const botonDetalle = `
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                title="Ver detalle"
                onclick="verDetalleEmpleadoHoras(${id})">
                <i class="fas fa-eye"></i>
            </button>
        `;

        tablaHorasCompensatorias.row.add([
            escaparHTML(
                `${e.apellido || ""}, ${e.nombre || ""}`
            ),
            formatearMinutos(e.minutos_generados),
            formatearMinutos(e.minutos_utilizados),
            formatearMinutos(e.minutos_vencidos),
            `<strong class="${disponible > 0 ? "text-success" : ""}">
                ${formatearMinutos(disponible)}
            </strong>`,
            estado,
            botonDetalle
        ]);
    });

    tablaHorasCompensatorias.draw();

    $("#contenedorTablaHoras").toggleClass(
        "d-none",
        empleadosFiltrados.length === 0
    );

    $("#sinDatosHoras").toggleClass(
        "d-none",
        empleadosFiltrados.length !== 0
    );
}

// =========================================================
// DETALLE DEL EMPLEADO
// =========================================================

async function verDetalleEmpleadoHoras(idEmpleado) {

    try {

        empleadoDetalleActual = Number(idEmpleado);

        const resultado = await solicitarHoras("detalle", {
            idempleado: empleadoDetalleActual
        });

        const datos = resultado.data || {};
        const empleado = datos.empleado || {};
        const resumen = datos.resumen || {};

        $("#tituloModalDetalleHoras").text(
            "Detalle de horas compensatorias"
        );

        $("#subtituloModalDetalleHoras").text(
            `${empleado.apellido || ""}, ${empleado.nombre || ""}`
        );

        $("#detalleGenerado").text(
            formatearMinutos(resumen.minutos_generados)
        );

        $("#detalleUtilizado").text(
            formatearMinutos(resumen.minutos_utilizados)
        );

        $("#detalleVencido").text(
            formatearMinutos(resumen.minutos_vencidos)
        );

        $("#detalleDisponible").text(
            formatearMinutos(resumen.minutos_disponibles)
        );

        renderizarLotesEmpleado(datos.lotes || []);
        renderizarMovimientosEmpleado(datos.movimientos || []);

        modalDetalleHoras.show();

    } catch (error) {

        console.error("Error al cargar detalle del empleado:", error);
        toast(error.message, "error");
    }
}

function renderizarLotesEmpleado(lotes) {

    const tbody = $("#tbodyLotesEmpleado");
    tbody.empty();

    $("#sinLotesEmpleado").toggleClass("d-none", lotes.length !== 0);

    lotes.forEach(lote => {

        const estado = escaparHTML(lote.estado || "—");

        tbody.append(`
            <tr>
                <td>${escaparHTML(lote.fecha_origen || "—")}</td>
                <td>${escaparHTML(lote.tipo_tramo_origen || "—")}</td>
                <td class="text-end">
                    ${formatearMinutos(lote.minutos_generados)}
                </td>
                <td class="text-end">
                    ${formatearMinutos(lote.minutos_disponibles)}
                </td>
                <td>${escaparHTML(lote.fecha_vencimiento || "Sin vencimiento")}</td>
                <td>${badgeEstado(lote.estado)}</td>
            </tr>
        `);
    });
}

function renderizarMovimientosEmpleado(movimientos) {

    const tbody = $("#tbodyDetalleMovimientos");
    tbody.empty();

    $("#sinDetalleMovimientos").toggleClass(
        "d-none",
        movimientos.length !== 0
    );

    movimientos.forEach(m => {

        tbody.append(`
            <tr>
                <td>${escaparHTML(m.fecha_movimiento || "—")}</td>
                <td>${escaparHTML(m.tipo_movimiento || "—")}</td>
                <td class="text-end">${formatearMinutos(m.minutos)}</td>
                <td>${badgeEstado(m.estado)}</td>
                <td>${escaparHTML(m.observaciones || "—")}</td>
            </tr>
        `);
    });
}

// =========================================================
// MOVIMIENTOS GENERALES
// =========================================================

async function cargarMovimientosHoras() {

    const resultado = await solicitarHoras("movimientos");

    movimientosHoras = Array.isArray(resultado.data)
        ? resultado.data
        : [];

    renderizarMovimientosGenerales();
}

function renderizarMovimientosGenerales() {

    tablaMovimientosHoras.clear();

    movimientosHoras.forEach(m => {

        tablaMovimientosHoras.row.add([
            escaparHTML(m.fecha_movimiento || "—"),
            escaparHTML(
                `${m.apellido || ""}, ${m.nombre || ""}`
            ),
            escaparHTML(m.tipo_movimiento || "—"),
            formatearMinutos(m.minutos),
            badgeEstado(m.estado),
            escaparHTML(m.observaciones || "—")
        ]);
    });

    tablaMovimientosHoras.draw();

    $("#sinMovimientosHoras").toggleClass(
        "d-none",
        movimientosHoras.length !== 0
    );
}

// =========================================================
// AUTORIZACIONES
// =========================================================

async function cargarAutorizacionesHoras() {
    const resultado = await solicitarHoras("autorizaciones");

    autorizacionesHoras = Array.isArray(resultado.data)
        ? resultado.data
        : [];

    const pendientes = autorizacionesHoras.filter(
        a => a.estado === "pendiente"
    ).length;

    $("#badgeAutorizacionesPendientes").text(pendientes);

    renderizarAutorizacionesHoras();
}

function renderizarAutorizacionesHoras() {

    tablaAutorizacionesHoras.clear();

    autorizacionesHoras.forEach(a => {

        const id = Number(a.idautorizacion);

        const acciones = a.estado === "pendiente"
            ? `
                <div class="d-flex gap-1 justify-content-center">
                    <button type="button"
                        class="btn btn-success btn-sm btn-autorizar-compensacion"
                        data-idautorizacion="${id}">
                        <i class="bi bi-check-lg"></i> Autorizar
                    </button>

                    <button type="button"
                        class="btn btn-danger btn-sm btn-rechazar-compensacion"
                        data-idautorizacion="${id}">
                        <i class="bi bi-x-lg"></i> Rechazar
                    </button>
                </div>
              `
            : `<span class="text-muted">Sin acciones</span>`;

        tablaAutorizacionesHoras.row.add([
            escaparHTML(a.fecha_solicitud || "—"),
            escaparHTML(`${a.apellido || ""}, ${a.nombre || ""}`),
            escaparHTML(a.tipo_operacion || "—"),
            formatearMinutos(a.minutos_autorizados),
            escaparHTML(a.motivo || "—"),
            badgeEstado(a.estado),
            acciones
        ]);
    });

    tablaAutorizacionesHoras.draw();

    $("#sinAutorizacionesHoras").toggleClass(
        "d-none",
        autorizacionesHoras.length !== 0
    );
}

// =========================================================
// EVENTOS
// =========================================================

function registrarEventosHoras() {

    $("#btnActualizarHoras").on("click", cargarHorasCompensatorias);

    $("#btnLimpiarFiltrosHoras").on("click", function () {

        $("#filtroEmpleadoHoras").val("");
        $("#filtroEstadoSaldo").val("");
        $("#filtroSoloConSaldo").prop("checked", false);

        renderizarSaldosEmpleados();
    });

    $("#filtroEmpleadoHoras").on("input", renderizarSaldosEmpleados);
    $("#filtroEstadoSaldo").on("change", renderizarSaldosEmpleados);
    $("#filtroSoloConSaldo").on("change", renderizarSaldosEmpleados);

    $("#btnVerAutorizaciones").on("click", function () {
        modalAutorizacionesHoras.show();
    });

    $("#btnProcesarVencimientos").on(
        "click",
        procesarVencimientosHoras
    );
}

// =========================================================
// UTILIDADES
// =========================================================

function formatearMinutos(valor) {

    const minutos = Math.max(0, Number(valor || 0));

    const horas = Math.floor(minutos / 60);
    const resto = minutos % 60;

    return `${horas} h ${String(resto).padStart(2, "0")} min`;
}

function normalizarTexto(texto) {

    return String(texto || "")
        .toLocaleLowerCase("es-AR")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
}

function escaparHTML(valor) {

    return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[caracter]);
}

function badgeEstado(estado) {

    const estados = {
        disponible: ["success", "Disponible"],
        agotado: ["secondary", "Agotado"],
        vencido: ["warning text-dark", "Vencido"],
        revertido: ["danger", "Revertido"],
        vigente: ["success", "Vigente"],
        pendiente: ["warning text-dark", "Pendiente"],
        autorizada: ["success", "Autorizada"],
        rechazada: ["danger", "Rechazada"],
        anulada: ["secondary", "Anulada"]
    };

    const configuracion = estados[estado] || ["secondary", estado || "—"];

    return `<span class="badge bg-${configuracion[0]}">
        ${escaparHTML(configuracion[1])}
    </span>`;
}

const URL_RESOLVER_AUTORIZACION =
    API_BASE + "/fichajes/compensacion/resolver_autorizacion.php";

$(document).on(
    "click",
    ".btn-autorizar-compensacion, .btn-rechazar-compensacion",
    async function () {
        const idautorizacion = Number($(this).data("idautorizacion"));
        const accion = $(this).hasClass("btn-autorizar-compensacion")
            ? "autorizar"
            : "rechazar";

        if (!Number.isInteger(idautorizacion) || idautorizacion < 1) {
            toast("La autorización indicada no es válida.", "error");
            return;
        }

        let observaciones = "";

        if (accion === "autorizar") {
            const solicitud = autorizacionesHoras.find(
                a => Number(a.idautorizacion) === idautorizacion
            );

            const esUtilizacion =
                solicitud?.tipo_operacion === "utilizacion";

            const confirmacion = await Swal.fire({
                icon: "question",
                title: esUtilizacion
                    ? "¿Autorizar utilización de horas?"
                    : "¿Autorizar generación de horas?",
                text: esUtilizacion
                    ? "Se descontarán del saldo disponible los minutos solicitados."
                    : "Se generará el saldo de horas compensatorias de esta solicitud.",
                showCancelButton: true,
                confirmButtonText: "Autorizar",
                cancelButtonText: "Cancelar"
            });

            if (!confirmacion.isConfirmed) {
                return;
            }
        } else {
            const resultado = await Swal.fire({
                target: document.getElementById("modalAutorizacionesHoras"),
                icon: "warning",
                title: "¿Rechazar solicitud?",
                input: "textarea",
                inputLabel: "Motivo del rechazo",
                inputPlaceholder: "Ingresá el motivo...",
                inputValidator: valor =>
                    !valor || !valor.trim()
                        ? "Tenés que indicar el motivo."
                        : undefined,
                showCancelButton: true,
                confirmButtonText: "Rechazar",
                cancelButtonText: "Cancelar"
            });

            if (!resultado.isConfirmed) {
                return;
            }

            observaciones = resultado.value.trim();
        }

        const $boton = $(this);
        $boton.prop("disabled", true);

        try {
            const respuesta = await fetch(URL_RESOLVER_AUTORIZACION, {
                method: "POST",
                headers: {
                    ...obtenerHeadersSSO(),
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    idautorizacion,
                    accion,
                    observaciones
                })
            });

            const texto = await respuesta.text();
            let resultado;

            try {
                resultado = JSON.parse(texto);
            } catch (error) {
                throw new Error("El servidor no devolvió una respuesta JSON válida.");
            }

            if (!respuesta.ok || resultado.status !== "ok") {
                throw new Error(resultado.msg || "No se pudo resolver la solicitud.");
            }

            toast(resultado.msg, "success");

            // Recargar el listado y los saldos con las funciones existentes.
            await Promise.all([
                cargarResumenHoras(),
                cargarSaldosEmpleados(),
                cargarMovimientosHoras(),
                cargarAutorizacionesHoras()
            ]);
            
        } catch (error) {
            toast(error.message, "error");
        } finally {
            $boton.prop("disabled", false);
        }
    }
);