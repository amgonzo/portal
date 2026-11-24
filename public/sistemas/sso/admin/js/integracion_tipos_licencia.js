const API_INTEGRACION_TIPOS =
    API_BASE + '/sso/integraciones/tipos_asistencia.php';

let tablaIntegracionTipos = null;
let relacionesTipos = [];
let opcionesLicencias = [];
let opcionesAsistencias = [];

document.addEventListener('DOMContentLoaded', () => {

    if (!localStorage.getItem('sso_token')) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    tablaIntegracionTipos = $('#tablaIntegracionTipos').DataTable({
        language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "No hay relaciones configuradas",
            sInfo: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            sInfoEmpty: "Mostrando 0 registros",
            sSearch: "Buscar:",
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

    cargarOpciones();
    cargarRelaciones();

});

// =====================================================
// OPCIONES DE AMBAS TABLAS
// =====================================================

async function cargarOpciones() {

    try {

        const res = await fetch(
            API_INTEGRACION_TIPOS + '?action=opciones',
            {
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || 'Error al cargar los tipos.', 'error');
            return;
        }

        opcionesLicencias = json.licencias || [];
        opcionesAsistencias = json.asistencias || [];

        const selectLicencia = document.getElementById('integracion_licencia');
        const selectAsistencia = document.getElementById('integracion_asistencia');

        selectLicencia.innerHTML = '<option value="">Seleccionar...</option>';
        selectAsistencia.innerHTML = '<option value="">Seleccionar...</option>';

        opcionesLicencias.forEach(item => {
            selectLicencia.add(new Option(
                item.nombre,
                item.idtipo_licencia
            ));
        });

        opcionesAsistencias.forEach(item => {
            selectAsistencia.add(new Option(
                item.abreviatura
                    ? `${item.nombre} (${item.abreviatura})`
                    : item.nombre,
                item.idtipo_asistencia
            ));
        });

    } catch (error) {
        console.error(error);
        toast('Error de conexión al cargar las opciones.', 'error');
    }

}

// =====================================================
// LISTAR RELACIONES
// =====================================================

async function cargarRelaciones() {

    try {

        const res = await fetch(
            API_INTEGRACION_TIPOS + '?action=listar',
            {
                headers: obtenerHeadersSSO()
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || 'Error al cargar las relaciones.', 'error');
            return;
        }

        relacionesTipos = json.data || [];

        tablaIntegracionTipos.clear();

        relacionesTipos.forEach(item => {

            const activo = Number(item.activo) === 1;

            const estado = activo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const botonEditar = `
                <button class="btn btn-sm btn-outline-primary"
                    onclick="editarRelacion(${item.id})"
                    title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
            `;

            const botonEstado = activo
                ? `
                    <button class="btn btn-sm btn-outline-danger"
                        onclick="cambiarEstadoRelacion(${item.id}, 0)"
                        title="Desactivar">
                        <i class="fas fa-ban"></i>
                    </button>
                `
                : `
                    <button class="btn btn-sm btn-outline-success"
                        onclick="cambiarEstadoRelacion(${item.id}, 1)"
                        title="Activar">
                        <i class="fas fa-check"></i>
                    </button>
                `;

            tablaIntegracionTipos.row.add([
                escapeHtmlIntegracion(item.licencia),
                escapeHtmlIntegracion(
                    item.abreviatura
                        ? `${item.asistencia} (${item.abreviatura})`
                        : item.asistencia
                ),
                `<div class="text-center">${estado}</div>`,
                `<div class="text-center">${botonEditar} ${botonEstado}</div>`
            ]);

        });

        tablaIntegracionTipos.draw();

    } catch (error) {
        console.error(error);
        toast('Error de conexión al consultar las relaciones.', 'error');
    }

}

// =====================================================
// NUEVA RELACIÓN
// =====================================================

function abrirNuevaRelacion() {

    document.getElementById('formIntegracionTipos').reset();
    document.getElementById('integracion_id').value = '';

    $('#ModalIntegracionTipos .modal-title').text('Nueva Relación');

    $('#ModalIntegracionTipos').modal('show');

}

// =====================================================
// EDITAR RELACIÓN
// =====================================================

function editarRelacion(id) {

    const item = relacionesTipos.find(
        r => Number(r.id) === Number(id)
    );

    if (!item) {
        toast('Relación no encontrada.', 'error');
        return;
    }

    document.getElementById('integracion_id').value = item.id;
    document.getElementById('integracion_licencia').value = item.idtipo_licencia;
    document.getElementById('integracion_asistencia').value = item.idtipo_asistencia;

    $('#ModalIntegracionTipos .modal-title').text('Editar Relación');

    $('#ModalIntegracionTipos').modal('show');

}

// =====================================================
// GUARDAR RELACIÓN
// =====================================================

async function guardarRelacion() {

    const idLicencia = document.getElementById('integracion_licencia').value;
    const idAsistencia = document.getElementById('integracion_asistencia').value;

    if (!idLicencia || !idAsistencia) {
        toast('Seleccioná ambos tipos.', 'warning');
        return;
    }

    const boton = document.getElementById('btnGuardarIntegracion');

    boton.disabled = true;

    const formData = new FormData(
        document.getElementById('formIntegracionTipos')
    );

    formData.append('action', 'guardar');

    try {

        const res = await fetch(
            API_INTEGRACION_TIPOS + '?action=guardar',
            {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: formData
            }
        );

        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || 'No se pudo guardar la relación.', 'error');
            return;
        }

        $('#ModalIntegracionTipos').modal('hide');

        toast(json.msg, 'success');

        cargarRelaciones();

    } catch (error) {
        console.error(error);
        toast('Error de conexión al guardar.', 'error');

    } finally {
        boton.disabled = false;
    }

}

// =====================================================
// ACTIVAR / DESACTIVAR
// =====================================================

function cambiarEstadoRelacion(id, activo) {

    Swal.fire({
        title: '¿Está seguro?',
        text: activo
            ? '¿Desea activar esta relación?'
            : '¿Desea desactivar esta relación?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then(async result => {

        if (!result.isConfirmed) return;

        const formData = new FormData();

        formData.append('id', id);
        formData.append('activo', activo);

        try {

            const res = await fetch(
                API_INTEGRACION_TIPOS + '?action=estado',
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

            cargarRelaciones();

        } catch (error) {
            console.error(error);
            toast('Error de conexión.', 'error');
        }

    });

}

// =====================================================
// ESCAPAR HTML
// =====================================================

function escapeHtmlIntegracion(valor) {

    if (valor === null || valor === undefined) return '';

    return String(valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}