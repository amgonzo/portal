<?php

function versionar($url)
{
    global $rutas;

    $urlLimpia = ltrim($url, '/');

    // Raíz física y prefijo público de la aplicación
    $rutaPublica = rtrim($rutas['public'], '/');
    $prefijoURL = '/' . basename($rutaPublica);

    // 1. Buscar archivos globales
    $rutaAbsoluta = $rutaPublica . '/' . $urlLimpia;

    if (file_exists($rutaAbsoluta)) {
        return $prefijoURL . '/' . $urlLimpia
            . '?v=' . filemtime($rutaAbsoluta);
    }

    // 2. Buscar dentro de la aplicación actual
    $scriptActual = $_SERVER['SCRIPT_NAME'] ?? '';
    $directorioActual = dirname($scriptActual);

    // Quitar el prefijo público solamente para la ruta física
    $directorioRelativo = preg_replace(
        '#^' . preg_quote($prefijoURL, '#') . '#',
        '',
        $directorioActual
    );

    if ($directorioRelativo === '/') {
        $directorioRelativo = '';
    }

    $rutaApp = $rutaPublica
        . $directorioRelativo
        . '/'
        . $urlLimpia;

    if (file_exists($rutaApp)) {

        // La URL conserva /sistemas
        $urlFinal = $directorioActual . '/' . $urlLimpia;
        $urlFinal = preg_replace('#/+#', '/', $urlFinal);

        return $urlFinal . '?v=' . filemtime($rutaApp);
    }

    // 3. Si no existe físicamente, conservar el prefijo público
    return $prefijoURL . '/' . $urlLimpia;
}