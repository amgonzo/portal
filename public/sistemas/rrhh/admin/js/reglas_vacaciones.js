const API_REGLAS_VACACIONES =
    API_BASE + '/rrhh/vacaciones/reglas.php';

let tablaReglasVacaciones = null;
let reglasVacacionesCache = [];

document.addEventListener('DOMContentLoaded', function () {

    if (!localStorage.getItem('sso_token')) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTableReglas();
    cargarReglasVacaciones();

    document.getElementById('btnNuevaRegla')
        .addEventListener('click', nuevaReglaVacaciones);

    document.getElementById('formReglaVacaciones')
        .addEventListener('submit', guardarReglaVacaciones);
});


function initDataTableReglas() {

    tablaReglasVacaciones = $('#tablaReglasVacaciones').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[0, 'asc']],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columns: [
            { data: 'antiguedad_desde' },
            { data: 'antiguedad_hasta' },
            { data: 'dias_asignados' },
            { data: 'estado' },
            { data: 'fecha_registro' },
            { data: 'acciones', orderable: false, searchable: false }
        ]
    });
}


async function cargarReglasVacaciones() {

    try {

        const response = await fetch(
            API_REGLAS_VACACIONES + '?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const resultado = await response.json();

        if (resultado.status !== 'ok') {
            toast('error', resultado.msg || 'Error al cargar las reglas');
            return;
        }

        reglasVacacionesCache = resultado.data || [];

        tablaReglasVacaciones.clear();

        reglasVacacionesCache.forEach(regla => {

            tablaReglasVacaciones.row.add({
                antiguedad_desde:
                    `${regla.antiguedad_desde} años`,

                antiguedad_hasta:
                    regla.antiguedad_hasta === null
                        ? 'Sin límite'
                        : `${regla.antiguedad_hasta} años`,

                dias_asignados:
                    `${parseInt(regla.dias_asignados)} días`,

                estado:
                    parseInt(regla.activo) === 1
                        ? '<span class="badge badge-success">Activo</span>'
                        : '<span class="badge badge-secondary">Inactivo</span>',

                fecha_registro:
                    regla.fecha_registro || '',

                acciones: `
                    <button type="button"
                        class="btn btn-sm btn-warning"
                        title="Editar"
                        onclick="editarReglaVacaciones(${regla.idregla})">
                        <i class="fa fa-pencil"></i>
                    </button>

                    <button type="button"
                        class="btn btn-sm ${parseInt(regla.activo) === 1 ? 'btn-danger' : 'btn-success'}"
                        title="${parseInt(regla.activo) === 1 ? 'Desactivar' : 'Activar'}"
                        onclick="cambiarEstadoReglaVacaciones(${regla.idregla}, ${parseInt(regla.activo) === 1 ? 0 : 1})">
                        <i class="fa ${parseInt(regla.activo) === 1 ? 'fa-ban' : 'fa-check'}"></i>
                    </button>
                `
            });

        });

        tablaReglasVacaciones.draw();

    } catch (error) {
        console.error('Error cargando reglas de vacaciones:', error);
        toast('error', 'No se pudieron cargar las reglas');
    }
}


function nuevaReglaVacaciones() {

    document.getElementById('formReglaVacaciones').reset();
    document.getElementById('regla_id').value = '';

    document.getElementById('tituloModalRegla').textContent =
        'Nueva regla';

    $('#ModalReglaVacaciones').modal('show');
}


function editarReglaVacaciones(id) {

    const regla = reglasVacacionesCache.find(
        item => parseInt(item.idregla) === parseInt(id)
    );

    if (!regla) {
        toast('error', 'No se encontró la regla');
        return;
    }

    document.getElementById('regla_id').value = regla.idregla;
    document.getElementById('antiguedad_desde').value =
        regla.antiguedad_desde;

    document.getElementById('antiguedad_hasta').value =
        regla.antiguedad_hasta ?? '';

    document.getElementById('dias_asignados').value =
        regla.dias_asignados;

    document.getElementById('tituloModalRegla').textContent =
        'Editar regla';

    $('#ModalReglaVacaciones').modal('show');
}


async function guardarReglaVacaciones(e) {

    e.preventDefault();

    const form = document.getElementById('formReglaVacaciones');
    const boton = document.getElementById('btnGuardarRegla');

    const desde = parseInt(
        document.getElementById('antiguedad_desde').value
    );

    const hastaValor =
        document.getElementById('antiguedad_hasta').value;

    const hasta = hastaValor === ''
        ? null
        : parseInt(hastaValor);

    const dias = parseFloat(
        document.getElementById('dias_asignados').value
    );

    if (hasta !== null && hasta < desde) {
        toast('error', 'La antigüedad hasta debe ser mayor o igual a desde');
        return;
    }

    if (!Number.isFinite(dias) || dias <= 0) {
        toast('error', 'Ingresá una cantidad de días válida');
        return;
    }

    const datos = new FormData(form);

    datos.append(
        'action',
        document.getElementById('regla_id').value
            ? 'editar'
            : 'crear'
    );

    boton.disabled = true;

    try {

        const response = await fetch(API_REGLAS_VACACIONES, {
            method: 'POST',
            headers: obtenerHeadersSSO(),
            body: datos
        });

        const resultado = await response.json();

        if (resultado.status !== 'ok') {
            toast('error', resultado.msg || 'No se pudo guardar la regla');
            return;
        }

        $('#ModalReglaVacaciones').modal('hide');

        toast('success', resultado.msg);

        await cargarReglasVacaciones();

    } catch (error) {
        console.error('Error guardando regla:', error);
        toast('error', 'Error al guardar la regla');
    } finally {
        boton.disabled = false;
    }
}


async function cambiarEstadoReglaVacaciones(id, activo) {

    const confirmacion = await Swal.fire({
        title: activo === 1 ? '¿Activar regla?' : '¿Desactivar regla?',
        text: activo === 1
            ? 'La regla volverá a estar disponible.'
            : 'La regla dejará de estar disponible.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    const datos = new FormData();

    datos.append('action', 'estado');
    datos.append('idregla', id);
    datos.append('activo', activo);

    try {

        const response = await fetch(API_REGLAS_VACACIONES, {
            method: 'POST',
            headers: obtenerHeadersSSO(),
            body: datos
        });

        const resultado = await response.json();

        if (resultado.status !== 'ok') {
            toast('error', resultado.msg || 'No se pudo cambiar el estado');
            return;
        }

        toast('success', resultado.msg);

        await cargarReglasVacaciones();

    } catch (error) {
        console.error('Error cambiando estado:', error);
        toast('error', 'Error al cambiar el estado');
    }
}