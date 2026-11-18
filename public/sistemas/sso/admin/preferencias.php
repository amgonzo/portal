<?php
// =========================================================
// 1. CARGAR RUTAS CENTRALES
// =========================================================
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/sistemas/api/config/rutas.php';

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
    // Si no existe .env, continuamos normalmente.
}

// =========================================================
// 4. VARIABLES DE CONFIGURACIÓN
// =========================================================
$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';
$apiUrl = $_ENV['API_URL'] ?? '/sistemasapi';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Mis Preferencias - <?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-primary">
                            <i class="fa fa-sliders-h me-2"></i>Mis Preferencias
                        </h5>
                    </div>

                    <div class="card-body">
                        <form id="formPreferencias">
                            <div class="mb-4">
                                <label for="registros_por_pagina" class="form-label fw-bold">
                                    Registros por página
                                </label>
                                <select id="registros_por_pagina" name="registros_por_pagina" class="form-select">
                                    <option value="10">10 registros</option>
                                    <option value="25">25 registros</option>
                                    <option value="50">50 registros</option>
                                    <option value="100">100 registros</option>
                                </select>
                                <div class="form-text">Se utilizará como valor predeterminado en las tablas de los sistemas.</div>
                            </div>

                            <div class="mb-4">
                                <label for="tema" class="form-label fw-bold">Tema predeterminado</label>
                                <select id="tema" name="tema" class="form-select">
                                    <option value="claro">Claro</option>
                                    <option value="oscuro">Oscuro</option>
                                </select>
                                <div class="form-text">Se aplicará en los sistemas que tengan integrado el tema global.</div>
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="#" id="btnVolverPanel" class="btn btn-light text-muted">
                                    <i class="fa fa-chevron-left"></i> Volver
                                </a>
                                <button type="submit" id="btnGuardarPreferencias" class="btn btn-primary px-4">
                                    <i class="fa fa-save me-1"></i> Guardar preferencias
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-3 small">
                    Las preferencias se guardan en el SSO y se comparten entre los sistemas vinculados a tu usuario.
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
        <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
        </div>
    </div>

    <script>
        const LOGIN_WEB = <?= json_encode($rutas['login_web']) ?>;
        const PANEL_SSO = <?= json_encode($rutas['panel_sso_web']) ?>;
        //const API_BASE = <?= json_encode($apiUrl) ?>;

        // Este endpoint debe implementarse en la API central del SSO.
        const API_PREFERENCIAS = API_BASE + '/sso/auth/preferencias_usuario.php';

        function mostrarToast(mensaje, tipo = 'success') {
            const toastEl = document.getElementById('liveToast');
            const mensajeEl = document.getElementById('toastMessage');
            if (!toastEl || !mensajeEl || typeof bootstrap === 'undefined') {
                alert(mensaje);
                return;
            }
            toastEl.className = `toast align-items-center text-white border-0 bg-${tipo === 'error' ? 'danger' : tipo}`;
            mensajeEl.textContent = mensaje;
            bootstrap.Toast.getOrCreateInstance(toastEl).show();
        }

        function manejarNoAutorizado(xhr) {
            if (xhr && xhr.status === 401) {
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = LOGIN_WEB;
                return true;
            }
            return false;
        }

        function cargarPreferencias() {
            $.ajax({
                type: 'GET',
                url: API_PREFERENCIAS + '?action=obtener',
                headers: { 'Authorization': 'Bearer ' + TOKEN },
                dataType: 'json',
                success: function (res) {
                    if (res.status !== 'ok') {
                        mostrarToast(res.msg || 'No se pudieron cargar las preferencias.', 'error');
                        return;
                    }
                    const datos = res.data || res;
                    const cantidad = String(datos.registros_por_pagina || 10);
                    const tema = datos.tema || 'claro';
                    $('#registros_por_pagina').val(['10', '25', '50', '100'].includes(cantidad) ? cantidad : '10');
                    $('#tema').val(['claro', 'oscuro'].includes(tema) ? tema : 'claro');
                },
                error: function (xhr) {
                    if (!manejarNoAutorizado(xhr)) {
                        mostrarToast('Error al consultar las preferencias.', 'error');
                    }
                }
            });
        }

        $('#formPreferencias').on('submit', function (e) {
            e.preventDefault();

            const datos = {
                registros_por_pagina: Number($('#registros_por_pagina').val()),
                tema: $('#tema').val()
            };

            if (![10, 25, 50, 100].includes(datos.registros_por_pagina) || !['claro', 'oscuro'].includes(datos.tema)) {
                mostrarToast('Los valores seleccionados no son válidos.', 'warning');
                return;
            }

            const boton = $('#btnGuardarPreferencias');
            const textoOriginal = boton.html();
            boton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Guardando...');

            $.ajax({
                type: 'POST',
                url: API_PREFERENCIAS + '?action=guardar',
                headers: {
                    'Authorization': 'Bearer ' + TOKEN,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify(datos),
                dataType: 'json',
                success: function (res) {
                    if (res.status !== 'ok') {
                        mostrarToast(res.msg || 'No se pudieron guardar las preferencias.', 'error');
                        return;
                    }
                    mostrarToast(res.msg || 'Preferencias guardadas correctamente.', 'success');
                    // El tema se aplicará globalmente cuando los sistemas lean esta preferencia al iniciar.
                    actualizarPreferenciasLocalStorage(datos);
                },
                error: function (xhr) {
                    if (!manejarNoAutorizado(xhr)) {
                        mostrarToast('Error en el servidor al guardar las preferencias.', 'error');
                    }
                },
                complete: function () {
                    boton.prop('disabled', false).html(textoOriginal);
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            cargarPreferencias();

            const btnVolver = document.getElementById('btnVolverPanel');
            if (btnVolver) {
                btnVolver.addEventListener('click', function (e) {
                    e.preventDefault();
                    const token = localStorage.getItem('sso_token');
                    window.location.href = token
                        ? PANEL_SSO + '?token=' + encodeURIComponent(token)
                        : PANEL_SSO;
                });
            }
        });
    </script>
</body>
</html>
