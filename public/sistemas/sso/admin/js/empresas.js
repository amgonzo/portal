let modoEmpresa = "nuevo";
let permisosUsuario = [];

$(document).ready(function() {
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
            if (response.status === 'ok' && response.usuario) {
                permisosUsuario = response.permisos || [];

                if (!tienePermiso('empresas_crear')) {
                    $('#btnNuevaEmpresa').hide();
                } else {
                    $('#btnNuevaEmpresa').show();
                }

                cargarEmpresas();
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

function limpiarModalEmpresa() {
    modoEmpresa = "nuevo";

    $("#edit_empresa_id").val("");
    $("#empresa_nombre").val("");
    $("#empresa_razon_social").val("");
    $("#empresa_cuit").val("");
    $("#empresa_slug").val("");
    $("#empresa_db").val("");

    $(".modal-title").text("Crear Nueva Empresa");
    $("#btnGuardarEmpresa").text("Crear Empresa");
}

function abrirNuevaEmpresa() {limpiarModalEmpresa();
    $("#ModalEmpresa").modal("show");
}

function cargarEmpresas() {
    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/empresas/listar_empresas.php",
        headers: obtenerHeadersSSO(),

        success: function(res) {
            const respuesta = (typeof res === 'string') ? JSON.parse(res) : res;

            if (respuesta.status !== "ok") {
                toast(
                    respuesta.msg || "No se pudieron cargar las empresas",
                    "error"
                );
                return;
            }

            let html = "";

            const puedeEditar = tienePermiso('empresas_editar');
            const puedeBorrar = tienePermiso('empresas_borrar');

            const puedeGestionarAplicaciones = tienePermiso('apps_asociar');
            const puedeGestionarConexiones = tienePermiso('conexiones_gestionar');

            respuesta.data.forEach(e => {

                /* =====================================================
                   APLICACIONES DE LA EMPRESA
                   ===================================================== */

                let aplicacionesHtml = '';

                if (
                    Array.isArray(e.aplicaciones) &&
                    e.aplicaciones.length > 0
                ) {

                    aplicacionesHtml = e.aplicaciones
                        .map(app =>
                            `<span class="badge bg-secondary me-1 mb-1">${app}</span>`
                        )
                        .join('');

                } else {

                    aplicacionesHtml =
                        '<span class="text-muted">Sin aplicaciones</span>';
                }

                /* =====================================================
                   BOTÓN EDITAR
                   ===================================================== */

                let btnEditar = puedeEditar
                    ? `<button
                        class="btn btn-sm btn-info"
                        title="Editar"
                        onclick="editarEmpresa(${e.idempresa})"
                    >
                           <i class="fas fa-edit"></i>
                       </button>`
                    : '';

                /* =====================================================
                   BOTÓN APLICACIONES
                   ===================================================== */

                let btnAplicaciones = puedeGestionarAplicaciones
                ? `<button
                    class="btn btn-sm btn-primary"
                    title="Aplicaciones"
                    onclick="abrirAplicacionesEmpresa(
                        ${e.idempresa},
                        '${String(e.nombre).replace(/'/g, "\\'")}'
                    )"
                >
                    <i class="fas fa-th-large"></i>
                </button>`
                : '';

                let btnConexiones = puedeGestionarConexiones
                ? `
                    <button
                        class="btn btn-sm btn-dark"
                        title="Conexiones"
                        onclick="abrirConexionesEmpresa(
                            ${e.idempresa},
                            '${String(e.nombre).replace(/'/g, "\\'")}'
                        )"
                    >
                        <i class="fas fa-database"></i>
                    </button>
                `
                : '';

                /* =====================================================
                   BOTÓN ESTADO
                   ===================================================== */

                let btnEstado = '';

                if (puedeBorrar) {

                    if (e.activo == 0) {

                        btnEstado = `
                            <button
                                class="btn btn-sm btn-success"
                                title="Activar"
                                onclick="cambiarEstadoEmpresa(
                                    ${e.idempresa},
                                    'alta'
                                )"
                            >
                                <i class="fas fa-check"></i>
                            </button>
                        `;

                    } else {

                        btnEstado = `
                            <button
                                class="btn btn-sm btn-danger"
                                title="Desactivar"
                                onclick="cambiarEstadoEmpresa(
                                    ${e.idempresa},
                                    'baja'
                                )"
                            >
                                <i class="fas fa-ban"></i>
                            </button>
                        `;
                    }
                }

                /* =====================================================
                   FILA
                   ===================================================== */

                html += `
                    <tr>

                        <td>
                            <strong>${e.nombre}</strong><br>
                            <small class="text-muted">
                                ${e.razon_social ?? 'Sin razón social'}
                            </small>
                        </td>

                        <td>
                            ${e.cuit ?? '-'}
                        </td>

                        <td>
                            <code>${e.slug}</code><br>
                            <small class="text-muted">
                                ${e.db_nombre}
                            </small>
                        </td>

                        <td>
                            ${aplicacionesHtml}
                        </td>

                        <td class="text-center">
                            ${
                                e.activo == 1
                                    ? '<span class="badge bg-success">Activo</span>'
                                    : '<span class="badge bg-danger">Inactivo</span>'
                            }
                        </td>

                        <td>
                            <div class="d-flex align-items-center gap-1">
                                ${btnEditar}
                                ${btnAplicaciones}
                                ${btnConexiones}
                                ${btnEstado}
                            </div>
                        </td>

                    </tr>
                `;
            });

            $("#listaEmpresas").html(html);
        },

        error: function() {
            toast(
                'Error de conexión al cargar las empresas',
                'error'
            );
        }
    });
}


/* =========================================================
   EDITAR EMPRESA
   ========================================================= */

function editarEmpresa(id) {

    modoEmpresa = "editar";

    $.ajax({
        type: "GET",
        url: API_BASE + "/sso/empresas/obtener_empresa.php",
        data: {
            id: id
        },
        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status !== "ok") {

                toast(
                    respuesta.msg || "No se pudo obtener la empresa",
                    "error"
                );

                return;
            }

            const e = respuesta.data;

            $("#edit_empresa_id").val(e.idempresa);
            $("#empresa_nombre").val(e.nombre);
            $("#empresa_razon_social").val(e.razon_social);
            $("#empresa_cuit").val(e.cuit);
            $("#empresa_slug").val(e.slug);
            $("#empresa_db").val(e.db_nombre);

            $(".modal-title").text(
                "Editar Empresa: " + e.nombre
            );

            $("#btnGuardarEmpresa").text(
                "Guardar Cambios"
            );

            $("#ModalEmpresa").modal("show");
        },

        error: function() {

            toast(
                'Error de conexión al obtener la empresa',
                'error'
            );
        }
    });
}


/* =========================================================
   GUARDAR EMPRESA
   ========================================================= */

function guardarEmpresa() {

    const datos = {

        idempresa:
            modoEmpresa === "editar"
                ? $("#edit_empresa_id").val()
                : "",

        nombre:
            $("#empresa_nombre").val().trim(),

        razon_social:
            $("#empresa_razon_social").val().trim(),

        cuit:
            $("#empresa_cuit").val().trim(),

        slug:
            $("#empresa_slug").val().trim(),

        db_nombre:
            $("#empresa_db").val().trim()
    };

    if (
        !datos.nombre ||
        !datos.slug ||
        !datos.db_nombre
    ) {

        toast(
            'Faltan campos obligatorios (Nombre, Slug y Base de Datos)',
            'warning'
        );

        return;
    }

    $.ajax({

        url:
            API_BASE +
            "/sso/empresas/guardar_empresa.php",

        type: "POST",

        data: datos,

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status === "ok") {

                $("#ModalEmpresa").modal("hide");

                cargarEmpresas();

                toast(
                    modoEmpresa === "nuevo"
                        ? "Empresa creada con éxito"
                        : "Empresa actualizada correctamente",
                    "success"
                );

            } else {

                toast(
                    respuesta.msg ||
                    "Error al guardar la empresa",
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

function cambiarEstadoEmpresa(id, accion) {

    const titulo =
        accion === 'alta'
            ? '¿Activar empresa?'
            : '¿Desactivar empresa?';

    const color =
        accion === 'alta'
            ? '#28a745'
            : '#d33';

    Swal.fire({

        title: titulo,

        icon: 'question',

        showCancelButton: true,

        confirmButtonColor: color,

        confirmButtonText: 'Sí, confirmar',

        cancelButtonText: 'Cancelar'

    }).then((result) => {

        if (!result.isConfirmed) {
            return;
        }

        $.ajax({

            type: "POST",

            url:
                API_BASE +
                "/sso/empresas/baja_empresa.php",

            data: {
                id: id,
                tarea: accion
            },

            headers: obtenerHeadersSSO(),

            success: function(res) {

                const respuesta =
                    (typeof res === 'string')
                        ? JSON.parse(res)
                        : res;

                if (respuesta.status === "ok") {

                    cargarEmpresas();

                    toast(
                        'Estado actualizado correctamente',
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

            error: function() {

                toast(
                    'Error de conexión con el servidor',
                    'error'
                );
            }
        });
    });
}


/* =========================================================
   ABRIR APLICACIONES DE EMPRESA
   ========================================================= */

function abrirAplicacionesEmpresa(
    idEmpresa,
    nombreEmpresa
) {

    $("#aplicaciones_empresa_id").val(
        idEmpresa
    );

    $("#aplicaciones_empresa_nombre").text(
        "Empresa: " + nombreEmpresa
    );

    $("#listaAplicacionesEmpresa").html(`
        <div class="text-center p-4">
            <i class="fas fa-spinner fa-spin"></i>
            Cargando aplicaciones...
        </div>
    `);

    $("#ModalAplicacionesEmpresa").modal("show");

    cargarAplicacionesEmpresa(idEmpresa);
}


/* =========================================================
   CARGAR APLICACIONES DE EMPRESA
   ========================================================= */

function cargarAplicacionesEmpresa(idEmpresa) {

    $.ajax({

        type: "GET",

        url:
            API_BASE +
            "/sso/empresas/listar_aplicaciones_empresa.php",

        data: {
            idempresa: idEmpresa
        },

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status !== "ok") {

                $("#listaAplicacionesEmpresa").html(`
                    <div class="alert alert-danger">
                        ${
                            respuesta.msg ||
                            "No se pudieron cargar las aplicaciones"
                        }
                    </div>
                `);

                return;
            }

            const aplicaciones =
                respuesta.data?.aplicaciones || [];

            if (aplicaciones.length === 0) {

                $("#listaAplicacionesEmpresa").html(`
                    <div class="alert alert-info">
                        No hay aplicaciones activas disponibles.
                    </div>
                `);

                return;
            }

            let html = `
                <div class="list-group">
            `;

            aplicaciones.forEach(app => {

                const checked =
                    parseInt(app.asignada) === 1
                        ? "checked"
                        : "";

                const icono =
                    app.icono ||
                    "fas fa-cubes";

                html += `
                    <label
                        class="list-group-item d-flex align-items-center"
                        style="cursor:pointer;"
                    >

                        <input
                            type="checkbox"
                            class="form-check-input me-3 check-aplicacion-empresa"
                            value="${app.idaplicacion}"
                            ${checked}
                        >

                        <i
                            class="${icono} me-3"
                            style="width:25px;"
                        ></i>

                        <div>

                            <strong>
                                ${app.nombre}
                            </strong>

                            <br>

                            <small class="text-muted">
                                ${app.slug}
                            </small>

                        </div>

                    </label>
                `;
            });

            html += `
                </div>
            `;

            $("#listaAplicacionesEmpresa").html(html);
        },

        error: function(xhr) {

            console.error(
                xhr.responseText
            );

            $("#listaAplicacionesEmpresa").html(`
                <div class="alert alert-danger">
                    Error de conexión al cargar las aplicaciones.
                </div>
            `);
        }
    });
}


/* =========================================================
   GUARDAR APLICACIONES DE EMPRESA
   ========================================================= */

function guardarAplicacionesEmpresa() {

    const idEmpresa =
        parseInt(
            $("#aplicaciones_empresa_id").val() || 0
        );

    if (idEmpresa <= 0) {

        toast(
            "No se seleccionó una empresa",
            "warning"
        );

        return;
    }

    const aplicaciones = [];

    $(".check-aplicacion-empresa:checked")
        .each(function() {

            const idAplicacion =
                parseInt(
                    $(this).val() || 0
                );

            if (idAplicacion > 0) {
                aplicaciones.push(
                    idAplicacion
                );
            }
        });

    const btn =
        $("#btnGuardarAplicacionesEmpresa");

    btn.prop("disabled", true);

    $.ajax({

        type: "POST",

        url:
            API_BASE +
            "/sso/empresas/guardar_aplicaciones_empresa.php",

        data: {
            idempresa: idEmpresa,
            aplicaciones: aplicaciones
        },

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status === "ok") {

                $("#ModalAplicacionesEmpresa")
                    .modal("hide");

                /*
                 * Recargamos la tabla para que
                 * se actualicen las aplicaciones
                 * mostradas en la empresa.
                 */
                cargarEmpresas();

                toast(
                    respuesta.msg ||
                    "Aplicaciones actualizadas correctamente",
                    "success"
                );

            } else {

                toast(
                    respuesta.msg ||
                    "No se pudieron guardar las aplicaciones",
                    "error"
                );
            }
        },

        error: function(xhr) {

            console.error(
                xhr.responseText
            );

            let mensaje =
                "Error de conexión al guardar las aplicaciones";

            try {

                const respuesta =
                    JSON.parse(
                        xhr.responseText
                    );

                if (respuesta.msg) {
                    mensaje = respuesta.msg;
                }

            } catch (e) {
                // Mantener mensaje genérico
            }

            toast(
                mensaje,
                "error"
            );
        },

        complete: function() {

            btn.prop(
                "disabled",
                false
            );
        }
    });
}

function abrirConexionesEmpresa(idempresa, nombreEmpresa) {

    $('#conexiones_empresa_id').val(idempresa);
    $('#conexiones_empresa_nombre').text(nombreEmpresa);

    $('#ModalConexionesEmpresa').modal('show');

    cargarConexionesEmpresa();
}

function cargarConexionesEmpresa() {

    const idempresa = $('#conexiones_empresa_id').val();

    if (!idempresa) {
        return;
    }

    $('#listaConexionesEmpresa').html(`
        <div class="text-center py-3">
            <i class="fas fa-spinner fa-spin"></i>
            Cargando conexiones...
        </div>
    `);

    $.ajax({
        url: API_BASE + '/sso/empresas/listar_conexiones_empresa.php',

        type: 'GET',

        data: {
            idempresa: idempresa
        },

        headers: obtenerHeadersSSO(),

        dataType: 'json',

        success: function (respuesta) {

            if (respuesta.status !== 'ok') {

                $('#listaConexionesEmpresa').html(`
                    <div class="alert alert-danger">
                        ${respuesta.msg || 'No se pudieron cargar las conexiones.'}
                    </div>
                `);

                return;
            }

            const conexiones = respuesta.conexiones || [];

            if (conexiones.length === 0) {

                $('#listaConexionesEmpresa').html(`
                    <div class="alert alert-info mb-0">
                        Esta empresa no tiene conexiones configuradas.
                    </div>
                `);

                return;
            }

            let html = '';

            conexiones.forEach(function (conexion) {

                const estado = Number(conexion.activo) === 1
                    ? '<span class="badge bg-success">Activa</span>'
                    : '<span class="badge bg-secondary">Inactiva</span>';

                html += `
                    <div class="card mb-2">
                        <div class="card-body py-2">

                            <div class="row align-items-center">

                                <div class="col-md-2">
                                    <strong>${conexion.clave}</strong><br>
                                    <small class="text-muted">
                                        ${conexion.nombre}
                                    </small>
                                </div>

                                <div class="col-md-1">
                                    <span class="badge bg-dark">
                                        ${conexion.tipo}
                                    </span>
                                </div>

                                <div class="col-md-2">
                                    ${conexion.host}:${conexion.puerto}
                                </div>

                                <div class="col-md-2">
                                    ${conexion.db_nombre}
                                </div>

                                <div class="col-md-2">
                                    ${conexion.db_usuario}
                                </div>

                                <div class="col-md-1">
                                    ${estado}
                                </div>

                                <div class="col-md-2 text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        onclick="editarConexionEmpresa(${conexion.idempresa_conexion})"
                                        title="Editar conexión"
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>

                            </div>

                        </div>
                    </div>
                `;
            });

            $('#listaConexionesEmpresa').html(html);
        },

        error: function (xhr) {

            console.error(xhr.responseText);

            toast(
                'Error al consultar las conexiones de la empresa',
                'error'
            );
        }
    });
}
function nuevaConexionEmpresa() {

    $('#conexion_id').val('');
    $('#conexion_clave').val('');
    $('#conexion_nombre').val('');
    $('#conexion_tipo').val('MYSQL');
    $('#conexion_host').val('');
    $('#conexion_puerto').val('3306');
    $('#conexion_db_nombre').val('');
    $('#conexion_db_usuario').val('');
    $('#conexion_db_password').val('');
    activo: $('#conexion_activo').prop('checked') ? 1 : 0

    $('#formularioConexionEmpresa').show();
}

function guardarConexionEmpresa() {

    const idEmpresa = $('#conexiones_empresa_id').val();

    if (!idEmpresa) {
        Swal.fire('Error', 'No se seleccionó una empresa.', 'error');
        return;
    }

    const idConexion = $('#conexion_id').val();

    const datos = {
        idempresa: idEmpresa,
        idempresa_conexion: idConexion,
        clave: $('#conexion_clave').val().trim().toUpperCase(),
        nombre: $('#conexion_nombre').val().trim(),
        tipo: $('#conexion_tipo').val(),
        host: $('#conexion_host').val().trim(),
        puerto: $('#conexion_puerto').val().trim(),
        db_nombre: $('#conexion_db_nombre').val().trim(),
        db_usuario: $('#conexion_db_usuario').val().trim(),
        db_password: $('#conexion_db_password').val(),
        activo: $('#conexion_activo').is(':checked') ? 1 : 0
    };

    if (!datos.clave) {
        Swal.fire(
            'Falta la clave',
            'Debe indicar la clave de uso de la conexión.',
            'warning'
        );
        return;
    }

    if (!datos.nombre) {
        Swal.fire('Falta información', 'Ingresá el nombre de la conexión.', 'warning');
        return;
    }

    if (!datos.host) {
        Swal.fire('Falta información', 'Ingresá el host.', 'warning');
        return;
    }

    if (!datos.puerto) {
        Swal.fire('Falta información', 'Ingresá el puerto.', 'warning');
        return;
    }

    if (!datos.db_nombre) {
        Swal.fire('Falta información', 'Ingresá el nombre de la base de datos.', 'warning');
        return;
    }

    if (!datos.db_usuario) {
        Swal.fire('Falta información', 'Ingresá el usuario de la base de datos.', 'warning');
        return;
    }

    // Al crear una conexión la contraseña es obligatoria.
    // Al editar puede quedar vacía para conservar la anterior.
    if (!datos.idempresa_conexion && !datos.db_password) {
        Swal.fire('Falta información', 'Ingresá la contraseña de la base de datos.', 'warning');
        return;
    }

    $.ajax({
        url: API_BASE + '/sso/empresas/guardar_conexion_empresa.php',
        type: 'POST',
        data: datos,
        dataType: 'json',

        headers: obtenerHeadersSSO(),

        success: function (respuesta) {

            if (respuesta.status !== 'ok') {
                Swal.fire(
                    'Error',
                    respuesta.msg || 'No se pudo guardar la conexión.',
                    'error'
                );
                return;
            }

            toast(
                respuesta.msg || 'Conexión guardada correctamente.'
            );

            $('#formularioConexionEmpresa').hide();

            cargarConexionesEmpresa();
        },

        error: function (xhr) {

            let mensaje = 'No se pudo guardar la conexión.';

            if (xhr.responseJSON && xhr.responseJSON.msg) {
                mensaje = xhr.responseJSON.msg;
            }

            Swal.fire('Error', mensaje, 'error');
        }
    });
}

function cancelarEdicionConexion() {

    $('#formularioConexionEmpresa').hide();

    $('#conexion_id').val('');
    $('#conexion_clave').val('');
    $('#conexion_nombre').val('');
    $('#conexion_tipo').val('MYSQL');
    $('#conexion_host').val('');
    $('#conexion_puerto').val('3306');
    $('#conexion_db_nombre').val('');
    $('#conexion_db_usuario').val('');
    $('#conexion_db_password').val('');
    $('#conexion_activo').prop('checked', true);

    $('#tituloFormularioConexion').text('Nueva conexión');
}

function editarConexionEmpresa(idConexion) {

    $.ajax({

        type: 'GET',

        url:
            API_BASE +
            '/sso/empresas/obtener_conexion_empresa.php',

        data: {
            idempresa_conexion: idConexion
        },

        headers: obtenerHeadersSSO(),

        dataType: 'json',

        success: function(respuesta) {

            if (respuesta.status !== 'ok') {

                Swal.fire(
                    'Error',
                    respuesta.msg ||
                    'No se pudo obtener la conexión.',
                    'error'
                );

                return;
            }

            const conexion = respuesta.conexion;

            $('#conexion_id').val(
                conexion.idempresa_conexion
            );
            
            $('#conexion_clave').val(
                conexion.clave
            );

            $('#conexion_nombre').val(
                conexion.nombre
            );

            $('#conexion_tipo').val(
                conexion.tipo
            );

            $('#conexion_host').val(
                conexion.host
            );

            $('#conexion_puerto').val(
                conexion.puerto
            );

            $('#conexion_db_nombre').val(
                conexion.db_nombre
            );

            $('#conexion_db_usuario').val(
                conexion.db_usuario
            );

            /*
             * La contraseña nunca se devuelve.
             * Si se deja vacía al guardar,
             * se conserva la existente.
             */
            $('#conexion_db_password').val('');

            $('#conexion_activo').prop(
                'checked',
                Number(conexion.activo) === 1
            );

            $('#tituloFormularioConexion').text(
                'Editar conexión'
            );

            $('#formularioConexionEmpresa').show();
        },

        error: function(xhr) {

            console.error(
                xhr.responseText
            );

            let mensaje =
                'Error al obtener la conexión.';

            if (
                xhr.responseJSON &&
                xhr.responseJSON.msg
            ) {
                mensaje =
                    xhr.responseJSON.msg;
            }

            Swal.fire(
                'Error',
                mensaje,
                'error'
            );
        }
    });
}