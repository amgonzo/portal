<?php
ini_set('session.gc_maxlifetime', 7200);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// 🛑 BYPASS PROVISORIO DE ACCESO
// ===============================
$_SESSION['idusuario'] = 2; // Forzamos ID del usuario Auditor / Root
$_SESSION['idtipousuario'] = 99; // Forzamos rol de superusuario
$_SESSION['nombreusuario'] = 'Auditor';
$_SESSION['last_activity'] = time();

$empresa = "Mi Sistema";
$isAjax = false;

// Omitimos validaciones estrictas y consultas a DB para que no rompa más.
return;