<?php

function formatearUltimoAcceso(?string $ultimoAcceso, ?string $fechaCreacion): string {
    $valor = $ultimoAcceso ?? $fechaCreacion;
    if (!$valor) return '—';
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
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
