<?php
session_start();
require_once '../00_connect/pdo.php';

// Fórmula oficial de Nota Final/Definitiva — extraída de 'guardar_nota'
// (Fase 2.14.D) para que 'guardar_nota_n3' la reutilice sin duplicarla.
// A diferencia del bloque inline original, aquí SIEMPRE se persiste
// (incluyendo NULL si N1-N4 no están completas), evitando que un valor
// quede desactualizado si una nota se limpia después de haber estado completa.
function recalcularNotaFinalYDefinitiva(PDO $pdo, int $grmo_id, int $estu_id): void {
    $check = $pdo->prepare("
        SELECT cali_id, cali_n1, cali_n2, cali_n3, cali_n4,
               cali_sup_n1, cali_sup_n2, cali_sup_n4,
               cali_habilitacion
        FROM calificaciones WHERE grmo_id = ? AND estu_id = ?
    ");
    $check->execute([$grmo_id, $estu_id]);
    $fila = $check->fetch();
    if (!$fila) {
        return;
    }

    $n1 = $fila['cali_n1'];
    $n2 = $fila['cali_n2'];
    $n3 = $fila['cali_n3'];
    $n4 = $fila['cali_n4'];
    $s1 = $fila['cali_sup_n1'];
    $s2 = $fila['cali_sup_n2'];
    $s4 = $fila['cali_sup_n4'];
    $habilitacion = $fila['cali_habilitacion'];

    $notaFinal = null;
    $definitivaOficial = null;
    if ($n1 !== null && $n2 !== null && $n3 !== null && $n4 !== null) {
        $ef1 = ($n1 == 0.0 && $s1 !== null) ? $s1 : $n1;
        $ef2 = ($n2 == 0.0 && $s2 !== null) ? $s2 : $n2;
        $ef4 = ($n4 == 0.0 && $s4 !== null) ? $s4 : $n4;
        $notaFinal = round($ef1 * 0.2 + $ef2 * 0.2 + $n3 * 0.2 + $ef4 * 0.4, 1);

        // Definitiva oficial: nota final si aprueba, habilitación si no
        // aprueba y hay habilitación registrada, o NULL en otro caso.
        if ($notaFinal >= 3.0) {
            $definitivaOficial = $notaFinal;
        } elseif ($habilitacion !== null) {
            $definitivaOficial = round((float)$habilitacion, 1);
        }
    }

    $upd = $pdo->prepare("
        UPDATE calificaciones SET cali_nota_final = ?, cali_definitiva = ?
        WHERE cali_id = ?
    ");
    $upd->execute([$notaFinal, $definitivaOficial, $fila['cali_id']]);
}

// N3 derivado del promedio de notasn3 — Fase 2.14.D. Retorna null si no hay
// actividades configuradas o si al estudiante le falta alguna nota (N3
// incompleto no se promedia parcialmente).
function recalcularN3(PDO $pdo, int $grmo_id, int $estu_id): ?float {
    $totalAct = $pdo->prepare("SELECT COUNT(*) AS total FROM actividadesn3 WHERE grmo_id = ?");
    $totalAct->execute([$grmo_id]);
    $totalActividades = (int)$totalAct->fetch()['total'];
    if ($totalActividades === 0) {
        return null;
    }

    $totalNotas = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM notasn3 n
        INNER JOIN actividadesn3 a ON n.acn3_id = a.acn3_id
        WHERE a.grmo_id = ? AND n.estu_id = ? AND n.non3_valor IS NOT NULL
    ");
    $totalNotas->execute([$grmo_id, $estu_id]);
    $totalCompletas = (int)$totalNotas->fetch()['total'];

    if ($totalCompletas !== $totalActividades) {
        return null;
    }

    $promedio = $pdo->prepare("
        SELECT AVG(n.non3_valor) AS promedio
        FROM notasn3 n
        INNER JOIN actividadesn3 a ON n.acn3_id = a.acn3_id
        WHERE a.grmo_id = ? AND n.estu_id = ?
    ");
    $promedio->execute([$grmo_id, $estu_id]);
    $valor = $promedio->fetch()['promedio'];

    return $valor !== null ? round((float)$valor, 1) : null;
}

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    // ── LISTAR GRUPOS DEL DOCENTE O TODOS (coordinador) ──────────────────────

    case 'listar_grupos':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
                break;
            }

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
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
                break;
            } else {
                // Coordinador/Admin: todos los grupos, con filtros opcionales (doce_id, prog_id, peri_id)
                $doce_id_filtro = trim($_POST['doce_id'] ?? '');
                $prog_id_filtro = trim($_POST['prog_id'] ?? '');
                $peri_id_filtro = trim($_POST['peri_id'] ?? '');

                $where  = "WHERE gm.grmo_activo = 1";
                $params = [];

                if ($doce_id_filtro !== '') {
                    $where .= " AND d.doce_id = ?";
                    $params[] = (int)$doce_id_filtro;
                }
                if ($prog_id_filtro !== '') {
                    $where .= " AND p.prog_id = ?";
                    $params[] = (int)$prog_id_filtro;
                }
                if ($peri_id_filtro !== '') {
                    $where .= " AND gs.peri_id = ?";
                    $params[] = (int)$peri_id_filtro;
                }

                $stmt = $pdo->prepare("
                    SELECT gm.grmo_id, gm.grmo_horario, gm.fechainicio, gm.fechafin,
                           m.modu_nombre, m.modu_sigla,
                           gs.grse_codigo, gs.grse_semestre, gs.peri_id,
                           c.coho_codigo, p.prog_sigla,
                           pe.peri_codigo,
                           d.doce_id, d.doce_nombres, d.doce_apellidos,
                           (SELECT COUNT(*) FROM grmoestudiantes ge WHERE ge.grmo_id = gm.grmo_id) AS total_estudiantes
                    FROM gruposmodulos gm
                    INNER JOIN modulos m ON gm.modu_id = m.modu_id
                    INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                    INNER JOIN cohortes c ON gs.coho_id = c.coho_id
                    INNER JOIN programas p ON c.prog_id = p.prog_id
                    INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    {$where}
                    ORDER BY p.prog_sigla ASC, gs.grse_semestre ASC, m.modu_nombre ASC
                ");
                $stmt->execute($params);
            }
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── CATÁLOGOS PARA FILTROS DE listar_grupos (Coordinador/Admin) ──────────

    case 'listar_programas_filtro':
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
            $stmt = $pdo->prepare("SELECT prog_id, prog_nombre, prog_sigla FROM programas ORDER BY prog_nombre");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_periodos_filtro':
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
            $stmt = $pdo->prepare("SELECT peri_id, peri_codigo FROM periodos ORDER BY peri_anio DESC, peri_semestre DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'listar_docentes_filtro':
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
                SELECT d.doce_id, d.doce_nombres, d.doce_apellidos
                FROM docentes d
                INNER JOIN usuarios u ON d.usua_id = u.usua_id
                WHERE u.usua_activo = 1
                ORDER BY d.doce_apellidos
            ");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── LISTAR ESTUDIANTES CON CALIFICACIONES DE UN GRUPO ────────────────────

    case 'listar_calificaciones':
        try {
            // Validar sesión activa (mismo criterio que check_session.php)
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);

            if ($role_id === 3) {
                // Docente: solo puede consultar grupos asignados a él
                // (mismo criterio de pertenencia que listar_grupos/guardar_nota: docentes.usua_id)
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                // Coordinador/Admin: sin restricción de ownership. Cualquier otro rol (ej. estudiante) → rechazado.
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc,
                       c.cali_id,
                       c.cali_n1, c.cali_n2, c.cali_n3, c.cali_n4,
                       c.cali_sup_n1, c.cali_sup_n2, c.cali_sup_n4,
                       c.cali_habilitacion, c.cali_nota_final,
                       c.cali_definitiva, c.cali_observacion
                FROM grmoestudiantes ge
                INNER JOIN estudiantes e ON ge.estu_id = e.estu_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = e.estu_id
                WHERE ge.grmo_id = ?
                ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC
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
            // Validar sesión activa (mismo criterio que check_session.php)
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id  = (int)($_SESSION['role_id'] ?? 0);
            $usua_id  = (int)($_SESSION['usua_id'] ?? 0);
            $grmo_id  = (int)($_POST['grmo_id'] ?? 0);
            $estu_id  = (int)($_POST['estu_id'] ?? 0);
            $campo    = $_POST['campo'] ?? '';
            $valor    = $_POST['valor'] ?? '';

            if ($role_id === 3) {
                // Docente: solo puede guardar notas en grupos asignados a él
                // (mismo criterio de pertenencia que listar_grupos: docentes.usua_id)
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                // Coordinador/Admin: sin restricción de ownership. Cualquier otro rol (ej. estudiante) → rechazado.
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            // Normalizar coma por punto (teclados numéricos)
            if ($campo !== 'cali_observacion') {
                $valor = str_replace(',', '.', $valor);
            }

            // Whitelist de campos permitidos
            $campos_permitidos = [
                'cali_n1', 'cali_n2', 'cali_n3', 'cali_n4',
                'cali_sup_n1', 'cali_sup_n2', 'cali_sup_n4',
                'cali_habilitacion',
                'cali_observacion'
            ];
            if (!in_array($campo, $campos_permitidos)) {
                echo json_encode(['status' => 'error', 'message' => 'Campo no permitido']);
                break;
            }

            // Validar rango de notas
            if ($campo !== 'cali_observacion') {
                if ($valor === '') {
                    $valor = null;
                } elseif (!is_numeric($valor)) {
                    echo json_encode(['status' => 'error', 'message' => 'Valor no numérico']);
                    break;
                } else {
                    $valor = (float)$valor;
                }
                if ($valor !== null && ($valor < 0.0 || $valor > 5.0)) {
                    echo json_encode(['status' => 'error', 'message' => 'Nota fuera de rango (0.0 - 5.0)']);
                    break;
                }
            }

            // Verificar si ya existe registro
            $check = $pdo->prepare("
                SELECT cali_id, cali_n1, cali_n2, cali_n3, cali_n4,
                       cali_sup_n1, cali_sup_n2, cali_sup_n4,
                       cali_habilitacion, cali_nota_final
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
                           cali_sup_n1, cali_sup_n2, cali_sup_n4,
                           cali_habilitacion, cali_nota_final
                    FROM calificaciones WHERE cali_id = ?
                ");
                $r2->execute([$cali_id]);
                $existing = $r2->fetch();
            }

            // Calcular y persistir Nota Final/Definitiva (función reutilizada
            // también por 'guardar_nota_n3' — ver recalcularNotaFinalYDefinitiva())
            recalcularNotaFinalYDefinitiva($pdo, $grmo_id, $estu_id);

            $finalStmt = $pdo->prepare("SELECT cali_nota_final, cali_definitiva FROM calificaciones WHERE cali_id = ?");
            $finalStmt->execute([$cali_id]);
            $filaFinal = $finalStmt->fetch();
            $notaFinal = $filaFinal['cali_nota_final'];
            $definitivaOficial = $filaFinal['cali_definitiva'];

            echo json_encode([
                'status'            => 'ok',
                'cali_id'           => $cali_id,
                'cali_nota_final'   => $notaFinal,
                'cali_definitiva'   => $definitivaOficial,
                'cali_n1'           => $existing['cali_n1'],
                'cali_n2'           => $existing['cali_n2'],
                'cali_n3'           => $existing['cali_n3'],
                'cali_n4'           => $existing['cali_n4'],
                'cali_sup_n1'       => $existing['cali_sup_n1'],
                'cali_sup_n2'       => $existing['cali_sup_n2'],
                'cali_sup_n4'       => $existing['cali_sup_n4'],
                'cali_habilitacion' => $existing['cali_habilitacion'],
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── ACTIVIDADES N3 CONFIGURABLES (Fase 2.14.B) ────────────────────────────

    case 'listar_actividades_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);

            if ($role_id === 3) {
                // Docente: solo puede consultar actividades de grupos asignados a él
                // (mismo criterio de pertenencia que listar_calificaciones/guardar_nota: docentes.usua_id)
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                // Coordinador/Admin: sin restricción de ownership. Cualquier otro rol (ej. estudiante) → rechazado.
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT acn3_id, acn3_nombre, acn3_comentario, acn3_orden
                FROM actividadesn3
                WHERE grmo_id = ?
                ORDER BY acn3_orden ASC, acn3_id ASC
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_actividad_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id         = (int)($_SESSION['role_id'] ?? 0);
            $usua_id         = (int)($_SESSION['usua_id'] ?? 0);
            $grmo_id         = (int)($_POST['grmo_id'] ?? 0);
            $acn3_nombre     = trim($_POST['acn3_nombre'] ?? '');
            $acn3_comentario = trim($_POST['acn3_comentario'] ?? '');

            if ($role_id === 3) {
                // Docente: solo puede crear actividades en grupos asignados a él
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            if ($acn3_nombre === '') {
                echo json_encode(['status' => 'error', 'message' => 'El nombre de la actividad es obligatorio']);
                break;
            }
            if ($acn3_comentario === '') {
                $acn3_comentario = null;
            }

            // Límite de actividades + siguiente orden en una sola consulta.
            // acn3_orden = MAX(acn3_orden)+1 (no COUNT(*)): tras eliminar una
            // actividad intermedia, COUNT(*) reasignaría un acn3_orden ya usado
            // por otra fila existente — MAX+1 nunca colisiona.
            $info = $pdo->prepare("
                SELECT COUNT(*) AS total, COALESCE(MAX(acn3_orden), -1) AS max_orden
                FROM actividadesn3 WHERE grmo_id = ?
            ");
            $info->execute([$grmo_id]);
            $fila = $info->fetch();
            if ((int)$fila['total'] >= 15) {
                echo json_encode(['status' => 'error', 'message' => 'Máximo 15 actividades por grupo módulo.']);
                break;
            }
            $acn3_orden = (int)$fila['max_orden'] + 1;

            $stmt = $pdo->prepare("
                INSERT INTO actividadesn3 (grmo_id, acn3_nombre, acn3_comentario, acn3_orden)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$grmo_id, $acn3_nombre, $acn3_comentario, $acn3_orden]);

            echo json_encode([
                'status'          => 'ok',
                'acn3_id'         => $pdo->lastInsertId(),
                'grmo_id'         => $grmo_id,
                'acn3_nombre'     => $acn3_nombre,
                'acn3_comentario' => $acn3_comentario,
                'acn3_orden'      => $acn3_orden,
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'editar_actividad_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id         = (int)($_SESSION['role_id'] ?? 0);
            $usua_id         = (int)($_SESSION['usua_id'] ?? 0);
            $acn3_id         = (int)($_POST['acn3_id'] ?? 0);
            $acn3_nombre     = trim($_POST['acn3_nombre'] ?? '');
            $acn3_comentario = trim($_POST['acn3_comentario'] ?? '');

            // Resolver grmo_id de la actividad ANTES de verificar ownership
            // (no se puede validar pertenencia sin saber a qué grupo pertenece)
            $act = $pdo->prepare("SELECT grmo_id FROM actividadesn3 WHERE acn3_id = ?");
            $act->execute([$acn3_id]);
            $actividad = $act->fetch();
            if (!$actividad) {
                echo json_encode(['status' => 'error', 'message' => 'Actividad no encontrada']);
                break;
            }
            $grmo_id = (int)$actividad['grmo_id'];

            if ($role_id === 3) {
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            if ($acn3_nombre === '') {
                echo json_encode(['status' => 'error', 'message' => 'El nombre de la actividad es obligatorio']);
                break;
            }
            if ($acn3_comentario === '') {
                $acn3_comentario = null;
            }

            $stmt = $pdo->prepare("
                UPDATE actividadesn3 SET acn3_nombre = ?, acn3_comentario = ?
                WHERE acn3_id = ?
            ");
            $stmt->execute([$acn3_nombre, $acn3_comentario, $acn3_id]);

            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar_actividad_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $acn3_id = (int)($_POST['acn3_id'] ?? 0);

            // Resolver grmo_id de la actividad ANTES de verificar ownership
            $act = $pdo->prepare("SELECT grmo_id FROM actividadesn3 WHERE acn3_id = ?");
            $act->execute([$acn3_id]);
            $actividad = $act->fetch();
            if (!$actividad) {
                echo json_encode(['status' => 'error', 'message' => 'Actividad no encontrada']);
                break;
            }
            $grmo_id = (int)$actividad['grmo_id'];

            if ($role_id === 3) {
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            // Verificación 1: no eliminar la última actividad del grupo módulo
            $total = $pdo->prepare("SELECT COUNT(*) AS total FROM actividadesn3 WHERE grmo_id = ?");
            $total->execute([$grmo_id]);
            if ((int)$total->fetch()['total'] <= 1) {
                echo json_encode(['status' => 'error', 'message' => 'No se puede eliminar: debe existir al menos 1 actividad.']);
                break;
            }

            // Verificación 2: no eliminar si ya tiene notas registradas
            $notas = $pdo->prepare("SELECT COUNT(*) AS total FROM notasn3 WHERE acn3_id = ? AND non3_valor IS NOT NULL");
            $notas->execute([$acn3_id]);
            if ((int)$notas->fetch()['total'] > 0) {
                echo json_encode(['status' => 'error', 'message' => 'No se puede eliminar: ya tiene notas registradas.']);
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM actividadesn3 WHERE acn3_id = ?");
            $stmt->execute([$acn3_id]);

            echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── NOTAS POR ACTIVIDAD N3 (Fase 2.14.C) ──────────────────────────────────

    case 'listar_notas_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo = getConexion();
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            $usua_id = (int)($_SESSION['usua_id'] ?? 0);
            $grmo_id = (int)($_POST['grmo_id'] ?? 0);

            if ($role_id === 3) {
                // Docente: solo puede consultar notas de grupos asignados a él
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            // Array plano estudiante+actividad — el frontend arma la matriz
            // estudiante×actividad en la Fase 2.14.E, este case no pivotea nada.
            $stmt = $pdo->prepare("
                SELECT n.acn3_id, n.estu_id, n.non3_valor
                FROM notasn3 n
                INNER JOIN actividadesn3 a ON n.acn3_id = a.acn3_id
                WHERE a.grmo_id = ?
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'guardar_nota_n3':
        try {
            if (!isset($_SESSION['usua_id'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
                break;
            }

            $pdo        = getConexion();
            $role_id    = (int)($_SESSION['role_id'] ?? 0);
            $usua_id    = (int)($_SESSION['usua_id'] ?? 0);
            $acn3_id    = (int)($_POST['acn3_id'] ?? 0);
            $estu_id    = (int)($_POST['estu_id'] ?? 0);
            $non3_valor = trim($_POST['non3_valor'] ?? '');

            // Resolver grmo_id de la actividad ANTES de verificar ownership
            $act = $pdo->prepare("SELECT grmo_id FROM actividadesn3 WHERE acn3_id = ?");
            $act->execute([$acn3_id]);
            $actividad = $act->fetch();
            if (!$actividad) {
                echo json_encode(['status' => 'error', 'message' => 'Actividad no encontrada']);
                break;
            }
            $grmo_id = (int)$actividad['grmo_id'];

            if ($role_id === 3) {
                $own = $pdo->prepare("
                    SELECT gm.grmo_id
                    FROM gruposmodulos gm
                    INNER JOIN docentes d ON gm.doce_id = d.doce_id
                    WHERE gm.grmo_id = ? AND d.usua_id = ?
                ");
                $own->execute([$grmo_id, $usua_id]);
                if (!$own->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'No autorizado para este grupo']);
                    break;
                }
            } elseif (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }

            // El estudiante debe pertenecer al grupo módulo de la actividad
            $miembro = $pdo->prepare("SELECT COUNT(*) AS total FROM grmoestudiantes WHERE grmo_id = ? AND estu_id = ?");
            $miembro->execute([$grmo_id, $estu_id]);
            if ((int)$miembro->fetch()['total'] === 0) {
                echo json_encode(['status' => 'error', 'message' => 'El estudiante no pertenece a este grupo módulo.']);
                break;
            }

            // Validar valor — SIN lógica de supletorio (N3 nunca tiene
            // supletorio, no existe cali_sup_n3 ni equivalente aquí)
            if ($non3_valor === '') {
                $non3_valor = null;
            } elseif (!is_numeric($non3_valor)) {
                echo json_encode(['status' => 'error', 'message' => 'Valor no numérico']);
                break;
            } else {
                $non3_valor = round((float)$non3_valor, 1);
                if ($non3_valor < 0.0 || $non3_valor > 5.0) {
                    echo json_encode(['status' => 'error', 'message' => 'Nota fuera de rango (0.0 - 5.0)']);
                    break;
                }
            }

            // Upsert: UPDATE si ya existe la fila (acn3_id, estu_id), INSERT si no
            $check = $pdo->prepare("SELECT non3_id FROM notasn3 WHERE acn3_id = ? AND estu_id = ?");
            $check->execute([$acn3_id, $estu_id]);
            $existente = $check->fetch();

            try {
                if ($existente) {
                    $stmt = $pdo->prepare("UPDATE notasn3 SET non3_valor = ? WHERE non3_id = ?");
                    $stmt->execute([$non3_valor, $existente['non3_id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO notasn3 (acn3_id, estu_id, non3_valor) VALUES (?, ?, ?)");
                    $stmt->execute([$acn3_id, $estu_id, $non3_valor]);
                }
            } catch (PDOException $e) {
                // Condición de carrera: otra petición insertó la fila entre el
                // SELECT y el INSERT — uq_non3_acn3_estu la protege. Reintentar
                // como UPDATE en vez de fallar (mismo criterio ya documentado
                // en CLAUDE.md para SQLSTATE 23000 sobre asignaciones concurrentes).
                if ($e->getCode() === '23000') {
                    $stmt = $pdo->prepare("UPDATE notasn3 SET non3_valor = ? WHERE acn3_id = ? AND estu_id = ?");
                    $stmt->execute([$non3_valor, $acn3_id, $estu_id]);
                } else {
                    throw $e;
                }
            }

            // Recalcular cali_n3 (promedio de notasn3, solo si todas las
            // actividades tienen nota) y propagar a Nota Final/Definitiva.
            $n3 = recalcularN3($pdo, $grmo_id, $estu_id);

            $existeCalif = $pdo->prepare("SELECT cali_id FROM calificaciones WHERE grmo_id = ? AND estu_id = ?");
            $existeCalif->execute([$grmo_id, $estu_id]);
            $filaCalif = $existeCalif->fetch();

            if ($filaCalif) {
                $updN3 = $pdo->prepare("UPDATE calificaciones SET cali_n3 = ? WHERE cali_id = ?");
                $updN3->execute([$n3, $filaCalif['cali_id']]);
            } else {
                $insN3 = $pdo->prepare("INSERT INTO calificaciones (grmo_id, estu_id, cali_n3) VALUES (?, ?, ?)");
                $insN3->execute([$grmo_id, $estu_id, $n3]);
            }

            recalcularNotaFinalYDefinitiva($pdo, $grmo_id, $estu_id);

            $finalStmt = $pdo->prepare("
                SELECT cali_n3, cali_nota_final, cali_definitiva
                FROM calificaciones WHERE grmo_id = ? AND estu_id = ?
            ");
            $finalStmt->execute([$grmo_id, $estu_id]);
            $filaFinal = $finalStmt->fetch();

            echo json_encode([
                'status'          => 'ok',
                'acn3_id'         => $acn3_id,
                'estu_id'         => $estu_id,
                'non3_valor'      => $non3_valor,
                'cali_n3'         => $filaFinal['cali_n3'],
                'cali_nota_final' => $filaFinal['cali_nota_final'],
                'cali_definitiva' => $filaFinal['cali_definitiva'],
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
