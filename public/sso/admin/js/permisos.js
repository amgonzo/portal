let permisosUsuario = [];

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

                // 2. Controlar visibilidad de botones según permisos
                if (!tienePermiso('configuracion_avanzada')) {
                    $('#btnNuevoTipoUsuario, #btnNuevoPermiso, #btnEditarTipoUsuario').hide();
                } else {
                    $('#btnNuevoTipoUsuario, #btnNuevoPermiso, #btnEditarTipoUsuario').show();
                }

                // 3. Cargar aplicaciones iniciales (esto disparará la carga en cascada de forma ordenada)
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

    // Eventos controlados con validación de existencia de valores
    $(document).on("change", "#select_aplicacion", function() {
        const idApp = $(this).val();
        if (idApp) {
            cargarPermisosRol();
        }
    });

    $(document).on("change", "#select_tipo_permiso", function() {
        const idTipo = $(this).val();
        if (idTipo) {
            cargarPermisosRol();
        }
    });
});

// Función global de permisos para este módulo
function tienePermiso(clave) {
    return Array.isArray(permisosUsuario) && permisosUsuario.includes(clave);
}

function cargarAplicaciones() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/aplicaciones/listar_aplicaciones.php",
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if(res.status === "ok") {
                let html = '<option value="">Seleccione una aplicación...</option>';
                res.data.forEach(a => {
                    html += `<option value="${a.idaplicacion}">${a.nombre}</option>`;
                });
                $("#select_aplicacion, #nueva_app").html(html);

                if (res.data.length > 0) {
                    $("#select_aplicacion").val(res.data[0].idaplicacion);
                    cargarTipos();
                }
            }
        }
    });
}

function cargarTipos() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/usuarios/listar_tipos_usuario.php",
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if(res.status === "ok") {
                let html = '<option value="">Seleccione un rol...</option>';
                res.data.forEach(t => {
                    html += `<option value="${t.idtipousuario}">${t.descripcion}</option>`;
                });

                $("#select_tipo_permiso").html(html);

                if (res.data.length > 0) {
                    $("#select_tipo_permiso").val(res.data[0].idtipousuario);
                    cargarPermisosRol();
                }
            }
        }
    });
}

function cargarPermisosRol() {
    const idApp = $("#select_aplicacion").val();
    const idTipo = $("#select_tipo_permiso").val();

    if(!idApp || !idTipo) { 
        $("#contenedor_permisos").hide(); 
        return; 
    }

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/permisos/get_permisos_rol.php",
        data: { idaplicacion: idApp, idtipousuario: idTipo },
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if(res.status === "ok") {
                let html = "";
                let catActual = "";

                if (!res.data.todos || res.data.todos.length === 0) {
                    $("#listaPermisos").html('<tr><td colspan="6" class="text-center text-muted">No hay permisos registrados para esta aplicación.</td></tr>');
                    $("#contenedor_permisos").show();
                    return;
                }

                res.data.todos.sort((a, b) => a.clavepermiso.localeCompare(b.clavepermiso));

                res.data.todos.forEach(p => {
                    let cat = p.clavepermiso.split('_')[0].toUpperCase();
                    
                    if (cat !== catActual) {
                        catActual = cat;
                        html += `
                            <tr class="header-modulo" style="cursor:pointer;" data-modulo="${catActual}">
                                <td colspan="6">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><strong><i class="fas fa-chevron-down mr-3 arrow-icon"></i> MÓDULO: ${catActual}</strong></span>
                                        <small class="text-muted">Total: ${res.data.todos.filter(x => x.clavepermiso.startsWith(cat.toLowerCase())).length}</small>
                                    </div>
                                </td>
                            </tr>`;
                    }

                    let checked = res.data.asignados.includes(parseInt(p.idpermiso)) ? "checked" : "";
                    let endpointText = p.endpoint ? `<code class="text-dark">${p.endpoint}</code>` : '<span class="text-muted">-</span>';
                    
                    // Manejo dinámico del color de la insignia según el verbo HTTP
                    let metodoClase = 'bg-secondary';
                    let m = p.metodo ? p.metodo.toUpperCase() : 'ALL';
                    if (m === 'GET') metodoClase = 'bg-success';
                    else if (m === 'POST') metodoClase = 'bg-primary';
                    else if (m === 'PUT') metodoClase = 'bg-warning text-dark';
                    else if (m === 'DELETE') metodoClase = 'bg-danger';

                    let metodoBadge = `<span class="badge ${metodoClase}">${m}</span>`;

                    // Botón para editar el permiso individual (endpoint, método, clave, etc.)
                    let btnEditar = `<button class="btn btn-sm btn-outline-primary" onclick='abrirModalEditarPermiso(${p.idpermiso}, "${p.clavepermiso}", ${JSON.stringify(p.endpoint)}, "${p.metodo ?? 'ALL'}", ${JSON.stringify(p.descripcion)})'>
                                        <i class="fas fa-edit"></i>
                                     </button>`;

                    html += `
                        <tr class="fila-modulo fila-${catActual}">
                            <td class="pl-4"><strong>${p.clavepermiso}</strong></td>
                            <td>${p.descripcion ?? '-'}</td>
                            <td>${endpointText}</td>
                            <td>${metodoBadge}</td>
                            <td class="text-center">${btnEditar}</td>
                            <td class="text-center">
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input check-permiso" id="p_${p.idpermiso}" value="${p.idpermiso}" ${checked}>
                                    <label class="form-check-label" for="p_${p.idpermiso}"></label>
                                </div>
                            </td>
                        </tr>`;
                });

                $("#listaPermisos").html(html);
                $("#contenedor_permisos").show();

                $(".header-modulo").off("click").on("click", function() {
                    const modulo = $(this).data("modulo");
                    const icono = $(this).find(".arrow-icon");
                    
                    $(".fila-" + modulo).toggle();
                    icono.toggleClass("rotate-icon");
                });
            } else {
                toast("Error al cargar permisos: " + res.msg, "error");
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                toast("Sesión expirada o token inválido", "error");
                setTimeout(() => { window.location.href = '../auth/login.php'; }, 1500);
            } else {
                toast("Error de conexión con el servidor", "error");
            }
        }
    });
}

function guardarPermisos() {
    const idApp = $("#select_aplicacion").val();
    const idTipo = $("#select_tipo_permiso").val();

    if(!idApp || !idTipo){
        toast("Seleccioná aplicación y rol", "warning");
        return;
    }

    let seleccionados = [];
    $(".check-permiso:checked").each(function() {
        seleccionados.push(parseInt($(this).val()));
    });

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/permisos/guardar_permisos.php",
        data: { idaplicacion: idApp, idtipousuario: idTipo, permisos: seleccionados },
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if(res.status === "ok") {
                toast("Permisos actualizados correctamente");
            } else {
                toast("Error: " + res.msg, "error");
            }
        }
    });
}

function crearPermisoBase() {
    const idApp = $("#nueva_app").val(); // Asegúrate de capturar la aplicación del modal
    const clave = $("#nueva_clave").val();
    const endpoint = $("#nuevo_endpoint").val();
    const metodo = $("#nuevo_metodo").val();
    const desc = $("#nueva_desc").val();

    if (!idApp || !clave) {
        toast("La aplicación y la clave del permiso son obligatorias", 'error');
        return;
    }

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/permisos/crear_permiso.php",
        data: {
            idaplicacion: idApp, // 👈 Obligatorio por el nuevo esquema
            clave: clave,
            endpoint: endpoint,
            metodo: metodo,
            descripcion: desc
        },
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === "ok") {
                $("#modalNuevoPermiso").modal('hide');
                $("#nueva_clave, #nuevo_endpoint, #nuevo_desc").val('');
                $("#nuevo_metodo").val('ALL');
                toast("Permiso creado correctamente");
                cargarPermisosRol();
            } else {
                toast(res.msg, 'error');
            }
        },
        error: function() {
            toast("Error de conexión con el servidor", 'error');
        }
    });
}

function crearNuevoTipoUsuario() {
    let nombre = $("#nuevo_rol_nombre").val();

    if (!nombre) {
        toast("El nombre del tipo de usuario es obligatorio", "warning");
        return;
    }

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/usuarios/crear_tipo_usuario.php",
        data: { nombre: nombre },
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') },
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === "ok") {
                toast("Tipo de usuario creado correctamente");
                $("#modalNuevoRol").modal("hide");
                $("#nuevo_rol_nombre").val("");
                cargarTipos(); 
            } else {
                toast("Error: " + res.msg, "error");
            }
        }
    });
}

// Funciones para Editar un Permiso existente (Endpoint, Método, Clave, Descripción)
function abrirModalEditarPermiso(id, clave, endpoint, metodo, desc) {
    $("#edit_idpermiso").val(id);
    $("#edit_clave").val(clave);
    $("#edit_endpoint").val(endpoint === null ? '' : endpoint);
    $("#edit_metodo").val(metodo);
    $("#edit_desc").val(desc === null ? '' : desc);
    
    $("#modalEditarPermiso").modal("show");
}

function actualizarPermisoBase() {
    const id = $("#edit_idpermiso").val();
    const clave = $("#edit_clave").val();
    const endpoint = $("#edit_endpoint").val();
    const metodo = $("#edit_metodo").val();
    const desc = $("#edit_desc").val();

    if (!id || !clave) {
        toast("La clave es obligatoria", "warning");
        return;
    }

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/permisos/editar_permisos.php",
        data: {
            idpermiso: id,
            clave: clave,
            endpoint: endpoint,
            metodo: metodo,
            descripcion: desc
        },
        headers: { "Authorization": "Bearer " + localStorage.getItem('sso_token') }, // 👈 Acá estaba el faltante
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === "ok") {
                toast("Permiso actualizado correctamente");
                $("#modalEditarPermiso").modal("hide");
                cargarPermisosRol();
            } else {
                toast("Error: " + res.msg, "error");
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                toast("Sesión expirada o token inválido", "error");
                setTimeout(() => { window.location.href = '../auth/login.php'; }, 1500);
            } else {
                toast("Error de conexión con el servidor", "error");
            }
        }
    });
}