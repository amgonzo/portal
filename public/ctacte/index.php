<?php

// =========================================================
// 1. CARGAR RUTAS CENTRALES
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';


// =========================================================
// 2. CARGAR COMPOSER
// =========================================================

require_once $rutas['autoload'];


// =========================================================
// 3. CARGAR .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_ctacte']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos normalmente

}


// =========================================================
// 4. CONFIGURACIÓN DE LA APLICACIÓN
// =========================================================

$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

$ssoApiUrl = $_ENV['API_URL'] ?? '/api';

$ssoLoginUrl = $_ENV['SSO_LOGIN_URL'] ?? '/sso/auth/login.php';

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">


    <!-- =====================================================
         FAVICON
         ===================================================== -->

    <?php if (file_exists($rutas['favicon'])): ?>

        <link
            rel="icon"
            href="<?= htmlspecialchars($rutas['favicon_web']) ?>"
            type="image/x-icon"
        >

    <?php endif; ?>


    <!-- =====================================================
         TÍTULO
         ===================================================== -->

    <title>
        <?= htmlspecialchars($empresa) ?> - Validación SSO
    </title>


    <!-- =====================================================
         BOOTSTRAP
         ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- =====================================================
         CSS PRINCIPAL
         ===================================================== -->

    <?php if (file_exists($rutas['css_main'])): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($rutas['css_main_web']) ?>?v=<?= filemtime($rutas['css_main']) ?>"
        >

    <?php endif; ?>


    <!-- =====================================================
         CSS DEL LOGO
         ===================================================== -->

    <?php if (
        isset($rutas['logo_ctacte']) &&
        isset($rutas['logo_ctacte_web']) &&
        file_exists($rutas['logo_ctacte'])
    ): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($rutas['logo_ctacte_web']) ?>?v=<?= filemtime($rutas['logo_ctacte']) ?>"
        >

    <?php endif; ?>


    <style>

        body {
            background-color: #f8f9fa;
        }

        .hero-section {
            padding: 3rem 1rem;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }

        .logo-placeholder {
            font-size: 2.75rem;
            font-weight: 700;
            color: #0d6efd;
        }

        .logo-sistema {
            max-height: 100px;
            width: auto;
        }

        .btn-custom-xl {
            padding: 1rem 1.75rem;
            font-size: 1.25rem;
            border-radius: 0.5rem;
        }

    </style>

</head>


<body class="d-flex flex-column min-vh-100">


    <!-- =====================================================
         HEADER
         ===================================================== -->

    <header class="hero-section text-center mb-4 shadow-sm">

        <div class="container">


            <!-- =================================================
                 LOGO
                 ================================================= -->

            <?php if (file_exists($rutas['logo_ctacte'])): ?>

                <div class="mb-3">

                    <img
                        src="<?= htmlspecialchars($rutas['logo_ctacte_web']) ?>"
                        alt="Logo <?= htmlspecialchars($empresa) ?>"
                        class="logo-sistema img-fluid"
                    >

                </div>

            <?php endif; ?>


            <!-- =================================================
                 NOMBRE DE LA EMPRESA
                 ================================================= -->

            <h1 class="logo-placeholder mb-2">

                <?= htmlspecialchars($empresa) ?>

            </h1>


            <p class="lead text-secondary mb-0">

                Sistema de Gestión - Validación SSO

            </p>

        </div>

    </header>


    <!-- =====================================================
         CONTENIDO
         ===================================================== -->

    <main class="container my-auto">

        <div class="row justify-content-center">

            <div class="col-12 col-sm-8 col-md-5 col-lg-4 text-center">


                <div class="card shadow-sm border-0 p-4 bg-white rounded-3">


                    <!-- =================================================
                         SPINNER
                         ================================================= -->

                    <div id="spinner-carga">

                        <div
                            class="spinner-border text-primary mb-3"
                            role="status"
                            style="width: 2.5rem; height: 2.5rem;"
                        ></div>


                        <h5 class="text-secondary fw-semibold">

                            Validando sesión...

                        </h5>


                        <p class="text-muted small mb-0">

                            Comprobando credenciales...

                        </p>

                    </div>


                    <!-- =================================================
                         MENSAJE DE ERROR / LOGIN
                         ================================================= -->

                    <div id="mensaje-estado" style="display: none;">


                        <div class="text-danger mb-3">

                            <i class="bi bi-exclamation-triangle-fill fs-1"></i>

                        </div>


                        <h5
                            id="texto-resultado"
                            class="fw-bold mb-2 text-danger"
                        >
                            Sin sesión activa
                        </h5>


                        <p
                            id="detalle-resultado"
                            class="text-muted small mb-4"
                        >
                            No se encontró un token válido o tu sesión ha expirado.
                        </p>


                        <a
                            href="#"
                            id="enlaceAccion"
                            class="btn btn-primary btn-custom-xl w-100 shadow-sm d-inline-flex align-items-center justify-content-center gap-2"
                        >

                            <i class="bi bi-box-arrow-in-right fs-4"></i>

                            <span>
                                IR AL LOGIN SSO
                            </span>

                        </a>

                    </div>


                </div>

            </div>

        </div>

    </main>


    <!-- =====================================================
         SCRIPTS
         ===================================================== -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>


    <script>

        // =====================================================
        // CONFIGURACIÓN PHP → JAVASCRIPT
        // =====================================================

        const SSO_API_URL = <?= json_encode($ssoApiUrl) ?>;

        const SSO_LOGIN_URL = <?= json_encode($ssoLoginUrl) ?>;


        // =====================================================
        // INICIO
        // =====================================================

        $(document).ready(function() {


            // =================================================
            // 1. CAPTURAR TOKEN DESDE GET
            // =================================================

            const urlParams = new URLSearchParams(
                window.location.search
            );

            const tokenUrl = urlParams.get('token');


            if (tokenUrl) {

                localStorage.setItem(
                    'sso_token',
                    tokenUrl
                );

                window.history.replaceState(
                    {},
                    document.title,
                    window.location.pathname
                );

            }


            // =================================================
            // 2. LEER TOKEN LOCAL
            // =================================================

            const token = localStorage.getItem('sso_token');


            if (!token) {

                mostrarPanelLogin(
                    "Sesión requerida",
                    "Debes iniciar sesión a través del sistema centralizado de autenticación."
                );

                return;

            }


            // =================================================
            // 3. VALIDAR TOKEN CONTRA API SSO
            // =================================================

            $.ajax({

                url: SSO_API_URL + '/sso/auth/me.php',

                type: 'GET',

                headers: {
                    "Authorization": "Bearer " + token
                },


                success: function(response) {


                    const res =
                        (typeof response === 'string')
                            ? JSON.parse(response)
                            : response;


                    if (
                        res.status === 'ok' &&
                        res.usuario
                    ) {


                        // =========================================
                        // GUARDAR USUARIO
                        // =========================================

                        localStorage.setItem(
                            'usuario_actual',
                            JSON.stringify(res.usuario)
                        );


                        // =========================================
                        // MENSAJE
                        // =========================================

                        $('#spinner-carga').html(`

                            <div
                                class="spinner-grow text-success mb-3"
                                role="status"
                                style="width: 2.5rem; height: 2.5rem;"
                            ></div>

                            <h5 class="text-success fw-bold">

                                ¡Bienvenido,
                                ${escapeHtml(
                                    res.usuario.nombre ||
                                    res.usuario.username
                                )}!

                            </h5>

                            <p class="text-muted small mb-0">

                                Entrando al sistema...

                            </p>

                        `);


                        // =========================================
                        // REDIRECCIÓN
                        // =========================================

                        setTimeout(() => {

                            window.location.href =
                                '/ctacte/admin/dashboard.php';

                        }, 500);


                    } else {


                        mostrarPanelLogin(
                            "Sesión Expirada",
                            "El token almacenado ya no es válido. Volvé a ingresar."
                        );

                    }

                },


                error: function(xhr) {

                    console.error(
                        xhr.responseText
                    );


                    mostrarPanelLogin(
                        "Error de Conexión",
                        "No se pudo validar la sesión con el servidor central."
                    );

                }

            });

        });


        // =====================================================
        // MOSTRAR PANEL DE LOGIN
        // =====================================================

        function mostrarPanelLogin(titulo, detalle) {


            $("#spinner-carga").hide();


            $("#texto-resultado").text(
                titulo
            );


            $("#detalle-resultado").text(
                detalle
            );


            $("#enlaceAccion")
                .off("click")
                .on("click", function(e) {


                    e.preventDefault();


                    // =============================================
                    // LIMPIAR TOKEN
                    // =============================================

                    localStorage.removeItem(
                        'sso_token'
                    );

                    localStorage.removeItem(
                        'usuario_actual'
                    );


                    // =============================================
                    // URL DE RETORNO
                    // =============================================

                    const redirectUrl =
                        encodeURIComponent(
                            window.location.origin +
                            window.location.pathname.replace(
                                'index.php',
                                '/admin/dashboard.php'
                            )
                        );


                    // =============================================
                    // IR AL LOGIN SSO
                    // =============================================

                    window.location.href =
                        SSO_LOGIN_URL +
                        "?redirect=" +
                        redirectUrl;

                });


            $("#mensaje-estado").show();

        }


        // =====================================================
        // ESCAPAR HTML
        // =====================================================

        function escapeHtml(text) {

            const div = document.createElement('div');

            div.textContent = text ?? '';

            return div.innerHTML;

        }

    </script>

</body>

</html>