let tablaTiposAsistencia = null;
let modalTipoAsistencia = null;


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', function () {

    const modalElement =
        document.getElementById('ModalTipoAsistencia');

    if (modalElement) {
        modalTipoAsistencia =
            new bootstrap.Modal(modalElement);
    }

    inicializarTablaTiposAsistencia();

    cargarTiposAsistencia();

    inicializarColores();

    const form =
        document.getElementById('formTipoAsistencia');

    if (form) {

        form.addEventListener('submit', function (event) {

            event.preventDefault();

            guardarTipoAsistencia();

        });

    }

});


// =========================================================
// DATATABLE
// =========================================================

function inicializarTablaTiposAsistencia() {

    if (!window.jQuery || !$.fn.DataTable) {
        return;
    }

    tablaTiposAsistencia =
        $('#tablaTiposAsistencia').DataTable({

            destroy: true,

            responsive: true,

            pageLength: 10,

            order: [[0, 'asc']],

            language: {
                url:
                    'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
            },

            columnDefs: [

                {
                    targets: [8],
                    orderable: false,
                    searchable: false,
                    className: 'text-center'
                }

            ]

        });

}


// =========================================================
// CARGAR
// =========================================================

async function cargarTiposAsistencia() {

    try {

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/tipos_asistencia/tipos_asistencias.php',
                {
                    method: 'GET',
                    cache: 'no-store',
                    headers: obtenerHeadersSSO()
                }
            );

        const data =
            await respuesta.json();

        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudieron cargar los tipos de asistencia.'
            );

        }

        renderizarTiposAsistencia(
            data.data || []
        );

    } catch (error) {

        console.error(
            'Error al cargar tipos de asistencia:',
            error
        );

        toast(
            error.message ||
            'Error al cargar los tipos de asistencia.',
            'error'
        );

    }

}


// =========================================================
// RENDER TABLA
// =========================================================

function renderizarTiposAsistencia(lista) {

    if (tablaTiposAsistencia) {
        tablaTiposAsistencia.clear();
    }

    const filas = [];

    lista.forEach(function (tipo) {

        const id =
            Number(tipo.idtipo_asistencia);

        const activo =
            Number(tipo.activo) === 1;

        const modificable =
            Number(tipo.modificable) === 1;

        const visibleMaestro =
            Number(tipo.visible_maestro) === 1;

        const computaTrabajo =
            Number(tipo.computa_trabajo) === 1;

        const computaAusencia =
            Number(tipo.computa_ausencia) === 1;

        const fondo =
            tipo.color_fondo ||
            '#FFFFFF';

        const texto =
            tipo.color_texto ||
            '#000000';

        const abreviatura =
            tipo.abreviatura || '';


        let color = `

            <div
                class="d-flex align-items-center gap-2">

                <span
                    class="d-inline-flex align-items-center justify-content-center rounded border"
                    style="
                        width: 36px;
                        height: 30px;
                        background-color: ${escapeAttribute(fondo)};
                        color: ${escapeAttribute(texto)};
                        font-weight: 700;
                    ">

                    ${escapeHtml(abreviatura)}

                </span>

                <small class="text-muted">
                    ${escapeHtml(fondo)}
                </small>

            </div>

        `;


        let acciones = '';

        acciones += `

            <div
                class="d-flex justify-content-center gap-1">

        `;


        if (modificable) {

            acciones += `

                <button
                    type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Editar tipo de asistencia"
                    data-permiso="tipos_asistencia_gestionar"
                    onclick="editarTipoAsistencia(${id})">

                    <i class="fas fa-edit"></i>

                </button>

            `;

        }


        if (activo) {

            acciones += `

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Desactivar tipo de asistencia"
                    data-permiso="tipos_asistencia_gestionar"
                    onclick="cambiarEstadoTipoAsistencia(${id}, 0)">

                    <i class="fas fa-toggle-off"></i>

                </button>

            `;

        } else {

            acciones += `

                <button
                    type="button"
                    class="btn btn-sm btn-outline-success"
                    title="Activar tipo de asistencia"
                    data-permiso="tipos_asistencia_gestionar"
                    onclick="cambiarEstadoTipoAsistencia(${id}, 1)">

                    <i class="fas fa-toggle-on"></i>

                </button>

            `;

        }


        acciones += '</div>';


        filas.push([

            escapeHtml(
                tipo.nombre || ''
            ),

            `
                <span class="badge bg-secondary">
                    ${escapeHtml(abreviatura)}
                </span>
            `,

            color,

            computaTrabajo
                ? '<span class="badge bg-success">Sí</span>'
                : '<span class="badge bg-secondary">No</span>',

            computaAusencia
                ? '<span class="badge bg-warning text-dark">Sí</span>'
                : '<span class="badge bg-secondary">No</span>',

            modificable
                ? '<span class="badge bg-primary">Sí</span>'
                : '<span class="badge bg-secondary">No</span>',

            visibleMaestro
                ? '<span class="badge bg-success">Sí</span>'
                : '<span class="badge bg-secondary">No</span>',

            activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>',

            acciones

        ]);

    });


    if (tablaTiposAsistencia) {

        tablaTiposAsistencia
            .rows
            .add(filas)
            .draw();

    } else {

        const tbody =
            document.getElementById(
                'listaTiposAsistencia'
            );

        if (!tbody) {
            return;
        }

        tbody.innerHTML = '';

        filas.forEach(function (fila) {

            const tr =
                document.createElement('tr');

            fila.forEach(function (valor) {

                const td =
                    document.createElement('td');

                td.innerHTML = valor;

                tr.appendChild(td);

            });

            tbody.appendChild(tr);

        });

    }

}


// =========================================================
// NUEVO
// =========================================================

function nuevoTipoAsistencia() {

    const form =
        document.getElementById(
            'formTipoAsistencia'
        );

    if (!form) {
        return;
    }

    form.reset();

    document.getElementById(
        'edit_tipo_asistencia_id'
    ).value = '';


    document.getElementById(
        'tipo_asistencia_nombre'
    ).value = '';


    document.getElementById(
        'tipo_asistencia_abreviatura'
    ).value = '';


    document.getElementById(
        'tipo_asistencia_descripcion'
    ).value = '';


    document.getElementById(
        'tipo_asistencia_color_fondo'
    ).value = '#008000';


    document.getElementById(
        'tipo_asistencia_color_fondo_hex'
    ).value = '#008000';


    document.getElementById(
        'tipo_asistencia_color_texto'
    ).value = '#000000';


    document.getElementById(
        'tipo_asistencia_color_texto_hex'
    ).value = '#000000';


    document.getElementById(
        'tipo_asistencia_computa_trabajo'
    ).checked = false;


    document.getElementById(
        'tipo_asistencia_computa_ausencia'
    ).checked = false;


    document.getElementById(
        'tipo_asistencia_modificable'
    ).checked = true;


    document.getElementById(
        'tipo_asistencia_visible_maestro'
    ).checked = false;


    actualizarVistaPrevia();


    document.getElementById(
        'ModalTipoAsistenciaLabel'
    ).textContent =
        'Nuevo Tipo de Asistencia';


    if (modalTipoAsistencia) {
        modalTipoAsistencia.show();
    }

}


// =========================================================
// EDITAR
// =========================================================

async function editarTipoAsistencia(
    idtipo_asistencia
) {

    try {

        const respuesta =
            await fetch(

                API_BASE +
                '/fichajes/tipos_asistencia/tipos_asistencias.php?idtipo_asistencia=' +
                encodeURIComponent(
                    idtipo_asistencia
                ),

                {
                    method: 'GET',
                    cache: 'no-store',
                    headers: obtenerHeadersSSO()
                }

            );


        const data =
            await respuesta.json();


        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudo obtener el tipo de asistencia.'
            );

        }


        const tipo =
            data.data;


        document.getElementById(
            'edit_tipo_asistencia_id'
        ).value =
            tipo.idtipo_asistencia || '';


        document.getElementById(
            'tipo_asistencia_nombre'
        ).value =
            tipo.nombre || '';


        document.getElementById(
            'tipo_asistencia_abreviatura'
        ).value =
            tipo.abreviatura || '';


        document.getElementById(
            'tipo_asistencia_descripcion'
        ).value =
            tipo.descripcion || '';


        const colorFondo =
            normalizarColor(
                tipo.color_fondo,
                '#008000'
            );


        const colorTexto =
            normalizarColor(
                tipo.color_texto,
                '#000000'
            );


        document.getElementById(
            'tipo_asistencia_color_fondo'
        ).value =
            convertirAHexColor(colorFondo);


        document.getElementById(
            'tipo_asistencia_color_fondo_hex'
        ).value =
            colorFondo;


        document.getElementById(
            'tipo_asistencia_color_texto'
        ).value =
            convertirAHexColor(colorTexto);


        document.getElementById(
            'tipo_asistencia_color_texto_hex'
        ).value =
            colorTexto;


        document.getElementById(
            'tipo_asistencia_computa_trabajo'
        ).checked =
            Number(
                tipo.computa_trabajo || 0
            ) === 1;


        document.getElementById(
            'tipo_asistencia_computa_ausencia'
        ).checked =
            Number(
                tipo.computa_ausencia || 0
            ) === 1;


        document.getElementById(
            'tipo_asistencia_modificable'
        ).checked =
            Number(
                tipo.modificable || 0
            ) === 1;


        document.getElementById(
            'tipo_asistencia_visible_maestro'
        ).checked =
            Number(
                tipo.visible_maestro || 0
            ) === 1;


        actualizarVistaPrevia();


        document.getElementById(
            'ModalTipoAsistenciaLabel'
        ).textContent =
            'Editar Tipo de Asistencia';


        if (modalTipoAsistencia) {
            modalTipoAsistencia.show();
        }


    } catch (error) {

        console.error(
            'Error al editar tipo de asistencia:',
            error
        );

        toast(
            error.message ||
            'Error al obtener el tipo de asistencia.',
            'error'
        );

    }

}


// =========================================================
// GUARDAR
// =========================================================

async function guardarTipoAsistencia() {

    const idtipo =
        document.getElementById(
            'edit_tipo_asistencia_id'
        ).value;


    const nombre =
        document.getElementById(
            'tipo_asistencia_nombre'
        ).value.trim();


    const abreviatura =
        document.getElementById(
            'tipo_asistencia_abreviatura'
        ).value.trim();


    const descripcion =
        document.getElementById(
            'tipo_asistencia_descripcion'
        ).value.trim();


    const colorFondo =
        document.getElementById(
            'tipo_asistencia_color_fondo_hex'
        ).value.trim();


    const colorTexto =
        document.getElementById(
            'tipo_asistencia_color_texto_hex'
        ).value.trim();


    const computaTrabajo =
        document.getElementById(
            'tipo_asistencia_computa_trabajo'
        ).checked
            ? 1
            : 0;


    const computaAusencia =
        document.getElementById(
            'tipo_asistencia_computa_ausencia'
        ).checked
            ? 1
            : 0;


    const modificable =
        document.getElementById(
            'tipo_asistencia_modificable'
        ).checked
            ? 1
            : 0;


    const visibleMaestro =
        document.getElementById(
            'tipo_asistencia_visible_maestro'
        ).checked
            ? 1
            : 0;


    if (!nombre) {

        toast(
            'Debe ingresar el nombre del tipo de asistencia.',
            'warning'
        );

        return;

    }


    if (!abreviatura) {

        toast(
            'Debe ingresar la abreviatura.',
            'warning'
        );

        return;

    }


    if (!colorValido(colorFondo)) {

        toast(
            'El color de fondo no es válido.',
            'warning'
        );

        return;

    }


    if (!colorValido(colorTexto)) {

        toast(
            'El color del texto no es válido.',
            'warning'
        );

        return;

    }


    const datos = {

        accion: 'guardar',

        idtipo_asistencia:
            idtipo
                ? Number(idtipo)
                : null,

        nombre:
            nombre,

        descripcion:
            descripcion || null,

        abreviatura:
            abreviatura,

        color_fondo:
            colorFondo,

        color_texto:
            colorTexto,

        computa_trabajo:
            computaTrabajo,

        computa_ausencia:
            computaAusencia,

        modificable:
            modificable,

        visible_maestro:
            visibleMaestro

    };


    const boton =
        document.getElementById(
            'btnGuardarTipoAsistencia'
        );


    try {

        if (boton) {
            boton.disabled = true;
        }


        const respuesta =
            await fetch(

                API_BASE +
                '/fichajes/tipos_asistencia/tipos_asistencias.php',

                {
                    method: 'POST',
                    cache: 'no-store',

                    headers: {

                        ...obtenerHeadersSSO(),

                        'Content-Type':
                            'application/json'

                    },

                    body:
                        JSON.stringify(datos)

                }

            );


        const data =
            await respuesta.json();


        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudo guardar el tipo de asistencia.'
            );

        }


        if (modalTipoAsistencia) {
            modalTipoAsistencia.hide();
        }


        toast(

            data.message ||
            data.mensaje ||
            'Tipo de asistencia guardado correctamente.',

            'success'

        );


        cargarTiposAsistencia();


    } catch (error) {

        console.error(
            'Error al guardar tipo de asistencia:',
            error
        );


        toast(

            error.message ||
            'Error al guardar el tipo de asistencia.',

            'error'

        );


    } finally {

        if (boton) {
            boton.disabled = false;
        }

    }

}


// =========================================================
// CAMBIAR ESTADO
// =========================================================

async function cambiarEstadoTipoAsistencia(
    idtipo_asistencia,
    nuevoEstado
) {

    const activo =
        Number(nuevoEstado) === 1;


    const resultado =
        await Swal.fire({

            title:
                activo
                    ? '¿Activar tipo de asistencia?'
                    : '¿Desactivar tipo de asistencia?',

            text:
                activo
                    ? 'El tipo volverá a estar disponible.'
                    : 'El tipo dejará de estar disponible para nuevas asignaciones.',

            icon: 'question',

            showCancelButton: true,

            confirmButtonText:
                activo
                    ? 'Sí, activar'
                    : 'Sí, desactivar',

            cancelButtonText:
                'Cancelar'

        });


    if (!resultado.isConfirmed) {
        return;
    }


    try {

        const respuesta =
            await fetch(

                API_BASE +
                '/fichajes/tipos_asistencia/tipos_asistencias.php',

                {

                    method: 'POST',

                    cache: 'no-store',

                    headers: {

                        ...obtenerHeadersSSO(),

                        'Content-Type':
                            'application/json'

                    },

                    body:
                        JSON.stringify({

                            accion:
                                'cambiar_estado',

                            idtipo_asistencia:
                                Number(
                                    idtipo_asistencia
                                ),

                            activo:
                                activo
                                    ? 1
                                    : 0

                        })

                }

            );


        const data =
            await respuesta.json();


        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(

                data.message ||
                data.mensaje ||
                'No se pudo cambiar el estado.'

            );

        }


        toast(

            data.message ||
            data.mensaje ||
            'Estado actualizado correctamente.',

            'success'

        );


        cargarTiposAsistencia();


    } catch (error) {

        console.error(
            'Error al cambiar estado:',
            error
        );


        toast(

            error.message ||
            'Error al cambiar el estado.',

            'error'

        );

    }

}


// =========================================================
// COLORES
// =========================================================

function inicializarColores() {

    const colorFondo =
        document.getElementById(
            'tipo_asistencia_color_fondo'
        );


    const colorFondoHex =
        document.getElementById(
            'tipo_asistencia_color_fondo_hex'
        );


    const colorTexto =
        document.getElementById(
            'tipo_asistencia_color_texto'
        );


    const colorTextoHex =
        document.getElementById(
            'tipo_asistencia_color_texto_hex'
        );


    if (colorFondo) {

        colorFondo.addEventListener(
            'input',
            function () {

                colorFondoHex.value =
                    this.value.toUpperCase();

                actualizarVistaPrevia();

            }
        );

    }


    if (colorFondoHex) {

        colorFondoHex.addEventListener(
            'input',
            function () {

                const color =
                    normalizarColor(
                        this.value,
                        null
                    );

                if (color) {

                    colorFondo.value =
                        convertirAHexColor(color);

                }

                actualizarVistaPrevia();

            }
        );

    }


    if (colorTexto) {

        colorTexto.addEventListener(
            'input',
            function () {

                colorTextoHex.value =
                    this.value.toUpperCase();

                actualizarVistaPrevia();

            }
        );

    }


    if (colorTextoHex) {

        colorTextoHex.addEventListener(
            'input',
            function () {

                const color =
                    normalizarColor(
                        this.value,
                        null
                    );

                if (color) {

                    colorTexto.value =
                        convertirAHexColor(color);

                }

                actualizarVistaPrevia();

            }
        );

    }


    const abreviatura =
        document.getElementById(
            'tipo_asistencia_abreviatura'
        );


    if (abreviatura) {

        abreviatura.addEventListener(
            'input',
            actualizarVistaPrevia
        );

    }

}


// =========================================================
// VISTA PREVIA
// =========================================================

function actualizarVistaPrevia() {

    const preview =
        document.getElementById(
            'vistaPreviaTipoAsistencia'
        );


    const texto =
        document.getElementById(
            'vistaPreviaAbreviatura'
        );


    if (!preview || !texto) {
        return;
    }


    const fondo =
        normalizarColor(

            document.getElementById(
                'tipo_asistencia_color_fondo_hex'
            )?.value,

            '#008000'

        );


    const color =
        normalizarColor(

            document.getElementById(
                'tipo_asistencia_color_texto_hex'
            )?.value,

            '#000000'

        );


    const abreviatura =
        document.getElementById(
            'tipo_asistencia_abreviatura'
        )?.value || 'P';


    preview.style.backgroundColor =
        fondo;


    preview.style.color =
        color;


    texto.textContent =
        abreviatura;

}


// =========================================================
// COLORES UTILIDADES
// =========================================================

function colorValido(valor) {

    const color =
        String(valor || '').trim();

    if (!color) {
        return false;
    }


    /*
     * Se permite HEX:
     * #RGB
     * #RRGGBB
     */

    if (
        /^#[0-9A-Fa-f]{3}$/.test(color) ||
        /^#[0-9A-Fa-f]{6}$/.test(color)
    ) {

        return true;

    }


    /*
     * También se permite mantener
     * nombres CSS existentes.
     */

    if (
        /^[a-zA-Z]+$/.test(color)
    ) {

        return true;

    }


    return false;

}


function normalizarColor(
    valor,
    valorPorDefecto = '#000000'
) {

    const color =
        String(valor || '').trim();


    if (!color) {
        return valorPorDefecto;
    }


    if (
        /^#[0-9A-Fa-f]{3}$/.test(color) ||
        /^#[0-9A-Fa-f]{6}$/.test(color)
    ) {

        return color.toUpperCase();

    }


    if (
        /^[a-zA-Z]+$/.test(color)
    ) {

        return color;

    }


    return valorPorDefecto;

}


function convertirAHexColor(valor) {

    const color =
        String(valor || '').trim();


    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
        return color.toUpperCase();
    }


    if (/^#[0-9A-Fa-f]{3}$/.test(color)) {

        const r = color.charAt(1);
        const g = color.charAt(2);
        const b = color.charAt(3);

        return (
            '#' +
            r + r +
            g + g +
            b + b
        ).toUpperCase();

    }


    /*
     * El input type=color solamente
     * acepta HEX. Para nombres CSS,
     * dejamos un valor seguro para el
     * selector y conservamos el nombre
     * en el campo de texto.
     */

    return '#000000';

}


// =========================================================
// UTILIDADES
// =========================================================

function escapeHtml(valor) {

    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}


function escapeAttribute(valor) {

    return escapeHtml(valor);

}