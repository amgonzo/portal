<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Auditoría - <?php echo $empresa; ?></title>
    <style>
        .text-old { color: #dc3545; text-decoration: line-through; font-size: 0.85rem; margin-right: 5px; }
        .text-new { color: #28a745; font-weight: bold; font-size: 0.85rem; }
        .cambio-item { border-bottom: 1px solid #eee; padding: 2px 0; }
        /* Estilo para el visor de JSON */
        .json-block { background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.8rem; }
        .ip-text {word-break: break-all;}
        .modal-body b { color: #333; }
        .text-primary { font-weight: bold; }
    </style>
</head>
<body>
    <?php include 'menu.php'; ?>
    <div class="container-fluid mt-5 px-4">
        <div class="d-flex justify-content-between mb-4">
            <h2>Gestión de Auditoría</h2>
        </div>

        <div class="card shadow-sm p-3">
            <table id="tablaAuditoria" class="table table-striped table-bordered table-hover w-100">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Tabla</th>
                        <th>ID Ref</th>
                        <th>Cambios Resumen</th>
                        <th style="width: 50px;">Ver</th>
                    </tr>
                </thead>
                <tbody id="listaAuditoria"></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalDetalleLog" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Detalle de Auditoría #<span id="detId"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-2">
                        <div class="col-md-6"><b>Fecha:</b> <span id="detFecha"></span></div>
                        <div class="col-md-6"><b>Usuario:</b> <span id="detUser"></span></div>
                        <div class="col-md-4"><b>Acción:</b> <span id="detAccion" class="badge"></span></div>
                        <div class="col-md-4"><b>Tabla:</b> <span id="detTabla"></span></div>
                        <div class="col-md-4"><b>IP:</b> <span class="ip-text" id="detIp"></span></div>
                    </div>

                    <hr>

                    <h6>🔄 Cambios detectados</h6>
                    <div id="detCambios"></div>

                    <hr>

                    <h6>📦 Estado actual</h6>
                    <div id="detActual"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIBRERÍAS DE JS -->
    <script src="<?php echo versionar('js/verauditoria.js'); ?>"></script>
</body>
</html>