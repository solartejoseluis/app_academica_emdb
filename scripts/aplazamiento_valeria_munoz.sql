-- =============================================================================
-- CORRECCIÓN DE DATOS — matrícula de Valeria Muñoz Gómez: estado académico
-- 'Retirado' -> 'Aplazamiento' (Fase D, reincorporación de estudiantes,
-- commit 2). Fecha: 2026-10-11.
--
-- PROPÓSITO
--   La pestaña "Retirados" (commits siguientes de la Fase D) listará las
--   matrículas con matr_estado = 'retirado' AND matr_estado_academico =
--   'Aplazamiento'. La matrícula 2026-1 de Valeria Muñoz Gómez (programa
--   ASO2016, cohorte CH-ASO-2025B) está hoy en retirado + Retirado. Este
--   script la deja en retirado + Aplazamiento y anota el cambio al final de
--   matr_observacion, sin borrar el texto que ya tenga.
--     - matr_estado NO cambia (sigue 'retirado').
--     - Solo toca UNA fila de `matriculas`. No toca grmoestudiantes,
--       calificaciones ni ninguna otra tabla.
--
-- IDENTIFICACIÓN POR CLAVE NATURAL (no por matr_id: los ids difieren entre
-- local, dev y producción)
--   estudiantes.estu_apellidos = 'MUÑOZ GOMEZ'  y  estu_nombres = 'VALERIA'
--   programas.prog_sigla       = 'ASO2016'
--   periodos.peri_codigo       = '2026-1'
--   cohortes.coho_codigo       = 'CH-ASO-2025B'
--   matriculas.matr_estado     = 'retirado'
--   Las columnas de texto son utf8mb4_unicode_ci: la comparación no
--   distingue mayúsculas ni tildes ('MUÑOZ GOMEZ' también encuentra
--   'Muñoz Gómez'). En la base local está guardado 'MUÑOZ GOMEZ', sin tilde
--   en GOMEZ.
--
-- ORDEN DE DESPLIEGUE (en CADA entorno: local, dev/staging, producción)
--   1. mysqldump (o phpMyAdmin > Exportar) de ESTA base, con nombre fechado.
--   2. Ejecutar este script.
--   3. Revisar el "PASO 3": la fila DESPUÉS y la comprobación final
--      (matricula_en_aplazamiento = 1, anotacion_presente = 1).
--   Es indiferente ejecutarlo antes o después de subir el código de la
--   Fase D: no depende de la columna matr_origen_id ni de ningún código
--   nuevo. Hasta que exista la pestaña "Retirados", el único efecto visible
--   es el estado académico de esa fila en "Matriculados (Per. Anteriores)".
--
-- CÓMO EJECUTARLO
--   - CLI:        mysql -u <usuario> -p <nombre_de_la_base> < aplazamiento_valeria_munoz.sql
--   - phpMyAdmin: seleccionar primero la base en el panel izquierdo, luego
--                 pestaña Importar > elegir este archivo (juego de
--                 caracteres utf-8) > Continuar. También sirve pegarlo
--                 COMPLETO en la pestaña SQL, en un solo envío: las
--                 variables @... viven solo dentro de la misma sesión.
--   El script NO tiene "USE": trabaja sobre la base seleccionada.
--
-- SEGURO E IDEMPOTENTE
--   - Se detiene con un error explícito, SIN modificar nada, si no hay base
--     seleccionada, si no encuentra la matrícula o si encuentra más de una.
--     El "error" es intencional: un SELECT sobre una tabla inexistente cuyo
--     nombre explica la causa (misma técnica de migracion_matr_origen_id.sql).
--   - Puede ejecutarse varias veces: si la matrícula ya está en
--     'Aplazamiento' no modifica nada y lo informa; la anotación nunca se
--     duplica.
--   - La anotación se escribe junto con el cambio de estado. Si alguien ya
--     hubiera pasado la matrícula a 'Aplazamiento' por otra vía, el script
--     no agrega la anotación (no modifica nada).
--   - La fecha de la anotación es fija ([2026-10-11], fecha de la decisión),
--     no la fecha en que se ejecute el script en cada entorno.
--
-- REVERSIÓN MANUAL (hacer mysqldump antes). Devuelve el estado académico a
-- 'Retirado' y quita la anotación, dejando la observación como estaba.
-- Ejecutar con la base seleccionada, quitando los "-- " del inicio:
--
-- SET NAMES utf8mb4;
-- UPDATE matriculas m
-- INNER JOIN estudiantes e ON e.estu_id = m.estu_id
-- INNER JOIN programas p   ON p.prog_id = m.prog_id
-- INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
-- INNER JOIN cohortes c    ON c.coho_id = m.coho_id
-- SET m.matr_estado_academico = 'Retirado',
--     m.matr_observacion = NULLIF(REPLACE(REPLACE(m.matr_observacion,
--         ' | [2026-10-11] Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.', ''),
--         '[2026-10-11] Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.', ''), '')
-- WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
--   AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
--   AND c.coho_codigo = 'CH-ASO-2025B'
--   AND m.matr_estado = 'retirado' AND m.matr_estado_academico = 'Aplazamiento';
--
--   Debe informar 1 fila afectada (0 si ya estaba revertida).
-- =============================================================================

SET NAMES utf8mb4;


-- =============================================================================
-- PASO 0 — Base seleccionada y tabla matriculas presente
-- =============================================================================

SET @db := DATABASE();

SET @tipo_matr_id := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_id'
);

-- Sin base seleccionada (o sin tabla `matriculas`), falla a propósito aquí,
-- antes de cualquier cambio.
SET @sql := IF(@tipo_matr_id IS NULL,
    'SELECT * FROM `ERROR_seleccione_la_base_correcta_no_existe_matriculas`',
    'SELECT ''PASO 0: base y tabla matriculas encontradas'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 1 — Fila candidata ANTES (no modifica nada) y validación
-- =============================================================================

-- La(s) matrícula(s) que coinciden con la clave natural, en cualquier estado
-- académico. Debe salir exactamente UNA fila.
SELECT 'ANTES' AS momento, m.matr_id, e.estu_id, e.estu_apellidos, e.estu_nombres,
       p.prog_sigla, c.coho_codigo, pe.peri_codigo,
       m.matr_estado, m.matr_estado_academico, m.matr_semestre, m.matr_observacion
FROM matriculas m
INNER JOIN estudiantes e ON e.estu_id = m.estu_id
INNER JOIN programas p   ON p.prog_id = m.prog_id
INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
INNER JOIN cohortes c    ON c.coho_id = m.coho_id
WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
  AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
  AND c.coho_codigo = 'CH-ASO-2025B'
  AND m.matr_estado = 'retirado';

-- Pendientes de cambiar (retirado + Retirado)
SET @n_pendientes := (
    SELECT COUNT(*)
    FROM matriculas m
    INNER JOIN estudiantes e ON e.estu_id = m.estu_id
    INNER JOIN programas p   ON p.prog_id = m.prog_id
    INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
    INNER JOIN cohortes c    ON c.coho_id = m.coho_id
    WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
      AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
      AND c.coho_codigo = 'CH-ASO-2025B'
      AND m.matr_estado = 'retirado' AND m.matr_estado_academico = 'Retirado'
);

-- Ya cambiadas (retirado + Aplazamiento)
SET @n_hechas := (
    SELECT COUNT(*)
    FROM matriculas m
    INNER JOIN estudiantes e ON e.estu_id = m.estu_id
    INNER JOIN programas p   ON p.prog_id = m.prog_id
    INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
    INNER JOIN cohortes c    ON c.coho_id = m.coho_id
    WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
      AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
      AND c.coho_codigo = 'CH-ASO-2025B'
      AND m.matr_estado = 'retirado' AND m.matr_estado_academico = 'Aplazamiento'
);

-- Solo hay dos situaciones válidas: exactamente 1 pendiente y 0 hechas
-- (se aplica el cambio), o 0 pendientes y exactamente 1 hecha (ya estaba).
-- Cualquier otra combinación detiene el script aquí, sin modificar nada.
SET @sql := CASE
    WHEN @n_pendientes + @n_hechas = 0
        THEN 'SELECT * FROM `ERROR_no_se_encontro_la_matricula_retirada_de_Valeria_Munoz`'
    WHEN @n_pendientes + @n_hechas > 1
        THEN 'SELECT * FROM `ERROR_hay_mas_de_una_matricula_candidata_no_se_modifica_nada`'
    WHEN @n_pendientes = 1
        THEN 'SELECT ''PASO 1: 1 matricula candidata en retirado + Retirado, se va a cambiar'' AS resultado'
    ELSE 'SELECT ''PASO 1: la matricula ya estaba en Aplazamiento, no se va a modificar'' AS resultado'
END;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- matr_id de la única fila válida. Queda NULL (y el UPDATE del PASO 2 no
-- encuentra nada) si la validación de arriba no dio exactamente una fila.
SET @matr_id := (
    SELECT IF(COUNT(*) = 1, MIN(m.matr_id), NULL)
    FROM matriculas m
    INNER JOIN estudiantes e ON e.estu_id = m.estu_id
    INNER JOIN programas p   ON p.prog_id = m.prog_id
    INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
    INNER JOIN cohortes c    ON c.coho_id = m.coho_id
    WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
      AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
      AND c.coho_codigo = 'CH-ASO-2025B'
      AND m.matr_estado = 'retirado'
      AND m.matr_estado_academico IN ('Retirado', 'Aplazamiento')
);


-- =============================================================================
-- PASO 2 — Cambio de estado académico + anotación en matr_observacion
-- =============================================================================

-- Solo actúa sobre la fila en retirado + Retirado. matr_estado no se toca.
-- La anotación se agrega al final del texto actual (o queda sola si la
-- observación estaba vacía) y solo si el texto no la contiene ya.
UPDATE matriculas
SET matr_estado_academico = 'Aplazamiento',
    matr_observacion = CASE
        WHEN matr_observacion LIKE '%Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.%'
            THEN matr_observacion
        WHEN matr_observacion IS NULL OR matr_observacion = ''
            THEN '[2026-10-11] Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.'
        ELSE CONCAT(matr_observacion,
            ' | [2026-10-11] Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.')
    END
WHERE matr_id = @matr_id
  AND matr_estado = 'retirado'
  AND matr_estado_academico = 'Retirado';

SET @filas := ROW_COUNT();

SELECT CASE
    WHEN @filas = 1 THEN 'PASO 2: estado academico cambiado de Retirado a Aplazamiento (1 fila)'
    WHEN @n_hechas = 1 THEN 'PASO 2: ya estaba en Aplazamiento, no se modifico nada'
    ELSE 'PASO 2: no se modifico nada'
END AS resultado;


-- =============================================================================
-- PASO 3 — Fila DESPUÉS y comprobación final (no modifica nada)
-- =============================================================================

SELECT 'DESPUES' AS momento, m.matr_id, e.estu_id, e.estu_apellidos, e.estu_nombres,
       p.prog_sigla, c.coho_codigo, pe.peri_codigo,
       m.matr_estado, m.matr_estado_academico, m.matr_semestre, m.matr_observacion
FROM matriculas m
INNER JOIN estudiantes e ON e.estu_id = m.estu_id
INNER JOIN programas p   ON p.prog_id = m.prog_id
INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
INNER JOIN cohortes c    ON c.coho_id = m.coho_id
WHERE m.matr_id = @matr_id;

-- Las dos columnas deben mostrar 1.
SELECT COUNT(*) AS matricula_en_aplazamiento,
       COALESCE(SUM(m.matr_observacion LIKE '%Estado académico cambiado de Retirado a Aplazamiento para su reincorporación.%'), 0) AS anotacion_presente
FROM matriculas m
INNER JOIN estudiantes e ON e.estu_id = m.estu_id
INNER JOIN programas p   ON p.prog_id = m.prog_id
INNER JOIN periodos pe   ON pe.peri_id = m.peri_id
INNER JOIN cohortes c    ON c.coho_id = m.coho_id
WHERE e.estu_apellidos = 'MUÑOZ GOMEZ' AND e.estu_nombres = 'VALERIA'
  AND p.prog_sigla = 'ASO2016' AND pe.peri_codigo = '2026-1'
  AND c.coho_codigo = 'CH-ASO-2025B'
  AND m.matr_estado = 'retirado' AND m.matr_estado_academico = 'Aplazamiento';
