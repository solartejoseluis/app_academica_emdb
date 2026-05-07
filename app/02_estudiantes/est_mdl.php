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
        $estu_id        = trim($_POST['estu_id'] ?? '');
        $estu_nombres   = strtoupper(trim($_POST['estu_nombres'] ?? ''));
        $estu_apellidos = strtoupper(trim($_POST['estu_apellidos'] ?? ''));
        $estu_numerodoc = trim($_POST['estu_numerodoc'] ?? '');

        if ($estu_nombres === '' || $estu_apellidos === '' || $estu_numerodoc === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nombres, apellidos y número de documento son requeridos']);
            break;
        }

        $estu_tipodoc    = trim($_POST['estu_tipodoc'] ?? '') ?: null;
        $fechanacimiento = trim($_POST['fechanacimiento'] ?? '') ?: null;
        $estu_sexo       = trim($_POST['estu_sexo'] ?? '') ?: null;
        $estu_telefono   = trim($_POST['estu_telefono'] ?? '') ?: null;
        $estu_email      = trim($_POST['estu_email'] ?? '') ?: null;
        $estu_ciudad     = strtoupper(trim($_POST['estu_ciudad'] ?? ''));
        $estu_direccion  = strtoupper(trim($_POST['estu_direccion'] ?? ''));
        $estu_barrio     = strtoupper(trim($_POST['estu_barrio'] ?? ''));
        $estu_estrato    = trim($_POST['estu_estrato'] ?? '') ?: null;
        $estu_eps        = strtoupper(trim($_POST['estu_eps'] ?? ''));

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
