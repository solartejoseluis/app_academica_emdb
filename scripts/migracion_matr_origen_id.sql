-- =============================================================================
-- MIGRACIÓN — matriculas.matr_origen_id (Fase D, reincorporación de
-- estudiantes, commit 1). Fecha: 2026-10-11.
--
-- PROPÓSITO
--   Enlazar la matrícula NUEVA de un estudiante que se reincorpora con la
--   matrícula de ORIGEN (la que quedó 'retirado'), para que el cambio de
--   programa/cohorte quede trazable en el histórico sin depender de un texto
--   libre en matr_observacion. Agrega tres cosas a `matriculas`:
--     1. Columna  matr_origen_id  — mismo tipo exacto que matr_id (se lee de
--        information_schema en el propio servidor, no se asume), NULL por
--        defecto, ubicada después de coho_id.
--     2. UNIQUE   uq_matr_origen  — una matrícula solo puede ser origen de UNA
--        reincorporación. Los NULL no chocan entre sí.
--     3. FK       fk_matr_origen  — auto-referenciada a matriculas(matr_id),
--        ON DELETE SET NULL, ON UPDATE CASCADE. Usa el índice UNIQUE de
--        arriba; no se crea ningún índice adicional.
--
-- ORDEN DE DESPLIEGUE (en CADA entorno: local, dev/staging, producción)
--   1. mysqldump (o phpMyAdmin > Exportar) de ESTA base, con nombre fechado.
--   2. Ejecutar este script.
--   3. Revisar el resultado del "PASO 4 — COMPROBACIÓN" (3 filas con 1).
--   4. SOLO DESPUÉS subir el código de app/ que lea o escriba la columna.
--   Ejecutar este script ANTES del código: el código nuevo falla con
--   "Unknown column 'matr_origen_id'" si la columna no existe; el código
--   viejo, en cambio, funciona igual con la columna ya creada (ningún
--   INSERT/SELECT de app/ depende de la posición de las columnas).
--
-- CÓMO EJECUTARLO
--   - CLI:        mysql -u <usuario> -p <nombre_de_la_base> < migracion_matr_origen_id.sql
--   - phpMyAdmin: seleccionar primero la base en el panel izquierdo, luego
--                 pestaña Importar > elegir este archivo > Continuar.
--                 (También sirve pegarlo COMPLETO en la pestaña SQL, en un
--                 solo envío: las variables @... y los PREPARE viven solo
--                 dentro de la misma sesión.)
--   El script NO tiene "USE": el nombre de la base cambia por entorno (en
--   cPanel lleva el prefijo de la cuenta). Trabaja sobre la base seleccionada
--   (DATABASE()); si no hay ninguna seleccionada, se detiene en el PASO 0 sin
--   modificar nada.
--
-- IDEMPOTENTE Y PORTABLE
--   Puede ejecutarse varias veces sin error y sin duplicar nada: cada paso
--   consulta information_schema y solo crea lo que falta. No usa
--   "ADD COLUMN IF NOT EXISTS" (existe en MariaDB pero no en MySQL 8), sino
--   information_schema + PREPARE/EXECUTE, válido en MySQL 5.7/8.x y MariaDB
--   10.x. Sin triggers ni procedimientos almacenados.
--
-- REVERSIÓN: scripts/rollback_matr_origen_id.sql
-- =============================================================================


-- =============================================================================
-- PASO 0 — Base seleccionada y tipo exacto de matr_id
-- =============================================================================

SET @db := DATABASE();

SET @tipo_matr_id := (
    SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_id'
);

-- Si no hay base seleccionada o la tabla `matriculas` no existe en ella,
-- @tipo_matr_id queda NULL y esta sentencia falla a propósito (tabla
-- inexistente), deteniendo el script antes de cualquier cambio.
SET @sql := IF(@tipo_matr_id IS NULL,
    'SELECT * FROM `ERROR_seleccione_la_base_correcta_no_existe_matriculas`',
    'SELECT ''PASO 0: base y tabla matriculas encontradas'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 1 — Columna matr_origen_id (mismo tipo que matr_id, NULL por defecto)
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_origen_id'
);

SET @sql := IF(@existe = 0,
    CONCAT('ALTER TABLE `matriculas` ADD COLUMN `matr_origen_id` ', @tipo_matr_id,
           ' NULL DEFAULT NULL AFTER `coho_id`'),
    'SELECT ''PASO 1: la columna matr_origen_id ya existe, no se modifica'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 2 — UNIQUE uq_matr_origen (también sirve de índice para la FK)
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'uq_matr_origen'
);

SET @sql := IF(@existe = 0,
    'ALTER TABLE `matriculas` ADD UNIQUE KEY `uq_matr_origen` (`matr_origen_id`)',
    'SELECT ''PASO 2: el indice uq_matr_origen ya existe, no se modifica'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 3 — FK auto-referenciada fk_matr_origen
-- =============================================================================

SET @existe := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'matriculas'
      AND CONSTRAINT_NAME = 'fk_matr_origen' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql := IF(@existe = 0,
    'ALTER TABLE `matriculas` ADD CONSTRAINT `fk_matr_origen` FOREIGN KEY (`matr_origen_id`) REFERENCES `matriculas` (`matr_id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT ''PASO 3: la FK fk_matr_origen ya existe, no se modifica'' AS resultado');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- =============================================================================
-- PASO 4 — COMPROBACIÓN (no modifica nada). Las 3 filas deben mostrar 1.
-- =============================================================================

SELECT 'columna matr_origen_id' AS objeto, COUNT(*) AS existe
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND COLUMN_NAME = 'matr_origen_id'
UNION ALL
SELECT 'indice unico uq_matr_origen', COUNT(*)
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matriculas' AND INDEX_NAME = 'uq_matr_origen' AND NON_UNIQUE = 0
UNION ALL
SELECT 'FK fk_matr_origen', COUNT(*)
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'matriculas'
  AND CONSTRAINT_NAME = 'fk_matr_origen' AND CONSTRAINT_TYPE = 'FOREIGN KEY';
