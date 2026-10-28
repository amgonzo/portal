<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-5">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Diccionario</h2>

            <button
                class="btn btn-primary"
                name="btnNuevoDiccionario"
                id="btnNuevoDiccionario"
                onclick="abrirNuevo()"
            >
                <i class="fas fa-plus"></i> Nuevo Término
            </button>
        </div>

        <div class="card shadow-sm p-3">
            <table
                id="tablaDiccionario"
                class="table table-striped table-bordered table-hover w-100"
            >
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Valor</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody id="listaDiccionario">
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL DICCIONARIO -->
    <div class="modal fade" id="ModalDiccionario" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form id="formDiccionario">

                <div class="modal-content">

                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Crear Nuevo Término</h5>

                        <button
                            type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                        ></button>
                    </div>

                    <div class="modal-body">

                        <input
                            type="hidden"
                            id="edit_dic_id"
                            name="id"
                        >

                        <div class="row mb-3">

                            <div class="col-md-6">
                                <label class="form-label">Clave</label>

                                <input
                                    type="text"
                                    id="dic_clave"
                                    name="clave"
                                    class="form-control"
                                    maxlength="100"
                                    required
                                >
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Valor</label>

                                <input
                                    type="text"
                                    id="dic_valor"
                                    name="valor"
                                    class="form-control"
                                    maxlength="255"
                                    required
                                >
                            </div>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Descripción</label>

                            <textarea
                                id="dic_descripcion"
                                name="descripcion"
                                class="form-control"
                                rows="4"
                                maxlength="255"
                            ></textarea>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Cerrar
                        </button>

                        <button
                            type="button"
                            id="btnGuardar"
                            class="btn btn-primary"
                            onclick="guardar()"
                        >
                            Guardar
                        </button>

                    </div>

                </div>

            </form>
        </div>
    </div>

    <script src="<?php echo versionar('js/diccionario.js'); ?>"></script>

</body>
</html>