<?php
// =========================================================
// CONFIGURACIÓN CENTRAL
// =========================================================
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

require_once $rutas['autoload'];
require_once $rutas['public'] . '/api/utils/helpers.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_ctacte']);
    $dotenv->load();
} catch (Exception $e) {
    // Si no existe .env, continuamos
}

$apiUrl   = $_ENV['API_URL'] ?? '/api';
$loginWeb = $rutas['login_sso_web'] ?? '../auth/login.php';
$empresa  = $_ENV['APP_NAME'] ?? 'Mi Sistema';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($empresa); ?></title>

    <!-- =====================================================
         GUARDIÁN DE TOKEN
         ===================================================== -->
     <script>
     if (!localStorage.getItem('sso_token')) {
          location.href = window.SSO_LOGIN_URL;
     }
     </script>
    <script>
        window.SSO_LOGIN_URL = <?= json_encode($rutas['login_sso_web']) ?>;
    </script>
    <!-- =====================================================
         VARIABLES GLOBALES DE CTA CTE
         ===================================================== -->
    <script>
        const TOKEN = localStorage.getItem('sso_token') || '';

        let MIS_PERMISOS = JSON.parse(
          localStorage.getItem('sso_permisos') || '[]'
          );

        window.APP_NAME = <?php echo json_encode($empresa); ?>;
        window.API_BASE = <?php echo json_encode($apiUrl); ?>;
    </script>

    <!-- =====================================================
         GOOGLE FONTS
         ===================================================== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;1,300&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- =====================================================
         ICONOS
         ===================================================== -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- =====================================================
         BOOTSTRAP / PLUGINS CSS
         ===================================================== -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

    <!-- =====================================================
         ESTILOS PROPIOS
         ===================================================== -->
    <link rel="stylesheet" href="<?php echo versionar('logo_sistema.css'); ?>">
    <link rel="stylesheet" href="<?php echo versionar('css/boostrap5a4.css'); ?>">

    <!-- =====================================================
         FAVICON
         ===================================================== -->
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- =====================================================
         LIBRERÍAS JS
         ===================================================== -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>

    <!-- =====================================================
         SSO - FUNCIONES COMUNES
         Empresa, diccionario, tema, permisos, logout, etc.
         ===================================================== -->
    <script src="<?= htmlspecialchars($rutas['js_sso_web']) ?>?v=<?= filemtime($rutas['js_sso']) ?>"></script>

</head>
<body class="bg-body-tertiary">