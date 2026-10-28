-- =============================================================================
-- backfill_requisitos_sucesoras.sql
-- -----------------------------------------------------------------------------
-- Completa requisitos_estudiante en las matrículas sucesoras creadas por
-- 'avanzar_semestre' antes de que ese case asignara requisitos.
--
-- Alcance: matrículas en matr_estado = 'matriculado' con matr_semestre > 1.
-- Para cada requisito ACTIVO de su programa (requisitos_programa.reqp_activo
-- = 1) que todavía no tenga fila, inserta una con el estado y la fecha de
-- entrega de la matrícula 'cursado' más reciente (mayor matr_id) del mismo
-- estudiante y programa, si esa matrícula tiene fila para el requisito; si
-- no la tiene, 'pendiente' sin fecha. Mismo criterio que 'avanzar_semestre'.
--
-- No toca matrículas 'cursado', 'retirado' ni 'aspirante', ni filas ya
-- existentes. Idempotente: el NOT EXISTS respeta uq_reqe_matr_reqp y una
-- segunda ejecución inserta 0 filas. Sin USE ni variables de usuario:
-- se ejecuta sobre la base ya seleccionada.
--
-- Orden: mysqldump de la base, luego las secciones 1 VISTA PREVIA,
-- 2 APLICACIÓN y 3 VERIFICACIÓN. La sección 4 REVERSA es opcional y está
-- comentada.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. VISTA PREVIA (solo lectura)
-- -----------------------------------------------------------------------------

-- 1.a Resumen por programa: matrículas afectadas y filas que se insertarían.
SELECT p.prog_sigla,
       COUNT(DISTINCT m.matr_id)                                   AS matriculas_afectadas,
       COUNT(*)                                                    AS filas_a_insertar,
       SUM(COALESCE(ant.reqe_estado, 'pendiente') = 'entregado')   AS filas_heredadas_entregado
FROM matriculas m
INNER JOIN programas p           ON p.prog_id = m.prog_id
INNER JOIN requisitos_programa rp ON rp.prog_id = m.prog_id AND rp.reqp_activo = 1
LEFT JOIN requisitos_estudiante ant
       ON ant.reqp_id = rp.reqp_id
      AND ant.matr_id = (SELECT a.matr_id FROM matriculas a
                         WHERE a.estu_id = m.estu_id AND a.prog_id = m.prog_id
                           AND a.matr_estado = 'cursado' AND a.matr_id <> m.matr_id
                         ORDER BY a.matr_id DESC LIMIT 1)
WHERE m.matr_estado = 'matriculado'
  AND m.matr_semestre > 1
  AND NOT EXISTS (SELECT 1 FROM requisitos_estudiante re
                  WHERE re.matr_id = m.matr_id AND re.reqp_id = rp.reqp_id)
GROUP BY p.prog_sigla
ORDER BY p.prog_sigla;

-- 1.b Lista de matr_id afectados, separada en dos grupos:
--     sin_filas = la matrícula no tiene ninguna fila hoy (todas las suyas
--                 las crearía el script);
--     parciales = ya tiene alguna fila y el script solo agrega las faltantes.
--     Guardar la lista 'sin_filas' si se quiere poder usar la REVERSA (sección 4).
SELECT CASE WHEN EXISTS (SELECT 1 FROM requisitos_estudiante re WHERE re.matr_id = m.matr_id)
            THEN 'parciales' ELSE 'sin_filas' END                 AS grupo,
       COUNT(*)                                                    AS matriculas,
       GROUP_CONCAT(m.matr_id ORDER BY m.matr_id)                  AS matr_ids
FROM matriculas m
WHERE m.matr_estado = 'matriculado'
  AND m.matr_semestre > 1
  AND EXISTS (SELECT 1 FROM requisitos_programa rp
              WHERE rp.prog_id = m.prog_id AND rp.reqp_activo = 1
                AND NOT EXISTS (SELECT 1 FROM requisitos_estudiante re
                                WHERE re.matr_id = m.matr_id AND re.reqp_id = rp.reqp_id))
GROUP BY grupo;


-- -----------------------------------------------------------------------------
-- 2. APLICACIÓN
-- -----------------------------------------------------------------------------
START TRANSACTION;

INSERT INTO requisitos_estudiante (matr_id, reqp_id, reqe_estado, reqe_fecha)
SELECT m.matr_id, rp.reqp_id, COALESCE(ant.reqe_estado, 'pendiente'), ant.reqe_fecha
FROM matriculas m
INNER JOIN requisitos_programa rp ON rp.prog_id = m.prog_id AND rp.reqp_activo = 1
LEFT JOIN requisitos_estudiante ant
       ON ant.reqp_id = rp.reqp_id
      AND ant.matr_id = (SELECT a.matr_id FROM matriculas a
                         WHERE a.estu_id = m.estu_id AND a.prog_id = m.prog_id
                           AND a.matr_estado = 'cursado' AND a.matr_id <> m.matr_id
                         ORDER BY a.matr_id DESC LIMIT 1)
WHERE m.matr_estado = 'matriculado'
  AND m.matr_semestre > 1
  AND NOT EXISTS (SELECT 1 FROM requisitos_estudiante re
                  WHERE re.matr_id = m.matr_id AND re.reqp_id = rp.reqp_id);

SELECT ROW_COUNT() AS filas_insertadas;

COMMIT;


-- -----------------------------------------------------------------------------
-- 3. VERIFICACIÓN (solo lectura)
-- -----------------------------------------------------------------------------

-- 3.a Debe devolver 0: sucesoras 'matriculado' con algún requisito activo sin fila.
SELECT COUNT(*) AS faltantes_restantes
FROM matriculas m
INNER JOIN requisitos_programa rp ON rp.prog_id = m.prog_id AND rp.reqp_activo = 1
WHERE m.matr_estado = 'matriculado'
  AND m.matr_semestre > 1
  AND NOT EXISTS (SELECT 1 FROM requisitos_estudiante re
                  WHERE re.matr_id = m.matr_id AND re.reqp_id = rp.reqp_id);

-- 3.b Sucesoras por programa: filas por matrícula frente al catálogo activo.
SELECT p.prog_sigla,
       COUNT(*) AS sucesoras,
       SUM((SELECT COUNT(*) FROM requisitos_estudiante re WHERE re.matr_id = m.matr_id)
           = (SELECT COUNT(*) FROM requisitos_programa rp
              WHERE rp.prog_id = m.prog_id AND rp.reqp_activo = 1)) AS completas,
       (SELECT COUNT(*) FROM requisitos_programa rp
        WHERE rp.prog_id = p.prog_id AND rp.reqp_activo = 1)        AS catalogo_activo
FROM matriculas m
INNER JOIN programas p ON p.prog_id = m.prog_id
WHERE m.matr_estado = 'matriculado' AND m.matr_semestre > 1
GROUP BY p.prog_id, p.prog_sigla
ORDER BY p.prog_sigla;


-- -----------------------------------------------------------------------------
-- 4. REVERSA (opcional — comentada a propósito)
-- -----------------------------------------------------------------------------
-- Criterio: se borran solo filas que el script pudo haber creado, es decir
--   (a) de una matrícula de la lista 'sin_filas' guardada en la vista previa
--       1.b (antes del script no tenía ninguna fila, así que todas sus filas
--       actuales salieron de la aplicación), y
--   (b) cuyo reqe_estado/reqe_fecha siguen siendo los que puso el script
--       ('pendiente' sin fecha, o el estado y la fecha heredados de la
--       matrícula 'cursado' más reciente). Una fila que el coordinador ya
--       cambió después del backfill no coincide y se conserva.
-- Las matrículas 'parciales' no se incluyen: sus filas previas no se
-- distinguen de las del script. Revertirlas, si hiciera falta, es manual.
-- Uso: reemplazar 0 por la lista 'sin_filas' (ej. 101,102,103) y
-- descomentar. Con 0 no borra nada.
--
-- START TRANSACTION;
-- DELETE re
-- FROM requisitos_estudiante re
-- INNER JOIN matriculas m ON m.matr_id = re.matr_id
-- LEFT JOIN requisitos_estudiante ant
--        ON ant.reqp_id = re.reqp_id
--       AND ant.matr_id = (SELECT a.matr_id FROM matriculas a
--                          WHERE a.estu_id = m.estu_id AND a.prog_id = m.prog_id
--                            AND a.matr_estado = 'cursado' AND a.matr_id <> m.matr_id
--                          ORDER BY a.matr_id DESC LIMIT 1)
-- WHERE re.matr_id IN (0)
--   AND m.matr_estado = 'matriculado'
--   AND m.matr_semestre > 1
--   AND re.reqe_estado = COALESCE(ant.reqe_estado, 'pendiente')
--   AND re.reqe_fecha <=> ant.reqe_fecha;
-- SELECT ROW_COUNT() AS filas_borradas;
-- COMMIT;
