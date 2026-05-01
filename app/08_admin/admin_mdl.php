<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        try {
            $pdo = getConexion();
            $sql = "SELECT u.usua_id, u.usua_email, r.role_nombre, u.usua_activo, u.fechacreacion
                    FROM usuarios u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE u.role_id IN (1, 2)
                    ORDER BY u.usua_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar usuarios']);
        }
        break;

    case 'listar_roles':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT role_id, role_nombre FROM roles WHERE role_id IN (1, 2) ORDER BY role_id");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar roles']);
        }
        break;

    case 'crear':
        $email    = trim($_POST['usua_email'] ?? '');
        $password = $_POST['usua_password'] ?? '';
        $role_id  = (int)($_POST['role_id'] ?? 0);

        if ($email === '' || $password === '' || $role_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'El email ya está registrado']);
                break;
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (role_id, usua_email, usua_passwordhash) VALUES (?, ?, ?)"
            );
            $stmt->execute([$role_id, $email, $hash]);
            echo json_encode(['status' => 'ok', 'usua_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al crear usuario']);
        }
        break;

    case 'editar':
        $usua_id    = (int)($_POST['usua_id'] ?? 0);
        $email      = trim($_POST['usua_email'] ?? '');
        $password   = $_POST['usua_password'] ?? '';
        $role_id    = (int)($_POST['role_id'] ?? 0);
        $usua_activo = (int)($_POST['usua_activo'] ?? 1);

        if ($usua_id === 0 || $email === '' || $role_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            break;
        }

        try {
            $pdo = getConexion();

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "UPDATE usuarios
                     SET role_id = ?, usua_email = ?, usua_passwordhash = ?, usua_activo = ?
                     WHERE usua_id = ?"
                );
                $stmt->execute([$role_id, $email, $hash, $usua_activo, $usua_id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE usuarios
                     SET role_id = ?, usua_email = ?, usua_activo = ?
                     WHERE usua_id = ?"
                );
                $stmt->execute([$role_id, $email, $usua_activo, $usua_id]);
            }

            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar usuario']);
        }
        break;

    case 'obtener':
        $usua_id = (int)($_GET['usua_id'] ?? 0);

        if ($usua_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "SELECT usua_id, usua_email, role_id, usua_activo FROM usuarios WHERE usua_id = ?"
            );
            $stmt->execute([$usua_id]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
                break;
            }

            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener usuario']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
