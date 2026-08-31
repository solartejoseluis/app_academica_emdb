<?php
session_start();
require_once '../01_login/check_session.php';
require_once '../00_connect/pdo.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Resuelve el estudiante objetivo — mismo criterio ya implementado en
// reportes_mdl.php (Fase 1): role 4 siempre es el propio estudiante en
// sesión (ignora cualquier otro dato); roles 1/2 pueden generar el boletín
// de otro estudiante vía estu_id por GET.
function resolverEstuIdObjetivo(): ?int {
    $role_id = (int)($_SESSION['role_id'] ?? 0);
    if ($role_id === 4) {
        return isset($_SESSION['estu_id']) ? (int)$_SESSION['estu_id'] : null;
    }
    if (in_array($role_id, [1, 2], true)) {
        $estu_id = (int)($_GET['estu_id'] ?? 0);
        return $estu_id > 0 ? $estu_id : null;
    }
    return null;
}

$role_id = (int)($_SESSION['role_id'] ?? 0);
if (!in_array($role_id, [1, 2, 4], true)) {
    http_response_code(403);
    die('No autorizado');
}

$estu_id = resolverEstuIdObjetivo();
$matr_id = (int)($_GET['matr_id'] ?? 0);
$peri_id = (int)($_GET['peri_id'] ?? 0);

// Mismo criterio ya documentado para este archivo: no se distingue "no
// existe" de "es de otro estudiante" — 403 genérico en cualquiera de los
// dos casos.
if ($estu_id === null || $matr_id <= 0 || $peri_id <= 0) {
    http_response_code(403);
    die('No autorizado');
}

$pdo = getConexion();

// La matrícula debe pertenecer al estudiante objetivo.
$stmtMatr = $pdo->prepare("
    SELECT m.prog_id, p.prog_nombre, p.prog_sigla, c.coho_codigo,
           e.estu_nombres, e.estu_apellidos, e.estu_numerodoc
    FROM matriculas m
    INNER JOIN programas p ON m.prog_id = p.prog_id
    INNER JOIN estudiantes e ON m.estu_id = e.estu_id
    LEFT JOIN cohortes c ON m.coho_id = c.coho_id
    WHERE m.matr_id = ? AND m.estu_id = ?
");
$stmtMatr->execute([$matr_id, $estu_id]);
$matricula = $stmtMatr->fetch();

if (!$matricula) {
    http_response_code(403);
    die('No autorizado');
}
$prog_id = (int)$matricula['prog_id'];

$stmtPeri = $pdo->prepare("SELECT peri_codigo FROM periodos WHERE peri_id = ?");
$stmtPeri->execute([$peri_id]);
$periodo = $stmtPeri->fetch();
if (!$periodo) {
    http_response_code(403);
    die('No autorizado');
}

$stmtConf = $pdo->prepare("SELECT institucion_nombre FROM configuracion WHERE config_id = 1");
$stmtConf->execute();
$config = $stmtConf->fetch();
$institucionNombre = $config['institucion_nombre'] ?? 'Escuela de Mecánica Dental Bolaños (EMDB)';

// Módulos del programa+período para el estudiante — misma ruta SQL que
// reportes_mdl.php/detalle_periodo (Fase 1).
$stmtModulos = $pdo->prepare("
    SELECT gm.grmo_id, m.modu_sigla, m.modu_nombre, gs.grse_codigo,
           d.doce_nombres, d.doce_apellidos,
           c.cali_n1, c.cali_sup_n1, c.cali_n2, c.cali_sup_n2,
           c.cali_n3, c.cali_n4, c.cali_sup_n4,
           c.cali_nota_final, c.cali_habilitacion, c.cali_definitiva
    FROM grmoestudiantes ge
    JOIN gruposmodulos gm ON ge.grmo_id = gm.grmo_id
    JOIN modulos m ON gm.modu_id = m.modu_id
    JOIN gruposemestres gs ON gm.grse_id = gs.grse_id
    JOIN docentes d ON gm.doce_id = d.doce_id
    LEFT JOIN calificaciones c ON c.grmo_id = ge.grmo_id AND c.estu_id = ge.estu_id
    WHERE ge.estu_id = ? AND gs.prog_id = ? AND gs.peri_id = ?
    ORDER BY m.modu_orden
");
$stmtModulos->execute([$estu_id, $prog_id, $peri_id]);
$modulos = $stmtModulos->fetchAll();

// ── Helpers de formato (mismo patrón que la versión anterior de este archivo) ─
function fmtNota($valor) {
    return $valor !== null ? number_format((float)$valor, 1) : '—';
}

function colorSemaforo($valor) {
    if ($valor === null) return '';
    return ((float)$valor >= 3.0) ? 'background-color:#d4edda;' : 'background-color:#f8d7da;';
}

// Mismos 3 casos que badgeEstado() en reportes_ctrl.js — estado de UN módulo.
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

// Estado del período — MISMO cálculo que detalle_periodo (reportes_mdl.php,
// Fase 1): 'En Curso' si algún módulo aún no tiene cali_definitiva; si TODOS
// ya la tienen, promedio aritmético simple decide Aprobado/Reprobado.
// 'Aplazado' es un 4to valor posible (alineado con matr_estado_academico)
// pero no es alcanzable todavía por ningún cálculo — se activará en fase futura.
$estadoPeriodoTexto = 'En Curso';
$estadoPeriodoColor = '#e9ecef';
$promedioPeriodo    = null;
if (count($modulos) > 0) {
    $definitivas    = array_column($modulos, 'cali_definitiva');
    $todasDefinidas = !in_array(null, $definitivas, true);
    if ($todasDefinidas) {
        $promedioPeriodo = round(array_sum(array_map('floatval', $definitivas)) / count($definitivas), 1);
        if ($promedioPeriodo >= 3.0) {
            $estadoPeriodoTexto = 'Aprobado';
            $estadoPeriodoColor = '#d4edda';
        } else {
            $estadoPeriodoTexto = 'Reprobado';
            $estadoPeriodoColor = '#f8d7da';
        }
    }
}

$fechaGenerado = date('d/m/Y H:i');

$apellidoArchivo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $matricula['estu_apellidos']) ?: 'estudiante';
$apellidoArchivo = preg_replace('/[^A-Za-z0-9]+/', '', $apellidoArchivo);
$periodoArchivo  = preg_replace('/[^A-Za-z0-9]+/', '', $periodo['peri_codigo']);
$nombreArchivo   = 'boletin_' . strtolower($apellidoArchivo) . '_' . strtolower($periodoArchivo) . '.pdf';

// ── Bloque por módulo (una tabla de contexto + una tabla de notas por cada uno) ─
$modulosHtml = '';
if (!$modulos) {
    $modulosHtml = '<p class="sin-modulos">Sin módulos asignados en este período.</p>';
} else {
    foreach ($modulos as $mod) {
        $estado = estadoInfo($mod['cali_nota_final'], $mod['cali_definitiva']);
        $modulosHtml .= '
        <table class="contexto-modulo">
            <tr>
                <td class="etiqueta">Módulo:</td><td>' . htmlspecialchars($mod['modu_sigla'] . ' — ' . $mod['modu_nombre']) . '</td>
                <td class="etiqueta">Grupo:</td><td>' . htmlspecialchars($mod['grse_codigo']) . '</td>
            </tr>
            <tr>
                <td class="etiqueta">Docente:</td><td>' . htmlspecialchars($mod['doce_apellidos'] . ', ' . $mod['doce_nombres']) . '</td>
                <td class="etiqueta"></td><td></td>
            </tr>
        </table>
        <table class="ficha">
            <thead>
                <tr>
                    <th>N1<br>20%</th><th>Sup N1</th><th>N2<br>20%</th><th>Sup N2</th>
                    <th>N3<br>20%</th><th>N4<br>40%</th><th>Sup N4</th>
                    <th>Habilitación</th><th>Nota Final</th><th>Definitiva</th><th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="centro">' . fmtNota($mod['cali_n1']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_sup_n1']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_n2']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_sup_n2']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_n3']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_n4']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_sup_n4']) . '</td>
                    <td class="centro">' . fmtNota($mod['cali_habilitacion']) . '</td>
                    <td class="centro" style="' . colorSemaforo($mod['cali_nota_final']) . '">' . fmtNota($mod['cali_nota_final']) . '</td>
                    <td class="centro" style="' . colorSemaforo($mod['cali_definitiva']) . '">' . fmtNota($mod['cali_definitiva']) . '</td>
                    <td class="centro" style="background-color:' . $estado['color'] . ';">' . $estado['texto'] . '</td>
                </tr>
            </tbody>
        </table>';
    }
}

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
    table.estado { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
    table.estado td { border: 1px solid #adb5bd; padding: 8px; font-size: 12px; font-weight: bold; text-align: center; }
    table.contexto-modulo { width: 100%; border-collapse: collapse; margin-top: 14px; }
    table.contexto-modulo td { padding: 3px 6px; font-size: 10px; }
    table.contexto-modulo td.etiqueta { font-weight: bold; width: 90px; }
    table.ficha { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 4px; margin-bottom: 6px; }
    table.ficha th, table.ficha td { border: 1px solid #adb5bd; padding: 4px 3px; font-size: 8.5px; word-wrap: break-word; }
    table.ficha th { background-color: #343a40; color: #fff; text-align: center; width: 9.09%; }
    table.ficha td.centro { text-align: center; }
    .sin-modulos { text-align: center; color: #6c757d; margin: 20px 0; }
    .leyenda { margin-top: 14px; font-size: 9px; text-align: center; color: #495057; }
    .nota-supletorios {
        margin-top: 10px;
        font-size: 8px;
        color: #6c757d;
        padding: 0 4px;
    }
</style>
</head>
<body>
    <div class="codigo">GA-FO-04</div>
    <h1>' . htmlspecialchars($institucionNombre) . '</h1>
    <h2>BOLETÍN DE CALIFICACIONES</h2>
    <div class="meta">Generado el ' . $fechaGenerado . ' por el sistema</div>

    <table class="contexto">
        <tr>
            <td class="etiqueta">Estudiante:</td><td>' . htmlspecialchars($matricula['estu_apellidos'] . ', ' . $matricula['estu_nombres']) . '</td>
            <td class="etiqueta">Documento:</td><td>' . htmlspecialchars($matricula['estu_numerodoc']) . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Programa:</td><td>' . htmlspecialchars($matricula['prog_nombre']) . ' (' . htmlspecialchars($matricula['prog_sigla']) . ')</td>
            <td class="etiqueta">Cohorte:</td><td>' . htmlspecialchars($matricula['coho_codigo'] ?? '—') . '</td>
        </tr>
        <tr>
            <td class="etiqueta">Período:</td><td>' . htmlspecialchars($periodo['peri_codigo']) . '</td>
            <td class="etiqueta"></td><td></td>
        </tr>
    </table>

    <div class="formula">N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = Nota Final</div>

    <table class="estado">
        <tr>
            <td style="background-color:' . $estadoPeriodoColor . ';">Estado del Período: ' . $estadoPeriodoTexto .
                ($promedioPeriodo !== null ? ' (Promedio: ' . number_format($promedioPeriodo, 1) . ')' : '') . '</td>
        </tr>
    </table>

    ' . $modulosHtml . '

    <div class="leyenda">
        <span style="display:inline-block; width:10px; height:10px; background-color:#f8d7da; border-radius:50%;"></span> No aprobado (&lt; 3.0)
        &nbsp;&nbsp;
        <span style="display:inline-block; width:10px; height:10px; background-color:#d4edda; border-radius:50%;"></span> Aprobado (≥ 3.0)
    </div>

    <div class="nota-supletorios">
        Notas de recuperación (supletorio): se habilita solo si la nota original (N1, N2 o N4) es 0.0 — es decir, el estudiante no presentó la prueba. N3 no tiene supletorio, por corresponder a trabajos, actividades y exposiciones en clase. Nota Final = N1(20%)+N2(20%)+N3(20%)+N4(40%). Si es menor a 3.0, se activa Habilitación; la Definitiva es el valor oficial final.
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
