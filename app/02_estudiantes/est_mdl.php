<?php
session_start();
require_once '../00_connect/pdo.php';
require_once '../00_files/helpers.php';

function calcularEstadoFicha(array $fila): string {
    $requeridos = [
        'prog_id', 'jornada', 'acud_es', 'acud_parentesco', 'acud_nombres', 'acud_apellidos',
        'acud_profesion', 'acud_empresa', 'acud_telefono', 'acud_direccion', 'acud_barrio', 'acud_ciudad',
        'estudio_tipo', 'estudio_titulo', 'estudio_institucion', 'estudio_aniofin',
    ];
    if ((int)($fila['padr_vive'] ?? 0) === 1) {
        $requeridos = array_merge($requeridos, [
            'padr_nombres', 'padr_apellidos', 'padr_profesion', 'padr_empresa',
            'padr_telefono', 'padr_direccion', 'padr_barrio', 'padr_ciudad',
        ]);
    }
    if ((int)($fila['madr_vive'] ?? 0) === 1) {
        $requeridos = array_merge($requeridos, [
            'madr_nombres', 'madr_apellidos', 'madr_profesion', 'madr_empresa',
            'madr_telefono', 'madr_direccion', 'madr_barrio', 'madr_ciudad',
        ]);
    }

    $llenos = 0;
    foreach ($requeridos as $campo) {
        $valor = $fila[$campo] ?? null;
        if ($valor !== null && $valor !== '') {
            $llenos++;
        }
    }

    if ($llenos === 0) {
        return 'sin_iniciar';
    }
    if ($llenos === count($requeridos)) {
        return 'completa';
    }
    return 'incompleta';
}

// Extraída de los bloques inline (antes duplicados) de 'matricular' y
// 'editar_matricula' — misma consulta y mismo rango exactos, sin cambio
// de comportamiento. Retorna null si es válido, mensaje de error si no.
function validarSemestrePrograma(PDO $pdo, int $prog_id, int $matr_semestre): ?string {
    $stmt = $pdo->prepare("SELECT prog_duracion_semestres FROM programas WHERE prog_id = ?");
    $stmt->execute([$prog_id]);
    $duracion = $stmt->fetchColumn();
    if ($duracion === false) return 'Programa no encontrado';
    if ($matr_semestre < 1 || $matr_semestre > (int)$duracion) {
        return 'Semestre inválido para este programa';
    }
    return null;
}

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar_aspirantes':
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
            $sql = "SELECT e.estu_id, e.estu_nombres, e.estu_apellidos,
                           e.estu_tipodoc, e.estu_numerodoc, e.estu_telefono,
                           e.estu_origen, e.estu_foto, e.fechacreacion,
                           fi.prog_id, fi.jornada,
                           fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
                           fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
                           fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
                           fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
                           fi.acud_es, fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion,
                           fi.acud_empresa, fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
                           fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin
                    FROM estudiantes e
                    LEFT JOIN matriculas m ON e.estu_id = m.estu_id
                    LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
                    WHERE e.estu_activo = 1
                      AND (m.matr_estado = 'aspirante' OR m.matr_id IS NULL)
                    ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['estado_ficha'] = calcularEstadoFicha($row);
            }
            unset($row);
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar aspirantes']);
        }
        break;

    case 'listar_matriculados':
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

            $prog_id_filtro = trim($_POST['prog_id'] ?? '');
            $peri_id_filtro = trim($_POST['peri_id'] ?? '');
            $grse_id_filtro = trim($_POST['grse_id'] ?? '');
            $modu_id_filtro = trim($_POST['modu_id'] ?? '');

            $params = [];

            $condicionPeriodoModulos = '';
            if ($peri_id_filtro !== '') {
                $condicionPeriodoModulos = ' AND gs3.peri_id = ?';
                $params[] = (int)$peri_id_filtro;
            }

            $where = "WHERE e.estu_activo = 1 AND m.matr_estado = 'matriculado'";

            if ($prog_id_filtro !== '') {
                $where .= " AND m.prog_id = ?";
                $params[] = (int)$prog_id_filtro;
            }
            if ($peri_id_filtro !== '') {
                $where .= " AND m.peri_id = ?";
                $params[] = (int)$peri_id_filtro;
            }
            if ($grse_id_filtro !== '') {
                $where .= " AND EXISTS (
                    SELECT 1 FROM grmoestudiantes geg
                    JOIN gruposmodulos gmg ON geg.grmo_id = gmg.grmo_id
                    WHERE geg.estu_id = e.estu_id AND gmg.grse_id = ? AND gmg.grmo_activo = 1
                )";
                $params[] = (int)$grse_id_filtro;
            }
            if ($modu_id_filtro !== '') {
                $where .= " AND EXISTS (
                    SELECT 1 FROM grmoestudiantes gem
                    JOIN gruposmodulos gmm ON gem.grmo_id = gmm.grmo_id
                    WHERE gem.estu_id = e.estu_id AND gmm.modu_id = ? AND gmm.grmo_activo = 1
                )";
                $params[] = (int)$modu_id_filtro;
            }

            $sql = "SELECT e.estu_id, e.estu_nombres, e.estu_apellidos,
                           e.estu_tipodoc, e.estu_numerodoc, e.estu_email, e.estu_foto,
                           e.fechanacimiento,
                           co.coho_codigo,
                           (SELECT COUNT(*) FROM grmoestudiantes ge3
                            JOIN gruposmodulos gm3 ON ge3.grmo_id = gm3.grmo_id
                            JOIN gruposemestres gs3 ON gm3.grse_id = gs3.grse_id
                            WHERE ge3.estu_id = e.estu_id AND gm3.grmo_activo = 1
                              AND gs3.prog_id = m.prog_id{$condicionPeriodoModulos}) AS total_modulos,
                           -- COUNT(DISTINCT prog_id), no COUNT(*): el indicador 'N programas'
                           -- debe reflejar programas DISTINTOS que la persona cursa, no filas
                           -- de matriculas — desde que matr_semestre permite varias filas por
                           -- estu_id+prog_id (una por semestre), COUNT(*) contaba de más (ej.
                           -- 2 semestres del mismo programa mostraban '2 programas').
                           (SELECT COUNT(DISTINCT mt3.prog_id) FROM matriculas mt3 WHERE mt3.estu_id = e.estu_id) AS total_matriculas_estudiante,
                           (SELECT GROUP_CONCAT(CONCAT(p2.prog_sigla, ' — ', UPPER(LEFT(mt4.matr_estado, 1)), SUBSTRING(mt4.matr_estado, 2))
                                                 ORDER BY mt4.matr_id SEPARATOR ', ')
                            FROM matriculas mt4
                            INNER JOIN programas p2 ON mt4.prog_id = p2.prog_id
                            WHERE mt4.estu_id = e.estu_id) AS detalle_matriculas_estudiante,
                           -- Mismo valor repetido en cada fila, sin relación con estu_id —
                           -- total de programas activos en todo el sistema.
                           (SELECT COUNT(*) FROM programas WHERE prog_activo = 1) AS programas_activos_totales,
                           -- Misma condición de matr_estado usada en los cases 'matricular'
                           -- y 'editar_matricula' (solo 'matriculado', sin incluir
                           -- aspirante/retirado/graduado) — programas DISTINTOS que el
                           -- estudiante ya cursa activamente, entre los que siguen activos.
                           (SELECT COUNT(DISTINCT mt5.prog_id)
                            FROM matriculas mt5
                            INNER JOIN programas p3 ON mt5.prog_id = p3.prog_id
                            WHERE mt5.estu_id = e.estu_id
                              AND mt5.matr_estado = 'matriculado'
                              AND p3.prog_activo = 1) AS programas_matriculados_activos,
                           -- Réplica exacta de la lógica de promedio de 'detalle_periodo'
                           -- (reportes_mdl.php): promedio de cali_definitiva de los módulos
                           -- de ESTE prog_id+peri_id para este estudiante, solo si TODAS
                           -- están pobladas y el promedio es >= 3.0. Sin filtro por
                           -- grmo_activo — detalle_periodo tampoco lo aplica. Duplicación
                           -- consciente del umbral 3.0 (ya repetido en ~15 lugares del
                           -- proyecto), no extraída a función compartida entre módulos.
                           (SELECT CASE
                                WHEN COUNT(*) = 0 THEN 0
                                WHEN SUM(CASE WHEN c6.cali_definitiva IS NULL THEN 1 ELSE 0 END) > 0 THEN 0
                                WHEN AVG(c6.cali_definitiva) >= 3.0 THEN 1
                                ELSE 0
                            END
                            FROM grmoestudiantes ge6
                            JOIN gruposmodulos gm6 ON ge6.grmo_id = gm6.grmo_id
                            JOIN gruposemestres gs6 ON gm6.grse_id = gs6.grse_id
                            LEFT JOIN calificaciones c6 ON c6.grmo_id = ge6.grmo_id AND c6.estu_id = ge6.estu_id
                            WHERE ge6.estu_id = e.estu_id AND gs6.prog_id = m.prog_id AND gs6.peri_id = m.peri_id
                           ) AS aprobado_periodo_actual,
                           -- Solicitud de actualización de datos MÁS RECIENTE de este
                           -- estudiante, solo si sigue activa ('generado'/'recibido') —
                           -- NULL si nunca tuvo una o si la última ya fue resuelta
                           -- (aprobada/descartada). Único dato que la Fase 4 necesita
                           -- para decidir si pintar el badge de actualización pendiente.
                           (SELECT soac1.soac_estado FROM solicitudes_actualizacion soac1
                            WHERE soac1.estu_id = e.estu_id AND soac1.soac_estado IN ('generado','recibido')
                            ORDER BY soac1.soac_id DESC LIMIT 1
                           ) AS soac_estado_activo,
                           -- Fecha de la aprobación MÁS RECIENTE del historial de este
                           -- estudiante, sin importar si hay algo pendiente ahora mismo
                           -- (independiente de soac_estado_activo arriba) — NULL si nunca
                           -- tuvo ninguna solicitud aprobada. Badge permanente
                           -- 'Actualizado', no ligado a ninguna ventana de tiempo.
                           (SELECT soac2.soac_resuelto_en FROM solicitudes_actualizacion soac2
                            WHERE soac2.estu_id = e.estu_id AND soac2.soac_estado = 'aprobado'
                            ORDER BY soac2.soac_id DESC LIMIT 1
                           ) AS soac_fecha_ultima_aprobacion,
                           p.prog_sigla, p.prog_duracion_semestres, m.matr_semestre,
                           m.peri_id, pe.peri_codigo, m.matr_estado, m.matr_estado_academico,
                           m.matr_id, m.prog_id AS matr_prog_id,
                           fi.prog_id, fi.jornada,
                           fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
                           fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
                           fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
                           fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
                           fi.acud_es, fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion,
                           fi.acud_empresa, fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
                           fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin,
                           u.usua_ultimo_acceso, u.fechacreacion
                    FROM estudiantes e
                    INNER JOIN matriculas m ON e.estu_id = m.estu_id
                    INNER JOIN programas p ON m.prog_id = p.prog_id
                    INNER JOIN periodos pe ON m.peri_id = pe.peri_id
                    LEFT JOIN cohortes co ON m.coho_id = co.coho_id
                    LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
                    LEFT JOIN usuarios u ON e.usua_id = u.usua_id
                    {$where}
                    ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['estado_ficha'] = calcularEstadoFicha($row);
                $row['ultimo_acceso_fmt'] = formatearUltimoAcceso($row['usua_ultimo_acceso'], $row['fechacreacion']);
            }
            unset($row);
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar matriculados']);
        }
        break;

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
            $stmt = $pdo->prepare("SELECT prog_id, prog_nombre, prog_sigla, prog_duracion_semestres FROM programas ORDER BY prog_nombre ASC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al cargar programas']);
        }
        break;

    case 'listar_periodos':
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
            $stmt = $pdo->prepare("SELECT peri_id, peri_codigo, peri_activo FROM periodos ORDER BY peri_anio DESC, peri_semestre DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al cargar períodos']);
        }
        break;

    case 'listar_cohortes':
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
            $stmt = $pdo->prepare("SELECT coho_id, coho_codigo FROM cohortes WHERE coho_activa = 1 ORDER BY coho_codigo DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al cargar cohortes']);
        }
        break;

    case 'listar_cohortes_por_programa':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        $prog_id = (int)($_POST['prog_id'] ?? 0);
        if ($prog_id === 0) {
            echo json_encode(['status' => 'ok', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT coho_id, coho_codigo FROM cohortes WHERE prog_id = ? AND coho_activa = 1 ORDER BY coho_codigo DESC");
            $stmt->execute([$prog_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    // ── CATÁLOGOS PARA FILTROS DE listar_matriculados (Coordinador/Admin) ────

    case 'listar_grupos_filtro':
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
            $stmt = $pdo->prepare("SELECT grse_id, grse_codigo FROM gruposemestres WHERE grse_activo = 1 ORDER BY grse_codigo DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    case 'listar_modulos_filtro':
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
            $stmt = $pdo->prepare("SELECT modu_id, modu_sigla, modu_nombre FROM modulos WHERE modu_activo = 1 ORDER BY modu_sigla");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    // ── MODAL DE DETALLE: MÓDULOS ASIGNADOS A UN ESTUDIANTE ──────────────────

    case 'listar_modulos_estudiante':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        $peri_id_filtro = trim($_POST['peri_id'] ?? '');
        $prog_id_filtro = trim($_POST['prog_id'] ?? '');
        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $params = [$estu_id];
            // prog_id es opcional — si no llega, el comportamiento queda
            // igual que antes (todos los módulos del estudiante, sin
            // distinguir programa), para no romper otro llamador que
            // pudiera invocar este case sin ese contexto.
            $condicionPrograma = '';
            if ($prog_id_filtro !== '') {
                $condicionPrograma = ' AND gs.prog_id = ?';
                $params[] = (int)$prog_id_filtro;
            }
            $condicionPeriodo = '';
            if ($peri_id_filtro !== '') {
                $condicionPeriodo = ' AND gs.peri_id = ?';
                $params[] = (int)$peri_id_filtro;
            }
            $stmt = $pdo->prepare("
                SELECT m.modu_sigla, m.modu_nombre, gs.grse_codigo, pe.peri_codigo,
                       d.doce_nombres, d.doce_apellidos
                FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN modulos m ON gm.modu_id = m.modu_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                JOIN periodos pe ON gs.peri_id = pe.peri_id
                JOIN docentes d ON gm.doce_id = d.doce_id
                WHERE ge.estu_id = ? AND gm.grmo_activo = 1{$condicionPrograma}{$condicionPeriodo}
                ORDER BY pe.peri_codigo DESC, m.modu_orden
            ");
            $stmt->execute($params);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    case 'obtener_defaults_matricula':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => null]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => null]);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? $_GET['estu_id'] ?? 0);

        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido', 'data' => null]);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT prog_id, jornada FROM fichas_inscripcion WHERE estu_id = :estu_id LIMIT 1");
            $stmt->execute(['estu_id' => $estu_id]);
            $row = $stmt->fetch();

            echo json_encode(['status' => 'ok', 'data' => $row ?: null]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener valores por defecto', 'data' => null]);
        }
        break;

    case 'matricular':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id     = (int)($_POST['estu_id'] ?? 0);
        $prog_id     = (int)($_POST['prog_id'] ?? 0);
        $peri_id     = (int)($_POST['peri_id'] ?? 0);

        if ($estu_id === 0 || $prog_id === 0 || $peri_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Estudiante, programa y período son requeridos']);
            break;
        }

        $tipo_acceso  = trim($_POST['tipo_acceso'] ?? 'no');
        $clave_manual = trim($_POST['clave_manual'] ?? '');

        if ($tipo_acceso === 'manual' && $clave_manual === '') {
            echo json_encode(['status' => 'error', 'message' => 'Ingrese la clave manual']);
            break;
        }

        $coho_id          = (int)($_POST['coho_id'] ?? 0) ?: null;
        $matr_folio       = trim($_POST['matr_folio'] ?? '') ?: null;
        $fechamatricula   = trim($_POST['fechamatricula'] ?? '') ?: null;
        $matr_observacion = trim($_POST['matr_observacion'] ?? '') ?: null;

        try {
            $pdo = getConexion();

            $stmtEstu = $pdo->prepare(
                "SELECT estu_numerodoc, estu_apellidos, fechanacimiento, usua_id, estu_email
                 FROM estudiantes WHERE estu_id = ?"
            );
            $stmtEstu->execute([$estu_id]);
            $estudiante = $stmtEstu->fetch();

            if (!$estudiante) {
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }

            // matr_semestre: si no llega por POST, se asume semestre 1
            // explícitamente (no se deja el INSERT sin columna confiando en
            // el DEFAULT de la tabla) — igual pasa por la misma validación
            // contra prog_duracion_semestres que un valor sí enviado.
            $matr_semestre_raw = trim($_POST['matr_semestre'] ?? '');
            $matr_semestre = ($matr_semestre_raw === '') ? 1 : (int)$matr_semestre_raw;

            $errorSemestre = validarSemestrePrograma($pdo, $prog_id, $matr_semestre);
            if ($errorSemestre !== null) {
                echo json_encode(['status' => 'error', 'message' => $errorSemestre]);
                break;
            }

            // Validación de programa duplicado: si el estudiante ya está
            // 'matriculado' en este mismo prog_id pero en OTRO peri_id, esto
            // no es una reactivación de la matrícula exacta (esa la cubre el
            // chequeo de $existingMatr más abajo, sobre el mismo peri_id) —
            // es un intento de crear una segunda matrícula duplicada del
            // mismo programa. Se bloquea antes de tocar $existingMatr.
            $stmtProgCheck = $pdo->prepare(
                "SELECT matr_id FROM matriculas
                 WHERE estu_id = ? AND prog_id = ? AND matr_estado = 'matriculado' AND peri_id != ?"
            );
            $stmtProgCheck->execute([$estu_id, $prog_id, $peri_id]);
            if ($stmtProgCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'El estudiante ya está matriculado en este programa. Use Editar Matrícula si desea modificar el período o la cohorte.']);
                break;
            }

            $stmtCheck = $pdo->prepare(
                "SELECT matr_id FROM matriculas WHERE estu_id = ? AND prog_id = ? AND peri_id = ?"
            );
            $stmtCheck->execute([$estu_id, $prog_id, $peri_id]);
            $existingMatr = $stmtCheck->fetch();

            // Determine clave before transaction
            $clave_generada = null;
            $crear_acceso = ($tipo_acceso !== 'no' && $estudiante['usua_id'] === null);

            if ($crear_acceso) {
                $numerodoc = $estudiante['estu_numerodoc'];

                $stmtLogin = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_login = ?");
                $stmtLogin->execute([$numerodoc]);

                if (!$stmtLogin->fetch()) {
                    $clave_generada = ($tipo_acceso === 'automatica')
                        ? generarClaveAuto($estudiante['estu_apellidos'], $estudiante['fechanacimiento'])
                        : $clave_manual;
                } else {
                    $crear_acceso = false;
                }

                if ($crear_acceso && trim($estudiante['estu_email'] ?? '') === '') {
                    echo json_encode(['status' => 'error', 'message' => 'Este estudiante no tiene correo registrado. Edítalo antes de crear el acceso.']);
                    break;
                }
            }

            $pdo->beginTransaction();

            if ($existingMatr) {
                $stmtM = $pdo->prepare(
                    "UPDATE matriculas
                     SET matr_estado = 'matriculado', coho_id = ?, matr_semestre = ?, matr_folio = ?,
                         fechamatricula = ?, matr_observacion = ?
                     WHERE matr_id = ?"
                );
                $stmtM->execute([
                    $coho_id, $matr_semestre,
                    $matr_folio, $fechamatricula, $matr_observacion,
                    $existingMatr['matr_id']
                ]);
            } else {
                $stmtM = $pdo->prepare(
                    "INSERT INTO matriculas
                        (estu_id, prog_id, peri_id, coho_id, matr_estado, matr_semestre, matr_folio,
                         fechainscripcion, fechamatricula, matr_observacion)
                     VALUES (?, ?, ?, ?, 'matriculado', ?, ?, CURDATE(), ?, ?)"
                );
                $stmtM->execute([
                    $estu_id, $prog_id, $peri_id, $coho_id, $matr_semestre,
                    $matr_folio, $fechamatricula, $matr_observacion
                ]);
            }

            if ($crear_acceso && $clave_generada !== null) {
                $hash        = password_hash($clave_generada, PASSWORD_BCRYPT);
                $numerodoc   = $estudiante['estu_numerodoc'];
                $usua_email  = $estudiante['estu_email'];

                $stmtU = $pdo->prepare(
                    "INSERT INTO usuarios (role_id, usua_login, usua_email, usua_passwordhash) VALUES (4, ?, ?, ?)"
                );
                $stmtU->execute([$numerodoc, $usua_email, $hash]);
                $new_usua_id = $pdo->lastInsertId();

                $stmtUpdEstu = $pdo->prepare("UPDATE estudiantes SET usua_id = ? WHERE estu_id = ?");
                $stmtUpdEstu->execute([$new_usua_id, $estu_id]);
            }

            $pdo->commit();

            $response = ['status' => 'ok', 'rows' => 1];
            if ($clave_generada !== null) {
                $response['clave_generada'] = $clave_generada;
                $response['usua_email']     = $usua_email;
            }
            echo json_encode($response);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al procesar la matrícula']);
        }
        break;

    // ── RESUMEN DE MATRÍCULAS DE UN ESTUDIANTE (todas, cualquier estado) ────
    // Alimenta el contexto del modal "Matricular en otro programa" y la
    // sección "Programas matriculados" del modal "Datos Estudiante".

    case 'matriculas_estudiante':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida', 'data' => []]);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización', 'data' => []]);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "SELECT m.matr_id, m.prog_id, p.prog_nombre, p.prog_sigla,
                        m.peri_id, pe.peri_codigo,
                        m.matr_estado, m.matr_estado_academico, m.fechamatricula
                 FROM matriculas m
                 INNER JOIN programas p ON m.prog_id = p.prog_id
                 INNER JOIN periodos pe ON m.peri_id = pe.peri_id
                 WHERE m.estu_id = ?
                 ORDER BY m.matr_id ASC"
            );
            $stmt->execute([$estu_id]);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener las matrículas del estudiante', 'data' => []]);
        }
        break;

    case 'obtener_matricula':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $matr_id = (int)($_POST['matr_id'] ?? 0);
        if ($matr_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de matrícula inválido']);
            break;
        }
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("
                SELECT m.matr_id, m.prog_id, m.peri_id, m.estu_id, m.coho_id, m.matr_semestre,
                       m.matr_folio, m.fechamatricula, m.matr_observacion, m.matr_numero,
                       e.estu_nombres, e.estu_apellidos
                FROM matriculas m
                INNER JOIN estudiantes e ON m.estu_id = e.estu_id
                WHERE m.matr_id = ?
            ");
            $stmt->execute([$matr_id]);
            $matricula = $stmt->fetch();
            if (!$matricula) {
                echo json_encode(['status' => 'error', 'message' => 'Matrícula no encontrada']);
                break;
            }
            echo json_encode(['status' => 'ok', 'data' => $matricula]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'editar_matricula':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $matr_id          = (int)($_POST['matr_id'] ?? 0);
        $prog_id          = (int)($_POST['prog_id'] ?? 0);
        $peri_id          = (int)($_POST['peri_id'] ?? 0);
        $coho_id          = (int)($_POST['coho_id'] ?? 0);
        $matr_folio       = trim($_POST['matr_folio'] ?? '') ?: null;
        $fechamatricula   = trim($_POST['fechamatricula'] ?? '') ?: null;
        $matr_observacion = trim($_POST['matr_observacion'] ?? '') ?: null;
        $matr_numero_raw  = trim($_POST['matr_numero'] ?? '');
        $matr_numero      = ($matr_numero_raw === '') ? null : (int)$matr_numero_raw;
        if ($matr_id === 0 || $prog_id === 0 || $peri_id === 0 || $coho_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Programa, cohorte y período son requeridos']);
            break;
        }
        try {
            $pdo = getConexion();
            $pdo->beginTransaction();

            $stmtEstu = $pdo->prepare("SELECT estu_id FROM matriculas WHERE matr_id = ?");
            $stmtEstu->execute([$matr_id]);
            $estu_id = $stmtEstu->fetchColumn();
            if (!$estu_id) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Matrícula no encontrada']);
                break;
            }

            // matr_semestre: misma validación que 'matricular' — si no llega
            // por POST, se asume semestre 1 explícitamente.
            $matr_semestre_raw = trim($_POST['matr_semestre'] ?? '');
            $matr_semestre = ($matr_semestre_raw === '') ? 1 : (int)$matr_semestre_raw;

            $errorSemestre = validarSemestrePrograma($pdo, $prog_id, $matr_semestre);
            if ($errorSemestre !== null) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $errorSemestre]);
                break;
            }

            // Misma validación de programa duplicado ya usada en 'matricular':
            // si el nuevo prog_id coincide con el de OTRA matrícula
            // 'matriculado' del mismo estudiante (matr_id distinto al que se
            // está editando), bloquea.
            $stmtProgCheck = $pdo->prepare(
                "SELECT matr_id FROM matriculas
                 WHERE estu_id = ? AND prog_id = ? AND matr_estado = 'matriculado' AND matr_id != ?"
            );
            $stmtProgCheck->execute([$estu_id, $prog_id, $matr_id]);
            if ($stmtProgCheck->fetch()) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'El estudiante ya está matriculado en este programa. Use Editar Matrícula si desea modificar el período o la cohorte.']);
                break;
            }

            if ($matr_numero !== null) {
                $checkNumero = $pdo->prepare("SELECT matr_id FROM matriculas WHERE matr_numero = ? AND matr_id != ?");
                $checkNumero->execute([$matr_numero, $matr_id]);
                if ($checkNumero->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => 'El número de matrícula ya está asignado a otro estudiante.']);
                    break;
                }
            }

            $stmtM = $pdo->prepare(
                "UPDATE matriculas
                 SET prog_id = ?, peri_id = ?, coho_id = ?, matr_semestre = ?, matr_folio = ?,
                     fechamatricula = ?, matr_observacion = ?, matr_numero = ?
                 WHERE matr_id = ?"
            );
            $stmtM->execute([$prog_id, $peri_id, $coho_id, $matr_semestre, $matr_folio, $fechamatricula, $matr_observacion, $matr_numero, $matr_id]);

            $stmtDel = $pdo->prepare("
                DELETE ge FROM grmoestudiantes ge
                JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                WHERE ge.estu_id = ? AND (gs.prog_id != ? OR gs.peri_id != ?)
            ");
            $stmtDel->execute([$estu_id, $prog_id, $peri_id]);
            $modulos_retirados = $stmtDel->rowCount();

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'modulos_retirados' => $modulos_retirados]);
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al editar la matrícula']);
        }
        break;

    // ── AVANZAR AL SIGUIENTE SEMESTRE ────────────────────────────────────
    // Nueva fila en matriculas (matr_semestre + 1, mismo prog_id, nuevo
    // peri_id elegido por el coordinador); la fila actual pasa a
    // matr_estado = 'cursado' (valor reservado desde c985188). Solo
    // coordinador/admin — nunca automático, siempre una acción deliberada.

    case 'avanzar_semestre':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $matr_id         = (int)($_POST['matr_id'] ?? 0);
        $peri_id_destino = (int)($_POST['peri_id_destino'] ?? 0);
        if ($matr_id === 0 || $peri_id_destino === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Matrícula y período destino son requeridos']);
            break;
        }
        try {
            $pdo = getConexion();

            $stmtActual = $pdo->prepare(
                "SELECT estu_id, prog_id, peri_id, coho_id, matr_semestre, matr_estado, matr_estado_academico
                 FROM matriculas WHERE matr_id = ?"
            );
            $stmtActual->execute([$matr_id]);
            $actual = $stmtActual->fetch();
            if (!$actual) {
                echo json_encode(['status' => 'error', 'message' => 'Matrícula no encontrada']);
                break;
            }

            if ($actual['matr_estado'] !== 'matriculado') {
                echo json_encode(['status' => 'error', 'message' => 'Solo se puede avanzar una matrícula en estado "matriculado".']);
                break;
            }
            if ($actual['matr_estado_academico'] !== 'Activo') {
                echo json_encode(['status' => 'error', 'message' => 'El estudiante no está en condición académica Activa.']);
                break;
            }

            $siguienteSemestre = (int)$actual['matr_semestre'] + 1;
            $errorSemestre = validarSemestrePrograma($pdo, (int)$actual['prog_id'], $siguienteSemestre);
            if ($errorSemestre !== null) {
                echo json_encode(['status' => 'error', 'message' => $errorSemestre]);
                break;
            }

            if ($peri_id_destino === (int)$actual['peri_id']) {
                echo json_encode(['status' => 'error', 'message' => 'El período destino debe ser distinto del período actual.']);
                break;
            }

            // Aprobación del período actual — misma lógica que 'detalle_periodo'
            // (reportes_mdl.php): promedio simple de cali_definitiva de TODOS
            // los módulos de este prog_id+peri_id, solo si todas están
            // pobladas. Recalculada aquí en el momento de escribir, sin
            // confiar en ningún valor recibido por POST ni en la columna
            // aprobado_periodo_actual de listar_matriculados (esa es solo
            // para decidir qué mostrar en el listado, no para autorizar).
            $stmtModulos = $pdo->prepare(
                "SELECT c.cali_definitiva
                 FROM grmoestudiantes ge
                 JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
                 JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
                 LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
                 WHERE ge.estu_id = ? AND gs.prog_id = ? AND gs.peri_id = ?"
            );
            $stmtModulos->execute([$actual['estu_id'], $actual['prog_id'], $actual['peri_id']]);
            $definitivas = array_column($stmtModulos->fetchAll(), 'cali_definitiva');

            $aprobado = false;
            if (count($definitivas) > 0 && !in_array(null, $definitivas, true)) {
                $promedio = array_sum(array_map('floatval', $definitivas)) / count($definitivas);
                $aprobado = ($promedio >= 3.0);
            }
            if (!$aprobado) {
                echo json_encode(['status' => 'error', 'message' => 'El estudiante no ha aprobado el período actual.']);
                break;
            }

            // Mismo criterio que el UNIQUE KEY uq_matr_estu_peri_prog — caso
            // raro pero posible (ya avanzado por otro medio).
            $stmtDup = $pdo->prepare(
                "SELECT matr_id FROM matriculas WHERE estu_id = ? AND prog_id = ? AND peri_id = ?"
            );
            $stmtDup->execute([$actual['estu_id'], $actual['prog_id'], $peri_id_destino]);
            if ($stmtDup->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Ya existe una matrícula de este estudiante en este programa para el período destino.']);
                break;
            }

            $pdo->beginTransaction();

            $stmtCursado = $pdo->prepare("UPDATE matriculas SET matr_estado = 'cursado' WHERE matr_id = ?");
            $stmtCursado->execute([$matr_id]);

            // Campos no heredados (matr_folio, matr_numero, matr_matriculadopor,
            // fechainscripcion, matr_observacion, req_*): se dejan en su
            // NULL/DEFAULT 0 de columna, igual que una matrícula nueva creada
            // por 'matricular' sin esos datos por POST — son datos propios del
            // trámite de ESTE semestre, no algo que tenga sentido heredar de la
            // matrícula anterior. coho_id sí se hereda (misma cohorte de
            // ingreso del estudiante, no cambia entre semestres).
            $stmtNueva = $pdo->prepare(
                "INSERT INTO matriculas
                    (estu_id, prog_id, peri_id, coho_id, matr_estado, matr_estado_academico,
                     matr_semestre, fechamatricula)
                 VALUES (?, ?, ?, ?, 'matriculado', 'Activo', ?, CURDATE())"
            );
            $stmtNueva->execute([
                $actual['estu_id'], $actual['prog_id'], $peri_id_destino, $actual['coho_id'],
                $siguienteSemestre,
            ]);

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'message' => 'Estudiante avanzado al semestre ' . $siguienteSemestre . ' correctamente.']);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al avanzar de semestre']);
        }
        break;

    // ── LINK DE ACTUALIZACIÓN DE DATOS (Fase 2 de 5) ─────────────────────
    // Genera un token público para que el estudiante actualice su ficha sin
    // sesión (Fase 3, todavía sin implementar); el coordinador aprueba o
    // descarta la propuesta desde aquí. Todo restringido a coordinador/admin.

    case 'generar_link_actualizacion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido']);
            break;
        }
        try {
            $pdo = getConexion();

            // Mismo patrón de resolución de "el activo por defecto" ya usado
            // en doc_mdl.php (SELECT peri_id FROM periodos WHERE peri_activo=1).
            $stmtPeriActivo = $pdo->query("SELECT peri_id FROM periodos WHERE peri_activo = 1 LIMIT 1");
            $peri_activo_id = $stmtPeriActivo->fetchColumn();

            // Mismo criterio que separa "Per. Actual" en listar_matriculados:
            // matriculado + Activo + período activo.
            $stmtElegible = $pdo->prepare(
                "SELECT matr_id FROM matriculas
                 WHERE estu_id = ? AND matr_estado = 'matriculado' AND matr_estado_academico = 'Activo'
                   AND peri_id = ?"
            );
            $stmtElegible->execute([$estu_id, $peri_activo_id ?: 0]);
            if (!$stmtElegible->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Solo se puede generar el link para estudiantes activos del período actual']);
                break;
            }

            $pdo->beginTransaction();

            // Invalidación automática: nunca conviven 2 solicitudes activas
            // para el mismo estudiante — generar un link nuevo descarta
            // cualquier solicitud 'generado'/'recibido' previa.
            $stmtInvalidar = $pdo->prepare(
                "UPDATE solicitudes_actualizacion
                 SET soac_estado = 'descartado', soac_resuelto_en = NOW(), soac_resuelto_por = ?
                 WHERE estu_id = ? AND soac_estado IN ('generado','recibido')"
            );
            $stmtInvalidar->execute([$_SESSION['usua_id'], $estu_id]);

            $soac_token = bin2hex(random_bytes(32));

            $stmtInsert = $pdo->prepare(
                "INSERT INTO solicitudes_actualizacion
                    (estu_id, soac_token, soac_generado_en, soac_expira_en, soac_estado)
                 VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 'generado')"
            );
            $stmtInsert->execute([$estu_id, $soac_token]);

            $pdo->commit();

            // Ruta corregida en la Fase 4: la Fase 3 ya implementó el módulo
            // público real bajo app/11_actualizacion_datos/ — la ruta
            // provisional de la Fase 2 (sin el prefijo app_academica_emdb/app/)
            // no apuntaba al archivo real servido por Apache.
            $url = '/app_academica_emdb/app/11_actualizacion_datos/actualizar_view.php?token=' . $soac_token;
            echo json_encode(['status' => 'ok', 'token' => $soac_token, 'url' => $url]);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al generar el link de actualización']);
        }
        break;

    case 'estado_solicitud_actualizacion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido']);
            break;
        }
        try {
            $pdo = getConexion();
            // Sin filtrar por estado — la Fase 4 necesita saber si la ÚLTIMA
            // solicitud fue aprobada/descartada para decidir qué botón mostrar.
            $stmt = $pdo->prepare(
                "SELECT * FROM solicitudes_actualizacion WHERE estu_id = ? ORDER BY soac_id DESC LIMIT 1"
            );
            $stmt->execute([$estu_id]);
            $solicitud = $stmt->fetch();
            // Caso normal (estudiante sin ninguna solicitud todavía): null
            // explícito, no un error.
            echo json_encode(['status' => 'ok', 'data' => $solicitud ?: null]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al consultar la solicitud']);
        }
        break;

    case 'aprobar_actualizacion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $soac_id = (int)($_POST['soac_id'] ?? 0);
        if ($soac_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de solicitud inválido']);
            break;
        }

        // Mapeo columna_soac => [columna_destino, etiqueta legible] — estudiantes.
        // soac_fechanacimiento es la única sin prefijo estu_ (la columna
        // original tampoco lo lleva en estudiantes).
        $mapaEstudiantes = [
            'soac_estu_expedidoen'        => ['estu_expedidoen', 'Expedido en'],
            'soac_estu_nombres'           => ['estu_nombres', 'Nombres'],
            'soac_estu_apellidos'         => ['estu_apellidos', 'Apellidos'],
            'soac_estu_ciudadnac'         => ['estu_ciudadnac', 'Ciudad de nacimiento'],
            'soac_fechanacimiento'        => ['fechanacimiento', 'Fecha de nacimiento'],
            'soac_estu_sexo'              => ['estu_sexo', 'Sexo'],
            'soac_estu_telefono'          => ['estu_telefono', 'Teléfono'],
            'soac_estu_email'             => ['estu_email', 'Correo electrónico'],
            'soac_estu_ocupacion'         => ['estu_ocupacion', 'Ocupación'],
            'soac_estu_direccion'         => ['estu_direccion', 'Dirección'],
            'soac_estu_barrio'            => ['estu_barrio', 'Barrio'],
            'soac_estu_ciudad'            => ['estu_ciudad', 'Ciudad'],
            'soac_estu_estrato'           => ['estu_estrato', 'Estrato'],
            'soac_estu_estadocivil'       => ['estu_estadocivil', 'Estado civil'],
            'soac_estu_eps'               => ['estu_eps', 'EPS'],
            'soac_estu_discapacidad'      => ['estu_discapacidad', 'Discapacidad'],
            'soac_estu_multiculturalidad' => ['estu_multiculturalidad', 'Multiculturalidad'],
        ];

        // Mapeo columna_soac => [columna_destino, etiqueta legible] — fichas_inscripcion.
        $mapaFicha = [
            'soac_ficha_prog_id'             => ['prog_id', 'Programa'],
            'soac_ficha_jornada'             => ['jornada', 'Jornada'],
            'soac_ficha_fechainscripcion'    => ['fechainscripcion', 'Fecha de inscripción'],
            'soac_ficha_padr_vive'           => ['padr_vive', 'Padre vive'],
            'soac_ficha_padr_nombres'        => ['padr_nombres', 'Nombres del padre'],
            'soac_ficha_padr_apellidos'      => ['padr_apellidos', 'Apellidos del padre'],
            'soac_ficha_padr_profesion'      => ['padr_profesion', 'Profesión del padre'],
            'soac_ficha_padr_empresa'        => ['padr_empresa', 'Empresa del padre'],
            'soac_ficha_padr_telefono'       => ['padr_telefono', 'Teléfono del padre'],
            'soac_ficha_padr_direccion'      => ['padr_direccion', 'Dirección del padre'],
            'soac_ficha_padr_barrio'         => ['padr_barrio', 'Barrio del padre'],
            'soac_ficha_padr_ciudad'         => ['padr_ciudad', 'Ciudad del padre'],
            'soac_ficha_madr_vive'           => ['madr_vive', 'Madre vive'],
            'soac_ficha_madr_nombres'        => ['madr_nombres', 'Nombres de la madre'],
            'soac_ficha_madr_apellidos'      => ['madr_apellidos', 'Apellidos de la madre'],
            'soac_ficha_madr_profesion'      => ['madr_profesion', 'Profesión de la madre'],
            'soac_ficha_madr_empresa'        => ['madr_empresa', 'Empresa de la madre'],
            'soac_ficha_madr_telefono'       => ['madr_telefono', 'Teléfono de la madre'],
            'soac_ficha_madr_direccion'      => ['madr_direccion', 'Dirección de la madre'],
            'soac_ficha_madr_barrio'         => ['madr_barrio', 'Barrio de la madre'],
            'soac_ficha_madr_ciudad'         => ['madr_ciudad', 'Ciudad de la madre'],
            'soac_ficha_acud_es'             => ['acud_es', 'Acudiente'],
            'soac_ficha_acud_parentesco'     => ['acud_parentesco', 'Parentesco del acudiente'],
            'soac_ficha_acud_nombres'        => ['acud_nombres', 'Nombres del acudiente'],
            'soac_ficha_acud_apellidos'      => ['acud_apellidos', 'Apellidos del acudiente'],
            'soac_ficha_acud_profesion'      => ['acud_profesion', 'Profesión del acudiente'],
            'soac_ficha_acud_empresa'        => ['acud_empresa', 'Empresa del acudiente'],
            'soac_ficha_acud_telefono'       => ['acud_telefono', 'Teléfono del acudiente'],
            'soac_ficha_acud_direccion'      => ['acud_direccion', 'Dirección del acudiente'],
            'soac_ficha_acud_barrio'         => ['acud_barrio', 'Barrio del acudiente'],
            'soac_ficha_acud_ciudad'         => ['acud_ciudad', 'Ciudad del acudiente'],
            'soac_ficha_estudio_tipo'        => ['estudio_tipo', 'Tipo de estudio'],
            'soac_ficha_estudio_titulo'      => ['estudio_titulo', 'Título obtenido'],
            'soac_ficha_estudio_institucion' => ['estudio_institucion', 'Institución'],
            'soac_ficha_estudio_aniofin'     => ['estudio_aniofin', 'Año de finalización'],
        ];

        try {
            $pdo = getConexion();
            $pdo->beginTransaction();

            $stmtSoac = $pdo->prepare("SELECT * FROM solicitudes_actualizacion WHERE soac_id = ?");
            $stmtSoac->execute([$soac_id]);
            $solicitud = $stmtSoac->fetch();

            if (!$solicitud || $solicitud['soac_estado'] !== 'recibido') {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Solo se puede aprobar una solicitud en estado "recibido"']);
                break;
            }

            $estu_id_int = (int)$solicitud['estu_id'];

            // Columnas explícitas: estu_id/usua_id (identidad/sesión) +
            // las columnas destino de $mapaEstudiantes (estu_email incluida
            // ahí). Evita traer coho_id/estu_activo/estu_foto/estu_origen/
            // fechacreacion, que $estudianteActual nunca usa.
            $columnasEstudiante = array_unique(array_merge(['estu_id', 'usua_id'], array_column($mapaEstudiantes, 0)));
            $stmtEstu = $pdo->prepare("SELECT " . implode(', ', $columnasEstudiante) . " FROM estudiantes WHERE estu_id = ?");
            $stmtEstu->execute([$estu_id_int]);
            $estudianteActual = $stmtEstu->fetch();
            if (!$estudianteActual) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }

            $stmtFicha = $pdo->prepare("SELECT * FROM fichas_inscripcion WHERE estu_id = ?");
            $stmtFicha->execute([$estu_id_int]);
            $fichaActual = $stmtFicha->fetch();

            // Paso c: solo campos soac_* NO NULOS entran al UPDATE; de esos,
            // solo los que además difieren del valor vigente entran a la
            // lista de "campos modificados". Comparación como string para
            // evitar falsos positivos por tipos (PDO devuelve todo como
            // string salvo NULL).
            $camposModificados = [];

            $setEstu  = [];
            $valsEstu = [];
            foreach ($mapaEstudiantes as $colSoac => [$colDestino, $etiqueta]) {
                $valorNuevo = $solicitud[$colSoac];
                if ($valorNuevo === null) continue;

                $valorActual = $estudianteActual[$colDestino] ?? null;
                if ((string)$valorNuevo !== (string)$valorActual) {
                    $camposModificados[] = $etiqueta;
                }
                $setEstu[]  = "$colDestino = ?";
                $valsEstu[] = $valorNuevo;
            }

            $setFicha  = [];
            $valsFicha = [];
            foreach ($mapaFicha as $colSoac => [$colDestino, $etiqueta]) {
                $valorNuevo = $solicitud[$colSoac];
                if ($valorNuevo === null) continue;

                $valorActual = $fichaActual[$colDestino] ?? null;
                if ((string)$valorNuevo !== (string)$valorActual) {
                    $camposModificados[] = $etiqueta;
                }
                $setFicha[]  = "$colDestino = ?";
                $valsFicha[] = $valorNuevo;
            }

            // estudiantes.estu_email tiene su propia UNIQUE KEY (uq_estu_email),
            // independiente de usuarios.usua_email — hay que validarla ANTES del
            // UPDATE genérico de abajo, igual que guardar_completo valida contra
            // ambas tablas. Sin este chequeo, una colisión aquí lanza un
            // PDOException genérico de MySQL antes de llegar siquiera al paso e
            // (sincronizarEmailUsuario), perdiendo el mensaje específico.
            $emailNuevo = $solicitud['soac_estu_email'];
            if ($emailNuevo !== null && $emailNuevo !== $estudianteActual['estu_email']) {
                $checkEmailEstu = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_email = ? AND estu_id != ?");
                $checkEmailEstu->execute([$emailNuevo, $estu_id_int]);
                if ($checkEmailEstu->fetch()) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                    break;
                }
            }

            // Paso d: UPDATE estudiantes con los campos soac_estu_* no nulos.
            if (!empty($setEstu)) {
                $sqlEstu = "UPDATE estudiantes SET " . implode(', ', $setEstu) . " WHERE estu_id = ?";
                $valsEstu[] = $estu_id_int;
                $pdo->prepare($sqlEstu)->execute($valsEstu);
            }

            // Paso d: UPDATE si ya existe fila en fichas_inscripcion, INSERT
            // si no — mismo criterio que guardar_completo.
            if (!empty($setFicha)) {
                if ($fichaActual) {
                    $sqlFicha = "UPDATE fichas_inscripcion SET " . implode(', ', $setFicha) . " WHERE estu_id = ?";
                    $valsFicha[] = $estu_id_int;
                    $pdo->prepare($sqlFicha)->execute($valsFicha);
                } else {
                    $colsFicha = array_map(function ($asignacion) {
                        return explode(' = ', $asignacion)[0];
                    }, $setFicha);
                    $placeholders = implode(', ', array_fill(0, count($colsFicha) + 1, '?'));
                    $sqlFicha = "INSERT INTO fichas_inscripcion (estu_id, " . implode(', ', $colsFicha) . ")
                                 VALUES ($placeholders)";
                    $pdo->prepare($sqlFicha)->execute(array_merge([$estu_id_int], $valsFicha));
                }
            }

            // Paso e: sincroniza usuarios.usua_email si el correo cambió y el
            // estudiante tiene cuenta de acceso — misma función que guardar_completo.
            // $emailNuevo ya se calculó arriba, antes del UPDATE de estudiantes.
            if ($emailNuevo !== null && $emailNuevo !== $estudianteActual['estu_email'] && $estudianteActual['usua_id']) {
                try {
                    sincronizarEmailUsuario($pdo, (int)$estudianteActual['usua_id'], $emailNuevo);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso por otro usuario']);
                    break;
                }
            }

            // Paso f: cierra la solicitud como aprobada, con el registro de
            // qué campos se aplicaron realmente.
            $stmtResolver = $pdo->prepare(
                "UPDATE solicitudes_actualizacion
                 SET soac_estado = 'aprobado', soac_resuelto_en = NOW(), soac_resuelto_por = ?,
                     soac_campos_modificados = ?
                 WHERE soac_id = ?"
            );
            $stmtResolver->execute([$_SESSION['usua_id'], implode(', ', $camposModificados), $soac_id]);

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'campos_aplicados' => $camposModificados]);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al aprobar la actualización']);
        }
        break;

    case 'descartar_actualizacion':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $soac_id = (int)($_POST['soac_id'] ?? 0);
        if ($soac_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de solicitud inválido']);
            break;
        }
        try {
            $pdo = getConexion();

            $stmt = $pdo->prepare("SELECT soac_estado FROM solicitudes_actualizacion WHERE soac_id = ?");
            $stmt->execute([$soac_id]);
            $soac_estado = $stmt->fetchColumn();

            if ($soac_estado === false || !in_array($soac_estado, ['generado', 'recibido'], true)) {
                echo json_encode(['status' => 'error', 'message' => 'Solo se puede descartar una solicitud en estado "generado" o "recibido"']);
                break;
            }

            $stmtUpd = $pdo->prepare(
                "UPDATE solicitudes_actualizacion
                 SET soac_estado = 'descartado', soac_resuelto_en = NOW(), soac_resuelto_por = ?
                 WHERE soac_id = ?"
            );
            $stmtUpd->execute([$_SESSION['usua_id'], $soac_id]);

            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al descartar la solicitud']);
        }
        break;

    case 'subir_foto':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);

        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            $check = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_id = ?");
            $check->execute([$estu_id]);
            if (!$check->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al verificar el estudiante']);
            break;
        }

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'No se recibió ningún archivo válido']);
            break;
        }

        if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
            echo json_encode(['status' => 'error', 'message' => 'La imagen no debe superar 5MB']);
            break;
        }

        $rutaTemporal = $_FILES['foto']['tmp_name'];
        $info = @getimagesize($rutaTemporal);

        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            echo json_encode(['status' => 'error', 'message' => 'El archivo no es una imagen JPEG o PNG válida']);
            break;
        }

        $anchoOriginal = $info[0];
        $altoOriginal  = $info[1];

        $origen = ($info[2] === IMAGETYPE_JPEG)
            ? @imagecreatefromjpeg($rutaTemporal)
            : @imagecreatefrompng($rutaTemporal);

        if ($origen === false) {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo procesar la imagen']);
            break;
        }

        $maxMayor = 500;
        $maxMenor = 400;

        $ladoMayorOriginal = max($anchoOriginal, $altoOriginal);
        $ladoMenorOriginal = min($anchoOriginal, $altoOriginal);
        $escala = min($maxMayor / $ladoMayorOriginal, $maxMenor / $ladoMenorOriginal, 1);

        $anchoFinal = max(1, (int)round($anchoOriginal * $escala));
        $altoFinal  = max(1, (int)round($altoOriginal * $escala));

        $lienzo = imagecreatetruecolor($anchoFinal, $altoFinal);
        $blanco = imagecolorallocate($lienzo, 255, 255, 255);
        imagefill($lienzo, 0, 0, $blanco);
        imagecopyresampled(
            $lienzo, $origen,
            0, 0, 0, 0,
            $anchoFinal, $altoFinal, $anchoOriginal, $altoOriginal
        );

        $nombreArchivo = $estu_id . '.jpg';
        $rutaDestino   = __DIR__ . '/../../uploads/fotos_estudiantes/' . $nombreArchivo;
        $guardado      = imagejpeg($lienzo, $rutaDestino, 80);

        if (!$guardado) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar la imagen procesada']);
            break;
        }

        try {
            $pdo->prepare("UPDATE estudiantes SET estu_foto = ? WHERE estu_id = ?")
                ->execute([$nombreArchivo, $estu_id]);
            echo json_encode(['status' => 'ok', 'foto' => $nombreArchivo]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar la foto en la base de datos']);
        }
        break;

    case 'eliminar_aspirante':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);

        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            // Verificación completa de TODAS las filas del estudiante — antes
            // se usaba fetch() de una sola fila sin ORDER BY, lo que hacía el
            // chequeo no determinista si el estudiante tenía 2+ matrículas
            // (ej. una 'aspirante' de un segundo programa y otra 'matriculado'
            // ya activa). Bloquea si CUALQUIERA no es 'aspirante'.
            $stmtMatr = $pdo->prepare("SELECT matr_id, matr_estado FROM matriculas WHERE estu_id = ?");
            $stmtMatr->execute([$estu_id]);
            $matriculas = $stmtMatr->fetchAll();

            foreach ($matriculas as $mat) {
                if ($mat['matr_estado'] !== 'aspirante') {
                    echo json_encode(['status' => 'error', 'message' => 'Este estudiante ya tiene una matrícula activa, no puede eliminarse como aspirante']);
                    break 2;
                }
            }

            $pdo->beginTransaction();

            // Si llegamos aquí, TODAS las filas (si hay alguna) son
            // 'aspirante' — se borran todas antes del DELETE de estudiantes,
            // no solo una.
            foreach ($matriculas as $mat) {
                $stmtDelMatr = $pdo->prepare("DELETE FROM matriculas WHERE matr_id = ?");
                $stmtDelMatr->execute([$mat['matr_id']]);
            }

            // fichas_inscripcion se elimina sola vía ON DELETE CASCADE.
            // Si existieran calificaciones o grmoestudiantes inesperados, el
            // RESTRICT de calificaciones hace fallar esta sentencia — se captura abajo.
            $stmtDelEstu = $pdo->prepare("DELETE FROM estudiantes WHERE estu_id = ? AND estu_activo = 1");
            $stmtDelEstu->execute([$estu_id]);

            if ($stmtDelEstu->rowCount() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Aspirante no encontrado']);
                break;
            }

            $pdo->commit();
            echo json_encode(['status' => 'ok']);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar: existen datos asociados al estudiante']);
        }
        break;

    // ── Fase C1: endpoint transaccional único (estudiante + ficha familiar) ──
    // Los cases 'guardar'/'guardar_ficha'/'obtener'/'obtener_ficha' de arriba
    // quedan sin uso desde el frontend (código muerto, limpieza en fase
    // posterior) pero no se tocan — 'guardar_completo'/'obtener_completo' son
    // independientes y no los invocan.
    case 'guardar_completo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }

        // --- Datos del estudiante (mismos campos que 'guardar') ---
        $estu_id         = trim($_POST['estu_id'] ?? '');
        $estu_tipodoc    = trim($_POST['estu_tipodoc'] ?? '');
        $estu_numerodoc  = str_replace('.', '', trim($_POST['estu_numerodoc'] ?? ''));
        $estu_nombres    = strtoupper(trim($_POST['estu_nombres'] ?? ''));
        $estu_apellidos  = strtoupper(trim($_POST['estu_apellidos'] ?? ''));
        $fechanacimiento = trim($_POST['fechanacimiento'] ?? '');
        $estu_sexo       = trim($_POST['estu_sexo'] ?? '');
        $estu_telefono   = trim($_POST['estu_telefono'] ?? '');
        $estu_email      = trim($_POST['estu_email'] ?? '');
        $estu_ciudad     = strtoupper(trim($_POST['estu_ciudad'] ?? ''));
        $estu_direccion  = strtoupper(trim($_POST['estu_direccion'] ?? ''));
        $estu_barrio     = strtoupper(trim($_POST['estu_barrio'] ?? ''));
        $estu_estrato    = trim($_POST['estu_estrato'] ?? '');
        $estu_eps        = strtoupper(trim($_POST['estu_eps'] ?? ''));
        $estu_expedidoen        = strtoupper(trim($_POST['estu_expedidoen'] ?? ''));
        $estu_ciudadnac         = strtoupper(trim($_POST['estu_ciudadnac'] ?? ''));
        $estu_ocupacion         = strtoupper(trim($_POST['estu_ocupacion'] ?? ''));
        $estu_estadocivil       = trim($_POST['estu_estadocivil'] ?? '');
        $estu_discapacidad      = trim($_POST['estu_discapacidad'] ?? '');
        $estu_multiculturalidad = trim($_POST['estu_multiculturalidad'] ?? '');
        $tipo_clave_estudiante   = trim($_POST['tipo_clave_estudiante'] ?? '');
        $clave_manual_estudiante = trim($_POST['clave_manual_estudiante'] ?? '');

        if ($estu_tipodoc === '' || $estu_numerodoc === '' || $estu_nombres === '' || $estu_apellidos === ''
            || $fechanacimiento === '' || $estu_sexo === '' || $estu_telefono === '' || $estu_email === ''
            || $estu_ciudad === '' || $estu_direccion === '' || $estu_barrio === '' || $estu_estrato === ''
            || $estu_eps === '' || $estu_expedidoen === '' || $estu_ciudadnac === '' || $estu_ocupacion === ''
            || $estu_estadocivil === '' || $estu_discapacidad === '' || $estu_multiculturalidad === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos del estudiante son requeridos']);
            break;
        }

        if (!filter_var($estu_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
            break;
        }

        // --- Datos de la ficha familiar (mismos campos que 'guardar_ficha') ---
        $prog_id          = (int)($_POST['prog_id'] ?? 0) ?: null;
        $jornada          = strtoupper(trim($_POST['jornada'] ?? '')) ?: null;
        $fechainscripcion = trim($_POST['fechainscripcion'] ?? '') ?: null;

        $padr_vive      = (int)($_POST['padr_vive'] ?? 0);
        $padr_nombres   = strtoupper(trim($_POST['padr_nombres'] ?? '')) ?: null;
        $padr_apellidos = strtoupper(trim($_POST['padr_apellidos'] ?? '')) ?: null;
        $padr_profesion = strtoupper(trim($_POST['padr_profesion'] ?? '')) ?: null;
        $padr_empresa   = strtoupper(trim($_POST['padr_empresa'] ?? '')) ?: null;
        $padr_telefono  = trim($_POST['padr_telefono'] ?? '') ?: null;
        $padr_direccion = strtoupper(trim($_POST['padr_direccion'] ?? '')) ?: null;
        $padr_barrio    = strtoupper(trim($_POST['padr_barrio'] ?? '')) ?: null;
        $padr_ciudad    = strtoupper(trim($_POST['padr_ciudad'] ?? '')) ?: null;

        $madr_vive      = (int)($_POST['madr_vive'] ?? 0);
        $madr_nombres   = strtoupper(trim($_POST['madr_nombres'] ?? '')) ?: null;
        $madr_apellidos = strtoupper(trim($_POST['madr_apellidos'] ?? '')) ?: null;
        $madr_profesion = strtoupper(trim($_POST['madr_profesion'] ?? '')) ?: null;
        $madr_empresa   = strtoupper(trim($_POST['madr_empresa'] ?? '')) ?: null;
        $madr_telefono  = trim($_POST['madr_telefono'] ?? '') ?: null;
        $madr_direccion = strtoupper(trim($_POST['madr_direccion'] ?? '')) ?: null;
        $madr_barrio    = strtoupper(trim($_POST['madr_barrio'] ?? '')) ?: null;
        $madr_ciudad    = strtoupper(trim($_POST['madr_ciudad'] ?? '')) ?: null;

        $acud_es         = trim($_POST['acud_es'] ?? '') ?: null;
        $acud_parentesco = strtoupper(trim($_POST['acud_parentesco'] ?? '')) ?: null;
        $acud_nombres    = strtoupper(trim($_POST['acud_nombres'] ?? '')) ?: null;
        $acud_apellidos  = strtoupper(trim($_POST['acud_apellidos'] ?? '')) ?: null;
        $acud_profesion  = strtoupper(trim($_POST['acud_profesion'] ?? '')) ?: null;
        $acud_empresa    = strtoupper(trim($_POST['acud_empresa'] ?? '')) ?: null;
        $acud_telefono   = trim($_POST['acud_telefono'] ?? '') ?: null;
        $acud_direccion  = strtoupper(trim($_POST['acud_direccion'] ?? '')) ?: null;
        $acud_barrio     = strtoupper(trim($_POST['acud_barrio'] ?? '')) ?: null;
        $acud_ciudad     = strtoupper(trim($_POST['acud_ciudad'] ?? '')) ?: null;

        $estudio_tipo        = strtoupper(trim($_POST['estudio_tipo'] ?? '')) ?: null;
        $estudio_titulo      = strtoupper(trim($_POST['estudio_titulo'] ?? '')) ?: null;
        $estudio_institucion = strtoupper(trim($_POST['estudio_institucion'] ?? '')) ?: null;
        $estudio_aniofin     = trim($_POST['estudio_aniofin'] ?? '') ?: null;

        // Ficha Familiar obligatoria siempre — sin excepción por modo
        // creación/edición (decisión confirmada para la Fase C).
        $camposFichaRequeridos = [
            $prog_id, $jornada, $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos,
            $acud_profesion, $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
            $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
        ];
        if ($padr_vive === 1) {
            $camposFichaRequeridos = array_merge($camposFichaRequeridos, [
                $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
            ]);
        }
        if ($madr_vive === 1) {
            $camposFichaRequeridos = array_merge($camposFichaRequeridos, [
                $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
            ]);
        }
        $campoFichaFaltante = false;
        foreach ($camposFichaRequeridos as $valor) {
            if ($valor === null) {
                $campoFichaFaltante = true;
                break;
            }
        }
        if ($campoFichaFaltante) {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos de la ficha familiar son obligatorios']);
            break;
        }

        try {
            $pdo = getConexion();
            $clave_generada_estudiante = null;

            if ($estu_id === '') {
                // ---- Crear aspirante nuevo ----
                $check = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_numerodoc = ?");
                $check->execute([$estu_numerodoc]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El número de documento ya está registrado']);
                    break;
                }

                $checkEmailEstu = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_email = ?");
                $checkEmailEstu->execute([$estu_email]);
                if ($checkEmailEstu->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                    break;
                }
                $checkEmailUsua = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ?");
                $checkEmailUsua->execute([$estu_email]);
                if ($checkEmailUsua->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                    break;
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO estudiantes
                        (estu_tipodoc, estu_numerodoc, estu_nombres, estu_apellidos,
                         fechanacimiento, estu_sexo, estu_telefono, estu_email,
                         estu_ciudad, estu_direccion, estu_barrio, estu_estrato, estu_eps,
                         estu_expedidoen, estu_ciudadnac, estu_ocupacion,
                         estu_estadocivil, estu_discapacidad, estu_multiculturalidad, estu_origen)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $estu_tipodoc, $estu_numerodoc, $estu_nombres, $estu_apellidos,
                    $fechanacimiento, $estu_sexo, $estu_telefono, $estu_email,
                    $estu_ciudad, $estu_direccion, $estu_barrio, $estu_estrato, $estu_eps,
                    $estu_expedidoen, $estu_ciudadnac, $estu_ocupacion,
                    $estu_estadocivil, $estu_discapacidad, $estu_multiculturalidad, 'manual'
                ]);

                $estu_id_int = (int)$pdo->lastInsertId();

            } else {
                // ---- Editar estudiante existente ----
                $estu_id_int = (int)$estu_id;

                $check = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_numerodoc = ? AND estu_id != ?");
                $check->execute([$estu_numerodoc, $estu_id_int]);
                if ($check->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'El número de documento ya está registrado por otro estudiante']);
                    break;
                }

                $checkEmailEstu = $pdo->prepare("SELECT estu_id FROM estudiantes WHERE estu_email = ? AND estu_id != ?");
                $checkEmailEstu->execute([$estu_email, $estu_id_int]);
                if ($checkEmailEstu->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                    break;
                }

                // estu_email también se lee aquí (antes de este UPDATE) para
                // poder comparar el valor VIGENTE contra el nuevo más abajo,
                // sin depender de lo que ya se sobrescribió.
                $stmtUsuaPropio = $pdo->prepare("SELECT usua_id, estu_email FROM estudiantes WHERE estu_id = ?");
                $stmtUsuaPropio->execute([$estu_id_int]);
                $rowPropio = $stmtUsuaPropio->fetch();
                $usua_id_propio = $rowPropio['usua_id'];
                $estu_email_anterior = $rowPropio['estu_email'];

                if ($usua_id_propio) {
                    $checkEmailUsua = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ? AND usua_id != ?");
                    $checkEmailUsua->execute([$estu_email, $usua_id_propio]);
                } else {
                    $checkEmailUsua = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ?");
                    $checkEmailUsua->execute([$estu_email]);
                }
                if ($checkEmailUsua->fetch()) {
                    echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                    break;
                }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "UPDATE estudiantes
                     SET estu_tipodoc = ?, estu_numerodoc = ?, estu_nombres = ?, estu_apellidos = ?,
                         fechanacimiento = ?, estu_sexo = ?, estu_telefono = ?, estu_email = ?,
                         estu_ciudad = ?, estu_direccion = ?, estu_barrio = ?, estu_estrato = ?, estu_eps = ?,
                         estu_expedidoen = ?, estu_ciudadnac = ?, estu_ocupacion = ?,
                         estu_estadocivil = ?, estu_discapacidad = ?, estu_multiculturalidad = ?
                     WHERE estu_id = ?"
                );
                $stmt->execute([
                    $estu_tipodoc, $estu_numerodoc, $estu_nombres, $estu_apellidos,
                    $fechanacimiento, $estu_sexo, $estu_telefono, $estu_email,
                    $estu_ciudad, $estu_direccion, $estu_barrio, $estu_estrato, $estu_eps,
                    $estu_expedidoen, $estu_ciudadnac, $estu_ocupacion,
                    $estu_estadocivil, $estu_discapacidad, $estu_multiculturalidad,
                    $estu_id_int
                ]);

                // Sincroniza usuarios.usua_email si el correo cambió y el
                // estudiante ya tiene cuenta de acceso — antes este UPDATE
                // solo tocaba estudiantes.estu_email, dejando usuarios.usua_email
                // desincronizado (hallazgo documentado en el diagnóstico de la
                // Fase 1 del link de actualización de datos).
                if ($usua_id_propio && $estu_email !== $estu_email_anterior) {
                    try {
                        sincronizarEmailUsuario($pdo, (int)$usua_id_propio, $estu_email);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['status' => 'error', 'message' => 'Este correo electrónico ya está en uso']);
                        break;
                    }
                }

                // Cambio opcional de clave — solo si el estudiante ya tiene acceso
                // creado (usua_id) y se envió una intención de cambio de clave.
                if ($tipo_clave_estudiante !== '') {
                    $usua_id_actual = $usua_id_propio;

                    if ($usua_id_actual) {
                        if ($tipo_clave_estudiante === 'manual' && $clave_manual_estudiante === '') {
                            $pdo->rollBack();
                            echo json_encode(['status' => 'error', 'message' => 'Ingrese la clave manual']);
                            break;
                        }
                        $clave_generada_estudiante = ($tipo_clave_estudiante === 'automatica')
                            ? generarClaveAuto($estu_apellidos, $fechanacimiento)
                            : $clave_manual_estudiante;

                        $hash = password_hash($clave_generada_estudiante, PASSWORD_BCRYPT);
                        $stmtPass = $pdo->prepare("UPDATE usuarios SET usua_passwordhash = ? WHERE usua_id = ?");
                        $stmtPass->execute([$hash, $usua_id_actual]);
                    }
                }
            }

            // ---- Ficha Familiar: mismo patrón SELECT-previo de 'guardar_ficha',
            // ahora dentro de la misma transacción que el paso anterior ----
            $checkFicha = $pdo->prepare("SELECT finc_id FROM fichas_inscripcion WHERE estu_id = ?");
            $checkFicha->execute([$estu_id_int]);
            $fichaExistente = $checkFicha->fetch();

            if ($fichaExistente) {
                $stmtFicha = $pdo->prepare(
                    "UPDATE fichas_inscripcion
                     SET prog_id = ?, jornada = ?, fechainscripcion = ?,
                         padr_vive = ?, padr_nombres = ?, padr_apellidos = ?, padr_profesion = ?, padr_empresa = ?,
                         padr_telefono = ?, padr_direccion = ?, padr_barrio = ?, padr_ciudad = ?,
                         madr_vive = ?, madr_nombres = ?, madr_apellidos = ?, madr_profesion = ?, madr_empresa = ?,
                         madr_telefono = ?, madr_direccion = ?, madr_barrio = ?, madr_ciudad = ?,
                         acud_es = ?, acud_parentesco = ?, acud_nombres = ?, acud_apellidos = ?, acud_profesion = ?,
                         acud_empresa = ?, acud_telefono = ?, acud_direccion = ?, acud_barrio = ?, acud_ciudad = ?,
                         estudio_tipo = ?, estudio_titulo = ?, estudio_institucion = ?, estudio_aniofin = ?
                     WHERE estu_id = ?"
                );
                $stmtFicha->execute([
                    $prog_id, $jornada, $fechainscripcion,
                    $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                    $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                    $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                    $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                    $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                    $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                    $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
                    $estu_id_int
                ]);
            } else {
                $stmtFicha = $pdo->prepare(
                    "INSERT INTO fichas_inscripcion
                        (estu_id, prog_id, jornada, fechainscripcion,
                         padr_vive, padr_nombres, padr_apellidos, padr_profesion, padr_empresa,
                         padr_telefono, padr_direccion, padr_barrio, padr_ciudad,
                         madr_vive, madr_nombres, madr_apellidos, madr_profesion, madr_empresa,
                         madr_telefono, madr_direccion, madr_barrio, madr_ciudad,
                         acud_es, acud_parentesco, acud_nombres, acud_apellidos, acud_profesion,
                         acud_empresa, acud_telefono, acud_direccion, acud_barrio, acud_ciudad,
                         estudio_tipo, estudio_titulo, estudio_institucion, estudio_aniofin)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmtFicha->execute([
                    $estu_id_int, $prog_id, $jornada, $fechainscripcion,
                    $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                    $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                    $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                    $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                    $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                    $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                    $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin
                ]);
            }

            $pdo->commit();
            $response = ['status' => 'ok', 'estu_id' => $estu_id_int];
            if ($clave_generada_estudiante !== null) {
                $response['clave_generada'] = $clave_generada_estudiante;
            }
            echo json_encode($response);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar los datos del estudiante y la ficha']);
        }
        break;

    case 'obtener_completo':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
        $estu_id = (int)($_POST['estu_id'] ?? 0);

        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            break;
        }

        try {
            $pdo = getConexion();
            // LEFT JOIN — mismo criterio que pdf_ficha.php: tolera estudiantes
            // sin fila aún en fichas_inscripcion (registros creados antes de
            // que la Ficha Familiar fuera obligatoria).
            $stmt = $pdo->prepare(
                "SELECT e.estu_id, e.estu_tipodoc, e.estu_numerodoc, e.estu_nombres, e.estu_apellidos,
                        e.fechanacimiento, e.estu_sexo, e.estu_telefono, e.estu_email,
                        e.estu_ciudad, e.estu_direccion, e.estu_barrio, e.estu_estrato, e.estu_eps, e.estu_foto,
                        e.estu_expedidoen, e.estu_ciudadnac, e.estu_ocupacion,
                        e.estu_estadocivil, e.estu_discapacidad, e.estu_multiculturalidad, e.usua_id,
                        fi.prog_id, fi.jornada, fi.fechainscripcion,
                        fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
                        fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
                        fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
                        fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
                        fi.acud_es, fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion,
                        fi.acud_empresa, fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
                        fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin,
                        m.matr_estado, m.matr_semestre
                 FROM estudiantes e
                 LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
                 LEFT JOIN matriculas m ON m.estu_id = e.estu_id
                 WHERE e.estu_id = ?
                 ORDER BY m.matr_id DESC
                 LIMIT 1"
            );
            $stmt->execute([$estu_id]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }

            $row['estado_ficha'] = calcularEstadoFicha($row);

            // Todas las matrículas del estudiante (sin importar estado), para
            // la sección "Programas matriculados" del modal — no reemplaza
            // m.matr_estado de arriba (sigue siendo la más reciente vía
            // LIMIT 1, usada tal cual por el botón de Hoja de Matrícula).
            $stmtMatrs = $pdo->prepare(
                "SELECT m.matr_id, m.prog_id, p.prog_nombre, p.prog_sigla,
                        m.peri_id, pe.peri_codigo,
                        m.matr_estado, m.matr_estado_academico, m.fechamatricula
                 FROM matriculas m
                 INNER JOIN programas p ON m.prog_id = p.prog_id
                 INNER JOIN periodos pe ON m.peri_id = pe.peri_id
                 WHERE m.estu_id = ?
                 ORDER BY m.matr_id ASC"
            );
            $stmtMatrs->execute([$estu_id]);
            $row['matriculas'] = $stmtMatrs->fetchAll();

            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener los datos del estudiante']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida']);
        break;
}

function generarClaveAuto($apellido, $fechanacimiento) {
    $map = [
        'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U',
        'ñ'=>'N','Ñ'=>'N','ü'=>'U','Ü'=>'U'
    ];
    $normalizado = strtr(strtoupper($apellido ?? ''), $map);
    $normalizado = preg_replace('/[^A-Z]/', '', $normalizado);
    $prefijo = str_pad(substr($normalizado, 0, 4), 4, 'X');
    $anio    = $fechanacimiento ? date('Y', strtotime($fechanacimiento)) : date('Y');
    return $prefijo . $anio;
}
