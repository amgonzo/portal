<?php
/**
 * Sistema de Auditoría Central - SSO / CTACTE
 */
// Cargamos el archivo de rutas centralizado de forma segura
$rutas = require $_SERVER['DOCUMENT_ROOT'] . '/api/config/rutas.php';

function registrarLog($mysqli, $accion, $tabla = null, $id_registro = null, $idusuario = null, $dataAntes = null, $dataDespues = null) 
{
    // 1. Forzar NULL si el idusuario no es un entero válido > 0 para evitar el fallo de la FK
    $finalUserId = null;
    if (!empty($idusuario) && (int)$idusuario > 0) {
        $finalUserId = (int)$idusuario;
    } elseif (!empty($_SESSION['idusuario']) && (int)$_SESSION['idusuario'] > 0) {
        $finalUserId = (int)$_SESSION['idusuario'];
    }

    // 2. Capturar IP y obtener el username real
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $username = 'Sistema';

    if ($finalUserId) {
        // Intentamos buscar el usuario. Como la tabla 'usuarios' está en la BD del SSO, 
        // si la conexión actual es de CTACTE fallará, por lo que usamos una conexión al SSO o un fallback seguro.
        try {
            $stmtUser = $mysqli->prepare("SELECT username FROM usuarios WHERE idusuario = ? LIMIT 1");
            if ($stmtUser) {
                $stmtUser->bind_param("i", $finalUserId);
                $stmtUser->execute();
                $resUser = $stmtUser->get_result();
                if ($rowUser = $resUser->fetch_assoc()) {
                    $username = $rowUser['username'];
                }
                $stmtUser->close();
            }
        } catch (Exception $e) {
            // Si la tabla no existe en esta conexión (ej. estamos en CTACTE), 
            // intentamos conectar por un instante a la base de datos del SSO para rescatar el username.
            if (function_exists('conectarDB')) {
                try {
                    $mysqliSso = conectarDB('SSO_');
                    if ($mysqliSso) {
                        $stmtSso = $mysqliSso->prepare("SELECT username FROM usuarios WHERE idusuario = ? LIMIT 1");
                        if ($stmtSso) {
                            $stmtSso->bind_param("i", $finalUserId);
                            $stmtSso->execute();
                            $resSso = $stmtSso->get_result();
                            if ($rowSso = $resSso->fetch_assoc()) {
                                $username = $rowSso['username'];
                            }
                            $stmtSso->close();
                        }
                        $mysqliSso->close();
                    }
                } catch (Exception $ex) {
                    // Si falla la conexión al SSO, mantenemos 'Sistema' o el ID para no romper la ejecución
                }
            }
        }
    }

    // 3. Formatear datos a JSON (antes y después)
    $jsonAntes = null;
    if ($dataAntes !== null) {
        $jsonAntes = (is_array($dataAntes) || is_object($dataAntes)) 
            ? json_encode($dataAntes, JSON_UNESCAPED_UNICODE) 
            : $dataAntes;
    }

    $jsonDespues = null;
    if ($dataDespues !== null) {
        $jsonDespues = (is_array($dataDespues) || is_object($dataDespues)) 
            ? json_encode($dataDespues, JSON_UNESCAPED_UNICODE) 
            : $dataDespues;
    }

    // 4. Inserción en la tabla audit_logs de la conexión activa actual
    $sql = "INSERT INTO audit_logs (idusuario, username, action, tablename, idregistro, dataantes, datadespues, ipaddress, createdat) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "isssisss", 
        $finalUserId, 
        $username, 
        $accion, 
        $tabla, 
        $id_registro, 
        $jsonAntes, 
        $jsonDespues, 
        $ip
    );

    $success = $stmt->execute();
    $stmt->close();

    return $success;
}