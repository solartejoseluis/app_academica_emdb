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

    // --- Nuevo aspirante ---
    $('#btn_nuevo_aspirante').click(function () {
        limpiarFormulario();
        $('#mdl_estudiante_titulo').text('Nuevo Aspirante');
        new bootstrap.Modal(document.getElementById('mdl_estudiante')).show();
    });

    // --- Guardar estudiante (crear / editar) ---
    $('#btn_guardar_estudiante').click(function () {
        let estu_id        = $('#npt_estu_id').val().trim();
        let estu_nombres   = $('#npt_estu_nombres').val().trim();
        let estu_apellidos = $('#npt_estu_apellidos').val().trim();
        let estu_numerodoc = $('#npt_estu_numerodoc').val().trim();

        if (!estu_nombres)   { alert('Los nombres son requeridos.');               return false; }
        if (!estu_apellidos) { alert('Los apellidos son requeridos.');              return false; }
        if (!estu_numerodoc) { alert('El número de documento es requerido.');      return false; }

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
            estu_eps:       $('#npt_estu_eps').val().trim()
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
            matr_folio:        $('#npt_matr_folio').val().trim(),
            matr_numero:       $('#npt_matr_numero').val().trim(),
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
        $('#npt_matr_folio').val('');
        $('#npt_matr_numero').val('');
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
                {
                    data: null,
                    orderable: false,
                    width: '160px',
                    render: function (data, type, row) {
                        return `<button class="btn btn-sm btn-outline-success me-1"
                                        onclick="abrirMatricular(${row.estu_id})">Matricular</button>
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="abrirEditar(${row.estu_id})">Editar</button>`;
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
                    width: '80px',
                    render: function (data, type, row) {
                        return `<button class="btn btn-sm btn-outline-primary"
                                        onclick="abrirEditar(${row.estu_id})">Editar</button>`;
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
                    let sel = $('#slct_prog_id');
                    response.data.forEach(function (p) {
                        sel.append(`<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`);
                    });
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
