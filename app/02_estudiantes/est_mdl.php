<?php
session_start();
require_once '../00_connect/pdo.php';

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
                            WHERE ge3.estu_id = e.estu_id AND gm3.grmo_activo = 1{$condicionPeriodoModulos}) AS total_modulos,
                           p.prog_sigla, pe.peri_codigo, m.matr_estado, m.matr_id,
                           fi.prog_id, fi.jornada,
                           fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
                           fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
                           fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
                           fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
                           fi.acud_es, fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion,
                           fi.acud_empresa, fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
                           fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin
                    FROM estudiantes e
                    INNER JOIN matriculas m ON e.estu_id = m.estu_id
                    INNER JOIN programas p ON m.prog_id = p.prog_id
                    INNER JOIN periodos pe ON m.peri_id = pe.peri_id
                    LEFT JOIN cohortes co ON e.coho_id = co.coho_id
                    LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
                    {$where}
                    ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$row) {
                $row['estado_ficha'] = calcularEstadoFicha($row);
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
            $stmt = $pdo->prepare("SELECT prog_id, prog_nombre, prog_sigla FROM programas ORDER BY prog_nombre ASC");
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
            $stmt = $pdo->prepare("SELECT peri_id, peri_codigo FROM periodos ORDER BY peri_anio DESC, peri_semestre DESC");
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
        if ($estu_id === 0) {
            echo json_encode(['status' => 'error', 'message' => 'ID de estudiante inválido', 'data' => []]);
            break;
        }
        try {
            $pdo = getConexion();
            $params = [$estu_id];
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
                WHERE ge.estu_id = ? AND gm.grmo_activo = 1{$condicionPeriodo}
                ORDER BY pe.peri_codigo DESC, m.modu_orden
            ");
            $stmt->execute($params);
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
        }
        break;

    case 'guardar':
        if (!isset($_SESSION['usua_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
            break;
        }
        $role_id = (int)($_SESSION['role_id'] ?? 0);
        if (!in_array($role_id, [1, 2], true)) {
            echo json_encode(['status' => 'error', 'message' => 'Sin autorización']);
            break;
        }
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
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
            break;
        }

        if (!filter_var($estu_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
            break;
        }

        try {
            $pdo = getConexion();

            if ($estu_id === '') {
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
                $pdo->commit();
                echo json_encode(['status' => 'ok', 'rows' => 1]);

            } else {
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

                $stmtUsuaPropio = $pdo->prepare("SELECT usua_id FROM estudiantes WHERE estu_id = ?");
                $stmtUsuaPropio->execute([$estu_id_int]);
                $usua_id_propio = $stmtUsuaPropio->fetchColumn();

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

                // Cambio opcional de clave — solo si el estudiante ya tiene acceso
                // creado (usua_id) y se envió una intención de cambio de clave.
                $clave_generada_estudiante = null;
                if ($tipo_clave_estudiante !== '') {
                    $stmtUsua = $pdo->prepare("SELECT usua_id FROM estudiantes WHERE estu_id = ?");
                    $stmtUsua->execute([$estu_id_int]);
                    $usua_id_actual = $stmtUsua->fetchColumn();

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

                $pdo->commit();
                $response = ['status' => 'ok', 'rows' => 1];
                if ($clave_generada_estudiante !== null) {
                    $response['clave_generada'] = $clave_generada_estudiante;
                }
                echo json_encode($response);
            }

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar el estudiante']);
        }
        break;

    case 'obtener':
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
            $stmt = $pdo->prepare(
                "SELECT estu_id, estu_tipodoc, estu_numerodoc, estu_nombres, estu_apellidos,
                        fechanacimiento, estu_sexo, estu_telefono, estu_email,
                        estu_ciudad, estu_direccion, estu_barrio, estu_estrato, estu_eps, estu_foto,
                        estu_expedidoen, estu_ciudadnac, estu_ocupacion,
                        estu_estadocivil, estu_discapacidad, estu_multiculturalidad, usua_id
                 FROM estudiantes
                 WHERE estu_id = ?"
            );
            $stmt->execute([$estu_id]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }

            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener el estudiante']);
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

        $coho_id     = (int)($_POST['coho_id'] ?? 0) ?: null;
        $matr_folio  = trim($_POST['matr_folio'] ?? '') ?: null;
        $matr_numero = trim($_POST['matr_numero'] ?? '') ?: null;

        $req = [
            'req_copiadiploma'  => (int)($_POST['req_copiadiploma'] ?? 0),
            'req_actagrado'     => (int)($_POST['req_actagrado'] ?? 0),
            'req_documento'     => (int)($_POST['req_documento'] ?? 0),
            'req_carnetsalud'   => (int)($_POST['req_carnetsalud'] ?? 0),
            'req_examenmedico'  => (int)($_POST['req_examenmedico'] ?? 0),
            'req_fotos'         => (int)($_POST['req_fotos'] ?? 0),
            'req_carpeta'       => (int)($_POST['req_carpeta'] ?? 0),
            'req_vacunastetano' => (int)($_POST['req_vacunastetano'] ?? 0),
            'req_hepatitisb'    => (int)($_POST['req_hepatitisb'] ?? 0),
        ];

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
                     SET matr_estado = 'matriculado', matr_folio = ?, matr_numero = ?,
                         fechamatricula = CURDATE(),
                         req_copiadiploma = ?, req_actagrado = ?, req_documento = ?,
                         req_carnetsalud = ?, req_examenmedico = ?, req_fotos = ?,
                         req_carpeta = ?, req_vacunastetano = ?, req_hepatitisb = ?
                     WHERE matr_id = ?"
                );
                $stmtM->execute([
                    $matr_folio, $matr_numero,
                    $req['req_copiadiploma'], $req['req_actagrado'], $req['req_documento'],
                    $req['req_carnetsalud'], $req['req_examenmedico'], $req['req_fotos'],
                    $req['req_carpeta'], $req['req_vacunastetano'], $req['req_hepatitisb'],
                    $existingMatr['matr_id']
                ]);
            } else {
                $stmtM = $pdo->prepare(
                    "INSERT INTO matriculas
                        (estu_id, prog_id, peri_id, matr_estado, matr_folio, matr_numero,
                         fechainscripcion, fechamatricula,
                         req_copiadiploma, req_actagrado, req_documento,
                         req_carnetsalud, req_examenmedico, req_fotos,
                         req_carpeta, req_vacunastetano, req_hepatitisb)
                     VALUES (?, ?, ?, 'matriculado', ?, ?, CURDATE(), CURDATE(),
                             ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmtM->execute([
                    $estu_id, $prog_id, $peri_id,
                    $matr_folio, $matr_numero,
                    $req['req_copiadiploma'], $req['req_actagrado'], $req['req_documento'],
                    $req['req_carnetsalud'], $req['req_examenmedico'], $req['req_fotos'],
                    $req['req_carpeta'], $req['req_vacunastetano'], $req['req_hepatitisb']
                ]);
            }

            if ($coho_id !== null) {
                $stmtCoho = $pdo->prepare("UPDATE estudiantes SET coho_id = ? WHERE estu_id = ?");
                $stmtCoho->execute([$coho_id, $estu_id]);
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
                SELECT m.matr_id, m.prog_id, m.peri_id, m.estu_id,
                       e.coho_id, e.estu_nombres, e.estu_apellidos
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
        $matr_id = (int)($_POST['matr_id'] ?? 0);
        $prog_id = (int)($_POST['prog_id'] ?? 0);
        $peri_id = (int)($_POST['peri_id'] ?? 0);
        $coho_id = (int)($_POST['coho_id'] ?? 0);
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

            $stmtM = $pdo->prepare("UPDATE matriculas SET prog_id = ?, peri_id = ? WHERE matr_id = ?");
            $stmtM->execute([$prog_id, $peri_id, $matr_id]);

            $stmtE = $pdo->prepare("UPDATE estudiantes SET coho_id = ? WHERE estu_id = ?");
            $stmtE->execute([$coho_id, $estu_id]);

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

    case 'obtener_ficha':
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
            $stmt = $pdo->prepare(
                "SELECT estu_id, prog_id, jornada, fechainscripcion,
                        padr_vive, padr_nombres, padr_apellidos, padr_profesion, padr_empresa,
                        padr_telefono, padr_direccion, padr_barrio, padr_ciudad,
                        madr_vive, madr_nombres, madr_apellidos, madr_profesion, madr_empresa,
                        madr_telefono, madr_direccion, madr_barrio, madr_ciudad,
                        acud_es, acud_parentesco, acud_nombres, acud_apellidos, acud_profesion,
                        acud_empresa, acud_telefono, acud_direccion, acud_barrio, acud_ciudad,
                        estudio_tipo, estudio_titulo, estudio_institucion, estudio_aniofin
                 FROM fichas_inscripcion
                 WHERE estu_id = ?"
            );
            $stmt->execute([$estu_id]);
            $row = $stmt->fetch();

            echo json_encode(['status' => 'ok', 'data' => $row ?: null]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al obtener la ficha']);
        }
        break;

    case 'guardar_ficha':
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

        $camposRequeridos = [
            $prog_id, $jornada, $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos,
            $acud_profesion, $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
            $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
        ];
        if ($padr_vive === 1) {
            $camposRequeridos = array_merge($camposRequeridos, [
                $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
            ]);
        }
        if ($madr_vive === 1) {
            $camposRequeridos = array_merge($camposRequeridos, [
                $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
            ]);
        }
        $campoFaltante = false;
        foreach ($camposRequeridos as $valor) {
            if ($valor === null) {
                $campoFaltante = true;
                break;
            }
        }
        if ($campoFaltante) {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos de la ficha familiar son obligatorios']);
            break;
        }

        try {
            $pdo = getConexion();

            $check = $pdo->prepare("SELECT finc_id FROM fichas_inscripcion WHERE estu_id = ?");
            $check->execute([$estu_id]);
            $existente = $check->fetch();

            $pdo->beginTransaction();

            if ($existente) {
                $stmt = $pdo->prepare(
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
                $stmt->execute([
                    $prog_id, $jornada, $fechainscripcion,
                    $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                    $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                    $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                    $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                    $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                    $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                    $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
                    $estu_id
                ]);
            } else {
                $stmt = $pdo->prepare(
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
                $stmt->execute([
                    $estu_id, $prog_id, $jornada, $fechainscripcion,
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
            echo json_encode(['status' => 'ok', 'rows' => 1]);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar la ficha']);
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

            $stmtMatr = $pdo->prepare("SELECT matr_id, matr_estado FROM matriculas WHERE estu_id = ?");
            $stmtMatr->execute([$estu_id]);
            $matricula = $stmtMatr->fetch();

            if ($matricula && $matricula['matr_estado'] !== 'aspirante') {
                echo json_encode(['status' => 'error', 'message' => 'Este estudiante ya tiene una matrícula activa, no puede eliminarse como aspirante']);
                break;
            }

            $pdo->beginTransaction();

            if ($matricula) {
                $stmtDelMatr = $pdo->prepare("DELETE FROM matriculas WHERE matr_id = ?");
                $stmtDelMatr->execute([$matricula['matr_id']]);
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

                $stmtUsuaPropio = $pdo->prepare("SELECT usua_id FROM estudiantes WHERE estu_id = ?");
                $stmtUsuaPropio->execute([$estu_id_int]);
                $usua_id_propio = $stmtUsuaPropio->fetchColumn();

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
                        fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin
                 FROM estudiantes e
                 LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
                 WHERE e.estu_id = ?"
            );
            $stmt->execute([$estu_id]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
                break;
            }

            $row['estado_ficha'] = calcularEstadoFicha($row);

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
