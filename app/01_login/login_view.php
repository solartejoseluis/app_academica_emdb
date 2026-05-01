<?php
session_start();
if (isset($_SESSION['usua_id'])) {
    $destinos = [
        1 => '../08_admin/admin_view.php',
        2 => '../07_coordinador/coordinador_view.php',
        3 => '../05_calificaciones/calificaciones_view.php',
        4 => '../06_reportes/reportes_view.php',
    ];
    $destino = $destinos[$_SESSION['role_id']] ?? 'login_view.php';
    header("Location: $destino");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .login-card { max-width: 420px; }
    </style>
</head>
<body>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="login-card w-100">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-1 fw-bold">EMDB Académica</h4>
                <p class="text-center text-muted small mb-4">Sistema de gestión académica</p>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger py-2 small">
                        Correo o contraseña incorrectos.
                    </div>
                <?php endif; ?>

                <form method="POST" action="login_mdl.php">
                    <div class="mb-3">
                        <label for="npt_email" class="form-label">Correo electrónico</label>
                        <input type="email"
                               class="form-control"
                               id="npt_email"
                               name="usua_email"
                               required
                               autofocus
                               autocomplete="email">
                    </div>
                    <div class="mb-4">
                        <label for="npt_password" class="form-label">Contraseña</label>
                        <input type="password"
                               class="form-control"
                               id="npt_password"
                               name="usua_password"
                               required
                               autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
