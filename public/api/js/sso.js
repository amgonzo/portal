// =========================================================
// SSO.JS - FUNCIONES COMUNES DEL SISTEMA
// =========================================================

const ID_USUARIO_LOGUEADO =
    localStorage.getItem('sso_idusuario') || 0;


// =========================================================
// TOAST
// =========================================================

const toast = (mensaje, icono = 'success') => {
    Swal.mixin({
        toast: true,
        position: 'bottom-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    }).fire({
        icon: icono,
        title: mensaje
    });
};


// =========================================================
// TEMA
// =========================================================

(function() {

    const temaGuardado =
        localStorage.getItem('theme_mode') || 'light';

    document.documentElement.setAttribute(
        'data-bs-theme',
        temaGuardado
    );

})();


function toggleModoOscuro() {

    const html = document.documentElement;

    const nuevoTema =
        html.getAttribute('data-bs-theme') === 'dark'
            ? 'light'
            : 'dark';

    html.setAttribute(
        'data-bs-theme',
        nuevoTema
    );

    localStorage.setItem(
        'theme_mode',
        nuevoTema
    );

    actualizarIconoTema(nuevoTema);
}


function actualizarIconoTema(tema) {

    const icono =
        document.getElementById('iconoTheme');

    const switchInput =
        document.getElementById('checkThemeSwitch');

    if (tema === 'dark') {

        if (icono) {
            icono.className =
                'bi bi-sun-fill text-warning me-2';
        }

        if (switchInput) {
            switchInput.checked = true;
        }

    } else {

        if (icono) {
            icono.className =
                'bi bi-moon-stars-fill me-2';
        }

        if (switchInput) {
            switchInput.checked = false;
        }

    }
}


document.addEventListener(
    'DOMContentLoaded',
    function() {

        actualizarIconoTema(
            localStorage.getItem('theme_mode') || 'light'
        );

    }
);


// =========================================================
// EMPRESA ACTIVA
// =========================================================

function obtenerEmpresaActiva() {

    try {

        return JSON.parse(
            localStorage.getItem(
                'sso_empresa_activa'
            ) || 'null'
        );

    } catch (e) {

        return null;

    }
}

// =========================================================
// HEADERS SSO
// =========================================================

function obtenerHeadersSSO() {

    const token =
        localStorage.getItem('sso_token');

    const empresa =
        obtenerEmpresaActiva();

    let aplicacion = null;

    try {

        aplicacion = JSON.parse(
            localStorage.getItem(
                'sso_app_activa'
            ) || 'null'
        );

    } catch (e) {

        aplicacion = null;

    }


    const headers = {

        "Authorization":
            "Bearer " + (token || "")

    };


    // -----------------------------------------
    // EMPRESA ACTIVA
    // -----------------------------------------

    if (
        empresa &&
        empresa.idempresa
    ) {

        headers["X-EMPRESA-ID"] =
            String(empresa.idempresa);

    }


    // -----------------------------------------
    // APLICACIÓN ACTIVA
    // -----------------------------------------

    if (
        aplicacion &&
        aplicacion.idaplicacion
    ) {

        headers["X-APLICACION-ID"] =
            String(aplicacion.idaplicacion);

    }


    return headers;
}


// =========================================================
// DICCIONARIO
// =========================================================

let APP_DICCIONARIO = {};


// Carga el diccionario correspondiente
// a la empresa activa.

async function cargarDiccionario() {

    const token =
        localStorage.getItem('sso_token');

    if (!token) {
        return;
    }

    const empresa =
        obtenerEmpresaActiva();

    if (
        !empresa ||
        !empresa.idempresa
    ) {

        APP_DICCIONARIO = {};

        return;

    }

    const idempresa =
        String(empresa.idempresa);


    // -----------------------------------------
    // Intentar usar cache de la empresa actual
    // -----------------------------------------

    try {

        const guardado =
            JSON.parse(
                localStorage.getItem(
                    'app_diccionario'
                ) || 'null'
            );

        if (
            guardado &&
            String(guardado.idempresa) === idempresa
        ) {

            APP_DICCIONARIO =
                guardado.diccionario || {};

            return;

        }

    } catch (e) {

        localStorage.removeItem(
            'app_diccionario'
        );

    }


    // -----------------------------------------
    // Consultar API
    // -----------------------------------------

    try {

        const response =
            await fetch(
                API_BASE +
                '/config/diccionario.php',
                {
                    method: 'GET',
                    headers: obtenerHeadersSSO()
                }
            );

        if (!response.ok) {

            throw new Error(
                'No se pudo cargar el diccionario'
            );

        }

        const data =
            await response.json();

        if (
            data.status !== 'ok'
        ) {

            APP_DICCIONARIO = {};

            return;

        }


        APP_DICCIONARIO =
            data.diccionario || {};


        localStorage.setItem(
            'app_diccionario',
            JSON.stringify({
                idempresa:
                    data.idempresa,
                diccionario:
                    data.diccionario || {}
            })
        );


        // Avisar a las aplicaciones
        // que el diccionario ya está cargado.

        document.dispatchEvent(
            new CustomEvent(
                'diccionarioCargado'
            )
        );


    } catch (error) {

        console.error(
            'Error cargando diccionario:',
            error
        );

    }

}


// =========================================================
// OBTENER TEXTO DEL DICCIONARIO
// =========================================================

function diccionario(
    clave,
    defecto = ''
) {

    return Object.prototype.hasOwnProperty.call(
        APP_DICCIONARIO,
        clave
    )
        ? APP_DICCIONARIO[clave]
        : defecto;
}


// =========================================================
// APLICAR DICCIONARIO A ELEMENTOS
// =========================================================

function aplicarDiccionario() {

    document
        .querySelectorAll(
            '[data-diccionario]'
        )
        .forEach(
            elemento => {

                const clave =
                    elemento.dataset.diccionario;

                elemento.textContent =
                    diccionario(
                        clave,
                        ''
                    );

            }
        );

}


// =========================================================
// APLICAR DICCIONARIO A TEXTO
// =========================================================

function aplicarDiccionarioTexto(
    texto
) {

    if (!texto) {
        return texto;
    }

    return texto

        .replace(
            /\bEmpleados\b/g,
            diccionario(
                'empleado_plural',
                'Empleados'
            )
        )

        .replace(
            /\bEmpleado\b/g,
            diccionario(
                'empleado_singular',
                'Empleado'
            )
        );

}


// =========================================================
// CARGAR DICCIONARIO AL INICIAR
// =========================================================

document.addEventListener(
    'DOMContentLoaded',
    async function() {

        await cargarDiccionario();

        aplicarDiccionario();

    }
);


// Si el diccionario llega después,
// volver a aplicarlo.

document.addEventListener(
    'diccionarioCargado',
    function() {

        aplicarDiccionario();

    }
);


// =========================================================
// PERMISOS
// =========================================================

function tienePermiso(clave) {

    const permisos =
        JSON.parse(
            localStorage.getItem(
                'sso_permisos'
            ) || '[]'
        );

    return Array.isArray(permisos)
        && permisos.includes(clave);

}


// =========================================================
// CERRAR SESIÓN
// =========================================================

function cerrarSesion() {

    const token =
        localStorage.getItem('sso_token');

    $.ajax({

        type: "POST",

        url:
            API_BASE +
            "/sso/auth/logout.php",

        headers: {

            "Authorization":
                "Bearer " + (token || "")

        },

        complete: function() {

            localStorage.removeItem(
                'sso_token'
            );

            localStorage.removeItem(
                'sso_empresa_activa'
            );

            localStorage.removeItem(
                'sso_ultima_empresa'
            );

            localStorage.removeItem(
                'sso_aplicaciones'
            );

            localStorage.removeItem(
                'sso_empresas'
            );

            localStorage.removeItem(
                'sso_permisos'
            );

            localStorage.removeItem(
                'app_diccionario'
            );

            sessionStorage.clear();

            window.location.href =
                "/sso/auth/login.php";

        }

    });

}


// =========================================================
// INTERCEPTOR 401
// =========================================================
/*
$(document).ajaxError(
    function(
        event,
        jqXHR,
        settings,
        thrownError
    ) {

        if (jqXHR.status !== 401) {
            return;
        }


        if (
            settings.url.includes(
                'login.php'
            ) ||
            settings.url.includes(
                'logout.php'
            )
        ) {

            return;

        }


        localStorage.removeItem(
            'sso_token'
        );

        if (
            typeof Swal !==
            'undefined'
        ) {

            Swal.fire({

                title:
                    'Sesión expirada',

                text:
                    'Tu sesión ha expirado por inactividad. Por favor, volvé a ingresar.',

                icon:
                    'warning',

                confirmButtonText:
                    'Ir al Login',

                allowOutsideClick:
                    false

            }).then(
                () => {

                    window.location.href =
                        '/sso/auth/login.php';

                }
            );

        } else {

            alert(
                'Tu sesión ha expirado por inactividad.'
            );

            window.location.href =
                '/sso/auth/login.php';

        }

    }
);

document.addEventListener('DOMContentLoaded', async function () {

    try {

        const response = await fetch(
            API_BASE + '/sso/auth/me.php',
            {
                method: 'GET',
                headers: obtenerHeadersSSO()
            }
        );

        const data = await response.json();

        if (data.status !== 'ok') {
            console.error('Error obteniendo contexto SSO:', data);
            return;
        }

        MIS_PERMISOS = Array.isArray(data.permisos)
            ? data.permisos
            : [];

        localStorage.setItem(
            'sso_permisos',
            JSON.stringify(MIS_PERMISOS)
        );

    } catch (error) {

        console.error(
            'Error consultando permisos SSO:',
            error
        );

    }

});*/