<?php
// Ruta directa y simple (asumiendo que tu estructura siempre respeta la raíz del sitio)
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// Carga directa del .env y autoload desde la config central si la necesitás
require_once $rutas['autoload'];

require_once $rutas['public'] . '/api/utils/helpers.php';

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_fichajes']);
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $empresa; ?></title>
    
    <!-- 🛡️ Guardián de Token en el Cliente -->
    <script>
        if (!localStorage.getItem('sso_token')) {
            window.location.href = '../index.php';
        }
                function obtenerEmpresaActiva() {
            try {
                return JSON.parse(
                    localStorage.getItem('sso_empresa_activa') || 'null'
                );
            } catch (e) {
                return null;
            }
        }

        function obtenerHeadersSSO() {
            const token = localStorage.getItem('sso_token');
            const empresa = obtenerEmpresaActiva();

            const headers = {
                "Authorization": "Bearer " + (token || "")
            };

            if (empresa && empresa.idempresa) {
                headers["X-EMPRESA-ID"] = String(empresa.idempresa);
            }

            return headers;
        }
    </script>

    <!-- Variables y Helpers Globales -->
    <script>
        const TOKEN = localStorage.getItem('sso_token') || '';
        const MIS_PERMISOS = JSON.parse(
            localStorage.getItem('sso_permisos') || '[]'
        );
        window.APP_NAME = <?php echo json_encode($empresa); ?>;
        window.API_BASE = <?php echo json_encode($apiUrl); ?>;
        // ============================================================
        // DICCIONARIO DE LA EMPRESA
        // ============================================================
        const empresaActiva = obtenerEmpresaActiva();
        const empresaActual = empresaActiva?.idempresa || '';

        let datosDiccionario = null;

        try {
            datosDiccionario = JSON.parse(
                localStorage.getItem('app_diccionario') || 'null'
            );
        } catch (e) {
            datosDiccionario = null;
        }

        // ------------------------------------------------------------
        // Si existe diccionario y pertenece a la empresa actual,
        // lo usamos directamente.
        // ------------------------------------------------------------
        if (
            datosDiccionario &&
            String(datosDiccionario.idempresa) === String(empresaActual)
        ) {
            window.APP_DICCIONARIO = datosDiccionario.diccionario || {};
        } else {

            window.APP_DICCIONARIO = {};

            // --------------------------------------------------------
            // Primera carga o cambio de empresa
            // --------------------------------------------------------
            if (TOKEN) {

                fetch(
                    API_BASE + '/config/diccionario.php',
                    {
                        method: 'GET',
                        headers: obtenerHeadersSSO()
                    }
                )
                .then(response => {

                    if (!response.ok) {
                        throw new Error(
                            'No se pudo cargar el diccionario'
                        );
                    }

                    return response.json();
                })
                .then(data => {

                    if (data.status !== 'ok') {
                        return;
                    }

                    window.APP_DICCIONARIO =
                        data.diccionario || {};

                    localStorage.setItem(
                        'app_diccionario',
                        JSON.stringify({
                            idempresa: data.idempresa,
                            diccionario: data.diccionario || {}
                        })
                    );

                    aplicarDiccionario();
                })
                .catch(error => {

                    console.error(
                        'Error cargando diccionario:',
                        error
                    );

                });
            }
        }
        // ============================================================
        // FUNCIÓN PARA USAR EL DICCIONARIO
        // ============================================================
        function diccionario(clave, defecto = '') {
            return Object.prototype.hasOwnProperty.call(
                APP_DICCIONARIO,
                clave
            )
                ? APP_DICCIONARIO[clave]
                : defecto;
        }
        function aplicarDiccionario() {
            document.querySelectorAll('[data-diccionario]').forEach(elemento => {
                const clave = elemento.dataset.diccionario;

                elemento.textContent = diccionario(clave, '');
            });
        }
        function aplicarDiccionarioTexto(texto) {
            if (!texto) return texto;

            return texto
                .replace(/\bEmpleados\b/g, diccionario('empleado_plural', 'Empleados'))
                .replace(/\bEmpleado\b/g, diccionario('empleado_singular', 'Empleado'));
        }
        // ============================================================
        // PERMISOS
        // ============================================================
        function tienePermiso(clave) {
            return Array.isArray(MIS_PERMISOS)
                && MIS_PERMISOS.includes(clave);
        }
    </script>

    <!-- 1. Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:ital,wght@0,300;0,400;0,700;1,300&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- 2. Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- 3. Frameworks y Plugins CSS (Vía CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.3/dist/flatly/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <link rel="stylesheet" href="<?php echo versionar('logo_sistema.css'); ?>">
    
    <link rel="icon" href="/favicon.ico" type="image/x-icon">

    <!-- 4. Estilos Propios -->
    <link rel="stylesheet" href="<?php echo versionar('css/boostrap5a4.css'); ?>">

    <!-- Librerías JS Base (Vía CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.4/jquery-confirm.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>

    <script>
        const ID_USUARIO_LOGUEADO = localStorage.getItem('sso_idusuario') || 1;

        const toast = (mensaje, icono = 'success') => {
            Swal.mixin({
                toast: true,
                position: 'bottom-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            }).fire({
                icon: icono,
                title: mensaje
            });
        }

        function cerrarSesion() {
            var token = localStorage.getItem('sso_token');

            $.ajax({
                type: "POST",
                url: API_BASE + "/sso/auth/logout.php",
                headers: {
                    "Authorization": "Bearer " + token
                },
                success: function() {
                    localStorage.clear();
                    sessionStorage.clear();
                    window.location.href = "../index.php";
                },
                error: function() {
                    localStorage.clear();
                    window.location.href = "../index.php";
                }
            });
        }

        // Interceptor global para peticiones AJAX de jQuery (manejo de 401 unificado)
        $(document).ajaxError(function(event, jqXHR, settings) {
            if (jqXHR.status === 401) {
                if (settings.url.includes('login.php') || settings.url.includes('logout.php')) {
                    return;
                }
                localStorage.removeItem('sso_token');
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Sesión expirada',
                        text: 'Tu sesión ha expirado. Por favor, volví a ingresar.',
                        icon: 'warning',
                        confirmButtonText: 'Ir al Login',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '../index.php';
                    });
                } else {
                    window.location.href = '../index.php';
                }
            }
        });
    </script>

    <!-- 🌙 CONTROL GLOBAL DE MODO OSCURO -->
    <script>
        (function() {
            const temaGuardado = localStorage.getItem('theme_mode') || 'light';
            document.documentElement.setAttribute('data-bs-theme', temaGuardado);
        })();

        function toggleModoOscuro() {
            const html = document.documentElement;
            const temaActual = html.getAttribute('data-bs-theme') || 'light';
            const nuevoTema = (temaActual === 'dark') ? 'light' : 'dark';
            
            html.setAttribute('data-bs-theme', nuevoTema);
            localStorage.setItem('theme_mode', nuevoTema);
            actualizarIconoTema(nuevoTema);
            return nuevoTema;
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
            const temaActual = localStorage.getItem('theme_mode') || 'light';
            actualizarIconoTema(temaActual);
            aplicarDiccionario();
        });
    </script>
</head>
<body class="bg-body-tertiary">