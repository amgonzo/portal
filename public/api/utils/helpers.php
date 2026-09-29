<?php

function versionar($url)
{
    global $rutas;

    $urlLimpia = ltrim($url, '/');

    /*
     * =========================================================
     * 1. Buscar primero en la raíz pública central
     *
     * Esto permite usar archivos globales, por ejemplo:
     *
     * versionar('css/bootstrap5a4.css')
     *
     * =========================================================
     */

    $rutaAbsoluta = $rutas['public'] . '/' . $urlLimpia;

    if (file_exists($rutaAbsoluta)) {
        return '/' . $urlLimpia . '?v=' . filemtime($rutaAbsoluta);
    }


    /*
     * =========================================================
     * 2. Buscar dentro de la aplicación actual
     *
     * Ejemplo:
     *
     * /public/sso/js/permisos.js
     *
     * Si la página actual está en /sso/, buscamos:
     *
     * /public/sso/js/permisos.js
     * =========================================================
     */

    $scriptActual = $_SERVER['SCRIPT_NAME'] ?? '';

    $directorioActual = dirname($scriptActual);

    // Evitamos problemas si estamos en la raíz
    if ($directorioActual === '/' || $directorioActual === '\\') {
        $directorioActual = '';
    }

    $rutaApp = $rutas['public']
        . $directorioActual
        . '/'
        . $urlLimpia;

    if (file_exists($rutaApp)) {

        $urlFinal = $directorioActual . '/' . $urlLimpia;

        // Normalizar posibles //
        $urlFinal = preg_replace('#/+#', '/', $urlFinal);

        return $urlFinal . '?v=' . filemtime($rutaApp);
    }


    /*
     * =========================================================
     * 3. Si no se encontró, mantener comportamiento anterior
     * =========================================================
     */

    return $url;
}