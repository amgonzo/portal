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
                        <th>Login</th>
                        <th>Rol / Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaUsuarios">
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="ModalUsuario" tabindex="-1">
        <div class="modal-dialog">
            <form id="formUsuario" enctype="multipart/form-data"> <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Crear Nuevo Usuario</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="edit_user_id" name="id">

                        <div class="mb-3">
                            <label>Nombre y Apellido</label>
                            <input type="text" id="user_nombre" name="nombre" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" id="user_email" name="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Tipo de Usuario</label>
                            <select id="user_tipo" name="tipo" class="form-control" required></select>
                        </div>

                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" id="user_login" name="login" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Contraseña</label>
                            <input type="password" id="user_pass" name="clave" class="form-control">
                        </div>

                        <div id="seccion_firma" style="display:none;">
                            <div class="form-group border p-2 bg-body-tertiary mt-3">
                                <label class="small font-weight-bold">Firma Digital Actual:</label>
                                <div class="text-center">
                                    <img id="img_firma_previa" src="" class="img-fluid border" style="max-height: 100px; background-color: #fff;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>Cambiar Firma (PNG Transparente)</label>
                                <input type="file" id="user_firma" name="user_firma" class="form-control" accept="image/png">
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
</body>
</html>