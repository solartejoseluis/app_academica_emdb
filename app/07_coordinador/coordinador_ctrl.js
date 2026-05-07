$(document).ready(function () {
    cargarDashboard();
});

function cargarDashboard() {
    $.ajax({
        type: 'POST',
        url: 'coordinador_mdl.php?accion=resumen_dashboard',
        dataType: 'json',
        success: function (r) {
            if (r.status !== 'ok') {
                alert('Error al cargar el dashboard: ' + r.message);
                return;
            }

            const c = r.data.conteos;
            $('#cnt_estudiantes').text(c.total_estudiantes);
            $('#cnt_docentes').text(c.total_docentes);
            $('#cnt_grupos').text(c.total_grupos);

            const tbody = $('#tbody_estado_notas');
            tbody.empty();

            if (!r.data.grupos.length) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted">Sin grupos activos</td></tr>');
                return;
            }

            r.data.grupos.forEach(function (g) {
                tbody.append(construirFilaEstado(g));
            });

            $('#tbl_estado_notas').DataTable({
                destroy: true,
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                pageLength: 25,
                columnDefs: [{ orderable: false, targets: [6, 7] }]
            });
        },
        error: function () {
            alert('Error de conexión al cargar el dashboard.');
        }
    });
}

function construirFilaEstado(g) {
    const badges = {
        completo:        '<span class="badge bg-success">Completo</span>',
        parcial:         '<span class="badge bg-warning text-dark">Parcial</span>',
        pendiente:       '<span class="badge bg-danger">Pendiente</span>',
        sin_estudiantes: '<span class="badge bg-secondary">Sin estudiantes</span>'
    };
    const badge = badges[g.estado_notas] || '<span class="badge bg-secondary">—</span>';

    return `<tr>
        <td>${g.grse_codigo}</td>
        <td>${g.modu_sigla} — ${g.modu_nombre}</td>
        <td>${g.docente}</td>
        <td class="text-center">${g.total_estudiantes}</td>
        <td class="text-center">${g.con_notas}</td>
        <td class="text-center">${g.con_definitiva}</td>
        <td class="text-center">${badge}</td>
        <td class="text-center">
            <a href="../05_calificaciones/calificaciones_view.php?grmo_id=${g.grmo_id}"
               class="btn btn-sm btn-outline-primary">
                ✏️ Ver Notas
            </a>
        </td>
    </tr>`;
}
