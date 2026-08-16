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
    <title>Ayudas — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php require_once '../00_files/navbar.php'; ?>

<div class="container-fluid mt-4 px-4">

    <!-- Encabezado y botón nuevo -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Gestión de Contenido de Ayuda</h5>
        <button class="btn btn-primary btn-sm" id="btn_nueva_ayuda">
            + Nueva Ayuda
        </button>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <table id="tbl_ayudas" class="table table-hover table-bordered w-100">
                <thead class="table-dark">
                    <tr>
                        <th>Sección</th>
                        <th>Título</th>
                        <th>Orden</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== Modal Nueva/Editar Ayuda ===== -->
<div class="modal fade" id="mdl_ayuda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="mdl_ayuda_titulo">Nueva Ayuda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ayud_id">
                <div class="mb-3">
                    <label class="form-label">Sección <span class="text-danger">*</span></label>
                    <select class="form-select" id="slct_ayud_seccion">
                        <option value="">-- Seleccionar --</option>
                        <!-- Agregar aquí nuevas secciones cuando se expanda a más módulos -->
                        <option value="02_estudiantes">02_estudiantes — Estudiantes</option>
                        <option value="03_docentes">03_docentes — Docentes</option>
                        <option value="04_grupos">04_grupos — Grupos</option>
                        <option value="05_calificaciones">05_calificaciones — Calificaciones</option>
                        <option value="06_reportes">06_reportes — Reportes</option>
                        <option value="07_coordinador">07_coordinador — Dashboard</option>
                        <option value="08_admin">08_admin — Usuarios</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Título <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="ayud_titulo" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contenido <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="ayud_contenido" rows="6"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Orden</label>
                    <input type="number" class="form-control" id="ayud_orden" min="0" value="0">
                </div>
                <div class="mb-3 d-none" id="bloque_activo_ayuda">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="slct_ayud_activo">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_ayuda">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Modal Confirmar Eliminar Ayuda ===== -->
<div class="modal fade" id="mdl_confirmar_eliminar_ayuda" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Eliminar ayuda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Eliminar la ayuda <strong id="spn_titulo_eliminar_ayuda"></strong> de forma permanente? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn_confirmar_eliminar_ayuda">Eliminar definitivamente</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="ayud_ctrl.js"></script>
</body>
</html>
