<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── GRUPOS DISPONIBLES PARA REPORTE (roles 1 y 2) ────────────────────────

    case 'grupos_para_reporte':
        try {
            if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2])) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT gm.grmo_id, m.modu_nombre, m.modu_sigla,
                       gs.grse_codigo, d.doce_nombres, d.doce_apellidos,
                       COUNT(ge.estu_id) AS total_estudiantes
                FROM gruposmodulos gm
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN docentes d ON gm.doce_id = d.doce_id
                LEFT JOIN grmoestudiantes ge ON gm.grmo_id = ge.grmo_id
                WHERE gm.grmo_activo = 1
                GROUP BY gm.grmo_id
                ORDER BY gs.grse_codigo, m.modu_orden
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── CALIFICACIONES DE TODOS LOS ESTUDIANTES DE UN GRUPO (roles 1 y 2) ────

    case 'reporte_grupo':
        try {
            if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2])) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT e.estu_nombres, e.estu_apellidos, e.estu_numerodoc,
                       c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
                       c.cali_n3, c.cali_n4, c.cali_sup_n4, c.cali_definitiva,
                       c.cali_observacion
                FROM grmoestudiantes ge
                JOIN estudiantes e ON ge.estu_id = e.estu_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
                WHERE ge.grmo_id = ?
                ORDER BY e.estu_apellidos, e.estu_nombres
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── MÓDULOS ACTIVOS DEL ESTUDIANTE (role 4) ──────────────────────────────

    case 'mis_modulos':
        try {
            if ((int)($_SESSION['role_id'] ?? 0) !== 4) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $pdo = getConexion();
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT DISTINCT m.modu_id, m.modu_nombre, m.modu_sigla,
                       gs.grse_codigo, gm.grmo_id
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN estudiantes est ON est.estu_id = ge.estu_id
                WHERE est.usua_id = ? AND gm.grmo_activo = 1
                ORDER BY gs.grse_codigo, m.modu_orden
            ");
            $stmt->execute([$usua_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── NOTAS DEL ESTUDIANTE EN UN GRUPO MÓDULO (role 4) ────────────────────

    case 'mis_notas':
        try {
            if ((int)($_SESSION['role_id'] ?? 0) !== 4) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT m.modu_nombre, m.modu_sigla,
                       gs.grse_codigo,
                       d.doce_nombres, d.doce_apellidos,
                       c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
                       c.cali_n3, c.cali_n4, c.cali_sup_n4, c.cali_definitiva,
                       c.cali_observacion
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN docentes d ON gm.doce_id = d.doce_id
                JOIN estudiantes est ON est.estu_id = ge.estu_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
                WHERE ge.grmo_id = ? AND est.usua_id = ?
            ");
            $stmt->execute([$grmo_id, $usua_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
