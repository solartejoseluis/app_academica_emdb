<?php
// Sin session_start() ni check_session.php — mismo criterio que
// 09_inscripcion_publica/insc_mdl.php: accesible por cualquiera con el
// token en la URL, sin cuenta ni sesión.
require '../00_connect/pdo.php';

// Reutilizada por 'validar_token' y 'guardar_actualizacion' — local a este
// archivo porque ningún otro módulo la necesita (mismo criterio que
// calcularEstadoFicha() en est_mdl.php).
function validarTokenSolicitud(PDO $pdo, string $token): array {
    $stmt = $pdo->prepare("SELECT * FROM solicitudes_actualizacion WHERE soac_token = ?");
    $stmt->execute([$token]);
    $solicitud = $stmt->fetch();

    if (!$solicitud) {
        return ['valido' => false, 'motivo' => 'invalido'];
    }
    if ($solicitud['soac_estado'] !== 'generado') {
        return ['valido' => false, 'motivo' => 'usado'];
    }
    if (strtotime($solicitud['soac_expira_en']) < time()) {
        return ['valido' => false, 'motivo' => 'expirado'];
    }
    return ['valido' => true, 'solicitud' => $solicitud];
}

switch ($_GET['accion'] ?? '') {

    // Catálogo de Programas para #slct_finc_prog_id — no estaba en el
    // alcance original de esta fase, pero la vista necesita poblarlo y este
    // módulo no tiene sesión para llamar a est_mdl.php?accion=listar_programas
    // (restringido a role_id 1/2). Mismo patrón ya documentado en CLAUDE.md
    // ("Catálogos compartidos... se duplican por módulo, no se centralizan"),
    // duplicado tal cual de insc_mdl.php (09_inscripcion_publica).
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

    case 'validar_token':
        $token = trim($_POST['token'] ?? '');
        if ($token === '') {
            echo json_encode(['status' => 'error', 'motivo' => 'invalido']);
            break;
        }
        try {
            $pdo = getConexion();
            $resultado = validarTokenSolicitud($pdo, $token);
            if (!$resultado['valido']) {
                echo json_encode(['status' => 'error', 'motivo' => $resultado['motivo']]);
                break;
            }
            $estu_id = (int)$resultado['solicitud']['estu_id'];

            // Valores VIGENTES de estudiantes + fichas_inscripcion — mismo
            // criterio de LEFT JOIN que obtener_completo (est_mdl.php): el
            // estudiante edita sobre sus datos actuales, no un formulario
            // vacío. estu_tipodoc/estu_numerodoc viajan de solo lectura,
            // nunca como campos editables.
            $stmt = $pdo->prepare(
                "SELECT e.estu_tipodoc, e.estu_numerodoc, e.estu_nombres, e.estu_apellidos,
                        e.fechanacimiento, e.estu_sexo, e.estu_telefono, e.estu_email,
                        e.estu_ciudad, e.estu_direccion, e.estu_barrio, e.estu_estrato, e.estu_eps,
                        e.estu_expedidoen, e.estu_ciudadnac, e.estu_ocupacion,
                        e.estu_estadocivil, e.estu_discapacidad, e.estu_multiculturalidad,
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
            $datos = $stmt->fetch();
            if (!$datos) {
                echo json_encode(['status' => 'error', 'motivo' => 'invalido']);
                break;
            }
            echo json_encode(['status' => 'ok', 'data' => $datos]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'motivo' => 'invalido']);
        }
        break;

    case 'guardar_actualizacion':
        $token = trim($_POST['token'] ?? '');
        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => 'Token requerido']);
            break;
        }

        // --- Datos del estudiante (mismos campos que guardar_completo,
        // sin estu_tipodoc/estu_numerodoc — nunca editables por este medio) ---
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

        if ($estu_nombres === '' || $estu_apellidos === '' || $fechanacimiento === '' || $estu_sexo === ''
            || $estu_telefono === '' || $estu_email === '' || $estu_ciudad === '' || $estu_direccion === ''
            || $estu_barrio === '' || $estu_estrato === '' || $estu_eps === '' || $estu_expedidoen === ''
            || $estu_ciudadnac === '' || $estu_ocupacion === '' || $estu_estadocivil === ''
            || $estu_discapacidad === '' || $estu_multiculturalidad === '') {
            echo json_encode(['status' => 'error', 'message' => 'Todos los campos del estudiante son requeridos']);
            break;
        }

        if (!filter_var($estu_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Correo electrónico inválido']);
            break;
        }

        // --- Datos de la ficha familiar (mismos campos/validación condicional
        // que guardar_completo) ---
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

        // Ficha Familiar obligatoria siempre — mismo criterio que guardar_completo.
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

            // Re-valida el token exactamente igual que 'validar_token' — nunca
            // confiar en que el frontend ya lo validó, pudo expirar o usarse
            // entre que se abrió la página y que se dio "Guardar".
            $resultado = validarTokenSolicitud($pdo, $token);
            if (!$resultado['valido']) {
                $mensajes = [
                    'invalido' => 'Este link no es válido.',
                    'usado'    => 'Este link ya fue utilizado.',
                    'expirado' => 'Este link ha expirado. Solicita uno nuevo al coordinador.',
                ];
                echo json_encode(['status' => 'error', 'message' => $mensajes[$resultado['motivo']] ?? 'Este link no es válido.']);
                break;
            }

            // UPDATE atómico y condicional: la condición AND soac_estado =
            // 'generado' en el propio UPDATE evita la condición de carrera de
            // un doble submit — solo el primero en llegar afecta una fila.
            // NUNCA toca estudiantes/fichas_inscripcion directamente — todo
            // queda en las columnas soac_*/soac_ficha_* de la solicitud; la
            // escritura real ocurre solo al aprobar (case 'aprobar_actualizacion',
            // Fase 2, ya implementado).
            $stmt = $pdo->prepare(
                "UPDATE solicitudes_actualizacion
                 SET soac_estu_expedidoen = ?, soac_estu_nombres = ?, soac_estu_apellidos = ?,
                     soac_estu_ciudadnac = ?, soac_fechanacimiento = ?, soac_estu_sexo = ?,
                     soac_estu_telefono = ?, soac_estu_email = ?, soac_estu_ocupacion = ?,
                     soac_estu_direccion = ?, soac_estu_barrio = ?, soac_estu_ciudad = ?,
                     soac_estu_estrato = ?, soac_estu_estadocivil = ?, soac_estu_eps = ?,
                     soac_estu_discapacidad = ?, soac_estu_multiculturalidad = ?,
                     soac_ficha_prog_id = ?, soac_ficha_jornada = ?, soac_ficha_fechainscripcion = ?,
                     soac_ficha_padr_vive = ?, soac_ficha_padr_nombres = ?, soac_ficha_padr_apellidos = ?,
                     soac_ficha_padr_profesion = ?, soac_ficha_padr_empresa = ?, soac_ficha_padr_telefono = ?,
                     soac_ficha_padr_direccion = ?, soac_ficha_padr_barrio = ?, soac_ficha_padr_ciudad = ?,
                     soac_ficha_madr_vive = ?, soac_ficha_madr_nombres = ?, soac_ficha_madr_apellidos = ?,
                     soac_ficha_madr_profesion = ?, soac_ficha_madr_empresa = ?, soac_ficha_madr_telefono = ?,
                     soac_ficha_madr_direccion = ?, soac_ficha_madr_barrio = ?, soac_ficha_madr_ciudad = ?,
                     soac_ficha_acud_es = ?, soac_ficha_acud_parentesco = ?, soac_ficha_acud_nombres = ?,
                     soac_ficha_acud_apellidos = ?, soac_ficha_acud_profesion = ?, soac_ficha_acud_empresa = ?,
                     soac_ficha_acud_telefono = ?, soac_ficha_acud_direccion = ?, soac_ficha_acud_barrio = ?,
                     soac_ficha_acud_ciudad = ?, soac_ficha_estudio_tipo = ?, soac_ficha_estudio_titulo = ?,
                     soac_ficha_estudio_institucion = ?, soac_ficha_estudio_aniofin = ?,
                     soac_estado = 'recibido', soac_recibido_en = NOW()
                 WHERE soac_token = ? AND soac_estado = 'generado'"
            );
            $stmt->execute([
                $estu_expedidoen, $estu_nombres, $estu_apellidos, $estu_ciudadnac, $fechanacimiento, $estu_sexo,
                $estu_telefono, $estu_email, $estu_ocupacion, $estu_direccion, $estu_barrio, $estu_ciudad,
                $estu_estrato, $estu_estadocivil, $estu_eps, $estu_discapacidad, $estu_multiculturalidad,
                $prog_id, $jornada, $fechainscripcion,
                $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
                $token
            ]);

            if ($stmt->rowCount() === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Este link ya no es válido']);
                break;
            }

            echo json_encode([
                'status'  => 'ok',
                'message' => 'Tus datos fueron enviados. El coordinador los revisará antes de aplicarlos.',
            ]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar la actualización']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
