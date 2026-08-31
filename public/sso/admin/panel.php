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
    .app-icon { font-size: 3rem; }
    .loader-overlay {
        display: none;
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(2px);
        align-items: center;
        justify-content: center;
        border-radius: inherit;
        z-index: 10;
    }
</style>

<main class="container py-3">
    <div class="text-center mb-5">
        <h2 class="fw-light">Aplicaciones Disponibles</h2>
        <p class="text-muted">Seleccioná un sistema para ingresar con tus credenciales unificadas</p>
    </div>

    <div class="row g-4 justify-content-center" id="container-apps">
        <!-- Carga dinámica vía JS -->
    </div>
</main>

<script>
    const ICONOS_APP = {
        'ctacte': 'fa-solid fa-file-invoice-dollar',
        'medicina': 'fa-solid fa-user-nurse',
        'saas_docs': 'fa-solid fa-book-bookmark',
        'admin': 'fa-solid fa-gears',
        'default': 'fa-solid fa-cubes'
    };

    $(document).ready(function() {
        const token = localStorage.getItem('sso_token');
        
        if (!token) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin sesión activa',
                text: 'No se encontraron credenciales de acceso.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => { window.location.href = '../auth/login.php'; });
            return;
        }

        // Consultamos al backend en cada carga para traer las apps actualizadas al instante
        $.ajax({
            url: API_BASE + '/sso/obtener_apps.php', // O el endpoint que devuelva las apps del token
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ token: token }),
            success: function(response) {
                if (response.status === 'ok' && response.aplicaciones) {
                    // Guardamos las apps frescas en el localStorage
                    localStorage.setItem('sso_aplicaciones', JSON.stringify(response.aplicaciones));
                    renderizarApps(response.aplicaciones);
                } else {
                    forzarFallbackLocal();
                }
            },
            error: function() {
                forzarFallbackLocal();
            }
        });
    });

    // Plan B por si falla la red: usa lo último que tenga guardado localmente
    function forzarFallbackLocal() {
        const rawApps = localStorage.getItem('sso_aplicaciones');
        if (rawApps) {
            renderizarApps(JSON.parse(rawApps));
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Sin sesión activa',
                text: 'No se encontraron aplicaciones asignadas.',
                timer: 2000,
                showConfirmButton: false
            }).then(() => { window.location.href = '../auth/login.php'; });
        }
    }

    function renderizarApps(aplicaciones) {
        const $container = $('#container-apps');
        $container.empty();

        let appsVisibles = 0;

        aplicaciones.forEach(app => {
            // Ocultamos el panel central para que no salga como tarjeta de cliente
            if (app.slug === 'sso_central' || app.slug === 'admin') return;

            appsVisibles++;
            const iconoClass = app.icono ? app.icono : 'fa-solid fa-cubes';
            
            const cardHtml = `
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="card app-card text-center h-100 shadow-sm border-0 position-relative" onclick="ingresarApp('${app.slug}', this)">
                        <div class="loader-overlay">
                            <i class="fa-solid fa-circle-notch fa-spin fa-2x text-white"></i>
                        </div>
                        <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                            <div class="app-icon text-primary mb-3">
                                <i class="${iconoClass}"></i>
                            </div>
                            <h5 class="card-title fw-bold text-body mb-2">${app.nombre}</h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 text-uppercase">${app.slug}</span>
                        </div>
                    </div>
                </div>
            `;
            $container.append(cardHtml);
        });

        if (appsVisibles === 0) {
            $container.html('<div class="text-center text-muted"><p>No hay aplicaciones activas disponibles en este momento.</p></div>');
        }
    }

    function ingresarApp(slug, element) {
        const $card = $(element);
        $card.find('.loader-overlay').css('display', 'flex');

        $.ajax({
            url: API_BASE + '/sso/seleccionar_app.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                app_slug: slug,
                token: localStorage.getItem('sso_token')
            }),
            success: function(response) {
                if (response.status === 'ok') {
                    localStorage.setItem('sso_app_activa', JSON.stringify(response.app));
                    
                    const tokenActual = localStorage.getItem('sso_token');
                    let urlDestino = response.app.url_base;
                    const separador = urlDestino.includes('?') ? '&' : '?';
                    urlDestino = `${urlDestino}${separador}token=${tokenActual}`;

                    window.location.href = urlDestino;
                } else {
                    $card.find('.loader-overlay').hide();
                    Swal.fire('Error de acceso', response.msg || 'No autorizado', 'error');
                }
            },
            error: function() {
                $card.find('.loader-overlay').hide();
                Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
            }
        });
    }
</script>
</body>
</html>