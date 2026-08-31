<!DOCTYPE html>
<html lang="es">
<head>
    <?php include 'header.php'; ?>
    <title>Mi Perfil - <?php echo $empresa; ?></title>
</head>
<body>
    <?php include 'menu.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-primary"><i class="fa fa-lock"></i> Seguridad de la Cuenta</h5>
                    </div>
                    <div class="card-body">
                        <form id="formPassword">
                            <div class="mb-3">
                                <label class="font-weight-bold">Nueva Contraseña</label>
                                <input type="password" id="new_pass" class="form-control" placeholder="Mínimo 6 caracteres">
                            </div>
                            <div class="mb-3">
                                <label class="font-weight-bold">Confirmar Contraseña</label>
                                <input type="password" id="new_pass_confirm" class="form-control" placeholder="Repetí la clave">
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="dashboard.php" class="btn btn-light text-muted">
                                    <i class="fa fa-chevron-left"></i> Volver
                                </a>
                                <button type="button" onclick="actualizarPass()" class="btn btn-primary px-4">
                                    <i class="fa fa-save"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-muted"><i class="fa fa-id-badge"></i> Mi Perfil de Acceso</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted small d-block">Rol Asignado:</label>
                            <span class="badge bg-primary p-2" style="font-size: 0.9rem;">
                                <i class="fa fa-user-shield"></i> 
                                <?php echo $_SESSION['rol_nombre'] ?? 'Usuario Estándar'; ?>
                            </span>
                        </div>
                        
                        <label class="text-muted small d-block">Permisos habilitados:</label>
                        <div class="d-flex flex-wrap">
                            <?php 
                            if (!empty($_SESSION['permisos'])) {
                                foreach ($_SESSION['permisos'] as $p) {
                                    // Limpiamos el nombre para que sea legible (ej: personas_ver -> Personas ver)
                                    $nombre_limpio = ucfirst(str_replace('_', ' ', $p));
                                    echo '<span class="badge bg-body-tertiary text-dark border m-1 p-2 text-dark"><i class="fa fa-check text-success"></i> '.$nombre_limpio.'</span>';
                                }
                            } else {
                                echo '<span class="text-muted italic">No tenés permisos específicos asignados.</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function actualizarPass() {
        var pass = $("#new_pass").val();
        var confirmPass = $("#new_pass_confirm").val();

        // 1. Usamos el TOAST para errores rápidos
        if(pass === "" || pass !== confirmPass) {
            toast("Las contraseñas no coinciden", "warning");
            return;
        }

        if(pass.length < 6){
            toast("La clave es muy corta (mín. 6)", "info");
            return;
        }

        $.ajax({
            type: "POST",
            url: "/api/cambiar_clave.php",
            headers: { "Authorization": "Bearer " + TOKEN },
            data: { clave: pass },
            success: function(res) {
                // 2. Usamos SWAL (el modal grande) para el éxito, porque es un cambio crítico
                if(res.status === "ok") {
                    Swal.fire({
                        title: '¡Clave Cambiada!',
                        text: 'Por seguridad, ingresá nuevamente con tu nueva clave.',
                        icon: 'success',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'Aceptar'
                    }).then(() => {
                        window.location.href = "/auth/login.php";
                    });
                } else {
                    toast(res.msg, "error");
                }
            },
            error: function(e) {
                if(e.status === 401) window.location.href = "/auth/login.php";
                else toast("Error en el servidor", "error");
            }
        });
    }
    </script>
</body>
</html>