<?php
session_start();
require_once '../01_login/check_session.php';
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
        <span class="text-light small"><?= htmlspecialchars($_SESSION['usua_email']) ?></span>
        <a href="/app_academica_emdb/app/01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-4">

    <div class="row">

        <!-- Panel izquierdo: lista de grupos -->
        <div class="col-md-3" id="panel_grupos">
            <h6 class="fw-bold mb-3">Mis Módulos</h6>
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
                                <th class="text-center">N3<br><small class="fw-normal">20%</small></th>
                                <th class="text-center">N4<br><small class="fw-normal">40%</small></th>
                                <th class="text-center celda-sup">Sup N4</th>
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
                    </small>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="calificaciones_ctrl.js"></script>
</body>
</html>
