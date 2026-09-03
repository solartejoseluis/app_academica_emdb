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

$stmt = $pdo->prepare("
    SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_tipodoc, e.estu_numerodoc,
           e.estu_expedidoen, e.estu_ciudadnac, e.fechanacimiento, e.estu_sexo, e.estu_telefono,
           e.estu_email, e.estu_ocupacion, e.estu_direccion, e.estu_barrio, e.estu_ciudad,
           e.estu_estrato, e.estu_estadocivil, e.estu_eps, e.estu_discapacidad,
           e.estu_multiculturalidad, e.estu_foto,
           fi.jornada, fi.fechainscripcion,
           fi.padr_vive, fi.padr_nombres, fi.padr_apellidos, fi.padr_profesion, fi.padr_empresa,
           fi.padr_telefono, fi.padr_direccion, fi.padr_barrio, fi.padr_ciudad,
           fi.madr_vive, fi.madr_nombres, fi.madr_apellidos, fi.madr_profesion, fi.madr_empresa,
           fi.madr_telefono, fi.madr_direccion, fi.madr_barrio, fi.madr_ciudad,
           fi.acud_parentesco, fi.acud_nombres, fi.acud_apellidos, fi.acud_profesion, fi.acud_empresa,
           fi.acud_telefono, fi.acud_direccion, fi.acud_barrio, fi.acud_ciudad,
           fi.estudio_tipo, fi.estudio_titulo, fi.estudio_institucion, fi.estudio_aniofin,
           p.prog_nombre, p.prog_sigla
    FROM estudiantes e
    LEFT JOIN fichas_inscripcion fi ON fi.estu_id = e.estu_id
    LEFT JOIN programas p ON p.prog_id = fi.prog_id
    WHERE e.estu_id = ?
");
$stmt->execute([$estu_id]);
$d = $stmt->fetch();

if (!$d) {
    http_response_code(404);
    die('Estudiante no encontrado');
}

$stmtConf = $pdo->prepare("SELECT institucion_nombre FROM configuracion WHERE config_id = 1");
$stmtConf->execute();
$config = $stmtConf->fetch();
$institucionNombre = $config['institucion_nombre'] ?? 'Escuela de Mecánica Dental Bolaños (EMDB)';

// ── Helpers de formato ────────────────────────────────────────────────────────
function fmt($valor) {
    return ($valor !== null && $valor !== '') ? htmlspecialchars((string)$valor) : '';
}

function siNo($valor) {
    if ($valor === null) return '';
    return ((int)$valor === 1) ? 'Sí' : 'No';
}

function fmtFecha($valor) {
    if ($valor === null || $valor === '' || $valor === '0000-00-00') return '';
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y', $ts) : '';
}

function estadoCivilTexto($valor) {
    $map = [
        'soltero'      => 'Soltero/a',
        'casado'       => 'Casado/a',
        'union_libre'  => 'Unión libre',
        'divorciado'   => 'Divorciado/a',
        'viudo'        => 'Viudo/a',
    ];
    return $valor !== null && isset($map[$valor]) ? $map[$valor] : fmt($valor);
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

$nombreArchivo = 'Ficha_' . sanitizarNombreArchivo($d['estu_apellidos']) . '_' . sanitizarNombreArchivo($d['estu_nombres']) . '.pdf';
$programaTexto = $d['prog_nombre'] ? ($d['prog_nombre'] . ($d['prog_sigla'] ? ' (' . $d['prog_sigla'] . ')' : '')) : '';

// ── HTML de la ficha ────────────────────────────────────────────────────────────
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #212529; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 10px; margin: 0; color: #495057; font-weight: normal; }
    .codigo { font-size: 9px; color: #6c757d; margin-top: 2px; }
    table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    table.encabezado td { vertical-align: top; padding: 0; }
    .foto-box {
        width: 100px; height: 120px; border: 1px solid #adb5bd;
        text-align: center; font-size: 9px; color: #adb5bd;
        padding-top: 50px;
    }
    .foto-box img { width: 100px; height: 120px; object-fit: cover; }
    .barra {
        background-color: #343a40; color: #fff; font-weight: bold;
        font-size: 11px; padding: 5px 8px; margin-top: 10px;
    }
    table.datos { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    table.datos td { border: 1px solid #adb5bd; padding: 4px 6px; font-size: 9.5px; }
    table.datos td.etq { font-weight: bold; background-color: #f1f3f5; }
    .subseccion { font-weight: bold; font-size: 10px; margin: 8px 0 4px; text-decoration: underline; }
    table.firmas { width: 100%; margin-top: 40px; }
    table.firmas td { text-align: center; font-size: 9px; padding-top: 4px; border-top: 1px solid #212529; width: 45%; }
    table.firmas td.espacio { border-top: none; width: 10%; }
    table.pie { width: 100%; border-collapse: collapse; margin-top: 14px; }
    table.pie td { border: 1px solid #adb5bd; padding: 5px 8px; font-size: 9.5px; }
    table.pie td.etq { font-weight: bold; background-color: #f1f3f5; }
</style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td style="width:70%;">
                <h1>' . htmlspecialchars($institucionNombre) . '</h1>
                <h2>Ficha de Inscripción</h2>
                <div class="codigo">Código: AC-FO-02</div>
            </td>
            <td style="width:30%; text-align:right;">
                ' . $fotoHtml . '
            </td>
        </tr>
    </table>

    <div class="barra">DATOS DEL ESTUDIANTE</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Nombres</td><td style="width:35%;">' . fmt($d['estu_nombres']) . '</td>
            <td class="etq" style="width:15%;">Apellidos</td><td style="width:35%;">' . fmt($d['estu_apellidos']) . '</td>
        </tr>
        <tr>
            <td class="etq">Tipo de documento</td><td>' . fmt($d['estu_tipodoc']) . '</td>
            <td class="etq">Número de documento</td><td>' . fmt($d['estu_numerodoc']) . '</td>
        </tr>
        <tr>
            <td class="etq">Expedido en</td><td>' . fmt($d['estu_expedidoen']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Ciudad de nacimiento</td><td>' . fmt($d['estu_ciudadnac']) . '</td>
            <td class="etq">Fecha de nacimiento</td><td>' . fmtFecha($d['fechanacimiento']) . '</td>
        </tr>
        <tr>
            <td class="etq">Sexo</td><td>' . fmt($d['estu_sexo']) . '</td>
            <td class="etq">Teléfono</td><td>' . fmt($d['estu_telefono']) . '</td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td>' . fmt($d['estu_direccion']) . '</td>
            <td class="etq">Barrio</td><td>' . fmt($d['estu_barrio']) . '</td>
        </tr>
        <tr>
            <td class="etq">Ciudad</td><td>' . fmt($d['estu_ciudad']) . '</td>
            <td class="etq">Estrato</td><td>' . fmt($d['estu_estrato']) . '</td>
        </tr>
        <tr>
            <td class="etq">Estado civil</td><td>' . estadoCivilTexto($d['estu_estadocivil']) . '</td>
            <td class="etq">EPS</td><td>' . fmt($d['estu_eps']) . '</td>
        </tr>
        <tr>
            <td class="etq">Discapacidad</td><td>' . fmt($d['estu_discapacidad']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Correo electrónico</td><td>' . fmt($d['estu_email']) . '</td>
            <td class="etq">Ocupación</td><td>' . fmt($d['estu_ocupacion']) . '</td>
        </tr>
        <tr>
            <td class="etq">Multiculturalidad</td><td>' . fmt($d['estu_multiculturalidad']) . '</td><td class="etq"></td><td></td>
        </tr>
    </table>

    <div class="subseccion">Relación de estudios anteriores</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Tipo de formación</td><td style="width:35%;">' . fmt($d['estudio_tipo']) . '</td>
            <td class="etq" style="width:15%;">Grado o título obtenido</td><td style="width:35%;">' . fmt($d['estudio_titulo']) . '</td>
        </tr>
        <tr>
            <td class="etq">Institución</td><td>' . fmt($d['estudio_institucion']) . '</td>
            <td class="etq">Año fin</td><td>' . fmt($d['estudio_aniofin']) . '</td>
        </tr>
    </table>

    <div class="barra">DATOS DEL PADRE</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Vive</td><td style="width:18%;">' . siNo($d['padr_vive']) . '</td>
            <td class="etq" style="width:15%;">Nombres</td><td style="width:26%;">' . fmt($d['padr_nombres']) . '</td>
            <td class="etq" style="width:15%;">Apellidos</td><td style="width:26%;">' . fmt($d['padr_apellidos']) . '</td>
        </tr>
        <tr>
            <td class="etq">Profesión</td><td>' . fmt($d['padr_profesion']) . '</td>
            <td class="etq">Empresa</td><td>' . fmt($d['padr_empresa']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Teléfono</td><td>' . fmt($d['padr_telefono']) . '</td>
            <td class="etq">Barrio</td><td>' . fmt($d['padr_barrio']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td>' . fmt($d['padr_direccion']) . '</td>
            <td class="etq">Ciudad</td><td>' . fmt($d['padr_ciudad']) . '</td><td class="etq"></td><td></td>
        </tr>
    </table>

    <div class="barra">DATOS DE LA MADRE</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Vive</td><td style="width:18%;">' . siNo($d['madr_vive']) . '</td>
            <td class="etq" style="width:15%;">Nombres</td><td style="width:26%;">' . fmt($d['madr_nombres']) . '</td>
            <td class="etq" style="width:15%;">Apellidos</td><td style="width:26%;">' . fmt($d['madr_apellidos']) . '</td>
        </tr>
        <tr>
            <td class="etq">Profesión</td><td>' . fmt($d['madr_profesion']) . '</td>
            <td class="etq">Empresa</td><td>' . fmt($d['madr_empresa']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Teléfono</td><td>' . fmt($d['madr_telefono']) . '</td>
            <td class="etq">Barrio</td><td>' . fmt($d['madr_barrio']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td>' . fmt($d['madr_direccion']) . '</td>
            <td class="etq">Ciudad</td><td>' . fmt($d['madr_ciudad']) . '</td><td class="etq"></td><td></td>
        </tr>
    </table>

    <div class="barra">DATOS DEL ACUDIENTE O PERSONA DE CONTACTO</div>
    <table class="datos">
        <tr>
            <td class="etq" style="width:15%;">Parentesco</td><td style="width:18%;">' . fmt($d['acud_parentesco']) . '</td>
            <td class="etq" style="width:15%;">Nombres</td><td style="width:26%;">' . fmt($d['acud_nombres']) . '</td>
            <td class="etq" style="width:15%;">Apellidos</td><td style="width:26%;">' . fmt($d['acud_apellidos']) . '</td>
        </tr>
        <tr>
            <td class="etq">Profesión</td><td>' . fmt($d['acud_profesion']) . '</td>
            <td class="etq">Empresa</td><td>' . fmt($d['acud_empresa']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Teléfono</td><td>' . fmt($d['acud_telefono']) . '</td>
            <td class="etq">Barrio</td><td>' . fmt($d['acud_barrio']) . '</td><td class="etq"></td><td></td>
        </tr>
        <tr>
            <td class="etq">Dirección</td><td>' . fmt($d['acud_direccion']) . '</td>
            <td class="etq">Ciudad</td><td>' . fmt($d['acud_ciudad']) . '</td><td class="etq"></td><td></td>
        </tr>
    </table>

    <table class="firmas">
        <tr>
            <td>Firma de Padre o Acudiente</td>
            <td class="espacio"></td>
            <td>Firma del Alumno</td>
        </tr>
    </table>

    <table class="pie">
        <tr>
            <td class="etq" style="width:16%;">Programa técnico</td><td style="width:34%;">' . fmt($programaTexto) . '</td>
            <td class="etq" style="width:16%;">Fecha de inscripción</td><td style="width:17%;">' . fmtFecha($d['fechainscripcion']) . '</td>
            <td class="etq" style="width:8%;">Jornada</td><td style="width:9%;">' . fmt($d['jornada']) . '</td>
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
// Oficio Colombia: mismo ancho que Carta (21.59cm = 612pt),
// alto de 33cm = 935.43pt
$dompdf->setPaper([0, 0, 612, 935.43], 'portrait');
$dompdf->render();
$dompdf->stream($nombreArchivo, ['Attachment' => true]);
