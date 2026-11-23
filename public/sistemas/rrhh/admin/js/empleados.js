let tablaEmpleados = null;
let empleadosList = [];
let sectoresDisponibles = [];
let puestosDisponibles = [];

const API_EMPLEADOS =
    API_BASE + '/rrhh/personal/empleados.php';

document.addEventListener('DOMContentLoaded', () => {

    if (!localStorage.getItem('sso_token')) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    inicializarTablaEmpleados();
    cargarDatosIniciales();
    cargarEmpleados();

    document
        .getElementById('btnNuevoEmpleado')
        .addEventListener('click', abrirNuevoEmpleado);

    document
        .getElementById('formEmpleado')
        .addEventListener('submit', guardarEmpleado);

    document
        .getElementById('btnVerHistorial')
        .addEventListener('click', abrirHistorialDesdeFormulario);

    document
        .getElementById('btnVerHistorialContractual')
        .addEventListener('click', abrirHistorialContractualDesdeFormulario);
});


// ============================================================
// DATATABLE
// ============================================================

function inicializarTablaEmpleados() {

    tablaEmpleados = $('#tablaEmpleados').DataTable({

        language: {
            processing: 'Procesando...',
            lengthMenu: 'Mostrar _MENU_ registros',
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay empleados registrados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            search: 'Buscar:',
            loadingRecords: 'Cargando...',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        },

        responsive: true,
        autoWidth: false,
        order: [[1, 'asc']]
    });
}


// ============================================================
// PETICIONES
// ============================================================

async function peticionEmpleados(url, opciones = {}) {

    const respuesta = await fetch(url, {
        ...opciones,
        headers: {
            ...obtenerHeadersSSO(),
            ...(opciones.headers || {})
        },
        cache: 'no-store'
    });

    const json = await respuesta.json();

    if (!respuesta.ok || json.status !== 'ok') {
        throw new Error(json.msg || 'No se pudo completar la operación.');
    }

    return json;
}


// ============================================================
// DATOS INICIALES
// ============================================================

async function cargarDatosIniciales() {

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS + '?action=datos_iniciales'
        );

        sectoresDisponibles = json.data.sectores || [];
        puestosDisponibles = json.data.puestos || [];

        cargarSelectLaboral(
            'emp_sector',
            sectoresDisponibles,
            'idsector'
        );

        cargarSelectLaboral(
            'emp_puesto',
            puestosDisponibles,
            'idpuesto'
        );

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');
    }
}


function cargarSelectLaboral(idSelect, registros, campoId) {

    const select = document.getElementById(idSelect);

    if (!select) return;

    select.innerHTML = '<option value="">Sin asignar</option>';

    registros.forEach(registro => {

        const option = document.createElement('option');

        option.value = registro[campoId];
        option.textContent = registro.nombre;

        select.appendChild(option);
    });
}


// ============================================================
// LISTAR EMPLEADOS
// ============================================================

async function cargarEmpleados() {

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS + '?action=listar'
        );

        const puedeVerGrupoFamiliar = tienePermiso('grupo_familiar_ver');
        const puedeVerContactos = tienePermiso('contactos_ver');

        empleadosList = json.data || [];

        tablaEmpleados.clear();

        empleadosList.forEach(e => {

            const estado = Number(e.activo) === 1
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>';

            const nombreCompleto =
                `${escaparHTML(e.apellido)}, ${escaparHTML(e.nombre)}`;

            const btnEditar = `
                <button type="button"
                        class="btn btn-sm btn-outline-primary me-1"
                        onclick="editarEmpleado(${Number(e.idempleado)})"
                        title="Editar empleado">
                    <i class="fas fa-edit"></i>
                </button>
            `;

            const btnHistorial = `
                <button type="button"
                        class="btn btn-sm btn-outline-info me-1"
                        onclick="verHistorialLaboral(${Number(e.idempleado)})"
                        title="Historial laboral">
                    <i class="fas fa-history"></i>
                </button>
            `;

            const btnHistorialContractual = `
                <button type="button"
                        class="btn btn-sm btn-outline-secondary me-1"
                        onclick="verHistorialContractual(${Number(e.idempleado)})"
                        title="Historial contractual">
                    <i class="fas fa-file-contract"></i>
                </button>
            `;

            const btnEstado = Number(e.activo) === 1
                ? `
                    <button type="button"
                            class="btn btn-sm btn-outline-danger me-1"
                            onclick="cambiarEstadoEmpleado(${Number(e.idempleado)}, 0)"
                            title="Dar de baja">
                        <i class="fas fa-user-slash"></i>
                    </button>
                `
                : `
                    <button type="button"
                            class="btn btn-sm btn-outline-success me-1"
                            onclick="cambiarEstadoEmpleado(${Number(e.idempleado)}, 1)"
                            title="Dar de alta">
                        <i class="fas fa-user-check"></i>
                    </button>
                `;
                
                const btnGrupoFamiliar = puedeVerGrupoFamiliar ? `
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            onclick="abrirGrupoFamiliar(${Number(e.idempleado)})"
                            title="Grupo familiar">
                        <i class="fas fa-users"></i>
                    </button>
                ` : '';

                const btnDatosContacto = puedeVerContactos ? `
                    <button type="button"
                            class="btn btn-sm btn-outline-info me-1"
                            onclick="abrirDatosContacto(${Number(e.idempleado)})"
                            title="Datos de contacto">
                        <i class="fas fa-address-book"></i>
                    </button>
                ` : '';

            tablaEmpleados.row.add([

                escaparHTML(e.documento),

                `<strong>${nombreCompleto}</strong>`,

                escaparHTML(e.numero_legajo || '-'),

                escaparHTML(e.sector || '-'),

                escaparHTML(e.puesto || '-'),

                escaparHTML(e.fecha_inicio || '-'),

                estado,

                `${btnEditar}${btnHistorial}${btnHistorialContractual}${btnGrupoFamiliar}${btnDatosContacto}${btnEstado}`

            ]);
        });

        tablaEmpleados.draw();

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');
    }
}


// ============================================================
// NUEVO EMPLEADO
// ============================================================

function abrirNuevoEmpleado() {

    const form = document.getElementById('formEmpleado');

    form.reset();

    document.getElementById('edit_empleado_id').value = '';

    document.getElementById('tituloModalEmpleado').textContent =
        'Nuevo ' + aplicarDiccionarioTexto('Empleado');

    document.getElementById('btnGuardarEmpleado').textContent =
        'Guardar ' + aplicarDiccionarioTexto('Empleado');

    document.getElementById('emp_fecha_desde_contrato').value =
        document.getElementById('emp_fecha_inicio').value ||
        new Date().toISOString().slice(0, 10);

    bootstrap.Modal
        .getOrCreateInstance(document.getElementById('ModalEmpleado'))
        .show();
}


// ============================================================
// EDITAR EMPLEADO
// ============================================================

function editarEmpleado(id) {

    const e = empleadosList.find(
        empleado => Number(empleado.idempleado) === Number(id)
    );

    if (!e) {
        toast(aplicarDiccionarioTexto('Empleado') + ' no encontrado.', 'error');
        return;
    }

    document.getElementById('formEmpleado').reset();

    document.getElementById('edit_empleado_id').value = e.idempleado;

    document.getElementById('emp_documento').value = e.documento || '';
    document.getElementById('emp_nombre').value = e.nombre || '';
    document.getElementById('emp_apellido').value = e.apellido || '';
    document.getElementById('emp_fecha_inicio').value = e.fecha_inicio || '';

    document.getElementById('emp_numero_legajo').value =
        e.numero_legajo || '';

    document.getElementById('emp_tipo_contrato').value =
        e.tipo_contrato || '';

    document.getElementById('emp_categoria').value =
        e.categoria || '';

    document.getElementById('emp_fecha_desde_contrato').value =
        e.fecha_desde_contrato || '';
    
    document.getElementById('emp_observaciones').value =
        e.observaciones || '';

    document.getElementById('emp_sector').value =
        e.idsector || '';

    document.getElementById('emp_puesto').value =
        e.idpuesto || '';

    document.getElementById('emp_fecha_desde').value =
        e.fecha_desde || new Date().toISOString().slice(0, 10);

    document.getElementById('tituloModalEmpleado').textContent =
        aplicarDiccionarioTexto(`Editar empleado: ${e.apellido}, ${e.nombre}`);

    document.getElementById('btnGuardarEmpleado').textContent =
        'Guardar cambios';

    bootstrap.Modal
        .getOrCreateInstance(document.getElementById('ModalEmpleado'))
        .show();
}


// ============================================================
// GUARDAR EMPLEADO
// ============================================================

async function guardarEmpleado(event) {

    event.preventDefault();

    const form = document.getElementById('formEmpleado');

    if (!form.reportValidity()) {
        return;
    }

    const id = document.getElementById('edit_empleado_id').value;

    const action = id ? 'editar' : 'crear';

    const formData = new FormData(form);

    //console.log('SECTOR:', formData.get('idsector'));
    //console.log('PUESTO:', formData.get('idpuesto'));

    const boton = document.getElementById('btnGuardarEmpleado');
    const textoOriginal = boton.textContent;

    boton.disabled = true;
    boton.textContent = 'Guardando...';

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS + '?action=' + action,
            {
                method: 'POST',
                body: formData
            }
        );

        if (!id) {
            const datosEvento = new FormData();
            datosEvento.append('origen', 'rrhh');
            datosEvento.append('destino', 'fichajes');
            datosEvento.append('tipo_evento', 'empleado_creado');
            datosEvento.append('entidad', 'empleado');
            datosEvento.append('identidad', String(json.idempleado));
            datosEvento.append('titulo', 'Nuevo empleado');
            datosEvento.append('descripcion', 'Se dio de alta un empleado desde RRHH.');

            fetch(API_BASE + '/sso/integraciones/eventos/crear.php', {
                method: 'POST',
                headers: obtenerHeadersSSO(),
                body: datosEvento
            }).catch(console.error);
        }

        bootstrap.Modal
            .getOrCreateInstance(document.getElementById('ModalEmpleado'))
            .hide();

        toast(json.msg, 'success');

        await cargarEmpleados();

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');

    } finally {

        boton.disabled = false;
        boton.textContent = textoOriginal;
    }
}


// ============================================================
// HISTORIAL LABORAL
// ============================================================

function abrirHistorialDesdeFormulario() {

    const id = document.getElementById('edit_empleado_id').value;

    if (!id) {
        toast('Primero guardá el ' + aplicarDiccionarioTexto('Empleado') + '  para consultar su historial.', 'warning');
        return;
    }

    verHistorialLaboral(id);
}


async function verHistorialLaboral(id) {

    const empleado = empleadosList.find(
        e => Number(e.idempleado) === Number(id)
    );

    if (!empleado) {
        toast(aplicarDiccionarioTexto('Empleado') + ' no encontrado.', 'error');
        return;
    }

    document.getElementById('historialEmpleadoNombre').textContent =
        `${empleado.apellido}, ${empleado.nombre}`;

    const tbody = document.getElementById('tablaHistorialLaboral');

    tbody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center text-muted">
                Cargando historial...
            </td>
        </tr>
    `;

    bootstrap.Modal
        .getOrCreateInstance(
            document.getElementById('ModalHistorialLaboral')
        )
        .show();

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS +
            '?action=historial&idempleado=' +
            encodeURIComponent(id)
        );

        const historial = json.data || [];

        if (historial.length === 0) {

            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        No hay historial laboral registrado.
                    </td>
                </tr>
            `;

            return;
        }

        tbody.innerHTML = '';

        historial.forEach(h => {

            const vigente = !h.fecha_hasta;

            const fila = document.createElement('tr');

            fila.innerHTML = `
                <td>${escaparHTML(h.sector || '-')}</td>
                <td>${escaparHTML(h.puesto || '-')}</td>
                <td>${escaparHTML(h.fecha_desde || '-')}</td>
                <td>${escaparHTML(h.fecha_hasta || '-')}</td>
                <td>
                    ${
                        vigente
                            ? '<span class="badge bg-success">Vigente</span>'
                            : '<span class="badge bg-secondary">Anterior</span>'
                    }
                </td>
            `;

            tbody.appendChild(fila);
        });

    } catch (error) {

        console.error(error);

        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    ${escaparHTML(error.message)}
                </td>
            </tr>
        `;
    }
}

// ============================================================
// HISTORIAL CONTRACTUAL
// ============================================================

function abrirHistorialContractualDesdeFormulario() {

    const id = document.getElementById('edit_empleado_id').value;

    if (!id) {
        toast('Primero guardá el empleado para consultar su historial contractual.', 'warning');
        return;
    }

    verHistorialContractual(id);
}


async function verHistorialContractual(id) {

    const empleado = empleadosList.find(
        e => Number(e.idempleado) === Number(id)
    );

    if (!empleado) {
        toast('Empleado no encontrado.', 'error');
        return;
    }

    document.getElementById('historialContractualEmpleado').textContent =
        `${empleado.apellido}, ${empleado.nombre}`;

    const tbody = document.getElementById('tablaHistorialContractual');

    tbody.innerHTML = `
        <tr>
            <td colspan="5" class="text-center text-muted">
                Cargando historial...
            </td>
        </tr>
    `;

    bootstrap.Modal
        .getOrCreateInstance(
            document.getElementById('ModalHistorialContractual')
        )
        .show();

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS +
            '?action=historial_contractual&idempleado=' +
            encodeURIComponent(id)
        );

        const historial = json.data || [];

        if (historial.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        No hay historial contractual registrado.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = '';

        historial.forEach(h => {

            const fila = document.createElement('tr');

            fila.innerHTML = `
                <td>${escaparHTML(h.tipo_contrato || '-')}</td>
                <td>${escaparHTML(h.categoria || '-')}</td>
                <td>${escaparHTML(h.fecha_desde || '-')}</td>
                <td>${escaparHTML(h.fecha_hasta || 'Vigente')}</td>
                <td>${escaparHTML(h.observaciones || '-')}</td>
            `;

            tbody.appendChild(fila);
        });

    } catch (error) {

        console.error(error);

        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="text-center text-danger">
                    ${escaparHTML(error.message)}
                </td>
            </tr>
        `;
    }
}

// ============================================================
// CAMBIAR ESTADO
// ============================================================

async function cambiarEstadoEmpleado(id, nuevoEstado) {

    let datosBaja = null;

    if (nuevoEstado === 0) {

        const hoy = new Date();
        const fechaActual = [
            hoy.getFullYear(),
            String(hoy.getMonth() + 1).padStart(2, '0'),
            String(hoy.getDate()).padStart(2, '0')
        ].join('-');

        const resultado = await Swal.fire({
            title: 'Registrar baja',
            html: `
                <div class="text-start">
                    <label class="form-label">Fecha de baja</label>
                    <input
                        type="date"
                        id="swalFechaBaja"
                        class="form-control mb-3"
                        value="${fechaActual}"
                        required
                    >

                    <label class="form-label">Motivo de baja</label>
                    <textarea
                        id="swalMotivoBaja"
                        class="form-control"
                        rows="3"
                        maxlength="500"
                        placeholder="Ingresá el motivo de la baja"
                        required
                    ></textarea>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Registrar baja',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#d33',

            preConfirm: () => {

                const fechaBaja =
                    document.getElementById('swalFechaBaja').value;

                const motivoBaja =
                    document.getElementById('swalMotivoBaja').value.trim();

                if (!fechaBaja || !motivoBaja) {
                    Swal.showValidationMessage(
                        'La fecha y el motivo son obligatorios.'
                    );
                    return false;
                }

                return {
                    fecha_baja: fechaBaja,
                    motivo_baja: motivoBaja
                };
            }
        });

        if (!resultado.isConfirmed) return;

        datosBaja = resultado.value;

    } else {

        const confirmacion = await Swal.fire({
            title: '¿Confirmar alta?',
            text: '¿Querés reactivar este empleado?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Dar de alta',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) return;
    }

    const formData = new FormData();

    formData.append('idempleado', id);
    formData.append('activo', nuevoEstado);

    if (datosBaja) {
        formData.append('fecha_baja', datosBaja.fecha_baja);
        formData.append('motivo_baja', datosBaja.motivo_baja);
    }

    try {

        const json = await peticionEmpleados(
            API_EMPLEADOS + '?action=estado',
            {
                method: 'POST',
                body: formData
            }
        );

        toast(json.msg, 'success');

        await cargarEmpleados();

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');
    }
}

// ============================================================
// ESCAPAR HTML
// ============================================================
/*
function escaparHTML(valor) {

    return String(valor ?? '').replace(/[&<>"']/g, caracter => {

        const entidades = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return entidades[caracter];
    });
}*/