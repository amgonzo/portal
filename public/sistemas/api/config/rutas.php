<?php

// /var/www/portal/public/api/config/rutas.php

$projectRoot = dirname(__DIR__, 4);
$publicRoot  = $projectRoot . '/public_html/sistemas';

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
    'env_fichajes' => $publicRoot . '/fichajes',
    'env_tablero' => $publicRoot . '/tablero',

    // Archivos PHP
    'conexion'       => $publicRoot . '/api/config/conexion.php',
    'auditoria'      => $publicRoot . '/api/utils/auditoria.php',
    'auditoria_core' => $publicRoot . '/api/utils/auditoria_core.php',
    'diccionario'    => $publicRoot . '/api/utils/diccionario.php',
    'middleware'     => $publicRoot . '/api/sso/auth/auth_middleware.php',
    'contexto'       => $publicRoot . '/api/sso/auth/empresa_context.php',
    'login'          => $publicRoot . '/api/sso/auth/login.php',
    'obtener_recibo' => $publicRoot . '/api/ctacte/reportes/obtener_nro_recibo.php',
    'secretos'       => $projectRoot . '/config/secretos.php',
    'configuracion'  => $publicRoot . '/api/utils/configuracion.php',
    
     // Biometría
    'biometria'      => $publicRoot . '/api/fichajes/biometria/biometria.php',
    
    //compensacion horas
    'compensacion_horas' => $publicRoot . '/api/fichajes/jornadas/compensacion_horas.php',

    'helpers'    => $publicRoot . '/api/utils/helpers.php',
    
    // Rutas web
    'login_sso_web' => '/sistemas/sso/auth/login.php',
    'panel_sso_web' => '/sistemas/sso/admin/panel.php',
    'perfil_sso_web'  => '/sistemas/sso/admin/perfil.php',

    'logo_sso'        => $publicRoot . '/img/logo-orbit.jpg',
    'logo_sso_web'    => '/sistemas/img/logo-orbit.jpg',

    'logo_ctacte'        => $publicRoot . '/img/logoctacte.png',
    'logo_ctacte_web'    => '/sistemas/img/logoctacte.png',

    'logo_fichajes'        => $publicRoot . '/img/logoctacte.png',
    'logo_fichajes_web'    => '/sistemas/img/logoctacte.png',

    'logo_tablero'        => $publicRoot . '/img/logoctacte.png',
    'logo_tablero_web'    => '/sistemas/img/logoctacte.png',

    'favicon'         => $publicRoot . '/favicon.ico',
    'favicon_web'     => '/sistemas/favicon.ico',

    'css_main'        => $publicRoot . '/css/boostrap5a4.css',
    'css_main_web'    => '/sistemas/css/boostrap5a4.css',

    'css_logo' => $publicRoot . '/logo_sistema.css',

    // JS compartido
    'js_sso'     => $publicRoot . '/api/js/sso.js',
    'js_sso_web' => '/sistemas/api/js/sso.js',

    'api_diccionario' => '/config/diccionario.php',
    'api_logout'     => '/sso/auth/logout.php',
];