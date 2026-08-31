let tablaCategorias = null;
let categoriasList = [];
let personasList = [];

document.addEventListener('DOMContentLoaded', () => {
    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    initDataTable();
    listarCategorias();
    cargarPersonasAsignacion();
});

// Inicializar DataTables con idioma local (Sin CORS)
function initDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaCategorias')) {
        $('#tablaCategorias').DataTable().destroy();
    }
    
    tablaCategorias = $('#tablaCategorias').DataTable({
        language: {
            "sProcessing":     "Procesando...",
            "sLengthMenu":     "Mostrar _MENU_ registros",
            "sZeroRecords":    "No se encontraron resultados",
            "sEmptyTable":     "Ningún dato disponible en esta tabla",
            "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
            "sSearch":         "Buscar:",
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
// 1. LISTAR CATEGORÍAS
// ---------------------------------------------------------------------
async function listarCategorias() {
    try {
        const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=listar', {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || "Error al cargar categorías", "error");
            return;
        }

        categoriasList = json.data;
        tablaCategorias.clear();

        categoriasList.forEach(cat => {
            const esDefault = cat.es_default;
            const formateado = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(cat.limite_mensual);
            
            const badgeEstado = esDefault 
                ? '<span class="badge badge-secondary">Default / Base</span>' 
                : '<span class="badge bg-success">Activa</span>';

            const btnIntegrantes = `
                <button class="btn btn-sm btn-outline-info font-weight-bold" onclick="verPersonasCategoria(${cat.idcategoria}, '${cat.nombre}')" title="Ver integrantes">
                    <i class="fas fa-user font-weight-bold"></i> ${cat.total_personas}
                </button>
            `;

            const acciones = esDefault ? '<small class="text-muted font-italic">Categoría Protegida</small>' : `
                <button class="btn btn-sm btn-warning" onclick="editar(${cat.idcategoria})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="eliminar(${cat.idcategoria}, '${cat.nombre}')" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            `;

            tablaCategorias.row.add([
                `<strong>${cat.nombre}</strong>`,
                formateado,
                btnIntegrantes,
                badgeEstado,
                acciones
            ]);
        });

        tablaCategorias.draw();
        actualizarSelectCategoriasModal();

    } catch (err) {
        toast("Error de conexión al obtener categorías", "error");
    }
}

// ---------------------------------------------------------------------
// 2. ABRIR Y GUARDAR (CREAR / EDITAR)
// ---------------------------------------------------------------------
function abrirNuevo() {
    $('#formCategoria')[0].reset();
    $('#edit_cat_id').val('');
    $('#modalCategoriaTitulo').text('Crear Nueva Categoría');
    $('#ModalCategoria').modal('show');
}

function editar(id) {
    const cat = categoriasList.find(c => c.idcategoria == id);
    if (!cat) return;

    if (cat.es_default) {
        toast('La categoría por defecto no puede ser modificada.', 'warning');
        return;
    }

    $('#edit_cat_id').val(cat.idcategoria);
    $('#cat_nombre').val(cat.nombre);
    $('#cat_limite').val(cat.limite_mensual);
    $('#modalCategoriaTitulo').text('Editar Categoría');
    $('#ModalCategoria').modal('show');
}

async function guardar() {
    const nombre = $('#cat_nombre').val().trim();
    const limite = $('#cat_limite').val();
    const id = $('#edit_cat_id').val();

    if (!nombre || limite === '') {
        toast("Complete todos los campos requeridos", "warning");
        return;
    }

    const action = id ? 'editar' : 'crear';
    const formData = new FormData(document.getElementById('formCategoria'));

    try {
        const res = await fetch(API_BASE + `/ctacte/categorias/categorias.php?action=${action}`, {
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

        $('#ModalCategoria').modal('hide');
        toast(json.msg, "success");
        listarCategorias();
        cargarPersonasAsignacion();

    } catch (err) {
        toast("Error al procesar la solicitud", "error");
    }
}

// ---------------------------------------------------------------------
// 3. ELIMINAR CON SWEETALERT2 (Mantiene confirmación de seguridad crítica)
// ---------------------------------------------------------------------
function eliminar(id, nombre) {
    Swal.fire({
        title: '¿Está seguro?',
        text: `Desea eliminar la categoría "${nombre}". Todas las personas asociadas pasarán a la categoría Default (Sin Categoría).`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('idcategoria', id);

            try {
                const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=eliminar', {
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

                toast(json.msg, "success");
                listarCategorias();
                cargarPersonasAsignacion();

            } catch (err) {
                toast("Error al intentar eliminar", "error");
            }
        }
    });
}

// ---------------------------------------------------------------------
// 4. REASIGNACIÓN DE PERSONAS CON CONFIRMACIÓN INTELIGENTE
// ---------------------------------------------------------------------
async function cargarPersonasAsignacion() {
    try {
        const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=listar_personas_asignacion', {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();

        if (json.status !== 'ok') return;

        personasList = json.data;
        const selectP = $('#asig_persona_select');
        selectP.empty();

        personasList.forEach(p => {
            selectP.append(`<option value="${p.dni}">${p.nombre} (${p.dni}) - [${p.estado_categoria}]</option>`);
        });

    } catch (err) {
        console.error("Error al cargar selector de personas", err);
    }
}

function actualizarSelectCategoriasModal() {
    const selectC = $('#asig_categoria_select');
    selectC.empty();

    categoriasList.forEach(c => {
        selectC.append(`<option value="${c.idcategoria}">${c.nombre} ($${c.limite_mensual})</option>`);
    });
}

function abrirModalReasignar() {
    $('#ModalAsignarPersona').modal('show');
}

async function guardarReasignacion() {
    const dni = $('#asig_persona_select').val();
    const nuevaCatId = $('#asig_categoria_select').val();

    const persona = personasList.find(p => p.dni === dni);
    const nuevaCat = categoriasList.find(c => c.idcategoria == nuevaCatId);

    if (!persona || !nuevaCat) {
        toast("Selección no válida", "warning");
        return;
    }

    if (persona.idcategoria == nuevaCatId) {
        $('#ModalAsignarPersona').modal('hide');
        return;
    }

    // SI TIENE CATEGORÍA ASIGNADA (NO DEFAULT), LE PEDIMOS CONFIRMACIÓN
    if (!persona.es_default) {
        const confirmacion = await Swal.fire({
            title: 'Cambio de Categoría',
            html: `La persona <strong>${persona.nombre}</strong> ya pertenece a la categoría <b>"${persona.categoria_nombre}"</b>.<br><br>¿Desea moverla a <b>"${nuevaCat.nombre}"</b>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        });

        if (!confirmacion.isConfirmed) return;
    }

    const formData = new FormData();
    formData.append('dni', dni);
    formData.append('idcategoria', nuevaCatId);

    try {
        const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=asignar_persona', {
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

        $('#ModalAsignarPersona').modal('hide');
        toast(json.msg, "success");
        listarCategorias();
        cargarPersonasAsignacion();

    } catch (err) {
        toast("Error al procesar el cambio de categoría", "error");
    }
}

// Cargar años dinámicamente al iniciar (Año actual y anteriores)
function cargarSelectAnios() {
    const selectAnio = $('#lim_anio');
    selectAnio.empty();
    const anioActual = new Date().getFullYear();
    
    for (let i = anioActual; i >= anioActual - 2; i--) {
        selectAnio.append(`<option value="${i}">${i}</option>`);
    }
    
    const mesActual = new Date().getMonth() + 1;
    $('#lim_mes').val(mesActual);
}

function abrirModalAplicarLimites() {
    cargarSelectAnios();
    $('#ModalAplicarLimites').modal('show');
}

async function procesarAplicacionLimites() {
    const anio = $('#lim_anio').val();
    const mes = $('#lim_mes').val();
    const nombreMes = $("#lim_mes option:selected").text();

    const confirmacion = await Swal.fire({
        title: '¿Confirmar actualización masiva?',
        html: `Se actualizarán los límites de <b>todas las personas</b> para el período <b>${nombreMes} / ${anio}</b> según su categoría actual.<br><br><small class="text-danger">Si el mes está cerrado, la operación será rechazada.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, aplicar límites',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    const formData = new FormData();
    formData.append('anio', anio);
    formData.append('mes', mes);

    try {
        const res = await fetch(API_BASE + '/ctacte/categorias/categorias.php?action=aplicar_limites_mes', {
            method: 'POST',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            },
            body: formData
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg, 'error');
            return;
        }

        $('#ModalAplicarLimites').modal('hide');
        toast(json.msg, "success");

    } catch (err) {
        toast("Error de conexión al aplicar límites", "error");
    }
}

async function verPersonasCategoria(idcategoria, nombreCategoria) {
    $('#lblCatNombre').text(nombreCategoria);
    const ul = $('#listaPersonasCat');
    ul.html('<li class="list-group-item text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando...</li>');

    $('#ModalVerPersonas').modal('show');

    try {
        const res = await fetch(API_BASE + `/ctacte/categorias/categorias.php?action=listar_personas_por_categoria&idcategoria=${idcategoria}`, {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            ul.html(`<li class="list-group-item text-danger">${json.msg}</li>`);
            return;
        }

        ul.empty();
        if (json.data.length === 0) {
            ul.html('<li class="list-group-item text-center text-muted">No hay personas asignadas a esta categoría.</li>');
            return;
        }

        json.data.forEach((p, index) => {
            ul.append(`
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><strong>${index + 1}.</strong> ${p.nombre_completo}</span>
                    <small class="text-muted">${p.dni}</small>
                </li>
            `);
        });

    } catch (err) {
        ul.html('<li class="list-group-item text-danger">Error de conexión al cargar la lista.</li>');
    }
}