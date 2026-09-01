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
                cargarAppsPlantilla();
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
    let permisosSeleccionados = [];
    $('.permiso-checkbox:checked').each(function() {
        permisosSeleccionados.push(parseInt($(this).val()));
    });

    let datos = {
        idaplicacion: $('#idaplicacion').val(),
        nombre: $('#nombre').val(),
        slug: $('#slug').val(),
        url_base: $('#url_base').val(),
        icono: $('#icono').val(), // 👈 Incluye el icono seleccionado
        activo: $('#activo').is(':checked') ? 1 : 0,
        permisos_seleccionados: permisosSeleccionados
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

document.getElementById('app_plantilla').addEventListener('change', function() {
    let idAppModelo = this.value;
    let contenedor = document.getElementById('contenedor-permisos-disponibles');
    let lista = document.getElementById('lista-checkboxes-permisos');
    
    if (!idAppModelo) {
        contenedor.style.display = 'none';
        lista.innerHTML = '';
        return;
    }

    // Petición AJAX para obtener los permisos de la app seleccionada
    fetch(API_BASE + `/sso/aplicaciones/obtener_permisos_app.php?idaplicacion=${idAppModelo}`, {
        method: 'GET',
        headers: {
            "Authorization": "Bearer " + localStorage.getItem('sso_token'),
            "Content-Type": "application/json"
        }
    })
        .then(response => response.json())
        .then(data => {
            lista.innerHTML = '';
            if (data.length === 0) {
                lista.innerHTML = '<span class="text-muted">Esta aplicación no tiene permisos registrados.</span>';
            } else {
                data.forEach(permiso => {
                    let div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = `
                        <input class="form-check-input permiso-checkbox" type="checkbox" name="permisos_seleccionados[]" value="${permiso.idpermiso}" id="perm_${permiso.idpermiso}" checked>
                        <label class="form-check-label" for="perm_${permiso.idpermiso}">
                            <strong>${permiso.clavepermiso}</strong> <span class="text-muted">(${permiso.descripcion || 'Sin descripción'})</span>
                        </label>
                    `;
                    lista.appendChild(div);
                });
            }
            contenedor.style.display = 'block';
        })
        .catch(error => console.error('Error al cargar permisos:', error));
});

// Opcional: Botón para marcar/desmarcar todos rápido
document.getElementById('btn-seleccionar-todos').addEventListener('click', function() {
    let checkboxes = document.querySelectorAll('.permiso-checkbox');
    let todosMarcados = Array.from(checkboxes.every(chk => chk.checked));
    checkboxes.forEach(chk => chk.checked = !todosMarcados);
});

function cargarAppsPlantilla() {
    $.ajax({
        type: "GET",
        url: API_BASE + '/sso/aplicaciones/get_aplicaciones.php',
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === 'ok') {
                let html = '<option value="">-- Seleccionar aplicación de referencia --</option>';
                res.data.forEach(app => {
                    html += `<option value="${app.idaplicacion}">${app.nombre}</option>`;
                });
                $('#app_plantilla').html(html);
            }
        }
    });
}