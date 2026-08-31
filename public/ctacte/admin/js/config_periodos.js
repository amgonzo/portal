let tablaReglas = null;
let reglasList = [];

const NOMBRES_MESES = [
    "", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio",
    "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
];

document.addEventListener('DOMContentLoaded', () => {
    // Validación de seguridad SSO inicial requerida por el sistema
    const token = localStorage.getItem('sso_token');
    if (!token) {
        window.location.href = 'index.php';
        return;
    }

    initDataTable();
    listarReglas();
});

function initDataTable() {
    if ($.fn.DataTable.isDataTable('#tablaReglas')) {
        $('#tablaReglas').DataTable().destroy();
    }
    
    tablaReglas = $('#tablaReglas').DataTable({
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
            }
        },
        responsive: true,
        autoWidth: false,
        paging: false,     // Son 12 filas fijas
        searching: false,  // Sin buscador
        ordering: false,   // 👈 DESACTIVA EL ORDENAMIENTO ALFABÉTICO (Conserva 1 a 12 de SQL)
        order: []          // 👈 PREVIENE EL ORDEN INICIAL POR DEFECTO
    });
}

// ---------------------------------------------------------------------
// 1. LISTAR REGLAS DE PERÍODO
// ---------------------------------------------------------------------
async function listarReglas() {
    try {
        const res = await fetch(API_BASE + '/ctacte/periodos/config_periodos.php?action=listar', {
            method: 'GET',
            headers: {
                "Authorization": "Bearer " + (localStorage.getItem('sso_token') || '')
            }
        });
        const json = await res.json();

        if (json.status !== 'ok') {
            toast(json.msg || "Error al cargar reglas de períodos", "error");
            return;
        }

        reglasList = json.data;
        tablaReglas.clear();

        reglasList.forEach(r => {
            const nombreMesOperativo = NOMBRES_MESES[r.mes_periodo] || `Mes ${r.mes_periodo}`;
            const nombreMesInicio = NOMBRES_MESES[r.mes_inicio];
            const nombreMesFin = NOMBRES_MESES[r.mes_fin];

            const strAnioInicio = r.resta_anio_inicio == 1 ? ' <span class="badge badge-warning">Año Anterior</span>' : '';

            const textoDesde = `<b>${String(r.dia_inicio).padStart(2, '0')}</b> de <b>${nombreMesInicio}</b>${strAnioInicio}`;
            const textoHasta = `<b>${String(r.dia_fin).padStart(2, '0')}</b> de <b>${nombreMesFin}</b>`;

            const acciones = `
                <button class="btn btn-sm btn-primary" onclick="editarRegla(${r.mes_periodo})" title="Editar Regla">
                    <i class="fas fa-edit"></i> Editar
                </button>
            `;

            tablaReglas.row.add([
                `<strong class="text-uppercase text-primary">${nombreMesOperativo}</strong>`,
                textoDesde,
                textoHasta,
                r.descripcion || '<span class="text-muted font-italic">Sin descripción</span>',
                `<div class="text-center">${acciones}</div>`
            ]);
        });

        tablaReglas.draw();

    } catch (err) {
        toast("Error de conexión al obtener reglas de períodos", "error");
    }
}

// ---------------------------------------------------------------------
// 2. ABRIR MODAL PARA EDITAR
// ---------------------------------------------------------------------
function editarRegla(mesPeriodo) {
    const regla = reglasList.find(r => r.mes_periodo == mesPeriodo);
    if (!regla) return;

    $('#edit_mes_periodo').val(regla.mes_periodo);
    $('#regla_mes_nombre').val((NOMBRES_MESES[regla.mes_periodo] || '').toUpperCase());
    
    $('#regla_dia_inicio').val(regla.dia_inicio);
    $('#regla_mes_inicio').val(regla.mes_inicio);
    $('#regla_resta_anio_inicio').prop('checked', parseInt(regla.resta_anio_inicio) === 1);

    $('#regla_dia_fin').val(regla.dia_fin);
    $('#regla_mes_fin').val(regla.mes_fin);

    $('#regla_descripcion').val(regla.descripcion || '');

    $('#ModalRegla').modal('show');
}

// ---------------------------------------------------------------------
// 3. GUARDAR CAMBIOS DE LA REGLA
// ---------------------------------------------------------------------
async function guardarRegla() {
    const diaInicio = $('#regla_dia_inicio').val();
    const diaFin = $('#regla_dia_fin').val();

    if (!diaInicio || !diaFin || diaInicio < 1 || diaInicio > 31 || diaFin < 1 || diaFin > 31) {
        toast("Ingrese días válidos entre 1 y 31", "warning");
        return;
    }

    const formData = new FormData(document.getElementById('formRegla'));
    
    // Si el checkbox no está marcado, enviamos 0 explícitamente
    if (!$('#regla_resta_anio_inicio').is(':checked')) {
        formData.set('resta_anio_inicio', '0');
    }

    try {
        const res = await fetch(API_BASE + '/ctacte/periodos/config_periodos.php?action=editar', {
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

        $('#ModalRegla').modal('hide');
        toast(json.msg, "success");
        listarReglas();

    } catch (err) {
        toast("Error al intentar guardar la regla", "error");
    }
}