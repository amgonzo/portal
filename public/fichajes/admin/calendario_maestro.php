<?php

include 'header.php';
include 'menu.php';

?>

<div class="container-fluid py-3">

    <!-- =====================================================
         CABECERA
         ===================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">

        <div>

            <h4 class="mb-1">
                <i class="fas fa-calendar-alt me-2"></i>
                Calendario Maestro
            </h4>

            <div class="text-muted small">
                Configuración general del calendario de asistencia
            </div>

        </div>


        <div class="d-flex align-items-center gap-2">

            <label
                for="calendario_anio"
                class="fw-semibold mb-0">

                Año:

            </label>

            <input
                type="number"
                id="calendario_anio"
                class="form-control form-control-sm anio-control"
                min="2000"
                max="2100">

            <button
                type="button"
                class="btn btn-primary btn-sm"
                id="btnCargarCalendario">

                <i class="fas fa-sync-alt me-1"></i>
                Cargar

            </button>

        </div>

    </div>


    <!-- =====================================================
         LEYENDA
         ===================================================== -->

    <div class="card shadow-sm mb-3">

        <div class="card-body py-2">

            <div class="d-flex align-items-center mb-2">

                <strong class="small">
                    Tipos disponibles
                </strong>

            </div>

            <div
                id="leyendaAsistencias"
                class="leyenda">

                <span class="text-muted small">
                    Cargando...
                </span>

            </div>

        </div>

    </div>


    <!-- =====================================================
         CALENDARIO
         ===================================================== -->

    <div class="card shadow-sm">

        <div class="card-body p-2">

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

                    <tbody id="cuerpoCalendario">
                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     MENÚ CONTEXTUAL
     ========================================================= -->

<div
    id="menuAsistencia"
    class="menu-asistencia">

    <div class="menu-asistencia-titulo">

        Aplicar asistencia

    </div>

    <div id="opcionesAsistencia">

    </div>

</div>


<!-- =========================================================
     ESTILOS ESPECÍFICOS DEL CALENDARIO
     ========================================================= -->

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
        min-width: 220px;
        max-width: 280px;
        background: #ffffff;
        border: 1px solid #ced4da;
        border-radius: 6px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.20);
        padding: 5px;
        z-index: 99999;
    }

    .menu-asistencia-titulo {
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 700;
        color: #6c757d;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 3px;
    }

    .opcion-asistencia {
        width: 100%;
        border: 0;
        background: transparent;
        border-radius: 4px;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        text-align: left;
    }

    .opcion-asistencia:hover {
        background-color: #f1f3f5;
    }

    .opcion-color {
        width: 25px;
        height: 25px;
        min-width: 25px;
        border-radius: 4px;
        border: 1px solid rgba(0, 0, 0, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9px;
        font-weight: 700;
    }

    .opcion-nombre {
        font-size: 12px;
        line-height: 1.1;
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


<!-- =========================================================
     JAVASCRIPT
     ========================================================= -->

<script src="js/calendario_maestro.js?v=<?php echo time(); ?>"></script>