$(document).ready(function () {

    let grmo_id_activo = null;

    // ── Cargar lista de grupos al inicio ────────────────────────────────────
    cargarGrupos();

    function cargarGrupos() {
        $.ajax({
            type: 'POST',
            url: 'calificaciones_mdl.php?accion=listar_grupos',
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
                    <input class="input-nota" type="text"
                           data-campo="cali_n3" value="${n3}"
                           placeholder="0.0">
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

    // ── Autosave on blur ─────────────────────────────────────────────────────
    $(document).on('blur', '.input-nota', function () {
        const input  = $(this);
        const fila   = input.closest('tr');
        const estu_id = fila.data('estu');
        const grmo_id = fila.data('grmo');
        const campo  = input.data('campo');
        const valor  = input.val().trim();
        // Normalizar separador decimal: coma → punto
        let valorNorm = valor.replace(',', '.');
        // Si es número entero válido, agregar decimal .0
        if (valorNorm !== '' && /^\d+$/.test(valorNorm)) {
            valorNorm = valorNorm + '.0';
        }
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

                    // Actualizar Nota Final en tiempo real (maneja null explícitamente)
                    const notaFinal = r.cali_nota_final;
                    const notaFinalTexto = (notaFinal !== null && notaFinal !== undefined) ? notaFinal : '—';
                    const badgeNotaFinal = fila.find('.notafinal-badge');
                    badgeNotaFinal.text(notaFinalTexto);
                    badgeNotaFinal.removeClass('bg-secondary bg-success bg-danger');
                    badgeNotaFinal.addClass(
                        notaFinalTexto !== '—'
                            ? (parseFloat(notaFinalTexto) >= 3.0 ? 'bg-success' : 'bg-danger')
                            : 'bg-secondary'
                    );

                    // Actualizar Definitiva en tiempo real (maneja null explícitamente)
                    const definitiva = r.cali_definitiva;
                    const definitivaTexto = (definitiva !== null && definitiva !== undefined) ? definitiva : '—';
                    const badgeDefinitiva = fila.find('.definitiva-badge');
                    badgeDefinitiva.text(definitivaTexto);
                    badgeDefinitiva.removeClass('bg-secondary bg-success bg-danger');
                    badgeDefinitiva.addClass(
                        definitivaTexto !== '—'
                            ? (parseFloat(definitivaTexto) >= 3.0 ? 'bg-success' : 'bg-danger')
                            : 'bg-secondary'
                    );

                    // Actualizar color semáforo del td del input que se acaba de guardar
                    input.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
                        .addClass(colorSemaforo(valorNorm));

                    // Actualizar color semáforo de los td de Nota Final y Definitiva
                    badgeNotaFinal.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
                        .addClass(colorSemaforo(notaFinalTexto));
                    badgeDefinitiva.closest('td').removeClass('semaforo-rojo semaforo-amarillo semaforo-verde')
                        .addClass(colorSemaforo(definitivaTexto));

                    // Mostrar/ocultar supletorios según valor guardado
                    actualizarVisibilidadSupletorio(fila, campo, valorNorm);

                    // Mostrar/ocultar habilitación según la Nota Final recalculada
                    // (dato leído directamente de la respuesta del servidor, no del DOM)
                    actualizarVisibilidadHabilitacion(fila, notaFinal);

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
    $(document).on('focus', '.input-nota', function () {
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

});
