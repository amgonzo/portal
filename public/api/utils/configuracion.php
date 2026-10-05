<?php

/**
 * =========================================================
 * CONFIGURACIÓN DEL SISTEMA
 * =========================================================
 *
 * Funciones reutilizables para consultar la configuración
 * central almacenada en la tabla configuracion_sistema.
 */

/**
 * Obtiene una configuración del sistema.
 *
 * @param mysqli $mysqli   Conexión a la base central.
 * @param string $clave    Clave de configuración.
 * @param mixed $default   Valor por defecto si no existe o es inválido.
 *
 * @return mixed
 */
function obtenerConfiguracionSistema(
    mysqli $mysqli,
    string $clave,
    mixed $default = null
) {
    $stmt = $mysqli->prepare("
        SELECT valor, tipo
        FROM configuracion_sistema
        WHERE clave = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $clave);

    if (!$stmt->execute()) {
        $stmt->close();
        return $default;
    }

    $resultado = $stmt->get_result();
    $configuracion = $resultado->fetch_assoc();

    $stmt->close();

    if (!$configuracion) {
        return $default;
    }

    $valor = $configuracion['valor'];
    $tipo = $configuracion['tipo'] ?? 'texto';

    switch ($tipo) {

        case 'entero':

            $valorConvertido = filter_var(
                $valor,
                FILTER_VALIDATE_INT
            );

            return $valorConvertido !== false
                ? $valorConvertido
                : $default;


        case 'decimal':

            if (!is_numeric($valor)) {
                return $default;
            }

            return (float)$valor;


        case 'booleano':

            $valorNormalizado = strtolower(trim((string)$valor));

            if (in_array(
                $valorNormalizado,
                ['1', 'true', 'si', 'sí', 'yes', 'on'],
                true
            )) {
                return true;
            }

            if (in_array(
                $valorNormalizado,
                ['0', 'false', 'no', 'off'],
                true
            )) {
                return false;
            }

            return $default;


        default:

            return $valor;
    }
}