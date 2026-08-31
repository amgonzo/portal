<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Categorías - <?php echo $empresa; ?></title>
    <!-- SweetAlert2 & Toastify CSS/JS (por si no están cargados en header.php) -->
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Categorías</h2>
            <!-- BOTÓN APLICAR LÍMITES A MES -->
            <button class="btn btn-warning me-2" onclick="abrirModalAplicarLimites()">
                <i class="fas fa-calendar-check"></i> Aplicar Límites a Mes
            </button>
            <!-- BOTÓN REASIGNAR PERSONA -->
            <button class="btn btn-info me-2" onclick="abrirModalReasignar()">
                <i class="fas fa-users-cog"></i> Asignar Persona
            </button>
            <!-- BOTÓN NUEVA CATEGORÍA -->
            <button class="btn btn-primary" name="btnNuevaCategoria" id="btnNuevaCategoria" onclick="abrirNuevo()">
                <i class="fas fa-plus-circle"></i> Nueva Categoría
            </button>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaCategorias" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Límite Mensual</th>
                        <th>Integrantes</th> <!-- NUEVA COLUMNA -->
                        <th>Estado / Tipo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaCategorias">
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL CREAR / EDITAR CATEGORÍA -->
    <div class="modal fade" id="ModalCategoria" tabindex="-1">
        <div class="modal-dialog">
            <form id="formCategoria">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalCategoriaTitulo">Crear Nueva Categoría</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="edit_cat_id" name="idcategoria">

                        <div class="mb-3">
                            <label>Nombre de la Categoría</label>
                            <input type="text" id="cat_nombre" name="nombre" class="form-control" required placeholder="Ej: Gerencia, Externos, Administrativos">
                        </div>

                        <div class="mb-3">
                            <label>Límite Mensual ($)</label>
                            <input type="number" step="0.01" id="cat_limite" name="limite_mensual" class="form-control" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="btnGuardarCat" class="btn btn-primary" onclick="guardar()">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL ASIGNAR CATEGORÍA A PERSONA -->
    <div class="modal fade" id="ModalAsignarPersona" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-users-cog"></i> Reasignar Categoría a Persona</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p>Seleccione la persona y la nueva categoría a la que desea asociarla:</p>
                    
                    <div class="mb-3">
                        <label>Persona:</label>
                        <select id="asig_persona_select" class="form-control" style="width: 100%;">
                            <!-- Carga dinámicamente -->
                        </select>
                    </div>

                    <div class="mb-3">
                        <label>Nueva Categoría:</label>
                        <select id="asig_categoria_select" class="form-control" style="width: 100%;">
                            <!-- Carga dinámicamente -->
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="guardarReasignacion()">Aplicar Cambio</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL APLICAR LÍMITES A MES -->
    <div class="modal fade" id="ModalAplicarLimites" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-history"></i> Actualizar Límites de un Mes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">
                        Esta acción actualizará el límite mensual de <strong>todos los empleados activos</strong> en el período seleccionado, alineándolos con los valores configurados actualmente en sus categorías.
                    </p>
                    <div class="mb-3">
                        <label>Año:</label>
                        <select id="lim_anio" class="form-control">
                            <!-- Carga vía JS -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Mes:</label>
                        <select id="lim_mes" class="form-control">
                            <option value="1">Enero</option>
                            <option value="2">Febrero</option>
                            <option value="3">Marzo</option>
                            <option value="4">Abril</option>
                            <option value="5">Mayo</option>
                            <option value="6">Junio</option>
                            <option value="7">Julio</option>
                            <option value="8">Agosto</option>
                            <option value="9">Septiembre</option>
                            <option value="10">Octubre</option>
                            <option value="11">Noviembre</option>
                            <option value="12">Diciembre</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning font-weight-bold" onclick="procesarAplicacionLimites()">
                        <i class="fas fa-sync-alt"></i> Aplicar Límites
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALLE DE PERSONAS EN CATEGORÍA -->
    <div class="modal fade" id="ModalVerPersonas" tabindex="-1">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-users"></i> Integrantes: <span id="lblCatNombre"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="max-height: 400px; overflow-y: auto;">
                    <ul class="list-group list-group-flush" id="listaPersonasCat">
                        <!-- Carga dinámica vía JS -->
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LIBRERÍAS DE JS -->
    <script src="<?php echo versionar('js/categorias.js'); ?>"></script>
</body>
</html>