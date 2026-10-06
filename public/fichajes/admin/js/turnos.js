// ============================================================
// TURNOS
// ============================================================

let tablaCiclosEmpleado = null;
let tablaHorariosEspeciales = null;
let tablaHorariosExtra = null;

let modalCicloEmpleado = null;
let modalHorarioEspecial = null;
let modalHorarioExtra = null;

let empleados = [];
let ciclos = [];
let horarios = [];


// ============================================================
// INICIALIZACIÓN
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    modalCicloEmpleado = new bootstrap.Modal(
        document.getElementById('ModalCicloEmpleado')
    );

    modalHorarioEspecial = new bootstrap.Modal(
        document.getElementById('ModalHorarioEspecial')
    );

    modalHorarioExtra = new bootstrap.Modal(
        document.getElementById('ModalHorarioExtra')
    );

    inicializarTablas();

    cargarDatosIniciales();

    document.getElementById('formCicloEmpleado')
        .addEventListener('submit', guardarCicloEmpleado);

    document.getElementById('formHorarioEspecial')
        .addEventListener('submit', guardarHorarioEspecial);

    document.getElementById('formHorarioExtra')
        .addEventListener('submit', guardarHorarioExtra);

});


// ============================================================
// TABLAS
// ============================================================

function inicializarTablas() {

    tablaCiclosEmpleado = $('#tablaCiclosEmpleado').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columns: [
            { data: 'empleado' },
            { data: 'documento' },
            { data: 'ciclo' },
            { data: 'fecha_desde' },
            { data: 'fecha_hasta' },
            { data: 'estado' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end'
            }
        ],
        columnDefs: [
            {
                targets: 6,
                render: function (data, type, row) {

                    let botones = '';

                    botones += `
                        <button
                            type="button"
                            class="btn btn-sm btn-primary me-1"
                            title="Editar"
                            data-permiso="turnos_gestionar"
                            onclick="editarCicloEmpleado(${row.idempleado_ciclo})">

                            <i class="fas fa-edit"></i>

                        </button>
                    `;

                    if (Number(row.activo) === 1) {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-danger"
                                title="Finalizar asignación"
                                data-permiso="turnos_gestionar"
                                onclick="cambiarEstadoCiclo(${row.idempleado_ciclo}, 0)">

                                <i class="fas fa-times"></i>

                            </button>
                        `;

                    } else {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-success"
                                title="Activar"
                                data-permiso="turnos_gestionar"
                                onclick="cambiarEstadoCiclo(${row.idempleado_ciclo}, 1)">

                                <i class="fas fa-check"></i>

                            </button>
                        `;

                    }

                    return botones;
                }
            }
        ]
    });


    tablaHorariosEspeciales = $('#tablaHorariosEspeciales').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[2, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columns: [
            { data: 'empleado' },
            { data: 'documento' },
            { data: 'fecha' },
            { data: 'horario' },
            { data: 'estado' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end'
            }
        ],
        columnDefs: [
            {
                targets: 5,
                render: function (data, type, row) {

                    return `
                        <button
                            type="button"
                            class="btn btn-sm btn-primary me-1"
                            title="Editar"
                            data-permiso="turnos_gestionar"
                            onclick="editarHorarioEspecial(${row.idhorario_especial})">

                            <i class="fas fa-edit"></i>

                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-danger"
                            title="Eliminar"
                            data-permiso="turnos_gestionar"
                            onclick="eliminarHorarioEspecial(${row.idhorario_especial})">

                            <i class="fas fa-trash"></i>

                        </button>
                    `;
                }
            }
        ]
    });


    tablaHorariosExtra = $('#tablaHorariosExtra').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[2, 'desc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columns: [
            { data: 'empleado' },
            { data: 'documento' },
            { data: 'fecha' },
            { data: 'hora_desde' },
            { data: 'hora_hasta' },
            { data: 'observaciones' },
            { data: 'estado' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-end'
            }
        ],
        columnDefs: [
            {
                targets: 6,
                render: function (data, type, row) {

                    let botones = '';

                    botones += `
                        <button
                            type="button"
                            class="btn btn-sm btn-primary me-1"
                            title="Editar"
                            data-permiso="turnos_gestionar"
                            onclick="editarHorarioExtra(${row.idhorario_extra})">

                            <i class="fas fa-edit"></i>

                        </button>
                    `;

                    if (Number(row.activo) === 1) {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-danger"
                                title="Desactivar"
                                data-permiso="turnos_gestionar"
                                onclick="cambiarEstadoHorarioExtra(${row.idhorario_extra}, 0)">

                                <i class="fas fa-times"></i>

                            </button>
                        `;

                    } else {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-success"
                                title="Activar"
                                data-permiso="turnos_gestionar"
                                onclick="cambiarEstadoHorarioExtra(${row.idhorario_extra}, 1)">

                                <i class="fas fa-check"></i>

                            </button>
                        `;

                    }

                    return botones;
                }
            }
        ]
    });

}


// ============================================================
// DATOS INICIALES
// ============================================================

async function cargarDatosIniciales() {

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php?accion=datos_iniciales',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar los datos de Turnos.',
                'error'
            );

            return;
        }

        empleados = res.data.empleados || [];
        ciclos = res.data.ciclos || [];
        horarios = res.data.horarios || [];

        cargarSelectEmpleados();
        cargarSelectCiclos();
        cargarSelectHorarios();

        cargarCiclosEmpleado();
        cargarHorariosEspeciales();
        cargarHorariosExtra();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar los datos de Turnos.',
            'error'
        );
    }

}


// ============================================================
// SELECT EMPLEADOS
// ============================================================

function cargarSelectEmpleados() {

    const selects = [
        document.getElementById('ciclo_idempleado'),
        document.getElementById('especial_idempleado'),
        document.getElementById('extra_idempleado')
    ];

    selects.forEach(select => {

        select.innerHTML = `
            <option value="">
                Seleccione un empleado
            </option>
        `;

        empleados.forEach(e => {

            const option = document.createElement('option');

            option.value = e.idempleado;

            option.textContent =
                `${e.apellido}, ${e.nombre} - ${e.documento}`;

            select.appendChild(option);

        });

    });

}


// ============================================================
// SELECT CICLOS
// ============================================================

function cargarSelectCiclos() {

    const select = document.getElementById('ciclo_idciclo');

    select.innerHTML = `
        <option value="">
            Seleccione un ciclo
        </option>
    `;

    ciclos.forEach(c => {

        const option = document.createElement('option');

        option.value = c.idciclo;

        option.textContent =
            `${c.nombre} (${c.cantidad_semanas} ${Number(c.cantidad_semanas) === 1 ? 'semana' : 'semanas'})`;

        select.appendChild(option);

    });

}


// ============================================================
// SELECT HORARIOS
// ============================================================

function cargarSelectHorarios() {

    const select = document.getElementById('especial_idhorario');

    select.innerHTML = `
        <option value="">
            Seleccione un horario
        </option>
    `;

    horarios.forEach(h => {

        const option = document.createElement('option');

        option.value = h.idhorario;

        option.textContent = h.nombre;

        select.appendChild(option);

    });

}


// ============================================================
// CICLOS EMPLEADO
// ============================================================

async function cargarCiclosEmpleado() {

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php?accion=listar_ciclos',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar las asignaciones.',
                'error'
            );

            return;
        }

        tablaCiclosEmpleado.clear();

        tablaCiclosEmpleado.rows.add(res.data || []);

        tablaCiclosEmpleado.draw();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar las asignaciones de ciclos.',
            'error'
        );
    }

}


// ============================================================
// NUEVA ASIGNACIÓN
// ============================================================

function nuevoCicloEmpleado() {

    document.getElementById('tituloModalCiclo').textContent =
        'Nueva asignación de ciclo';

    document.getElementById('edit_empleado_ciclo_id').value = '0';

    document.getElementById('ciclo_idempleado').value = '';
    document.getElementById('ciclo_idciclo').value = '';
    document.getElementById('ciclo_fecha_desde').value = '';
    document.getElementById('ciclo_fecha_hasta').value = '';

    modalCicloEmpleado.show();

}


// ============================================================
// EDITAR ASIGNACIÓN
// ============================================================

async function editarCicloEmpleado(id) {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/turnos/turnos.php?accion=obtener_ciclo&id=' +
            encodeURIComponent(id),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo obtener la asignación.',
                'error'
            );

            return;
        }

        const d = res.data;

        document.getElementById('tituloModalCiclo').textContent =
            'Editar asignación de ciclo';

        document.getElementById('edit_empleado_ciclo_id').value =
            d.idempleado_ciclo;

        document.getElementById('ciclo_idempleado').value =
            d.idempleado;

        document.getElementById('ciclo_idciclo').value =
            d.idciclo;

        document.getElementById('ciclo_fecha_desde').value =
            d.fecha_desde || '';

        document.getElementById('ciclo_fecha_hasta').value =
            d.fecha_hasta || '';

        modalCicloEmpleado.show();

    } catch (error) {

        console.error(error);

        toast(
            'Error al obtener la asignación.',
            'error'
        );
    }

}


// ============================================================
// GUARDAR CICLO
// ============================================================

async function guardarCicloEmpleado(event) {

    event.preventDefault();

    const fechaDesde =
        document.getElementById('ciclo_fecha_desde').value;

    const fechaHasta =
        document.getElementById('ciclo_fecha_hasta').value;

    if (fechaHasta && fechaHasta < fechaDesde) {

        toast(
            'La fecha hasta no puede ser anterior a la fecha desde.',
            'warning'
        );

        return;
    }

    const datos = {
        accion: 'guardar_ciclo',
        idempleado_ciclo: Number(
            document.getElementById('edit_empleado_ciclo_id').value
        ),
        idempleado: Number(
            document.getElementById('ciclo_idempleado').value
        ),
        idciclo: Number(
            document.getElementById('ciclo_idciclo').value
        ),
        fecha_desde: fechaDesde,
        fecha_hasta: fechaHasta || null
    };

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo guardar la asignación.',
                'error'
            );

            return;
        }

        modalCicloEmpleado.hide();

        toast(
            res.msg || 'Asignación guardada correctamente.',
            'success'
        );

        cargarCiclosEmpleado();

    } catch (error) {

        console.error(error);

        toast(
            'Error al guardar la asignación.',
            'error'
        );
    }

}


// ============================================================
// CAMBIAR ESTADO CICLO
// ============================================================

async function cambiarEstadoCiclo(id, estado) {

    const texto = estado === 1
        ? '¿Desea activar esta asignación?'
        : '¿Desea finalizar esta asignación?';

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: 'Confirmar',
        text: texto,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'cambiar_estado_ciclo',
                    idempleado_ciclo: id,
                    activo: estado
                })
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo cambiar el estado.',
                'error'
            );

            return;
        }

        toast(
            res.msg || 'Estado actualizado correctamente.',
            'success'
        );

        cargarCiclosEmpleado();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cambiar el estado.',
            'error'
        );
    }

}


// ============================================================
// HORARIOS ESPECIALES
// ============================================================

async function cargarHorariosEspeciales() {

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php?accion=listar_especiales',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar los horarios especiales.',
                'error'
            );

            return;
        }

        tablaHorariosEspeciales.clear();

        tablaHorariosEspeciales.rows.add(res.data || []);

        tablaHorariosEspeciales.draw();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar los horarios especiales.',
            'error'
        );
    }

}


// ============================================================
// NUEVO HORARIO ESPECIAL
// ============================================================

function nuevoHorarioEspecial() {

    document.getElementById('tituloModalEspecial').textContent =
        'Nuevo horario especial';

    document.getElementById('edit_horario_especial_id').value = '0';

    document.getElementById('especial_idempleado').value = '';
    document.getElementById('especial_fecha').value = '';
    document.getElementById('especial_idhorario').value = '';

    modalHorarioEspecial.show();

}


// ============================================================
// EDITAR HORARIO ESPECIAL
// ============================================================

async function editarHorarioEspecial(id) {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/turnos/turnos.php?accion=obtener_especial&id=' +
            encodeURIComponent(id),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo obtener el horario especial.',
                'error'
            );

            return;
        }

        const d = res.data;

        document.getElementById('tituloModalEspecial').textContent =
            'Editar horario especial';

        document.getElementById('edit_horario_especial_id').value =
            d.idhorario_especial;

        document.getElementById('especial_idempleado').value =
            d.idempleado;

        document.getElementById('especial_fecha').value =
            d.fecha;

        document.getElementById('especial_idhorario').value =
            d.idhorario;

        modalHorarioEspecial.show();

    } catch (error) {

        console.error(error);

        toast(
            'Error al obtener el horario especial.',
            'error'
        );
    }

}


// ============================================================
// GUARDAR HORARIO ESPECIAL
// ============================================================

async function guardarHorarioEspecial(event) {

    event.preventDefault();

    const datos = {
        accion: 'guardar_especial',
        idhorario_especial: Number(
            document.getElementById('edit_horario_especial_id').value
        ),
        idempleado: Number(
            document.getElementById('especial_idempleado').value
        ),
        fecha:
            document.getElementById('especial_fecha').value,
        idhorario: Number(
            document.getElementById('especial_idhorario').value
        )
    };

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo guardar el horario especial.',
                'error'
            );

            return;
        }

        modalHorarioEspecial.hide();

        toast(
            res.msg || 'Horario especial guardado correctamente.',
            'success'
        );

        cargarHorariosEspeciales();

    } catch (error) {

        console.error(error);

        toast(
            'Error al guardar el horario especial.',
            'error'
        );
    }

}


// ============================================================
// ELIMINAR HORARIO ESPECIAL
// ============================================================

async function eliminarHorarioEspecial(id) {

    const confirmacion = await Swal.fire({
        icon: 'warning',
        title: 'Eliminar horario especial',
        text: '¿Está seguro de eliminar este horario especial?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'eliminar_especial',
                    idhorario_especial: id
                })
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo eliminar el horario especial.',
                'error'
            );

            return;
        }

        toast(
            res.msg || 'Horario especial eliminado correctamente.',
            'success'
        );

        cargarHorariosEspeciales();

    } catch (error) {

        console.error(error);

        toast(
            'Error al eliminar el horario especial.',
            'error'
        );
    }

}


// ============================================================
// HORAS EXTRA PROGRAMADAS
// ============================================================

async function cargarHorariosExtra() {

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php?accion=listar_extra',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar las horas extra.',
                'error'
            );

            return;
        }

        tablaHorariosExtra.clear();

        tablaHorariosExtra.rows.add(res.data || []);

        tablaHorariosExtra.draw();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar las horas extra programadas.',
            'error'
        );
    }

}


// ============================================================
// NUEVA HORA EXTRA
// ============================================================

function nuevoHorarioExtra() {

    document.getElementById('tituloModalExtra').textContent =
        'Nueva hora extra programada';

    document.getElementById('edit_horario_extra_id').value = '0';

    document.getElementById('extra_idempleado').value = '';
    document.getElementById('extra_fecha').value = '';
    document.getElementById('extra_hora_desde').value = '';
    document.getElementById('extra_hora_hasta').value = '';
    document.getElementById('extra_observaciones').value = '';

    modalHorarioExtra.show();

}


// ============================================================
// EDITAR HORA EXTRA
// ============================================================

async function editarHorarioExtra(id) {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/turnos/turnos.php?accion=obtener_extra&id=' +
            encodeURIComponent(id),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo obtener la hora extra.',
                'error'
            );

            return;
        }

        const d = res.data;

        document.getElementById('tituloModalExtra').textContent =
            'Editar hora extra programada';

        document.getElementById('edit_horario_extra_id').value =
            d.idhorario_extra;

        document.getElementById('extra_idempleado').value =
            d.idempleado;

        document.getElementById('extra_fecha').value =
            d.fecha;

        document.getElementById('extra_hora_desde').value =
            d.hora_desde.substring(0, 5);

        document.getElementById('extra_hora_hasta').value =
            d.hora_hasta.substring(0, 5);

        document.getElementById('extra_observaciones').value =
            d.observaciones || '';

        modalHorarioExtra.show();

    } catch (error) {

        console.error(error);

        toast(
            'Error al obtener la hora extra.',
            'error'
        );
    }

}


// ============================================================
// NORMALIZAR HORA
// ============================================================

function normalizarHora(hora) {

    hora = String(hora || '').trim();

    if (!/^\d{1,2}:\d{2}$/.test(hora)) {
        return null;
    }

    const partes = hora.split(':');

    const h = Number(partes[0]);
    const m = Number(partes[1]);

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

    return String(h).padStart(2, '0') +
           ':' +
           String(m).padStart(2, '0');

}


// ============================================================
// GUARDAR HORA EXTRA
// ============================================================

async function guardarHorarioExtra(event) {

    event.preventDefault();

    const horaDesde = normalizarHora(
        document.getElementById('extra_hora_desde').value
    );

    const horaHasta = normalizarHora(
        document.getElementById('extra_hora_hasta').value
    );

    if (!horaDesde || !horaHasta) {

        toast(
            'Las horas deben tener formato HH:mm.',
            'warning'
        );

        return;
    }

    if (horaDesde === horaHasta) {

        toast(
            'La hora desde y la hora hasta no pueden ser iguales.',
            'warning'
        );

        return;
    }

    const datos = {
        accion: 'guardar_extra',
        idhorario_extra: Number(
            document.getElementById('edit_horario_extra_id').value
        ),
        idempleado: Number(
            document.getElementById('extra_idempleado').value
        ),
        fecha:
            document.getElementById('extra_fecha').value,
        hora_desde: horaDesde,
        hora_hasta: horaHasta,
        observaciones:
            document.getElementById('extra_observaciones').value.trim()
    };

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo guardar la hora extra.',
                'error'
            );

            return;
        }

        modalHorarioExtra.hide();

        toast(
            res.msg || 'Hora extra guardada correctamente.',
            'success'
        );

        cargarHorariosExtra();

    } catch (error) {

        console.error(error);

        toast(
            'Error al guardar la hora extra.',
            'error'
        );
    }

}


// ============================================================
// CAMBIAR ESTADO HORA EXTRA
// ============================================================

async function cambiarEstadoHorarioExtra(id, estado) {

    const texto = estado === 1
        ? '¿Desea activar esta hora extra?'
        : '¿Desea desactivar esta hora extra?';

    const confirmacion = await Swal.fire({
        icon: 'question',
        title: 'Confirmar',
        text: texto,
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) {
        return;
    }

    try {

        const response = await fetch(
            API_BASE + '/fichajes/turnos/turnos.php',
            {
                method: 'POST',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'cambiar_estado_extra',
                    idhorario_extra: id,
                    activo: estado
                })
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo cambiar el estado.',
                'error'
            );

            return;
        }

        toast(
            res.msg || 'Estado actualizado correctamente.',
            'success'
        );

        cargarHorariosExtra();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cambiar el estado.',
            'error'
        );
    }

}