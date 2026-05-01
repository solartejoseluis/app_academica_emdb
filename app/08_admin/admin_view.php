<?php
session_start();
require_once '../01_login/check_session.php';

if ($_SESSION['role_id'] !== 1) {
    header('Location: /app_academica_emdb/app/01_login/login_view.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- Navbar -->
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">Panel Administrador — EMDB</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small"><?= htmlspecialchars($_SESSION['usua_email']) ?></span>
        <a href="../01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>

<div class="container-fluid mt-4 px-4">

    <!-- Encabezado y botón nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Gestión de Usuarios</h5>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mdl_nuevo_usuario">
            + Nuevo Usuario
        </button>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table id="tbl_usuarios" class="table table-hover table-bordered w-100">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Fecha creación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== Modal Nuevo Usuario ===== -->
<div class="modal fade" id="mdl_nuevo_usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="npt_email" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="npt_password" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select class="form-select" id="slct_role_id">
                        <option value="">-- Seleccionar --</option>
                    </select>
                    <input type="hidden" id="npt_role_id" value="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_usuario">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal Editar Usuario ===== -->
<div class="modal fade" id="mdl_editar_usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="npt_usua_id_editar" value="">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="npt_email_editar" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="npt_password_editar"
                           placeholder="Dejar vacío para no cambiar" autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select class="form-select" id="slct_role_id_editar">
                        <option value="">-- Seleccionar --</option>
                    </select>
                    <input type="hidden" id="npt_role_id_editar" value="">
                </div>
                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="npt_activo_editar">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_editar">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="admin_ctrl.js"></script>
</body>
</html>
