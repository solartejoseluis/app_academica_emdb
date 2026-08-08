// Reutilizable entre mdl_estudiante y (Fase B) mdl_ficha — fuera de $(document).ready
// para que abrirEditar()/abrirFicha() también puedan invocarla.
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
    let tablaMatriculados;

    cargarTablas();
    cargarProgramas();
    cargarPeriodos();
    cargarCohortes();

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

    // --- Nuevo aspirante ---
    $('#btn_nuevo_aspirante').click(function () {
        limpiarFormulario();
        $('#mdl_estudiante_titulo').text('Nuevo Aspirante');
        new bootstrap.Modal(document.getElementById('mdl_estudiante')).show();
    });

    // --- Guardar estudiante (crear / editar) ---
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
            estu_multiculturalidad: obtenerMulticulturalidad()
        };

        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=guardar',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_estudiante')).hide();
                    tablaAspirantes.ajax.reload(null, false);
                    alert(estu_id === '' ? 'Aspirante registrado correctamente.' : 'Estudiante actualizado correctamente.');
                } else {
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
                    tablaMatriculados.ajax.reload(null, false);
                } else {
                    alert(response.message);
                }
            },
            error: function () {
                alert('Error al subir la foto.');
            }
        });
    });

    // --- Radio tipo_acceso ---
    $('input[name="tipo_acceso"]').change(function () {
        if ($(this).val() === 'manual') {
            $('#bloque_clave_manual').removeClass('d-none');
        } else {
            $('#bloque_clave_manual').addClass('d-none');
        }
    });

    // --- Confirmar matrícula ---
    $('#btn_confirmar_matricula').click(function () {
        let estu_id  = $('#npt_estu_id_matricular').val();
        let prog_id  = $('#slct_prog_id').val();
        let peri_id  = $('#slct_peri_id').val();

        if (!prog_id) { alert('Seleccione el programa.');  return false; }
        if (!peri_id) { alert('Seleccione el período.');   return false; }

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
            tipo_acceso:       tipo_acceso,
            clave_manual:      $('#npt_clave_manual').val().trim(),
            req_copiadiploma:  $('#npt_req_copiadiploma').is(':checked') ? 1 : 0,
            req_actagrado:     $('#npt_req_actagrado').is(':checked')    ? 1 : 0,
            req_documento:     $('#npt_req_documento').is(':checked')    ? 1 : 0,
            req_carnetsalud:   $('#npt_req_carnetsalud').is(':checked')  ? 1 : 0,
            req_examenmedico:  $('#npt_req_examenmedico').is(':checked') ? 1 : 0,
            req_fotos:         $('#npt_req_fotos').is(':checked')        ? 1 : 0,
            req_carpeta:       $('#npt_req_carpeta').is(':checked')      ? 1 : 0,
            req_vacunastetano: $('#npt_req_vacunastetano').is(':checked')? 1 : 0,
            req_hepatitisb:    $('#npt_req_hepatitisb').is(':checked')   ? 1 : 0
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
                        $('#div_clave_generada').removeClass('d-none');
                        $('#btn_confirmar_matricula').addClass('d-none');
                        $('#btn_cerrar_matricula').removeClass('d-none');
                    } else {
                        bootstrap.Modal.getInstance(document.getElementById('mdl_matricular')).hide();
                        tablaAspirantes.ajax.reload(null, false);
                        tablaMatriculados.ajax.reload(null, false);
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

    // --- Guardar ficha familiar (AC-FO-02) ---
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
            estu_id:              $('#npt_estu_id_ficha').val(),
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
            url: 'est_mdl.php?accion=guardar_ficha',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_ficha')).hide();
                    tablaAspirantes.ajax.reload(null, false);
                    tablaMatriculados.ajax.reload(null, false);
                    alert('Ficha guardada correctamente.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // --- Cerrar y actualizar lista (tras mostrar clave) ---
    $('#btn_cerrar_matricula').click(function () {
        bootstrap.Modal.getInstance(document.getElementById('mdl_matricular')).hide();
        tablaAspirantes.ajax.reload(null, false);
        tablaMatriculados.ajax.reload(null, false);
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
                    width: '300px',
                    render: function (data, type, row) {
                        const mapaColorFicha = { sin_iniciar: '#dc3545', incompleta: '#ffc107', completa: '#28a745' };
                        const mapaTituloFicha = { sin_iniciar: 'Ficha sin iniciar', incompleta: 'Ficha incompleta', completa: 'Ficha completa' };
                        const colorFicha = mapaColorFicha[row.estado_ficha] || '#dc3545';
                        const tituloFicha = mapaTituloFicha[row.estado_ficha] || 'Ficha sin iniciar';
                        const botonPdf = row.estado_ficha === 'completa'
                            ? `<button class="btn btn-sm btn-outline-danger ms-1" onclick="descargarFichaPdf(${row.estu_id})" title="Descargar ficha en PDF">🖨️ PDF</button>`
                            : '';
                        return `<button class="btn btn-sm btn-outline-success me-1"
                                        onclick="abrirMatricular(${row.estu_id})">Matricular</button>
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="abrirEditar(${row.estu_id})">Editar</button>
                                <button class="btn btn-sm btn-outline-info" onclick="abrirFicha(${row.estu_id}, '${(row.estu_nombres + ' ' + row.estu_apellidos).replace(/'/g, "\\'")}')" title="${tituloFicha}">
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background-color:${colorFicha};margin-right:4px;"></span>📋 Ficha
                                </button>${botonPdf}`;
                    }
                }
            ]
        });

        tablaMatriculados = $('#tbl_matriculados').DataTable({
            ajax: {
                url: 'est_mdl.php?accion=listar_matriculados',
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
                { data: 'prog_sigla', width: '70px' },
                { data: 'peri_codigo', width: '80px' },
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
                {
                    data: null,
                    orderable: false,
                    width: '200px',
                    render: function (data, type, row) {
                        const mapaColorFicha = { sin_iniciar: '#dc3545', incompleta: '#ffc107', completa: '#28a745' };
                        const mapaTituloFicha = { sin_iniciar: 'Ficha sin iniciar', incompleta: 'Ficha incompleta', completa: 'Ficha completa' };
                        const colorFicha = mapaColorFicha[row.estado_ficha] || '#dc3545';
                        const tituloFicha = mapaTituloFicha[row.estado_ficha] || 'Ficha sin iniciar';
                        const botonPdf = row.estado_ficha === 'completa'
                            ? `<button class="btn btn-sm btn-outline-danger ms-1" onclick="descargarFichaPdf(${row.estu_id})" title="Descargar ficha en PDF">🖨️ PDF</button>`
                            : '';
                        return `<button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="abrirEditar(${row.estu_id})">Editar</button>
                                <button class="btn btn-sm btn-outline-info" onclick="abrirFicha(${row.estu_id}, '${(row.estu_nombres + ' ' + row.estu_apellidos).replace(/'/g, "\\'")}')" title="${tituloFicha}">
                                    <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background-color:${colorFicha};margin-right:4px;"></span>📋 Ficha
                                </button>${botonPdf}`;
                    }
                }
            ]
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
                        opciones += `<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                    });
                    $('#slct_prog_id, #slct_finc_prog_id').append(opciones);
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
                if (response.status === 'ok') {
                    let sel = $('#slct_peri_id');
                    response.data.forEach(function (p) {
                        sel.append(`<option value="${p.peri_id}">${p.peri_codigo}</option>`);
                    });
                }
            }
        });
    }

    function cargarCohortes() {
        $.ajax({
            type: 'POST',
            url: 'est_mdl.php?accion=listar_cohortes',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let sel = $('#slct_coho_id');
                    response.data.forEach(function (c) {
                        sel.append(`<option value="${c.coho_id}">${c.coho_codigo}</option>`);
                    });
                }
            }
        });
    }

    function limpiarFormulario() {
        $('#npt_estu_id').val('');
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
    }

    function obtenerMulticulturalidad() {
        let seleccionadas = [];
        $('.chk-multicultural:checked').each(function () {
            seleccionadas.push($(this).data('valor'));
        });
        return seleccionadas.join(',');
    }
});

// Fuera de ready — requerido para onclick inline de DataTables
function abrirEditar(estu_id) {
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let d = response.data;
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

                $('#bloque_foto_estudiante').removeClass('d-none');
                $('#npt_foto_estudiante').val('');
                if (d.estu_foto) {
                    $('#img_preview_foto')
                        .attr('src', '../../uploads/fotos_estudiantes/' + d.estu_foto)
                        .removeClass('d-none');
                } else {
                    $('#img_preview_foto').attr('src', '').addClass('d-none');
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
    new bootstrap.Modal(document.getElementById('mdl_matricular')).show();
}

function descargarFichaPdf(estu_id) {
    window.open('../06_reportes/pdf_ficha.php?estu_id=' + estu_id, '_blank');
}

function abrirFicha(estu_id, nombreCompleto) {
    $('#ficha_nombre_estudiante').text(nombreCompleto || '');
    $.ajax({
        type: 'POST',
        url: 'est_mdl.php?accion=obtener_ficha',
        data: { estu_id: estu_id },
        dataType: 'json',
        success: function (response) {
            if (response.status !== 'ok') {
                alert('No se pudo cargar la ficha.');
                return;
            }

            limpiarFormularioFicha();
            $('#npt_estu_id_ficha').val(estu_id);

            let d = response.data;
            if (d) {
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
            }

            actualizarVisibilidadPadreMadre();
            actualizarSelectAcudiente();
            $('#slct_acud_es').val(d ? d.acud_es : '');
            manejarCambioAcudiente();

            const modalFicha = document.getElementById('mdl_ficha');
            if (d) {
                $(modalFicha).one('shown.bs.modal', function () {
                    CAMPOS_FICHA_SIEMPRE.concat(CAMPOS_FICHA_PADRE, CAMPOS_FICHA_MADRE)
                        .forEach(function (selector) {
                            if ($(selector).is(':visible')) {
                                marcarValidacion($(selector));
                            }
                        });
                });
            }
            new bootstrap.Modal(modalFicha).show();
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
