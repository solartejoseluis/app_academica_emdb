<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── COHORTES ─────────────────────────────────────────────────────────────

    case 'listar_cohortes':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT c.coho_id, c.coho_codigo, c.fechainicio,
                       c.coho_activa, c.coho_jornada,
                       p.prog_nombre, p.prog_sigla
                FROM cohortes c
                INNER JOIN programas p ON c.prog_id = p.prog_id
                ORDER BY c.coho_id DESC
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_cohorte':
        try {
            $pdo = getConexion();
            $coho_id    = trim($_POST['coho_id'] ?? '');
            $prog_id    = (int)($_POST['prog_id'] ?? 0);
            $coho_codigo = strtoupper(trim($_POST['coho_codigo'] ?? ''));
            $fechainicio = trim($_POST['fechainicio'] ?? '');
            $coho_jornada = trim($_POST['coho_jornada'] ?? 'Semana');
            $coho_activa  = (int)($_POST['coho_activa'] ?? 1);

            if ($coho_id === '') {
                // INSERT — verificar código duplicado
                $check = $pdo->prepare("SELECT coho_id FROM cohortes WHERE coho_codigo = ?");
                $check->execute([$coho_codigo]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El código de cohorte ya existe']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO cohortes (prog_id, coho_codigo, fechainicio, coho_jornada, coho_activa)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$prog_id, $coho_codigo, $fechainicio, $coho_jornada, $coho_activa]);
            } else {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE cohortes
                    SET prog_id=?, coho_codigo=?, fechainicio=?, coho_jornada=?, coho_activa=?
                    WHERE coho_id=?
                ");
                $stmt->execute([$prog_id, $coho_codigo, $fechainicio, $coho_jornada, $coho_activa, (int)$coho_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener_cohorte':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT c.*, p.prog_nombre
                FROM cohortes c
                INNER JOIN programas p ON c.prog_id = p.prog_id
                WHERE c.coho_id = ?
            ");
            $stmt->execute([(int)($_POST['coho_id'] ?? 0)]);
            $row = $stmt->fetch();
            echo json_encode($row
                ? ['status' => 'ok', 'data' => $row]
                : ['status' => 'error', 'message' => 'No encontrado']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── GRUPOS SEMESTRE ───────────────────────────────────────────────────────

    case 'listar_grupos':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT gs.grse_id, gs.grse_codigo, gs.grse_semestre,
                       gs.fechainicio, gs.fechafin, gs.grse_activo, gs.coho_id,
                       c.coho_codigo, c.coho_jornada,
                       p.prog_sigla, p.prog_nombre,
                       pe.peri_codigo,
                       (SELECT COUNT(*) FROM gruposmodulos gm WHERE gm.grse_id = gs.grse_id) AS total_modulos
                FROM gruposemestres gs
                INNER JOIN cohortes c ON gs.coho_id = c.coho_id
                INNER JOIN programas p ON c.prog_id = p.prog_id
                INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
                ORDER BY gs.grse_id DESC
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_grupo':
        try {
            $pdo = getConexion();
            $grse_id      = trim($_POST['grse_id'] ?? '');
            $coho_id      = (int)($_POST['coho_id'] ?? 0);
            $peri_id      = (int)($_POST['peri_id'] ?? 0);
            $prog_id      = (int)($_POST['prog_id'] ?? 0);
            $grse_semestre = (int)($_POST['grse_semestre'] ?? 1);
            $grse_codigo  = strtoupper(trim($_POST['grse_codigo'] ?? ''));
            $fechainicio  = trim($_POST['fechainicio'] ?? '');
            $fechafin     = trim($_POST['fechafin'] ?? '');
            $grse_activo  = (int)($_POST['grse_activo'] ?? 1);

            if ($grse_id === '') {
                $check = $pdo->prepare("SELECT grse_id FROM gruposemestres WHERE grse_codigo = ?");
                $check->execute([$grse_codigo]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El código de grupo ya existe']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO gruposemestres
                      (coho_id, prog_id, peri_id, grse_semestre, grse_codigo, fechainicio, fechafin, grse_activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$coho_id, $prog_id, $peri_id, $grse_semestre, $grse_codigo, $fechainicio, $fechafin, $grse_activo]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE gruposemestres
                    SET coho_id=?, prog_id=?, peri_id=?, grse_semestre=?,
                        grse_codigo=?, fechainicio=?, fechafin=?, grse_activo=?
                    WHERE grse_id=?
                ");
                $stmt->execute([$coho_id, $prog_id, $peri_id, $grse_semestre, $grse_codigo,
                                $fechainicio, $fechafin, $grse_activo, (int)$grse_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener_grupo':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT gs.*, c.coho_codigo, c.prog_id
                FROM gruposemestres gs
                INNER JOIN cohortes c ON gs.coho_id = c.coho_id
                WHERE gs.grse_id = ?
            ");
            $stmt->execute([(int)($_POST['grse_id'] ?? 0)]);
            $row = $stmt->fetch();
            echo json_encode($row
                ? ['status' => 'ok', 'data' => $row]
                : ['status' => 'error', 'message' => 'No encontrado']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── MÓDULOS DEL GRUPO ─────────────────────────────────────────────────────

    case 'listar_modulos_grupo':
        try {
            $pdo = getConexion();
            $grse_id = (int)($_POST['grse_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT gm.grmo_id, gm.grmo_horario, gm.fechainicio, gm.fechafin, gm.grmo_activo,
                       m.modu_nombre, m.modu_sigla,
                       d.doce_nombres, d.doce_apellidos, d.doce_sigla,
                       (SELECT COUNT(*) FROM grmoestudiantes ge WHERE ge.grmo_id = gm.grmo_id) AS total_estudiantes
                FROM gruposmodulos gm
                INNER JOIN modulos m ON gm.modu_id = m.modu_id
                INNER JOIN docentes d ON gm.doce_id = d.doce_id
                WHERE gm.grse_id = ?
                ORDER BY m.modu_orden ASC
            ");
            $stmt->execute([$grse_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_modulo_grupo':
        try {
            $pdo = getConexion();
            $grmo_id     = trim($_POST['grmo_id'] ?? '');
            $grse_id     = (int)($_POST['grse_id'] ?? 0);
            $modu_id     = (int)($_POST['modu_id'] ?? 0);
            $doce_id     = (int)($_POST['doce_id'] ?? 0);
            $grmo_horario = strtoupper(trim($_POST['grmo_horario'] ?? ''));
            $fechainicio  = trim($_POST['fechainicio_mod'] ?? '');
            $fechafin     = trim($_POST['fechafin_mod'] ?? '');

            if ($grmo_id === '') {
                $check = $pdo->prepare("SELECT grmo_id FROM gruposmodulos WHERE grse_id=? AND modu_id=?");
                $check->execute([$grse_id, $modu_id]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este módulo ya está asignado al grupo']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO gruposmodulos (grse_id, modu_id, doce_id, grmo_horario, fechainicio, fechafin)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$grse_id, $modu_id, $doce_id, $grmo_horario, $fechainicio, $fechafin]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE gruposmodulos
                    SET doce_id=?, grmo_horario=?, fechainicio=?, fechafin=?
                    WHERE grmo_id=?
                ");
                $stmt->execute([$doce_id, $grmo_horario, $fechainicio, $fechafin, (int)$grmo_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── ASIGNACIÓN DE ESTUDIANTES A MÓDULOS ───────────────────────────────────

    case 'listar_estudiantes_disponibles':
        try {
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $coho_id = (int)($_POST['coho_id'] ?? 0);
            // Estudiantes matriculados en esa cohorte que NO están ya en ese módulo
            $stmt = $pdo->prepare("
                SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc
                FROM estudiantes e
                WHERE e.coho_id = ?
                  AND e.estu_id NOT IN (
                      SELECT ge.estu_id FROM grmoestudiantes ge WHERE ge.grmo_id = ?
                  )
                ORDER BY e.estu_apellidos ASC
            ");
            $stmt->execute([$coho_id, $grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_estudiantes_modulo':
        try {
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc
                FROM grmoestudiantes ge
                INNER JOIN estudiantes e ON ge.estu_id = e.estu_id
                WHERE ge.grmo_id = ?
                ORDER BY e.estu_apellidos ASC
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'asignar_estudiantes':
        try {
            $pdo = getConexion();
            $grmo_id    = (int)($_POST['grmo_id'] ?? 0);
            $estu_ids   = $_POST['estu_ids'] ?? [];
            if (empty($estu_ids) || $grmo_id === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
                break;
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO grmoestudiantes (grmo_id, estu_id) VALUES (?, ?)
            ");
            foreach ($estu_ids as $estu_id) {
                $stmt->execute([$grmo_id, (int)$estu_id]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'ok', 'rows' => count($estu_ids)]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'retirar_estudiante':
        try {
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $estu_id = (int)($_POST['estu_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM grmoestudiantes WHERE grmo_id=? AND estu_id=?");
            $stmt->execute([$grmo_id, $estu_id]);
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── SELECTORES ────────────────────────────────────────────────────────────

    case 'listar_programas':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT prog_id, prog_nombre, prog_sigla FROM programas ORDER BY prog_id");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_periodos':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT peri_id, peri_codigo FROM periodos ORDER BY peri_id DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_cohortes_por_programa':
        try {
            $pdo = getConexion();
            $prog_id = (int)($_POST['prog_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT coho_id, coho_codigo, coho_jornada
                FROM cohortes
                WHERE prog_id = ? AND coho_activa = 1
                ORDER BY coho_id DESC
            ");
            $stmt->execute([$prog_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_modulos_por_programa':
        try {
            $pdo = getConexion();
            $prog_id = (int)($_POST['prog_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT m.modu_id, m.modu_nombre, m.modu_sigla,
                       COALESCE(pm.prmo_semestre_sugerido, m.modu_orden) AS semestre_sugerido
                FROM modulos m
                LEFT JOIN programa_modulos pm ON m.modu_id = pm.modu_id AND pm.prog_id = ?
                WHERE m.prog_id = ? AND m.modu_activo = 1
                ORDER BY semestre_sugerido ASC, m.modu_nombre ASC
            ");
            $stmt->execute([$prog_id, $prog_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_docentes':
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT d.doce_id, d.doce_nombres, d.doce_apellidos, d.doce_sigla
                FROM docentes d
                INNER JOIN usuarios u ON d.usua_id = u.usua_id
                WHERE u.usua_activo = 1
                ORDER BY d.doce_apellidos ASC
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
