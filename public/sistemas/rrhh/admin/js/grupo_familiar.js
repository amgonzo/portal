
const API_FAMILIAR =
    API_BASE + '/rrhh/personal/grupo_familiar.php';

let idEmpleadoFamiliarActual = null;

document.addEventListener('DOMContentLoaded', () => {

    document.getElementById('btnNuevoFamiliar')
        ?.addEventListener('click', nuevoFamiliar);

    document.getElementById('formFamiliar')
        ?.addEventListener('submit', guardarFamiliar);

});

async function peticionFamiliar(url, opciones = {}) {

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
// ABRIR GRUPO FAMILIAR DEL EMPLEADO
// ============================================================

async function abrirGrupoFamiliar(idEmpleado) {

    idEmpleadoFamiliarActual = idEmpleado;

    document.getElementById('familiar_idempleado').value = idEmpleado;
    document.getElementById('idempleadoFamiliar').value = idEmpleado;

    const empleado = empleadosList.find(
        e => Number(e.idempleado) === Number(idEmpleado)
    );

    document.getElementById('nombreEmpleadoFamiliar').textContent =
        empleado
            ? `${empleado.apellido}, ${empleado.nombre}`
            : '-';

    document.getElementById('tablaFamiliares').innerHTML = `
        <tr>
            <td colspan="5" class="text-center text-muted">
                Cargando familiares...
            </td>
        </tr>
    `;

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalFamiliar')
    ).show();

    await cargarFamiliares();
}

// ============================================================
// LISTAR FAMILIARES
// ============================================================

async function cargarFamiliares() {

    const tabla = document.getElementById('tablaFamiliares');

    try {

        const json = await peticionFamiliar(
            API_FAMILIAR +
            '?action=listar&idempleado=' +
            encodeURIComponent(idEmpleadoFamiliarActual)
        );

        tabla.innerHTML = '';

        if (!json.data || json.data.length === 0) {
            tabla.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        No hay familiares registrados.
                    </td>
                </tr>
            `;
            return;
        }

        json.data.forEach(f => {

            tabla.innerHTML += `
                <tr>
                    <td>${escaparHTML(f.apellido)}, ${escaparHTML(f.nombre)}</td>
                    <td>${escaparHTML(f.parentesco)}</td>
                    <td>${escaparHTML(f.documento || '-')}</td>
                    <td>${escaparHTML(f.fecha_nacimiento || '-')}</td>
                    <td class="text-center">
                        ${Number(f.convive) === 1 ? 'Sí' : 'No'}
                    </td>
                    <td class="text-center">
                        ${Number(f.contacto_emergencia) === 1 ? 'Sí' : 'No'}
                    </td>
                    <td class="text-center text-nowrap">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary me-1"
                                onclick="editarFamiliar(${Number(f.idfamiliar)})"
                                title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>

                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                onclick="eliminarFamiliar(${Number(f.idfamiliar)})"
                                title="Dar de baja">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');
    }
}

// ============================================================
// NUEVO FAMILIAR
// ============================================================

function nuevoFamiliar() {

    document.getElementById('formFamiliar').reset();

    document.getElementById('idfamiliar').value = '';
    document.getElementById('idempleadoFamiliar').value =
        idEmpleadoFamiliarActual;

    document.getElementById('tituloModalFamiliar').textContent =
        'Nuevo familiar';

    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('ModalFormFamiliar')
    ).show();
}

// ============================================================
// EDITAR FAMILIAR
// ============================================================

async function editarFamiliar(id) {

    try {

        const json = await peticionFamiliar(
            API_FAMILIAR +
            '?action=obtener&idfamiliar=' +
            encodeURIComponent(id)
        );

        const f = json.data;

        document.getElementById('idfamiliar').value = f.idfamiliar;
        document.getElementById('idempleadoFamiliar').value =
            idEmpleadoFamiliarActual;

        document.getElementById('familiar_nombre').value = f.nombre;
        document.getElementById('familiar_apellido').value = f.apellido;
        document.getElementById('familiar_parentesco').value = f.parentesco;
        document.getElementById('familiar_documento').value = f.documento || '';
        document.getElementById('familiar_fecha_nacimiento').value =
            f.fecha_nacimiento || '';
        document.getElementById('familiar_genero').value = f.genero || '';
        document.getElementById('familiar_telefono').value = f.telefono || '';
        document.getElementById('familiar_convive').checked =
            Number(f.convive) === 1;
        document.getElementById('familiar_contacto_emergencia').checked =
            Number(f.contacto_emergencia) === 1;
        document.getElementById('familiar_observaciones').value =
            f.observaciones || '';

        document.getElementById('tituloModalFamiliar').textContent =
            'Editar familiar';

        bootstrap.Modal.getOrCreateInstance(
            document.getElementById('ModalFormFamiliar')
        ).show();

    } catch (error) {

        toast(error.message, 'error');
    }
}

// ============================================================
// GUARDAR FAMILIAR
// ============================================================

async function guardarFamiliar(e) {

    e.preventDefault();

    const form = document.getElementById('formFamiliar');
    const formData = new FormData(form);

    formData.set('idempleado', idEmpleadoFamiliarActual);

    const id = document.getElementById('idfamiliar').value;
    const action = id ? 'editar' : 'crear';

    if (id) {
        formData.set('idfamiliar', id);
    }

    const btn = document.getElementById('btnGuardarFamiliar');
    btn.disabled = true;

    try {

        const json = await peticionFamiliar(
            API_FAMILIAR + '?action=' + action,
            {
                method: 'POST',
                body: formData
            }
        );

        bootstrap.Modal.getInstance(
            document.getElementById('ModalFormFamiliar')
        )?.hide();

        toast(json.msg, 'success');

        await cargarFamiliares();

    } catch (error) {

        toast(error.message, 'error');

    } finally {

        btn.disabled = false;
    }
}

// ============================================================
// ELIMINAR FAMILIAR
// ============================================================

async function eliminarFamiliar(id) {

    const confirmacion = await Swal.fire({
        title: '¿Dar de baja al familiar?',
        text: 'El registro dejará de aparecer en el listado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    const formData = new FormData();
    formData.append('idfamiliar', id);

    try {

        const json = await peticionFamiliar(
            API_FAMILIAR + '?action=eliminar',
            {
                method: 'POST',
                body: formData
            }
        );

        toast(json.msg, 'success');

        await cargarFamiliares();

    } catch (error) {

        toast(error.message, 'error');
    }
}
