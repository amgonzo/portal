$(document).ready(function() {
    cargarAnios();

    // Control de permisos
    if (!tienePermiso('anios_crear')) {
        $('[data-permiso="anios_crear"]').hide();
    }
});

function cargarAnios() {
    $.ajax({
        type: "GET",
        url: "../api/anios/get_anios.php",
        success: function(res) {
            if (res.status === "ok") {
                if ($.fn.DataTable.isDataTable('#tablaAnios')) $('#tablaAnios').DataTable().destroy();

                let html = "";
                res.data.forEach(a => {
                    let esActivo = a.activo == 1;
                    let estado = esActivo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>';
                    
                    let btnToggle = "";
                    // Mantenemos tu lógica de botones grises/naranjas pero con el nuevo permiso
                    if (esActivo) {
                        btnToggle = `<button class="btn btn-sm btn-light text-muted" disabled><i class="fas fa-check-double"></i></button>`;
                    } else if (tienePermiso('anios_activar')) { 
                        btnToggle = `<button class="btn btn-sm btn-warning" onclick="toggleAnio(${a.anio}, ${a.activo})" data-bs-toggle="tooltip" title="Activar este año"><i class="fas fa-check"></i></button>`;
                    }

                    // Volvemos a tu fila estándar sin colores inventados
                    html += `<tr>
                        <td>${a.anio}</td>
                        <td>${estado}</td>
                        <td class="text-center">
                            ${tienePermiso('anios_editar') ? `<button class="btn btn-sm btn-info" onclick="editarAnio(${a.anio})" data-bs-toggle="tooltip" title="Editar"><i class="fas fa-edit"></i></button> ` : ''}
                            ${btnToggle}
                        </td>
                    </tr>`;
                });
                $("#listaAnios").html(html);
                $('#tablaAnios').DataTable({
                    "language": {
                        "sProcessing":     "Procesando...",
                        "sLengthMenu":     "Mostrar _MENU_ registros",
                        "sZeroRecords":    "No se encontraron resultados",
                        "sEmptyTable":     "Ningún dato disponible en esta tabla",
                        "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                        "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
                        "sSearch":         "Buscar:",
                        "oPaginate": {
                            "sFirst":    "Primero",
                            "sLast":     "Último",
                            "sNext":     "Siguiente",
                            "sPrevious": "Anterior"
                        }
                    }
                });
                $('[data-bs-toggle="tooltip"]').tooltip();
            }
        }
    });
}

function nuevoAnio() {
    $("#formAnio")[0].reset();
    $("#anio").prop('readonly', false);
    $(".modal-title").text("Nuevo Año");
    $("#modalAnio").modal("show");
}

function editarAnio(anio) {
    $.ajax({
        type: "GET",
        url: "../api/anios/get_anio.php",
        data: { anio: anio },
        success: function(res) {
            if (res.status === "ok") {
                const a = res.data;
                $("#anio").val(a.anio).prop('readonly', true);
                $("#activo").prop('checked', a.activo == 1);
                
                // Si no tiene permiso de activar, deshabilitamos el checkbox
                if (!tienePermiso('anios_activar')) {
                    $("#activo").prop('disabled', true);
                } else {
                    $("#activo").prop('disabled', false);
                }

                $(".modal-title").text("Editar Año");
                $("#modalAnio").modal("show");
            }
        }
    });
}

function guardarAnio() {
    let anio = $("#anio").val();
    let activo = $("#activo").is(':checked') ? 1 : 0;

    if (!anio) {
        toast("El año es obligatorio", "warning");
        return;
    }

    // Si el usuario intenta guardar uno activo, avisamos que reemplazará al anterior
    if (activo === 1) {
        Swal.fire({
            title: '¿Establecer como año activo?',
            text: "Al guardar este año como activo, los demás se desactivarán.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, guardar y activar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarGuardado(anio, activo);
            }
        });
    } else {
        ejecutarGuardado(anio, activo);
    }
}

function ejecutarGuardado(anio, activo) {
    $.ajax({
        type: "POST",
        url: "../api/anios/guardar_anio.php",
        data: { anio: anio, activo: activo },
        success: function(res) {
            if (res.status === "ok") {
                toast("Año guardado correctamente");
                $("#modalAnio").modal("hide");
                cargarAnios();
            } else {
                toast("Error: " + res.msg, "error");
            }
        }
    });
}
/*
function toggleAnio(anio, actual) {
    let nuevo = actual == 1 ? 0 : 1;
    let titulo = nuevo == 1 ? '¿Activar este año?' : '¿Desactivar este año?';
    let texto = nuevo == 1 ? 'Se activará para su uso' : 'Se desactivará y no podrá usarse';

    
    Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#007bff',
        cancelButtonColor: '#6c757d',
        confirmButtonText: nuevo == 1 ? 'Sí, activar' : 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/anios/toggle_anio.php",
                data: { anio: anio, activo: nuevo },
                success: function(res) {
                    if (res.status === "ok") {
                        let msj = nuevo == 1 ? 'Año activado' : 'Año desactivado';
                        toast(msj);
                        cargarAnios();
                    } else {
                        toast("Error: " + res.msg, "error");
                    }
                }
            });
        }
    });
}*/
function toggleAnio(anio, actual) {
    // Si ya está activo, quizás no quieras dejar que lo desactiven sin activar otro primero
    if (actual == 1) {
        Swal.fire('Atención', 'Debe haber al menos un año activo. Active otro año para cambiar el actual.', 'info');
        return;
    }

    Swal.fire({
        title: '¿Establecer como año activo?',
        text: "Al activar el año " + anio + ", se desactivarán automáticamente los demás.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        confirmButtonText: 'Sí, activar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/anios/toggle_anio.php",
                data: { anio: anio, activo: 1 }, // Siempre mandamos 1 porque el toggle solo activa
                success: function(res) {
                    if (res.status === "ok") {
                        toast('Año ' + anio + ' activado como ciclo actual');
                        cargarAnios();
                    }
                }
            });
        }
    });
}