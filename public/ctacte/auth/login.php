<?php
session_start();

// Cargamos el entorno subiendo un nivel desde /auth/
require __DIR__ . '/../vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
} catch (Exception $e) {
    // Error silencioso si no hay .env
}
$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';
$mostrarNombre = filter_var($_ENV['MOSTRAR_APP_NAME'] ?? false, FILTER_VALIDATE_BOOLEAN);
$_SESSION['NombreEmpresa'] = $empresa;
$_SESSION['MostrarNombre'] = $mostrarNombre;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../logo_sistema.css?v=<?php echo filemtime('../logo_sistema.css'); ?>">
    <script src="../js/jquery-3.4.1.min.js"></script>
    <script src="../js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style type="text/css">
        a:link {
            text-decoration: none;
        }

        .col-centered {
            float: none;
            margin: 0 auto;
        }
    </style>
    <title>Login - <?php echo $empresa; ?></title>
</head>

<body>

    <div class="page-header">
        <p></p>
        <p></p>
        <p></p>
    </div>

    <div class="container">
    <div class="row">
        <div class="col-xs-12 col-sm-8 col-md-6 col-lg-4 col-centered">
            <div class="bs-component">
                <div class="text-center" style="margin-bottom: 25px;">
                    <div class="logo-container-login">
                        <img src="../img/logo.png" alt="Logo" class="logo-sistema">
                    </div>
                    <h3 style="color: #337ab7; font-weight: bold; margin-top: 0;">Login <?php if ($mostrarNombre): echo $empresa; endif;?></h3>
                </div>

                <form id="formLogin">
                    <input type="hidden" name="from_web" value="1">
                    <fieldset>
                        <div class="mb-3">
                            <label class="col-form-label" for="username">Usuario</label>
                            <input type="text" class="form-control" placeholder="Ingresar Usuario" name="username" id="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Ingresa tu Contraseña" required>
                        </div>
                        <p></p>
                        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                    </fieldset>
                </form>
                <p></p>
                <div class="text-center">
                    <span class="psw">Olvido su <a href="#">contraseña</a></span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
<script>
    const toast = (mensaje, icono = 'success') => {
        Swal.mixin({
            toast: true,
            position: 'bottom-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        }).fire({
            icon: icono,
            title: mensaje
        });
    }

    $(document).ready(function() {
        $("#formLogin").submit(function(e) {
            e.preventDefault();

            var user = $("#username").val();
            var pass = $("#password").val();

            $.ajax({
                type: "POST",
                url: "../api/login.php",
                data: {
                    username: user,
                    password: pass
                },
                success: function(res) {
                    try {
                        var data = typeof res === "string" ? JSON.parse(res) : res;

                        if (data.status === "ok") {
                            window.location.href = "../admin/dashboard.php";
                        } else {
                            if (data.msg === "usuario" || data.msg === "password" || data.msg === "datos") {
                                toast("Usuario o contraseña incorrectos", "error");
                            } else {
                                toast("Error: " + data.msg, "error");
                            }
                        }
                    } catch (e) {
                        console.log(res);
                        toast("Error inesperado", "error");
                    }
                }
            });
        });
    });
</script>

</html>