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
    <title>Docentes — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

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

<div class="container-fluid mt-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Gestión de Docentes</h5>
        <button class="btn btn-primary btn-sm" id="btn_nuevo_docente">+ Nuevo Docente</button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table id="tbl_docentes" class="table table-hover table-bordered w-100">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Nombres</th>
                        <th>Apellidos</th>
                        <th>Sigla</th>
                        <th>Correo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Nuevo / Editar Docente -->
<div class="modal fade" id="mdl_docente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdl_docente_titulo">Nuevo Docente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="npt_doce_id" value="">

                <div class="mb-3">
                    <label class="form-label">Nombres</label>
                    <input type="text" class="form-control texto-mayus" id="npt_doce_nombres" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Apellidos</label>
                    <input type="text" class="form-control texto-mayus" id="npt_doce_apellidos" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sigla <span class="text-muted small">(máx. 10 caracteres)</span></label>
                    <input type="text" class="form-control texto-mayus" id="npt_doce_sigla" maxlength="10" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Correo electrónico</label>
                    <input type="email" class="form-control" id="npt_usua_email" autocomplete="off">
                </div>

                <div class="mb-3" id="bloque_password">
                    <label class="form-label">Contraseña inicial</label>
                    <input type="password" class="form-control" id="npt_usua_password"
                           placeholder="Contraseña inicial" autocomplete="new-password">
                </div>

                <div class="mb-3 d-none" id="bloque_activo">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="slct_usua_activo">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_docente">Guardar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="doc_ctrl.js"></script>
</body>
</html>
