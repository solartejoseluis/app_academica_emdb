<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usua_id'])) {
    header('Location: /app_academica_emdb/app/01_login/login_view.php');
    exit;
}

// Usuario desactivado (usua_activo = 0) con sesión abierta: redirige al
// login. Rutas con __DIR__ para que funcionen desde cualquier vista y desde
// los pdf_*.php; require_once evita la doble carga con los require de cada
// archivo (resuelven al mismo archivo real).
require_once __DIR__ . '/../00_connect/pdo.php';
require_once __DIR__ . '/../00_files/helpers.php';
try {
    exigirUsuarioActivo(getConexion(), false);
} catch (PDOException $e) {
    http_response_code(503);
    echo 'Servicio no disponible. Intente de nuevo en unos minutos.';
    exit;
}
