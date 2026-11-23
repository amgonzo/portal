const API_DATOS_CONTACTO =
    API_BASE + '/rrhh/personal/datos_contacto.php';


document.addEventListener('DOMContentLoaded', () => {

    document
        .getElementById('formDatosContacto')
        .addEventListener('submit', guardarDatosContacto);

});


async function abrirDatosContacto(idEmpleado) {

    const form = document.getElementById('formDatosContacto');

    form.reset();

    document.getElementById('contacto_idempleado').value = idEmpleado;

    try {

        const respuesta = await fetch(
            API_DATOS_CONTACTO +
            '?action=obtener&idempleado=' +
            encodeURIComponent(idEmpleado),
            {
                method: 'GET',
                headers: obtenerHeadersSSO(),
                cache: 'no-store'
            }
        );

        const json = await respuesta.json();

        if (!respuesta.ok || json.status !== 'ok') {
            throw new Error(json.msg || 'No se pudieron obtener los datos de contacto.');
        }

        const datos = json.data;

        document.getElementById('contacto_email').value =
            datos.email || '';

        document.getElementById('contacto_telefono').value =
            datos.telefono || '';

        document.getElementById('contacto_celular').value =
            datos.celular || '';

        document.getElementById('contacto_domicilio').value =
            datos.domicilio || '';

        document.getElementById('contacto_localidad').value =
            datos.localidad || '';

        document.getElementById('contacto_provincia').value =
            datos.provincia || '';

        document.getElementById('contacto_codigo_postal').value =
            datos.codigo_postal || '';

        document.getElementById('contacto_entre_calles').value =
            datos.entre_calles || '';

        document.getElementById('contacto_referencia').value =
            datos.referencia_domicilio || '';

        bootstrap.Modal
            .getOrCreateInstance(
                document.getElementById('ModalDatosContacto')
            )
            .show();

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');
    }
}


async function guardarDatosContacto(event) {

    event.preventDefault();

    const form = document.getElementById('formDatosContacto');
    const boton = document.getElementById('btnGuardarContacto');

    if (!form.reportValidity()) {
        return;
    }

    const formData = new FormData(form);

    formData.append('action', 'guardar');

    const textoOriginal = boton.innerHTML;

    boton.disabled = true;
    boton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {

        const respuesta = await fetch(API_DATOS_CONTACTO, {
            method: 'POST',
            headers: obtenerHeadersSSO(),
            body: formData
        });

        const json = await respuesta.json();

        if (!respuesta.ok || json.status !== 'ok') {
            throw new Error(json.msg || 'No se pudieron guardar los datos.');
        }

        bootstrap.Modal
            .getOrCreateInstance(
                document.getElementById('ModalDatosContacto')
            )
            .hide();

        toast(json.msg, 'success');

    } catch (error) {

        console.error(error);
        toast(error.message, 'error');

    } finally {

        boton.disabled = false;
        boton.innerHTML = textoOriginal;
    }
}