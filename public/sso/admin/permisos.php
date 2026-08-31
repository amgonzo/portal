<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Configuración de Permisos</title>
    <style>
        .header-modulo {
            background-color: #f8f9fa !important;
            color: #333 !important;
            border-left: 4px solid #2c3e50;
            transition: background 0.2s;
        }

        .header-modulo:hover {
            background-color: #e9ecef !important;
        }

        .arrow-icon {
            transition: transform 0.3s ease;
            color: #2c3e50;
        }

        .rotate-icon {
            transform: rotate(-90deg);
        }

        .btn-guardar-container {
            margin-top: 50px;
            padding: 20px 0;
            border-top: 2px solid #eee;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="m-0">Configurar Permisos por Rol</h2>
            <div>
                <button class="btn btn-outline-primary me-2" name="btnNuevoTipoUsuario" id="btnNuevoTipoUsuario" data-bs-toggle="modal" data-bs-target="#modalNuevoRol" style="display: none;">
                    <i class="fas fa-user-tag"></i> Nuevo Tipo de Usuario
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" name="btnNuevoPermiso" id="btnNuevoPermiso" data-bs-target="#modalNuevoPermiso" style="display: none;">
                    <i class="fas fa-plus"></i> Nuevo Permiso Base
                </button>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <div class="row mb-3">
                <!-- Selector de Aplicación -->
                <div class="col-md-4">
                    <label class="fw-bold">Aplicación:</label>
                    <select id="select_aplicacion" class="form-control">
                        <option value="">Cargando aplicaciones...</option>
                    </select>
                </div>
                <!-- Selector de Tipo de Usuario -->
                <div class="col-md-4">
                    <label class="fw-bold">Tipo de Usuario (Rol):</label>
                    <select id="select_tipo_permiso" class="form-control">
                        <option value="">Seleccione una aplicación primero...</option>
                    </select>
                </div>
            </div>
            <hr>
            <div id="contenedor_permisos" style="display:none;">
                <table class="table table-hover border">
                    <thead>
                        <tr>
                            <th>Permiso</th>
                            <th>Descripción</th>
                            <th>Endpoint</th>
                            <th>Método</th>
                            <th class="text-center">Editar</th>
                            <th class="text-center">Acceso</th>
                        </tr>
                    </thead>
                    <tbody id="listaPermisos">
                    </tbody>
                </table>

                <div class="btn-guardar-container clearfix">
                    <button class="btn btn-primary btn-lg float-end shadow" onclick="guardarPermisos()">
                        <i class="fas fa-save"></i> Guardar Cambios del Rol
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Permiso -->
    <div class="modal fade" id="modalNuevoPermiso" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Permiso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Aplicación Destino:</label>
                        <select id="nueva_app" class="form-control">
                            <!-- Se puebla dinámicamente -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Clave del Permiso:</label>
                        <input type="text" id="nueva_clave" class="form-control" placeholder="ej: usuarios_leer">
                    </div>
                    <div class="mb-3">
                        <label>Endpoint / Ruta:</label>
                        <input type="text" id="nuevo_endpoint" class="form-control" placeholder="ej: /usuarios/listar_usuarios.php o /usuarios/">
                    </div>
                    <div class="mb-3">
                        <label>Método HTTP:</label>
                        <select id="nuevo_metodo" class="form-control">
                            <option value="ALL">ALL (Todos)</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="DELETE">DELETE</option>
                            <option value="PUT">PUT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Descripción:</label>
                        <input type="text" id="nueva_desc" class="form-control" placeholder="Describí qué hace este permiso">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" onclick="crearPermisoBase()">Crear Permiso</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nuevo Rol -->
    <div class="modal fade" id="modalNuevoRol" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Tipo de Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nombre del Rol:</label>
                        <input type="text" id="nuevo_rol_nombre" class="form-control" placeholder="ej: Auditor Externo">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" onclick="crearNuevoTipoUsuario()">Crear Tipo de Usuario</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Editar Permiso (Endpoint / Método / Clave) -->
    <div class="modal fade" id="modalEditarPermiso" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Permiso y Endpoint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_idpermiso">
                    <div class="mb-3">
                        <label>Clave del Permiso:</label>
                        <input type="text" id="edit_clave" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Endpoint / Ruta:</label>
                        <input type="text" id="edit_endpoint" class="form-control" placeholder="ej: /usuarios/listar.php">
                    </div>
                    <div class="mb-3">
                        <label>Método HTTP:</label>
                        <select id="edit_metodo" class="form-control">
                            <option value="ALL">ALL (Todos)</option>
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="DELETE">DELETE</option>
                            <option value="PUT">PUT</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Descripción:</label>
                        <input type="text" id="edit_desc" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" onclick="actualizarPermisoBase()">Guardar Cambios</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo versionar('js/permisos.js'); ?>"></script>
</body>

</html>