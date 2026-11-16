let tablaSectores = null;
let sectoresList = [];
let guardandoSector = false;

const API_SECTORES = API_BASE + '/rrhh/organizacion/sectores.php';

document.addEventListener('DOMContentLoaded', () => {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTable();
    cargarSectores();

    $('#btnGuardarSector').on('click', guardarSector);

});

// ================================================================
// DATATABLE
// ================================================================

function initDataTable() {

    if ($.fn.DataTable.isDataTable('#tablaSectores')) {
        $('#tablaSectores').DataTable().destroy();
    }

    tablaSectores = $('#tablaSectores').DataTable({

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

async function cargarSectores() {

    try {

        const res = await fetch(
            API_SECTORES + '?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg || 'Error al cargar sectores.',
                'error'
            );

            return;
        }

        sectoresList = json.data || [];

        tablaSectores.clear();

        sectoresList.forEach(s => {

            const activo = Number(s.activo) === 1;

            const badgeEstado = activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const btnEditar = `
                <button
                    class="btn btn-sm btn-info text-white me-1"
                    onclick="editarSector(${Number(s.idsector)})">
                    Editar
                </button>
            `;

            const btnEstado = activo
                ? `
                    <button
                        class="btn btn-sm btn-danger"
                        onclick="cambiarEstadoSector(${Number(s.idsector)}, 0)">
                        Desactivar
                    </button>
                `
                : `
                    <button
                        class="btn btn-sm btn-success"
                        onclick="cambiarEstadoSector(${Number(s.idsector)}, 1)">
                        Activar
                    </button>
                `;

            tablaSectores.row.add([

                `<strong>${escapeHtml(s.nombre)}</strong>`,

                escapeHtml(s.descripcion || '—'),

                `<div class="text-center">${badgeEstado}</div>`,

                `<div class="text-center">${btnEditar}${btnEstado}</div>`

            ]);

        });

        tablaSectores.draw();

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al obtener sectores.',
            'error'
        );

    }

}

// ================================================================
// NUEVO
// ================================================================

function abrirNuevoSector() {

    $('#formSector')[0].reset();

    $('#edit_sector_id').val('');

    $('.modal-title').text('Nuevo Sector');

    $('#btnGuardarSector')
        .text('Crear Sector')
        .prop('disabled', false);

    guardandoSector = false;

    $('#ModalSector').modal('show');

}

// ================================================================
// EDITAR
// ================================================================

function editarSector(id) {

    const sector = sectoresList.find(
        s => Number(s.idsector) === Number(id)
    );

    if (!sector) {

        toast('Sector no encontrado.', 'error');

        return;
    }

    $('#edit_sector_id').val(sector.idsector);

    $('#sector_nombre').val(sector.nombre);

    $('#sector_descripcion').val(sector.descripcion || '');

    $('.modal-title').text('Editar Sector');

    $('#btnGuardarSector')
        .text('Guardar Cambios')
        .prop('disabled', false);

    guardandoSector = false;

    $('#ModalSector').modal('show');

}

// ================================================================
// GUARDAR
// ================================================================

async function guardarSector() {

    if (guardandoSector) {
        return;
    }

    const id = $('#edit_sector_id').val();

    const nombre = $('#sector_nombre').val().trim();

    const descripcion = $('#sector_descripcion').val().trim();

    if (!nombre) {

        toast('Ingrese el nombre del sector.', 'warning');

        return;
    }

    const action = id ? 'editar' : 'crear';

    const formData = new FormData();

    formData.append('nombre', nombre);
    formData.append('descripcion', descripcion);

    if (id) {
        formData.append('idsector', id);
    }

    guardandoSector = true;

    $('#btnGuardarSector').prop('disabled', true);

    try {

        const res = await fetch(
            API_SECTORES + `?action=${action}`,
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg || 'No se pudo guardar el sector.',
                'error'
            );

            return;
        }

        $('#ModalSector').modal('hide');

        toast(
            json.msg || 'Sector guardado correctamente.',
            'success'
        );

        cargarSectores();

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al guardar el sector.',
            'error'
        );

    } finally {

        guardandoSector = false;

        $('#btnGuardarSector').prop('disabled', false);

    }

}

// ================================================================
// CAMBIAR ESTADO
// ================================================================

function cambiarEstadoSector(id, nuevoEstado) {

    const accionTexto = nuevoEstado === 1
        ? 'activar'
        : 'desactivar';

    Swal.fire({

        title: '¿Está seguro?',

        text: `¿Desea ${accionTexto} este sector?`,

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

        formData.append('idsector', id);
        formData.append('activo', nuevoEstado);

        try {

            const res = await fetch(
                API_SECTORES + '?action=estado',
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

            cargarSectores();

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