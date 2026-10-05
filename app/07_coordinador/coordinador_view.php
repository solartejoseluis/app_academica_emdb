<?php
session_start();
require_once '../01_login/check_session.php';
if (!in_array((int)$_SESSION['role_id'], [1, 2])) {
    header('Location: ../01_login/login_view.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .card-stat { border-top-width: 4px; }
    </style>
</head>
<body>

<?php require_once '../00_files/navbar.php'; ?>

<div class="container-fluid mt-4">

    <!-- ── Sección 1: Tarjetas de resumen ──────────────────────────────────── -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="card card-stat border-primary shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-2 mb-1">👥</div>
                    <div class="text-muted small mb-1">Estudiantes Activos</div>
                    <h2 class="mb-0 fw-bold" id="cnt_estudiantes">—</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-stat border-success shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-2 mb-1">👨‍🏫</div>
                    <div class="text-muted small mb-1">Docentes Activos</div>
                    <h2 class="mb-0 fw-bold" id="cnt_docentes">—</h2>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-stat border-warning shadow-sm text-center py-3">
                <div class="card-body">
                    <div class="fs-2 mb-1">📚</div>
                    <div class="text-muted small mb-1">Grupos Activos</div>
                    <h2 class="mb-0 fw-bold" id="cnt_grupos">—</h2>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Sección 2: Estado de notas por grupo ─────────────────────────── -->
    <h5 class="fw-bold mb-3">Estado de Notas por Grupo</h5>

    <div class="row g-2 mb-3" id="bloque_filtros_estado_notas">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="slct_filtro_peri_id">Período</label>
            <select class="form-select form-select-sm" id="slct_filtro_peri_id">
                <option value="">Todos los períodos</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="slct_filtro_prog_id">Programa</label>
            <select class="form-select form-select-sm" id="slct_filtro_prog_id">
                <option value="">Seleccione programa</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="slct_filtro_doce_id">Docente</label>
            <select class="form-select form-select-sm" id="slct_filtro_doce_id">
                <option value="">Seleccione docente</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tbl_estado_notas" style="width:100%">
            <thead class="table-dark">
                <tr>
                    <th>Grupo</th>
                    <th>Módulo</th>
                    <th>Docente</th>
                    <th class="text-center">Estudiantes</th>
                    <th class="text-center">Con Notas</th>
                    <th class="text-center">Completos</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Actualizado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="../00_files/helpers.js"></script>
<script src="coordinador_ctrl.js"></script>
<script>const MODULO_ACTUAL = '07_coordinador';</script>
<script src="../00_files/ayuda_sidebar.js"></script>
</body>
</html>
