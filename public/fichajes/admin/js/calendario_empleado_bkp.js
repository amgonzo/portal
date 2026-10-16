let anioActual = new Date().getFullYear();

let idEmpleado = null;
let empleado = null;

let tiposAsistencia = [];

let calendarioMaestro = {};
let calendarioEmpleado = {};

let celdaSeleccionada = null;
let fechaSeleccionada = null;


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', async function () {

    const params = new URLSearchParams(
        window.location.search
    );

    idEmpleado = Number(
        params.get('idempleado')
    );

    if (!idEmpleado || idEmpleado <= 0) {

        toast(
            'No se indicó un empleado válido.',
            'error'
        );

        return;
    }


    document.getElementById(
        'calendario_anio'
    ).value = anioActual;


    document
        .getElementById('btnCargarCalendario')
        .addEventListener(
            'click',
            async function () {

                const anio = Number(
                    document.getElementById(
                        'calendario_anio'
                    ).value
                );

                if (
                    !anio ||
                    anio < 2000 ||
                    anio > 2100
                ) {

                    toast(
                        'Ingrese un año válido.',
                        'warning'
                    );

                    return;
                }

                anioActual = anio;

                await cargarCalendarioCompleto();

            }
        );


    document
        .getElementById('calendario_anio')
        .addEventListener(
            'keydown',
            async function (e) {

                if (e.key !== 'Enter') {
                    return;
                }

                document
                    .getElementById(
                        'btnCargarCalendario'
                    )
                    .click();

            }
        );


    document.addEventListener(
        'click',
        function (e) {

            const menu =
                document.getElementById(
                    'menuAsistencia'
                );

            if (
                menu &&
                menu.style.display === 'block' &&
                !menu.contains(e.target)
            ) {

                ocultarMenuAsistencia();

            }

        }
    );


    document.addEventListener(
        'keydown',
        function (e) {

            if (e.key === 'Escape') {

                ocultarMenuAsistencia();

            }

        }
    );


    await cargarDatosIniciales();

});


// =========================================================
// CARGA INICIAL
// =========================================================

async function cargarDatosIniciales() {

    try {

        await cargarEmpleado();

        // Primero todos los tipos activos.
        await cargarTiposAsistencia();

        construirLeyenda();

        // Primero maestro y después empleado.
        await cargarCalendarioCompleto();

    } catch (error) {

        console.error(error);

        toast(
            error.message ||
            'No se pudieron cargar los datos.',
            'error'
        );

    }

}


// =========================================================
// EMPLEADO
// =========================================================

async function cargarEmpleado() {

    const response = await fetch(

        API_BASE +
        '/fichajes/calendario_empleado/calendario_empleado.php' +
        '?accion=empleado&idempleado=' +
        encodeURIComponent(idEmpleado),

        {
            method: 'GET',
            headers: obtenerHeadersSSO()
        }

    );


    const data = await response.json();


    if (
        !response.ok ||
        data.status !== 'ok'
    ) {

        throw new Error(
            data.mensaje ||
            data.msg ||
            'No se pudo obtener el empleado.'
        );

    }


    empleado = data.empleado;


    let textoEmpleado =
        empleado.apellido +
        ', ' +
        empleado.nombre;


    if (empleado.documento) {

        textoEmpleado +=
            ' · Documento: ' +
            empleado.documento;

    }


    document.getElementById(
        'nombreEmpleado'
    ).innerHTML =
        '<strong>' +
        escapeHtml(textoEmpleado) +
        '</strong>';

}


// =========================================================
// TIPOS DE ASISTENCIA
// =========================================================

async function cargarTiposAsistencia() {

    const response = await fetch(

        API_BASE +
        '/fichajes/tipos_asistencia/tipos_asistencias.php',

        {
            method: 'GET',
            headers: obtenerHeadersSSO()
        }

    );


    const data = await response.json();


    if (
        !response.ok ||
        data.status !== 'ok'
    ) {

        throw new Error(
            data.mensaje ||
            data.msg ||
            'No se pudieron cargar los tipos de asistencia.'
        );

    }


    /*
     * IMPORTANTE:
     *
     * En el calendario del empleado usamos TODOS
     * los tipos activos.
     *
     * visible_maestro NO limita este menú.
     */

    tiposAsistencia =
        (data.data || data.tipos || [])
            .filter(function (tipo) {

                return Number(
                    tipo.activo
                ) === 1;

            })
            .sort(function (a, b) {

                return String(
                    a.nombre || ''
                ).localeCompare(
                    String(
                        b.nombre || ''
                    ),
                    'es'
                );

            });

}


// =========================================================
// CARGAR CALENDARIO COMPLETO
// =========================================================

async function cargarCalendarioCompleto() {

    try {

        /*
         * PRIMERO:
         * calendario maestro
         */

        await cargarCalendarioMaestro();


        /*
         * SEGUNDO:
         * calendario específico del empleado
         */

        await cargarCalendarioEmpleado();


        /*
         * Finalmente construimos la matriz.
         */

        construirCalendario();


    } catch (error) {

        console.error(error);

        toast(
            error.message ||
            'No se pudo cargar el calendario.',
            'error'
        );

    }

}


// =========================================================
// CALENDARIO MAESTRO
// =========================================================

async function cargarCalendarioMaestro() {

    const response = await fetch(

        API_BASE +
        '/fichajes/calendario_maestro/calendario_maestro.php?anio=' +
        encodeURIComponent(anioActual),

        {
            method: 'GET',
            headers: obtenerHeadersSSO()
        }

    );


    const data = await response.json();


    if (
        !response.ok ||
        data.status !== 'ok'
    ) {

        throw new Error(
            data.mensaje ||
            data.msg ||
            'No se pudo cargar el calendario maestro.'
        );

    }


    calendarioMaestro = {};


    (data.data || []).forEach(
        function (registro) {

            calendarioMaestro[
                registro.fecha
            ] = registro;

        }
    );

}


// =========================================================
// CALENDARIO DEL EMPLEADO
// =========================================================

async function cargarCalendarioEmpleado() {

    const response = await fetch(

        API_BASE +
        '/fichajes/calendario_empleado/calendario_empleado.php' +
        '?accion=calendario' +
        '&idempleado=' +
        encodeURIComponent(idEmpleado) +
        '&anio=' +
        encodeURIComponent(anioActual),

        {
            method: 'GET',
            headers: obtenerHeadersSSO()
        }

    );


    const data = await response.json();


    if (
        !response.ok ||
        data.status !== 'ok'
    ) {

        throw new Error(
            data.mensaje ||
            data.msg ||
            'No se pudo cargar el calendario del empleado.'
        );

    }


    calendarioEmpleado = {};


    (data.calendario || []).forEach(
        function (registro) {

            calendarioEmpleado[
                registro.fecha
            ] = registro;

        }
    );

}


// =========================================================
// OBTENER ASISTENCIA FINAL DEL DÍA
// =========================================================

function obtenerRegistroFecha(fecha) {

    /*
     * Si existe registro del empleado:
     *
     * - con tipo -> gana el empleado
     * - con tipo NULL -> vuelve al maestro
     */

    if (
        Object.prototype.hasOwnProperty.call(
            calendarioEmpleado,
            fecha
        )
    ) {

        const empleadoRegistro =
            calendarioEmpleado[fecha];


        if (
            empleadoRegistro &&
            empleadoRegistro.idtipo_asistencia
        ) {

            return empleadoRegistro;

        }

    }


    /*
     * Si no hay asistencia específica
     * del empleado, usamos maestro.
     */

    if (
        Object.prototype.hasOwnProperty.call(
            calendarioMaestro,
            fecha
        )
    ) {

        return calendarioMaestro[fecha];

    }


    return null;

}


// =========================================================
// CONSTRUIR CALENDARIO
// =========================================================

function construirCalendario() {

    const filaCabecera =
        document.getElementById(
            'filaCabeceraDias'
        );

    const cuerpo =
        document.getElementById(
            'cuerpoCalendario'
        );


    filaCabecera.innerHTML =
        '<th class="columna-mes">Mes</th>';

    cuerpo.innerHTML = '';


    const nombresDias = [
        'Dom',
        'Lun',
        'Mar',
        'Mié',
        'Jue',
        'Vie',
        'Sáb'
    ];


    // =====================================================
    // CABECERA
    // =====================================================

    for (
        let dia = 1;
        dia <= 31;
        dia++
    ) {

        const fecha =
            new Date(
                anioActual,
                0,
                dia
            );


        const th =
            document.createElement('th');


        th.className =
            'cabecera-dia';


        th.innerHTML =
            '<span class="numero">' +
            dia +
            '</span>' +
            '<span class="semana">' +
            nombresDias[
                fecha.getDay()
            ] +
            '</span>';


        filaCabecera.appendChild(th);

    }


    // =====================================================
    // MESES
    // =====================================================

    const meses = [
        'Enero',
        'Febrero',
        'Marzo',
        'Abril',
        'Mayo',
        'Junio',
        'Julio',
        'Agosto',
        'Septiembre',
        'Octubre',
        'Noviembre',
        'Diciembre'
    ];


    for (
        let mes = 0;
        mes < 12;
        mes++
    ) {

        const tr =
            document.createElement('tr');


        const tdMes =
            document.createElement('td');


        tdMes.className =
            'columna-mes';


        tdMes.textContent =
            meses[mes];


        tr.appendChild(tdMes);


        const cantidadDias =
            new Date(
                anioActual,
                mes + 1,
                0
            ).getDate();


        for (
            let dia = 1;
            dia <= 31;
            dia++
        ) {

            const td =
                document.createElement('td');


            td.className =
                'celda-dia';


            if (dia > cantidadDias) {

                td.classList.add(
                    'dia-inexistente'
                );

                tr.appendChild(td);

                continue;

            }


            const fecha =
                construirFecha(
                    anioActual,
                    mes + 1,
                    dia
                );


            td.dataset.fecha =
                fecha;


            const fechaJS =
                new Date(
                    anioActual,
                    mes,
                    dia
                );


            // Fin de semana

            if (
                fechaJS.getDay() === 0 ||
                fechaJS.getDay() === 6
            ) {

                td.classList.add(
                    'fin-de-semana'
                );

            }


            // Hoy

            const hoy =
                new Date();


            if (
                hoy.getFullYear() === anioActual &&
                hoy.getMonth() === mes &&
                hoy.getDate() === dia
            ) {

                td.classList.add(
                    'hoy'
                );

            }


            // Menú derecho

            td.addEventListener(
                'contextmenu',
                function (event) {

                    event.preventDefault();

                    celdaSeleccionada =
                        td;

                    fechaSeleccionada =
                        fecha;

                    mostrarMenuAsistencia(
                        event.clientX,
                        event.clientY
                    );

                }
            );


            tr.appendChild(td);

        }


        cuerpo.appendChild(tr);

    }


    aplicarDatosCalendario();

}


// =========================================================
// FECHA
// =========================================================

function construirFecha(
    anio,
    mes,
    dia
) {

    return (
        String(anio) +
        '-' +
        String(mes).padStart(2, '0') +
        '-' +
        String(dia).padStart(2, '0')
    );

}


// =========================================================
// APLICAR DATOS
// =========================================================

function aplicarDatosCalendario() {

    document
        .querySelectorAll(
            '.celda-dia[data-fecha]'
        )
        .forEach(
            function (celda) {

                const fecha =
                    celda.dataset.fecha;


                const registro =
                    obtenerRegistroFecha(
                        fecha
                    );


                if (!registro) {

                    limpiarCeldaVisual(
                        celda
                    );

                    return;

                }


                pintarCelda(
                    celda,
                    registro
                );

            }
        );

}


// =========================================================
// PINTAR CELDA
// =========================================================

function pintarCelda(
    celda,
    registro
) {

    celda.textContent = '';

    celda.style.backgroundColor = '';
    celda.style.color = '';


    if (
        !registro ||
        !registro.idtipo_asistencia
    ) {

        return;

    }


    const tipo =
        tiposAsistencia.find(
            function (item) {

                return Number(
                    item.idtipo_asistencia
                ) === Number(
                    registro.idtipo_asistencia
                );

            }
        );


    if (!tipo) {

        /*
         * También puede venir desde maestro.
         * En ese caso usamos directamente los
         * datos que trae el registro.
         */

        if (registro.abreviatura) {

            celda.textContent =
                registro.abreviatura;

            celda.style.backgroundColor =
                registro.color_fondo || '';

            celda.style.color =
                registro.color_texto || '';

        }

        return;

    }


    celda.textContent =
        tipo.abreviatura || '';


    celda.style.backgroundColor =
        tipo.color_fondo || '';


    celda.style.color =
        tipo.color_texto || '';


    celda.dataset.idtipoAsistencia =
        tipo.idtipo_asistencia;


    celda.dataset.nombreTipo =
        tipo.nombre;

}


// =========================================================
// LIMPIAR CELDA
// =========================================================

function limpiarCeldaVisual(celda) {

    celda.textContent = '';

    celda.style.backgroundColor = '';
    celda.style.color = '';

    delete celda.dataset.idtipoAsistencia;
    delete celda.dataset.nombreTipo;

}


// =========================================================
// MENÚ
// =========================================================

function mostrarMenuAsistencia(
    x,
    y
) {

    const menu =
        document.getElementById(
            'menuAsistencia'
        );


    const opciones =
        document.getElementById(
            'opcionesAsistencia'
        );


    opciones.innerHTML = '';


    /*
     * TODOS los tipos activos.
     * No filtramos visible_maestro.
     */

    tiposAsistencia.forEach(
        function (tipo) {

            const boton =
                document.createElement(
                    'button'
                );


            boton.type =
                'button';


            boton.className =
                'opcion-asistencia';


            boton.innerHTML =
                '<span class="opcion-color" ' +
                'style="' +
                'background-color:' +
                escapeAttribute(
                    tipo.color_fondo ||
                    '#ffffff'
                ) +
                ';color:' +
                escapeAttribute(
                    tipo.color_texto ||
                    '#000000'
                ) +
                ';">' +
                escapeHtml(
                    tipo.abreviatura || ''
                ) +
                '</span>' +
                '<span class="opcion-nombre">' +
                escapeHtml(
                    tipo.nombre
                ) +
                '</span>';


            boton.addEventListener(
                'click',
                async function () {

                    await aplicarTipoAsistencia(
                        tipo
                    );

                }
            );


            opciones.appendChild(
                boton
            );

        }
    );


    // =====================================================
    // SEPARADOR
    // =====================================================

    const separador =
        document.createElement(
            'div'
        );


    separador.className =
        'menu-separador';


    opciones.appendChild(
        separador
    );


    // =====================================================
    // QUITAR
    // =====================================================

    const botonQuitar =
        document.createElement(
            'button'
        );


    botonQuitar.type =
        'button';


    botonQuitar.className =
        'opcion-asistencia text-danger';


    botonQuitar.innerHTML =
        '<span class="opcion-color" ' +
        'style="' +
        'background-color:#ffffff;' +
        'color:#dc3545;' +
        'border:1px solid #dc3545;">' +
        '<i class="fas fa-times"></i>' +
        '</span>' +
        '<span class="opcion-nombre">' +
        'Quitar asistencia' +
        '</span>';


    botonQuitar.addEventListener(
        'click',
        async function () {

            await quitarAsistencia();

        }
    );


    opciones.appendChild(
        botonQuitar
    );


    // =====================================================
    // MOSTRAR
    // =====================================================

    menu.style.display =
        'block';


    const ancho =
        menu.offsetWidth;


    const alto =
        menu.offsetHeight;


    let posicionX =
        x;


    let posicionY =
        y;


    if (
        posicionX + ancho >
        window.innerWidth
    ) {

        posicionX =
            window.innerWidth -
            ancho -
            10;

    }


    if (
        posicionY + alto >
        window.innerHeight
    ) {

        posicionY =
            window.innerHeight -
            alto -
            10;

    }


    menu.style.left =
        Math.max(
            5,
            posicionX
        ) + 'px';


    menu.style.top =
        Math.max(
            5,
            posicionY
        ) + 'px';

}


// =========================================================
// APLICAR TIPO
// =========================================================

async function aplicarTipoAsistencia(
    tipo
) {

    if (!fechaSeleccionada) {
        return;
    }


    const fecha =
        fechaSeleccionada;


    ocultarMenuAsistencia();


    try {

        const response =
            await fetch(

                API_BASE +
                '/fichajes/calendario_empleado/calendario_empleado.php',

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
                            idEmpleado,

                        fecha:
                            fecha,

                        idtipo_asistencia:
                            Number(
                                tipo.idtipo_asistencia
                            )

                    })

                }

            );


        const data =
            await response.json();


        if (
            !response.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(
                data.mensaje ||
                data.msg ||
                'No se pudo guardar la asistencia.'
            );

        }


        /*
         * El registro propio del empleado
         * pasa a tener prioridad sobre maestro.
         */

        calendarioEmpleado[fecha] =
            data.calendario;


        const celda =
            document.querySelector(
                '.celda-dia[data-fecha="' +
                fecha +
                '"]'
            );


        if (celda) {

            pintarCelda(
                celda,
                data.calendario
            );

        }


        toast(
            'Asistencia guardada correctamente.',
            'success'
        );


    } catch (error) {

        console.error(error);

        toast(
            error.message ||
            'No se pudo guardar la asistencia.',
            'error'
        );

    }

}


// =========================================================
// QUITAR ASISTENCIA
// =========================================================

async function quitarAsistencia() {

    if (!fechaSeleccionada) {
        return;
    }


    const fecha =
        fechaSeleccionada;


    ocultarMenuAsistencia();


    const resultado =
        await Swal.fire({

            icon: 'warning',

            title: 'Quitar asistencia',

            text:
                '¿Está seguro de quitar la asistencia de este día?',

            showCancelButton: true,

            confirmButtonText:
                'Sí, quitar',

            cancelButtonText:
                'Cancelar',

            confirmButtonColor:
                '#dc3545'

        });


    if (!resultado.isConfirmed) {
        return;
    }


    try {

        const response =
            await fetch(

                API_BASE +
                '/fichajes/calendario_empleado/calendario_empleado.php',

                {
                    method: 'POST',

                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },

                    body: JSON.stringify({

                        accion:
                            'eliminar',

                        idempleado:
                            idEmpleado,

                        fecha:
                            fecha

                    })

                }

            );


        const data =
            await response.json();


        if (
            !response.ok ||
            data.status !== 'ok'
        ) {

            throw new Error(
                data.mensaje ||
                data.msg ||
                'No se pudo quitar la asistencia.'
            );

        }


        /*
         * Eliminamos la excepción del empleado.
         *
         * Si existe una asistencia maestro,
         * automáticamente vuelve a mostrarse.
         */

        delete calendarioEmpleado[
            fecha
        ];


        const celda =
            document.querySelector(
                '.celda-dia[data-fecha="' +
                fecha +
                '"]'
            );


        if (celda) {

            const registro =
                obtenerRegistroFecha(
                    fecha
                );


            if (registro) {

                pintarCelda(
                    celda,
                    registro
                );

            } else {

                limpiarCeldaVisual(
                    celda
                );

            }

        }


        toast(
            'Asistencia quitada correctamente.',
            'success'
        );


    } catch (error) {

        console.error(error);

        toast(
            error.message ||
            'No se pudo quitar la asistencia.',
            'error'
        );

    }

}


// =========================================================
// OCULTAR MENÚ
// =========================================================

function ocultarMenuAsistencia() {

    const menu =
        document.getElementById(
            'menuAsistencia'
        );


    if (menu) {

        menu.style.display =
            'none';

    }

}


// =========================================================
// LEYENDA
// =========================================================

function construirLeyenda() {

    const contenedor =
        document.getElementById(
            'leyendaAsistencias'
        );


    if (!contenedor) {
        return;
    }


    contenedor.innerHTML = '';


    tiposAsistencia.forEach(
        function (tipo) {

            const item =
                document.createElement(
                    'div'
                );


            item.className =
                'leyenda-item';


            const color =
                document.createElement(
                    'span'
                );


            color.className =
                'leyenda-color';


            color.style.backgroundColor =
                tipo.color_fondo ||
                '#ffffff';


            color.style.color =
                tipo.color_texto ||
                '#000000';


            color.textContent =
                tipo.abreviatura || '';


            const nombre =
                document.createElement(
                    'span'
                );


            nombre.textContent =
                tipo.nombre;


            item.appendChild(color);
            item.appendChild(nombre);


            contenedor.appendChild(
                item
            );

        }
    );

}


// =========================================================
// ESCAPE HTML
// =========================================================

function escapeHtml(valor) {

    if (
        valor === null ||
        valor === undefined
    ) {

        return '';

    }


    return String(valor)
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


// =========================================================
// ESCAPE ATRIBUTO
// =========================================================

function escapeAttribute(valor) {

    if (
        valor === null ||
        valor === undefined
    ) {

        return '';

    }


    return String(valor)
        .replace(
            /"/g,
            '&quot;'
        )
        .replace(
            /'/g,
            '&#039;'
        );

}