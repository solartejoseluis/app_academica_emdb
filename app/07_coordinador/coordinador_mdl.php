<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── RESUMEN DASHBOARD (roles 1 y 2) ──────────────────────────────────────

    case 'resumen_dashboard':
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
            // ultima_actualizacion: la más reciente entre calificaciones (todas
            // las filas del grupo módulo, también de estudiantes retirados) y
            // notasn3 (vía actividadesn3). Dos subconsultas escalares y no JOINs
            // 1:N, que multiplicarían las filas de la consulta principal.
            // GREATEST devuelve NULL si un argumento es NULL: cada fuente se pasa
            // a texto 'Y-m-d H:i:s' y su NULL a '' (menor que cualquier fecha);
            // NULLIF devuelve NULL solo cuando no existe ninguna de las dos.
            $stmt2 = $pdo->prepare("
                SELECT
                    gm.grmo_id,
                    m.modu_nombre,
                    m.modu_sigla,
                    gs.grse_codigo,
                    pe.peri_id,
                    pe.peri_codigo,
                    pe.peri_activo,
                    p.prog_id,
                    p.prog_sigla,
                    d.doce_id,
                    NULLIF(GREATEST(
                        COALESCE((SELECT DATE_FORMAT(MAX(c2.fechaactualizacion), '%Y-%m-%d %H:%i:%s')
                                  FROM calificaciones c2
                                  WHERE c2.grmo_id = gm.grmo_id), ''),
                        COALESCE((SELECT DATE_FORMAT(MAX(n.fechaactualizacion), '%Y-%m-%d %H:%i:%s')
                                  FROM notasn3 n
                                  INNER JOIN actividadesn3 a ON n.acn3_id = a.acn3_id
                                  WHERE a.grmo_id = gm.grmo_id), '')
                    ), '') AS ultima_actualizacion,
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
                JOIN periodos pe ON gs.peri_id = pe.peri_id
                JOIN programas p ON gs.prog_id = p.prog_id
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
