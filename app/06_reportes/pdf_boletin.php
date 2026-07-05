<?php
session_start();
require_once '../01_login/check_session.php';

$role_id = (int)($_SESSION['role_id'] ?? 0);
if ($role_id !== 4) {
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
$usua_id = (int)($_SESSION['usua_id'] ?? 0);

// El filtro "est.usua_id = ?" garantiza que solo se pueda traer el propio
// boletín — si el grmo_id no existe o pertenece a otro estudiante, la
// query no devuelve fila (no se distingue un caso del otro).
$stmt = $pdo->prepare("
    SELECT est.estu_nombres, est.estu_apellidos, est.estu_numerodoc,
           m.modu_nombre, m.modu_sigla,
           gs.grse_codigo,
           p.prog_nombre, p.prog_sigla,
           pe.peri_codigo,
           d.doce_nombres, d.doce_apellidos,
           coh.coho_jornada,
           c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
           c.cali_n3, c.cali_n4, c.cali_sup_n4,
           c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva
    FROM grmoestudiantes ge
    JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
    JOIN modulos m ON gm.modu_id = m.modu_id
    JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
    JOIN programas p ON gs.prog_id = p.prog_id
    JOIN periodos pe ON gs.peri_id = pe.peri_id
    JOIN docentes d ON gm.doce_id = d.doce_id
    JOIN estudiantes est ON est.estu_id = ge.estu_id
    LEFT JOIN cohortes coh ON gs.coho_id = coh.coho_id
    LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
    WHERE ge.grmo_id = ? AND est.usua_id = ?
");
$stmt->execute([$grmo_id, $usua_id]);
$d = $stmt->fetch();

if (!$d) {
    http_response_code(403);
    die('No autorizado');
}

// ── Helpers de formato (mismo patrón que pdf_grupo.php) ──────────────────────
function fmtNota($valor) {
    return $valor !== null ? number_format((float)$valor, 1) : '—';
}

function colorSemaforo($valor) {
    if ($valor === null) return '';
    return ((float)$valor >= 3.0) ? 'background-color:#d4edda;' : 'background-color:#f8d7da;';
}

// Mismos 3 casos que badgeEstado() en reportes_ctrl.js.
function estadoInfo($notaFinal, $definitiva) {
    if ($definitiva !== null) {
        return ((float)$definitiva >= 3.0)
            ? ['texto' => 'Aprobado', 'color' => '#d4edda']
            : ['texto' => 'Reprobado', 'color' => '#f8d7da'];
    }
    if ($notaFinal !== null) {
        return ['texto' => 'Reprobado — pendiente habilitación', 'color' => '#f8d7da'];
    }
    return ['texto' => 'En curso', 'color' => '#e9ecef'];
}

$estado = estadoInfo($d['cali_nota_final'], $d['cali_definitiva']);
$jornada = $d['coho_jornada'] ?? '—';
$fechaGenerado = date('d/m/Y H:i');
$nombreArchivo = 'Boletin_' . $d['estu_numerodoc'] . '_' . $d['modu_sigla'] . '_' . date('Y-m-d') . '.pdf';

// ── HTML del boletín ──────────────────────────────────────────────────────────
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #212529; }
    h1 { font-size: 16px; text-align: center; margin: 0 0 2px; }
    h2 { font-size: 13px; text-align: center; margin: 0 0 10px; color: #495057; }
    .codigo { text-align: right; font-size: 9px; color: #6c757d; }
    .meta { font-size: 9px; color: #6c757d; text-align: center; margin-bottom: 14px; }
    table.contexto { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
    table.contexto td { padding: 4px 6px; font-size: 11px; }
    table.contexto td.etiqueta { font-weight: bold; width: 110px; }
    .formula { text-align: center; font-size: 11px; font-weight: bold; margin: 10px 0; padding: 6px; background-color: #f1f3f5; }
    table.ficha { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.ficha th, table.ficha td { border: 1px solid #adb5bd; padding: 6px 8px; font-size: 11px; }
    table.ficha th { background-color: #343a40; color: #fff; text-align: center; width: 14.28%; }
    table.ficha td.centro { text-align: center; }
    table.estado td { border: 1px solid #adb5bd; padding: 8px; font-size: 12px; font-weight: bold; text-align: center; }
    .leyenda { margin-top: 14px; font-size: 9px; text-align: center; color: #495057; }
</style>
</head>
<body>
    <div class="codigo">GA-FO-04</div>
    <h1>Escuela de Mecánica Dental Bolaños (EMDB)</h1>
    <h2>BOLETÍN DE CALIFICACIONES</h2>
    <div class="meta">Generado el ' . $fechaGenerado . ' por el sistema</div>

    <table class="contexto">
        <tr>
            <td class="etiqueta">Estudiante:</td><td>' . htmlspecialchars($d['estu_apellidos'] . ', ' . $d['estu_nombres']) . '</td>
            <td class="etiqueta">Documento:</td><td>' . htmlspecialchars($d['estu_numerodoc']) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Programa:</td><td>' . htmlspecialchars($d['prog_nombre']) . ' (' . htmlspecialchars($d['prog_sigla']) . ')</td>
            <td class="etiqueta">Período:</td><td>' . htmlspecialchars($d['peri_codigo']) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Módulo:</td><td>' . htmlspecialchars($d['modu_sigla']) . ' — ' . htmlspecialchars($d['modu_nombre']) . '</td>
            <td class="etiqueta">Jornada:</td><td>' . htmlspecialchars($jornada) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Docente:</td><td>' . htmlspecialchars($d['doce_apellidos'] . ', ' . $d['doce_nombres']) . '</td>
            <td class="etiqueta">Grupo:</td><td>' . htmlspecialchars($d['grse_codigo']) . '</td>
        </tr>
    </table>

    <div class="formula">N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = Nota Final</div>

    <table class="ficha">
        <thead>
            <tr>
                <th>N1</th><th>Sup N1</th><th>N2</th><th>Sup N2</th><th>N3</th><th>N4</th><th>Sup N4</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro">' . fmtNota($d['cali_n1']) . '</td>
                <td class="centro">' . fmtNota($d['cali_sup_n1']) . '</td>
                <td class="centro">' . fmtNota($d['cali_n2']) . '</td>
                <td class="centro">' . fmtNota($d['cali_sup_n2']) . '</td>
                <td class="centro">' . fmtNota($d['cali_n3']) . '</td>
                <td class="centro">' . fmtNota($d['cali_n4']) . '</td>
                <td class="centro">' . fmtNota($d['cali_sup_n4']) . '</td>
            </tr>
        </tbody>
    </table>

    <table class="ficha">
        <thead>
            <tr>
                <th>Nota Final</th><th>Habilitación</th><th>Definitiva</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="centro" style="' . colorSemaforo($d['cali_nota_final']) . '">' . fmtNota($d['cali_nota_final']) . '</td>
                <td class="centro">' . fmtNota($d['cali_habilitacion']) . '</td>
                <td class="centro" style="' . colorSemaforo($d['cali_definitiva']) . '">' . fmtNota($d['cali_definitiva']) . '</td>
            </tr>
        </tbody>
    </table>

    <table class="estado">
        <tr>
            <td style="background-color:' . $estado['color'] . ';">Estado: ' . $estado['texto'] . '</td>
        </tr>
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
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream($nombreArchivo, ['Attachment' => true]);
