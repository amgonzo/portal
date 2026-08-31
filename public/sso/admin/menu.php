<section class="ftco-section" style="padding-top: 20px;">
    <div class="container-fluid"> 
        <nav class="navbar navbar-expand-lg ftco_navbar ftco-navbar-light" id="ftco-navbar">
            <div class="container-fluid">
                <a class="navbar-brand d-flex align-items-center" href="panel.php" style="font-weight: bold; color: #337ab7; gap: 15px;">
                    <i class="fa-solid fa-shield-halved fs-4"></i>
                    <span><?PHP echo htmlspecialchars($empresa); ?></span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ftco-nav">
                    <span class="fa fa-bars"></span> Menú
                </button>

                <div class="collapse navbar-collapse" id="ftco-nav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a href="panel.php" class="nav-link">Inicio</a></li>
                        
                        <!-- CONFIGURACIÓN (Controlada por JS según permisos locales) -->
                        <li class="nav-item dropdown menu-config" style="display: none;">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fa fa-cog"></i> Configuración
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-header">Panel de Control</div>
                                
                                <a href="usuarios.php" class="dropdown-item item-permiso" data-permiso="usuarios_ver" style="display: none;">
                                    <i class="fa fa-users"></i> Gestión Usuarios
                                </a>

                                <a href="permisos.php" class="dropdown-item item-permiso" data-permiso="roles_editar" style="display: none;">
                                    <i class="fas fa-key"></i> Configurar Permisos
                                </a>

                                <a href="aplicaciones.php" class="dropdown-item item-permiso" data-permiso="apps_ver" style="display: none;">
                                    <i class="fas fa-cubes"></i> Registrar Aplicaciones
                                </a>

                                <div class="dropdown-divider div-auditoria" style="display: none;"></div>
                                <div class="dropdown-header text-warning header-auditoria" style="display: none;"><i class="fas fa-user-secret"></i> Seguridad</div>
                                <a href="verauditoria.php" class="dropdown-item font-weight-bold item-permiso" data-permiso="auditoria_ver" style="display: none;">
                                    <i class="fas fa-fingerprint text-warning"></i> Log de Auditoría
                                </a>
                            </div>
                        </li>

                        <!-- USUARIO -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="dropUser" data-bs-toggle="dropdown" style="color: #337ab7; font-weight: bold;">
                                <i class="fa fa-user-circle"></i>
                                <span id="navNombreUsuario">Usuario</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow-sm">
                                <a href="perfil.php" class="dropdown-item">
                                    <i class="fa fa-key me-2"></i> Mi Password
                                </a>

                                <div class="dropdown-divider"></div>

                                <!-- MODO OSCURO DENTRO DEL MENÚ -->
                                <div class="dropdown-item d-flex align-items-center justify-content-between" onclick="event.stopPropagation();">
                                    <span class="small"><i id="iconoTheme" class="bi bi-moon-stars-fill me-2"></i> Modo Oscuro</span>
                                    <div class="form-check form-switch mb-0 ms-2">
                                        <input class="form-check-input" type="checkbox" id="checkThemeSwitch" onchange="toggleModoOscuro();" style="cursor: pointer;">
                                    </div>
                                </div>

                                <div class="dropdown-divider"></div>

                                <a href="javascript:void(0);" onclick="cerrarSesion();" class="dropdown-item text-danger">
                                    <i class="fa fa-sign-out me-2"></i> Cerrar Sesión
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
    //const API_BASE = "<?php echo $apiUrl; ?>";
    
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Validar que exista el token
        const token = localStorage.getItem('sso_token');
        if (!token) {
            window.location.href = '../auth/login.php';
            return;
        }

        // 2. Consultar de forma segura al endpoint me.php que ya probamos que funciona
        $.ajax({
            url: API_BASE + '/sso/auth/me.php',
            type: 'GET',
            headers: { "Authorization": "Bearer " + token },
            success: function(response) {
                if (response.status === 'ok' && response.usuario) {
                    // Pintar el nombre real que devuelve la base de datos
                    const spanUser = document.getElementById('navNombreUsuario');
                    if (spanUser) spanUser.textContent = response.usuario.nombre || 'Usuario';

                    // Obtener los permisos seguros directamente de la respuesta de la API
                    const permisos = response.permisos || [];

                    function tienePermisoAPI(clave) {
                        return Array.isArray(permisos) && permisos.includes(clave);
                    }

                    // Evaluar si muestra el menú de Configuración
                    let verConfig = tienePermisoAPI('configuracion_ver') || 
                                    tienePermisoAPI('usuarios_ver') || 
                                    tienePermisoAPI('roles_editar') || 
                                    tienePermisoAPI('apps_ver') || 
                                    tienePermisoAPI('auditoria_ver');

                    if (verConfig) {
                        const menuConfig = document.querySelector('.menu-config');
                        if (menuConfig) menuConfig.style.display = 'block';
                    }

                    // Evaluar ítem por ítem según los permisos de la API
                    document.querySelectorAll('.item-permiso').forEach(item => {
                        let permisoRequerido = item.getAttribute('data-permiso');
                        if (tienePermisoAPI(permisoRequerido)) {
                            item.style.display = 'block';
                        }
                    });

                    // Evaluar sección de auditoría
                    if (tienePermisoAPI('auditoria_ver')) {
                        const divAuditoria = document.querySelector('.div-auditoria');
                        const headerAuditoria = document.querySelector('.header-auditoria');
                        if (divAuditoria) divAuditoria.style.display = 'block';
                        if (headerAuditoria) headerAuditoria.style.display = 'block';
                    }

                } else {
                    localStorage.clear();
                    window.location.href = '../auth/login.php';
                }
            },
            error: function() {
                localStorage.clear();
                window.location.href = '../auth/login.php';
            }
        });
    });
</script>