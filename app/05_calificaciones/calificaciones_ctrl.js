$(document).ready(function () {

    let grmo_id_activo = null;

    // ── Filtros de grupos (solo Coordinador/Admin) ───────────────────────────
    const bloqueFiltros = $('#bloque_filtros_grupos');

    if (bloqueFiltros.length) {
        poblarFiltros();
        $('#slct_filtro_doce_id, #slct_filtro_prog_id, #slct_filtro_peri_id').on('change', function () {
            cargarGrupos();
        });
    }

    function poblarFiltros() {
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_docentes_filtro',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                const slct = $('#slct_filtro_doce_id');
                r.data.forEach(d => {
                    slct.append(`<option value="${d.doce_id}">${d.doce_apellidos}, ${d.doce_nombres}</option>`);
                });
            }
        });
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_programas_filtro',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                const slct = $('#slct_filtro_prog_id');
                r.data.forEach(p => {
                    slct.append(`<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`);
                });
            }
        });
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_periodos_filtro',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                const slct = $('#slct_filtro_peri_id');
                r.data.forEach(pe => {
                    slct.append(`<option value="${pe.peri_id}">${pe.peri_codigo}</option>`);
                });
            }
        });
    }

    // ── Cargar lista de grupos al inicio ────────────────────────────────────
    cargarGrupos();

    function cargarGrupos() {
        const filtros = {};
        if (bloqueFiltros.length) {
            filtros.doce_id = $('#slct_filtro_doce_id').val();
            filtros.prog_id = $('#slct_filtro_prog_id').val();
            filtros.peri_id = $('#slct_filtro_peri_id').val();
        }
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_grupos',
            data: filtros,
            dataType: 'json',
            success: function (r) {
                const contenedor = $('#lista_grupos');
                contenedor.empty();
                if (!r.data || !r.data.length) {
                    contenedor.html('<p class="text-muted small">Sin módulos asignados</p>');
                    return;
                }
                r.data.forEach(g => {
                    const docente = g.doce_nombres
                        ? `<div class="text-muted" style="font-size:0.75em">${g.doce_apellidos}, ${g.doce_nombres}</div>`
                        : '';
                    const card = $(`
                        <div class="card grupo-card mb-2 p-2" data-grmo="${g.grmo_id}">
                            <div class="fw-bold" style="font-size:0.9em">${g.modu_sigla} — ${g.modu_nombre}</div>
                            <div class="text-muted" style="font-size:0.78em">
                                ${g.coho_codigo} · Sem.${g.grse_semestre} · ${g.peri_codigo}
                            </div>
                            ${docente}
                            <div style="font-size:0.78em">
                                <span class="badge bg-info text-dark">${g.total_estudiantes} estudiantes</span>
                            </div>
                        </div>
                    `);
                    contenedor.append(card);
                });

                const params = new URLSearchParams(window.location.search);
                const grmoParam = params.get('grmo_id');
                if (grmoParam) {
                    const cardTarget = $('.grupo-card[data-grmo="' + grmoParam + '"]');
                    if (cardTarget.length) {
                        cardTarget.trigger('click');
                    }
                }
            }
        });
    }

    // ── Click en card de grupo ───────────────────────────────────────────────
    $(document).on('click', '.grupo-card', function () {
        $('.grupo-card').removeClass('activo');
        $(this).addClass('activo');
        grmo_id_activo = $(this).data('grmo');
        cargarCalificaciones(grmo_id_activo);
    });

    // ── Exportación de reporte del grupo (Excel / PDF) — reutiliza grmo_id_activo ──
    let tablaExportGrupo = null;

    function nExp(v) {
        return (v !== null && v !== undefined) ? v : '—';
    }
    function textoNotaFinalExp(notaFinal) {
        return (notaFinal === null || notaFinal === undefined) ? '—' : notaFinal;
    }
    function textoDefinitivaExp(d) {
        if (d.cali_definitiva !== null && d.cali_definitiva !== undefined) return d.cali_definitiva;
        if (d.cali_nota_final !== null && d.cali_nota_final !== undefined) return d.cali_nota_final;
        return '—';
    }
    function textoEstadoExp(d) {
        if (d.cali_definitiva !== null && d.cali_definitiva !== undefined) {
            return parseFloat(d.cali_definitiva) >= 3.0 ? 'Aprobado' : 'Reprobado';
        }
        if (d.cali_nota_final !== null && d.cali_nota_final !== undefined) {
            return 'Reprobado — pendiente habilitación';
        }
        return 'En curso';
    }
    function construirContextoTextoExp(g) {
        if (!g) return { linea1: '', linea2: '' };
        return {
            linea1: 'Módulo: ' + g.modu_nombre + ' (' + g.modu_sigla + ') — Grupo: ' + g.grse_codigo + ' — Docente: ' + g.doce_nombres + ' ' + g.doce_apellidos,
            linea2: 'Programa: ' + g.prog_nombre + ' — Período: ' + g.peri_codigo + ' — Jornada: ' + (g.grse_jornada || '—')
        };
    }
    function escaparXmlExp(texto) {
        return String(texto).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    function construirFilaExport(e, num) {
        return '<tr>' +
            '<td>' + num + '</td>' +
            '<td>' + e.estu_apellidos + '</td>' +
            '<td>' + e.estu_nombres + '</td>' +
            '<td>' + e.estu_numerodoc + '</td>' +
            '<td>' + nExp(e.cali_n1) + '</td>' +
            '<td>' + nExp(e.cali_sup_n1) + '</td>' +
            '<td>' + nExp(e.cali_n2) + '</td>' +
            '<td>' + nExp(e.cali_sup_n2) + '</td>' +
            '<td>' + nExp(e.cali_n3) + '</td>' +
            '<td>' + nExp(e.cali_n4) + '</td>' +
            '<td>' + nExp(e.cali_sup_n4) + '</td>' +
            '<td>' + textoNotaFinalExp(e.cali_nota_final) + '</td>' +
            '<td>' + textoDefinitivaExp(e) + '</td>' +
            '<td>' + textoEstadoExp(e) + '</td>' +
            '<td>' + nExp(e.cali_observacion) + '</td>' +
        '</tr>';
    }

    $('#btn_pdf_grupo').on('click', function () {
        if (!grmo_id_activo) { alert('Selecciona un grupo'); return; }
        window.open('../06_reportes/pdf_grupo.php?grmo_id=' + grmo_id_activo, '_blank');
    });

    $('#btn_excel_grupo').on('click', function () {
        if (!grmo_id_activo) { alert('Selecciona un grupo'); return; }
        $.ajax({
            type: 'POST',
            url: '../06_reportes/reportes_mdl.php?accion=reporte_grupo',
            data: { grmo_id: grmo_id_activo },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') { alert('Error: ' + r.message); return; }
                const contexto = construirContextoTextoExp(r.grupo);
                const tbody = $('#tbody_export_grupo');
                tbody.empty();
                if (!r.data.length) {
                    tbody.html('<tr><td colspan="15" class="text-center text-muted">Sin estudiantes en este grupo</td></tr>');
                } else {
                    r.data.forEach(function (e, idx) {
                        tbody.append(construirFilaExport(e, idx + 1));
                    });
                }
                if (tablaExportGrupo !== null) {
                    tablaExportGrupo.destroy();
                    tablaExportGrupo = null;
                }
                tablaExportGrupo = $('#tbl_export_grupo').DataTable({
                    destroy: true,
                    dom: 'Bfrtip',
                    buttons: [{
                        extend: 'excel',
                        customize: function (xlsx) {
                            const sheet = xlsx.xl.worksheets['sheet1.xml'];
                            const fila1 = '<row r="1"><c r="A1" t="inlineStr"><is><t>' + escaparXmlExp(contexto.linea1) + '</t></is></c></row>';
                            const fila2 = '<row r="2"><c r="A2" t="inlineStr"><is><t>' + escaparXmlExp(contexto.linea2) + '</t></is></c></row>';
                            $('row', sheet).eq(0).before(fila1 + fila2);
                            $('row', sheet).each(function (i) {
                                const nuevaFila = i + 1;
                                $(this).attr('r', nuevaFila);
                                $('c', this).each(function () {
                                    const col = $(this).attr('r').replace(/[0-9]/g, '');
                                    $(this).attr('r', col + nuevaFila);
                                });
                            });
                            const filas = $('row', sheet);
                            const ultimaCelda = $('c', filas.eq(filas.length - 1)).last().attr('r');
                            if (ultimaCelda) {
                                $('dimension', sheet).attr('ref', 'A1:' + ultimaCelda);
                            }
                        }
                    }]
                });
                tablaExportGrupo.button(0).trigger();
            }
        });
    });

    // ── Cargar planilla de calificaciones ───────────────────────────────────
    function cargarCalificaciones(grmo_id) {
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_calificaciones',
            data: { grmo_id: grmo_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;

                // Actualizar encabezado
                const card = $(`.grupo-card[data-grmo="${grmo_id}"]`);
                $('#titulo_modulo').text(card.find('.fw-bold').first().text());
                $('#subtitulo_modulo').text(card.find('.text-muted').first().text());
                $('#badge_total_estudiantes').text(r.data.length + ' estudiantes');

                // Renderizar filas
                const tbody = $('#tbody_calificaciones');
                tbody.empty();

                if (!r.data.length) {
                    tbody.html('<tr><td colspan="13" class="text-center text-muted">Sin estudiantes asignados a este módulo</td></tr>');
                    $('#msg_seleccione').hide();
                    $('#contenedor_notas').show();
                    return;
                }

                r.data.forEach((e, idx) => {
                    const fila = construirFila(e, idx + 1, grmo_id);
                    tbody.append(fila);
                });

                $('#msg_seleccione').hide();
                $('#contenedor_notas').show();
            }
        });
    }

    // ── Construir fila de estudiante ─────────────────────────────────────────
    function construirFila(e, num, grmo_id) {
        const n1  = e.cali_n1  !== null ? e.cali_n1  : '';
        const n2  = e.cali_n2  !== null ? e.cali_n2  : '';
        const n3  = e.cali_n3  !== null ? e.cali_n3  : '';
        const n4  = e.cali_n4  !== null ? e.cali_n4  : '';
        const s1  = e.cali_sup_n1 !== null ? e.cali_sup_n1 : '';
        const s2  = e.cali_sup_n2 !== null ? e.cali_sup_n2 : '';
        const s4  = e.cali_sup_n4 !== null ? e.cali_sup_n4 : '';
        const hab = e.cali_habilitacion !== null ? e.cali_habilitacion : '';

        const notaFinalRaw = e.cali_nota_final;
        const notaFinal = notaFinalRaw !== null ? notaFinalRaw : '—';
        const def = e.cali_definitiva !== null ? e.cali_definitiva : '—';

        // Supletorios visibles solo si nota original = 0.0
        const verS1 = (parseFloat(n1) === 0.0 && n1 !== '') ? '' : 'display:none';
        const verS2 = (parseFloat(n2) === 0.0 && n2 !== '') ? '' : 'display:none';
        const verS4 = (parseFloat(n4) === 0.0 && n4 !== '') ? '' : 'display:none';

        // Habilitación visible solo si la Nota Final es < 3.0
        const verHab = (notaFinalRaw !== null && parseFloat(notaFinalRaw) < 3.0) ? '' : 'display:none';

        // Color Nota Final
        const notaFinalColor = notaFinal !== '—'
            ? (parseFloat(notaFinal) >= 3.0 ? 'bg-success' : 'bg-danger')
            : 'bg-secondary';

        // Color Definitiva
        const defColor = def !== '—'
            ? (parseFloat(def) >= 3.0 ? 'bg-success' : 'bg-danger')
            : 'bg-secondary';

        return `
            <tr data-estu="${e.estu_id}" data-grmo="${grmo_id}">
                <td>${num}</td>
                <td>${e.estu_apellidos}, ${e.estu_nombres}</td>
                <td><small>${e.estu_numerodoc}</small></td>
                <td class="text-center ${colorSemaforo(n1)}" data-celda="cali_n1">
                    <input class="input-nota" type="text"
                           data-campo="cali_n1" value="${n1}"
                           placeholder="0.0">
                </td>
                <td class="text-center celda-sup ${colorSemaforo(s1)}" data-celda="cali_sup_n1">
                    <input class="input-nota input-sup" type="text"
                           data-campo="cali_sup_n1" value="${s1}"
                           placeholder="0.0" style="${verS1}">
                </td>
                <td class="text-center ${colorSemaforo(n2)}" data-celda="cali_n2">
                    <input class="input-nota" type="text"
                           data-campo="cali_n2" value="${n2}"
                           placeholder="0.0">
                </td>
                <td class="text-center celda-sup ${colorSemaforo(s2)}" data-celda="cali_sup_n2">
                    <input class="input-nota input-sup" type="text"
                           data-campo="cali_sup_n2" value="${s2}"
                           placeholder="0.0" style="${verS2}">
                </td>
                <td class="text-center ${colorSemaforo(n3)}" data-celda="cali_n3">
                    <input class="input-n3-solo-lectura" type="text"
                           data-campo="cali_n3" value="${n3}"
                           placeholder="0.0" readonly
                           style="width:60px;text-align:center;padding:2px 4px;font-size:0.9em;border:1px solid #dee2e6;border-radius:4px;background:#fff;"
                           title="Se calcula desde el modal &quot;Registro y cálculo de N3&quot;">
                </td>
                <td class="text-center ${colorSemaforo(n4)}" data-celda="cali_n4">
                    <input class="input-nota" type="text"
                           data-campo="cali_n4" value="${n4}"
                           placeholder="0.0">
                </td>
                <td class="text-center celda-sup ${colorSemaforo(s4)}" data-celda="cali_sup_n4">
                    <input class="input-nota input-sup" type="text"
                           data-campo="cali_sup_n4" value="${s4}"
                           placeholder="0.0" style="${verS4}">
                </td>
                <td class="text-center ${colorSemaforo(notaFinal)}">
                    <span class="badge notafinal-badge ${notaFinalColor}">${notaFinal}</span>
                </td>
                <td class="text-center celda-hab ${colorSemaforo(hab)}" data-celda="cali_habilitacion">
                    <input class="input-nota input-hab" type="text"
                           data-campo="cali_habilitacion" value="${hab}"
                           placeholder="0.0" style="${verHab}">
                </td>
                <td class="text-center ${colorSemaforo(def)}">
                    <span class="badge definitiva-badge ${defColor}">${def}</span>
                </td>
            </tr>
        `;
    }

    // ── Normalizar valor de nota: coma→punto, entero→.0 (compartida entre
    //    N1/N2/N4 y los inputs de actividad N3 del modal de registro) ────────
    function normalizarValorNota(valorCrudo) {
        let valorNorm = valorCrudo.trim().replace(',', '.');
        if (valorNorm !== '' && /^\d+$/.test(valorNorm)) {
            valorNorm = valorNorm + '.0';
        }
        return valorNorm;
    }

    // ── Actualizar badges de Nota Final/Definitiva de una fila (tabla
    //    principal) — compartida con la actualización que dispara el
    //    autosave de N3 sobre esa misma fila (ver actualizarCeldaN3EnTablaPrincipal) ──
    function actualizarNotaFinalYDefinitivaEnFila(fila, notaFinal, definitiva) {
        const notaFinalTexto = (notaFinal !== null && notaFinal !== undefined) ? notaFinal : '—';
        const badgeNotaFinal = fila.find('.notafinal-badge');
        badgeNotaFinal.text(notaFinalTexto);
        badgeNotaFinal.removeClass('bg-secondary bg-success bg-danger');
        badgeNotaFinal.addClass(
            notaFinalTexto !== '—'
                ? (parseFloat(notaFinalTexto) >= 3.0 ? 'bg-success' : 'bg-danger')
                : 'bg-secondary'
        );

        const definitivaTexto = (definitiva !== null && definitiva !== undefined) ? definitiva : '—';
        const badgeDefinitiva = fila.find('.definitiva-badge');
        badgeDefinitiva.text(definitivaTexto);
        badgeDefinitiva.removeClass('bg-secondary bg-success bg-danger');
        badgeDefinitiva.addClass(
            definitivaTexto !== '—'
                ? (parseFloat(definitivaTexto) >= 3.0 ? 'bg-success' : 'bg-danger')
                : 'bg-secondary'
        );

        badgeNotaFinal.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
            .addClass(colorSemaforo(notaFinalTexto));
        badgeDefinitiva.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
            .addClass(colorSemaforo(definitivaTexto));
    }

    // ── Autosave on blur ─────────────────────────────────────────────────────
    $(document).on('blur', '.input-nota', function () {
        const input  = $(this);
        const fila   = input.closest('tr');
        const estu_id = fila.data('estu');
        const grmo_id = fila.data('grmo');
        const campo  = input.data('campo');
        const valor  = input.val().trim();
        const valorNorm = normalizarValorNota(valor);
        input.val(valorNorm);

        // Validar que el valor sea numérico (si no está vacío)
        if (valorNorm !== '' && isNaN(parseFloat(valorNorm))) {
            input.removeClass('guardando').addClass('error');
            alert('Valor inválido: solo se permiten números (0.0 - 5.0)');
            input.val('');
            setTimeout(() => input.trigger('focus'), 50);
            return;
        }

        // No guardar si está vacío y no había valor antes
        if (valor === '' && input.attr('data-valor-original') === '') return;

        input.removeClass('guardado error').addClass('guardando');

        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=guardar_nota',
            data: {
                grmo_id: grmo_id,
                estu_id: estu_id,
                campo:   campo,
                valor:   valorNorm
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    input.removeClass('guardando').addClass('guardado');
                    input.attr('data-valor-original', valorNorm);

                    actualizarNotaFinalYDefinitivaEnFila(fila, r.cali_nota_final, r.cali_definitiva);

                    // Actualizar color semáforo del td del input que se acaba de guardar
                    input.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
                        .addClass(colorSemaforo(valorNorm));

                    // Mostrar/ocultar supletorios según valor guardado
                    actualizarVisibilidadSupletorio(fila, campo, valorNorm);

                    // Mostrar/ocultar habilitación según la Nota Final recalculada
                    // (dato leído directamente de la respuesta del servidor, no del DOM)
                    actualizarVisibilidadHabilitacion(fila, r.cali_nota_final);

                    // Quitar clase guardado después de 2 segundos
                    setTimeout(() => input.removeClass('guardado'), 2000);
                } else {
                    input.removeClass('guardando').addClass('error');
                    alert('Error al guardar: ' + r.message);
                    input.val('');
                    setTimeout(() => input.trigger('focus'), 50);
                }
            },
            error: function () {
                input.removeClass('guardando').addClass('error');
            }
        });
    });

    // ── Mostrar/ocultar supletorio según nota original ───────────────────────
    function actualizarVisibilidadSupletorio(fila, campo, valor) {
        const mapaSup = {
            'cali_n1': 'cali_sup_n1',
            'cali_n2': 'cali_sup_n2',
            'cali_n4': 'cali_sup_n4'
        };
        // N3 nunca tiene supletorio — no está en el mapa
        if (!mapaSup[campo]) return;

        const campSup = mapaSup[campo];
        const inputSup = fila.find(`[data-campo="${campSup}"]`);
        if (parseFloat(valor) === 0.0) {
            inputSup.show();
        } else {
            inputSup.hide().val('');
        }
    }

    // ── Mostrar/ocultar habilitación según la Nota Final actual ──────────────
    function actualizarVisibilidadHabilitacion(fila, notaFinalValor) {
        const inputHab = fila.find('[data-campo="cali_habilitacion"]');
        const reprobando = notaFinalValor !== null && notaFinalValor !== undefined &&
            parseFloat(notaFinalValor) < 3.0;
        if (reprobando) {
            inputHab.show();
        } else {
            // No se limpia el valor (a diferencia del supletorio): una habilitación
            // ya registrada no debe perderse visualmente si la Nota Final sube de 3.0.
            inputHab.hide();
        }
    }

    // ── Guardar valor original al hacer focus ────────────────────────────────
    $(document).on('focus', '.input-nota, .input-nota-n3', function () {
        $(this).attr('data-valor-original', $(this).val());
        // Seleccionar todo el texto al entrar al campo
        $(this).select();
        $(this).removeClass('guardado error');
    });

    // ── Semáforo de colores por rango de nota ────────────────────────────────
    function colorSemaforo(valor) {
        if (valor === null || valor === undefined || valor === '' || valor === '—') return '';
        const n = parseFloat(valor);
        if (isNaN(n)) return '';
        if (n <= 2.9) return 'semaforo-rojo';
        if (n <= 3.9) return 'semaforo-amarillo';
        return 'semaforo-verde';
    }

    // ══════════════════════════════════════════════════════════════════════
    // MODALES N3 — Registro/Configurar actividades (Fase 2.14.E2)
    // ══════════════════════════════════════════════════════════════════════

    let totalActividadesN3Actual = 0;

    // ── Cargar la tabla dinámica de #mdl_registro_n3 cada vez que se abre —
    //    cubre tanto el primer open (link "N3") como el reopen tras cerrar
    //    #mdl_configurar_actividades_n3 (ver TODO de la Fase E2 más abajo:
    //    ambos casos llaman a .show(), que dispara este mismo evento) ────────
    $('#mdl_registro_n3').on('show.bs.modal', function () {
        cargarTablaRegistroN3(grmo_id_activo);
    });

    // ── Conexión modal-a-modal: Registro → Configurar (evita el problema de
    //    backdrop de modales anidados, ver decisión de la Fase 2.14.E1) ──────
    $('#btn_abrir_configurar_n3').on('click', function () {
        $('#mdl_registro_n3').one('hidden.bs.modal', function () {
            cargarActividadesN3(grmo_id_activo);
            bootstrap.Modal.getOrCreateInstance('#mdl_configurar_actividades_n3').show();
        });
        bootstrap.Modal.getOrCreateInstance('#mdl_registro_n3').hide();
    });

    // Al cerrar Configurar, siempre vuelve a mostrar Registro. El .show() de
    // abajo dispara 'show.bs.modal' (ver arriba), que ya recarga
    // #tbl_registro_n3 — cubre el caso "las actividades cambiaron" sin
    // necesidad de una segunda llamada explícita aquí (Fase 2.14.E3.1).
    $('#mdl_configurar_actividades_n3').on('hidden.bs.modal', function () {
        limpiarFormularioActividadN3();
        bootstrap.Modal.getOrCreateInstance('#mdl_registro_n3').show();
    });

    // ── Cargar lista de actividades del grupo activo ─────────────────────────
    function cargarActividadesN3(grmoId) {
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_actividades_n3',
            data: { grmo_id: grmoId },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') {
                    alert('Error al cargar actividades: ' + r.message);
                    return;
                }
                totalActividadesN3Actual = r.data.length;
                renderizarListaActividadesN3(r.data);
                actualizarEstadoFormularioActividadN3();
            }
        });
    }

    // ── Renderizar la lista de actividades con sus botones Editar/Eliminar ──
    function renderizarListaActividadesN3(actividades) {
        const contenedor = $('#lista_actividades_n3');
        contenedor.empty();

        if (!actividades.length) {
            contenedor.html('<p class="text-muted small mb-0">Sin actividades configuradas todavía.</p>');
            return;
        }

        const esUltima = actividades.length === 1;
        const tabla = $(`
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr><th>Actividad</th><th>Comentario</th><th class="text-end">Acciones</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        `);
        const tbody = tabla.find('tbody');

        actividades.forEach(a => {
            const comentario = a.acn3_comentario || '';
            const comentarioMostrado = comentario.length > 40 ? comentario.substring(0, 40) + '…' : comentario;

            const btnEditar = $('<button type="button" class="btn btn-sm btn-outline-secondary btn-editar-actividad-n3">Editar</button>')
                .attr('data-acn3-id', a.acn3_id)
                .attr('data-nombre', a.acn3_nombre)
                .attr('data-comentario', comentario);

            const btnEliminar = esUltima
                ? $('<button type="button" class="btn btn-sm btn-outline-danger" disabled>Eliminar</button>')
                    .attr('title', 'No se puede eliminar la última actividad.')
                : $('<button type="button" class="btn btn-sm btn-outline-danger btn-eliminar-actividad-n3">Eliminar</button>')
                    .attr('data-acn3-id', a.acn3_id);

            const fila = $('<tr></tr>');
            fila.append($('<td></td>').text(a.acn3_nombre));
            fila.append($('<td></td>').append($('<small class="text-muted"></small>').text(comentarioMostrado)));
            fila.append($('<td class="text-end"></td>').append(btnEditar, ' ', btnEliminar));
            tbody.append(fila);
        });

        contenedor.append(tabla);
    }

    // ── Habilitar/deshabilitar el formulario según el límite de 15 ──────────
    function actualizarEstadoFormularioActividadN3() {
        const enEdicion = $('#hdn_acn3_id').val() !== '';
        const limiteAlcanzado = !enEdicion && totalActividadesN3Actual >= 15;

        $('#txt_acn3_nombre, #txt_acn3_comentario, #btn_guardar_actividad_n3').prop('disabled', limiteAlcanzado);

        let msg = $('#msg_limite_actividades_n3');
        if (limiteAlcanzado && !msg.length) {
            msg = $('<p id="msg_limite_actividades_n3" class="text-danger small mb-2">Máximo de actividades alcanzado (15).</p>');
            $('#frm_actividad_n3').before(msg);
        }
        msg.toggle(limiteAlcanzado);
    }

    // ── Limpiar formulario (crear/editar) ────────────────────────────────────
    function limpiarFormularioActividadN3() {
        $('#hdn_acn3_id').val('');
        $('#txt_acn3_nombre').val('');
        $('#txt_acn3_comentario').val('');
        actualizarEstadoFormularioActividadN3();
    }

    // ── Guardar (crear o editar según #hdn_acn3_id) ──────────────────────────
    function guardarActividadN3() {
        const acn3Id = $('#hdn_acn3_id').val();
        const nombre = $('#txt_acn3_nombre').val().trim();
        const comentario = $('#txt_acn3_comentario').val().trim();

        if (nombre === '') {
            alert('El nombre de la actividad es obligatorio');
            return;
        }

        const esEdicion = acn3Id !== '';
        const accion = esEdicion ? 'editar_actividad_n3' : 'guardar_actividad_n3';
        const datos = esEdicion
            ? { acn3_id: acn3Id, acn3_nombre: nombre, acn3_comentario: comentario }
            : { grmo_id: grmo_id_activo, acn3_nombre: nombre, acn3_comentario: comentario };

        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=' + accion,
            data: datos,
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    limpiarFormularioActividadN3();
                    cargarActividadesN3(grmo_id_activo);
                } else {
                    alert('Error al guardar: ' + r.message);
                }
            }
        });
    }

    $('#frm_actividad_n3').on('submit', function (e) {
        e.preventDefault();
        guardarActividadN3();
    });
    $('#btn_guardar_actividad_n3').on('click', guardarActividadN3);

    // ── Click en "Editar" de una fila ─────────────────────────────────────────
    $(document).on('click', '.btn-editar-actividad-n3', function () {
        const btn = $(this);
        $('#hdn_acn3_id').val(btn.data('acn3-id'));
        $('#txt_acn3_nombre').val(btn.data('nombre'));
        $('#txt_acn3_comentario').val(btn.data('comentario'));
        actualizarEstadoFormularioActividadN3();
    });

    // ── Click en "Eliminar" de una fila ────────────────────────────────────────
    $(document).on('click', '.btn-eliminar-actividad-n3', function () {
        const acn3Id = $(this).data('acn3-id');
        if (!confirm('¿Eliminar esta actividad?')) return;

        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=eliminar_actividad_n3',
            data: { acn3_id: acn3Id },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    cargarActividadesN3(grmo_id_activo);
                } else {
                    alert('Error al eliminar: ' + r.message);
                }
            }
        });
    });

    // ── Tabla dinámica estudiante×actividad de #mdl_registro_n3 (Fase 2.14.E3.1) ──
    function cargarTablaRegistroN3(grmoId) {
        const reqActividades = $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_actividades_n3',
            data: { grmo_id: grmoId },
            dataType: 'json'
        });
        const reqNotas = $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_notas_n3',
            data: { grmo_id: grmoId },
            dataType: 'json'
        });
        // Mismo endpoint que ya usa la tabla principal (listar_calificaciones) —
        // se reutiliza solo por el roster (estu_id/nombres/apellidos), ignorando
        // los campos de notas N1-N4 que trae de más.
        const reqEstudiantes = $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_calificaciones',
            data: { grmo_id: grmoId },
            dataType: 'json'
        });

        $.when(reqActividades, reqNotas, reqEstudiantes).done(function (rActividades, rNotas, rEstudiantes) {
            const actividades = rActividades[0].status === 'ok' ? rActividades[0].data : [];
            const notas = rNotas[0].status === 'ok' ? rNotas[0].data : [];
            const estudiantes = rEstudiantes[0].status === 'ok' ? rEstudiantes[0].data : [];
            renderizarTablaRegistroN3(actividades, notas, estudiantes);
        });
    }

    function renderizarTablaRegistroN3(actividades, notas, estudiantes) {
        const theadTr = $('#tbl_registro_n3 thead tr');
        const tbody = $('#tbl_registro_n3 tbody');

        // Quitar columnas dinámicas de una carga anterior — deja solo # y Estudiante
        theadTr.find('th').slice(2).remove();
        tbody.empty();

        if (!actividades.length) {
            tbody.html('<tr><td colspan="2" class="text-center text-muted">Configura al menos una actividad para comenzar.</td></tr>');
            return;
        }

        actividades.forEach(act => {
            const nombreCorto = act.acn3_nombre.length > 10 ? act.acn3_nombre.substring(0, 10) + '…' : act.acn3_nombre;
            const tooltipTexto = act.acn3_comentario ? `${act.acn3_nombre} — ${act.acn3_comentario}` : act.acn3_nombre;
            theadTr.append($('<th class="text-center"></th>').text(nombreCorto).attr('title', tooltipTexto));
        });
        theadTr.append('<th class="text-center">Nota final</th>');

        if (!estudiantes.length) {
            tbody.html(`<tr><td colspan="${actividades.length + 3}" class="text-center text-muted">Sin estudiantes asignados a este módulo</td></tr>`);
            return;
        }

        // notaMap[acn3_id][estu_id] = non3_valor
        const notaMap = {};
        notas.forEach(n => {
            if (!notaMap[n.acn3_id]) notaMap[n.acn3_id] = {};
            notaMap[n.acn3_id][n.estu_id] = n.non3_valor;
        });

        estudiantes.forEach((e, idx) => {
            const tr = $('<tr></tr>');
            tr.append($('<td></td>').text(idx + 1));
            tr.append($('<td></td>').text(`${e.estu_apellidos}, ${e.estu_nombres}`));

            let todasCompletas = true;
            let suma = 0;

            actividades.forEach(act => {
                const porEstudiante = notaMap[act.acn3_id] || {};
                const valor = porEstudiante[e.estu_id];
                if (valor === undefined || valor === null) {
                    todasCompletas = false;
                } else {
                    suma += parseFloat(valor);
                }

                // Clase distinta de .input-nota a propósito: esa clase ya tiene
                // un handler global de blur -> guardar_nota (N1/N2/N4), que no
                // aplica aquí — el autosave de estos inputs (más abajo) llama a
                // guardar_nota_n3 en su lugar.
                const input = $('<input type="text" class="input-nota-n3 form-control form-control-sm text-center">')
                    .attr('data-acn3-id', act.acn3_id)
                    .attr('data-estu-id', e.estu_id)
                    .val(valor !== undefined && valor !== null ? valor : '');
                tr.append($('<td></td>').append(input));
            });

            // Mismo criterio que recalcularN3() en PHP: promedio solo si TODAS
            // las actividades de la fila tienen valor; si no, "—".
            const notaFinalTexto = todasCompletas ? (suma / actividades.length).toFixed(1) : '—';
            tr.append($('<td class="text-center fw-bold celda-notafinal-n3"></td>').text(notaFinalTexto));

            tbody.append(tr);
        });
    }

    // ── Autosave on blur — notas por actividad N3 (Fase 2.14.E3.2) ───────────
    $(document).on('blur', '#tbl_registro_n3 .input-nota-n3', function () {
        const input = $(this);
        const fila = input.closest('tr');
        const acn3Id = input.data('acn3-id');
        const estuId = input.data('estu-id');
        const valor = input.val().trim();
        const valorNorm = normalizarValorNota(valor);
        input.val(valorNorm);

        // Validar numérico y rango (mismo feedback visual que .input-nota:
        // clase 'error', alert(), limpiar y reenfocar) — nunca hay supletorio
        // que evaluar aquí, N3 no lo tiene bajo ninguna circunstancia.
        if (valorNorm !== '' &&
            (isNaN(parseFloat(valorNorm)) || parseFloat(valorNorm) < 0.0 || parseFloat(valorNorm) > 5.0)) {
            input.removeClass('guardando').addClass('error');
            alert('Valor inválido: solo se permiten números (0.0 - 5.0)');
            input.val('');
            setTimeout(() => input.trigger('focus'), 50);
            return;
        }

        // No guardar si está vacío y no había valor antes
        if (valor === '' && input.attr('data-valor-original') === '') return;

        input.removeClass('guardado error').addClass('guardando');

        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=guardar_nota_n3',
            data: { acn3_id: acn3Id, estu_id: estuId, non3_valor: valorNorm },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    input.removeClass('guardando').addClass('guardado');
                    input.attr('data-valor-original', valorNorm);

                    input.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
                        .addClass(colorSemaforo(valorNorm));

                    const n3Texto = (r.cali_n3 !== null && r.cali_n3 !== undefined)
                        ? parseFloat(r.cali_n3).toFixed(1) : '—';
                    fila.find('.celda-notafinal-n3').text(n3Texto);

                    actualizarCeldaN3EnTablaPrincipal(estuId, r.cali_n3, r.cali_nota_final, r.cali_definitiva);

                    setTimeout(() => input.removeClass('guardado'), 2000);
                } else {
                    input.removeClass('guardando').addClass('error');
                    alert('Error al guardar: ' + r.message);
                    input.val('');
                    setTimeout(() => input.trigger('focus'), 50);
                }
            },
            error: function () {
                input.removeClass('guardando').addClass('error');
            }
        });
    });

    // ── Reflejar cali_n3/Nota Final/Definitiva en la tabla principal (fuera
    //    del modal) tras un autosave de N3 — reutiliza el mismo mecanismo de
    //    actualización de celdas que .input-nota (N1/N2/N4). El selector de
    //    abajo usa el atributo data-campo (no la clase CSS del input), así que
    //    sigue funcionando sin cambios tras la Fase 2.14.F (input de solo
    //    lectura, clase .input-n3-solo-lectura en vez de .input-nota).
    function actualizarCeldaN3EnTablaPrincipal(estuId, cali_n3, notaFinal, definitiva) {
        const filaPrincipal = $(`#tbody_calificaciones tr[data-estu="${estuId}"]`);
        if (!filaPrincipal.length) return;

        const n3Texto = (cali_n3 !== null && cali_n3 !== undefined) ? cali_n3 : '';
        const inputN3 = filaPrincipal.find('input[data-campo="cali_n3"]');
        inputN3.val(n3Texto);
        inputN3.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
            .addClass(colorSemaforo(n3Texto));

        actualizarNotaFinalYDefinitivaEnFila(filaPrincipal, notaFinal, definitiva);
    }

});
