// =========================================================
// CONFIGURACIÓN DEL SISTEMA - FICHAJES
// =========================================================

let tablaConfiguracion = null;


// =========================================================
// INICIO
// =========================================================

$(document).ready(function () {

    const token = localStorage.getItem('sso_token');

    if (!token) {
        window.location.href = window.SSO_LOGIN_URL;
        return;
    }

    cargarConfiguracion();

});


// =========================================================
// CARGAR CONFIGURACIONES
// =========================================================

function cargarConfiguracion() {

    $.ajax({

        url: API_BASE + '/fichajes/configuracion/obtener_configuracion.php',

        type: 'GET',

        dataType: 'json',

        cache: false,

        headers: obtenerHeadersSSO(),

        success: function (response) {

            if (response.status !== 'ok') {

                toast(
                    'Al recuperar configuraciones: ' +
                    (response.msg || 'Error desconocido.'),
                    'error'
                );

                return;
            }

            mostrarConfiguraciones(
                response.data || []
            );

        },

        error: function (xhr) {

            let msg =
                'Error de conexión al consultar las configuraciones.';

            if (
                xhr.status === 401 ||
                xhr.status === 403
            ) {
                msg =
                    'No autorizado para consultar las configuraciones.';
            }

            toast(msg, 'error');

        }

    });

}


// =========================================================
// MOSTRAR TABLA
// =========================================================

function mostrarConfiguraciones(configuraciones) {

    const tbody = $('#listaConfiguracion');

    tbody.empty();


    if (!Array.isArray(configuraciones) ||
        configuraciones.length === 0) {

        tbody.append(`
            <tr>
                <td colspan="6" class="text-center text-muted py-4">
                    <i class="fas fa-info-circle me-1"></i>
                    No hay configuraciones registradas.
                </td>
            </tr>
        `);

        return;
    }


    configuraciones.forEach(function (config) {

        const valor = escaparHTML(
            config.valor ?? ''
        );

        const clave = escaparHTML(
            config.clave ?? ''
        );

        const descripcion = escaparHTML(
            config.descripcion ?? ''
        );

        const tipo = escaparHTML(
            config.tipo ?? ''
        );

        const fecha = formatearFecha(
            config.fecha_actualizacion
        );


        tbody.append(`

            <tr>

                <td>
                    <span class="fw-semibold">
                        ${clave}
                    </span>
                </td>


                <td>
                    <span class="text-muted">
                        ${descripcion || '-'}
                    </span>
                </td>


                <td class="text-center">

                    <span class="badge bg-secondary">
                        ${valor || '-'}
                    </span>

                </td>


                <td class="text-center">

                    ${badgeTipo(config.tipo)}

                </td>


                <td>
                    ${fecha}
                </td>


                <td class="text-center">

                    <div class="btn-group btn-group-sm">

                        <button
                            type="button"
                            class="btn btn-outline-primary"
                            title="Editar"
                            onclick="editarConfiguracion(
                                ${Number(config.idconfiguracion)}
                            )">

                            <i class="fas fa-edit"></i>

                        </button>


                        <button
                            type="button"
                            class="btn btn-outline-success"
                            title="Aplicar configuración a los Agents"
                            onclick="aplicarConfiguracion(
                                ${Number(config.idconfiguracion)},
                                '${escaparJS(config.clave ?? '')}'
                            )">

                            <i class="fas fa-sync-alt"></i>

                        </button>

                    </div>

                </td>

            </tr>

        `);

    });

}


// =========================================================
// NUEVA CONFIGURACIÓN
// =========================================================

function abrirNuevaConfiguracion() {

    limpiarFormularioConfiguracion();


    $('#tituloModalConfiguracion').text(
        'Nueva configuración'
    );


    $('#config_clave')
        .prop('readonly', false);


    $('#avisoAplicacionConfiguracion')
        .removeClass('d-none');


    abrirModalConfiguracion();

}


// =========================================================
// EDITAR CONFIGURACIÓN
// =========================================================

function editarConfiguracion(id) {

    $.ajax({

        url: API_BASE +
            '/fichajes/configuracion/obtener_configuracion.php',

        type: 'GET',

        dataType: 'json',

        cache: false,

        headers: obtenerHeadersSSO(),

        success: function (response) {

            if (response.status !== 'ok') {

                toast(
                    response.msg ||
                    'No se pudo recuperar la configuración.',
                    'error'
                );

                return;
            }


            const configuraciones =
                response.data || [];


            const config =
                configuraciones.find(function (item) {

                    return Number(
                        item.idconfiguracion
                    ) === Number(id);

                });


            if (!config) {

                toast(
                    'No se encontró la configuración seleccionada.',
                    'error'
                );

                return;
            }


            $('#config_id').val(
                config.idconfiguracion
            );

            $('#config_clave').val(
                config.clave
            );

            $('#config_descripcion').val(
                config.descripcion || ''
            );

            $('#config_valor').val(
                config.valor
            );

            $('#config_tipo').val(
                config.tipo
            );


            $('#tituloModalConfiguracion').text(
                'Editar configuración'
            );


            /*
             * La clave de una configuración existente
             * no debería cambiarse.
             */

            $('#config_clave')
                .prop('readonly', true);


            $('#avisoAplicacionConfiguracion')
                .removeClass('d-none');


            abrirModalConfiguracion();

        },

        error: function (xhr) {

            let msg =
                'Error al consultar la configuración.';

            if (
                xhr.status === 401 ||
                xhr.status === 403
            ) {
                msg =
                    'No autorizado para realizar esta acción.';
            }

            toast(msg, 'error');

        }

    });

}


// =========================================================
// ABRIR MODAL
// =========================================================

function abrirModalConfiguracion() {

    const modalElement =
        document.getElementById(
            'ModalConfiguracion'
        );

    if (!modalElement) {
        return;
    }


    const modal =
        bootstrap.Modal.getOrCreateInstance(
            modalElement
        );

    modal.show();

}


// =========================================================
// CERRAR MODAL
// =========================================================

function cerrarModalConfiguracion() {

    const modalElement =
        document.getElementById(
            'ModalConfiguracion'
        );

    if (!modalElement) {
        return;
    }


    const modal =
        bootstrap.Modal.getInstance(
            modalElement
        );

    if (modal) {
        modal.hide();
    }

}


// =========================================================
// LIMPIAR FORMULARIO
// =========================================================

function limpiarFormularioConfiguracion() {

    $('#formConfiguracion')[0].reset();

    $('#config_id').val('');

    $('#config_tipo').val('texto');

    $('#config_clave')
        .prop('readonly', false);

}


// =========================================================
// GUARDAR CONFIGURACIÓN
// =========================================================

function guardarConfiguracion() {

    const id =
        $('#config_id').val().trim();

    const clave =
        $('#config_clave').val().trim();

    const valor =
        $('#config_valor').val().trim();

    const descripcion =
        $('#config_descripcion').val().trim();

    const tipo =
        $('#config_tipo').val();


    if (clave === '') {

        toast(
            'La clave es obligatoria.',
            'error'
        );

        $('#config_clave').focus();

        return;
    }


    if (valor === '') {

        toast(
            'El valor es obligatorio.',
            'error'
        );

        $('#config_valor').focus();

        return;
    }


    if (
        !['texto', 'entero', 'decimal', 'booleano']
            .includes(tipo)
    ) {

        toast(
            'El tipo de configuración no es válido.',
            'error'
        );

        return;
    }


    /*
     * Validaciones adicionales en el navegador.
     * El PHP vuelve a validar todo.
     */

    if (tipo === 'entero') {

        if (!/^-?\d+$/.test(valor)) {

            toast(
                'El valor debe ser un número entero.',
                'error'
            );

            $('#config_valor').focus();

            return;
        }

    }


    if (tipo === 'decimal') {

        if (
            valor === '' ||
            isNaN(Number(valor))
        ) {

            toast(
                'El valor debe ser numérico.',
                'error'
            );

            $('#config_valor').focus();

            return;
        }

    }


    if (tipo === 'booleano') {

        const valorBooleano =
            valor.toLowerCase();

        const permitidos = [
            '1',
            '0',
            'true',
            'false',
            'si',
            'sí',
            'no',
            'yes'
        ];

        if (
            !permitidos.includes(
                valorBooleano
            )
        ) {

            toast(
                'El valor booleano debe ser verdadero o falso.',
                'error'
            );

            $('#config_valor').focus();

            return;
        }

    }


    const formData =
        $('#formConfiguracion').serialize();


    const boton =
        $('#btnGuardarConfiguracion');


    boton
        .prop('disabled', true)
        .html(
            '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...'
        );


    $.ajax({

        url: API_BASE +
            '/fichajes/configuracion/guardar_configuracion.php',

        type: 'POST',

        data: formData,

        dataType: 'json',

        headers: obtenerHeadersSSO(),

        success: function (response) {

            if (response.status !== 'ok') {

                toast(
                    response.msg ||
                    'No se pudo guardar la configuración.',
                    'error'
                );

                return;
            }


            /*
             * Si hubo cambio, el PHP genera las tareas.
             */

            if (response.tarea_generada === true) {

                const cantidad =
                    Number(
                        response.tareas_generadas || 0
                    );


                if (cantidad > 0) {

                    toast(
                        'Configuración guardada y enviada a ' +
                        cantidad +
                        ' Agent' +
                        (cantidad === 1 ? '' : 's') +
                        '.',
                        'success'
                    );

                } else {

                    toast(
                        'Configuración guardada. No hay Agents activos para actualizar.',
                        'success'
                    );

                }

            } else {

                toast(
                    response.msg ||
                    'La configuración no tenía cambios.',
                    'success'
                );

            }


            cerrarModalConfiguracion();

            cargarConfiguracion();

        },

        error: function (xhr) {

            let msg =
                'No se pudo guardar la configuración.';

            if (
                xhr.status === 401 ||
                xhr.status === 403
            ) {

                msg =
                    'No autorizado para realizar esta acción.';

            } else if (
                xhr.responseJSON &&
                xhr.responseJSON.msg
            ) {

                msg =
                    xhr.responseJSON.msg;
            }


            toast(msg, 'error');

        },

        complete: function () {

            boton
                .prop('disabled', false)
                .html(
                    '<i class="fas fa-save me-2"></i>Guardar'
                );

        }

    });

}


// =========================================================
// APLICAR CONFIGURACIÓN
// =========================================================

function aplicarConfiguracion(
    id,
    clave
) {

    Swal.fire({

        title: 'Aplicar configuración',

        html:
            '¿Querés volver a enviar la configuración ' +
            '<strong>' +
            escaparHTML(clave) +
            '</strong> ' +
            'a los Agents activos?',

        icon: 'question',

        showCancelButton: true,

        confirmButtonText:
            '<i class="fas fa-sync-alt me-1"></i> Aplicar',

        cancelButtonText:
            'Cancelar',

        reverseButtons: true

    }).then(function (result) {

        if (!result.isConfirmed) {
            return;
        }


        const boton =
            $(
                `button[onclick*="aplicarConfiguracion(${id}"]`
            );


        boton
            .prop('disabled', true)
            .html(
                '<i class="fas fa-spinner fa-spin"></i>'
            );


        $.ajax({

            url: API_BASE +
                '/fichajes/configuracion/aplicar_configuracion.php',

            type: 'POST',

            data: {
                idconfiguracion: id
            },

            dataType: 'json',

            headers: obtenerHeadersSSO(),

            success: function (response) {

                if (response.status !== 'ok') {

                    toast(
                        response.msg ||
                        'No se pudo aplicar la configuración.',
                        'error'
                    );

                    return;
                }


                const cantidad =
                    Number(
                        response.tareas_generadas || 0
                    );


                if (cantidad > 0) {

                    toast(
                        'Configuración enviada a ' +
                        cantidad +
                        ' Agent' +
                        (cantidad === 1 ? '' : 's') +
                        '.',
                        'success'
                    );

                } else {

                    toast(
                        'No hay Agents activos para actualizar.',
                        'warning'
                    );

                }

            },

            error: function (xhr) {

                let msg =
                    'No se pudo aplicar la configuración.';

                if (
                    xhr.status === 401 ||
                    xhr.status === 403
                ) {

                    msg =
                        'No autorizado para realizar esta acción.';

                } else if (
                    xhr.responseJSON &&
                    xhr.responseJSON.msg
                ) {

                    msg =
                        xhr.responseJSON.msg;
                }


                toast(msg, 'error');

            },

            complete: function () {

                boton
                    .prop('disabled', false)
                    .html(
                        '<i class="fas fa-sync-alt"></i>'
                    );

            }

        });

    });

}


// =========================================================
// BADGE DEL TIPO
// =========================================================

function badgeTipo(tipo) {

    switch (tipo) {

        case 'entero':

            return `
                <span class="badge bg-primary">
                    Entero
                </span>
            `;


        case 'decimal':

            return `
                <span class="badge bg-info text-dark">
                    Decimal
                </span>
            `;


        case 'booleano':

            return `
                <span class="badge bg-warning text-dark">
                    Booleano
                </span>
            `;


        case 'texto':

            return `
                <span class="badge bg-secondary">
                    Texto
                </span>
            `;


        default:

            return `
                <span class="badge bg-dark">
                    ${escaparHTML(tipo || '')}
                </span>
            `;
    }

}


// =========================================================
// FORMATEAR FECHA
// =========================================================

function formatearFecha(fecha) {

    if (!fecha) {
        return '-';
    }


    const valor =
        String(fecha).replace(
            ' ',
            'T'
        );


    const date =
        new Date(valor);


    if (isNaN(date.getTime())) {
        return escaparHTML(fecha);
    }


    const dia =
        String(
            date.getDate()
        ).padStart(2, '0');

    const mes =
        String(
            date.getMonth() + 1
        ).padStart(2, '0');

    const anio =
        date.getFullYear();

    const hora =
        String(
            date.getHours()
        ).padStart(2, '0');

    const minuto =
        String(
            date.getMinutes()
        ).padStart(2, '0');


    return `
        ${dia}/${mes}/${anio}
        ${hora}:${minuto}
    `;
}


// =========================================================
// ESCAPAR HTML
// =========================================================

function escaparHTML(valor) {

    return $('<div>')
        .text(valor ?? '')
        .html();

}


// =========================================================
// ESCAPAR PARA JAVASCRIPT
// =========================================================

function escaparJS(valor) {

    return String(valor ?? '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/\r/g, '\\r')
        .replace(/\n/g, '\\n');

}