let datosLogs = [];

$(document).ready(function() {
    cargarAuditoria();
});

function cargarAuditoria() {
    if ($.fn.DataTable.isDataTable('#tablaAuditoria')) {
        $('#tablaAuditoria').DataTable().destroy();
    }

    $.ajax({
        url: API_BASE + '/ctacte/auditoria/get_todos_logs.php',
        type: 'GET',
        headers: { 
            "Authorization": "Bearer " + localStorage.getItem('sso_token') 
        },
        success: function(res) {
            if (res.status === 'ok') {
                datosLogs = res.data;
                let html = '';
                datosLogs.forEach((log, index) => {
                    let cambios = compararJSON(log.dataantes, log.datadespues);
                    html += `
                    <tr>
                        <td class="small">${log.createdat || ''}</td>
                        <td>${log.username || 'Sistema'}</td>
                        <td><span class="badge ${colorAccion(log.action)}">${log.action}</span></td>
                        <td><code>${log.tablename}</code></td>
                        <td>#${log.idregistro}</td>
                        <td>${cambios}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" onclick="verMas(${index})">
                                <i class="fa fa-eye"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                $('#listaAuditoria').html(html);
                $('#tablaAuditoria').DataTable({
                    "order": [[0, "desc"]],
                    "language": {
                        "sProcessing":     "Procesando...",
                        "sLengthMenu":     "Mostrar _MENU_ registros",
                        "sZeroRecords":    "No se encontraron resultados",
                        "sEmptyTable":     "Ningún dato disponible en esta tabla",
                        "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                        "sSearch":         "Buscar:",
                        "sInfoThousands":  ",",
                        "sLoadingRecords": "Cargando...",
                        "oPaginate": {
                            "sFirst":    "Primero",
                            "sLast":     "Último",
                            "sNext":     "Siguiente",
                            "sPrevious": "Anterior"
                        },
                        "oAria": {
                            "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
                            "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                        }
                    }
                });
            }
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                console.error("Token inválido o expirado en cargarAuditoria");
                if (typeof toast === 'function') {
                    toast("Sesión expirada o token inválido", "error");
                }
                setTimeout(() => { window.location.href = '../auth/login.php'; }, 1500);
            } else {
                if (typeof toast === 'function') {
                    toast("Error al cargar los registros de auditoría", "error");
                }
                console.error("Error al cargar los registros de auditoría", xhr.responseText);
            }
        }
    });
}

function verMas(index) {
    const log = datosLogs[index];

    $('#detId').text(log.id);
    $('#detFecha').text(log.createdat);
    $('#detUser').text(log.username || 'Sistema');
    $('#detTabla').text(log.tablename || 'N/A');
    $('#detIp').text(log.ipaddress || 'N/A');

    $('#detAccion')
        .text(log.action)
        .removeClass()
        .addClass('badge ' + colorAccion(log.action));

    // Validar si el idregistro es inválido, nulo o cero
    if (!log.idregistro || log.idregistro == 0 || log.idregistro === 'null') {
        $('#detCambios').html(renderCambios(log.dataantes, log.datadespues));
        $('#detActual').html('<span class="text-muted">Este registro corresponde a un evento global, error o acción masiva (sin ID de entidad individual).</span>');
        $('#modalDetalleLog').modal('show');
        return; // Detiene la ejecución para que no lance la petición AJAX con error
    }

    $('#detActual').html('<span class="text-muted">Cargando estado actual...</span>');

    $.ajax({
        url: API_BASE + '/ctacte/auditoria/get_registros.php',
        type: 'POST',
        data: {
            tabla: log.tablename,
            id: log.idregistro
        },
        headers: { 
            "Authorization": "Bearer " + localStorage.getItem('sso_token') 
        },
        success: function(res) {
            if (res.status === 'ok') {
                $('#detCambios').html(renderCambios(log.dataantes, log.datadespues, res.data)); 
                $('#detActual').html(renderFicha(res.data));
            } else {
                $('#detCambios').html(renderCambios(log.dataantes, log.datadespues));
                $('#detActual').html('<span class="text-danger">Registro actual no disponible o eliminado</span>');
            }
        },
        error: function(xhr) {
            $('#detCambios').html(renderCambios(log.dataantes, log.datadespues));
            $('#detActual').html('<span class="text-danger">Error de conexión al buscar el registro</span>');
        }
    });

    $('#modalDetalleLog').modal('show');
}

function compararJSON(antes, despues) {
    if (!antes) return '<span class="text-success small">Alta</span>';
    try {
        let a = typeof antes === 'string' ? JSON.parse(antes) : antes;
        let d = typeof despues === 'string' ? JSON.parse(despues) : despues;
        let out = '';
        for (let key in d) {
            if (String(a[key]) !== String(d[key])) {
                out += `<div class="cambio-item">
                            <small><b>${key}:</b></small> 
                            <span class="text-old">${a[key] || '[vacío]'}</span> 
                            <span class="text-new">${d[key]}</span>
                        </div>`;
            }
        }
        return out || '<span class="text-muted small">Sin cambios</span>';
    } catch (e) { return '---'; }
}

function colorAccion(action) {
    if (!action) return 'badge-secondary';
    switch (action.toLowerCase()) {
        case 'insert': case 'alta': case 'crear': return 'badge-success';
        case 'update': case 'modificacion': case 'editar': return 'badge-warning';
        case 'delete': case 'baja': case 'eliminar': return 'badge-danger';
        default: return 'badge-info';
    }
}

function renderCambios(antes, despues, dataActual = null) {
    if (!antes) return '<span class="text-success">Alta de registro</span>';

    try {
        let a = typeof antes === 'string' ? JSON.parse(antes) : antes;
        let d = typeof despues === 'string' ? JSON.parse(despues) : despues;
        let html = '';

        const camposSensibles = ['password', 'clave', 'token', 'password_hash', 'auth_token'];

        const nombresRelacionados = {
            'idaplicacion': dataActual?.nombre_aplicacion || null,
            'idusuario': dataActual?.nombre_usuario || null,
            'idtipousuario': dataActual?.rol_descripcion || null
        };

        for (let key in d) {
            if (String(a[key]) !== String(d[key])) {
                let valorAntes = a[key];
                let valorDespues = d[key];

                if (camposSensibles.includes(key.toLowerCase())) {
                    valorAntes = '[Oculto]';
                    valorDespues = '[Oculto por seguridad]';
                } else {
                    if (nombresRelacionados[key]) {
                        valorDespues = `<b>${nombresRelacionados[key]}</b>`;
                    }
                }

                if (valorAntes === null || valorAntes === '' || valorAntes === '---') valorAntes = '<span style="color:#ccc">[vacío]</span>';
                if (valorDespues === null || valorDespues === '' || valorDespues === '---') valorDespues = '<span style="color:#ccc">[vacío]</span>';

                html += `
                <div class="mb-1">
                    <small class="text-muted"><b>${key}:</b></small>
                    <span class="text-old">${valorAntes}</span>
                    <i class="fa fa-arrow-right mx-1 small text-muted"></i>
                    <span class="text-new">${valorDespues}</span>
                </div>`;
            }
        }
        return html || '<span class="text-muted">Sin cambios detectados</span>';
    } catch (e) {
        return '<span class="text-danger">Error al procesar cambios</span>';
    }
}

function renderFicha(data) {
    let html = '<div class="row">';
    
    const camposSensibles = ['password', 'clave', 'token', 'password_hash', 'auth_token'];

    const labelsEspeciales = {
        'idmedicoapto': 'Médico Apto',
        'idmedico_firma': 'Médico Firma',
        'idusuariooperadorapto': 'Operador Apto',
        'idusuario_operador_apto': 'Operador Apto',
        'idusuariolicprov': 'Usuario Lic. Prov.',
        'idusuariolicdef': 'Usuario Lic. Def.',
        'idusuariologueado': 'Usuario Logueado',
        'mailprovisorioenviado': 'Mail Provisorio',
        'maildefinitivoenviado': 'Mail Definitivo',
        'idaplicacion': 'Aplicación',
        'idusuario': 'Usuario Central',
        'idtipousuario': 'Rol / Tipo Usuario',
        'idpermiso': 'Permiso',
        'clavepermiso': 'Clave Permiso',
        'ultimologin': 'Último Login',
        'url_base': 'URL Base',
        'baja': 'Estado Usuario',
        'activo': 'Estado App'
    };

    for (let key in data) {
        if (key.startsWith('nombre_') || key.startsWith('slug_') || key.startsWith('rol_')) continue;

        let valor = data[key];
        let label = labelsEspeciales[key] || key;

        if (camposSensibles.includes(key.toLowerCase())) {
            valor = '<span class="text-muted fst-italic">[Oculto por seguridad]</span>';
        } else {
            if ((key.includes('medico') || key.includes('firma')) && data.nombre_medico) valor = `<b>${data.nombre_medico}</b>`;
            else if ((key.includes('operador')) && data.nombre_operador) valor = `<b>${data.nombre_operador}</b>`;
            else if (key === 'idusuariolicprov' && data.nombre_licprov) valor = `<b>${data.nombre_licprov}</b>`;
            else if (key === 'idusuariolicdef' && data.nombre_licdef) valor = `<b>${data.nombre_licdef}</b>`;
            else if (key === 'idusuariologueado' && data.nombre_logueado) valor = `<b>${data.nombre_logueado}</b>`;
            else if (key === 'idaplicacion' && data.nombre_aplicacion) valor = `<b>${data.nombre_aplicacion} (${data.slug_aplicacion || ''})</b>`;
            else if (key === 'idusuario' && data.nombre_usuario) valor = `<b>${data.nombre_usuario} (@${data.user_ref || ''})</b>`;
            else if (key === 'idtipousuario' && data.rol_descripcion) valor = `<b>${data.rol_descripcion}</b>`;
            
            const flags = ['piloto', 'copiloto', 'licenciaprovisoria', 'licenciadefinitiva', 'aptomedico', 'suplantacion', 'mailprovisorioenviado', 'maildefinitivoenviado', 'activo', 'baja'];
            if (flags.includes(key.toLowerCase())) {
                if (key === 'baja') {
                    valor = (valor == 0) ? '<span class="text-success"><i class="fa fa-check"></i> Habilitado</span>' : '<span class="text-danger"><i class="fa fa-times"></i> Dado de Baja</span>';
                } else {
                    valor = (valor == 1 || valor == 'Sí' || valor == 'SI' || valor == '1') ? 
                        '<span class="text-success"><i class="fa fa-check"></i> Sí</span>' : 
                        '<span class="text-muted"><i class="fa fa-times"></i> No</span>';
                }
            }
        }

        if (valor === null || valor === '' || valor === '---') {
            valor = '<span style="color: #bbb; font-style: italic;">[N/A]</span>';
        }

        html += `
        <div class="col-md-6 mb-2 border-bottom pb-1">
            <small class="text-primary" style="text-transform: uppercase; font-size: 0.7rem;">${label}</small><br>
            <span style="font-size: 0.85rem;">${valor}</span>
        </div>`;
    }
    html += '</div>';
    return html;
}