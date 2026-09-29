let modoUsuario = "nuevo";

let tiposUsuariosGlobal = [];

let aplicacionesGlobal = [];

let permisosUsuario = [];

let esRoot = false;


/* =========================================================
   INICIO
   ========================================================= */

$(document).ready(function() {

    const token =
        localStorage.getItem('sso_token');

    if (!token) {

        window.location.href =
            '../auth/login.php';

        return;
    }


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


                /*
                 * Detectamos SUPER_ADMIN.
                 *
                 * Primero usamos el dato que envía
                 * directamente me.php.
                 *
                 * Se agregan algunas variantes por
                 * compatibilidad con distintas respuestas
                 * del backend.
                 */
                esRoot =
                    String(response.usuario.tipo_sistema || '').toUpperCase() === 'ROOT'
                    || response.usuario.root === true;


                if (
                    !tienePermiso('usuarios_crear')
                ) {

                    $('#btnNuevoUsuario').hide();

                } else {

                    $('#btnNuevoUsuario').show();
                }


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


/* =========================================================
   PERMISOS
   ========================================================= */

function tienePermiso(clave) {

    return (
        Array.isArray(permisosUsuario) &&
        permisosUsuario.includes(clave)
    );
}


/* =========================================================
   EMPRESAS DEL SUPER ADMIN
   ========================================================= */

function obtenerEmpresasGuardadas() {

    try {

        const datos =
            JSON.parse(
                localStorage.getItem('sso_empresas') || '[]'
            );

        if (Array.isArray(datos)) {
            return datos;
        }

        return [];

    } catch (e) {

        return [];
    }
}


/* =========================================================
   EMPRESA ACTIVA GLOBAL
   ========================================================= */

function obtenerIdEmpresaGlobal() {

    const empresa =
        obtenerEmpresaActiva();

    if (
        empresa &&
        empresa.idempresa
    ) {

        return parseInt(
            empresa.idempresa
        );
    }

    return 0;
}


/* =========================================================
   CARGAR SELECTOR DE EMPRESA DEL MODAL
   ========================================================= */

function cargarEmpresasModal(
    idEmpresaSeleccionada = 0
) {

    if (!esRoot) {

        $('#contenedor_empresa_usuario')
            .hide();

        return;
    }


    const empresas =
        obtenerEmpresasGuardadas();


    const $select =
        $('#selectEmpresaUsuario');


    $select.empty();


    $select.append(`
        <option value="">
            Seleccionar empresa...
        </option>
    `);


    empresas.forEach(function(empresa) {

        const id =
            parseInt(
                empresa.idempresa ||
                empresa.id ||
                0
            );


        const nombre =
            empresa.nombre ||
            empresa.nombreempresa ||
            empresa.descripcion ||
            ('Empresa ' + id);


        if (id > 0) {

            const selected =
                parseInt(idEmpresaSeleccionada) === id
                    ? 'selected'
                    : '';


            $select.append(`
                <option
                    value="${id}"
                    ${selected}
                >
                    ${nombre}
                </option>
            `);
        }
    });


    /*
     * IMPORTANTE:
     * Siempre mostramos el selector para SUPER_ADMIN.
     */
    $('#contenedor_empresa_usuario')
        .show();
}


/* =========================================================
   EMPRESA DEL MODAL
   ========================================================= */

function obtenerEmpresaModal() {

    if (!esRoot) {

        const empresa =
            obtenerEmpresaActiva();

        return empresa &&
               empresa.idempresa
            ? parseInt(empresa.idempresa)
            : 0;
    }


    return parseInt(
        $('#selectEmpresaUsuario').val() || 0
    );
}


/* =========================================================
   HEADERS PARA EL MODAL
   ========================================================= */

function obtenerHeadersEmpresaModal() {

    const headers =
        obtenerHeadersSSO();


    if (esRoot) {

        const idEmpresa =
            obtenerEmpresaModal();


        if (idEmpresa > 0) {

            headers["X-EMPRESA-ID"] =
                String(idEmpresa);

        } else {

            delete headers["X-EMPRESA-ID"];
        }
    }


    return headers;
}


/* =========================================================
   CARGAR ROLES Y APLICACIONES
   ========================================================= */

function cargarCombos() {

    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/usuarios/listar_tipos_usuario.php",

        headers:
            obtenerHeadersSSO(),

        success: function(resRoles) {

            const respuestaRoles =
                (
                    typeof resRoles === 'string'
                )
                    ? JSON.parse(resRoles)
                    : resRoles;


            if (
                respuestaRoles.status === "ok"
            ) {

                tiposUsuariosGlobal =
                    respuestaRoles.data || [];

            } else {

                tiposUsuariosGlobal = [];
            }


            /*
             * Las aplicaciones ya NO se cargan acá.
             *
             * Ahora dependen de la empresa seleccionada
             * y se cargan mediante cargarAplicaciones().
             */

            aplicacionesGlobal = [];

            renderizarMatrizAccesos([]);

        },

        error: function() {

            tiposUsuariosGlobal = [];

            aplicacionesGlobal = [];

            toast(
                'No se pudieron cargar los roles',
                'error'
            );
        }
    });
}

function cargarAplicaciones(callback = null) {

    const idEmpresa =
        obtenerEmpresaModal();


    if (idEmpresa <= 0) {

        aplicacionesGlobal = [];

        renderizarMatrizAccesos([]);

        if (typeof callback === 'function') {
            callback();
        }

        return;
    }


    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/aplicaciones/listar_aplicaciones.php",

        headers:
            obtenerHeadersEmpresaModal(),

        success: function(response) {

            const res =
                (
                    typeof response === 'string'
                )
                    ? JSON.parse(response)
                    : response;


            if (
                res.status === "ok"
            ) {

                aplicacionesGlobal =
                    res.data || [];

            } else {

                aplicacionesGlobal = [];

                toast(
                    res.msg ||
                    "No se pudieron cargar las aplicaciones",
                    "error"
                );
            }


            renderizarMatrizAccesos([]);


            if (typeof callback === 'function') {
                callback();
            }
        },

        error: function(xhr) {

            aplicacionesGlobal = [];

            renderizarMatrizAccesos([]);

            toast(
                "No se pudieron cargar las aplicaciones",
                "error"
            );


            if (typeof callback === 'function') {
                callback();
            }
        }
    });
}

/* =========================================================
   RENDERIZAR APLICACIONES + ROLES
   ========================================================= */

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

                    <select  class="form-select form-select-sm select-rol-app"
                        data-idaplicacion="${app.idaplicacion}"
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
                    <div
                        id="permisos_rol_${app.idaplicacion}"
                        class="mt-2"
                        style="display:none;"
                    ></div>
                </div>

            </div>

            `;
        }
    );


    $("#contenedor_apps").html(html);

    $('.select-rol-app').each(function() {

    const idTipoUsuario = $(this).val();

    if (!idTipoUsuario) {
        return;
    }

    const idAplicacion = $(this).data('idaplicacion');

    cargarPermisosRol(
        idAplicacion,
        idTipoUsuario
    );

});
}


/* =========================================================
   HABILITAR / DESHABILITAR ROL
   ========================================================= */

function toggleRolSelect(idApp) {
    const isChecked = $(`#app_${idApp}`).is(':checked');
    const $select = $(`#rol_app_${idApp}`);
    const $contenedor = $(`#permisos_rol_${idApp}`);

    $select.prop('disabled', !isChecked);

    if (!isChecked) {
        $select.val('');
        $contenedor.hide().html('');
        return;
    }

    const idRol = $select.val();

    if (idRol) {
        cargarPermisosRol(idApp, idRol);
    } else {
        $contenedor.hide().html('');
    }
}


/* =========================================================
   LIMPIAR MODAL
   ========================================================= */

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


/* =========================================================
   NUEVO USUARIO
   ========================================================= */

function abrirNuevo() {

    limpiarModalUsuario();


    if (esRoot) {

        const idEmpresa =
            obtenerIdEmpresaGlobal();


        /*
         * Si hay empresa global:
         * la seleccionamos automáticamente.
         *
         * Si no hay:
         * queda "Seleccionar empresa..."
         * y será obligatorio elegirla.
         */
        cargarEmpresasModal(
            idEmpresa
        );
        if (idEmpresa > 0) {
            cargarAplicaciones();
        }
    } else {

        $('#contenedor_empresa_usuario')
            .hide();
    }


    $("#ModalUsuario")
        .modal("show");
}


/* =========================================================
   LISTAR USUARIOS
   ========================================================= */

/* =========================================================
   LISTAR USUARIOS
   ========================================================= */

function cargarUsuarios() {

    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/usuarios/listar_usuarios.php",

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
                respuesta.status !== "ok"
            ) {

                toast(
                    respuesta.msg ||
                    "No se pudieron cargar los usuarios",
                    "error"
                );

                return;
            }

            /*
             * =====================================================
             * COLUMNA EMPRESA
             *
             * Solo se muestra cuando SUPER_ADMIN está viendo
             * todas las empresas, es decir, sin empresa activa.
             * =====================================================
             */

            const mostrarEmpresa =
                esRoot &&
                obtenerIdEmpresaGlobal() <= 0;


            if (mostrarEmpresa) {

                $('#thEmpresaUsuario').show();

            } else {

                $('#thEmpresaUsuario').hide();
            }


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
                                title="Editar"
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

                                let empresaAcceso = '';


                                if (
                                    mostrarEmpresa &&
                                    acc.nombre_empresa
                                ) {

                                    empresaAcceso = `
                                        <div class="small mt-1 opacity-75">
                                            <i class="fas fa-building me-1"></i>
                                            ${acc.nombre_empresa}
                                        </div>
                                    `;
                                }


                                badgesApps += `

                                <span
                                    class="badge bg-primary me-1 mb-1"
                                    style="display:inline-block; text-align:left;"
                                >
                                    ${acc.nombre_app}:
                                    <i>${acc.rolnombre}</i>

                                    ${empresaAcceso}

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


                    /*
                     * =====================================================
                     * EMPRESAS DEL USUARIO
                     *
                     * En la respuesta actual las empresas vienen
                     * asociadas a los accesos.
                     * =====================================================
                     */

                    let empresasUsuario = '';


                    if (
                        mostrarEmpresa &&
                        u.accesos &&
                        u.accesos.length > 0
                    ) {

                        const empresasUnicas = [];


                        u.accesos.forEach(
                            acc => {

                                if (
                                    acc.nombre_empresa &&
                                    !empresasUnicas.includes(
                                        acc.nombre_empresa
                                    )
                                ) {

                                    empresasUnicas.push(
                                        acc.nombre_empresa
                                    );
                                }
                            }
                        );


                        if (
                            empresasUnicas.length > 0
                        ) {

                            empresasUsuario =
                                empresasUnicas.join(
                                    '<br>'
                                );

                        } else {

                            empresasUsuario =
                                '<span class="text-muted">-</span>';
                        }

                    } else {

                        empresasUsuario =
                            '<span class="text-muted">-</span>';
                    }


                    html += `

                    <tr>

                        <td>
                            ${u.nombreapellido ?? '-'}
                        </td>

                        <td>
                            ${u.username}
                        </td>

                        ${
                            mostrarEmpresa
                                ? `
                                <td>
                                    ${empresasUsuario}
                                </td>
                                `
                                : ''
                        }

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
                            <div class="d-flex align-items-center gap-1">

                                ${btnEditar}

                                ${btnEstado}

                            </div>
                        </td>

                    </tr>

                    `;
                }
            );


            $("#listaUsuarios")
                .html(html);


            /*
             * =====================================================
             * AHORA QUE esRoot YA ESTÁ CORRECTAMENTE
             * DETERMINADO, EL SELECTOR DEL MODAL FUNCIONARÁ.
             * =====================================================
             */

        },

        error: function(xhr) {

            toast(
                'No se pudieron cargar los usuarios',
                'error'
            );
        }
    });
}


/* =========================================================
   EDITAR USUARIO
   ========================================================= */

function editar(id) {

    modoUsuario = "editar";


    let idEmpresa = 0;


    if (esRoot) {

        idEmpresa =
            obtenerIdEmpresaGlobal();


        cargarEmpresasModal(
            idEmpresa
        );

    } else {

        const empresaActiva =
            obtenerEmpresaActiva();


        if (
            empresaActiva &&
            empresaActiva.idempresa
        ) {

            idEmpresa =
                parseInt(
                    empresaActiva.idempresa
                );
        }
    }


    /*
     * SUPER_ADMIN sin empresa global:
     *
     * Abrimos el modal mostrando el selector.
     * No intentamos consultar todavía porque
     * obtener_usuario.php necesita la empresa.
     */
    if (
        esRoot &&
        idEmpresa <= 0
    ) {

        $("#edit_user_id")
            .val(id);


        $("#user_nombre")
            .val("");


        $("#user_email")
            .val("");


        $("#user_login")
            .val("");


        $("#user_pass")
            .val("");


        $("#lblClaveOpcional")
            .text(
                "(Dejar en blanco para mantener actual)"
            );


        renderizarMatrizAccesos([]);


        $(".modal-title")
            .text(
                "Editar Usuario"
            );


        $("#btnGuardar")
            .text(
                "Guardar Cambios"
            );


        $("#ModalUsuario")
            .modal("show");


        return;
    }


    /*
     * Si ya hay empresa seleccionada,
     * cargamos directamente el usuario.
     */
    cargarAplicaciones(function() {
        cargarDatosUsuario(id);
    });
}


/* =========================================================
   CARGAR DATOS DEL USUARIO
   ========================================================= */

function cargarDatosUsuario(id) {

    const idEmpresa =
        obtenerEmpresaModal();


    /*
     * Evita mandar una consulta que sabemos
     * que el backend va a rechazar.
     */
    if (
        idEmpresa <= 0
    ) {

        return;
    }


    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/usuarios/obtener_usuario.php",

        data: {
            id: id
        },

        headers:
            obtenerHeadersEmpresaModal(),

        success: function(res) {

            const respuesta =
                (
                    typeof res === 'string'
                )
                    ? JSON.parse(res)
                    : res;


            if (
                respuesta.status !== "ok"
            ) {

                toast(
                    respuesta.msg ||
                    "No se pudo obtener el usuario",
                    "error"
                );

                return;
            }


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


            /*
             * Si SUPER_ADMIN está trabajando con
             * una empresa desde el modal, mantenemos
             * esa empresa seleccionada.
             */
            if (esRoot) {

                cargarEmpresasModal(
                    idEmpresa
                );
            }


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
        },

        error: function() {

            toast(
                "Error de conexión con el servidor",
                "error"
            );
        }
    });
}


/* =========================================================
   CAMBIO DE EMPRESA DENTRO DEL MODAL
   ========================================================= */

$(document).on(
    'change',
    '#selectEmpresaUsuario',
    function() {

        const idEmpresa =
            parseInt(
                $(this).val() || 0
            );


        /*
         * Si estamos creando:
         * solamente necesitamos que quede
         * seleccionada para guardar.
         */
        if (modoUsuario === "nuevo") {

            if (idEmpresa > 0) {
                cargarAplicaciones();
            } else {
                aplicacionesGlobal = [];
                renderizarMatrizAccesos([]);
            }

            return;
        }


        /*
         * Si estamos editando y se eligió
         * una empresa, cargamos nuevamente
         * los datos/accesos del usuario
         * correspondientes a esa empresa.
         */
        if (
            modoUsuario === "editar" &&
            idEmpresa > 0
        ) {

            const idUsuario =
                parseInt(
                    $("#edit_user_id").val() || 0
                );

            cargarAplicaciones(function() {

                if (idUsuario > 0) {

                    cargarDatosUsuario(
                        idUsuario
                    );
                }

            });
        }
    }
);


/* =========================================================
   GUARDAR
   ========================================================= */

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


    if (
        accesos.length === 0 &&
        !esRoot
    ) {

        toast(
            'Debe seleccionar al menos una aplicación con su rol correspondiente',
            'warning'
        );

        return;
    }


    /*
     * Empresa que se utilizará para guardar.
     */
    const idEmpresa =
        obtenerEmpresaModal();


    if (
        idEmpresa <= 0 &&
        !esRoot
    ) {

        toast(
            'Debe seleccionar una empresa',
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


    $.ajax({

        url:
            API_BASE +
            "/sso/usuarios/crear_usuarios.php",

        type:
            "POST",

        data:
            datos,

        headers:
            obtenerHeadersEmpresaModal(),

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


/* =========================================================
   CAMBIAR ESTADO
   ========================================================= */

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

function cargarPermisosRol(idAplicacion, idTipoUsuario) {
    const contenedor = $(`#permisos_rol_${idAplicacion}`);

    if (!idAplicacion || !idTipoUsuario) {
        contenedor.hide().html('');
        return;
    }

    contenedor
        .show()
        .html('<div class="small text-muted">Cargando permisos...</div>');

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/permisos/get_permisos_rol.php",
        data: {
            idaplicacion: idAplicacion,
            idtipousuario: idTipoUsuario
        },
        headers: obtenerHeadersSSO(),
        success: function(response) {
            const res = (typeof response === 'string')
                ? JSON.parse(response)
                : response;

            if (res.status !== "ok") {
                contenedor.html(
                    '<div class="small text-danger">No se pudieron cargar los permisos.</div>'
                );
                return;
            }

            const todos = Array.isArray(res.data.todos)
                ? res.data.todos
                : [];

            const asignados = Array.isArray(res.data.asignados)
                ? res.data.asignados.map(Number)
                : [];

            const permisosPuede = todos.filter(p =>
                asignados.includes(Number(p.idpermiso))
            );

            const permisosNoPuede = todos.filter(p =>
                !asignados.includes(Number(p.idpermiso))
            );

            let html = `
                <div class="border rounded p-2 bg-body-tertiary small">
            `;

            if (permisosPuede.length > 0) {
                html += `
                    <div class="fw-semibold mb-1">
                        <i class="fas fa-check text-success me-1"></i>
                        Puede:
                    </div>
                    <ul class="mb-2 ps-3">
                `;

                permisosPuede.forEach(p => {
                    html += `
                        <li>
                            ${p.descripcion || p.clavepermiso}
                        </li>
                    `;
                });

                html += `
                    </ul>
                `;
            } else {
                html += `
                    <div class="text-muted mb-2">
                        Este rol no tiene permisos asignados.
                    </div>
                `;
            }

            if (permisosNoPuede.length > 0) {
                html += `
                    <div class="fw-semibold mb-1">
                        <i class="fas fa-times text-danger me-1"></i>
                        No puede:
                    </div>
                    <ul class="mb-0 ps-3">
                `;

                permisosNoPuede.forEach(p => {
                    html += `
                        <li>
                            ${p.descripcion || p.clavepermiso}
                        </li>
                    `;
                });

                html += `
                    </ul>
                `;
            }

            html += `
                </div>
            `;

            contenedor.html(html);
        },
        error: function() {
            contenedor.html(
                '<div class="small text-danger">Error al consultar los permisos del rol.</div>'
            );
        }
    });
}

$(document).on('change', '.select-rol-app', function() {

    const idAplicacion = $(this).data('idaplicacion');
    const idTipoUsuario = $(this).val();

    cargarPermisosRol(
        idAplicacion,
        idTipoUsuario
    );

});