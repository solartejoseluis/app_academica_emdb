-- ============================================================================
-- Fase E: fechas por nota en calificaciones (8 columnas)
--
-- Guarda la última vez que cambió cada nota, sin historial. Las columnas no se
-- muestran en ninguna pantalla, reporte ni PDF.
--
-- ORDEN DE DESPLIEGUE, en cada entorno (local, dev, producción):
--   1. mysqldump o exportación de la base del entorno.
--   2. Este script en la base del entorno.
--   3. Después, el código (app/05_calificaciones/calificaciones_mdl.php).
-- El código nuevo falla con "Unknown column" si las columnas todavía no existen.
-- El código anterior sigue funcionando con las columnas ya creadas.
--
-- CÓMO EJECUTARLO: seleccione primero la base en phpMyAdmin (el script no lleva
-- USE) y ejecute bloque por bloque, en orden:
--   Bloque 1: debe devolver 0 filas. Si devuelve alguna, NO ejecute el bloque 2.
--   Bloque 2: agrega las 8 columnas.
--   Bloque 3: todas las filas deben decir 'ok' y el total debe ser 8.
--   Bloque 4: reversa, comentada. Solo si hay que deshacer, y con el código
--             anterior ya restaurado.
--
-- IDEMPOTENCIA: la da el bloque 1. MySQL 8.0 (local) no admite
-- ADD COLUMN IF NOT EXISTS. Si el bloque 2 se ejecuta dos veces falla con
-- "Duplicate column name" sin cambiar nada.
--
-- TIMESTAMP NULL DEFAULT NULL se declara explícito en las 8 columnas: en MariaDB
-- un TIMESTAMP sin esa declaración podría recibir un ON UPDATE automático.
--
-- HISTÓRICOS: las filas existentes quedan con NULL en las 8 columnas. No se
-- inventan fechas. Las notas conservan sus valores.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- BLOQUE 1: vista previa. Cuáles de las 8 columnas ya existen. Esperado: 0 filas.
-- ----------------------------------------------------------------------------
SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'calificaciones'
  AND COLUMN_NAME IN ('fecha_n1', 'fecha_n2', 'fecha_n3', 'fecha_n4',
                      'fecha_sup_n1', 'fecha_sup_n2', 'fecha_sup_n4',
                      'fecha_habilitacion');


-- ----------------------------------------------------------------------------
-- BLOQUE 2: aplicación.
-- ----------------------------------------------------------------------------
ALTER TABLE calificaciones
  ADD COLUMN fecha_n1           TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_n2           TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_n3           TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_n4           TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_sup_n1       TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_sup_n2       TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_sup_n4       TIMESTAMP NULL DEFAULT NULL,
  ADD COLUMN fecha_habilitacion TIMESTAMP NULL DEFAULT NULL;


-- ----------------------------------------------------------------------------
-- BLOQUE 3: verificación. Esperado: 8 filas con estado 'ok'.
-- MariaDB informa el default nulo como el texto 'NULL'; MySQL, como NULL.
-- ----------------------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
       IF(DATA_TYPE = 'timestamp'
          AND IS_NULLABLE = 'YES'
          AND (COLUMN_DEFAULT IS NULL OR COLUMN_DEFAULT = 'NULL')
          AND EXTRA NOT LIKE '%on update%', 'ok', 'REVISAR') AS estado
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'calificaciones'
  AND COLUMN_NAME IN ('fecha_n1', 'fecha_n2', 'fecha_n3', 'fecha_n4',
                      'fecha_sup_n1', 'fecha_sup_n2', 'fecha_sup_n4',
                      'fecha_habilitacion')
ORDER BY ORDINAL_POSITION;

-- Esperado: columnas_correctas = 8.
SELECT COUNT(*) AS columnas_correctas
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'calificaciones'
  AND COLUMN_NAME IN ('fecha_n1', 'fecha_n2', 'fecha_n3', 'fecha_n4',
                      'fecha_sup_n1', 'fecha_sup_n2', 'fecha_sup_n4',
                      'fecha_habilitacion')
  AND DATA_TYPE = 'timestamp'
  AND IS_NULLABLE = 'YES'
  AND (COLUMN_DEFAULT IS NULL OR COLUMN_DEFAULT = 'NULL')
  AND EXTRA NOT LIKE '%on update%';


-- ----------------------------------------------------------------------------
-- BLOQUE 4: reversa. Quita las 8 columnas y pierde las fechas ya registradas.
-- ----------------------------------------------------------------------------
-- ALTER TABLE calificaciones
--   DROP COLUMN fecha_n1,
--   DROP COLUMN fecha_n2,
--   DROP COLUMN fecha_n3,
--   DROP COLUMN fecha_n4,
--   DROP COLUMN fecha_sup_n1,
--   DROP COLUMN fecha_sup_n2,
--   DROP COLUMN fecha_sup_n4,
--   DROP COLUMN fecha_habilitacion;
