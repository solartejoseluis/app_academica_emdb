<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── RESUMEN DASHBOARD (roles 1 y 2) ──────────────────────────────────────

    case 'resumen_dashboard':
        try {
            if (!in_array((int)($_SESSION['role_id'] ?? 0), [1, 2])) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $pdo = getConexion();

            // Query 1 — conteos generales
            $stmt1 = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM estudiantes WHERE estu_activo = 1) AS total_estudiantes,
                    (SELECT COUNT(*) FROM docentes WHERE doce_activo = 1)    AS total_docentes,
                    (SELECT COUNT(*) FROM gruposmodulos WHERE grmo_activo = 1) AS total_grupos
            ");
            $stmt1->execute();
            $conteos = $stmt1->fetch();

            // Query 2 — estado de notas por grupo
            $stmt2 = $pdo->prepare("
                SELECT
                    gm.grmo_id,
                    m.modu_nombre,
                    m.modu_sigla,
                    gs.grse_codigo,
                    CONCAT(d.doce_nombres, ' ', d.doce_apellidos) AS docente,
                    COUNT(DISTINCT ge.estu_id) AS total_estudiantes,
                    COUNT(DISTINCT c.estu_id)  AS con_notas,
                    COUNT(DISTINCT CASE WHEN c.cali_definitiva IS NOT NULL THEN c.estu_id END) AS con_definitiva,
                    CASE
                        WHEN COUNT(DISTINCT ge.estu_id) = 0 THEN 'sin_estudiantes'
                        WHEN COUNT(DISTINCT CASE WHEN c.cali_definitiva IS NOT NULL THEN c.estu_id END) = COUNT(DISTINCT ge.estu_id) THEN 'completo'
                        WHEN COUNT(DISTINCT c.estu_id) > 0 THEN 'parcial'
                        ELSE 'pendiente'
                    END AS estado_notas
                FROM gruposmodulos gm
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN docentes d ON gm.doce_id = d.doce_id
                LEFT JOIN grmoestudiantes ge ON gm.grmo_id = ge.grmo_id
                LEFT JOIN calificaciones c ON c.grmo_id = gm.grmo_id AND c.estu_id = ge.estu_id
                WHERE gm.grmo_activo = 1
                GROUP BY gm.grmo_id
                ORDER BY gs.grse_codigo, m.modu_orden
            ");
            $stmt2->execute();
            $grupos = $stmt2->fetchAll();

            echo json_encode([
                'status' => 'ok',
                'data'   => [
                    'conteos' => $conteos,
                    'grupos'  => $grupos,
                ],
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
