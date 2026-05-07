<?php
session_start();
require_once '../01_login/check_session.php';
$role_id = (int)$_SESSION['role_id'];
if (!in_array($role_id, [1, 2, 4])) {
    header('Location: ../01_login/login_view.php');
    exit;
}
$es_coordinador = in_array($role_id, [1, 2]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <?php if ($es_coordinador): ?>
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">EMDB Académica</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small"><?= htmlspecialchars($_SESSION['usua_email']) ?></span>
        <a href="../01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>

<div class="container-fluid mt-4">

<?php if (!$es_coordinador): ?>
    <!-- ── Vista Estudiante: Mis Notas ───────────────────────────────────── -->
    <div class="row justify-content-center">
        <div class="col-md-10">
            <h5 class="fw-bold mb-3">Mis Notas</h5>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Módulo</label>
                            <select class="form-select" id="sel_modulo">
                                <option value="">— Seleccione un módulo —</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary w-100" id="btn_ver_notas">Ver Notas</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="info_grupo" class="alert alert-info mb-3" style="display:none">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Módulo:</strong> <span id="spn_modulo_nombre">—</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Grupo:</strong> <span id="spn_grupo_codigo">—</span>
                    </div>
                    <div class="col-md-4">
                        <strong>Docente:</strong> <span id="spn_docente">—</span>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="tbl_mis_notas">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center">N1<br><small class="fw-normal">20%</small></th>
                            <th class="text-center">Sup N1</th>
                            <th class="text-center">N2<br><small class="fw-normal">20%</small></th>
                            <th class="text-center">Sup N2</th>
                            <th class="text-center">N3<br><small class="fw-normal">20%</small></th>
                            <th class="text-center">N4<br><small class="fw-normal">40%</small></th>
                            <th class="text-center">Sup N4</th>
                            <th class="text-center">Definitiva</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_mis_notas">
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                Seleccione un módulo para ver sus notas
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ── Vista Admin/Coordinador: Reporte por Grupo ────────────────────── -->
    <div class="row">
        <div class="col-12">
            <h5 class="fw-bold mb-3">Reporte por Grupo</h5>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-9">
                            <label class="form-label fw-semibold">Grupo Módulo</label>
                            <select class="form-select" id="sel_grupo">
                                <option value="">— Seleccione un grupo —</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btn_cargar_reporte">
                                Cargar Reporte
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="tbl_reporte" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Apellidos</th>
                            <th>Nombres</th>
                            <th>Documento</th>
                            <th class="text-center">N1</th>
                            <th class="text-center">Sup N1</th>
                            <th class="text-center">N2</th>
                            <th class="text-center">Sup N2</th>
                            <th class="text-center">N3</th>
                            <th class="text-center">N4</th>
                            <th class="text-center">Sup N4</th>
                            <th class="text-center">Definitiva</th>
                            <th class="text-center">Estado</th>
                            <th>Observación</th>
                        </tr>
                    </thead>
                    <tbody id="tbody_reporte">
                        <tr>
                            <td colspan="14" class="text-center text-muted">
                                Seleccione un grupo y haga clic en Cargar Reporte
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<?php if ($es_coordinador): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<?php endif; ?>
<script src="reportes_ctrl.js"></script>
</body>
</html>
