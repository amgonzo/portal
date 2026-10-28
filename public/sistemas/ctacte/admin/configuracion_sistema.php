<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'header.php'; ?>
    <title>Configuración del Sistema - <?php echo $empresa; ?></title>
</head>

<body>
    <?php include 'menu.php'; ?>
    <div class="container mt-5">
        <div class="mb-4">
            <h2>Variables del Sistema</h2>
        </div>

        <div class="card shadow-sm p-4">
            <form id="formConfiguracion">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="font-weight-bold">Última Sincronización</label>
                            <input type="text" id="config_ultima_sincro" class="form-control" readonly>
                            <small class="form-text text-muted">Fecha y hora del último bloque histórico recibido.</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="font-weight-bold">Porcentaje Descuento Defecto (%)</label>
                            <input type="number" step="0.5" min="0" max="100" id="config_porcentaje_defecto" name="porcentaje_descuento_default" class="form-control" required placeholder="Ej: 30">
                            <small class="form-text text-muted">Porcentaje aplicado por defecto a los empleados de fichajes.</small>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-3">
                    <button type="button" id="btnGuardarConfig" class="btn btn-success" onclick="guardarConfig()">
                        <i class="fas fa-save"></i> Guardar Configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="<?php echo versionar('js/configuracion_sistema.js'); ?>"></script>
</body>
</html>