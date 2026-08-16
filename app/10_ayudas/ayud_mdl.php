<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── LISTAR TODAS LAS AYUDAS (Admin) ───────────────────────────────────────

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
            $stmt = $pdo->prepare("SELECT * FROM ayudas ORDER BY ayud_seccion, ayud_orden");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── GUARDAR (crear o editar) ───────────────────────────────────────────────

    case 'guardar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if ($role_id !== 1) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }

        $ayud_id        = trim($_POST['ayud_id'] ?? '');
        $ayud_seccion   = trim($_POST['ayud_seccion'] ?? '');
        $ayud_titulo    = trim($_POST['ayud_titulo'] ?? '');
        $ayud_contenido = trim($_POST['ayud_contenido'] ?? '');
        $ayud_orden     = trim($_POST['ayud_orden'] ?? '0');
        $ayud_activo    = (int)($_POST['ayud_activo'] ?? 1);

        if ($ayud_seccion === '' || $ayud_titulo === '' || $ayud_contenido === '') {
            echo json_encode(['status' => 'error', 'message' => 'Sección, título y contenido son requeridos']);
            break;
        }

        $ayud_orden_int = (int)$ayud_orden;

        try {
            $pdo = getConexion();

            if ($ayud_id === '') {
                $stmt = $pdo->prepare("
                    INSERT INTO ayudas (ayud_seccion, ayud_titulo, ayud_contenido, ayud_orden, ayud_activo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$ayud_seccion, $ayud_titulo, $ayud_contenido, $ayud_orden_int, $ayud_activo]);
            } else {
                $ayud_id_int = (int)$ayud_id;
                $stmt = $pdo->prepare("
                    UPDATE ayudas
                    SET ayud_seccion = ?, ayud_titulo = ?, ayud_contenido = ?, ayud_orden = ?, ayud_activo = ?
                    WHERE ayud_id = ?
                ");
                $stmt->execute([$ayud_seccion, $ayud_titulo, $ayud_contenido, $ayud_orden_int, $ayud_activo, $ayud_id_int]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── OBTENER UNA AYUDA (precargar modal de edición) ─────────────────────────

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
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT * FROM ayudas WHERE ayud_id = ?");
            $stmt->execute([(int)($_POST['ayud_id'] ?? 0)]);
            $row = $stmt->fetch();
            echo json_encode($row
                ? ['status' => 'ok', 'data' => $row]
                : ['status' => 'error', 'message' => 'No encontrado']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── ELIMINAR (DELETE físico simple — sin tabla dependiente) ───────────────

    case 'eliminar':
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
            $stmt = $pdo->prepare("DELETE FROM ayudas WHERE ayud_id = ?");
            $stmt->execute([(int)($_POST['ayud_id'] ?? 0)]);
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── LISTAR POR SECCIÓN (lectura abierta a los 4 roles) ─────────────────────
    // Primer endpoint del proyecto sin chequeo de role_id: cualquier usuario
    // autenticado (Admin, Coordinador, Docente, Estudiante) puede consultar
    // la ayuda de la sección en la que se encuentra. Decisión deliberada,
    // no un guard olvidado — ver CLAUDE.md.

    case 'listar_por_modulo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT * FROM ayudas
                WHERE ayud_seccion = ? AND ayud_activo = 1
                ORDER BY ayud_orden
            ");
            $stmt->execute([trim($_POST['ayud_seccion'] ?? '')]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
