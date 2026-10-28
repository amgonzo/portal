let tablaCiclos = null;
let modalCiclo = null;

let horariosDisponibles = [];
let detalleCiclo = [];


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', function () {

    const modalElement = document.getElementById('ModalCiclo');

    if (modalElement) {
        modalCiclo = new bootstrap.Modal(modalElement);
    }

    inicializarTablaCiclos();

    cargarHorarios();
    cargarCiclos();

    const form = document.getElementById('formCiclo');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            guardarCiclo();
        });
    }

    const cantidadSemanas =
        document.getElementById('cantidadSemanas');

    if (cantidadSemanas) {
        cantidadSemanas.addEventListener('change', function () {
            construirSemanas();
        });
    }

});


// =========================================================
// DATATABLE
// =========================================================

function inicializarTablaCiclos() {

    if (!window.jQuery || !$.fn.DataTable) {
        return;
    }

    tablaCiclos = $('#tablaCiclos').DataTable({
        destroy: true,
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columnDefs: [
            {
                targets: [3],
                orderable: false,
                searchable: false,
                className: 'text-center'
            }
        ]
    });

}


// =========================================================
// CARGAR CICLOS
// =========================================================

async function cargarCiclos() {

    try {

        const respuesta = await fetch(
            API_BASE + '/fichajes/ciclos/ciclos.php',
            {
                method: 'GET',
                cache: 'no-store',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await respuesta.json();

        if (!respuesta.ok || data.status !== 'ok') {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudieron cargar los ciclos.'
            );

        }

        renderizarCiclos(data.data || []);

    } catch (error) {

        console.error('Error al cargar ciclos:', error);

        toast(
            error.message || 'Error al cargar los ciclos.',
            'error'
        );

    }

}


// =========================================================
// RENDER TABLA
// =========================================================

function renderizarCiclos(lista) {

    if (tablaCiclos) {
        tablaCiclos.clear();
    }

    const filas = [];

    lista.forEach(function (ciclo) {

        const estadoActivo =
            Number(ciclo.activo) === 1;

        let acciones = '';

        acciones += `
            <div class="d-flex justify-content-center gap-1">
        `;

        acciones += `
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                title="Editar ciclo"
                data-permiso="ciclos_gestionar"
                onclick="editarCiclo(${Number(ciclo.idciclo)})">

                <i class="fas fa-edit"></i>

            </button>
        `;

        if (estadoActivo) {

            acciones += `
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Desactivar ciclo"
                    data-permiso="ciclos_gestionar"
                    onclick="cambiarEstadoCiclo(
                        ${Number(ciclo.idciclo)},
                        0
                    )">

                    <i class="fas fa-toggle-off"></i>

                </button>
            `;

        } else {

            acciones += `
                <button
                    type="button"
                    class="btn btn-sm btn-outline-success"
                    title="Activar ciclo"
                    data-permiso="ciclos_gestionar"
                    onclick="cambiarEstadoCiclo(
                        ${Number(ciclo.idciclo)},
                        1
                    )">

                    <i class="fas fa-toggle-on"></i>

                </button>
            `;

        }

        acciones += '</div>';

        filas.push([

            escapeHtml(ciclo.nombre || ''),

            `${Number(ciclo.cantidad_semanas || 0)}
             ${Number(ciclo.cantidad_semanas) === 1
                ? 'semana'
                : 'semanas'}`,

            estadoActivo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>',

            acciones

        ]);

    });


    if (tablaCiclos) {

        tablaCiclos.rows.add(filas).draw();

    } else {

        const tbody =
            document.getElementById('listaCiclos');

        if (!tbody) return;

        tbody.innerHTML = '';

        filas.forEach(function (fila) {

            const tr =
                document.createElement('tr');

            fila.forEach(function (valor) {

                const td =
                    document.createElement('td');

                td.innerHTML = valor;

                tr.appendChild(td);

            });

            tbody.appendChild(tr);

        });

    }

}


// =========================================================
// CARGAR HORARIOS
// =========================================================

async function cargarHorarios() {

    try {

        const respuesta = await fetch(
            API_BASE + '/fichajes/horarios/horarios.php',
            {
                method: 'GET',
                cache: 'no-store',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await respuesta.json();

        if (!respuesta.ok || data.status !== 'ok') {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudieron cargar los horarios.'
            );

        }

        horariosDisponibles =
            Array.isArray(data.data)
                ? data.data
                : [];

    } catch (error) {

        console.error(
            'Error al cargar horarios:',
            error
        );

        toast(
            error.message ||
            'Error al cargar los horarios.',
            'error'
        );

    }

}


// =========================================================
// NUEVO
// =========================================================

function nuevoCiclo() {

    const form =
        document.getElementById('formCiclo');

    if (!form) return;

    form.reset();

    document.getElementById('edit_ciclo_id').value = '';

    document.getElementById('nombreCiclo').value = '';

    document.getElementById('cantidadSemanas').value = 1;

    detalleCiclo = [];

    construirSemanas();

    document.getElementById('ModalCicloLabel').textContent =
        'Nuevo Ciclo';

    if (modalCiclo) {
        modalCiclo.show();
    }

}


// =========================================================
// EDITAR
// =========================================================

async function editarCiclo(idciclo) {

    try {

        const respuesta = await fetch(
            API_BASE +
            '/fichajes/ciclos/ciclos.php?idciclo=' +
            encodeURIComponent(idciclo),
            {
                method: 'GET',
                cache: 'no-store',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await respuesta.json();

        if (!respuesta.ok || data.status !== 'ok') {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudo obtener el ciclo.'
            );

        }

        const ciclo = data.data;

        document.getElementById('edit_ciclo_id').value =
            ciclo.idciclo || '';

        document.getElementById('nombreCiclo').value =
            ciclo.nombre || '';

        document.getElementById('cantidadSemanas').value =
            Number(ciclo.cantidad_semanas || 1);

        detalleCiclo =
            Array.isArray(ciclo.detalle)
                ? ciclo.detalle.map(function (detalle) {

                    return {

                        idciclo_detalle:
                            detalle.idciclo_detalle || null,

                        semana:
                            Number(detalle.semana || 1),

                        dia_semana:
                            Number(detalle.dia_semana || 1),

                        idhorario:
                            detalle.idhorario !== null &&
                            detalle.idhorario !== undefined
                                ? Number(detalle.idhorario)
                                : null

                    };

                })
                : [];

        construirSemanas();

        document.getElementById('ModalCicloLabel').textContent =
            'Editar Ciclo';

        if (modalCiclo) {
            modalCiclo.show();
        }

    } catch (error) {

        console.error(
            'Error al editar ciclo:',
            error
        );

        toast(
            error.message ||
            'Error al obtener el ciclo.',
            'error'
        );

    }

}


// =========================================================
// CONSTRUIR SEMANAS
// =========================================================

function construirSemanas() {

    const contenedor =
        document.getElementById('contenedorSemanas');

    const mensaje =
        document.getElementById('mensajeSinSemanas');

    const campoCantidad =
        document.getElementById('cantidadSemanas');

    if (!contenedor || !campoCantidad) {
        return;
    }

    const cantidadSemanas =
        Number(campoCantidad.value || 0);

    contenedor.innerHTML = '';

    if (cantidadSemanas <= 0) {

        if (mensaje) {
            mensaje.style.display = 'block';
        }

        return;
    }

    if (mensaje) {
        mensaje.style.display = 'none';
    }


    for (
        let semana = 1;
        semana <= cantidadSemanas;
        semana++
    ) {

        const card =
            document.createElement('div');

        card.className =
            'card border mb-4';


        const encabezado =
            document.createElement('div');

        encabezado.className =
            'card-header d-flex justify-content-between align-items-center';


        encabezado.innerHTML = `

            <div>

                <h6 class="mb-0 fw-bold">

                    <i class="fas fa-calendar-week me-2"></i>

                    Semana ${semana}

                </h6>

                <small class="text-muted">
                    Defina el horario de cada día.
                </small>

            </div>

        `;


        const cuerpo =
            document.createElement('div');

        cuerpo.className =
            'card-body';


        const tablaResponsive =
            document.createElement('div');

        tablaResponsive.className =
            'table-responsive';


        const tabla =
            document.createElement('table');

        tabla.className =
            'table table-sm table-hover align-middle mb-0';


        tabla.innerHTML = `

            <thead>

                <tr>

                    <th style="width: 25%;">
                        Día
                    </th>

                    <th>
                        Horario
                    </th>

                </tr>

            </thead>

            <tbody></tbody>

        `;


        const tbody =
            tabla.querySelector('tbody');


        const dias = [

            {
                numero: 1,
                nombre: 'Lunes'
            },

            {
                numero: 2,
                nombre: 'Martes'
            },

            {
                numero: 3,
                nombre: 'Miércoles'
            },

            {
                numero: 4,
                nombre: 'Jueves'
            },

            {
                numero: 5,
                nombre: 'Viernes'
            },

            {
                numero: 6,
                nombre: 'Sábado'
            },

            {
                numero: 7,
                nombre: 'Domingo'
            }

        ];


        dias.forEach(function (dia) {

            const registro =
                obtenerDetalleDia(
                    semana,
                    dia.numero
                );

            const tr =
                document.createElement('tr');


            const tdDia =
                document.createElement('td');

            tdDia.className =
                'fw-semibold';

            tdDia.textContent =
                dia.nombre;


            const tdHorario =
                document.createElement('td');


            const select =
                document.createElement('select');

            select.className =
                'form-select form-select-sm horario-dia';


            select.dataset.semana =
                semana;

            select.dataset.dia =
                dia.numero;


            select.innerHTML =
                generarOpcionesHorarios(
                    registro
                        ? registro.idhorario
                        : null
                );


            select.addEventListener(
                'change',
                function () {

                    actualizarDetalleDia(
                        Number(this.dataset.semana),
                        Number(this.dataset.dia),
                        this.value
                    );

                }
            );


            tdHorario.appendChild(select);

            tr.appendChild(tdDia);

            tr.appendChild(tdHorario);

            tbody.appendChild(tr);

        });


        tablaResponsive.appendChild(tabla);

        cuerpo.appendChild(tablaResponsive);

        card.appendChild(encabezado);

        card.appendChild(cuerpo);

        contenedor.appendChild(card);

    }

}


// =========================================================
// OBTENER DETALLE DE UN DÍA
// =========================================================

function obtenerDetalleDia(
    semana,
    diaSemana
) {

    return detalleCiclo.find(function (detalle) {

        return Number(detalle.semana) ===
            Number(semana) &&

            Number(detalle.dia_semana) ===
            Number(diaSemana);

    }) || null;

}


// =========================================================
// ACTUALIZAR DETALLE
// =========================================================

function actualizarDetalleDia(
    semana,
    diaSemana,
    valor
) {

    const existente =
        obtenerDetalleDia(
            semana,
            diaSemana
        );


    const idhorario =
        valor === ''
            ? null
            : Number(valor);


    if (existente) {

        existente.idhorario =
            idhorario;

        return;

    }


    detalleCiclo.push({

        idciclo_detalle: null,

        semana: Number(semana),

        dia_semana: Number(diaSemana),

        idhorario: idhorario

    });

}


// =========================================================
// GENERAR OPCIONES DE HORARIOS
// =========================================================

function generarOpcionesHorarios(
    idSeleccionado
) {

    let html = `

        <option value="">
            -- Sin horario --
        </option>

    `;


    horariosDisponibles.forEach(function (horario) {

        const id =
            Number(horario.idhorario);

        const seleccionado =
            idSeleccionado !== null &&
            idSeleccionado !== undefined &&
            Number(idSeleccionado) === id
                ? 'selected'
                : '';


        const activo =
            Number(horario.activo) === 1;


        const textoEstado =
            activo
                ? ''
                : ' (Inactivo)';


        html += `

            <option
                value="${id}"
                ${seleccionado}>

                ${escapeHtml(
                    horario.nombre || ''
                )}${textoEstado}

            </option>

        `;

    });


    return html;

}


// =========================================================
// GUARDAR
// =========================================================

async function guardarCiclo() {

    const idciclo =
        document.getElementById(
            'edit_ciclo_id'
        ).value;


    const nombre =
        document.getElementById(
            'nombreCiclo'
        ).value.trim();


    const cantidadSemanas =
        Number(
            document.getElementById(
                'cantidadSemanas'
            ).value || 0
        );


    if (!nombre) {

        toast(
            'Debe ingresar el nombre del ciclo.',
            'warning'
        );

        return;

    }


    if (
        cantidadSemanas < 1 ||
        cantidadSemanas > 52
    ) {

        toast(
            'La cantidad de semanas debe estar entre 1 y 52.',
            'warning'
        );

        return;

    }


    const detalleEnviar = [];


    /*
     * Generamos siempre los 7 días de cada semana.
     * Si un día no tiene horario, se envía NULL.
     */

    for (
        let semana = 1;
        semana <= cantidadSemanas;
        semana++
    ) {

        for (
            let dia = 1;
            dia <= 7;
            dia++
        ) {

            const detalle =
                obtenerDetalleDia(
                    semana,
                    dia
                );


            detalleEnviar.push({

                semana: semana,

                dia_semana: dia,

                idhorario:
                    detalle &&
                    detalle.idhorario !== null &&
                    detalle.idhorario !== undefined
                        ? Number(detalle.idhorario)
                        : null

            });

        }

    }


    const datos = {

        accion: 'guardar',

        idciclo:
            idciclo
                ? Number(idciclo)
                : null,

        nombre: nombre,

        cantidad_semanas:
            cantidadSemanas,

        detalle:
            detalleEnviar

    };


    const boton =
        document.getElementById(
            'btnGuardarCiclo'
        );


    try {

        if (boton) {
            boton.disabled = true;
        }


        const respuesta = await fetch(

            API_BASE +
            '/fichajes/ciclos/ciclos.php',

            {

                method: 'POST',

                cache: 'no-store',

                headers: {

                    ...obtenerHeadersSSO(),

                    'Content-Type':
                        'application/json'

                },

                body:
                    JSON.stringify(datos)

            }

        );


        const data =
            await respuesta.json();


        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(

                data.message ||
                data.mensaje ||
                'No se pudo guardar el ciclo.'

            );

        }


        if (modalCiclo) {
            modalCiclo.hide();
        }


        toast(

            data.message ||
            data.mensaje ||
            'Ciclo guardado correctamente.',

            'success'

        );


        cargarCiclos();


    } catch (error) {

        console.error(
            'Error al guardar ciclo:',
            error
        );


        toast(

            error.message ||
            'Error al guardar el ciclo.',

            'error'

        );


    } finally {

        if (boton) {
            boton.disabled = false;
        }

    }

}


// =========================================================
// CAMBIAR ESTADO
// =========================================================

async function cambiarEstadoCiclo(
    idciclo,
    nuevoEstado
) {

    const activo =
        Number(nuevoEstado) === 1;


    const resultado =
        await Swal.fire({

            title:
                activo
                    ? '¿Activar ciclo?'
                    : '¿Desactivar ciclo?',

            text:
                activo
                    ? 'El ciclo volverá a estar disponible.'
                    : 'El ciclo dejará de estar disponible.',

            icon: 'question',

            showCancelButton: true,

            confirmButtonText:
                activo
                    ? 'Sí, activar'
                    : 'Sí, desactivar',

            cancelButtonText:
                'Cancelar'

        });


    if (!resultado.isConfirmed) {
        return;
    }


    try {

        const respuesta = await fetch(

            API_BASE +
            '/fichajes/ciclos/ciclos.php',

            {

                method: 'POST',

                cache: 'no-store',

                headers: {

                    ...obtenerHeadersSSO(),

                    'Content-Type':
                        'application/json'

                },

                body:
                    JSON.stringify({

                        accion:
                            'estado',

                        idciclo:
                            Number(idciclo),

                        activo:
                            activo
                                ? 1
                                : 0

                    })

            }

        );


        const data =
            await respuesta.json();


        if (
            !respuesta.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(

                data.message ||
                data.mensaje ||
                'No se pudo cambiar el estado.'

            );

        }


        toast(

            data.message ||
            data.mensaje ||
            'Estado actualizado correctamente.',

            'success'

        );


        cargarCiclos();


    } catch (error) {

        console.error(
            'Error al cambiar estado:',
            error
        );


        toast(

            error.message ||
            'Error al cambiar el estado.',

            'error'

        );

    }

}


// =========================================================
// UTILIDADES
// =========================================================

function escapeHtml(valor) {

    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

}