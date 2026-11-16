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
        "id" => "asistencia",
        "categoria" => "Asistencia",
        "nombre" => "Asistencia",
        "descripcion" => "Listado de asistencia por empleado."
    ],
    [
        "id" => "resumen_asistencia",
        "categoria" => "Asistencia",
        "nombre" => "Resumen Mensual",
        "descripcion" => "Listado mensula de asistencia de todos los empleados."
    ]
];

echo json_encode([
    "status" => "ok",
    "data" => $reportes
]);