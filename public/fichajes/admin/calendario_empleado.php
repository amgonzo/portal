<?php

include 'header.php';
include 'menu.php';

?>

<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">

        <div>
            <h4 class="mb-1">
                <i class="fas fa-calendar-alt me-2"></i>
                Calendario del empleado
            </h4>

            <div id="nombreEmpleado" class="text-muted">
                Cargando empleado...
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">

            <label for="calendario_anio" class="mb-0 fw-semibold">
                Año:
            </label>

            <input
                type="number"
                id="calendario_anio"
                class="form-control anio-control"
                min="2000"
                max="2100"
                step="1"
            >

            <button
                type="button"
                id="btnCargarCalendario"
                class="btn btn-primary">
                <i class="fas fa-sync-alt me-1"></i>
                Cargar
            </button>

            <button
                type="button"
                class="btn btn-secondary"
                onclick="history.back()">
                <i class="fas fa-arrow-left me-1"></i>
                Volver
            </button>

        </div>

    </div>


    <!-- LEYENDA -->

    <div class="card shadow-sm mb-3">

        <div class="card-header fw-semibold">
            <i class="fas fa-palette me-1"></i>
            Referencias
        </div>

        <div class="card-body py-2">

            <div
                id="leyendaAsistencias"
                class="leyenda">
            </div>

        </div>

    </div>


    <!-- CALENDARIO -->

    <div class="card shadow-sm">

        <div class="card-body p-0">

            <div class="contenedor-calendario">

                <table
                    class="tabla-calendario"
                    id="tablaCalendario">

                    <thead>

                        <tr id="filaCabeceraDias">

                            <th class="columna-mes">
                                Mes
                            </th>

                        </tr>

                    </thead>

                    <tbody id="cuerpoCalendario"></tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- MENÚ CONTEXTUAL -->

<div
    id="menuAsistencia"
    class="menu-asistencia">

    <div class="menu-asistencia-titulo">
        Aplicar asistencia
    </div>

    <div id="opcionesAsistencia"></div>

</div>


<style>

.contenedor-calendario {
    width: 100%;
    overflow-x: auto;
}

.tabla-calendario {
    border-collapse: collapse;
    width: 100%;
    min-width: 1250px;
    table-layout: fixed;
}

.tabla-calendario th,
.tabla-calendario td {
    border: 1px solid #ced4da;
    padding: 0;
    text-align: center;
}

.tabla-calendario thead th {
    background-color: #2c3e50;
    color: #ffffff;
    font-weight: 600;
    height: 34px;
    position: sticky;
    top: 0;
    z-index: 5;
}

.tabla-calendario .columna-mes {
    width: 110px;
    min-width: 110px;
    background-color: #2c3e50;
    color: #ffffff;
    font-weight: 700;
    position: sticky;
    left: 0;
    z-index: 6;
}

.tabla-calendario .celda-dia {
    width: 38px;
    min-width: 38px;
    height: 38px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    user-select: none;
    transition: filter 0.1s;
}

.tabla-calendario .celda-dia:hover {
    filter: brightness(0.90);
    outline: 2px solid #3498db;
    outline-offset: -2px;
}

.tabla-calendario .dia-inexistente {
    background-color: #e9ecef !important;
    color: #adb5bd !important;
    cursor: default;
}

.tabla-calendario .fin-de-semana {
    box-shadow: inset 0 0 0 9999px rgba(0, 0, 0, 0.04);
}

.tabla-calendario .hoy {
    outline: 3px solid #0d6efd;
    outline-offset: -3px;
}

.cabecera-dia {
    font-size: 11px;
}

.cabecera-dia .numero {
    display: block;
    font-size: 13px;
}

.cabecera-dia .semana {
    display: block;
    font-size: 8px;
    opacity: 0.75;
}

.menu-asistencia {
    position: fixed;
    display: none;

    width: 260px;
    min-width: 260px;
    max-width: 260px;

    max-height: 420px;

    background: #ffffff;
    border: 1px solid #ced4da;
    border-radius: 6px;

    box-shadow:
        0 5px 20px rgba(0, 0, 0, 0.20);

    padding: 5px;

    z-index: 99999;

    overflow: hidden;
}


.menu-asistencia-titulo {
    padding: 6px 9px;

    font-size: 11px;
    font-weight: 700;

    color: #6c757d;

    border-bottom: 1px solid #dee2e6;

    margin-bottom: 2px;
}


/*
 * Solamente el listado hace scroll.
 * El título queda siempre visible.
 */

#opcionesAsistencia {
    max-height: 370px;
    overflow-y: auto;
    overflow-x: hidden;

    padding-right: 2px;
}


/*
 * Scroll del menú
 */

#opcionesAsistencia::-webkit-scrollbar {
    width: 6px;
}

#opcionesAsistencia::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 4px;
}

#opcionesAsistencia::-webkit-scrollbar-thumb {
    background: #adb5bd;
    border-radius: 4px;
}

#opcionesAsistencia::-webkit-scrollbar-thumb:hover {
    background: #6c757d;
}


.opcion-asistencia {
    width: 100%;

    border: 0;
    background: transparent;

    border-radius: 4px;

    padding: 4px 6px;

    display: flex;
    align-items: center;

    gap: 7px;

    cursor: pointer;

    text-align: left;
}


.opcion-asistencia:hover {
    background-color: #f1f3f5;
}


.opcion-color {
    width: 23px;
    height: 23px;

    min-width: 23px;

    border-radius: 4px;

    border: 1px solid rgba(0, 0, 0, 0.15);

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 8px;
    font-weight: 700;
}


.opcion-nombre {
    font-size: 11px;
    line-height: 1.1;
}


.menu-separador {
    border-top: 1px solid #dee2e6;
    margin: 4px 0;
}


.anio-control {
    width: 110px;
}


.leyenda {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}


.leyenda-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    font-size: 12px;
}


.leyenda-color {
    width: 22px;
    height: 22px;

    border-radius: 4px;

    border: 1px solid rgba(0, 0, 0, 0.15);

    display: inline-flex;
    align-items: center;
    justify-content: center;

    font-size: 9px;
    font-weight: 700;
}

@media (max-width: 768px) {

    .tabla-calendario {
        min-width: 1250px;
    }

}

</style>


<script src="js/calendario_empleado.js?v=<?php echo time(); ?>"></script>