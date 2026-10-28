<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Configuración de Permisos</title>
    <style>
        /* Encabezado más sobrio */
        .header-modulo {
            background-color: #f8f9fa !important;
            /* Gris muy claro, resalta el blanco de las filas */
            color: #333 !important;
            border-left: 4px solid #2c3e50;
            /* Una barrita lateral para darle estilo */
            transition: background 0.2s;
        }

        .header-modulo:hover {
            background-color: #e9ecef !important;
        }

        /* Animación de la flecha */
        .arrow-icon {
            transition: transform 0.3s ease;
            color: #2c3e50;
        }

        .rotate-icon {
            transform: rotate(-90deg);
            /* La flecha apunta a la derecha al cerrar */
        }

        /* Separar el botón de guardar */
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
                <button class="btn btn-outline-primary me-2" name="btnNuevoTipoUsuario" id="btnNuevoTipoUsuario" data-bs-toggle="modal" data-bs-target="#modalNuevoRol">
                    <i class="fas fa-user-tag"></i> Nuevo Tipo de Usuario
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" name="btnNuevoPermiso" id="btnNuevoPermiso" data-bs-target="#modalNuevoPermiso">
                    <i class="fas fa-plus"></i> Nuevo Permiso Base
                </button>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <div class="col-md-4 mb-3 p-0">
                <label>Seleccionar Tipo de Usuario:</label>
                <select id="select_tipo_permiso" class="form-control">
                </select>
            </div>
            <hr>
            <div id="contenedor_permisos" style="display:none;">
                <table class="table table-hover border">
                    <thead>
                        <tr>
                            <th>Permiso</th>
                            <th>Descripción</th>
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

    <div class="modal fade" id="modalNuevoPermiso" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Crear Nuevo Permiso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Clave del Permiso:</label>
                        <input type="text" id="nueva_clave" class="form-control" placeholder="ej: pacientes_borrar">
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
<div class="modal fade" id="modalNuevoRol" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crear Nuevo Tipo de Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal">
                    <span>&times;</span>
                </button>
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
</body>

</html>