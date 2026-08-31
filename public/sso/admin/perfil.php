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
// 3. CARGAR .ENV DEL SSO
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_sso']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos normalmente

}


// =========================================================
// 4. VARIABLES DE CONFIGURACIÓN
// =========================================================

$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

$apiUrl = $_ENV['API_URL'] ?? '/api';

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <?php include 'header.php'; ?>

    <title>
        Mi Perfil - <?= htmlspecialchars($empresa) ?>
    </title>

</head>


<body>

    <?php include 'menu.php'; ?>


    <!-- =========================================================
         CONTENIDO
         ========================================================= -->

    <div class="container mt-5">

        <div class="row justify-content-center">

            <div class="col-md-5">


                <!-- =================================================
                     CARD CAMBIAR CONTRASEÑA
                     ================================================= -->

                <div class="card shadow-sm">

                    <div class="card-header bg-white border-bottom">

                        <h5 class="mb-0 text-primary">

                            <i class="fa fa-lock"></i>

                            Seguridad de la Cuenta

                        </h5>

                    </div>


                    <div class="card-body">

                        <form id="formPassword">


                            <!-- NUEVA CONTRASEÑA -->

                            <div class="mb-3">

                                <label class="fw-bold mb-1">

                                    Nueva Contraseña

                                </label>

                                <input
                                    type="password"
                                    id="new_pass"
                                    class="form-control"
                                    placeholder="Mínimo 6 caracteres"
                                >

                            </div>


                            <!-- CONFIRMAR CONTRASEÑA -->

                            <div class="mb-3">

                                <label class="fw-bold mb-1">

                                    Confirmar Contraseña

                                </label>

                                <input
                                    type="password"
                                    id="new_pass_confirm"
                                    class="form-control"
                                    placeholder="Repetí la clave"
                                >

                            </div>


                            <hr>


                            <!-- BOTONES -->

                            <div class="d-flex justify-content-between">

                                <!-- VOLVER AL PANEL SSO -->

                                <a
                                    href="#"
                                    id="btnVolverPanel"
                                    class="btn btn-light text-muted"
                                >
                                    <i class="fa fa-chevron-left"></i> Volver
                                </a>


                                <!-- GUARDAR -->

                                <button
                                    type="button"
                                    onclick="actualizarPass()"
                                    class="btn btn-primary px-4"
                                >

                                    <i class="fa fa-save"></i>

                                    Guardar Cambios

                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                <!-- =================================================
                     CARD INFORMACIÓN DEL PERFIL
                     ================================================= -->

                <div class="card shadow-sm mt-4">

                    <div class="card-header bg-white border-bottom">

                        <h5 class="mb-0 text-muted">

                            <i class="fa fa-id-badge"></i>

                            Mi Perfil de Acceso

                        </h5>

                    </div>


                    <div class="card-body">


                        <!-- ROL -->

                        <div class="mb-3">

                            <label class="text-muted small d-block mb-1">

                                Rol Asignado:

                            </label>


                            <span class="badge bg-primary p-2 fs-6 fw-normal">

                                <i class="fa fa-user-shield"></i>

                                <?= htmlspecialchars(
                                    $_SESSION['rol_nombre'] ?? 'Usuario Estándar'
                                ) ?>

                            </span>

                        </div>


                        <!-- PERMISOS -->

                        <label class="text-muted small d-block mb-1">

                            Permisos habilitados:

                        </label>


                        <div class="d-flex flex-wrap gap-1">

                            <?php

                            if (!empty($_SESSION['permisos'])):

                                foreach ($_SESSION['permisos'] as $p):

                                    $nombre_limpio =
                                        ucfirst(
                                            str_replace('_', ' ', $p)
                                        );

                            ?>

                                    <span
                                        class="badge bg-body-secondary text-dark border p-2"
                                    >

                                        <i class="fa fa-check text-success me-1"></i>

                                        <?= htmlspecialchars($nombre_limpio) ?>

                                    </span>

                            <?php

                                endforeach;

                            else:

                            ?>

                                <span class="text-muted fst-italic">

                                    No tenés permisos específicos asignados.

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =========================================================
         TOAST
         ========================================================= -->

    <div
        class="toast-container position-fixed bottom-0 end-0 p-3"
        style="z-index: 1100;"
    >

        <div
            id="liveToast"
            class="toast align-items-center text-white border-0"
            role="alert"
            aria-live="assertive"
            aria-atomic="true"
        >

            <div class="d-flex">

                <div
                    class="toast-body"
                    id="toastMessage"
                ></div>


                <button
                    type="button"
                    class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast"
                    aria-label="Cerrar"
                ></button>

            </div>

        </div>

    </div>


    <!-- =========================================================
         JAVASCRIPT
         ========================================================= -->

    <script>

        // =========================================================
        // RUTAS CENTRALES
        // =========================================================

        const LOGIN_WEB =
            <?= json_encode($rutas['login_web']) ?>;


        const API_BASE =
            <?= json_encode($apiUrl) ?>;


        // =========================================================
        // CAMBIAR CONTRASEÑA
        // =========================================================

        function actualizarPass() {

            const pass =
                $("#new_pass").val();

            const confirmPass =
                $("#new_pass_confirm").val();


            // -----------------------------------------------------
            // VALIDAR CAMPOS
            // -----------------------------------------------------

            if (pass === "") {

                toast(
                    "Ingresá una nueva contraseña",
                    "warning"
                );

                return;
            }


            // -----------------------------------------------------
            // VALIDAR COINCIDENCIA
            // -----------------------------------------------------

            if (pass !== confirmPass) {

                toast(
                    "Las contraseñas no coinciden",
                    "warning"
                );

                return;
            }


            // -----------------------------------------------------
            // VALIDAR LONGITUD
            // -----------------------------------------------------

            if (pass.length < 6) {

                toast(
                    "La clave es muy corta (mín. 6)",
                    "info"
                );

                return;
            }


            // -----------------------------------------------------
            // ENVIAR AL API
            // -----------------------------------------------------

            $.ajax({

                type: "POST",

                url: API_BASE + "/cambiar_clave.php",

                headers: {

                    "Authorization":
                        "Bearer " + TOKEN

                },

                data: {

                    clave: pass

                },


                // ================================================
                // RESPUESTA EXITOSA
                // ================================================

                success: function(res) {

                    if (res.status === "ok") {

                        Swal.fire({

                            title: '¡Clave Cambiada!',

                            text:
                                'Por seguridad, ingresá nuevamente con tu nueva clave.',

                            icon: 'success',

                            confirmButtonColor: '#0d6efd',

                            confirmButtonText: 'Aceptar'

                        }).then(() => {

                            // -------------------------------------
                            // VOLVER AL LOGIN SSO
                            // -------------------------------------

                            localStorage.clear();

                            sessionStorage.clear();

                            window.location.href =
                                LOGIN_WEB;

                        });

                    } else {

                        toast(

                            res.msg ||
                            "Error al cambiar la clave",

                            "error"

                        );

                    }

                },


                // ================================================
                // ERROR
                // ================================================

                error: function(xhr) {

                    if (xhr.status === 401) {

                        localStorage.clear();

                        sessionStorage.clear();

                        window.location.href =
                            LOGIN_WEB;

                    } else {

                        toast(
                            "Error en el servidor",
                            "error"
                        );

                    }

                }

            });

        }

    </script>
<script>

const PANEL_SSO = <?= json_encode($rutas['panel_sso_web']) ?>;

document.addEventListener("DOMContentLoaded", function () {

    const btnVolverPanel = document.getElementById('btnVolverPanel');

    if (btnVolverPanel) {

        btnVolverPanel.addEventListener('click', function (e) {

            e.preventDefault();

            const token = localStorage.getItem('sso_token');

            if (token) {
                window.location.href =
                    PANEL_SSO + '?token=' + encodeURIComponent(token);
            } else {
                window.location.href = PANEL_SSO;
            }

        });

    }

});

</script>
</body>

</html>