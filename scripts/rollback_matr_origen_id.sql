-- =============================================================================
-- REVERSIÓN (rollback) — matriculas.matr_origen_id (Fase D, reincorporación
-- de estudiantes, commit 1). Fecha: 2026-10-11.
--
-- PROPÓSITO
--   Deshace scripts/migracion_matr_origen_id.sql: quita, en este orden, la FK
--   fk_matr_origen, el índice único uq_matr_origen y la columna
--   matr_origen_id. La tabla `matriculas` vuelve a su definición anterior.
--
-- ⚠️  PÉRDIDA DE DATOS: al eliminar la columna se pierde el enlace
--     matrícula nueva -> matrícula de origen de TODAS las reincorporaciones
--     ya registradas. Las matrículas en sí no se tocan. Hacer mysqldump (o
--     phpMyAdmin > Exportar) de ESTA base, con nombre fechado, ANTES de
--     ejecutarlo.
--
-- ORDEN DE DESPLIEGUE (inverso al de la migración)
--   1. PRIMERO retirar/revertir el código de app/ que use la columna.
--   2. mysqldump de esta base.
--   3. Ejecutar este script y revisar el "PASO 4 — COMPROBACIÓN" (3 filas
--      con 0).
--
-- CÓMO EJECUTARLO: igual que la migración — con la base ya seleccionada
--   (CLI: mysql -u <usuario> -p <base> < rollback_matr_origen_id.sql;
--   phpMyAdmin: seleccionar la base > Importar, o pegarlo completo en SQL en
--   un solo envío). No tiene "USE".
--
-- IDEMPOTENTE Y PORTABLE: puede ejecutarse varias veces sin error; cada paso
--   consulta information_schema y solo elimina lo que existe (misma técnica
--   information_schema + PREPARE/EXECUTE de la migración).
-- =============================================================================


-- =============================================================================
-- PASO 0 — Base seleccionada
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
-- PASO 1 — Quitar la FK fk_matr_origen (primero: depende del índice único)
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'matriculas'
      AND CONSTRAINT_NAME = 'fk_matr_origen' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(@existe > 0,
    'ALTER TABLE `matriculas` DROP FOREIGN KEY `fk_matr_origen`',
    'SELECT ''PASO 1: la FK fk_matr_origen no existe, nada que quitar'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 2 — Quitar el índice único uq_matr_origen
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'uq_matr_origen'
);

SET @sql := IF(@existe > 0,
    'ALTER TABLE `matriculas` DROP INDEX `uq_matr_origen`',
    'SELECT ''PASO 2: el indice uq_matr_origen no existe, nada que quitar'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 3 — Quitar la columna matr_origen_id
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_origen_id'
);

SET @sql := IF(@existe > 0,
    'ALTER TABLE `matriculas` DROP COLUMN `matr_origen_id`',
    'SELECT ''PASO 3: la columna matr_origen_id no existe, nada que quitar'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 4 — COMPROBACIÓN (no modifica nada). Las 3 filas deben mostrar 0.
-- =============================================================================

SELECT 'columna matr_origen_id' AS objeto, COUNT(*) AS existe
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_origen_id'
UNION ALL
SELECT 'indice unico uq_matr_origen', COUNT(*)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'uq_matr_origen'
UNION ALL
SELECT 'FK fk_matr_origen', COUNT(*)
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'matriculas'
  AND CONSTRAINT_NAME = 'fk_matr_origen' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
