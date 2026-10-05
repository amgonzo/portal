<!DOCTYPE html>
<html lang="es">

<head>
    <?php require_once __DIR__ . '/header.php'; ?>
</head>

<body>

<?php require_once __DIR__ . '/menu.php'; ?>

<div class="container-fluid px-4 mt-4">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h2 class="fw-bold mb-1">
                Tipos de Asistencia
            </h2>

            <p class="text-muted mb-0">
                Administración y configuración de los tipos de asistencia.
            </p>
        </div>

        <div data-permiso="tipos_asistencia_gestionar">

            <button
                type="button"
                class="btn btn-primary fw-semibold shadow-sm"
                onclick="nuevoTipoAsistencia()">

                <i class="fas fa-plus me-2"></i>
                Nuevo Tipo

            </button>

        </div>

    </div>


    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table
                    id="tablaTiposAsistencia"
                    class="table table-hover table-striped align-middle w-100">

                    <thead>

                        <tr>

                            <th>Nombre</th>

                            <th>Abrev.</th>

                            <th>Color</th>

                            <th>Trabajo</th>

                            <th>Ausencia</th>

                            <th>Modificable</th>

                            <th>Maestro</th>

                            <th>Estado</th>

                            <th class="text-center">
                                Acciones
                            </th>

                        </tr>

                    </thead>

                    <tbody id="listaTiposAsistencia"></tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MODAL TIPO DE ASISTENCIA
========================================================= -->

<div
    class="modal fade"
    id="ModalTipoAsistencia"
    tabindex="-1"
    aria-labelledby="ModalTipoAsistenciaLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <!-- =====================================================
                 HEADER
            ====================================================== -->

            <div class="modal-header">

                <h5
                    class="modal-title"
                    id="ModalTipoAsistenciaLabel">

                    Nuevo Tipo de Asistencia

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar">
                </button>

            </div>


            <!-- =====================================================
                 BODY
            ====================================================== -->

            <div class="modal-body">

                <form id="formTipoAsistencia">

                    <input
                        type="hidden"
                        id="edit_tipo_asistencia_id"
                        name="idtipo_asistencia"
                        value="">


                    <!-- =================================================
                         DATOS GENERALES
                    ================================================== -->

                    <div class="row g-3 mb-4">

                        <div class="col-md-8">

                            <label
                                for="tipo_asistencia_nombre"
                                class="form-label fw-semibold">

                                Nombre

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="tipo_asistencia_nombre"
                                name="nombre"
                                maxlength="100"
                                required>

                        </div>


                        <div class="col-md-4">

                            <label
                                for="tipo_asistencia_abreviatura"
                                class="form-label fw-semibold">

                                Abreviatura

                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="tipo_asistencia_abreviatura"
                                name="abreviatura"
                                maxlength="10"
                                required>

                            <div class="form-text">
                                Letras que se mostrarán en el calendario.
                            </div>

                        </div>


                        <div class="col-12">

                            <label
                                for="tipo_asistencia_descripcion"
                                class="form-label fw-semibold">

                                Descripción

                            </label>

                            <textarea
                                class="form-control"
                                id="tipo_asistencia_descripcion"
                                name="descripcion"
                                maxlength="255"
                                rows="2"></textarea>

                        </div>

                    </div>


                    <!-- =================================================
                         COLORES
                    ================================================== -->

                    <div class="card border mb-4">

                        <div class="card-header">

                            <h6 class="mb-0 fw-bold">
                                Apariencia en el calendario
                            </h6>

                        </div>

                        <div class="card-body">

                            <div class="row g-3 align-items-end">

                                <div class="col-md-5">

                                    <label
                                        for="tipo_asistencia_color_fondo"
                                        class="form-label fw-semibold">

                                        Color de fondo

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="color"
                                            class="form-control form-control-color"
                                            id="tipo_asistencia_color_fondo"
                                            value="#008000"
                                            title="Seleccionar color de fondo">

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="tipo_asistencia_color_fondo_hex"
                                            maxlength="30"
                                            value="#008000">

                                    </div>

                                </div>


                                <div class="col-md-5">

                                    <label
                                        for="tipo_asistencia_color_texto"
                                        class="form-label fw-semibold">

                                        Color del texto

                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="color"
                                            class="form-control form-control-color"
                                            id="tipo_asistencia_color_texto"
                                            value="#000000"
                                            title="Seleccionar color del texto">

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="tipo_asistencia_color_texto_hex"
                                            maxlength="30"
                                            value="#000000">

                                    </div>

                                </div>


                                <div class="col-md-2">

                                    <label class="form-label fw-semibold">
                                        Vista previa
                                    </label>

                                    <div
                                        id="vistaPreviaTipoAsistencia"
                                        class="d-flex align-items-center justify-content-center rounded border"
                                        style="
                                            min-height: 38px;
                                            background-color: #008000;
                                            color: #000000;
                                            font-weight: 700;
                                        ">

                                        <span id="vistaPreviaAbreviatura">
                                            P
                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         COMPORTAMIENTO
                    ================================================== -->

                    <div class="card border mb-4">

                        <div class="card-header">

                            <h6 class="mb-0 fw-bold">
                                Comportamiento
                            </h6>

                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="tipo_asistencia_computa_trabajo">

                                        <label
                                            class="form-check-label fw-semibold"
                                            for="tipo_asistencia_computa_trabajo">

                                            Computa como trabajo

                                        </label>

                                    </div>

                                    <div class="form-text">

                                        Las horas correspondientes cuentan como horas trabajadas.

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="tipo_asistencia_computa_ausencia">

                                        <label
                                            class="form-check-label fw-semibold"
                                            for="tipo_asistencia_computa_ausencia">

                                            Computa como ausencia

                                        </label>

                                    </div>

                                    <div class="form-text">

                                        Las horas programadas se consideran horas adeudadas.

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         CONFIGURACIÓN
                    ================================================== -->

                    <div class="card border">

                        <div class="card-header">

                            <h6 class="mb-0 fw-bold">
                                Configuración
                            </h6>

                        </div>

                        <div class="card-body">

                            <div class="row g-3">

                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="tipo_asistencia_modificable">

                                        <label
                                            class="form-check-label fw-semibold"
                                            for="tipo_asistencia_modificable">

                                            Modificable

                                        </label>

                                    </div>

                                    <div class="form-text">

                                        Indica si este tipo puede ser modificado desde la administración.

                                    </div>

                                </div>


                                <div class="col-md-6">

                                    <div class="form-check form-switch">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="tipo_asistencia_visible_maestro">

                                        <label
                                            class="form-check-label fw-semibold"
                                            for="tipo_asistencia_visible_maestro">

                                            Disponible en calendario maestro

                                        </label>

                                    </div>

                                    <div class="form-text">

                                        Si está activo, este tipo estará disponible para utilizarlo en el calendario general.

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </form>

            </div>


            <!-- =====================================================
                 FOOTER
            ====================================================== -->

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Cancelar

                </button>


                <button
                    type="submit"
                    id="btnGuardarTipoAsistencia"
                    class="btn btn-primary"
                    form="formTipoAsistencia">

                    <i class="fas fa-save me-2"></i>

                    Guardar Tipo

                </button>

            </div>

        </div>

    </div>

</div>


<script src="js/tipos_asistencias.js?v=<?php echo time(); ?>"></script>

</body>
</html>
