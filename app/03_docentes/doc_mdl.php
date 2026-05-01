<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        try {
            $pdo = getConexion();
            $sql = "SELECT d.doce_id, d.doce_nombres, d.doce_apellidos, d.doce_sigla,
                           u.usua_email, u.usua_activo
                    FROM docentes d
                    INNER JOIN usuarios u ON d.usua_id = u.usua_id
                    ORDER BY d.doce_apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar docentes']);
        }
        break;

    case 'guardar':
        $doce_id        = trim($_POST['doce_id'] ?? '');
        $doce_nombres   = trim($_POST['doce_nombres'] ?? '');
        $doce_apellidos = trim($_POST['doce_apellidos'] ?? '');
        $doce_sigla     = trim($_POST['doce_sigla'] ?? '');
        $usua_email     = trim($_POST['usua_email'] ?? '');

        if ($doce_nombres === '' || $doce_apellidos === '' || $doce_sigla === '' || $usua_email === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
            break;
        }

        try {
            $pdo = getConexion();

            if ($doce_id === '') {
                $usua_password = $_POST['usua_password'] ?? '';
                if ($usua_password === '') {
                    echo json_encode(['status' => 'error', 'message' => 'La contraseña inicial es requerida']);
                    break;
                }

                $check = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ?");
                $check->execute([$usua_email]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El correo ya está registrado']);
                    break;
                }

                $hash = password_hash($usua_password, PASSWORD_BCRYPT);

                $pdo->beginTransaction();
                $stmtU = $pdo->prepare(
                    "INSERT INTO usuarios (role_id, usua_email, usua_passwordhash) VALUES (3, ?, ?)"
                );
                $stmtU->execute([$usua_email, $hash]);
                $usua_id = $pdo->lastInsertId();

                $stmtD = $pdo->prepare(
                    "INSERT INTO docentes (usua_id, doce_nombres, doce_apellidos, doce_sigla)
                     VALUES (?, ?, ?, ?)"
                );
                $stmtD->execute([$usua_id, $doce_nombres, $doce_apellidos, $doce_sigla]);
                $pdo->commit();

                echo json_encode(['status' => 'ok', 'rows' => 1]);

            } else {
                $doce_id_int = (int)$doce_id;
                $usua_activo = (int)($_POST['usua_activo'] ?? 1);

                $stmtGetId = $pdo->prepare("SELECT usua_id FROM docentes WHERE doce_id = ?");
                $stmtGetId->execute([$doce_id_int]);
                $rowDoc = $stmtGetId->fetch();

                if (!$rowDoc) {
                    echo json_encode(['status' => 'error', 'message' => 'Docente no encontrado']);
                    break;
                }

                $pdo->beginTransaction();
                $stmtD = $pdo->prepare(
                    "UPDATE docentes
                     SET doce_nombres = ?, doce_apellidos = ?, doce_sigla = ?
                     WHERE doce_id = ?"
                );
                $stmtD->execute([$doce_nombres, $doce_apellidos, $doce_sigla, $doce_id_int]);

                $stmtU = $pdo->prepare(
                    "UPDATE usuarios SET usua_activo = ? WHERE usua_id = ?"
                );
                $stmtU->execute([$usua_activo, $rowDoc['usua_id']]);
                $pdo->commit();

                echo json_encode(['status' => 'ok', 'rows' => 1]);
            }

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar el docente']);
        }
        break;

    case 'obtener':
        $doce_id = (int)($_POST['doce_id'] ?? 0);

        if ($doce_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "SELECT d.doce_id, d.doce_nombres, d.doce_apellidos, d.doce_sigla,
                        u.usua_email, u.usua_activo
                 FROM docentes d
                 JOIN usuarios u ON d.usua_id = u.usua_id
                 WHERE d.doce_id = ?"
            );
            $stmt->execute([$doce_id]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Docente no encontrado']);
                break;
            }

            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener el docente']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
