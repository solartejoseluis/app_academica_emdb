<?php
require '../00_connect/pdo.php';

switch ($_GET['accion'] ?? '') {

    case 'consultar_por_codigo':
        $codigo = trim($_POST['codigo'] ?? '');

        if ($codigo === '') {
            echo json_encode(['status' => 'error', 'message' => 'Ingresa tu código de acceso']);
            break;
        }

        try {
            $pdo = getConexion();
            $stmt = $pdo->prepare(
                "SELECT fi.estu_id, e.estu_nombres, e.estu_apellidos, fi.prog_id, fi.jornada,
                        fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
                        fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
                        fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
                        fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
                        fi.acud_es, fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion,
                        fi.acud_empresa, fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
                        fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin
                 FROM fichas_inscripcion fi
                 JOIN estudiantes e ON e.estu_id = fi.estu_id
                 WHERE fi.finc_codigotemporal = ?"
            );
            $stmt->execute([$codigo]);
            $row = $stmt->fetch();

            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Código no válido, verifica que esté bien escrito']);
                break;
            }

            echo json_encode(['status' => 'ok', 'data' => $row]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al consultar el código']);
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

    case 'completar_ficha':
        $codigo = trim($_POST['codigo'] ?? '');

        if ($codigo === '') {
            echo json_encode(['status' => 'error', 'message' => 'Código no válido']);
            break;
        }

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

            $check = $pdo->prepare("SELECT estu_id FROM fichas_inscripcion WHERE finc_codigotemporal = ?");
            $check->execute([$codigo]);
            if (!$check->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Código no válido']);
                break;
            }

            $stmt = $pdo->prepare(
                "UPDATE fichas_inscripcion
                 SET prog_id = ?, jornada = ?, fechainscripcion = CURDATE(),
                     padr_vive = ?, padr_nombres = ?, padr_apellidos = ?, padr_profesion = ?, padr_empresa = ?,
                     padr_telefono = ?, padr_direccion = ?, padr_barrio = ?, padr_ciudad = ?,
                     madr_vive = ?, madr_nombres = ?, madr_apellidos = ?, madr_profesion = ?, madr_empresa = ?,
                     madr_telefono = ?, madr_direccion = ?, madr_barrio = ?, madr_ciudad = ?,
                     acud_es = ?, acud_parentesco = ?, acud_nombres = ?, acud_apellidos = ?, acud_profesion = ?,
                     acud_empresa = ?, acud_telefono = ?, acud_direccion = ?, acud_barrio = ?, acud_ciudad = ?,
                     estudio_tipo = ?, estudio_titulo = ?, estudio_institucion = ?, estudio_aniofin = ?
                 WHERE finc_codigotemporal = ?"
            );
            $stmt->execute([
                $prog_id, $jornada,
                $padr_vive, $padr_nombres, $padr_apellidos, $padr_profesion, $padr_empresa,
                $padr_telefono, $padr_direccion, $padr_barrio, $padr_ciudad,
                $madr_vive, $madr_nombres, $madr_apellidos, $madr_profesion, $madr_empresa,
                $madr_telefono, $madr_direccion, $madr_barrio, $madr_ciudad,
                $acud_es, $acud_parentesco, $acud_nombres, $acud_apellidos, $acud_profesion,
                $acud_empresa, $acud_telefono, $acud_direccion, $acud_barrio, $acud_ciudad,
                $estudio_tipo, $estudio_titulo, $estudio_institucion, $estudio_aniofin,
                $codigo
            ]);

            echo json_encode(['status' => 'ok']);

        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error al guardar los datos familiares']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
