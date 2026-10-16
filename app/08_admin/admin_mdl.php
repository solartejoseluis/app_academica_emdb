<?php
session_start();
require_once '../00_connect/pdo.php';
require_once '../00_files/helpers.php';

// Usuario desactivado (usua_activo = 0) con sesión abierta: responde
// codigo 'sesion_invalida' (ver exigirUsuarioActivo() en helpers.php).
try {
    exigirUsuarioActivo(getConexion(), true);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de conexión con la base de datos']);
    exit;
}

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $sql = "SELECT u.usua_id, u.usua_email, u.usua_nombre, r.role_nombre, u.usua_activo,
                           u.usua_ultimo_acceso, u.fechacreacion
                    FROM usuarios u
                    JOIN roles r ON u.role_id = r.role_id
                    WHERE u.role_id IN (1, 2)
                    ORDER BY u.usua_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['ultimo_acceso_fmt'] = formatearUltimoAcceso($row['usua_ultimo_acceso'], $row['fechacreacion']);
            }
            unset($row);
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar usuarios']);
        }
        break;

    case 'listar_roles':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
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
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $email    = trim($_POST['usua_email'] ?? '');
        $nombre   = trim($_POST['usua_nombre'] ?? '');
        $password = $_POST['usua_password'] ?? '';
        $role_id  = (int)($_POST['role_id'] ?? 0);

        if ($email === '' || $nombre === '' || $password === '' || $role_id === 0) {
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
                "INSERT INTO usuarios (role_id, usua_email, usua_nombre, usua_passwordhash) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$role_id, $email, $nombre, $hash]);
            echo json_encode(['status' => 'ok', 'usua_id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al crear usuario']);
        }
        break;

    case 'editar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $usua_id    = (int)($_POST['usua_id'] ?? 0);
        $email      = trim($_POST['usua_email'] ?? '');
        $nombre     = trim($_POST['usua_nombre'] ?? '');
        $password   = $_POST['usua_password'] ?? '';
        $role_id    = (int)($_POST['role_id'] ?? 0);
        $usua_activo = (int)($_POST['usua_activo'] ?? 1);

        if ($usua_id === 0 || $email === '' || $nombre === '' || $role_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            break;
        }

        try {
            $pdo = getConexion();
            $pdo->beginTransaction();

            // Reglas sobre el estado resultante, con las filas bloqueadas para
            // que dos ediciones simultáneas no dejen el sistema sin
            // administradores activos.
            $stmtAct = $pdo->prepare("SELECT role_id FROM usuarios WHERE usua_id = ? FOR UPDATE");
            $stmtAct->execute([$usua_id]);
            $actual = $stmtAct->fetch();
            if (!$actual) {
                throw new Exception('Usuario no encontrado');
            }
            if ($usua_id === (int)$_SESSION['usua_id']) {
                if ($usua_activo !== 1) {
                    throw new Exception('No puede desactivar su propio usuario. Pida a otro administrador que lo haga.');
                }
                if ($role_id !== (int)$actual['role_id']) {
                    throw new Exception('No puede cambiar el rol de su propio usuario. Pida a otro administrador que lo haga.');
                }
            }
            $stmtAdm = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE role_id = 1 AND usua_activo = 1 AND usua_id <> ? FOR UPDATE"
            );
            $stmtAdm->execute([$usua_id]);
            $adminsResultantes = (int)$stmtAdm->fetchColumn() + (($role_id === 1 && $usua_activo === 1) ? 1 : 0);
            if ($adminsResultantes === 0) {
                throw new Exception('El sistema debe conservar al menos un administrador activo.');
            }

            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    "UPDATE usuarios
                     SET role_id = ?, usua_email = ?, usua_nombre = ?, usua_passwordhash = ?, usua_activo = ?
                     WHERE usua_id = ?"
                );
                $stmt->execute([$role_id, $email, $nombre, $hash, $usua_activo, $usua_id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE usuarios
                     SET role_id = ?, usua_email = ?, usua_nombre = ?, usua_activo = ?
                     WHERE usua_id = ?"
                );
                $stmt->execute([$role_id, $email, $nombre, $usua_activo, $usua_id]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar usuario']);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $usua_id = (int)($_GET['usua_id'] ?? 0);

        if ($usua_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "SELECT usua_id, usua_email, usua_nombre, role_id, usua_activo FROM usuarios WHERE usua_id = ?"
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

    case 'obtener_configuracion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT * FROM configuracion WHERE config_id = 1");
            $stmt->execute();
            $row = $stmt->fetch();
            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener configuración']);
        }
        break;

    case 'guardar_configuracion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $matr_numero_inicial = (int)($_POST['matr_numero_inicial'] ?? 0);
        $director_nombre     = trim($_POST['director_nombre'] ?? '');
        $secretario_nombre   = trim($_POST['secretario_nombre'] ?? '');

        if ($matr_numero_inicial <= 0 || $director_nombre === '' || $secretario_nombre === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos y el número inicial debe ser positivo']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "UPDATE configuracion
                 SET matr_numero_inicial = ?, director_nombre = ?, secretario_nombre = ?
                 WHERE config_id = 1"
            );
            $stmt->execute([$matr_numero_inicial, $director_nombre, $secretario_nombre]);
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar configuración']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
