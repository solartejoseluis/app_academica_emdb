$(document).ready(function () {
    cargarDashboard();
});

// Texto y clase del badge por estado_notas.
const ESTADOS_NOTAS = {
    completo:        { texto: 'Completo',        clase: 'bg-success' },
    parcial:         { texto: 'Parcial',         clase: 'bg-warning text-dark' },
    pendiente:       { texto: 'Pendiente',       clase: 'bg-danger' },
    sin_estudiantes: { texto: 'Sin estudiantes', clase: 'bg-secondary' }
};

// Todo texto que viene de la base pasa por aquí antes de insertarse en HTML.
function escaparHtml(texto) {
    return String(texto === null || texto === undefined ? '' : texto)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Columna de texto de la base: escapada al mostrar, cruda para ordenar/filtrar.
function renderTexto(data, type) {
    return type === 'display' ? escaparHtml(data) : data;
}

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

            // Única inicialización, ya con los datos (sin tabla vacía previa).
            $('#tbl_estado_notas').DataTable({
                destroy: true,
                data: r.data.grupos,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                    emptyTable: 'Sin grupos activos'
                },
                pageLength: 25,
                // Más reciente arriba; sin fecha al final (tipo fecha-emdb).
                order: [[7, 'desc'], [0, 'asc']],
                columns: [
                    { data: 'grse_codigo', render: renderTexto },
                    {
                        data: null,
                        render: function (data, type, row) {
                            return renderTexto(row.modu_sigla + ' — ' + row.modu_nombre, type);
                        }
                    },
                    { data: 'docente', render: renderTexto },
                    { data: 'total_estudiantes', className: 'text-center' },
                    { data: 'con_notas', className: 'text-center' },
                    { data: 'con_definitiva', className: 'text-center' },
                    {
                        data: 'estado_notas',
                        className: 'text-center',
                        render: function (data, type) {
                            const estado = ESTADOS_NOTAS[data] || { texto: '—', clase: 'bg-secondary' };
                            if (type !== 'display') return estado.texto;
                            return `<span class="badge ${estado.clase}">${estado.texto}</span>`;
                        }
                    },
                    {
                        data: 'ultima_actualizacion',
                        className: 'text-center',
                        type: 'fecha-emdb',
                        render: renderFecha
                    },
                    {
                        data: 'grmo_id',
                        className: 'text-center',
                        orderable: false,
                        searchable: false,
                        render: function (data) {
                            return `<a href="../05_calificaciones/calificaciones_view.php?grmo_id=${encodeURIComponent(data)}"
                                       class="btn btn-sm btn-outline-primary">
                                        ✏️ Ver Notas
                                    </a>`;
                        }
                    }
                ]
            });
        },
        error: function () {
            alert('Error de conexión al cargar el dashboard.');
        }
    });
}
