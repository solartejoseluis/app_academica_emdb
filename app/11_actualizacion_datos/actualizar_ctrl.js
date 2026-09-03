// Reutilizadas (copiadas, no importadas) de est_ctrl.js/insc_ctrl.js — mismo
// criterio de duplicación deliberada ya documentado en CLAUDE.md para
// formularios públicos sin sesión ("insc_view.php es una copia deliberada de
// est_view.php"). Sin estu_tipodoc/estu_numerodoc — nunca editables aquí.
const CAMPOS_ESTUDIANTE = [
    '#npt_estu_nombres', '#npt_estu_apellidos',
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

// --- Visibilidad Padre/Madre y cascada de Acudiente (mismo patrón que insc_ctrl.js) ---
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

    const token = new URLSearchParams(window.location.search).get('token') || '';
    $('#npt_token').val(token);

    const MENSAJES_ERROR = {
        invalido: 'Este link no es válido. Verifica que copiaste la URL completa, o solicita uno nuevo al coordinador.',
        usado: 'Este link ya fue utilizado — tu actualización ya fue enviada y está pendiente de revisión (o ya fue resuelta). Si necesitas hacer más cambios, solicita un nuevo link al coordinador.',
        expirado: 'Este link ha expirado. Solicita uno nuevo al coordinador para continuar.'
    };

    function mostrarError(motivo) {
        $('#bloque_cargando').addClass('d-none');
        $('#txt_error_mensaje').text(MENSAJES_ERROR[motivo] || MENSAJES_ERROR.invalido);
        $('#bloque_error').removeClass('d-none');
    }

    if (!token) {
        mostrarError('invalido');
        return;
    }

    // --- Carga de catálogo de Programas (mismo endpoint público de insc_mdl.php
    // no aplica aquí — este módulo tiene su propio backend sin sesión) ---
    function cargarProgramas() {
        $.ajax({
            type: 'POST',
            url: 'actualizar_mdl.php?accion=listar_programas',
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
    }

    $.ajax({
        type: 'POST',
        url: 'actualizar_mdl.php?accion=validar_token',
        data: { token: token },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                mostrarError(response.motivo);
                return;
            }
            const d = response.data;

            $('#txt_encabezado_nombre').text(d.estu_nombres + ' ' + d.estu_apellidos);
            $('#txt_encabezado_documento').text((d.estu_tipodoc || '') + ' ' + (d.estu_numerodoc || ''));
            $('#txt_estu_tipodoc').text(d.estu_tipodoc || '—');
            $('#txt_estu_numerodoc').text(d.estu_numerodoc || '—');

            $('#npt_estu_nombres').val(d.estu_nombres);
            $('#npt_estu_apellidos').val(d.estu_apellidos);
            $('#npt_fechanacimiento').val(d.fechanacimiento);
            $('#slct_estu_sexo').val(d.estu_sexo);
            $('#npt_estu_telefono').val(d.estu_telefono);
            $('#npt_estu_email').val(d.estu_email);
            $('#npt_estu_ciudad').val(d.estu_ciudad);
            $('#npt_estu_direccion').val(d.estu_direccion);
            $('#npt_estu_barrio').val(d.estu_barrio);
            $('#slct_estu_estrato').val(d.estu_estrato);
            $('#npt_estu_eps').val(d.estu_eps);
            $('#npt_estu_expedidoen').val(d.estu_expedidoen);
            $('#npt_estu_ciudadnac').val(d.estu_ciudadnac);
            $('#npt_estu_ocupacion').val(d.estu_ocupacion);
            $('#slct_estu_estadocivil').val(d.estu_estadocivil);
            $('#slct_estu_discapacidad').val(d.estu_discapacidad || '9) NO APLICA');

            let multi = (d.estu_multiculturalidad || '')
                .split(',')
                .map(function (v) { return v.trim(); })
                .filter(function (v) { return v !== ''; });
            $('.chk-multicultural').prop('checked', false);
            if (multi.length === 0) {
                $('#chk_multi_no_aplica').prop('checked', true);
            } else {
                multi.forEach(function (valor) {
                    $('.chk-multicultural[data-valor="' + valor + '"]').prop('checked', true);
                });
            }

            $('#npt_padr_vive').prop('checked', d.padr_vive == 1);
            $('#npt_padr_nombres').val(d.padr_nombres);
            $('#npt_padr_apellidos').val(d.padr_apellidos);
            $('#npt_padr_profesion').val(d.padr_profesion);
            $('#npt_padr_empresa').val(d.padr_empresa);
            $('#npt_padr_telefono').val(d.padr_telefono);
            $('#npt_padr_direccion').val(d.padr_direccion);
            $('#npt_padr_barrio').val(d.padr_barrio);
            $('#npt_padr_ciudad').val(d.padr_ciudad);

            $('#npt_madr_vive').prop('checked', d.madr_vive == 1);
            $('#npt_madr_nombres').val(d.madr_nombres);
            $('#npt_madr_apellidos').val(d.madr_apellidos);
            $('#npt_madr_profesion').val(d.madr_profesion);
            $('#npt_madr_empresa').val(d.madr_empresa);
            $('#npt_madr_telefono').val(d.madr_telefono);
            $('#npt_madr_direccion').val(d.madr_direccion);
            $('#npt_madr_barrio').val(d.madr_barrio);
            $('#npt_madr_ciudad').val(d.madr_ciudad);

            $('#npt_acud_parentesco').val(d.acud_parentesco);
            $('#npt_acud_nombres').val(d.acud_nombres);
            $('#npt_acud_apellidos').val(d.acud_apellidos);
            $('#npt_acud_profesion').val(d.acud_profesion);
            $('#npt_acud_empresa').val(d.acud_empresa);
            $('#npt_acud_telefono').val(d.acud_telefono);
            $('#npt_acud_direccion').val(d.acud_direccion);
            $('#npt_acud_barrio').val(d.acud_barrio);
            $('#npt_acud_ciudad').val(d.acud_ciudad);

            $('#npt_estudio_tipo').val(d.estudio_tipo);
            $('#npt_estudio_titulo').val(d.estudio_titulo);
            $('#npt_estudio_institucion').val(d.estudio_institucion);
            $('#npt_estudio_aniofin').val(d.estudio_aniofin);

            $('#npt_jornada').val(d.jornada);
            $('#npt_fechainscripcion').val(d.fechainscripcion);

            actualizarVisibilidadPadreMadre();
            actualizarSelectAcudiente();
            $('#slct_acud_es').val(d.acud_es);
            manejarCambioAcudiente();

            $('[id^="npt_"], [id^="slct_"]').each(function () {
                marcarCampoLleno($(this));
            });

            cargarProgramas();
            // El programa se precarga después de que cargarProgramas() puebla
            // las opciones — pequeño diferido para no depender de un callback
            // adicional en este módulo de una sola pantalla.
            setTimeout(function () {
                $('#slct_finc_prog_id').val(d.prog_id);
            }, 300);

            $('#bloque_cargando').addClass('d-none');
            $('#form_actualizacion').removeClass('d-none');
        },
        error: function () {
            mostrarError('invalido');
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

    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
        .forEach(function (selector) {
            $(selector).on('input change blur', function () {
                marcarValidacion($(this));
            });
        });

    $(document).on('input change', '#form_actualizacion input, #form_actualizacion select, #form_actualizacion textarea', function () {
        marcarCampoLleno($(this));
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

    $('#npt_padr_vive, #npt_madr_vive').on('change', function () {
        actualizarVisibilidadPadreMadre();
        actualizarSelectAcudiente();
    });
    $('#slct_acud_es').on('change', manejarCambioAcudiente);

    // --- Guardar actualización ---
    $('#btn_guardar_actualizacion').click(function () {
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
            alert('Por favor complete todos los campos antes de guardar.');
            return;
        }

        if (!multiculturalidadValida) {
            alert('Por favor complete todos los campos antes de guardar.');
            return;
        }

        let data = {
            token: $('#npt_token').val(),

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
            fechainscripcion:     $('#npt_fechainscripcion').val(),
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
            estudio_aniofin:      $('#npt_estudio_aniofin').val()
        };

        $.ajax({
            type: 'POST',
            url: 'actualizar_mdl.php?accion=guardar_actualizacion',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    $('#form_actualizacion').addClass('d-none');
                    $('#bloque_confirmacion').removeClass('d-none');
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Error al procesar la actualización');
            }
        });
    });
});
