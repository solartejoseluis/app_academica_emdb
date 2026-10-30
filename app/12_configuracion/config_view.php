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
    <title>Configuración — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
</head>
<body class="bg-light">

<?php require_once '../00_files/navbar.php'; ?>

<div class="container-fluid mt-4 px-4">
    <div class="row g-4">

        <!-- Menú lateral: Institución es el único panel propio; el resto enlaza a su pantalla -->
        <div class="col-md-3">
            <h6 class="text-muted text-uppercase small mb-2">Configuración</h6>
            <div class="list-group shadow-sm mb-4">
                <a class="list-group-item list-group-item-action active" id="lnk_institucion"
                   href="#bloque_institucion" aria-current="true">Institución</a>
                <a class="list-group-item list-group-item-action" id="lnk_usuarios"
                   href="../08_admin/admin_view.php">Usuarios</a>
                <a class="list-group-item list-group-item-action" id="lnk_ayudas"
                   href="../10_ayudas/ayud_view.php">Ayudas</a>
            </div>

            <h6 class="text-muted text-uppercase small mb-2">Catálogos académicos</h6>
            <div class="list-group shadow-sm">
                <a class="list-group-item list-group-item-action" id="lnk_requisitos"
                   href="../02_estudiantes/est_view.php#config-requisitos">Requisitos por programa</a>
                <a class="list-group-item list-group-item-action" id="lnk_programas"
                   href="../04_grupos/grupos_view.php#programas">Programas</a>
                <a class="list-group-item list-group-item-action" id="lnk_modulos"
                   href="../04_grupos/grupos_view.php#modulos">Módulos</a>
                <a class="list-group-item list-group-item-action" id="lnk_periodos"
                   href="../04_grupos/grupos_view.php#periodos">Períodos</a>
                <a class="list-group-item list-group-item-action" id="lnk_cohortes"
                   href="../04_grupos/grupos_view.php#cohortes">Cohortes</a>
            </div>
        </div>

        <!-- Panel Institución -->
        <div class="col-md-9">
            <div id="bloque_institucion">
                <h5 class="mb-3">Institución</h5>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="npt_institucion_nombre">Nombre de la institución</label>
                                <input type="text" class="form-control" id="npt_institucion_nombre" maxlength="150" autocomplete="off">
                                <div class="form-text">Aparece en el encabezado de los PDF y de los reportes.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="npt_matr_numero_inicial">Número de matrícula inicial</label>
                                <input type="number" class="form-control" id="npt_matr_numero_inicial" min="1" autocomplete="off">
                                <div class="form-text">Solo aplica al primer estudiante matriculado desde que se guarda — las matrículas ya generadas con un número no se ven afectadas retroactivamente.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="npt_director_nombre">Nombre del Director</label>
                                <input type="text" class="form-control texto-mayus" id="npt_director_nombre" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="npt_secretario_nombre">Nombre del Secretario(a)</label>
                                <input type="text" class="form-control texto-mayus" id="npt_secretario_nombre" autocomplete="off">
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary btn-sm" id="btn_guardar_configuracion">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../00_files/helpers.js"></script>
<script src="config_ctrl.js"></script>
<script>const MODULO_ACTUAL = '12_configuracion';</script>
<script src="../00_files/ayuda_sidebar.js"></script>
</body>
</html>
