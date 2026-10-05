// =========================================================
// DASHBOARD DE FICHAJES
// =========================================================


// =========================================================
// VALIDACIÓN INICIAL SSO
// =========================================================

$(document).ready(function () {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    // ---------------------------------------------------------
    // Verificar sesión contra SSO
    // ---------------------------------------------------------

    $.ajax({
        url: API_BASE + "/sso/auth/me.php",
        type: "GET",
        headers: obtenerHeadersSSO(),

        success: function (response) {

            const res =
                (typeof response === "string")
                    ? JSON.parse(response)
                    : response;

            if (res.status !== "ok" || !res.usuario) {

                localStorage.clear();
                window.location.href = window.SSO_LOGIN_URL;
                return;
            }

        },

        error: function (xhr) {

            if (xhr.status === 401 || xhr.status === 403 || xhr.status === 0) {

                localStorage.clear();
                window.location.href = window.SSO_LOGIN_URL;
            }

        }
    });


    // =========================================================
    // CARGA INICIAL DEL DASHBOARD
    // =========================================================

    if (document.getElementById("card-empleados-activos")) {

        cargarMetricasDashboard();
        cargarIncidenciasDashboard();


        // -----------------------------------------------------
        // Obtener intervalo desde configuración central
        // -----------------------------------------------------

        obtenerConfiguracionDashboard().then(configuracion => {

            let intervaloDashboard = Number(
                configuracion.dashboard_incidencias_intervalo
            );

            // Respaldo
            if (
                !Number.isFinite(intervaloDashboard) ||
                intervaloDashboard <= 0
            ) {
                intervaloDashboard = 30;
            }

            const intervaloMilisegundos =
                intervaloDashboard * 1000;


            // -------------------------------------------------
            // Actualización automática
            // -------------------------------------------------

            setInterval(() => {

                cargarMetricasDashboard();
                cargarIncidenciasDashboard();

            }, intervaloMilisegundos);

        });

    }


    // =========================================================
    // BOTÓN ACTUALIZAR FICHAJES
    // =========================================================

    $("#btnSincronizarFichajes").on("click", function () {

        const $btn = $(this);

        const textoOriginal =
            $btn.find("#texto-sync").text();

        const iconoOriginal =
            $btn.find("#icono-sync").attr("class");


        $btn.prop("disabled", true);

        $btn.find("#icono-sync")
            .attr(
                "class",
                "spinner-border spinner-border-sm me-2"
            );

        $btn.find("#texto-sync")
            .text("Actualizando...");


        Promise.all([
            cargarMetricasDashboard(),
            cargarIncidenciasDashboard()
        ])
        .finally(() => {

            $btn.prop("disabled", false);

            $btn.find("#icono-sync")
                .attr("class", iconoOriginal);

            $btn.find("#texto-sync")
                .text(textoOriginal);

        });

    });


    // =========================================================
    // MOSTRAR USUARIO ACTUAL
    // =========================================================

    const usuario =
        JSON.parse(
            localStorage.getItem("usuario_actual") || "{}"
        );

    if (usuario.nombreapellido || usuario.username) {

        $("#nombre-usuario-ui").text(
            usuario.nombreapellido ||
            usuario.username
        );
    }

});


// =========================================================
// MÉTRICAS PRINCIPALES DEL DASHBOARD
// =========================================================

async function cargarMetricasDashboard() {

    try {

        const response = await fetch(
            API_BASE +
            "/fichajes/dashboard/obtener_datos_dashboard.php",
            {
                method: "GET",
                cache: "no-store",
                headers: obtenerHeadersSSO()
            }
        );


        const text = await response.text();


        let res;

        try {

            res = JSON.parse(text);

        } catch (error) {

            console.error(
                "La API del dashboard devolvió una respuesta no válida:",
                text
            );

            throw new Error(
                "La API del dashboard no devolvió JSON válido."
            );
        }


        if (res.status !== "ok") {

            console.error(
                "Error al cargar métricas del dashboard:",
                res.msg
            );

            return;
        }


        const datos = res.data || {};


        // =====================================================
        // EMPLEADOS ACTIVOS
        // =====================================================

        const empleadosActivos =
            document.getElementById(
                "card-empleados-activos"
            );

        if (empleadosActivos) {

            empleadosActivos.textContent =
                Number(datos.empleados_activos || 0);
        }


        // =====================================================
        // MARCAS DE HOY
        // =====================================================

        const marcasHoy =
            document.getElementById(
                "card-marcas-hoy"
            );

        if (marcasHoy) {

            marcasHoy.textContent =
                Number(datos.marcas_hoy || 0);
        }


        // =====================================================
        // JORNADAS PENDIENTES
        // =====================================================

        const jornadasPendientes =
            document.getElementById(
                "card-jornadas-pendientes"
            );

        if (jornadasPendientes) {

            jornadasPendientes.textContent =
                Number(datos.jornadas_pendientes || 0);
        }


        // =====================================================
        // JORNADAS CON REVISIÓN
        // =====================================================

        const jornadasRevision =
            document.getElementById(
                "card-jornadas-revision"
            );

        if (jornadasRevision) {

            jornadasRevision.textContent =
                Number(datos.jornadas_revision || 0);
        }


    } catch (error) {

        console.error(
            "Error al cargar las métricas del dashboard:",
            error
        );
    }
}


// =========================================================
// INCIDENCIAS DE AGENTES
// =========================================================

async function cargarIncidenciasDashboard() {

    try {

        const response = await fetch(
            API_BASE +
            "/fichajes/incidencias/incidencias.php",
            {
                method: "GET",
                cache: "no-store",
                headers: obtenerHeadersSSO()
            }
        );


        const text = await response.text();


        let res;

        try {

            res = JSON.parse(text);

        } catch (error) {

            console.error(
                "La API de incidencias devolvió una respuesta no válida:",
                text
            );

            throw new Error(
                "La API de incidencias no devolvió JSON válido."
            );
        }


        if (res.status !== "ok") {

            console.error(
                "Error al consultar incidencias:",
                res.msg
            );

            return;
        }


        const resumen =
            res.resumen || {};

        const agentes =
            res.agentes || [];

        const incidencias =
            res.incidencias || [];


        // =====================================================
        // TOTAL INCIDENCIAS
        // =====================================================

        const elementoTotal =
            document.getElementById(
                "badge-incidencias-total"
            );

        if (elementoTotal) {

            const total =
                Number(resumen.abiertas || 0) +
                Number(resumen.reconocidas || 0);

            elementoTotal.textContent = total;
        }


        // =====================================================
        // ABIERTAS
        // =====================================================

        const elementoAbiertas =
            document.getElementById(
                "incidencias-abiertas"
            );

        if (elementoAbiertas) {

            elementoAbiertas.textContent =
                Number(resumen.abiertas || 0);
        }


        // =====================================================
        // RECONOCIDAS
        // =====================================================

        const elementoReconocidas =
            document.getElementById(
                "incidencias-reconocidas"
            );

        if (elementoReconocidas) {

            elementoReconocidas.textContent =
                Number(resumen.reconocidas || 0);
        }


        // =====================================================
        // ESTADO DEL AGENT
        // =====================================================

        const indicador =
            document.getElementById(
                "indicador-estado-agent"
            );

        const textoEstado =
            document.getElementById(
                "texto-estado-agent"
            );


        if (indicador && textoEstado) {

            const agenteOnline =
                agentes.some(
                    agente => agente.online === true
                );


            if (agenteOnline) {

                indicador.className =
                    "badge bg-success";

                indicador.textContent =
                    "ONLINE";

                textoEstado.textContent =
                    "El Agent está conectado y activo.";

            } else {

                indicador.className =
                    "badge bg-danger";

                indicador.textContent =
                    "OFFLINE";

                textoEstado.textContent =
                    "No se detecta actividad reciente del Agent.";
            }
        }


        // =====================================================
        // LISTA DE ÚLTIMAS INCIDENCIAS
        // =====================================================

        const contenedor =
            document.getElementById(
                "lista-incidencias-dashboard"
            );


        if (!contenedor) {
            return;
        }


        contenedor.innerHTML = "";


        // -----------------------------------------------------
        // Sin incidencias
        // -----------------------------------------------------

        if (
            !incidencias ||
            incidencias.length === 0
        ) {

            contenedor.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                    No hay incidencias pendientes.
                </div>
            `;

            return;
        }


        // -----------------------------------------------------
        // Mostrar máximo 5
        // -----------------------------------------------------

        incidencias
            .slice(0, 5)
            .forEach(incidencia => {


                let claseSeveridad = "secondary";
                let icono = "bi-info-circle";


                if (incidencia.severidad === "critica") {

                    claseSeveridad = "danger";
                    icono = "bi-exclamation-octagon";

                } else if (incidencia.severidad === "error") {

                    claseSeveridad = "danger";
                    icono = "bi-exclamation-triangle";

                } else if (incidencia.severidad === "advertencia") {

                    claseSeveridad = "warning";
                    icono = "bi-exclamation-circle";
                }


                contenedor.innerHTML += `
                    <div class="border rounded-3 p-3 mb-2">

                        <div class="d-flex justify-content-between align-items-start">

                            <div class="d-flex align-items-start">

                                <i class="bi ${icono} text-${claseSeveridad} fs-5 me-2"></i>

                                <div>

                                    <div class="fw-semibold">
                                        ${incidencia.mensaje}
                                    </div>

                                    <div class="small text-muted">
                                        Agent:
                                        ${incidencia.agente_nombre || "-"}
                                    </div>

                                </div>

                            </div>

                            <span class="badge bg-${claseSeveridad}">
                                ${incidencia.cantidad_ocurrencias || 1}
                            </span>

                        </div>

                    </div>
                `;
            });


    } catch (error) {

        console.error(
            "Error al cargar incidencias:",
            error
        );
    }
}


// =========================================================
// CONFIGURACIÓN DEL DASHBOARD
// =========================================================

async function obtenerConfiguracionDashboard() {

    try {

        const response = await fetch(
            API_BASE +
            "/fichajes/configuracion/obtener_configuracion.php",
            {
                method: "GET",
                cache: "no-store",
                headers: obtenerHeadersSSO()
            }
        );


        const res =
            await response.json();


        if (res.status !== "ok") {

            console.error(
                "No se pudo obtener la configuración del sistema:",
                res.msg
            );

            return {};
        }


        return res.data || {};


    } catch (error) {

        console.error(
            "Error al obtener la configuración del sistema:",
            error
        );

        return {};
    }
}


// =========================================================
// LOGOUT
// =========================================================

function logout() {

    localStorage.removeItem("sso_token");
    localStorage.removeItem("usuario_actual");
    localStorage.removeItem("sso_app_activa");

    window.location.href =
        window.SSO_LOGIN_URL;
}