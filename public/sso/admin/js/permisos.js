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
        headers: obtenerHeadersSSO(),
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if (res.status === 'ok' && res.usuario) {
                permisosUsuario = res.permisos || [];

                // 2. Controlar visibilidad de botones según permisos
                if (!tienePermiso('configuracion_avanzada')) {
                    $('#btnNuevoTipoUsuario, #btnNuevoPermiso, #btnAsociarPermiso, #btnEditarTipoUsuario').hide();
                } else {
                    $('#btnNuevoTipoUsuario, #btnNuevoPermiso, #btnAsociarPermiso, #btnEditarTipoUsuario').show();
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

   $(document).on("show.bs.modal", "#modalAsociarPermiso", function() {
        cargarPermisosParaAsociar();
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
        headers: obtenerHeadersSSO(),
        success: function(response) {
            const res = (typeof response === 'string') ? JSON.parse(response) : response;
            if(res.status === "ok") {
                let html = '<option value="">Seleccione una aplicación...</option>';
                res.data.forEach(a => {
                    html += `<option value="${a.idaplicacion}">${a.nombre}</option>`;
                });
                
                // 👈 Poblamos tanto el buscador principal, el modal nuevo y el modal editar
                $("#select_aplicacion, #asociar_app").html(html);
                
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
        headers: obtenerHeadersSSO(),
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
        headers: obtenerHeadersSSO(),
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
        headers: obtenerHeadersSSO(),
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

    const clave = $("#nueva_clave").val().trim();
    const endpoint = $("#nuevo_endpoint").val().trim();
    const metodo = $("#nuevo_metodo").val();
    const desc = $("#nueva_desc").val().trim();

    if (!clave) {
        toast("La clave del permiso es obligatoria", "warning");
        return;
    }

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/permisos/crear_permiso.php",
        data: {
            idaplicacion: $("#select_aplicacion").val(),
            clave: clave,
            endpoint: endpoint,
            metodo: metodo,
            descripcion: desc
        },
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status === "ok") {

                $("#modalNuevoPermiso").modal("hide");

                $("#nueva_clave, #nuevo_endpoint, #nueva_desc").val("");
                $("#nuevo_metodo").val("ALL");

                toast("Permiso creado correctamente");

                cargarPermisosRol();

            } else {
                toast(res.msg, "error");
            }
        },

        error: function() {
            toast("Error de conexión con el servidor", "error");
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
        headers: obtenerHeadersSSO(),
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
// Actualizar la función que abre el modal para incluir la app actual
// Función para abrir el modal de edición cargando la app actual y bloqueándola para que no se cambie por error
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
    const clave = $("#edit_clave").val().trim();
    const endpoint = $("#edit_endpoint").val().trim();
    const metodo = $("#edit_metodo").val();
    const desc = $("#edit_desc").val().trim();

    if (!id || !clave) {
        toast("La clave del permiso es obligatoria", "warning");
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
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status === "ok") {

                toast("Permiso actualizado correctamente");

                $("#modalEditarPermiso").modal("hide");

                cargarPermisosRol();

            } else {
                toast("Error: " + res.msg, "error");
            }
        },

        error: function() {
            toast("Error de conexión con el servidor", "error");
        }
    });
}

function cargarPermisosParaAsociar() {

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/permisos/listar_permisos.php",
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status !== "ok") {
                toast(res.msg || "Error al cargar permisos", "error");
                return;
            }

            let html = '<option value="">Seleccione un permiso...</option>';

            res.data.forEach(p => {

                html += `
                    <option value="${p.idpermiso}">
                        ${p.clavepermiso} - ${p.descripcion || ''}
                    </option>
                `;
            });

            $("#asociar_permiso").html(html);
        },

        error: function() {
            toast("Error al cargar los permisos", "error");
        }
    });
}

$(document).on("change", "#asociar_permiso", function() {

    const idPermiso = $(this).val();
    cargarAplicacionesDelPermiso(idPermiso);

    if (!idPermiso) {
        $("#infoPermisoAsociar").hide().html("");
        return;
    }

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/permisos/listar_permisos.php",
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status !== "ok") {
                return;
            }

            const permiso = res.data.find(
                p => parseInt(p.idpermiso) === parseInt(idPermiso)
            );

            if (!permiso) {
                return;
            }

            const endpoint = permiso.endpoint
                ? `<code>${permiso.endpoint}</code>`
                : '-';

            const metodo = permiso.metodo || 'ALL';

            $("#infoPermisoAsociar")
                .html(`
                    <strong>${permiso.clavepermiso}</strong><br>
                    ${permiso.descripcion || ''}<br>
                    Endpoint: ${endpoint}<br>
                    Método: <strong>${metodo}</strong>
                `)
                .show();
        }
    });
});

function asociarPermisoAplicacion() {

    const idPermiso = $("#asociar_permiso").val();
    const idAplicacion = $("#asociar_app").val();

    if (!idPermiso || !idAplicacion) {
        toast("Seleccioná el permiso y la aplicación destino", "warning");
        return;
    }

    $.ajax({
        type: "POST",
        url: API_BASE + "/sso/permisos/agregar_permiso_aplicacion.php",
        data: {
            idpermiso: idPermiso,
            idaplicacion: idAplicacion
        },
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status === "ok") {

                $("#modalAsociarPermiso").modal("hide");

                $("#asociar_permiso").val("");
                $("#infoPermisoAsociar").hide().html("");

                toast("Permiso asociado correctamente");

                // Si la aplicación destino es la que estamos viendo,
                // actualizamos la lista inmediatamente.
                if (String($("#select_aplicacion").val()) === String(idAplicacion)) {
                    cargarPermisosRol();
                }

            } else {
                toast(res.msg || "No se pudo asociar el permiso", "error");
            }
        },

        error: function(xhr) {

            console.error("Error al asociar permiso:", xhr.responseText);

            if (xhr.status === 401) {
                toast("Sesión expirada", "error");
                setTimeout(() => {
                    window.location.href = '../auth/login.php';
                }, 1500);
            } else {
                toast("Error de conexión con el servidor", "error");
            }
        }
    });
}

function cargarAplicacionesDelPermiso(idPermiso) {

    $("#aplicacionesPermisoAsociar").hide();
    $("#listaAplicacionesPermiso").html("");

    if (!idPermiso) {
        return;
    }

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/permisos/listar_aplicaciones_permiso.php",
        data: {
            idpermiso: idPermiso
        },
        headers: obtenerHeadersSSO(),

        success: function(response) {

            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status !== "ok") {
                toast(
                    res.msg || "Error al consultar las aplicaciones",
                    "error"
                );
                return;
            }

            if (!res.data || res.data.length === 0) {
                $("#listaAplicacionesPermiso").html(`
                    <div class="alert alert-light border mb-0">
                        Este permiso no está asociado a ninguna aplicación.
                    </div>
                `);

                $("#aplicacionesPermisoAsociar").show();
                return;
            }

            let html = "";

            res.data.forEach(app => {

                html += `
                    <div class="list-group-item d-flex justify-content-between align-items-center">

                        <div>
                            <strong>${app.nombre}</strong>
                            <br>
                            <small class="text-muted">${app.slug}</small>
                        </div>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            onclick="desasociarPermisoAplicacion(${idPermiso}, ${app.idaplicacion}, '${String(app.nombre).replace(/'/g, "\\'")}')"
                        >
                            <i class="fas fa-unlink"></i>
                            Desasociar
                        </button>

                    </div>
                `;
            });

            $("#listaAplicacionesPermiso").html(html);
            $("#aplicacionesPermisoAsociar").show();
        },

        error: function(xhr) {

            console.error(
                "Error al consultar aplicaciones del permiso:",
                xhr.responseText
            );

            toast(
                "Error de conexión al consultar las aplicaciones",
                "error"
            );
        }
    });
}

function desasociarPermisoAplicacion(idPermiso, idAplicacion, nombreAplicacion) {

    if (!idPermiso || !idAplicacion) {
        return;
    }

    Swal.fire({
        title: '¿Está seguro?',
        text: `Desea desasociar este permiso de la aplicación "${nombreAplicacion}".`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, desasociar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            type: "POST",
            url: API_BASE + "/sso/permisos/desasociar_permiso_aplicacion.php",

            data: {
                idpermiso: idPermiso,
                idaplicacion: idAplicacion
            },

            headers: obtenerHeadersSSO(),

            success: function(response) {

                const res = (typeof response === 'string')
                    ? JSON.parse(response)
                    : response;

                if (res.status === "ok") {

                    toast("Permiso desasociado correctamente");

                    cargarAplicacionesDelPermiso(idPermiso);

                    if (
                        String($("#select_aplicacion").val()) ===
                        String(idAplicacion)
                    ) {
                        cargarPermisosRol();
                    }

                } else {

                    toast(
                        res.msg || "No se pudo desasociar el permiso",
                        "error"
                    );
                }
            },

            error: function(xhr) {

                console.error(
                    "Error al desasociar permiso:",
                    xhr.responseText
                );

                if (xhr.status === 401) {

                    toast("Sesión expirada", "error");

                    setTimeout(() => {
                        window.location.href = '../auth/login.php';
                    }, 1500);

                } else {

                    toast(
                        "Error de conexión con el servidor",
                        "error"
                    );
                }
            }
        });
    });
}