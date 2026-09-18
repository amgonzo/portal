let modoUsuario = "nuevo";

let tiposUsuariosGlobal = [];

let aplicacionesGlobal = [];

let permisosUsuario = [];


$(document).ready(function() {

    // =====================================================
    // 1. VALIDAR TOKEN
    // =====================================================

    const token =
        localStorage.getItem('sso_token');

    if (!token) {

        window.location.href =
            '../auth/login.php';

        return;
    }


    // =====================================================
    // 2. OBTENER USUARIO Y PERMISOS
    // =====================================================

    $.ajax({

        url:
            API_BASE + '/sso/auth/me.php',

        type:
            'GET',

        headers:
            obtenerHeadersSSO(),

        success: function(response) {

            if (
                response.status === 'ok' &&
                response.usuario
            ) {

                permisosUsuario =
                    response.permisos || [];


                // =========================================
                // BOTÓN NUEVO USUARIO
                // =========================================

                if (
                    !tienePermiso(
                        'usuarios_crear'
                    )
                ) {

                    $('#btnNuevoUsuario').hide();

                } else {

                    $('#btnNuevoUsuario').show();
                }


                // =========================================
                // CARGAR COMBOS Y USUARIOS
                // =========================================

                cargarCombos();

                cargarUsuarios();

            } else {

                localStorage.clear();

                window.location.href =
                    '../auth/login.php';
            }
        },

        error: function() {

            localStorage.clear();

            window.location.href =
                '../auth/login.php';
        }
    });
});


// =========================================================
// PERMISOS
// =========================================================

function tienePermiso(clave) {

    return (
        Array.isArray(permisosUsuario) &&
        permisosUsuario.includes(clave)
    );
}


// =========================================================
// CARGAR ROLES Y APLICACIONES
// =========================================================
//
// IMPORTANTE:
// Las dos consultas son asincrónicas.
// Esperamos a que AMBAS terminen antes de renderizar.
//
// =========================================================

function cargarCombos() {

    $.when(

        $.ajax({

            type: "GET",

            url:
                API_BASE +
                "/sso/usuarios/listar_tipos_usuario.php",

            headers:
                obtenerHeadersSSO()
        }),

        $.ajax({

            type: "GET",

            url:
                API_BASE +
                "/sso/aplicaciones/listar_aplicaciones.php",

            headers:
                obtenerHeadersSSO()
        })

    ).done(function(
        resRoles,
        resApps
    ) {

        // =================================================
        // ROLES
        // =================================================

        const respuestaRoles =
            (
                typeof resRoles[0] === 'string'
            )
                ? JSON.parse(resRoles[0])
                : resRoles[0];


        // =================================================
        // APLICACIONES
        // =================================================

        const respuestaApps =
            (
                typeof resApps[0] === 'string'
            )
                ? JSON.parse(resApps[0])
                : resApps[0];


        // =================================================
        // GUARDAR ROLES
        // =================================================

        if (
            respuestaRoles.status === "ok"
        ) {

            tiposUsuariosGlobal =
                respuestaRoles.data || [];

        } else {

            tiposUsuariosGlobal = [];
        }


        // =================================================
        // GUARDAR APLICACIONES
        // =================================================

        if (
            respuestaApps.status === "ok"
        ) {

            aplicacionesGlobal =
                respuestaApps.data || [];

        } else {

            aplicacionesGlobal = [];
        }


        // =================================================
        // AHORA SÍ RENDERIZAMOS
        // =================================================

        renderizarMatrizAccesos([]);

    }).fail(function() {

        toast(
            'No se pudieron cargar las aplicaciones y roles',
            'error'
        );
    });
}


// =========================================================
// RENDERIZAR APLICACIONES + ROLES
// =========================================================

function renderizarMatrizAccesos(
    permisosAsignados = []
) {

    let html = "";


    if (
        aplicacionesGlobal.length === 0
    ) {

        $("#contenedor_apps").html(
            '<div class="text-muted">' +
            'No hay aplicaciones disponibles para asignar.' +
            '</div>'
        );

        return;
    }


    aplicacionesGlobal.forEach(
        app => {

            const asignacionActual =
                permisosAsignados.find(
                    p =>
                        p.idaplicacion ==
                        app.idaplicacion
                );


            const checked =
                asignacionActual
                    ? "checked"
                    : "";


            const disabled =
                asignacionActual
                    ? ""
                    : "disabled";


            html += `

            <div class="row align-items-center mb-2 pb-2 border-bottom">

                <div class="col-md-5">

                    <div class="form-check">

                        <input
                            class="form-check-input check-app"
                            type="checkbox"

                            id="app_${app.idaplicacion}"

                            value="${app.idaplicacion}"

                            ${checked}

                            onchange="
                                toggleRolSelect(
                                    ${app.idaplicacion}
                                )
                            "
                        >

                        <label
                            class="form-check-label fw-semibold"
                            for="app_${app.idaplicacion}"
                        >
                            ${app.nombre}
                        </label>

                    </div>

                </div>


                <div class="col-md-7">

                    <select
                        class="form-select form-select-sm select-rol-app"

                        id="rol_app_${app.idaplicacion}"

                        ${disabled}
                    >

                        <option value="">
                            Seleccionar Rol...
                        </option>
            `;


            tiposUsuariosGlobal.forEach(
                rol => {

                    const selected =
                        (
                            asignacionActual &&
                            asignacionActual.idtipousuario ==
                            rol.idtipousuario
                        )
                            ? "selected"
                            : "";


                    html += `

                        <option
                            value="${rol.idtipousuario}"
                            ${selected}
                        >
                            ${rol.descripcion}
                        </option>

                    `;
                }
            );


            html += `

                    </select>

                </div>

            </div>

            `;
        }
    );


    $("#contenedor_apps").html(html);
}


// =========================================================
// HABILITAR / DESHABILITAR ROL
// =========================================================

function toggleRolSelect(idApp) {

    const isChecked =
        $(`#app_${idApp}`).is(':checked');


    const $select =
        $(`#rol_app_${idApp}`);


    $select.prop(
        'disabled',
        !isChecked
    );


    if (!isChecked) {

        $select.val('');
    }
}


// =========================================================
// LIMPIAR MODAL
// =========================================================

function limpiarModalUsuario() {

    modoUsuario = "nuevo";


    $("#edit_user_id").val("");

    $("#user_nombre").val("");

    $("#user_email").val("");

    $("#user_login").val("");

    $("#user_pass").val("");


    $("#lblClaveOpcional")
        .text("(Obligatoria)");


    renderizarMatrizAccesos([]);


    $(".modal-title")
        .text("Crear Nuevo Usuario");


    $("#btnGuardar")
        .text("Crear Usuario");
}


// =========================================================
// NUEVO USUARIO
// =========================================================

function abrirNuevo() {

    limpiarModalUsuario();

    $("#ModalUsuario").modal("show");
}


// =========================================================
// LISTAR USUARIOS
// =========================================================

// =========================================================
// LISTAR USUARIOS
// =========================================================

function cargarUsuarios() {

    // Obtenemos el ID de la empresa seleccionada en el combo (si existe en el HTML)
    //let idEmpresaFiltro = $('#selectEmpresaActiva').val() || 0;

    $.ajax({

        type: "GET",

        url:
            API_BASE + "/sso/usuarios/listar_usuarios.php",//?idempresa=" + idEmpresaFiltro,

        headers:
            obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (
                    typeof res === 'string'
                )
                    ? JSON.parse(res)
                    : res;


            if (
                respuesta.status === "ok"
            ) {

                let html = "";


                const puedeEditar =
                    tienePermiso(
                        'usuarios_editar'
                    );


                const puedeBorrar =
                    tienePermiso(
                        'usuarios_borrar'
                    );


                respuesta.data.forEach(
                    u => {

                        let btnEditar =
                            puedeEditar

                            ? `
                            <button
                                class="btn btn-sm btn-info me-1"
                                onclick="editar(${u.idusuario})"
                            >
                                <i class="fas fa-edit"></i>
                            </button>
                            `

                            : '';


                        let btnEstado = '';


                        if (puedeBorrar) {

                            if (
                                u.baja == 1
                            ) {

                                btnEstado = `

                                <button
                                    class="btn btn-sm btn-success"
                                    title="Dar de Alta"

                                    onclick="
                                        cambiarEstado(
                                            ${u.idusuario},
                                            'alta'
                                        )
                                    "
                                >

                                    <i class="fas fa-user-check"></i>

                                </button>

                                `;

                            } else {

                                btnEstado = `

                                <button
                                    class="btn btn-sm btn-danger"
                                    title="Dar de Baja"

                                    onclick="
                                        cambiarEstado(
                                            ${u.idusuario},
                                            'baja'
                                        )
                                    "
                                >

                                    <i class="fas fa-user-slash"></i>

                                </button>

                                `;
                            }
                        }


                        let badgesApps = "";


                        if (
                            u.accesos &&
                            u.accesos.length > 0
                        ) {

                            u.accesos.forEach(
                                acc => {

                                    badgesApps += `

                                    <span
                                        class="badge bg-primary me-1"
                                    >
                                        ${acc.nombre_app}:
                                        <i>${acc.rolnombre}</i>
                                    </span>

                                    `;
                                }
                            );

                        } else {

                            badgesApps =
                                '<span class="text-muted small">' +
                                'Sin accesos' +
                                '</span>';
                        }


                        html += `

                        <tr>

                            <td>
                                ${u.nombreapellido ?? '-'}
                            </td>

                            <td>
                                ${u.username}
                            </td>

                            <td>
                                ${badgesApps}
                            </td>

                            <td class="text-center">

                                ${
                                    u.baja == 0

                                    ? '<span class="badge bg-success">Activo</span>'

                                    : '<span class="badge bg-danger">Baja</span>'
                                }

                            </td>

                            <td>

                                ${btnEditar}

                                ${btnEstado}

                            </td>

                        </tr>

                        `;
                    }
                );


                $("#listaUsuarios")
                    .html(html);
            }
        }
    });
}


// =========================================================
// EDITAR USUARIO
// =========================================================

function editar(id) {

    modoUsuario = "editar";


    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/usuarios/obtener_usuario.php",

        data: {
            id: id
        },

        headers:
            obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (
                    typeof res === 'string'
                )
                    ? JSON.parse(res)
                    : res;


            if (
                respuesta.status === "ok"
            ) {

                const u =
                    respuesta.data;


                $("#edit_user_id")
                    .val(u.idusuario);


                $("#user_nombre")
                    .val(u.nombreapellido);


                $("#user_email")
                    .val(u.email);


                $("#user_login")
                    .val(u.username);


                $("#user_pass")
                    .val("");


                $("#lblClaveOpcional")
                    .text(
                        "(Dejar en blanco para mantener actual)"
                    );


                renderizarMatrizAccesos(
                    u.accesos || []
                );


                $(".modal-title")
                    .text(
                        "Editar Usuario: " +
                        u.nombreapellido
                    );


                $("#btnGuardar")
                    .text(
                        "Guardar Cambios"
                    );


                $("#ModalUsuario")
                    .modal("show");
            }
        }
    });
}


// =========================================================
// GUARDAR
// =========================================================

function guardar() {

    let accesos = [];


    $(".check-app:checked").each(
        function() {

            const idApp =
                $(this).val();


            const idRol =
                $(`#rol_app_${idApp}`)
                    .val();


            if (idRol) {

                accesos.push({

                    idaplicacion:
                        parseInt(idApp),

                    idtipousuario:
                        parseInt(idRol)
                });
            }
        }
    );


    // =====================================================
    // VALIDAR APLICACIÓN + ROL
    // =====================================================

    if (
        accesos.length === 0
    ) {

        toast(
            'Debe seleccionar al menos una aplicación con su rol correspondiente',
            'warning'
        );

        return;
    }


    const datos = {

        id:
            (
                modoUsuario === "editar"
            )
                ? $("#edit_user_id").val()
                : "",

        nombre:
            $("#user_nombre")
                .val()
                .trim(),

        email:
            $("#user_email")
                .val()
                .trim(),

        login:
            $("#user_login")
                .val()
                .trim(),

        clave:
            $("#user_pass")
                .val()
                .trim(),

        accesos:
            JSON.stringify(accesos)
    };


    // =====================================================
    // CAMPOS OBLIGATORIOS
    // =====================================================

    if (
        !datos.nombre ||
        !datos.login ||
        (
            modoUsuario === "nuevo" &&
            !datos.clave
        )
    ) {

        toast(
            'Faltan campos obligatorios',
            'warning'
        );

        return;
    }


    // =====================================================
    // GUARDAR EN BACKEND
    // =====================================================

    $.ajax({

        url:
            API_BASE +
            "/sso/usuarios/crear_usuarios.php",

        type:
            "POST",

        data:
            datos,

        headers:
            obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (
                    typeof res === 'string'
                )
                    ? JSON.parse(res)
                    : res;


            if (
                respuesta.status === "ok"
            ) {

                $("#ModalUsuario")
                    .modal("hide");


                cargarUsuarios();


                toast(

                    modoUsuario === "nuevo"

                        ? "Usuario creado con éxito"

                        : "Datos actualizados correctamente",

                    "success"
                );

            } else {

                toast(

                    respuesta.msg ||
                    "Error al guardar el usuario",

                    "error"
                );
            }
        },

        error: function() {

            toast(
                'Error de conexión con el servidor',
                'error'
            );
        }
    });
}


// =========================================================
// CAMBIAR ESTADO
// =========================================================

function cambiarEstado(
    id,
    accion
) {

    const titulo =
        accion === 'alta'
            ? '¿Dar de alta?'
            : '¿Dar de baja?';


    const texto =
        accion === 'alta'
            ? 'El usuario volverá a tener acceso.'
            : 'El usuario ya no podrá loguearse.';


    const color =
        accion === 'alta'
            ? '#28a745'
            : '#d33';


    Swal.fire({

        title:
            titulo,

        text:
            texto,

        icon:
            'question',

        showCancelButton:
            true,

        confirmButtonColor:
            color,

        confirmButtonText:
            'Sí, confirmar',

        cancelButtonText:
            'Cancelar'

    }).then(
        (result) => {

            if (
                result.isConfirmed
            ) {

                $.ajax({

                    type:
                        "POST",

                    url:
                        API_BASE +
                        "/sso/usuarios/baja_usuario.php",

                    data: {
                        id:
                            id,

                        tarea:
                            accion
                    },

                    headers:
                        obtenerHeadersSSO(),

                    success:
                        function(res) {

                            const respuesta =
                                (
                                    typeof res === 'string'
                                )
                                    ? JSON.parse(res)
                                    : res;


                            if (
                                respuesta.status === "ok"
                            ) {

                                cargarUsuarios();


                                toast(
                                    'El estado del usuario ha sido actualizado correctamente',
                                    'success'
                                );

                            } else {

                                toast(

                                    respuesta.msg ||
                                    'No se pudo cambiar el estado',

                                    'error'
                                );
                            }
                        },

                    error:
                        function() {

                            toast(
                                'Error de conexión con el servidor',
                                'error'
                            );
                        }
                });
            }
        }
    );
}