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

// Filtros Período/Programa/Docente: se aplican en el cliente y solo a
// #tbl_estado_notas. Se registra una sola vez, al cargar este archivo; lee los
// selects en cada draw(), así que la tabla nace ya filtrada.
$.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex, fila) {
    if (settings.nTable.id !== 'tbl_estado_notas') return true;
    const peri_id = $('#slct_filtro_peri_id').val();
    const prog_id = $('#slct_filtro_prog_id').val();
    const doce_id = $('#slct_filtro_doce_id').val();
    return (!peri_id || String(fila.peri_id) === peri_id)
        && (!prog_id || String(fila.prog_id) === prog_id)
        && (!doce_id || String(fila.doce_id) === doce_id);
});

// Las opciones salen de TODAS las filas recibidas (los tres filtros son
// independientes). El período arranca en el activo, o en "Todos" si no hay.
// poblarFiltro() vive en 00_files/helpers.js.
function poblarFiltros(grupos) {
    const alfabetico = function (a, b) { return String(a.texto).localeCompare(String(b.texto), 'es'); };
    poblarFiltro('#slct_filtro_peri_id', grupos, 'peri_id', 'peri_codigo', function (a, b) { return alfabetico(b, a); });
    poblarFiltro('#slct_filtro_prog_id', grupos, 'prog_id', 'prog_sigla', alfabetico);
    poblarFiltro('#slct_filtro_doce_id', grupos, 'doce_id', 'docente', alfabetico);

    const activo = grupos.find(function (g) { return g.peri_activo == 1; });
    $('#slct_filtro_peri_id').val(activo ? String(activo.peri_id) : '');
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

            // Antes de crear la tabla, para que el primer draw ya salga filtrado.
            poblarFiltros(r.data.grupos);

            // Única inicialización, ya con los datos (sin tabla vacía previa).
            $('#tbl_estado_notas').DataTable({
                destroy: true,
                data: r.data.grupos,
                // El change se engancha con la tabla ya lista: un cambio hecho
                // antes igual lo recoge el primer draw.
                initComplete: function () {
                    const tabla = this.api();
                    $('#slct_filtro_peri_id, #slct_filtro_prog_id, #slct_filtro_doce_id').on('change', function () {
                        tabla.draw();
                    });
                },
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
                                       class="btn btn-sm btn-outline-primary"
                                       target="_blank" rel="noopener">
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
