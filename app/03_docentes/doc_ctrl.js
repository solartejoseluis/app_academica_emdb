$(document).ready(function () {

    let tablaDocentes;

    cargarTabla();

    $('#btn_nuevo_docente').click(function () {
        limpiarFormulario();
        $('#mdl_docente_titulo').text('Nuevo Docente');
        $('#bloque_password').removeClass('d-none');
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
            usua_email:     usua_email
        };

        if (doce_id === '') {
            data.usua_password = $('#npt_usua_password').val();
        } else {
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

    function cargarTabla() {
        tablaDocentes = $('#tbl_docentes').DataTable({
            ajax: {
                url: 'doc_mdl.php?accion=listar',
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
                    data: null,
                    orderable: false,
                    width: '80px',
                    render: function (data, type, row) {
                        return `<button class="btn btn-sm btn-outline-primary"
                                        onclick="abrirEditar(${row.doce_id})">Editar</button>`;
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
                $('#npt_usua_email').val(d.usua_email).prop('readonly', true);
                $('#slct_usua_activo').val(d.usua_activo);
                $('#mdl_docente_titulo').text('Editar Docente');
                $('#bloque_password').addClass('d-none');
                $('#bloque_activo').removeClass('d-none');
                new bootstrap.Modal(document.getElementById('mdl_docente')).show();
            } else {
                alert('No se pudo cargar el docente.');
            }
        }
    });
}
