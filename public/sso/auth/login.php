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
        Login - <?= htmlspecialchars($empresa) ?>
    </title>


    <!-- =====================================================
         BOOTSTRAP
         ===================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
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

        .login-card {
            border-radius: 0.75rem;
            border: 1px solid #dee2e6;
        }

    </style>

</head>


<body class="d-flex align-items-center justify-content-center min-vh-100 py-4">


    <div class="container">

        <div class="row justify-content-center">

            <div class="col-12 col-sm-8 col-md-6 col-lg-4">


                <div class="card login-card shadow-sm p-3 bg-white">

                    <div class="card-body">


                        <!-- =================================================
                             HEADER / LOGO
                             ================================================= -->

                        <div class="text-center mb-4">

                            <div class="logo-container-login mb-3">

                                <?php if (file_exists($rutas['logo_sso'])): ?>

                                    <img
                                        src="<?= htmlspecialchars($rutas['logo_sso_web']) ?>"
                                        alt="Logo"
                                        class="logo-sistema img-fluid"
                                        style="max-height: 80px;"
                                    >

                                <?php endif; ?>

                            </div>


                            <h3 class="fw-bold text-primary mb-0">

                                Login

                                <?php if ($mostrarNombre): ?>

                                    <?= htmlspecialchars($empresa) ?>

                                <?php endif; ?>

                            </h3>

                        </div>


                        <!-- =================================================
                             FORMULARIO
                             ================================================= -->

                        <form id="formLogin">

                            <input
                                type="hidden"
                                name="from_web"
                                value="1"
                            >


                            <!-- USUARIO -->

                            <div class="mb-3">

                                <label
                                    class="form-label text-secondary fw-semibold"
                                    for="username"
                                >
                                    Usuario
                                </label>


                                <div class="input-group">

                                    <span class="input-group-text bg-light text-secondary">

                                        <i class="bi bi-person"></i>

                                    </span>


                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Ingresar usuario"
                                        name="username"
                                        id="username"
                                        required
                                        autocomplete="username"
                                    >

                                </div>

                            </div>


                            <!-- CONTRASEÑA -->

                            <div class="mb-4">

                                <label
                                    class="form-label text-secondary fw-semibold"
                                    for="password"
                                >
                                    Contraseña
                                </label>


                                <div class="input-group">

                                    <span class="input-group-text bg-light text-secondary">

                                        <i class="bi bi-lock"></i>

                                    </span>


                                    <input
                                        type="password"
                                        class="form-control"
                                        name="password"
                                        id="password"
                                        placeholder="Ingresar contraseña"
                                        required
                                        autocomplete="current-password"
                                    >

                                </div>

                            </div>


                            <!-- BOTÓN -->

                            <button
                                type="submit"
                                class="btn btn-primary w-100 py-2 fw-semibold shadow-sm mb-3"
                            >

                                <i class="bi bi-box-arrow-in-right me-1"></i>

                                Ingresar

                            </button>

                        </form>


                        <!-- OLVIDÓ CONTRASEÑA -->

                        <div class="text-center mt-2">

                            <a
                                href="#"
                                class="text-decoration-none small text-muted"
                            >
                                ¿Olvidó su contraseña?
                            </a>

                        </div>


                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SCRIPTS
         ===================================================== -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>

        // =====================================================
        // CONFIGURACIÓN DESDE PHP
        // =====================================================

        const API_BASE = <?= json_encode($apiUrl) ?>;

        const URL_PANEL = <?= json_encode(
            $rutas['panel_sso_web'] ?? '/sso/admin/panel.php'
        ) ?>;


        // =====================================================
        // TOAST
        // =====================================================

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

        };


        // =====================================================
        // LOGIN
        // =====================================================

        $(document).ready(function() {

            $("#formLogin").submit(function(e) {

                e.preventDefault();


                const user = $("#username").val();
                const pass = $("#password").val();


                $.ajax({

                    type: "POST",

                    url: API_BASE + "/sso/auth/login.php",

                    dataType: "json",

                    data: {

                        username: user,
                        password: pass

                    },


                    success: function(data) {

                        console.log(
                            "Respuesta completa del login:",
                            data
                        );


                        if (data.status === "ok") {


                            // =================================================
                            // GUARDAR TOKEN
                            // =================================================

                            localStorage.setItem(
                                'sso_token',
                                data.token
                            );


                            localStorage.setItem(
                                'sso_aplicaciones',
                                JSON.stringify(data.aplicaciones)
                            );


                            // =================================================
                            // REDIRECCIÓN
                            // =================================================

                            window.location.href = URL_PANEL;


                        } else {


                            if (
                                data.msg === "usuario" ||
                                data.msg === "password" ||
                                data.msg === "datos"
                            ) {

                                toast(
                                    "Usuario o contraseña incorrectos",
                                    "error"
                                );


                            } else if (
                                data.msg === "sin_acceso_app"
                            ) {

                                toast(
                                    "El usuario no tiene aplicaciones asignadas",
                                    "error"
                                );


                            } else {

                                toast(
                                    "Error: " + data.msg,
                                    "error"
                                );

                            }

                        }

                    },


                    error: function(xhr) {

                        console.error(
                            xhr.responseText
                        );


                        toast(
                            "Error inesperado en el servidor",
                            "error"
                        );

                    }

                });

            });

        });

    </script>

</body>

</html>
