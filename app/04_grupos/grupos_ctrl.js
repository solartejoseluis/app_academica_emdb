// Cache de la última carga de listar_modulos (modu_id → fila completa) y
// modu_id pendiente de confirmación de borrado — accesibles tanto desde el
// scope de $(document).ready como desde las funciones globales de abajo,
// que se invocan vía onclick inline desde el HTML renderizado por DataTables.
let cacheModulos = {};
let moduloIdPendienteEliminar = null;
let cohorteIdPendienteEliminar = null;
let periodoActivoId = '';

$(document).ready(function () {

    // ── Variables globales de estado ─────────────────────────────────────────
    let tablaCohortes, tablaGrupos, tablaPeriodos, tablaModulos;
    let grmo_id_activo = null;
    let coho_id_activo = null;

    // ── Inicialización ───────────────────────────────────────────────────────
    cargarTablaCohortes();
    cargarTablaGrupos();
    cargarTablaPeriodos();
    cargarTablaModulos();
    cargarProgramasSelectores();
    cargarPeriodosSelector();
    cargarDocentesSelector();
    cargarGruposAsignacion();

    // ── Fix render DataTables en tabs ocultos ────────────────────────────────
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        if (tablaCohortes) tablaCohortes.columns.adjust();
        if (tablaGrupos) tablaGrupos.columns.adjust();
        if (tablaPeriodos) tablaPeriodos.columns.adjust();
        if (tablaModulos) tablaModulos.columns.adjust();
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
                { data: 'fechainicio' },
                { data: 'coho_activa', render: v =>
                    v == 1
                        ? '<span class="badge bg-success">Activa</span>'
                        : '<span class="badge bg-secondary">Inactiva</span>'
                },
                { data: null, render: (d, t, row) => {
                    let botones = `<button class="btn btn-sm btn-outline-primary me-1"
                        onclick="abrirEditarCohorte(${row.coho_id})">Editar</button>`;
                    if (row.coho_activa == 1 && row.total_estudiantes == 0 && row.total_grupos == 0) {
                        botones += `<button class="btn btn-sm btn-outline-danger"
                            onclick="confirmarEliminarCohorte(${row.coho_id}, '${row.coho_codigo.replace(/'/g, "\\'")}')">Eliminar</button>`;
                    } else if (row.coho_activa == 1 && (row.total_estudiantes > 0 || row.total_grupos > 0)) {
                        botones += `<button class="btn btn-sm btn-outline-warning"
                            onclick="toggleEstadoCohorte(${row.coho_id}, 0)">Desactivar</button>`;
                    } else {
                        botones += `<button class="btn btn-sm btn-outline-success"
                            onclick="toggleEstadoCohorte(${row.coho_id}, 1)">Activar</button>`;
                    }
                    return botones;
                }}
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

    function cargarTablaPeriodos() {
        if (tablaPeriodos) { tablaPeriodos.ajax.reload(); return; }
        tablaPeriodos = $('#tbl_periodos').DataTable({
            ajax: {
                url: 'grupos_mdl.php?accion=listar_periodos',
                type: 'POST',
                dataSrc: 'data'
            },
            columns: [
                { data: null, render: (d, t, r, m) => m.row + 1 },
                { data: 'peri_codigo' },
                { data: 'peri_anio' },
                { data: 'peri_semestre' },
                { data: 'fechainicio', render: v => v || '—' },
                { data: 'fechafin', render: v => v || '—' },
                { data: 'peri_activo', render: v =>
                    v == 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>'
                },
                { data: null, render: (d, t, row) => {
                    let botones = `<button class="btn btn-sm btn-outline-primary me-1"
                        onclick="abrirEditarPeriodo(${row.peri_id})">Editar</button>`;
                    if (row.peri_activo == 0) {
                        botones += `<button class="btn btn-sm btn-outline-success"
                            onclick="activarPeriodo(${row.peri_id})">Marcar como activo</button>`;
                    }
                    return botones;
                }}
            ],
            language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' },
            responsive: true
        });
    }

    function cargarTablaModulos() {
        if (tablaModulos) { tablaModulos.ajax.reload(); return; }
        tablaModulos = $('#tbl_modulos').DataTable({
            ajax: {
                url: 'grupos_mdl.php?accion=listar_modulos',
                type: 'POST',
                dataSrc: function (json) {
                    if (json.status !== 'ok') return [];
                    cacheModulos = {};
                    json.data.forEach(m => { cacheModulos[m.modu_id] = m; });
                    return json.data;
                }
            },
            columns: [
                { data: 'prog_nombre' },
                { data: 'modu_sigla' },
                { data: 'modu_nombre' },
                { data: 'modu_orden' },
                { data: 'modu_activo', render: v =>
                    v == 1
                        ? '<span class="badge bg-success">Activo</span>'
                        : '<span class="badge bg-secondary">Inactivo</span>'
                },
                { data: null, render: (d, t, row) => {
                    let botones = `<button class="btn btn-sm btn-outline-primary me-1"
                        onclick="abrirEditarModulo(${row.modu_id})">Editar</button>`;
                    if (row.modu_activo == 1 && row.total_grupos == 0) {
                        botones += `<button class="btn btn-sm btn-outline-danger"
                            onclick="confirmarEliminarModulo(${row.modu_id}, '${row.modu_nombre.replace(/'/g, "\\'")}')">Eliminar</button>`;
                    } else if (row.modu_activo == 1 && row.total_grupos > 0) {
                        botones += `<button class="btn btn-sm btn-outline-warning"
                            onclick="toggleEstadoModulo(${row.modu_id}, 0)">Desactivar</button>`;
                    } else {
                        botones += `<button class="btn btn-sm btn-outline-success"
                            onclick="toggleEstadoModulo(${row.modu_id}, 1)">Activar</button>`;
                    }
                    return botones;
                }}
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
                    opts += `<option value="${p.prog_id}" data-sigla="${p.prog_sigla}">${p.prog_sigla} — ${p.prog_nombre}</option>`;
                });
                $('#slct_prog_cohorte, #slct_prog_grupo, #slct_prog_modulo').html(opts);
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
                    opts += `<option value="${c.coho_id}">${c.coho_codigo}</option>`;
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
    // MODAL PERÍODO — botones
    // ════════════════════════════════════════════════════════════════════════

    // Autogenerar Código (AAAA-N) desde Año + Semestre — readonly permanente
    $('#peri_anio, #peri_semestre').on('change', function () {
        const anio = $('#peri_anio').val();
        const sem  = $('#peri_semestre').val();
        if (anio && sem) {
            $('#peri_codigo').val(`${anio}-${sem}`);
        }
    });

    $('#btn_nuevo_periodo').on('click', function () {
        $('#peri_id').val('');
        $('#peri_anio, #peri_fechainicio, #peri_fechafin').val('');
        $('#peri_codigo').val('').prop('readonly', true);
        $('#peri_semestre').val('1');
        $('#mdl_periodo_titulo').text('Nuevo Período');
        new bootstrap.Modal('#mdl_periodo').show();
    });

    $('#btn_guardar_periodo').on('click', function () {
        const anio   = $('#peri_anio').val();
        const sem    = $('#peri_semestre').val();
        const codigo = $('#peri_codigo').val().trim();
        if (!anio || !sem || !codigo) {
            alert('Complete los campos obligatorios');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=guardar_periodo',
            data: {
                peri_id:       $('#peri_id').val(),
                peri_codigo:   codigo,
                peri_anio:     anio,
                peri_semestre: sem,
                fechainicio:   $('#peri_fechainicio').val(),
                fechafin:      $('#peri_fechafin').val()
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance('#mdl_periodo').hide();
                    cargarTablaPeriodos();
                    cargarPeriodosSelector();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

    // ════════════════════════════════════════════════════════════════════════
    // MODAL GRUPO — autogenerar código (prog_sigla_peri_codigo_Ssemestre_jornada)
    // El campo es readonly permanente — siempre 100% autogenerado, sin edición
    // manual (mismo criterio que #peri_codigo en el modal Período).
    // ════════════════════════════════════════════════════════════════════════

    function generarSugerenciaCodigoGrupo() {
        const prog_sigla = $('#slct_prog_grupo option:selected').data('sigla');
        const peri_codigo = $('#slct_peri_grupo option:selected').text().trim();
        const grse_semestre = $('#grse_semestre').val();
        const jornadaMap = { 'Semana': 'SEM', 'Sabados': 'SAB' };
        const jornada_abrev = jornadaMap[$('#grse_jornada').val()];

        if (!prog_sigla || !peri_codigo || !grse_semestre || !jornada_abrev) return;

        $('#grse_codigo').val(`${prog_sigla}_${peri_codigo}_S${grse_semestre}_${jornada_abrev}`);
    }

    $('#slct_prog_grupo, #slct_peri_grupo, #grse_semestre, #grse_jornada').on('change', function () {
        generarSugerenciaCodigoGrupo();
    });

    // ════════════════════════════════════════════════════════════════════════
    // MODAL GRUPO — botones
    // ════════════════════════════════════════════════════════════════════════

    $('#btn_nuevo_grupo').on('click', function () {
        $('#grse_id').val('');
        $('#grse_codigo, #grse_fechainicio, #grse_fechafin').val('');
        $('#slct_prog_grupo, #slct_coho_grupo').val('');
        $('#slct_peri_grupo').val(periodoActivoId || '');
        $('#grse_semestre').val('1');
        $('#grse_jornada').val('Semana');
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
                grse_jornada:  $('#grse_jornada').val(),
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
        cargarSelectModulosPrograma(prog_id).then(function () {
            $('#grmo_id').val('');
            $('#grmo_grse_id').val(grse_id);
            $('#grmo_horario, #grmo_fechainicio, #grmo_fechafin').val('');
            $('#slct_doce_grupo').val('');
            $('#mdl_modulo_grupo_titulo').text('Asignar Módulo al Grupo');
            $('#btn_eliminar_modulo_grupo').addClass('d-none');
            new bootstrap.Modal('#mdl_modulo_grupo').show();
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

    $('#btn_eliminar_modulo_grupo').on('click', function () {
        const nombre = $('#slct_modu_grupo option:selected').text().trim();
        $('#spn_nombre_eliminar_grmo').text(nombre);
        new bootstrap.Modal(document.getElementById('mdl_confirmar_eliminar_grmo')).show();
    });

    $('#btn_confirmar_eliminar_grmo').on('click', function () {
        const grmo_id = $('#grmo_id').val();
        const grse_id = $('#grmo_grse_id').val();
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=eliminar_modulo_grupo',
            data: { grmo_id: grmo_id },
            dataType: 'json',
            success: function (r) {
                bootstrap.Modal.getInstance(document.getElementById('mdl_confirmar_eliminar_grmo')).hide();
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance(document.getElementById('mdl_modulo_grupo')).hide();
                    cargarModulosGrupo(grse_id);
                    cargarTablaGrupos();
                } else {
                    alert(r.message);
                }
            }
        });
    });

    // ════════════════════════════════════════════════════════════════════════
    // MODAL MÓDULO — botones
    // ════════════════════════════════════════════════════════════════════════

    $('#btn_nuevo_modulo').on('click', function () {
        $('#modu_id').val('');
        $('#modu_nombre, #modu_sigla, #modu_orden').val('');
        $('#slct_prog_modulo').val('');
        $('#mdl_modulo_titulo').text('Nuevo Módulo');
        new bootstrap.Modal('#mdl_modulo').show();
    });

    $('#btn_guardar_modulo').on('click', function () {
        const prog_id = $('#slct_prog_modulo').val();
        const nombre  = $('#modu_nombre').val().trim();
        const sigla   = $('#modu_sigla').val().trim();
        const orden   = $('#modu_orden').val();
        if (!prog_id || !nombre || !sigla || !orden) {
            alert('Complete los campos obligatorios');
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=guardar_modulo',
            data: {
                modu_id:     $('#modu_id').val(),
                prog_id:     prog_id,
                modu_nombre: nombre,
                modu_sigla:  sigla,
                modu_orden:  orden
            },
            dataType: 'json',
            success: function (r) {
                if (r.status === 'ok') {
                    bootstrap.Modal.getInstance('#mdl_modulo').hide();
                    cargarTablaModulos();
                } else {
                    alert('Error: ' + r.message);
                }
            }
        });
    });

    // Eliminar vive como acción de fila en la tabla (no dentro del modal de
    // edición) — al confirmar, solo hay un modal abierto: el de confirmación.
    $('#btn_confirmar_eliminar_modulo').on('click', function () {
        if (!moduloIdPendienteEliminar) return;
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=eliminar_modulo',
            data: { modu_id: moduloIdPendienteEliminar },
            dataType: 'json',
            success: function (r) {
                bootstrap.Modal.getInstance('#mdl_confirmar_eliminar_modulo').hide();
                if (r.status === 'ok') {
                    cargarTablaModulos();
                } else {
                    alert(r.message);
                }
                moduloIdPendienteEliminar = null;
            }
        });
    });

    // Eliminar vive como acción de fila en la tabla (no dentro del modal de
    // edición) — mismo patrón que btn_confirmar_eliminar_modulo.
    $('#btn_confirmar_eliminar_cohorte').on('click', function () {
        if (!cohorteIdPendienteEliminar) return;
        $.ajax({
            type: 'POST',
            url: 'grupos_mdl.php?accion=eliminar_cohorte',
            data: { coho_id: cohorteIdPendienteEliminar },
            dataType: 'json',
            success: function (r) {
                bootstrap.Modal.getInstance('#mdl_confirmar_eliminar_cohorte').hide();
                if (r.status === 'ok') {
                    cargarTablaCohortes();
                } else {
                    alert(r.message);
                }
                cohorteIdPendienteEliminar = null;
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
            $('#coho_fechainicio').val(d.fechainicio);
            $('#coho_activa').val(d.coho_activa);
            $('#bloque_activo_cohorte').removeClass('d-none');
            $('#mdl_cohorte_titulo').text('Editar Cohorte');
            new bootstrap.Modal('#mdl_cohorte').show();
        }
    });
}

function abrirEditarPeriodo(peri_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=obtener_periodo',
        data: { peri_id: peri_id },
        dataType: 'json',
        success: function (r) {
            if (r.status !== 'ok') { alert('Error al cargar'); return; }
            const d = r.data;
            $('#peri_id').val(d.peri_id);
            $('#peri_anio').val(d.peri_anio);
            $('#peri_semestre').val(d.peri_semestre);
            $('#peri_codigo').val(d.peri_codigo);
            $('#peri_fechainicio').val(d.fechainicio);
            $('#peri_fechafin').val(d.fechafin);
            $('#mdl_periodo_titulo').text('Editar Período');
            new bootstrap.Modal('#mdl_periodo').show();
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
            $('#grse_jornada').val(d.grse_jornada);
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
                        opts += `<option value="${c.coho_id}">${c.coho_codigo}</option>`;
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

function cargarSelectModulosPrograma(prog_id) {
    return $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=listar_modulos_por_programa',
        data: { prog_id: prog_id },
        dataType: 'json'
    }).then(function (r) {
        if (r.status !== 'ok') return;
        let opts = '<option value="">-- Seleccionar --</option>';
        r.data.forEach(m => {
            opts += `<option value="${m.modu_id}">Sem.${m.semestre_sugerido} — ${m.modu_sigla} ${m.modu_nombre}</option>`;
        });
        $('#slct_modu_grupo').html(opts);
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
            const prog_id = $('#slct_prog_grupo').val();
            cargarSelectModulosPrograma(prog_id).then(function () {
                $('#grmo_id').val(grmo_id);
                $('#grmo_grse_id').val(grse_id);
                $('#grmo_horario').val(mod.grmo_horario || '');
                $('#grmo_fechainicio').val(mod.fechainicio || '');
                $('#grmo_fechafin').val(mod.fechafin || '');
                $('#slct_modu_grupo').val(mod.modu_id);
                $('#slct_doce_grupo').val(mod.doce_id);
                $('#mdl_modulo_grupo_titulo').text('Editar Módulo');
                $('#btn_eliminar_modulo_grupo').removeClass('d-none');
                new bootstrap.Modal('#mdl_modulo_grupo').show();
            });
        }
    });
}

// Sin segundo AJAX — reutiliza la fila ya cacheada por listar_modulos.
function abrirEditarModulo(modu_id) {
    const d = cacheModulos[modu_id];
    if (!d) { alert('Módulo no encontrado'); return; }
    $('#modu_id').val(d.modu_id);
    $('#slct_prog_modulo').val(d.prog_id);
    $('#modu_nombre').val(d.modu_nombre);
    $('#modu_sigla').val(d.modu_sigla);
    $('#modu_orden').val(d.modu_orden);
    $('#mdl_modulo_titulo').text('Editar Módulo');
    new bootstrap.Modal('#mdl_modulo').show();
}

function confirmarEliminarModulo(modu_id, nombre) {
    moduloIdPendienteEliminar = modu_id;
    $('#spn_nombre_eliminar_modulo').text(nombre);
    new bootstrap.Modal('#mdl_confirmar_eliminar_modulo').show();
}

function confirmarEliminarCohorte(coho_id, codigo) {
    cohorteIdPendienteEliminar = coho_id;
    $('#spn_codigo_eliminar_cohorte').text(codigo);
    new bootstrap.Modal('#mdl_confirmar_eliminar_cohorte').show();
}

function toggleEstadoModulo(modu_id, nuevoEstado) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=toggle_estado_modulo',
        data: { modu_id: modu_id, modu_activo: nuevoEstado },
        dataType: 'json',
        success: function (r) {
            if (r.status === 'ok') {
                $('#tbl_modulos').DataTable().ajax.reload(null, false);
            } else {
                alert('Error: ' + r.message);
            }
        }
    });
}

function toggleEstadoCohorte(coho_id, nuevoEstado) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=toggle_estado_cohorte',
        data: { coho_id: coho_id, coho_activa: nuevoEstado },
        dataType: 'json',
        success: function (r) {
            if (r.status === 'ok') {
                $('#tbl_cohortes').DataTable().ajax.reload(null, false);
            } else {
                alert('Error: ' + r.message);
            }
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
            let activoId = '';
            r.data.forEach(p => {
                opts += `<option value="${p.peri_id}">${p.peri_codigo}</option>`;
                if (p.peri_activo == 1) activoId = p.peri_id;
            });
            $('#slct_peri_grupo').html(opts);
            periodoActivoId = activoId;
        }
    });
}

function activarPeriodo(peri_id) {
    $.ajax({
        type: 'POST',
        url: 'grupos_mdl.php?accion=activar_periodo',
        data: { peri_id: peri_id },
        dataType: 'json',
        success: function (r) {
            if (r.status === 'ok') {
                $('#tbl_periodos').DataTable().ajax.reload(null, false);
                cargarPeriodosSelector();
            } else {
                alert('Error: ' + r.message);
            }
        }
    });
}
