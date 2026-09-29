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

        <button
            class="btn btn-primary"
            name="btnNuevaEmpresa"
            id="btnNuevaEmpresa"
            onclick="abrirNuevaEmpresa()"
        >
            <i class="fas fa-building"></i>
            Nueva Empresa
        </button>
    </div>

    <div class="card shadow-sm p-3">

        <table
            id="tablaEmpresas"
            class="table table-striped table-bordered table-hover w-100"
        >

            <thead>
                <tr>
                    <th>Nombre / Razón Social</th>
                    <th>CUIT</th>
                    <th>Slug / BD</th>
                    <th>Aplicaciones</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody id="listaEmpresas"></tbody>

        </table>

    </div>

</div>

<!-- MODAL EMPRESA -->

<div
    class="modal fade"
    id="ModalEmpresa"
    tabindex="-1"
>
    <div class="modal-dialog modal-lg">

        <form id="formEmpresa">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">

                    <h5 class="modal-title">
                        Crear Nueva Empresa
                    </h5>

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
                        id="edit_empresa_id"
                        name="idempresa"
                    >

                    <div class="row mb-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Nombre Comercial
                            </label>

                            <input
                                type="text"
                                id="empresa_nombre"
                                name="nombre"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Razón Social
                            </label>

                            <input
                                type="text"
                                id="empresa_razon_social"
                                name="razon_social"
                                class="form-control"
                            >

                        </div>

                    </div>

                    <div class="row mb-3">

                        <div class="col-md-4">

                            <label class="form-label">
                                CUIT
                            </label>

                            <input
                                type="text"
                                id="empresa_cuit"
                                name="cuit"
                                class="form-control"
                                placeholder="20-XXXXXXXX-X"
                            >

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Slug
                            </label>

                            <input
                                type="text"
                                id="empresa_slug"
                                name="slug"
                                class="form-control"
                                placeholder="mi-empresa"
                                required
                            >

                        </div>

                        <div class="col-md-4">

                            <label class="form-label">
                                Base de Datos (DB)
                            </label>

                            <input
                                type="text"
                                id="empresa_db"
                                name="db_nombre"
                                class="form-control"
                                placeholder="portal_db_empresa"
                                required
                            >

                        </div>

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
                        id="btnGuardarEmpresa"
                        class="btn btn-primary"
                        onclick="guardarEmpresa()"
                    >
                        Guardar
                    </button>

                </div>

            </div>

        </form>

    </div>
</div>

<!-- MODAL APLICACIONES DE LA EMPRESA -->

<div
    class="modal fade"
    id="ModalAplicacionesEmpresa"
    tabindex="-1"
>
    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">
                    Aplicaciones de la Empresa
                </h5>

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
                    id="aplicaciones_empresa_id"
                >

                <h5 id="aplicaciones_empresa_nombre" class="mb-4"></h5>

                <div id="listaAplicacionesEmpresa">

                    <div class="text-center p-4">
                        <i class="fas fa-spinner fa-spin"></i>
                        Cargando aplicaciones...
                    </div>

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
                    id="btnGuardarAplicacionesEmpresa"
                    class="btn btn-primary"
                    onclick="guardarAplicacionesEmpresa()"
                >
                    <i class="fas fa-save"></i>
                    Guardar
                </button>

            </div>

        </div>

    </div>
</div>

<!-- MODAL CONEXIONES DE LA EMPRESA -->

<div
    class="modal fade"
    id="ModalConexionesEmpresa"
    tabindex="-1"
>
    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title">
                    Conexiones de la Empresa
                </h5>

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
                    id="conexiones_empresa_id"
                >

                <h5
                    id="conexiones_empresa_nombre"
                    class="mb-4"
                ></h5>

                <div class="d-flex justify-content-end mb-3">

                    <button
                        type="button"
                        class="btn btn-success"
                        onclick="nuevaConexionEmpresa()"
                    >
                        <i class="fas fa-plus"></i>
                        Nueva conexión
                    </button>

                </div>

                <div id="listaConexionesEmpresa">

                    <div class="text-center p-4">
                        <i class="fas fa-spinner fa-spin"></i>
                        Cargando conexiones...
                    </div>

                </div>

                <hr>

                <!-- FORMULARIO CONEXIÓN -->

                <div
                    id="formularioConexionEmpresa"
                    style="display:none;"
                >

                    <h5 class="mb-3">
                        <span id="tituloFormularioConexion">
                            Nueva conexión
                        </span>
                    </h5>

                    <input
                        type="hidden"
                        id="conexion_id"
                    >

                    <div class="row mb-3">

                        <div class="col-md-6">
                            <label class="form-label">Clave de uso</label>
                            <input
                                type="text"
                                id="conexion_clave"
                                class="form-control"
                                maxlength="50"
                                placeholder="Ej: DATOS, MS3, IA"
                            >
                            <small class="text-muted">
                                Identificador que utilizará el sistema para seleccionar esta conexión.
                            </small>
                        </div>
                        <div class="col-md-6">

                            <label class="form-label">
                                Nombre de conexión
                            </label>

                            <input
                                type="text"
                                id="conexion_nombre"
                                class="form-control"
                                placeholder="Principal"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Tipo
                            </label>

                            <select
                                id="conexion_tipo"
                                class="form-select"
                            >
                                <option value="MYSQL">
                                    MySQL / MariaDB
                                </option>

                                <option value="SQLSERVER">
                                    Microsoft SQL Server
                                </option>
                            </select>

                        </div>

                    </div>

                    <div class="row mb-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Host
                            </label>

                            <input
                                type="text"
                                id="conexion_host"
                                class="form-control"
                                placeholder="192.168.0.221"
                                required
                            >

                        </div>

                        <div class="col-md-3">

                            <label class="form-label">
                                Puerto
                            </label>

                            <input
                                type="number"
                                id="conexion_puerto"
                                class="form-control"
                                value="3306"
                                min="1"
                                max="65535"
                                required
                            >

                        </div>

                        <div class="col-md-3">

                            <label class="form-label">
                                Estado
                            </label>

                            <div class="form-check form-switch mt-2">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="conexion_activo"
                                    checked
                                >

                                <label
                                    class="form-check-label"
                                    for="conexion_activo"
                                >
                                    Activa
                                </label>

                            </div>

                        </div>

                    </div>

                    <div class="row mb-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Base de datos
                            </label>

                            <input
                                type="text"
                                id="conexion_db_nombre"
                                class="form-control"
                                required
                            >

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Usuario
                            </label>

                            <input
                                type="text"
                                id="conexion_db_usuario"
                                class="form-control"
                                required
                            >

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            id="conexion_db_password"
                            class="form-control"
                            autocomplete="new-password"
                            placeholder="Ingrese la contraseña"
                        >

                        <small class="text-muted">
                            La contraseña se almacena cifrada.
                            Al editar una conexión, dejar vacío para conservar
                            la contraseña actual.
                        </small>

                    </div>

                    <div class="d-flex justify-content-end gap-2">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            onclick="cancelarEdicionConexion()"
                        >
                            Cancelar
                        </button>

                        <button
                            type="button"
                            id="btnGuardarConexion"
                            class="btn btn-primary"
                            onclick="guardarConexionEmpresa()"
                        >
                            <i class="fas fa-save"></i>
                            Guardar conexión
                        </button>

                    </div>

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

            </div>

        </div>

    </div>
</div>

<script src="<?php echo versionar('js/empresas.js'); ?>"></script>

</body>
</html>