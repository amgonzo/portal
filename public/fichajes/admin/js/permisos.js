// ============================================================
// PERMISOS DE SALIDA
// ============================================================

let tablaPermisos = null;
let modalPermiso = null;

let empleados = [];


// ============================================================
// INICIALIZACIÓN
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    document
        .getElementById('permiso_hora_desde')
        .addEventListener('blur', function () {

            const valor = this.value.trim();

            if (valor === '') {
                return;
            }

            const hora = normalizarHora(valor);

            if (hora !== null) {
                this.value = hora;
            }

        });


    document
        .getElementById('permiso_hora_hasta')
        .addEventListener('blur', function () {

            const valor = this.value.trim();

            if (valor === '') {
                return;
            }

            const hora = normalizarHora(valor);

            if (hora !== null) {
                this.value = hora;
            }

        });

    modalPermiso = new bootstrap.Modal(
        document.getElementById('ModalPermiso')
    );

    inicializarTabla();

    cargarDatosIniciales();

    document
        .getElementById('formPermiso')
        .addEventListener('submit', guardarPermiso);

});


// ============================================================
// TABLA
// ============================================================

function inicializarTabla() {

    tablaPermisos = $('#tablaPermisos').DataTable({

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
            { data: 'motivo' },
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
                targets: 7,

                render: function (data, type, row) {

                    let botones = '';

                    botones += `
                        <button
                            type="button"
                            class="btn btn-sm btn-primary me-1"
                            title="Editar"
                            data-permiso="permisos_gestionar"
                            onclick="editarPermiso(${row.idpermiso})">

                            <i class="fas fa-edit"></i>

                        </button>
                    `;

                    if (Number(row.activo) === 1) {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-danger"
                                title="Desactivar"
                                data-permiso="permisos_gestionar"
                                onclick="cambiarEstadoPermiso(${row.idpermiso}, 0)">

                                <i class="fas fa-times"></i>

                            </button>
                        `;

                    } else {

                        botones += `
                            <button
                                type="button"
                                class="btn btn-sm btn-success"
                                title="Activar"
                                data-permiso="permisos_gestionar"
                                onclick="cambiarEstadoPermiso(${row.idpermiso}, 1)">

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
            API_BASE +
            '/fichajes/permisos/permisos.php?accion=datos_iniciales',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar los empleados.',
                'error'
            );

            return;
        }

        empleados = res.data.empleados || [];

        cargarSelectEmpleados();

        cargarPermisos();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar los datos de permisos.',
            'error'
        );
    }

}


// ============================================================
// SELECT EMPLEADOS
// ============================================================

function cargarSelectEmpleados() {

    const select =
        document.getElementById('permiso_idempleado');

    select.innerHTML = `
        <option value="">
            Seleccione un empleado
        </option>
    `;

    empleados.forEach(function (empleado) {

        const option =
            document.createElement('option');

        option.value =
            empleado.idempleado;

        option.textContent =
            `${empleado.apellido}, ${empleado.nombre} - ${empleado.documento}`;

        select.appendChild(option);

    });

}


// ============================================================
// CARGAR PERMISOS
// ============================================================

async function cargarPermisos() {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/permisos/permisos.php?accion=listar',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudieron cargar los permisos.',
                'error'
            );

            return;
        }

        tablaPermisos.clear();

        tablaPermisos.rows.add(
            res.data || []
        );

        tablaPermisos.draw();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cargar los permisos.',
            'error'
        );
    }

}


// ============================================================
// NUEVO
// ============================================================

function nuevoPermiso() {

    document.getElementById(
        'tituloModalPermiso'
    ).textContent = 'Nuevo permiso de salida';

    document.getElementById(
        'edit_permiso_id'
    ).value = '0';

    document.getElementById(
        'permiso_idempleado'
    ).value = '';

    document.getElementById(
        'permiso_fecha'
    ).value = '';

    document.getElementById(
        'permiso_hora_desde'
    ).value = '';

    document.getElementById(
        'permiso_hora_hasta'
    ).value = '';

    document.getElementById(
        'permiso_motivo'
    ).value = '';

    modalPermiso.show();

}


// ============================================================
// EDITAR
// ============================================================

async function editarPermiso(id) {

    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/permisos/permisos.php?accion=obtener&id=' +
            encodeURIComponent(id),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const res = await response.json();

        if (!response.ok || res.status !== 'ok') {

            toast(
                res.msg || 'No se pudo obtener el permiso.',
                'error'
            );

            return;
        }

        const permiso = res.data;

        document.getElementById(
            'tituloModalPermiso'
        ).textContent = 'Editar permiso de salida';

        document.getElementById(
            'edit_permiso_id'
        ).value = permiso.idpermiso;

        document.getElementById(
            'permiso_idempleado'
        ).value = permiso.idempleado;

        document.getElementById(
            'permiso_fecha'
        ).value = permiso.fecha || '';

        document.getElementById(
            'permiso_hora_desde'
        ).value = permiso.hora_desde
            ? permiso.hora_desde.substring(0, 5)
            : '';

        document.getElementById(
            'permiso_hora_hasta'
        ).value = permiso.hora_hasta
            ? permiso.hora_hasta.substring(0, 5)
            : '';

        document.getElementById(
            'permiso_motivo'
        ).value = permiso.motivo || '';

        modalPermiso.show();

    } catch (error) {

        console.error(error);

        toast(
            'Error al obtener el permiso.',
            'error'
        );
    }

}


// ============================================================
// NORMALIZAR HORA
// ============================================================

function normalizarHora(valor) {

    let hora = String(valor || '').trim();

    if (hora === '') {
        return null;
    }

    // ---------------------------------------------------------
    // Ya viene como HH:mm
    // ---------------------------------------------------------

    if (/^\d{1,2}:\d{1,2}$/.test(hora)) {

        const partes = hora.split(':');

        let h = Number(partes[0]);
        let m = Number(partes[1]);

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


    // ---------------------------------------------------------
    // Solo números
    //
    // 8     -> 08:00
    // 11    -> 11:00
    // 800   -> 08:00
    // 1100  -> 11:00
    // 1130  -> 11:30
    // ---------------------------------------------------------

    if (/^\d+$/.test(hora)) {

        if (hora.length === 1 || hora.length === 2) {

            const h = Number(hora);

            if (h >= 0 && h <= 23) {

                return String(h).padStart(2, '0') + ':00';

            }

            return null;
        }


        if (hora.length === 3) {

            const h = Number(hora.substring(0, 1));
            const m = Number(hora.substring(1));

            if (
                h >= 0 &&
                h <= 9 &&
                m >= 0 &&
                m <= 59
            ) {

                return String(h).padStart(2, '0') +
                    ':' +
                    String(m).padStart(2, '0');

            }

            return null;
        }


        if (hora.length === 4) {

            const h = Number(hora.substring(0, 2));
            const m = Number(hora.substring(2, 4));

            if (
                h >= 0 &&
                h <= 23 &&
                m >= 0 &&
                m <= 59
            ) {

                return String(h).padStart(2, '0') +
                    ':' +
                    String(m).padStart(2, '0');

            }

            return null;
        }

        return null;
    }


    return null;
}

// ============================================================
// GUARDAR
// ============================================================

async function guardarPermiso(event) {

    event.preventDefault();

    const horaDesde =
        normalizarHora(
            document.getElementById(
                'permiso_hora_desde'
            ).value
        );

    const horaHasta =
        normalizarHora(
            document.getElementById(
                'permiso_hora_hasta'
            ).value
        );


    /*
     * Si se informa una hora, deben informarse las dos.
     */

    const tieneDesde =
        horaDesde !== null;

    const tieneHasta =
        horaHasta !== null;

    if (tieneDesde !== tieneHasta) {

        toast(
            'Debe indicar las dos horas del permiso o dejar ambas vacías.',
            'warning'
        );

        return;
    }


    /*
     * Si hay intervalo, validar que no sea cero
     * ni esté invertido.
     */

    if (tieneDesde && tieneHasta) {

        if (horaDesde === horaHasta) {

            toast(
                'La hora desde y la hora hasta no pueden ser iguales.',
                'warning'
            );

            return;
        }

        if (horaHasta < horaDesde) {

            toast(
                'La hora hasta no puede ser anterior a la hora desde.',
                'warning'
            );

            return;
        }

    }


    const datos = {

        accion: 'guardar',

        idpermiso: Number(
            document.getElementById(
                'edit_permiso_id'
            ).value
        ),

        idempleado: Number(
            document.getElementById(
                'permiso_idempleado'
            ).value
        ),

        fecha:
            document.getElementById(
                'permiso_fecha'
            ).value,

        hora_desde: horaDesde,

        hora_hasta: horaHasta,

        motivo:
            document.getElementById(
                'permiso_motivo'
            ).value.trim()

    };


    try {

        const response = await fetch(
            API_BASE +
            '/fichajes/permisos/permisos.php',
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
                res.msg || 'No se pudo guardar el permiso.',
                'error'
            );

            return;
        }

        modalPermiso.hide();

        toast(
            res.msg || 'Permiso guardado correctamente.',
            'success'
        );

        cargarPermisos();

    } catch (error) {

        console.error(error);

        toast(
            'Error al guardar el permiso.',
            'error'
        );
    }

}


// ============================================================
// CAMBIAR ESTADO
// ============================================================

async function cambiarEstadoPermiso(id, estado) {

    const texto = estado === 1
        ? '¿Desea activar este permiso?'
        : '¿Desea desactivar este permiso?';


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
            API_BASE +
            '/fichajes/permisos/permisos.php',
            {
                method: 'POST',

                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify({

                    accion: 'cambiar_estado',

                    idpermiso: id,

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

        cargarPermisos();

    } catch (error) {

        console.error(error);

        toast(
            'Error al cambiar el estado.',
            'error'
        );
    }

}