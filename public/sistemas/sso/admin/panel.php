<?php
include_once 'header.php';
include_once 'menu.php';
?>

<style>
    .app-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
    }

    .app-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    .app-icon {
        font-size: 3rem;
    }

    .loader-overlay {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
        border-radius: inherit;
        z-index: 10;
    }
</style>

<main class="container py-3">

<!-- =====================================================
     TÍTULO
     ===================================================== -->

<div class="text-center mb-5">

    <h2 class="fw-light" id="tituloPanel">
        Aplicaciones Disponibles
    </h2>

    <p class="text-muted" id="subtituloPanel">
        Seleccioná un sistema para ingresar con tus credenciales unificadas
    </p>

</div>


<!-- =====================================================
     APLICACIONES
     ===================================================== -->

<div
    class="row g-4 justify-content-center"
    id="container-apps"
></div>

</main>

<script>

    // =========================================================
    // ICONOS
    // =========================================================

    const ICONOS_APP = {
        'ctacte': 'fa-solid fa-file-invoice-dollar',
        'medicina': 'fa-solid fa-user-nurse',
        'saas_docs': 'fa-solid fa-book-bookmark',
        'admin': 'fa-solid fa-gears',
        'default': 'fa-solid fa-cubes'
    };


    // =========================================================
    // INICIO
    // =========================================================

    $(document).ready(function() {

        const token =
            localStorage.getItem('sso_token');


        if (!token) {

            Swal.fire({
                icon: 'warning',
                title: 'Sin sesión activa',
                text: 'No se encontraron credenciales de acceso.',
                timer: 2000,
                showConfirmButton: false
            }).then(function() {

                window.location.href =
                    '../auth/login.php';

            });

            return;
        }


        // =====================================================
        // IMPORTANTE
        //
        // La empresa se selecciona desde el MENÚ.
        //
        // Si todavía no hay empresa:
        // el panel queda vacío.
        //
        // Si ya hay empresa:
        // carga sus aplicaciones.
        // =====================================================

        cargarAplicaciones();

    });


    // =========================================================
    // CARGAR APLICACIONES
    // =========================================================

    function cargarAplicaciones() {

        const empresa =
            obtenerEmpresaActiva();


        // -----------------------------------------------------
        // SIN EMPRESA
        // -----------------------------------------------------
        //
        // No mostramos selector.
        // No usamos aplicaciones anteriores.
        // -----------------------------------------------------

        if (
            !empresa ||
            !empresa.idempresa
        ) {

            $('#container-apps').empty();

            localStorage.removeItem(
                'sso_aplicaciones'
            );

            return;
        }


        // -----------------------------------------------------
        // MOSTRAR CARGANDO
        // -----------------------------------------------------

        const $container =
            $('#container-apps');


        $container.html(`

            <div class="col-12 text-center text-muted">

                <div
                    class="spinner-border mb-3"
                    role="status"
                ></div>

                <p>
                    Cargando aplicaciones...
                </p>

            </div>

        `);


        // -----------------------------------------------------
        // CONSULTAR APLICACIONES
        // -----------------------------------------------------

        $.ajax({

            url:
                API_BASE +
                '/sso/obtener_apps.php',

            type:
                'POST',

            contentType:
                'application/json',

            headers:
                obtenerHeadersSSO(),

            data:
                JSON.stringify({}),

            success:
                function(response) {

                    if (
                        response &&
                        response.status === 'ok'
                    ) {

                        const aplicaciones =
                            Array.isArray(
                                response.aplicaciones
                            )
                                ? response.aplicaciones
                                : [];


                        localStorage.setItem(
                            'sso_aplicaciones',
                            JSON.stringify(
                                aplicaciones
                            )
                        );


                        renderizarApps(
                            aplicaciones
                        );

                        return;
                    }


                    localStorage.removeItem(
                        'sso_aplicaciones'
                    );


                    renderizarApps([]);

                },

            error:
                function(xhr) {

                    console.error(
                        'Error obtener_apps.php:',
                        xhr.status,
                        xhr.responseText
                    );


                    localStorage.removeItem(
                        'sso_aplicaciones'
                    );


                    $container.html(`

                        <div class="col-12 text-center text-danger">

                            <p>
                                No se pudieron cargar las aplicaciones.
                            </p>

                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm"
                                onclick="cargarAplicaciones()"
                            >
                                Reintentar
                            </button>

                        </div>

                    `);

                }

        });

    }


    // =========================================================
    // RENDERIZAR APLICACIONES
    // =========================================================

    function renderizarApps(aplicaciones) {

        const $container =
            $('#container-apps');


        $container.empty();


        let appsVisibles = 0;


        if (
            !Array.isArray(aplicaciones)
        ) {

            aplicaciones = [];

        }


        aplicaciones.forEach(function(app) {


            // -------------------------------------------------
            // OCULTAR SISTEMAS CENTRALES
            // -------------------------------------------------

            if (
                app.slug === 'sso_central' ||
                app.slug === 'admin'
            ) {

                return;
            }


            appsVisibles++;


            const iconoClass =
                app.icono ||
                ICONOS_APP[app.slug] ||
                ICONOS_APP.default;


            const cardHtml = `

                <div class="col-12 col-sm-6 col-lg-4">

                    <div
                        class="card app-card text-center h-100 shadow-sm border-0 position-relative"
                        onclick="ingresarApp('${app.slug}', this)"
                    >

                        <div class="loader-overlay">

                            <i
                                class="fa-solid fa-circle-notch fa-spin fa-2x text-white"
                            ></i>

                        </div>


                        <div
                            class="card-body d-flex flex-column align-items-center justify-content-center p-4"
                        >

                            <div
                                class="app-icon text-primary mb-3"
                            >

                                <i
                                    class="${iconoClass}"
                                ></i>

                            </div>


                            <h5
                                class="card-title fw-bold text-body mb-2"
                            >

                                ${app.nombre}

                            </h5>


                            <span
                                class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 text-uppercase"
                            >

                                ${app.slug}

                            </span>

                        </div>

                    </div>

                </div>

            `;


            $container.append(
                cardHtml
            );

        });


        // -----------------------------------------------------
        // NO HAY APLICACIONES
        // -----------------------------------------------------

        if (
            appsVisibles === 0
        ) {

            $container.html(`

                <div class="col-12 text-center text-muted">

                    <p>
                        No hay aplicaciones activas disponibles para esta empresa.
                    </p>

                </div>

            `);

        }

    }


    // =========================================================
    // INGRESAR A UNA APLICACIÓN
    // =========================================================

    function ingresarApp(
        slug,
        element
    ) {

        const $card =
            $(element);


        $card
            .find('.loader-overlay')
            .css('display', 'flex');


        $.ajax({

            url:
                API_BASE +
                '/sso/seleccionar_app.php',

            type:
                'POST',

            contentType:
                'application/json',

            headers:
                obtenerHeadersSSO(),

            data:
                JSON.stringify({
                    app_slug: slug
                }),

            success:
                function(response) {

                    console.log(
                        'seleccionar_app.php:',
                        response
                    );


                    if (
                        response &&
                        response.status === 'ok'
                    ) {

                        localStorage.setItem(
                            'sso_app_activa',
                            JSON.stringify(
                                response.app
                            )
                        );


                        const tokenActual =
                            localStorage.getItem(
                                'sso_token'
                            );


                        let urlDestino =
                            response.app.url_base;


                        const separador =
                            urlDestino.includes('?')
                                ? '&'
                                : '?';


                        urlDestino =
                            `${urlDestino}${separador}token=${tokenActual}`;


                        window.location.href =
                            urlDestino;


                        return;
                    }


                    $card
                        .find('.loader-overlay')
                        .hide();


                    Swal.fire(
                        'Error de acceso',
                        response.msg ||
                        'No autorizado',
                        'error'
                    );

                },

            error:
                function(xhr) {

                    $card
                        .find('.loader-overlay')
                        .hide();


                    console.error(
                        'Error seleccionar_app.php:',
                        xhr.status,
                        xhr.responseText
                    );


                    Swal.fire(
                        'Error',
                        'Error de conexión con el servidor.',
                        'error'
                    );

                }

        });

    }

</script>

</body>
</html>
