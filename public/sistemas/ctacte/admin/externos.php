<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Personal Externo - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>
    
    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Personal Externo</h2>
            <button class="btn btn-primary" name="btnNuevoExterno" id="btnNuevoExterno" onclick="abrirNuevo()">
                <i class="fas fa-user-plus"></i> Nuevo Externo
            </button>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaExternos" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>DNI / ID</th>
                        <th>Apellido y Nombre</th>
                        <th>Categoría</th>
                        <th>% Desc.</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaExternos">
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL CREAR / EDITAR EXTERNO -->
    <div class="modal fade" id="ModalExterno" tabindex="-1">
        <div class="modal-dialog">
            <form id="formExterno">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="modalExternoTitulo">Crear Persona Externa</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="ext_es_edicion" name="es_edicion" value="0">

                        <div class="mb-3">
                            <label>DNI / Identificador</label>
                            <input type="text" id="ext_dni" name="dni" class="form-control" required placeholder="Ingrese DNI sin puntos">
                        </div>

                        <div class="mb-3">
                            <label>Apellido</label>
                            <input type="text" id="ext_apellido" name="apellido" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Nombre</label>
                            <input type="text" id="ext_nombre" name="nombre" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label>Categoría Asignada</label>
                            <select id="ext_idcategoria" name="idcategoria" class="form-control" required>
                                <!-- Carga dinámicamente vía JS -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label>Porcentaje de Descuento (%)</label>
                            <input type="number" id="ext_porcentaje_descuento" name="porcentaje_descuento" class="form-control" min="0" max="100" step="0.5" value="0" required placeholder="Ej: 30">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" id="btnGuardarExt" class="btn btn-primary" onclick="guardar()">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- LIBRERÍAS DE JS -->
    <script src="<?php echo versionar('js/externos.js'); ?>"></script>
</body>
</html>