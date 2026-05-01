$(document).ready(function () {

    let tablaUsuarios;

    // Select → Input bridge
    $('#slct_role_id').change(function () {
        $('#npt_role_id').val($(this).val());
    });

    $('#slct_role_id_editar').change(function () {
        $('#npt_role_id_editar').val($(this).val());
    });

    // Limpiar modal nuevo al abrir
    $('#mdl_nuevo_usuario').on('show.bs.modal', function () {
        limpiarFormularioNuevo();
    });

    cargarRoles();
    cargarTabla();

    // ── Guardar nuevo usuario ─────────────────────────────────────────────
    $('#btn_guardar_usuario').click(function () {
        let email    = $('#npt_email').val().trim();
        let password = $('#npt_password').val();
        let role_id  = $('#npt_role_id').val();

        if (!email || !password || !role_id) {
            alert('Todos los campos son requeridos.');
            return false;
        }

        $.ajax({
            type: 'POST',
            url: 'admin_mdl.php?accion=crear',
            data: { usua_email: email, usua_password: password, role_id: role_id },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_nuevo_usuario')).hide();
                    tablaUsuarios.ajax.reload(null, false);
                    alert('Usuario creado correctamente.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // ── Guardar edición ───────────────────────────────────────────────────
    $('#btn_guardar_editar').click(function () {
        let usua_id     = $('#npt_usua_id_editar').val();
        let email       = $('#npt_email_editar').val().trim();
        let password    = $('#npt_password_editar').val();
        let role_id     = $('#npt_role_id_editar').val();
        let usua_activo = $('#npt_activo_editar').val();

        if (!email || !role_id) {
            alert('Email y rol son requeridos.');
            return false;
        }

        $.ajax({
            type: 'POST',
            url: 'admin_mdl.php?accion=editar',
            data: {
                usua_id: usua_id,
                usua_email: email,
                usua_password: password,
                role_id: role_id,
                usua_activo: usua_activo
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_editar_usuario')).hide();
                    tablaUsuarios.ajax.reload(null, false);
                    alert('Usuario actualizado correctamente.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // ── Funciones ─────────────────────────────────────────────────────────

    function cargarRoles() {
        $.ajax({
            type: 'POST',
            url: 'admin_mdl.php?accion=listar_roles',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let opts = '<option value="">-- Seleccionar --</option>';
                    response.data.forEach(function (r) {
                        opts += `<option value="${r.role_id}">${r.role_nombre}</option>`;
                    });
                    $('#slct_role_id, #slct_role_id_editar').html(opts);
                }
            }
        });
    }

    function cargarTabla() {
        tablaUsuarios = $('#tbl_usuarios').DataTable({
            ajax: {
                url: 'admin_mdl.php?accion=listar',
                type: 'POST',
                dataSrc: 'data'
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: [
                { data: 'usua_id', width: '50px' },
                { data: 'usua_email' },
                { data: 'role_nombre' },
                {
                    data: 'usua_activo',
                    render: function (data) {
                        return data == 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                { data: 'fechacreacion' },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row) {
                        return `<button class="btn btn-sm btn-outline-primary"
                                        onclick="abrirEditar(${row.usua_id})">Editar</button>`;
                    }
                }
            ]
        });
    }

    function limpiarFormularioNuevo() {
        $('#npt_email').val('');
        $('#npt_password').val('');
        $('#slct_role_id').val('').trigger('change');
    }
});

function abrirEditar(usua_id) {
    $.ajax({
        type: 'GET',
        url: 'admin_mdl.php?accion=obtener&usua_id=' + usua_id,
        dataType: 'json',
        success: function (response) {
            if (response.status === 'ok') {
                let u = response.data;
                $('#npt_usua_id_editar').val(u.usua_id);
                $('#npt_email_editar').val(u.usua_email);
                $('#npt_password_editar').val('');
                $('#slct_role_id_editar').val(u.role_id).trigger('change');
                $('#npt_activo_editar').val(u.usua_activo);
                new bootstrap.Modal(document.getElementById('mdl_editar_usuario')).show();
            } else {
                alert('No se pudo cargar el usuario.');
            }
        }
    });
}
