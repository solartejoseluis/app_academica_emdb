<?php
session_start();
require_once '../01_login/check_session.php';
require_once '../00_files/helpers.php';
if ($_SESSION['role_id'] !== 1 && $_SESSION['role_id'] !== 2 && $_SESSION['role_id'] !== 3) {
    header('Location: ../01_login/login_view.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .tabla-notas td { vertical-align: middle; }
        .input-nota {
            width: 60px;
            text-align: center;
            padding: 2px 4px;
            font-size: 0.9em;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
        }
        .input-nota:focus {
            border-color: #0d6efd;
            outline: none;
            background: #f0f4ff;
        }
        .input-nota.guardando {
            background: #fff3cd;
            border-color: #ffc107;
        }
        .input-nota.guardado {
            background: #d1e7dd;
            border-color: #198754;
        }
        .input-nota.error {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .celda-sup { background: #fff8e1; }
        .celda-hab { background: #ede7f6; }
        .definitiva-badge {
            font-size: 1em;
            font-weight: bold;
            min-width: 48px;
            display: inline-block;
            text-align: center;
        }
        .grupo-card {
            cursor: pointer;
            transition: box-shadow 0.15s;
        }
        .grupo-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .grupo-card.activo { border-color: #0d6efd; box-shadow: 0 0 0 2px #0d6efd33; }
        .semaforo-verde    { background-color: #d4edda !important; }
        .semaforo-amarillo { background-color: #fff3cd !important; }
        .semaforo-rojo     { background-color: #f8d7da !important; }
    </style>
</head>
<body>

<?php if (in_array((int)$_SESSION['role_id'], [1, 2])): ?>
<?php require_once '../00_files/navbar.php'; ?>
<?php else: ?>
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">EMDB Académica</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small">
            <?= htmlspecialchars($_SESSION['usua_nombre'] ?? '') ?> — <?= htmlspecialchars($_SESSION['usua_email']) ?> — Último acceso: <?= htmlspecialchars(formatearUltimoAcceso($_SESSION['usua_ultimo_acceso_anterior'] ?? null, $_SESSION['usua_fechacreacion'] ?? null)) ?>
        </span>
        <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasAyuda" aria-controls="offcanvasAyuda">
            ❓ Ayuda
        </button>
        <a href="/app_academica_emdb/app/01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>

<!-- Docente no pasa por navbar.php (roles 1/2 únicamente), pero sí necesita
     el offcanvas de ayuda — 05_calificaciones es justo el piloto donde el
     docente es la audiencia principal del contenido de ayuda. -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasAyuda" aria-labelledby="offcanvasAyudaLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="offcanvasAyudaLabel">Ayuda</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div id="contenido_ayuda_offcanvas">
            <div class="text-muted small">Cargando...</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container-fluid mt-4">

    <div class="row">

        <!-- Panel izquierdo: lista de grupos -->
        <div class="col-md-3" id="panel_grupos">
            <h6 class="fw-bold mb-3">
                <?= in_array((int)$_SESSION['role_id'], [1, 2]) ? 'Módulos' : 'Mis Módulos' ?>
            </h6>
            <?php if (in_array((int)$_SESSION['role_id'], [1, 2])): ?>
            <div id="bloque_filtros_grupos" class="mb-3">
                <div class="mb-2">
                    <label class="form-label small mb-1">Profesor</label>
                    <select class="form-select form-select-sm" id="slct_filtro_doce_id">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Programa</label>
                    <select class="form-select form-select-sm" id="slct_filtro_prog_id">
                        <option value="">Todos</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">Período</label>
                    <select class="form-select form-select-sm" id="slct_filtro_peri_id">
                        <option value="">Todos</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <div id="lista_grupos">
                <div class="text-muted small">Cargando...</div>
            </div>
        </div>

        <!-- Panel derecho: planilla de notas -->
        <div class="col-md-9" id="panel_notas">
            <div id="msg_seleccione" class="text-muted mt-5 text-center">
                <i>Seleccione un módulo para ver y registrar calificaciones</i>
            </div>
            <div id="contenedor_notas" style="display:none">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0" id="titulo_modulo">—</h5>
                        <small class="text-muted" id="subtitulo_modulo">—</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-secondary" id="badge_total_estudiantes">0 estudiantes</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover tabla-notas" id="tbl_calificaciones">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Estudiante</th>
                                <th>Documento</th>
                                <th class="text-center">N1<br><small class="fw-normal">20%</small></th>
                                <th class="text-center celda-sup">Sup N1</th>
                                <th class="text-center">N2<br><small class="fw-normal">20%</small></th>
                                <th class="text-center celda-sup">Sup N2</th>
                                <th class="text-center">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#mdl_registro_n3" class="text-decoration-none text-reset">N3</a>
                                    <br><small class="fw-normal">20%</small>
                                </th>
                                <th class="text-center">N4<br><small class="fw-normal">40%</small></th>
                                <th class="text-center celda-sup">Sup N4</th>
                                <th class="text-center">Nota Final</th>
                                <th class="text-center celda-hab">Habilitación</th>
                                <th class="text-center">Definitiva</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_calificaciones">
                        </tbody>
                    </table>
                </div>

                <div class="mt-2">
                    <small class="text-muted">
                        Las notas se guardan automáticamente al salir de cada campo.
                        Los supletorios se habilitan cuando la nota original es 0.0.
                        N3 no tiene supletorio.
                        Nota Final siempre se calcula con la fórmula N1(20%)+N2(20%)+N3(20%)+N4(40%), sin importar si aprueba o no.
                        Habilitación se habilita cuando Nota Final es menor a 3.0.
                        Definitiva es el valor oficial: copia de Nota Final si aprueba, o de Habilitación si esta fue registrada.
                    </small>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal Registro y cálculo de N3 (Fase 2.14.E1 — estructura; JS en E2/E3) -->
<div class="modal fade" id="mdl_registro_n3" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registro y cálculo de N3</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">
                    En esta sección el docente recopila las actividades y las
                    calificaciones que al final son promediadas y equivalen a
                    la N3 que tiene un peso del 20% en la nota final de cada
                    estudiante.
                </p>
                <div class="mb-3">
                    <!-- Bootstrap 5.3 no soporta bien un modal-sobre-modal
                         (el backdrop/scroll del primero queda inconsistente
                         al cerrar el segundo) — este botón NO usa
                         data-bs-toggle/target directo. La Fase E2 lo conecta
                         por JS: cierra este modal y abre #mdl_configurar_actividades_n3. -->
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn_abrir_configurar_n3">Configurar</button>
                </div>
                <div class="table-responsive">
                    <table id="tbl_registro_n3" class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Estudiante</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Configurar actividades de N3 (Fase 2.14.E1 — estructura; JS en E2) -->
<div class="modal fade" id="mdl_configurar_actividades_n3" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configurar actividades</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="lista_actividades_n3"></div>
                <hr>
                <form id="frm_actividad_n3">
                    <div class="mb-3">
                        <label class="form-label">Nombre de la actividad</label>
                        <input type="text" class="form-control" id="txt_acn3_nombre" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comentarios</label>
                        <textarea class="form-control" id="txt_acn3_comentario" maxlength="255" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_actividad_n3">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="calificaciones_ctrl.js"></script>
<script>const MODULO_ACTUAL = '05_calificaciones';</script>
<script src="../00_files/ayuda_sidebar.js"></script>
</body>
</html>
