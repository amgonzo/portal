<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Usuarios - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Usuarios</h2>
            <button class="btn btn-primary" name="btnNuevoUsuario" id="btnNuevoUsuario" onclick="abrirNuevo()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaUsuarios" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Username</th>
                        <th>Aplicaciones / Roles Asignados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaUsuarios">
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL USUARIO -->
    <div class="modal fade" id="ModalUsuario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formUsuario">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Crear Nuevo Usuario</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="edit_user_id" name="id">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre y Apellido</label>
                                <input type="text" id="user_nombre" name="nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" id="user_email" name="email" class="form-control" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" id="user_login" name="login" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contraseña <small class="text-muted" id="lblClaveOpcional"></small></label>
                                <input type="password" id="user_pass" name="clave" class="form-control">
                            </div>
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-3">Asignación de Aplicaciones y Roles</h6>

                        <div class="card bg-body-tertiary p-3">
                            <div id="contenedor_apps">
                                <!-- Se renderiza dinámicamente con JS -->
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="btnGuardar" class="btn btn-primary" onclick="guardar()">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="<?php echo versionar('js/usuarios.js'); ?>"></script>
</body>
</html>