<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        try {
            $pdo = getConexion();

            $peri_id = (int)($_POST['peri_id'] ?? 0);
            if ($peri_id === 0) {
                $stmtPeri = $pdo->prepare("SELECT peri_id FROM periodos WHERE peri_activo = 1 LIMIT 1");
                $stmtPeri->execute();
                $peri_id = (int)($stmtPeri->fetchColumn() ?: 0);
            }

            $sql = "SELECT d.doce_id, d.doce_nombres, d.doce_apellidos, d.doce_sigla,
                           d.doce_activo,
                           u.usua_email, u.usua_activo,
                           (SELECT COUNT(*) FROM gruposmodulos gm
                            INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                            WHERE gm.doce_id = d.doce_id AND gs.peri_id = ? AND gm.grmo_activo = 1) AS total_grupos,
                           (SELECT COUNT(*) FROM gruposmodulos gm2
                            WHERE gm2.doce_id = d.doce_id) AS total_grupos_historico,
                           (SELECT peri_codigo FROM periodos WHERE peri_id = ?) AS peri_codigo
                    FROM docentes d
                    INNER JOIN usuarios u ON d.usua_id = u.usua_id
                    ORDER BY d.doce_apellidos ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$peri_id, $peri_id]);
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar docentes']);
        }
        break;

    case 'listar_periodos':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT peri_id, peri_codigo, peri_activo
                FROM periodos
                ORDER BY peri_anio DESC, peri_semestre DESC
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar períodos']);
        }
        break;

    case 'eliminar_docente':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $doce_id = (int)($_POST['doce_id'] ?? 0);

        if ($doce_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de docente inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT COUNT(*) FROM gruposmodulos WHERE doce_id = ?");
            $check->execute([$doce_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este docente tiene grupos asignados (actuales o históricos), no puede eliminarse — puedes desactivarlo en su lugar.']);
                break;
            }

            $pdo->beginTransaction();

            $stmtGetId = $pdo->prepare("SELECT usua_id FROM docentes WHERE doce_id = ?");
            $stmtGetId->execute([$doce_id]);
            $usua_id = $stmtGetId->fetchColumn();

            $stmtD = $pdo->prepare("DELETE FROM docentes WHERE doce_id = ?");
            $stmtD->execute([$doce_id]);

            if ($usua_id) {
                $stmtU = $pdo->prepare("DELETE FROM usuarios WHERE usua_id = ?");
                $stmtU->execute([$usua_id]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al docente']);
        }
        break;

    case 'toggle_estado_docente':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $doce_id      = (int)($_POST['doce_id'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($doce_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de docente inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $pdo->beginTransaction();

            $stmtD = $pdo->prepare("UPDATE docentes SET doce_activo = ? WHERE doce_id = ?");
            $stmtD->execute([$nuevo_estado, $doce_id]);

            $stmtU = $pdo->prepare(
                "UPDATE usuarios SET usua_activo = ? WHERE usua_id = (SELECT usua_id FROM docentes WHERE doce_id = ?)"
            );
            $stmtU->execute([$nuevo_estado, $doce_id]);

            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $doce_id        = trim($_POST['doce_id'] ?? '');
        $doce_nombres   = strtoupper(trim($_POST['doce_nombres'] ?? ''));
        $doce_apellidos = strtoupper(trim($_POST['doce_apellidos'] ?? ''));
        $doce_sigla     = strtoupper(trim($_POST['doce_sigla'] ?? ''));
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
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
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
