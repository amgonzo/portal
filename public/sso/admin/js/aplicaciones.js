let permisosUsuario = [];
let tablaApps = null;

$(document).ready(function() {
    // 1. Validar token y obtener permisos directamente del backend antes de cargar nada
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = '../auth/login.php';
        return;
    }

    $.ajax({
        url: API_BASE + '/sso/auth/me.php',
        type: 'GET',
        headers: { "Authorization": "Bearer " + token },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === 'ok' && res.usuario) {
                permisosUsuario = res.permisos || [];

                // 2. Controlar visibilidad del botón de nueva aplicación según permisos
                if (!tienePermiso('apps_crear')) {
                    $('#btnNuevaApp').hide();
                } else {
                    $('#btnNuevaApp').show();
                }

                // 3. Una vez obtenidos los permisos, cargamos las aplicaciones
                cargarAplicaciones();
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

    $('#formApp').on('submit', function(e) {
        e.preventDefault();
        guardarAplicacion();
    });
});

// Función global de permisos para este módulo
function tienePermiso(clave) {
    return Array.isArray(permisosUsuario) && permisosUsuario.includes(clave);
}

function cargarAplicaciones() {
    if ($.fn.DataTable.isDataTable('#tablaAplicaciones')) {
        $('#tablaAplicaciones').DataTable().destroy();
    }

    $.ajax({
        type: "GET",
        url: API_BASE + '/sso/aplicaciones/get_aplicaciones.php',
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === 'ok') {
                let html = '';
                
                const puedeEditar = tienePermiso('apps_editar');

                res.data.forEach(app => {
                    let estadoBadge = app.activo == 1 
                        ? '<span class="badge bg-success">Activa</span>' 
                        : '<span class="badge bg-secondary">Inactiva</span>';

                    let btnEditar = '';
                    if (puedeEditar) {
                        btnEditar = `<button class="btn btn-sm btn-outline-primary me-1" onclick='editarApp(${JSON.stringify(app)})' title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </button>`;
                    }
                    html += `
                    <tr>
                        <td>#${app.idaplicacion}</td>
                        <td class="text-center align-middle"><i class="${app.icono || 'fa-solid fa-cubes'} fa-lg text-primary"></i></td>
                        <td><b>${app.nombre}</b></td>
                        <td><code>${app.slug}</code></td>
                        <td><a href="${app.url_base}" target="_blank" class="small text-decoration-none">${app.url_base} <i class="fa fa-external-link-alt fa-xs"></i></a></td>
                        <td>${estadoBadge}</td>
                        <td class="text-center">
                            ${btnEditar}
                        </td>
                    </tr>`;
                });
                $('#listaAplicaciones').html(html);
                
                tablaApps = $('#tablaAplicaciones').DataTable({
                    "language": {
                        "sProcessing": "Procesando...",
                        "sLengthMenu": "Mostrar _MENU_ registros",
                        "sZeroRecords": "No se encontraron resultados",
                        "sEmptyTable": "Ningún dato disponible en esta tabla",
                        "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sSearch": "Buscar:",
                        "oPaginate": { "sFirst": "Primero", "sLast": "Último", "sNext": "Siguiente", "sPrevious": "Anterior" }
                    }
                });
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                toast("Sesión expirada o token inválido", "error");
                setTimeout(() => { window.location.href = '../auth/login.php'; }, 1500);
            }
        }
    });
}

function abrirModalApp() {
    $('#formApp')[0].reset();
    $('#idaplicacion').val('');
    $('#icono').val('fa-solid fa-cubes'); // Resetea al valor por defecto
    $('#modalAppTitulo').text('Nueva Aplicación');
    $('#activo').prop('checked', true);
    $('#modalApp').modal('show');
}

function editarApp(app) {
    $('#idaplicacion').val(app.idaplicacion);
    $('#nombre').val(app.nombre);
    $('#slug').val(app.slug);
    $('#url_base').val(app.url_base);
    $('#icono').val(app.icono ? app.icono : 'fa-solid fa-cubes'); // Carga el icono actual de la app
    $('#activo').prop('checked', app.activo == 1);
    $('#modalAppTitulo').text('Editar Aplicación');
    $('#modalApp').modal('show');
}

function guardarAplicacion() {
    let datos = {
        idaplicacion: $('#idaplicacion').val(),
        nombre: $('#nombre').val(),
        slug: $('#slug').val(),
        url_base: $('#url_base').val(),
        icono: $('#icono').val(), // 👈 Incluye el icono seleccionado
        activo: $('#activo').is(':checked') ? 1 : 0
    };

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/aplicaciones/guardar_aplicaciones.php",
        data: JSON.stringify(datos),
        contentType: "application/json",
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            let res;
            try {
                res = (typeof response === 'string') ? JSON.parse(response) : response;
            } catch (e) {
                console.error("El servidor no devolvió un JSON válido:", response);
                toast("Error: El servidor respondió con un formato no válido", "error");
                return;
            }

            if (res.status === 'ok') {
                $('#modalApp').modal('hide');
                toast(res.msg || 'Guardado correctamente', 'success');
                cargarAplicaciones();
            } else {
                toast(res.msg || 'Error al guardar', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error("Error AJAX:", xhr.responseText);
            if (xhr.status === 401) {
                toast("Sesión expirada", "error");
                setTimeout(() => { window.location.href = '../auth/login.php'; }, 1500);
            } else {
                toast('Error de conexión con el servidor', 'error');
            }
        }
    });
}