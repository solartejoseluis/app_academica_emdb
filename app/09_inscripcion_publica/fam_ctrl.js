function marcarValidacion($campo) {
    const valor = ($campo.val() || '').toString().trim();
    $campo.removeClass('is-invalid is-valid');
    $campo.addClass(valor === '' ? 'is-invalid' : 'is-valid');
}

// --- Ficha Familiar: campos siempre obligatorios vs. condicionados a Padre/Madre ---
// fechainscripcion no está en esta lista: no es un input del usuario, el backend
// siempre la setea con CURDATE() en completar_ficha.
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

function poblarFormularioFicha(d) {
    $('#npt_codigo_ficha').val($('#npt_codigo_busqueda').val().trim());
    $('#nombre_estudiante_ficha').text((d.estu_nombres || '') + ' ' + (d.estu_apellidos || ''));

    // Poblar el select de programas ANTES de asignar el valor precargado,
    // para que la opción ya exista cuando se intente seleccionar.
    $.ajax({
        type: 'POST',
        url: 'fam_mdl.php?accion=listar_programas',
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let opciones = '<option value="">-- Seleccionar --</option>';
                response.data.forEach(function (p) {
                    opciones += `<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                });
                $('#slct_finc_prog_id').html(opciones);
                $('#slct_finc_prog_id').val(d.prog_id || '');
            }
        }
    });

    $('#npt_jornada').val(d.jornada || '');
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

    actualizarVisibilidadPadreMadre();
    actualizarSelectAcudiente();
    $('#slct_acud_es').val(d.acud_es || '');
    manejarCambioAcudiente();

    $('#bloque_codigo').addClass('d-none');
    $('#bloque_ficha').removeClass('d-none');
}

function buscarCodigo() {
    let codigo = $('#npt_codigo_busqueda').val().trim();

    $('#error_codigo').addClass('d-none').text('');

    $.ajax({
        type: 'POST',
        url: 'fam_mdl.php?accion=consultar_por_codigo',
        data: { codigo: codigo },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                poblarFormularioFicha(response.data);
            } else {
                $('#error_codigo').text(response.message).removeClass('d-none');
            }
        },
        error: function () {
            $('#error_codigo').text('Error al consultar el código').removeClass('d-none');
        }
    });
}

$(document).ready(function () {

    // --- Visibilidad Padre/Madre y Acudiente dinámico ---
    $('#npt_padr_vive, #npt_madr_vive').on('change', function () {
        actualizarVisibilidadPadreMadre();
        actualizarSelectAcudiente();
    });
    $('#slct_acud_es').on('change', manejarCambioAcudiente);

    // --- Validación visual en vivo: Ficha Familiar ---
    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
        .forEach(function (selector) {
            $(selector).on('input change blur', function () {
                marcarValidacion($(this));
            });
        });

    // --- Buscar código ---
    $('#btn_buscar_codigo').click(buscarCodigo);

    // --- Autobúsqueda si el código llegó en la URL ---
    if (($('body').data('codigo-auto') || '').toString().trim() !== '') {
        buscarCodigo();
    }

    // --- Guardar datos familiares ---
    $('#btn_guardar_ficha').click(function () {
        let camposRequeridos = CAMPOS_FICHA_SIEMPRE.slice();
        if (!$('#bloque_acud_parentesco').is(':visible')) {
            camposRequeridos = camposRequeridos.filter(function (selector) {
                return selector !== '#npt_acud_parentesco';
            });
        }
        if ($('#npt_padr_vive').is(':checked')) {
            camposRequeridos = camposRequeridos.concat(CAMPOS_FICHA_PADRE);
        }
        if ($('#npt_madr_vive').is(':checked')) {
            camposRequeridos = camposRequeridos.concat(CAMPOS_FICHA_MADRE);
        }

        let primerCampoVacioFicha = null;
        camposRequeridos.forEach(function (selector) {
            let $campo = $(selector);
            marcarValidacion($campo);
            if ($campo.hasClass('is-invalid') && primerCampoVacioFicha === null) {
                primerCampoVacioFicha = $campo;
            }
        });

        if (primerCampoVacioFicha !== null) {
            primerCampoVacioFicha.focus();
            alert('Por favor complete todos los campos antes de guardar.');
            return;
        }

        let data = {
            codigo:               $('#npt_codigo_ficha').val(),
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
            estudio_aniofin:      $('#npt_estudio_aniofin').val()
        };

        $.ajax({
            type: 'POST',
            url: 'fam_mdl.php?accion=completar_ficha',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    $('#bloque_ficha').addClass('d-none');
                    $('#bloque_confirmacion_ficha').removeClass('d-none');
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Error al guardar los datos familiares');
            }
        });
    });
});
