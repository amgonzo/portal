// ============================================================
// DASHBOARD SSO RRHH
// Endpoint: /rrhh/dashboard/obtener_datos_dashboard.php
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

    const inputDesde = document.getElementById('fecha_desde');
    const inputHasta = document.getElementById('fecha_hasta');
    const btnConsultar = document.getElementById('btnConsultar');
    const btnMesActual = document.getElementById('btnMesActual');

    if (!inputDesde || !inputHasta) return;

    // ========================================================
    // FECHAS INICIALES
    // ========================================================

    const hoy = new Date();

    const primerDia = new Date(
        hoy.getFullYear(),
        hoy.getMonth(),
        1
    );

    const formatoFecha = fecha => {
        const anio = fecha.getFullYear();
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const dia = String(fecha.getDate()).padStart(2, '0');

        return `${anio}-${mes}-${dia}`;
    };

    inputDesde.value = formatoFecha(primerDia);
    inputHasta.value = formatoFecha(hoy);

    // ========================================================
    // UTILIDADES
    // ========================================================

    const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[caracter]));

    const mostrar = (id, valor) => {
        const elemento = document.getElementById(id);

        if (elemento) {
            elemento.textContent = valor ?? 0;
        }
    };

    const fechaAR = valor => {
        if (!valor) return '-';

        const partes = String(valor)
            .substring(0, 10)
            .split('-');

        return partes.length === 3
            ? `${partes[2]}/${partes[1]}/${partes[0]}`
            : valor;
    };

    const nombreEmpleado = fila => {
        return `${fila.apellido ?? ''}, ${fila.nombre ?? ''}`
            .replace(/^, |, $/g, '');
    };

    // ========================================================
    // TABLA SOLICITUDES PENDIENTES
    // ========================================================
/*
    function renderSolicitudes(filas) {

        const tbody = document.getElementById(
            'tabla-solicitudes-pendientes'
        );

        if (!tbody) return;

        if (!filas.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        No hay solicitudes pendientes.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filas.map(fila => `
            <tr>
                <td>
                    <div class="fw-semibold">
                        ${escapar(nombreEmpleado(fila))}
                    </div>
                    <small class="text-muted">
                        ${escapar(fila.documento)}
                    </small>
                </td>

                <td>${escapar(fila.tipo_descripcion)}</td>

                <td>${fechaAR(fila.fecha_desde)}</td>

                <td>${fechaAR(fila.fecha_hasta)}</td>

                <td>${fechaAR(fila.fecha_solicitud)}</td>

                <td class="text-end">
                    <a href="solicitudes.php?id=${encodeURIComponent(fila.idsolicitud)}"
                       class="btn btn-sm btn-outline-primary">
                        Ver
                    </a>
                </td>
            </tr>
        `).join('');
    }
*/
    // ========================================================
    // TABLA DOCUMENTOS POR VENCER
    // ========================================================

    function renderDocumentos(filas) {

        const tbody = document.getElementById(
            'tabla-documentos-vencer'
        );

        if (!tbody) return;

        if (!filas.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center py-4 text-muted">
                        No hay documentos próximos a vencer.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filas.map(fila => `
            <tr>
                <td>${escapar(nombreEmpleado(fila))}</td>

                <td>
                    <div>${escapar(fila.titulo)}</div>
                    <small class="text-muted">
                        ${escapar(fila.tipo_documento)}
                    </small>
                </td>

                <td class="text-danger fw-semibold">
                    ${fechaAR(fila.fecha_vencimiento)}
                </td>
            </tr>
        `).join('');
    }

    // ========================================================
    // TABLA PRÓXIMAS AUSENCIAS
    // ========================================================
    /*
    function renderAusencias(filas) {

        const tbody = document.getElementById(
            'tabla-proximas-ausencias'
        );

        if (!tbody) return;

        if (!filas.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        No hay ausencias próximas aprobadas.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filas.map(fila => `
            <tr>
                <td>${escapar(nombreEmpleado(fila))}</td>

                <td>${escapar(fila.tipo_descripcion)}</td>

                <td>${fechaAR(fila.fecha_desde)}</td>

                <td>${fechaAR(fila.fecha_hasta)}</td>
            </tr>
        `).join('');
    }
    */
    // ========================================================
    // TABLA PERSONAL POR SECTOR
    // ========================================================

    function renderSectores(filas) {

        const tbody = document.getElementById(
            'tabla-personal-sectores'
        );

        if (!tbody) return;

        if (!filas.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="2" class="text-center py-4 text-muted">
                        Todavía no hay empleados asignados a sectores.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filas.map(fila => `
            <tr>
                <td>${escapar(fila.sector)}</td>

                <td class="text-end fw-semibold">
                    ${Number(fila.cantidad) || 0}
                </td>
            </tr>
        `).join('');
    }

    // ========================================================
    // CARGAR DASHBOARD
    // ========================================================

    async function cargarDashboard() {

        if (!inputDesde.value || !inputHasta.value) {
            Swal.fire(
                'Atención',
                'Seleccioná ambas fechas.',
                'warning'
            );
            return;
        }

        if (inputDesde.value > inputHasta.value) {
            Swal.fire(
                'Atención',
                'La fecha desde no puede ser posterior a la fecha hasta.',
                'warning'
            );
            return;
        }

        if (btnConsultar) {
            btnConsultar.disabled = true;

            btnConsultar.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2"></span>
                Consultando...
            `;
        }

        try {

            const params = new URLSearchParams({
                desde: inputDesde.value,
                hasta: inputHasta.value
            });

            const url =
                `${API_BASE}/rrhh/dashboard/obtener_datos_dashboard.php?${params.toString()}`;

            const response = await fetch(url, {
                method: 'GET',
                headers: obtenerHeadersSSO()
            });

            const data = await response.json();

            if (!response.ok || data.status !== 'ok') {
                throw new Error(
                    data.mensaje || 'No se pudieron obtener los datos del dashboard.'
                );
            }

            // ================================================
            // INDICADORES
            // ================================================

            const k = data.indicadores || {};

            mostrar(
                'card-empleados-activos',
                k.empleados_activos
            );
/*
            mostrar(
                'card-solicitudes-pendientes',
                k.solicitudes_pendientes
            );
*/
            mostrar(
                'card-documentos-vencer',
                k.documentos_por_vencer
            );
/*
            mostrar(
                'card-vacaciones-proximas',
                k.vacaciones_proximas
            );
*/
            mostrar(
                'card-legajos-completos',
                k.legajos_registrados
            );

            mostrar(
                'card-altas-periodo',
                k.altas_periodo
            );

            mostrar(
                'card-bajas-periodo',
                k.empleados_inactivos
            );

            // ================================================
            // TABLAS
            // ================================================
/*
            renderSolicitudes(
                data.solicitudes_pendientes || []
            );
*/
            renderDocumentos(
                data.documentos_por_vencer || []
            );
/*
            renderAusencias(
                data.proximas_ausencias || []
            );
*/
            renderSectores(
                data.personal_sectores || []
            );

        } catch (error) {

            console.error(
                'Error cargando dashboard RRHH:',
                error
            );

            if (typeof toast === 'function') {
                toast('error', error.message);
            } else {
                Swal.fire(
                    'Error',
                    error.message,
                    'error'
                );
            }

        } finally {

            if (btnConsultar) {
                btnConsultar.disabled = false;

                btnConsultar.innerHTML = `
                    <i class="bi bi-search me-2"></i>
                    Consultar período
                `;
            }
        }
    }

    // ========================================================
    // EVENTOS
    // ========================================================

    if (btnConsultar) {
        btnConsultar.addEventListener(
            'click',
            cargarDashboard
        );
    }

    if (btnMesActual) {

        btnMesActual.addEventListener('click', () => {

            const ahora = new Date();

            inputDesde.value = formatoFecha(
                new Date(
                    ahora.getFullYear(),
                    ahora.getMonth(),
                    1
                )
            );

            inputHasta.value = formatoFecha(ahora);

            cargarDashboard();
        });
    }

    // Carga inicial
    cargarDashboard();

});