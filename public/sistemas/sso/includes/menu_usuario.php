<!-- NOVEDADES DE INTEGRACIONES -->
<li class="nav-item dropdown" id="menuNovedades" style="display:none;">
    <a class="nav-link position-relative"
       href="#"
       id="btnNovedades"
       data-bs-toggle="dropdown"
       aria-expanded="false"
       title="Novedades">

        <i class="fas fa-bell"></i>

        <span id="contadorNovedades"
              class="badge bg-danger rounded-pill position-absolute"
              style="top:0; right:0; font-size:10px; display:none;">
            0
        </span>
    </a>

    <div class="dropdown-menu dropdown-menu-end shadow"
         style="width:350px; max-height:420px; overflow-y:auto;">

        <h6 class="dropdown-header d-flex justify-content-between">
            Novedades
            <span id="totalNovedades" class="badge bg-primary">0</span>
        </h6>

        <div id="listaNovedades">
            <div class="text-center text-muted p-3">
                Cargando novedades...
            </div>
        </div>

    </div>
</li>
<!-- MENÚ DE USUARIO SSO -->
<li class="nav-item dropdown">
    <a
        class="nav-link dropdown-toggle"
        href="#"
        id="dropUser"
        data-bs-toggle="dropdown"
        style="color: #337ab7; font-weight: bold;"
    >
        <i class="fa fa-user-circle"></i>
        <span id="nombre-usuario-ui">Usuario</span>
    </a>

    <div class="dropdown-menu dropdown-menu-end shadow-sm">

        <a id="linkPanelSso" href="#" class="dropdown-item">
            <i class="fa fa-th-large me-2"></i> Volver al Panel Central
        </a>

        <a id="linkPasswordSso" href="#" class="dropdown-item">
            <i class="fa fa-key me-2"></i> Mi Password (SSO)
        </a>

        <a id="linkPreferenciasSso" href="#" class="dropdown-item">
            <i class="fa fa-sliders-h me-2"></i> Mis Preferencias (SSO)
        </a>

        <div class="dropdown-divider"></div>

        <div
            class="dropdown-item d-flex align-items-center justify-content-between"
            onclick="event.stopPropagation();"
        >
            <span class="small">
                <i id="iconoTheme" class="bi bi-moon-stars-fill me-2"></i>
                Modo Oscuro
            </span>
            <div class="form-check form-switch mb-0 ms-2">
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

        <a
            href="javascript:void(0);"
            onclick="cerrarSesion();"
            class="dropdown-item text-danger"
        >
            <i class="fa fa-sign-out me-2"></i> Cerrar Sesión
        </a>
    </div>
</li>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlPanelSso = <?= json_encode($rutas['panel_sso_web']) ?>;
    const urlPerfilSso = <?= json_encode($rutas['perfil_sso_web']) ?>;
    const token = localStorage.getItem('sso_token') || '';

    const linkPanel = document.getElementById('linkPanelSso');
    const linkPass = document.getElementById('linkPasswordSso');
    const linkPreferencias = document.getElementById('linkPreferenciasSso');

    if (linkPanel) {
        linkPanel.href = urlPanelSso + '?token=' + encodeURIComponent(token);
    }

    if (linkPass) {
        linkPass.href = urlPerfilSso + '?token=' + encodeURIComponent(token);
    }

    if (linkPreferencias) {
        linkPreferencias.href =
            urlPerfilSso.replace('perfil.php', 'preferencias.php') +
            '?token=' + encodeURIComponent(token);
    }

    if (linkPanel) {
        linkPanel.addEventListener('click', function () {
            const clavesApp = [
                'sso_app_activa',
                'sso_integraciones',
            ];

            clavesApp.forEach(clave => {
                localStorage.removeItem(clave);
            });
        });
    }
});

// =========================================================
// CAMPANITA DE NOVEDADES
// =========================================================

document.addEventListener('DOMContentLoaded', () => {

    const menu = document.getElementById('menuNovedades');
    const contador = document.getElementById('contadorNovedades');
    const total = document.getElementById('totalNovedades');
    const lista = document.getElementById('listaNovedades');

    if (!menu || !contador || !lista) return;

    const MAX_NOVEDADES = 100;

    function escaparHTML(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    function obtenerUsuarioActual() {
        try {
            const usuario = JSON.parse(
                localStorage.getItem('usuario_actual') || 'null'
            );

            return Number(usuario?.id || 0);
        } catch {
            return 0;
        }
    }

    async function peticionEvento(url, opciones = {}) {

        const response = await fetch(url, {
            ...opciones,
            headers: {
                ...obtenerHeadersSSO(),
                ...(opciones.headers || {})
            }
        });

        const json = await response.json();

        if (!response.ok || json.status !== 'ok') {
            throw new Error(json.msg || 'No se pudo completar la operación.');
        }

        return json;
    }

    let empleadosPorId = new Map();

    async function cargarEmpleadosIntegracion() {

        const res = await peticionEvento(
            API_BASE + '/fichajes/empleados/empleados.php?action=listar'
        );

        empleadosPorId = new Map(
            (res.data || []).map(empleado => [
                Number(empleado.idempleado),
                `${empleado.apellido}, ${empleado.nombre}`
            ])
        );
    }

    async function cargarNovedades() {

        const app = JSON.parse(
            localStorage.getItem('sso_app_activa') || 'null'
        );

        if (!app || !app.slug) return;

        const integraciones = JSON.parse(
            localStorage.getItem('sso_integraciones') || '[]'
        );

        const nombreApp = (app.nombre || '').toLowerCase();

        const tieneIntegracionActiva = integraciones.some(i =>
            (
                (i.origen || '').toLowerCase() === nombreApp ||
                (i.destino || '').toLowerCase() === nombreApp
            ) &&
            Number(i.activo) === 1
        );

        if (!tieneIntegracionActiva) {
            menu.style.display = 'none';
            return;
        }
        
        try {

            const res = await peticionEvento(
                API_BASE +
                '/sso/integraciones/eventos/listar.php?destino=' +
                encodeURIComponent(app.slug) +
                '&limite=' + MAX_NOVEDADES
            );

            const idUsuario = obtenerUsuarioActual();

            if (app.slug === 'fichajes' && empleadosPorId.size === 0) {
                await cargarEmpleadosIntegracion();
            }

            const eventos = (res.data || []).filter(evento =>
                ['pendiente', 'en_proceso'].includes(evento.estado)
            );

            const accionables = eventos.filter(evento =>
                evento.estado === 'pendiente' ||
                Number(evento.idusuario_asignado) === idUsuario
            );

            menu.style.display = '';

            contador.textContent = accionables.length;
            contador.style.display = accionables.length ? 'inline-block' : 'none';

            total.textContent = eventos.length;

            if (!eventos.length) {
                lista.innerHTML = `
                    <div class="text-center text-muted p-3">
                        <i class="fas fa-check-circle text-success"></i>
                        <br>
                        No hay novedades pendientes.
                    </div>
                `;
                return;
            }

            lista.innerHTML = eventos.map(evento => {

                let acciones = '';

                if (evento.estado === 'pendiente') {

                    acciones = `
                        <button type="button"
                            class="btn btn-sm btn-primary btn-tomar-evento"
                            data-id="${evento.idevento}">
                            Tomar
                        </button>
                    `;

                } else if (
                    Number(evento.idusuario_asignado) === idUsuario &&
                    idUsuario > 0
                ) {

                    acciones = `
                        <button type="button"
                            class="btn btn-sm btn-success btn-resolver-evento"
                            data-id="${evento.idevento}">
                            Resolver
                        </button>
                    `;

                } else {

                    acciones = `
                        <span class="badge bg-info text-dark">
                            En proceso
                        </span>
                    `;
                }

                return `
                    <div class="dropdown-item-text border-bottom py-2">

                        <div class="fw-bold">
                            ${escaparHTML(
                                evento.tipo_evento === 'empleado_creado' && app.slug === 'fichajes'
                                    ? empleadosPorId.get(Number(evento.identidad)) || evento.titulo
                                    : evento.titulo
                            )}
                        </div>

                        <div class="small text-muted">
                            ${escaparHTML(evento.aplicacion_origen)}
                        </div>

                        ${evento.estado === 'en_proceso' ? `
                            <div class="small text-primary mt-1">
                                <i class="fas fa-user"></i>
                                Tomado por: ${escaparHTML(evento.usuario_asignado || 'Usuario desconocido')}
                                ${evento.fecha_asignacion ? ' - ' + escaparHTML(evento.fecha_asignacion) : ''}
                            </div>
                        ` : ''}

                        <div class="small">
                            ${escaparHTML(evento.descripcion || '')}
                        </div>

                        <div class="small text-muted mt-1">
                            ${escaparHTML(evento.fecha_creacion)}
                        </div>

                        <div class="mt-2">
                            ${acciones}
                        </div>

                    </div>
                `;

            }).join('');

        } catch (error) {
            console.error('Error cargando novedades:', error);
        }
    }

    // TOMAR EVENTO
    lista.addEventListener('click', async function(event) {

        const boton = event.target.closest('.btn-tomar-evento');

        if (!boton) return;

        const idEvento = boton.dataset.id;

        const confirmacion = await Swal.fire({
            title: '¿Tomar este evento?',
            text: 'El evento quedará asignado a tu usuario.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Tomar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) return;

        boton.disabled = true;

        try {

            const datos = new FormData();
            datos.append('idevento', idEvento);

            const app = JSON.parse(
                localStorage.getItem('sso_app_activa') || 'null'
            );

            datos.append('destino', app.slug);

            const res = await peticionEvento(
                API_BASE + '/sso/integraciones/eventos/tomar.php',
                {
                    method: 'POST',
                    body: datos
                }
            );

            toast(res.msg, 'success');

            await cargarNovedades();

        } catch (error) {

            toast(error.message, 'error');
            await cargarNovedades();

        }

    });

    // RESOLVER EVENTO
    lista.addEventListener('click', async function(event) {

        const boton = event.target.closest('.btn-resolver-evento');

        if (!boton) return;

        const idEvento = boton.dataset.id;

        const resultado = await Swal.fire({
            title: 'Resolver evento',
            html: `
                <select id="estadoResolucion" class="form-select mb-3">
                    <option value="resuelto">Resuelto</option>
                    <option value="error">Error</option>
                </select>

                <textarea id="resultadoResolucion"
                    class="form-control"
                    rows="4"
                    placeholder="Detalle de la resolución..."
                    required></textarea>
            `,
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {

                const estado = document.getElementById('estadoResolucion').value;
                const detalle = document.getElementById('resultadoResolucion').value.trim();

                if (!detalle) {
                    Swal.showValidationMessage('Ingresá el resultado de la resolución.');
                    return false;
                }

                return {
                    estado,
                    resultado: detalle
                };
            }
        });

        if (!resultado.isConfirmed) return;

        boton.disabled = true;

        try {

            const app = JSON.parse(
                localStorage.getItem('sso_app_activa') || 'null'
            );

            const datos = new FormData();

            datos.append('idevento', idEvento);
            datos.append('destino', app.slug);
            datos.append('estado', resultado.value.estado);
            datos.append('resultado', resultado.value.resultado);

            const res = await peticionEvento(
                API_BASE + '/sso/integraciones/eventos/resolver.php',
                {
                    method: 'POST',
                    body: datos
                }
            );

            toast(res.msg, 'success');

            await cargarNovedades();

        } catch (error) {

            toast(error.message, 'error');
            await cargarNovedades();

        }

    });

    cargarNovedades();

    setInterval(cargarNovedades, 30000);

});
</script>