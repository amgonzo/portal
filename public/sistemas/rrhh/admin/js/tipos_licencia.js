let tablaTiposLicencia = null;
let tiposLicenciaList = [];
let guardandoTipoLicencia = false;

const API_TIPOS_LICENCIA =
    API_BASE + '/rrhh/organizacion/tipos_licencia.php';

document.addEventListener('DOMContentLoaded', () => {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTableTiposLicencia();
    cargarTiposLicencia();

    $('#btnGuardarTipoLicencia').on('click', guardarTipoLicencia);

});

// ================================================================
// DATATABLE
// ================================================================

function initDataTableTiposLicencia() {

    if ($.fn.DataTable.isDataTable('#tablaTiposLicencia')) {
        $('#tablaTiposLicencia').DataTable().destroy();
    }

    tablaTiposLicencia = $('#tablaTiposLicencia').DataTable({

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

async function cargarTiposLicencia() {

    try {

        const res = await fetch(
            API_TIPOS_LICENCIA + '?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || 'Error al cargar tipos de licencia.', 'error');
            return;
        }

        tiposLicenciaList = json.data || [];

        tablaTiposLicencia.clear();

        tiposLicenciaList.forEach(tipo => {

            const activo = Number(tipo.activo) === 1;

            const badgeEstado = activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const documentacion = Number(tipo.requiere_documentacion) === 1
                ? '<span class="badge bg-info">Sí</span>'
                : '<span class="badge bg-secondary">No</span>';

            const vacaciones = Number(tipo.descuenta_vacaciones) === 1
                ? '<span class="badge bg-warning text-dark">Sí</span>'
                : '<span class="badge bg-secondary">No</span>';

            const btnEditar = `
                <button type="button"
                        class="btn btn-sm btn-outline-primary me-1"
                        onclick="editarTipoLicencia(${Number(tipo.idtipo_licencia)})"
                        title="Editar tipo de licencia">
                    <i class="fas fa-edit"></i>
                </button>
            `;

            const btnEstado = activo
                ? `
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            onclick="cambiarEstadoTipoLicencia(${Number(tipo.idtipo_licencia)}, 0)"
                            title="Desactivar">
                        <i class="fas fa-user-slash"></i>
                    </button>
                `
                : `
                    <button type="button"
                            class="btn btn-sm btn-outline-success"
                            onclick="cambiarEstadoTipoLicencia(${Number(tipo.idtipo_licencia)}, 1)"
                            title="Activar">
                        <i class="fas fa-user-check"></i>
                    </button>
                `;

            tablaTiposLicencia.row.add([

                `<strong>${escapeHtmlTipoLicencia(tipo.nombre)}</strong>`,

                `<div class="text-center">${documentacion}</div>`,

                `<div class="text-center">${vacaciones}</div>`,

                `<div class="text-center">${badgeEstado}</div>`,

                `<div class="text-center">${btnEditar}${btnEstado}</div>`

            ]);

        });

        tablaTiposLicencia.draw();

    } catch (err) {

        console.error(err);
        toast('Error de conexión al obtener tipos de licencia.', 'error');

    }

}

// ================================================================
// NUEVO
// ================================================================

function abrirNuevoTipoLicencia() {

    $('#formTipoLicencia')[0].reset();

    $('#edit_tipo_licencia_id').val('');

    $('#ModalTipoLicencia .modal-title').text('Nuevo Tipo de Licencia');

    $('#btnGuardarTipoLicencia')
        .text('Crear Tipo de Licencia')
        .prop('disabled', false);

    guardandoTipoLicencia = false;

    $('#ModalTipoLicencia').modal('show');

}

// ================================================================
// EDITAR
// ================================================================

function editarTipoLicencia(id) {

    const tipo = tiposLicenciaList.find(
        t => Number(t.idtipo_licencia) === Number(id)
    );

    if (!tipo) {
        toast('Tipo de licencia no encontrado.', 'error');
        return;
    }

    $('#edit_tipo_licencia_id').val(tipo.idtipo_licencia);

    $('#tipo_licencia_nombre').val(tipo.nombre);

    $('#requiere_documentacion')
        .prop('checked', Number(tipo.requiere_documentacion) === 1);

    $('#descuenta_vacaciones')
        .prop('checked', Number(tipo.descuenta_vacaciones) === 1);

    $('#ModalTipoLicencia .modal-title').text('Editar Tipo de Licencia');

    $('#btnGuardarTipoLicencia')
        .text('Guardar Cambios')
        .prop('disabled', false);

    guardandoTipoLicencia = false;

    $('#ModalTipoLicencia').modal('show');

}

// ================================================================
// GUARDAR
// ================================================================

async function guardarTipoLicencia() {

    if (guardandoTipoLicencia) {
        return;
    }

    const id = $('#edit_tipo_licencia_id').val();

    const nombre = $('#tipo_licencia_nombre').val().trim();

    if (!nombre) {
        toast('Ingrese el nombre del tipo de licencia.', 'warning');
        return;
    }

    const action = id ? 'editar' : 'crear';

    const formData = new FormData();

    formData.append('nombre', nombre);

    formData.append(
        'requiere_documentacion',
        $('#requiere_documentacion').is(':checked') ? 1 : 0
    );

    formData.append(
        'descuenta_vacaciones',
        $('#descuenta_vacaciones').is(':checked') ? 1 : 0
    );

    if (id) {
        formData.append('idtipo_licencia', id);
    }

    guardandoTipoLicencia = true;

    $('#btnGuardarTipoLicencia').prop('disabled', true);

    try {

        const res = await fetch(
            API_TIPOS_LICENCIA + `?action=${action}`,
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || 'No se pudo guardar el tipo de licencia.', 'error');
            return;
        }

        $('#ModalTipoLicencia').modal('hide');

        toast(json.msg || 'Tipo de licencia guardado correctamente.', 'success');

        cargarTiposLicencia();

    } catch (err) {

        console.error(err);
        toast('Error de conexión al guardar el tipo de licencia.', 'error');

    } finally {

        guardandoTipoLicencia = false;

        $('#btnGuardarTipoLicencia').prop('disabled', false);

    }

}

// ================================================================
// CAMBIAR ESTADO
// ================================================================

function cambiarEstadoTipoLicencia(id, nuevoEstado) {

    const accionTexto = nuevoEstado === 1
        ? 'activar'
        : 'desactivar';

    Swal.fire({

        title: '¿Está seguro?',

        text: `¿Desea ${accionTexto} este tipo de licencia?`,

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

        formData.append('idtipo_licencia', id);
        formData.append('activo', nuevoEstado);

        try {

            const res = await fetch(
                API_TIPOS_LICENCIA + '?action=estado',
                {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                }
            );

            const json = await res.json();

            if (json.status !== 'ok') {
                toast(json.msg || 'No se pudo cambiar el estado.', 'error');
                return;
            }

            toast(json.msg, 'success');

            cargarTiposLicencia();

        } catch (err) {

            console.error(err);
            toast('Error de conexión al cambiar el estado.', 'error');

        }

    });

}

// ================================================================
// ESCAPAR HTML
// ================================================================

function escapeHtmlTipoLicencia(valor) {

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