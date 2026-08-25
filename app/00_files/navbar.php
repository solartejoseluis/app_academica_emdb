<?php
// Navbar compartido para roles 1 y 2 (Admin y Coordinador)
// Requiere que $_SESSION['role_id'] y $_SESSION['usua_email'] estén disponibles
require_once 'helpers.php';
$role_id = (int)($_SESSION['role_id'] ?? 0);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <a class="navbar-brand fw-bold" href="#">EMDB Académica</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
        <ul class="navbar-nav me-auto">
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/07_coordinador/coordinador_view.php">
                    📊 Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/02_estudiantes/est_view.php">
                    🎓 Estudiantes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/03_docentes/doc_view.php">
                    👨‍🏫 Docentes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/04_grupos/grupos_view.php">
                    📚 Grupos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/05_calificaciones/calificaciones_view.php">
                    📝 Calificaciones
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/06_reportes/reportes_view.php">
                    📈 Reportes
                </a>
            </li>
            <?php if ($role_id === 1): ?>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/08_admin/admin_view.php">
                    ⚙️ Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/app_academica_emdb/app/10_ayudas/ayud_view.php">
                    ❓ Ayudas
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <div class="d-flex align-items-center gap-3">
            <span class="text-light small">
                <?= htmlspecialchars($_SESSION['usua_nombre'] ?? '') ?> — <?= htmlspecialchars($_SESSION['usua_email']) ?> — Último acceso: <?= htmlspecialchars(formatearUltimoAcceso($_SESSION['usua_ultimo_acceso_anterior'] ?? null, $_SESSION['usua_fechacreacion'] ?? null)) ?>
            </span>
            <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasAyuda" aria-controls="offcanvasAyuda">
                ❓ Ayuda
            </button>
            <a href="/app_academica_emdb/app/01_login/logout.php" class="btn btn-outline-light btn-sm">
                Cerrar Sesión
            </a>
        </div>
    </div>
</nav>

<!-- ===== Offcanvas de Ayuda (compartido, contenido cargado vía ayuda_sidebar.js) ===== -->
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
