let tablaEmpleados = null;
let empleadosList = [];

let ciclosDisponibles = [];
let ciclosEmpleadoList = [];
let modalCicloEmpleado = null;

let lectoresDisponibles = [];
let lectoresEmpleadoList = [];

// =========================================================
// ESTADO DEL POLLING DE HUELLAS
// =========================================================

let pollingHuellaActivo = false;
let pollingHuellaTimer = null;
let pollingHuellaInicio = 0;

const POLLING_HUELLA_INTERVALO = 1500;
const POLLING_HUELLA_TIMEOUT = 90000;


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', () => {

    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    initDataTable();
    cargarEmpleados();

    const modalElement =
        document.getElementById('ModalCicloEmpleado');

    if (modalElement) {
        modalCicloEmpleado =
            new bootstrap.Modal(modalElement);
    }

    document
        .getElementById('formCicloEmpleado')
        ?.addEventListener('submit', function (e) {
            e.preventDefault();
            guardarCicloEmpleado();
        });

    cargarCiclosDisponibles();
});


// =========================================================
// DETENER POLLING DE HUELLA
// =========================================================

function detenerPollingHuella() {

    pollingHuellaActivo = false;

    if (pollingHuellaTimer) {
        clearTimeout(pollingHuellaTimer);
        pollingHuellaTimer = null;
    }

    pollingHuellaInicio = 0;
}


// =========================================================
// MOSTRAR ESTADO DEL LECTOR
// =========================================================

function mostrarEstadoHuella(
    tipo,
    html
) {

    const estado =
        document.getElementById(
            'huellasEstadoLector'
        );

    if (!estado) {
        return;
    }

    estado.className =
        `alert alert-${tipo} py-2 mb-3`;

    estado.innerHTML = html;
}


// =========================================================
// INICIALIZAR DATATABLE
// =========================================================

function initDataTable() {

    if (
        $.fn.DataTable.isDataTable(
            '#tablaEmpleados'
        )
    ) {
        $('#tablaEmpleados')
            .DataTable()
            .destroy();
    }

    tablaEmpleados =
        $('#tablaEmpleados').DataTable({

            language: {

                "sProcessing":
                    "Procesando...",

                "sLengthMenu":
                    "Mostrar _MENU_ registros",

                "sZeroRecords":
                    "No se encontraron resultados",

                "sEmptyTable":
                    "Ningún dato disponible en esta tabla",

                "sInfo":
                    "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",

                "sInfoEmpty":
                    "Mostrando registros del 0 al 0 de un total de 0 registros",

                "sInfoFiltered":
                    "(filtrado de un total de _MAX_ registros)",

                "sSearch":
                    "Buscar:",

                "sLoadingRecords":
                    "Cargando...",

                "oPaginate": {

                    "sFirst":
                        "Primero",

                    "sLast":
                        "Último",

                    "sNext":
                        "Siguiente",

                    "sPrevious":
                        "Anterior"
                },

                "oAria": {

                    "sSortAscending":
                        ": Activar para ordenar la columna de manera ascendente",

                    "sSortDescending":
                        ": Activar para ordenar la columna de manera descendente"
                }
            },

            responsive: true,
            autoWidth: false
        });
}


// ---------------------------------------------------------------------
// 1. LISTAR EMPLEADOS
// ---------------------------------------------------------------------

async function cargarEmpleados() {

    try {

        const res =
            await fetch(
                API_BASE +
                '/fichajes/empleados/empleados.php?action=listar',
                {
                    method: 'GET',
                    headers: obtenerHeadersSSO()
                }
            );

        const json =
            await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg ||
                "Error al cargar empleados",
                "error"
            );

            return;
        }

        empleadosList =
            json.data;

        tablaEmpleados.clear();

        empleadosList.forEach(e => {

            const activo =
                e.activo == 1;

            const badgeEstado =
                activo
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>';

            const badgeTarjeta =
                e.tarjeta
                    ? `<span class="badge bg-secondary">${e.tarjeta}</span>`
                    : '<span class="text-muted small">Sin asignar</span>';

            const btnEditar = `
                <button
                    class="btn btn-sm btn-info text-white me-1"
                    onclick="editarEmpleado(${e.idempleado})"
                    title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
            `;

            const btnCiclo = `
                <button
                    class="btn btn-sm btn-primary text-white me-1"
                    onclick="asignarCicloEmpleado(${e.idempleado})"
                    title="Asignar ciclo">
                    <i class="fas fa-sync-alt"></i>
                </button>
            `;

            const btnEstado =
                activo
                    ? `
                        <button
                            class="btn btn-sm btn-danger"
                            onclick="cambiarEstadoEmpleado(${e.idempleado}, 0)"
                            title="Dar de baja">
                            <i class="fas fa-user-slash"></i>
                        </button>
                    `
                    : `
                        <button
                            class="btn btn-sm btn-success"
                            onclick="cambiarEstadoEmpleado(${e.idempleado}, 1)"
                            title="Dar de alta">
                            <i class="fas fa-user-check"></i>
                        </button>
                    `;

            const btnEnviarLector = `
                <button
                    class="btn btn-sm btn-warning text-dark me-1"
                    onclick="enviarEmpleadoLector(${e.idempleado})"
                    title="Enviar al lector">
                    <i class="fas fa-id-card"></i>
                </button>
            `;

            const btnHuellas = `
                <button
                    type="button"
                    class="btn btn-sm btn-dark text-white me-1"
                    onclick="abrirModalHuellas(${e.idempleado})"
                    title="Gestionar huellas">
                    <i class="fas fa-fingerprint"></i>
                </button>
            `;

            const btnCalendario = `
                <button
                    class="btn btn-sm btn-primary text-white me-1"
                    onclick="abrirCalendarioEmpleado(${e.idempleado})"
                    title="Calendario del empleado">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            `;

            tablaEmpleados.row.add([

                e.documento,

                `<strong>
                    ${e.apellido}, ${e.nombre}
                </strong>`,

                badgeTarjeta,

                e.fecha_inicio ?? '-',

                badgeEstado,

                `${btnEditar}
                 ${btnCiclo}
                 ${btnEnviarLector}
                 ${btnHuellas}
                 ${btnCalendario}
                 ${btnEstado}`
            ]);
        });

        tablaEmpleados.draw();

    } catch (err) {

        console.error(err);

        toast(
            "Error de conexión al obtener empleados",
            "error"
        );
    }
}


// ---------------------------------------------------------------------
// 2. ABRIR Y GUARDAR (CREAR / EDITAR)
// ---------------------------------------------------------------------

async function abrirNuevoEmpleado() {

    $('#formEmpleado')[0].reset();

    $('#edit_empleado_id').val('');

    lectoresEmpleadoList = [];

    $('.modal-title')
        .text('Crear Nuevo Empleado');

    $('#btnGuardarEmpleado')
        .text('Guardar Empleado');

    const contenedor =
        document.getElementById(
            'listaLectoresEmpleado'
        );

    if (contenedor) {

        contenedor.innerHTML = `
            <div class="text-center text-muted py-2">
                Cargando relojes...
            </div>
        `;
    }

    $('#ModalEmpleado').modal('show');

    await cargarLectoresDisponibles();

    renderizarLectoresEmpleado();
}


async function editarEmpleado(id) {

    const e =
        empleadosList.find(
            emp =>
                emp.idempleado == id
        );

    if (!e) {
        return;
    }

    $('#edit_empleado_id')
        .val(e.idempleado);

    $('#emp_documento')
        .val(e.documento);

    $('#emp_nombre')
        .val(e.nombre);

    $('#emp_apellido')
        .val(e.apellido);

    $('#emp_tarjeta')
        .val(e.tarjeta);

    $('#emp_fecha_inicio')
        .val(e.fecha_inicio);

    $('.modal-title')
        .text(
            `Editar Empleado: ${e.apellido}, ${e.nombre}`
        );

    $('#btnGuardarEmpleado')
        .text('Guardar Cambios');

    const contenedor =
        document.getElementById(
            'listaLectoresEmpleado'
        );

    if (contenedor) {

        contenedor.innerHTML = `
            <div class="text-center text-muted py-2">
                Cargando relojes...
            </div>
        `;
    }

    $('#ModalEmpleado').modal('show');

    await cargarLectoresDisponibles();

    await cargarLectoresEmpleado(
        e.idempleado
    );

    renderizarLectoresEmpleado();
}


async function guardarEmpleado() {

    const documento =
        $('#emp_documento')
            .val()
            .trim();

    const nombre =
        $('#emp_nombre')
            .val()
            .trim();

    const apellido =
        $('#emp_apellido')
            .val()
            .trim();

    const id =
        $('#edit_empleado_id')
            .val();

    if (
        !documento ||
        !nombre ||
        !apellido
    ) {

        toast(
            "Complete los campos requeridos (Documento, Nombre y Apellido)",
            "warning"
        );

        return;
    }

    const action =
        id
            ? 'editar'
            : 'crear';

    const formData =
        new FormData(
            document.getElementById(
                'formEmpleado'
            )
        );

    if (id) {
        formData.append(
            'idempleado',
            id
        );
    }

    const lectores = [];

    $('.lector-checkbox:checked')
        .each(function () {

            lectores.push(
                Number(this.value)
            );
        });

    const btn =
        $('#btnGuardarEmpleado');

    const textoOriginal =
        btn.html();

    btn.prop(
        'disabled',
        true
    );

    try {

        const res =
            await fetch(
                API_BASE +
                `/fichajes/empleados/empleados.php?action=${action}`,
                {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                }
            );

        const json =
            await res.json();

        if (json.status !== 'ok') {

            toast(
                json.msg ||
                'No se pudo guardar el empleado.',
                "error"
            );

            return;
        }

        const idEmpleadoGuardado =
            Number(
                id ||
                json.idempleado ||
                json.data?.idempleado ||
                0
            );

        if (!idEmpleadoGuardado) {

            toast(
                'El empleado fue guardado, pero no se pudo obtener su ID para guardar los relojes.',
                'error'
            );

            return;
        }

        const respuestaLectores =
            await fetch(
                API_BASE +
                '/fichajes/empleados/empleados_lectores.php',
                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({
                        idempleado:
                            idEmpleadoGuardado,

                        lectores:
                            lectores
                    })
                }
            );

        const datosLectores =
            await respuestaLectores.json();

        console.log('HTTP lectores:', respuestaLectores.status);
        console.log('Respuesta lectores:', datosLectores);

        if (
            !respuestaLectores.ok ||
            datosLectores.status !== 'ok'
        ) {

            toast(
                datosLectores.message ||
                datosLectores.msg ||
                'El empleado se guardó, pero no se pudieron guardar los relojes.',
                'error'
            );

            return;
        }

        const modalElement =
            document.getElementById(
                'ModalEmpleado'
            );

        if (modalElement) {

            const modal =
                bootstrap.Modal
                    .getInstance(
                        modalElement
                    );

            if (modal) {
                modal.hide();
            }
        }

        toast(
            json.msg ||
            'Empleado guardado correctamente.',
            "success"
        );

        cargarEmpleados();

    } catch (err) {

        console.error(err);

        toast(
            "Error al procesar la solicitud",
            "error"
        );

    } finally {

        btn.prop(
            'disabled',
            false
        );

        btn.html(
            textoOriginal
        );
    }
}


// ---------------------------------------------------------------------
// 3. CAMBIAR ESTADO
// ---------------------------------------------------------------------

function cambiarEstadoEmpleado(
    id,
    nuevoEstado
) {

    const accionTexto =
        nuevoEstado === 1
            ? 'dar de alta'
            : 'dar de baja';

    Swal.fire({

        title: '¿Está seguro?',

        text:
            `¿Desea ${accionTexto} a este empleado?`,

        icon: 'question',

        showCancelButton: true,

        confirmButtonColor:
            nuevoEstado === 1
                ? '#28a745'
                : '#d33',

        cancelButtonColor:
            '#6c757d',

        confirmButtonText:
            'Sí, confirmar',

        cancelButtonText:
            'Cancelar'

    }).then(
        async (result) => {

            if (!result.isConfirmed) {
                return;
            }

            const formData =
                new FormData();

            formData.append(
                'idempleado',
                id
            );

            formData.append(
                'activo',
                nuevoEstado
            );

            try {

                const res =
                    await fetch(
                        API_BASE +
                        '/fichajes/empleados/empleados.php?action=estado',
                        {
                            method: 'POST',
                            headers:
                                obtenerHeadersSSO(),
                            body: formData
                        }
                    );

                const json =
                    await res.json();

                if (
                    json.status !== 'ok'
                ) {

                    toast(
                        json.msg,
                        "error"
                    );

                    return;
                }

                toast(
                    json.msg,
                    "success"
                );

                cargarEmpleados();

            } catch (err) {

                toast(
                    "Error al intentar cambiar el estado",
                    "error"
                );
            }
        }
    );
}


// ---------------------------------------------------------------------
// 4. ENVIAR EMPLEADO AL LECTOR
// ---------------------------------------------------------------------

function enviarEmpleadoLector(
    idempleado
) {

    const empleado =
        empleadosList.find(
            e =>
                e.idempleado ==
                idempleado
        );

    if (!empleado) {

        toast(
            "Empleado no encontrado.",
            "error"
        );

        return;
    }

    Swal.fire({

        title:
            '¿Enviar empleado al lector?',

        html: `
            <div class="text-start">

                <strong>Empleado:</strong>
                ${empleado.apellido},
                ${empleado.nombre}

                <br>

                <strong>Documento:</strong>
                ${empleado.documento}

            </div>
        `,

        icon: 'question',

        showCancelButton: true,

        confirmButtonText:
            'Sí, enviar',

        cancelButtonText:
            'Cancelar'

    }).then(
        async (result) => {

            if (!result.isConfirmed) {
                return;
            }

            try {

                const formData =
                    new FormData();

                formData.append(
                    'idempleado',
                    idempleado
                );

                const res =
                    await fetch(
                        API_BASE +
                        '/fichajes/empleados/empleados.php?action=solicitar_carga',
                        {
                            method: 'POST',
                            headers:
                                obtenerHeadersSSO(),
                            body: formData
                        }
                    );

                const json =
                    await res.json();

                if (
                    json.status !== 'ok'
                ) {

                    toast(
                        json.msg ||
                        "No se pudo generar la solicitud.",
                        "error"
                    );

                    if (
                        json.msg ===
                        'El empleado no tiene un lector activo asignado.'
                    ) {

                        setTimeout(
                            () => {
                                editarEmpleado(
                                    idempleado
                                );
                            },
                            500
                        );
                    }

                    return;
                }

                toast(
                    json.msg ||
                    "Solicitud enviada correctamente.",
                    "success"
                );

            } catch (err) {

                console.error(err);

                toast(
                    "Error de conexión al generar la solicitud.",
                    "error"
                );
            }
        }
    );
}


// ---------------------------------------------------------------------
// 5. CARGAR CICLOS DISPONIBLES
// ---------------------------------------------------------------------

async function cargarCiclosDisponibles() {

    try {

        const res =
            await fetch(
                API_BASE +
                '/fichajes/empleados/ciclos_empleados.php?accion=ciclos',
                {
                    method: 'GET',
                    headers:
                        obtenerHeadersSSO(),
                    cache: 'no-store'
                }
            );

        const json =
            await res.json();

        if (
            json.status !== 'ok'
        ) {

            toast(
                json.message ||
                'Error al cargar los ciclos',
                'error'
            );

            return;
        }

        ciclosDisponibles =
            json.data || [];

        const select =
            document.getElementById(
                'ciclo_empleado_ciclo'
            );

        if (!select) {
            return;
        }

        select.innerHTML = `
            <option value="">
                Seleccione un ciclo
            </option>
        `;

        ciclosDisponibles.forEach(
            ciclo => {

                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    ciclo.idciclo;

                option.textContent =
                    `${ciclo.nombre} (${ciclo.cantidad_semanas} semanas)`;

                select.appendChild(
                    option
                );
            }
        );

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al cargar los ciclos',
            'error'
        );
    }
}


// ---------------------------------------------------------------------
// 6. ABRIR ASIGNACIÓN DE CICLO
// ---------------------------------------------------------------------

async function asignarCicloEmpleado(
    idempleado
) {

    const empleado =
        empleadosList.find(
            e =>
                e.idempleado ==
                idempleado
        );

    if (!empleado) {

        toast(
            'Empleado no encontrado.',
            'error'
        );

        return;
    }

    document.getElementById(
        'ciclo_empleado_id'
    ).value =
        idempleado;

    document.getElementById(
        'ciclo_empleado_asignacion_id'
    ).value =
        '';

    document.getElementById(
        'cicloEmpleadoNombre'
    ).textContent =
        `${empleado.apellido}, ${empleado.nombre}`;

    document.getElementById(
        'ciclo_empleado_ciclo'
    ).value =
        '';

    document.getElementById(
        'ciclo_empleado_fecha_desde'
    ).value =
        empleado.fecha_inicio || '';

    document.getElementById(
        'ciclo_empleado_fecha_hasta'
    ).value =
        '';

    document.getElementById(
        'btnGuardarCicloEmpleado'
    ).innerHTML = `
        <i class="fas fa-save me-2"></i>
        Guardar Ciclo
    `;

    if (modalCicloEmpleado) {
        modalCicloEmpleado.show();
    }

    await cargarAsignacionesEmpleado(
        idempleado
    );
}


// ---------------------------------------------------------------------
// 7. CARGAR HISTORIAL DEL EMPLEADO
// ---------------------------------------------------------------------

async function cargarAsignacionesEmpleado(
    idempleado
) {

    const tbody =
        document.getElementById(
            'listaCiclosEmpleado'
        );

    if (tbody) {

        tbody.innerHTML = `
            <tr>
                <td
                    colspan="4"
                    class="text-center text-muted">
                    Cargando...
                </td>
            </tr>
        `;
    }

    try {

        const res =
            await fetch(
                API_BASE +
                `/fichajes/empleados/ciclos_empleados.php?idempleado=${encodeURIComponent(idempleado)}`,
                {
                    method: 'GET',
                    headers:
                        obtenerHeadersSSO(),
                    cache: 'no-store'
                }
            );

        const json =
            await res.json();

        if (
            json.status !== 'ok'
        ) {

            toast(
                json.message ||
                'Error al cargar las asignaciones',
                'error'
            );

            return;
        }

        ciclosEmpleadoList =
            json.data || [];

        renderizarAsignacionesEmpleado();

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al cargar las asignaciones',
            'error'
        );
    }
}


// ---------------------------------------------------------------------
// 8. MOSTRAR HISTORIAL DE CICLOS
// ---------------------------------------------------------------------

function renderizarAsignacionesEmpleado() {

    const tbody =
        document.getElementById(
            'listaCiclosEmpleado'
        );

    if (!tbody) {
        return;
    }

    tbody.innerHTML = '';

    if (!ciclosEmpleadoList.length) {

        tbody.innerHTML = `
            <tr>
                <td
                    colspan="4"
                    class="text-center text-muted">
                    Sin asignaciones.
                </td>
            </tr>
        `;

        return;
    }

    ciclosEmpleadoList.forEach(
        asignacion => {

            const tr =
                document.createElement(
                    'tr'
                );

            const tdCiclo =
                document.createElement(
                    'td'
                );

            tdCiclo.innerHTML =
                `<strong>
                    ${escapeHtml(
                        asignacion.ciclo_nombre
                    )}
                </strong>`;

            const tdDesde =
                document.createElement(
                    'td'
                );

            tdDesde.textContent =
                asignacion.fecha_desde ||
                '-';

            const tdHasta =
                document.createElement(
                    'td'
                );

            tdHasta.textContent =
                asignacion.fecha_hasta ||
                'Sin fecha';

            const tdAccion =
                document.createElement(
                    'td'
                );

            tdAccion.className =
                'text-center';

            const btnEditar =
                document.createElement(
                    'button'
                );

            btnEditar.type =
                'button';

            btnEditar.className =
                'btn btn-sm btn-info text-white';

            btnEditar.title =
                'Editar asignación';

            btnEditar.innerHTML =
                '<i class="fas fa-edit"></i>';

            btnEditar.onclick =
                function () {

                    editarAsignacionCiclo(
                        asignacion
                            .idempleado_ciclo
                    );
                };

            tdAccion.appendChild(
                btnEditar
            );

            tr.appendChild(
                tdCiclo
            );

            tr.appendChild(
                tdDesde
            );

            tr.appendChild(
                tdHasta
            );

            tr.appendChild(
                tdAccion
            );

            tbody.appendChild(
                tr
            );
        }
    );
}


// ---------------------------------------------------------------------
// 9. EDITAR ASIGNACIÓN
// ---------------------------------------------------------------------

function editarAsignacionCiclo(
    idempleadoCiclo
) {

    const asignacion =
        ciclosEmpleadoList.find(
            a =>
                a.idempleado_ciclo ==
                idempleadoCiclo
        );

    if (!asignacion) {
        return;
    }

    document.getElementById(
        'ciclo_empleado_asignacion_id'
    ).value =
        asignacion.idempleado_ciclo;

    document.getElementById(
        'ciclo_empleado_ciclo'
    ).value =
        asignacion.idciclo;

    document.getElementById(
        'ciclo_empleado_fecha_desde'
    ).value =
        asignacion.fecha_desde ||
        '';

    document.getElementById(
        'ciclo_empleado_fecha_hasta'
    ).value =
        asignacion.fecha_hasta ||
        '';

    document.getElementById(
        'btnGuardarCicloEmpleado'
    ).innerHTML = `
        <i class="fas fa-save me-2"></i>
        Guardar Cambios
    `;
}


// ---------------------------------------------------------------------
// 10. GUARDAR ASIGNACIÓN
// ---------------------------------------------------------------------

async function guardarCicloEmpleado() {

    const idempleado =
        parseInt(
            document.getElementById(
                'ciclo_empleado_id'
            ).value,
            10
        );

    const idempleadoCiclo =
        document.getElementById(
            'ciclo_empleado_asignacion_id'
        ).value;

    const idciclo =
        parseInt(
            document.getElementById(
                'ciclo_empleado_ciclo'
            ).value,
            10
        );

    const fechaDesde =
        document.getElementById(
            'ciclo_empleado_fecha_desde'
        ).value;

    const fechaHasta =
        document.getElementById(
            'ciclo_empleado_fecha_hasta'
        ).value;

    if (!idempleado) {

        toast(
            'Empleado inválido.',
            'warning'
        );

        return;
    }

    if (!idciclo) {

        toast(
            'Seleccione un ciclo.',
            'warning'
        );

        return;
    }

    if (!fechaDesde) {

        toast(
            'Indique la fecha desde.',
            'warning'
        );

        return;
    }

    if (
        fechaHasta &&
        fechaHasta < fechaDesde
    ) {

        toast(
            'La fecha hasta no puede ser anterior a la fecha desde.',
            'warning'
        );

        return;
    }

    const btn =
        document.getElementById(
            'btnGuardarCicloEmpleado'
        );

    const textoOriginal =
        btn.innerHTML;

    btn.disabled = true;

    btn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2"></span>
        Guardando...
    `;

    try {

        const res =
            await fetch(
                API_BASE +
                '/fichajes/empleados/ciclos_empleados.php',
                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        accion:
                            'guardar',

                        idempleado:
                            idempleado,

                        idempleado_ciclo:
                            idempleadoCiclo || '',

                        idciclo:
                            idciclo,

                        fecha_desde:
                            fechaDesde,

                        fecha_hasta:
                            fechaHasta ||
                            null
                    })
                }
            );

        const json =
            await res.json();

        if (
            json.status !== 'ok'
        ) {

            toast(
                json.message ||
                'No se pudo guardar la asignación.',
                'error'
            );

            return;
        }

        toast(
            json.message ||
            'Ciclo asignado correctamente.',
            'success'
        );

        document.getElementById(
            'ciclo_empleado_asignacion_id'
        ).value = '';

        document.getElementById(
            'ciclo_empleado_ciclo'
        ).value = '';

        document.getElementById(
            'ciclo_empleado_fecha_hasta'
        ).value = '';

        btn.innerHTML = `
            <i class="fas fa-save me-2"></i>
            Guardar Ciclo
        `;

        await cargarAsignacionesEmpleado(
            idempleado
        );

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al guardar la asignación.',
            'error'
        );

    } finally {

        btn.disabled = false;

        if (
            btn.innerHTML.includes(
                'spinner-border'
            )
        ) {

            btn.innerHTML =
                textoOriginal;
        }
    }
}


// ---------------------------------------------------------------------
// RELOJES DISPONIBLES
// ---------------------------------------------------------------------

async function cargarLectoresDisponibles() {

    try {

        const res =
            await fetch(
                API_BASE +
                '/fichajes/empleados/empleados_lectores.php?accion=lectores',
                {
                    method: 'GET',
                    headers:
                        obtenerHeadersSSO(),
                    cache: 'no-store'
                }
            );

        const json =
            await res.json();

        if (
            json.status !== 'ok'
        ) {

            toast(
                json.message ||
                json.msg ||
                'Error al cargar los relojes.',
                'error'
            );

            lectoresDisponibles = [];

            return false;
        }

        lectoresDisponibles =
            json.data || [];

        return true;

    } catch (err) {

        console.error(err);

        lectoresDisponibles = [];

        toast(
            'Error de conexión al cargar los relojes.',
            'error'
        );

        return false;
    }
}


// ---------------------------------------------------------------------
// RELOJES ASIGNADOS AL EMPLEADO
// ---------------------------------------------------------------------

async function cargarLectoresEmpleado(
    idempleado
) {

    lectoresEmpleadoList = [];

    try {

        const res =
            await fetch(
                API_BASE +
                `/fichajes/empleados/empleados_lectores.php?accion=empleado&idempleado=${encodeURIComponent(idempleado)}`,
                {
                    method: 'GET',
                    headers:
                        obtenerHeadersSSO(),
                    cache: 'no-store'
                }
            );

        const json =
            await res.json();

        if (
            json.status !== 'ok'
        ) {

            toast(
                json.message ||
                json.msg ||
                'Error al cargar los relojes asignados.',
                'error'
            );

            return false;
        }

        lectoresEmpleadoList =
            json.data || [];

        return true;

    } catch (err) {

        console.error(err);

        toast(
            'Error de conexión al cargar los relojes asignados.',
            'error'
        );

        return false;
    }
}


// ---------------------------------------------------------------------
// MOSTRAR RELOJES
// ---------------------------------------------------------------------

function renderizarLectoresEmpleado() {

    const contenedor =
        document.getElementById(
            'listaLectoresEmpleado'
        );

    if (!contenedor) {
        return;
    }

    contenedor.innerHTML = '';

    if (!lectoresDisponibles.length) {

        contenedor.innerHTML = `
            <div class="text-muted small">
                No hay relojes activos disponibles.
            </div>
        `;

        return;
    }

    lectoresDisponibles.forEach(
        lector => {

            const asignado =
                lectoresEmpleadoList.some(
                    id =>
                        Number(id) ===
                        Number(lector.idlector)
                );

            const div =
                document.createElement(
                    'div'
                );

            div.className =
                'form-check border rounded p-2 mb-2';

            div.innerHTML = `

                <input
                    class="form-check-input ms-0 me-2 lector-checkbox"
                    type="checkbox"
                    value="${lector.idlector}"
                    id="lector_${lector.idlector}"
                    ${asignado ? 'checked' : ''}
                >

                <label
                    class="form-check-label ms-1 w-100"
                    for="lector_${lector.idlector}">

                    <strong>
                        ${escapeHtml(
                            lector.nombre
                        )}
                    </strong>

                    ${
                        Number(
                            lector.predeterminado
                        ) === 1

                            ? `
                                <span
                                    class="badge bg-primary ms-2">
                                    Predeterminado
                                </span>
                            `

                            : ''
                    }

                    <div class="small text-muted">

                        ${escapeHtml(
                            lector.ip
                        )}

                        ${
                            lector.ubicacion

                                ? `
                                    ·
                                    ${escapeHtml(
                                        lector.ubicacion
                                    )}
                                `

                                : ''
                        }

                    </div>

                </label>
            `;

            contenedor.appendChild(
                div
            );
        }
    );
}


// =========================================================
// GESTIÓN DE HUELLAS
// =========================================================

async function gestionarHuellas(
    idempleado
) {

    const empleado =
        empleadosList.find(
            e =>
                Number(e.idempleado) ===
                Number(idempleado)
        );

    if (!empleado) {

        toast(
            'No se encontró el empleado.',
            'error'
        );

        return;
    }

    const nombreEmpleado =
        `${empleado.nombre || ''} ${empleado.apellido || ''}`
            .trim();

    Swal.fire({

        title:
            'Huellas del empleado',

        html: `
            <div class="text-left">

                <div class="mb-3">
                    <strong>
                        ${nombreEmpleado}
                    </strong>
                </div>

                <div id="contenedorHuellasEmpleado">

                    <div class="text-center py-3">

                        <i class="fas fa-spinner fa-spin"></i>

                        Consultando huellas...

                    </div>

                </div>

            </div>
        `,

        width: '700px',

        showConfirmButton: false,

        showCloseButton: true,

        didOpen: () => {

            cargarHuellasEmpleado(
                idempleado
            );
        }
    });
}


// =========================================================
// CARGAR HUELLAS DESDE LA BASE
// =========================================================

async function cargarHuellasEmpleado(
    idempleado
) {

    const contenedor =
        document.getElementById(
            'listaHuellasEmpleado'
        );

    if (!contenedor) {
        return false;
    }

    try {

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/empleados/huellas.php',
                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        accion:
                            'listar_huellas',

                        idempleado:
                            Number(idempleado)
                    })
                }
            );

        const datos =
            await respuesta.json();

        if (
            !respuesta.ok ||
            datos.status !== 'ok'
        ) {

            throw new Error(
                datos.msg ||
                datos.message ||
                'No se pudieron consultar las huellas.'
            );
        }

        renderizarHuellasEmpleado(
            Array.isArray(datos.huellas)
                ? datos.huellas
                : []
        );

        return true;

    } catch (error) {

        console.error(
            'Error cargando huellas:',
            error
        );

        renderizarHuellasEmpleado([]);

        toast(
            error.message ||
            'No se pudieron consultar las huellas.',
            'error'
        );

        return false;
    }
}


// =========================================================
// ABRIR MODAL DE HUELLAS
// =========================================================

async function abrirModalHuellas(
    idempleado
) {

    // Si hubiera un polling anterior,
    // detenerlo antes de abrir otro empleado.
    detenerPollingHuella();

    const empleado =
        empleadosList.find(
            e =>
                Number(e.idempleado) ===
                Number(idempleado)
        );

    if (!empleado) {

        toast(
            'No se encontró el empleado.',
            'error'
        );

        return;
    }

    const modalElement =
        document.getElementById(
            'ModalHuellasEmpleado'
        );

    if (!modalElement) {

        toast(
            'No se encontró el modal de huellas.',
            'error'
        );

        return;
    }

    const nombreEmpleado =
        `${empleado.apellido || ''}, ${empleado.nombre || ''}`
            .trim();

    const inputEmpleado =
        document.getElementById(
            'huellasIdEmpleado'
        );

    if (inputEmpleado) {
        inputEmpleado.value =
            idempleado;
    }

    const nombreElement =
        document.getElementById(
            'huellasNombreEmpleado'
        );

    if (nombreElement) {

        nombreElement.textContent =
            nombreEmpleado;
    }

    const combo =
        document.getElementById(
            'huella_idlector'
        );

    const estado =
        document.getElementById(
            'huellasEstadoLector'
        );

    if (!combo) {

        toast(
            'No se encontró el selector de lector.',
            'error'
        );

        return;
    }

    // ---------------------------------------------------------
    // PREPARAR MODAL
    // ---------------------------------------------------------

    combo.innerHTML = `
        <option value="">
            Cargando lectores...
        </option>
    `;

    combo.disabled = true;

    if (estado) {

        estado.className =
            'alert alert-secondary py-2 mb-3';

        estado.innerHTML = `
            <i class="fas fa-spinner fa-spin me-1"></i>
            Cargando lectores disponibles...
        `;
    }

    renderizarHuellasEmpleado([]);

    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

    try {

        // -----------------------------------------------------
        // CARGAR LECTORES
        // -----------------------------------------------------

        const lectoresOK =
            await cargarLectoresDisponibles();

        if (!lectoresOK) {

            combo.innerHTML = `
                <option value="">
                    Error al cargar lectores
                </option>
            `;

            combo.disabled = true;

            if (estado) {

                estado.className =
                    'alert alert-danger py-2 mb-3';

                estado.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No se pudieron cargar los lectores.
                `;
            }

        } else if (
            !Array.isArray(
                lectoresDisponibles
            ) ||
            lectoresDisponibles.length === 0
        ) {

            combo.innerHTML = `
                <option value="">
                    No hay lectores disponibles
                </option>
            `;

            combo.disabled = true;

            if (estado) {

                estado.className =
                    'alert alert-warning py-2 mb-3';

                estado.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No hay lectores disponibles.
                `;
            }

        } else {

            // -------------------------------------------------
            // LLENAR SELECT
            // -------------------------------------------------

            combo.innerHTML = `
                <option value="">
                    Seleccione un lector
                </option>
            `;

            lectoresDisponibles.forEach(
                lector => {

                    const option =
                        document.createElement(
                            'option'
                        );

                    option.value =
                        lector.idlector;

                    option.textContent =
                        lector.nombre;

                    combo.appendChild(
                        option
                    );
                }
            );

            combo.disabled = false;

            if (estado) {

                estado.className =
                    'alert alert-secondary py-2 mb-3';

                estado.innerHTML = `
                    <i class="fas fa-info-circle me-1"></i>
                    Seleccione un lector para registrar una huella.
                `;
            }
        }

        // -----------------------------------------------------
        // LAS HUELLAS SE CARGAN DESDE BD
        // -----------------------------------------------------

        await cargarHuellasEmpleado(
            idempleado
        );

    } catch (error) {

        console.error(
            'Error cargando modal de huellas:',
            error
        );

        combo.innerHTML = `
            <option value="">
                Error al cargar lectores
            </option>
        `;

        combo.disabled = true;

        if (estado) {

            estado.className =
                'alert alert-danger py-2 mb-3';

            estado.innerHTML = `
                <i class="fas fa-exclamation-triangle me-1"></i>
                ${escapeHtml(
                    error.message ||
                    'No se pudieron cargar los datos.'
                )}
            `;
        }
    }
}


// =========================================================
// HUELLAS DEL EMPLEADO
// =========================================================

function renderizarHuellasEmpleado(
    huellas = []
) {

    const contenedor =
        document.getElementById(
            'listaHuellasEmpleado'
        );

    if (!contenedor) {
        return;
    }

    const dedos = [

        'Pulgar derecho',

        'Índice derecho',

        'Medio derecho',

        'Anular derecho',

        'Meñique derecho',

        'Pulgar izquierdo',

        'Índice izquierdo',

        'Medio izquierdo',

        'Anular izquierdo',

        'Meñique izquierdo'
    ];

    contenedor.innerHTML = '';

    dedos.forEach(
        (
            nombreDedo,
            iddedo
        ) => {

            const huellaRegistrada =
                huellas.some(
                    huella =>
                        Number(
                            huella.iddedo
                        ) ===
                        Number(iddedo)
                );

            const div =
                document.createElement(
                    'div'
                );

            div.className =
                'col-md-6 mb-3';

            div.innerHTML = `

                <div
                    class="border rounded p-3 h-100">

                    <div
                        class="d-flex justify-content-between align-items-center mb-3">

                        <div>

                            <div class="fw-semibold">

                                <i
                                    class="fas fa-fingerprint me-1">
                                </i>

                                ${nombreDedo}

                            </div>

                            <div
                                class="small text-muted">

                                Dedo ${iddedo}

                            </div>

                        </div>

                        ${
                            huellaRegistrada

                                ? `
                                    <span
                                        class="badge bg-success">
                                        Registrada
                                    </span>
                                `

                                : `
                                    <span
                                        class="badge bg-secondary">
                                        Sin registrar
                                    </span>
                                `
                        }

                    </div>

                    <button
                        type="button"
                        class="btn btn-sm ${
                            huellaRegistrada
                                ? 'btn-warning'
                                : 'btn-primary'
                        } w-100"
                        onclick="registrarHuellaEmpleado(${iddedo})">

                        <i
                            class="fas fa-fingerprint me-1">
                        </i>

                        ${
                            huellaRegistrada
                                ? 'Reemplazar'
                                : 'Registrar'
                        }

                    </button>

                </div>
            `;

            contenedor.appendChild(
                div
            );
        }
    );
}


// =========================================================
// CONSULTAR ESTADO DE TAREA DE HUELLA
// =========================================================

async function consultarEstadoTareaHuella(
    idtarea,
    idempleado
) {

    if (!pollingHuellaActivo) {
        return;
    }

    // ---------------------------------------------------------
    // TIMEOUT
    // ---------------------------------------------------------

    if (
        Date.now() -
        pollingHuellaInicio >=
        POLLING_HUELLA_TIMEOUT
    ) {

        detenerPollingHuella();

        mostrarEstadoHuella(
            'warning',
            `
                <i class="fas fa-clock me-1"></i>
                Se agotó el tiempo de espera del lector.
                Puede consultar nuevamente o intentar registrar
                la huella otra vez.
            `
        );

        return;
    }

    try {

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/empleados/huellas.php',
                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        accion:
                            'estado_tarea',

                        idtarea:
                            Number(idtarea),

                        idempleado:
                            Number(idempleado)
                    })
                }
            );

        const datos =
            await respuesta.json();

        if (
            !respuesta.ok ||
            datos.status !== 'ok'
        ) {

            throw new Error(
                datos.msg ||
                datos.message ||
                'No se pudo consultar el estado de la tarea.'
            );
        }

        const estadoTarea =
            String(
                datos.estado ||
                ''
            ).toLowerCase();

        // -----------------------------------------------------
        // PENDIENTE
        // -----------------------------------------------------

        if (
            estadoTarea ===
            'pendiente'
        ) {

            mostrarEstadoHuella(
                'info',
                `
                    <i class="fas fa-clock me-1"></i>
                    Solicitud pendiente. Esperando al agente...
                `
            );

            pollingHuellaTimer =
                setTimeout(
                    () => {

                        consultarEstadoTareaHuella(
                            idtarea,
                            idempleado
                        );

                    },
                    POLLING_HUELLA_INTERVALO
                );

            return;
        }

        // -----------------------------------------------------
        // PROCESANDO
        // -----------------------------------------------------

        if (
            estadoTarea ===
            'procesando'
        ) {

            mostrarEstadoHuella(
                'info',
                `
                    <i class="fas fa-spinner fa-spin me-1"></i>
                    El lector está registrando la huella...
                `
            );

            pollingHuellaTimer =
                setTimeout(
                    () => {

                        consultarEstadoTareaHuella(
                            idtarea,
                            idempleado
                        );

                    },
                    POLLING_HUELLA_INTERVALO
                );

            return;
        }

        // -----------------------------------------------------
        // COMPLETADA
        // -----------------------------------------------------

        if (
            estadoTarea ===
            'completada'
        ) {

            detenerPollingHuella();

            mostrarEstadoHuella(
                'success',
                `
                    <i class="fas fa-check-circle me-1"></i>
                    Huella registrada correctamente.
                `
            );

            await cargarHuellasEmpleado(
                idempleado
            );

            toast(
                'La huella fue registrada correctamente.',
                'success'
            );

            return;
        }

        // -----------------------------------------------------
        // ERROR DE PROCESAMIENTO
        // -----------------------------------------------------

        if (
            estadoTarea ===
            'error'
        ) {

            detenerPollingHuella();

            let mensaje =
                datos.msg ||
                'El lector no pudo completar el registro de la huella.';

            /*
             * Si huellas.php devuelve información del
             * procesamiento, intentamos utilizarla.
             */
            if (
                datos.respuesta &&
                typeof datos.respuesta ===
                    'object'
            ) {

                mensaje =
                    datos.respuesta.msg ||
                    datos.respuesta.message ||
                    mensaje;
            }

            mostrarEstadoHuella(
                'danger',
                `
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    ${escapeHtml(mensaje)}
                `
            );

            toast(
                mensaje,
                'error'
            );

            return;
        }

        // -----------------------------------------------------
        // ESTADO DESCONOCIDO
        // -----------------------------------------------------

        throw new Error(
            'La tarea devolvió un estado desconocido.'
        );

    } catch (error) {

        console.error(
            'Error consultando estado de huella:',
            error
        );

        /*
         * Si todavía estamos dentro del tiempo máximo,
         * consideramos que puede ser un error transitorio
         * de comunicación y volvemos a consultar.
         */

        if (
            pollingHuellaActivo &&
            Date.now() -
                pollingHuellaInicio <
                POLLING_HUELLA_TIMEOUT
        ) {

            mostrarEstadoHuella(
                'warning',
                `
                    <i class="fas fa-sync-alt fa-spin me-1"></i>
                    Error temporal al consultar el estado.
                    Reintentando...
                `
            );

            pollingHuellaTimer =
                setTimeout(
                    () => {

                        consultarEstadoTareaHuella(
                            idtarea,
                            idempleado
                        );

                    },
                    2000
                );

            return;
        }

        detenerPollingHuella();

        mostrarEstadoHuella(
            'danger',
            `
                <i class="fas fa-exclamation-triangle me-1"></i>
                ${escapeHtml(
                    error.message ||
                    'No se pudo consultar el estado de la tarea.'
                )}
            `
        );

        toast(
            error.message ||
            'No se pudo consultar el estado de la tarea.',
            'error'
        );
    }
}


// =========================================================
// INICIAR POLLING DE HUELLA
// =========================================================

function iniciarPollingHuella(
    idtarea,
    idempleado
) {

    detenerPollingHuella();

    pollingHuellaActivo = true;

    pollingHuellaInicio =
        Date.now();

    consultarEstadoTareaHuella(
        Number(idtarea),
        Number(idempleado)
    );
}


// =========================================================
// REGISTRAR / REEMPLAZAR HUELLA
// =========================================================

async function registrarHuellaEmpleado(
    iddedo
) {

    const idempleado =
        Number(
            document.getElementById(
                'huellasIdEmpleado'
            ).value
        );

    const idlector =
        Number(
            document.getElementById(
                'huella_idlector'
            ).value
        );

    if (!idempleado) {

        toast(
            'No se encontró el empleado.',
            'error'
        );

        return;
    }

    if (!idlector) {

        toast(
            'Seleccione un lector.',
            'warning'
        );

        return;
    }

    const nombresDedos = [

        'Pulgar derecho',

        'Índice derecho',

        'Medio derecho',

        'Anular derecho',

        'Meñique derecho',

        'Pulgar izquierdo',

        'Índice izquierdo',

        'Medio izquierdo',

        'Anular izquierdo',

        'Meñique izquierdo'
    ];

    const nombreDedo =
        nombresDedos[
            Number(iddedo)
        ] ||
        `Dedo ${iddedo}`;

    const empleado =
        empleadosList.find(
            e =>
                Number(
                    e.idempleado
                ) ===
                idempleado
        );

    const nombreEmpleado =
        empleado
            ? `${empleado.apellido}, ${empleado.nombre}`
            : '';

    const confirmacion =
        await Swal.fire({

            title:
                'Registrar huella',

            html: `
                <div class="text-start">

                    <p class="mb-2">

                        <strong>
                            Empleado:
                        </strong>

                        ${escapeHtml(
                            nombreEmpleado
                        )}

                    </p>

                    <p class="mb-2">

                        <strong>
                            Dedo:
                        </strong>

                        ${escapeHtml(
                            nombreDedo
                        )}

                    </p>

                    <p class="mb-0">

                        Coloque el dedo en el lector
                        <strong>3 veces</strong>
                        cuando sea solicitado.

                    </p>

                </div>
            `,

            icon:
                'question',

            showCancelButton:
                true,

            confirmButtonText:
                'Registrar',

            cancelButtonText:
                'Cancelar'
        });

    if (
        !confirmacion.isConfirmed
    ) {
        return;
    }

    // Si había otro polling activo,
    // detenerlo antes de iniciar el nuevo.
    detenerPollingHuella();

    try {

        mostrarEstadoHuella(
            'info',
            `
                <i class="fas fa-spinner fa-spin me-1"></i>
                Generando solicitud de registro...
            `
        );

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/empleados/huellas.php',
                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        accion:
                            'enrolar_huella',

                        idempleado:
                            idempleado,

                        idlector:
                            idlector,

                        iddedo:
                            Number(iddedo)
                    })
                }
            );

        const datos =
            await respuesta.json();

        if (
            !respuesta.ok ||
            datos.status !== 'ok'
        ) {

            throw new Error(
                datos.msg ||
                datos.message ||
                'No se pudo generar la solicitud.'
            );
        }

        const idtarea =
            Number(
                datos.idtarea ||
                datos.data?.idtarea ||
                0
            );

        if (!idtarea) {

            throw new Error(
                'La solicitud fue generada pero no se recibió el ID de la tarea.'
            );
        }

        toast(
            datos.msg ||
            'Solicitud enviada al lector.',
            'success'
        );

        mostrarEstadoHuella(
            'info',
            `
                <i class="fas fa-spinner fa-spin me-1"></i>
                Esperando que el lector registre la huella...
            `
        );

        // -----------------------------------------------------
        // INICIAR POLLING
        // -----------------------------------------------------

        iniciarPollingHuella(
            idtarea,
            idempleado
        );

    } catch (error) {

        console.error(
            'Error al registrar huella:',
            error
        );

        detenerPollingHuella();

        mostrarEstadoHuella(
            'danger',
            `
                <i class="fas fa-exclamation-triangle me-1"></i>
                ${escapeHtml(
                    error.message ||
                    'No se pudo registrar la huella.'
                )}
            `
        );

        Swal.fire({

            icon:
                'error',

            title:
                'Error',

            text:
                error.message ||
                'No se pudo registrar la huella.'
        });
    }
}


// =========================================================
// CALENDARIO
// =========================================================

function abrirCalendarioEmpleado(
    idempleado
) {

    const empleado =
        empleadosList.find(
            e =>
                Number(
                    e.idempleado
                ) ===
                Number(idempleado)
        );

    if (!empleado) {

        toast(
            'No se encontró el empleado.',
            'error'
        );

        return;
    }

    window.location.href =
        'calendario_empleado.php?idempleado=' +
        encodeURIComponent(
            idempleado
        );
}


// =========================================================
// ESCAPE HTML
// =========================================================

function escapeHtml(text) {

    if (
        text === null ||
        text === undefined
    ) {
        return '';
    }

    return String(text)

        .replace(
            /&/g,
            '&amp;'
        )

        .replace(
            /</g,
            '&lt;'
        )

        .replace(
            />/g,
            '&gt;'
        )

        .replace(
            /"/g,
            '&quot;'
        )

        .replace(
            /'/g,
            '&#039;'
        );
}