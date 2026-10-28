let tablaAgentes = null;
let agentesList = [];
let guardandoAgente = false;

document.addEventListener('DOMContentLoaded', () => {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTable();
    cargarAgentes();

    // UN SOLO manejador para el botón
    $('#btnGuardarAgente').on('click', guardarAgente);
});

const empresaActiva = JSON.parse(
    localStorage.getItem('sso_empresa_activa') || '{}'
);

const nombreEmpresa = empresaActiva.nombre || '';

// ================================================================
// DATATABLE
// ================================================================

function initDataTable() {

    if ($.fn.DataTable.isDataTable('#tablaAgentes')) {
        $('#tablaAgentes').DataTable().destroy();
    }

    tablaAgentes = $('#tablaAgentes').DataTable({

        language: {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            }
        },

        responsive: true,
        autoWidth: false
    });
}


// ================================================================
// LISTAR
// ================================================================

async function cargarAgentes() {

    try {

        const res = await fetch(
            API_BASE + '/fichajes/agentes/agentes.php?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg || "Error al cargar agentes",
                "error"
            );

            return;
        }

        agentesList = json.data || [];

        tablaAgentes.clear();

        agentesList.forEach(a => {

            const activo = a.activo == 1;

            const badgeEstado = activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const ultimoAcceso = a.ultimo_acceso
                ? a.ultimo_acceso
                : '<span class="text-muted small">Nunca</span>';

            const ultimaActividad = a.ultima_actividad
                ? a.ultima_actividad
                : '<span class="text-muted small">Nunca</span>';

            const btnEditar = `
                <button
                    class="btn btn-sm btn-info text-white me-1"
                    onclick="editarAgente(${a.idagente})"
                    title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
            `;

            const btnEstado = activo
                ? `
                    <button
                        class="btn btn-sm btn-danger"
                        onclick="cambiarEstadoAgente(${a.idagente}, 0)"
                        title="Desactivar">
                        <i class="fas fa-power-off"></i>
                    </button>
                `
                : `
                    <button
                        class="btn btn-sm btn-success"
                        onclick="cambiarEstadoAgente(${a.idagente}, 1)"
                        title="Activar">
                        <i class="fas fa-power-off"></i>
                    </button>
                `;

            tablaAgentes.row.add([

                `<strong>${escapeHtml(a.nombre)}</strong>`,

                escapeHtml(a.empresa_nombre || ''),

                `<span class="badge bg-secondary">${escapeHtml(a.agent_id)}</span>`,

                `<div class="text-center">${badgeEstado}</div>`,

                ultimoAcceso,

                ultimaActividad,

                `<div class="text-center">${btnEditar}${btnEstado}</div>`
            ]);
        });

        tablaAgentes.draw();

    } catch (err) {

        console.error(err);

        toast(
            "Error de conexión al obtener agentes",
            "error"
        );
    }
}


// ================================================================
// NUEVO
// ================================================================

function abrirNuevoAgente() {

    $('#formAgente')[0].reset();

    $('#edit_agente_id').val('');

    $('#agt_nombre')
        .prop('readonly', false)
        .val('');

        
    $('#agt_empresa')
        .val(nombreEmpresa || '');

    $('#agt_agent_id')
        .val('');

    $('#contenedorCredenciales')
        .addClass('d-none');

    $('#cred_agent_id').val('');
    $('#cred_token').val('');

    $('.modal-title')
        .text('Crear Nuevo Agente');

    $('#btnGuardarAgente')
        .text('Crear Agente')
        .prop('disabled', false);

    guardandoAgente = false;

    $('#ModalAgente').modal('show');
}


// ================================================================
// EDITAR
// ================================================================

function editarAgente(id) {

    const agente = agentesList.find(
        a => a.idagente == id
    );

    if (!agente) {

        toast(
            "Agente no encontrado.",
            "error"
        );

        return;
    }

    $('#edit_agente_id')
        .val(agente.idagente);

    $('#agt_nombre')
        .prop('readonly', false)
        .val(agente.nombre);

    $('#agt_empresa')
        .val(
            agente.empresa_nombre ||
            nombreEmpresa ||
            ''
        );

    $('#agt_agent_id')
        .val(agente.agent_id);

    $('#contenedorCredenciales')
        .addClass('d-none');

    $('#cred_agent_id').val('');
    $('#cred_token').val('');

    $('.modal-title')
        .text(`Editar Agente: ${agente.nombre}`);

    $('#btnGuardarAgente')
        .text('Guardar Cambios')
        .prop('disabled', false);

    guardandoAgente = false;

    $('#ModalAgente').modal('show');
}


// ================================================================
// GUARDAR
// ================================================================

async function guardarAgente() {

    // Evita doble clic
    if (guardandoAgente) {
        return;
    }

    const nombre = $('#agt_nombre')
        .val()
        .trim();

    const id = $('#edit_agente_id')
        .val();

    if (!nombre) {

        toast(
            "Ingrese el nombre del agente.",
            "warning"
        );

        return;
    }

    const action = id
        ? 'editar'
        : 'crear';

    const formData = new FormData();

    formData.append(
        'nombre',
        nombre
    );

    if (id) {

        formData.append(
            'idagente',
            id
        );
    }

    guardandoAgente = true;

    $('#btnGuardarAgente')
        .prop('disabled', true);

    try {

        const res = await fetch(
            API_BASE +
            `/fichajes/agentes/agentes.php?action=${action}`,
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            guardandoAgente = false;

            $('#btnGuardarAgente')
                .prop('disabled', false);

            toast(
                json.msg ||
                "No se pudo guardar el agente.",
                "error"
            );

            return;
        }


        // ========================================================
        // CREAR
        // ========================================================

        if (action === 'crear') {

            $('#cred_agent_id')
                .val(json.agent_id);

            $('#cred_token')
                .val(json.token);

            $('#agt_agent_id')
                .val(json.agent_id);

            $('#contenedorCredenciales')
                .removeClass('d-none');

            /*
             * Una vez creado:
             * - no se vuelve a enviar
             * - el botón simplemente cierra
             */

            guardandoAgente = false;

            $('#btnGuardarAgente')
                .text('Cerrar')
                .prop('disabled', false)
                .off('click')
                .on('click', function () {

                    $('#ModalAgente').modal('hide');

                });

            toast(
                "Agente creado correctamente.",
                "success"
            );

            cargarAgentes();

            return;
        }


        // ========================================================
        // EDITAR
        // ========================================================

        guardandoAgente = false;

        $('#ModalAgente')
            .modal('hide');

        toast(
            json.msg ||
            "Agente actualizado correctamente.",
            "success"
        );

        cargarAgentes();

    } catch (err) {

        console.error(err);

        guardandoAgente = false;

        $('#btnGuardarAgente')
            .prop('disabled', false);

        toast(
            "Error de conexión al guardar el agente.",
            "error"
        );
    }
}


// ================================================================
// CAMBIAR ESTADO
// ================================================================

function cambiarEstadoAgente(id, nuevoEstado) {

    const accionTexto =
        nuevoEstado === 1
            ? 'activar'
            : 'desactivar';

    Swal.fire({

        title: '¿Está seguro?',

        text:
            `¿Desea ${accionTexto} este agente?`,

        icon: 'question',

        showCancelButton: true,

        confirmButtonColor:
            nuevoEstado === 1
                ? '#28a745'
                : '#d33',

        cancelButtonColor: '#6c757d',

        confirmButtonText:
            'Sí, confirmar',

        cancelButtonText:
            'Cancelar'

    }).then(async result => {

        if (!result.isConfirmed) {
            return;
        }

        const formData = new FormData();

        formData.append(
            'idagente',
            id
        );

        formData.append(
            'activo',
            nuevoEstado
        );

        try {

            const res = await fetch(
                API_BASE +
                '/fichajes/agentes/agentes.php?action=estado',
                {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                }
            );

            const json = await res.json();

            if (json.status !== 'ok') {

                toast(
                    json.msg ||
                    "No se pudo cambiar el estado.",
                    "error"
                );

                return;
            }

            toast(
                json.msg,
                "success"
            );

            cargarAgentes();

        } catch (err) {

            console.error(err);

            toast(
                "Error de conexión al cambiar el estado.",
                "error"
            );
        }
    });
}


// ================================================================
// COPIAR CREDENCIAL
// ================================================================

async function copiarTexto(id) {

    const elemento =
        document.getElementById(id);

    if (!elemento) {
        return;
    }

    try {

        await navigator.clipboard.writeText(
            elemento.value
        );

        toast(
            "Copiado al portapapeles.",
            "success"
        );

    } catch (err) {

        elemento.select();

        document.execCommand('copy');

        toast(
            "Copiado al portapapeles.",
            "success"
        );
    }
}


// ================================================================
// ESCAPAR HTML
// ================================================================

function escapeHtml(valor) {

    if (
        valor === null ||
        valor === undefined
    ) {
        return '';
    }

    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}