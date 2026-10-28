<!DOCTYPE html>
<html lang="es">

<head>

    <?php include 'header.php'; ?>
    <title>Años - <?php echo $empresa; ?></title>
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
    </style>
</head>

<body>
    <?php include 'menu.php'; ?>

    <div class="container-fluid mt-5 px-5">
        <div class="row mb-2">
            <div class="col-12">
                <h2><i class="fas fa-calendar text-primary"></i> Gestión de Años</h2>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <!-- Espacio vacío para mantener alineación -->
            </div>

            <div>
                <button class="btn btn-primary shadow-sm" onclick="nuevoAnio()" data-permiso="anios_crear">
                    <i class="fas fa-plus"></i> Nuevo Año
                </button>
            </div>
        </div>

        <table id="tablaAnios" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Año</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="listaAnios">
            </tbody>
        </table>

        <!-- Modal Nuevo/Editar Año -->
        <div class="modal fade" id="modalAnio" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-custom">
                        <h5 class="modal-title">Nuevo Año</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formAnio">
                            <div class="mb-3">
                                <label for="anio">Año</label>
                                <input type="number" class="form-control" id="anio" name="anio" required min="2000" max="2100">
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo">
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer bg-body-tertiary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="guardarAnio()">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include "footer.php"; ?>
</body>

</html>