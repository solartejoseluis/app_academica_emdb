const CAMPOS_ESTUDIANTE = [
    '#slct_estu_tipodoc', '#npt_estu_numerodoc', '#npt_estu_nombres', '#npt_estu_apellidos',
    '#npt_fechanacimiento', '#slct_estu_sexo', '#npt_estu_telefono', '#npt_estu_email',
    '#npt_estu_ciudad', '#npt_estu_direccion', '#npt_estu_barrio', '#slct_estu_estrato', '#npt_estu_eps',
    '#npt_estu_expedidoen', '#npt_estu_ciudadnac', '#npt_estu_ocupacion',
    '#slct_estu_estadocivil', '#slct_estu_discapacidad'
];

function marcarValidacion($campo) {
    const valor = ($campo.val() || '').toString().trim();
    $campo.removeClass('is-invalid is-valid');
    $campo.addClass(valor === '' ? 'is-invalid' : 'is-valid');
}

function validarFormatoEmail($campo) {
    const valor = ($campo.val() || '').trim();
    $campo.removeClass('is-invalid is-valid');
    if (valor === '' || valor.indexOf('@') === -1) {
        $campo.addClass('is-invalid');
    } else {
        $campo.addClass('is-valid');
    }
}

function obtenerMulticulturalidad() {
    let seleccionadas = [];
    $('.chk-multicultural:checked').each(function () {
        seleccionadas.push($(this).data('valor'));
    });
    return seleccionadas.join(',');
}

$(document).ready(function () {

    CAMPOS_ESTUDIANTE.forEach(function (selector) {
        $(selector).on('input change blur', function () {
            if (selector === '#npt_estu_email') {
                validarFormatoEmail($(this));
            } else {
                marcarValidacion($(this));
            }
        });
    });

    // --- Formato en vivo del número de documento (separador de miles) ---
    $('#npt_estu_numerodoc').on('input', function () {
        let soloDigitos = $(this).val().replace(/\D/g, '');
        $(this).val(soloDigitos.replace(/\B(?=(\d{3})+(?!\d))/g, '.'));
    });

    // --- Minúsculas forzadas en vivo: correo electrónico ---
    $('#npt_estu_email').on('input', function () {
        const inicio = this.selectionStart;
        const fin = this.selectionEnd;
        $(this).val($(this).val().toLowerCase());
        this.setSelectionRange(inicio, fin);
    });

    // --- Multiculturalidad: exclusión mutua "No aplica" vs. el resto ---
    $('#chk_multi_no_aplica').on('change', function () {
        if ($(this).is(':checked')) {
            $('.chk-multicultural').not(this).prop('checked', false);
        }
    });
    $('.chk-multicultural').not('#chk_multi_no_aplica').on('change', function () {
        if ($(this).is(':checked')) {
            $('#chk_multi_no_aplica').prop('checked', false);
        }
    });

    // --- Enviar inscripción ---
    $('#btn_enviar_inscripcion').click(function () {
        let primerCampoVacio = null;

        CAMPOS_ESTUDIANTE.forEach(function (selector) {
            let $campo = $(selector);
            if (selector === '#npt_estu_email') {
                validarFormatoEmail($campo);
            } else {
                marcarValidacion($campo);
            }
            if ($campo.hasClass('is-invalid') && primerCampoVacio === null) {
                primerCampoVacio = $campo;
            }
        });

        let multiculturalidadValida = $('.chk-multicultural:checked').length > 0;
        $('#lbl_multiculturalidad').toggleClass('text-danger', !multiculturalidadValida);

        if (primerCampoVacio !== null) {
            primerCampoVacio.focus();
            alert('Por favor complete todos los campos antes de guardar.');
            return;
        }

        if (!multiculturalidadValida) {
            alert('Por favor complete todos los campos antes de guardar.');
            return;
        }

        let recaptchaResponse = grecaptcha.getResponse();
        if (!recaptchaResponse) {
            alert('Por favor confirma que no eres un robot');
            return;
        }

        let data = {
            estu_tipodoc:   $('#slct_estu_tipodoc').val(),
            estu_numerodoc: $('#npt_estu_numerodoc').val().replace(/\./g, ''),
            estu_nombres:   $('#npt_estu_nombres').val().trim(),
            estu_apellidos: $('#npt_estu_apellidos').val().trim(),
            fechanacimiento: $('#npt_fechanacimiento').val(),
            estu_sexo:      $('#slct_estu_sexo').val(),
            estu_telefono:  $('#npt_estu_telefono').val().trim(),
            estu_email:     $('#npt_estu_email').val().trim(),
            estu_ciudad:    $('#npt_estu_ciudad').val().trim(),
            estu_direccion: $('#npt_estu_direccion').val().trim(),
            estu_barrio:    $('#npt_estu_barrio').val().trim(),
            estu_estrato:   $('#slct_estu_estrato').val(),
            estu_eps:       $('#npt_estu_eps').val().trim(),
            estu_expedidoen:        $('#npt_estu_expedidoen').val().trim(),
            estu_ciudadnac:         $('#npt_estu_ciudadnac').val().trim(),
            estu_ocupacion:         $('#npt_estu_ocupacion').val().trim(),
            estu_estadocivil:       $('#slct_estu_estadocivil').val(),
            estu_discapacidad:      $('#slct_estu_discapacidad').val(),
            estu_multiculturalidad: obtenerMulticulturalidad(),
            'g-recaptcha-response':  recaptchaResponse
        };

        $.ajax({
            type: 'POST',
            url: 'insc_mdl.php?accion=crear_aspirante',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    $('#form_inscripcion').addClass('d-none');
                    $('#codigo_temporal_resultado').text(response.codigo);
                    $('#lnk_continuar_datos_familiares').attr('href', 'fam_view.php?codigo=' + response.codigo);
                    $('#bloque_confirmacion').removeClass('d-none');
                } else {
                    alert(response.message);
                    grecaptcha.reset();
                }
            },
            error: function () {
                alert('Error al procesar la inscripción');
                grecaptcha.reset();
            }
        });
    });
});
