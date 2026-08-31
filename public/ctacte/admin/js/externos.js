let tablaExternos = null;
let externosList = [];

$(document).ready(function() {
    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    initDataTable();
    cargarCategoriasSelect();
    listarExternos();
});

function initDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaExternos')) {
        $('#tablaExternos').DataTable().destroy();
    }

    tablaExternos = $('#tablaExternos').DataTable({
        destroy: true,
        language: {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron personas externas",
            "sEmptyTable": "Sin personas externas registradas",
            "sInfo": "Mostrando _START_ al _END_ de _TOTAL_",
            "sSearch": "Buscar:",
            "oPaginate": { "sNext": "Siguiente", "sPrevious": "Anterior" }
        },
        responsive: true,
        autoWidth: false
    });
}

async function cargarCategoriasSelect() {
    try {
        const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=listar', {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();
        if (json.status === 'ok') {
            const select = $('#ext_idcategoria');
            select.empty();
            json.data.forEach(c => {
                select.append(`<option value="${c.idcategoria}">${c.nombre} ($${c.limite_mensual})</option>`);
            });
        }
    } catch (e) {
        console.error("Error al cargar categorías", e);
    }
}

async function listarExternos() {
    try {
        const res = await fetch(API_BASE + '/ctacte/externos/externos.php?action=listar', {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || "Error al listar externos", "error");
            return;
        }

        externosList = json.data;
        tablaExternos.clear();

        externosList.forEach(e => {
            const esActivo = e.activo === 1;
            const badgeEstado = esActivo 
                ? '<span class="badge bg-success">Activo</span>' 
                : '<span class="badge badge-danger">Inactivo / Baja</span>';

            const btnEstado = esActivo
                ? `<button class="btn btn-sm btn-outline-danger" onclick="cambiarEstado('${e.dni}', 0)" title="Dar de Baja"><i class="fas fa-user-slash"></i></button>`
                : `<button class="btn btn-sm btn-outline-success" onclick="cambiarEstado('${e.dni}', 1)" title="Reactivar"><i class="fas fa-user-check"></i></button>`;

            const acciones = `
                <button class="btn btn-sm btn-warning" onclick="editar('${e.dni}')" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                ${btnEstado}
            `;

            const porcentajeText = e.porcentaje_descuento !== undefined && e.porcentaje_descuento !== null 
                ? `<strong>${parseFloat(e.porcentaje_descuento)}%</strong>` 
                : '<span class="text-muted">0%</span>';

            tablaExternos.row.add([
                `<strong>${e.dni}</strong>`,
                `${e.apellido}, ${e.nombre}`,
                e.categoria_nombre,
                porcentajeText,
                badgeEstado,
                acciones
            ]);
        });

        tablaExternos.draw();

    } catch (err) {
        toast("Error de conexión al cargar externos", "error");
    }
}

function abrirNuevo() {
    $('#formExterno')[0].reset();
    $('#ext_es_edicion').val('0');
    $('#ext_dni').prop('readonly', false);
    $('#ext_porcentaje_descuento').val('0'); // Valor por defecto
    $('#modalExternoTitulo').text('Crear Persona Externa');
    $('#ModalExterno').modal('show');
}

function editar(dni) {
    const ext = externosList.find(e => e.dni === dni);
    if (!ext) return;

    $('#ext_es_edicion').val('1');
    $('#ext_dni').val(ext.dni).prop('readonly', true);
    $('#ext_apellido').val(ext.apellido);
    $('#ext_nombre').val(ext.nombre);
    $('#ext_idcategoria').val(ext.idcategoria);
    $('#ext_porcentaje_descuento').val(ext.porcentaje_descuento ?? 0);
    $('#modalExternoTitulo').text('Editar Persona Externa');
    $('#ModalExterno').modal('show');
}

async function guardar() {
    const dni = $('#ext_dni').val().trim();
    const nombre = $('#ext_nombre').val().trim();
    const apellido = $('#ext_apellido').val().trim();
    const porcentaje = $('#ext_porcentaje_descuento').val().trim();

    if (!dni || !nombre || !apellido || porcentaje === '') {
        toast("Complete todos los campos obligatorios", "warning");
        return;
    }

    const formData = new FormData(document.getElementById('formExterno'));

    try {
        const res = await fetch(API_BASE + '/ctacte/externos/externos.php?action=guardar', {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            },
            body: formData
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg, "error");
            return;
        }

        $('#ModalExterno').modal('hide');
        toast(json.msg, "success");
        listarExternos();

    } catch (err) {
        toast("Error al guardar registro", "error");
    }
}

async function cambiarEstado(dni, nuevoEstado) {
    const formData = new FormData();
    formData.append('dni', dni);
    formData.append('estado', nuevoEstado);

    try {
        const res = await fetch(API_BASE + '/ctacte/externos/externos.php?action=cambiar_estado', {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            },
            body: formData
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg, "error");
            return;
        }

        toast(json.msg || (nuevoEstado === 1 ? "Externo reactivado con éxito" : "Externo dado de baja con éxito"), "success");
        listarExternos();

    } catch (err) {
        toast("Error al cambiar estado", "error");
    }
}