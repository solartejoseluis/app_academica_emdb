<?php
session_start();
require_once '../01_login/check_session.php';

$role_id = (int)($_SESSION['role_id'] ?? 0);
if (!in_array($role_id, [1, 2])) {
    header('Location: ../01_login/login_view.php');
    exit;
}

require_once '../00_connect/pdo.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$estu_id = (int)($_GET['estu_id'] ?? 0);
if ($estu_id <= 0) {
    http_response_code(400);
    die('estu_id inválido');
}

$pdo = getConexion();

// La matrícula más reciente del estudiante (matriculas es 1:N por estu_id —
// un estudiante puede tener más de una a lo largo del tiempo). matr_id DESC
// desempata de forma determinista.
$stmt = $pdo->prepare("
    SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_ciudadnac, e.fechanacimiento,
           e.estu_ciudad, e.estu_direccion, e.estu_barrio, e.estu_foto,
           fi.jornada,
           fi.padr_nombres, fi.padr_apellidos,
           fi.madr_nombres, fi.madr_apellidos,
           fi.acud_nombres, fi.acud_apellidos, fi.acud_direccion, fi.acud_barrio, fi.acud_telefono,
           fi.estudio_institucion,
           m.matr_id, m.matr_estado, m.matr_folio, m.matr_numero, m.fechamatricula, m.matr_observacion,
           p.prog_nombre,
           c.coho_codigo,
           per.peri_codigo,
           conf.director_nombre, conf.secretario_nombre
    FROM estudiantes e
    LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
    INNER JOIN matriculas m ON m.estu_id = e.estu_id
    LEFT JOIN programas p ON p.prog_id = m.prog_id
    LEFT JOIN cohortes c ON c.coho_id = e.coho_id
    LEFT JOIN periodos per ON per.peri_id = m.peri_id
    LEFT JOIN configuracion conf ON conf.config_id = 1
    WHERE e.estu_id = ?
    ORDER BY m.matr_id DESC
    LIMIT 1
");
$stmt->execute([$estu_id]);
$d = $stmt->fetch();

if (!$d) {
    http_response_code(404);
    die('Estudiante no encontrado');
}

if ($d['matr_estado'] !== 'matriculado') {
    http_response_code(400);
    die('El estudiante no está matriculado');
}

// ── Asignación segura de matr_numero (solo si aún no tiene) ────────────────────
if ($d['matr_numero'] === null) {
    try {
        $pdo->beginTransaction();

        $stmtConf = $pdo->prepare("SELECT matr_numero_inicial FROM configuracion WHERE config_id = 1 FOR UPDATE");
        $stmtConf->execute();
        $numeroInicial = (int)$stmtConf->fetchColumn();

        $stmtMax = $pdo->prepare("SELECT MAX(matr_numero) FROM matriculas");
        $stmtMax->execute();
        $maxActual = $stmtMax->fetchColumn();

        $nuevoNumero = ($maxActual === null) ? $numeroInicial : max((int)$maxActual + 1, $numeroInicial);

        $stmtUpd = $pdo->prepare("UPDATE matriculas SET matr_numero = ? WHERE matr_id = ?");
        $stmtUpd->execute([$nuevoNumero, $d['matr_id']]);

        $pdo->commit();
        $d['matr_numero'] = $nuevoNumero;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        die('Error al asignar el número de matrícula');
    }
}

// ── Helpers de formato ────────────────────────────────────────────────────────
function fmt($valor) {
    return ($valor !== null && $valor !== '') ? htmlspecialchars((string)$valor) : '';
}

function fmtFecha($valor) {
    if ($valor === null || $valor === '' || $valor === '0000-00-00') return '';
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y', $ts) : '';
}

function calcularEdad($fechanacimiento) {
    if ($fechanacimiento === null || $fechanacimiento === '' || $fechanacimiento === '0000-00-00') return '';
    try {
        $nacimiento = new DateTime($fechanacimiento);
        $hoy = new DateTime();
        return (string)$hoy->diff($nacimiento)->y;
    } catch (Exception $e) {
        return '';
    }
}

function sanitizarNombreArchivo($texto) {
    $texto = str_replace(' ', '_', trim((string)$texto));
    return preg_replace('/[^A-Za-z0-9_]/', '', $texto);
}

// ── Foto del estudiante ────────────────────────────────────────────────────────
$fotoHtml = '<div class="foto-box">Sin foto</div>';
if (!empty($d['estu_foto'])) {
    $rutaFoto = __DIR__ . '/../../uploads/fotos_estudiantes/' . $d['estu_foto'];
    if (file_exists($rutaFoto)) {
        $fotoHtml = '<div class="foto-box"><img src="' . $rutaFoto . '" alt="Foto"></div>';
    }
}

$nombreArchivo = 'Hoja_Matricula_' . sanitizarNombreArchivo($d['estu_apellidos']) . '_' . sanitizarNombreArchivo($d['estu_nombres']) . '.pdf';
$nombreAlumno = fmt($d['estu_nombres']) . ' ' . fmt($d['estu_apellidos']);
$nombrePadre  = trim(fmt($d['padr_nombres']) . ' ' . fmt($d['padr_apellidos']));
$nombreMadre  = trim(fmt($d['madr_nombres']) . ' ' . fmt($d['madr_apellidos']));
$nombreAcud   = trim(fmt($d['acud_nombres']) . ' ' . fmt($d['acud_apellidos']));
$matriculadoPor = fmt($_SESSION['usua_nombre'] ?? '');

// ── HTML de la Hoja de Matrícula ────────────────────────────────────────────────
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #212529; }
    h1 { font-size: 14px; margin: 0 0 2px; }
    h2 { font-size: 10px; margin: 0; color: #495057; font-weight: normal; }
    .codigo { font-size: 9px; color: #6c757d; margin-top: 2px; }
    table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.encabezado td { vertical-align: top; padding: 0; }
    .foto-box {
        width: 85px; height: 100px; border: 1px solid #adb5bd;
        text-align: center; font-size: 9px; color: #adb5bd;
        padding-top: 42px;
    }
    .foto-box img { width: 85px; height: 100px; object-fit: cover; }
    .barra {
        background-color: #343a40; color: #fff; font-weight: bold;
        font-size: 10px; padding: 4px 8px; margin-top: 8px;
    }
    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    table.datos td { border: 1px solid #adb5bd; padding: 3px 6px; font-size: 9px; }
    table.datos td.etq { font-weight: bold; background-color: #f1f3f5; }
    .compromiso { text-align: center; font-weight: bold; font-size: 9.5px; margin: 10px 0; }
    table.firmas { width: 100%; margin-top: 26px; }
    table.firmas td { text-align: center; font-size: 8.5px; padding-top: 3px; border-top: 1px solid #212529; width: 45%; }
    table.firmas td.espacio { border-top: none; width: 10%; }
</style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td style="width:70%;">
                <h1>Escuela de Mecánica Dental Bolaños (EMDB)</h1>
                <h2>Hoja de Matrícula</h2>
                <div class="codigo">Código: AC-FO-09</div>
            </td>
            <td style="width:30%; text-align:right;">
                ' . $fotoHtml . '
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Folio</td><td style="width:35%;">' . fmt($d['matr_folio']) . '</td>
            <td class="etq" style="width:15%;">Matrícula No.</td><td style="width:35%;">' . fmt($d['matr_numero']) . '</td>
        </tr>
        <tr>
            <td class="etq">Programa</td><td>' . fmt($d['prog_nombre']) . '</td>
            <td class="etq">Cohorte</td><td>' . fmt($d['coho_codigo']) . '</td>
        </tr>
        <tr>
            <td class="etq">Período</td><td>' . fmt($d['peri_codigo']) . '</td>
            <td class="etq">Jornada</td><td>' . fmt($d['jornada']) . '</td>
        </tr>
        <tr>
            <td class="etq">Ciudad y fecha de matrícula</td><td colspan="3">Tuluá, ' . fmtFecha($d['fechamatricula']) . '</td>
        </tr>
    </table>

    <div class="barra">DATOS DEL ALUMNO</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Nombre del alumno</td><td colspan="3">' . $nombreAlumno . '</td>
        </tr>
        <tr>
            <td class="etq">Ciudad de nacimiento</td><td style="width:35%;">' . fmt($d['estu_ciudadnac']) . '</td>
            <td class="etq" style="width:15%;">Fecha de nacimiento</td><td style="width:35%;">' . fmtFecha($d['fechanacimiento']) . '</td>
        </tr>
        <tr>
            <td class="etq">Edad</td><td>' . fmt(calcularEdad($d['fechanacimiento'])) . '</td>
            <td class="etq">Establecimiento anterior</td><td>' . fmt($d['estudio_institucion']) . '</td>
        </tr>
        <tr>
            <td class="etq">Nombre del padre</td><td>' . $nombrePadre . '</td>
            <td class="etq">Nombre de la madre</td><td>' . $nombreMadre . '</td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td>' . fmt($d['estu_direccion']) . '</td>
            <td class="etq">Barrio</td><td>' . fmt($d['estu_barrio']) . '</td>
        </tr>
        <tr>
            <td class="etq">Ciudad</td><td colspan="3">' . fmt($d['estu_ciudad']) . '</td>
        </tr>
    </table>

    <div class="barra">ACUDIENTE</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Nombre</td><td colspan="3">' . $nombreAcud . '</td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td style="width:35%;">' . fmt($d['acud_direccion']) . '</td>
            <td class="etq" style="width:15%;">Barrio</td><td style="width:35%;">' . fmt($d['acud_barrio']) . '</td>
        </tr>
        <tr>
            <td class="etq">Celular</td><td colspan="3">' . fmt($d['acud_telefono']) . '</td>
        </tr>
    </table>

    <div class="compromiso">NOS COMPROMETEMOS A CUMPLIR CON EL REGLAMENTO DE LA INSTITUCIÓN</div>

    <table class="firmas">
        <tr>
            <td>Firma de Padre o Acudiente</td>
            <td class="espacio"></td>
            <td>Firma del Alumno</td>
        </tr>
    </table>

    <table class="firmas">
        <tr>
            <td>' . fmt($d['director_nombre']) . '<br>Director</td>
            <td class="espacio"></td>
            <td>' . fmt($d['secretario_nombre']) . '<br>Secretario(a)</td>
        </tr>
    </table>

    <table class="datos" style="margin-top:10px;">
        <tr>
            <td class="etq" style="width:15%;">Observaciones</td><td colspan="3">' . fmt($d['matr_observacion']) . '</td>
        </tr>
        <tr>
            <td class="etq">Matriculado por</td><td colspan="3">' . $matriculadoPor . '</td>
        </tr>
    </table>
</body>
</html>
';

// ── Generación del PDF ────────────────────────────────────────────────────────
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('chroot', realpath(__DIR__ . '/../../'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreArchivo, ['Attachment' => true]);
