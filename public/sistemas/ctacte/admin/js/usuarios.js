let modoUsuario = "nuevo"; 

$(document).ready(function() {

    $.get("../api/usuarios/tipos_usuarios.php", function(res) {
        const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
        if(respuesta.status === "ok") {
            tiposUsuariosGlobal = respuesta.data;
            renderizarSelectTipos();
            cargarUsuarios();
        }
    });

    if (!tienePermiso('usuarios_crear')) {
        $('#btnNuevoUsuario').hide();
    } else {
        $('#btnNuevoUsuario').show();
    }
});

function renderizarSelectTipos(idSeleccionar = null) {
    if (tiposUsuariosGlobal.length === 0) return;
    
    let options = '<option value="">Seleccione Tipo...</option>';
    tiposUsuariosGlobal.forEach(t => {
        const selected = (idSeleccionar && t.idtipousuario == idSeleccionar) ? 'selected' : '';
        options += `<option value="${t.idtipousuario}" ${selected}>${t.descripcion}</option>`;
    });
    
    $("#user_tipo").html(options);

    if(idSeleccionar) {
        $("#user_tipo").val(idSeleccionar);
    }
}

function limpiarModalUsuario() {
    modoUsuario = "nuevo";
    $("#edit_user_id").val("");

    $("#user_nombre").val("");
    $("#user_email").val("");
    $("#user_login").val("");
    $("#user_pass").val("");

    $("#user_tipo").val(""); 

    $(".modal-title").text("Crear Nuevo Usuario");
}

$(document).on('click', '#btnNuevoUsuario', function() {
    limpiarModalUsuario();
    $("#ModalUsuario").modal("show");
});

function cargarTipos() {
    $.ajax({
        type: "GET",
        url: "../api/usuarios/tipos_usuarios.php",
        success: function(res) {
            if(res.status === "ok") {
                var options = '<option value="">Seleccione Tipo...</option>';
                res.data.forEach(t => {
                    options += `<option value="${t.idtipousuario}">${t.descripcion}</option>`;
                });
                $("#user_tipo").html(options);
            }
        }
    });
}

$(document).on('change', '#user_tipo', function() {
    const idSeleccionado = $(this).val();
    const idMedico = obtenerIdTipoMedico();

    // Si el ID elegido coincide con el de Médico, mostramos la firma
    if (idMedico && idSeleccionado == idMedico) {
        $("#seccion_firma").fadeIn();
    } else {
        $("#seccion_firma").fadeOut();
        // Limpiamos el input file por si había algo cargado
        $("#user_firma").val(""); 
        $("#img_firma_previa").hide();
    }
});

function cargarUsuarios() {
    $.ajax({
        type: "GET",
        url: "../api/usuarios/listar_usuarios.php",
        success: function(res) {
            if(res.status === "ok") {
                let html = "";


                res.data.forEach(u => {
                    

                    let btnEditar = tienePermiso('usuarios_editar') 
                    ? `<button class="btn btn-sm btn-info" onclick="editar(${u.idusuario})">
                         <i class="fas fa-edit"></i>
                       </button>` 
                    : '';

                    let btnEstado = '';
                    if (tienePermiso('usuarios_borrar')) {
                        if (u.baja == 1) {
                            // Si está de baja, botón verde para dar de ALTA
                            btnEstado = `<button class="btn btn-sm btn-success" title="Dar de Alta" onclick="cambiarEstado(${u.idusuario}, 'alta')">
                                            <i class="fas fa-user-check"></i>
                                        </button>`;
                        } else {
                            // Si está activo, botón rojo para dar de BAJA
                            btnEstado = `<button class="btn btn-sm btn-danger" title="Dar de Baja" onclick="cambiarEstado(${u.idusuario}, 'baja')">
                                            <i class="fas fa-user-slash"></i>
                                        </button>`;
                        }
                    }

                    html += `<tr>
                        <td title="${u.email ?? ''}">
                            ${u.nombreapellido ?? '-'} 
                        </td>
                        <td>${u.username}</td>
                        <td>${u.rolnombre ?? '-'}</td>
                        <td class="text-center">
                            ${u.baja == 0 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge badge-danger">Baja</span>'}
                        </td>
                        <td>${btnEditar} ${btnEstado}</td>
                    </tr>`;
                });

                $("#listaUsuarios").html(html);
            }
        }
    });
}

 let modo = "nuevo";

function abrirNuevo() {
    modo = "nuevo";
    
    $("#edit_user_id").val("");
    $("#user_nombre").val("");
    $("#user_email").val("");
    $("#user_login").val("");
    $("#user_pass").val("");
    $("#user_tipo").val(""); 
    
    $("#seccion_firma").hide();
    //$("#check_prov, #check_def").prop('checked', false);
    $("#img_firma_previa").hide().attr("src", "");
    
    $(".modal-title").text("Crear Nuevo Usuario");
    $("#btnGuardar").text("Crear Usuario");
    
    $("#ModalUsuario").modal("show");
}

$(document).on('change', '#user_tipo', function() {
    //const idMedico = obtenerIdTipoMedico();
    /*
    if ($(this).val() == idMedico && idMedico !== null) {
        $("#seccion_firma, #seccion_firma_check").fadeIn();
    } else {
        $("#seccion_firma, #seccion_firma_check").fadeOut();
        $("#check_prov, #check_def").prop('checked', false);
        $("#user_firma").val(""); 
    }*/
});

function editar(id) {
    modo = "editar";
    
    $.get("../api/usuarios/get_usuario.php", { id: id }, function(res) {
        const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
        
        if(respuesta.status === "ok") {
            const u = respuesta.data; 
            const idMedico = obtenerIdTipoMedico();

            $("#edit_user_id").val(u.idusuario);
            $("#user_nombre").val(u.nombreapellido);
            $("#user_email").val(u.email);
            $("#user_login").val(u.username);
            $("#user_pass").val(""); 
            
            renderizarSelectTipos(u.idtipousuario);

            if (u.idtipousuario == idMedico && idMedico !== null) {
                $("#seccion_firma").show();
                //$("#check_prov").prop('checked', u.firmaprovisoria == 1);
                //$("#check_def").prop('checked', u.firmadefinitiva == 1);

                if (u.firmadigital) {
                    $("#img_firma_previa").attr("src", "/firmas/" + u.firmadigital + "?v=" + new Date().getTime()).show();
                } else {
                    $("#img_firma_previa").hide();
                }
            } else {
                $("#seccion_firma").hide();
                //$("#check_prov, #check_def").prop('checked', false);
            }

            $(".modal-title").text("Editar Usuario: " + u.nombreapellido);
            $("#btnGuardar").text("Guardar Cambios");
            $("#ModalUsuario").modal("show");
        }
    });
}

function obtenerIdTipoMedico() {
    const tipoMedico = tiposUsuariosGlobal.find(t => 
        t.descripcion.toLowerCase().includes('médico') || 
        t.descripcion.toLowerCase().includes('medico')
    );
    return tipoMedico ? tipoMedico.idtipousuario : null;
}

function guardar() {
    const formulario = document.getElementById('formUsuario');
    const datos = new FormData(formulario);

    datos.append("id", (modo === "editar") ? $("#edit_user_id").val() : "");
    datos.append("nombre", $("#user_nombre").val().trim());
    datos.append("email", $("#user_email").val().trim());
    datos.append("tipo", $("#user_tipo").val());
    datos.append("login", $("#user_login").val().trim());
    datos.append("clave", $("#user_pass").val().trim());

    //datos.append("firmaprovisoria", $("#check_prov").is(':checked') ? 1 : 0);
    //datos.append("firmadefinitiva", $("#check_def").is(':checked') ? 1 : 0);

    if (!$("#user_nombre").val().trim() || !$("#user_login").val().trim() || (modo === "nuevo" && !$("#user_pass").val().trim())) {
        toast("Faltan campos obligatorios", "warning");
        return;
    }

    $.ajax({
        url: "../api/usuarios/crear_usuarios.php",
        type: "POST",
        data: datos,
        processData: false, 
        contentType: false, 
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if(respuesta.status === "ok") {
                $("#ModalUsuario").modal("hide");
                cargarUsuarios(); 
                toast(modo === "nuevo" ? "Usuario creado con éxito" : "Datos actualizados");
                $("#user_firma").val(""); 
            } else {
                toast("Error: " + respuesta.msg, "error");
            }
        },
        error: function() {
            toast("Error de conexión con el servidor", "error");
        }
    });
}

function borrar(id) {
    Swal.fire({
        title: '¿Dar de baja?',
        text: "El usuario ya no podrá loguearse",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/usuarios/acciones_usuarios.php",
                data: { id: id, tarea: 'baja' },
                success: function(res) {
                    if(res.status === "ok") {
                        toast("Usuario dado de baja", "info");
                        cargarUsuarios();
                    }
                }
            });
        }
    });
}

function cambiarEstado(id, accion) {
    const titulo = accion === 'alta' ? '¿Dar de alta?' : '¿Dar de baja?';
    const texto = accion === 'alta' ? 'El usuario volverá a tener acceso.' : 'El usuario ya no podrá loguearse.';
    const color = accion === 'alta' ? '#28a745' : '#d33';

    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/usuarios/acciones_usuarios.php",
                data: { id: id, tarea: accion }, // 'alta' o 'baja'
                success: function(res) {
                    if(res.status === "ok") {
                        toast(accion === 'alta' ? "Usuario activado" : "Usuario dado de baja", "info");
                        cargarUsuarios();
                    }
                }
            });
        }
    });
}