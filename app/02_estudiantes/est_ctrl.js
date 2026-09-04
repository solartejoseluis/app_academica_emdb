// Declarado fuera de $(document).ready para que abrirEditar() (función
// global, invocada vía onclick inline) también pueda invocarla.
const CAMPOS_ESTUDIANTE = [
    '#slct_estu_tipodoc', '#npt_estu_numerodoc', '#npt_estu_nombres', '#npt_estu_apellidos',
    '#npt_fechanacimiento', '#slct_estu_sexo', '#npt_estu_telefono', '#npt_estu_email',
    '#npt_estu_ciudad', '#npt_estu_direccion', '#npt_estu_barrio', '#slct_estu_estrato', '#npt_estu_eps',
    '#npt_estu_expedidoen', '#npt_estu_ciudadnac', '#npt_estu_ocupacion',
    '#slct_estu_estadocivil', '#slct_estu_discapacidad'
];

// Última respuesta de listar_requisitos_programa — evita una llamada AJAX
// extra en abrirEditarRequisitoPrograma() (función global, invocada vía
// onclick inline) solo para precargar nombre/descripción.
let ultimoListadoRequisitosPrograma = [];

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

// --- Ficha Familiar: campos siempre obligatorios vs. condicionados a Padre/Madre ---
const CAMPOS_FICHA_SIEMPRE = [
    '#slct_finc_prog_id', '#npt_jornada', '#npt_fechainscripcion',
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

$(document).ready(function () {

    let tablaAspirantes;
    let tablaMatriculadosActual;
    let tablaMatriculadosAnteriores;
    // Resuelto por cargarPeriodos() a partir de peri_activo=1 — usado como
    // peri_id fijo (sin selector) en el ajax.data de tablaMatriculadosActual.
    let periodoActivoId = null;

    cargarTablas();
    cargarProgramas();
    cargarPeriodos();
    cargarGruposFiltro();
    cargarModulosFiltro();

    // --- Filtros de la pestaña "Matriculados (Per. Actual)": solo recargan su propia tabla ---
    $('#slct_filtro_matr_prog_id_actual, #slct_filtro_matr_grse_id_actual, #slct_filtro_matr_modu_id_actual').on('change', function () {
        tablaMatriculadosActual.ajax.reload();
    });

    // --- Filtros de la pestaña "Matriculados (Per. Anteriores)": solo recargan su propia tabla ---
    $('#slct_filtro_matr_prog_id_anteriores, #slct_filtro_matr_peri_id_anteriores, #slct_filtro_matr_grse_id_anteriores, #slct_filtro_matr_modu_id_anteriores').on('change', function () {
        tablaMatriculadosAnteriores.ajax.reload();
    });

    // Ajustar columnas al cambiar de tab (soluciona render en tab oculto)
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
    });

    // --- Validación visual en vivo: modal Nuevo Aspirante / Editar Estudiante ---
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

    // --- Fondo verde para campos ya diligenciados (Ficha de Inscripción) ---
    $(document).on('input change', '#mdl_estudiante input, #mdl_estudiante select, #mdl_estudiante textarea', function() {
        marcarCampoLleno($(this));
    });

    // --- Nuevo aspirante ---
    $('#btn_nuevo_aspirante').click(function () {
        limpiarFormulario();
        $('#mdl_estudiante_titulo').text('Ficha de Inscripción');
        $('#btn_eliminar_aspirante').addClass('d-none');
        new bootstrap.Modal(document.getElementById('mdl_estudiante')).show();
    });

    // --- Guardar estudiante + Ficha Familiar en un solo submit (Fase C2) ---
    $('#btn_guardar_estudiante').click(function () {
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

        // --- Validación de la Ficha Familiar — mismo criterio que tenía
        // btn_guardar_ficha antes de la fusión (campos siempre requeridos +
        // padre/madre condicionados a sus checkboxes "¿Vive?") ---
        let camposFichaRequeridos = CAMPOS_FICHA_SIEMPRE.slice();
        if (!$('#bloque_acud_parentesco').is(':visible')) {
            camposFichaRequeridos = camposFichaRequeridos.filter(function (selector) {
                return selector !== '#npt_acud_parentesco';
            });
        }
        if ($('#npt_padr_vive').is(':checked')) {
            camposFichaRequeridos = camposFichaRequeridos.concat(CAMPOS_FICHA_PADRE);
        }
        if ($('#npt_madr_vive').is(':checked')) {
            camposFichaRequeridos = camposFichaRequeridos.concat(CAMPOS_FICHA_MADRE);
        }

        let primerCampoVacioFicha = null;
        camposFichaRequeridos.forEach(function (selector) {
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

        let estu_id        = $('#npt_estu_id').val().trim();
        let estu_nombres   = $('#npt_estu_nombres').val().trim();
        let estu_apellidos = $('#npt_estu_apellidos').val().trim();
        let estu_numerodoc = $('#npt_estu_numerodoc').val().replace(/\./g, '');

        let data = {
            estu_id:        estu_id,
            estu_tipodoc:   $('#slct_estu_tipodoc').val(),
            estu_numerodoc: estu_numerodoc,
            estu_nombres:   estu_nombres,
            estu_apellidos: estu_apellidos,
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
            tipo_clave_estudiante:  $('input[name="tipo_clave_estudiante"]:checked').val() || '',
            clave_manual_estudiante: $('#npt_clave_manual_estudiante').val().trim(),

            // --- Ficha Familiar (AC-FO-02) ---
            prog_id:              $('#slct_finc_prog_id').val(),
            jornada:              $('#npt_jornada').val().trim(),
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
            url: 'est_mdl.php?accion=guardar_completo',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    if (estu_id === '') {
                        $('#npt_estu_id').val(response.estu_id);
                    }
                    bootstrap.Modal.getInstance(document.getElementById('mdl_estudiante')).hide();
                    tablaAspirantes.ajax.reload(null, false);
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                    let mensaje = estu_id === '' ? 'Aspirante registrado correctamente.' : 'Estudiante actualizado correctamente.';
                    if (response.clave_generada) {
                        mensaje += '\n\nClave asignada: ' + response.clave_generada + '\nAnote esta clave — no se volverá a mostrar.';
                    }
                    alert(mensaje);
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // --- Eliminar aspirante (DELETE físico, solo aspirantes) ---
    $('#btn_eliminar_aspirante').click(function () {
        let nombre = $('#npt_estu_nombres').val().trim() + ' ' + $('#npt_estu_apellidos').val().trim();
        $('#spn_nombre_eliminar_aspirante').text(nombre);
        new bootstrap.Modal(document.getElementById('mdl_confirmar_eliminar_aspirante')).show();
    });

    $('#btn_confirmar_eliminar_aspirante').click(function () {
        let estu_id = $('#npt_estu_id').val().trim();

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=eliminar_aspirante',
            data: { estu_id: estu_id },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_confirmar_eliminar_aspirante')).hide();
                    bootstrap.Modal.getInstance(document.getElementById('mdl_estudiante')).hide();
                    tablaAspirantes.ajax.reload(null, false);
                    alert('Aspirante eliminado correctamente.');
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_confirmar_eliminar_aspirante')).hide();
                    alert(response.message);
                }
            }
        });
    });

    // --- Subir foto del estudiante (petición propia, fuera del guardado general) ---
    $('#btn_subir_foto').click(function () {
        let archivo = $('#npt_foto_estudiante')[0].files[0];
        if (!archivo) {
            alert('Seleccione una imagen primero.');
            return;
        }
        let formData = new FormData();
        formData.append('estu_id', $('#npt_estu_id').val());
        formData.append('foto', archivo);
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=subir_foto',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    $('#img_preview_foto')
                        .attr('src', '../../uploads/fotos_estudiantes/' + response.foto)
                        .removeClass('d-none');
                    alert('Foto actualizada correctamente.');
                    tablaAspirantes.ajax.reload(null, false);
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Error al subir la foto.');
            }
        });
    });

    // --- Descargar Ficha de Inscripción en PDF desde el modal unificado ---
    $('#btn_ficha_inscripcion_pdf').click(function () {
        descargarFichaPdf($('#npt_estu_id').val());
    });

    // --- Descargar Hoja de Matrícula en PDF desde el modal unificado ---
    $('#btn_hoja_matricula_pdf').click(function () {
        window.open('../06_reportes/pdf_hoja_matricula.php?estu_id=' + $('#npt_estu_id').val(), '_blank');
    });

    // --- Radio tipo_acceso ---
    $('input[name="tipo_acceso"]').change(function () {
        if ($(this).val() === 'manual') {
            $('#bloque_clave_manual').removeClass('d-none');
        } else {
            $('#bloque_clave_manual').addClass('d-none');
        }
    });

    // --- Radio tipo_clave_estudiante (cambiar clave desde el modal de edición) ---
    $('input[name="tipo_clave_estudiante"]').change(function () {
        if ($(this).val() === 'manual') {
            $('#bloque_clave_manual_estudiante').removeClass('d-none');
        } else {
            $('#bloque_clave_manual_estudiante').addClass('d-none');
        }
    });

    // --- Confirmar matrícula ---
    $('#btn_confirmar_matricula').click(function () {
        let estu_id  = $('#npt_estu_id_matricular').val();
        let prog_id  = $('#slct_prog_id').val();
        let coho_id  = $('#slct_coho_id').val();
        let peri_id  = $('#slct_peri_id').val();
        let matr_semestre = $('#slct_matr_semestre').val();

        if (!prog_id) { alert('Seleccione el programa.');  return false; }
        if (!coho_id) { alert('Seleccione la cohorte.');   return false; }
        if (!peri_id) { alert('Seleccione el período.');   return false; }
        if (!matr_semestre) { alert('Seleccione el semestre.'); return false; }

        let tipo_acceso = $('input[name="tipo_acceso"]:checked').val();
        if (tipo_acceso === 'manual' && !$('#npt_clave_manual').val().trim()) {
            alert('Ingrese la clave manual.');
            return false;
        }

        let data = {
            estu_id:           estu_id,
            prog_id:           prog_id,
            peri_id:           peri_id,
            coho_id:           $('#slct_coho_id').val(),
            matr_semestre:     matr_semestre,
            matr_folio:        $('#npt_matr_folio').val().trim(),
            fechamatricula:    $('#npt_fechamatricula').val(),
            matr_observacion:  $('#npt_matr_observacion').val().trim(),
            tipo_acceso:       tipo_acceso,
            clave_manual:      $('#npt_clave_manual').val().trim()
        };

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=matricular',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    if (response.clave_generada) {
                        $('#spn_clave_valor').text(response.clave_generada);
                        $('#spn_usua_email_valor').text(response.usua_email);
                        $('#div_clave_generada').removeClass('d-none');
                        $('#btn_confirmar_matricula').addClass('d-none');
                        $('#btn_cerrar_matricula').removeClass('d-none');
                    } else {
                        bootstrap.Modal.getInstance(document.getElementById('mdl_matricular')).hide();
                        tablaAspirantes.ajax.reload(null, false);
                        if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                        alert('Matrícula completada correctamente.');
                    }
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // --- Ficha Familiar: visibilidad Padre/Madre y Acudiente dinámico ---
    $('#npt_padr_vive, #npt_madr_vive').on('change', function () {
        actualizarVisibilidadPadreMadre();
        actualizarSelectAcudiente();
    });
    $('#slct_acud_es').on('change', manejarCambioAcudiente);

    // --- Validación visual en vivo: modal Ficha Familiar ---
    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
        .forEach(function (selector) {
            $(selector).on('input change blur', function () {
                marcarValidacion($(this));
            });
        });

    // --- Cerrar y actualizar lista (tras mostrar clave) ---
    $('#btn_cerrar_matricula').click(function () {
        bootstrap.Modal.getInstance(document.getElementById('mdl_matricular')).hide();
        tablaAspirantes.ajax.reload(null, false);
        if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
        if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
    });

    // --- Reset modal matrícula al cerrar ---
    $('#mdl_matricular').on('hidden.bs.modal', function () {
        $(this).find('input[type="checkbox"]').prop('checked', false);
        $('#radio_no').prop('checked', true);
        $('#bloque_clave_manual').addClass('d-none');
        $('#div_clave_generada').addClass('d-none');
        $('#btn_cerrar_matricula').addClass('d-none');
        $('#btn_confirmar_matricula').removeClass('d-none');
        $('#npt_clave_manual').val('');
        $('#slct_coho_id').val('');
        $('#slct_prog_id').val('');
        $('#slct_peri_id').val('');
        poblarSelectSemestre('#slct_matr_semestre', 0);
        $('#npt_matr_folio').val('');
        $('#npt_fechamatricula').val('');
        $('#npt_matr_observacion').val('');
    });

    // --- Guardar cambios en Editar Matrícula ---
    $('#btn_guardar_editar_matricula').click(function () {
        const matr_id = $('#npt_matr_id_editar').val();
        const prog_id = $('#slct_editar_prog_id').val();
        const coho_id = $('#slct_editar_coho_id').val();
        const peri_id = $('#slct_editar_peri_id').val();
        const matr_semestre = $('#slct_editar_matr_semestre').val();

        if (!prog_id) { alert('Seleccione el programa.'); return false; }
        if (!coho_id) { alert('Seleccione la cohorte.');  return false; }
        if (!peri_id) { alert('Seleccione el período.');  return false; }
        if (!matr_semestre) { alert('Seleccione el semestre.'); return false; }

        const matr_folio       = $('#npt_editar_matr_folio').val().trim();
        const fechamatricula   = $('#npt_editar_fechamatricula').val();
        const matr_observacion = $('#npt_editar_matr_observacion').val().trim();
        const matr_numero      = $('#npt_editar_matr_numero').val() || null;

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=editar_matricula',
            data: {
                matr_id: matr_id,
                prog_id: prog_id,
                coho_id: coho_id,
                peri_id: peri_id,
                matr_semestre: matr_semestre,
                matr_folio: matr_folio,
                fechamatricula: fechamatricula,
                matr_observacion: matr_observacion,
                matr_numero: matr_numero
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    if (response.modulos_retirados > 0) {
                        alert('Se guardaron los cambios. Se retiraron ' + response.modulos_retirados + ' módulo(s) que ya no correspondían al nuevo programa/período.');
                    } else {
                        alert('Cambios guardados.');
                    }
                    bootstrap.Modal.getInstance(document.getElementById('mdl_editar_matricula')).hide();
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload();
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    $('#btn_confirmar_avanzar_semestre').click(function () {
        const matr_id         = $('#npt_avanzar_matr_id').val();
        const peri_id_destino = $('#slct_avanzar_peri_id_destino').val();

        if (!peri_id_destino) { alert('Seleccione el período destino.'); return false; }

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=avanzar_semestre',
            data: { matr_id: matr_id, peri_id_destino: peri_id_destino },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_avanzar_semestre')).hide();
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload();
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload();
                    alert(response.message);
                } else {
                    // Sin cerrar el modal — el coordinador necesita ver el
                    // motivo del rechazo (mismo patrón que otros submits del
                    // módulo, ej. btn_guardar_editar_matricula arriba).
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    // --- Link de actualización de datos (Fase 4 de 5) ---

    $('#btn_generar_link_actualizacion').click(function () {
        const estu_id = $('#npt_actualizacion_estu_id').val();
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=generar_link_actualizacion',
            data: { estu_id: estu_id },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    $('#npt_link_generado').val(window.location.origin + response.url);
                    $('#bloque_link_generado').removeClass('d-none');
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                } else {
                    alert(response.message);
                }
            }
        });
    });

    $('#btn_copiar_link').click(function () {
        const link = $('#npt_link_generado').val();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function () {
                alert('Link copiado al portapapeles.');
            }, function () {
                alert('No se pudo copiar automáticamente. Copia el link manualmente.');
            });
        } else {
            // Fallback para navegadores sin soporte de Clipboard API (ej. sin
            // contexto seguro/HTTPS) — selecciona el texto y usa execCommand.
            $('#npt_link_generado')[0].select();
            try {
                document.execCommand('copy');
                alert('Link copiado al portapapeles.');
            } catch (e) {
                alert('No se pudo copiar automáticamente. Copia el link manualmente.');
            }
        }
    });

    $('#btn_ver_aprobar_actualizacion').click(function () {
        const estu_id = $('#npt_actualizacion_estu_id').val();
        const soac_id = $('#npt_actualizacion_soac_id').val();
        bootstrap.Modal.getInstance(document.getElementById('mdl_actualizacion_datos')).hide();
        abrirRevisionActualizacion(estu_id, soac_id);
    });

    $('#btn_aprobar_actualizacion').click(function () {
        const soac_id = $('#npt_revision_soac_id').val();
        if (!confirm('¿Aprobar esta actualización? Los datos del estudiante se reemplazarán con los valores propuestos resaltados en amarillo.')) return;
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=aprobar_actualizacion',
            data: { soac_id: soac_id },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_estudiante')).hide();
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                    const campos = response.campos_aplicados || [];
                    alert(campos.length > 0
                        ? 'Actualización aprobada. Campos aplicados: ' + campos.join(', ')
                        : 'Actualización aprobada (sin cambios respecto a los datos vigentes).');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    $('#btn_descartar_actualizacion').click(function () {
        const soac_id = $('#npt_revision_soac_id').val();
        if (!confirm('¿Descartar esta actualización? Los datos propuestos por el estudiante se perderán.')) return;
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=descartar_actualizacion',
            data: { soac_id: soac_id },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_estudiante')).hide();
                    if (tablaMatriculadosActual) tablaMatriculadosActual.ajax.reload(null, false);
                    if (tablaMatriculadosAnteriores) tablaMatriculadosAnteriores.ajax.reload(null, false);
                    alert('Actualización descartada.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // Restaura el modo normal si el modal se cierra estando en modo revisión
    // (ej. el coordinador aprueba/descarta, o cierra con la X/Cancelar sin
    // decidir) — sin esto, la próxima apertura normal (abrirEditar) heredaría
    // campos deshabilitados y botones equivocados hasta que restaurarModoNormalEstudiante()
    // se llamara de nuevo ahí (ya se llama también, esto es la otra mitad).
    $('#mdl_estudiante').on('hidden.bs.modal', function () {
        restaurarModoNormalEstudiante();
    });

    // -------------------------------------------------------------------------
    // Funciones internas
    // -------------------------------------------------------------------------

    function cargarTablas() {
        tablaAspirantes = $('#tbl_aspirantes').DataTable({
            ajax: {
                url: 'est_mdl.php?accion=listar_aspirantes',
                type: 'POST',
                dataSrc: 'data'
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: [
                {
                    data: 'estu_foto',
                    orderable: false,
                    width: '50px',
                    render: function (data, type, row) {
                        let src = data
                            ? '../../uploads/fotos_estudiantes/' + data
                            : 'https://via.placeholder.com/40x50?text=%20';
                        return `<img src="${src}" style="width:40px;height:50px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    width: '40px',
                    render: function (data, type, row, meta) { return meta.row + 1; }
                },
                { data: 'estu_nombres' },
                { data: 'estu_apellidos' },
                {
                    data: null,
                    render: function (data, type, row) {
                        let tipo = row.estu_tipodoc ? row.estu_tipodoc + ' ' : '';
                        return tipo + (row.estu_numerodoc || '');
                    }
                },
                { data: 'estu_telefono', defaultContent: '—' },
                { data: 'fechacreacion', width: '130px' },
                { data: 'estu_origen', render: function (data) {
                    let map = { 'manual': 'bg-secondary', 'web': 'bg-info text-dark' };
                    let label = { 'manual': 'Manual', 'web': 'Web' };
                    let cls = map[data] || 'bg-secondary';
                    return `<span class="badge ${cls}">${label[data] || data}</span>`;
                }},
                {
                    data: null,
                    orderable: false,
                    width: '70px',
                    render: function (data, type, row) {
                        const mapaColorFicha = { sin_iniciar: '#dc3545', incompleta: '#ffc107', completa: '#28a745' };
                        const mapaTituloFicha = { sin_iniciar: 'Ficha sin iniciar', incompleta: 'Ficha incompleta', completa: 'Ficha completa' };
                        const colorFicha = mapaColorFicha[row.estado_ficha] || '#dc3545';
                        const tituloFicha = mapaTituloFicha[row.estado_ficha] || 'Ficha sin iniciar';

                        return `<div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" type="button" onclick="abrirMatricular(${row.estu_id})">Matricular</button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" type="button" onclick="abrirEditar(${row.estu_id}, true)" title="${tituloFicha}">
                                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background-color:${colorFicha};margin-right:4px;"></span>📝 Datos Estudiante
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" type="button" onclick="window.open('../06_reportes/pdf_ficha.php?estu_id=' + ${row.estu_id}, '_blank')" title="Descargar Ficha de Inscripción (AC-FO-02)">🖨️ Ficha de Inscripción</button>
                                        </li>
                                    </ul>
                                </div>`;
                    }
                }
            ]
        });

    }

    // Separada de cargarTablas() para que la construcción de estas 2 tablas
    // ocurra solo después de que cargarPeriodos() resuelva periodoActivoId y
    // preseleccione #slct_filtro_matr_peri_id_anteriores — antes vivían
    // dentro de cargarTablas() y disparaban un auto-load prematuro con el
    // filtro de período sin resolver, superpuesto con el reload posterior
    // de cargarPeriodos() (causaba filas duplicadas en el DataTable
    // renderizado, ver diagnóstico previo).
    function cargarTablasMatriculados() {
        tablaMatriculadosActual = $('#tbl_matriculados_actual').DataTable({
            ajax: {
                url: 'est_mdl.php?accion=listar_matriculados',
                type: 'POST',
                dataSrc: 'data',
                data: function (d) {
                    d.prog_id = $('#slct_filtro_matr_prog_id_actual').val();
                    d.peri_id = periodoActivoId;
                    d.grse_id = $('#slct_filtro_matr_grse_id_actual').val();
                    d.modu_id = $('#slct_filtro_matr_modu_id_actual').val();
                }
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: crearColumnasMatriculados(true)
        });

        tablaMatriculadosAnteriores = $('#tbl_matriculados_anteriores').DataTable({
            ajax: {
                url: 'est_mdl.php?accion=listar_matriculados',
                type: 'POST',
                dataSrc: 'data',
                data: function (d) {
                    d.prog_id = $('#slct_filtro_matr_prog_id_anteriores').val();
                    d.peri_id = $('#slct_filtro_matr_peri_id_anteriores').val();
                    d.grse_id = $('#slct_filtro_matr_grse_id_anteriores').val();
                    d.modu_id = $('#slct_filtro_matr_modu_id_anteriores').val();
                }
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: crearColumnasMatriculados(false)
        });
    }

    function cargarProgramas() {
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=listar_programas',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let opciones = '';
                    response.data.forEach(function (p) {
                        opciones += `<option value="${p.prog_id}" data-duracion="${p.prog_duracion_semestres}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                    });
                    $('#slct_prog_id, #slct_finc_prog_id, #slct_filtro_matr_prog_id_actual, #slct_filtro_matr_prog_id_anteriores, #slct_editar_prog_id').append(opciones);
                }
            }
        });
    }

    function cargarPeriodos() {
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=listar_periodos',
            dataType: 'json',
            success: function (response) {
                if (response.status !== 'ok') return;

                // #slct_peri_id (modal Completar Matrícula) y #slct_editar_peri_id
                // (modal Editar Matrícula) siguen recibiendo TODOS los períodos —
                // no tienen relación con las pestañas Actual/Anteriores.
                let opciones = '';
                response.data.forEach(function (p) {
                    opciones += `<option value="${p.peri_id}">${p.peri_codigo}</option>`;
                });
                $('#slct_peri_id').append(opciones);
                $('#slct_editar_peri_id').append(opciones);

                // Pestaña "Per. Actual": sin selector — solo texto de referencia
                // con el período activo (peri_activo=1). Mismo patrón de
                // filtrado client-side por el flag que doc_ctrl.js (activoId).
                const periodoActivo = response.data.find(function (p) { return p.peri_activo == 1; });
                periodoActivoId = periodoActivo ? periodoActivo.peri_id : null;
                $('#txt_periodo_activo').val(periodoActivo ? periodoActivo.peri_codigo : 'Sin período activo');

                // Pestaña "Per. Anteriores": select con TODOS los períodos
                // EXCEPTO el activo — sin opción "Todos" (enviar peri_id vacío
                // haría que listar_matriculados no filtre por período y el
                // activo se colaría en esta pestaña). Se preselecciona el más
                // reciente de los anteriores para que la tabla nunca quede sin
                // peri_id explícito.
                const periodosAnteriores = response.data.filter(function (p) { return p.peri_activo != 1; });
                if (periodosAnteriores.length > 0) {
                    let opcionesAnteriores = '';
                    periodosAnteriores.forEach(function (p) {
                        opcionesAnteriores += `<option value="${p.peri_id}">${p.peri_codigo}</option>`;
                    });
                    $('#slct_filtro_matr_peri_id_anteriores').html(opcionesAnteriores).val(periodosAnteriores[0].peri_id);
                } else {
                    // Sin períodos anteriores todavía (ej. instalación nueva,
                    // un solo período creado hasta ahora): value="0" no
                    // coincide con ningún peri_id real — listar_matriculados
                    // filtra por él y devuelve 0 filas, en vez de caer en el
                    // caso "sin filtro" (que mostraría de más, el período
                    // activo incluido).
                    $('#slct_filtro_matr_peri_id_anteriores').html('<option value="0">-- Sin períodos anteriores --</option>');
                }

                // Ambos selectores de período ya tienen su valor definitivo —
                // recién ahora tiene sentido cargar las dos tablas de
                // Matriculados (antes de este punto, periodoActivoId es null y
                // el select de Anteriores está vacío).
                cargarTablasMatriculados();
            }
        });
    }

    function cargarGruposFiltro() {
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=listar_grupos_filtro',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let opciones = '';
                    response.data.forEach(function (g) {
                        opciones += `<option value="${g.grse_id}">${g.grse_codigo}</option>`;
                    });
                    $('#slct_filtro_matr_grse_id_actual, #slct_filtro_matr_grse_id_anteriores').append(opciones);
                }
            }
        });
    }

    function cargarModulosFiltro() {
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=listar_modulos_filtro',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let opciones = '';
                    response.data.forEach(function (m) {
                        opciones += `<option value="${m.modu_id}">${m.modu_sigla} — ${m.modu_nombre}</option>`;
                    });
                    $('#slct_filtro_matr_modu_id_actual, #slct_filtro_matr_modu_id_anteriores').append(opciones);
                }
            }
        });
    }

    $('#slct_prog_id').on('change', function () {
        const prog_id = $(this).val();
        $('#slct_coho_id').html('<option value="">-- Seleccionar --</option>');
        if (prog_id) {
            $('#slct_coho_id').prop('disabled', false);
            cargarCohortesPorPrograma(prog_id);
            poblarSelectSemestre('#slct_matr_semestre', $(this).find('option:selected').data('duracion'));
        } else {
            $('#slct_coho_id').prop('disabled', true).html('<option value="">-- Primero seleccione un programa --</option>');
            poblarSelectSemestre('#slct_matr_semestre', 0);
        }
    });

    $('#slct_editar_prog_id').on('change', function () {
        const prog_id = $(this).val();
        $('#slct_editar_coho_id').html('<option value="">-- Seleccionar --</option>');
        if (prog_id) {
            $('#slct_editar_coho_id').prop('disabled', false);
            cargarCohortesPorPrograma(prog_id, '#slct_editar_coho_id');
            poblarSelectSemestre('#slct_editar_matr_semestre', $(this).find('option:selected').data('duracion'));
        } else {
            $('#slct_editar_coho_id').prop('disabled', true).html('<option value="">-- Primero seleccione un programa --</option>');
            poblarSelectSemestre('#slct_editar_matr_semestre', 0);
        }
    });

    // --- Pestaña "Configurar Requisitos" (solo Admin) — Etapa 2.12.E2 ---
    $('#slct_prog_config_requisitos').on('change', function () {
        const prog_id = $(this).val();
        if (!prog_id) {
            $('#tbl_requisitos_programa').addClass('d-none');
            $('#bloque_sin_programa')
                .text('Selecciona un programa para ver o configurar sus requisitos.')
                .removeClass('d-none');
            $('#btn_agregar_requisito').prop('disabled', true);
            return;
        }
        // data-attribute del botón (mismo estilo que data-duracion en las
        // <option> de programa) — lo usará #btn_guardar_requisito_programa
        // en la Etapa 2.12.E3 para saber a qué prog_id atar un requisito nuevo.
        $('#btn_agregar_requisito').prop('disabled', false).attr('data-prog-id', prog_id);
        cargarTablaRequisitosPrograma(prog_id);
    });

    $('#btn_agregar_requisito').click(function () {
        const prog_id = $(this).attr('data-prog-id');
        $('#npt_reqp_id').val('');
        $('#npt_reqp_nombre').val('').removeClass('is-invalid');
        $('#npt_reqp_descripcion').val('');
        $('#npt_reqp_prog_id_modal').val(prog_id);
        $('#mdl_requisito_programa_titulo').text('Agregar Requisito');
        new bootstrap.Modal(document.getElementById('mdl_requisito_programa')).show();
    });

    $('#btn_guardar_requisito_programa').click(function () {
        const reqp_id = $('#npt_reqp_id').val();
        const prog_id = $('#npt_reqp_prog_id_modal').val().trim();
        const reqp_nombre = $('#npt_reqp_nombre').val().trim();
        const reqp_descripcion = $('#npt_reqp_descripcion').val().trim();

        if (reqp_nombre === '') {
            $('#npt_reqp_nombre').addClass('is-invalid');
            return;
        }
        $('#npt_reqp_nombre').removeClass('is-invalid');

        const esEdicion = reqp_id !== '';
        const accion = esEdicion ? 'editar_requisito_programa' : 'crear_requisito_programa';
        const datos = esEdicion
            ? { reqp_id: reqp_id, reqp_nombre: reqp_nombre, reqp_descripcion: reqp_descripcion }
            : { prog_id: prog_id, reqp_nombre: reqp_nombre, reqp_descripcion: reqp_descripcion };

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=' + accion,
            data: datos,
            dataType: 'json',
            success: function (response) {
                if (response.status !== 'ok') {
                    alert('Error: ' + response.message);
                    return;
                }
                bootstrap.Modal.getInstance(document.getElementById('mdl_requisito_programa')).hide();
                cargarTablaRequisitosPrograma($('#slct_prog_config_requisitos').val());
            }
        });
    });

    // --- Reset modal Agregar/Editar Requisito al cerrar (X/Cancelar) ---
    $('#mdl_requisito_programa').on('hidden.bs.modal', function () {
        $('#npt_reqp_id').val('');
        $('#npt_reqp_nombre').val('').removeClass('is-invalid');
        $('#npt_reqp_descripcion').val('');
        $('#npt_reqp_prog_id_modal').val('');
    });

    function limpiarFormulario() {
        $('#npt_estu_id').val('');
        $('#npt_estu_usua_id_actual').val('');
        $('input[name="tipo_clave_estudiante"]').prop('checked', false);
        $('#npt_clave_manual_estudiante').val('');
        $('#bloque_clave_manual_estudiante').addClass('d-none');
        $('#bloque_cambiar_clave_estudiante').addClass('d-none');
        $('#slct_estu_tipodoc').val('');
        $('#npt_estu_numerodoc').val('');
        $('#npt_estu_nombres').val('');
        $('#npt_estu_apellidos').val('');
        $('#npt_fechanacimiento').val('');
        $('#slct_estu_sexo').val('');
        $('#npt_estu_telefono').val('');
        $('#npt_estu_email').val('');
        $('#npt_estu_ciudad').val('');
        $('#npt_estu_direccion').val('');
        $('#npt_estu_barrio').val('');
        $('#slct_estu_estrato').val('');
        $('#npt_estu_eps').val('');
        $('#npt_estu_expedidoen').val('');
        $('#npt_estu_ciudadnac').val('');
        $('#npt_estu_ocupacion').val('');
        $('#slct_estu_estadocivil').val('');
        $('#slct_estu_discapacidad').val('9) NO APLICA');

        $('.chk-multicultural').prop('checked', false);
        $('#chk_multi_no_aplica').prop('checked', true);
        $('#lbl_multiculturalidad').removeClass('text-danger');

        CAMPOS_ESTUDIANTE.forEach(function (selector) {
            $(selector).removeClass('is-invalid is-valid');
        });

        $('#bloque_foto_estudiante').addClass('d-none');
        $('#img_preview_foto').attr('src', '').addClass('d-none');
        $('#npt_foto_estudiante').val('');

        // Ficha Familiar (AC-FO-02) — mismo modal unificado desde la Fase C2.
        limpiarFormularioFicha();
        $('#btn_ficha_inscripcion_pdf').addClass('d-none');
        $('#btn_hoja_matricula_pdf').addClass('d-none');
    }

    function obtenerMulticulturalidad() {
        let seleccionadas = [];
        $('.chk-multicultural:checked').each(function () {
            seleccionadas.push($(this).data('valor'));
        });
        return seleccionadas.join(',');
    }

    cargarProgramasConfigRequisitos();
});

// Fuera de ready — usada por tablaMatriculadosActual y tablaMatriculadosAnteriores
// (mismas columnas, mismo render, dos tabs distintos — ver DIAGNÓSTICO Pestañas
// Per. Actual/Per. Anteriores). Devuelve un array NUEVO en cada llamada: el
// array de columns de un DataTable no debe compartirse por referencia entre
// dos instancias.
// esPeriodoActual distingue únicamente el ítem "Matricular al sgte. sem."
// del dropdown de Acciones (solo tiene sentido avanzar desde el período EN
// CURSO) — las demás 12 columnas son idénticas entre ambas tablas, por eso
// se mantiene un solo parámetro puntual en vez de duplicar la función.
function crearColumnasMatriculados(esPeriodoActual) {
    return [
        {
            data: 'estu_foto',
            orderable: false,
            width: '50px',
            render: function (data, type, row) {
                let src = data
                    ? '../../uploads/fotos_estudiantes/' + data
                    : 'https://via.placeholder.com/40x50?text=%20';
                return `<img src="${src}" style="width:40px;height:50px;object-fit:cover;border-radius:4px;">`;
            }
        },
        {
            data: null,
            orderable: false,
            width: '40px',
            render: function (data, type, row, meta) { return meta.row + 1; }
        },
        { data: 'estu_nombres' },
        { data: 'estu_apellidos' },
        {
            data: null,
            render: function (data, type, row) {
                let tipo = row.estu_tipodoc ? row.estu_tipodoc + ' ' : '';
                return tipo + (row.estu_numerodoc || '');
            }
        },
        {
            data: 'fechanacimiento',
            width: '100px',
            render: function (data) {
                if (!data) return '—';

                const mesesAbrev = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                const nacimiento = new Date(data + 'T00:00:00');
                const hoy = new Date();

                let edad = hoy.getFullYear() - nacimiento.getFullYear();
                const noHaCumplidoAnioAun =
                    hoy.getMonth() < nacimiento.getMonth() ||
                    (hoy.getMonth() === nacimiento.getMonth() && hoy.getDate() < nacimiento.getDate());
                if (noHaCumplidoAnioAun) edad--;

                const cumple = nacimiento.getDate() + ' ' + mesesAbrev[nacimiento.getMonth()];
                const texto = `${edad} (${cumple})`;

                return edad < 18
                    ? `<span style="background-color:#cfe2ff;padding:2px 6px;border-radius:4px;">${texto}</span>`
                    : texto;
            }
        },
        {
            data: null,
            render: function (data, type, row) {
                let badges = '';
                if (row.total_matriculas_estudiante > 1) {
                    const detalle = (row.detalle_matriculas_estudiante || '').replace(/"/g, '&quot;');
                    badges += `<span class="badge bg-info text-dark" title="${detalle}">🎓 ${row.total_matriculas_estudiante} programas</span> `;
                }
                if (row.soac_estado_activo === 'generado') {
                    badges += `<span class="badge bg-warning text-dark" title="Link enviado, esperando respuesta del estudiante">🔔 Link enviado</span> `;
                } else if (row.soac_estado_activo === 'recibido') {
                    badges += `<span class="badge bg-danger" title="Datos recibidos, pendiente de aprobación">🔔 Pendiente aprobación</span> `;
                }
                if (row.soac_fecha_ultima_aprobacion) {
                    const partesFecha = row.soac_fecha_ultima_aprobacion.split(' ')[0].split('-');
                    const fechaFmt = partesFecha[2] + '/' + partesFecha[1] + '/' + partesFecha[0];
                    badges += `<span class="badge bg-success" title="Última actualización aprobada: ${row.soac_fecha_ultima_aprobacion}">✅ Actualizado ${fechaFmt}</span> `;
                }
                return badges.trim() !== '' ? badges.trim() : '—';
            }
        },
        {
            data: null,
            title: 'Requisitos',
            render: function (data, type, row) {
                if (type !== 'display') return '';
                const total = row.requisitos_total || 0;
                if (total === 0) {
                    return '—';
                }
                const entregados = row.requisitos_entregados || 0;
                const completo = entregados === total;
                const claseBadge = completo ? 'bg-success' : 'bg-warning text-dark';
                let html = `<span class="badge ${claseBadge}">${entregados}/${total}</span>`;
                if (row.requisitos_ultima_actualizacion) {
                    // Mismo patrón inline ya usado en cargarTablaRequisitosPrograma()
                    // (Etapa 2.12.E2) — split ' '/'-' y reordenar a dd/mm/aaaa,
                    // sin función compartida extraída en el archivo.
                    const partes = row.requisitos_ultima_actualizacion.split(' ')[0].split('-');
                    const fechaFmt = partes[2] + '/' + partes[1] + '/' + partes[0];
                    html += ` <small class="text-muted d-block">${fechaFmt}</small>`;
                }
                return html;
            }
        },
        { data: 'coho_codigo', render: v => v || '—' },
        { data: 'prog_sigla', width: '70px' },
        {
            data: null,
            width: '60px',
            render: function (data, type, row) {
                if (row.matr_semestre == null || row.prog_duracion_semestres == null) return '—';
                const texto = `${row.matr_semestre}/${row.prog_duracion_semestres}`;
                return (row.matr_semestre == row.prog_duracion_semestres)
                    ? `<span style="background-color:#cfe2ff;padding:2px 6px;border-radius:4px;">${texto}</span>`
                    : texto;
            }
        },
        { data: 'peri_codigo', width: '80px' },
        {
            data: 'total_modulos',
            orderable: false,
            width: '90px',
            render: function (data, type, row) {
                return `<button class="btn btn-sm btn-outline-secondary" onclick="verModulosEstudiante(${row.estu_id}, '${(row.estu_nombres + ' ' + row.estu_apellidos).replace(/'/g, "\\'")}', ${row.matr_prog_id}, ${row.peri_id})">${data}</button>`;
            }
        },
        {
            data: 'matr_estado',
            width: '100px',
            render: function (data) {
                let map = {
                    'matriculado': 'bg-success',
                    'aspirante':   'bg-warning text-dark',
                    'retirado':    'bg-danger',
                    'graduado':    'bg-primary'
                };
                let cls = map[data] || 'bg-secondary';
                return `<span class="badge ${cls}">${data}</span>`;
            }
        },
        { data: 'ultimo_acceso_fmt', width: '120px' },
        {
            data: null,
            orderable: false,
            width: '70px',
            render: function (data, type, row) {
                const mapaColorFicha = { sin_iniciar: '#dc3545', incompleta: '#ffc107', completa: '#28a745' };
                const mapaTituloFicha = { sin_iniciar: 'Ficha sin iniciar', incompleta: 'Ficha incompleta', completa: 'Ficha completa' };
                const colorFicha = mapaColorFicha[row.estado_ficha] || '#dc3545';
                const tituloFicha = mapaTituloFicha[row.estado_ficha] || 'Ficha sin iniciar';

                const sinProgramasDisponibles = row.programas_matriculados_activos >= row.programas_activos_totales;
                const itemMatricularOtroPrograma = sinProgramasDisponibles
                    ? `<li tabindex="0" title="Este estudiante ya está matriculado en todos los programas activos disponibles.">
                           <button class="dropdown-item disabled" type="button" disabled>➕ Matricular en otro programa</button>
                       </li>`
                    : `<li>
                           <button class="dropdown-item" type="button" onclick="abrirMatricular(${row.estu_id})" title="Matricular en un programa adicional">➕ Matricular en otro programa</button>
                       </li>`;

                // Solo tiene sentido avanzar desde el período EN CURSO — nunca
                // en "Per. Anteriores" (esPeriodoActual === false). El primer
                // motivo de bloqueo que aplique es el que se muestra en el
                // tooltip, mismo patrón que itemMatricularOtroPrograma arriba.
                let itemAvanzarSemestre = '';
                if (esPeriodoActual) {
                    let motivoDeshabilitado = null;
                    if (row.matr_estado !== 'matriculado') {
                        motivoDeshabilitado = 'Esta matrícula ya no está activa.';
                    } else if (row.matr_estado_academico !== 'Activo') {
                        motivoDeshabilitado = 'El estudiante no está en condición académica Activa.';
                    } else if (row.matr_semestre >= row.prog_duracion_semestres) {
                        motivoDeshabilitado = 'El estudiante ya está en su último semestre.';
                    } else if (row.aprobado_periodo_actual != 1) {
                        motivoDeshabilitado = 'El estudiante aún no ha aprobado el período actual.';
                    }

                    const nombreCompleto = (row.estu_nombres + ' ' + row.estu_apellidos).replace(/'/g, "\\'");

                    itemAvanzarSemestre = motivoDeshabilitado
                        ? `<li tabindex="0" title="${motivoDeshabilitado}">
                               <button class="dropdown-item disabled" type="button" disabled>⏩ Matricular al sgte. sem.</button>
                           </li>`
                        : `<li>
                               <button class="dropdown-item" type="button" onclick="abrirAvanzarSemestre(${row.matr_id}, '${nombreCompleto}', ${row.matr_semestre}, ${row.prog_duracion_semestres}, '${row.prog_sigla}')">⏩ Matricular al sgte. sem.</button>
                           </li>`;
                }

                // Igual que "Matricular al sgte. sem." — solo tiene sentido
                // generar un link para el período EN CURSO. matr_estado ya
                // viene garantizado 'matriculado' por el WHERE de
                // listar_matriculados (igual que "Hoja de Matrícula" no
                // revalida ese dato en frontend) — aquí solo hace falta
                // revisar matr_estado_academico, que sí puede variar.
                let itemActualizacionDatos = '';
                if (esPeriodoActual) {
                    itemActualizacionDatos = (row.matr_estado_academico !== 'Activo')
                        ? `<li tabindex="0" title="El estudiante no está en condición académica Activa.">
                               <button class="dropdown-item disabled" type="button" disabled>🔗 Generar link actualizar datos</button>
                           </li>`
                        : `<li>
                               <button class="dropdown-item" type="button" onclick="abrirActualizacionDatos(${row.estu_id})">🔗 Generar link actualizar datos</button>
                           </li>`;
                }

                return `<div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                <i class="bi bi-gear-fill"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <button class="dropdown-item" type="button" onclick="abrirEditar(${row.estu_id}, false)" title="${tituloFicha}">
                                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background-color:${colorFicha};margin-right:4px;"></span>📝 Datos Estudiante
                                    </button>
                                </li>
                                ${itemActualizacionDatos}
                                <li>
                                    <button class="dropdown-item" type="button" onclick="abrirEditarMatricula(${row.matr_id})" title="Editar programa, cohorte o período">✏️ Matrícula</button>
                                </li>
                                ${itemAvanzarSemestre}
                                <li>
                                    <button class="dropdown-item" type="button" onclick="window.open('../06_reportes/pdf_hoja_matricula.php?estu_id=' + ${row.estu_id}, '_blank')" title="Descargar Hoja de Matrícula (AC-FO-09)">🖨️ Hoja de Matrícula</button>
                                </li>
                                ${itemMatricularOtroPrograma}
                            </ul>
                        </div>`;
            }
        }
    ];
}

// ── Link de actualización de datos (Fase 4 de 5) ────────────────────────
// Mapeo columna_soac -> selector del campo correspondiente en #mdl_estudiante
// — espejo en JS del mismo mapeo ya usado server-side en
// est_mdl.php?accion=aprobar_actualizacion (Fase 2). soac_fechanacimiento es
// la única sin prefijo estu_ (la columna original tampoco lo lleva).
// soac_estu_multiculturalidad, soac_ficha_padr_vive, soac_ficha_madr_vive y
// soac_ficha_acud_es se manejan aparte en poblarModalRevision() por no ser
// un simple .val().
const MAPA_REVISION_ESTUDIANTE = [
    ['soac_estu_expedidoen', '#npt_estu_expedidoen'],
    ['soac_estu_nombres', '#npt_estu_nombres'],
    ['soac_estu_apellidos', '#npt_estu_apellidos'],
    ['soac_estu_ciudadnac', '#npt_estu_ciudadnac'],
    ['soac_fechanacimiento', '#npt_fechanacimiento'],
    ['soac_estu_sexo', '#slct_estu_sexo'],
    ['soac_estu_telefono', '#npt_estu_telefono'],
    ['soac_estu_email', '#npt_estu_email'],
    ['soac_estu_ocupacion', '#npt_estu_ocupacion'],
    ['soac_estu_direccion', '#npt_estu_direccion'],
    ['soac_estu_barrio', '#npt_estu_barrio'],
    ['soac_estu_ciudad', '#npt_estu_ciudad'],
    ['soac_estu_estrato', '#slct_estu_estrato'],
    ['soac_estu_estadocivil', '#slct_estu_estadocivil'],
    ['soac_estu_eps', '#npt_estu_eps'],
    ['soac_estu_discapacidad', '#slct_estu_discapacidad']
];
const MAPA_REVISION_FICHA = [
    ['soac_ficha_prog_id', '#slct_finc_prog_id'],
    ['soac_ficha_jornada', '#npt_jornada'],
    ['soac_ficha_fechainscripcion', '#npt_fechainscripcion'],
    ['soac_ficha_padr_nombres', '#npt_padr_nombres'],
    ['soac_ficha_padr_apellidos', '#npt_padr_apellidos'],
    ['soac_ficha_padr_profesion', '#npt_padr_profesion'],
    ['soac_ficha_padr_empresa', '#npt_padr_empresa'],
    ['soac_ficha_padr_telefono', '#npt_padr_telefono'],
    ['soac_ficha_padr_direccion', '#npt_padr_direccion'],
    ['soac_ficha_padr_barrio', '#npt_padr_barrio'],
    ['soac_ficha_padr_ciudad', '#npt_padr_ciudad'],
    ['soac_ficha_madr_nombres', '#npt_madr_nombres'],
    ['soac_ficha_madr_apellidos', '#npt_madr_apellidos'],
    ['soac_ficha_madr_profesion', '#npt_madr_profesion'],
    ['soac_ficha_madr_empresa', '#npt_madr_empresa'],
    ['soac_ficha_madr_telefono', '#npt_madr_telefono'],
    ['soac_ficha_madr_direccion', '#npt_madr_direccion'],
    ['soac_ficha_madr_barrio', '#npt_madr_barrio'],
    ['soac_ficha_madr_ciudad', '#npt_madr_ciudad'],
    ['soac_ficha_acud_parentesco', '#npt_acud_parentesco'],
    ['soac_ficha_acud_nombres', '#npt_acud_nombres'],
    ['soac_ficha_acud_apellidos', '#npt_acud_apellidos'],
    ['soac_ficha_acud_profesion', '#npt_acud_profesion'],
    ['soac_ficha_acud_empresa', '#npt_acud_empresa'],
    ['soac_ficha_acud_telefono', '#npt_acud_telefono'],
    ['soac_ficha_acud_direccion', '#npt_acud_direccion'],
    ['soac_ficha_acud_barrio', '#npt_acud_barrio'],
    ['soac_ficha_acud_ciudad', '#npt_acud_ciudad'],
    ['soac_ficha_estudio_tipo', '#npt_estudio_tipo'],
    ['soac_ficha_estudio_titulo', '#npt_estudio_titulo'],
    ['soac_ficha_estudio_institucion', '#npt_estudio_institucion'],
    ['soac_ficha_estudio_aniofin', '#npt_estudio_aniofin']
];

// Abre el modal de "Actualización de datos" — Modo 1 (ver y aprobar) si la
// solicitud más reciente del estudiante está 'recibido', Modo 2 (generar
// link) en cualquier otro caso (sin solicitud, o la más reciente ya resuelta).
function abrirActualizacionDatos(estu_id) {
    $('#npt_actualizacion_estu_id').val(estu_id);
    $('#npt_actualizacion_soac_id').val('');
    $('#txt_ultima_actualizacion').text('—');
    $('#npt_link_generado').val('');
    $('#bloque_link_generado').addClass('d-none');
    $('#bloque_generar_link').addClass('d-none');
    $('#bloque_ver_aprobar').addClass('d-none');

    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=estado_solicitud_actualizacion',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('No se pudo consultar el estado de la solicitud.');
                return;
            }
            const s = response.data;

            // "Última fecha de actualización" — solo se muestra cuando la
            // solicitud MÁS RECIENTE está 'aprobado'. Si la más reciente es
            // 'generado'/'recibido'/'descartado' se muestra '—', aunque
            // exista una aprobación más antigua en el historial — decisión
            // de alcance de la Fase 4, ver CHANGELOG.md.
            if (s && s.soac_estado === 'aprobado' && s.soac_resuelto_en) {
                $('#txt_ultima_actualizacion').text(s.soac_resuelto_en);
            }

            if (s && s.soac_estado === 'recibido') {
                $('#npt_actualizacion_soac_id').val(s.soac_id);
                $('#bloque_ver_aprobar').removeClass('d-none');
            } else {
                $('#bloque_generar_link').removeClass('d-none');
            }

            new bootstrap.Modal(document.getElementById('mdl_actualizacion_datos')).show();
        },
        error: function () {
            alert('Error al consultar el estado de la solicitud.');
        }
    });
}

// Carga el estudiante (valores vigentes) + la solicitud 'recibido' y llama a
// poblarModalRevision() — separado de abrirActualizacionDatos() porque este
// segundo llamado necesita ambas respuestas juntas antes de pintar el modal.
function abrirRevisionActualizacion(estu_id, soac_id) {
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener_completo',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (responseEstu) {
            if (responseEstu.status !== 'ok') {
                alert('No se pudo cargar el estudiante.');
                return;
            }
            $.ajax({
                type: 'POST',
                url: 'est_mdl.php?accion=estado_solicitud_actualizacion',
                data: { estu_id: estu_id },
                dataType: 'json',
                success: function (responseSoac) {
                    if (responseSoac.status !== 'ok' || !responseSoac.data || responseSoac.data.soac_estado !== 'recibido') {
                        alert('Esta solicitud ya no está disponible para revisión.');
                        return;
                    }
                    poblarModalRevision(responseEstu.data, responseSoac.data);
                }
            });
        }
    });
}

// Puebla #mdl_estudiante en modo revisión: valores vigentes como base,
// sobrescritos por los propuestos (soac_*) no nulos con resaltado
// .campo-actualizado. Formulario deshabilitado — solo lectura, con Aprobar/
// Descartar en vez de Guardar.
function poblarModalRevision(d, s) {
    $('#npt_estu_id').val(d.estu_id);
    $('#slct_estu_tipodoc').val(d.estu_tipodoc);
    $('#npt_estu_numerodoc').val(d.estu_numerodoc);
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
    $('#slct_estu_discapacidad').val(d.estu_discapacidad);

    let multi = (d.estu_multiculturalidad || '')
        .split(',').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
    $('.chk-multicultural').prop('checked', false);
    if (multi.length === 0) {
        $('#chk_multi_no_aplica').prop('checked', true);
    } else {
        multi.forEach(function (v) { $('.chk-multicultural[data-valor="' + v + '"]').prop('checked', true); });
    }

    $('#slct_finc_prog_id').val(d.prog_id);
    $('#npt_jornada').val(d.jornada);
    $('#npt_fechainscripcion').val(d.fechainscripcion);
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
    $('#slct_acud_es').val(d.acud_es);
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

    // Limpia resaltados de una revisión anterior antes de aplicar los nuevos.
    $('#mdl_estudiante .campo-actualizado').removeClass('campo-actualizado');
    $('#lbl_multiculturalidad').removeClass('campo-actualizado');

    MAPA_REVISION_ESTUDIANTE.forEach(function (par) {
        const valor = s[par[0]];
        if (valor !== null && valor !== undefined) {
            $(par[1]).val(valor).addClass('campo-actualizado');
        }
    });
    MAPA_REVISION_FICHA.forEach(function (par) {
        const valor = s[par[0]];
        if (valor !== null && valor !== undefined) {
            $(par[1]).val(valor).addClass('campo-actualizado');
        }
    });
    if (s.soac_ficha_padr_vive !== null && s.soac_ficha_padr_vive !== undefined) {
        $('#npt_padr_vive').prop('checked', s.soac_ficha_padr_vive == 1).addClass('campo-actualizado');
    }
    if (s.soac_ficha_madr_vive !== null && s.soac_ficha_madr_vive !== undefined) {
        $('#npt_madr_vive').prop('checked', s.soac_ficha_madr_vive == 1).addClass('campo-actualizado');
    }
    if (s.soac_ficha_acud_es !== null && s.soac_ficha_acud_es !== undefined) {
        $('#slct_acud_es').addClass('campo-actualizado');
    }
    if (s.soac_estu_multiculturalidad !== null && s.soac_estu_multiculturalidad !== undefined) {
        let multiPropuesta = (s.soac_estu_multiculturalidad || '')
            .split(',').map(function (v) { return v.trim(); }).filter(function (v) { return v !== ''; });
        $('.chk-multicultural').prop('checked', false);
        if (multiPropuesta.length === 0) {
            $('#chk_multi_no_aplica').prop('checked', true);
        } else {
            multiPropuesta.forEach(function (v) { $('.chk-multicultural[data-valor="' + v + '"]').prop('checked', true); });
        }
        $('#lbl_multiculturalidad').addClass('campo-actualizado');
    }

    // Recalcula visibilidad Padre/Madre/Acudiente con los valores ya
    // (potencialmente) sobrescritos — mismo patrón que abrirEditar().
    actualizarVisibilidadPadreMadre();
    actualizarSelectAcudiente();
    $('#slct_acud_es').val(s.soac_ficha_acud_es !== null && s.soac_ficha_acud_es !== undefined ? s.soac_ficha_acud_es : d.acud_es);
    manejarCambioAcudiente();

    // Modo revisión: formulario deshabilitado, Guardar oculto, Aprobar/Descartar visibles.
    $('#npt_revision_soac_id').val(s.soac_id);
    $('#aviso_revision_actualizacion').removeClass('d-none');
    $('#mdl_estudiante .modal-body input, #mdl_estudiante .modal-body select, #mdl_estudiante .modal-body textarea')
        .prop('disabled', true);
    $('#bloque_foto_estudiante, #bloque_cambiar_clave_estudiante, #bloque_programas_matriculados').addClass('d-none');
    $('#btn_guardar_estudiante, #btn_eliminar_aspirante, #btn_ficha_inscripcion_pdf, #btn_hoja_matricula_pdf').addClass('d-none');
    $('#btn_aprobar_actualizacion, #btn_descartar_actualizacion').removeClass('d-none');

    $('#mdl_estudiante_titulo').text('FICHA DE INSCRIPCIÓN — código AC-FO-02');
    new bootstrap.Modal(document.getElementById('mdl_estudiante')).show();
}

// Revierte el modo revisión de poblarModalRevision() — habilita de nuevo
// todos los campos, restaura los botones normales, quita los resaltados.
// Se llama al cerrar el modal (hidden.bs.modal) y defensivamente al abrir
// el modo normal (abrirEditar), por si se navegara de un modo a otro sin
// pasar por el cierre.
function restaurarModoNormalEstudiante() {
    if ($('#npt_revision_soac_id').val() === '') return;
    $('#npt_revision_soac_id').val('');
    $('#aviso_revision_actualizacion').addClass('d-none');
    $('#mdl_estudiante .modal-body input, #mdl_estudiante .modal-body select, #mdl_estudiante .modal-body textarea')
        .prop('disabled', false);
    $('#mdl_estudiante .campo-actualizado').removeClass('campo-actualizado');
    $('#lbl_multiculturalidad').removeClass('campo-actualizado');
    $('#btn_guardar_estudiante').removeClass('d-none');
    $('#btn_aprobar_actualizacion, #btn_descartar_actualizacion').addClass('d-none');
}

// Fuera de ready — requerido para onclick inline de DataTables
function abrirEditar(estu_id, esAspirante) {
    restaurarModoNormalEstudiante();
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener_completo',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let d = response.data;
                $('#btn_eliminar_aspirante').toggleClass('d-none', !esAspirante);
                $('#npt_estu_id').val(d.estu_id);
                $('#npt_estu_usua_id_actual').val(d.usua_id || '');

                // Bloque "Cambiar clave" — solo visible si el estudiante ya tiene
                // acceso creado (usua_id no nulo). Reset en cada apertura para no
                // arrastrar la selección de una edición anterior.
                $('input[name="tipo_clave_estudiante"]').prop('checked', false);
                $('#npt_clave_manual_estudiante').val('');
                $('#bloque_clave_manual_estudiante').addClass('d-none');
                $('#bloque_cambiar_clave_estudiante').toggleClass('d-none', !d.usua_id);
                $('#slct_estu_tipodoc').val(d.estu_tipodoc);
                $('#npt_estu_numerodoc').val(d.estu_numerodoc);
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
                $('#slct_estu_discapacidad').val(d.estu_discapacidad);

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
                $('#lbl_multiculturalidad').removeClass('text-danger');

                CAMPOS_ESTUDIANTE.forEach(function (selector) {
                    if (selector === '#npt_estu_email') {
                        validarFormatoEmail($(selector));
                    } else {
                        marcarValidacion($(selector));
                    }
                });

                // --- Ficha Familiar (AC-FO-02) — mismo modal unificado desde la Fase C2 ---
                $('#slct_finc_prog_id').val(d.prog_id);
                $('#npt_jornada').val(d.jornada);
                $('#npt_fechainscripcion').val(d.fechainscripcion);
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
                $('#slct_acud_es').val(d.acud_es);
                manejarCambioAcudiente();

                // Marca validación de los campos de ficha ya cargados — el chequeo
                // :visible solo es correcto una vez el modal ya está mostrado
                // (mientras el modal es display:none, todo su contenido reporta
                // :visible === false), por eso se difiere hasta shown.bs.modal
                // (mismo patrón que usaba la función abrirFicha(), eliminada
                // tras la fusión de modales — ver CLAUDE.md, "Marcado de
                // validación con :visible dentro de un modal recién abierto").
                $('#mdl_estudiante').one('shown.bs.modal', function () {
                    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
                        .forEach(function (selector) {
                            if ($(selector).is(':visible')) {
                                marcarValidacion($(selector));
                            }
                        });
                });

                $('#bloque_foto_estudiante').removeClass('d-none');
                $('#npt_foto_estudiante').val('');
                if (d.estu_foto) {
                    $('#img_preview_foto')
                        .attr('src', '../../uploads/fotos_estudiantes/' + d.estu_foto)
                        .removeClass('d-none');
                } else {
                    $('#img_preview_foto').attr('src', '').addClass('d-none');
                }

                $('#btn_ficha_inscripcion_pdf').toggleClass('d-none', d.estado_ficha !== 'completa');
                $('#btn_hoja_matricula_pdf').toggleClass('d-none', d.matr_estado !== 'matriculado');

                // Programas matriculados — resumen de solo lectura de TODAS
                // las matrículas del estudiante (d.matriculas, agregado a
                // obtener_completo). Defensivo: si viene vacío (no debería
                // pasar en la práctica), el bloque queda oculto.
                if (d.matriculas && d.matriculas.length > 0) {
                    const texto = d.matriculas.map(function (m) {
                        const estado = m.matr_estado
                            ? m.matr_estado.charAt(0).toUpperCase() + m.matr_estado.slice(1)
                            : '';
                        let fechaTexto = '';
                        if (m.fechamatricula) {
                            const partes = m.fechamatricula.split('-');
                            fechaTexto = ' (' + partes[2] + '/' + partes[1] + '/' + partes[0] + ')';
                        }
                        return m.prog_sigla + ' — ' + estado + fechaTexto;
                    }).join(', ');
                    $('#txt_programas_matriculados').text(texto);
                    $('#bloque_programas_matriculados').show();
                } else {
                    $('#txt_programas_matriculados').text('');
                    $('#bloque_programas_matriculados').hide();
                }

                $('#mdl_estudiante_titulo').text('Editar Estudiante');
                new bootstrap.Modal(document.getElementById('mdl_estudiante')).show();
            } else {
                alert('No se pudo cargar el estudiante.');
            }
        }
    });
}

function abrirMatricular(estu_id) {
    $('#npt_estu_id_matricular').val(estu_id);
    $('#slct_prog_id, #slct_peri_id').val('');
    $('#slct_coho_id').prop('disabled', true).html('<option value="">-- Primero seleccione un programa --</option>');
    poblarSelectSemestre('#slct_matr_semestre', 0);
    $('#slct_jornada_declarada').val('');
    $('#npt_matr_folio').val('');
    $('#npt_matr_observacion').val('');
    $('#npt_fechamatricula').val(new Date().toISOString().slice(0, 10));
    $('#bloque_matriculas_previas').hide();
    $('#lista_matriculas_previas').empty();

    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener_defaults_matricula',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok' && response.data) {
                if (response.data.prog_id) {
                    $('#slct_prog_id').val(response.data.prog_id).trigger('change');
                    // el 'change' ya dispara cargarCohortesPorPrograma() vía el
                    // handler existente — no duplicar esa llamada aquí
                }
                if (response.data.jornada) {
                    $('#slct_jornada_declarada').val(response.data.jornada);
                }
            }
            // Si data es null (aspirante sin ficha diligenciada): el modal
            // queda como hoy, sin precargar nada.
        },
        error: function () {
            console.warn('No se pudieron precargar los datos de la Ficha de Inscripción (no crítico).');
        }
    });

    // Contexto de matrículas ya existentes — solo se muestra si el
    // estudiante ya tiene 1+ filas (caso de "matricular en otro programa");
    // el uso más común del modal (aspirante sin ninguna matrícula) no
    // cambia: el bloque queda oculto igual que antes de este cambio.
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=matriculas_estudiante',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok' && response.data.length > 0) {
                const $lista = $('#lista_matriculas_previas').empty();
                response.data.forEach(function (m) {
                    $lista.append('<li>' + m.prog_nombre + ' — ' + m.matr_estado + ' (' + m.peri_codigo + ')</li>');
                });
                $('#bloque_matriculas_previas').show();
            }
        }
    });

    new bootstrap.Modal(document.getElementById('mdl_matricular')).show();
}

// Global (no dentro de $(document).ready) — mismo motivo que
// cargarCohortesPorPrograma justo debajo: se invoca tanto desde los
// listeners de change de #slct_prog_id / #slct_editar_prog_id (dentro de
// ready) como desde abrirMatricular()/abrirEditarMatricula() (global).
// Repuebla un <select> de semestre con 1..duracion. duracion viene del
// atributo data-duracion que cargarProgramas() ya agrega a cada <option>
// de programa (prog_duracion_semestres) — sin llamada AJAX adicional.
// valorSeleccionado es opcional (precarga en modo edición); si se omite,
// selecciona 1.
function poblarSelectSemestre(selectorDestino, duracion, valorSeleccionado) {
    const $sel = $(selectorDestino);
    if (!duracion || duracion < 1) {
        $sel.prop('disabled', true).html('<option value="">-- Primero seleccione un programa --</option>');
        return;
    }
    let opciones = '';
    for (let i = 1; i <= duracion; i++) {
        opciones += `<option value="${i}">${i}</option>`;
    }
    $sel.prop('disabled', false).html(opciones).val(valorSeleccionado || 1);
}

// Global (no dentro de $(document).ready) — se invoca desde el onclick inline
// del botón "✏️ Matrícula" renderizado por el DataTable, y también desde los
// listeners de change de #slct_prog_id / #slct_editar_prog_id dentro de ready.
function cargarCohortesPorPrograma(prog_id, selectorDestino, callback) {
    selectorDestino = selectorDestino || '#slct_coho_id';
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=listar_cohortes_por_programa',
        data: { prog_id: prog_id },
        dataType: 'json',
        success: function (response) {
            let sel = $(selectorDestino);
            if (response.status === 'ok' && response.data.length > 0) {
                let opciones = '<option value="">-- Seleccionar --</option>';
                response.data.forEach(function (c) {
                    opciones += `<option value="${c.coho_id}">${c.coho_codigo}</option>`;
                });
                sel.html(opciones);
            } else {
                sel.html('<option value="">-- Sin cohortes activas para este programa --</option>');
            }
            if (typeof callback === 'function') callback();
        }
    });
}

function abrirEditarMatricula(matr_id) {
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener_matricula',
        data: { matr_id: matr_id },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('Error: ' + response.message);
                return;
            }
            const d = response.data;
            $('#npt_matr_id_editar').val(d.matr_id);
            $('#mdl_editar_matricula_titulo').text('Editar Matrícula de ' + d.estu_nombres + ' ' + d.estu_apellidos);
            $('#aviso_editar_matricula').hide();
            $('#slct_editar_prog_id').val(d.prog_id);
            poblarSelectSemestre('#slct_editar_matr_semestre', $('#slct_editar_prog_id option:selected').data('duracion'), d.matr_semestre);
            $('#slct_editar_peri_id').val(d.peri_id);
            $('#npt_editar_matr_folio').val(d.matr_folio || '');
            $('#npt_editar_fechamatricula').val(d.fechamatricula || '');
            $('#npt_editar_matr_observacion').val(d.matr_observacion || '');
            $('#npt_editar_matr_numero').val(d.matr_numero !== null && d.matr_numero !== undefined ? d.matr_numero : '');
            $('#slct_editar_coho_id').prop('disabled', false).html('<option value="">-- Seleccionar --</option>');
            cargarCohortesPorPrograma(d.prog_id, '#slct_editar_coho_id', function () {
                $('#slct_editar_coho_id').val(d.coho_id);
            });
            new bootstrap.Modal(document.getElementById('mdl_editar_matricula')).show();
        }
    });
}

// Todos los datos ya viajan en la fila de listar_matriculados (matr_id,
// matr_semestre, prog_duracion_semestres, prog_sigla) — sin llamada AJAX
// adicional, mismo criterio que verModulosEstudiante() de abajo. El select
// de período destino se puebla clonando las opciones ya calculadas de
// #slct_filtro_matr_peri_id_anteriores (todos los períodos EXCEPTO el
// activo, ya resuelto una vez por cargarPeriodos()) en vez de duplicar esa
// misma exclusión aquí o depender de periodoActivoId — esa variable es
// local a $(document).ready y esta función es global (invocada desde el
// onclick inline del dropdown), así que no es accesible directamente.
function abrirAvanzarSemestre(matr_id, nombreCompleto, matrSemestreActual, progDuracionSemestres, progSigla) {
    $('#npt_avanzar_matr_id').val(matr_id);
    $('#txt_avanzar_nombre').text(nombreCompleto);
    $('#txt_avanzar_prog_sigla').text(progSigla);
    $('#txt_avanzar_semestre_actual').text(matrSemestreActual + '/' + progDuracionSemestres);
    $('#txt_avanzar_semestre_nuevo').text((matrSemestreActual + 1) + '/' + progDuracionSemestres);

    const opcionesAnteriores = $('#slct_filtro_matr_peri_id_anteriores option[value!="0"]').clone();
    $('#slct_avanzar_peri_id_destino').html('<option value="">-- Seleccionar --</option>').append(opcionesAnteriores);

    new bootstrap.Modal(document.getElementById('mdl_avanzar_semestre')).show();
}

function descargarFichaPdf(estu_id) {
    window.open('../06_reportes/pdf_ficha.php?estu_id=' + estu_id, '_blank');
}

// peri_id ahora llega como 4º parámetro explícito desde el onclick inline del
// botón de total_modulos (row.peri_id, ya incluido en la respuesta de
// listar_matriculados) — antes se leía de #slct_filtro_matr_peri_id, un
// selector único que dejó de existir al dividirse en las pestañas Per.
// Actual/Per. Anteriores (cada una con su propia fuente de peri_id, o
// ninguna en el caso de Actual). Así la función queda correcta en ambas
// pestañas sin depender de cuál filtro esté visible.
function verModulosEstudiante(estu_id, nombreCompleto, prog_id, peri_id) {
    $('#mdl_modulos_estudiante_titulo').text('Módulos de ' + nombreCompleto);
    const data = { estu_id: estu_id, peri_id: peri_id };
    // prog_id es opcional (tercer parámetro): solo se envía cuando el
    // llamador conoce la matrícula/programa específico de la fila (ej. el
    // botón de total_modulos en tablaMatriculadosActual/Anteriores). Sin él,
    // el backend conserva el comportamiento anterior (todos los programas).
    if (prog_id !== undefined && prog_id !== null) {
        data.prog_id = prog_id;
    }
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=listar_modulos_estudiante',
        data: data,
        dataType: 'json',
        success: function (response) {
            const tbody = $('#tbody_modulos_estudiante');
            tbody.empty();
            if (response.status === 'ok' && response.data.length > 0) {
                response.data.forEach(function (m) {
                    tbody.append(`
                        <tr>
                            <td>${m.modu_sigla} — ${m.modu_nombre}</td>
                            <td>${m.grse_codigo}</td>
                            <td>${m.peri_codigo}</td>
                            <td>${m.doce_apellidos}, ${m.doce_nombres}</td>
                        </tr>
                    `);
                });
            } else {
                const mensaje = peri_id
                    ? 'Sin módulos asignados para el período filtrado'
                    : 'Sin módulos asignados';
                tbody.append(`<tr><td colspan="4" class="text-muted text-center">${mensaje}</td></tr>`);
            }
            new bootstrap.Modal(document.getElementById('mdl_modulos_estudiante')).show();
        }
    });
}

function limpiarFormularioFicha() {
    $('#npt_estu_id_ficha').val('');
    $('#slct_finc_prog_id').val('');
    $('#npt_jornada').val('');
    $('#npt_fechainscripcion').val('');
    $('#npt_padr_vive').prop('checked', false);
    $('#npt_padr_nombres').val('');
    $('#npt_padr_apellidos').val('');
    $('#npt_padr_profesion').val('');
    $('#npt_padr_empresa').val('');
    $('#npt_padr_telefono').val('');
    $('#npt_padr_direccion').val('');
    $('#npt_padr_barrio').val('');
    $('#npt_padr_ciudad').val('');
    $('#npt_madr_vive').prop('checked', false);
    $('#npt_madr_nombres').val('');
    $('#npt_madr_apellidos').val('');
    $('#npt_madr_profesion').val('');
    $('#npt_madr_empresa').val('');
    $('#npt_madr_telefono').val('');
    $('#npt_madr_direccion').val('');
    $('#npt_madr_barrio').val('');
    $('#npt_madr_ciudad').val('');
    $('#slct_acud_es').val('');
    $('#npt_acud_parentesco').val('');
    $('#npt_acud_nombres').val('');
    $('#npt_acud_apellidos').val('');
    $('#npt_acud_profesion').val('');
    $('#npt_acud_empresa').val('');
    $('#npt_acud_telefono').val('');
    $('#npt_acud_direccion').val('');
    $('#npt_acud_barrio').val('');
    $('#npt_acud_ciudad').val('');
    $('#npt_estudio_tipo').val('');
    $('#npt_estudio_titulo').val('');
    $('#npt_estudio_institucion').val('');
    $('#npt_estudio_aniofin').val('');

    actualizarVisibilidadPadreMadre();
    actualizarSelectAcudiente();

    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE).forEach(function (selector) {
        $(selector).removeClass('is-invalid is-valid');
    });
}

// ── Configurar Requisitos (Etapa 2.12.E2, solo Admin) ───────────────────

// Llamada independiente con su propio $.ajax en vez de extender
// cargarProgramas() (que ya dispara el suyo) — cargarProgramas() no
// expone la respuesta fuera de su propio success, así que reutilizarla
// habría exigido restructurarla; se prefirió una segunda consulta al
// mismo catálogo simple, mismo criterio ya aplicado en el proyecto para
// catálogos compartidos (duplicar una consulta barata en vez de acoplar
// dos funciones ya estables).
function cargarProgramasConfigRequisitos() {
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=listar_programas',
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') return;
            let opciones = '';
            response.data.forEach(function (p) {
                if (p.prog_activo == 1) {
                    opciones += `<option value="${p.prog_id}">${p.prog_nombre}</option>`;
                }
            });
            $('#slct_prog_config_requisitos').append(opciones);
        }
    });
}

// Global (no dentro de $(document).ready) — se invoca desde el listener
// de change de #slct_prog_config_requisitos (dentro de ready) y también
// desde desactivarRequisitoPrograma()/reactivarRequisitoPrograma()
// (global) para refrescar la tabla tras la acción — mismo motivo de
// scope global que cargarCohortesPorPrograma().
function cargarTablaRequisitosPrograma(prog_id) {
    $.ajax({
        type: 'GET',
        url: 'est_mdl.php?accion=listar_requisitos_programa&prog_id=' + prog_id,
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('Error: ' + response.message);
                return;
            }
            ultimoListadoRequisitosPrograma = response.data;

            if (response.data.length === 0) {
                $('#tbl_requisitos_programa').addClass('d-none');
                $('#bloque_sin_programa')
                    .text('Este programa aún no tiene requisitos configurados.')
                    .removeClass('d-none');
                return;
            }

            $('#bloque_sin_programa').addClass('d-none');
            $('#tbl_requisitos_programa').removeClass('d-none');

            const $tbody = $('#tbl_requisitos_programa tbody').empty();
            response.data.forEach(function (r, idx) {
                const descripcion = r.reqp_descripcion || '—';

                // Mismo patrón inline ya usado en crearColumnasMatriculados()
                // para soac_fecha_ultima_aprobacion: split '-' y reordenar a
                // dd/mm/aaaa — sin función compartida extraída en el archivo.
                let fechaFmt = '—';
                if (r.reqp_fecha_actualizacion) {
                    const partes = r.reqp_fecha_actualizacion.split(' ')[0].split('-');
                    fechaFmt = partes[2] + '/' + partes[1] + '/' + partes[0];
                }

                let claseFila = '';
                let acciones;
                if (r.reqp_activo == 1) {
                    acciones = `<button class="btn btn-sm btn-outline-primary" onclick="abrirEditarRequisitoPrograma(${r.reqp_id})">✏️</button> ` +
                               `<button class="btn btn-sm btn-outline-danger" onclick="desactivarRequisitoPrograma(${r.reqp_id})">🗑️</button>`;
                } else {
                    claseFila = 'table-secondary text-muted';
                    acciones = `<span class="badge bg-secondary">Inactivo</span> ` +
                               `<button class="btn btn-sm btn-outline-success" onclick="reactivarRequisitoPrograma(${r.reqp_id})">♻️ Reactivar</button>`;
                }

                $tbody.append(`<tr class="${claseFila}">
                    <td>${idx + 1}</td>
                    <td>${r.reqp_nombre}</td>
                    <td>${descripcion}</td>
                    <td>${fechaFmt}</td>
                    <td>${acciones}</td>
                </tr>`);
            });
        }
    });
}

// Global — invocada vía onclick inline desde #tbl_requisitos_programa.
// Abre el modal y precarga nombre/descripción/prog_id desde
// ultimoListadoRequisitosPrograma (sin llamada AJAX adicional) — el
// guardado (#btn_guardar_requisito_programa) se conectó en la Etapa 2.12.E3.
function abrirEditarRequisitoPrograma(reqp_id) {
    const requisito = ultimoListadoRequisitosPrograma.find(function (r) { return r.reqp_id == reqp_id; });
    if (!requisito) return;

    $('#mdl_requisito_programa_titulo').text('Editar Requisito');
    $('#npt_reqp_id').val(requisito.reqp_id);
    $('#npt_reqp_nombre').val(requisito.reqp_nombre).removeClass('is-invalid');
    $('#npt_reqp_descripcion').val(requisito.reqp_descripcion || '');
    // Mismo campo hidden que usa el flujo de "Agregar" — editar_requisito_programa
    // no necesita prog_id en el UPDATE, pero se fija igual para mantener el
    // estado del modal consistente entre ambos modos (Agregar/Editar).
    $('#npt_reqp_prog_id_modal').val(requisito.prog_id);

    new bootstrap.Modal(document.getElementById('mdl_requisito_programa')).show();
}

// Global — invocada vía onclick inline desde #tbl_requisitos_programa.
function desactivarRequisitoPrograma(reqp_id) {
    if (!confirm('¿Desactivar este requisito? Los estudiantes que ya lo tengan asignado lo conservarán en su historial.')) {
        return;
    }
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=eliminar_requisito_programa',
        data: { reqp_id: reqp_id },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('Error: ' + response.message);
                return;
            }
            cargarTablaRequisitosPrograma($('#slct_prog_config_requisitos').val());
        }
    });
}

// Global — invocada vía onclick inline desde #tbl_requisitos_programa.
// Sin confirm(): reactivar no es una acción destructiva.
function reactivarRequisitoPrograma(reqp_id) {
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=reactivar_requisito_programa',
        data: { reqp_id: reqp_id },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('Error: ' + response.message);
                return;
            }
            cargarTablaRequisitosPrograma($('#slct_prog_config_requisitos').val());
        }
    });
}
