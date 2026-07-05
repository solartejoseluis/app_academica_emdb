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

$grmo_id = (int)($_GET['grmo_id'] ?? 0);
if ($grmo_id <= 0) {
    http_response_code(400);
    die('grmo_id inválido');
}

$pdo = getConexion();

// ── Datos de contexto del grupo módulo ───────────────────────────────────────
$stmtGrupo = $pdo->prepare("
    SELECT gm.grmo_id,
           m.modu_nombre, m.modu_sigla,
           gs.grse_codigo,
           p.prog_nombre, p.prog_sigla,
           pe.peri_codigo,
           d.doce_nombres, d.doce_apellidos,
           coh.coho_jornada
    FROM gruposmodulos gm
    INNER JOIN modulos m ON gm.modu_id = m.modu_id
    INNER JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
    INNER JOIN programas p ON gs.prog_id = p.prog_id
    INNER JOIN periodos pe ON gs.peri_id = pe.peri_id
    INNER JOIN docentes d ON gm.doce_id = d.doce_id
    LEFT JOIN cohortes coh ON gs.coho_id = coh.coho_id
    WHERE gm.grmo_id = ?
");
$stmtGrupo->execute([$grmo_id]);
$grupo = $stmtGrupo->fetch();

if (!$grupo) {
    http_response_code(404);
    die('Grupo módulo no encontrado');
}

// ── Calificaciones de todos los estudiantes del grupo ────────────────────────
$stmtNotas = $pdo->prepare("
    SELECT e.estu_nombres, e.estu_apellidos, e.estu_numerodoc,
           c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
           c.cali_n3, c.cali_n4, c.cali_sup_n4,
           c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva
    FROM grmoestudiantes ge
    JOIN estudiantes e ON ge.estu_id = e.estu_id
    LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
    WHERE ge.grmo_id = ?
    ORDER BY e.estu_apellidos, e.estu_nombres
");
$stmtNotas->execute([$grmo_id]);
$estudiantes = $stmtNotas->fetchAll();

// ── Helpers de formato ────────────────────────────────────────────────────────
function fmtNota($valor) {
    return $valor !== null ? number_format((float)$valor, 1) : '—';
}

function colorSemaforo($valor) {
    if ($valor === null) return '';
    return ((float)$valor >= 3.0) ? 'background-color:#d4edda;' : 'background-color:#f8d7da;';
}

$fechaGenerado = date('d/m/Y H:i');
$nombreArchivo = 'GA-FO-04_' . $grupo['grse_codigo'] . '_' . date('Y-m-d') . '.pdf';

// ── Filas de la tabla de notas ────────────────────────────────────────────────
$filasHtml = '';
if (!$estudiantes) {
    $filasHtml = '<tr><td colspan="14" class="centro">Sin estudiantes asignados a este módulo</td></tr>';
} else {
    $num = 1;
    foreach ($estudiantes as $est) {
        $filasHtml .= '<tr>
            <td>' . $num++ . '</td>
            <td>' . htmlspecialchars($est['estu_apellidos']) . '</td>
            <td>' . htmlspecialchars($est['estu_nombres']) . '</td>
            <td>' . htmlspecialchars($est['estu_numerodoc']) . '</td>
            <td class="centro">' . fmtNota($est['cali_n1']) . '</td>
            <td class="centro">' . fmtNota($est['cali_sup_n1']) . '</td>
            <td class="centro">' . fmtNota($est['cali_n2']) . '</td>
            <td class="centro">' . fmtNota($est['cali_sup_n2']) . '</td>
            <td class="centro">' . fmtNota($est['cali_n3']) . '</td>
            <td class="centro">' . fmtNota($est['cali_n4']) . '</td>
            <td class="centro">' . fmtNota($est['cali_sup_n4']) . '</td>
            <td class="centro" style="' . colorSemaforo($est['cali_nota_final']) . '">' . fmtNota($est['cali_nota_final']) . '</td>
            <td class="centro">' . fmtNota($est['cali_habilitacion']) . '</td>
            <td class="centro" style="' . colorSemaforo($est['cali_definitiva']) . '">' . fmtNota($est['cali_definitiva']) . '</td>
        </tr>';
    }
}

$jornada = $grupo['coho_jornada'] ?? '—';

// ── HTML del reporte ──────────────────────────────────────────────────────────
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #212529; }
    h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
    h2 { font-size: 13px; text-align: center; margin: 0 0 10px; color: #495057; }
    .codigo { text-align: right; font-size: 9px; color: #6c757d; }
    .meta { font-size: 9px; color: #6c757d; text-align: center; margin-bottom: 10px; }
    table.contexto { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
    table.contexto td { padding: 3px 6px; font-size: 10px; }
    table.contexto td.etiqueta { font-weight: bold; width: 110px; }
    .formula { text-align: center; font-size: 10px; font-weight: bold; margin: 8px 0; padding: 5px; background-color: #f1f3f5; }
    table.notas { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.notas th, table.notas td { border: 1px solid #adb5bd; padding: 3px 4px; font-size: 9px; }
    table.notas th { background-color: #343a40; color: #fff; text-align: center; }
    table.notas td.centro { text-align: center; }
    .leyenda { margin-top: 10px; font-size: 9px; text-align: center; color: #495057; }
</style>
</head>
<body>
    <div class="codigo">GA-FO-04</div>
    <h1>Escuela de Mecánica Dental Bolaños (EMDB)</h1>
    <h2>PLANILLA DE CALIFICACIONES</h2>
    <div class="meta">Generado el ' . $fechaGenerado . ' por el sistema</div>

    <table class="contexto">
        <tr>
            <td class="etiqueta">Programa:</td><td>' . htmlspecialchars($grupo['prog_nombre']) . ' (' . htmlspecialchars($grupo['prog_sigla']) . ')</td>
            <td class="etiqueta">Período:</td><td>' . htmlspecialchars($grupo['peri_codigo']) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Módulo:</td><td>' . htmlspecialchars($grupo['modu_sigla']) . ' — ' . htmlspecialchars($grupo['modu_nombre']) . '</td>
            <td class="etiqueta">Jornada:</td><td>' . htmlspecialchars($jornada) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Docente:</td><td>' . htmlspecialchars($grupo['doce_apellidos'] . ', ' . $grupo['doce_nombres']) . '</td>
            <td class="etiqueta">Grupo:</td><td>' . htmlspecialchars($grupo['grse_codigo']) . '</td>
        </tr>
    </table>

    <div class="formula">N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = Nota Final</div>

    <table class="notas">
        <thead>
            <tr>
                <th>#</th><th>Apellidos</th><th>Nombres</th><th>Documento</th>
                <th>N1</th><th>Sup N1</th><th>N2</th><th>Sup N2</th><th>N3</th><th>N4</th><th>Sup N4</th>
                <th>Nota Final</th><th>Habilitación</th><th>Definitiva</th>
            </tr>
        </thead>
        <tbody>
            ' . $filasHtml . '
        </tbody>
    </table>

    <div class="leyenda">
        <span style="display:inline-block; width:10px; height:10px; background-color:#f8d7da; border-radius:50%;"></span> No aprobado (&lt; 3.0)
        &nbsp;&nbsp;
        <span style="display:inline-block; width:10px; height:10px; background-color:#d4edda; border-radius:50%;"></span> Aprobado (≥ 3.0)
    </div>
</body>
</html>
';

// ── Generación del PDF ────────────────────────────────────────────────────────
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream($nombreArchivo, ['Attachment' => true]);
