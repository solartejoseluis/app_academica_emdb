<?php
session_start();
require_once '../00_connect/pdo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_view.php');
    exit;
}

$email    = trim($_POST['usua_email'] ?? '');
$password = $_POST['usua_password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login_view.php?error=1');
    exit;
}

try {
    $pdo = getConexion();

    $sql = "SELECT u.usua_id, u.usua_email, u.usua_passwordhash, u.role_id, r.role_nombre
            FROM usuarios u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.usua_email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['usua_passwordhash'])) {
        header('Location: login_view.php?error=1');
        exit;
    }

    $_SESSION['usua_id']    = (int)$usuario['usua_id'];
    $_SESSION['role_id']    = (int)$usuario['role_id'];
    $_SESSION['role_nombre']= $usuario['role_nombre'];
    $_SESSION['usua_email'] = $usuario['usua_email'];


    if ($usuario['role_id'] == 3) {
        $stmt2 = $pdo->prepare("SELECT doce_id, doce_sigla FROM docentes WHERE usua_id = ?");
        $stmt2->execute([$usuario['usua_id']]);
        $docente = $stmt2->fetch();
        if ($docente) {
            $_SESSION['doce_id']    = $docente['doce_id'];
            $_SESSION['doce_sigla'] = $docente['doce_sigla'];
        }
    }

    if ($usuario['role_id'] == 4) {
        $stmt3 = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE usua_id = ?");
        $stmt3->execute([$usuario['usua_id']]);
        $estudiante = $stmt3->fetch();
        if ($estudiante) {
            $_SESSION['estu_id'] = $estudiante['estu_id'];
        }
    }

    $destinos = [
        1 => '../08_admin/admin_view.php',
        2 => '../07_coordinador/coordinador_view.php',
        3 => '../05_calificaciones/calificaciones_view.php',
        4 => '../06_reportes/reportes_view.php',
    ];

    $destino = $destinos[$usuario['role_id']] ?? 'login_view.php';
    header("Location: $destino");
    exit;

} catch (PDOException $e) {
    header('Location: login_view.php?error=1');
    exit;
}
