<?php
session_start();
require_once '../00_connect/pdo.php';

// Resuelve el estu_id objetivo del reporte: para role_id=4 siempre es el
// propio estudiante en sesión (ignora cualquier estu_id que llegue por
// POST); para role_id=1/2 es el estu_id recibido por POST (el coordinador
// consultando a otro estudiante). Cualquier otro role_id retorna null.
function resolverEstuIdObjetivo(): ?int {
    $role_id = (int)($_SESSION['role_id'] ?? 0);
    if ($role_id === 4) {
        return isset($_SESSION['estu_id']) ? (int)$_SESSION['estu_id'] : null;
    }
    if (in_array($role_id, [1, 2], true)) {
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        return $estu_id > 0 ? $estu_id : null;
    }
    return null;
}

// Mismos 3 casos que badgeEstado() en reportes_ctrl.js / estadoInfo() en
// pdf_boletin.php — replicado aquí, no duplicado con lógica distinta.
function estadoModuloTexto($notaFinal, $definitiva): string {
    if ($definitiva !== null) {
        return ((float)$definitiva >= 3.0) ? 'Aprobado' : 'Reprobado';
    }
    if ($notaFinal !== null) {
        return 'Reprobado — pendiente habilitación';
    }
    return 'En curso';
}

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

            $stmtGrupo = $pdo->prepare("
                SELECT gm.grmo_id,
                       m.modu_nombre, m.modu_sigla,
                       gs.grse_codigo,
                       p.prog_nombre, p.prog_sigla,
                       pe.peri_codigo,
                       d.doce_nombres, d.doce_apellidos,
                       gs.grse_jornada
                FROM gruposmodulos gm
                INNER JOIN modulos m ON gm.modu_id = m.modu_id
                INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                INNER JOIN programas p ON gs.prog_id = p.prog_id
                INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
                INNER JOIN docentes d ON gm.doce_id = d.doce_id
                WHERE gm.grmo_id = ?
            ");
            $stmtGrupo->execute([$grmo_id]);
            $contexto = $stmtGrupo->fetch();

            $stmt = $pdo->prepare("
                SELECT e.estu_nombres, e.estu_apellidos, e.estu_numerodoc,
                       c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
                       c.cali_n3, c.cali_n4, c.cali_sup_n4,
                       c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva,
                       c.cali_observacion
                FROM grmoestudiantes ge
                JOIN estudiantes e ON ge.estu_id = e.estu_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
                WHERE ge.grmo_id = ?
                ORDER BY e.estu_apellidos, e.estu_nombres
            ");
            $stmt->execute([$grmo_id]);
            echo json_encode(['status' => 'ok', 'grupo' => $contexto, 'data' => $stmt->fetchAll()]);
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
                SELECT m.modu_id, m.modu_nombre, m.modu_sigla,
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
                       c.cali_n3, c.cali_n4, c.cali_sup_n4,
                       c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva,
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

    // ── PROGRAMAS/MATRÍCULAS DEL ESTUDIANTE OBJETIVO (roles 1, 2, 4) ────────

    case 'mis_programas':
        try {
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            if (!in_array($role_id, [1, 2, 4], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $estu_id = resolverEstuIdObjetivo();
            if ($estu_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo determinar el estudiante']);
                break;
            }
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT m.matr_id, m.prog_id, p.prog_nombre, p.prog_sigla,
                       m.matr_estado, m.matr_estado_academico,
                       m.matr_semestre, p.prog_duracion_semestres,
                       m.coho_id, c.coho_codigo
                FROM matriculas m
                INNER JOIN programas p ON m.prog_id = p.prog_id
                LEFT JOIN cohortes c ON m.coho_id = c.coho_id
                WHERE m.estu_id = ?
                ORDER BY m.matr_id ASC
            ");
            $stmt->execute([$estu_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── PERÍODOS CURSADOS POR EL ESTUDIANTE EN UN PROGRAMA (roles 1, 2, 4) ──

    case 'mis_periodos':
        try {
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            if (!in_array($role_id, [1, 2, 4], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $estu_id = resolverEstuIdObjetivo();
            if ($estu_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo determinar el estudiante']);
                break;
            }
            $matr_id = (int)($_POST['matr_id'] ?? 0);
            if ($matr_id === 0) {
                echo json_encode(['status' => 'error', 'message' => 'matr_id inválido']);
                break;
            }
            $pdo = getConexion();

            // El matr_id debe pertenecer al estudiante objetivo — nunca
            // confiar en que el cliente solo pida las suyas.
            $stmtMatr = $pdo->prepare("SELECT prog_id FROM matriculas WHERE matr_id = ? AND estu_id = ?");
            $stmtMatr->execute([$matr_id, $estu_id]);
            $matricula = $stmtMatr->fetch();
            if (!$matricula) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización sobre esta matrícula']);
                break;
            }
            $prog_id = (int)$matricula['prog_id'];

            // Sin filtro de grmo_activo — se quiere el histórico completo de
            // períodos cursados, activos o no. Orden: más reciente primero
            // (peri_anio/peri_semestre DESC, mismo criterio ya usado en
            // calificaciones_mdl.php/listar_periodos_filtro). El frontend
            // puede derivar el ordinal ("1er período", "2do período", etc.)
            // invirtiendo este orden (el más antiguo de esta lista es el 1).
            $stmt = $pdo->prepare("
                SELECT DISTINCT gs.peri_id, pe.peri_codigo, pe.peri_anio, pe.peri_semestre
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN periodos pe ON gs.peri_id = pe.peri_id
                WHERE ge.estu_id = ? AND gs.prog_id = ?
                ORDER BY pe.peri_anio DESC, pe.peri_semestre DESC
            ");
            $stmt->execute([$estu_id, $prog_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── MÓDULOS + ESTADO DE UN PERÍODO ESPECÍFICO (roles 1, 2, 4) ───────────

    case 'detalle_periodo':
        try {
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            if (!in_array($role_id, [1, 2, 4], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $estu_id = resolverEstuIdObjetivo();
            if ($estu_id === null) {
                echo json_encode(['status' => 'error', 'message' => 'No se pudo determinar el estudiante']);
                break;
            }
            $matr_id = (int)($_POST['matr_id'] ?? 0);
            $peri_id = (int)($_POST['peri_id'] ?? 0);
            if ($matr_id === 0 || $peri_id === 0) {
                echo json_encode(['status' => 'error', 'message' => 'matr_id y peri_id son requeridos']);
                break;
            }
            $pdo = getConexion();

            $stmtMatr = $pdo->prepare("
                SELECT m.prog_id, m.matr_semestre, p.prog_duracion_semestres
                FROM matriculas m
                INNER JOIN programas p ON m.prog_id = p.prog_id
                WHERE m.matr_id = ? AND m.estu_id = ?
            ");
            $stmtMatr->execute([$matr_id, $estu_id]);
            $matricula = $stmtMatr->fetch();
            if (!$matricula) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización sobre esta matrícula']);
                break;
            }
            $prog_id = (int)$matricula['prog_id'];

            $stmt = $pdo->prepare("
                SELECT gm.grmo_id, m.modu_sigla, m.modu_nombre, gs.grse_codigo,
                       d.doce_nombres, d.doce_apellidos,
                       c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
                       c.cali_n3, c.cali_n4, c.cali_sup_n4,
                       c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN docentes d ON gm.doce_id = d.doce_id
                LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
                WHERE ge.estu_id = ? AND gs.prog_id = ? AND gs.peri_id = ?
                ORDER BY m.modu_orden
            ");
            $stmt->execute([$estu_id, $prog_id, $peri_id]);
            $modulos = $stmt->fetchAll();

            foreach ($modulos as &$mod) {
                $mod['estado_modulo'] = estadoModuloTexto($mod['cali_nota_final'], $mod['cali_definitiva']);
            }
            unset($mod);

            // Estado del período: 'En Curso' si algún módulo todavía no tiene
            // cali_definitiva; si TODOS ya la tienen, promedio aritmético
            // simple de esas definitivas decide Aprobado/Reprobado.
            // 'Aplazado' es un 4to valor posible (alineado con
            // matr_estado_academico) pero no es alcanzable todavía por
            // ningún cálculo de este case — se activará en una fase futura.
            $estadoPeriodo   = 'En Curso';
            $promedioPeriodo = null;
            if (count($modulos) > 0) {
                $definitivas    = array_column($modulos, 'cali_definitiva');
                $todasDefinidas = !in_array(null, $definitivas, true);
                if ($todasDefinidas) {
                    $promedioPeriodo = round(array_sum(array_map('floatval', $definitivas)) / count($definitivas), 1);
                    $estadoPeriodo   = ($promedioPeriodo >= 3.0) ? 'Aprobado' : 'Reprobado';
                }
            }

            echo json_encode([
                'status'                   => 'ok',
                'estado_periodo'           => $estadoPeriodo,
                'promedio_periodo'         => $promedioPeriodo,
                'matr_semestre'            => $matricula['matr_semestre'],
                'prog_duracion_semestres'  => $matricula['prog_duracion_semestres'],
                'data'                     => $modulos,
            ]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    // ── BÚSQUEDA DE ESTUDIANTE PARA EL SELECTOR DEL COORDINADOR (1, 2) ──────

    case 'buscar_estudiante':
        try {
            $role_id = (int)($_SESSION['role_id'] ?? 0);
            if (!in_array($role_id, [1, 2], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
                break;
            }
            $termino = trim($_POST['termino'] ?? '');
            if (mb_strlen($termino) < 3) {
                echo json_encode(['status' => 'error', 'message' => 'Ingrese al menos 3 caracteres']);
                break;
            }
            $pdo = getConexion();
            $like = '%' . $termino . '%';
            $stmt = $pdo->prepare("
                SELECT estu_id, estu_nombres, estu_apellidos, estu_numerodoc, estu_tipodoc
                FROM estudiantes
                WHERE estu_nombres LIKE ? OR estu_apellidos LIKE ? OR estu_numerodoc LIKE ?
                ORDER BY estu_apellidos, estu_nombres
                LIMIT 15
            ");
            $stmt->execute([$like, $like, $like]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}
