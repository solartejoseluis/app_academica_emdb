$(document).ready(function () {

    let tablaReporte = null;

    if ($('#sel_modulo').length) cargarModulos();
    if ($('#sel_grupo').length)  cargarGrupos();

    // ── Carga selects al iniciar ─────────────────────────────────────────────

    function cargarModulos() {
        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=mis_modulos',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                const sel = $('#sel_modulo');
                r.data.forEach(function (m) {
                    sel.append($('<option>', {
                        value: m.grmo_id,
                        text:  m.grse_codigo + ' — ' + m.modu_sigla + ' ' + m.modu_nombre
                    }));
                });
            }
        });
    }

    function cargarGrupos() {
        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=grupos_para_reporte',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                const sel = $('#sel_grupo');
                r.data.forEach(function (g) {
                    sel.append($('<option>', {
                        value: g.grmo_id,
                        text:  g.grse_codigo + ' — ' + g.modu_sigla + ' — ' +
                               g.doce_apellidos + ' (' + g.total_estudiantes + ' estudiantes)'
                    }));
                });
            }
        });
    }

    // ── Ver notas del estudiante (role 4) ────────────────────────────────────

    $('#btn_ver_notas').on('click', function () {
        const grmo_id = $('#sel_modulo').val();
        if (!grmo_id) { alert('Selecciona un módulo'); return; }

        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=mis_notas',
            data: { grmo_id: grmo_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }

                const tbody = $('#tbody_mis_notas');
                tbody.empty();

                if (!r.data || !r.data.length) {
                    tbody.html('<tr><td colspan="9" class="text-center text-muted">Sin datos de calificaciones</td></tr>');
                    $('#info_grupo').hide();
                    return;
                }

                const d = r.data[0];
                $('#spn_modulo_nombre').text(d.modu_sigla + ' — ' + d.modu_nombre);
                $('#spn_grupo_codigo').text(d.grse_codigo);
                $('#spn_docente').text(d.doce_apellidos + ', ' + d.doce_nombres);
                $('#info_grupo').show();

                tbody.append(construirFilaEstudiante(d));
            }
        });
    });

    // ── Cargar reporte de grupo (roles 1 y 2) ────────────────────────────────

    $('#btn_cargar_reporte').on('click', function () {
        const grmo_id = $('#sel_grupo').val();
        if (!grmo_id) { alert('Selecciona un grupo'); return; }

        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=reporte_grupo',
            data: { grmo_id: grmo_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }

                if (tablaReporte !== null) {
                    tablaReporte.destroy();
                    tablaReporte = null;
                }

                const tbody = $('#tbody_reporte');
                tbody.empty();

                if (!r.data.length) {
                    tbody.html('<tr><td colspan="14" class="text-center text-muted">Sin estudiantes en este grupo</td></tr>');
                    return;
                }

                r.data.forEach(function (e, idx) {
                    tbody.append(construirFilaReporte(e, idx + 1));
                });

                tablaReporte = $('#tbl_reporte').DataTable({
                    destroy: true,
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    dom: 'Bfrtip',
                    buttons: ['excel'],
                    pageLength: 25,
                    columnDefs: [{ orderable: false, targets: [11, 12] }]
                });
            }
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────────

    function n(v) {
        return (v !== null && v !== undefined) ? v : '—';
    }

    function badgeDefinitiva(def) {
        if (def === null || def === undefined) {
            return '<span class="badge bg-secondary">—</span>';
        }
        const cls = parseFloat(def) >= 3.0 ? 'bg-success' : 'bg-danger';
        return '<span class="badge ' + cls + '">' + def + '</span>';
    }

    function badgeEstado(def) {
        if (def === null || def === undefined) {
            return '<span class="badge bg-secondary">En curso</span>';
        }
        return parseFloat(def) >= 3.0
            ? '<span class="badge bg-success">Aprobado</span>'
            : '<span class="badge bg-danger">Reprobado</span>';
    }

    function construirFilaEstudiante(d) {
        return `<tr>
            <td class="text-center">${n(d.cali_n1)}</td>
            <td class="text-center">${n(d.cali_sup_n1)}</td>
            <td class="text-center">${n(d.cali_n2)}</td>
            <td class="text-center">${n(d.cali_sup_n2)}</td>
            <td class="text-center">${n(d.cali_n3)}</td>
            <td class="text-center">${n(d.cali_n4)}</td>
            <td class="text-center">${n(d.cali_sup_n4)}</td>
            <td class="text-center">${badgeDefinitiva(d.cali_definitiva)}</td>
            <td class="text-center">${badgeEstado(d.cali_definitiva)}</td>
        </tr>`;
    }

    function construirFilaReporte(e, num) {
        return `<tr>
            <td>${num}</td>
            <td>${e.estu_apellidos}</td>
            <td>${e.estu_nombres}</td>
            <td>${e.estu_numerodoc}</td>
            <td class="text-center">${n(e.cali_n1)}</td>
            <td class="text-center">${n(e.cali_sup_n1)}</td>
            <td class="text-center">${n(e.cali_n2)}</td>
            <td class="text-center">${n(e.cali_sup_n2)}</td>
            <td class="text-center">${n(e.cali_n3)}</td>
            <td class="text-center">${n(e.cali_n4)}</td>
            <td class="text-center">${n(e.cali_sup_n4)}</td>
            <td class="text-center">${badgeDefinitiva(e.cali_definitiva)}</td>
            <td class="text-center">${badgeEstado(e.cali_definitiva)}</td>
            <td>${n(e.cali_observacion)}</td>
        </tr>`;
    }

});
