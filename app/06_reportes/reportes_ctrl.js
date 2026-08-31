$(document).ready(function () {

    let estudianteActualId = null;
    let tablaReporte = null;

    // ── Helpers de formato (idénticos a la versión anterior de este archivo,
    //    reutilizados sin cambios de lógica) ──────────────────────────────────

    function n(v) {
        return (v !== null && v !== undefined) ? v : '—';
    }

    function badgeNotaFinal(notaFinal) {
        if (notaFinal === null || notaFinal === undefined) {
            return '<span class="badge bg-secondary">—</span>';
        }
        const cls = parseFloat(notaFinal) >= 3.0 ? 'bg-success' : 'bg-danger';
        return '<span class="badge ' + cls + '">' + notaFinal + '</span>';
    }

    // Muestra cali_definitiva; si aún no hay definitiva oficial pero ya existe
    // cali_nota_final (reprobó, sin habilitación registrada), muestra ese
    // resultado bruto en vez de ocultarlo con '—'.
    function badgeDefinitiva(d) {
        if (d.cali_definitiva !== null && d.cali_definitiva !== undefined) {
            const cls = parseFloat(d.cali_definitiva) >= 3.0 ? 'bg-success' : 'bg-danger';
            return '<span class="badge ' + cls + '">' + d.cali_definitiva + '</span>';
        }
        if (d.cali_nota_final !== null && d.cali_nota_final !== undefined) {
            const cls = parseFloat(d.cali_nota_final) >= 3.0 ? 'bg-success' : 'bg-danger';
            return '<span class="badge ' + cls + '">' + d.cali_nota_final + '</span>';
        }
        return '<span class="badge bg-secondary">—</span>';
    }

    // 3 casos: sin nota_final aún (en curso, con o sin notas parciales),
    // nota_final calculada pero definitiva null (reprobó, pendiente habilitación),
    // o definitiva ya oficial (aprobado o reprobado definitivo).
    function badgeEstado(d) {
        if (d.cali_definitiva !== null && d.cali_definitiva !== undefined) {
            return parseFloat(d.cali_definitiva) >= 3.0
                ? '<span class="badge bg-success">Aprobado</span>'
                : '<span class="badge bg-danger">Reprobado</span>';
        }
        if (d.cali_nota_final !== null && d.cali_nota_final !== undefined) {
            return '<span class="badge bg-danger">Reprobado — pendiente habilitación</span>';
        }
        return '<span class="badge bg-secondary">En curso</span>';
    }

    // ── Helpers nuevos de esta Fase 3 (mismo esquema de color que los de arriba) ─

    function badgeEstadoAcademico(valor) {
        const mapa = { 'Activo': 'bg-success', 'Aplazamiento': 'bg-warning text-dark', 'Retirado': 'bg-secondary' };
        const cls = mapa[valor] || 'bg-secondary';
        return '<span class="badge ' + cls + '">' + (valor || '—') + '</span>';
    }

    // Estado agregado del período, ya calculado por el backend
    // (detalle_periodo / pdf_boletin.php) — mismo esquema de color que
    // badgeEstado() de arriba (verde/rojo/gris), aplicado a un texto en vez
    // de a un par nota/definitiva.
    function badgeEstadoPeriodo(estado, promedio) {
        let cls = 'bg-secondary';
        if (estado === 'Aprobado') cls = 'bg-success';
        else if (estado === 'Reprobado') cls = 'bg-danger';
        let texto = estado;
        if (promedio !== null && promedio !== undefined) {
            texto += ' (Promedio: ' + promedio + ')';
        }
        return '<span class="badge ' + cls + '">' + texto + '</span>';
    }

    // El backend (mis_periodos) entrega los períodos del más reciente al más
    // antiguo. "Períodos realizados" = cuántos ya se cursaron ANTES del
    // actual = invertir la lista, ubicar la posición del período más
    // reciente en ella y restar 1 — que algebraicamente equivale a
    // (total de períodos - 1). Si el resultado es 0 (el actual es el único
    // período cursado), se muestra "en curso" en vez de "0".
    function calcularPeriodosRealizados(periodos) {
        const n = periodos.length - 1;
        return n === 0 ? 'en curso' : String(n);
    }

    function construirUrlBoletin(matr_id, peri_id) {
        let url = 'pdf_boletin.php?matr_id=' + matr_id + '&peri_id=' + peri_id;
        if (ES_COORDINADOR && estudianteActualId) {
            url += '&estu_id=' + estudianteActualId;
        }
        return url;
    }

    function construirBloqueModulo(m) {
        return `<div class="card mb-3">
            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                <div><strong>Módulo:</strong> ${m.modu_sigla} — ${m.modu_nombre} &nbsp; <strong>Grupo:</strong> ${m.grse_codigo}</div>
                <div><strong>Docente:</strong> ${m.doce_apellidos}, ${m.doce_nombres}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm text-center mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>N1<br><small class="fw-normal">20%</small></th>
                            <th>Sup N1</th>
                            <th>N2<br><small class="fw-normal">20%</small></th>
                            <th>Sup N2</th>
                            <th>N3<br><small class="fw-normal">20%</small></th>
                            <th>N4<br><small class="fw-normal">40%</small></th>
                            <th>Sup N4</th>
                            <th>Habilitación</th>
                            <th>Nota Final</th>
                            <th>Definitiva</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>${n(m.cali_n1)}</td>
                            <td>${n(m.cali_sup_n1)}</td>
                            <td>${n(m.cali_n2)}</td>
                            <td>${n(m.cali_sup_n2)}</td>
                            <td>${n(m.cali_n3)}</td>
                            <td>${n(m.cali_n4)}</td>
                            <td>${n(m.cali_sup_n4)}</td>
                            <td>${n(m.cali_habilitacion)}</td>
                            <td>${badgeNotaFinal(m.cali_nota_final)}</td>
                            <td>${badgeDefinitiva(m)}</td>
                            <td>${badgeEstado(m)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>`;
    }

    // ── Carga de datos (AJAX) ────────────────────────────────────────────────

    function cargarDetallePeriodo(matr_id, peri_id, $pane) {
        const data = { matr_id: matr_id, peri_id: peri_id };
        if (ES_COORDINADOR) data.estu_id = estudianteActualId;

        $pane.find('.contenido-periodo').html('<div class="text-muted">Cargando...</div>');

        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=detalle_periodo',
            data: data,
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }

                const urlPdf = construirUrlBoletin(matr_id, peri_id);
                let html = `<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div><strong>Estado del Período:</strong> ${badgeEstadoPeriodo(r.estado_periodo, r.promedio_periodo)}</div>
                    <a href="${urlPdf}" target="_blank" class="btn btn-outline-danger btn-sm">📄 Descargar Boletín PDF</a>
                </div>`;

                if (!r.data.length) {
                    html += '<div class="text-muted">Sin módulos asignados en este período.</div>';
                } else {
                    r.data.forEach(function (m) { html += construirBloqueModulo(m); });
                }

                $pane.find('.contenido-periodo').html(html);
            }
        });
    }

    function cargarPeriodos(matr_id, $pane) {
        const data = { matr_id: matr_id };
        if (ES_COORDINADOR) data.estu_id = estudianteActualId;

        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=mis_periodos',
            data: data,
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }

                $pane.data('periodosLoaded', true);

                const $sel = $pane.find('.sel-periodo');
                $sel.html('<option value="">— Seleccione un período —</option>');
                r.data.forEach(function (p) {
                    $sel.append($('<option>', { value: p.peri_id, text: p.peri_codigo }));
                });

                $pane.find('.periodos-realizados').text(calcularPeriodosRealizados(r.data));

                if (r.data.length) {
                    // El más reciente es el primero (mis_periodos ya lo
                    // entrega ordenado DESC) — se preselecciona por defecto.
                    const masReciente = r.data[0];
                    $sel.val(masReciente.peri_id);
                    cargarDetallePeriodo(matr_id, masReciente.peri_id, $pane);
                } else {
                    $pane.find('.contenido-periodo').html('<div class="text-muted">Sin períodos cursados todavía.</div>');
                }
            }
        });
    }

    function cargarProgramas() {
        const data = ES_COORDINADOR ? { estu_id: estudianteActualId } : {};

        $.ajax({
            type: 'POST',
            url: 'reportes_mdl.php?accion=mis_programas',
            data: data,
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }

                const $tabs = $('#tabsProgramas').empty();
                const $content = $('#contenidoProgramas').empty();

                if (!r.data.length) {
                    $('#bloque_sin_programas').removeClass('d-none');
                    return;
                }
                $('#bloque_sin_programas').addClass('d-none');

                r.data.forEach(function (matr, idx) {
                    const activo = idx === 0;
                    const etiqueta = matr.prog_sigla || matr.prog_nombre;

                    $tabs.append(`<li class="nav-item">
                        <button class="nav-link${activo ? ' active' : ''}" data-bs-toggle="tab"
                                data-bs-target="#tab_prog_${matr.matr_id}" type="button">${etiqueta}</button>
                    </li>`);

                    const $pane = $(`<div class="tab-pane fade${activo ? ' show active' : ''}" id="tab_prog_${matr.matr_id}"></div>`);
                    $pane.data('matrId', matr.matr_id);
                    $pane.html(`
                        <div class="row mb-3 g-2">
                            <div class="col-md-4"><strong>Programa:</strong> ${matr.prog_nombre}</div>
                            <div class="col-md-3"><strong>Cohorte:</strong> ${matr.coho_codigo || '—'}</div>
                            <div class="col-md-3"><strong>Períodos realizados:</strong> <span class="periodos-realizados">—</span></div>
                            <div class="col-md-2"><strong>Estado actual:</strong> ${badgeEstadoAcademico(matr.matr_estado_academico)}</div>
                        </div>
                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Período</label>
                                <select class="form-select sel-periodo">
                                    <option value="">— Seleccione un período —</option>
                                </select>
                            </div>
                        </div>
                        <div class="contenido-periodo">
                            <div class="text-muted">Cargando períodos...</div>
                        </div>
                    `);
                    $content.append($pane);
                });

                // La primera pestaña ya queda activa por CSS (show active) —
                // se carga de una vez, sin esperar shown.bs.tab (que solo
                // dispara al CAMBIAR de pestaña, nunca en el estado inicial).
                const primerMatrId = r.data[0].matr_id;
                cargarPeriodos(primerMatrId, $content.find('.tab-pane').first());
            }
        });
    }

    function resolverEstudiante(estu_id, nombreCompleto) {
        estudianteActualId = estu_id;
        $('#spn_nombre_estudiante_titulo').text(nombreCompleto);
        $('#spn_estudiante_elegido_nombre').text(nombreCompleto);
        $('#bloque_estudiante_elegido').removeClass('d-none').addClass('d-flex');
        $('#bloque_buscador_estudiante').addClass('d-none');
        $('#bloque_reporte').removeClass('d-none');
        cargarProgramas();
    }

    // ── Reporte por Grupo — recuperado tal cual del commit ef429bd (Fase 2),
    //    intacto sin modificar su lógica interna ─────────────────────────────

    function inicialDocente(nombres) {
        return nombres.trim().split(' ')[0].charAt(0).toUpperCase();
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
                        text:  g.grse_codigo + ' — ' + g.modu_sigla + ' ' + g.modu_nombre + ' — ' +
                               inicialDocente(g.doce_nombres) + '.' + g.doce_apellidos +
                               ' (' + g.total_estudiantes + ' est)'
                    }));
                });
            }
        });
    }

    function construirContextoTexto(g) {
        if (!g) return { linea1: '', linea2: '' };
        return {
            linea1: 'Módulo: ' + g.modu_nombre + ' (' + g.modu_sigla + ') — Grupo: ' + g.grse_codigo + ' — Docente: ' + g.doce_nombres + ' ' + g.doce_apellidos,
            linea2: 'Programa: ' + g.prog_nombre + ' — Período: ' + g.peri_codigo + ' — Jornada: ' + (g.grse_jornada || '—')
        };
    }

    // Escapa & < > para insertarlos como texto plano dentro de un <t> XML
    // del sheet1.xml exportado (nombres de docente/módulo son texto libre).
    function escaparXml(texto) {
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function mostrarInfoReporteGrupo(g) {
        if (!g) { $('#info_reporte_grupo').hide(); return; }
        $('#spn_ctx_modulo').text(g.modu_sigla + ' — ' + g.modu_nombre);
        $('#spn_ctx_grupo').text(g.grse_codigo);
        $('#spn_ctx_docente').text(g.doce_apellidos + ', ' + g.doce_nombres);
        $('#spn_ctx_programa').text(g.prog_nombre + ' (' + g.prog_sigla + ')');
        $('#spn_ctx_periodo').text(g.peri_codigo);
        $('#spn_ctx_jornada').text(g.grse_jornada || '—');
        $('#info_reporte_grupo').show();
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
            <td class="text-center">${badgeNotaFinal(e.cali_nota_final)}</td>
            <td class="text-center">${badgeDefinitiva(e)}</td>
            <td class="text-center">${badgeEstado(e)}</td>
            <td>${n(e.cali_observacion)}</td>
        </tr>`;
    }

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

                const contexto = construirContextoTexto(r.grupo);
                mostrarInfoReporteGrupo(r.grupo);

                const tbody = $('#tbody_reporte');
                tbody.empty();

                if (!r.data.length) {
                    tbody.html('<tr><td colspan="15" class="text-center text-muted">Sin estudiantes en este grupo</td></tr>');
                    return;
                }

                r.data.forEach(function (e, idx) {
                    tbody.append(construirFilaReporte(e, idx + 1));
                });

                tablaReporte = $('#tbl_reporte').DataTable({
                    destroy: true,
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
                    dom: 'Bfrtip',
                    buttons: [
                        {
                            extend: 'excel',
                            customize: function (xlsx) {
                                const sheet = xlsx.xl.worksheets['sheet1.xml'];

                                const fila1 = '<row r="1"><c r="A1" t="inlineStr"><is><t>' +
                                    escaparXml(contexto.linea1) + '</t></is></c></row>';
                                const fila2 = '<row r="2"><c r="A2" t="inlineStr"><is><t>' +
                                    escaparXml(contexto.linea2) + '</t></is></c></row>';

                                $('row', sheet).eq(0).before(fila1 + fila2);

                                // Reindexar TODAS las filas (las 2 nuevas + las que ya
                                // existían) — cada <row r="N"> y cada <c r="colN"> deben
                                // quedar consistentes tras el corrimiento de 2 posiciones.
                                $('row', sheet).each(function (i) {
                                    const nuevaFila = i + 1;
                                    $(this).attr('r', nuevaFila);
                                    $('c', this).each(function () {
                                        const col = $(this).attr('r').replace(/[0-9]/g, '');
                                        $(this).attr('r', col + nuevaFila);
                                    });
                                });

                                // Actualizar el rango declarado de la hoja (dimension)
                                // para reflejar las 2 filas nuevas al inicio.
                                const filas = $('row', sheet);
                                const ultimaCelda = $('c', filas.eq(filas.length - 1)).last().attr('r');
                                if (ultimaCelda) {
                                    $('dimension', sheet).attr('ref', 'A1:' + ultimaCelda);
                                }
                            }
                        },
                        {
                            text: '📄 Descargar PDF',
                            className: 'btn btn-outline-danger btn-sm',
                            action: function () {
                                window.open('pdf_grupo.php?grmo_id=' + grmo_id, '_blank');
                            }
                        }
                    ],
                    pageLength: 25,
                    columnDefs: [{ orderable: false, targets: [11, 12, 13] }]
                });
            }
        });
    });

    // ── Cambio de pestaña de programa: carga períodos la primera vez ────────

    $(document).on('shown.bs.tab', '#tabsProgramas button[data-bs-toggle="tab"]', function () {
        const $pane = $($(this).data('bs-target'));
        if (!$pane.data('periodosLoaded')) {
            cargarPeriodos($pane.data('matrId'), $pane);
        }
    });

    // ── Cambio de período ────────────────────────────────────────────────────

    $(document).on('change', '.sel-periodo', function () {
        const $pane = $(this).closest('.tab-pane');
        const peri_id = $(this).val();
        const matr_id = $pane.data('matrId');
        if (!peri_id) {
            $pane.find('.contenido-periodo').html('<div class="text-muted">Seleccione un período para ver el detalle.</div>');
            return;
        }
        cargarDetallePeriodo(matr_id, peri_id, $pane);
    });

    // ── Arranque ─────────────────────────────────────────────────────────────

    // El select de grupo vive dentro de la pestaña "Reporte por Grupo", que
    // existe en el DOM (aunque no esté activa) para roles 1/2 — se carga
    // siempre que el elemento exista, igual que el resto de selects de este
    // archivo.
    if ($('#sel_grupo').length) cargarGrupos();

    if (ES_COORDINADOR) {

        let debounceTimer = null;

        function buscarEstudiante(termino) {
            $.ajax({
                type: 'POST',
                url: 'reportes_mdl.php?accion=buscar_estudiante',
                data: { termino: termino },
                dataType: 'json',
                success: function (r) {
                    const $lista = $('#lista_resultados_estudiante').empty();
                    if (r.status !== 'ok' || !r.data.length) {
                        $lista.append('<div class="list-group-item text-muted">Sin resultados</div>').show();
                        return;
                    }
                    r.data.forEach(function (est) {
                        const nombreCompleto = est.estu_apellidos + ', ' + est.estu_nombres;
                        $lista.append(
                            $('<button>', {
                                type: 'button',
                                class: 'list-group-item list-group-item-action',
                                text: nombreCompleto + ' — ' + est.estu_tipodoc + ' ' + est.estu_numerodoc
                            }).data('estuId', est.estu_id).data('nombreCompleto', nombreCompleto)
                        );
                    });
                    $lista.show();
                }
            });
        }

        $('#npt_buscar_estudiante').on('input', function () {
            clearTimeout(debounceTimer);
            const termino = $(this).val().trim();
            if (termino.length < 3) {
                $('#lista_resultados_estudiante').hide().empty();
                return;
            }
            debounceTimer = setTimeout(function () { buscarEstudiante(termino); }, 300);
        });

        $(document).on('click', '#lista_resultados_estudiante button', function () {
            const estu_id = $(this).data('estuId');
            const nombreCompleto = $(this).data('nombreCompleto');
            $('#lista_resultados_estudiante').hide().empty();
            $('#npt_buscar_estudiante').val('');
            resolverEstudiante(estu_id, nombreCompleto);
        });

        $('#btn_cambiar_estudiante').on('click', function () {
            estudianteActualId = null;
            $('#bloque_reporte').addClass('d-none');
            $('#bloque_estudiante_elegido').addClass('d-none').removeClass('d-flex');
            $('#bloque_buscador_estudiante').removeClass('d-none');
            $('#tabsProgramas').empty();
            $('#contenidoProgramas').empty();
            $('#spn_nombre_estudiante_titulo').text('—');
            $('#npt_buscar_estudiante').val('').focus();
        });

    } else {
        // Estudiante (role 4): el estu_id ya se resolvió en sesión — pasa
        // directo al reporte, sin buscador.
        cargarProgramas();
    }

});
