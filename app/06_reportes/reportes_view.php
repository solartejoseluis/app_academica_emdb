<?php
session_start();
require_once '../01_login/check_session.php';
require_once '../00_files/helpers.php';
require_once '../00_connect/pdo.php';
$role_id = (int)$_SESSION['role_id'];
if (!in_array($role_id, [1, 2, 4])) {
    header('Location: ../01_login/login_view.php');
    exit;
}
$es_coordinador = in_array($role_id, [1, 2]);

$pdo = getConexion();

// Nombre institucional — un solo valor fijo, se resuelve server-side sin
// necesidad de un endpoint AJAX (mismo criterio para el nombre del propio
// estudiante cuando role_id=4, ver más abajo).
$stmtConf = $pdo->prepare("SELECT institucion_nombre FROM configuracion WHERE config_id = 1");
$stmtConf->execute();
$institucionNombre = $stmtConf->fetchColumn() ?: 'Escuela de Mecánica Dental Bolaños (EMDB)';

// Para el estudiante (role 4) el estu_id ya se conoce por sesión — se
// resuelve su nombre aquí mismo para el encabezado, evitando un viaje AJAX
// extra solo para un dato ya disponible del lado servidor. Para el
// coordinador/admin, el nombre se completa vía JS al elegir un estudiante
// del buscador (buscar_estudiante ya lo trae en su respuesta).
$estudianteObjetivoNombre = null;
if (!$es_coordinador) {
    $stmtEst = $pdo->prepare("SELECT estu_nombres, estu_apellidos FROM estudiantes WHERE estu_id = ?");
    $stmtEst->execute([(int)($_SESSION['estu_id'] ?? 0)]);
    $est = $stmtEst->fetch();
    if ($est) {
        $estudianteObjetivoNombre = $est['estu_apellidos'] . ', ' . $est['estu_nombres'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($es_coordinador): ?>
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <?php endif; ?>
</head>
<body>

<?php if ($es_coordinador): ?>
<?php require_once '../00_files/navbar.php'; ?>
<?php else: ?>
<!-- Estudiante (role_id=4) no pasa por navbar.php (roles 1/2 únicamente,
     y sus enlaces apuntan a módulos sin acceso para Estudiante) — mismo
     patrón de <nav> fallback + offcanvas de ayuda duplicado ya usado en
     calificaciones_view.php para Docente. No se tocó navbar.php: ampliarlo
     con enlaces condicionados por rol es un cambio de arquitectura propio,
     fuera de alcance de este rediseño de 06_reportes. -->
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
<div class="row justify-content-center">
<div class="col-md-10">

    <?php if ($es_coordinador): ?>
    <!-- ── Pestañas de nivel superior (solo coordinador/admin) ─────────────── -->
    <ul class="nav nav-tabs mb-3" id="tabsReportesNivelSuperior">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab_reporte_grupo" type="button">Reporte por Grupo</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab_reporte_estudiante" type="button">Reporte por Estudiante</button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ═══ Reporte por Grupo — recuperado tal cual del commit ef429bd ═══ -->
        <div class="tab-pane fade show active" id="tab_reporte_grupo">
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

                    <div id="info_reporte_grupo" class="alert alert-info mb-3" style="display:none">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <strong>Módulo:</strong> <span id="spn_ctx_modulo">—</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Grupo:</strong> <span id="spn_ctx_grupo">—</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Docente:</strong> <span id="spn_ctx_docente">—</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Programa:</strong> <span id="spn_ctx_programa">—</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Período:</strong> <span id="spn_ctx_periodo">—</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Jornada:</strong> <span id="spn_ctx_jornada">—</span>
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
                                    <th class="text-center">Nota Final</th>
                                    <th class="text-center">Definitiva</th>
                                    <th class="text-center">Estado</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_reporte">
                                <tr>
                                    <td colspan="15" class="text-center text-muted">
                                        Seleccione un grupo y haga clic en Cargar Reporte
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Reporte por Estudiante (Fase 3, sin cambios) ═══ -->
        <div class="tab-pane fade" id="tab_reporte_estudiante">
    <?php endif; ?>

    <?php if ($es_coordinador): ?>
            <!-- ── Buscador de estudiante (solo coordinador/admin) ─────────── -->
            <div class="card mb-3" id="bloque_buscador_estudiante">
                <div class="card-body">
                    <label class="form-label fw-semibold">Buscar estudiante</label>
                    <div class="position-relative">
                        <input type="text" class="form-control" id="npt_buscar_estudiante" autocomplete="off"
                               placeholder="Nombre, apellido o documento (mínimo 3 caracteres)">
                        <div class="list-group position-absolute w-100 shadow-sm" id="lista_resultados_estudiante"
                             style="z-index:1000; display:none; max-height:260px; overflow-y:auto;"></div>
                    </div>
                </div>
            </div>

            <div class="alert alert-secondary d-none align-items-center justify-content-between" id="bloque_estudiante_elegido">
                <span><strong>Estudiante:</strong> <span id="spn_estudiante_elegido_nombre">—</span></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_cambiar_estudiante">Cambiar estudiante</button>
            </div>
    <?php endif; ?>

            <!-- ── Reporte (oculto hasta elegir estudiante, si aplica) ─────── -->
            <div id="bloque_reporte" class="<?= $es_coordinador ? 'd-none' : '' ?>">

                <?php if (!$es_coordinador): ?>
                <div id="barra_requisitos_pendientes" class="alert d-none mb-3" role="alert"></div>
                <?php endif; ?>

                <div class="mb-3">
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($institucionNombre) ?></h5>
                    <h6 class="text-muted mb-0">
                        Informe de Calificaciones del Estudiante:
                        <span id="spn_nombre_estudiante_titulo"><?= htmlspecialchars($estudianteObjetivoNombre ?? '—') ?></span>
                    </h6>
                </div>

                <ul class="nav nav-tabs mb-3" id="tabsProgramas"></ul>
                <div class="tab-content" id="contenidoProgramas"></div>

                <div class="text-muted text-center py-4 d-none" id="bloque_sin_programas">
                    Este estudiante no tiene ninguna matrícula registrada.
                </div>

            </div>

    <?php if ($es_coordinador): ?>
        </div>

    </div>
    <?php endif; ?>

</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($es_coordinador): ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<?php endif; ?>
<script>
    const ES_COORDINADOR = <?= $es_coordinador ? 'true' : 'false' ?>;
</script>
<script src="reportes_ctrl.js"></script>
<script>const MODULO_ACTUAL = '06_reportes';</script>
<script src="../00_files/ayuda_sidebar.js"></script>
</body>
</html>
