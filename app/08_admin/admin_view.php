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
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
</head>
<body class="bg-light">

<?php require_once '../00_files/navbar.php'; ?>

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

    <!-- Configuración institucional -->
    <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
        <h5 class="mb-0">Configuración Institucional</h5>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Número de matrícula inicial</label>
                    <input type="number" class="form-control" id="npt_matr_numero_inicial" min="1" autocomplete="off">
                    <div class="form-text">Solo aplica al primer estudiante matriculado desde que se guarda — las matrículas ya generadas con un número no se ven afectadas retroactivamente.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nombre del Director</label>
                    <input type="text" class="form-control texto-mayus" id="npt_director_nombre" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nombre del Secretario(a)</label>
                    <input type="text" class="form-control texto-mayus" id="npt_secretario_nombre" autocomplete="off">
                </div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-primary btn-sm" id="btn_guardar_configuracion">Guardar configuración</button>
            </div>
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
<script>const MODULO_ACTUAL = '08_admin';</script>
<script src="../00_files/ayuda_sidebar.js"></script>
</body>
</html>
