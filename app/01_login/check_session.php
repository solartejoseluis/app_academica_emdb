<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usua_id'])) {
    header('Location: /app_academica_emdb/app/01_login/login_view.php');
    exit;
}
