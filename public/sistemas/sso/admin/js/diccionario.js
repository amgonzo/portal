let modoDiccionario = "nuevo";
let permisosUsuario = [];

$(document).ready(function() {

    // =====================================================
    // 1. VALIDAR TOKEN
    // =====================================================

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = '../auth/login.php';
        return;
    }

    // =====================================================
    // 2. OBTENER USUARIO Y PERMISOS
    // =====================================================

    $.ajax({
        url: API_BASE + '/sso/auth/me.php',
        type: 'GET',
        headers: obtenerHeadersSSO(),
        success: function(response) {

            if (response.status === 'ok' && response.usuario) {

                permisosUsuario = response.permisos || [];

                // =========================================
                // BOTÓN NUEVO TÉRMINO
                // =========================================

                if (!tienePermiso('diccionario_crear')) {
                    $('#btnNuevoDiccionario').hide();
                } else {
                    $('#btnNuevoDiccionario').show();
                }

                // =========================================
                // CARGAR REGISTROS
                // =========================================

                cargarDiccionario();

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
// LIMPIAR MODAL
// =========================================================

function limpiarModalDiccionario() {

    modoDiccionario = "nuevo";

    $("#edit_dic_id").val("");
    $("#dic_clave").val("");
    $("#dic_valor").val("");
    $("#dic_descripcion").val("");

    $(".modal-title").text("Crear Nuevo Término");
    $("#btnGuardar").text("Crear Término");
}


// =========================================================
// NUEVO TÉRMINO
// =========================================================

function abrirNuevo() {

    limpiarModalDiccionario();

    $("#ModalDiccionario").modal("show");
}


// =========================================================
// LISTAR DICCIONARIO
// =========================================================

function cargarDiccionario() {

    $.ajax({

        type: "GET",

        url: API_BASE + "/sso/diccionario/listar_diccionario.php",

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status === "ok") {

                let html = "";

                const puedeEditar =
                    tienePermiso('diccionario_editar');

                const puedeBorrar =
                    tienePermiso('diccionario_borrar');

                respuesta.data.forEach(item => {

                    // =====================================
                    // BOTÓN EDITAR
                    // =====================================

                    let btnEditar = puedeEditar
                        ? `
                            <button
                                class="btn btn-sm btn-info me-1"
                                onclick="editar(${item.iddiccionario})"
                                title="Editar"
                            >
                                <i class="fas fa-edit"></i>
                            </button>
                          `
                        : '';


                    // =====================================
                    // BOTÓN ESTADO
                    // =====================================

                    let btnEstado = '';

                    if (puedeBorrar) {

                        if (item.activo == 0) {

                            btnEstado = `
                                <button
                                    class="btn btn-sm btn-success"
                                    title="Dar de Alta"
                                    onclick="cambiarEstado(${item.iddiccionario}, 'alta')"
                                >
                                    <i class="fas fa-check"></i>
                                </button>
                            `;

                        } else {

                            btnEstado = `
                                <button
                                    class="btn btn-sm btn-danger"
                                    title="Dar de Baja"
                                    onclick="cambiarEstado(${item.iddiccionario}, 'baja')"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            `;
                        }
                    }


                    // =====================================
                    // FILA
                    // =====================================

                    html += `
                        <tr>

                            <td>
                                <strong>
                                    ${item.clave ?? '-'}
                                </strong>
                            </td>

                            <td>
                                ${item.valor ?? '-'}
                            </td>

                            <td>
                                ${item.descripcion ?? '-'}
                            </td>

                            <td class="text-center">

                                ${
                                    item.activo == 1

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
                });

                $("#listaDiccionario").html(html);
            }
        },

        error: function() {

            toast(
                'Error al obtener el diccionario',
                'error'
            );
        }
    });
}


// =========================================================
// EDITAR TÉRMINO
// =========================================================

function editar(id) {

    modoDiccionario = "editar";

    $.ajax({

        type: "GET",

        url: API_BASE + "/sso/diccionario/obtener_diccionario.php",

        data: {
            id: id
        },

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status === "ok") {

                const item = respuesta.data;

                $("#edit_dic_id")
                    .val(item.iddiccionario);

                $("#dic_clave")
                    .val(item.clave);

                $("#dic_valor")
                    .val(item.valor);

                $("#dic_descripcion")
                    .val(item.descripcion);

                $(".modal-title")
                    .text(
                        "Editar Término: " +
                        item.clave
                    );

                $("#btnGuardar")
                    .text("Guardar Cambios");

                $("#ModalDiccionario")
                    .modal("show");
            }
        },

        error: function() {

            toast(
                'Error al obtener el término',
                'error'
            );
        }
    });
}


// =========================================================
// GUARDAR
// =========================================================

function guardar() {

    const datos = {

        id:
            (modoDiccionario === "editar")
                ? $("#edit_dic_id").val()
                : "",

        clave:
            $("#dic_clave")
                .val()
                .trim(),

        valor:
            $("#dic_valor")
                .val()
                .trim(),

        descripcion:
            $("#dic_descripcion")
                .val()
                .trim()
    };


    // =====================================================
    // CAMPOS OBLIGATORIOS
    // =====================================================

    if (!datos.clave || !datos.valor) {

        toast(
            'Faltan campos obligatorios (Clave y Valor)',
            'warning'
        );

        return;
    }


    // =====================================================
    // GUARDAR
    // =====================================================

    $.ajax({

        url:
            API_BASE +
            "/sso/diccionario/guardar_diccionario.php",

        type: "POST",

        data: datos,

        headers: obtenerHeadersSSO(),

        success: function(res) {

            const respuesta =
                (typeof res === 'string')
                    ? JSON.parse(res)
                    : res;

            if (respuesta.status === "ok") {

                $("#ModalDiccionario")
                    .modal("hide");

                cargarDiccionario();

                toast(

                    modoDiccionario === "nuevo"
                        ? "Término creado con éxito"
                        : "Término actualizado correctamente",

                    "success"
                );

            } else {

                toast(
                    respuesta.msg ||
                    "Error al guardar el término",
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

function cambiarEstado(id, accion) {

    const titulo =
        accion === 'alta'
            ? '¿Dar de alta?'
            : '¿Dar de baja?';

    const texto =
        accion === 'alta'
            ? 'El término volverá a estar visible.'
            : 'El término dejará de estar disponible.';

    const color =
        accion === 'alta'
            ? '#28a745'
            : '#d33';


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

                url:
                    API_BASE +
                    "/sso/diccionario/baja_diccionario.php",

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

                        cargarDiccionario();

                        toast(
                            'El estado del término ha sido actualizado',
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
        }
    });
}