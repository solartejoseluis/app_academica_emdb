<?php
require '../00_connect/pdo.php';
require '../00_connect/recaptcha_config.php';

function verificarRecaptcha(string $token, string $remoteip): array {
    if ($token === '') {
        return ['ok' => false, 'message' => 'Por favor confirma que no eres un robot'];
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'secret'   => RECAPTCHA_SECRET_KEY,
        'response' => $token,
        'remoteip' => $remoteip,
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $respuesta = curl_exec($ch);

    if ($respuesta === false) {
        return ['ok' => false, 'message' => 'No se pudo verificar la seguridad, intenta más tarde'];
    }

    $data = json_decode($respuesta, true);
    if (!isset($data['success']) || $data['success'] !== true) {
        return ['ok' => false, 'message' => 'Verificación de seguridad fallida, intenta de nuevo'];
    }

    return ['ok' => true];
}

switch ($_GET['accion'] ?? '') {

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

    case 'crear_aspirante':
        // --- Datos del aspirante (paso 1 original) ---
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
        $recaptcha_token        = trim($_POST['g-recaptcha-response'] ?? '');

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

        // --- Ficha Familiar (paso 2 original, fusionado en el mismo formulario) ---
        $prog_id = (int)($_POST['prog_id'] ?? 0) ?: null;
        $jornada = strtoupper(trim($_POST['jornada'] ?? '')) ?: null;

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
        foreach ($camposFichaRequeridos as $valor) {
            if ($valor === null) {
                echo json_encode(['status' => 'error', 'message' => 'Todos los campos de la ficha familiar son obligatorios']);
                break 2;
            }
        }

        $verificacion = verificarRecaptcha($recaptcha_token, $_SERVER['REMOTE_ADDR'] ?? '');
        if (!$verificacion['ok']) {
            echo json_encode(['status' => 'error', 'message' => $verificacion['message']]);
            break;
        }

        try {
            $pdo = getConexion();

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
                $estu_estadocivil, $estu_discapacidad, $estu_multiculturalidad, 'web'
            ]);
            $estu_id = (int)$pdo->lastInsertId();

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
                 VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmtFicha->execute([
                $estu_id, $prog_id, $jornada,
                $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin
            ]);

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'estu_id' => $estu_id]);

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['status' => 'error', 'message' => 'Error al procesar la inscripción']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
