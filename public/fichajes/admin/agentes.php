<!DOCTYPE HTML>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Agentes - <?php echo $empresa; ?></title>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container-fluid px-4" style="margin-top: 30px;">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-0">
                Gestión de Agentes
            </h2>

            <p class="text-muted mb-0">
                Administración de agentes Node.js para comunicación con los lectores.
            </p>
        </div>

        <button
            class="btn btn-primary fw-semibold shadow-sm"
            id="btnNuevoAgente"
            onclick="abrirNuevoAgente()">

            <i class="fas fa-plus me-2"></i>
            Nuevo Agente

        </button>

    </div>

    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">

        <div class="table-responsive">

            <table
                id="tablaAgentes"
                class="table table-hover align-middle mb-0 w-100">

                <thead class="table-light">

                    <tr>
                        <th>Agente</th>
                        <th>Empresa</th>
                        <th>Agent ID</th>
                        <th class="text-center">Estado</th>
                        <th>Último acceso</th>
                        <th>Última actividad</th>
                        <th class="text-center">Acciones</th>
                    </tr>

                </thead>

                <tbody id="listaAgentes">
                    <!-- Carga dinámica vía JS -->
                </tbody>

            </table>

        </div>

    </div>

</div>


<!-- ========================================================= -->
<!-- MODAL CREAR / EDITAR AGENTE -->
<!-- ========================================================= -->

<div class="modal fade" id="ModalAgente" tabindex="-1">

    <div class="modal-dialog">

        <form id="formAgente">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">
                        Crear Nuevo Agente
                    </h5>

                    <button
                        type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_agente_id"
                        name="idagente">


                    <!-- NOMBRE -->

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Nombre del Agente
                            <span class="text-danger">*</span>
                        </label>

                        <input
                            type="text"
                            id="agt_nombre"
                            name="nombre"
                            class="form-control"
                            maxlength="150"
                            required
                            placeholder="Ej.: PC Administración">

                    </div>


                    <!-- EMPRESA -->

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Empresa
                        </label>

                        <input
                            type="text"
                            id="agt_empresa"
                            class="form-control"
                            readonly>
                    </div>


                    <!-- AGENT ID -->

                    <div
                        id="contenedorAgentId"
                        class="mb-3 d-none">

                        <label class="form-label fw-semibold">
                            Agent ID
                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="agt_agent_id"
                                class="form-control"
                                readonly>

                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="copiarTexto('agt_agent_id')">

                                <i class="fas fa-copy"></i>

                            </button>

                        </div>

                    </div>


                    <!-- CREDENCIALES -->

                    <div
                        id="contenedorCredenciales"
                        class="alert alert-warning d-none">

                        <div class="fw-bold mb-2">

                            <i class="fas fa-key me-1"></i>

                            Credenciales del agente

                        </div>

                        <div class="small mb-3">

                            El token se muestra solamente ahora.
                            Guardalo antes de cerrar esta ventana.

                        </div>


                        <label class="form-label fw-semibold">
                            Agent ID
                        </label>

                        <div class="input-group mb-3">

                            <input
                                type="text"
                                id="cred_agent_id"
                                class="form-control"
                                readonly>

                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="copiarTexto('cred_agent_id')">

                                <i class="fas fa-copy"></i>

                            </button>

                        </div>


                        <label class="form-label fw-semibold">
                            Token
                        </label>

                        <div class="input-group">

                            <input
                                type="text"
                                id="cred_token"
                                class="form-control"
                                readonly>

                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="copiarTexto('cred_token')">

                                <i class="fas fa-copy"></i>

                            </button>

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        id="btnCerrarAgente"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cerrar

                    </button>

                    <button
                        type="button"
                        id="btnGuardarAgente"
                        class="btn btn-primary"
                        >

                        Crear Agente

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script src="js/agentes.js?v=<?php echo time(); ?>"></script>

</body>
</html>