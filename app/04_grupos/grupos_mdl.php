<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── COHORTES ─────────────────────────────────────────────────────────────

    case 'listar_cohortes':
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
                SELECT c.coho_id, c.coho_codigo, c.fechainicio,
                       c.coho_activa,
                       p.prog_nombre, p.prog_sigla,
                       (SELECT COUNT(*) FROM matriculas mt WHERE mt.coho_id = c.coho_id) AS total_estudiantes,
                       (SELECT COUNT(*) FROM gruposemestres g WHERE g.coho_id = c.coho_id) AS total_grupos
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
            $coho_id    = trim($_POST['coho_id'] ?? '');
            $prog_id    = (int)($_POST['prog_id'] ?? 0);
            $coho_codigo = strtoupper(trim($_POST['coho_codigo'] ?? ''));
            $fechainicio = trim($_POST['fechainicio'] ?? '');
            $coho_activa  = (int)($_POST['coho_activa'] ?? 1);

            if (mb_strlen($coho_codigo) > 25) {
                echo json_encode(['status' => 'error', 'message' => 'El código de cohorte ("' . $coho_codigo . '") excede el máximo de 25 caracteres.']);
                break;
            }

            if ($coho_id === '') {
                // INSERT — verificar código duplicado
                $check = $pdo->prepare("SELECT coho_id FROM cohortes WHERE coho_codigo = ?");
                $check->execute([$coho_codigo]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El código de cohorte ya existe']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO cohortes (prog_id, coho_codigo, fechainicio, coho_activa)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$prog_id, $coho_codigo, $fechainicio, $coho_activa]);
            } else {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE cohortes
                    SET prog_id=?, coho_codigo=?, fechainicio=?, coho_activa=?
                    WHERE coho_id=?
                ");
                $stmt->execute([$prog_id, $coho_codigo, $fechainicio, $coho_activa, (int)$coho_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener_cohorte':
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

    case 'eliminar_cohorte':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $coho_id = (int)($_POST['coho_id'] ?? 0);

        if ($coho_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de cohorte inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE coho_id = ?");
            $check->execute([$coho_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Esta cohorte tiene estudiantes matriculados, no puede eliminarse.']);
                break;
            }

            $check = $pdo->prepare("SELECT COUNT(*) FROM gruposemestres WHERE coho_id = ?");
            $check->execute([$coho_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Esta cohorte tiene grupos semestre asociados, no puede eliminarse.']);
                break;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM cohortes WHERE coho_id = ?");
            $stmt->execute([$coho_id]);
            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados a la cohorte']);
        }
        break;

    case 'toggle_estado_cohorte':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $coho_id     = (int)($_POST['coho_id'] ?? 0);
        $coho_activa = (int)($_POST['coho_activa'] ?? 0);

        if ($coho_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de cohorte inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("UPDATE cohortes SET coho_activa = ? WHERE coho_id = ?");
            $stmt->execute([$coho_activa, $coho_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── GRUPOS SEMESTRE ───────────────────────────────────────────────────────

    case 'listar_grupos':
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
                SELECT gs.grse_id, gs.grse_codigo, gs.grse_semestre,
                       gs.fechainicio, gs.fechafin, gs.grse_activo, gs.coho_id,
                       c.coho_codigo,
                       p.prog_sigla, p.prog_nombre,
                       pe.peri_codigo,
                       (SELECT COUNT(*) FROM gruposmodulos gm WHERE gm.grse_id = gs.grse_id AND gm.grmo_activo = 1) AS total_modulos,
                       (SELECT COUNT(DISTINCT ge.estu_id)
                        FROM grmoestudiantes ge
                        JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                        WHERE gm.grse_id = gs.grse_id AND gm.grmo_activo = 1) AS total_estudiantes
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

    case 'listar_estudiantes_grupo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        $grse_id = (int)($_POST['grse_id'] ?? 0);
        if ($grse_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de grupo semestre inválido', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT DISTINCT e.estu_apellidos, e.estu_nombres
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN estudiantes e ON ge.estu_id = e.estu_id
                WHERE gm.grse_id = ? AND gm.grmo_activo = 1
                ORDER BY e.estu_apellidos, e.estu_nombres
            ");
            $stmt->execute([$grse_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    case 'guardar_grupo':
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
            $grse_id      = trim($_POST['grse_id'] ?? '');
            $coho_id      = (int)($_POST['coho_id'] ?? 0);
            $peri_id      = (int)($_POST['peri_id'] ?? 0);
            $prog_id      = (int)($_POST['prog_id'] ?? 0);
            $grse_semestre = (int)($_POST['grse_semestre'] ?? 1);
            $grse_codigo  = strtoupper(trim($_POST['grse_codigo'] ?? ''));
            $grse_jornada = trim($_POST['grse_jornada'] ?? 'Semana');
            $fechainicio  = trim($_POST['fechainicio'] ?? '');
            $fechafin     = trim($_POST['fechafin'] ?? '');
            $grse_activo  = (int)($_POST['grse_activo'] ?? 1);

            if (mb_strlen($grse_codigo) > 25) {
                echo json_encode(['status' => 'error', 'message' => 'El código generado ("' . $grse_codigo . '") excede el máximo de 25 caracteres. Use una sigla de programa más corta o revise la combinación período/semestre/jornada.']);
                break;
            }

            if ($grse_id === '') {
                $check = $pdo->prepare("SELECT grse_id FROM gruposemestres WHERE grse_codigo = ?");
                $check->execute([$grse_codigo]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El código de grupo ya existe']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO gruposemestres
                      (coho_id, prog_id, peri_id, grse_semestre, grse_codigo, grse_jornada, fechainicio, fechafin, grse_activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$coho_id, $prog_id, $peri_id, $grse_semestre, $grse_codigo, $grse_jornada, $fechainicio, $fechafin, $grse_activo]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE gruposemestres
                    SET coho_id=?, prog_id=?, peri_id=?, grse_semestre=?,
                        grse_codigo=?, grse_jornada=?, fechainicio=?, fechafin=?, grse_activo=?
                    WHERE grse_id=?
                ");
                $stmt->execute([$coho_id, $prog_id, $peri_id, $grse_semestre, $grse_codigo,
                                $grse_jornada, $fechainicio, $fechafin, $grse_activo, (int)$grse_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener_grupo':
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
            $grse_id = (int)($_POST['grse_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT gm.grmo_id, gm.grmo_horario, gm.fechainicio, gm.fechafin, gm.grmo_activo,
                       gm.modu_id, gm.doce_id,
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
                $check = $pdo->prepare("SELECT grmo_id FROM gruposmodulos WHERE grse_id=? AND modu_id=? AND grmo_id != ?");
                $check->execute([$grse_id, $modu_id, (int)$grmo_id]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este módulo ya está asignado a este grupo']);
                    break;
                }
                $stmt = $pdo->prepare("
                    UPDATE gruposmodulos
                    SET modu_id=?, doce_id=?, grmo_horario=?, fechainicio=?, fechafin=?
                    WHERE grmo_id=?
                ");
                $stmt->execute([$modu_id, $doce_id, $grmo_horario, $fechainicio, $fechafin, (int)$grmo_id]);
            }
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar_modulo_grupo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $grmo_id = (int)($_POST['grmo_id'] ?? 0);

        if ($grmo_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de grupo módulo inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT COUNT(*) FROM calificaciones WHERE grmo_id = ?");
            $check->execute([$grmo_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este módulo ya tiene calificaciones registradas, no puede eliminarse.']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM gruposmodulos WHERE grmo_id = ?");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al módulo']);
        }
        break;

    // ── ASIGNACIÓN DE ESTUDIANTES A MÓDULOS ───────────────────────────────────

    case 'listar_estudiantes_disponibles':
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
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $coho_id = (int)($_POST['coho_id'] ?? 0);
            // Estudiantes cuya matrícula (prog_id + peri_id) coincide con el
            // programa+período del grupo semestre de este grmo_id, que NO
            // están ya asignados a ese módulo.
            $stmt = $pdo->prepare("
                SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc
                FROM estudiantes e
                INNER JOIN matriculas mt ON mt.estu_id = e.estu_id AND mt.matr_estado = 'matriculado'
                INNER JOIN gruposmodulos gm ON gm.grmo_id = ?
                INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                WHERE mt.prog_id = gs.prog_id AND mt.peri_id = gs.peri_id
                  AND e.estu_id NOT IN (SELECT ge.estu_id FROM grmoestudiantes ge WHERE ge.grmo_id = ?)
                ORDER BY e.estu_apellidos ASC
            ");
            $stmt->execute([$grmo_id, $grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_estudiantes_modulo':
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
            $stmt = $pdo->prepare("SELECT prog_id, prog_nombre, prog_sigla FROM programas ORDER BY prog_id");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
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
                SELECT peri_id, peri_codigo, peri_anio, peri_semestre, fechainicio, fechafin, peri_activo,
                       (SELECT COUNT(*) FROM gruposemestres gs WHERE gs.peri_id = periodos.peri_id) AS total_gruposemestres,
                       (SELECT COUNT(*) FROM matriculas mt WHERE mt.peri_id = periodos.peri_id) AS total_matriculas
                FROM periodos
                ORDER BY peri_id DESC
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_periodo':
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
            $peri_id       = trim($_POST['peri_id'] ?? '');
            $peri_codigo   = trim($_POST['peri_codigo'] ?? '');
            $peri_anio     = trim($_POST['peri_anio'] ?? '');
            $peri_semestre = trim($_POST['peri_semestre'] ?? '');
            $fechainicio   = trim($_POST['fechainicio'] ?? '') ?: null;
            $fechafin      = trim($_POST['fechafin'] ?? '') ?: null;

            if ($peri_codigo === '' || $peri_anio === '' || $peri_semestre === '') {
                echo json_encode(['status' => 'error', 'message' => 'Código, año y semestre son requeridos']);
                break;
            }

            if (!preg_match('/^\d{4}-[12]$/', $peri_codigo)) {
                echo json_encode(['status' => 'error', 'message' => 'El código debe tener el formato AAAA-N (ej. 2026-2)']);
                break;
            }

            $peri_semestre_int = (int)$peri_semestre;
            if (!in_array($peri_semestre_int, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'El semestre debe ser 1 o 2']);
                break;
            }

            if ($peri_id === '') {
                $check = $pdo->prepare("SELECT peri_id FROM periodos WHERE peri_codigo = ?");
                $check->execute([$peri_codigo]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Ya existe un período con ese código']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO periodos (peri_codigo, peri_anio, peri_semestre, fechainicio, fechafin)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$peri_codigo, (int)$peri_anio, $peri_semestre_int, $fechainicio, $fechafin]);
            } else {
                $peri_id_int = (int)$peri_id;
                $check = $pdo->prepare("SELECT peri_id FROM periodos WHERE peri_codigo = ? AND peri_id != ?");
                $check->execute([$peri_codigo, $peri_id_int]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Ya existe un período con ese código']);
                    break;
                }
                $stmt = $pdo->prepare("
                    UPDATE periodos
                    SET peri_codigo=?, peri_anio=?, peri_semestre=?, fechainicio=?, fechafin=?
                    WHERE peri_id=?
                ");
                $stmt->execute([$peri_codigo, (int)$peri_anio, $peri_semestre_int, $fechainicio, $fechafin, $peri_id_int]);
            }
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'obtener_periodo':
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
            $stmt = $pdo->prepare("SELECT * FROM periodos WHERE peri_id = ?");
            $stmt->execute([(int)($_POST['peri_id'] ?? 0)]);
            $row = $stmt->fetch();
            echo json_encode($row
                ? ['status' => 'ok', 'data' => $row]
                : ['status' => 'error', 'message' => 'No encontrado']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'activar_periodo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $peri_id = (int)($_POST['peri_id'] ?? 0);

        if ($peri_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de período inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $pdo->beginTransaction();

            $pdo->exec("UPDATE periodos SET peri_activo = 0");

            $stmt = $pdo->prepare("UPDATE periodos SET peri_activo = 1 WHERE peri_id = ?");
            $stmt->execute([$peri_id]);

            $pdo->commit();
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar_periodo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $peri_id = (int)($_POST['peri_id'] ?? 0);

        if ($peri_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de período inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $stmtCheckActivo = $pdo->prepare("SELECT peri_activo FROM periodos WHERE peri_id = ?");
            $stmtCheckActivo->execute([$peri_id]);
            $peri_activo_actual = $stmtCheckActivo->fetchColumn();
            if ($peri_activo_actual === false) {
                echo json_encode(['status' => 'error', 'message' => 'Período no encontrado']);
                break;
            }
            if ((int)$peri_activo_actual === 1) {
                echo json_encode(['status' => 'error', 'message' => 'No se puede eliminar el período activo institucional. Active otro período primero.']);
                break;
            }

            $check = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM gruposemestres gs WHERE gs.peri_id = ?) +
                    (SELECT COUNT(*) FROM matriculas mt WHERE mt.peri_id = ?) AS total
            ");
            $check->execute([$peri_id, $peri_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este período tiene grupos semestre o matrículas asociadas, no puede eliminarse.']);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM periodos WHERE peri_id = ?");
            $stmt->execute([$peri_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al período']);
        }
        break;

    case 'listar_cohortes_por_programa':
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
            $prog_id = (int)($_POST['prog_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT coho_id, coho_codigo
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

            $stmtPeri = $pdo->prepare("SELECT peri_id FROM periodos WHERE peri_activo = 1 LIMIT 1");
            $stmtPeri->execute();
            $peri_id = (int)($stmtPeri->fetchColumn() ?: 0);

            $stmt = $pdo->prepare("
                SELECT d.doce_id, d.doce_nombres, d.doce_apellidos, d.doce_sigla,
                       (SELECT COUNT(*) FROM gruposmodulos gm
                        INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                        WHERE gm.doce_id = d.doce_id AND gs.peri_id = ? AND gm.grmo_activo = 1) AS total_grupos
                FROM docentes d
                INNER JOIN usuarios u ON d.usua_id = u.usua_id
                WHERE u.usua_activo = 1
                ORDER BY d.doce_apellidos ASC
            ");
            $stmt->execute([$peri_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── MÓDULOS ──────────────────────────────────────────────────────────────

    case 'listar_modulos':
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
                SELECT m.modu_id, m.modu_nombre, m.modu_sigla, m.modu_orden,
                       m.modu_activo, m.prog_id, p.prog_nombre,
                       COUNT(gm.grmo_id) AS total_grupos
                FROM modulos m
                JOIN programas p ON m.prog_id = p.prog_id
                LEFT JOIN gruposmodulos gm ON gm.modu_id = m.modu_id
                GROUP BY m.modu_id
                ORDER BY p.prog_nombre, m.modu_orden, m.modu_sigla
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_modulo':
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
            $modu_id     = trim($_POST['modu_id'] ?? '');
            $modu_nombre = trim($_POST['modu_nombre'] ?? '');
            $modu_sigla  = strtoupper(trim($_POST['modu_sigla'] ?? ''));
            $prog_id     = trim($_POST['prog_id'] ?? '');
            $modu_orden  = trim($_POST['modu_orden'] ?? '');

            if ($modu_nombre === '' || $modu_sigla === '' || $prog_id === '' || $modu_orden === '') {
                echo json_encode(['status' => 'error', 'message' => 'Nombre, sigla, programa y orden son requeridos']);
                break;
            }

            $prog_id_int    = (int)$prog_id;
            $modu_orden_int = (int)$modu_orden;

            if ($modu_id === '') {
                $check = $pdo->prepare("SELECT modu_id FROM modulos WHERE prog_id = ? AND modu_sigla = ?");
                $check->execute([$prog_id_int, $modu_sigla]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Ya existe un módulo con esa sigla en este programa']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO modulos (prog_id, modu_nombre, modu_sigla, modu_orden, modu_activo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$prog_id_int, $modu_nombre, $modu_sigla, $modu_orden_int]);
            } else {
                $modu_id_int = (int)$modu_id;
                $check = $pdo->prepare("SELECT modu_id FROM modulos WHERE prog_id = ? AND modu_sigla = ? AND modu_id != ?");
                $check->execute([$prog_id_int, $modu_sigla, $modu_id_int]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Ya existe un módulo con esa sigla en este programa']);
                    break;
                }
                $stmt = $pdo->prepare("
                    UPDATE modulos
                    SET prog_id = ?, modu_nombre = ?, modu_sigla = ?, modu_orden = ?
                    WHERE modu_id = ?
                ");
                $stmt->execute([$prog_id_int, $modu_nombre, $modu_sigla, $modu_orden_int, $modu_id_int]);
            }
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar_modulo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $modu_id = (int)($_POST['modu_id'] ?? 0);

        if ($modu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de módulo inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT COUNT(*) FROM gruposmodulos WHERE modu_id = ?");
            $check->execute([$modu_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este módulo ya fue asignado a uno o más grupos, no puede eliminarse — puedes desactivarlo en su lugar.']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM modulos WHERE modu_id = ?");
            $stmt->execute([$modu_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al módulo']);
        }
        break;

    case 'toggle_estado_modulo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $modu_id     = (int)($_POST['modu_id'] ?? 0);
        $modu_activo = (int)($_POST['modu_activo'] ?? 0);

        if ($modu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de módulo inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("UPDATE modulos SET modu_activo = ? WHERE modu_id = ?");
            $stmt->execute([$modu_activo, $modu_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── PROGRAMAS (CRUD) ─────────────────────────────────────────────────────
    // Nota: 'listar_programas' ya existe arriba (selector simple prog_id/
    // prog_nombre/prog_sigla, usado por cargarProgramasSelectores() para
    // poblar los <select> de Cohortes/Grupos/Módulos). El listado completo
    // para el DataTable de este CRUD usa el nombre 'listar_programas_crud'
    // para no colisionar con ese case existente — mismo criterio de
    // nombrado ya usado en el archivo entre listar_modulos y
    // listar_modulos_por_programa.

    case 'listar_programas_crud':
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
                SELECT p.prog_id, p.prog_nombre, p.prog_sigla, p.prog_duracion_semestres,
                       p.prog_resolucion, p.prog_fechaaprobacion, p.prog_fechavencimiento,
                       p.prog_descripcion, p.prog_activo,
                       (SELECT COUNT(*) FROM modulos m WHERE m.prog_id = p.prog_id) AS total_modulos,
                       (SELECT COUNT(*) FROM cohortes c WHERE c.prog_id = p.prog_id) AS total_cohortes,
                       (SELECT COUNT(*) FROM matriculas mt WHERE mt.prog_id = p.prog_id) AS total_matriculas
                FROM programas p
                ORDER BY p.prog_nombre
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    case 'guardar_programa':
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
            $prog_id                 = trim($_POST['prog_id'] ?? '');
            $prog_nombre              = trim($_POST['prog_nombre'] ?? '');
            $prog_sigla               = strtoupper(trim($_POST['prog_sigla'] ?? ''));
            $prog_duracion_semestres  = trim($_POST['prog_duracion_semestres'] ?? '');
            $prog_resolucion          = trim($_POST['prog_resolucion'] ?? '');
            $prog_fechaaprobacion     = trim($_POST['prog_fechaaprobacion'] ?? '');
            $prog_fechavencimiento    = trim($_POST['prog_fechavencimiento'] ?? '');
            $prog_descripcion         = trim($_POST['prog_descripcion'] ?? '');

            if ($prog_nombre === '' || $prog_sigla === '' || $prog_duracion_semestres === '') {
                echo json_encode(['status' => 'error', 'message' => 'Nombre, sigla y duración son requeridos']);
                break;
            }

            $prog_duracion_int   = (int)$prog_duracion_semestres;
            $prog_resolucion_db  = $prog_resolucion !== '' ? $prog_resolucion : null;
            $prog_fechaapr_db    = $prog_fechaaprobacion !== '' ? $prog_fechaaprobacion : null;
            $prog_fechaven_db    = $prog_fechavencimiento !== '' ? $prog_fechavencimiento : null;
            $prog_descripcion_db = $prog_descripcion !== '' ? $prog_descripcion : null;

            if ($prog_id === '') {
                $check = $pdo->prepare("SELECT prog_id FROM programas WHERE prog_sigla = ?");
                $check->execute([$prog_sigla]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Ya existe un programa con esa sigla']);
                    break;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO programas
                        (prog_nombre, prog_sigla, prog_duracion_semestres, prog_resolucion,
                         prog_fechaaprobacion, prog_fechavencimiento, prog_descripcion, prog_activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $prog_nombre, $prog_sigla, $prog_duracion_int, $prog_resolucion_db,
                    $prog_fechaapr_db, $prog_fechaven_db, $prog_descripcion_db
                ]);
            } else {
                $prog_id_int = (int)$prog_id;

                $stmtActual = $pdo->prepare("SELECT prog_sigla FROM programas WHERE prog_id = ?");
                $stmtActual->execute([$prog_id_int]);
                $sigla_actual = $stmtActual->fetchColumn();

                if ($sigla_actual !== false && $prog_sigla !== $sigla_actual) {
                    $checkDep = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM modulos m WHERE m.prog_id = ?) +
                            (SELECT COUNT(*) FROM cohortes c WHERE c.prog_id = ?) +
                            (SELECT COUNT(*) FROM matriculas mt WHERE mt.prog_id = ?) AS total
                    ");
                    $checkDep->execute([$prog_id_int, $prog_id_int, $prog_id_int]);
                    if ((int)$checkDep->fetchColumn() > 0) {
                        echo json_encode(['status' => 'error', 'message' => 'No se puede modificar la sigla: el programa ya tiene módulos, cohortes o matrículas asociadas.']);
                        break;
                    }

                    $checkSigla = $pdo->prepare("SELECT prog_id FROM programas WHERE prog_sigla = ? AND prog_id != ?");
                    $checkSigla->execute([$prog_sigla, $prog_id_int]);
                    if ($checkSigla->fetch()) {
                        echo json_encode(['status' => 'error', 'message' => 'Ya existe un programa con esa sigla']);
                        break;
                    }
                }

                $stmt = $pdo->prepare("
                    UPDATE programas
                    SET prog_nombre = ?, prog_sigla = ?, prog_duracion_semestres = ?,
                        prog_resolucion = ?, prog_fechaaprobacion = ?, prog_fechavencimiento = ?,
                        prog_descripcion = ?
                    WHERE prog_id = ?
                ");
                $stmt->execute([
                    $prog_nombre, $prog_sigla, $prog_duracion_int, $prog_resolucion_db,
                    $prog_fechaapr_db, $prog_fechaven_db, $prog_descripcion_db, $prog_id_int
                ]);
            }
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar_programa':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $prog_id = (int)($_POST['prog_id'] ?? 0);

        if ($prog_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de programa inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM modulos m WHERE m.prog_id = ?) +
                    (SELECT COUNT(*) FROM cohortes c WHERE c.prog_id = ?) +
                    (SELECT COUNT(*) FROM matriculas mt WHERE mt.prog_id = ?) AS total
            ");
            $check->execute([$prog_id, $prog_id, $prog_id]);
            if ((int)$check->fetchColumn() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este programa tiene módulos, cohortes o matrículas asociadas, no puede eliminarse — puedes desactivarlo en su lugar.']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM programas WHERE prog_id = ?");
            $stmt->execute([$prog_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al programa']);
        }
        break;

    case 'toggle_estado_programa':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $prog_id     = (int)($_POST['prog_id'] ?? 0);
        $prog_activo = (int)($_POST['prog_activo'] ?? 0);

        if ($prog_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de programa inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("UPDATE programas SET prog_activo = ? WHERE prog_id = ?");
            $stmt->execute([$prog_activo, $prog_id]);
            echo json_encode(['status' => 'ok']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
