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
// 3. CARGAR .ENV DE TABLERO
// =========================================================

try {

    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_tablero']);
    $dotenv->load();

} catch (Exception $e) {

    // Si no existe .env, continuamos

}


// =========================================================
// 4. VARIABLES DE CONFIGURACIÓN
// =========================================================

$empresa = $_ENV['APP_NAME'] ?? 'Sistema tablero';

$apiUrl = $_ENV['API_URL'] ?? '/api';

?>
<section class="ftco-section" style="padding-top: 20px;">

    <div class="container-fluid">

        <nav
            class="navbar navbar-expand-lg ftco_navbar ftco-navbar-light"
            id="ftco-navbar"
        >

            <div class="container-fluid">


                <!-- =====================================================
                     LOGO + NOMBRE EMPRESA
                     ===================================================== -->

                <a
                    class="navbar-brand d-flex align-items-center"
                    href="dashboard.php"
                    style="font-weight: bold; color: #337ab7; gap: 15px;"
                >

                    <?php if (file_exists($rutas['logo_tablero'])): ?>

                        <img
                            src="<?= htmlspecialchars($rutas['logo_tablero_web']) ?>"
                            alt="Logo <?= htmlspecialchars($empresa) ?>"
                            class="logo-sistema"
                        >

                    <?php endif; ?>


                    <span id="nombre-empresa-ui">
                        <?= htmlspecialchars($empresa) ?>
                    </span>

                </a>


                <!-- =====================================================
                     BOTÓN MENÚ MOBILE
                     ===================================================== -->

                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#ftco-nav"
                >

                    <span class="fa fa-bars"></span>
                    Menú

                </button>


                <!-- =====================================================
                     MENÚ
                     ===================================================== -->

                <div
                    class="collapse navbar-collapse"
                    id="ftco-nav"
                >

                    <ul class="navbar-nav ms-auto">


                        <!-- INICIO -->

                        <li class="nav-item">

                            <a
                                href="dashboard.php"
                                class="nav-link"
                            >
                                Inicio
                            </a>

                        </li>
                        
                        <li class="nav-item">
                            <a href="reportes.php" class="nav-link">
                                <i class="fa fa-chart-bar"></i> Reportes
                            </a>
                        </li>


                        <!-- =================================================
                             CONFIGURACIÓN
                             ================================================= -->

                        <li
                            class="nav-item dropdown menu-config"
                            style="display: none;"
                        >

                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                data-bs-toggle="dropdown"
                            >

                                <i class="fa fa-cog"></i>
                                Configuración

                            </a>


                            <div class="dropdown-menu">

                                <div class="dropdown-header">
                                    Panel de Control
                                </div>

                            

                                <!-- VARIABLES DEL SISTEMA -->

                                <a
                                    href="configuracion_sistema.php"
                                    class="dropdown-item item-permiso"
                                    data-permiso="sistema_configuracion"
                                    style="display: none;"
                                >

                                    <i class="fas fa-sliders-h"></i>
                                    Variables del Sistema

                                </a>
                                                                
                             
                                <!-- AUDITORÍA -->

                                <div
                                    class="seccion-auditoria-container"
                                    style="display: none;"
                                >

                                    <div
                                        class="dropdown-divider div-auditoria"
                                        style="display: none;"
                                    ></div>


                                    <div
                                        class="dropdown-header text-warning header-auditoria"
                                        style="display: none;"
                                    >

                                        <i class="fas fa-user-secret"></i>
                                        Seguridad

                                    </div>


                                    <a
                                        href="verauditoria.php"
                                        class="dropdown-item font-weight-bold item-permiso"
                                        data-permiso="auditoria_ver"
                                        style="display: none;"
                                    >

                                        <i class="fas fa-fingerprint text-warning"></i>
                                        Log de Auditoría

                                    </a>

                                </div>

                            </div>

                        </li>


                        <!-- =================================================
                             USUARIO
                             ================================================= -->

                        <li class="nav-item dropdown">

                            <a
                                class="nav-link dropdown-toggle"
                                href="#"
                                id="dropUser"
                                data-bs-toggle="dropdown"
                                style="color: #337ab7; font-weight: bold;"
                            >

                                <i class="fa fa-user-circle"></i>

                                <span id="nombre-usuario-ui">
                                    Usuario
                                </span>

                            </a>


                            <div class="dropdown-menu dropdown-menu-end shadow-sm">


                                <!-- VOLVER AL PANEL SSO -->

                                <a
                                    id="btnVolverSso"
                                    href="#"
                                    class="dropdown-item text-primary fw-bold"
                                    style="display: none;"
                                >

                                    <i class="fa fa-th-large me-2"></i>

                                    Cambiar de Aplicación

                                </a>


                                <div
                                    id="divDividerSso"
                                    class="dropdown-divider"
                                    style="display: none;"
                                ></div>


                                <!-- CAMBIAR PASSWORD -->

                                <a
                                    id="linkPasswordSso"
                                    href="#"
                                    class="dropdown-item"
                                >

                                    <i class="fa fa-key me-2"></i>

                                    Mi Password (SSO)

                                </a>


                                <div class="dropdown-divider"></div>


                                <!-- MODO OSCURO -->

                                <div
                                    class="dropdown-item d-flex align-items-center justify-content-between"
                                    onclick="event.stopPropagation();"
                                >

                                    <span>

                                        <i
                                            id="iconoTheme"
                                            class="bi bi-moon-stars-fill me-2"
                                        ></i>

                                        Modo Oscuro

                                    </span>


                                    <div class="form-check form-switch m-0 ms-2">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="checkThemeSwitch"
                                            onchange="toggleModoOscuro();"
                                            style="cursor: pointer;"
                                        >

                                    </div>

                                </div>


                                <div class="dropdown-divider"></div>


                                <!-- CERRAR SESIÓN -->

                                <a
                                    href="javascript:void(0);"
                                    onclick="cerrarSesion();"
                                    class="dropdown-item text-danger"
                                >

                                    <i class="fa fa-sign-out me-2"></i>

                                    Cerrar Sesión

                                </a>

                            </div>

                        </li>

                    </ul>

                </div>

            </div>

        </nav>

    </div>

</section>


<script>

document.addEventListener("DOMContentLoaded", async () => {

    // =========================================================
    // TOKEN
    // =========================================================

    const token =
        localStorage.getItem('sso_token');

    if (!token) {

        window.location.href =
            <?= json_encode($rutas['login_sso_web']) ?>;

        return;

    }


    // =========================================================
    // RUTAS SSO
    // =========================================================

    const urlPanelSso =
        <?= json_encode($rutas['panel_sso_web']) ?>;

    const urlPerfilSso =
        <?= json_encode($rutas['perfil_sso_web']) ?>;


    // =========================================================
    // ELEMENTOS
    // =========================================================

    const btnSso =
        document.getElementById('btnVolverSso');

    const divSso =
        document.getElementById('divDividerSso');

    const linkPass =
        document.getElementById('linkPasswordSso');


    // =========================================================
    // BOTÓN CAMBIAR DE APLICACIÓN
    // =========================================================

    if (btnSso && divSso) {

        btnSso.style.display = 'block';

        divSso.style.display = 'block';

        btnSso.href =
            urlPanelSso +
            '?token=' +
            encodeURIComponent(token);

    }


    // =========================================================
    // CAMBIAR PASSWORD
    // =========================================================

    if (linkPass) {

        linkPass.href =
            urlPerfilSso +
            '?token=' +
            encodeURIComponent(token);

    }


    // =========================================================
    // OBTENER CONTEXTO SSO
    // =========================================================

    try {

        const response =
            await fetch(
                API_BASE + '/sso/auth/me.php',
                {
                    method: 'GET',
                    headers: obtenerHeadersSSO()
                }
            );


        const res =
            await response.json();


        if (
            res.status !== 'ok' ||
            !res.usuario
        ) {

            localStorage.clear();

            window.location.href =
                <?= json_encode($rutas['login_sso_web']) ?>;

            return;

        }


        const user =
            res.usuario;


        // =====================================================
        // NOMBRE USUARIO
        // =====================================================

        const nombreDisplay =
            user.nombreapellido ||
            user.nombre ||
            user.username ||
            user.email ||
            'Usuario';


        const spanUser =
            document.getElementById(
                'nombre-usuario-ui'
            );


        if (spanUser) {

            spanUser.textContent =
                nombreDisplay;

        }


        // =====================================================
        // GUARDAR USUARIO
        // =====================================================

        localStorage.setItem(
            'usuario_actual',
            JSON.stringify(user)
        );


        // =====================================================
        // PERMISOS
        // =====================================================

        const permisos =
            Array.isArray(res.permisos)
                ? res.permisos
                : [];


        MIS_PERMISOS =
            permisos;


        localStorage.setItem(
            'sso_permisos',
            JSON.stringify(permisos)
        );


        /*console.log(
            'Permisos SSO cargados:',
            MIS_PERMISOS
        );*/


        // =====================================================
        // FUNCIÓN DE PERMISOS
        // =====================================================

        function tienePermisoAPI(clave) {

            return Array.isArray(MIS_PERMISOS) &&
                   MIS_PERMISOS.includes(clave);

        }


        // =====================================================
        // ADMINISTRADOR
        // =====================================================

        const esAdmin =
            user.idtipousuario == 99 ||
            user.tipousuario == 99 ||
            user.id == 2;


        // =====================================================
        // MENÚ PRINCIPAL
        // =====================================================

        document
            .querySelectorAll('.menu-item-permiso')
            .forEach(item => {

                const permisoRequerido =
                    item.getAttribute('data-permiso');


                if (
                    esAdmin ||
                    !permisoRequerido ||
                    tienePermisoAPI(permisoRequerido)
                ) {

                    item.style.display = 'block';

                }

            });


        // =====================================================
        // CONFIGURACIÓN
        // =====================================================

        document
            .querySelectorAll('.item-permiso')
            .forEach(item => {

                const permisoRequerido =
                    item.getAttribute('data-permiso');


                if (
                    esAdmin ||
                    !permisoRequerido ||
                    tienePermisoAPI(permisoRequerido)
                ) {

                    item.style.display = 'block';

                }

            });


        // =====================================================
        // MENÚ CONFIGURACIÓN
        // =====================================================

        const verConfig =
            esAdmin ||
            tienePermisoAPI('configuracion_ver') ||
            tienePermisoAPI('sistema_configuracion') ||
            tienePermisoAPI('categorias_gestionar') ||
            tienePermisoAPI('periodos_configurar') ||
            tienePermisoAPI('auditoria_ver');


        if (verConfig) {

            const menuConfig =
                document.querySelector(
                    '.menu-config'
                );


            if (menuConfig) {

                menuConfig.style.display =
                    'block';

            }

        }


        // =====================================================
        // AUDITORÍA
        // =====================================================

        if (
            esAdmin ||
            tienePermisoAPI('auditoria_ver')
        ) {

            const auditContainer =
                document.querySelector(
                    '.seccion-auditoria-container'
                );


            const divAuditoria =
                document.querySelector(
                    '.div-auditoria'
                );


            const headerAuditoria =
                document.querySelector(
                    '.header-auditoria'
                );


            if (auditContainer) {

                auditContainer.style.display =
                    'block';

            }


            if (divAuditoria) {

                divAuditoria.style.display =
                    'block';

            }


            if (headerAuditoria) {

                headerAuditoria.style.display =
                    'block';

            }

        }


    } catch (error) {

        console.error(
            'Error consultando contexto SSO:',
            error
        );

    }

});

</script>