$(document).ready(function() {

    if ($.fn.DataTable.isDataTable('#tablaRecetasGral')) {
        $('#tablaRecetasGral').DataTable().destroy();
    }

    window.tablaRecetas = $('#tablaRecetasGral').DataTable({
        language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "Ningún dato disponible en esta tabla",
            sInfo: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
            sSearch: "Buscar:",
            sLoadingRecords: "Cargando...",
            oPaginate: {
                sFirst: "Primero",
                sLast: "Último",
                sNext: "Siguiente",
                sPrevious: "Anterior"
            }
        },
        order: [],
        columnDefs: [
            { targets: -1, className: "text-center" }
        ],
            "createdRow": function(row, data, dataIndex) {
            // Si el texto "(ANULADA)" está en la columna del nombre (col index 2)
            if (data[5].includes("Anulada")) {
                $(row).addClass('table-danger'); // Clase de Bootstrap para fondo rojo suave
            }
        }    
    });

    $('#dniBusqueda').on('keypress', function(e) {
        if (e.which == 13) buscarParaReceta();
    });
});

function buscarParaReceta() {
    let dni = $('#dniBusqueda').val();
    if (!dni) {
        Swal.fire('Atención', 'Por favor ingrese un DNI', 'warning');
        return;
    }

    $.ajax({
        url: '../api/recetas/buscar_paciente_recetario.php', // Crearemos este ahora
        type: 'GET',
        data: { dni: dni },
        success: function(res) {

            if (res.status === 'success') {

                $('#btnNuevaRecetaGral').removeClass('d-none');
                $('#infoPacienteReceta').removeClass('d-none');
                $('#nombrePacienteActivo').text(res.persona.apellidonombre);

                let rolesHtml = '';
                res.roles.forEach(rol => {
                    rolesHtml += `<span class="badge ${rol.color_etiqueta} badge-rol">${rol.descripcion}</span> `;
                });

                $('#rolesPacienteActivo').html(rolesHtml);
                //console.log("Paciente encontrado:", res);
                actualizarTablaRecetas(res.recetas);

            } else if (res.status === 'not_found') {
                confirmarAltaPacienteNuevo(dni);
            }
        },
        error: function(xhr) {
            console.error(xhr.responseText);
        }
    });
}

function actualizarTablaRecetas(recetas) {
    if (!window.tablaRecetas) {
        console.error("La tabla no está inicializada.");
        return;
    }
    const ultimoIdReceta = Math.max(...recetas.map(r => parseInt(r.idreceta)));
    //console.log("Recetas recibidas para actualizar tabla:", recetas);
    window.tablaRecetas.clear().draw();

    if (!recetas || recetas.length === 0) {
        tablaRecetas.row.add([
            '',
            '',
            '',
            '',
            '<span class="text-muted">Sin resultados</span>',
            ''
        ]).draw();
    }
    if (recetas && recetas.length > 0) {
        recetas.forEach((r, index) => {
            const tienePermisoAnular = tienePermiso('recetas_anular'); // O 'recetas_editar' según tu tabla
            const tienePermisoSuplantar = tienePermiso('recetas_suplantacion');

            // 2. IDs para comparar (idUsuarioLogueado es la variable global de tu sistema)
            const idActual = parseInt(window.idUsuarioLogueado, 10);
            const idFirma = parseInt(r.idmedico, 10);    // Quién firma la receta
            const idCarga = parseInt(r.idoperador, 10);  // Quién apretó el botón
            const estado = parseInt(r.estado, 10);

            const esAnulada = (estado === 0); // O (r.usuario_anula !== null)


            // 3. Lógica de Propiedad (Copiada de antecedentes)
            const esCreador = idFirma === idActual;
            const esSuplantador = idCarga === idActual;

            // Lógica de anulación: Solo la primera de todas (offset 0 e index 0)
            
            const esLaUltima = parseInt(r.idreceta) === ultimoIdReceta;
            const puedeAnular = 
            esLaUltima &&
            estado === 1 && // Solo si está activa
            (
                (esCreador && tienePermisoAnular) || 
                (
                    esSuplantador && 
                    tienePermisoAnular && 
                    tienePermisoSuplantar
                )
            );
            
            let btnImprimir = '';
            if (!esAnulada) {
                btnImprimir = `<button class="btn btn-sm btn-outline-primary" onclick="imprimirReceta(${r.idreceta})" title="Imprimir">
                                    <i class="fa fa-print"></i>
                                </button>`;
            } else {
                btnImprimir = `<span class="badge badge-danger">Anulada</span>`;
            }
            
            const btnAnular = puedeAnular ? 
                    `<button class="btn btn-sm btn-outline-danger py-0 border-0" title="Anular Receta" onclick="anularReceta(${r.idreceta}, '${r.documento}')">
                        <i class="fas fa-trash-alt"></i> Anular
                     </button>` : '';

            let nombreDisplay = esAnulada ? `<span class="text-danger font-weight-bold">${r.paciente_nombre} (ANULADA)</span>` : r.paciente_nombre;
            let detalleDisplay = esAnulada ? `<strike class="text-muted">${r.contenido_breve}...</strike>` : `<small class="text-muted">${r.contenido_breve}...</small>`;

            window.tablaRecetas.row.add([
                r.fecha_formateada,
                r.documento,
                r.paciente_nombre,
                r.medico_nombre,
                detalleDisplay,
                `<div class="d-flex justify-content-center align-items-center" style="gap: 10px;">
                    ${btnImprimir} ${btnAnular}
                </div>`
            ]).draw(false);
        });
    }
}

function imprimirReceta(id) {
    window.open(`../api/recetas/get_receta_pdf.php?id=${id}`, '_blank');
}

function confirmarAltaPacienteNuevo(dni) {
    Swal.fire({
        title: 'Paciente no registrado',
        text: `El DNI ${dni} no existe en el sistema. ¿Desea cargarlo como Particular para esta receta?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, dar de alta',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#28a745'
    }).then((result) => {
        if (result.isConfirmed) {
            // Seteamos el DNI en el modal de alta y lo abrimos
            $('#alta_dni').val(dni);
            $('#modalAltaRapida').modal('show');
        }
    });
}
/*
$('#formAltaRapida').on('submit', function(e) {
    e.preventDefault();
    let datos = $(this).serialize();

    $.ajax({
        url: 'api/personas/guardar_alta_rapida.php',
        type: 'POST',
        data: datos,
        success: function(response) {
            let res = JSON.parse(response);
            if (res.status === 'success') {
                $('#modalAltaRapida').modal('hide');
                Swal.fire('Éxito', 'Paciente registrado', 'success');
                // Ahora que existe, volvemos a buscarlo para que habilite la tabla y el botón de nueva receta
                buscarParaReceta(); 
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        }
    });
});
*/
$(document).on('submit', '#formAltaRapida', function(e) {
    e.preventDefault(); // Detiene la recarga de la página
    
    let datos = $(this).serialize();
    //console.log("Enviando datos...", datos); // Para que veas en consola si arranca

    $.ajax({
        url: '../api/personas/guardar_alta_rapida.php',
        type: 'POST',
        data: datos,
        success: function(res) { // 'res' ya es el objeto
            try {
                // Quitamos la línea: let res = JSON.parse(response);
                
                if (res.status === 'success') {
                    $('#modalAltaRapida').modal('hide');
                    toast("Paciente registrado correctamente", "success");
                    
                    $('#formAltaRapida')[0].reset();
                    buscarParaReceta(); 
                } else {
                    toast(data.msg || "Error al registrar", "error");
                }
            } catch (err) {
                console.error("Error procesando la respuesta:", err);
                toast("Error en el formato de respuesta", "error");
            }
        },
        error: function(xhr) {
            console.error("Error de servidor:", xhr.responseText);
            toast("Error de conexión con el servidor", "error");
        }
    });
});

function nuevaReceta() {
    let dni = $('#dniBusqueda').val();
    let nombre = $('#nombrePacienteActivo').text();

    if (!dni || nombre === "") {
        toast("Busque un paciente antes de emitir una receta", "error");
        return;
    }

    // Resetear formulario
    $('#formEmitirRecetaGral')[0].reset();

    // Cargar datos
    $('#receta_dni_gral').val(dni);
    $('#nombrePacienteRecetaGral').text(nombre);

    // Mostrar el nuevo modal
    $('#modalEmitirRecetaGral').modal('show');
}
/*
// Evento de guardado
$(document).on('submit', '#formEmitirRecetaGral', function(e) {
    e.preventDefault();
    
    let btn = $('#btnGuardarRecetaGral');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

    let datos = $(this).serialize();

    $.ajax({
        url: '../api/recetas/guardar_nueva_receta.php',
        type: 'POST',
        data: datos,
        success: function(res) {
            let data = (typeof res === 'object') ? res : JSON.parse(res);
            
            if (data.status === 'success') {
                $('#modalEmitirRecetaGral').modal('hide');
                toast("Receta generada con éxito", "success");
                
                // Refrescar la tabla de la pantalla principal
                buscarParaReceta(); 
                
                // Abrir el PDF en pestaña nueva (ajusta la URL según tu script de impresión)
                if(data.idreceta) {
                    window.open('imprimir_receta.php?id=' + data.idreceta, '_blank');
                }
            } else {
                toast(data.msg || "Error al procesar", "error");
                btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Guardar y Generar PDF');
            }
        },
        error: function() {
            toast("Error de red", "error");
            btn.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Guardar y Generar PDF');
        }
    });
});
*/
$(document).on("submit", "#formEmitirRecetaGral", async function(e) {
    e.preventDefault();

    const form = this;
    const dni = $('#receta_dni_gral').val(); // Tomamos el DNI del campo hidden del modal
    
    if(!dni) {
        toast("Error: No se detectó el DNI", "error");
        return;
    }

    // Verificamos permisos (ajustá los nombres de permisos si son distintos en tu BD)
    if (!window.esMedico && !tienePermiso('recetas_suplantacion')) {
        toast("No tenés permiso para emitir recetas", "error");
        return;
    }
    
    let idMedicoFinal = window.idUsuarioLogueado;
    let idUsuarioFinal = window.idUsuarioLogueado;
    let auditoria = "";

    // 🔥 SOLO SI ES SUPLANTACIÓN (No es médico pero tiene permiso de suplantar)
    if (!window.esMedico && tienePermiso('recetas_suplantacion')) {
        try {
            const { idMedico } = await window.elegirMedico(); // Tu función popup ya existente
            if (!idMedico) return; // Si cancela el popup, no hace nada

            idMedicoFinal = idMedico;
            idUsuarioFinal = window.idUsuarioLogueado;
            auditoria = `Suplantado por operador ID: ${window.idUsuarioLogueado}`;
        } catch (err) {
            return; // Error o canceló el modal de eleowgir médico
        }
    }

    enviarDataReceta(form, dni, idMedicoFinal, idUsuarioFinal, auditoria);
});

function enviarDataReceta(form, dni, idMedico, idCarga, txtAuditoria) {
    let formData = new FormData(form);
    
    // Agregamos los datos de control
    formData.append('dni', dni);
    formData.append('idMedico', idMedico); // Quién firma
    formData.append('idCarga', idCarga);   // Quién apretó el botón
    formData.append('auditoria', txtAuditoria);
    
    // El año es importante para tu filtro
    //const anioSeleccionado = $("#filtroAnioGlobal").val();
    //formData.append('anio', anioSeleccionado);
    //console.log("Guardando Receta - Médico ID:", idMedico, "Paciente DNI:", dni);
    $.ajax({
        type: "POST",
        url: "../api/recetas/guardar_receta.php", 
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function() {
            $('#btnGuardarRecetaGral').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
        },
        success: function(res) {
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            
            if (r.status === "ok" || r.status === "success") {
                toast("Receta guardada con éxito");
                
                $('#modalEmitirRecetaGral').modal('hide');

                // Limpiar el textarea
                $('#contenido_receta').val('');
                
                buscarParaReceta();

                // 🔥 LANZAR IMPRESIÓN AUTOMÁTICA
                if(r.idreceta) {
                    imprimirReceta(r.idreceta);
                }
            } else {
                toast(r.msg || "Error al guardar", "error");
            }
        },
        complete: function() {
            $('#btnGuardarRecetaGral').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Receta');
        },
        error: function() {
            toast("Error de conexión con el servidor", "error");
        }
    });
}

function anularReceta(idreceta, dni) {
    // 1. Pedimos confirmación y motivo (Siguiendo tu lógica de seguridad)
    Swal.fire({
    title: '¿Anular esta receta?',
    text: 'Debe ingresar el motivo de la anulación:',
    input: 'text',
    inputPlaceholder: 'Ej: Error en la medicación...',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Sí, anular',
    cancelButtonText: 'Cancelar',
    allowOutsideClick: false,
    allowEscapeKey: false,

    didOpen: () => {
        $(document).off('focusin.modal');

        setTimeout(() => {
            $('.swal2-input').trigger('focus');
        }, 100);
    },

    willClose: () => {
        // Restaurar el manejo de foco del modal activo
        const modalAbierto = document.querySelector('.modal.show');
        if (modalAbierto) {
            modalAbierto.focus();
        }
    },

    inputValidator: (value) => {
        if (!value || !value.trim()) {
            return '¡El motivo es obligatorio para la auditoría!';
        }
    }
    }).then((result) => {
        if (result.isConfirmed) {
            const motivo = result.value;

            // 2. Llamada a la API que armamos
            $.ajax({
                url: '../api/recetas/anular_receta.php',
                type: 'POST',
                data: {
                    idreceta: idreceta,
                    dni: dni,
                    motivo: motivo
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'ok') {
                        toast(response.msg, "¡Anulación Exitosa!");
                        buscarParaReceta();
                        // 3. Refrescamos el listado (o la función que uses para cargar recetas)
                        /*
                        if (typeof cargarHistorialRecetas === 'function') {
                            cargarHistorialRecetas(dni);
                        } else {
                            location.reload(); // Backup por si no tenés la función a mano
                        }*/
                    } else {
                        toast(response.msg, "error");
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("Error en anularReceta:", textStatus, errorThrown);
                    toast("Error de conexión al anular la receta", "error");
                }
            });
        }
    });
}
