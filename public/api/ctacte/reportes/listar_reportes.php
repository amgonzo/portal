<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["status" => "error", "msg" => "metodo_no_permitido"]);
    exit();
}
 
// Por ahora, como los tipos de reportes son fijos, los devolvemos en un array.
// Si mañana querés que el administrador cree nuevos reportes, los sacás de la DB.
$reportes = [
    [
        "id" => "estado_cuenta",
        "categoria" => "Cuentas",
        "nombre" => "Estados de Cuenta",
        "descripcion" => "Listado completo de estados de cuenta."
    ],
    [
        "id" => "recibo",
        "categoria" => "Recibos",
        "nombre" => "Recibo de Pago",
        "descripcion" => "Listado de Recibo por Asociado."
    ],
    [
        "id" => "resumen_mensual",
        "categoria" => "Ctacte",
        "nombre" => "Resumen Mensual",
        "descripcion" => "Listado todos los gastos con limite."
    ]
];

echo json_encode([
    "status" => "ok",
    "data" => $reportes
]);