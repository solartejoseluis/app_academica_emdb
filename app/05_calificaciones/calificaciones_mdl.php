<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── LISTAR GRUPOS DEL DOCENTE O TODOS (coordinador) ──────────────────────

    case 'listar_grupos':
        try {
            $pdo = getConexion();
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);

            if ($role_id === 3) {
                // Docente: solo sus grupos asignados
                $stmt = $pdo->prepare("
                    SELECT gm.grmo_id, gm.grmo_horario, gm.fechainicio, gm.fechafin,
                           m.modu_nombre, m.modu_sigla,
                           gs.grse_codigo, gs.grse_semestre,
                           c.coho_codigo, p.prog_sigla,
                           pe.peri_codigo,
                           (SELECT COUNT(*) FROM grmoestudiantes ge WHERE ge.grmo_id = gm.grmo_id) AS total_estudiantes
                    FROM gruposmodulos gm
                    INNER JOIN modulos m ON gm.modu_id = m.modu_id
                    INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                    INNER JOIN cohortes c ON gs.coho_id = c.coho_id
                    INNER JOIN programas p ON c.prog_id = p.prog_id
                    INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE d.usua_id = ? AND gm.grmo_activo = 1
                    ORDER BY gs.grse_semestre ASC, m.modu_nombre ASC
                ");
                $stmt->execute([$usua_id]);
            } else {
                // Coordinador/Admin: todos los grupos
                $stmt = $pdo->prepare("
                    SELECT gm.grmo_id, gm.grmo_horario, gm.fechainicio, gm.fechafin,
                           m.modu_nombre, m.modu_sigla,
                           gs.grse_codigo, gs.grse_semestre,
                           c.coho_codigo, p.prog_sigla,
                           pe.peri_codigo,
                           d.doce_nombres, d.doce_apellidos,
                           (SELECT COUNT(*) FROM grmoestudiantes ge WHERE ge.grmo_id = gm.grmo_id) AS total_estudiantes
                    FROM gruposmodulos gm
                    INNER JOIN modulos m ON gm.modu_id = m.modu_id
                    INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                    INNER JOIN cohortes c ON gs.coho_id = c.coho_id
                    INNER JOIN programas p ON c.prog_id = p.prog_id
                    INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_activo = 1
                    ORDER BY p.prog_sigla ASC, gs.grse_semestre ASC, m.modu_nombre ASC
                ");
                $stmt->execute();
            }
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── LISTAR ESTUDIANTES CON CALIFICACIONES DE UN GRUPO ────────────────────

    case 'listar_calificaciones':
        try {
            $pdo = getConexion();
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc,
                       c.cali_id,
                       c.cali_n1, c.cali_n2, c.cali_n3, c.cali_n4,
                       c.cali_sup_n1, c.cali_sup_n2, c.cali_sup_n4,
                       c.cali_definitiva, c.cali_observacion
                FROM grmoestudiantes ge
                INNER JOIN estudiantes e ON ge.estu_id = e.estu_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = e.estu_id
                WHERE ge.grmo_id = ?
                ORDER BY e.estu_apellidos ASC
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── GUARDAR UNA NOTA (autosave on blur) ───────────────────────────────────

    case 'guardar_nota':
        try {
            $pdo = getConexion();
            $grmo_id  = (int)($_POST['grmo_id'] ?? 0);
            $estu_id  = (int)($_POST['estu_id'] ?? 0);
            $campo    = $_POST['campo'] ?? '';
            $valor    = $_POST['valor'] ?? '';

            // Normalizar coma por punto (teclados numéricos)
            if ($campo !== 'cali_observacion') {
                $valor = str_replace(',', '.', $valor);
            }

            // Whitelist de campos permitidos
            $campos_permitidos = [
                'cali_n1', 'cali_n2', 'cali_n3', 'cali_n4',
                'cali_sup_n1', 'cali_sup_n2', 'cali_sup_n4',
                'cali_observacion'
            ];
            if (!in_array($campo, $campos_permitidos)) {
                echo json_encode(['status' => 'error', 'message' => 'Campo no permitido']);
                break;
            }

            // Validar rango de notas
            if ($campo !== 'cali_observacion') {
                $valor = $valor === '' ? null : (float)$valor;
                if ($valor !== null && ($valor < 0.0 || $valor > 5.0)) {
                    echo json_encode(['status' => 'error', 'message' => 'Nota fuera de rango (0.0 - 5.0)']);
                    break;
                }
            }

            // Verificar si ya existe registro
            $check = $pdo->prepare("
                SELECT cali_id, cali_n1, cali_n2, cali_n3, cali_n4,
                       cali_sup_n1, cali_sup_n2, cali_sup_n4
                FROM calificaciones WHERE grmo_id = ? AND estu_id = ?
            ");
            $check->execute([$grmo_id, $estu_id]);
            $existing = $check->fetch();

            if ($existing) {
                // UPDATE campo específico
                $stmt = $pdo->prepare("
                    UPDATE calificaciones SET {$campo} = ?
                    WHERE grmo_id = ? AND estu_id = ?
                ");
                $stmt->execute([$valor, $grmo_id, $estu_id]);
                $cali_id = $existing['cali_id'];
                // Actualizar fila existente con el nuevo valor
                $existing[$campo] = $valor;
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO calificaciones (grmo_id, estu_id, {$campo})
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$grmo_id, $estu_id, $valor]);
                $cali_id = $pdo->lastInsertId();
                // Leer fila recién insertada
                $r2 = $pdo->prepare("
                    SELECT cali_n1, cali_n2, cali_n3, cali_n4,
                           cali_sup_n1, cali_sup_n2, cali_sup_n4
                    FROM calificaciones WHERE cali_id = ?
                ");
                $r2->execute([$cali_id]);
                $existing = $r2->fetch();
            }

            // Calcular definitiva en PHP (triggers eliminados por limitación MySQL)
            $n1 = $existing['cali_n1'];
            $n2 = $existing['cali_n2'];
            $n3 = $existing['cali_n3'];
            $n4 = $existing['cali_n4'];
            $s1 = $existing['cali_sup_n1'];
            $s2 = $existing['cali_sup_n2'];
            $s4 = $existing['cali_sup_n4'];

            $definitiva = null;
            if ($n1 !== null && $n2 !== null && $n3 !== null && $n4 !== null) {
                $ef1 = ($n1 == 0.0 && $s1 !== null) ? $s1 : $n1;
                $ef2 = ($n2 == 0.0 && $s2 !== null) ? $s2 : $n2;
                $ef4 = ($n4 == 0.0 && $s4 !== null) ? $s4 : $n4;
                $definitiva = round($ef1 * 0.2 + $ef2 * 0.2 + $n3 * 0.2 + $ef4 * 0.4, 1);

                // Actualizar cali_definitiva en BD
                $upd = $pdo->prepare("
                    UPDATE calificaciones SET cali_definitiva = ?
                    WHERE cali_id = ?
                ");
                $upd->execute([$definitiva, $cali_id]);
            }

            echo json_encode([
                'status'          => 'ok',
                'cali_id'         => $cali_id,
                'cali_definitiva' => $definitiva,
                'cali_n1'         => $existing['cali_n1'],
                'cali_n2'         => $existing['cali_n2'],
                'cali_n3'         => $existing['cali_n3'],
                'cali_n4'         => $existing['cali_n4'],
                'cali_sup_n1'     => $existing['cali_sup_n1'],
                'cali_sup_n2'     => $existing['cali_sup_n2'],
                'cali_sup_n4'     => $existing['cali_sup_n4'],
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
