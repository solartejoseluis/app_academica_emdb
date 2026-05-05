-- =============================================================================
-- emdb_academica.sql
-- Base de datos del Sistema de Gestión Académica
-- Escuela de Mecánica Dental Bolaños (EMDB) — Tuluá, Valle del Cauca
-- Proyecto de Grado — Ingeniería de Sistemas, UNAD CEAD Palmira
-- Estudiante: Jose Luis Solarte Orozco — CC 76.322.816
-- Versión: 1.0.0 — 2026-04-30
-- Compatible con: MySQL 8.0 / MariaDB 10.6+
-- Instrucciones: Ejecutar en phpMyAdmin o terminal MySQL como root/admin
-- =============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 0. CREAR Y SELECCIONAR BASE DE DATOS
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS emdb_academica
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE emdb_academica;

-- =============================================================================
-- BLOQUE 1: TABLAS DE CATÁLOGO (sin dependencias externas)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. roles
--    Catálogo de roles de acceso al sistema.
--    role_id: 1=Administrador, 2=Coordinador, 3=Docente, 4=Estudiante
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS roles;
CREATE TABLE roles (
  role_id   TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_nombre VARCHAR(50)    NOT NULL,
  PRIMARY KEY (role_id),
  UNIQUE KEY uq_role_nombre (role_nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de roles de acceso al sistema';

-- -----------------------------------------------------------------------------
-- 2. programas
--    Oferta académica institucional: ASO y MD.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS programas;
CREATE TABLE programas (
  prog_id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prog_nombre      VARCHAR(120)      NOT NULL,
  prog_sigla       VARCHAR(10)       NOT NULL,
  prog_resolucion  VARCHAR(80)       DEFAULT NULL,
  prog_vigencia    DATE             DEFAULT NULL,
  prog_duracion_semestres TINYINT UNSIGNED NOT NULL DEFAULT 4,
  PRIMARY KEY (prog_id),
  UNIQUE KEY uq_prog_sigla (prog_sigla)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Programas académicos ofertados por la EMDB';

-- -----------------------------------------------------------------------------
-- 3. periodos
--    Períodos académicos semestrales.
--    peri_semestre: 1 = primer semestre, 2 = segundo semestre del año.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS periodos;
CREATE TABLE periodos (
  peri_id        SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  peri_codigo    VARCHAR(10)       NOT NULL,   -- Ej: "2025-1", "2025-2"
  peri_anio      YEAR              NOT NULL,
  peri_semestre  TINYINT UNSIGNED  NOT NULL CHECK (peri_semestre IN (1, 2)),
  fechainicio    DATE              DEFAULT NULL,
  fechafin       DATE              DEFAULT NULL,
  PRIMARY KEY (peri_id),
  UNIQUE KEY uq_peri_codigo (peri_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Períodos académicos semestrales';

-- =============================================================================
-- BLOQUE 2: TABLAS DE USUARIOS Y PERSONAS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 4. usuarios
--    Credenciales de acceso al sistema. Un usuario puede ser docente O
--    estudiante según el role_id. El rol se detecta automáticamente en login;
--    NO existe selector de rol en la pantalla de ingreso.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS usuarios;
CREATE TABLE usuarios (
  usua_id           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  role_id           TINYINT UNSIGNED NOT NULL,
  usua_login        VARCHAR(20)      DEFAULT NULL,
  usua_email        VARCHAR(100)     NOT NULL,
  usua_passwordhash VARCHAR(255)     NOT NULL,
  usua_activo       TINYINT(1)       NOT NULL DEFAULT 1,
  fechacreacion     TIMESTAMP        DEFAULT current_timestamp(),
  PRIMARY KEY (usua_id),
  UNIQUE KEY uq_usua_email (usua_email),
  UNIQUE KEY uq_usua_login (usua_login),
  CONSTRAINT fk_usua_role FOREIGN KEY (role_id) REFERENCES roles (role_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Credenciales de acceso al sistema';

-- -----------------------------------------------------------------------------
-- 5. cohortes
--    Grupo de estudiantes que inician el programa en la misma fecha.
--    Relacionado con prog_id para distinguir cohortes ASO vs MD.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS cohortes;
CREATE TABLE cohortes (
  coho_id      SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prog_id      SMALLINT UNSIGNED NOT NULL,
  coho_codigo  VARCHAR(20)       NOT NULL,   -- Ej: "ASO-2024-1"
  fechainicio  DATE              NOT NULL,
  coho_activa  TINYINT(1)        NOT NULL DEFAULT 1,
  coho_jornada     VARCHAR(20)      DEFAULT 'Semana',
  PRIMARY KEY (coho_id),
  UNIQUE KEY uq_coho_codigo (coho_codigo),
  CONSTRAINT fk_coho_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cohortes de ingreso por programa';

-- -----------------------------------------------------------------------------
-- 6. docentes
--    Perfil del docente. Ligado a un usuario del sistema.
--    doce_sigla: abreviatura usada en planillas (3-4 caracteres).
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS docentes;
CREATE TABLE docentes (
  doce_id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usua_id       INT UNSIGNED      DEFAULT NULL,   -- NULL si no tiene acceso al sistema
  doce_nombres  VARCHAR(80)       NOT NULL,
  doce_apellidos VARCHAR(80)      NOT NULL,
  doce_cedula   VARCHAR(15)       DEFAULT NULL,
  doce_sigla    VARCHAR(6)        NOT NULL,
  doce_activo   TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (doce_id),
  UNIQUE KEY uq_doce_sigla (doce_sigla),
  UNIQUE KEY uq_doce_cedula (doce_cedula),
  CONSTRAINT fk_doce_usua FOREIGN KEY (usua_id) REFERENCES usuarios (usua_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Datos de docentes de la EMDB';

-- -----------------------------------------------------------------------------
-- 7. estudiantes
--    Perfil del estudiante. Ligado a usuario y cohorte.
--    El ciclo aspirante → matriculado se maneja en tabla matriculas.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS estudiantes;
CREATE TABLE estudiantes (
  estu_id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  usua_id              INT UNSIGNED      DEFAULT NULL,
  coho_id              SMALLINT UNSIGNED DEFAULT NULL,
  estu_tipodoc         VARCHAR(20)       DEFAULT NULL,
  estu_numerodoc       VARCHAR(20)       DEFAULT NULL,
  estu_expedidoen      VARCHAR(60)       DEFAULT NULL,
  estu_nombres         VARCHAR(80)       NOT NULL,
  estu_apellidos       VARCHAR(80)       NOT NULL,
  estu_ciudadnac       VARCHAR(60)       DEFAULT NULL,
  fechanacimiento      DATE              DEFAULT NULL,
  estu_sexo            VARCHAR(20)       DEFAULT NULL,
  estu_telefono        VARCHAR(15)       DEFAULT NULL,
  estu_email           VARCHAR(100)      DEFAULT NULL,
  estu_ocupacion       VARCHAR(80)       DEFAULT NULL,
  estu_direccion       VARCHAR(120)      DEFAULT NULL,
  estu_barrio          VARCHAR(60)       DEFAULT NULL,
  estu_ciudad          VARCHAR(60)       DEFAULT NULL,
  estu_estrato         TINYINT UNSIGNED  DEFAULT NULL,
  estu_estadocivil     VARCHAR(20)       DEFAULT NULL,
  estu_eps             VARCHAR(80)       DEFAULT NULL,
  estu_discapacidad    VARCHAR(80)       DEFAULT NULL,
  estu_multiculturalidad VARCHAR(60)     DEFAULT NULL,
  estu_activo          TINYINT(1)        NOT NULL DEFAULT 1,
  fechacreacion        TIMESTAMP         DEFAULT current_timestamp(),
  PRIMARY KEY (estu_id),
  UNIQUE KEY uq_estu_numerodoc (estu_numerodoc),
  CONSTRAINT fk_estu_usua FOREIGN KEY (usua_id) REFERENCES usuarios (usua_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_estu_coho FOREIGN KEY (coho_id) REFERENCES cohortes (coho_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Datos de estudiantes — formatos AC-FO-02 y AC-FO-09';

-- =============================================================================
-- BLOQUE 3: MÓDULOS ACADÉMICOS Y PLAN DE ESTUDIOS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 8. modulos
--    Módulos (asignaturas) por programa.
--    ASO: 17 módulos. MD: 19 módulos.
--    modu_orden: posición en el plan de estudios (semestre de ubicación).
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS modulos;
CREATE TABLE modulos (
  modu_id     SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prog_id     SMALLINT UNSIGNED NOT NULL,
  modu_nombre VARCHAR(120)      NOT NULL,
  modu_sigla  VARCHAR(10)       NOT NULL,
  modu_orden  TINYINT UNSIGNED  NOT NULL DEFAULT 1,   -- semestre/bloque
  modu_activo TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (modu_id),
  UNIQUE KEY uq_modu_sigla_prog (prog_id, modu_sigla),
  CONSTRAINT fk_modu_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Módulos académicos por programa';

-- =============================================================================
-- BLOQUE 4: MATRÍCULAS Y GRUPOS
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 9. matriculas
--    Proceso de inscripción y matrícula del estudiante (AC-FO-02 + AC-FO-09).
--    matr_estado:
--      'aspirante'  = inscripción iniciada (AC-FO-02 diligenciado)
--      'matriculado'= matrícula confirmada (AC-FO-09 diligenciado + pago)
--      'retirado'   = se retiró durante el período
--      'graduado'   = proceso completado
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS matriculas;
CREATE TABLE matriculas (
  matr_id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  estu_id              INT UNSIGNED      NOT NULL,
  prog_id              SMALLINT UNSIGNED NOT NULL,
  peri_id              SMALLINT UNSIGNED NOT NULL,
  matr_estado          ENUM('aspirante','matriculado','retirado','graduado')
                       NOT NULL DEFAULT 'aspirante',
  matr_folio           VARCHAR(20)       DEFAULT NULL,
  matr_numero          VARCHAR(20)       DEFAULT NULL,
  matr_matriculadopor  VARCHAR(80)       DEFAULT NULL,
  fechainscripcion     DATE              DEFAULT NULL,
  fechamatricula       DATE              DEFAULT NULL,
  req_copiadiploma     TINYINT(1)        NOT NULL DEFAULT 0,
  req_actagrado        TINYINT(1)        NOT NULL DEFAULT 0,
  req_documento        TINYINT(1)        NOT NULL DEFAULT 0,
  req_carnetsalud      TINYINT(1)        NOT NULL DEFAULT 0,
  req_examenmedico     TINYINT(1)        NOT NULL DEFAULT 0,
  req_fotos            TINYINT(1)        NOT NULL DEFAULT 0,
  req_carpeta          TINYINT(1)        NOT NULL DEFAULT 0,
  req_vacunastetano    TINYINT(1)        NOT NULL DEFAULT 0,
  req_hepatitisb       TINYINT(1)        NOT NULL DEFAULT 0,
  matr_observacion     TEXT              DEFAULT NULL,
  PRIMARY KEY (matr_id),
  UNIQUE KEY uq_matr_estu_peri_prog (estu_id, peri_id, prog_id),
  CONSTRAINT fk_matr_estu FOREIGN KEY (estu_id) REFERENCES estudiantes (estu_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matr_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_matr_peri FOREIGN KEY (peri_id) REFERENCES periodos (peri_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Inscripción y matrícula por período — AC-FO-02 y AC-FO-09';

-- -----------------------------------------------------------------------------
-- 10. fichas_inscripcion
--     Datos familiares y de estudios anteriores del estudiante (AC-FO-02).
--     Relación 1:1 con estudiantes.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS fichas_inscripcion;
CREATE TABLE fichas_inscripcion (
  finc_id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  estu_id              INT UNSIGNED  NOT NULL,
  prog_id              SMALLINT UNSIGNED DEFAULT NULL,
  jornada              VARCHAR(20)       DEFAULT NULL,
  fechainscripcion     DATE              DEFAULT NULL,
  -- Padre
  padr_vive            TINYINT(1)    DEFAULT NULL,
  padr_nombres         VARCHAR(80)   DEFAULT NULL,
  padr_apellidos       VARCHAR(80)   DEFAULT NULL,
  padr_profesion       VARCHAR(80)   DEFAULT NULL,
  padr_empresa         VARCHAR(80)   DEFAULT NULL,
  padr_telefono        VARCHAR(15)   DEFAULT NULL,
  padr_direccion       VARCHAR(120)  DEFAULT NULL,
  padr_barrio          VARCHAR(60)   DEFAULT NULL,
  padr_ciudad          VARCHAR(60)   DEFAULT NULL,
  -- Madre
  madr_vive            TINYINT(1)    DEFAULT NULL,
  madr_nombres         VARCHAR(80)   DEFAULT NULL,
  madr_apellidos       VARCHAR(80)   DEFAULT NULL,
  madr_profesion       VARCHAR(80)   DEFAULT NULL,
  madr_empresa         VARCHAR(80)   DEFAULT NULL,
  madr_telefono        VARCHAR(15)   DEFAULT NULL,
  madr_direccion       VARCHAR(120)  DEFAULT NULL,
  madr_barrio          VARCHAR(60)   DEFAULT NULL,
  madr_ciudad          VARCHAR(60)   DEFAULT NULL,
  -- Acudiente / Persona de contacto
  acud_es              ENUM('padre','madre','otro') DEFAULT NULL,
  acud_parentesco      VARCHAR(40)   DEFAULT NULL,
  acud_nombres         VARCHAR(80)   DEFAULT NULL,
  acud_apellidos       VARCHAR(80)   DEFAULT NULL,
  acud_profesion       VARCHAR(80)   DEFAULT NULL,
  acud_empresa         VARCHAR(80)   DEFAULT NULL,
  acud_telefono        VARCHAR(15)   DEFAULT NULL,
  acud_direccion       VARCHAR(120)  DEFAULT NULL,
  acud_barrio          VARCHAR(60)   DEFAULT NULL,
  acud_ciudad          VARCHAR(60)   DEFAULT NULL,
  -- Estudios anteriores (una fila)
  estudio_tipo         VARCHAR(60)   DEFAULT NULL,
  estudio_titulo       VARCHAR(120)  DEFAULT NULL,
  estudio_institucion  VARCHAR(120)  DEFAULT NULL,
  estudio_aniofin      YEAR          DEFAULT NULL,
  -- Código temporal de acceso al formulario público
  finc_codigotemporal  VARCHAR(20)   DEFAULT NULL,
  finc_estado          ENUM('aspirante','matriculado') NOT NULL DEFAULT 'aspirante',
  fechacreacion        TIMESTAMP     DEFAULT current_timestamp(),
  PRIMARY KEY (finc_id),
  UNIQUE KEY uq_finc_estu (estu_id),
  CONSTRAINT fk_finc_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_finc_estu FOREIGN KEY (estu_id) REFERENCES estudiantes (estu_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Datos familiares y estudios anteriores — AC-FO-02';

-- -----------------------------------------------------------------------------
-- 11. gruposemestres
--     Grupo de estudiantes que cursan juntos un semestre completo.
--     Un grupo semestre pertenece a un programa y período específico.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS gruposemestres;
CREATE TABLE gruposemestres (
  grse_id       SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prog_id       SMALLINT UNSIGNED NOT NULL,
  coho_id          SMALLINT UNSIGNED DEFAULT NULL,
  peri_id       SMALLINT UNSIGNED NOT NULL,
  grse_codigo   VARCHAR(20)       NOT NULL,   -- Ej: "ASO-2025-1-A"
  grse_semestre TINYINT UNSIGNED  NOT NULL,   -- Semestre del programa (1-8)
  fechainicio   DATE              DEFAULT NULL,
  fechafin      DATE              DEFAULT NULL,
  grse_activo   TINYINT(1)        NOT NULL DEFAULT 1,
  PRIMARY KEY (grse_id),
  UNIQUE KEY uq_grse_codigo (grse_codigo),
  CONSTRAINT fk_grse_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_grse_peri FOREIGN KEY (peri_id) REFERENCES periodos (peri_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_grse_coho FOREIGN KEY (coho_id) REFERENCES cohortes (coho_id)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Grupos semestre (cohorte cursando un período)';

-- -----------------------------------------------------------------------------
-- 11. programa_modulos
--     Semestre sugerido por módulo según programa — flexible al armar grupos.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS programa_modulos;
CREATE TABLE programa_modulos (
  prmo_id                SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  prog_id                SMALLINT UNSIGNED NOT NULL,
  modu_id                SMALLINT UNSIGNED NOT NULL,
  prmo_semestre_sugerido TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  PRIMARY KEY (prmo_id),
  UNIQUE KEY uq_prmo_prog_modu (prog_id, modu_id),
  CONSTRAINT fk_prmo_prog FOREIGN KEY (prog_id) REFERENCES programas (prog_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_prmo_modu FOREIGN KEY (modu_id) REFERENCES modulos (modu_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Semestre sugerido por módulo según programa — flexible al armar grupos';

-- -----------------------------------------------------------------------------
-- 11b. grmoestudiantes
--      Tabla puente N:M — estudiantes asignados a cada módulo específico.
--      Reemplaza grseestudiantes para un control más granular.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS grmoestudiantes;
CREATE TABLE grmoestudiantes (
  grmo_id  INT UNSIGNED NOT NULL,
  estu_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (grmo_id, estu_id),
  CONSTRAINT fk_grmo_estu_grmo FOREIGN KEY (grmo_id) REFERENCES gruposmodulos (grmo_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_grmo_estu_estu FOREIGN KEY (estu_id) REFERENCES estudiantes (estu_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Estudiantes asignados a cada módulo específico — reemplaza grseestudiantes';

-- -----------------------------------------------------------------------------
-- 12. gruposmodulos
--     Un grupo semestre se divide en grupos módulo: cada módulo tiene
--     un docente asignado. Esta es la unidad donde se registran las notas.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS gruposmodulos;
CREATE TABLE gruposmodulos (
  grmo_id   INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  grse_id   SMALLINT UNSIGNED NOT NULL,
  modu_id   SMALLINT UNSIGNED NOT NULL,
  doce_id   SMALLINT UNSIGNED NOT NULL,
  grmo_horario     VARCHAR(30)      DEFAULT NULL,
  fechainicio      DATE             DEFAULT NULL,
  fechafin         DATE             DEFAULT NULL,
  grmo_activo TINYINT(1)      NOT NULL DEFAULT 1,
  PRIMARY KEY (grmo_id),
  UNIQUE KEY uq_grmo_grse_modu (grse_id, modu_id),   -- un módulo por grupo semestre
  CONSTRAINT fk_grmo_grse FOREIGN KEY (grse_id) REFERENCES gruposemestres (grse_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_grmo_modu FOREIGN KEY (modu_id) REFERENCES modulos (modu_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_grmo_doce FOREIGN KEY (doce_id) REFERENCES docentes (doce_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Docente asignado a cada módulo dentro de un grupo semestre';

-- =============================================================================
-- BLOQUE 5: CALIFICACIONES (núcleo crítico — formato GA-FO-04)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 13. calificaciones
--     Registro de notas según formato GA-FO-04.
--     Estructura: N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = definitiva
--     REGLAS CRÍTICAS:
--       • Supletorios SOLO en N1, N2 y N4. N3 NUNCA tiene supletorio.
--       • Supletorio se activa SOLO si la nota original = 0.0
--       • Escala: 0.0 a 5.0 con un decimal → DECIMAL(3,1)
--       • Nota definitiva = N1×0.2 + N2×0.2 + N3×0.2 + N4×0.4
--         (usando supletorio en lugar de nota original si aplica)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS calificaciones;
CREATE TABLE calificaciones (
  cali_id        INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  grmo_id        INT UNSIGNED      NOT NULL,
  estu_id        INT UNSIGNED      NOT NULL,

  -- Notas parciales (formato GA-FO-04)
  cali_n1        DECIMAL(3,1)      DEFAULT NULL CHECK (cali_n1 BETWEEN 0.0 AND 5.0),
  cali_n2        DECIMAL(3,1)      DEFAULT NULL CHECK (cali_n2 BETWEEN 0.0 AND 5.0),
  cali_n3        DECIMAL(3,1)      DEFAULT NULL CHECK (cali_n3 BETWEEN 0.0 AND 5.0),
  cali_n4        DECIMAL(3,1)      DEFAULT NULL CHECK (cali_n4 BETWEEN 0.0 AND 5.0),

  -- Supletorios: SOLO N1, N2 y N4. N3 no tiene supletorio (regla institucional).
  cali_sup_n1    DECIMAL(3,1)      DEFAULT NULL CHECK (cali_sup_n1 BETWEEN 0.0 AND 5.0),
  cali_sup_n2    DECIMAL(3,1)      DEFAULT NULL CHECK (cali_sup_n2 BETWEEN 0.0 AND 5.0),
  -- cali_sup_n3 OMITIDO INTENCIONALMENTE — N3 nunca tiene supletorio
  cali_sup_n4    DECIMAL(3,1)      DEFAULT NULL CHECK (cali_sup_n4 BETWEEN 0.0 AND 5.0),

  -- Definitiva calculada: almacenada para reportes rápidos
  -- Fórmula: COALESCE(sup,n1)*0.2 + COALESCE(sup,n2)*0.2 + n3*0.2 + COALESCE(sup,n4)*0.4
  cali_definitiva DECIMAL(3,1)     DEFAULT NULL,

  cali_observacion TEXT            DEFAULT NULL,
  fecharegistro   TIMESTAMP        DEFAULT current_timestamp(),
  fechaactualizacion TIMESTAMP     DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (cali_id),
  UNIQUE KEY uq_cali_grmo_estu (grmo_id, estu_id),   -- un registro por estudiante por módulo
  CONSTRAINT fk_cali_grmo FOREIGN KEY (grmo_id) REFERENCES gruposmodulos (grmo_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_cali_estu FOREIGN KEY (estu_id) REFERENCES estudiantes (estu_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Calificaciones por módulo y estudiante (formato GA-FO-04)';

-- =============================================================================
-- BLOQUE 6: HORARIOS (complementario)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 14. horariosgrupo
--     Horario semanal de clases por grupo semestre.
--     hora_diasemana: 1=Lunes … 7=Domingo
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS horariosgrupo;
CREATE TABLE horariosgrupo (
  hora_id       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  grse_id       SMALLINT UNSIGNED NOT NULL,
  hora_diasemana TINYINT UNSIGNED NOT NULL CHECK (hora_diasemana BETWEEN 1 AND 7),
  hora_horainicio TIME            NOT NULL,
  hora_horafin   TIME             DEFAULT NULL,
  hora_aula      VARCHAR(40)      DEFAULT NULL,
  PRIMARY KEY (hora_id),
  CONSTRAINT fk_hora_grse FOREIGN KEY (grse_id) REFERENCES gruposemestres (grse_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Horarios de clases por grupo semestre';

-- =============================================================================
-- BLOQUE 7: STORED PROCEDURE — cálculo de definitiva
-- =============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_calcular_definitiva $$

CREATE PROCEDURE sp_calcular_definitiva(IN p_cali_id INT UNSIGNED)
BEGIN
  -- Recalcula la nota definitiva respetando reglas de supletorios.
  -- Supletorio reemplaza nota original SOLO si nota original = 0.0 y supletorio IS NOT NULL.
  DECLARE v_n1   DECIMAL(3,1);
  DECLARE v_n2   DECIMAL(3,1);
  DECLARE v_n3   DECIMAL(3,1);
  DECLARE v_n4   DECIMAL(3,1);
  DECLARE v_s1   DECIMAL(3,1);
  DECLARE v_s2   DECIMAL(3,1);
  DECLARE v_s4   DECIMAL(3,1);
  DECLARE v_ef1  DECIMAL(3,1);
  DECLARE v_ef2  DECIMAL(3,1);
  DECLARE v_ef4  DECIMAL(3,1);
  DECLARE v_def  DECIMAL(3,1);

  SELECT cali_n1, cali_n2, cali_n3, cali_n4,
         cali_sup_n1, cali_sup_n2, cali_sup_n4
  INTO   v_n1, v_n2, v_n3, v_n4, v_s1, v_s2, v_s4
  FROM   calificaciones
  WHERE  cali_id = p_cali_id;

  -- Nota efectiva = supletorio SI nota original = 0.0 Y supletorio ingresado
  SET v_ef1 = IF(v_n1 = 0.0 AND v_s1 IS NOT NULL, v_s1, v_n1);
  SET v_ef2 = IF(v_n2 = 0.0 AND v_s2 IS NOT NULL, v_s2, v_n2);
  -- N3 no tiene supletorio → siempre usa nota original
  SET v_ef4 = IF(v_n4 = 0.0 AND v_s4 IS NOT NULL, v_s4, v_n4);

  -- Calcular solo si todas las notas obligatorias están ingresadas
  IF v_n1 IS NOT NULL AND v_n2 IS NOT NULL AND v_n3 IS NOT NULL AND v_n4 IS NOT NULL THEN
    SET v_def = ROUND(v_ef1 * 0.2 + v_ef2 * 0.2 + v_n3 * 0.2 + v_ef4 * 0.4, 1);
    UPDATE calificaciones SET cali_definitiva = v_def WHERE cali_id = p_cali_id;
  END IF;
END $$

DELIMITER ;

-- =============================================================================
-- BLOQUE 8: TRIGGER — recalcular definitiva automáticamente
-- =============================================================================

DELIMITER $$

DROP TRIGGER IF EXISTS trg_calificaciones_after_update $$

CREATE TRIGGER trg_calificaciones_after_update
AFTER UPDATE ON calificaciones
FOR EACH ROW
BEGIN
  CALL sp_calcular_definitiva(NEW.cali_id);
END $$

DROP TRIGGER IF EXISTS trg_calificaciones_after_insert $$

CREATE TRIGGER trg_calificaciones_after_insert
AFTER INSERT ON calificaciones
FOR EACH ROW
BEGIN
  CALL sp_calcular_definitiva(NEW.cali_id);
END $$

DELIMITER ;

-- =============================================================================
-- BLOQUE 9: DATOS SEMILLA (SEEDS)
-- =============================================================================

-- ----- 9.1 Roles -----
INSERT INTO roles (role_id, role_nombre) VALUES
  (1, 'Administrador'),
  (2, 'Coordinador'),
  (3, 'Docente'),
  (4, 'Estudiante');

-- ----- 9.2 Programas -----
INSERT INTO programas (prog_id, prog_nombre, prog_sigla, prog_resolucion, prog_duracion_semestres) VALUES
  (1, 'Auxiliar en Salud Oral', 'ASO', 'Resolución institucional EMDB', 4),
  (2, 'Mecánica Dental', 'MD', 'Resolución institucional EMDB', 4);

-- ----- 9.3 Período semilla (2025-2 para datos históricos) -----
INSERT INTO periodos (peri_id, peri_codigo, peri_anio, peri_semestre, fechainicio, fechafin) VALUES
  (1, '2025-1', 2025, 1, '2025-01-20', '2025-06-20'),
  (2, '2025-2', 2025, 2, '2025-07-21', '2025-12-12'),
  (3, '2026-1', 2026, 1, '2026-01-19', '2026-06-19');

-- ----- 9.4 Módulos ASO (17 módulos) -----
INSERT INTO modulos (prog_id, modu_nombre, modu_sigla, modu_orden) VALUES
  -- Semestre 1
  (1, 'Anatomía y Fisiología General',      'ASO-AF1',  1),
  (1, 'Biología Oral',                       'ASO-BIO',  1),
  (1, 'Ética y Humanización en Salud',       'ASO-ETI',  1),
  (1, 'Inglés Técnico I',                    'ASO-ING1', 1),
  -- Semestre 2
  (1, 'Anatomía y Fisiología Oral',          'ASO-AF2',  2),
  (1, 'Radiología Oral Básica',              'ASO-RAD',  2),
  (1, 'Microbiología e Higiene Oral',        'ASO-MIC',  2),
  (1, 'Asistencia en Odontología General',   'ASO-AOG',  2),
  -- Semestre 3
  (1, 'Asistencia en Ortodoncia',            'ASO-ORT',  3),
  (1, 'Asistencia en Endodoncia',            'ASO-END',  3),
  (1, 'Asistencia en Cirugía Oral',          'ASO-CIR',  3),
  (1, 'Inglés Técnico II',                   'ASO-ING2', 3),
  -- Semestre 4
  (1, 'Asistencia en Periodoncia',           'ASO-PER',  4),
  (1, 'Asistencia en Odontopediatría',       'ASO-ODP',  4),
  (1, 'Salud Pública y Epidemiología Oral',  'ASO-SPE',  4),
  (1, 'Gestión Administrativa en Salud',     'ASO-GAS',  4),
  (1, 'Práctica Clínica Integradora',        'ASO-PCI',  4);

-- ----- 9.5 Módulos MD (19 módulos) -----
INSERT INTO modulos (prog_id, modu_nombre, modu_sigla, modu_orden) VALUES
  -- Semestre 1
  (2, 'Anatomía Dental',                     'MD-ANT',   1),
  (2, 'Materiales Dentales I',               'MD-MAT1',  1),
  (2, 'Biología General',                    'MD-BIO',   1),
  (2, 'Dibujo Técnico Dental',               'MD-DTE',   1),
  -- Semestre 2
  (2, 'Materiales Dentales II',              'MD-MAT2',  2),
  (2, 'Oclusión Dental',                     'MD-OCL',   2),
  (2, 'Prótesis Parcial Removible I',        'MD-PPR1',  2),
  (2, 'Fundamentos de Laboratorio',          'MD-FUN',   2),
  -- Semestre 3
  (2, 'Prótesis Parcial Removible II',       'MD-PPR2',  3),
  (2, 'Prótesis Total',                      'MD-PTO',   3),
  (2, 'Prótesis Fija I',                     'MD-PFI1',  3),
  (2, 'Control de Calidad en Laboratorio',   'MD-CCL',   3),
  (2, 'Inglés Técnico',                      'MD-ING',   3),
  -- Semestre 4
  (2, 'Prótesis Fija II',                    'MD-PFI2',  4),
  (2, 'Ortodoncia de Laboratorio',           'MD-ORT',   4),
  (2, 'Implantología Protésica',             'MD-IMP',   4),
  (2, 'Gestión y Administración de Laboratorio', 'MD-GAL', 4),
  (2, 'Higiene y Bioseguridad',              'MD-HBS',   4),
  (2, 'Práctica de Laboratorio Integradora', 'MD-PLI',   4);

-- ----- 9.6 Usuario administrador por defecto -----
-- Contraseña: Admin@2026 → hash bcrypt generado con cost=10
-- IMPORTANTE: Cambiar esta contraseña inmediatamente en producción
-- Para regenerar: php -r "echo password_hash('Admin@2026', PASSWORD_BCRYPT);"
INSERT INTO usuarios (role_id, usua_email, usua_passwordhash) VALUES
  (1, 'admin@emdb.edu.co', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
-- Nota: el hash anterior es el hash de 'password' en bcrypt — solo de referencia.
-- En setup real, ejecutar: UPDATE usuarios SET usua_passwordhash=password_hash('Admin@2026', PASSWORD_BCRYPT) WHERE usua_id=1;

-- =============================================================================
-- RESTAURAR FOREIGN KEY CHECKS
-- =============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- VERIFICACIÓN (ejecutar manualmente para confirmar estructura)
-- =============================================================================
-- SHOW TABLES;
-- SELECT COUNT(*) FROM modulos WHERE prog_id = 1;  -- Debe retornar 17 (ASO)
-- SELECT COUNT(*) FROM modulos WHERE prog_id = 2;  -- Debe retornar 19 (MD)
-- SELECT * FROM roles;
-- SELECT * FROM programas;
-- CALL sp_calcular_definitiva(1);  -- Probar stored procedure

-- =============================================================================
-- FIN DEL SCRIPT
-- emdb_academica.sql — v1.0.0 — 2026-04-30
-- Tablas creadas: 16
-- Registros semilla: 4 roles + 2 programas + 3 períodos + 36 módulos + 1 usuario admin
-- =============================================================================
