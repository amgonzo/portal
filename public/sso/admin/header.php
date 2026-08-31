<?php
// Ruta directa y simple (asumiendo que tu estructura siempre respeta la raíz del sitio)
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// Carga directa del .env y autoload desde la config central si la necesitás
require_once $rutas['autoload'];

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_sso']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos

}

$apiUrl = $_ENV['API_URL'] ?? '/api';
$loginWeb = $rutas[' login_sso_web'] ?? '../auth/login.php';
$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

function versionar($url) {
    // Limpiamos la URL por si viene con barra inicial
    $urlLimpia = ltrim($url, '/');
    $rutaAbsoluta = $_SERVER['DOCUMENT_ROOT'] . '/' . $urlLimpia;
    
    return file_exists($rutaAbsoluta) ? '/' . $urlLimpia . "?v=" . filemtime($rutaAbsoluta) : $url;
}
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

    <!-- Variables y Helpers Globales basados en Storage -->
    <script>
        const TOKEN = localStorage.getItem('sso_token') || '';
        // Puedes guardar los permisos en localStorage al loguear para usarlos aquí
        const MIS_PERMISOS = JSON.parse(localStorage.getItem('sso_permisos') || '[]');
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
    <?php if (file_exists(__DIR__ . '/../css/boostrap5a4.css')): ?>
        <link rel="stylesheet" href="../css/boostrap5a4.css?v=<?php echo filemtime(__DIR__ . '/../css/boostrap5a4.css'); ?>">
    <?php elseif (file_exists(__DIR__ . '/css/boostrap5a4.css')): ?>
        <link rel="stylesheet" href="css/boostrap5a4.css?v=<?php echo filemtime(__DIR__ . '/css/boostrap5a4.css'); ?>">
    <?php endif; ?>

    <!-- JS Base -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- DataTables CSS y JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        const API_BASE = "<?php echo $apiUrl; ?>";
        // ID de usuario genérico o manejado por JS si es necesario
        const ID_USUARIO_LOGUEADO = localStorage.getItem('sso_idusuario') || 0;
        
        const toast = (mensaje, icono = 'success') => {
            Swal.mixin({
                toast: true,
                position: 'bottom-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            }).fire({ icon: icono, title: mensaje });
        };

        // Estado inicial de Modo Oscuro
        (function() {
            const temaGuardado = localStorage.getItem('theme_mode') || 'light';
            document.documentElement.setAttribute('data-bs-theme', temaGuardado);
        })();

        function toggleModoOscuro() {
            const html = document.documentElement;
            const nuevoTema = (html.getAttribute('data-bs-theme') === 'dark') ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', nuevoTema);
            localStorage.setItem('theme_mode', nuevoTema);
            actualizarIconoTema(nuevoTema);
        }

        function actualizarIconoTema(tema) {
            const icono = document.getElementById('iconoTheme');
            const switchInput = document.getElementById('checkThemeSwitch');
            if (tema === 'dark') {
                if (icono) icono.className = 'bi bi-sun-fill text-warning me-2';
                if (switchInput) switchInput.checked = true;
            } else {
                if (icono) icono.className = 'bi bi-moon-stars-fill me-2';
                if (switchInput) switchInput.checked = false;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            actualizarIconoTema(localStorage.getItem('theme_mode') || 'light');
        });

        function cerrarSesion() {
            var token = localStorage.getItem('sso_token');

            $.ajax({
                type: "POST",
                url: API_BASE + "/sso/auth/logout.php",
                headers: { "Authorization": "Bearer " + token },
                success: function() {
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location.href = "../auth/login.php";
                },
                error: function() {
                    localStorage.clear();
                    window.location.href = "../auth/login.php";
                }
            });
        }

        // Interceptor global para peticiones AJAX de jQuery
    $(document).ajaxError(function(event, jqXHR, settings, thrownError) {
        // Si la API responde con 401 (No autorizado / Token expirado)
        if (jqXHR.status === 401) {
            // Evitamos bucles si el error ocurre justamente en el login o logout
            if (settings.url.includes('login.php') || settings.url.includes('logout.php')) {
                return;
            }

            // Limpiamos el localStorage del token vencido
            localStorage.removeItem('sso_token');

            // Mostramos una alerta elegante si usas SweetAlert, o un alert común
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Sesión expirada',
                    text: 'Tu sesión ha expirado por inactividad. Por favor, volví a ingresar.',
                    icon: 'warning',
                    confirmButtonText: 'Ir al Login',
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = '../auth/login.php'; // Ajusta la ruta a tu login
                });
            } else {
                alert('Tu sesión ha expirado por inactividad.');
                window.location.href = '../auth/login.php';
            }
        }
    });
    </script>
</head>
<body class="bg-body-tertiary">