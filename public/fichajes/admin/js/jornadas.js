let tablaJornadasPendientes = null;
let modalCalcularJornadas = null;
let modalMarcaManual = null;


/* =========================================================
   NORMALIZAR HORA
========================================================= */

function normalizarHora(valor) {

    let hora = String(valor || '').trim();

    if (hora === '') {
        return null;
    }

    /*
     * HH:mm
     */
    if (/^\d{1,2}:\d{1,2}$/.test(hora)) {

        const partes = hora.split(':');

        let h = Number(partes[0]);
        let m = Number(partes[1]);

        if (
            !Number.isInteger(h) ||
            !Number.isInteger(m) ||
            h < 0 ||
            h > 23 ||
            m < 0 ||
            m > 59
        ) {
            return null;
        }

        return (
            String(h).padStart(2, '0') +
            ':' +
            String(m).padStart(2, '0')
        );
    }

    /*
     * Solo números
     */

    if (/^\d+$/.test(hora)) {

        /*
         * 8 -> 08:00
         * 11 -> 11:00
         */
        if (hora.length === 1 || hora.length === 2) {

            const h = Number(hora);

            if (h >= 0 && h <= 23) {
                return String(h).padStart(2, '0') + ':00';
            }

            return null;
        }

        /*
         * 800 -> 08:00
         * 113 -> 01:13
         * 1103 -> 11:03
         */

        if (hora.length === 3) {

            const h = Number(hora.substring(0, 1));
            const m = Number(hora.substring(1));

            if (
                h >= 0 &&
                h <= 9 &&
                m >= 0 &&
                m <= 59
            ) {
                return (
                    String(h).padStart(2, '0') +
                    ':' +
                    String(m).padStart(2, '0')
                );
            }

            return null;
        }

        /*
         * 1103 -> 11:03
         */
        if (hora.length === 4) {

            const h = Number(hora.substring(0, 2));
            const m = Number(hora.substring(2, 4));

            if (
                h >= 0 &&
                h <= 23 &&
                m >= 0 &&
                m <= 59
            ) {
                return (
                    String(h).padStart(2, '0') +
                    ':' +
                    String(m).padStart(2, '0')
                );
            }

            return null;
        }
    }

    return null;
}


/* =========================================================
   FECHA
========================================================= */

function formatearFecha(fecha) {

    if (!fecha) {
        return '';
    }

    const partes = String(fecha).split('-');

    if (partes.length !== 3) {
        return fecha;
    }

    return (
        partes[2] +
        '/' +
        partes[1] +
        '/' +
        partes[0]
    );
}


/* =========================================================
   DOCUMENT READY
========================================================= */

document.addEventListener('DOMContentLoaded', function () {

    modalCalcularJornadas = new bootstrap.Modal(
        document.getElementById('ModalCalcularJornadas')
    );

    modalMarcaManual = new bootstrap.Modal(
        document.getElementById('ModalMarcaManual')
    );

    inicializarTablaJornadas();

    document
        .getElementById('btnActualizarJornadas')
        .addEventListener('click', cargarJornadasPendientes);

    document
        .getElementById('btnCalcularJornadas')
        .addEventListener('click', abrirModalCalcular);

    document
        .getElementById('btnEjecutarCalculo')
        .addEventListener('click', ejecutarCalculo);

    document
        .getElementById('btnGuardarMarcaManual')
        .addEventListener('click', guardarMarcaManual);

    document
        .getElementById('marca_manual_hora')
        .addEventListener('blur', function () {

            const valor = this.value.trim();

            if (valor === '') {
                return;
            }

            const hora = normalizarHora(valor);

            if (hora !== null) {
                this.value = hora;
            }
        });

    cargarJornadasPendientes();
});


/* =========================================================
   DATATABLE
========================================================= */

function inicializarTablaJornadas() {

    tablaJornadasPendientes = $('#tablaJornadasPendientes').DataTable({

        responsive: false,

        autoWidth: false,

        pageLength: 25,

        order: [
            [3, 'desc'],
            [1, 'asc']
        ],

        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },

        columns: [

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                width: '35px',
                render: function () {

                    return `
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary btn-expandir-jornada"
                            title="Ver detalle">
                            <i class="fas fa-plus"></i>
                        </button>
                    `;
                }
            },

            {
                data: 'empleado'
            },

            {
                data: 'documento'
            },

            {
                data: 'fecha',
                render: function (data) {
                    return formatearFecha(data);
                }
            },

            {
                data: 'cantidad_marcas',
                className: 'text-center',
                render: function (data) {
                    return `<strong>${data}</strong>`;
                }
            },

            {
                data: 'estado',
                render: function (data) {

                    if (data === 'requiere_revision') {

                        return `
                            <span class="badge bg-warning text-dark">
                                Requiere revisión
                            </span>
                        `;
                    }

                    return `
                        <span class="badge bg-secondary">
                            Pendiente
                        </span>
                    `;
                }
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function () {

                    return `
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary btn-ver-detalle">
                            <i class="fas fa-eye me-1"></i>
                            Ver
                        </button>
                    `;
                }
            }

        ]

    });


    $('#tablaJornadasPendientes tbody').on(
        'click',
        '.btn-expandir-jornada, .btn-ver-detalle',
        function () {

            const tr = $(this).closest('tr');
            const row = tablaJornadasPendientes.row(tr);

            if (row.child.isShown()) {

                row.child.hide();

                tr.removeClass('shown');

                tr.find('.btn-expandir-jornada i')
                    .removeClass('fa-minus')
                    .addClass('fa-plus');

                return;
            }

            cargarDetalleJornada(row, tr);
        }
    );
}


/* =========================================================
   LISTAR PENDIENTES
========================================================= */

async function cargarJornadasPendientes() {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/jornadas/jornadas.php?accion=listar_pendientes',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar las jornadas.',
                'error'
            );

            return;
        }

        tablaJornadasPendientes.clear();
        tablaJornadasPendientes.rows.add(res.data || []);
        tablaJornadasPendientes.draw();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar las jornadas pendientes.',
            'error'
        );
    }
}


/* =========================================================
   DETALLE
========================================================= */

async function cargarDetalleJornada(row, tr) {

    const datos = row.data();

    tr.find('.btn-expandir-jornada i')
        .removeClass('fa-plus')
        .addClass('fa-minus');

    row.child(`
        <div class="jornada-detalle">
            <div class="text-muted">
                <i class="fas fa-spinner fa-spin me-1"></i>
                Cargando detalle...
            </div>
        </div>
    `).show();

    tr.addClass('shown');

    try {

        const url =
            API_BASE +
            '/fichajes/jornadas/jornadas.php' +
            '?accion=detalle' +
            '&idempleado=' +
            encodeURIComponent(datos.idempleado) +
            '&fecha=' +
            encodeURIComponent(datos.fecha);

        const response = await fetch(
            url,
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (res.status !== 'ok') {

            row.child(`
                <div class="jornada-detalle">
                    <div class="alert alert-danger mb-0">
                        ${escapeHtml(
                            res.msg ||
                            'No se pudo cargar el detalle.'
                        )}
                    </div>
                </div>
            `);

            return;
        }

        row.child(
            construirDetalleJornada(res.data)
        );

    } catch (error) {

        console.error(error);

        row.child(`
            <div class="jornada-detalle">
                <div class="alert alert-danger mb-0">
                    Error al cargar el detalle.
                </div>
            </div>
        `);
    }
}


/* =========================================================
   CONSTRUIR DETALLE
========================================================= */

function construirDetalleJornada(data) {

    let html = `
        <div class="jornada-detalle">

            <div class="jornada-detalle-titulo">
                ${escapeHtml(data.empleado.apellido)},
                ${escapeHtml(data.empleado.nombre)}
                —
                ${formatearFecha(data.fecha)}
            </div>
    `;


    /* =====================================================
       MARCAS
    ===================================================== */

    html += `
        <div class="jornada-seccion">

            <div class="d-flex justify-content-between align-items-center mb-2">

                <h6 class="mb-0">
                    <i class="fas fa-fingerprint me-1"></i>
                    Fichajes
                    (${data.marcas.length})
                </h6>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-permiso="jornadas_gestionar"
                    onclick="abrirMarcaManual(
                        ${Number(data.empleado.idempleado)},
                        '${escapeJs(data.fecha)}',
                        '${escapeJs(
                            data.empleado.apellido +
                            ', ' +
                            data.empleado.nombre
                        )}'
                    )">

                    <i class="fas fa-plus me-1"></i>
                    Fichaje manual

                </button>

            </div>
    `;


    if (!data.marcas.length) {

        html += `
            <div class="text-muted">
                No hay fichajes válidos.
            </div>
        `;

    } else {

        data.marcas.forEach(function (marca) {

            const origen =
                marca.origen === 'reloj'
                    ? `Reloj #${marca.idlector}`
                    : 'Manual';

            html += `
                <div class="jornada-ficha">

                    <div class="jornada-hora">
                        ${escapeHtml(marca.hora)}
                    </div>

                    <div class="jornada-origen">
                        <span class="badge ${
                            marca.origen === 'reloj'
                                ? 'bg-primary'
                                : 'bg-secondary'
                        }">
                            ${escapeHtml(origen)}
                        </span>
                    </div>

                    <div class="jornada-info">
                        ${
                            marca.origen === 'reloj'
                                ? 'Marca recibida desde reloj'
                                : 'Marca cargada manualmente'
                        }
                    </div>

                </div>
            `;
        });
    }

    html += `
        </div>
    `;


    /* =====================================================
       HORARIO
    ===================================================== */

    html += `
        <div class="jornada-seccion">

            <h6>
                <i class="fas fa-clock me-1"></i>
                Horario correspondiente
            </h6>
    `;

    if (!data.horario || !data.horario.idhorario) {

        html += `
            <div class="jornada-sin-horario">
                <i class="fas fa-exclamation-triangle me-1"></i>
                No hay un horario asignado para esta fecha.
            </div>
        `;

    } else {

        html += `
            <div class="mb-2">

                <strong>
                    ${escapeHtml(data.horario.nombre)}
                </strong>

                <span class="badge bg-info text-dark ms-2">
                    ${escapeHtml(data.horario.origen)}
                </span>

            </div>
        `;

        if (data.horario.detalle_origen) {

            html += `
                <div class="jornada-info mb-2">
                    ${escapeHtml(data.horario.detalle_origen)}
                </div>
            `;
        }

        html += `
            <div class="mb-2">

                <strong>Tolerancia:</strong>
                ${Number(data.horario.tolerancia_minutos)} minutos

            </div>
        `;


        if (data.horario.tramos.length) {

            html += `
                <div class="mb-2">
                    <strong>Tramos:</strong>
                </div>
            `;

            data.horario.tramos.forEach(function (tramo) {

                html += `
                    <span class="jornada-tramo">
                        ${escapeHtml(tramo.hora_desde)}
                        →
                        ${escapeHtml(tramo.hora_hasta)}
                        <small class="text-muted">
                            (${Number(tramo.minutos_teoricos)} min)
                        </small>
                    </span>
                `;
            });
        }


        if (data.horario.descansos.length) {

            html += `
                <div class="mt-2 mb-2">
                    <strong>Descansos permitidos:</strong>
                </div>
            `;

            data.horario.descansos.forEach(function (descanso) {

                html += `
                    <span class="jornada-tramo">
                        Descanso ${Number(descanso.orden)}
                        —
                        ${Number(descanso.minutos_permitidos)} min
                    </span>
                `;
            });
        }
    }

    html += `
        </div>
    `;


    /* =====================================================
       PERMISOS
    ===================================================== */

    html += `
        <div class="jornada-seccion">

            <h6>
                <i class="fas fa-door-open me-1"></i>
                Permisos de salida
            </h6>
    `;

    if (!data.permisos.length) {

        html += `
            <div class="text-muted">
                No hay permisos registrados para esta fecha.
            </div>
        `;

    } else {

        data.permisos.forEach(function (permiso) {

            const desde =
                permiso.hora_desde
                    ? permiso.hora_desde
                    : '—';

            const hasta =
                permiso.hora_hasta
                    ? permiso.hora_hasta
                    : '—';

            html += `
                <div class="jornada-tramo jornada-permiso">

                    <strong>
                        ${escapeHtml(desde)}
                        →
                        ${escapeHtml(hasta)}
                    </strong>

                    ${
                        permiso.motivo
                            ? `<span class="text-muted">
                                ${escapeHtml(permiso.motivo)}
                               </span>`
                            : ''
                    }

                </div>
            `;
        });
    }

    html += `
        </div>
    `;


    /* =====================================================
       EXTRA PROGRAMADA
    ===================================================== */

    html += `
        <div class="jornada-seccion">

            <h6>
                <i class="fas fa-business-time me-1"></i>
                Extra programada
            </h6>
    `;

    if (!data.extras.length) {

        html += `
            <div class="text-muted">
                No hay horas extra programadas para esta fecha.
            </div>
        `;

    } else {

        data.extras.forEach(function (extra) {

            html += `
                <div class="jornada-tramo jornada-extra">

                    <strong>
                        ${escapeHtml(extra.hora_desde)}
                        →
                        ${escapeHtml(extra.hora_hasta)}
                    </strong>

                    ${
                        extra.observaciones
                            ? `<span class="text-muted">
                                ${escapeHtml(extra.observaciones)}
                               </span>`
                            : ''
                    }

                </div>
            `;
        });
    }

    html += `
        </div>
    `;


    html += `
        </div>
    `;

    return html;
}


/* =========================================================
   ABRIR FICHAJE MANUAL
========================================================= */

function abrirMarcaManual(
    idempleado,
    fecha,
    empleado
) {

    document.getElementById(
        'marca_manual_idempleado'
    ).value = idempleado;

    document.getElementById(
        'marca_manual_fecha'
    ).value = fecha;

    document.getElementById(
        'marca_manual_empleado'
    ).value = empleado;

    document.getElementById(
        'marca_manual_fecha_mostrar'
    ).value = formatearFecha(fecha);

    document.getElementById(
        'marca_manual_hora'
    ).value = '';

    modalMarcaManual.show();

    setTimeout(function () {

        document
            .getElementById('marca_manual_hora')
            .focus();

    }, 300);
}


/* =========================================================
   GUARDAR FICHAJE MANUAL
========================================================= */

async function guardarMarcaManual() {

    const idempleado = Number(
        document.getElementById(
            'marca_manual_idempleado'
        ).value
    );

    const fecha =
        document.getElementById(
            'marca_manual_fecha'
        ).value;

    const inputHora =
        document.getElementById(
            'marca_manual_hora'
        );

    const hora = normalizarHora(
        inputHora.value
    );

    if (!idempleado || !fecha) {

        toast(
            'Faltan datos del empleado o la fecha.',
            'error'
        );

        return;
    }

    if (!hora) {

        toast(
            'Ingrese una hora válida.',
            'error'
        );

        inputHora.focus();

        return;
    }

    inputHora.value = hora;

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/jornadas/jornadas.php',
            {
                method: 'POST',

                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify({
                    accion: 'guardar_marca_manual',
                    idempleado: idempleado,
                    fecha: fecha,
                    hora: hora
                })
            }
        );

        const res = await response.json();

        if (res.status !== 'ok') {

            toast(
                res.msg ||
                'No se pudo guardar el fichaje.',
                'error'
            );

            return;
        }

        modalMarcaManual.hide();

        toast(
            'Fichaje manual guardado correctamente.',
            'success'
        );

        cargarJornadasPendientes();

    } catch (error) {

        console.error(error);

        toast(
            'Error al guardar el fichaje manual.',
            'error'
        );
    }
}


/* =========================================================
   MODAL CALCULAR
========================================================= */

function abrirModalCalcular() {

    const input =
        document.getElementById(
            'jornada_fecha_calculo'
        );

    input.value = '';

    modalCalcularJornadas.show();

    setTimeout(function () {
        input.focus();
    }, 300);
}


/* =========================================================
   CALCULAR
========================================================= */

function ejecutarCalculo() {

    const fecha =
        document.getElementById(
            'jornada_fecha_calculo'
        ).value;

    if (!fecha) {

        toast(
            'Seleccione la fecha a procesar.',
            'error'
        );

        return;
    }

    /*
     * El cálculo todavía no se ejecuta.
     * Esta parte queda preparada para el siguiente paso.
     */

    modalCalcularJornadas.hide();

    toast(
        'La pantalla de cálculo quedó preparada para procesar la fecha seleccionada.',
        'success'
    );
}


/* =========================================================
   ESCAPAR HTML
========================================================= */

function escapeHtml(valor) {

    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


/* =========================================================
   ESCAPAR JS
========================================================= */

function escapeJs(valor) {

    return String(valor ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r/g, '')
        .replace(/\n/g, '\\n');
}