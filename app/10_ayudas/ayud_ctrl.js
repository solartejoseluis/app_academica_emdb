// ayud_id pendiente de confirmación de borrado — accesible tanto desde el
// scope de $(document).ready como desde las funciones globales de abajo,
// que se invocan vía onclick inline desde el HTML renderizado por DataTables.
let ayudaIdPendienteEliminar = null;

$(document).ready(function () {

    let tablaAyudas;

    cargarTabla();

    function cargarTabla() {
        tablaAyudas = $('#tbl_ayudas').DataTable({
            ajax: {
                url: 'ayud_mdl.php?accion=listar',
                type: 'POST',
                dataSrc: 'data'
            },
            destroy: true,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            columns: [
                { data: 'ayud_seccion' },
                { data: 'ayud_titulo' },
                { data: 'ayud_orden' },
                {
                    data: 'ayud_activo',
                    render: function (data) {
                        return data == 1
                            ? '<span class="badge bg-success">Activo</span>'
                            : '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: function (data, type, row) {
                        const tituloEscapado = row.ayud_titulo.replace(/'/g, "\\'");
                        return `<button class="btn btn-sm btn-outline-primary me-1"
                                        onclick="abrirEditarAyuda(${row.ayud_id})">Editar</button>
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="confirmarEliminarAyuda(${row.ayud_id}, '${tituloEscapado}')">Eliminar</button>`;
                    }
                }
            ]
        });
    }

    $('#btn_nueva_ayuda').on('click', function () {
        $('#ayud_id').val('');
        $('#slct_ayud_seccion').val('');
        $('#ayud_titulo, #ayud_contenido').val('');
        $('#ayud_orden').val('0');
        $('#bloque_activo_ayuda').addClass('d-none');
        $('#mdl_ayuda_titulo').text('Nueva Ayuda');
        new bootstrap.Modal('#mdl_ayuda').show();
    });

    $('#btn_guardar_ayuda').on('click', function () {
        const seccion   = $('#slct_ayud_seccion').val();
        const titulo    = $('#ayud_titulo').val().trim();
        const contenido = $('#ayud_contenido').val().trim();
        const orden     = $('#ayud_orden').val();

        if (!seccion || !titulo || !contenido) {
            alert('Complete los campos obligatorios');
            return;
        }

        $.ajax({
            type: 'POST',
            url: 'ayud_mdl.php?accion=guardar',
            data: {
                ayud_id:        $('#ayud_id').val(),
                ayud_seccion:   seccion,
                ayud_titulo:    titulo,
                ayud_contenido: contenido,
                ayud_orden:     orden || 0,
                ayud_activo:    $('#slct_ayud_activo').val() || 1
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance('#mdl_ayuda').hide();
                    cargarTabla();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

    // Eliminar vive como acción de fila en la tabla (no dentro del modal de
    // edición) — mismo patrón que btn_confirmar_eliminar_modulo/_cohorte
    // en 04_grupos.
    $('#btn_confirmar_eliminar_ayuda').on('click', function () {
        if (!ayudaIdPendienteEliminar) return;
        $.ajax({
            type: 'POST',
            url: 'ayud_mdl.php?accion=eliminar',
            data: { ayud_id: ayudaIdPendienteEliminar },
            dataType: 'json',
            success: function (r) {
                bootstrap.Modal.getInstance('#mdl_confirmar_eliminar_ayuda').hide();
                if (r.status === 'ok') {
                    cargarTabla();
                } else {
                    alert(r.message);
                }
                ayudaIdPendienteEliminar = null;
            }
        });
    });

});

// ── Funciones globales (invocadas vía onclick inline desde DataTables) ──────

function abrirEditarAyuda(ayud_id) {
    $.ajax({
        type: 'POST',
        url: 'ayud_mdl.php?accion=obtener',
        data: { ayud_id: ayud_id },
        dataType: 'json',
        success: function (r) {
            if (r.status !== 'ok') { alert('Error al cargar'); return; }
            const d = r.data;
            $('#ayud_id').val(d.ayud_id);
            $('#slct_ayud_seccion').val(d.ayud_seccion);
            $('#ayud_titulo').val(d.ayud_titulo);
            $('#ayud_contenido').val(d.ayud_contenido);
            $('#ayud_orden').val(d.ayud_orden);
            $('#slct_ayud_activo').val(d.ayud_activo);
            $('#bloque_activo_ayuda').removeClass('d-none');
            $('#mdl_ayuda_titulo').text('Editar Ayuda');
            new bootstrap.Modal('#mdl_ayuda').show();
        }
    });
}

function confirmarEliminarAyuda(ayud_id, titulo) {
    ayudaIdPendienteEliminar = ayud_id;
    $('#spn_titulo_eliminar_ayuda').text(titulo);
    new bootstrap.Modal('#mdl_confirmar_eliminar_ayuda').show();
}
