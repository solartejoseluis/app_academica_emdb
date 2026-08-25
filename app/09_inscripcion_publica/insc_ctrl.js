const CAMPOS_ESTUDIANTE = [
    '#slct_estu_tipodoc', '#npt_estu_numerodoc', '#npt_estu_nombres', '#npt_estu_apellidos',
    '#npt_fechanacimiento', '#slct_estu_sexo', '#npt_estu_telefono', '#npt_estu_email',
    '#npt_estu_ciudad', '#npt_estu_direccion', '#npt_estu_barrio', '#slct_estu_estrato', '#npt_estu_eps',
    '#npt_estu_expedidoen', '#npt_estu_ciudadnac', '#npt_estu_ocupacion',
    '#slct_estu_estadocivil', '#slct_estu_discapacidad'
];

// --- Ficha Familiar: campos siempre obligatorios vs. condicionados a Padre/Madre ---
const CAMPOS_FICHA_SIEMPRE = [
    '#slct_finc_prog_id', '#npt_jornada',
    '#slct_acud_es', '#npt_acud_parentesco', '#npt_acud_nombres', '#npt_acud_apellidos',
    '#npt_acud_profesion', '#npt_acud_empresa', '#npt_acud_telefono', '#npt_acud_direccion',
    '#npt_acud_barrio', '#npt_acud_ciudad',
    '#npt_estudio_tipo', '#npt_estudio_titulo', '#npt_estudio_institucion', '#npt_estudio_aniofin'
];
const CAMPOS_FICHA_PADRE = [
    '#npt_padr_nombres', '#npt_padr_apellidos', '#npt_padr_profesion', '#npt_padr_empresa',
    '#npt_padr_telefono', '#npt_padr_barrio', '#npt_padr_ciudad', '#npt_padr_direccion'
];
const CAMPOS_FICHA_MADRE = [
    '#npt_madr_nombres', '#npt_madr_apellidos', '#npt_madr_profesion', '#npt_madr_empresa',
    '#npt_madr_telefono', '#npt_madr_barrio', '#npt_madr_ciudad', '#npt_madr_direccion'
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

function marcarCampoLleno($campo) {
    const valor = ($campo.val() || '').toString().trim();
    $campo.toggleClass('campo-lleno', valor !== '');
}

function obtenerMulticulturalidad() {
    let seleccionadas = [];
    $('.chk-multicultural:checked').each(function () {
        seleccionadas.push($(this).data('valor'));
    });
    return seleccionadas.join(',');
}

// --- Visibilidad Padre/Madre y cascada de Acudiente (fusionado de fam_ctrl.js) ---
function actualizarVisibilidadPadreMadre() {
    $('#bloque_padre_campos').toggle($('#npt_padr_vive').is(':checked'));
    $('#bloque_madre_campos').toggle($('#npt_madr_vive').is(':checked'));
}

function actualizarSelectAcudiente() {
    const valorActual = $('#slct_acud_es').val();
    let opciones = '<option value="">-- Seleccionar --</option>';
    if ($('#npt_padr_vive').is(':checked')) {
        opciones += '<option value="padre">Padre</option>';
    }
    if ($('#npt_madr_vive').is(':checked')) {
        opciones += '<option value="madre">Madre</option>';
    }
    opciones += '<option value="otro">Otro</option>';
    $('#slct_acud_es').html(opciones);
    // Si la selección anterior ya no es válida (la persona dejó de
    // "vivir"), resetear a vacío y limpiar/habilitar los campos.
    if ($('#slct_acud_es').find(`option[value="${valorActual}"]`).length) {
        $('#slct_acud_es').val(valorActual);
    } else {
        $('#slct_acud_es').val('');
        limpiarYHabilitarCamposAcudiente();
    }
}

const MAPEO_ACUDIENTE = ['nombres', 'apellidos', 'profesion', 'empresa', 'telefono', 'direccion', 'barrio', 'ciudad'];

function limpiarYHabilitarCamposAcudiente() {
    MAPEO_ACUDIENTE.forEach(function (campo) {
        $('#npt_acud_' + campo).val('').prop('readonly', false);
    });
}

function manejarCambioAcudiente() {
    const seleccion = $('#slct_acud_es').val();
    if (seleccion === 'padre' || seleccion === 'madre') {
        const prefijo = seleccion === 'padre' ? 'padr' : 'madr';
        $('#bloque_acud_parentesco').hide();
        $('#npt_acud_parentesco')
            .val(seleccion === 'padre' ? 'PADRE' : 'MADRE')
            .prop('readonly', true);
        MAPEO_ACUDIENTE.forEach(function (campo) {
            const valor = $('#npt_' + prefijo + '_' + campo).val();
            $('#npt_acud_' + campo).val(valor).prop('readonly', true);
        });
    } else {
        $('#bloque_acud_parentesco').show();
        $('#npt_acud_parentesco').val('').prop('readonly', false);
        limpiarYHabilitarCamposAcudiente();
    }
    marcarValidacion($('#npt_acud_parentesco'));
}

$(document).ready(function () {

    // --- Carga de catálogo de Programas ---
    $.ajax({
        type: 'POST',
        url: 'insc_mdl.php?accion=listar_programas',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let opciones = '<option value="">-- Seleccionar --</option>';
                response.data.forEach(function (p) {
                    opciones += `<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                });
                $('#slct_finc_prog_id').html(opciones);
            }
        }
    });

    CAMPOS_ESTUDIANTE.forEach(function (selector) {
        $(selector).on('input change blur', function () {
            if (selector === '#npt_estu_email') {
                validarFormatoEmail($(this));
            } else {
                marcarValidacion($(this));
            }
        });
    });

    // --- Validación visual en vivo: Ficha Familiar ---
    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
        .forEach(function (selector) {
            $(selector).on('input change blur', function () {
                marcarValidacion($(this));
            });
        });

    // --- Fondo verde para campos ya diligenciados ---
    $(document).on('input change', '#form_inscripcion input, #form_inscripcion select, #form_inscripcion textarea', function () {
        marcarCampoLleno($(this));
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

    // --- Visibilidad Padre/Madre y Acudiente dinámico ---
    $('#npt_padr_vive, #npt_madr_vive').on('change', function () {
        actualizarVisibilidadPadreMadre();
        actualizarSelectAcudiente();
    });
    $('#slct_acud_es').on('change', manejarCambioAcudiente);

    actualizarVisibilidadPadreMadre();
    actualizarSelectAcudiente();
    manejarCambioAcudiente();

    // --- Enviar inscripción (formulario único) ---
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

        let camposFicha = CAMPOS_FICHA_SIEMPRE.slice();
        if (!$('#bloque_acud_parentesco').is(':visible')) {
            camposFicha = camposFicha.filter(function (selector) {
                return selector !== '#npt_acud_parentesco';
            });
        }
        if ($('#npt_padr_vive').is(':checked')) {
            camposFicha = camposFicha.concat(CAMPOS_FICHA_PADRE);
        }
        if ($('#npt_madr_vive').is(':checked')) {
            camposFicha = camposFicha.concat(CAMPOS_FICHA_MADRE);
        }
        camposFicha.forEach(function (selector) {
            let $campo = $(selector);
            marcarValidacion($campo);
            if ($campo.hasClass('is-invalid') && primerCampoVacio === null) {
                primerCampoVacio = $campo;
            }
        });

        if (primerCampoVacio !== null) {
            primerCampoVacio.focus();
            alert('Por favor complete todos los campos antes de enviar.');
            return;
        }

        if (!multiculturalidadValida) {
            alert('Por favor complete todos los campos antes de enviar.');
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

            prog_id:              $('#slct_finc_prog_id').val(),
            jornada:              $('#npt_jornada').val(),
            padr_vive:            $('#npt_padr_vive').is(':checked') ? 1 : 0,
            padr_nombres:         $('#npt_padr_nombres').val().trim(),
            padr_apellidos:       $('#npt_padr_apellidos').val().trim(),
            padr_profesion:       $('#npt_padr_profesion').val().trim(),
            padr_empresa:         $('#npt_padr_empresa').val().trim(),
            padr_telefono:        $('#npt_padr_telefono').val().trim(),
            padr_direccion:       $('#npt_padr_direccion').val().trim(),
            padr_barrio:          $('#npt_padr_barrio').val().trim(),
            padr_ciudad:          $('#npt_padr_ciudad').val().trim(),
            madr_vive:            $('#npt_madr_vive').is(':checked') ? 1 : 0,
            madr_nombres:         $('#npt_madr_nombres').val().trim(),
            madr_apellidos:       $('#npt_madr_apellidos').val().trim(),
            madr_profesion:       $('#npt_madr_profesion').val().trim(),
            madr_empresa:         $('#npt_madr_empresa').val().trim(),
            madr_telefono:        $('#npt_madr_telefono').val().trim(),
            madr_direccion:       $('#npt_madr_direccion').val().trim(),
            madr_barrio:          $('#npt_madr_barrio').val().trim(),
            madr_ciudad:          $('#npt_madr_ciudad').val().trim(),
            acud_es:              $('#slct_acud_es').val(),
            acud_parentesco:      $('#npt_acud_parentesco').val().trim(),
            acud_nombres:         $('#npt_acud_nombres').val().trim(),
            acud_apellidos:       $('#npt_acud_apellidos').val().trim(),
            acud_profesion:       $('#npt_acud_profesion').val().trim(),
            acud_empresa:         $('#npt_acud_empresa').val().trim(),
            acud_telefono:        $('#npt_acud_telefono').val().trim(),
            acud_direccion:       $('#npt_acud_direccion').val().trim(),
            acud_barrio:          $('#npt_acud_barrio').val().trim(),
            acud_ciudad:          $('#npt_acud_ciudad').val().trim(),
            estudio_tipo:         $('#npt_estudio_tipo').val().trim(),
            estudio_titulo:       $('#npt_estudio_titulo').val().trim(),
            estudio_institucion:  $('#npt_estudio_institucion').val().trim(),
            estudio_aniofin:      $('#npt_estudio_aniofin').val(),

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
