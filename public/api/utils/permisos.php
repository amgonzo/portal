<?php
function tienePermiso($permiso) {
    return in_array($permiso, $_SESSION['permisos'] ?? []);
}
?>