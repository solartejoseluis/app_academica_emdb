<?php
session_start();
require_once '../00_connect/pdo.php';

$accion = $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar_aspirantes':
        try {
            $pdo = getConexion();
            $sql = "SELECT e.estu_id, e.estu_nombres, e.estu_apellidos,
                           e.estu_tipodoc, e.estu_numerodoc, e.estu_telefono,
                           e.fechacreacion
                    FROM estudiantes e
                    LEFT JOIN matriculas m ON e.estu_id = m.estu_id
                    WHERE e.estu_activo = 1
                      AND (m.matr_estado = 'aspirante' OR m.matr_id IS NULL)
                    ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar aspirantes']);
        }
        break;

    case 'listar_matriculados':
        try {
            $pdo = getConexion();
            $sql = "SELECT e.estu_id, e.estu_nombres, e.estu_apellidos,
                           e.estu_tipodoc, e.estu_numerodoc,
                           p.prog_sigla, pe.peri_codigo, m.matr_estado, m.matr_id
                    FROM estudiantes e
                    INNER JOIN matriculas m ON e.estu_id = m.estu_id
                    INNER JOIN programas p ON m.prog_id = p.prog_id
                    INNER JOIN periodos pe ON m.peri_id = pe.peri_id
                    WHERE e.estu_activo = 1
                      AND m.matr_estado = 'matriculado'
                    ORDER BY e.estu_apellidos ASC, e.estu_nombres ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['status' => 'ok', 'data' => $rows]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al listar matriculados']);
        }
        break;

    case 'listar_programas':
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
        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare("SELECT coho_id, coho_codigo FROM cohortes WHERE coho_activa = 1 ORDER BY coho_codigo DESC");
            $stmt->execute();
            echo json_encode(['status' => 'ok', 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al cargar cohortes']);
        }
        break;

    case 'guardar':
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

        if ($estu_tipodoc === '' || $estu_numerodoc === '' || $estu_nombres === '' || $estu_apellidos === ''
            || $fechanacimiento === '' || $estu_sexo === '' || $estu_telefono === '' || $estu_email === ''
            || $estu_ciudad === '' || $estu_direccion === '' || $estu_barrio === '' || $estu_estrato === ''
            || $estu_eps === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos son requeridos']);
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

                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "INSERT INTO estudiantes
                        (estu_tipodoc, estu_numerodoc, estu_nombres, estu_apellidos,
                         fechanacimiento, estu_sexo, estu_telefono, estu_email,
                         estu_ciudad, estu_direccion, estu_barrio, estu_estrato, estu_eps)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $estu_tipodoc, $estu_numerodoc, $estu_nombres, $estu_apellidos,
                    $fechanacimiento, $estu_sexo, $estu_telefono, $estu_email,
                    $estu_ciudad, $estu_direccion, $estu_barrio, $estu_estrato, $estu_eps
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

                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    "UPDATE estudiantes
                     SET estu_tipodoc = ?, estu_numerodoc = ?, estu_nombres = ?, estu_apellidos = ?,
                         fechanacimiento = ?, estu_sexo = ?, estu_telefono = ?, estu_email = ?,
                         estu_ciudad = ?, estu_direccion = ?, estu_barrio = ?, estu_estrato = ?, estu_eps = ?
                     WHERE estu_id = ?"
                );
                $stmt->execute([
                    $estu_tipodoc, $estu_numerodoc, $estu_nombres, $estu_apellidos,
                    $fechanacimiento, $estu_sexo, $estu_telefono, $estu_email,
                    $estu_ciudad, $estu_direccion, $estu_barrio, $estu_estrato, $estu_eps,
                    $estu_id_int
                ]);
                $pdo->commit();
                echo json_encode(['status' => 'ok', 'rows' => 1]);
            }

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar el estudiante']);
        }
        break;

    case 'obtener':
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
                        estu_ciudad, estu_direccion, estu_barrio, estu_estrato, estu_eps
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

    case 'matricular':
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
                "SELECT estu_numerodoc, estu_apellidos, fechanacimiento, usua_id
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
                $usua_email  = $numerodoc . '@emdb.local';

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
            }
            echo json_encode($response);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al procesar la matrícula']);
        }
        break;

    case 'obtener_ficha':
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
