<?php
session_start();
require_once '../01_login/check_session.php';
if ($_SESSION['role_id'] !== 1 && $_SESSION['role_id'] !== 2) {
    header('Location: ../01_login/login_view.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
.lista-item {
    cursor: pointer;
    user-select: none;
    padding: 8px 12px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: background-color 0.15s;
}
.lista-item:hover {
    background-color: #e8f0fe;
}
.lista-item input[type="checkbox"] {
    width: 20px !important;
    height: 20px !important;
    min-width: 20px !important;
    min-height: 20px !important;
    cursor: pointer;
    accent-color: #0d6efd;
    border: 2px solid #0d6efd !important;
    border-radius: 4px !important;
    flex-shrink: 0;
    appearance: auto !important;
    -webkit-appearance: checkbox !important;
    opacity: 1 !important;
    visibility: visible !important;
    position: relative !important;
    margin: 0 !important;
}
.lista-item label {
    cursor: pointer;
    font-size: 0.95em;
    margin-bottom: 0;
    flex: 1;
}
</style>
</head>
<body>

<?php if (in_array((int)$_SESSION['role_id'], [1, 2])): ?>
<?php require_once '../00_files/navbar.php'; ?>
<?php else: ?>
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">EMDB Académica</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small"><?= htmlspecialchars($_SESSION['usua_email']) ?></span>
        <a href="/app_academica_emdb/app/01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-4">
    <h4 class="mb-3">Gestión de Grupos</h4>

    <!-- Pestañas principales -->
    <ul class="nav nav-tabs mb-3" id="tabsGrupos">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab"
                    data-bs-target="#tab_cohortes">Cohortes</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#tab_grupos">Grupos Semestre</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab"
                    data-bs-target="#tab_asignacion">Asignación Estudiantes</button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- TAB 1: COHORTES -->
        <div class="tab-pane fade show active" id="tab_cohortes">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Cohortes</h5>
                <button class="btn btn-primary btn-sm" id="btn_nueva_cohorte">
                    + Nueva Cohorte
                </button>
            </div>
            <table id="tbl_cohortes" class="table table-bordered table-hover w-100">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Programa</th>
                        <th>Jornada</th>
                        <th>Fecha Inicio</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>

        <!-- TAB 2: GRUPOS SEMESTRE -->
        <div class="tab-pane fade" id="tab_grupos">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Grupos Semestre</h5>
                <button class="btn btn-primary btn-sm" id="btn_nuevo_grupo">
                    + Nuevo Grupo
                </button>
            </div>
            <table id="tbl_grupos" class="table table-bordered table-hover w-100">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>Cohorte</th>
                        <th>Programa</th>
                        <th>Período</th>
                        <th>Semestre</th>
                        <th>Módulos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>

        <!-- TAB 3: ASIGNACIÓN DE ESTUDIANTES -->
        <div class="tab-pane fade" id="tab_asignacion">
            <h5 class="mb-3">Asignación de Estudiantes a Módulos</h5>

            <!-- Selector de grupo -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Seleccionar Grupo Semestre</label>
                    <select class="form-select" id="slct_grupo_asignacion">
                        <option value="">-- Seleccionar grupo --</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Módulo</label>
                    <select class="form-select" id="slct_modulo_asignacion">
                        <option value="">-- Primero seleccione un grupo --</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button class="btn btn-secondary btn-sm" id="btn_cargar_asignacion">
                        Cargar
                    </button>
                </div>
            </div>

            <!-- Panel dual -->
            <div class="row" id="panel_asignacion" style="display:none!important">
                <!-- Izquierda: estudiantes disponibles -->
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header bg-secondary text-white d-flex justify-content-between">
                            <span>Estudiantes Disponibles</span>
                            <span class="badge bg-light text-dark" id="badge_disponibles">0</span>
                        </div>
                        <div class="card-body p-2">
                            <input type="text" class="form-control form-control-sm mb-2"
                                   id="filtro_disponibles" placeholder="Filtrar...">
                            <div id="lista_disponibles" style="max-height:400px;overflow-y:auto;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Centro: botones de acción -->
                <div class="col-md-2 d-flex flex-column justify-content-center align-items-center gap-2">
                    <button class="btn btn-success btn-sm w-100" id="btn_asignar_seleccionados">
                        Asignar →
                    </button>
                    <button class="btn btn-outline-success btn-sm w-100" id="btn_asignar_todos">
                        Asignar todos →
                    </button>
                    <button class="btn btn-danger btn-sm w-100" id="btn_retirar_seleccionados">
                        ← Retirar
                    </button>
                </div>

                <!-- Derecha: estudiantes en el módulo -->
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between">
                            <span>En este Módulo</span>
                            <span class="badge bg-light text-dark" id="badge_asignados">0</span>
                        </div>
                        <div class="card-body p-2">
                            <input type="text" class="form-control form-control-sm mb-2"
                                   id="filtro_asignados" placeholder="Filtrar...">
                            <div id="lista_asignados" style="max-height:400px;overflow-y:auto;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="msg_seleccione" class="text-muted mt-3">
                Seleccione un grupo y un módulo para gestionar estudiantes.
            </div>
        </div>

    </div><!-- /tab-content -->
</div><!-- /container -->

<!-- MODAL COHORTE -->
<div class="modal fade" id="mdl_cohorte" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="mdl_cohorte_titulo">Nueva Cohorte</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="coho_id">
                <div class="mb-3">
                    <label class="form-label">Programa <span class="text-danger">*</span></label>
                    <select class="form-select" id="slct_prog_cohorte">
                        <option value="">-- Seleccionar --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Código de Cohorte <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="coho_codigo"
                           placeholder="Ej: CH-ASO-2025B">
                    <div class="form-text">Formato: CH-SIGLA-AAAAP (P = A o B)</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Jornada <span class="text-danger">*</span></label>
                    <select class="form-select" id="coho_jornada">
                        <option value="Semana">Semana</option>
                        <option value="Sabados">Sábados</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" id="coho_fechainicio">
                </div>
                <div class="mb-3 d-none" id="bloque_activo_cohorte">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="coho_activa">
                        <option value="1">Activa</option>
                        <option value="0">Inactiva</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary"
                        id="btn_guardar_cohorte">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL GRUPO SEMESTRE -->
<div class="modal fade" id="mdl_grupo" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="mdl_grupo_titulo">Nuevo Grupo Semestre</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grse_id">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Programa <span class="text-danger">*</span></label>
                        <select class="form-select" id="slct_prog_grupo">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cohorte <span class="text-danger">*</span></label>
                        <select class="form-select" id="slct_coho_grupo">
                            <option value="">-- Primero seleccione programa --</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Período <span class="text-danger">*</span></label>
                        <select class="form-select" id="slct_peri_grupo">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Semestre <span class="text-danger">*</span></label>
                        <select class="form-select" id="grse_semestre">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="grse_codigo"
                               placeholder="Ej: ASO-2025B-S1">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="grse_fechainicio">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="grse_fechafin">
                    </div>
                    <div class="col-md-6 mb-3 d-none" id="bloque_activo_grupo">
                        <label class="form-label">Estado</label>
                        <select class="form-select" id="grse_activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>

                <!-- Sub-sección: módulos del grupo -->
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Módulos asignados a este grupo</h6>
                    <button type="button" class="btn btn-outline-primary btn-sm"
                            id="btn_agregar_modulo" disabled>
                        + Agregar Módulo
                    </button>
                </div>
                <table id="tbl_modulos_grupo" class="table table-sm table-bordered w-100">
                    <thead class="table-secondary">
                        <tr>
                            <th>Módulo</th>
                            <th>Docente</th>
                            <th>Horario</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estudiantes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tbody_modulos_grupo">
                        <tr><td colspan="7" class="text-center text-muted">
                            Guarde el grupo primero para agregar módulos
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary"
                        id="btn_guardar_grupo">Guardar Grupo</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL MÓDULO DEL GRUPO -->
<div class="modal fade" id="mdl_modulo_grupo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Asignar Módulo al Grupo</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="grmo_id">
                <input type="hidden" id="grmo_grse_id">
                <div class="mb-3">
                    <label class="form-label">Módulo <span class="text-danger">*</span></label>
                    <select class="form-select" id="slct_modu_grupo">
                        <option value="">-- Seleccionar --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Docente <span class="text-danger">*</span></label>
                    <select class="form-select" id="slct_doce_grupo">
                        <option value="">-- Seleccionar --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Horario</label>
                    <input type="text" class="form-control" id="grmo_horario"
                           placeholder="Ej: 8:00 - 11:00 a.m.">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="grmo_fechainicio">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="grmo_fechafin">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary"
                        id="btn_guardar_modulo_grupo">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="grupos_ctrl.js"></script>
</body>
</html>
