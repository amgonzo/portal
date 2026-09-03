<?php

// /var/www/portal/public/api/config/rutas.php

$projectRoot = dirname(__DIR__, 3);
$publicRoot  = $projectRoot . '/public';

return [

    // Raíz del proyecto
    'root' => $projectRoot,

    // Raíz pública
    'public' => $publicRoot,

    // Composer
    'autoload' => $projectRoot . '/vendor/autoload.php',

    // .env
    'env_sso'    => $publicRoot . '/sso',
    'env_api'    => $publicRoot . '/api',
    'env_ctacte' => $publicRoot . '/ctacte',

    // Archivos PHP
    'conexion'       => $publicRoot . '/api/config/conexion.php',
    'auditoria'      => $publicRoot . '/api/utils/auditoria.php',
    'auditoria_core' => $publicRoot . '/api/utils/auditoria_core.php',
    'middleware'     => $publicRoot . '/api/sso/auth/auth_middleware.php',
    'login'          => $publicRoot . '/api/sso/auth/login.php',
    'obtener_recibo' => $publicRoot . '/api/ctacte/reportes/obtener_nro_recibo.php',

    // Rutas web
    'login_sso_web' => '/sso/auth/login.php',
    'panel_sso_web' => '/sso/admin/panel.php',
    'perfil_sso_web'  => '/sso/admin/perfil.php',

    'logo_sso'        => $publicRoot . '/img/logosso.png',
    'logo_sso_web'    => '/img/logosso.png',

    'logo_ctacte'        => $publicRoot . '/img/logoctacte.png',
    'logo_ctacte_web'    => '/img/logoctacte.png',

    'favicon'         => $publicRoot . '/favicon.ico',
    'favicon_web'     => '/favicon.ico',

    'css_main'        => $publicRoot . '/css/boostrap5a4.css',
    'css_main_web'    => '/css/boostrap5a4.css',

    'css_logo' => $publicRoot . '/logo_sistema.css',
];