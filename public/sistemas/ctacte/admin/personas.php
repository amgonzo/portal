<!DOCTYPE html>
<html lang="es">

<head>

    <?php include 'header.php'; ?>
    <script>
    window.idUsuarioLogueado = <?php echo $_SESSION['idusuario']; ?>;
    window.esMedico = <?php echo ($_SESSION['rol_nombre'] == 'Medico') ? 'true' : 'false'; ?>;
    </script>
    <title>Pilotos - <?php echo $empresa; ?></title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">

    <style>
        /* --- NAVEGACIÓN DE LA TABLA --- */
        .page-link {
            color: #1a1a1a !important;
            background-color: #ffffff !important;
            border: 1px solid #dee2e6 !important;
        }

        .page-item.active .page-link {
            background-color: #343a40 !important;
            border-color: #343a40 !important;
            color: #ffffff !important;
        }

        .page-link:hover {
            background-color: #e9ecef !important;
            color: #000 !important;
        }

        /* --- BOTONES --- */
        .btn-success {
            background-color: #28a745 !important;
            border-color: #1e7e34 !important;
        }

        /* --- MODAL MÉDICO --- */
        #historialContenedor {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .burbuja-medica {
            font-size: 0.85rem;
            padding: 8px;
            border-left-width: 3px;
        }

        .burbuja-medica.cerrada {
            border-left-color: #6c757d;
            background: #f1f1f1;
        }

        /* --- FOTO --- */
        .foto-carnet-container {
            width: 120px;
            height: 150px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: #f8f9fa;
            margin: auto;
        }

        .foto-carnet-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* --- CATEGORÍAS --- */
        #listaCategoriasCheck {
            background-color: #f8f9fa;
            max-height: 200px;
            overflow-y: auto;
        }

        .modal-header-custom {
            padding: 0.5rem 1rem;
            /* 🔥 baja altura del header */
        }

        .modal-header-custom h5 {
            font-size: 0.95rem;
            font-weight: 600;
        }

        /* --- MODALES (UNIFICADO) --- */
        .modal-header-custom {
            background-color: #2c3e50;
            color: white;
        }

        .modal-header-custom h5 {
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .modal-header-custom h5 {
            font-size: 1rem;
            /* 🔥 antes ~1.25rem */
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-bottom: 0;
        }

        .modal-footer {
            background-color: #f8f9fa;
        }

        /* --- BADGES --- */
        .badge-pendiente {
            background-color: #f8d7da;
            color: #721c24;
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }

        .badge-completado {
            background-color: #d4edda;
            color: #155724;
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }
        /* Ajustamos el ancho de la columna de acciones */
        #tablaPersonas td:last-child {
            white-space: nowrap !important;
            width: 1%; /* Esto obliga a la celda a ajustarse al contenido sin estirarse */
            min-width: 180px; /* Ajustá este valor según cuántos botones tengas */
        }
        /* Evita que los botones se peguen si la celda es muy chica */
        #tablaPersonas .btn {
            margin-right: 2px;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid mt-5 px-5">
        <div class="row mb-2">
            <div class="col-12">
                <h2><i class="fa fa-id-card text-primary"></i> Gestión de Pilotos</h2>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div class="d-flex align-items-end">
                <div style="width: 150px;" class="mr-3">
                    <label class="small font-weight-bold mb-1 text-muted text-uppercase">Ciclo / Año:</label>
                    <select id="filtroAnioGlobal" class="form-control form-control-sm font-weight-bold border-primary" onchange="cargarPersonas()">
                    </select>
                </div>
                
                <div class="custom-control custom-checkbox pb-1">
                    <input type="checkbox" class="form-check-input" id="checkVerBajas" onchange="cargarPersonas()">
                    <label class="form-check-label small font-weight-bold text-danger text-uppercase" style="cursor:pointer;" for="checkVerBajas">
                        Ver de baja
                    </label>
                </div>
            </div>

            <div>
                <button id="btnNuevaPersona" class="btn btn-primary shadow-sm" onclick="nuevaPersona()">
                    <i class="fa fa-user-plus"></i> Nueva Piloto
                </button>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <table id="tablaPersonas" class="table table-striped table-hover table-bordered w-100">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Historia Clínica</th>
                        <th>Apellido y Nombre</th>
                        <th class="text-center">Piloto / Copiloto</th>
                        <?php
                        if(isset($_SESSION['permisos']) && in_array('pagos_ver', $_SESSION['permisos'])){
                            echo '<th class="text-center">Pagos</th>';
                        }
                        ?>
                        <th class="text-center">Apto</th>
                        <th class="text-center">Prov.</th> 
                        <th class="text-center">Def.</th>
                        <th style="width: 180px; min-width: 180px; text-align: left;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaPersonas"></tbody>
            </table>
        </div>
    </div>

<?php include "modal/modal_personas.php"; ?>
<?php include "modal/modal_medicos.php"; ?>
<?php include "modal/modal_detalle_medico.php"; ?>
<?php include "modal/modal_estudios.php"; ?>
<?php include "modal/modal_ver_licencias.php"; ?>
<?php include "modal/modal_elegir_medico.php"; ?>
<?php include "modal/modal_recetas.php"; ?>

<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>

</body>

</html>