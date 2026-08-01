<?php
require '../00_connect/pdo.php';
require '../00_connect/recaptcha_config.php';

function generarCodigoTemporal(): string {
    $charset = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max     = strlen($charset) - 1;
    $codigo  = '';
    for ($i = 0; $i < 8; $i++) {
        $codigo .= $charset[random_int(0, $max)];
    }
    return $codigo;
}

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

    case 'crear_aspirante':
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

            $codigo = null;
            for ($intento = 0; $intento < 5; $intento++) {
                $candidato = generarCodigoTemporal();
                $checkCodigo = $pdo->prepare("SELECT finc_id FROM fichas_inscripcion WHERE finc_codigotemporal = ?");
                $checkCodigo->execute([$candidato]);
                if (!$checkCodigo->fetch()) {
                    $codigo = $candidato;
                    break;
                }
            }

            if ($codigo === null) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Error al generar el código de acceso, intenta de nuevo']);
                break;
            }

            $stmtFicha = $pdo->prepare(
                "INSERT INTO fichas_inscripcion (estu_id, finc_codigotemporal) VALUES (?, ?)"
            );
            $stmtFicha->execute([$estu_id, $codigo]);

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'codigo' => $codigo, 'estu_id' => $estu_id]);

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
