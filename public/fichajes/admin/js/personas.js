// Variable global para identificar si estamos en "modo operador"
let ANIO_ACTIVO_SISTEMA = null;
let accionActual = null; // 'apto' o 'guardar'
const idUsuarioLogueado = window.idUsuarioLogueado;
const esMedico = window.esMedico;

    $(document).ready(function() {
    // 1. CARGA DE AÑOS (Y después el resto)
    $.get('../api/anios/get_anio_activo.php', function(response) {
        if (response.status === 'ok') {
            ANIO_ACTIVO_SISTEMA = parseInt(response.anio);
            // Una vez que sabemos el año, cargamos los datos
            cargarComboAnios();
            //inicializarModulo();
        } else {
            console.error("Error al obtener el año activo");
        }
    });
    /*
    $.get("../api/anios/get_anios.php", function(res) {
        let r = (typeof res === 'string') ? JSON.parse(res) : res;
        let html_anios = "";
        
        if(r.status === "ok" && r.data.length > 0) {
            r.data.forEach((a, index) => {
                // Seleccionamos el último año por defecto
                let selected = (index === r.data.length - 1) ? 'selected' : '';
                html_anios += `<option value="${a.anio}" ${selected}>${a.anio}</option>`;
            });
            $("#filtroAnioGlobal").html(html_anios);
        } else {
            // Fallback si no hay años en la DB
            let anioActual = new Date().getFullYear();
            $("#filtroAnioGlobal").html(`<option value="${anioActual}">${anioActual}</option>`);
        }

        // Una vez cargados los años, disparamos las tablas
        //cargarPersonas();
        //cargarCategoriasCheck();

    }).fail(function() {
        // Si falla la API de años, cargamos igual para no romper todo
        console.error("No se pudieron cargar los años");
        cargarPersonas();
    }); // <--- CIERRE DEL $.GET
    */

    // 2. EVENTOS DE UI
    $("#per_foto").change(function() {
        const file = this.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(event) {
                $("#previewFoto").html(`<img src="${event.target.result}">`);
            };
            reader.readAsDataURL(file);
        }
    });

    // Control de permisos para el botón "Nueva"
    if(!tienePermiso('pilotos_crear')) {
        $('#btnNuevaPersona').hide();
    } else {
        $('#btnNuevaPersona').show();
        $('#btnNuevaPersona').off('click').on('click', nuevaPersona);
    }
    
    // Tooltips
    $('body').tooltip({ selector: '[data-bs-toggle="tooltip"]' });

}); // <--- CIERRE DEL DOCUMENT READY

function cargarComboAnios() {
    $.get("../api/anios/get_anios.php", function(res) {
        let r = (typeof res === 'string') ? JSON.parse(res) : res;
        let html_anios = "";
        
        if(r.status === "ok" && r.data.length > 0) {
            r.data.forEach((a) => {
                // CAMBIO CLAVE: El seleccionado es el que coincide con el ACTIVO de la API
                let selected = (parseInt(a.anio) === ANIO_ACTIVO_SISTEMA) ? 'selected' : '';
                html_anios += `<option value="${a.anio}" ${selected}>${a.anio}</option>`;
            });
            $("#filtroAnioGlobal").html(html_anios);
        } else {
            let anioActual = new Date().getFullYear();
            $("#filtroAnioGlobal").html(`<option value="${anioActual}" selected>${anioActual}</option>`);
        }

        // 3. TERCERO: Ahora que el select TIENE el valor correcto, cargamos las tablas
        inicializarModulo();

    }).fail(function() {
        console.error("No se pudieron cargar los años");
        inicializarModulo();
    });
}

function inicializarModulo() {
    // Aquí ponés lo que antes hacías en el ready
    cargarPersonas(); 
    cargarCategoriasCheck();
    // ... cualquier otra cosa que necesite el año activo
}

        function cargarCategoriasCheck() {
            $.ajax({
                type: "GET",
                url: "../api/categorias/get_categorias.php",
                headers: {
                    "Authorization": "Bearer " + TOKEN
                },
                success: function(res) {
                    if (res.status === "ok") {
                        let html = '';
                        res.data.forEach(c => {
                            html += `
                        <div class="custom-control custom-checkbox mb-1">
                            <input type="checkbox" class="form-check-input check-cat" id="cat_${c.idcategoria}" value="${c.idcategoria}">
                            <label class="form-check-label" for="cat_${c.idcategoria}">${c.nombrecategoria}</label>
                        </div>`;
                        });
                        $("#listaCategoriasCheck").html(html);
                    }
                }
            });
        }

        function cargarPersonas() {
            if (ANIO_ACTIVO_SISTEMA === null) return;
            const anio = $("#filtroAnioGlobal").val();
            const baja = $("#checkVerBajas").is(":checked") ? 1 : 0;
            // --- BLOQUEO POR AÑO ACTIVO ---
            // Si el año seleccionado no es el activo, desactivamos permisos de escritura
            const esAnioCerrado = (parseInt(anio) !== ANIO_ACTIVO_SISTEMA);
            
            // Bloqueamos el botón de Nueva Persona de la interfaz
            if (esAnioCerrado) {
                $('#btnNuevaPersona').hide();
            } else {
                $('#btnNuevaPersona').show();
            }

            $.ajax({
                type: "GET",
                url: "../api/personas/get_personas.php",
                data: { anio: anio, baja: baja },
                success: function(res) {
                    if (res.status === "ok") {
                        if ($.fn.DataTable.isDataTable('#tablaPersonas')) $('#tablaPersonas').DataTable().destroy();

                        let html = "";
                        res.data.forEach(p => {
                            let estado_tags = "";
                            let pagos_tags = "";
                            if (p.piloto == 1) estado_tags += '<span class="badge badge-info mr-1" data-bs-toggle="tooltip" title="Piloto Registrado">P</span>';
                            if (p.copiloto == 1) estado_tags += '<span class="badge badge-secondary mr-1" data-bs-toggle="tooltip" title="Copiloto Registrado">C</span>';

                        let iconoApto = "";
                            if (p.aptomedico == 1) {
                                // Ícono de escudo verde si es APTO
                                iconoApto = ` <i class="fas fa-check-circle text-success ml-1" data-bs-toggle="tooltip" title="APTO MÉDICO CONFIRMADO"></i>`;
                            } else {
                                // Ícono de médico gris si está PENDIENTE
                                iconoApto = ` <i class="fas fa-user-md text-muted ml-1" style="opacity:0.5" data-bs-toggle="tooltip" title="Apto Médico Pendiente"></i>`;
                            }
                            //PAGOS ++++++++++++++++++++++++++++++++++++
                            if(tienePermiso('pagos_ver')) {
                                if (p.pagolicenciaanual == 1) {
                                    pagos_tags += '<span class="badge badge-warning mr-1" data-bs-toggle="tooltip" title="Licencia Anual Pagada">A</span>';
                                }

                                // Semestre 1
                                if (p.pagolicenciasemestral1 == 1) {
                                    pagos_tags += '<span class="badge bg-success mr-1" data-bs-toggle="tooltip" title="1° Semestre Pagado">S1</span>';
                                }

                                // Semestre 2
                                if (p.pagolicenciasemestral2 == 1) {
                                    pagos_tags += '<span class="badge bg-success mr-1" data-bs-toggle="tooltip" title="2° Semestre Pagado">S2</span>';
                                }
                            }
                            //PAGOS ++++++++++++++++++++++++++++++++++++
                            let sinDato = '<span class="text-muted">---</span>';

                            let colProv = sinDato;
                                if (p.licenciaprovisoria == 1) {
                                    // Si mailprovisorioenviado es 1 -> verde, sino -> gris (muted)
                                    let colorP = (p.mailprovisorioenviado == 1) ? 'text-success' : 'text-muted';
                                    let tituloP = (p.mailprovisorioenviado == 1) ? 'Enviada por Mail' : 'Generada (Pendiente envío)';
                                    
                                    colProv = `<a href="javascript:void(0)" onclick="verLicenciaGenerada('${p.documento}', 'PRO')" class="${colorP}" data-bs-toggle="tooltip" title="${tituloP}">
                                                <i class="fas fa-address-card fa-lg"></i>
                                            </a>`;
                                }

                            let colDef = sinDato;
                                if (p.licenciadefinitiva == 1) {
                                    // Si maildefinitivoenviado es 1 -> verde, sino -> gris (muted)
                                    let colorD = (p.maildefinitivoenviado == 1) ? 'text-success' : 'text-muted';
                                    let tituloD = (p.maildefinitivoenviado == 1) ? 'Enviada por Mail' : 'Generada (Pendiente envío)';
                                    
                                    colDef = `<a href="javascript:void(0)" onclick="verLicenciaGenerada('${p.documento}', 'DEF')" class="${colorD}" data-bs-toggle="tooltip" title="${tituloD}">
                                                <i class="fas fa-id-badge fa-lg"></i>
                                            </a>`;
                                }
                                
                            let lic = "";
                            if (p.licenciadefinitiva == 1) {
                                lic = ` <i class="fas fa-star text-warning" data-bs-toggle="tooltip" title="Licencia Definitiva"></i>`;
                            }

                            // --- LÓGICA DE BOTONES CON CANDADO DE AÑO ---
                            // Si el año está cerrado, "pisamos" los permisos para que no dibuje los botones de acción
                            let permisoEditar = esAnioCerrado ? false : tienePermiso('pilotos_editar');
                            let permisoEliminar = esAnioCerrado ? false : tienePermiso('pilotos_eliminar');
                            let permisoGenerarDef = esAnioCerrado ? false : tienePermiso('licencias_definitivas_generar');
                            let pagosVer = tienePermiso('pagos_ver');

                            html += `<tr>
                                <td><span class="text-monospace font-weight-bold">${p.documento}</span></td>
                                <td>${p.historiaclinica || '---'}</td>
                                <td>${p.apellidonombre.toUpperCase()}${lic}</td>
                                <td>${estado_tags || '<small class="text-muted">Inactivo</small>'}</td>
                                
                                ${pagosVer ? `<td>${pagos_tags || '<small class="text-muted">sin pagos</small>'}</td>` : ''}

                                <td id="apto-col-${p.documento}">${iconoApto}</td>
                                <td class="text-center">${colProv}</td>
                                <td class="text-center">${colDef}</td>
                                <td>
                                    ${permisoEditar ? `
                                    <button class="btn btn-sm btn-info" onclick="editarPersona('${p.documento}')" data-bs-toggle="tooltip" title="Editar Ficha">
                                        <i class="fas fa-edit"></i>
                                    </button>` : ''}
                                    
                                    ${permisoEliminar ? `
                                    <button class="btn btn-sm ${p.baja == 1 ? 'btn-secondary' : 'btn-danger'}" 
                                            onclick="cambiarEstadoPersona('${p.documento}', ${p.baja == 1 ? 0 : 1})" 
                                            data-bs-toggle="tooltip" 
                                            title="${p.baja == 1 ? 'Dar de Alta' : 'Dar de Baja'}">
                                        <i class="fas ${p.baja == 1 ? 'fa-user-plus' : 'fa-trash-alt'}"></i>
                                    </button>` : ''}
                                    
                                    ${tienePermiso('antecedentes_ver') ? `
                                    <button class="btn btn-sm btn-success" onclick="abrirHistorialMedico('${p.documento}', '${p.apellidonombre.toUpperCase()}')" data-bs-toggle="tooltip" title="Historial Médico">
                                        <i class="fas fa-user-md"></i>
                                    </button>` : ''}

                                    ${tienePermiso('estudios_medicos_ver') ? `
                                    <button class="btn btn-sm btn-primary" onclick="abrirEstudios('${p.documento}', '${p.apellidonombre.toUpperCase()}')" data-bs-toggle="tooltip" title="Estudios Médicos">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>` : ''}
                                    
                                    ${/*tienePermiso('recetas_ver') ? `
                                    <button class="btn btn-sm btn-outline-dark" onclick="abrirRecetario('${p.documento}', '${p.apellidonombre.toUpperCase()}')" data-bs-toggle="tooltip" title="Recetario">
                                        <i class="fas fa-file-medical"></i>
                                    </button>` : */''}
                                    
                                    ${permisoGenerarDef ? `
                                        ${p.aptomedico == 1 ? `
                                            <button class="btn btn-sm btn-warning" 
                                                    onclick="generarLicenciaDefinitiva('${p.documento}', '${p.apellidonombre.toUpperCase()}')" 
                                                    data-bs-toggle="tooltip" 
                                                    title="Generar Licencia Definitiva">
                                                <i class="fas fa-id-card text-white"></i>
                                            </button>` 
                                        : ''}` 
                                    : ''}
                                </td>
                            </tr>`;
                        });
                        $("#listaPersonas").html(html);
                        $('#tablaPersonas').DataTable({
                            "autoWidth": false, // CRÍTICO: Para que no recalcule anchos raros
                            "columnDefs": [
                                { "className": "text-center", "targets": [3, 4, 5, 6] },
                                { "width": "180px", "targets": 7 }, // El índice 7 es la columna de Acciones
                                { "className": "text-nowrap", "targets": 7 }
                            ],
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
                        // Refresca los cartelitos después de crear la tabla
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    }
                }
            });
        }

        function togglePais() {
            const seleccion = $("#per_pais").val();
            if (seleccion === "ARGENTINA") {
                $(".div-arg").show();
                $(".div-otro").hide();
            } else {
                $(".div-arg").hide();
                $(".div-otro").show();
            }
        }

        function editarPersona(doc, origen = '') {
            const anio = $("#filtroAnioGlobal").val();
            
            $.ajax({
                type: "GET",
                url: "../api/personas/get_persona_detalle.php",
                data: {
                    documento: doc,
                    anio: anio
                },
                success: function(res) {
                    if (res.status === "ok") {
                        const p = res.data;
                        $("#formPersona")[0].reset();
                        $("#per_pais").val(p.pais);
                        togglePais(); // Esto muestra/oculta los divs según corresponda

                        if (p.pais === "ARGENTINA") {
                            // 1. Cargamos provincias y marcamos la que tiene el usuario
                            cargarProvincias(p.idprovincia);
                            
                            // 2. Cargamos las localidades de esa provincia y marcamos la del usuario
                            if (p.idprovincia) {
                                cargarLocalidades(p.idprovincia, p.idlocalidad);
                            }
                        } else {
                            // Si es extranjero, llenamos los campos de texto
                            $("#per_paisext").val(p.paisext);
                            $("#per_provext").val(p.provext);
                            $("#per_locext").val(p.locext);
                        }
                        
                        if (origen === 'blur' && p.baja == 1) {
                            $("#per_baja").val(0); // Forzamos el alta porque aceptó reactivarlo
                        } else {
                            $("#per_baja").val(p.baja); // Mantiene su estado real
                        }
                        
                        $('.nav-tabs a[href="#tabGral"]').tab('show');

                        $("#edit_persona_id").val(p.documento);
                        $("#per_documento").val(p.documento).prop("readonly", true);
                        $("#per_historiaclinica").val(p.historiaclinica);
                        $("#per_nombre").val(p.apellidonombre);
                        $("#per_nacimiento").val(p.fechanacimiento);

                        // --- LÓGICA DE FOTO CORREGIDA ---
                        // Si hay foto y NO es la default, intentamos mostrarla
                        if (p.foto_carnet && p.foto_carnet !== 'default.png') {
                            // Agregamos un timestamp (?t=...) para evitar que el navegador cachee fotos viejas
                            $("#previewFoto").html(`<img 
                                src="../api/helpers/foto_reader.php?file=${encodeURIComponent(p.foto_carnet)}&t=${Date.now()}"
                                class="img-fluid rounded"
                                style="max-width: 150px; max-height: 180px; object-fit: cover;"
                                alt="Foto del titular"
                                onerror="this.parentElement.innerHTML='<i class=&quot;fas fa-user fa-4x text-secondary&quot;></i>';"
                            >`);
                        } else {
                            // Si no tiene, forzamos la silueta
                            $("#previewFoto").html('<i class="fas fa-user fa-4x text-secondary"></i>');
                        }
                        // --------------------------------

                        $("#per_domicilio").val(p.domicilio);
                        $("#per_email").val(p.email);
                        $("#per_tel").val(p.telefono);
                        $("#per_sangre").val(p.gruposanguineo);
                        $("#per_usalentes").prop("checked", parseInt(p.usalentes) == 1);
                        $("#checkPiloto").prop("checked", p.piloto == 1);
                        $("#checkCopiloto").prop("checked", p.copiloto == 1);
                        $("#swProv").prop("checked", p.licenciaprovisoria == 1);
                        $("#swDef").prop("checked", p.licenciadefinitiva == 1);

                        if (parseInt(p.sw_observaciones) === 1) {
                            $('#sw_observaciones').prop('checked', true);
                            $('#per_observaciones').removeClass('d-none').val(p.observaciones);
                        } else {
                            $('#sw_observaciones').prop('checked', false);
                            $('#per_observaciones').addClass('d-none').val('');
                        }
                        if (parseInt(p.sw_alergias) === 1) {
                            $('#sw_alergias').prop('checked', true);
                            $('#label_alergias').text('Declara'); // Importante actualizar el texto
                            $('#per_alergias').removeClass('d-none').val(p.alergias);
                        } else {
                            $('#sw_alergias').prop('checked', false);
                            $('#label_alergias').text('No declara');
                            $('#per_alergias').addClass('d-none').val('');
                        }
                        $("#pagolicenciaanual").prop("checked", parseInt(p.pagolicenciaanual) == 1);
                        $("#pagolicenciasemestral1").prop("checked", parseInt(p.pagolicenciasemestral1) == 1);
                        $("#pagolicenciasemestral2").prop("checked", parseInt(p.pagolicenciasemestral2) == 1);
                        

                        $(".check-cat").prop("checked", false);
                        if (p.categorias) {
                            p.categorias.forEach(id_cat => {
                                $(`#cat_${id_cat}`).prop("checked", true);
                            });
                        }

                        $(".modal-title").text("Editando: " + p.apellidonombre.toUpperCase());
                        $("#ModalPersona").modal("show");
                    }
                }
            });
        }


        function guardarPersona() {

            const formulario = document.getElementById('formPersona');
            const esArgentina = ($('#per_pais').val() === 'ARGENTINA');

            const ordenTabs = ['tabGral', 'tabDomicilio', 'tabSalud', 'tabRoles'];
            let errorEncontrado = false;

            for (let idTab of ordenTabs) {
                const pane = document.getElementById(idTab);
                const primerInvalidoEnTab = pane.querySelector(':invalid');

                if (primerInvalidoEnTab) {
                    // Si hay un error en esta pestaña, la activamos
                    $(`#personaTabs a[href="#${idTab}"]`).tab('show');

                    // Esperamos a que la pestaña sea visible para reportar el error
                    setTimeout(() => {
                        primerInvalidoEnTab.reportValidity();
                        primerInvalidoEnTab.focus();
                    }, 250);

                    errorEncontrado = true;
                    break; // Salimos del bucle: solo procesamos el primer error de la primera pestaña fallida
                }
            }

            // Si encontramos algún error en los campos nativos, cortamos acá
            if (errorEncontrado) return;
            
            // 2. Validación lógica según País
            if (esArgentina) {
                // Si es Argentina, Provincia y Localidad NO pueden estar vacíos
                if ($('#per_idprovincia').val() == "" || $('#per_idlocalidad').val() == "") {
                    toast('Para Argentina, la Provincia y Localidad son obligatorias', 'error');
                    $('a[href="#tabDomicilio"]').tab('show'); // Lo llevamos a la pestaña
                    return;
                }
            } else {
                // Si es OTRO PAÍS, validamos los campos de texto del extranjero
                if ($('#per_paisext').val().trim() == "" || $('#per_provext').val().trim() == "") {
                    toast('Por favor, complete el nombre del País y la Provincia/Estado', 'error');
                    $('a[href="#tabDomicilio"]').tab('show');
                    return;
                }
            }
            
            // 3. Validación de Roles (lo que hablamos antes)
            if (!$('#checkPiloto').is(':checked') && !$('#checkCopiloto').is(':checked')) {
                toast('Debe seleccionar al menos un rol', 'error');
                $('a[href="#tabRoles"]').tab('show');
                return;
            }
            /*
            if (!$("#per_documento").val() || !$("#per_nombre").val()) {
                toast("DNI y Nombre son obligatorios", "warning");
                return;
            }*/

            // 4. Validación de Categorías (Al menos UNA seleccionada)
            // Buscamos cuántos checkboxes están marcados dentro del contenedor de categorías
            const categoriasMarcadas = $('#listaCategoriasCheck input[type="checkbox"]:checked').length;

            if (categoriasMarcadas === 0) {
                $(`#personaTabs a[href="#tabRoles"]`).tab('show');
                toast('Debe habilitar al menos una categoría para el piloto', 'error');
                return;
            }

            let fd = new FormData();
            let fotoInput = document.getElementById('per_foto');
            if (fotoInput && fotoInput.files.length > 0) {
                fd.append('foto', fotoInput.files[0]);
            }

            let cats = [];
            $(".check-cat:checked").each(function() {
                cats.push($(this).val());
            });

            fd.append('documento', $("#per_documento").val().trim());
            fd.append('historiaclinica', $("#per_historiaclinica").val().trim().toUpperCase());
            fd.append('nombre', $("#per_nombre").val().trim().toUpperCase());
            fd.append('nacimiento', $("#per_nacimiento").val());
            fd.append('domicilio', $("#per_domicilio").val());
            fd.append('email', $("#per_email").val().trim().toLowerCase());
            fd.append('telefono', $("#per_tel").val());
            fd.append('sangre', $("#per_sangre").val());
            fd.append('usalentes', $("#per_usalentes").is(":checked") ? 1 : 0);
            fd.append('sw_observaciones', $("#sw_observaciones").is(':checked') ? 1 : 0);
            fd.append('per_observaciones', $("#sw_observaciones").is(':checked') ? $("#per_observaciones").val() : '');
            fd.append('sw_alergias', $("#sw_alergias").is(':checked') ? 1 : 0);
            fd.append('per_alergias', $("#sw_alergias").is(':checked') ? $("#per_alergias").val() : '');
            fd.append('es_piloto', $("#checkPiloto").is(":checked") ? 1 : 0);
            fd.append('es_copiloto', $("#checkCopiloto").is(":checked") ? 1 : 0);
            fd.append('es_provisoria', $("#swProv").is(":checked") ? 1 : 0);
            fd.append('es_definitiva', $("#swDef").is(":checked") ? 1 : 0);
            fd.append('categorias', JSON.stringify(cats));
            fd.append('pais', $("#per_pais").val());
            fd.append('paisext', $("#per_paisext").val().trim().toUpperCase());
            fd.append('idprovincia', $("#per_idprovincia").val());
            fd.append('idlocalidad', $("#per_idlocalidad").val());
            fd.append('provext', $("#per_provext").val().trim().toUpperCase());
            fd.append('locext', $("#per_locext").val().trim().toUpperCase());
            fd.append('pagolicenciaanual', $("#pagolicenciaanual").is(':checked') ? 1 : 0);
            fd.append('pagolicenciasemestral1', $("#pagolicenciasemestral1").is(':checked') ? 1 : 0);
            fd.append('pagolicenciasemestral2', $("#pagolicenciasemestral2").is(':checked') ? 1 : 0);
            fd.append('baja', $("#per_baja").val());

            if (!$("#per_documento").val() || !$("#per_nombre").val()) {
                toast("DNI y Nombre son obligatorios", "warning");
                return;
            }

            $.ajax({
                type: "POST",
                url: "../api/personas/crear_persona.php",
                headers: {
                    "Authorization": "Bearer " + TOKEN
                },
                data: fd,
                processData: false,
                contentType: false,
                /*
                success: function(res) {
                    if (res.status === "ok") {
                        toast("✅ Guardado correctamente");
                        $("#ModalPersona").modal("hide");
                        cargarPersonas();
                    } else {
                        toast("Error: " + res.msg, "error");
                    }
                }*/
               success: function(res) {
                    if (res.status === "ok") {
                        if (res.hc_actualizada) {
                            toast(`✅ Guardado. El nro de HC se ajustó a ${res.nueva_hc} porque el anterior ya se había ocupado.`, "info");
                        } else {
                            toast("✅ Guardado correctamente");
                        }
                        $("#ModalPersona").modal("hide");
                        cargarPersonas();
                    }
                }
            });
        }

        function nuevaPersona() {
            $("#formPersona")[0].reset();
            // Usamos text-secondary para que coincida con la de editar
            $("#previewFoto").html('<i class="fas fa-user fa-4x text-secondary"></i>');

            $('.nav-tabs a[href="#tabGral"]').tab('show');
            $("#per_documento").prop("readonly", false);
            
            $("#per_pais").val("ARGENTINA");
            togglePais(); 
            
            // Cargamos las provincias para que el combo no esté vacío
            cargarProvincias();
            
            $.get("../api/personas/get_siguiente_historiaclinica.php", function(res) {
                if (res.status === "ok") {
                    // Ponemos el valor pero NO bloqueamos el input
                    $("#per_historiaclinica").val(res.siguiente);
                    
                    // Opcional: Seleccionar el texto para que si el usuario 
                    // empieza a escribir, se borre la sugerencia automáticamente
                    $("#per_historiaclinica").select(); 
                }
            });

            $(".modal-title").text("Nueva Persona");
            $("#ModalPersona").modal("show");
        }

        // --- FUNCIONES DEL HISTORIAL MÉDICO CON PAGINACIÓN ---

// --- LOGICA MÉDICA (PAGINACIÓN DE A 3 Y DNI POR DATA) ---

// --- LÓGICA MÉDICA (RUTAS api/antecedentes/ + PAGINACIÓN) ---

// --- LÓGICA MÉDICA (FIX DEFINITIVO RUTAS Y PRIMER REGISTRO) ---

let currentOffset = 0;
const limit = 3; 
let documentoSeleccionado = null; 
let anioSeleccionado = null;     

function abrirHistorialMedico(dni, nombre) {
    currentOffset = 0; 
    documentoSeleccionado = dni; // Guardamos para el Apto
    const anioSeleccionado = parseInt($("#filtroAnioGlobal").val());
    
    const esAnioDiferente = (anioSeleccionado !== ANIO_ACTIVO_SISTEMA);
    const noTienePermiso = !tienePermiso('antecedentes_crear');
    const esSoloLectura = (esAnioDiferente || noTienePermiso);

    $('#nombrePaciente').text(nombre); 
    $('#formNuevoAntecedente').data('dni', dni); 

    // --- CORRECCIÓN ACÁ: Mismas variables, nueva lógica para el botón ---
    if (!esAnioDiferente && (tienePermiso('antecedentes_crear') || tienePermiso('licencias_dar_apto_medico'))) {
        $('#contenedorBotonApto').show();
        verificarEstadoApto(dni, anioSeleccionado);
    } else {
        $('#contenedorBotonApto').hide();
    }
    
    if (esSoloLectura) {
        $('#rowMedico').addClass('modo-lectura');
        $('#statusApto').html('<span class="badge badge-dark"><i class="fas fa-lock"></i> HISTORIAL (Lectura)</span>');
    } else {
        $('#rowMedico').removeClass('modo-lectura');
        // El show del botón ya lo manejamos arriba
    }
    
    // Mostrar/ocultar el botón de agregar según permiso
    if(tienePermiso('antecedentes_crear')&& !esSoloLectura) {
        //$('#btnAgregarAntecedente').show();
        //$('#formNuevoAntecedente').show();

        // --- NUEVO: AUTOCOMPLETAR DATOS ---
        $.get("../api/antecedentes/get_ultimo_antecedente.php", { documento: dni }, function(res) {
            if (res.status === "ok" && res.data) {
                // Copiamos los datos tal cual vienen de la base de datos
                $("#med_peso").val(res.data.peso);
                $("#med_altura").val(res.data.altura);
                $("#med_edad").val(res.data.edadmomento); // Copia la edad literal de la DB
            }
        });
        // ----------------------------------
    } else {
        $('#btnAgregarAntecedente').hide();
        $('#formNuevoAntecedente').hide();
    }

    $('#btnCargarMas').hide(); 
    $('#historialContenedor').html('<div class="text-center p-3 text-muted"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');
    
    $('#modalMedicos').modal('show');

    if(tienePermiso('antecedentes_ver')) {
        cargarMasAntecedentes(dni);
    }
}

function cargarMasDesdeBoton() {
    const dni = $('#formNuevoAntecedente').data('dni');
    if(dni) cargarMasAntecedentes(dni);
}

function cargarMasAntecedentes(dni) {
    $.ajax({
        type: "GET",
        url: "../api/antecedentes/get_antecedentes.php", 
        data: { documento: dni, limit: limit, offset: currentOffset },
        success: function(res) {
            let respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            
            // --- CAMBIO 1: Extraemos el array real de la propiedad 'data' ---
            let lista = respuesta.data || []; 

            if (currentOffset === 0) $('#historialContenedor').empty();

            // --- CAMBIO 2: Validamos contra 'lista' en vez de 'datos' ---
            if (!lista || lista.length === 0) {
                if (currentOffset === 0) {
                    $('#historialContenedor').html('<div class="text-center p-4 border rounded bg-body-tertiary"><p class="text-muted mb-0">Sin registros previos.</p></div>');
                }
                $('#btnCargarMas').hide();
                return;
            }

            // ACÁ ES DONDE USAMOS LA FUNCIÓN DE RENDERIZADO DETALLADO
            // --- CAMBIO 3: Recorremos 'lista' ---
            lista.forEach(ant => {
                $('#historialContenedor').append(renderizarBurbuja(ant));
            });

            if (lista.length < limit) {
                $('#btnCargarMas').hide();
            } else {
                $('#btnCargarMas').show();
                currentOffset += limit;
            }
        },
        error: function() {
            if (currentOffset === 0) $('#historialContenedor').html('<div class="text-center p-4">Error de conexión.</div>');
            $('#btnCargarMas').hide();
        }
    });
}

$(document).on("submit", "#formNuevoAntecedente", async function(e) {
    e.preventDefault();

    const form = this;
    const dni = $(form).data('dni'); 
    
    if(!dni) {
        toast("Error: No se detectó el DNI", "error");
        return;
    }

    if (!window.esMedico && !tienePermiso('antecedentes_suplantacion')) {
        toast("No tenés permiso para cargar antecedentes", "error");
        return;
    }
    
    let idMedicoFinal = window.idUsuarioLogueado;
    let idUsuarioFinal = window.idUsuarioLogueado;
    let auditoria = "";

    //console.log("window.esMedico:", window.esMedico);
    //console.log("permiso:", tienePermiso('antecedentes_suplantacion'));
    // 🔥 SOLO SI ES SUPLANTACIÓN
    if (!window.esMedico && tienePermiso('antecedentes_suplantacion')) {

        const { idMedico } = await window.elegirMedico();

        idMedicoFinal = idMedico;
        idUsuarioFinal = window.idUsuarioLogueado;
        auditoria = `Suplantado por operador ID: ${window.idUsuarioLogueado}`;
    }

    // 🔥 USÁ UNA SOLA FUNCIÓN (la tuya original)
    enviarDataAntecedente(form, dni, idMedicoFinal, idUsuarioFinal, auditoria);
});

function enviarDataAntecedente(form, dni, idMedico, idCarga, txtAuditoria) {
    let formData = new FormData(form);
    formData.append('documento', dni);
    formData.append('idusuario', idMedico);
    formData.append('idusuariologueado', idCarga);
    formData.append('auditoriasuplantacion', txtAuditoria);

    $.ajax({
        type: "POST",
        url: "../api/antecedentes/guardar_antecedente.php", 
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            if (r.status === "ok") {
                toast("Evolución registrada");
                $(form)[0].reset();
                $(form).data('dni', dni);
                $('#id_edicion_antecedente').remove();
                
                $(form).find('button[type="submit"]')
                    .html('<i class="fas fa-save"></i> Registrar Evolución')
                    .removeClass('btn-warning').addClass('btn-success');

                //$('#sw_alergias').prop('checked', true).trigger('change');
                $('.check-estudio').each(function() {
                    $(this).closest('.border').find('textarea').addClass('d-none').val('');
                });

                currentOffset = 0;
                cargarMasAntecedentes(dni);
            } else {
                toast(r.msg, "error");
            }
        }
    });
}

$(document).on('change', '.check-estudio', function() {
    const textarea = $(this).closest('.border').find('textarea');
    if ($(this).is(':checked')) {
        textarea.addClass('d-none').val(''); // Oculta y limpia si vuelve a marcar normal
    } else {
        textarea.removeClass('d-none').focus(); // Muestra si desmarca
    }
});

function renderizarBurbuja(item) {
    const mostrarEstudio = (val, label, obs, fecha = null) => {
        let clase = (val == 1) ? 'badge-success' : 'badge-danger';
        let texto = (val == 1) ? label : `${label} ⚠️`;
        let htmlExtra = '';

        if (label === 'EEG' && fecha && fecha !== '0000-00-00') {
            let claseTexto = (val == 0) ? 'text-danger' : 'text-dark';
            let borde = (val == 0) ? 'border-danger' : 'border-secondary';

            htmlExtra = `
            <div class="${claseTexto} mb-1 p-1 border-left ${borde}" style="font-size: 0.75rem; border-left-width: 3px !important;">
                <strong>EEG Fecha:</strong> ${fecha}
                ${(val == 0 && obs) ? `<br><strong>Obs:</strong> ${obs}` : ''}
            </div>`;
        }

        //let htmlExtra = (val == 0 && obs) ? `<div class="text-danger mb-1" style="font-size: 0.75rem; padding-left: 5px; border-left: 2px solid #dc3545;"><strong>Obs ${label}:</strong> ${obs}</div>` : '';
        
        else if (val == 0 && obs) {
            htmlExtra = `
                <div class="text-danger mb-1 p-1 border-left border-danger" style="font-size: 0.75rem; border-left-width: 3px !important;">
                    <strong>Obs ${label}:</strong> ${obs}
                </div>`;
        }
        return {
            badge: `<span class="badge ${clase} mr-1 mb-1" style="font-size:0.7rem">${texto}</span>`,
            detalle: htmlExtra
        };
    };

    // Procesamos cada estudio
    const ecg = mostrarEstudio(item.ecg_normal, 'ECG', item.ecg_obs); 
    const eeg = mostrarEstudio(item.eeg_normal, 'EEG', item.eeg_obs, item.eeg_fecha);
    const labo = mostrarEstudio(item.labo_normal, 'LAB', item.labo_obs);
    const ergo = mostrarEstudio(item.ergo_normal, 'ERGO', item.ergo_obs);
    const rx = mostrarEstudio(item.rx_torax_normal, 'RX', item.rx_torax_obs);

    // --- LÓGICA DE BOTONES (EDITAR Y VER TODO) ---
    const tienePermisoEditar = tienePermiso('antecedentes_editar');
    const tienePermisoSuplantar = tienePermiso('antecedentes_suplantacion');

    const idActual = parseInt(window.idUsuarioLogueado, 10);
    const idUsuario = parseInt(item.idusuario, 10);
    const idUsuarioLog = parseInt(item.idusuariologueado, 10);
    const estado = parseInt(item.estado, 10);

    const esCreador = idUsuario === idActual;
    const esSuplantador = idUsuarioLog === idActual;

    const puedeEditar =
        estado === 0 &&
        (
            (esCreador && tienePermisoEditar) ||
            (
                esSuplantador &&
                tienePermisoEditar &&
                tienePermisoSuplantar
            )
        );

    // Se puede editar solo si el estado es 0 y el registro es del usuario logueado
    //const puedeEditar = (item.estado == 0 && item.idusuario == ID_USUARIO_LOGUEADO);
    //const puedeEditar = (item.estado == 0 && parseInt(item.idusuario) == parseInt(ID_USUARIO_LOGUEADO));
    //const puedeEditar = tienePermiso('antecedentes_editar') && (item.estado == 0 && parseInt(item.idusuario) == parseInt(ID_USUARIO_LOGUEADO));
    /*
    const tienePermisoEditar = tienePermiso('antecedentes_editar');
    const tienePermisoSuplantar = tienePermiso('antecedentes_suplantacion');

    const puedeEditar = tienePermisoEditar 
        && item.estado == 0
        && (
            parseInt(item.idusuario) === parseInt(idUsuarioLogueado)
            || (
                parseInt(item.idusuariologueado) === parseInt(idUsuarioLogueado)
                && tienePermisoSuplantar
            )
        );
   /*console.log({
        idusuario: item.idusuario,
        idusuariologueado: item.idusuariologueado,
        logueado: ID_USUARIO_LOGUEADO,
        tienePermisoEditar,
        tienePermisoSuplantar
    });*/
    //const puedeEditar = tienePermiso('antecedentes_editar') && (item.estado == 0 && parseInt(item.idusuario) == parseInt(ID_USUARIO_LOGUEADO));
    // Convertimos el objeto item a string para pasarlo por la función "Ver Todo"
    const itemJson = JSON.stringify(item).replace(/'/g, "&apos;");

    return `
    <div class="burbuja-medica mb-3 p-3 border-left border-primary bg-white shadow-sm" style="border-left-width: 5px !important; border-radius: 5px;">
        <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
            <strong class="text-primary small"><i class="fas fa-user-md"></i> DR. ${item.medico.toUpperCase()}</strong>
            <div class="d-flex align-items-center">
                ${puedeEditar ? `
                    <button class="btn btn-xs btn-outline-warning mr-1" onclick="editarAntecedente(${item.idantecedente})" style="padding: 0px 4px; font-size: 0.7rem;" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>` : ''}
                
                <button class="btn btn-xs btn-outline-info mr-2" onclick='verDetalleCompleto(${itemJson})' style="padding: 0px 4px; font-size: 0.7rem;" title="Ver Detalle">
                    <i class="fas fa-eye"></i>
                </button>

                <span class="badge badge-secondary" style="font-size: 0.7rem;">${item.fecharegistro}</span>
            </div>
        </div>

        <div class="row no-gutters text-center bg-body-tertiary rounded py-1 mb-3">
            <div class="col-4 small border-right"><strong>${item.peso}</strong> kg</div>
            <div class="col-4 small border-right"><strong>${item.altura}</strong> cm</div>
            <div class="col-4 small"><strong>${item.edadmomento}</strong> años</div>
        </div>

        <div class="mb-3">
            <small class="font-weight-bold text-muted text-uppercase d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Antecedentes</small>
            <div class="p-2 bg-body-tertiary-custom rounded" style="background-color: #fdfdfd; border: 1px solid #eee;">
                <p class="small text-dark mb-0" style="white-space: pre-wrap;">${item.antecedentes || 'Sin notas'}</p>
            </div>
        </div>

        <div class="small py-2 border-top border-bottom mb-3 bg-white">
            <i class="fas fa-eye text-muted mr-1"></i> <strong>Visión:</strong> OD: <span class="text-primary">${item.ag_visual_od || '-'}</span> | OI: <span class="text-primary">${item.ag_visual_oi || '-'}</span>
        </div>

        <div class="detalles-estudios mb-3">
            ${ecg.detalle} ${eeg.detalle} ${labo.detalle} ${ergo.detalle} ${rx.detalle}
        </div>

        <div class="pt-2 border-top">
            <small class="font-weight-bold text-muted text-uppercase d-block mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Observación General:</small>
            <p class="small text-dark mb-0" style="white-space: pre-wrap; font-style: italic;">${item.observaciones || 'Sin notas'}</p>
        </div>
    </div>`;
}

function verDetalleCompleto(item) {
    const textoNormal = (val) => val == 1 
        ? '<span class="text-success font-weight-bold">NORMAL</span>' 
        : '<span class="text-danger font-weight-bold">CON OBSERVACIONES</span>';

    let html = `
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead class="thead-light">
                <tr><th colspan="2">INFORMACIÓN DEL REGISTRO</th></tr>
            </thead>
            <tbody>
                <tr><td width="30%"><strong>Médico:</strong></td><td>${(item.medico || 'S/D').toUpperCase()}</td></tr>
                <tr><td><strong>Fecha y Hora:</strong></td><td>${item.fecharegistro}</td></tr>
                <tr><td><strong>Estado:</strong></td><td>${item.estado == 0 ? '<span class="badge bg-success">Abierto</span>' : '<span class="badge badge-secondary">Cerrado</span>'}</td></tr>
            </tbody>

            <thead class="thead-light">
                <tr><th colspan="2">DATOS ANTROPOMÉTRICOS</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>Peso / Altura:</strong></td><td>${item.peso || '-'} kg / ${item.altura || '-'} cm</td></tr>
                <tr><td><strong>Edad al momento:</strong></td><td>${item.edadmomento || '-'} años</td></tr>
            </tbody>

            <thead class="thead-light">
                <tr><th colspan="2">ALERGIAS</th></tr>
            </thead>
            <thead class="thead-light">
                <tr><th colspan="2">ANTECEDENTES</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="2" style="white-space: pre-wrap;">${item.antecedentes || 'Sin notas adicionales.'}</td></tr>
            </tbody>

            <thead class="thead-light">
                <tr><th colspan="2">ESTUDIOS COMPLEMENTARIOS</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>ECG:</strong></td><td>${textoNormal(item.ecg_normal)} | Obs: ${item.ecg_obs || '-'}</td></tr>
                <!--<tr><td><strong>EEG:</strong></td><td>${textoNormal(item.eeg_normal)} | Obs: ${item.eeg_obs || '-'}</td></tr>-->
                <tr>
                    <td><strong>EEG:</strong></td>
                    <td>
                        ${textoNormal(item.eeg_normal)} 
                        <span class="text-muted small ml-2">(${item.eeg_fecha || 'Sin fecha'})</span>
                        | Obs: ${item.eeg_obs || '-'}
                    </td>
                </tr>
                <tr><td><strong>Laboratorio:</strong></td><td>${textoNormal(item.labo_normal)} | Obs: ${item.labo_obs || '-'}</td></tr>
                <tr><td><strong>Ergometría:</strong></td><td>${textoNormal(item.ergo_normal)} | Obs: ${item.ergo_obs || '-'}</td></tr>
                <tr><td><strong>RX Tórax:</strong></td><td>${textoNormal(item.rx_torax_normal)} | Obs: ${item.rx_torax_obs || '-'}</td></tr>
            </tbody>

            <thead class="thead-light">
                <tr><th colspan="2">VISIÓN Y EXAMEN FÍSICO</th></tr>
            </thead>
            <tbody>
                <tr><td><strong>Agudeza Visual:</strong></td><td>OD: ${item.ag_visual_od || '-'} | OI: ${item.ag_visual_oi || '-'}</td></tr>
                <tr><td><strong>Obs. Visión:</strong></td><td>${item.ag_visual_obs || '-'}</td></tr>
                <tr><td><strong>Examen Físico Gral:</strong></td><td>${item.examen_fisico || '-'}</td></tr>
            </tbody>

            <thead class="thead-light">
                <tr><th colspan="2">OBSERVACIONES / EVOLUCIÓN</th></tr>
            </thead>
            <tbody>
                <tr><td colspan="2" style="white-space: pre-wrap;">${item.observaciones || 'Sin notas adicionales.'}</td></tr>
            </tbody>
        </table>
    </div>`;

    $('#detalleContenido').html(html);
    $('#modalDetalleMedico').modal('show');
}

function editarAntecedente(id) {
    $.get(`../api/antecedentes/get_detalle_id.php?id=${id}`, function(res) {
        if(res.status === 'ok') {
            const d = res.data; 
            const form = $('#formNuevoAntecedente');

            // 1. Datos básicos
            form.find('input[name="peso"]').val(d.peso);
            form.find('input[name="altura"]').val(d.altura);
            form.find('input[name="edadmomento"]').val(d.edadmomento);
            form.find('textarea[name="antecedentes"]').val(d.antecedentes);

            // 3. Estudios Complementarios (ECG, EEG, LABO, ERGO, RX_TORAX)
            const estudios = ['ecg', 'eeg', 'labo', 'ergo', 'rx_torax'];
            estudios.forEach(e => {
                const esNormal = d[e + '_normal'] == 1;
                $(`#sw_${e}`).prop('checked', esNormal).trigger('change');
                form.find(`textarea[name="${e}_obs"]`).val(d[e + '_obs'] || '');
                if (e === 'eeg') {
                    form.find(`input[name="eeg_fecha"]`).val(d.eeg_fecha || '');
                }
            });

            // 4. Visión y Examen Físico
            form.find('input[name="ag_visual_od"]').val(d.ag_visual_od || '');
            form.find('input[name="ag_visual_oi"]').val(d.ag_visual_oi || '');
            form.find('textarea[name="ag_visual_obs"]').val(d.ag_visual_obs || '');
            form.find('textarea[name="examen_fisico"]').val(d.examen_fisico || '');
            form.find('textarea[name="observaciones"]').val(d.observaciones || '');

            // 5. ID de control para el UPDATE (Se usa id_antecedente para el backend)
            if($('#id_edicion_antecedente').length === 0) {
                form.append(`<input type="hidden" id="id_edicion_antecedente" name="idantecedente" value="${id}">`);
            } else {
                $('#id_edicion_antecedente').val(id);
            }

            // 6. UI: Cambiar botón a modo Edición
            form.find('button[type="submit"]')
                .html('<i class="fas fa-sync"></i> Actualizar Evolución')
                .removeClass('btn-success')
                .addClass('btn-warning');
            
            toast("Modo edición: cargando datos del registro #" + id, "info");

            // Scroll al inicio del formulario
            $('#modalMedicos .modal-body').animate({ scrollTop: 0 }, 'slow');
        } else {
            toast("Error: " + res.msg, "error");
        }
    });
}
function abrirEstudios(dni, nombre) {
    //console.log("Intentando abrir estudios para:", dni);
    $("#dni_persona_actual").val(dni);
    // 1. Verificación de existencia del modal
    var modalJS = document.getElementById('modalEstudios');
    if (!modalJS) {
        toast("El archivo modal_estudios.php no se cargó en el HTML", "error");
        return;
    }

    // 2. Cargar datos en el modal
    personaActualDNI = dni;
    const anio = $("#filtroAnioGlobal").val();

    // --- LÓGICA DE BLOQUEO POR AÑO ---
    // Comparamos el año del filtro con el activo del sistema
    const esAnioCerrado = (parseInt(anio) !== ANIO_ACTIVO_SISTEMA);

    if (esAnioCerrado) {
        // Escondemos el botón de "Agregar Nuevo Estudio" (el que tiene el if de PHP)
        $('#btnAgregarEstudio').hide(); 
        // Cambiamos el texto del estado para avisar que es historial
        $("#estadoLicenciaTexto").html('<span class="badge badge-dark">MODO LECTURA - AÑO CERRADO</span>');
        // Si tenés los botones de Provisoria, podés desactivarlos o esconderlos
        $('.btn-outline-primary, #btnEnviarMailProv').hide();
    } else {
        // Año activo: mostramos el botón de agregar (si el permiso existe se verá)
        $('#btnAgregarEstudio').show();
        $("#estadoLicenciaTexto").text("Cargando estado de licencia...");
        $('.btn-outline-primary').show();
        // Acá podrías llamar a una función para ver si el mail se puede enviar
    }

    $("#nombrePersonaEstudio").text(nombre);
    $("#txtAnioTitulo").text(anio);

    // 3. Limpiar tabla antes de cargar
    $("#tablaCuerpoEstudios").html('<tr><td colspan="4" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>');

    // 4. ABRIR EL MODAL (Sintaxis BS4)
    $(modalJS).modal("show");

    // 5. Cargar los datos de la API
    cargarEstudios(dni, anio);
}

function cargarEstudios(documento, anio) {
    const esAnioCerrado = (parseInt(anio) !== ANIO_ACTIVO_SISTEMA);
    $.ajax({
        type: "GET",
        url: "../api/estudios/get_estudios.php",
        data: { documento: documento, anio: anio },
        success: function(res) {
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            if (r.status !== "ok") {
                toast("Error al obtener estudios", "error");
                return;
            }

            let html = ""; 
            if (r.data.length === 0) {
                html = '<tr><td colspan="4" class="text-center text-muted">No hay estudios cargados.</td></tr>';
            } else {
                r.data.forEach(e => {
                    let tieneArchivo = (e.nombrearchivo && e.nombrearchivo.trim() !== "") ? 1 : 0;
                    //let rutaArchivo = `../datos/${documento}/${anio}/estudios/${e.nombrearchivo}`;

                    let rutaArchivo = e.rutafisica
                        ? `../api/helpers/pdf_reader.php?file=${encodeURIComponent(e.rutafisica)}`
                        : '';
                        
                    let archivo = tieneArchivo //?v=${new Date().getTime()}
                    ? `<a href="${rutaArchivo}" 
                        target="_blank" 
                        class="btn btn-xs btn-outline-danger">
                        <i class="fas fa-file-pdf"></i> Ver
                    </a>`
                    : `<span class="badge badge-warning">Pendiente</span>`;

                    // --- CAMBIO ACÁ: Solo mostramos el botón si NO es año cerrado ---
                    let btnSubir = "";
                    if (!esAnioCerrado && tienePermiso('estudios_medicos_subir')) {
                        btnSubir = `
                            <button class="btn-subir" 
                                data-id="${e.idtipoestudio}" 
                                data-nombre="${e.nombreestudio}" 
                                data-existe="${tieneArchivo}"> <i class="fas fa-upload"></i>
                            </button>`;
                    } else if (esAnioCerrado) {
                        btnSubir = '<i class="fas fa-lock text-muted" title="Año cerrado"></i>';
                    }

                    html += `
                    <tr>
                        <td>${e.nombreestudio} ${e.esfijo == 1 ? '<br><small class="text-danger font-weight-bold">OBLIGATORIO</small>' : ''}</td>
                        <td>${archivo}</td>
                        <td>${e.fechasubida || '-'}</td>
                        <td class="text-center">
                            ${btnSubir}
                        </td>
                    </tr>`;
                });
            }
            $("#tablaCuerpoEstudios").html(html);
        },
        error: function() {
            toast("Error de conexión con la API de estudios", "error");
        }
    });
}
/*
$(document).on('click', '.btn-subir', function() {
    const id = $(this).data('id');
    const nombre = $(this).data('nombre');
    const existe = $(this).data('existe'); 

    const abrirModal = () => {
        $("#subir_idtipoestudio").val(id);
        $("#txtNombreEstudioSubir").text(nombre);
        $("#formSubirEstudio")[0].reset();
        $("#listaArchivosSeleccionados").html('');
        $("#progresoSubida").addClass("d-none");
        $("#modalSubirArchivo").modal("show");
    };

    if (existe == 1) {
        Swal.fire({
            title: '¿Reemplazar archivo?',
            text: `Ya existe un estudio para el ciclo ${$("#filtroAnioGlobal").val()}. Si subes uno nuevo, se eliminará el anterior.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, reemplazar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) abrirModal();
        });
    } else {
        abrirModal();
    }
});
*/

$(document).on('click', '.btn-subir', function() {
    const id = $(this).data('id');
    const nombre = $(this).data('nombre');
    const existe = $(this).data('existe');

    const abrirModal = (modo = 'reemplazar') => {
        $("#formSubirEstudio")[0].reset();

        $("#subir_idtipoestudio").val(id);
        $("#txtNombreEstudioSubir").text(nombre);
        $("#modoSubida").val(modo); // hidden input: reemplazar | agregar

        $("#listaArchivosSeleccionados").html('');
        $("#progresoSubida").addClass("d-none");
        $("#modalSubirArchivo").modal("show");

        //console.log("Modo de subida:", modo);
    };

    if (existe == 1) {
        Swal.fire({
            title: 'El estudio ya existe',
            text: `Ya existe un estudio para el ciclo ${$("#filtroAnioGlobal").val()}. ¿Qué deseas hacer?`,
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Reemplazar',
            denyButtonText: 'Agregar páginas',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                abrirModal('reemplazar');
            } else if (result.isDenied) {
                abrirModal('agregar');
            }
        });
    } else {
        abrirModal('nuevo');
    }
});

// Función para cuando hacés clic en "Subir un estudio ya existente" (tipo fijo/no fijo)
function prepararSubirEstudio() {
    $("#formSubirEstudio")[0].reset();
    $("#subir_idtipoestudio").val(""); // Vaciamos el oculto
    $("#txtNombreEstudioSubir").text("Nuevo Estudio");
    
    // Mostramos el selector de tipos
    $("#contenedorSelectTipo").removeClass("d-none");
    
    // Cargamos los tipos desde la base de datos (solo los no fijos)
    cargarTiposNoFijos();
    
    $("#modalSubirArchivo").modal("show");
}

// Función modificada para cuando subís uno de la tabla (Fijo)
function abrirSubida(idTipo, nombreEstudio) {
    $("#formSubirEstudio")[0].reset();
    $("#contenedorSelectTipo").addClass("d-none"); // Escondemos el select
    
    $("#subir_idtipoestudio").val(idTipo);
    $("#txtNombreEstudioSubir").text(nombreEstudio);
    
    $("#modalSubirArchivo").modal("show");
}

function cargarTiposNoFijos() {
    // Aquí llamamos a una pequeña API que devuelva los tipos de estudios
    $.get("../api/estudios/get_tipos_estudios.php", function(res) {
        let r = (typeof res === 'string') ? JSON.parse(res) : res;
        if(r.status === "ok") {
            let htmlSubir = '<option value="">-- Seleccione --</option>';
            let htmlNuevo = '<option value="">-- Seleccione estudio existente --</option>';
            r.data.forEach(t => {
                htmlSubir += `<option value="${t.idtipoestudio}">${t.nombreestudio}</option>`;
                htmlNuevo += `<option value="${t.idtipoestudio}">${t.nombreestudio}</option>`;
            });
            $("#subir_idtipoestudio_select").html(htmlSubir);
            $("#nuevo_idtipoestudio").html(htmlNuevo);
        }
    });
}

// Mantén el control de seleccionar estudio en modalNuevoEstudio
$(document).on('change', '#nuevo_idtipoestudio', function() {
    const selected = $(this).val();
    if (selected) {
        $("#nuevo_nombre_estudio").val('').prop('disabled', true);
    } else {
        $("#nuevo_nombre_estudio").prop('disabled', false);
    }
});

// Modificación en procesarSubida para detectar qué ID usar


// 2. Función para enviar el archivo final al PHP
function enviarAlServidor(archivoFinal, idTipo) {
    let formData = new FormData();
    formData.append('archivo', archivoFinal);
    formData.append('dni', personaActualDNI); 
    formData.append('idtipo', idTipo);
    formData.append('anio', $("#filtroAnioGlobal").val());

    $.ajax({
        url: "../api/estudios/subir_estudio.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(res) {
            $("#progresoSubida").addClass("d-none");
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            if (r.status === "ok") {
                toast("✅ Archivo procesado y subido con éxito");
                $("#modalSubirArchivo").modal("hide");
                // Recargamos la tabla de estudios
                cargarEstudios(personaActualDNI, $("#filtroAnioGlobal").val()); 
            } else {
                toast(r.msg, "error");
            }
        },
        error: function() {
            $("#progresoSubida").addClass("d-none");
            toast("Error de red al intentar subir", "error");
        }
    });
}

// Función auxiliar para leer archivos
function leerComoDataURL(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.readAsDataURL(file);
    });
}



// Modificación en procesarSubida para detectar qué ID usar

function prepararNuevoEstudio() {
    abrirModalNuevoEstudio();
}

function abrirModalNuevoEstudio() {
    // Limpiamos el formulario de "Nuevo"
    $("#formNuevoEstudio")[0].reset();
    
    // Cargamos los tipos disponibles en los selects (Fijos y No Fijos)
    cargarTiposNoFijos(); 
    
    // Habilitamos los campos por si quedaron bloqueados de una edición previa
    $("#nuevo_idtipoestudio").prop('disabled', false);
    $("#nuevo_nombre_estudio").prop('disabled', false);
    
    $("#progresoNuevo").addClass("d-none");
    $("#modalNuevoEstudio").modal("show");
}


function borrarPersona(doc) {
    Swal.fire({
        title: '¿Dar de baja?',
        text: "La persona ya no aparecerá en los listados activos",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, dar de baja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/personas/borrar_persona.php",
                headers: {
                    "Authorization": "Bearer " + TOKEN
                },
                data: { 
                    documento: doc 
                },
                success: function(res) {
                    // Si recibimos un string, lo parseamos (por si las dudas)
                    let r = (typeof res === 'string') ? JSON.parse(res) : res;

                    if (r.status === "ok") {
                        toast("✅ Piloto dada de baja", "info");
                        cargarPersonas(); // Refresca tu tabla principal
                    } else {
                        toast("Error: " + r.msg, "error");
                    }
                },
                error: function() {
                    toast("Error de conexión con el servidor", "error");
                }
            });
        }
    });
}

$(document).on('blur', '#per_documento', function() {
    const dni = $(this).val().trim();
    
    // Solo validamos si hay algo escrito y no estamos en modo edición (readonly false)
    if (dni.length > 5 && !$(this).prop("readonly")) { 
        $.ajax({
            type: "GET",
            url: "../api/personas/get_persona_detalle.php",
            data: { documento: dni },
            success: function(res) {
                // Si la persona existe...
                if (res.status === "ok" && res.data) {
                    const p = res.data;
                    
                    // Si el campo 'baja' es 1, avisamos
                    if (p.baja == 1) {
                        Swal.fire({
                            title: 'Piloto de baja',
                            text: `${p.apellidonombre} ya está en la base pero figura de baja. ¿Querés reactivar el perfil y editar sus datos?`,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, reactivar',
                            cancelButtonText: 'No, corregir DNI'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Cargamos los datos en el modal (reutilizamos tu lógica de editar)
                                editarPersona(p.documento,'blur'); 
                                // OJO: En el PHP de guardar, si el DNI existe, 
                                // asegurate de que el UPDATE ponga baja = 0 automáticamente.
                            } else {
                                $("#per_documento").val('').focus();
                            }
                        });
                    } 
                    /*else {
                        // Si existe y NO está de baja, es un duplicado real
                        toast("El DNI ya pertenece a " + p.apellidonombre, "warning");
                        $("#per_documento").val('').focus();
                    }*/
                   else {
                        // ESCENARIO 2: Existe y está activo (Socio o Particular)
                        // En lugar de solo dar el aviso, ofrecemos "importar" los datos para hacerlo piloto
                        Swal.fire({
                            title: 'Persona encontrada',
                            text: `${p.apellidonombre} ya existe en el sistema. ¿Deseas cargar sus datos para registrarlo como Piloto?`,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, cargar datos',
                            cancelButtonText: 'No, usar otro DNI'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Cargamos los datos que ya tenemos (Nombre, Teléfono, etc.)
                                // Esto rellena el formulario y te permite completar lo que falte (Grupo sanguíneo, etc.)
                                editarPersona(p.documento); 
                                toast("Datos cargados. Completá la información médica para guardarlo como Piloto.", "info");

                                setTimeout(function() {
                                    // Solo si después de la edición el campo sigue vacío, buscamos el siguiente
                                    if ($("#per_historiaclinica").val().trim() === "") {
                                        $.get("../api/personas/get_siguiente_historiaclinica.php", function(res) {
                                            if (res.status === "ok") {
                                                $("#per_historiaclinica").val(res.siguiente);
                                                $("#per_historiaclinica").select(); 
                                                toast("Se asignó un nuevo número de HC", "info");
                                            }
                                        });
                                    }
                                }, 600);
                                
                            } else {
                                $("#per_documento").val('').focus();
                            }
                        });
                    }
                }
            }
        });
    }
});

// 1. Cargar el combo de Provincias (Argentina)
function cargarProvincias(idSeleccionada = null) {
    $.get("../api/ubicacion/get_ubicacion.php", { tipo: 'provincias' }, function(res) {
        if (res.status === "ok") {
            let html = '<option value="">Seleccione Provincia...</option>';
            res.data.forEach(p => {
                html += `<option value="${p.IDProvincia}">${p.Provincia}</option>`;
            });
            $("#per_idprovincia").html(html);
            
            // Si venimos de un "Editar", seleccionamos la provincia
            if (idSeleccionada) {
                $("#per_idprovincia").val(idSeleccionada);
            }
        }
    });
}

// 2. Cargar el combo de Localidades basado en la Provincia
function cargarLocalidades(idProv, idLocSeleccionada = null) {
    if (!idProv) {
        $("#per_idlocalidad").html('<option value="">Seleccione Localidad...</option>');
        return;
    }

    $.get("../api/ubicacion/get_ubicacion.php", { tipo: 'localidades', idprovincia: idProv }, function(res) {
        if (res.status === "ok") {
            let html = '<option value="">Seleccione Localidad...</option>';
            res.data.forEach(l => {
                html += `<option value="${l.IDLocalidad}">${l.Localidad}</option>`;
            });
            $("#per_idlocalidad").html(html);

            // Si venimos de un "Editar", seleccionamos la localidad
            if (idLocSeleccionada) {
                $("#per_idlocalidad").val(idLocSeleccionada);
            }
        }
    });
}

function cambiarEstadoPersona(doc, nuevoEstado) {
    const esBaja = nuevoEstado === 1;
    
    Swal.fire({
        title: esBaja ? '¿Dar de baja?' : '¿Dar de alta?',
        text: esBaja ? "La persona ya no aparecerá en los listados activos" : "La persona volverá a estar activa en el sistema",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: esBaja ? '#d33' : '#28a745',
        cancelButtonColor: '#3085d6',
        confirmButtonText: esBaja ? 'Sí, dar de baja' : 'Sí, dar de alta',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                type: "POST",
                url: "../api/personas/cambiar_estado.php", // Tu nuevo PHP que actualiza el campo baja
                headers: {
                    "Authorization": "Bearer " + TOKEN
                },
                data: { 
                    documento: doc,
                    baja: nuevoEstado 
                },
                success: function(res) {
                    let r = (typeof res === 'string') ? JSON.parse(res) : res;

                    if (r.status === "ok") {
                        toast(esBaja ? "✅ Piloto dada de baja" : "✅ Piloto dada de alta", "info");
                        cargarPersonas(); 
                    } else {
                        toast("Error: " + r.msg, "error");
                    }
                },
                error: function() {
                    toast("Error de conexión con el servidor", "error");
                }
            });
        }
    });
}

// Para el modal de la tabla
$(document).on('change', '#fileEstudio, #nuevo_file_estudio', function() {
    const esNuevo = $(this).attr('id') === 'nuevo_file_estudio';
    const destino = esNuevo ? '#listaArchivosNuevoSeleccionados' : '#listaArchivosSeleccionados';
    
    let nombres = Array.from(this.files).map(f => 
        `<li><i class="fas fa-file-image"></i> ${f.name}</li>`
    ).join('');
    
    $(destino).html(`<ul class="list-unstyled mt-2 text-primary small">${nombres}</ul>`);
});

/*
// FUNCIÓN UNIFICADA DE SUBIDA
async function ejecutarSubidaGeneral(config) {
    const { inputId, idTipo, nombreNuevo, modalId, progresoId } = config;
    const input = document.getElementById(inputId);
    const archivos = Array.from(input.files);
    const anioSeleccionado = $("#filtroAnioGlobal").val();

    if (archivos.length === 0) return toast("Seleccione archivos", "warning");
    $(`#${progresoId}`).removeClass('d-none');

    try {
        const { PDFDocument } = PDFLib;
        const pdfFinal = await PDFDocument.create();

        for (const f of archivos) {
            if (f.type === "application/pdf") {
                // --- PROCESAR PDF ---
                const pdfBytes = await f.arrayBuffer();
                const pdfExterno = await PDFDocument.load(pdfBytes);
                const paginasCopiadas = await pdfFinal.copyPages(pdfExterno, pdfExterno.getPageIndices());
                paginasCopiadas.forEach(p => pdfFinal.addPage(p));

            } else if (f.type.startsWith("image/")) {
                // --- PROCESAR IMAGEN ---
                const imgData = await leerComoDataURL(f);
                const tempJsPDF = new jspdf.jsPDF();
                const imgProps = tempJsPDF.getImageProperties(imgData);
                const pdfWidth = tempJsPDF.internal.pageSize.getWidth();
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
                
                tempJsPDF.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight, undefined, 'FAST');
                
                // Convertimos este mini-pdf de imagen a bytes para pdf-lib
                const imagePdfBytes = tempJsPDF.output('arraybuffer');
                const imagePdfDoc = await PDFDocument.load(imagePdfBytes);
                const [paginaImagen] = await pdfFinal.copyPages(imagePdfDoc, [0]);
                pdfFinal.addPage(paginaImagen);
            }
        }

        // Generar el archivo final
        const pdfBytesFinal = await pdfFinal.save();
        const nombreFijo = `estudio_${personaActualDNI}_${idTipo}_${anioSeleccionado}.pdf`.replace(/\s+/g, '_');
        const fileFinal = new File([pdfBytesFinal], nombreFijo, { type: "application/pdf" });

        // --- ENVÍO AJAX (Igual que antes) ---
        let formData = new FormData();
        formData.append('archivo', fileFinal);
        formData.append('dni', personaActualDNI);
        formData.append('anio', anioSeleccionado);
        formData.append('idtipo', idTipo);
        if (idTipo === 'NUEVO') formData.append('nombre_nuevo', nombreNuevo);

        $.ajax({
            url: "../api/estudios/subir_estudio.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $(`#${progresoId}`).addClass("d-none");
                let r = (typeof res === 'string') ? JSON.parse(res) : res;
                if (r.status === "ok") {
                    toast("✅ Documentos unificados y subidos");
                    $(`#${modalId}`).modal("hide");
                    cargarEstudios(personaActualDNI, anioSeleccionado);
                } else {
                    toast(r.msg, "error");
                }
            }
        });

    } catch (error) {
        console.error(error);
        $(`#${progresoId}`).addClass("d-none");
        toast("Error al procesar/unir PDFs e imágenes", "error");
    }
}
*/

// 1. Función auxiliar: obtiene la URL del PDF ya guardado
async function obtenerUrlEstudioExistente(idTipo, anio, nombreNuevo = '') {
    const res = await $.getJSON('../api/estudios/obtener_estudio_existente.php', {
        dni: personaActualDNI,
        anio: anio,
        idtipo: idTipo,
        nombre_nuevo: nombreNuevo || ''
    });

    return res.status === 'ok' ? res.url : null;
}

// 2. Función principal de subida
async function ejecutarSubidaGeneral(config) {
    const { inputId, idTipo, nombreNuevo, modalId, progresoId } = config;
    const input = document.getElementById(inputId);
    const archivos = Array.from(input.files);
    const anioSeleccionado = $("#filtroAnioGlobal").val();
    const modoSubida = $("#modoSubida").val() || 'reemplazar';

//console.log("Modo recibido:", modoSubida);

    if (archivos.length === 0) {
        return toast('Seleccione archivos', 'warning');
    }

    $(`#${progresoId}`).removeClass('d-none');

    try {
        const { PDFDocument } = PDFLib;
        const pdfFinal = await PDFDocument.create();

        // Si el usuario eligió AGREGAR, primero cargamos el PDF existente
        if (modoSubida === 'agregar') {
            const urlExistente = await obtenerUrlEstudioExistente(idTipo, anioSeleccionado, nombreNuevo);

            //

            if (urlExistente) {
                const response = await fetch(urlExistente, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                const existingPdfBytes = await response.arrayBuffer();
                const existingPdf = await PDFDocument.load(existingPdfBytes);

                const paginasExistentes = await pdfFinal.copyPages(
                    existingPdf,
                    existingPdf.getPageIndices()
                );

                paginasExistentes.forEach(pagina => pdfFinal.addPage(pagina));
            }
        }

        // Agregar los nuevos archivos seleccionados
        for (const f of archivos) {
            if (f.type === 'application/pdf') {
                const pdfBytes = await f.arrayBuffer();
                const pdfExterno = await PDFDocument.load(pdfBytes);
                const paginas = await pdfFinal.copyPages(
                    pdfExterno,
                    pdfExterno.getPageIndices()
                );
                paginas.forEach(p => pdfFinal.addPage(p));

            } else if (f.type.startsWith('image/')) {
                const imgData = await leerComoDataURL(f);
                const tempJsPDF = new jspdf.jsPDF();
                const imgProps = tempJsPDF.getImageProperties(imgData);

                const pdfWidth = tempJsPDF.internal.pageSize.getWidth();
                const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

                tempJsPDF.addImage(
                    imgData,
                    'JPEG',
                    0,
                    0,
                    pdfWidth,
                    pdfHeight,
                    undefined,
                    'FAST'
                );

                const imagePdfBytes = tempJsPDF.output('arraybuffer');
                const imagePdfDoc = await PDFDocument.load(imagePdfBytes);
                const [paginaImagen] = await pdfFinal.copyPages(imagePdfDoc, [0]);
                pdfFinal.addPage(paginaImagen);
            }
        }

        const pdfBytesFinal = await pdfFinal.save();
        const nombreFijo = `estudio_${personaActualDNI}_${idTipo}_${anioSeleccionado}.pdf`.replace(/\s+/g, '_');
        const fileFinal = new File([pdfBytesFinal], nombreFijo, {
            type: 'application/pdf'
        });

        const formData = new FormData();
        formData.append('archivo', fileFinal);
        formData.append('dni', personaActualDNI);
        formData.append('anio', anioSeleccionado);
        formData.append('idtipo', idTipo);

        if (idTipo === 'NUEVO') {
            formData.append('nombre_nuevo', nombreNuevo);
        }

        $.ajax({
            url: '../api/estudios/subir_estudio.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $(`#${progresoId}`).addClass('d-none');

                const r = (typeof res === 'string') ? JSON.parse(res) : res;

                if (r.status === 'ok') {
                    toast('✅ Documento guardado correctamente');
                    $(`#${modalId}`).modal('hide');
                    cargarEstudios(personaActualDNI, anioSeleccionado);
                } else {
                    toast(r.msg || 'Error al guardar', 'error');
                }
            },
            error: function() {
                $(`#${progresoId}`).addClass('d-none');
                toast('Error de conexión al subir el archivo', 'error');
            }
        });

    } catch (error) {
        console.error(error);
        $(`#${progresoId}`).addClass('d-none');
        toast('Error al procesar el documento', 'error');
    }
}

// Cuando hacés clic en "Subir y Convertir" (Modal de la tabla)
function procesarSubida() {
    const idTipo = $("#subir_idtipoestudio").val() || $("#subir_idtipoestudio_select").val();
    ejecutarSubidaGeneral({
        inputId: 'fileEstudio',
        idTipo: idTipo,
        modalId: 'modalSubirArchivo',
        progresoId: 'progresoSubida'
    });
}

// Cuando hacés clic en "Guardar y Subir" (Modal Nuevo Estudio)
function subirNuevoEstudio() {
    const selectedTipo = $("#nuevo_idtipoestudio").val();
    const nombre = $("#nuevo_nombre_estudio").val().trim();
    
    ejecutarSubidaGeneral({
        inputId: 'nuevo_file_estudio',
        idTipo: selectedTipo ? selectedTipo : 'NUEVO',
        nombreNuevo: nombre,
        modalId: 'modalNuevoEstudio',
        progresoId: 'progresoNuevo'
    });
}

function verificarEstadoApto(doc, anio) {
    // Ponemos el badge en "cargando" mientras llega la respuesta
    $("#statusApto").html('<span class="badge badge-secondary"><i class="fas fa-spinner fa-spin"></i> Consultando...</span>');
    
    $.get("../api/antecedentes/get_estado_apto.php", { documento: doc, anio: anio }, function(res) {
        let r = (typeof res === 'string') ? JSON.parse(res) : res;
        // Si esApto viene en 1, mostramos confirmado, sino pendiente
        actualizarInterfazApto(r.esApto == 1);
    }).fail(function() {
        $("#statusApto").html('<span class="badge badge-danger">Error al consultar estado</span>');
    });
}

async function confirmarAptoMedico() {

    //const { idMedico, nombre } = await elegirMedico();

    let idMedico;
    let nombre = "Medico";

    // 🔥 CASO 1: ES MÉDICO → NO preguntar
    if (window.esMedico) {
        idMedico = window.idUsuarioLogueado;
        nombre = "vos"; // opcional para el texto
    } 
    // 🔥 CASO 2: NO ES MÉDICO pero puede suplantar
    else if (tienePermiso('licencias_dar_apto_medico')) {
        try {
            const res = await window.elegirMedico();
            if (!res || !res.idMedico) return;

            idMedico = res.idMedico;
            nombre = res.nombre;
        } catch (e) {
            return;
        }
    } 
    // 🔥 CASO 3: NO TIENE PERMISOS
    else {
        toast("No tenés permiso para dar apto", "error");
        return;
    }

    Swal.fire({
        title: '¿Confirmar Aptitud Médica?',
        text: `Vas a marcar a ${nombre} como APTO para el ciclo ${anioSeleccionado}. Esto permitirá generar su licencia.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check"></i> Sí, es Apto',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            cambiarAptoServidor(1, idMedico);
        }
    });
}
/*
function realizarConfirmacionSwal(nombre, idMedicoAFirmar) {

    Swal.fire({
        title: '¿Confirmar Aptitud Médica?',
        text: `Vas a marcar a ${nombre} como APTO para el ciclo ${anioSeleccionado}. Esto permitirá generar su licencia.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check"></i> Sí, es Apto',
        cancelButtonText: 'Cancelar',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            cambiarAptoServidor(1, idMedicoAFirmar);
        }
    });
}
*/
/*
function cargarSelectMedicosYMostrarModal(origen) {
    $.get('../api/usuarios/listar_medicos.php', function(res) {
        if(res.status === 'ok') {
            let options = '<option value="">Seleccione Médico...</option>';
            res.data.forEach(m => {
                options += `<option value="${m.idusuario}">${m.nombreapellido}</option>`;
            });
            $('#selectMedicoFirma').html(options);
            
            // Guardamos el origen ('apto' o 'antecedente') en el modal
            $('#modalElegirMedico').data('origen', origen).modal('show');
        }
    });
}*/
/*
function procesarAptoConMedicoElegido() {
    const idMedico = $("#selectMedicoFirma").val();
    if (!idMedico) {
        toast("Debe seleccionar un médico responsable", "error");
        return;
    }
    $("#modalElegirMedico").modal('hide');
    const nombre = $("#nombrePaciente").text();
    realizarConfirmacionSwal(nombre, idMedico);
}
*/
function revocarApto() {
    Swal.fire({
        title: '¿Revocar Aptitud?',
        text: "Se anulará el permiso para emitir la licencia definitivo.",
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: 'Sí, revocar',
        cancelButtonText: 'Mantener Apto',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            cambiarAptoServidor(0, window.idUsuarioLogueado);
        }
    });
}

function cambiarAptoServidor(nuevoEstado, idMedicoResponsable = null) {
    const anioSeleccionado = $("#filtroAnioGlobal").val();
    $.post("../api/antecedentes/actualizar_apto.php", {
        documento: documentoSeleccionado,
        anio: anioSeleccionado,
        apto: nuevoEstado,
        idmedico: idMedicoResponsable
    }, function(res) {
        let r = (typeof res === 'string') ? JSON.parse(res) : res;
        if(r.status === 'ok') {
            toast(nuevoEstado == 1 ? "Apto confirmado" : "Apto revocado", "success");
            actualizarInterfazApto(nuevoEstado == 1);

            if (typeof cargarPersonas === 'function') {
                cargarPersonas(); 
            }
        } else {
            Swal.fire('Error', r.msg || 'No se pudo actualizar', 'error');
        }
    });
}

function actualizarInterfazApto(esApto) {
    // 1. El Badge (Lo ven todos)
    if(esApto) {
        $("#statusApto").html('<span class="badge bg-success px-3 py-2 shadow-sm"><i class="fas fa-check-double"></i> APTO MÉDICO CONFIRMADO</span>');
    } else {
        $("#statusApto").html('<span class="badge badge-warning px-3 py-2 shadow-sm text-dark"><i class="fas fa-clock"></i> PENDIENTE DE VALIDACIÓN</span>');
    }

    // 2. El Botón (SOLO si tiene el permiso de tu tabla de roles)
    // Usamos el mismo nombre de permiso que pusiste en el PHP
    if (tienePermiso('licencias_dar_apto_medico')) { 
        if (esApto) {
            $("#contenedorBotonApto").html(`
                <button type="button" class="btn btn-outline-danger btn-sm shadow-sm" onclick="revocarApto()">
                    <i class="fas fa-undo"></i> Revocar Apto
                </button>
            `);
        } else {
            $("#contenedorBotonApto").html(`
                <button type="button" class="btn btn-success shadow-sm" onclick="confirmarAptoMedico()">
                    <i class="fas fa-check-circle"></i> DAR APTO MÉDICO
                </button>
            `);
        }
    } else {
        // Si no tiene permiso, vaciamos el contenedor o ponemos un aviso discreto
        //$("#contenedorBotonApto").html('<small class="text-muted"><i class="fas fa-lock"></i> No autorizado</small>');
    }

    // 3. Actualizar celda de la tabla (lo que ya tenías)
    const celdaApto = $(`#apto-col-${documentoSeleccionado}`);
    if (esApto) {
        celdaApto.html(`<i class="fas fas fa-check-circle text-success ml-1" data-bs-toggle="tooltip" title="APTO MÉDICO CONFIRMADO"></i>`);
    } else {
        celdaApto.html(`<i class="fas fa-user-md text-muted ml-1" style="opacity:0.5" data-bs-toggle="tooltip" title="Apto Médico Pendiente"></i>`);
    }
    $('[data-bs-toggle="tooltip"]').tooltip();
}

function actualizarEstadoLicenciaUI(documento, anio) {
    $.get("../api/licencias/check_estado.php", { dni: documento, anio: anio }, function(res) {
        const u = (typeof res === 'string') ? JSON.parse(res) : res;
        
        if (u.data && u.data.licenciaprovisoria == 1) {
            $("#estadoLicenciaTexto").html('<span class="badge bg-success">Generada: ' + u.data.nombrepdfprovisorio + '</span>');
            $("#btnEnviarMailProv").removeClass("d-none");
        } else {
            $("#estadoLicenciaTexto").text("Pendiente de generación");
            $("#btnEnviarMailProv").addClass("d-none");
        }
    });
}

async function gestionarLicenciaProv() {
    const dni = $("#dni_persona_actual").val(); // O el selector donde tengas el DNI en el modal
    const nombre = $("#nombrePersonaEstudio").text();

    let fechaDesde = null;
    let fechaHasta = null;

    if (window.PEDIR_FECHA) {
        const { value: formValues } = await Swal.fire({
            title: 'Vigencia de la Licencia',
            html: `
                <div class="text-start">
                    <label class="small font-weight-bold">Desde:</label>
                    <input type="date" id="swal-fecha-desde" class="form-control mb-2" value="${new Date().toISOString().split('T')[0]}">
                    
                    <label class="small font-weight-bold">Hasta:</label>
                    <input type="date" id="swal-fecha-hasta" class="form-control">
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const desde = document.getElementById('swal-fecha-desde').value;
                const hasta = document.getElementById('swal-fecha-hasta').value;
                
                if (!desde || !hasta) {
                    Swal.showValidationMessage('Debes completar ambas fechas');
                    return false;
                }
                return { desde: desde, hasta: hasta };
            }
        });
        if (!formValues) return; // Canceló el modal
        
        fechaDesde = formValues.desde;
        fechaHasta = formValues.hasta;
    }

    const { idMedico } = await window.elegirMedico(); 

    if (!idMedico) return;

    Swal.fire({
        title: 'Procesando Licencia',
        text: 'Generando archivo PDF con firma autorizada...',
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: "../api/licencias/generar_licencia_prov_pdf.php",
        type: "POST",
        data: { dni: dni, nombre: nombre, idmedico: idMedico, fecha_desde: fechaDesde, fecha_hasta: fechaHasta },
        dataType: "json",
        success: function(res) {
            Swal.close();
            if (res.status === "ok") {
                $("#mail_tipo_licencia").val("PRO");
                // 1. URL Anti-Caché (importante para que cambie el PDF)
                //const urlFinal = "../../" + res.url + "?v=" + new Date().getTime();
                const rutaCompleta = `${res.rutafisicapdfprovisorio.replace(/\/$/, '')}/${res.nombrepdfprovisorio}`;
                
                const urlFinal = `../api/helpers/pdf_reader.php?file=${encodeURIComponent(rutaCompleta)}`;
                
                // 2. Inyectamos el objeto PDF (esto reemplaza al iframe estático)
                // Usamos <embed> que suele ser más estable en modales que <iframe>
                const htmlVisor = `<embed src="${urlFinal}" type="application/pdf" width="100%" height="100%" />`;
                $("#contenedorPDF").html(htmlVisor);

                // 3. Llenamos los datos del formulario de la derecha
                $("#mail_to").val(res.email); 
                $("#mail_subject").val("Licencia en Trámite - " + res.nombre);
                const mensaje = "Hola " + res.nombre + ",\n\nAdjuntamos la Licencia Médica en Trámite.\n\nSaludos.";
                $("#mail_message").val(mensaje);
                
                // Datos ocultos
                //$("#mail_attach").val(res.url);
                $("#mail_attach").val(rutaCompleta);
                $("#mail_idautopersona").val($("#dni_persona_actual").val());

                //actualiza en la tabla los iconos
                const fila = $(`#apto-col-${dni}`).closest('tr');
                const nuevoIconoProv = `
                <a href="javascript:void(0)" onclick="verLicenciaGenerada('${dni}', 'PRO')" class="text-muted" data-bs-toggle="tooltip" title="Generada (Pendiente envío)">
                    <i class="fas fa-address-card fa-lg"></i>
                </a>`;
                if (tienePermiso('pagos_ver')) {
                    fila.find('td').eq(6).html(nuevoIconoProv);
                }
                else{
                    fila.find('td').eq(5).html(nuevoIconoProv);
                }
                $('[data-bs-toggle="tooltip"]').tooltip();

                // 4. Mostramos el modal
                $("#modalVerLicencia").modal("show");
            } else {
                toast("Error: " + res.msg, "error");
            }
        },
        error: function() {
            Swal.close();
            toast("Error de conexión al generar PDF", "error");
        }
    });
}


function generarPDFHistoriaClinica() {
    // 1. Probamos con la variable global que definiste en abrirEstudios
    // 2. Si falla, probamos con el input (por las dudas)
    const dni_paciente = documentoSeleccionado || $("#dni_persona_actual").val();
    
    // El año lo sacamos del filtro global que usas en abrirEstudios
    const anio_actual = $("#filtroAnioGlobal").val() || new Date().getFullYear(); 

    if (!dni_paciente) {
        Swal.fire({
            icon: 'error',
            title: 'DNI no encontrado',
            text: 'No se pudo detectar el DNI del paciente actual. Por favor, cerrá el modal y volvé a entrar.',
            confirmButtonColor: '#3085d6'
        });
        return;
    }

    Swal.fire({
        title: 'Generando Historia Clínica',
        text: 'Preparando el documento PDF...',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "../api/antecedentes/generar_historia_clinica_pdf.php"; 
    form.target = "_blank"; 

    const inputDni = document.createElement("input");
    inputDni.type = "hidden";
    inputDni.name = "dni";
    inputDni.value = dni_paciente;

    const inputAnio = document.createElement("input");
    inputAnio.type = "hidden";
    inputAnio.name = "anio";
    inputAnio.value = anio_actual;

    form.appendChild(inputDni);
    form.appendChild(inputAnio);
    document.body.appendChild(form);

    form.submit();
    
    // Limpieza
    document.body.removeChild(form);
    setTimeout(() => { Swal.close(); }, 1000);
}
/*
function generarLicenciaDefinitiva(dni, nombre) {
    Swal.fire({
        title: '¿Generar Licencia Definitiva?',
        text: `Se generará el carnet oficial para ${nombre}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        confirmButtonText: 'Sí, generar PDF',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Usamos un formulario dinámico para enviar por POST el DNI y el Año
            const anio = $("#filtroAnioGlobal").val();
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "../api/licencias/generar_licencia_definitiva_pdf.php";
            form.target = "_blank";

            const inputDni = document.createElement("input");
            inputDni.type = "hidden"; inputDni.name = "dni"; inputDni.value = dni;
            
            const inputAnio = document.createElement("input");
            inputAnio.type = "hidden"; inputAnio.name = "anio"; inputAnio.value = anio;

            form.appendChild(inputDni);
            form.appendChild(inputAnio);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    });
}*/
/*
function generarLicenciaDefinitiva(dni, nombre) {
    const anio = $("#filtroAnioGlobal").val();

    Swal.fire({
        title: '¿Generar Licencia Definitiva?',
        text: `Se generará el carnet oficial para ${nombre}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        confirmButtonText: 'Sí, generar PDF',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            
            // Mostramos cargando igual que en el provisorio
            Swal.fire({
                title: 'Procesando Licencia Definitiva',
                text: 'Generando carnet oficial...',
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: "../api/licencias/generar_licencia_definitiva_pdf.php",
                type: "POST",
                data: { dni: dni, anio: anio }, // Enviamos DNI y Año
                dataType: "json",
                success: function(res) {
                    Swal.close();
                    if (res.status === "ok") {
                        // 1. Cargamos el PDF en el iframe (igual que el provisorio)
                        const urlCompleta = "../../" + res.url + "?v=" + new Date().getTime();
                        $("#iframeLicencia").attr("src", urlCompleta);
                        $("#modalVerLicencia").modal("show");
                        
                        // 2. Mostramos el botón de enviar mail definitivo
                        // Asegurate de tener este ID en tu HTML (ej: btnEnviarMailDef)
                        $("#btnEnviarMailDef").removeClass("d-none");
                        
                        // 3. Actualizar estado si es necesario
                        $("#estadoLicenciaDefTexto").html('<span class="badge badge-warning">Carnet Generado</span>');
                        
                        toast("Carnet definitivo generado con éxito");
                    } else {
                        toast("Error: " + res.msg, "error");
                    }
                },
                error: function() {
                    Swal.close();
                    toast("Error de conexión al generar carnet definitivo", "error");
                }
            });
        }
    });
}*/
function generarLicenciaDefinitiva(dni, nombre) {
    const anio = $("#filtroAnioGlobal").val();

    Swal.fire({
        title: '¿Generar Licencia Definitiva?',
        text: `Se generará la licencia definitiva para ${nombre}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#f0ad4e',
        confirmButtonText: 'Sí, generar PDF',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Procesando Licencia Definitiva',
                text: 'Generando licencia definitiva...',
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: "../api/licencias/generar_licencia_definitiva_pdf.php",
                type: "POST",
                data: { dni: dni, anio: anio },
                dataType: "json",
                success: function(res) {
                    Swal.close();
                    if (res.status === "ok") {
                        $("#mail_tipo_licencia").val("DEF");
                        // 1. Evitamos cache y cargamos el visor (Sin iFrame viejo)
                        //const urlFinal = "../../" + res.url + "?v=" + new Date().getTime();
                        const rutaCompleta = `${res.rutafisicapdfdefinitivo.replace(/\/$/, '')}/${res.nombrepdfdefinitivo}`;
                        
                        const urlFinal = `../api/helpers/pdf_reader.php?file=${encodeURIComponent(rutaCompleta)}`;
                        
                        $("#contenedorPDF").html(`<embed src="${urlFinal}" type="application/pdf" width="100%" height="100%" />`);

                        // 2. Llenamos los datos del mail (Usando lo que devuelve el PHP)
                        $("#mail_to").val(res.email); 
                        $("#mail_subject").val("Licencia Definitiva - " + res.nombre);
                        $("#mail_message").val("Hola " + res.nombre + ",\n\nAdjuntamos tu Licencia Médica Definitiva.\n\nSaludos.");
                        
                        // Datos ocultos para el botón enviarmail()
                        //$("#mail_attach").val(res.url);
                        $("#mail_attach").val(rutaCompleta);
                        $("#mail_idautopersona").val(dni);

                        // Actualiza en la tabla el icono DEFINITIVO
                        const fila = $(`#apto-col-${dni}`).closest('tr');

                        const nuevoIconoDef = `
                            <a href="javascript:void(0)" onclick="verLicenciaGenerada('${dni}', 'DEF')" class="text-muted" data-bs-toggle="tooltip" title="Generada (Pendiente envío)">
                                <i class="fas fa-id-badge fa-lg"></i>
                            </a>`;

                        // Usamos eq(7) que es la columna de Licencia Definitiva
                        if (tienePermiso('pagos_ver')) {
                            fila.find('td').eq(7).html(nuevoIconoDef);
                        }
                        else{
                            fila.find('td').eq(6).html(nuevoIconoDef);
                        }
                        // Refrescamos tooltips para que tome el nuevo título
                        $('[data-bs-toggle="tooltip"]').tooltip();
                        
                        // 3. Mostramos el modal
                        $("#modalVerLicencia").modal("show");
                        
                        // Actualizar UI de fondo
                        $("#estadoLicenciaDefTexto").html('<span class="badge badge-warning">Licencia Generado</span>');
                        toast("Carnet definitivo generado con éxito");
                    } else {
                        toast("Error: " + res.msg, "error");
                    }
                },
                error: function() {
                    Swal.close();
                    toast("Error de conexión al generar licencia definitiva", "error");
                }
            });
        }
    });
}

function enviarmail() {
    const formData = new FormData();
    const dni = $("#mail_idautopersona").val();
    const tipo = $("#mail_tipo_licencia").val();
    const subject = $("#mail_subject").val();
    formData.append('to', $("#mail_to").val());
    formData.append('subject', $("#mail_subject").val());
    formData.append('message', $("#mail_message").val());
    formData.append('attach', $("#mail_attach").val()); // Aquí va la ruta que guardó cualquiera de las 2 funciones
    formData.append('idautopersona', $("#mail_idautopersona").val());
    formData.append('tipo', tipo);
    formData.append('anio', $("#filtroAnioGlobal").val());
    
    // Archivo extra si existe
    const fileExtra = $('#mail_archivo_extra')[0].files[0];
    if (fileExtra) {
        formData.append('archivo_extra', fileExtra);
    }

    Swal.fire({
        title: 'Enviando...',
        didOpen: () => { Swal.showLoading(); }
    });

    $.ajax({
        url: "../api/licencias/sendmail.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        dataType: "json",
        success: function(res) {
            Swal.close();
            if (res.status === "ok") {
                Swal.fire('¡Enviado!', 'El correo se envió correctamente.', 'success');
                $("#modalVerLicencia").modal("hide");
                // --- ACTUALIZACIÓN DE LA TABLA EN VIVO ---
                // Buscamos la fila por el "ancla" que ya tenés (apto-col)
                const fila = $(`#apto-col-${dni}`).closest('tr');
                
                // Definimos qué columna pintar (5 para Prov, 6 para Def)
                if(tienePermiso('pagos_ver')) {
                    colIndex = (tipo === 'PRO') ? 6 : 7;
                }else{                     
                    colIndex = (tipo === 'PRO') ? 5 : 6;
                }
                
                // Buscamos el link (<a>) dentro de esa celda y le cambiamos la clase
                const iconoLink = fila.find('td').eq(colIndex).find('a');
                
                if (iconoLink.length) {
                    iconoLink.removeClass('text-muted').addClass('text-success');
                    // Actualizamos el tooltip para que diga que ya se envió
                    iconoLink.attr('data-original-title', 'Enviada por Mail').tooltip('update');
                }
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        }
    });
}

function verLicenciaGenerada(dni, tipo) {
    const anio = $("#filtroAnioGlobal").val();
    const sufijo = (tipo === 'PRO') ? 'prov' : 'def';
    const titulo = (tipo === 'PRO') ? 'Licencia Provisoria' : 'Licencia Definitiva';
    
    // 1. Construimos la ruta del archivo (la misma que usamos al generar)
    //const rutaArchivo = `../datos/${dni}/${anio}/licencias/${dni}_${anio}_${sufijo}.pdf`;

    const rutaLimpia = `${dni}/${anio}/licencias/${dni}_${anio}_${sufijo}.pdf`;
    const rutaArchivo = `../api/helpers/pdf_reader.php?file=${encodeURIComponent(rutaLimpia)}`;

    // 2. Actualizamos el Modal
    $("#modalVerLicencia2 .modal-title").text(`Vista Previa: ${titulo}`);
    
    // Seteamos el src del iframe (agregamos timestamp para evitar caché)
    $("#iframeLicencia").attr("src", rutaArchivo);

    // 3. Guardamos datos "ocultos" en el botón de enviar o en variables globales 
    // para que la función lanzarEnvioMail() sepa qué está mandando
    // Podemos usar atributos 'data' en el botón de enviar
    //$("#modalVerLicencia2 .btn-success").attr("data-dni", dni);
    //$("#modalVerLicencia2 .btn-success").attr("data-tipo", tipo);
    //$("#modalVerLicencia2 .btn-success").attr("data-ruta", rutaArchivo);

    // 4. Mostramos el modal
    $("#modalVerLicencia2").modal("show");
}

window.elegirMedico = function elegirMedico() {
    return new Promise((resolve) => {

        // 🔥 CARGAR MÉDICOS SIEMPRE
       $.get('../api/usuarios/listar_medicos.php', function(res) {
        if(res.status === 'ok') {
            let options = '<option value="">Seleccione Médico...</option>';
            res.data.forEach(m => {
                options += `<option value="${m.idusuario}">${m.nombreapellido}</option>`;
            });
            $('#selectMedicoFirma').html(options);
            
        }

            // 👉 recién acá mostramos el modal
            $("#modalElegirMedico").modal("show");

            $("#btnConfirmarMedico").off("click").on("click", function () {

                const idMedico = $("#selectMedicoFirma").val();
                const nombre = $("#selectMedicoFirma option:selected").text();

                if (!idMedico) {
                    Swal.fire('Atención', 'Seleccioná un médico', 'warning');
                    return;
                }

                $("#modalElegirMedico").modal("hide");
                /*
                // 🔥 FIX SCROLL
                setTimeout(() => {
                    $('body').addClass('modal-open');
                    $('.modal-backdrop').remove();
                }, 200);
                */
                resolve({ idMedico, nombre });
            });

        });
    });
}

$(document).on('show.bs.modal', '.modal', function () {
    const zIndex = 1050 + (10 * $('.modal.show').length);
    $(this).css('z-index', zIndex);

    setTimeout(() => {
        $('.modal-backdrop').not('.modal-stack').first().css('z-index', zIndex - 1).addClass('modal-stack');
    }, 0);
});

$(document).on('hidden.bs.modal', '.modal', function () {
    if ($('.modal.show').length) {
        $('body').addClass('modal-open');
    }
});

$(document).ready(function() {
    // Lógica para el switch de Observaciones
    $('#sw_observaciones').on('change', function() {
        if ($(this).is(':checked')) {
            $('#per_observaciones').removeClass('d-none').focus();
        } else {
            $('#per_observaciones').addClass('d-none').val('');
        }
    });
    $('#sw_alergias').on('change', function() {
        if ($(this).is(':checked')) {
            $('#label_alergias').text('Declara'); 
            $('#per_alergias').removeClass('d-none').focus();
        } else {
            $('#label_alergias').text('No declara');
            $('#per_alergias').addClass('d-none').val('');
        }
    });
});

let currentOffsetRecetas = 0; 

function abrirRecetario(dni, nombre) {
    currentOffsetRecetas = 0; // Reiniciamos el contador siempre al abrir
    
    const anioSeleccionado = parseInt($("#filtroAnioGlobal").val());
    const esAnioDiferente = (anioSeleccionado !== ANIO_ACTIVO_SISTEMA);
    
    const puedeCrear = tienePermiso('recetas_crear');
    const puedeVer = tienePermiso('recetas_ver');
    
    const esSoloLectura = (esAnioDiferente || !puedeCrear);

    // Seteamos datos básicos en el modal
    $('#nombrePacienteReceta').text(nombre); 
    
    // Manejo del Formulario y modo lectura
    const form = $('#formNuevaReceta');
    if (form.length > 0) {
        form[0].reset(); // Limpiamos campos
        $('#receta_dni').val(dni); // Seteamos el DNI después del reset
    }

    if (esSoloLectura) {
        $('#rowRecetas').addClass('modo-lectura');
    } else {
        $('#rowRecetas').removeClass('modo-lectura');
    }

    // Preparamos el contenedor del historial
    $('#historialRecetasContenedor').html('<div class="text-center p-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando recetas...</div>');
    $('#btnCargarMasRecetas').hide(); // Ocultamos el botón de "ver más" inicialmente

    // Abrimos el modal
    $('#modalRecetas').modal('show');

    // Disparamos la carga de datos con la nueva función tipo "Antecedentes"
    if(puedeVer) {
        cargarMasRecetas(dni);
    } else {
        $('#historialRecetasContenedor').html('<div class="alert alert-warning m-2">Sin permiso para ver historial.</div>');
    }
}

// Función para traer el historial al contenedor id="historialRecetasContenedor"
// Asegurate de tener estas variables globales definidas arriba de todo
const limitRecetas = 5; // O el número que prefieras

function cargarMasRecetas(dni) {
    const anioSeleccionado = parseInt($("#filtroAnioGlobal").val());

    $.ajax({
        type: "GET",
        url: "../api/recetas/get_recetas.php",
        data: { 
            documento: dni, 
            //anio: anioSeleccionado, // Agregamos el año para que sea consistente con tu sistema
            offset: currentOffsetRecetas 
        },
        success: function(res) {
            let respuesta = (typeof res === 'string') ? JSON.parse(res) : res;
            let lista = respuesta.data || [];

            // Si es la primera carga (offset 0), limpiamos el contenedor
            if (currentOffsetRecetas === 0) $('#historialRecetasContenedor').empty();

            if (!lista || lista.length === 0) {
                if (currentOffsetRecetas === 0) {
                    $('#historialRecetasContenedor').html('<div class="text-center p-4 text-muted"><p class="mb-0">No hay recetas registradas este año.</p></div>');
                }
                $('#btnCargarMasRecetas').hide(); // Asegurate de tener este botón en el modal si lo vas a usar
                return;
            }

            // Recorremos la lista y append al contenedor
            lista.forEach((r, index) => {
                
                // 1. Definimos los permisos (Asegurate que existan en tu sistema)
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
                const esLaUltima = (index === 0 && currentOffsetRecetas === 0 && parseInt(r.estado) === 1);
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

                const claseCard = esAnulada ? 'border-left-danger bg-body-tertiary' : 'border-left-info shadow-sm';
                const estiloContenido = esAnulada ? 'text-muted text-decoration-line-through' : '';

                const btnImprimir = !esAnulada ? `
                    <button class="btn btn-link btn-sm p-0" onclick="imprimirReceta(${r.idreceta})">
                        <i class="fas fa-print text-primary"></i> Imprimir
                    </button>` : '<span class="badge badge-danger">ANULADA</span>';
                    
                const btnAnular = puedeAnular ? 
                    `<button class="btn btn-sm btn-outline-danger py-0 border-0" title="Anular Receta" onclick="anularReceta(${r.idreceta}, '${dni}')">
                        <i class="fas fa-trash-alt"></i> Anular
                     </button>` : '';
                
                const infoAnulacion = esAnulada ? `
                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0" style="font-size: 0.75rem;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <b>Anulada por:</b> ${r.usuario_anula_nombre || 'Sistema'} <br>
                        <b>Motivo:</b> ${r.motivo_anulacion}
                    </div>
                ` : '';
/*
                const badge = parseInt(r.estado) === 1 ? 
                    '<span class="badge bg-success">Activa</span>' : 
                    `<span class="badge badge-danger" title="${r.motivo_anulacion || ''}">Anulada</span>`;
*/
/*
                $('#historialRecetasContenedor').append(`
                    <div class="card mb-3 shadow-sm border-left-info">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <span class="badge badge-dark">${r.fecha_formateada}</span>
                                <div> ${btnAnular}</div>
                            </div>
                            <div class="my-2" style="white-space: pre-wrap; font-size: 0.9rem; color: #333;">${r.contenido}</div>
                            <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted"><i class="fas fa-user-md"></i> ${r.medico_nombre}</small>
                                <button class="btn btn-link btn-sm p-0" onclick="imprimirReceta(${r.idreceta})">
                                    <i class="fas fa-print text-primary"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                `);
            });
*/
$('#historialRecetasContenedor').append(`
        <div class="card mb-3 ${claseCard}" style="${esAnulada ? 'opacity: 0.8;' : ''}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="badge ${esAnulada ? 'badge-secondary' : 'badge-dark'}">${r.fecha_formateada}</span>
                    <div>${!esAnulada ? btnAnular : ''}</div> 
                </div>
                
                <div class="my-2 ${estiloContenido}" style="white-space: pre-wrap; font-size: 0.9rem; color: #333;">
                    ${r.contenido}
                </div>

                ${infoAnulacion}

                <div class="border-top pt-2 mt-2 d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted"><i class="fas fa-user-md"></i> ${r.medico_nombre}</small>
                        ${r.auditoria ? `<br><small class="text-danger" style="font-size: 0.7rem; font-style: italic;">${r.auditoria}</small>` : ''}
                    </div>
                    ${btnImprimir}
                </div>
            </div>
        </div>
    `);
    });
            // Lógica del botón Cargar Más (asumiendo que el límite en PHP es 10 o similar)
            // Si el backend no devuelve el límite, podés usar una constante como 10
            if (lista.length < 10) { 
                $('#btnCargarMasRecetas').hide();
            } else {
                $('#btnCargarMasRecetas').show();
                currentOffsetRecetas += 10;
            }
        },
        error: function() {
            if (currentOffsetRecetas === 0) $('#historialRecetasContenedor').html('<div class="text-center p-4">Error de conexión.</div>');
            $('#btnCargarMasRecetas').hide();
        }
    });
}

$(document).on("submit", "#formNuevaReceta", async function(e) {
    e.preventDefault();

    const form = this;
    const dni = $('#receta_dni').val(); // Tomamos el DNI del campo hidden del modal
    
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
            $('#btnGuardarReceta').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
        },
        success: function(res) {
            let r = (typeof res === 'string') ? JSON.parse(res) : res;
            
            if (r.status === "ok") {
                toast("Receta guardada con éxito");
                
                // Limpiar el textarea
                $('#contenido_receta').val('');
                
                // Resetear offset y recargar el historial de recetas
                currentOffsetRecetas = 0;
                cargarMasRecetas(dni);

                // 🔥 LANZAR IMPRESIÓN AUTOMÁTICA
                if(r.idreceta) {
                    //imprimirReceta(r.idreceta);
                }
            } else {
                toast(r.msg || "Error al guardar", "error");
            }
        },
        complete: function() {
            $('#btnGuardarReceta').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Receta');
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
                        
                        // 3. Refrescamos el listado (o la función que uses para cargar recetas)
                        if (typeof cargarHistorialRecetas === 'function') {
                            cargarHistorialRecetas(dni);
                        } else {
                            location.reload(); // Backup por si no tenés la función a mano
                        }
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

function imprimirReceta(idreceta) {
    if (!idreceta) {
        toast("Error: ID de receta no válido", "error");
        return;
    }
    // Abrimos el PDF en una pestaña nueva
    const url = `../api/recetas/get_receta_pdf.php?id=${idreceta}`;
    window.open(url, '_blank');
}