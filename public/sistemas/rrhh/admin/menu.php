<?php
// =========================================================
// MENÚ PRINCIPAL SSO RRHH
// =========================================================
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_rrhh']);
    $dotenv->load();
} catch (Exception $e) {
    // Si no existe .env, continuamos.
}

$empresa = $_ENV['APP_NAME'] ?? 'Sistema RRHH';
?>

<section class="ftco-section" style="padding-top:20px;">
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg ftco_navbar ftco-navbar-light" id="ftco-navbar">
            <div class="container-fluid">

                <a class="navbar-brand d-flex align-items-center"
                   href="dashboard.php"
                   style="font-weight:bold;color:#337ab7;gap:15px;">

                    <?php if (!empty($rutas['logo_rr']) && file_exists($rutas['logo_rr'])): ?>
                        <img src="<?= htmlspecialchars($rutas['logo_rrhh_web']) ?>"
                             alt="Logo <?= htmlspecialchars($empresa) ?>"
                             class="logo-sistema">
                    <?php endif; ?>

                    <span><?= htmlspecialchars($empresa) ?></span>
                </a>

                <button class="navbar-toggler" type="button"
                        data-bs-toggle="collapse" data-bs-target="#ftco-nav"
                        aria-controls="ftco-nav" aria-expanded="false"
                        aria-label="Abrir menú">
                    <span class="fa fa-bars"></span> Menú
                </button>

                <div class="collapse navbar-collapse" id="ftco-nav">
                    <ul class="navbar-nav ms-auto">

                        <!-- INICIO -->
                        <li class="nav-item">
                            <a href="dashboard.php"
                               class="nav-link menu-item-permiso"
                               style="display:none;">
                                Inicio
                            </a>
                        </li>

                        <!-- PERSONAL -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Personal
                            </a>

                            <div class="dropdown-menu">
                                <h6 class="dropdown-header">Administración de personal</h6>

                                <a href="empleados.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="empleados_ver"
                                   style="display:none;">
                                    <span data-diccionario="empleado_plural"></span>
                                </a>

                                <a href="documentos.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="documentos_ver"
                                   style="display:none;">
                                    Documentación
                                </a>

                                <a href="sanciones.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="sanciones_ver"
                                   style="display:none;">
                                    Sanciones
                                </a>
                            </div>
                        </li>

                        <!-- ORGANIZACIÓN -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Organización
                            </a>

                            <div class="dropdown-menu">
                                <h6 class="dropdown-header">Estructura organizacional</h6>

                                <a href="sectores.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="sectores_ver"
                                   style="display:none;">
                                    Sectores
                                </a>

                                <a href="puestos.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="puestos_ver"
                                   style="display:none;">
                                    Puestos
                                </a>
                            </div>
                        </li>

                        <!-- VACACIONES Y LICENCIAS -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Vacaciones y licencias
                            </a>

                            <div class="dropdown-menu">
                                <h6 class="dropdown-header">Administración de ausencias</h6>

                                <a href="solicitudes.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="solicitudes_ver"
                                   style="display:none;">
                                    Solicitudes
                                </a>

                                <a href="vacaciones.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="vacaciones_ver"
                                   style="display:none;">
                                    Vacaciones
                                </a>

                                <a href="tipos_licencia.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="licencias_ver"
                                   style="display:none;">
                                    Tipos de licencia
                                </a>

                                <a href="reglas_vacaciones.php"
                                   class="dropdown-item menu-item-permiso"
                                   data-permiso="vacaciones_ver"
                                   style="display:none;">
                                    Reglas de vacaciones
                                </a>
                            </div>
                        </li>

                        <!-- REPORTES -->
                        <li class="nav-item">
                            <a href="reportes.php"
                               class="nav-link menu-item-permiso"
                               data-permiso="reportes_ver"
                               style="display:none;">
                                Reportes
                            </a>
                        </li>

                        <!-- CONFIGURACIÓN -->
                        <li class="nav-item dropdown menu-config" style="display:none;">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                Configuración
                            </a>

                            <div class="dropdown-menu dropdown-menu-end">
                                <h6 class="dropdown-header">Panel de control</h6>

                                <a href="configuracion_sistema.php"
                                   class="dropdown-item item-permiso"
                                   data-permiso="sistema_configuracion"
                                   style="display:none;">
                                    Variables del sistema
                                </a>

                                <a href="verauditoria.php"
                                   class="dropdown-item item-permiso"
                                   data-permiso="auditoria_ver"
                                   style="display:none;">
                                    <i class="fas fa-fingerprint text-warning me-2"></i>
                                    Log de auditoría
                                </a>
                            </div>
                        </li>

                        <?php require $rutas['menu_usuario']; ?>

                    </ul>
                </div>
            </div>
        </nav>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = <?= json_encode($rutas['login_sso_web']) ?>;
        return;
    }

    const btnSso = document.getElementById('btnVolverSso');
    const divSso = document.getElementById('divDividerSso');

    if (btnSso && divSso) {
        btnSso.style.display = 'block';
        divSso.style.display = 'block';
        btnSso.href = urlPanelSso + '?token=' + encodeURIComponent(token);
    }

    try {
        const response = await fetch(API_BASE + '/sso/auth/me.php', {
            method: 'GET',
            headers: obtenerHeadersSSO()
        });

        const res = await response.json();

        if (res.status !== 'ok' || !res.usuario) {
            localStorage.clear();
            window.location.href = <?= json_encode($rutas['login_sso_web']) ?>;
            return;
        }

        const user = res.usuario;
        const nombre = user.nombreapellido || user.nombre || user.username || user.email || 'Usuario';
        const spanUser = document.getElementById('nombre-usuario-ui');

        if (spanUser) spanUser.textContent = nombre;

        localStorage.setItem('usuario_actual', JSON.stringify(user));

        MIS_PERMISOS = Array.isArray(res.permisos) ? res.permisos : [];
        localStorage.setItem('sso_permisos', JSON.stringify(MIS_PERMISOS));

        const tienePermiso = clave =>
            Array.isArray(MIS_PERMISOS) && MIS_PERMISOS.includes(clave);

        const esAdmin =
            user.idtipousuario == 99 ||
            user.tipousuario == 99 ||
            user.id == 2;

        document.querySelectorAll('.menu-item-permiso, .item-permiso').forEach(item => {
            const permiso = item.getAttribute('data-permiso');

            if (esAdmin || !permiso || tienePermiso(permiso)) {
                item.style.display = item.classList.contains('dropdown-item') ? 'block' : '';
            }
        });

        const verConfig = esAdmin ||
            tienePermiso('configuracion_ver') ||
            tienePermiso('sistema_configuracion') ||
            tienePermiso('auditoria_ver');

        const menuConfig = document.querySelector('.menu-config');
        if (menuConfig && verConfig) menuConfig.style.display = '';

    } catch (error) {
        console.error('Error consultando contexto SSO:', error);
    }
});
</script>