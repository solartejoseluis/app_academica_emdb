-- =============================================================================
-- Restauración de 11 CHECK constraints eliminadas accidentalmente por el
-- commit 300cfc1 (2026-09-06, "Reemplaza seed público con dataset
-- anonimizado") al reemplazar database/emdb_academica.sql por un mysqldump
-- generado desde una BD que ya no las tenía. Detalle completo de la
-- investigación de causa raíz en CHANGELOG.md / PROJECT_CONTEXT.md, entrada
-- de restauración de CHECK constraints (2026-09-08).
--
-- ENTORNO: STAGING (hosting de pruebas, dev.escuelamdb.com / aurusmind.com)
--
-- ⚠️  ANTES DE CORRER ESTO: mysqldump de ESTA base específica primero.
--     (En phpMyAdmin: Exportar → Método rápido → SQL → Continuar, o el
--     script scripts/export_para_hosting.sh si se ejecuta desde una
--     terminal con acceso directo al servidor.)
--
-- ⚠️  Este hosting corre MariaDB (confirmado en CLAUDE.md por la
--     incompatibilidad de colación utf8mb4_0900_ai_ci). MariaDB soporta y
--     enforce CHECK constraints desde la versión 10.2.1 — si la versión de
--     este hosting es anterior, los ALTER TABLE del Paso 2 pueden fallar o
--     (en versiones muy antiguas) aceptarse sin error pero sin aplicarse.
--     Verificar `SELECT VERSION();` antes de continuar si hay duda.
--
-- FLUJO:
--   1. Pegar y correr el "PASO 1 — VERIFICACIÓN" completo.
--   2. Revisar que las 11 filas de resultado tengan violaciones = 0.
--      Si CUALQUIERA es > 0: DETENERSE. No correr el Paso 2. Reportar cuál
--      columna tiene datos fuera de rango antes de decidir qué hacer con
--      esos registros (corregirlos manualmente o excluirlos del ALTER).
--   3. Solo si las 11 dieron 0, pegar y correr el "PASO 2 — ALTER TABLE".
--   4. Verificar con SHOW CREATE TABLE calificaciones / periodos /
--      horariosgrupo / configuracion que las CONSTRAINT quedaron listadas.
-- =============================================================================


-- =============================================================================
-- PASO 1 — VERIFICACIÓN (correr primero, no modifica nada)
-- =============================================================================

SELECT 'cali_n1' AS check_name, COUNT(*) AS violaciones
FROM calificaciones WHERE cali_n1 IS NOT NULL AND (cali_n1 < 0.0 OR cali_n1 > 5.0)
UNION ALL
SELECT 'cali_n2', COUNT(*) FROM calificaciones WHERE cali_n2 IS NOT NULL AND (cali_n2 < 0.0 OR cali_n2 > 5.0)
UNION ALL
SELECT 'cali_n3', COUNT(*) FROM calificaciones WHERE cali_n3 IS NOT NULL AND (cali_n3 < 0.0 OR cali_n3 > 5.0)
UNION ALL
SELECT 'cali_n4', COUNT(*) FROM calificaciones WHERE cali_n4 IS NOT NULL AND (cali_n4 < 0.0 OR cali_n4 > 5.0)
UNION ALL
SELECT 'cali_sup_n1', COUNT(*) FROM calificaciones WHERE cali_sup_n1 IS NOT NULL AND (cali_sup_n1 < 0.0 OR cali_sup_n1 > 5.0)
UNION ALL
SELECT 'cali_sup_n2', COUNT(*) FROM calificaciones WHERE cali_sup_n2 IS NOT NULL AND (cali_sup_n2 < 0.0 OR cali_sup_n2 > 5.0)
UNION ALL
SELECT 'cali_sup_n4', COUNT(*) FROM calificaciones WHERE cali_sup_n4 IS NOT NULL AND (cali_sup_n4 < 0.0 OR cali_sup_n4 > 5.0)
UNION ALL
SELECT 'cali_habilitacion', COUNT(*) FROM calificaciones WHERE cali_habilitacion IS NOT NULL AND (cali_habilitacion < 0.0 OR cali_habilitacion > 5.0)
UNION ALL
SELECT 'peri_semestre', COUNT(*) FROM periodos WHERE peri_semestre NOT IN (1,2)
UNION ALL
SELECT 'hora_diasemana', COUNT(*) FROM horariosgrupo WHERE hora_diasemana < 1 OR hora_diasemana > 7
UNION ALL
SELECT 'config_id', COUNT(*) FROM configuracion WHERE config_id <> 1;

-- Las 11 filas de "violaciones" deben ser 0 antes de continuar al Paso 2.


-- =============================================================================
-- PASO 2 — ALTER TABLE (correr SOLO si las 11 verificaciones del Paso 1
-- dieron 0). Aplicar uno por uno si se prefiere ir verificando cada uno con
-- SHOW CREATE TABLE, o el bloque completo de una vez.
-- =============================================================================

ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_n1 CHECK (cali_n1 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_n2 CHECK (cali_n2 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_n3 CHECK (cali_n3 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_n4 CHECK (cali_n4 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_sup_n1 CHECK (cali_sup_n1 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_sup_n2 CHECK (cali_sup_n2 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_sup_n4 CHECK (cali_sup_n4 BETWEEN 0.0 AND 5.0);
ALTER TABLE calificaciones ADD CONSTRAINT chk_cali_habilitacion CHECK (cali_habilitacion BETWEEN 0.0 AND 5.0);
ALTER TABLE periodos ADD CONSTRAINT chk_peri_semestre CHECK (peri_semestre IN (1, 2));
ALTER TABLE horariosgrupo ADD CONSTRAINT chk_hora_diasemana CHECK (hora_diasemana BETWEEN 1 AND 7);
ALTER TABLE configuracion ADD CONSTRAINT chk_configuracion_fila_unica CHECK (config_id = 1);


-- =============================================================================
-- PASO 3 — VERIFICACIÓN POST-ALTER (opcional pero recomendado)
-- =============================================================================

SELECT tc.TABLE_NAME, tc.CONSTRAINT_NAME, cc.CHECK_CLAUSE
FROM information_schema.TABLE_CONSTRAINTS tc
JOIN information_schema.CHECK_CONSTRAINTS cc
  ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
WHERE tc.CONSTRAINT_SCHEMA = DATABASE() AND tc.CONSTRAINT_TYPE = 'CHECK'
ORDER BY tc.TABLE_NAME, tc.CONSTRAINT_NAME;

-- Deben aparecer 11 filas nuevas (chk_cali_n1..n4, chk_cali_sup_n1/n2/n4,
-- chk_cali_habilitacion, chk_peri_semestre, chk_hora_diasemana,
-- chk_configuracion_fila_unica), más notasn3_chk_1 si esa tabla ya existe
-- en este entorno.
