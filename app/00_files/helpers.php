<?php

function formatearUltimoAcceso(?string $ultimoAcceso, ?string $fechaCreacion): string {
    $valor = $ultimoAcceso ?? $fechaCreacion;
    if (!$valor) return '—';
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

// Texto de presentación de gruposemestres.grse_jornada. El valor guardado
// ('Sabados', sin tilde) no cambia: es el que usan los selects y la fórmula
// de grse_codigo. Mismo criterio que etiquetaJornada() en 00_files/helpers.js.
function etiquetaJornada(?string $valor): string {
    if ($valor === null || $valor === '') return 'Semana';
    if ($valor === 'Sabados') return 'Sábados';
    return $valor;
}

// Extraída del patrón ya usado en doc_mdl.php (case 'guardar', commit
// b6cc503): valida unicidad de usuarios.usua_email (excluyendo el propio
// usua_id) y lo actualiza. Debe ejecutarse SIEMPRE dentro de la transacción
// PDO del llamador — no abre ni cierra transacción propia. Lanza Exception
// (no PDOException) en caso de colisión, para que el llamador la capture y
// decida el formato de respuesta al usuario final.
function sincronizarEmailUsuario(PDO $pdo, ?int $usua_id, string $nuevoEmail): void {
    if ($usua_id === null) {
        return;
    }
    $check = $pdo->prepare("SELECT usua_id FROM usuarios WHERE usua_email = ? AND usua_id != ?");
    $check->execute([$nuevoEmail, $usua_id]);
    if ($check->fetch()) {
        throw new Exception('Ese correo electrónico ya está registrado por otro usuario');
    }
    $update = $pdo->prepare("UPDATE usuarios SET usua_email = ? WHERE usua_id = ?");
    $update->execute([$nuevoEmail, $usua_id]);
}

/**
 * Crea registros 'pendiente' en requisitos_estudiante para todas
 * las matrículas activas (matr_estado = 'matriculado') de un
 * programa que aún no tengan el requisito indicado. Se usa al
 * crear o reactivar un requisito en el catálogo, y también desde
 * el flujo de matrícula (Etapa 2.12.C).
 */
function backfillRequisitoAMatriculasActivas(PDO $pdo, int $prog_id, int $reqp_id): void
{
    $stmt = $pdo->prepare("
        INSERT INTO requisitos_estudiante (matr_id, reqp_id, reqe_estado)
        SELECT m.matr_id, ?, 'pendiente'
        FROM matriculas m
        WHERE m.prog_id = ?
          AND m.matr_estado = 'matriculado'
          AND NOT EXISTS (
              SELECT 1 FROM requisitos_estudiante re
              WHERE re.matr_id = m.matr_id AND re.reqp_id = ?
          )
    ");
    $stmt->execute([$reqp_id, $prog_id, $reqp_id]);
}

// ── Matrículas retiradas en las listas de un grupo módulo ────────────────────
// Regla única del proyecto: una fila de grmoestudiantes queda FUERA de las
// listas operativas (planilla, roster de N3, conteos) cuando el grupo es del
// período activo y el estudiante tiene, para el programa y el período de ese
// grupo, una matrícula en matr_estado = 'retirado' (Aplazamiento o Retirado).
// La condición es "está retirado", nunca "no está matriculado": las
// matrículas 'cursado' siguen apareciendo. Los grupos de períodos cerrados no
// se filtran: conservan su historial completo. No borra ni modifica nada:
// asignaciones, calificaciones y notas se conservan.
// Ambas funciones devuelven SQL fijo (sin datos del usuario) correlacionado
// con el alias de grmoestudiantes de la consulta que las usa ($ge); no
// necesitan ningún otro JOIN en esa consulta. La matrícula se resuelve por
// uq_matr_estu_peri_prog (estu_id, peri_id, prog_id): a lo sumo una fila.

// Condición para WHERE / ON / AND: verdadera si la fila NO está retirada.
function sqlNoRetiradoPeriodoActivo(string $ge = 'ge'): string {
    return "NOT EXISTS (
                SELECT 1
                FROM gruposmodulos rt_gm
                INNER JOIN gruposemestres rt_gs ON rt_gs.grse_id = rt_gm.grse_id
                INNER JOIN periodos rt_pe ON rt_pe.peri_id = rt_gs.peri_id AND rt_pe.peri_activo = 1
                INNER JOIN matriculas rt_mt ON rt_mt.estu_id = {$ge}.estu_id
                       AND rt_mt.peri_id = rt_gs.peri_id AND rt_mt.prog_id = rt_gs.prog_id
                       AND rt_mt.matr_estado = 'retirado'
                WHERE rt_gm.grmo_id = {$ge}.grmo_id
            )";
}

// Columna para SELECT: matr_estado_academico ('Aplazamiento' | 'Retirado') de
// esa misma matrícula retirada, o NULL si la fila no está retirada. Para los
// listados que SÍ muestran al retirado, marcado (reporte y PDF de grupo).
function sqlTipoRetiroPeriodoActivo(string $ge = 'ge'): string {
    return "(SELECT rt_mt.matr_estado_academico
                FROM gruposmodulos rt_gm
                INNER JOIN gruposemestres rt_gs ON rt_gs.grse_id = rt_gm.grse_id
                INNER JOIN periodos rt_pe ON rt_pe.peri_id = rt_gs.peri_id AND rt_pe.peri_activo = 1
                INNER JOIN matriculas rt_mt ON rt_mt.estu_id = {$ge}.estu_id
                       AND rt_mt.peri_id = rt_gs.peri_id AND rt_mt.prog_id = rt_gs.prog_id
                       AND rt_mt.matr_estado = 'retirado'
                WHERE rt_gm.grmo_id = {$ge}.grmo_id
                LIMIT 1)";
}

// Tipos de documento aceptados en estudiantes.estu_tipodoc. Única lista del
// servidor: los selects de est_view.php e insc_view.php ofrecen estos mismos
// valores; un tipo nuevo se agrega aquí y en los dos selects.
const TIPOS_DOCUMENTO_VALIDOS = ['CC', 'TI', 'CE', 'PA', 'NIT', 'PPT', 'RC'];

// Devuelve el tipo de documento si está en la lista (mayúsculas exactas) o
// null si no lo está.
function validarTipoDocumento(string $v): ?string {
    $v = trim($v);
    return in_array($v, TIPOS_DOCUMENTO_VALIDOS, true) ? $v : null;
}

// Quita espacios, guiones, puntos y paréntesis y devuelve el teléfono limpio
// si quedan solo dígitos, entre 7 y 15 (tamaño de estu_telefono); null si no.
function normalizarTelefono(string $v): ?string {
    $limpio = preg_replace('/[\s.\-()]/u', '', $v);
    return preg_match('/^[0-9]{7,15}$/', $limpio) ? $limpio : null;
}

// Corta la sesión de un usuario desactivado (usuarios.usua_activo = 0),
// eliminado, o cuyo role_id en la BD ya no coincide con el de la sesión (o
// la sesión no tiene role_id): al volver a entrar, el login arma la sesión
// del rol nuevo (doce_id, estu_id). Una consulta por petición, por clave
// primaria, sin caché: el bloqueo aplica en la petición siguiente al
// cambio. El mensaje no dice el motivo. Sin
// $_SESSION['usua_id'] no hace nada (cada endpoint responde su propio
// "Sesión no válida"). Para AJAX responde HTTP 200 con el envelope de error y
// codigo 'sesion_invalida', que helpers.js atiende de forma global; para
// vistas y PDF redirige al login.
function exigirUsuarioActivo(PDO $pdo, bool $esAjax): void {
    if (!isset($_SESSION['usua_id'])) {
        return;
    }
    $stmt = $pdo->prepare("SELECT usua_activo, role_id FROM usuarios WHERE usua_id = ?");
    $stmt->execute([(int)$_SESSION['usua_id']]);
    $fila = $stmt->fetch();
    if ($fila
        && (int)$fila['usua_activo'] === 1
        && isset($_SESSION['role_id'])
        && (int)$fila['role_id'] === (int)$_SESSION['role_id']) {
        return;
    }
    session_unset();
    session_destroy();
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'error',
            'codigo'  => 'sesion_invalida',
            'message' => 'Su sesión ya no es válida. Inicie sesión de nuevo.',
        ]);
        exit;
    }
    header('Location: /app_academica_emdb/app/01_login/login_view.php');
    exit;
}
