let anioActual = new Date().getFullYear();

let tiposAsistencia = [];

let calendarioMaestro = {};

let celdaSeleccionada = null;


// =========================================================
// INICIO
// =========================================================

document.addEventListener('DOMContentLoaded', async function () {

    document.getElementById('calendario_anio').value =
        anioActual;


    document
        .getElementById('btnCargarCalendario')
        .addEventListener('click', async function () {

            const anio = Number(
                document.getElementById('calendario_anio').value
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

            await cargarCalendario();

        });


    // Enter sobre el año
    document
        .getElementById('calendario_anio')
        .addEventListener('keydown', function (event) {

            if (event.key === 'Enter') {

                document
                    .getElementById('btnCargarCalendario')
                    .click();

            }

        });


    try {

        await cargarTiposAsistencia();

        construirLeyenda();

        await cargarCalendario();

    } catch (error) {

        console.error(error);

        toast(
            'No se pudo cargar el calendario maestro.',
            'error'
        );

    }

});


// =========================================================
// TIPOS DE ASISTENCIA
// =========================================================

async function cargarTiposAsistencia() {

    const respuesta = await fetch(
        API_BASE +
        '/fichajes/tipos_asistencia/tipos_asistencias.php',
        {
            method: 'GET',
            headers: obtenerHeadersSSO()
        }
    );


    if (!respuesta.ok) {

        throw new Error(
            'Error HTTP al consultar tipos de asistencia.'
        );

    }


    const res = await respuesta.json();


    if (res.status !== 'ok') {

        throw new Error(
            res.msg ||
            'No se pudieron obtener los tipos de asistencia.'
        );

    }


    tiposAsistencia = (res.data || [])
        .filter(function (tipo) {

            return (
                Number(tipo.activo) === 1 &&
                Number(tipo.visible_maestro) === 1
            );

        });


}


// =========================================================
// CARGAR CALENDARIO
// =========================================================

async function cargarCalendario() {

    try {

        const respuesta = await fetch(
            API_BASE +
            '/fichajes/calendario_maestro/calendario_maestro.php?anio=' +
            encodeURIComponent(anioActual),
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );


        if (!respuesta.ok) {

            throw new Error(
                'Error HTTP al consultar calendario.'
            );

        }


        const res = await respuesta.json();


        if (res.status !== 'ok') {

            throw new Error(
                res.msg ||
                'No se pudo consultar el calendario.'
            );

        }


        calendarioMaestro = {};


        (res.data || []).forEach(function (registro) {

            calendarioMaestro[
                registro.fecha
            ] = registro;

        });


        construirCalendario();

        aplicarDatosCalendario();


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
// CONSTRUIR CALENDARIO
// =========================================================

function construirCalendario() {

    const cabecera =
        document.getElementById(
            'filaCabeceraDias'
        );

    const cuerpo =
        document.getElementById(
            'cuerpoCalendario'
        );


    cabecera.innerHTML = `
        <th class="columna-mes">
            Mes
        </th>
    `;

    cuerpo.innerHTML = '';


    // ---------------------------------------------------------
    // CABECERA 1 - 31
    // ---------------------------------------------------------

    for (let dia = 1; dia <= 31; dia++) {

        const th =
            document.createElement('th');

        th.className =
            'cabecera-dia';

        th.innerHTML = `
            <span class="numero">
                ${dia}
            </span>
        `;

        cabecera.appendChild(th);

    }


    // ---------------------------------------------------------
    // MESES
    // ---------------------------------------------------------

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


        // -----------------------------------------------------
        // MES
        // -----------------------------------------------------

        const tdMes =
            document.createElement('td');

        tdMes.className =
            'columna-mes';

        tdMes.textContent =
            meses[mes];

        tr.appendChild(tdMes);


        // -----------------------------------------------------
        // CANTIDAD DE DÍAS
        // -----------------------------------------------------

        const cantidadDias =
            new Date(
                anioActual,
                mes + 1,
                0
            ).getDate();


        // -----------------------------------------------------
        // DÍAS
        // -----------------------------------------------------

        for (
            let dia = 1;
            dia <= 31;
            dia++
        ) {

            const td =
                document.createElement('td');

            td.className =
                'celda-dia';


            // -------------------------------------------------
            // DÍA INEXISTENTE
            // -------------------------------------------------

            if (dia > cantidadDias) {

                td.classList.add(
                    'dia-inexistente'
                );

                tr.appendChild(td);

                continue;

            }


            // -------------------------------------------------
            // FECHA
            // -------------------------------------------------

            const fecha =
                `${anioActual}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;


            td.dataset.fecha =
                fecha;

            td.dataset.mes =
                mes + 1;

            td.dataset.dia =
                dia;


            // -------------------------------------------------
            // FIN DE SEMANA
            // -------------------------------------------------

            const fechaObj =
                new Date(
                    anioActual,
                    mes,
                    dia
                );


            const diaSemana =
                fechaObj.getDay();


            if (
                diaSemana === 0 ||
                diaSemana === 6
            ) {

                td.classList.add(
                    'fin-de-semana'
                );

            }


            // -------------------------------------------------
            // HOY
            // -------------------------------------------------

            const hoy =
                new Date();


            if (
                fechaObj.getFullYear() === hoy.getFullYear() &&
                fechaObj.getMonth() === hoy.getMonth() &&
                fechaObj.getDate() === hoy.getDate()
            ) {

                td.classList.add(
                    'hoy'
                );

            }


            // -------------------------------------------------
            // CLICK DERECHO
            // -------------------------------------------------

            td.addEventListener(
                'contextmenu',
                function (event) {

                    event.preventDefault();

                    celdaSeleccionada =
                        td;

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

}


// =========================================================
// APLICAR DATOS LEÍDOS
// =========================================================

function aplicarDatosCalendario() {

    document
        .querySelectorAll('.celda-dia[data-fecha]')
        .forEach(function (celda) {

            const fecha =
                celda.dataset.fecha;


            const registro =
                calendarioMaestro[fecha];


            if (!registro) {

                limpiarCeldaVisual(
                    celda
                );

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

                return;

            }


            pintarCelda(
                celda,
                tipo
            );

        });

}


// =========================================================
// PINTAR CELDA
// =========================================================

function pintarCelda(celda, tipo) {

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
// MOSTRAR MENÚ
// =========================================================

function mostrarMenuAsistencia(x, y) {

    const menu =
        document.getElementById(
            'menuAsistencia'
        );


    const opciones =
        document.getElementById(
            'opcionesAsistencia'
        );


    opciones.innerHTML = '';


    // ---------------------------------------------------------
    // TIPOS DISPONIBLES
    // ---------------------------------------------------------

    tiposAsistencia.forEach(function (tipo) {

        const boton =
            document.createElement(
                'button'
            );


        boton.type =
            'button';


        boton.className =
            'opcion-asistencia';


        boton.innerHTML = `

            <span
                class="opcion-color"
                style="
                    background-color:${escapeHtml(tipo.color_fondo)};
                    color:${escapeHtml(tipo.color_texto)};
                ">

                ${escapeHtml(tipo.abreviatura)}

            </span>

            <span class="opcion-nombre">

                ${escapeHtml(tipo.nombre)}

            </span>

        `;


        boton.addEventListener(
            'click',
            function () {

                aplicarTipoAsistencia(
                    tipo
                );

            }
        );


        opciones.appendChild(
            boton
        );

    });


    // ---------------------------------------------------------
    // SEPARADOR
    // ---------------------------------------------------------

    const separador =
        document.createElement(
            'div'
        );

    separador.style.borderTop =
        '1px solid #dee2e6';

    separador.style.margin =
        '5px 0';


    opciones.appendChild(
        separador
    );


    // ---------------------------------------------------------
    // QUITAR ASISTENCIA
    // ---------------------------------------------------------

    const botonQuitar =
        document.createElement(
            'button'
        );


    botonQuitar.type =
        'button';


    botonQuitar.className =
        'opcion-asistencia text-danger';


    botonQuitar.innerHTML = `

        <span
            class="opcion-color"
            style="
                background-color:#ffffff;
                color:#dc3545;
                border:1px solid #dc3545;
            ">

            ×

        </span>

        <span class="opcion-nombre">

            Quitar asistencia

        </span>

    `;


    botonQuitar.addEventListener(
        'click',
        function () {

            quitarAsistencia();

        }
    );


    opciones.appendChild(
        botonQuitar
    );


    // ---------------------------------------------------------
    // MOSTRAR
    // ---------------------------------------------------------

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

async function aplicarTipoAsistencia(tipo) {

    if (!celdaSeleccionada) {
        return;
    }


    const fecha =
        celdaSeleccionada.dataset.fecha;


    ocultarMenuAsistencia();


    try {

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/calendario_maestro/calendario_maestro.php',
                {
                    method: 'POST',
                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },
                    body: JSON.stringify({

                        accion: 'guardar',

                        fecha: fecha,

                        idtipo_asistencia:
                            Number(
                                tipo.idtipo_asistencia
                            )

                    })
                }
            );


        if (!respuesta.ok) {

            throw new Error(
                'Error HTTP al guardar la asistencia.'
            );

        }


        const res =
            await respuesta.json();


        if (res.status !== 'ok') {

            throw new Error(
                res.msg ||
                'No se pudo guardar la asistencia.'
            );

        }


        // -----------------------------------------------------
        // ACTUALIZAR MEMORIA
        // -----------------------------------------------------

        calendarioMaestro[fecha] = {

            idcalendario:
                res.data?.idcalendario ||
                null,

            fecha:
                fecha,

            idtipo_asistencia:
                Number(
                    tipo.idtipo_asistencia
                ),

            nombre:
                tipo.nombre,

            abreviatura:
                tipo.abreviatura,

            color_fondo:
                tipo.color_fondo,

            color_texto:
                tipo.color_texto

        };


        // -----------------------------------------------------
        // ACTUALIZAR CELDA
        // -----------------------------------------------------

        pintarCelda(
            celdaSeleccionada,
            tipo
        );


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

    if (!celdaSeleccionada) {
        return;
    }


    const fecha =
        celdaSeleccionada.dataset.fecha;


    const registro =
        calendarioMaestro[fecha];


    ocultarMenuAsistencia();


    // ---------------------------------------------------------
    // CONFIRMAR
    // ---------------------------------------------------------

    const resultado =
        await Swal.fire({

            title:
                '¿Quitar asistencia?',

            text:
                'Se eliminará de la base de datos la asistencia registrada para este día.',

            icon:
                'warning',

            showCancelButton:
                true,

            confirmButtonText:
                'Sí, quitar',

            cancelButtonText:
                'Cancelar',

            reverseButtons:
                true

        });


    if (!resultado.isConfirmed) {
        return;
    }


    try {

        const respuesta =
            await fetch(
                API_BASE +
                '/fichajes/calendario_maestro/calendario_maestro.php',
                {
                    method: 'POST',
                    headers: {
                        ...obtenerHeadersSSO(),
                        'Content-Type':
                            'application/json'
                    },
                    body: JSON.stringify({

                        accion: 'eliminar',

                        fecha: fecha

                    })
                }
            );


        if (!respuesta.ok) {

            throw new Error(
                'Error HTTP al eliminar la asistencia.'
            );

        }


        const res =
            await respuesta.json();


        if (res.status !== 'ok') {

            throw new Error(
                res.msg ||
                'No se pudo eliminar la asistencia.'
            );

        }


        // -----------------------------------------------------
        // ELIMINAR DE MEMORIA
        // -----------------------------------------------------

        delete calendarioMaestro[
            fecha
        ];


        // -----------------------------------------------------
        // LIMPIAR CELDA
        // -----------------------------------------------------

        limpiarCeldaVisual(
            celdaSeleccionada
        );


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

    document
        .getElementById(
            'menuAsistencia'
        )
        .style.display =
        'none';

}


// =========================================================
// CERRAR MENÚ
// =========================================================

document.addEventListener(
    'click',
    function (event) {

        const menu =
            document.getElementById(
                'menuAsistencia'
            );


        if (
            menu.style.display === 'block' &&
            !menu.contains(event.target)
        ) {

            ocultarMenuAsistencia();

        }

    }
);


// =========================================================
// ESC
// =========================================================

document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {

            ocultarMenuAsistencia();

        }

    }
);


// =========================================================
// LEYENDA
// =========================================================

function construirLeyenda() {

    const contenedor =
        document.getElementById(
            'leyendaAsistencias'
        );


    contenedor.innerHTML = '';


    tiposAsistencia.forEach(
        function (tipo) {

            const item =
                document.createElement(
                    'div'
                );


            item.className =
                'leyenda-item';


            item.innerHTML = `

                <span
                    class="leyenda-color"
                    style="
                        background-color:${escapeHtml(tipo.color_fondo)};
                        color:${escapeHtml(tipo.color_texto)};
                    ">

                    ${escapeHtml(tipo.abreviatura)}

                </span>

                <span>

                    ${escapeHtml(tipo.nombre)}

                </span>

            `;


            contenedor.appendChild(
                item
            );

        }
    );

}


// =========================================================
// ESCAPAR HTML
// =========================================================

function escapeHtml(valor) {

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        valor ?? '';


    return div.innerHTML;

}