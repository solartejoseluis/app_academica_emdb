$(document).ready(function () {

    // ── Variables globales de estado ─────────────────────────────────────────
    let tablaCohortes, tablaGrupos;
    let grmo_id_activo = null;
    let coho_id_activo = null;

    // ── Inicialización ───────────────────────────────────────────────────────
    cargarTablaCohortes();
    cargarTablaGrupos();
    cargarProgramasSelectores();
    cargarPeriodosSelector();
    cargarDocentesSelector();
    cargarGruposAsignacion();

    // ── Fix render DataTables en tabs ocultos ────────────────────────────────
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        if (tablaCohortes) tablaCohortes.columns.adjust();
        if (tablaGrupos) tablaGrupos.columns.adjust();
    });

    // ════════════════════════════════════════════════════════════════════════
    // COHORTES
    // ════════════════════════════════════════════════════════════════════════

    function cargarTablaCohortes() {
        if (tablaCohortes) { tablaCohortes.ajax.reload(); return; }
        tablaCohortes = $('#tbl_cohortes').DataTable({
            ajax: {
                url: 'grupos_mdl.php?accion=listar_cohortes',
                type: 'POST',
                dataSrc: 'data'
            },
            columns: [
                { data: null, render: (d, t, r, m) => m.row + 1 },
                { data: 'coho_codigo' },
                { data: 'prog_nombre' },
                { data: 'coho_jornada' },
                { data: 'fechainicio' },
                { data: 'coho_activa', render: v =>
                    v == 1
                        ? '<span class="badge bg-success">Activa</span>'
                        : '<span class="badge bg-secondary">Inactiva</span>'
                },
                { data: 'coho_id', render: id =>
                    `<button class="btn btn-sm btn-outline-primary"
                        onclick="abrirEditarCohorte(${id})">Editar</button>`
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            responsive: true
        });
    }

    function cargarTablaGrupos() {
        if (tablaGrupos) { tablaGrupos.ajax.reload(); return; }
        tablaGrupos = $('#tbl_grupos').DataTable({
            ajax: {
                url: 'grupos_mdl.php?accion=listar_grupos',
                type: 'POST',
                dataSrc: 'data'
            },
            columns: [
                { data: null, render: (d, t, r, m) => m.row + 1 },
                { data: 'grse_codigo' },
                { data: 'coho_codigo' },
                { data: 'prog_sigla' },
                { data: 'peri_codigo' },
                { data: 'grse_semestre', render: v => `Semestre ${v}` },
                { data: 'total_modulos', render: v =>
                    `<span class="badge bg-info text-dark">${v} módulos</span>`
                },
                { data: 'grse_activo', render: v =>
                    v == 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>'
                },
                { data: 'grse_id', render: id =>
                    `<button class="btn btn-sm btn-outline-primary"
                        onclick="abrirEditarGrupo(${id})">Ver/Editar</button>`
                }
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            responsive: true
        });
    }

    function cargarProgramasSelectores() {
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_programas',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar --</option>';
                r.data.forEach(p => {
                    opts += `<option value="${p.prog_id}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                });
                $('#slct_prog_cohorte, #slct_prog_grupo').html(opts);
            }
        });
    }

    function cargarPeriodosSelector() {
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_periodos',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar --</option>';
                r.data.forEach(p => {
                    opts += `<option value="${p.peri_id}">${p.peri_codigo}</option>`;
                });
                $('#slct_peri_grupo').html(opts);
            }
        });
    }

    function cargarDocentesSelector() {
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_docentes',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar --</option>';
                r.data.forEach(d => {
                    opts += `<option value="${d.doce_id}">${d.doce_apellidos}, ${d.doce_nombres}</option>`;
                });
                $('#slct_doce_grupo').html(opts);
            }
        });
    }

    function cargarGruposAsignacion() {
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_grupos',
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar grupo --</option>';
                r.data.forEach(g => {
                    opts += `<option value="${g.grse_id}" data-coho="${g.coho_id}">${g.grse_codigo} — ${g.coho_codigo} Sem.${g.grse_semestre}</option>`;
                });
                $('#slct_grupo_asignacion').html(opts);
            }
        });
    }

    // Cuando cambia el programa en modal cohorte — no hace nada adicional
    // Cuando cambia el programa en modal grupo — carga cohortes
    $('#slct_prog_grupo').on('change', function () {
        const prog_id = $(this).val();
        $('#slct_coho_grupo').html('<option value="">-- Cargando... --</option>');
        if (!prog_id) {
            $('#slct_coho_grupo').html('<option value="">-- Primero seleccione programa --</option>');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_cohortes_por_programa',
            data: { prog_id: prog_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar --</option>';
                r.data.forEach(c => {
                    opts += `<option value="${c.coho_id}">${c.coho_codigo} (${c.coho_jornada})</option>`;
                });
                $('#slct_coho_grupo').html(opts);
            }
        });
    });

    // Cuando cambia el grupo en tab asignación — carga módulos del grupo
    $('#slct_grupo_asignacion').on('change', function () {
        const grse_id = $(this).val();
        $('#slct_modulo_asignacion').html('<option value="">-- Cargando... --</option>');
        $('#panel_asignacion').hide();
        $('#msg_seleccione').show();
        if (!grse_id) return;
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_modulos_grupo',
            data: { grse_id: grse_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar módulo --</option>';
                r.data.forEach(m => {
                    opts += `<option value="${m.grmo_id}">${m.modu_sigla} — ${m.modu_nombre}</option>`;
                });
                $('#slct_modulo_asignacion').html(opts);
            }
        });
    });

    // Botón Cargar asignación
    $('#btn_cargar_asignacion').on('click', function () {
        grmo_id_activo = $('#slct_modulo_asignacion').val();
        coho_id_activo = $('#slct_grupo_asignacion option:selected').attr('data-coho');
        if (!grmo_id_activo) {
            alert('Seleccione un módulo');
            return;
        }
        $('#panel_asignacion').css('display', 'flex');
        $('#msg_seleccione').hide();
        cargarListasAsignacion();
    });

    function cargarListasAsignacion() {
        // Disponibles
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_estudiantes_disponibles',
            data: { grmo_id: grmo_id_activo, coho_id: coho_id_activo },
            dataType: 'json',
            success: function (r) {
                renderLista('#lista_disponibles', r.data || [], false);
                $('#badge_disponibles').text(r.data ? r.data.length : 0);
            }
        });
        // Asignados
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_estudiantes_modulo',
            data: { grmo_id: grmo_id_activo },
            dataType: 'json',
            success: function (r) {
                renderLista('#lista_asignados', r.data || [], true);
                $('#badge_asignados').text(r.data ? r.data.length : 0);
            }
        });
    }

    function renderLista(selector, estudiantes, esAsignado) {
        const container = $(selector);
        container.empty();
        if (!estudiantes.length) {
            container.html('<p class="text-muted small p-2">Sin estudiantes</p>');
            return;
        }
        estudiantes.forEach(e => {
            const item = $(`
                <div class="form-check border-bottom py-1 px-2 lista-item"
                     data-id="${e.estu_id}">
                    <input class="form-check-input chk-estudiante" type="checkbox"
                           value="${e.estu_id}">
                    <label class="form-check-label small">
                        ${e.estu_apellidos}, ${e.estu_nombres}
                        <span class="text-muted">(${e.estu_numerodoc})</span>
                    </label>
                </div>
            `);
            container.append(item);
        });
    }

    // Filtros de búsqueda en listas
    $('#filtro_disponibles').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#lista_disponibles .lista-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    $('#filtro_asignados').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#lista_asignados .lista-item').each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(q));
        });
    });

    // Botón Asignar seleccionados
    $('#btn_asignar_seleccionados').on('click', function () {
        const ids = [];
        $('#lista_disponibles .chk-estudiante:checked').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length) { alert('Seleccione al menos un estudiante'); return; }
        asignarEstudiantes(ids);
    });

    // Botón Asignar todos
    $('#btn_asignar_todos').on('click', function () {
        const ids = [];
        $('#lista_disponibles .chk-estudiante').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length) { alert('No hay estudiantes disponibles'); return; }
        asignarEstudiantes(ids);
    });

    function asignarEstudiantes(ids) {
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=asignar_estudiantes',
            data: { grmo_id: grmo_id_activo, estu_ids: ids },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    cargarListasAsignacion();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    }

    // Botón Retirar seleccionados
    $('#btn_retirar_seleccionados').on('click', function () {
        const ids = [];
        $('#lista_asignados .chk-estudiante:checked').each(function () {
            ids.push($(this).val());
        });
        if (!ids.length) { alert('Seleccione al menos un estudiante para retirar'); return; }
        ids.forEach(id => {
            $.ajax({
                type: 'POST',
                url: 'grupos_mdl.php?accion=retirar_estudiante',
                data: { grmo_id: grmo_id_activo, estu_id: id },
                dataType: 'json',
                success: function (r) {
                    if (r.status === 'ok') cargarListasAsignacion();
                }
            });
        });
    });

    // ════════════════════════════════════════════════════════════════════════
    // MODAL COHORTE — botones
    // ════════════════════════════════════════════════════════════════════════

    $('#btn_nueva_cohorte').on('click', function () {
        $('#coho_id').val('');
        $('#coho_codigo, #coho_fechainicio').val('');
        $('#slct_prog_cohorte').val('');
        $('#coho_jornada').val('Semana');
        $('#bloque_activo_cohorte').addClass('d-none');
        $('#mdl_cohorte_titulo').text('Nueva Cohorte');
        new bootstrap.Modal('#mdl_cohorte').show();
    });

    $('#btn_guardar_cohorte').on('click', function () {
        const prog_id = $('#slct_prog_cohorte').val();
        const codigo  = $('#coho_codigo').val().trim();
        const fecha   = $('#coho_fechainicio').val();
        if (!prog_id || !codigo || !fecha) {
            alert('Complete los campos obligatorios');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=guardar_cohorte',
            data: {
                coho_id:      $('#coho_id').val(),
                prog_id:      prog_id,
                coho_codigo:  codigo,
                fechainicio:  fecha,
                coho_jornada: $('#coho_jornada').val(),
                coho_activa:  $('#coho_activa').val() || 1
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance('#mdl_cohorte').hide();
                    cargarTablaCohortes();
                    cargarGruposAsignacion();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

    // ════════════════════════════════════════════════════════════════════════
    // MODAL GRUPO — botones
    // ════════════════════════════════════════════════════════════════════════

    $('#btn_nuevo_grupo').on('click', function () {
        $('#grse_id').val('');
        $('#grse_codigo, #grse_fechainicio, #grse_fechafin').val('');
        $('#slct_prog_grupo, #slct_coho_grupo, #slct_peri_grupo').val('');
        $('#grse_semestre').val('1');
        $('#bloque_activo_grupo').addClass('d-none');
        $('#btn_agregar_modulo').prop('disabled', true);
        $('#tbody_modulos_grupo').html(
            '<tr><td colspan="7" class="text-center text-muted">Guarde el grupo primero para agregar módulos</td></tr>'
        );
        $('#mdl_grupo_titulo').text('Nuevo Grupo Semestre');
        new bootstrap.Modal('#mdl_grupo').show();
    });

    $('#btn_guardar_grupo').on('click', function () {
        const coho_id = $('#slct_coho_grupo').val();
        const peri_id = $('#slct_peri_grupo').val();
        const codigo  = $('#grse_codigo').val().trim();
        const sem     = $('#grse_semestre').val();
        if (!coho_id || !peri_id || !codigo || !sem) {
            alert('Complete los campos obligatorios');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=guardar_grupo',
            data: {
                grse_id:       $('#grse_id').val(),
                coho_id:       coho_id,
                prog_id:       $('#slct_prog_grupo').val(),
                peri_id:       peri_id,
                grse_semestre: sem,
                grse_codigo:   codigo,
                fechainicio:   $('#grse_fechainicio').val(),
                fechafin:      $('#grse_fechafin').val(),
                grse_activo:   $('#grse_activo').val() || 1
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    cargarTablaGrupos();
                    cargarGruposAsignacion();
                    // Habilitar botón agregar módulo si es nuevo
                    if (!$('#grse_id').val()) {
                        // recargamos módulos del grupo recién creado
                        // necesitamos el ID — hacemos reload de la tabla y cerramos
                        bootstrap.Modal.getInstance(document.getElementById('mdl_grupo')).hide();
                    } else {
                        cargarModulosGrupo($('#grse_id').val());
                        $('#btn_agregar_modulo').prop('disabled', false);
                        bootstrap.Modal.getInstance(document.getElementById('mdl_grupo')).hide();
                    }
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

    // Botón agregar módulo dentro del modal grupo
    $('#btn_agregar_modulo').on('click', function () {
        const grse_id = $('#grse_id').val();
        const prog_id = $('#slct_prog_grupo').val();
        if (!grse_id) return;
        // Cargar módulos disponibles del programa
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=listar_modulos_por_programa',
            data: { prog_id: prog_id },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok') return;
                let opts = '<option value="">-- Seleccionar --</option>';
                r.data.forEach(m => {
                    opts += `<option value="${m.modu_id}">Sem.${m.semestre_sugerido} — ${m.modu_sigla} ${m.modu_nombre}</option>`;
                });
                $('#slct_modu_grupo').html(opts);
                $('#grmo_id').val('');
                $('#grmo_grse_id').val(grse_id);
                $('#grmo_horario, #grmo_fechainicio, #grmo_fechafin').val('');
                new bootstrap.Modal('#mdl_modulo_grupo').show();
            }
        });
    });

    $('#btn_guardar_modulo_grupo').on('click', function () {
        const modu_id = $('#slct_modu_grupo').val();
        const doce_id = $('#slct_doce_grupo').val();
        if (!modu_id || !doce_id) {
            alert('Seleccione módulo y docente');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=guardar_modulo_grupo',
            data: {
                grmo_id:         $('#grmo_id').val(),
                grse_id:         $('#grmo_grse_id').val(),
                modu_id:         modu_id,
                doce_id:         doce_id,
                grmo_horario:    $('#grmo_horario').val(),
                fechainicio_mod: $('#grmo_fechainicio').val(),
                fechafin_mod:    $('#grmo_fechafin').val()
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance('#mdl_modulo_grupo').hide();
                    cargarModulosGrupo($('#grmo_grse_id').val());
                    cargarTablaGrupos();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

}); // fin ready

// ════════════════════════════════════════════════════════════════════════════
// FUNCIONES GLOBALES (fuera de ready — compatibilidad con DataTables render)
// ════════════════════════════════════════════════════════════════════════════

function cargarModulosGrupo(grse_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=listar_modulos_grupo',
        data: { grse_id: grse_id },
        dataType: 'json',
        success: function (r) {
            const tbody = $('#tbody_modulos_grupo');
            tbody.empty();
            if (!r.data || !r.data.length) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted">Sin módulos asignados</td></tr>');
                return;
            }
            r.data.forEach(m => {
                tbody.append(`
                    <tr>
                        <td>${m.modu_sigla} — ${m.modu_nombre}</td>
                        <td>${m.doce_apellidos}, ${m.doce_nombres}</td>
                        <td>${m.grmo_horario || '—'}</td>
                        <td>${m.fechainicio || '—'}</td>
                        <td>${m.fechafin || '—'}</td>
                        <td><span class="badge bg-info text-dark">${m.total_estudiantes}</span></td>
                        <td>
                            <button class="btn btn-xs btn-outline-secondary btn-sm"
                                onclick="abrirEditarModuloGrupo(${m.grmo_id}, ${grse_id})">✏️</button>
                        </td>
                    </tr>
                `);
            });
            $('#btn_agregar_modulo').prop('disabled', false);
        }
    });
}

function abrirEditarCohorte(coho_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=obtener_cohorte',
        data: { coho_id: coho_id },
        dataType: 'json',
        success: function (r) {
            if (r.status !== 'ok') { alert('Error al cargar'); return; }
            const d = r.data;
            $('#coho_id').val(d.coho_id);
            $('#slct_prog_cohorte').val(d.prog_id);
            $('#coho_codigo').val(d.coho_codigo);
            $('#coho_jornada').val(d.coho_jornada);
            $('#coho_fechainicio').val(d.fechainicio);
            $('#coho_activa').val(d.coho_activa);
            $('#bloque_activo_cohorte').removeClass('d-none');
            $('#mdl_cohorte_titulo').text('Editar Cohorte');
            new bootstrap.Modal('#mdl_cohorte').show();
        }
    });
}

function abrirEditarGrupo(grse_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=obtener_grupo',
        data: { grse_id: grse_id },
        dataType: 'json',
        success: function (r) {
            if (r.status !== 'ok') { alert('Error al cargar'); return; }
            const d = r.data;
            $('#grse_id').val(d.grse_id);
            $('#slct_peri_grupo').val(d.peri_id);
            $('#grse_semestre').val(d.grse_semestre);
            $('#grse_codigo').val(d.grse_codigo);
            $('#grse_fechainicio').val(d.fechainicio);
            $('#grse_fechafin').val(d.fechafin);
            $('#grse_activo').val(d.grse_activo);
            $('#bloque_activo_grupo').removeClass('d-none');
            $('#btn_agregar_modulo').prop('disabled', false);
            $('#mdl_grupo_titulo').text('Editar Grupo Semestre');

            // Primero selecciona el programa y espera que carguen
            // las cohortes via AJAX antes de seleccionar la cohorte
            $('#slct_prog_grupo').val(d.prog_id);
            $.ajax({
                type: 'POST',
                url: 'grupos_mdl.php?accion=listar_cohortes_por_programa',
                data: { prog_id: d.prog_id },
                dataType: 'json',
                success: function (rc) {
                    if (rc.status !== 'ok') return;
                    let opts = '<option value="">-- Seleccionar --</option>';
                    rc.data.forEach(c => {
                        opts += `<option value="${c.coho_id}">${c.coho_codigo} (${c.coho_jornada})</option>`;
                    });
                    $('#slct_coho_grupo').html(opts);
                    $('#slct_coho_grupo').val(d.coho_id);
                    cargarModulosGrupo(grse_id);
                    new bootstrap.Modal('#mdl_grupo').show();
                }
            });
        }
    });
}

function abrirEditarModuloGrupo(grmo_id, grse_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=listar_modulos_grupo',
        data: { grse_id: grse_id },
        dataType: 'json',
        success: function (r) {
            const mod = r.data ? r.data.find(m => m.grmo_id == grmo_id) : null;
            if (!mod) { alert('Módulo no encontrado'); return; }
            $('#grmo_id').val(grmo_id);
            $('#grmo_grse_id').val(grse_id);
            $('#grmo_horario').val(mod.grmo_horario || '');
            $('#grmo_fechainicio').val(mod.fechainicio || '');
            $('#grmo_fechafin').val(mod.fechafin || '');
            new bootstrap.Modal('#mdl_modulo_grupo').show();
        }
    });
}
