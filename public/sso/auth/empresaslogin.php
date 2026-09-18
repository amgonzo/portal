<?php

// =========================================================
// 1. MOSTRAR ERRORES - SOLO PARA DEPURACIÓN
// =========================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// =========================================================
// 2. CARGAR RUTAS CENTRALES
// =========================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';


// =========================================================
// 3. CARGAR COMPOSER
// =========================================================

require_once $rutas['autoload'];


// =========================================================
// 4. CARGAR .ENV
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_sso']);
    $dotenv->load();

} catch (Exception $e) {

    echo "Error cargando .env: " . $e->getMessage();
    exit;

}


// =========================================================
// 5. CONFIGURACIÓN
// =========================================================

$apiUrl = $_ENV['API_URL'] ?? '/api';
$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

$mostrarNombre = filter_var(
    $_ENV['MOSTRAR_APP_NAME'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

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
        Seleccionar Empresa - <?= htmlspecialchars($empresa) ?>
    </title>

    <!-- =====================================================
         BOOTSTRAP & ICONS
         ===================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
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

    <?php if (file_exists($rutas['css_logo'])): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($rutas['css_logo_web']) ?>?v=<?= filemtime($rutas['css_logo']) ?>"
        >

    <?php endif; ?>

    <style>
        body {
            background-color: #f8f9fa;
        }
        .empresa-card {
            border-radius: 0.75rem;
            border: 1px solid #dee2e6;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
        }
        .empresa-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
            border-color: var(--bs-primary);
        }
    </style>

</head>

<body class="d-flex align-items-center justify-content-center min-vh-100 py-4">

    <div class="container" style="max-width: 600px;">

        <!-- HEADER / LOGO -->
        <div class="text-center mb-4">
            <div class="logo-container-login mb-3">
                <?php if (file_exists($rutas['logo_sso'])): ?>
                    <img
                        src="<?= htmlspecialchars($rutas['logo_sso_web']) ?>"
                        alt="Logo"
                        class="logo-sistema img-fluid"
                        style="max-height: 70px;"
                    >
                <?php endif; ?>
            </div>

            <h3 class="fw-bold text-primary mb-1">Seleccionar Empresa</h3>
            <p class="text-muted small">Elegí la empresa con la que deseas trabajar en esta sesión</p>
        </div>

        <!-- LISTADO DE EMPRESAS -->
        <div class="row g-3" id="lista-empresas">
            <!-- Renderizado dinámico vía JS -->
        </div>

    </div>

    <!-- =====================================================
         SCRIPTS
         ===================================================== -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Configuración inyectada desde PHP con rutas seguras
        const URL_PANEL = <?= json_encode($rutas['panel_sso_web'] ?? '/sso/admin/panel.php') ?>;
        const URL_LOGIN = <?= json_encode($rutas['login_sso_web'] ?? '/sso/auth/login.php') ?>;

        $(document).ready(function() {
            const token = localStorage.getItem('sso_token');
            if (!token) {
                window.location.href = URL_LOGIN;
                return;
            }

            const empresas = JSON.parse(localStorage.getItem('sso_empresas') || '[]');
            const $container = $('#lista-empresas');

            if (empresas.length === 0) {
                $container.html(`
                    <div class="col-12 text-center text-muted py-4">
                        <p>No se encontraron empresas disponibles para este usuario.</p>
                        <a href="${URL_LOGIN}" class="btn btn-sm btn-outline-primary mt-2">Volver al Login</a>
                    </div>
                `);
                return;
            }

            empresas.forEach(emp => {
                const cardHtml = `
                    <div class="col-12">
                        <div class="card empresa-card shadow-sm p-3 bg-white" onclick="seleccionarEmpresa(${emp.idempresa})">
                            <div class="card-body d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <h5 class="fw-bold text-dark mb-1">
                                        <i class="fa-solid fa-building text-primary me-2"></i> ${emp.nombre}
                                    </h5>
                                    <small class="text-muted">
                                        CUIT: ${emp.cuit || 'No registrado'} | Razón Social: ${emp.razon_social || emp.nombre}
                                    </small>
                                </div>
                                <div class="text-primary fs-5">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $container.append(cardHtml);
            });
        });

        function seleccionarEmpresa(idempresa) {
            const empresas = JSON.parse(localStorage.getItem('sso_empresas') || '[]');
            const empresaSeleccionada = empresas.find(e => e.idempresa == idempresa);

            if (empresaSeleccionada) {
                // Guardamos la empresa activa elegida
                localStorage.setItem('sso_empresa_activa', JSON.stringify(empresaSeleccionada));
                // Redirigimos usando la ruta del panel obtenida de PHP de forma limpia
                window.location.href = URL_PANEL;
            }
        }
    </script>
</body>
</html>