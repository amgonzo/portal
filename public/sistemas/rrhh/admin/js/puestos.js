let tablaPuestos = null;
let puestosList = [];
let guardandoPuesto = false;

const API_PUESTOS = API_BASE + '/rrhh/organizacion/puestos.php';

document.addEventListener('DOMContentLoaded', () => {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTable();
    cargarPuestos();

    $('#btnGuardarPuesto').on('click', guardarPuesto);

});

// ================================================================
// DATATABLE
// ================================================================

function initDataTable() {

    if ($.fn.DataTable.isDataTable('#tablaPuestos')) {
        $('#tablaPuestos').DataTable().destroy();
    }

    tablaPuestos = $('#tablaPuestos').DataTable({

        language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "Ningún dato disponible en esta tabla",
            sInfo: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            sInfoEmpty: "Mostrando registros del 0 al 0",
            sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
            sSearch: "Buscar:",
            sLoadingRecords: "Cargando...",
            oPaginate: {
                sFirst: "Primero",
                sLast: "Último",
                sNext: "Siguiente",
                sPrevious: "Anterior"
            }
        },

        responsive: true,
        autoWidth: false

    });
}

// ================================================================
// LISTAR
// ================================================================

async function cargarPuestos() {

    try {

        const res = await fetch(
            API_PUESTOS + '?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg || 'Error al cargar puestos.',
                'error'
            );

            return;
        }

        puestosList = json.data || [];

        tablaPuestos.clear();

        puestosList.forEach(p => {

            const activo = Number(p.activo) === 1;

            const badgeEstado = activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const btnEditar = `
                <button
                    class="btn btn-sm btn-info text-white me-1"
                    onclick="editarPuesto(${Number(p.idpuesto)})">
                    Editar
                </button>
            `;

            const btnEstado = activo
                ? `
                    <button
                        class="btn btn-sm btn-danger"
                        onclick="cambiarEstadoPuesto(${Number(p.idpuesto)}, 0)">
                        Desactivar
                    </button>
                `
                : `
                    <button
                        class="btn btn-sm btn-success"
                        onclick="cambiarEstadoPuesto(${Number(p.idpuesto)}, 1)">
                        Activar
                    </button>
                `;

            tablaPuestos.row.add([

                `<strong>${escapeHtmlPuesto(p.nombre)}</strong>`,

                escapeHtmlPuesto(p.descripcion || '—'),

                `<div class="text-center">${badgeEstado}</div>`,

                `<div class="text-center">${btnEditar}${btnEstado}</div>`

            ]);

        });

        tablaPuestos.draw();

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al obtener puestos.',
            'error'
        );

    }

}

// ================================================================
// NUEVO
// ================================================================

function abrirNuevoPuesto() {

    $('#formPuesto')[0].reset();

    $('#edit_puesto_id').val('');

    $('#ModalPuesto .modal-title').text('Nuevo Puesto');

    $('#btnGuardarPuesto')
        .text('Crear Puesto')
        .prop('disabled', false);

    guardandoPuesto = false;

    $('#ModalPuesto').modal('show');

}

// ================================================================
// EDITAR
// ================================================================

function editarPuesto(id) {

    const puesto = puestosList.find(
        p => Number(p.idpuesto) === Number(id)
    );

    if (!puesto) {

        toast('Puesto no encontrado.', 'error');

        return;
    }

    $('#edit_puesto_id').val(puesto.idpuesto);

    $('#puesto_nombre').val(puesto.nombre);

    $('#puesto_descripcion').val(puesto.descripcion || '');

    $('#ModalPuesto .modal-title').text('Editar Puesto');

    $('#btnGuardarPuesto')
        .text('Guardar Cambios')
        .prop('disabled', false);

    guardandoPuesto = false;

    $('#ModalPuesto').modal('show');

}

// ================================================================
// GUARDAR
// ================================================================

async function guardarPuesto() {

    if (guardandoPuesto) {
        return;
    }

    const id = $('#edit_puesto_id').val();

    const nombre = $('#puesto_nombre').val().trim();

    const descripcion = $('#puesto_descripcion').val().trim();

    if (!nombre) {

        toast('Ingrese el nombre del puesto.', 'warning');

        return;
    }

    const action = id ? 'editar' : 'crear';

    const formData = new FormData();

    formData.append('nombre', nombre);
    formData.append('descripcion', descripcion);

    if (id) {
        formData.append('idpuesto', id);
    }

    guardandoPuesto = true;

    $('#btnGuardarPuesto').prop('disabled', true);

    try {

        const res = await fetch(
            API_PUESTOS + `?action=${action}`,
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg || 'No se pudo guardar el puesto.',
                'error'
            );

            return;
        }

        $('#ModalPuesto').modal('hide');

        toast(
            json.msg || 'Puesto guardado correctamente.',
            'success'
        );

        cargarPuestos();

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al guardar el puesto.',
            'error'
        );

    } finally {

        guardandoPuesto = false;

        $('#btnGuardarPuesto').prop('disabled', false);

    }

}

// ================================================================
// CAMBIAR ESTADO
// ================================================================

function cambiarEstadoPuesto(id, nuevoEstado) {

    const accionTexto = nuevoEstado === 1
        ? 'activar'
        : 'desactivar';

    Swal.fire({

        title: '¿Está seguro?',

        text: `¿Desea ${accionTexto} este puesto?`,

        icon: 'question',

        showCancelButton: true,

        confirmButtonColor: nuevoEstado === 1 ? '#28a745' : '#d33',

        cancelButtonColor: '#6c757d',

        confirmButtonText: 'Sí, confirmar',

        cancelButtonText: 'Cancelar'

    }).then(async result => {

        if (!result.isConfirmed) {
            return;
        }

        const formData = new FormData();

        formData.append('idpuesto', id);
        formData.append('activo', nuevoEstado);

        try {

            const res = await fetch(
                API_PUESTOS + '?action=estado',
                {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                }
            );

            const json = await res.json();

            if (json.status !== 'ok') {

                toast(
                    json.msg || 'No se pudo cambiar el estado.',
                    'error'
                );

                return;
            }

            toast(json.msg, 'success');

            cargarPuestos();

        } catch (err) {

            console.error(err);

            toast(
                'Error de conexión al cambiar el estado.',
                'error'
            );

        }

    });

}

// ================================================================
// ESCAPAR HTML
// ================================================================

function escapeHtmlPuesto(valor) {

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