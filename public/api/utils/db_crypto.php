<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

/* =========================================================
   RUTAS CENTRALES Y SECRETOS (Carga segura)
   ========================================================= */

$rutasArchivo = $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';
if (!file_exists($rutasArchivo)) {
    throw new RuntimeException('No se encontró el archivo de rutas: ' . $rutasArchivo);
}

$rutas = require $rutasArchivo;

if (!isset($rutas['secretos']) || !file_exists($rutas['secretos'])) {
    throw new RuntimeException('La ruta del archivo de secretos no está definida o no existe.');
}

$secretos = require $rutas['secretos'];

if (!is_array($secretos) || empty($secretos['DB_ENCRYPTION_KEY'])) {
    throw new RuntimeException('No está configurada DB_ENCRYPTION_KEY o el archivo de secretos no retorna un array.');
}

/* =========================================================
   OBTENER CLAVE DE CIFRADO (Inyectando los secretos)
   ========================================================= */

function obtenerClaveCifradoBD(array $secretos): string
{
    // Verificamos explícitamente que la clave exista y sea string
    if (!isset($secretos['DB_ENCRYPTION_KEY']) || !is_string($secretos['DB_ENCRYPTION_KEY'])) {
        throw new RuntimeException('DB_ENCRYPTION_KEY no es válida o es nula.');
    }

    $claveBase64 = $secretos['DB_ENCRYPTION_KEY'];

    $clave = base64_decode(
        $claveBase64,
        true
    );

    if ($clave === false || strlen($clave) !== 32) {
        throw new RuntimeException(
            'DB_ENCRYPTION_KEY debe ser una clave Base64 válida de 32 bytes.'
        );
    }

    return $clave;
}

/* =========================================================
   CIFRAR PASSWORD
   ========================================================= */

function cifrarPasswordBD(string $password, array $secretos): string
{
    $clave = obtenerClaveCifradoBD($secretos);

    $iv = random_bytes(12);
    $tag = '';

    $cifrado = openssl_encrypt(
        $password,
        'aes-256-gcm',
        $clave,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($cifrado === false) {
        throw new RuntimeException(
            'No se pudo cifrar la contraseña de la BD.'
        );
    }

    return base64_encode(
        json_encode([
            'v'    => 1,
            'iv'   => base64_encode($iv),
            'tag'  => base64_encode($tag),
            'data' => base64_encode($cifrado)
        ], JSON_THROW_ON_ERROR)
    );
}

/* =========================================================
   DESCIFRAR PASSWORD
   ========================================================= */

function descifrarPasswordBD(string $passwordCifrada, array $secretos): string
{
    $clave = obtenerClaveCifradoBD($secretos);

    $json = base64_decode(
        $passwordCifrada,
        true
    );

    if ($json === false) {
        throw new RuntimeException(
            'La contraseña cifrada tiene un formato inválido.'
        );
    }

    $datos = json_decode(
        $json,
        true
    );

    if (
        !is_array($datos) ||
        empty($datos['iv']) ||
        empty($datos['tag']) ||
        !isset($datos['data'])
    ) {
        throw new RuntimeException(
            'La contraseña cifrada tiene un formato inválido.'
        );
    }

    $iv = base64_decode(
        $datos['iv'],
        true
    );

    $tag = base64_decode(
        $datos['tag'],
        true
    );

    $cifrado = base64_decode(
        $datos['data'],
        true
    );

    if (
        $iv === false ||
        $tag === false ||
        $cifrado === false
    ) {
        throw new RuntimeException(
            'No se pudo decodificar la contraseña cifrada.'
        );
    }

    $password = openssl_decrypt(
        $cifrado,
        'aes-256-gcm',
        $clave,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );

    if ($password === false) {
        throw new RuntimeException(
            'No se pudo descifrar la contraseña de la BD.'
        );
    }

    return $password;
}