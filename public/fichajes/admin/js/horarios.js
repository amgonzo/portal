let tablaHorarios = null;
let modalHorario = null;

let tramos = [];
let descansos = [];


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', function () {

    const modalElement = document.getElementById('ModalHorario');

    if (modalElement) {
        modalHorario = new bootstrap.Modal(modalElement);
    }

    inicializarTablaHorarios();

    cargarHorarios();

    const form = document.getElementById('formHorario');

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            guardarHorario();
        });
    }

});


// =========================================================
// DATATABLE
// =========================================================

function inicializarTablaHorarios() {

    if (!window.jQuery || !$.fn.DataTable) {
        return;
    }

    tablaHorarios = $('#tablaHorarios').DataTable({
        destroy: true,
        responsive: true,
        pageLength: 10,
        order: [[0, 'asc']],
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
        },
        columnDefs: [
            {
                targets: [6],
                orderable: false,
                searchable: false,
                className: 'text-center'
            }
        ]
    });

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

        renderizarHorarios(data.data || []);

    } catch (error) {

        console.error('Error al cargar horarios:', error);

        toast(
            error.message || 'Error al cargar los horarios.',
            'error'
        );

    }

}


// =========================================================
// RENDER TABLA
// =========================================================

function renderizarHorarios(lista) {

    if (tablaHorarios) {
        tablaHorarios.clear();
    }

    const filas = [];

    lista.forEach(function (horario) {

        const estadoActivo =
            Number(horario.activo) === 1;

        const cantidadTramos =
            Number(horario.cantidad_tramos || 0);

        const cantidadDescansos =
            Number(horario.cantidad_descansos || 0);

        const minutos =
            Number(horario.minutos_trabajo || 0);

        let acciones = '';

        acciones += `
            <div class="d-flex justify-content-center gap-1">
        `;

        acciones += `
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                title="Editar horario"
                data-permiso="horarios_gestionar"
                onclick="editarHorario(${Number(horario.idhorario)})">

                <i class="fas fa-edit"></i>

            </button>
        `;

        if (estadoActivo) {

            acciones += `
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Desactivar horario"
                    data-permiso="horarios_gestionar"
                    onclick="cambiarEstadoHorario(
                        ${Number(horario.idhorario)},
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
                    title="Activar horario"
                    data-permiso="horarios_gestionar"
                    onclick="cambiarEstadoHorario(
                        ${Number(horario.idhorario)},
                        1
                    )">

                    <i class="fas fa-toggle-on"></i>

                </button>
            `;

        }

        acciones += '</div>';

        filas.push([
            escapeHtml(horario.nombre || ''),
            `${Number(horario.tolerancia_minutos || 0)} min`,
            `${minutos} min`,
            cantidadTramos,
            cantidadDescansos,
            estadoActivo
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>',
            acciones
        ]);

    });

    if (tablaHorarios) {

        tablaHorarios.rows.add(filas).draw();

    } else {

        const tbody =
            document.getElementById('listaHorarios');

        if (!tbody) return;

        tbody.innerHTML = '';

        filas.forEach(function (fila) {

            const tr = document.createElement('tr');

            fila.forEach(function (valor) {

                const td = document.createElement('td');

                td.innerHTML = valor;

                tr.appendChild(td);

            });

            tbody.appendChild(tr);

        });

    }

}


// =========================================================
// NUEVO
// =========================================================

function nuevoHorario() {

    const form =
        document.getElementById('formHorario');

    if (!form) return;

    form.reset();

    document.getElementById('edit_horario_id').value = '';

    document.getElementById('nombre').value = '';

    document.getElementById('tolerancia_minutos').value = 0;

    document.getElementById('minutos_trabajo').value = 0;

    tramos = [];

    descansos = [];

    renderizarTramos();

    renderizarDescansos();

    document.getElementById('ModalHorarioLabel').textContent =
        'Nuevo Horario';

    if (modalHorario) {
        modalHorario.show();
    }

}


// =========================================================
// EDITAR
// =========================================================

async function editarHorario(idhorario) {

    try {

        const respuesta = await fetch(
            API_BASE +
            '/fichajes/horarios/horarios.php?idhorario=' +
            encodeURIComponent(idhorario),
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
                'No se pudo obtener el horario.'
            );

        }

        const horario = data.data;

        document.getElementById('edit_horario_id').value =
            horario.idhorario || '';

        document.getElementById('nombre').value =
            horario.nombre || '';

        document.getElementById('tolerancia_minutos').value =
            Number(horario.tolerancia_minutos || 0);

        tramos =
            Array.isArray(horario.tramos)
                ? horario.tramos.map(function (tramo, indice) {

                    return {
                        idhorario_tramo:
                            tramo.idhorario_tramo || null,

                        orden:
                            Number(tramo.orden || indice + 1),

                        hora_desde:
                            normalizarHora(tramo.hora_desde),

                        hora_hasta:
                            normalizarHora(tramo.hora_hasta),

                        minutos_teoricos:
                            Number(
                                tramo.minutos_teoricos || 0
                            )
                    };

                })
                : [];

        descansos =
            Array.isArray(horario.descansos)
                ? horario.descansos.map(function (descanso, indice) {

                    return {
                        idhorario_descanso:
                            descanso.idhorario_descanso || null,

                        orden:
                            Number(
                                descanso.orden || indice + 1
                            ),

                        minutos_permitidos:
                            Number(
                                descanso.minutos_permitidos || 0
                            )
                    };

                })
                : [];

        ordenarTramos();

        ordenarDescansos();

        renderizarTramos();

        renderizarDescansos();

        calcularMinutosTrabajo();

        document.getElementById('ModalHorarioLabel').textContent =
            'Editar Horario';

        if (modalHorario) {
            modalHorario.show();
        }

    } catch (error) {

        console.error('Error al editar horario:', error);

        toast(
            error.message || 'Error al obtener el horario.',
            'error'
        );

    }

}


// =========================================================
// TRAMOS
// =========================================================

function agregarTramo() {

    tramos.push({
        idhorario_tramo: null,
        orden: tramos.length + 1,
        hora_desde: '',
        hora_hasta: '',
        minutos_teoricos: 0
    });

    renderizarTramos();

}


function renderizarTramos() {

    const tbody =
        document.getElementById('listaTramos');

    const mensaje =
        document.getElementById('mensajeSinTramos');

    if (!tbody) return;

    tbody.innerHTML = '';

    if (tramos.length === 0) {

        if (mensaje) {
            mensaje.style.display = 'block';
        }

        calcularMinutosTrabajo();

        return;

    }

    if (mensaje) {
        mensaje.style.display = 'none';
    }

    ordenarTramos();

    tramos.forEach(function (tramo, indice) {

        const minutos =
            calcularMinutosEntreHoras(
                tramo.hora_desde,
                tramo.hora_hasta
            );

        tramo.minutos_teoricos = minutos;

        const tr = document.createElement('tr');

        tr.innerHTML = `

            <td class="text-center fw-semibold">
                ${indice + 1}
            </td>

            <td>
                <input
                    type="text"
                    class="form-control form-control-sm hora-tramo"
                    value="${escapeAttribute(
                        normalizarHora(tramo.hora_desde)
                    )}"
                    placeholder="HH:mm"
                    maxlength="5"
                    inputmode="numeric"
                    autocomplete="off"
                    data-indice="${indice}"
                    data-campo="desde">
            </td>

            <td>
                <input
                    type="text"
                    class="form-control form-control-sm hora-tramo"
                    value="${escapeAttribute(
                        normalizarHora(tramo.hora_hasta)
                    )}"
                    placeholder="HH:mm"
                    maxlength="5"
                    inputmode="numeric"
                    autocomplete="off"
                    data-indice="${indice}"
                    data-campo="hasta">
            </td>

            <td>
                <input
                    type="text"
                    class="form-control form-control-sm text-center"
                    value="${minutos}"
                    readonly>
            </td>

            <td class="text-center">

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Eliminar tramo"
                    onclick="eliminarTramo(${indice})">

                    <i class="fas fa-trash"></i>

                </button>

            </td>

        `;

        tbody.appendChild(tr);

    });

    tbody
        .querySelectorAll('.hora-tramo')
        .forEach(function (input) {

            input.addEventListener(
                'input',
                function () {

                    const indice =
                        Number(this.dataset.indice);

                    const campo =
                        this.dataset.campo;

                    let valor =
                        this.value.replace(
                            /[^0-9:]/g,
                            ''
                        );

                    if (
                        valor.length === 2 &&
                        !valor.includes(':')
                    ) {
                        valor += ':';
                    }

                    if (valor.length > 5) {
                        valor = valor.substring(0, 5);
                    }

                    this.value = valor;

                    if (tramos[indice]) {

                        if (campo === 'desde') {
                            tramos[indice].hora_desde = valor;
                        } else {
                            tramos[indice].hora_hasta = valor;
                        }

                    }

                    actualizarFilaTramo(indice);

                    calcularMinutosTrabajo();

                }
            );

            input.addEventListener(
                'blur',
                function () {

                    const indice =
                        Number(this.dataset.indice);

                    const campo =
                        this.dataset.campo;

                    const hora =
                        normalizarHora(this.value);

                    this.value = hora;

                    if (tramos[indice]) {

                        if (campo === 'desde') {
                            tramos[indice].hora_desde = hora;
                        } else {
                            tramos[indice].hora_hasta = hora;
                        }

                    }

                    actualizarFilaTramo(indice);

                    calcularMinutosTrabajo();

                }
            );

        });

    calcularMinutosTrabajo();

}


function actualizarFilaTramo(indice) {

    const tbody =
        document.getElementById('listaTramos');

    if (!tbody) return;

    const fila =
        tbody.children[indice];

    if (!fila || !tramos[indice]) return;

    const minutos =
        calcularMinutosEntreHoras(
            tramos[indice].hora_desde,
            tramos[indice].hora_hasta
        );

    tramos[indice].minutos_teoricos = minutos;

    const campoMinutos =
        fila.querySelectorAll('input')[2];

    if (campoMinutos) {
        campoMinutos.value = minutos;
    }

}


function eliminarTramo(indice) {

    tramos.splice(indice, 1);

    ordenarTramos();

    renderizarTramos();

}


function ordenarTramos() {

    tramos.sort(function (a, b) {

        const horaA =
            minutosDesdeMedianoche(a.hora_desde);

        const horaB =
            minutosDesdeMedianoche(b.hora_desde);

        if (horaA === null && horaB === null) {
            return 0;
        }

        if (horaA === null) {
            return 1;
        }

        if (horaB === null) {
            return -1;
        }

        return horaA - horaB;

    });

    tramos.forEach(function (tramo, indice) {
        tramo.orden = indice + 1;
    });

}


// =========================================================
// DESCANSOS
// =========================================================

function agregarDescanso() {

    descansos.push({
        idhorario_descanso: null,
        orden: descansos.length + 1,
        minutos_permitidos: 0
    });

    renderizarDescansos();

}


function renderizarDescansos() {

    const tbody =
        document.getElementById('listaDescansos');

    const mensaje =
        document.getElementById('mensajeSinDescansos');

    if (!tbody) return;

    tbody.innerHTML = '';

    if (descansos.length === 0) {

        if (mensaje) {
            mensaje.style.display = 'block';
        }

        return;

    }

    if (mensaje) {
        mensaje.style.display = 'none';
    }

    ordenarDescansos();

    descansos.forEach(function (descanso, indice) {

        const tr = document.createElement('tr');

        tr.innerHTML = `

            <td class="text-center fw-semibold">
                ${indice + 1}
            </td>

            <td>

                <div class="input-group input-group-sm">

                    <input
                        type="number"
                        class="form-control minutos-descanso"
                        min="0"
                        step="1"
                        value="${Number(
                            descanso.minutos_permitidos || 0
                        )}"
                        data-indice="${indice}">

                    <span class="input-group-text">
                        minutos
                    </span>

                </div>

            </td>

            <td class="text-center">

                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Eliminar descanso"
                    onclick="eliminarDescanso(${indice})">

                    <i class="fas fa-trash"></i>

                </button>

            </td>

        `;

        tbody.appendChild(tr);

    });

    tbody
        .querySelectorAll('.minutos-descanso')
        .forEach(function (input) {

            input.addEventListener(
                'input',
                function () {

                    const indice =
                        Number(this.dataset.indice);

                    if (descansos[indice]) {

                        descansos[indice]
                            .minutos_permitidos =
                            Math.max(
                                0,
                                Number(this.value || 0)
                            );

                    }

                }
            );

        });

}


function eliminarDescanso(indice) {

    descansos.splice(indice, 1);

    ordenarDescansos();

    renderizarDescansos();

}


function ordenarDescansos() {

    descansos.forEach(function (descanso, indice) {

        descanso.orden = indice + 1;

    });

}


// =========================================================
// CÁLCULO MINUTOS TRABAJADOS
// =========================================================

function calcularMinutosTrabajo() {

    let total = 0;

    tramos.forEach(function (tramo) {

        const minutos =
            calcularMinutosEntreHoras(
                tramo.hora_desde,
                tramo.hora_hasta
            );

        tramo.minutos_teoricos = minutos;

        total += minutos;

    });

    const campo =
        document.getElementById('minutos_trabajo');

    if (campo) {
        campo.value = total;
    }

    return total;

}


// =========================================================
// HORAS
// =========================================================

function normalizarHora(valor) {

    if (!valor) {
        return '';
    }

    let hora =
        String(valor)
            .trim()
            .replace(/[^\d:]/g, '');

    if (!hora) {
        return '';
    }

    /*
     * La base de datos devuelve TIME como HH:mm:ss.
     * Para la interfaz trabajamos siempre con HH:mm.
     */
    if (/^\d{2}:\d{2}:\d{2}$/.test(hora)) {
        hora = hora.substring(0, 5);
    }

    /*
     * Permitir también HHmm
     */
    if (/^\d{4}$/.test(hora)) {
        hora =
            hora.substring(0, 2) +
            ':' +
            hora.substring(2, 4);
    }

    /*
     * Normalizar H:M o HH:M
     */
    if (/^\d{1,2}:\d{1,2}$/.test(hora)) {

        const partes =
            hora.split(':');

        const hh =
            partes[0].padStart(2, '0');

        const mm =
            partes[1].padStart(2, '0');

        hora = hh + ':' + mm;

    }

    if (!/^\d{2}:\d{2}$/.test(hora)) {
        return '';
    }

    const partes =
        hora.split(':');

    const horas =
        Number(partes[0]);

    const minutos =
        Number(partes[1]);

    if (
        horas < 0 ||
        horas > 23 ||
        minutos < 0 ||
        minutos > 59
    ) {
        return '';
    }

    return (
        String(horas).padStart(2, '0') +
        ':' +
        String(minutos).padStart(2, '0')
    );

}



function minutosDesdeMedianoche(hora) {

    const normalizada =
        normalizarHora(hora);

    if (!normalizada) {
        return null;
    }

    const partes =
        normalizada.split(':');

    return (
        Number(partes[0]) * 60 +
        Number(partes[1])
    );

}


function calcularMinutosEntreHoras(desde, hasta) {

    const inicio =
        minutosDesdeMedianoche(desde);

    const fin =
        minutosDesdeMedianoche(hasta);

    if (inicio === null || fin === null) {
        return 0;
    }

    let diferencia = fin - inicio;

    /*
     * Si el horario cruza medianoche,
     * por ejemplo 22:00 -> 06:00,
     * se considera que termina al día siguiente.
     */
    if (diferencia < 0) {
        diferencia += 24 * 60;
    }

    return diferencia;

}


// =========================================================
// GUARDAR
// =========================================================

async function guardarHorario() {

    const idhorario =
        document.getElementById('edit_horario_id').value;

    const nombre =
        document.getElementById('nombre').value.trim();

    const tolerancia =
        Number(
            document.getElementById(
                'tolerancia_minutos'
            ).value || 0
        );

    if (!nombre) {

        toast(
            'Debe ingresar el nombre del horario.',
            'warning'
        );

        return;

    }

    if (tramos.length === 0) {

        toast(
            'Debe agregar al menos un tramo de trabajo.',
            'warning'
        );

        return;

    }

    const tramosEnviar = [];

    for (let i = 0; i < tramos.length; i++) {

        const tramo = tramos[i];

        const desde =
            normalizarHora(tramo.hora_desde);

        const hasta =
            normalizarHora(tramo.hora_hasta);

        if (!desde || !hasta) {

            toast(
                `El tramo ${i + 1} tiene una hora inválida. Use HH:mm.`,
                'warning'
            );

            return;

        }

        const minutos =
            calcularMinutosEntreHoras(
                desde,
                hasta
            );

        if (minutos <= 0) {

            toast(
                `El tramo ${i + 1} debe tener una duración mayor a cero.`,
                'warning'
            );

            return;

        }

        tramosEnviar.push({

            idhorario_tramo:
                tramo.idhorario_tramo || null,

            orden:
                i + 1,

            hora_desde:
                desde,

            hora_hasta:
                hasta,

            minutos_teoricos:
                minutos

        });

    }

    const descansosEnviar =
        descansos.map(function (descanso, indice) {

            return {

                idhorario_descanso:
                    descanso.idhorario_descanso || null,

                orden:
                    indice + 1,

                minutos_permitidos:
                    Math.max(
                        0,
                        Number(
                            descanso.minutos_permitidos || 0
                        )
                    )

            };

        });

    const datos = {

        accion: 'guardar',

        idhorario:
            idhorario
                ? Number(idhorario)
                : null,

        nombre:
            nombre,

        tolerancia_minutos:
            Math.max(0, tolerancia),

        tramos:
            tramosEnviar,

        descansos:
            descansosEnviar

    };

    const boton =
        document.getElementById(
            'btnGuardarHorario'
        );

    try {

        if (boton) {
            boton.disabled = true;
        }

        const respuesta = await fetch(
            API_BASE + '/fichajes/horarios/horarios.php',
            {
                method: 'POST',
                cache: 'no-store',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            }
        );

        const data =
            await respuesta.json();

        if (!respuesta.ok || data.status !== 'ok') {

            throw new Error(
                data.message ||
                data.mensaje ||
                'No se pudo guardar el horario.'
            );

        }

        if (modalHorario) {
            modalHorario.hide();
        }

        toast(
            data.message ||
            data.mensaje ||
            'Horario guardado correctamente.',
            'success'
        );

        cargarHorarios();

    } catch (error) {

        console.error(
            'Error al guardar horario:',
            error
        );

        toast(
            error.message ||
            'Error al guardar el horario.',
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

async function cambiarEstadoHorario(
    idhorario,
    nuevoEstado
) {

    const activo =
        Number(nuevoEstado) === 1;

    const resultado =
        await Swal.fire({

            title:
                activo
                    ? '¿Activar horario?'
                    : '¿Desactivar horario?',

            text:
                activo
                    ? 'El horario volverá a estar disponible.'
                    : 'El horario dejará de estar disponible.',

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
            API_BASE + '/fichajes/horarios/horarios.php',
            {
                method: 'POST',
                cache: 'no-store',
                headers: {
                    ...obtenerHeadersSSO(),
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({

                    accion:
                        'cambiar_estado',

                    idhorario:
                        Number(idhorario),

                    activo:
                        activo ? 1 : 0

                })
            }
        );

        const data =
            await respuesta.json();

        if (!respuesta.ok || data.status !== 'ok') {

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

        cargarHorarios();

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


function escapeAttribute(valor) {

    return escapeHtml(valor);

}