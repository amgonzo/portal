/* =========================================================
   INCIDENCIAS - FICHAJES
   ========================================================= */

let tablaIncidencias = null;


/* =========================================================
   INICIO
   ========================================================= */

$(document).ready(function () {

    $('#btnActualizarIncidencias').on(
        'click',
        cargarIncidencias
    );

    $(document).on(
        'click',
        '.btn-reintentar-incidencia',
        function () {

            const idIncidencia = parseInt(
                $(this).data('idincidencia') || 0
            );

            if (idIncidencia <= 0) {
                toast(
                    'No se pudo identificar la incidencia.',
                    'error'
                );
                return;
            }

            reintentarFichada(idIncidencia);
        }
    );

    cargarIncidencias();
});


/* =========================================================
   HEADERS SSO
   ========================================================= */

function obtenerHeadersIncidencias() {

    if (typeof obtenerHeadersSSO === 'function') {
        return obtenerHeadersSSO();
    }

    const token =
        localStorage.getItem('sso_token') || '';

    const idEmpresa =
        localStorage.getItem('sso_id_empresa_activa') || '';

    const idAplicacion =
        localStorage.getItem('sso_app_activa') || '';

    return {
        'Authorization': 'Bearer ' + token,
        'X-EMPRESA-ID': String(idEmpresa),
        'X-APLICACION-ID': String(idAplicacion)
    };
}


/* =========================================================
   CARGAR INCIDENCIAS
   ========================================================= */

function cargarIncidencias() {

    const $boton =
        $('#btnActualizarIncidencias');

    const htmlOriginal =
        $boton.html();

    $boton
        .prop('disabled', true)
        .html(
            '<i class="fas fa-spinner fa-spin me-2"></i>' +
            'Actualizando...'
        );

    $.ajax({

        type: 'GET',

        url:
            API_BASE +
            '/fichajes/incidencias/incidencias.php',

        headers:
            obtenerHeadersIncidencias(),

        dataType: 'json'

    })

    .done(function (respuesta) {

        if (
            !respuesta ||
            respuesta.status !== 'ok'
        ) {

            toast(
                respuesta?.msg ||
                'No se pudieron cargar las incidencias.',
                'error'
            );

            return;
        }

        /*
         * IMPORTANTE:
         * El API devuelve:
         *
         * resumen
         * agentes
         * incidencias
         */

        actualizarResumen(
            respuesta.resumen || {}
        );

        actualizarEstadoAgents(
            respuesta.agentes || []
        );

        renderizarIncidencias(
            respuesta.incidencias || []
        );

    })

    .fail(function (xhr) {

        let mensaje =
            'Error al consultar las incidencias.';

        if (
            xhr.responseJSON &&
            xhr.responseJSON.msg
        ) {
            mensaje =
                xhr.responseJSON.msg;
        }

        toast(
            mensaje,
            'error'
        );

    })

    .always(function () {

        $boton
            .prop('disabled', false)
            .html(htmlOriginal);
    });
}


/* =========================================================
   RESUMEN
   ========================================================= */

function actualizarResumen(resumen) {

    $('#incidencias-abiertas').text(
        Number(resumen.abiertas || 0)
    );

    $('#incidencias-reconocidas').text(
        Number(resumen.reconocidas || 0)
    );

    $('#incidencias-resueltas').text(
        Number(resumen.resueltas || 0)
    );

    $('#badge-incidencias-total').text(
        Number(resumen.abiertas || 0) +
        Number(resumen.reconocidas || 0)
    );
}


/* =========================================================
   ESTADO DE AGENTS
   ========================================================= */

function actualizarEstadoAgents(agents) {

    const $indicador =
        $('#indicador-estado-agent');

    const $texto =
        $('#texto-estado-agent');

    if (!Array.isArray(agents)) {

        $indicador
            .removeClass()
            .addClass('badge bg-secondary')
            .text('-');

        $texto.text(
            'Sin información del Agent.'
        );

        return;
    }

    if (agents.length === 0) {

        $indicador
            .removeClass()
            .addClass('badge bg-secondary')
            .text('Sin Agents');

        $texto.text(
            'No hay Agents configurados.'
        );

        return;
    }

    const online =
        agents.filter(
            agent =>
                Number(agent.online || 0) === 1
        ).length;

    const total =
        agents.length;

    if (online === total) {

        $indicador
            .removeClass()
            .addClass('badge bg-success')
            .text('Online');

        $texto.text(
            online +
            ' de ' +
            total +
            ' Agents conectados.'
        );

    } else if (online > 0) {

        $indicador
            .removeClass()
            .addClass('badge bg-warning')
            .text('Parcial');

        $texto.text(
            online +
            ' de ' +
            total +
            ' Agents conectados.'
        );

    } else {

        $indicador
            .removeClass()
            .addClass('badge bg-danger')
            .text('Offline');

        $texto.text(
            'Ningún Agent está conectado.'
        );
    }
}


/* =========================================================
   RENDERIZAR TABLA
   ========================================================= */

function renderizarIncidencias(incidencias) {

    const $tbody =
        $('#listaIncidencias');

    $tbody.empty();

    if (
        !Array.isArray(incidencias) ||
        incidencias.length === 0
    ) {

        $tbody.html(`
            <tr>
                <td
                    colspan="9"
                    class="text-center py-4 text-muted"
                >
                    No hay incidencias abiertas o reconocidas.
                </td>
            </tr>
        `);

        destruirDataTable();

        return;
    }

    incidencias.forEach(function (incidencia) {

        const id =
            Number(
                incidencia.idincidencia || 0
            );

        const severidad =
            String(
                incidencia.severidad || 'error'
            );

        const estado =
            String(
                incidencia.estado || ''
            );

        const codigo =
            escaparHtml(
                incidencia.codigo || '-'
            );

        const mensaje =
            escaparHtml(
                incidencia.mensaje || '-'
            );

        /*
         * El PHP devuelve agente_nombre.
         * Dejamos también agent_nombre como fallback
         * por compatibilidad.
         */

        const agent =
            escaparHtml(
                incidencia.agente_nombre ||
                incidencia.agent_nombre ||
                incidencia.agent_id ||
                '-'
            );

        const ocurrencias =
            Number(
                incidencia.cantidad_ocurrencias || 1
            );

        const fecha =
            formatearFecha(
                incidencia.primera_ocurrencia
            );

        const ultima =
            formatearFecha(
                incidencia.ultima_ocurrencia
            );

        let claseSeveridad =
            'bg-secondary';

        if (severidad === 'critica') {
            claseSeveridad = 'bg-danger';
        } else if (severidad === 'error') {
            claseSeveridad = 'bg-danger';
        } else if (severidad === 'advertencia') {
            claseSeveridad = 'bg-warning';
        } else if (severidad === 'info') {
            claseSeveridad = 'bg-info';
        }

        let claseEstado =
            'bg-secondary';

        if (estado === 'abierta') {
            claseEstado = 'bg-danger';
        } else if (estado === 'reconocida') {
            claseEstado = 'bg-warning';
        } else if (estado === 'resuelta') {
            claseEstado = 'bg-success';
        }

        let botonReintentar = '';

        let idFichada = Number(
            incidencia.idfichada || 0
        );

        /*
        * Si idfichada no viene directamente,
        * lo obtenemos desde contexto.
        */
        if (
            idFichada <= 0 &&
            incidencia.contexto
        ) {
            try {
                const contexto =
                    typeof incidencia.contexto === 'string'
                        ? JSON.parse(incidencia.contexto)
                        : incidencia.contexto;

                idFichada = Number(
                    contexto.idfichada || 0
                );

            } catch (e) {
                idFichada = 0;
            }
        }

        if (
            (
                estado === 'abierta' ||
                estado === 'reconocida'
            ) &&
            idFichada > 0
        ) {

            botonReintentar = `
                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary
                        btn-reintentar-incidencia"
                    data-idincidencia="${id}"
                    title="Reintentar fichada"
                >
                    <i class="fas fa-redo me-1"></i>
                    Reintentar
                </button>
            `;
        }

        $tbody.append(`
            <tr>

                <td>
                    <span class="small">
                        ${fecha}
                    </span>
                </td>

                <td>
                    <span class="small fw-semibold">
                        ${agent}
                    </span>
                </td>

                <td>
                    <span
                        class="badge ${claseSeveridad}"
                    >
                        ${escaparHtml(
                            severidad.charAt(0).toUpperCase() +
                            severidad.slice(1)
                        )}
                    </span>
                </td>

                <td>
                    <span
                        class="small text-break"
                    >
                        ${codigo}
                    </span>
                </td>

                <td>
                    <span
                        class="small"
                        title="${mensaje}"
                    >
                        ${mensaje}
                    </span>
                </td>

                <td class="text-center">
                    <span class="badge bg-secondary">
                        ${ocurrencias}
                    </span>
                </td>

                <td>
                    <span class="small">
                        ${ultima}
                    </span>
                </td>

                <td>
                    <span
                        class="badge ${claseEstado}"
                    >
                        ${escaparHtml(
                            estado.charAt(0).toUpperCase() +
                            estado.slice(1)
                        )}
                    </span>
                </td>

                <td class="text-center">
                    ${botonReintentar}
                </td>

            </tr>
        `);
    });

    inicializarDataTable();
}


/* =========================================================
   REINTENTAR FICHADA
   ========================================================= */

function reintentarFichada(idIncidencia) {

    Swal.fire({

        title:
            '¿Reintentar la fichada?',

        text:
            'La fichada será enviada nuevamente al Agent. ' +
            'Si vuelve a fallar, quedará nuevamente en error.',

        icon:
            'question',

        showCancelButton:
            true,

        confirmButtonText:
            'Sí, reintentar',

        cancelButtonText:
            'Cancelar',

        reverseButtons:
            true

    }).then(function (resultado) {

        if (!resultado.isConfirmed) {
            return;
        }

        enviarReintentoFichada(
            idIncidencia
        );
    });
}


/* =========================================================
   ENVIAR REINTENTO
   ========================================================= */

function enviarReintentoFichada(idIncidencia) {

    const $boton =
        $(
            '.btn-reintentar-incidencia' +
            '[data-idincidencia="' +
            idIncidencia +
            '"]'
        );

    const htmlOriginal =
        $boton.html();

    $boton
        .prop('disabled', true)
        .html(
            '<i class="fas fa-spinner fa-spin"></i>'
        );

    $.ajax({

        type:
            'POST',

        url:
            API_BASE +
            '/fichajes/incidencias/incidencias.php',

        headers:
            obtenerHeadersIncidencias(),

        contentType:
            'application/json; charset=utf-8',

        dataType:
            'json',

        data:
            JSON.stringify({

                accion:
                    'reintentar_fichada',

                idincidencia:
                    Number(idIncidencia)
            })

    })

    .done(function (respuesta) {

        if (
            !respuesta ||
            respuesta.status !== 'ok'
        ) {

            toast(
                respuesta?.msg ||
                'No se pudo solicitar el reintento.',
                'error'
            );

            return;
        }

        toast(
            respuesta.msg ||
            'El reintento fue enviado al Agent.',
            'success'
        );

        cargarIncidencias();

    })

    .fail(function (xhr) {

        let mensaje =
            'No se pudo solicitar el reintento.';

        if (
            xhr.responseJSON &&
            xhr.responseJSON.msg
        ) {
            mensaje =
                xhr.responseJSON.msg;
        }

        toast(
            mensaje,
            'error'
        );

    })

    .always(function () {

        $boton
            .prop('disabled', false)
            .html(htmlOriginal);
    });
}


/* =========================================================
   DATATABLE
   ========================================================= */

function destruirDataTable() {

    if (
        $.fn.DataTable &&
        $.fn.DataTable.isDataTable(
            '#tablaIncidencias'
        )
    ) {

        $('#tablaIncidencias')
            .DataTable()
            .destroy();
    }

    tablaIncidencias = null;
}


function inicializarDataTable() {

    destruirDataTable();

    if (
        !$.fn.DataTable
    ) {
        return;
    }

    tablaIncidencias =
        $('#tablaIncidencias').DataTable({

            pageLength: 25,

            order: [
                [6, 'desc']
            ],

            language: {

                search:
                    'Buscar:',

                lengthMenu:
                    'Mostrar _MENU_',

                info:
                    'Mostrando _START_ a _END_ de _TOTAL_',

                infoEmpty:
                    'Sin registros',

                zeroRecords:
                    'No se encontraron incidencias.',

                paginate: {
                    previous:
                        'Anterior',

                    next:
                        'Siguiente'
                }
            }
        });
}


/* =========================================================
   FECHAS
   ========================================================= */

function formatearFecha(valor) {

    if (!valor) {
        return '-';
    }

    const fecha =
        new Date(
            String(valor).replace(
                ' ',
                'T'
            )
        );

    if (
        Number.isNaN(
            fecha.getTime()
        )
    ) {

        return escaparHtml(
            String(valor)
        );
    }

    return (
        String(
            fecha.getDate()
        ).padStart(2, '0') +
        '/' +
        String(
            fecha.getMonth() + 1
        ).padStart(2, '0') +
        '/' +
        fecha.getFullYear() +
        ' ' +
        String(
            fecha.getHours()
        ).padStart(2, '0') +
        ':' +
        String(
            fecha.getMinutes()
        ).padStart(2, '0')
    );
}


/* =========================================================
   ESCAPAR HTML
   ========================================================= */

function escaparHtml(valor) {

    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
