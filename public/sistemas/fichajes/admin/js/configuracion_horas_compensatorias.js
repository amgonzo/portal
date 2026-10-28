/*
 * CONFIGURACIÓN DE HORAS COMPENSATORIAS
 */

$(document).ready(function () {
    const $form = $("#formConfiguracionHoras");
    const $btnGuardar = $("#btnGuardarConfiguracionHoras");
    const $btnRecargar = $("#btnRecargarConfiguracionHoras");

    const URL_CONFIGURACION =
        typeof API_BASE !== "undefined"
            ? API_BASE + "/fichajes/configuracion/horas_compensatorias.php"
            : null;

    // =====================================================
    // MENSAJES
    // SweetAlert solo para confirmaciones.
    // Toast para éxito, errores y advertencias.
    // =====================================================

    function mostrarToast(mensaje, tipo = "success") {
        if (typeof toast === "function") {
            toast(mensaje, tipo);
        } else {
            console.error(mensaje);
        }
    }

    // =====================================================
    // CAMPOS
    // =====================================================

    function actualizarCampos() {
        const tratamiento = $("#tratamiento_horas_extra").val();

        const habilitarCompensacion = tratamiento === "compensacion";

        $("#panelReglasCompensacion")
            .toggleClass("d-none", !habilitarCompensacion);

        const vigencia = $("#vigencia_saldo_compensacion").val();
        const vigenciaPorMeses = vigencia === "meses";

        $("#contenedorMesesVigencia")
            .toggleClass("d-none", !vigenciaPorMeses);

        $("#meses_vigencia_compensacion")
            .prop("required", vigenciaPorMeses);
    }

    // =====================================================
    // PETICIÓN AL BACKEND
    // =====================================================

    async function solicitarConfiguracion(metodo, datos = null) {
        const opciones = {
            method: metodo,
            cache: "no-store",
            headers: obtenerHeadersSSO()
        };

        if (datos !== null) {
            opciones.headers = {
                ...opciones.headers,
                "Content-Type": "application/json",
                "Accept": "application/json"
            };

            opciones.body = JSON.stringify(datos);
        }

        const response = await fetch(URL_CONFIGURACION, opciones);
        const texto = await response.text();

        let resultado;

        try {
            resultado = JSON.parse(texto);
        } catch (error) {
            console.error(
                "Respuesta inválida de configuración de horas compensatorias:",
                texto
            );

            throw new Error(
                "El servidor no devolvió una respuesta JSON válida."
            );
        }

        if (!response.ok || resultado.status !== "ok") {
            throw new Error(
                resultado.msg ||
                resultado.mensaje ||
                "No se pudo completar la operación."
            );
        }

        return resultado;
    }

    // =====================================================
    // CARGAR CONFIGURACIÓN
    // =====================================================

    async function cargarConfiguracion() {
        $btnRecargar.prop("disabled", true);

        try {
            const resultado = await solicitarConfiguracion("GET");
            const config = resultado.data || resultado.configuracion;

            if (!config || typeof config !== "object") {
                throw new Error(
                    "El servidor no devolvió los datos de configuración."
                );
            }

            const campos = [
                "minutos_minimos_extra",
                "tipo_extra_fuera_horario",
                "tratamiento_horas_extra",
                "modo_generacion_compensacion",
                "vigencia_saldo_compensacion",
                "meses_vigencia_compensacion",
                "modo_vencimiento_compensacion"
            ];

            campos.forEach(function (campo) {
                if (
                    Object.prototype.hasOwnProperty.call(config, campo) &&
                    config[campo] !== null
                ) {
                    $("#" + campo).val(String(config[campo]));
                }
            });

            actualizarCampos();

        } catch (error) {
            console.error(
                "Error al cargar la configuración de horas compensatorias:",
                error
            );

            mostrarToast(error.message, "error");

        } finally {
            $btnRecargar.prop("disabled", false);
        }
    }

    // =====================================================
    // OBTENER DATOS DEL FORMULARIO
    // =====================================================

    function obtenerDatosFormulario() {
        return {
            minutos_minimos_extra: Number(
                $("#minutos_minimos_extra").val()
            ),

            tipo_extra_fuera_horario:
                $("#tipo_extra_fuera_horario").val(),

            tratamiento_horas_extra:
                $("#tratamiento_horas_extra").val(),

            modo_generacion_compensacion:
                $("#modo_generacion_compensacion").val(),

            vigencia_saldo_compensacion:
                $("#vigencia_saldo_compensacion").val(),

            meses_vigencia_compensacion: Number(
                $("#meses_vigencia_compensacion").val()
            ),

            modo_vencimiento_compensacion:
                $("#modo_vencimiento_compensacion").val()
        };
    }

    // =====================================================
    // VALIDAR FORMULARIO
    // =====================================================

    function validarConfiguracion(datos) {
        if (
            !Number.isInteger(datos.minutos_minimos_extra) ||
            datos.minutos_minimos_extra < 0
        ) {
            return "Los minutos mínimos deben ser un número entero igual o mayor que cero.";
        }

        if (
            ![
                "ninguna",
                "pago",
                "compensacion"
            ].includes(datos.tratamiento_horas_extra)
        ) {
            return "El tratamiento de horas extra no es válido.";
        }

        if (
            ![
                "ninguna",
                "despues",
                "antes",
                "antes_despues"
            ].includes(datos.tipo_extra_fuera_horario)
        ) {
            return "El tipo de horas extra fuera del horario no es válido.";
        }

        if (
            ![
                "automatico",
                "autorizado"
            ].includes(datos.modo_generacion_compensacion)
        ) {
            return "El modo de generación no es válido.";
        }

        if (
            ![
                "mismo_mes",
                "meses"
            ].includes(datos.vigencia_saldo_compensacion)
        ) {
            return "La vigencia del saldo no es válida.";
        }

        if (
            ![
                "automatico",
                "revision"
            ].includes(datos.modo_vencimiento_compensacion)
        ) {
            return "El modo de vencimiento no es válido.";
        }

        if (
            datos.vigencia_saldo_compensacion === "meses" &&
            (
                !Number.isInteger(datos.meses_vigencia_compensacion) ||
                datos.meses_vigencia_compensacion < 1 ||
                datos.meses_vigencia_compensacion > 120
            )
        ) {
            return "Los meses de vigencia deben estar entre 1 y 120.";
        }

        return null;
    }

    // =====================================================
    // GUARDAR CONFIGURACIÓN
    // =====================================================

    $form.on("submit", async function (event) {
        event.preventDefault();

        if (!this.reportValidity()) {
            return;
        }

        const datos = obtenerDatosFormulario();
        const errorValidacion = validarConfiguracion(datos);

        if (errorValidacion) {
            mostrarToast(errorValidacion, "warning");
            return;
        }

        let confirmacion;

        const tratamientoActual = datos.tratamiento_horas_extra;

        if (tratamientoActual !== "compensacion") {
            confirmacion = await Swal.fire({
                icon: "warning",
                title: "Desactivar horas compensatorias",
                text: "El módulo dejará de estar habilitado para esta empresa. Los movimientos y antecedentes existentes deben conservarse.",
                showCancelButton: true,
                confirmButtonText: "Continuar",
                cancelButtonText: "Cancelar",
                reverseButtons: true
            });

        } else {
            confirmacion = await Swal.fire({
                icon: "question",
                title: "Guardar configuración",
                text: "¿Querés guardar los cambios de horas extra y compensatorias?",
                showCancelButton: true,
                confirmButtonText: "Guardar",
                cancelButtonText: "Cancelar",
                reverseButtons: true
            });
        }

        if (!confirmacion.isConfirmed) {
            return;
        }

        const textoOriginal = $btnGuardar.html();

        $btnGuardar
            .prop("disabled", true)
            .html(
                '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Guardando...'
            );

        $btnRecargar.prop("disabled", true);

        try {
            await solicitarConfiguracion("POST", datos);

            mostrarToast(
                "Configuración guardada correctamente.",
                "success"
            );

            await cargarConfiguracion();

        } catch (error) {
            console.error(
                "Error al guardar la configuración de horas compensatorias:",
                error
            );

            mostrarToast(error.message, "error");

        } finally {
            $btnGuardar
                .prop("disabled", false)
                .html(textoOriginal);

            $btnRecargar.prop("disabled", false);
        }
    });

    // =====================================================
    // EVENTOS
    // =====================================================

    $("#tratamiento_horas_extra").on("change", actualizarCampos);

    $("#vigencia_saldo_compensacion").on("change", actualizarCampos);

    $btnRecargar.on("click", cargarConfiguracion);

    // =====================================================
    // INICIO
    // =====================================================

    if (
        typeof API_BASE === "undefined" ||
        typeof obtenerHeadersSSO !== "function"
    ) {
        mostrarToast(
            "No están disponibles las funciones de conexión con SSO. Verificá header.php.",
            "error"
        );

        return;
    }

    cargarConfiguracion();
});