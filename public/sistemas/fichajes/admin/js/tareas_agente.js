let tablaTareas = null;
let tareasList = [];

const URL_API_TAREAS =
    API_BASE + '/fichajes/tareas/administrar_tareas.php';

document.addEventListener('DOMContentLoaded', () => {

    if (!localStorage.getItem('sso_token')) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    inicializarTablaTareas();

    document.getElementById('btnActualizarTareas')
        .addEventListener('click', cargarTareas);

    document.getElementById('filtroEstadoTarea')
        .addEventListener('change', aplicarFiltrosTareas);

    document.getElementById('filtroAccionTarea')
        .addEventListener('change', aplicarFiltrosTareas);

    document.getElementById('btnLimpiarFiltrosTareas')
        .addEventListener('click', () => {
            document.getElementById('filtroEstadoTarea').value = '';
            document.getElementById('filtroAccionTarea').value = '';
            aplicarFiltrosTareas();
        });

    cargarTareas();
});


function inicializarTablaTareas() {

    tablaTareas = $('#tablaTareas').DataTable({
        responsive: true,
        autoWidth: false,
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
            processing: 'Procesando...',
            lengthMenu: 'Mostrar _MENU_ registros',
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay tareas registradas',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ tareas',
            infoEmpty: 'Sin tareas',
            infoFiltered: '(filtrado de _MAX_ tareas)',
            search: 'Buscar:',
            loadingRecords: 'Cargando...',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        }
    });
}


async function cargarTareas() {

    const boton = document.getElementById('btnActualizarTareas');
    boton.disabled = true;

    try {

        const response = await fetch(
            URL_API_TAREAS + '?action=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await response.json();

        if (!response.ok || json.status !== 'ok') {
            toast(json.msg || 'No se pudieron cargar las tareas.', 'error');
            return;
        }

        tareasList = Array.isArray(json.data) ? json.data : [];

        actualizarResumenTareas();
        cargarAccionesFiltro();
        aplicarFiltrosTareas();

    } catch (error) {

        console.error(error);
        toast('Error de conexión al consultar las tareas.', 'error');

    } finally {
        boton.disabled = false;
    }
}


function actualizarResumenTareas() {

    document.getElementById('totalTareas').textContent = tareasList.length;

    document.getElementById('totalPendientes').textContent =
        tareasList.filter(t => t.estado === 'pendiente').length;

    document.getElementById('totalProcesando').textContent =
        tareasList.filter(t => t.estado === 'procesando').length;

    document.getElementById('totalErrores').textContent =
        tareasList.filter(t => t.estado === 'error').length;
}


function cargarAccionesFiltro() {

    const select = document.getElementById('filtroAccionTarea');
    const seleccionActual = select.value;

    const acciones = [...new Set(
        tareasList.map(t => t.accion).filter(Boolean)
    )].sort();

    select.replaceChildren(new Option('Todas las acciones', ''));

    acciones.forEach(accion => {
        select.add(new Option(accion, accion));
    });

    if (acciones.includes(seleccionActual)) {
        select.value = seleccionActual;
    }
}


function aplicarFiltrosTareas() {

    const estado = document.getElementById('filtroEstadoTarea').value;
    const accion = document.getElementById('filtroAccionTarea').value;

    const filtradas = tareasList.filter(t => {
        return (!estado || t.estado === estado) &&
               (!accion || t.accion === accion);
    });

    tablaTareas.clear();

    filtradas.forEach(t => {

        const estadoBadge = obtenerBadgeEstado(t.estado);

        const reloj = t.lector_nombre
            ? escapeHtml(t.lector_nombre)
            : (t.idlector ? `ID ${escapeHtml(t.idlector)}` : '—');

        const asociado = t.empleado_nombre
            ? escapeHtml(t.empleado_nombre)
            : (t.idempleado ? `ID ${escapeHtml(t.idempleado)}` : '—');

        const id = Number(t.idtarea);

        const botonDetalle = `
            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    title="Ver detalle"
                    onclick="verDetalleTarea(${id})">
                <i class="fas fa-eye"></i>
            </button>`;

        let botonesAccion = '';

        if (t.estado === 'pendiente') {

            botonesAccion = `
                <button type="button"
                        class="btn btn-sm btn-outline-danger"
                        title="Cancelar tarea pendiente"
                        onclick="cancelarTarea(${id})">
                    <i class="fas fa-ban"></i>
                </button>`;

        } else if (t.estado === 'error') {

            botonesAccion = `
                <button type="button"
                        class="btn btn-sm btn-outline-warning"
                        title="Reintentar tarea"
                        onclick="reintentarTarea(${id})">
                    <i class="fas fa-redo"></i>
                </button>`;

        } else if (t.estado === 'procesando') {

            botonesAccion = `
                <button type="button"
                        class="btn btn-sm btn-outline-secondary"
                        title="La cancelación en ejecución todavía no está disponible"
                        disabled>
                    <i class="fas fa-spinner"></i>
                </button>`;
        }

        tablaTareas.row.add([
            `<span class="fw-semibold">#${id}</span>`,
            `<code>${escapeHtml(t.accion)}</code>`,
            reloj,
            asociado,
            `<div class="text-center">${estadoBadge}</div>`,
            `<div class="text-center">${Number(t.intentos) || 0}</div>`,
            escapeHtml(t.fecha_creacion || '—'),
            escapeHtml(t.fecha_completada || '—'),
            `<div class="d-flex justify-content-center gap-1">
                ${botonDetalle}
                ${botonesAccion}
            </div>`
        ]);
    });

    tablaTareas.draw();
}


function obtenerBadgeEstado(estado) {

    const estados = {
        pendiente: ['bg-warning text-dark', 'Pendiente'],
        procesando: ['bg-primary', 'En proceso'],
        completada: ['bg-success', 'Completada'],
        error: ['bg-danger', 'Error'],
        cancelada: ['bg-secondary', 'Cancelada']
    };

    const config = estados[estado] ||
        ['bg-secondary', estado || 'Desconocido'];

    return `<span class="badge ${config[0]}">${escapeHtml(config[1])}</span>`;
}


async function verDetalleTarea(id) {

    const tarea = tareasList.find(t => Number(t.idtarea) === Number(id));

    if (!tarea) {
        toast('No se encontró la tarea seleccionada.', 'error');
        return;
    }

    document.getElementById('detalleIdTarea').textContent = `#${id}`;

    document.getElementById('detalleResumen').innerHTML = `
        <div><strong>Acción:</strong> ${escapeHtml(tarea.accion)}</div>
        <div><strong>Estado:</strong> ${obtenerBadgeEstado(tarea.estado)}</div>
        <div><strong>Intentos:</strong> ${Number(tarea.intentos) || 0}</div>
        <div><strong>Creación:</strong> ${escapeHtml(tarea.fecha_creacion || '—')}</div>
        <div><strong>Procesamiento:</strong> ${escapeHtml(tarea.fecha_procesamiento || '—')}</div>
        <div><strong>Finalización:</strong> ${escapeHtml(tarea.fecha_completada || '—')}</div>
    `;

    document.getElementById('detalleDatos').textContent = 'Cargando...';
    document.getElementById('detalleRespuesta').textContent = 'Cargando...';

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalDetalleTarea')
    ).show();

    try {

        const response = await fetch(
            URL_API_TAREAS +
            '?action=detalle&idtarea=' + encodeURIComponent(id),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const json = await response.json();

        if (!response.ok || json.status !== 'ok') {
            throw new Error(json.msg || 'No se pudo consultar el detalle.');
        }

        document.getElementById('detalleDatos').textContent =
            formatearJson(json.data.datos);

        document.getElementById('detalleRespuesta').textContent =
            formatearJson(json.data.respuesta);

    } catch (error) {

        document.getElementById('detalleDatos').textContent = 'No disponible';
        document.getElementById('detalleRespuesta').textContent = error.message;

        toast(error.message, 'error');
    }
}


async function cancelarTarea(id) {

    const tarea = tareasList.find(t => Number(t.idtarea) === Number(id));

    if (!tarea || tarea.estado !== 'pendiente') {
        toast('Solo se pueden cancelar tareas pendientes.', 'warning');
        return;
    }

    const confirmacion = await Swal.fire({
        title: `¿Cancelar la tarea #${id}?`,
        text: 'La tarea pendiente no será ejecutada por el agente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#dc3545',
        reverseButtons: true
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    await ejecutarAccionTarea(id, 'cancelar');
}


async function reintentarTarea(id) {

    const tarea = tareasList.find(t => Number(t.idtarea) === Number(id));

    if (!tarea || tarea.estado !== 'error') {
        toast('Solo se pueden reintentar tareas con error.', 'warning');
        return;
    }

    let texto =
        `La tarea #${id} volverá a la cola y el agente podrá ejecutarla nuevamente.`;

    if (tarea.accion === 'leer_fichadas') {
        texto +=
            '\n\nAtención: esta operación puede volver a leer fichadas del reloj y borrarlas del dispositivo después de guardarlas.';
    } else if (tarea.accion === 'sincronizar_empleados') {
        texto +=
            '\n\nAtención: esta operación puede modificar los usuarios almacenados en el reloj.';
    } else if (tarea.accion === 'enrolar_huella') {
        texto +=
            '\n\nAtención: esta operación volverá a intentar el enrolamiento biométrico.';
    }

    const confirmacion = await Swal.fire({
        title: `¿Reintentar la tarea #${id}?`,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, reintentar',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#d39e00',
        reverseButtons: true
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    await ejecutarAccionTarea(id, 'reintentar');
}


async function ejecutarAccionTarea(id, accion) {

    const botones = document.querySelectorAll(
        `button[onclick*="Tarea(${Number(id)})"]`
    );

    botones.forEach(boton => {
        boton.disabled = true;
    });

    try {

        const response = await fetch(URL_API_TAREAS, {
            method: 'POST',
            headers: {
                ...obtenerHeadersSSO(),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: accion,
                idtarea: Number(id)
            })
        });

        const json = await response.json();

        if (!response.ok || json.status !== 'ok') {
            throw new Error(json.msg || 'No se pudo completar la operación.');
        }

        toast(json.msg || 'Operación realizada correctamente.', 'success');

        await cargarTareas();

    } catch (error) {

        console.error(error);
        toast(error.message || 'Error al ejecutar la acción.', 'error');

        await cargarTareas();

    } finally {

        botones.forEach(boton => {
            boton.disabled = false;
        });
    }
}


function formatearJson(valor) {

    if (valor === null || valor === undefined || valor === '') {
        return 'Sin datos';
    }

    if (typeof valor === 'object') {
        return JSON.stringify(valor, null, 2);
    }

    try {
        return JSON.stringify(JSON.parse(valor), null, 2);
    } catch {
        return String(valor);
    }
}


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