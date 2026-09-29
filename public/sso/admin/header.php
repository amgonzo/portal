<?php
// Ruta directa y simple (asumiendo que tu estructura siempre respeta la raíz del sitio)
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// Carga directa del .env y autoload desde la config central si la necesitás
require_once $rutas['autoload'];

require_once $rutas['public'] . '/api/utils/helpers.php';

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_sso']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos

}

$apiUrl = $_ENV['API_URL'] ?? '/api';
$loginWeb = $rutas['login_sso_web'] ?? '../auth/login.php';
$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ecosistema SSO</title>
    
    <!-- 🛡️ Guardián de Token en el Cliente -->
    <script>
        if (!localStorage.getItem('sso_token')) {
            window.location.href = '../auth/login.php';
        }
    </script>
    <script>
        window.SSO_LOGIN_URL = <?= json_encode($rutas['login_sso_web']) ?>;
    </script>
    <!-- Variables y Helpers Globales basados en Storage -->
    <script>
        const TOKEN = localStorage.getItem('sso_token') || '';
        // Puedes guardar los permisos en localStorage al loguear para usarlos aquí
        let MIS_PERMISOS = JSON.parse(
            localStorage.getItem('sso_permisos') || '[]'
        );
        window.APP_NAME = "Ecosistema SSO";

        function tienePermiso(clave) {
            return Array.isArray(MIS_PERMISOS) && MIS_PERMISOS.includes(clave);
        }
    </script>

    <!-- Fonts & Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="<?php echo versionar('css/boostrap5a4.css'); ?>">

    <!-- JS Base -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables CSS y JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script src="<?= htmlspecialchars($rutas['js_sso_web']) ?>?v=<?= filemtime($rutas['js_sso']) ?>"></script>

    <script>
        const API_BASE = "<?php echo $apiUrl; ?>";   
    </script>
</head>
<body class="bg-body-tertiary">