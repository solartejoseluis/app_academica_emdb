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
            $stmt = $pdo->prepare(
                "SELECT institucion_nombre, matr_numero_inicial, director_nombre, secretario_nombre
                 FROM configuracion
                 WHERE config_id = 1"
            );
            $stmt->execute();
            $row = $stmt->fetch();
            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'No se encontró la configuración']);
                break;
            }
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
        $institucion_nombre  = trim($_POST['institucion_nombre'] ?? '');
        $matr_numero_inicial = (int)($_POST['matr_numero_inicial'] ?? 0);
        $director_nombre     = trim($_POST['director_nombre'] ?? '');
        $secretario_nombre   = trim($_POST['secretario_nombre'] ?? '');

        $largoInstitucion = mb_strlen($institucion_nombre, 'UTF-8');
        if ($largoInstitucion < 3 || $largoInstitucion > 150) {
            echo json_encode(['status' => 'error', 'message' => 'El nombre de la institución debe tener entre 3 y 150 caracteres']);
            break;
        }
        if ($matr_numero_inicial <= 0 || $director_nombre === '' || $secretario_nombre === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos y el número inicial debe ser positivo']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "UPDATE configuracion
                 SET institucion_nombre = ?, matr_numero_inicial = ?, director_nombre = ?, secretario_nombre = ?
                 WHERE config_id = 1"
            );
            $stmt->execute([$institucion_nombre, $matr_numero_inicial, $director_nombre, $secretario_nombre]);
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar configuración']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
