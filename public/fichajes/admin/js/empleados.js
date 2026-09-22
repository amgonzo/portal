let tablaEmpleados = null;
let empleadosList = [];

document.addEventListener('DOMContentLoaded', () => {
    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    initDataTable();
    cargarEmpleados();
});

// Inicializar DataTables con idioma local (Sin CORS)
function initDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaEmpleados')) {
        $('#tablaEmpleados').DataTable().destroy();
    }
    
    tablaEmpleados = $('#tablaEmpleados').DataTable({
        language: {
            "sProcessing":    "Procesando...",
            "sLengthMenu":    "Mostrar _MENU_ registros",
            "sZeroRecords":   "No se encontraron resultados",
            "sEmptyTable":    "Ningún dato disponible en esta tabla",
            "sInfo":          "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty":     "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered":  "(filtrado de un total de _MAX_ registros)",
            "sSearch":        "Buscar:",
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
        },
        responsive: true,
        autoWidth: false
    });
}

// ---------------------------------------------------------------------
// 1. LISTAR EMPLEADOS
// ---------------------------------------------------------------------
async function cargarEmpleados() {
    try {
        const res = await fetch(API_BASE + '/fichajes/empleados/empleados.php?action=listar', {
            method: 'GET',
            headers: obtenerHeadersSSO()
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || "Error al cargar empleados", "error");
            return;
        }

        empleadosList = json.data;
        tablaEmpleados.clear();

        empleadosList.forEach(e => {
            const activo = e.activo == 1;
            
            const badgeEstado = activo 
                ? '<span class="badge bg-success">Activo</span>' 
                : '<span class="badge bg-danger">Inactivo</span>';

            const badgeTarjeta = e.tarjeta 
                ? `<span class="badge bg-secondary">${e.tarjeta}</span>` 
                : '<span class="text-muted small">Sin asignar</span>';

            const btnEditar = `<button class="btn btn-sm btn-info text-white me-1" onclick="editarEmpleado(${e.idempleado})" title="Editar">
                                 <i class="fas fa-edit"></i>
                             </button>`;

            const btnEstado = activo 
                ? `<button class="btn btn-sm btn-danger" onclick="cambiarEstadoEmpleado(${e.idempleado}, 0)" title="Dar de baja">
                    <i class="fas fa-user-slash"></i>
                   </button>`
                : `<button class="btn btn-sm btn-success" onclick="cambiarEstadoEmpleado(${e.idempleado}, 1)" title="Dar de alta">
                    <i class="fas fa-user-check"></i>
                   </button>`;

            tablaEmpleados.row.add([
                e.documento,
                `<strong>${e.apellido}, ${e.nombre}</strong>`,
                badgeTarjeta,
                e.fecha_inicio ?? '-',
                badgeEstado,
                `${btnEditar} ${btnEstado}`
            ]);
        });

        tablaEmpleados.draw();

    } catch (err) {
        toast("Error de conexión al obtener empleados", "error");
    }
}

// ---------------------------------------------------------------------
// 2. ABRIR Y GUARDAR (CREAR / EDITAR)
// ---------------------------------------------------------------------
function abrirNuevoEmpleado() {
    $('#formEmpleado')[0].reset();
    $('#edit_empleado_id').val('');
    $('.modal-title').text('Crear Nuevo Empleado');$('#btnGuardarEmpleado').text('Guardar Empleado');
    $('#ModalEmpleado').modal('show');
}

function editarEmpleado(id) {
    const e = empleadosList.find(emp => emp.idempleado == id);
    if (!e) return;

    $('#edit_empleado_id').val(e.idempleado);
    $('#emp_documento').val(e.documento);
    $('#emp_nombre').val(e.nombre);
    $('#emp_apellido').val(e.apellido);
    $('#emp_tarjeta').val(e.tarjeta);
    $('#emp_fecha_inicio').val(e.fecha_inicio);
    
    $('.modal-title').text(`Editar Empleado: ${e.apellido}, ${e.nombre}`);
    $('#btnGuardarEmpleado').text('Guardar Cambios');
    $('#ModalEmpleado').modal('show');
}

async function guardarEmpleado() {
    const documento = $('#emp_documento').val().trim();
    const nombre = $('#emp_nombre').val().trim();
    const apellido = $('#emp_apellido').val().trim();
    const id = $('#edit_empleado_id').val();

    if (!documento || !nombre || !apellido) {
        toast("Complete los campos requeridos (Documento, Nombre y Apellido)", "warning");
        return;
    }

    const action = id ? 'editar' : 'crear';
    const formData = new FormData(document.getElementById('formEmpleado'));
    if (id) {
        formData.append('idempleado', id);
    }

    try {
        const res = await fetch(API_BASE + `/fichajes/empleados/empleados.php?action=${action}`, {
            method: 'POST',
            headers: obtenerHeadersSSO(),
            body: formData
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg, "error");
            return;
        }

        $('#ModalEmpleado').modal('hide');
        toast(json.msg, "success");
        cargarEmpleados();

    } catch (err) {
        toast("Error al procesar la solicitud", "error");
    }
}

// ---------------------------------------------------------------------
// 3. CAMBIAR ESTADO (ALTA / BAJA)
// ---------------------------------------------------------------------
function cambiarEstadoEmpleado(id, nuevoEstado) {
    const accionTexto = nuevoEstado === 1 ? 'dar de alta' : 'dar de baja';
    
    Swal.fire({
        title: '¿Está seguro?',
        text: `¿Desea ${accionTexto} a este empleado?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado === 1 ? '#28a745' : '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('idempleado', id);
            formData.append('activo', nuevoEstado);

            try {
                const res = await fetch(API_BASE + '/fichajes/empleados/empleados.php?action=estado', {
                    method: 'POST',
                    headers: obtenerHeadersSSO(),
                    body: formData
                });
                const json = await res.json();

                if (json.status !== 'ok') {
                    toast(json.msg, "error");
                    return;
                }

                toast(json.msg, "success");
                cargarEmpleados();

            } catch (err) {
                toast("Error al intentar cambiar el estado", "error");
            }
        }
    });
}