// js/configuracion_sistema.js

$(document).ready(function() {
    // 0. VALIDACIÓN DE SEGURIDAD SSO INICIAL (Igual que en el dashboard)
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    // Carga inicial automática de variables apenas está listo el DOM
    cargarConfiguracion();
});

function cargarConfiguracion() {
    $.ajax({
        url: API_BASE + '/ctacte/configuracion/obtener_configuracion.php',
        type: 'GET',
        dataType: 'json',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        },
        success: function(response) {
            if (response.status === 'ok') {
                $('#config_ultima_sincro').val(response.data.ultima_sincronizacion);
                $('#config_porcentaje_defecto').val(response.data.porcentaje_descuento_default ?? 30);
            } else {
                toast('Al recuperar configuraciones: ' + response.msg, 'error');
            }
        },
        error: function(xhr) {
            let msg = 'Error de conexión al consultar las variables.';
            if (xhr.status === 401 || xhr.status === 403 || xhr.status === 0) {
                msg = 'No autorizado o sesión expirada.';
            }
            toast(msg, 'error');
        }
    });
}

function guardarConfig() {
    let formData = $('#formConfiguracion').serialize();
    
    $('#btnGuardarConfig').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    $.ajax({
        url: API_BASE + '/ctacte/configuracion/guardar_configuracion.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        headers: {
            "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
        },
        success: function(res) {
            if (res.status === 'ok') {
                toast('Configuración guardada de manera exitosa.', 'success');
                cargarConfiguracion();
            } else {
                toast('Al guardar: ' + res.msg, 'error');
            }
        },
        error: function(xhr) {
            let msg = 'No se pudieron guardar las configuraciones.';
            if (xhr.status === 401 || xhr.status === 403) {
                msg = 'No autorizado para realizar esta acción.';
            }
            toast(msg, 'error');
        },
        complete: function() {
            $('#btnGuardarConfig').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Configuración');
        }
    });
}