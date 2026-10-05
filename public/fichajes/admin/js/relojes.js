// =========================================================
// relojes.js
// Administración de relojes de fichaje
// =========================================================

let tablaRelojes = null;
let relojesList = [];
let agentesList = [];


// =========================================================
// INICIO
// =========================================================

$(document).ready(function () {

    if (typeof validarSSO === 'function') {
        validarSSO();
    }

    inicializarTablaRelojes();

    cargarAgentes()
        .then(() => {
            cargarRelojes();
        });

    $('#formReloj').on('submit', function (e) {
        e.preventDefault();
        guardarReloj();
    });

});


// =========================================================
// DATATABLE
// =========================================================

function inicializarTablaRelojes() {

    tablaRelojes = $('#tablaRelojes').DataTable({

        responsive: true,

        pageLength: 25,

        order: [
            [0, 'asc']
        ],

        columns: [

            // -------------------------------------------------
            // 1. NOMBRE
            // -------------------------------------------------
            {
                data: 'nombre',

                render: function (data, type, row) {

                    const nombre = escapeHtml(data || '');

                    const estrella = Number(row.predeterminado) === 1
                        ? `
                            <i
                                class="fas fa-star text-warning ms-1"
                                title="Reloj predeterminado">
                            </i>
                          `
                        : '';

                    return `
                        <strong>
                            ${nombre}
                        </strong>
                        ${estrella}
                    `;
                }
            },


            // -------------------------------------------------
            // 2. MARCA
            // -------------------------------------------------
            {
                data: 'marca',

                render: function (data) {

                    return data
                        ? escapeHtml(data)
                        : '<span class="text-muted">—</span>';
                }
            },


            // -------------------------------------------------
            // 3. MODELO
            // -------------------------------------------------
            {
                data: 'modelo',

                render: function (data) {

                    return data
                        ? escapeHtml(data)
                        : '<span class="text-muted">—</span>';
                }
            },


            // -------------------------------------------------
            // 4. IP
            // -------------------------------------------------
            {
                data: 'ip',

                render: function (data) {

                    return `
                        <code>
                            ${escapeHtml(data || '')}
                        </code>
                    `;
                }
            },


            // -------------------------------------------------
            // 5. PUERTO
            // -------------------------------------------------
            {
                data: 'puerto',

                className: 'text-center'
            },


            // -------------------------------------------------
            // 6. UBICACIÓN
            // -------------------------------------------------
            {
                data: 'ubicacion',

                render: function (data) {

                    return data
                        ? escapeHtml(data)
                        : '<span class="text-muted">—</span>';
                }
            },


            // -------------------------------------------------
            // 7. AGENT
            // -------------------------------------------------
            {
                data: null,

                render: function (data) {

                    if (!data.idagente) {

                        return `
                            <span class="text-muted">
                                Sin Agent
                            </span>
                        `;
                    }

                    return `
                        <div>
                            <strong>
                                ${escapeHtml(
                                    data.agente_nombre || 'Agent'
                                )}
                            </strong>

                            ${
                                data.agent_id
                                    ? `
                                        <div class="small text-muted">
                                            ${escapeHtml(data.agent_id)}
                                        </div>
                                      `
                                    : ''
                            }
                        </div>
                    `;
                }
            },


            // -------------------------------------------------
            // 8. ESTADO
            // -------------------------------------------------
            {
                data: 'activo',

                className: 'text-center',

                render: function (data) {

                    if (Number(data) === 1) {

                        return `
                            <span class="badge bg-success">
                                Activo
                            </span>
                        `;

                    }

                    return `
                        <span class="badge bg-secondary">
                            Inactivo
                        </span>
                    `;
                }
            },


            // -------------------------------------------------
            // 9. ACCIONES
            // -------------------------------------------------
            {
                data: null,

                orderable: false,

                searchable: false,

                className: 'text-center',

                render: function (data) {

                    const id = Number(data.idlector);

                    return `

                        <div
                            class="btn-group btn-group-sm"
                            role="group">

                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                title="Editar"
                                onclick="editarReloj(${id})"
                                data-permiso="lectores_editar">

                                <i class="fas fa-edit"></i>

                            </button>

                            <button
                                type="button"
                                class="btn ${
                                    Number(data.activo) === 1
                                        ? 'btn-outline-warning'
                                        : 'btn-outline-success'
                                }"
                                title="${
                                    Number(data.activo) === 1
                                        ? 'Desactivar'
                                        : 'Activar'
                                }"
                                onclick="cambiarEstadoReloj(
                                    ${id},
                                    ${
                                        Number(data.activo) === 1
                                            ? 0
                                            : 1
                                    }
                                )"
                                data-permiso="lectores_editar">

                                <i class="fas ${
                                    Number(data.activo) === 1
                                        ? 'fa-power-off'
                                        : 'fa-check'
                                }"></i>

                            </button>

                        </div>
                    `;
                }
            }

        ]

    });

}


// =========================================================
// CARGAR AGENTES
// =========================================================

async function cargarAgentes() {

    try {

        const respuesta = await fetch(
            API_BASE + '/fichajes/lectores/lectores.php?action=agentes',
            {
                method: 'GET',
                cache: 'no-store',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await respuesta.json();

        if (data.status !== 'ok') {

            toast(
                data.mensaje || 'No se pudieron cargar los Agents.',
                'error'
            );

            return;
        }

        agentesList = data.data || [];

        const select = $('#reloj_idagente');

        select.empty();

        select.append(`
            <option value="">
                Sin Agent
            </option>
        `);

        agentesList.forEach(function (agente) {

            select.append(`
                <option value="${agente.idagente}">
                    ${escapeHtml(agente.nombre)}
                    ${
                        agente.agent_id
                            ? ` — ${escapeHtml(agente.agent_id)}`
                            : ''
                    }
                </option>
            `);

        });

    } catch (error) {

        console.error(
            'Error cargando Agents:',
            error
        );

        toast(
            'No se pudieron cargar los Agents.',
            'error'
        );
    }

}


// =========================================================
// CARGAR RELOJES
// =========================================================

async function cargarRelojes() {

    try {

        const respuesta = await fetch(
            API_BASE + '/fichajes/lectores/lectores.php?action=listar',
            {
                method: 'GET',
                cache: 'no-store',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await respuesta.json();

        if (data.status !== 'ok') {

            toast(
                data.mensaje || 'No se pudieron cargar los relojes.',
                'error'
            );

            return;
        }

        relojesList = data.data || [];

        tablaRelojes.clear();

        tablaRelojes
            .rows
            .add(relojesList);

        tablaRelojes.draw();

    } catch (error) {

        console.error(
            'Error cargando relojes:',
            error
        );

        toast(
            'No se pudieron cargar los relojes.',
            'error'
        );
    }

}


// =========================================================
// NUEVO RELOJ
// =========================================================

function abrirNuevoReloj() {

    $('#formReloj')[0].reset();

    $('#reloj_idlector').val('');

    $('#reloj_puerto').val('4370');

    $('#reloj_idagente').val('');

    $('#reloj_predeterminado').prop(
        'checked',
        false
    );

    $('#tituloModalReloj').text(
        'Nuevo Reloj'
    );

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('ModalReloj')
    );

    modal.show();

}


// =========================================================
// EDITAR RELOJ
// =========================================================

function editarReloj(idlector) {

    const reloj = relojesList.find(function (item) {

        return Number(item.idlector) === Number(idlector);

    });

    if (!reloj) {

        toast(
            'No se encontró el reloj seleccionado.',
            'error'
        );

        return;
    }

    $('#reloj_idlector').val(
        reloj.idlector
    );

    $('#reloj_nombre').val(
        reloj.nombre || ''
    );

    $('#reloj_marca').val(
        reloj.marca || ''
    );

    $('#reloj_modelo').val(
        reloj.modelo || ''
    );

    $('#reloj_ip').val(
        reloj.ip || ''
    );

    $('#reloj_puerto').val(
        reloj.puerto || 4370
    );

    $('#reloj_ubicacion').val(
        reloj.ubicacion || ''
    );

    $('#reloj_tipo_uso').val(
        reloj.tipo_uso || ''
    );

    $('#reloj_idagente').val(
        reloj.idagente !== null
            ? String(reloj.idagente)
            : ''
    );

    $('#reloj_predeterminado').prop(
        'checked',
        Number(reloj.predeterminado) === 1
    );

    $('#tituloModalReloj').text(
        'Editar Reloj'
    );

    const modal = bootstrap.Modal.getOrCreateInstance(
        document.getElementById('ModalReloj')
    );

    modal.show();

}


// =========================================================
// GUARDAR RELOJ
// =========================================================

async function guardarReloj() {

    const form = document.getElementById(
        'formReloj'
    );

    if (!form.checkValidity()) {

        form.reportValidity();

        return;
    }

    const formData = new FormData(form);

    const idlector = $('#reloj_idlector').val();

    const accion = idlector
        ? 'editar'
        : 'crear';

    formData.append(
        'action',
        accion
    );

    const boton = $('#btnGuardarReloj');

    const textoOriginal = boton.html();

    boton.prop(
        'disabled',
        true
    );

    boton.html(`
        <span
            class="spinner-border spinner-border-sm me-2"
            role="status">
        </span>

        Guardando...
    `);

    try {

        const respuesta = await fetch(
            API_BASE + '/fichajes/lectores/lectores.php',
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const data = await respuesta.json();

        if (data.status !== 'ok') {

            toast(
                data.mensaje || 'No se pudo guardar el reloj.',
                'error'
            );

            return;
        }

        const modal = bootstrap.Modal.getInstance(
            document.getElementById('ModalReloj')
        );

        if (modal) {
            modal.hide();
        }

        toast(
            data.mensaje || 'Reloj guardado correctamente.',
            'success'
        );

        await cargarRelojes();

    } catch (error) {

        console.error(
            'Error guardando reloj:',
            error
        );

        toast(
            'Ocurrió un error al guardar el reloj.',
            'error'
        );

    } finally {

        boton.prop(
            'disabled',
            false
        );

        boton.html(
            textoOriginal
        );
    }

}


// =========================================================
// CAMBIAR ESTADO
// =========================================================

function cambiarEstadoReloj(
    idlector,
    nuevoEstado
) {

    const reloj = relojesList.find(function (item) {

        return Number(item.idlector) === Number(idlector);

    });

    if (!reloj) {

        toast(
            'No se encontró el reloj seleccionado.',
            'error'
        );

        return;
    }

    const activar = Number(nuevoEstado) === 1;

    Swal.fire({

        title: activar
            ? '¿Activar reloj?'
            : '¿Desactivar reloj?',

        text: activar
            ? `Se activará el reloj "${reloj.nombre}".`
            : `Se desactivará el reloj "${reloj.nombre}".`,

        icon: 'question',

        showCancelButton: true,

        confirmButtonText: activar
            ? 'Sí, activar'
            : 'Sí, desactivar',

        cancelButtonText: 'Cancelar',

        reverseButtons: true

    }).then(async function (resultado) {

        if (!resultado.isConfirmed) {
            return;
        }

        const formData = new FormData();

        formData.append(
            'action',
            'estado'
        );

        formData.append(
            'idlector',
            idlector
        );

        formData.append(
            'activo',
            nuevoEstado
        );

        try {

            const respuesta = await fetch(
                API_BASE + '/fichajes/lectores/lectores.php',
                {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                }
            );

            const data = await respuesta.json();

            if (data.status !== 'ok') {

                toast(
                    data.mensaje ||
                    'No se pudo cambiar el estado.',
                    'error'
                );

                return;
            }

            toast(
                data.mensaje ||
                'Estado actualizado correctamente.',
                'success'
            );

            await cargarRelojes();

        } catch (error) {

            console.error(
                'Error cambiando estado:',
                error
            );

            toast(
                'No se pudo cambiar el estado del reloj.',
                'error'
            );
        }

    });

}


// =========================================================
// ESCAPE HTML
// =========================================================

function escapeHtml(valor) {

    if (valor === null || valor === undefined) {
        return '';
    }

    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}