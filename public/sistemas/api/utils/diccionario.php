<?php

function cargarDiccionario($mysqli)
{
    $diccionario = [];

    $resultado = $mysqli->query("
        SELECT clave, valor
        FROM diccionario
        WHERE activo = 1
    ");

    if (!$resultado) {
        return $diccionario;
    }

    while ($fila = $resultado->fetch_assoc()) {
        $diccionario[$fila['clave']] = $fila['valor'];
    }

    return $diccionario;
}

function diccionario($diccionario, $clave, $defecto = '')
{
    return $diccionario[$clave] ?? $defecto;
}