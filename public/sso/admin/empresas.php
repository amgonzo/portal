<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Empresas</h2>
            <button class="btn btn-primary" name="btnNuevaEmpresa" id="btnNuevaEmpresa" onclick="abrirNuevaEmpresa()">
                <i class="fas fa-building"></i> Nueva Empresa
            </button>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaEmpresas" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>Nombre / Razón Social</th>
                        <th>CUIT</th>
                        <th>Slug / BD</th>
                        <th>Usuarios Asignados</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaEmpresas">
                    <!-- Renderizado dinámico con JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL EMPRESA -->
    <div class="modal fade" id="ModalEmpresa" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formEmpresa">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Crear Nueva Empresa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="edit_empresa_id" name="idempresa">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre Comercial</label>
                                <input type="text" id="empresa_nombre" name="nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Razón Social</label>
                                <input type="text" id="empresa_razon_social" name="razon_social" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">CUIT</label>
                                <input type="text" id="empresa_cuit" name="cuit" class="form-control" placeholder="20-XXXXXXXX-X">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Slug</label>
                                <input type="text" id="empresa_slug" name="slug" class="form-control" placeholder="mi-empresa" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Base de Datos (DB)</label>
                                <input type="text" id="empresa_db" name="db_nombre" class="form-control" placeholder="portal_db_empresa" required>
                            </div>
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-3">Asignación de Usuarios a la Empresa</h6>

                        <div class="card bg-body-tertiary p-3">
                            <div id="contenedor_usuarios_empresa" style="max-height: 250px; overflow-y: auto;">
                                <!-- Se renderiza dinámicamente con JS (similar a la matriz de apps) -->
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="btnGuardarEmpresa" class="btn btn-primary" onclick="guardarEmpresa()">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo versionar('js/empresas.js'); ?>"></script>
</body>
</html>