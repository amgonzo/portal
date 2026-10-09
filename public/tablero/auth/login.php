<?php

// ============================================================
// LOGIN DE LA APLICACIÓN
// ============================================================
// La autenticación ahora la realiza el SSO central.
//
// Este archivo NO:
// - pide usuario y contraseña
// - consulta ../api/login.php
// - crea una sesión PHP para autenticar al usuario
// - guarda idusuario/idempresa en $_SESSION
//
// Si existe sso_token, se permite continuar al dashboard.
// Si no existe, se redirige al login central del SSO.
// ============================================================

$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

// ------------------------------------------------------------
// Configuración visual de la aplicación
// ------------------------------------------------------------

require_once $rutas['autoload'];

try {
    $dotenv = Dotenv\Dotenv::createImmutable($rutas['env_api']);
    $dotenv->load();
} catch (Exception $e) {
    // Error silencioso si no hay .env
}

$empresa = $_ENV['APP_NAME'] ?? 'Mi Sistema';

$mostrarNombre = filter_var(
    $_ENV['MOSTRAR_APP_NAME'] ?? false,
    FILTER_VALIDATE_BOOLEAN
);

// ------------------------------------------------------------
// Login central SSO
// ------------------------------------------------------------

$loginSSO = $rutas['login_sso_web'] ?? '/sso/auth/login.php';

?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        rel="icon"
        href="../favicon.ico"
        type="image/x-icon"
    >

    <link
        rel="stylesheet"
        href="../css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="../logo_sistema.css?v=<?php echo filemtime('../logo_sistema.css'); ?>"
    >

    <title>
        <?php
        echo $mostrarNombre
            ? 'Acceso - ' . htmlspecialchars($empresa)
            : 'Acceso';
        ?>
    </title>

</head>

<body>

<script>

// ============================================================
// VERIFICAR AUTENTICACIÓN SSO
// ============================================================

(function () {

    const token = localStorage.getItem('sso_token');

    // --------------------------------------------------------
    // Si NO hay token:
    // enviamos al login central SSO.
    // --------------------------------------------------------

    if (!token) {

        window.location.replace(
            <?= json_encode($loginSSO) ?>
        );

        return;
    }

    // --------------------------------------------------------
    // Si hay token:
    // entramos directamente a la aplicación.
    // --------------------------------------------------------

    window.location.replace(
        "../admin/dashboard.php"
    );

})();

</script>

</body>

</html>