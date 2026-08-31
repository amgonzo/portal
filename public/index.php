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

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_sso']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos normalmente

}


// =========================================================
// 4. CONFIGURACIÓN DE LA APLICACIÓN
// =========================================================

$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

$mostrarNombre = filter_var(
    $_ENV['MOSTRAR_APP_NAME'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);


// =========================================================
// 5. DETECCIÓN BÁSICA DE DISPOSITIVO
// =========================================================

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$isMobile = preg_match(
    '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm(os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',
    $userAgent
);


// =========================================================
// 6. URL DEL LOGIN
// =========================================================

$urlLogin = $rutas['login_sso_web'];

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

        <link rel="icon"
              href="<?= htmlspecialchars($rutas['favicon_web']) ?>"
              type="image/x-icon">

    <?php endif; ?>


    <!-- =====================================================
         TÍTULO
         ===================================================== -->

    <title>
        <?= htmlspecialchars($empresa) ?> - Presentación
    </title>


    <!-- =====================================================
         BOOTSTRAP
         ===================================================== -->

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">


    <!-- =====================================================
         CSS PRINCIPAL
         ===================================================== -->

    <?php if (file_exists($rutas['css_main'])): ?>

        <link rel="stylesheet"
              href="<?= htmlspecialchars($rutas['css_main_web']) ?>?v=<?= filemtime($rutas['css_main']) ?>">

    <?php endif; ?>


    <style>

        body {
            background-color: #f8f9fa;
        }

        .hero-section {
            padding: 4rem 1rem;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }

        .logo-placeholder {
            font-size: 2.75rem;
            font-weight: 700;
            color: #0d6efd;
        }

        .logo-sistema {
            max-height: 120px;
            width: auto;
        }

        .btn-custom-xl {
            padding: 1rem 1.75rem;
            font-size: 1.25rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
        }

        .btn-custom-xl:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

    </style>

</head>


<body class="d-flex flex-column min-vh-100">


    <!-- =====================================================
         HERO
         ===================================================== -->

    <header class="hero-section text-center mb-5 shadow-sm">

        <div class="container">

            <div class="logo-container mb-3">

                <?php if (file_exists($rutas['logo_sso'])): ?>

                    <img
                        src="<?= htmlspecialchars($rutas['logo_sso_web']) ?>"
                        alt="Logo <?= htmlspecialchars($empresa) ?>"
                        class="logo-sistema img-fluid"
                        onerror="this.style.display='none'"
                    >

                <?php endif; ?>

            </div>


            <?php if ($mostrarNombre): ?>

                <h1 class="logo-placeholder mb-2">
                    <?= htmlspecialchars($empresa) ?>
                </h1>

            <?php endif; ?>


            <p class="lead text-secondary mb-0">
                Sistema de Login único centralizado
            </p>

        </div>

    </header>


    <!-- =====================================================
         CONTENIDO PRINCIPAL
         ===================================================== -->

    <main class="container my-auto">

        <div class="row justify-content-center">

            <div class="col-12 col-sm-8 col-md-5 col-lg-4 text-center">


                <!-- =================================================
                     BOTÓN INGRESAR
                     ================================================= -->

                <a
                    href="<?= htmlspecialchars($urlLogin) ?>"
                    class="btn btn-primary btn-custom-xl w-100 shadow-sm mb-3 d-inline-flex align-items-center justify-content-center gap-2"
                >

                    <i class="bi bi-box-arrow-in-right fs-4"></i>

                    <span>
                        INGRESAR
                    </span>

                </a>


                <!-- =================================================
                     DETECCIÓN DE DISPOSITIVO
                     ================================================= -->

                <div class="alert alert-info text-center py-2 px-3 shadow-sm rounded-3">

                    <small class="d-flex align-items-center justify-content-center gap-1">

                        <i class="bi bi-display"></i>

                        <span>

                            Detectado como:

                            <strong>
                                <?= $isMobile ? 'Dispositivo Móvil' : 'Computadora (PC)' ?>
                            </strong>

                        </span>

                    </small>

                </div>

            </div>

        </div>

    </main>


    <!-- =====================================================
         FOOTER
         ===================================================== -->

    <footer class="footer mt-auto py-4 bg-light border-top">

        <div class="container text-center">

            <p class="text-muted small mb-0">

                © <?= date('Y') ?> La Amistad - Provincia de Buenos Aires

            </p>

        </div>

    </footer>


    <!-- =====================================================
         BOOTSTRAP JS
         ===================================================== -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

</body>

</html>
