<?php

function versionar($url)
{
    global $rutas;

    $urlLimpia = ltrim($url, '/');

    // Primero buscamos usando la raíz pública central
    $rutaAbsoluta = $rutas['public'] . '/' . $urlLimpia;

    if (file_exists($rutaAbsoluta)) {
        return '/' . $urlLimpia . '?v=' . filemtime($rutaAbsoluta);
    }

    // Si no existe ahí, mantenemos el comportamiento anterior
    return $url;
}