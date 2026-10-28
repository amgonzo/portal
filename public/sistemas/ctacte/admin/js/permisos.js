function cargarPermisosRol() {
    const idTipo = $("#select_tipo_permiso").val();
    if(!idTipo) { $("#contenedor_permisos").hide(); return; }

    $.ajax({
        type: "GET",
        url: "../api/permisos/get_permisos_rol.php",
        data: { idtipousuario: idTipo },
        success: function(res) {
            if(res.status === "ok") {
                let html = "";
                let catActual = "";

                // 1. Ordenar datos
                res.data.todos.sort((a, b) => a.clavepermiso.localeCompare(b.clavepermiso));

                res.data.todos.forEach(p => {
                    let cat = p.clavepermiso.split('_')[0].toUpperCase();
                    
                    // 2. Si cambia la categoría, dibujamos el encabezado limpio (solo flecha)
                    if (cat !== catActual) {
                        catActual = cat;
                        html += `
                            <tr class="header-modulo" style="cursor:pointer;" data-modulo="${catActual}">
                                <td colspan="3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><strong><i class="fas fa-chevron-down mr-3 arrow-icon"></i> MÓDULO: ${catActual}</strong></span>
                                        <small class="text-muted">Total: ${res.data.todos.filter(x => x.clavepermiso.startsWith(cat.toLowerCase())).length}</small>
                                    </div>
                                </td>
                            </tr>`;
                    }

                    // 3. Dibujamos la fila del permiso (lo que me comí en el anterior)
                    let checked = res.data.asignados.includes(parseInt(p.idpermiso)) ? "checked" : "";

                    html += `
                        <tr class="fila-modulo fila-${catActual}">
                            <td class="pl-4"><strong>${p.clavepermiso}</strong></td>
                            <td>${p.descripcion}</td>
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

                // 4. Evento Click para colapsar
                $(".header-modulo").off("click").on("click", function() {
                    const modulo = $(this).data("modulo");
                    const icono = $(this).find(".arrow-icon");
                    
                    $(".fila-" + modulo).toggle(); // Directo al grano
                    icono.toggleClass("rotate-icon");
                });
            }
        }
    });
}

function guardarPermisos() {
    const idTipo = $("#select_tipo_permiso").val();

    if(!idTipo){
        toast("Seleccioná un rol", "warning");
        return;
    }

    let seleccionados = [];

    $(".check-permiso:checked").each(function() {
        seleccionados.push(parseInt($(this).val()));
    });

    $.ajax({
        type: "POST",
        url: "../api/permisos/guardar_permisos.php",
        data: { idtipousuario: idTipo, permisos: seleccionados },
        success: function(res) {
            if(res.status === "ok") {
                toast("Permisos actualizados correctamente");
            } else {
                toast("Error: " + res.msg, "error");
            }
        }
    });
}

function cargarTipos() {
    $.ajax({
        type: "GET",
        url: "../api/usuarios/tipos_usuarios.php",
        success: function(res) {
            if(res.status === "ok") {
                let html = '<option value="">Seleccione un rol...</option>';

                res.data.forEach(t => {
                    html += `<option value="${t.idtipousuario}">${t.descripcion}</option>`;
                });

                $("#select_tipo_permiso").html(html);

                // 🔥 SELECCIONAR EL PRIMERO AUTOMÁTICAMENTE
                if (res.data.length > 0) {
                    $("#select_tipo_permiso").val(res.data[0].idtipousuario);
                    cargarPermisosRol(); // 🔥 DISPARAR
                }
            }
        }
    });
}

$(document).ready(function() {

    cargarTipos();

    // 🔥 cuando cambie el select
    $(document).on("change", "#select_tipo_permiso", function() {
        console.log("CHANGE detectado");
        cargarPermisosRol();
    });

    if (!tienePermiso('configuracion_avanzada')) {
        $('#btnNuevoTipoUsuario').hide();
        $('#btnNuevoPermiso').hide();
    } else {
        $('#btnNuevoTipoUsuario').show();
        $('#btnNuevoPermiso').show();
    }
});

function crearPermisoBase() {
    const clave = $("#nueva_clave").val();
    const desc = $("#nueva_desc").val();

    if (!clave || !desc) {
        toast("Completá todos los campos", 'error');
        return;
    }

    $.ajax({
        type: "POST",
        url: "../api/permisos/crear_permiso.php",
        data: {
            clave: clave,
            descripcion: desc
        },
        headers: {
            "Authorization": "Bearer " + TOKEN // Usamos la constante que tenés en el header
        },
        success: function(res) {
            if (res.status === "ok") {
                $("#modalNuevoPermiso").modal('hide');
                $("#nueva_clave, #nueva_desc").val('');
                
                // Usamos tu función toast del header
                toast("Permiso creado correctamente");
                
                // Recargamos la lista de permisos si la función existe
                if (typeof cargarPermisosRol === 'function') {
                    cargarPermisosRol();
                }
            } else {
                // Si el backend tira error (ej: clave duplicada)
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
        toast("El nombre del tipo de Usuario es obligatorio", "warning");
        return;
    }

    $.ajax({
        type: "POST",
        url: "../api/permisos/crear_tipo_usuario.php", // Deberás crear este archivo
        data: { nombre: nombre },
        success: function(res) {
            if (res.status === "ok") {
                toast("Tipo de usuario creado correctamente");
                $("#modalNuevoRol").modal("hide");
                $("#nuevo_rol_nombre").val("");
                // Recargamos el select para que aparezca el nuevo rol
                cargarTipos(); 
            } else {
                toast("Error: " + res.msg, "error");
            }
        }
    });
}