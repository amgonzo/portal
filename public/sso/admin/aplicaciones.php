<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Usuarios - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Gestión de Aplicaciones</h1>
            <p class="text-muted small">Administración de sistemas y servicios conectados al SSO.</p>
        </div>
       <button type="button" class="btn btn-primary" id="btnNuevaApp" onclick="abrirModalApp()" style="display: none;">
            <i class="fa fa-plus me-1"></i> Nueva Aplicación
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaAplicaciones" class="table table-striped table-hover align-middle w-100">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Icono</th>
                            <th>Nombre</th>
                            <th>Slug</th>
                            <th>URL Base</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="listaAplicaciones">
                        <!-- Carga dinámica vía JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear / Editar Aplicación -->
<div class="modal fade" id="modalApp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formApp">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAppTitulo">Nueva Aplicación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="idaplicacion" name="idaplicacion">
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Aplicación</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" required>
                    </div>

                    <div class="mb-3">
                        <label for="slug" class="form-label">Slug (Identificador único)</label>
                        <input type="text" class="form-control" id="slug" name="slug" placeholder="ej: sistema_medico" required>
                    </div>

                    <div class="mb-3">
                        <label for="url_base" class="form-label">URL Base</label>
                        <input type="url" class="form-control" id="url_base" name="url_base" placeholder="https://app.tu-dominio.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="icono" class="form-label">Icono (FontAwesome)</label>
                        <select class="form-select" id="icono" name="icono">
                            <option value="fa-solid fa-cubes">Cubes (Por defecto)</option>
                            <option value="fa-solid fa-file-invoice-dollar">Factura / Cuenta Corriente</option>
                            <option value="fa-solid fa-user-nurse">Medicina / Salud</option>
                            <option value="fa-solid fa-book-bookmark">Documentación / SaaS</option>
                            <option value="fa-solid fa-gears">Configuración / Admin</option>
                            <option value="fa-solid fa-users">Usuarios / Clientes</option>
                            <option value="fa-solid fa-chart-line">Estadísticas / Reportes</option>
                            <option value="fa-solid fa-shield-halved">Seguridad / SSO</option>
                        </select>
                    </div>

                    <div class="mb-3">
    <label for="app_plantilla" class="form-label">Copiar permisos de otra aplicación (Opcional):</label>
    <select id="app_plantilla" class="form-select">
        <option value="">-- Seleccionar aplicación de referencia --</option>
        <!-- Cargá aquí tus aplicaciones existentes con PHP o JS -->
    </select>
</div>

<!-- Contenedor donde se pintarán los checkboxes con los permisos disponibles -->
<div id="contenedor-permisos-disponibles" class="mb-3" style="display: none;">
    <label class="form-label">Seleccioná los permisos que querés incluir para esta app:</label>
    <div class="card p-3 bg-light">
        <div id="lista-checkboxes-permisos" style="max-height: 200px; overflow-y: auto;">
            <!-- Se llenará dinámicamente vía AJAX -->
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary mt-2 w-100" id="btn-seleccionar-todos">Seleccionar / Deseleccionar todos</button>
    </div>
</div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="activo" name="activo" value="1" checked>
                        <label class="form-check-label" for="activo">Aplicación Activa</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="<?php echo versionar('js/aplicaciones.js'); ?>"></script>

</body>
</html>
