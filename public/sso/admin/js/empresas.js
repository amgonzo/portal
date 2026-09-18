let modoEmpresa = "nuevo"; 
let usuariosGlobal = []; // Listado general de usuarios del sistema para los checkboxes
let permisosUsuario = [];

$(document).ready(function() {
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = '../auth/login.php';
        return;
    }

    // 1. LLAMADA INICIAL CORREGIDA (Con headers incluidos)
    $.ajax({
        url: API_BASE + '/sso/auth/me.php',
        type: 'GET',
        headers: obtenerHeadersSSO(), // <--- ESTO FALTABA
        success: function(response) {
            if (response.status === 'ok' && response.usuario) {
                permisosUsuario = response.permisos || [];

                if (!tienePermiso('empresas_crear')) {
                    $('#btnNuevaEmpresa').hide();
                } else {
                    $('#btnNuevaEmpresa').show();
                }

                cargarUsuariosGlobales();
                cargarEmpresas(); // <--- Acá ya llama con el token seguro
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

function tienePermiso(clave) {
    return Array.isArray(permisosUsuario) && permisosUsuario.includes(clave);
}

// Carga la lista completa de usuarios para asignarlos en el modal de empresas
function cargarUsuariosGlobales() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/usuarios/listar_usuarios.php",
        headers: obtenerHeadersSSO(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if(respuesta.status === "ok") {
                usuariosGlobal = respuesta.data;
            }
        }
    });
}

function renderizarListaUsuariosEmpresa(usuariosAsignados = []) {
    let html = "";

    if (usuariosGlobal.length === 0) {
        $("#contenedor_usuarios_empresa").html('<div class="text-muted">No hay usuarios registrados en el sistema.</div>');
        return;
    }

    usuariosGlobal.forEach(u => {
        // Verificamos si este usuario ya está asociado a la empresa actual
        const asignado = usuariosAsignados.find(ua => ua.idusuario == u.idusuario);
        const checked = asignado ? "checked" : "";

        html += `
        <div class="row align-items-center mb-2 pb-2 border-bottom">
            <div class="col-md-12">
                <div class="form-check">
                    <input class="form-check-input check-usuario-empresa" type="checkbox" 
                           id="usr_${u.idusuario}" 
                           value="${u.idusuario}" 
                           ${checked}>
                    <label class="form-check-label fw-semibold" for="usr_${u.idusuario}">
                        ${u.nombreapellido ?? u.username} <span class="text-muted fw-normal">(${u.username})</span>
                    </label>
                </div>
            </div>
        </div>`;
    });

    $("#contenedor_usuarios_empresa").html(html);
}

function limpiarModalEmpresa() {
    modoEmpresa = "nuevo";
    $("#edit_empresa_id").val("");
    $("#empresa_nombre").val("");
    $("#empresa_razon_social").val("");
    $("#empresa_cuit").val("");
    $("#empresa_slug").val("");
    $("#empresa_db").val("");

    renderizarListaUsuariosEmpresa([]);

    $(".modal-title").text("Crear Nueva Empresa");
    $("#btnGuardarEmpresa").text("Crear Empresa");
}

function abrirNuevaEmpresa() {
    limpiarModalEmpresa();
    $("#ModalEmpresa").modal("show");
}

function cargarEmpresas() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/empresas/listar_empresas.php", // Endpoint a construir en backend
        headers: obtenerHeadersSSO(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if(respuesta.status === "ok") {
                let html = "";
                
                const puedeEditar = tienePermiso('empresas_editar');
                const puedeBorrar = tienePermiso('empresas_borrar');

                respuesta.data.forEach(e => {
                    let btnEditar = puedeEditar 
                    ? `<button class="btn btn-sm btn-info me-1" onclick="editarEmpresa(${e.idempresa})">
                         <i class="fas fa-edit"></i>
                       </button>` 
                    : '';

                    let btnEstado = '';
                    if (puedeBorrar) {
                        if (e.activo == 0) {
                            btnEstado = `<button class="btn btn-sm btn-success" title="Activar" onclick="cambiarEstadoEmpresa(${e.idempresa}, 'alta')">
                                            <i class="fas fa-check"></i>
                                        </button>`;
                        } else {
                            btnEstado = `<button class="btn btn-sm btn-danger" title="Desactivar" onclick="cambiarEstadoEmpresa(${e.idempresa}, 'baja')">
                                            <i class="fas fa-ban"></i>
                                        </button>`;
                        }
                    }

                    let badgesUsuarios = "";
                    if (e.usuarios && e.usuarios.length > 0) {
                        e.usuarios.forEach(us => {
                            badgesUsuarios += `<span class="badge bg-secondary me-1">${us.nombreapellido || us.username}</span> `;
                        });
                    } else {
                        badgesUsuarios = '<span class="text-muted small">Sin usuarios asignados</span>';
                    }

                    html += `<tr>
                        <td>
                            <strong>${e.nombre}</strong><br>
                            <small class="text-muted">${e.razon_social ?? 'Sin razón social'}</small>
                        </td>
                        <td>${e.cuit ?? '-'}</td>
                        <td>
                            <code>${e.slug}</code><br>
                            <small class="text-muted">${e.db_nombre}</small>
                        </td>
                        <td>${badgesUsuarios}</td>
                        <td class="text-center">
                            ${e.activo == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>'}
                        </td>
                        <td>${btnEditar} ${btnEstado}</td>
                    </tr>`;
                });

                $("#listaEmpresas").html(html);
            }
        }
    });
}

function editarEmpresa(id) {
    modoEmpresa = "editar";
    
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/empresas/obtener_empresa.php",
        data: { id: id },
        headers: obtenerHeadersSSO(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            
            if(respuesta.status === "ok") {
                const e = respuesta.data; 

                $("#edit_empresa_id").val(e.idempresa);
                $("#empresa_nombre").val(e.nombre);
                $("#empresa_razon_social").val(e.razon_social);
                $("#empresa_cuit").val(e.cuit);
                $("#empresa_slug").val(e.slug);
                $("#empresa_db").val(e.db_nombre);

                renderizarListaUsuariosEmpresa(e.usuarios || []);

                $(".modal-title").text("Editar Empresa: " + e.nombre);
                $("#btnGuardarEmpresa").text("Guardar Cambios");
                $("#ModalEmpresa").modal("show");
            }
        }
    });
}

function guardarEmpresa() {
    let usuariosAsignados = [];

    $(".check-usuario-empresa:checked").each(function() {
        usuariosAsignados.push(parseInt($(this).val()));
    });

    const datos = {
        idempresa: (modoEmpresa === "editar") ? $("#edit_empresa_id").val() : "",
        nombre: $("#empresa_nombre").val().trim(),
        razon_social: $("#empresa_razon_social").val().trim(),
        cuit: $("#empresa_cuit").val().trim(),
        slug: $("#empresa_slug").val().trim(),
        db_nombre: $("#empresa_db").val().trim(),
        usuarios: JSON.stringify(usuariosAsignados)
    };

    if (!datos.nombre || !datos.slug || !datos.db_nombre) {
        toast('Faltan campos obligatorios (Nombre, Slug y Base de Datos)', 'warning');
        return;
    }

    $.ajax({
        url: API_BASE + "/sso/empresas/guardar_empresa.php",
        type: "POST",
        data: datos,
        headers: obtenerHeadersSSO(),
        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            if(respuesta.status === "ok") {
                $("#ModalEmpresa").modal("hide");
                cargarEmpresas(); 
                toast(modoEmpresa === "nuevo" ? "Empresa creada con éxito" : "Empresa actualizada correctamente", "success");
            } else {
                toast(respuesta.msg || "Error al guardar la empresa", "error");
            }
        },
        error: function() {
            toast('Error de conexión con el servidor', 'error');
        }
    });
}

function cambiarEstadoEmpresa(id, accion) {
    const titulo = accion === 'alta' ? '¿Activar empresa?' : '¿Desactivar empresa?';
    const color = accion === 'alta' ? '#28a745' : '#d33';

    Swal.fire({
        title: titulo,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: color,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: API_BASE + "/sso/empresas/baja_empresa.php",
                data: { id: id, tarea: accion },
                headers: obtenerHeadersSSO(),
                success: function(res) {
                    const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
                    if (respuesta.status === "ok") {
                        cargarEmpresas();
                        toast('Estado actualizado correctamente', 'success');
                    } else {
                        toast(respuesta.msg || 'No se pudo cambiar el estado', 'error');
                    }
                },
                error: function() {
                    toast('Error de conexión con el servidor', 'error');
                }
            });
        }
    });
}