<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <script>
        window.idUsuarioLogueado = <?php echo $_SESSION['idusuario']; ?>;
        window.esMedico = <?php echo ($_SESSION['rol_nombre'] == 'Medico') ? 'true' : 'false'; ?>;
    </script>
    <title>Recetario General - <?php echo $empresa; ?></title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap4.min.css">

    <style>
        /* Mantenemos tu paleta de colores y estilos de tabla */
        .page-link { color: #1a1a1a !important; background-color: #ffffff !important; border: 1px solid #dee2e6 !important; }
        .page-item.active .page-link { background-color: #343a40 !important; border-color: #343a40 !important; color: #ffffff !important; }
        
        /* Estilo para los badges de tipos de persona que vienen de la tabla intermedia */
        .badge-rol {
            font-size: 0.75rem;
            padding: 4px 8px;
            margin-right: 2px;
            border-radius: 4px;
        }

        /* Buscador destacado */
        .search-container {
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .modal-header-custom {
            background-color: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
        }

        .modal-header-custom h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid mt-5 px-5">
        <div class="row mb-2">
            <div class="col-12">
                <h2><i class="fa fa-prescription text-primary"></i> Recetario General</h2>
            </div>
        </div>

        <div class="card shadow-sm mb-4 search-container">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="small font-weight-bold text-muted text-uppercase">Buscar Paciente (DNI):</label>
                        <div class="input-group">
                            <input type="number" id="dniBusqueda" class="form-control" placeholder="Ingrese documento...">
                            <div class="input-group-append">
                                <button class="btn btn-primary" onclick="buscarParaReceta()">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 text-end">
                        <button id="btnNuevaRecetaGral" class="btn btn-primary shadow-sm d-none" onclick="nuevaReceta()">
                            <i class="fa fa-plus"></i> Generar Nueva Receta
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm p-4">
            <div id="infoPacienteReceta" class="mb-3 d-none">
                <h5 class="text-secondary">Historial para: <span id="nombrePacienteActivo" class="font-weight-bold text-dark"></span></h5>
                <div id="rolesPacienteActivo"></div>
            </div>

            <table id="tablaRecetasGral" class="table table-striped table-hover table-bordered w-100">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Documento</th>
                        <th>Apellido y Nombre</th>
                        <th>Médico</th>
                        <th>Observaciones / Vista Previa</th>
                        <th style="width: 100px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="listaRecetasGral">
                </tbody>
            </table>
        </div>
    </div>

    <?php include "modal/modal_alta_rapida_persona.php"; ?> 
    <?php include "modal/modal_recetas.php"; ?> 
    <?php include "modal/modal_elegir_medico.php"; ?>
    <script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap4.min.js"></script>
</body>

</html>