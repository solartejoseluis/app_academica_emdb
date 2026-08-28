// doce_id pendiente de confirmación de borrado — accesible tanto desde el
// scope de $(document).ready como desde las funciones globales de abajo,
// que se invocan vía onclick inline desde el HTML renderizado por DataTables.
let docenteIdPendienteEliminar = null;

$(document).ready(function () {

    let tablaDocentes;

    cargarPeriodos().then(function () {
        cargarTabla();
    });

    $('#slct_peri_docentes').on('change', function () {
        tablaDocentes.ajax.reload(null, false);
    });

    function cargarPeriodos() {
        return $.ajax({
            type: 'POST',
            url: 'doc_mdl.php?accion=listar_periodos',
            dataType: 'json'
        }).then(function (r) {
            if (r.status !== 'ok') return;
            let opts = '';
            let activoId = '';
            r.data.forEach(function (p) {
                opts += `<option value="${p.peri_id}">${p.peri_codigo}</option>`;
                if (p.peri_activo == 1) activoId = p.peri_id;
            });
            $('#slct_peri_docentes').html(opts);
            if (activoId !== '') {
                $('#slct_peri_docentes').val(activoId);
            }
        });
    }

    $('#btn_nuevo_docente').click(function () {
        limpiarFormulario();
        $('#mdl_docente_titulo').text('Nuevo Docente');
        $('#bloque_password').removeClass('d-none');
        $('#npt_usua_password').attr('placeholder', 'Contraseña inicial');
        $('#bloque_activo').addClass('d-none');
        $('#npt_usua_email').prop('readonly', false);
        new bootstrap.Modal(document.getElementById('mdl_docente')).show();
    });

    $('#btn_guardar_docente').click(function () {
        let doce_id        = $('#npt_doce_id').val().trim();
        let doce_nombres   = $('#npt_doce_nombres').val().trim();
        let doce_apellidos = $('#npt_doce_apellidos').val().trim();
        let doce_sigla     = $('#npt_doce_sigla').val().trim();
        let usua_email     = $('#npt_usua_email').val().trim();

        if (!validarFormulario(doce_id, doce_nombres, doce_apellidos, doce_sigla, usua_email)) {
            return false;
        }

        let data = {
            doce_id:        doce_id,
            doce_nombres:   doce_nombres,
            doce_apellidos: doce_apellidos,
            doce_sigla:     doce_sigla,
            usua_email:     usua_email,
            usua_password:  $('#npt_usua_password').val()
        };

        if (doce_id !== '') {
            data.usua_activo = $('#slct_usua_activo').val();
        }

        $.ajax({
            type: 'POST',
            url: 'doc_mdl.php?accion=guardar',
            data: data,
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_docente')).hide();
                    tablaDocentes.ajax.reload(null, false);
                    alert(doce_id === '' ? 'Docente creado correctamente.' : 'Docente actualizado correctamente.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    $('#btn_confirmar_eliminar_docente').click(function () {
        if (!docenteIdPendienteEliminar) return;
        $.ajax({
            type: 'POST',
            url: 'doc_mdl.php?accion=eliminar_docente',
            data: { doce_id: docenteIdPendienteEliminar },
            dataType: 'json',
            success: function (response) {
                bootstrap.Modal.getInstance(document.getElementById('mdl_confirmar_eliminar_docente')).hide();
                if (response.status === 'ok') {
                    tablaDocentes.ajax.reload(null, false);
                } else {
                    alert(response.message);
                }
                docenteIdPendienteEliminar = null;
            }
        });
    });

    function cargarTabla() {
        tablaDocentes = $('#tbl_docentes').DataTable({
            ajax: {
                url: 'doc_mdl.php?accion=listar',
                type: 'POST',
                data: function (d) {
                    d.peri_id = $('#slct_peri_docentes').val();
                },
                dataSrc: 'data'
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: [
                {
                    data: null,
                    orderable: false,
                    width: '40px',
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                { data: 'doce_nombres' },
                { data: 'doce_apellidos' },
                { data: 'doce_sigla', width: '70px' },
                { data: 'usua_email' },
                {
                    data: 'usua_activo',
                    width: '80px',
                    render: function (data) {
                        return data == 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: 'total_grupos',
                    width: '110px',
                    render: function (data, type, row) {
                        return `<span class="badge bg-info text-dark" title="Período: ${row.peri_codigo}">${data} grupos</span>`;
                    }
                },
                { data: 'ultimo_acceso_fmt', width: '120px' },
                {
                    data: null,
                    orderable: false,
                    width: '190px',
                    render: function (data, type, row) {
                        let botones = `<button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="abrirEditar(${row.doce_id})">Editar</button>`;
                        const nombreCompleto = (row.doce_nombres + ' ' + row.doce_apellidos).replace(/'/g, "\\'");
                        if (row.doce_activo == 1 && row.total_grupos_historico == 0) {
                            botones += `<button class="btn btn-sm btn-outline-danger"
                                onclick="confirmarEliminarDocente(${row.doce_id}, '${nombreCompleto}')">Eliminar</button>`;
                        } else if (row.doce_activo == 1 && row.total_grupos_historico > 0) {
                            botones += `<button class="btn btn-sm btn-outline-warning"
                                onclick="toggleEstadoDocente(${row.doce_id}, 0)">Desactivar</button>`;
                        } else {
                            botones += `<button class="btn btn-sm btn-outline-success"
                                onclick="toggleEstadoDocente(${row.doce_id}, 1)">Activar</button>`;
                        }
                        return botones;
                    }
                }
            ]
        });
    }

    function limpiarFormulario() {
        $('#npt_doce_id').val('');
        $('#npt_doce_nombres').val('');
        $('#npt_doce_apellidos').val('');
        $('#npt_doce_sigla').val('');
        $('#npt_usua_email').val('');
        $('#npt_usua_password').val('');
        $('#slct_usua_activo').val('1');
    }
});

function validarFormulario(doce_id, doce_nombres, doce_apellidos, doce_sigla, usua_email) {
    if (!doce_nombres) {
        alert('El nombre es requerido.');
        return false;
    }
    if (!doce_apellidos) {
        alert('Los apellidos son requeridos.');
        return false;
    }
    if (!doce_sigla) {
        alert('La sigla es requerida.');
        return false;
    }
    if (!usua_email) {
        alert('El correo electrónico es requerido.');
        return false;
    }
    if (doce_id === '') {
        if (!$('#npt_usua_password').val()) {
            alert('La contraseña inicial es requerida.');
            return false;
        }
    }
    return true;
}

function abrirEditar(doce_id) {
    $.ajax({
        type: 'POST',
        url: 'doc_mdl.php?accion=obtener',
        data: { doce_id: doce_id },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let d = response.data;
                $('#npt_doce_id').val(d.doce_id);
                $('#npt_doce_nombres').val(d.doce_nombres);
                $('#npt_doce_apellidos').val(d.doce_apellidos);
                $('#npt_doce_sigla').val(d.doce_sigla);
                $('#npt_usua_email').val(d.usua_email);
                $('#slct_usua_activo').val(d.usua_activo);
                $('#mdl_docente_titulo').text('Editar Docente');
                $('#npt_usua_password').val('');
                if (d.usua_id) {
                    $('#bloque_password').removeClass('d-none');
                    $('#npt_usua_password').attr('placeholder', 'Dejar vacío para no cambiar');
                } else {
                    $('#bloque_password').addClass('d-none');
                }
                $('#bloque_activo').removeClass('d-none');
                new bootstrap.Modal(document.getElementById('mdl_docente')).show();
            } else {
                alert('No se pudo cargar el docente.');
            }
        }
    });
}

function confirmarEliminarDocente(doce_id, nombre) {
    docenteIdPendienteEliminar = doce_id;
    $('#spn_nombre_eliminar_docente').text(nombre);
    new bootstrap.Modal('#mdl_confirmar_eliminar_docente').show();
}

function toggleEstadoDocente(doce_id, nuevoEstado) {
    $.ajax({
        type: 'POST',
        url: 'doc_mdl.php?accion=toggle_estado_docente',
        data: { doce_id: doce_id, nuevo_estado: nuevoEstado },
        dataType: 'json',
        success: function (r) {
            if (r.status === 'ok') {
                $('#tbl_docentes').DataTable().ajax.reload(null, false);
            } else {
                alert('Error: ' + r.message);
            }
        }
    });
}
