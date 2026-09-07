-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: emdb_academica
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `actividadesn3`
--

DROP TABLE IF EXISTS `actividadesn3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `actividadesn3` (
  `acn3_id` int unsigned NOT NULL AUTO_INCREMENT,
  `grmo_id` int unsigned NOT NULL,
  `acn3_nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acn3_comentario` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acn3_orden` tinyint unsigned NOT NULL DEFAULT '0',
  `fecharegistro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`acn3_id`),
  KEY `fk_acn3_grmo` (`grmo_id`),
  CONSTRAINT `fk_acn3_grmo` FOREIGN KEY (`grmo_id`) REFERENCES `gruposmodulos` (`grmo_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Actividades que componen la nota N3 configurable por grupo mÃ³dulo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actividadesn3`
--

LOCK TABLES `actividadesn3` WRITE;
/*!40000 ALTER TABLE `actividadesn3` DISABLE KEYS */;
INSERT INTO `actividadesn3` VALUES (9,3,'exposicion historia de la md','exposicion por grupos',0,'2026-09-06 04:26:15'),(10,3,'quiz',NULL,1,'2026-09-06 04:26:28'),(11,3,'video en clase',NULL,2,'2026-09-06 04:26:40');
/*!40000 ALTER TABLE `actividadesn3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ayudas`
--

DROP TABLE IF EXISTS `ayudas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ayudas` (
  `ayud_id` int NOT NULL AUTO_INCREMENT,
  `ayud_seccion` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayud_titulo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayud_contenido` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ayud_orden` smallint unsigned NOT NULL DEFAULT '0',
  `ayud_activo` tinyint(1) NOT NULL DEFAULT '1',
  `fecharegistro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaactualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ayud_id`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contenido de ayuda por sección de la aplicación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ayudas`
--

LOCK TABLES `ayudas` WRITE;
/*!40000 ALTER TABLE `ayudas` DISABLE KEYS */;
INSERT INTO `ayudas` VALUES (18,'04_grupos','¿Qué es una cohorte y cuándo se crea?','Una cohorte agrupa el momento de inicio de un programa (ej. CH-ASO-2026B). Se crea una vez por cada arranque de programa, y a partir de ella se configuran los grupos semestre correspondientes. No se puede eliminar una cohorte que ya tenga estudiantes matriculados o grupos semestre asociados — en ese caso, solo se puede desactivar.',1,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(19,'04_grupos','¿Cómo creo un grupo semestre?','Ve a la pestaña \'Grupos Semestre\' → \'+ Nuevo Grupo\'. Selecciona el Programa, la Cohorte, el Período académico, el número de Semestre, la Jornada (Semana o Sábados) y las fechas de inicio/fin. Cada grupo semestre representa un grupo de estudiantes cursando un semestre específico en un período determinado.',4,1,'2026-08-16 17:41:09','2026-08-25 04:00:38'),(20,'04_grupos','¿Cómo asigno un módulo y un docente a un grupo?','Dentro del modal \'Editar Grupo Semestre\', en la sección \'Módulos asignados a este grupo\', pulsa \'+ Agregar Módulo\'. Elige el módulo (asignatura), el docente responsable, el horario y las fechas. Puedes editar esta asignación después — incluyendo cambiar el módulo o el docente — desde el botón \'Editar\' de cada fila.',5,1,'2026-08-16 17:41:09','2026-08-25 04:00:38'),(21,'04_grupos','¿Cuándo puedo eliminar y cuándo debo desactivar?','El sistema solo permite eliminar registros que no tienen historial (sin estudiantes, sin notas, sin grupos asociados). Si un registro (cohorte, docente, módulo) ya tiene historial, verás el botón \'Desactivar\' en vez de \'Eliminar\' — esto lo oculta de los listados activos sin perder la información histórica de calificaciones y matrículas.',6,1,'2026-08-16 17:41:09','2026-08-25 04:00:38'),(22,'04_grupos','¿Cómo marco el período académico activo?','En la pestaña \'Períodos\', solo un período puede estar marcado como \'Activo\' a la vez. Este es el período que se usa por defecto en reportes y conteos (ej. cuántos grupos tiene asignados cada docente). Pulsa \'Marcar como activo\' sobre el período correspondiente al semestre en curso — el anterior se desactiva automáticamente.',7,1,'2026-08-16 17:41:09','2026-08-25 04:00:38'),(23,'05_calificaciones','¿Cómo registro las notas de mis estudiantes?','En el panel izquierdo verás tus módulos asignados. Selecciona uno para abrir la planilla de notas. Cada estudiante tiene 4 notas parciales (Nota 1, 2, 3 y 4, con distintos porcentajes). Las notas se guardan automáticamente al salir de cada campo — no necesitas pulsar ningún botón adicional.',1,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(24,'05_calificaciones','¿Cómo funcionan los supletorios (Sup N1, Sup N2, Sup N4)?','El campo de supletorio se habilita cuando la nota original correspondiente es 0.0. Si el estudiante presenta el supletorio, su valor reemplaza a la nota original en el cálculo de la Nota Final. La Nota 3 no tiene supletorio disponible.',2,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(25,'05_calificaciones','¿Cómo se calcula la Nota Final?','La Nota Final se calcula siempre con la fórmula: Nota 1 (20%) + Nota 2 (20%) + Nota 3 (20%) + Nota 4 (40%) — usando el valor del supletorio en vez de la nota original cuando corresponda. Se calcula sin importar si el estudiante aprueba o no.',3,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(26,'05_calificaciones','¿Qué es la habilitación y cuándo aplica?','El campo de habilitación se habilita cuando la Nota Final es menor a 3.0. La nota \'Definitiva\' es el valor oficial que queda registrado: copia automáticamente la Nota Final si el estudiante aprobó, o la Habilitación si esta fue registrada.',4,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(27,'05_calificaciones','¿Cómo uso los filtros de Profesor/Programa/Período?','Como coordinador o administrador, verás 3 filtros arriba del listado de grupos: Profesor, Programa y Período. Puedes combinarlos para acotar la vista — por ejemplo, ver solo los grupos de un docente específico en el período actual. Selecciona \'Todos\' en cualquiera para quitar ese filtro.',5,1,'2026-08-16 17:41:09','2026-08-16 17:41:09'),(28,'02_estudiantes','¿Cómo registro un aspirante nuevo?','Ve a \"+ Nuevo Estudiante\" para abrir la Ficha de Inscripción (AC-FO-02), un único modal con 5 secciones: Datos del Estudiante, Multiculturalidad, Información sobre Familiares, Estudios anteriores, y Programa técnico al que ingresa. Completa todas las secciones y guarda — todo se registra en una sola operación, ya no son pasos separados. El aspirante queda en estado \"aspirante\", sin matrícula activa todavía.',1,1,'2026-08-16 19:06:26','2026-08-25 03:56:53'),(29,'02_estudiantes','¿Qué es la Ficha Familiar y cuándo se completa?','Las secciones \"Información sobre Familiares\" y \"Estudios anteriores\" de la Ficha de Inscripción reúnen los datos del padre, madre o acudiente, y los estudios previos del aspirante. El sistema no permite matricular a un estudiante hasta que todos los campos obligatorios de estas secciones estén completos — verás un indicador de estado (sin iniciar / incompleta / completa) en el listado de aspirantes. Para editar esta información en un estudiante ya existente, usa el botón \"📝 Datos Estudiante\", que abre el mismo modal con todo precargado.',2,1,'2026-08-16 19:06:26','2026-08-25 03:56:53'),(30,'02_estudiantes','¿Cómo matriculo a un estudiante?','Una vez la Ficha Familiar está completa, usa la opción de matrícula. Selecciona Programa, Cohorte y Período (los tres son obligatorios), completa el Folio, la Fecha de matrícula y las Observaciones si aplica, y define la clave de acceso: automática (apellido + fecha de nacimiento) o manual. Al guardar, puedes generar la Hoja de Matrícula en PDF (AC-FO-09) desde el mismo modal — el número de matrícula se asigna automáticamente la primera vez que generas ese documento.',3,1,'2026-08-16 19:06:26','2026-08-25 03:56:53'),(31,'02_estudiantes','¿Puedo eliminar un aspirante o estudiante?','Solo se puede eliminar un aspirante que no tenga matrícula activa. Un estudiante ya matriculado no puede eliminarse — la información académica debe conservarse. Si necesitas corregir datos de una matrícula existente (Programa, Cohorte, Período, Folio, número de matrícula), usa \"Editar Matrícula\" en vez de eliminar y volver a registrar.',4,1,'2026-08-16 19:06:26','2026-08-25 03:56:53'),(32,'03_docentes','¿Cómo registro un docente nuevo?','Ve a \'+ Nuevo Docente\' y completa nombres, apellidos, sigla y correo. Al crearlo, el sistema genera automáticamente su cuenta de acceso al sistema.',1,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(33,'03_docentes','¿Cómo veo cuántos grupos tiene asignados un docente?','En el listado principal verás la columna \'Grupos (período)\', que muestra cuántos grupos tiene asignados el docente en el período seleccionado. Usa el select de período arriba de la tabla para consultar otros períodos, no solo el actual.',2,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(34,'03_docentes','¿Cuándo puedo eliminar un docente y cuándo debo desactivarlo?','Solo se puede eliminar un docente que nunca haya tenido ningún grupo asignado (ni siquiera en períodos anteriores). Si el docente tiene historial, solo puedes desactivarlo — esto lo oculta de los listados activos sin afectar las calificaciones ya registradas de sus grupos pasados.',3,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(35,'06_reportes','¿Cómo consulto mis propias notas? (Estudiante)','Al entrar verás automáticamente tus calificaciones del período correspondiente, sin necesidad de ningún filtro adicional.',1,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(36,'06_reportes','¿Cómo genero el reporte de un grupo? (Coordinador/Admin)','Selecciona el grupo que quieres consultar. Podrás exportar la información en dos formatos: Excel (para análisis o respaldo) y PDF (formato de reporte oficial GA-FO-04).',2,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(37,'06_reportes','¿Cómo genero el boletín individual de un estudiante?','Desde la consulta de notas de un estudiante específico, usa la opción de generar boletín en PDF — incluye todas sus calificaciones del módulo o período consultado.',3,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(38,'07_coordinador','¿Qué muestra este dashboard?','Un resumen general del estado académico: indicadores como el total de docentes activos, y otros datos de seguimiento que te ayudan a supervisar el avance del semestre sin tener que revisar módulo por módulo.',1,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(39,'08_admin','¿Qué cuentas administra este módulo?','Solo cuentas con rol Administrador o Coordinador. Docentes y Estudiantes se gestionan desde sus propios módulos (03_docentes y 02_estudiantes) — no aparecen aquí.',1,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(40,'08_admin','¿Cómo creo un usuario administrativo nuevo?','Ve a \'+ Nuevo Usuario\', ingresa el correo, define una clave y elige el rol (Administrador o Coordinador). Solo estos dos roles están disponibles en este módulo.',2,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(41,'08_admin','¿Puedo cambiar el rol de un usuario ya creado?','Sí, desde \'Editar\' puedes cambiar entre Administrador y Coordinador, y actualizar el correo o la clave si es necesario.',3,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(42,'08_admin','¿Cómo desactivo un usuario administrativo?','Desde el listado, cambia su estado a Inactivo. Esto le impide iniciar sesión sin eliminar su cuenta ni su historial de acciones en el sistema.',4,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(43,'08_admin','¿Este módulo elimina usuarios?','No — este módulo solo permite crear, editar y activar/desactivar cuentas de Administrador/Coordinador. No existe opción de eliminación aquí.',5,1,'2026-08-16 19:06:26','2026-08-16 19:06:26'),(44,'02_estudiantes','¿Cómo cambio la clave de acceso de un estudiante ya matriculado?','Abre \"📝 Datos Estudiante\" sobre el estudiante matriculado. En la parte inferior del modal encontrarás el bloque \"Cambiar clave de acceso\" (solo visible si el estudiante ya tiene acceso al sistema). Elige \"Generar clave automática\" o \"Asignar clave manual\"; si dejas ambas opciones sin marcar, la clave actual no se modifica.',5,1,'2026-08-17 00:02:49','2026-08-25 03:56:53'),(45,'04_grupos','¿Cómo elimino una cohorte?','Desde el listado de cohortes verás el botón \"Eliminar\" (si la cohorte no tiene estudiantes ni grupos semestre asociados) o \"Desactivar\" (si ya tiene historial). Este comportamiento es el mismo que ya se explica para otros registros del sistema.',3,1,'2026-08-17 00:04:17','2026-08-25 04:00:38'),(47,'04_grupos','Guía completa: de programa a estudiante matriculado en un módulo','El sistema organiza la información académica en una jerarquía de 6 niveles. Sigue este orden para configurar un semestre completo:\n\n1. Programa — Ya está sembrado (ASO o MD), normalmente no necesitas crearlo.\n\n2. Período académico — Ve a la pestaña \"Períodos\" y confirma que el período correspondiente existe y está marcado como \"Activo\".\n\n3. Cohorte — Crea la cohorte de ingreso vinculada al Programa (ej. \"estudiantes que iniciaron en 2026-B\").\n\n4. Grupo Semestre — Crea el grupo uniendo Programa + Cohorte + Período + número de Semestre + Jornada.\n\n5. Módulos del grupo — Dentro del grupo, agrega cada módulo (asignatura) con su docente, horario y fechas.\n\n6. Asignación de estudiantes — Ve a la pestaña \"Asignación Estudiantes\". Elige el Grupo Semestre y luego el Módulo específico. Verás dos listas: \"Estudiantes Disponibles\" (los estudiantes ya matriculados en esa cohorte que aún no están en este módulo) y \"En este Módulo\" (ya asignados). Puedes asignar de a uno, varios seleccionados, o todos con un clic (\"Asignar todos →\"). También puedes retirar estudiantes ya asignados.\n\n⚠️ Importante: la asignación se hace módulo por módulo, no por grupo completo — si un estudiante debe cursar 5 módulos, debes asignarlo en cada uno individualmente (o usar \"Asignar todos\" en cada módulo). Un estudiante solo aparece como \"disponible\" si ya completó su matrícula en la cohorte correspondiente (la cohorte se asigna en el paso de matrícula, en 02_estudiantes) — los aspirantes sin matricular nunca aparecen en esta lista.',0,1,'2026-08-17 17:56:40','2026-08-17 17:56:40'),(48,'04_grupos','¿Qué implica desactivar una cohorte?','Desactivar una cohorte (a diferencia de eliminarla) mantiene todo su historial intacto: los estudiantes ya vinculados y los grupos semestre asociados siguen existiendo y son consultables en reportes y calificaciones sin ningún cambio. Lo que cambia es la visibilidad: una cohorte desactivada deja de aparecer en los selectores de cohorte, tanto al crear como al editar grupos semestre o estudiantes/aspirantes. Si un registro ya estaba asociado a esa cohorte antes de desactivarla, conserva su valor, pero no podrás volver a seleccionarla si necesitas cambiarlo. Se puede reactivar en cualquier momento. Nota: el sistema solo permite desactivar una cohorte con estudiantes o grupos semestre asociados — si está completamente vacía, se elimina físicamente en lugar de desactivarse.',2,1,'2026-08-18 22:10:33','2026-08-18 22:10:33'),(49,'08_admin','¿Qué es la Configuración Institucional?','Debajo de la tabla de usuarios encontrarás el bloque \"Configuración Institucional\", con tres campos: número de matrícula inicial, nombre del Director y nombre del Secretario(a). Estos datos alimentan la Hoja de Matrícula en PDF (AC-FO-09) que se genera desde el módulo de Estudiantes. El número de matrícula inicial solo aplica hacia adelante — cambiarlo no afecta las matrículas que ya fueron generadas.',6,1,'2026-08-25 03:56:53','2026-08-25 03:56:53');
/*!40000 ALTER TABLE `ayudas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calificaciones`
--

DROP TABLE IF EXISTS `calificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calificaciones` (
  `cali_id` int unsigned NOT NULL AUTO_INCREMENT,
  `grmo_id` int unsigned NOT NULL,
  `estu_id` int unsigned NOT NULL,
  `cali_n1` decimal(3,1) DEFAULT NULL,
  `cali_n2` decimal(3,1) DEFAULT NULL,
  `cali_n3` decimal(3,1) DEFAULT NULL,
  `cali_n4` decimal(3,1) DEFAULT NULL,
  `cali_sup_n1` decimal(3,1) DEFAULT NULL,
  `cali_sup_n2` decimal(3,1) DEFAULT NULL,
  `cali_sup_n4` decimal(3,1) DEFAULT NULL,
  `cali_definitiva` decimal(3,1) DEFAULT NULL,
  `cali_habilitacion` decimal(3,1) DEFAULT NULL,
  `cali_nota_final` decimal(3,1) DEFAULT NULL,
  `cali_observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fecharegistro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fechaactualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`cali_id`),
  UNIQUE KEY `uq_cali_grmo_estu` (`grmo_id`,`estu_id`),
  KEY `fk_cali_estu` (`estu_id`),
  CONSTRAINT `fk_cali_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cali_grmo` FOREIGN KEY (`grmo_id`) REFERENCES `gruposmodulos` (`grmo_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calificaciones`
--

LOCK TABLES `calificaciones` WRITE;
/*!40000 ALTER TABLE `calificaciones` DISABLE KEYS */;
INSERT INTO `calificaciones` VALUES (4,3,79,4.5,2.8,3.8,0.0,NULL,NULL,3.0,3.4,NULL,3.4,NULL,'2026-09-06 04:27:03','2026-09-06 04:28:27'),(5,3,66,NULL,NULL,2.9,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 04:27:25','2026-09-06 04:27:33'),(6,3,77,NULL,NULL,3.3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 04:27:37','2026-09-06 04:27:48');
/*!40000 ALTER TABLE `calificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cohortes`
--

DROP TABLE IF EXISTS `cohortes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cohortes` (
  `coho_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_id` smallint unsigned NOT NULL,
  `coho_codigo` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fechainicio` date NOT NULL,
  `coho_activa` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`coho_id`),
  UNIQUE KEY `uq_coho_codigo` (`coho_codigo`),
  KEY `fk_coho_prog` (`prog_id`),
  CONSTRAINT `fk_coho_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cohortes de ingreso por programa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cohortes`
--

LOCK TABLES `cohortes` WRITE;
/*!40000 ALTER TABLE `cohortes` DISABLE KEYS */;
INSERT INTO `cohortes` VALUES (1,2,'CH-MD-2026B','2026-08-09',1),(7,1,'CH-ASO-2026B','2026-08-08',1),(8,2,'CH-MD-2026A','2026-02-07',1),(9,1,'CH-ASO-2026A','2026-02-01',1),(10,6,'CH-ASO-2025B','2025-08-02',1),(11,5,'CH-AMD-2025A','2025-02-01',1);
/*!40000 ALTER TABLE `cohortes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `configuracion` (
  `config_id` int NOT NULL DEFAULT '1',
  `institucion_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Escuela de MecÃ¡nica Dental BolaÃ±os (EMDB)',
  `matr_numero_inicial` int NOT NULL DEFAULT '1',
  `director_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `secretario_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES (1,'Escuela de Mecánica Dental Bolaños (EMDB)',1030,'sonia crsitina bolaños','elizabeth peredes');
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `docentes`
--

DROP TABLE IF EXISTS `docentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docentes` (
  `doce_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `usua_id` int unsigned DEFAULT NULL,
  `doce_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doce_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doce_cedula` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doce_sigla` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doce_activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`doce_id`),
  UNIQUE KEY `uq_doce_sigla` (`doce_sigla`),
  UNIQUE KEY `uq_doce_cedula` (`doce_cedula`),
  KEY `fk_doce_usua` (`usua_id`),
  CONSTRAINT `fk_doce_usua` FOREIGN KEY (`usua_id`) REFERENCES `usuarios` (`usua_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos de docentes de la EMDB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `docentes`
--

LOCK TABLES `docentes` WRITE;
/*!40000 ALTER TABLE `docentes` DISABLE KEYS */;
INSERT INTO `docentes` VALUES (1,5,'FABIAN','CARDONA RODRIGUEZ','1858092','FCRX',1),(2,6,'CAMILO ANDRES','RODRIGUEZ AGUDELO','152534022','CARA',1),(3,7,'SOFIA','ROMERO MARIN',NULL,'SRMX',1),(4,8,'MARIA JOSE','GOMEZ SUAREZ','84324392','MJGS',1),(11,28,'JORGE LUIS','ALVAREZ MORENO',NULL,'JLAM',1);
/*!40000 ALTER TABLE `docentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estudiantes`
--

DROP TABLE IF EXISTS `estudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estudiantes` (
  `estu_id` int unsigned NOT NULL AUTO_INCREMENT,
  `usua_id` int unsigned DEFAULT NULL,
  `estu_tipodoc` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_numerodoc` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_expedidoen` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estu_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estu_ciudadnac` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechanacimiento` date DEFAULT NULL,
  `estu_sexo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_ocupacion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_estrato` tinyint unsigned DEFAULT NULL,
  `estu_estadocivil` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_eps` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_discapacidad` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_multiculturalidad` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_activo` tinyint(1) NOT NULL DEFAULT '1',
  `estu_foto` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estu_origen` enum('manual','web') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `fechacreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`estu_id`),
  UNIQUE KEY `uq_estu_numerodoc` (`estu_numerodoc`),
  UNIQUE KEY `uq_estu_email` (`estu_email`),
  KEY `fk_estu_usua` (`usua_id`),
  CONSTRAINT `fk_estu_usua` FOREIGN KEY (`usua_id`) REFERENCES `usuarios` (`usua_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos de estudiantes — formatos AC-FO-02 y AC-FO-09';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estudiantes`
--

LOCK TABLES `estudiantes` WRITE;
/*!40000 ALTER TABLE `estudiantes` DISABLE KEYS */;
INSERT INTO `estudiantes` VALUES (55,NULL,'CC','28562481','SEVILLA','YULY ANDREA','ALVAREZ GALLEGO','SEVILLA','1986-04-09','Femenino','3421103366','alvarezyuly@correo.test','INDEPENDIENTE','CALLE 11 # 46-91','BELALCAZAR','SEVILLA',1,'UNION LIBRE','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(56,NULL,'CC','744761312','CALI','ESTEFANIA','MONTOYA ARANGO','CALI','1992-03-23','Femenino','3830008867','montoyaestefania@correo.test','COMERCIANTE','CALLE 5 # 62-53','EL JARDIN','CALI',2,'SOLTERO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(57,NULL,'CC','630419074','CAICEDONIA','EMILY','VIAFARA BEDOYA','CAICEDONIA','1998-11-19','Femenino','3211278918','viafaraemily@correo.test','COMERCIANTE','CALLE 34 # 15-25','EL JARDIN','CAICEDONIA',2,'SOLTERO(A)','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(58,NULL,'CC','413600211','ZARZAL','SAMUEL','MORALES CASTILLO','ZARZAL','1989-07-26','Masculino','3970552007','moralessamuel@correo.test','ESTUDIANTE','CALLE 81 # 29-64','LA INDEPENDENCIA','ZARZAL',2,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(59,NULL,'CC','244767047','SEVILLA','JORGE LUIS','MONTOYA GONZALEZ','SEVILLA','1999-02-15','Masculino','3342008096','montoyajorge@correo.test','AMA DE CASA','CALLE 12 # 1-14','LA INDEPENDENCIA','SEVILLA',6,'UNION LIBRE','SALUD TOTAL','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(60,NULL,'CC','541851038','CARTAGO','JUAN ESTEBAN','URIBE BEDOYA','CARTAGO','1986-02-07','Masculino','3340225384','uribejuan@correo.test','EMPLEADO(A)','CALLE 54 # 52-36','LAS AMERICAS','CARTAGO',6,'CASADO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(61,NULL,'TI','1044604526','CARTAGO','SOFIA','ECHEVERRI CARDONA','CARTAGO','2010-01-26','Femenino','3087556038','echeverrisofia@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 67 # 44-75','EL BOSQUE','CARTAGO',4,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(62,NULL,'CC','975235648','PALMIRA','STIVEN','RAMIREZ SALAZAR','PALMIRA','1990-10-15','Masculino','3272552709','ramirezstiven@correo.test','AMA DE CASA','CALLE 12 # 76-63','EL BOSQUE','PALMIRA',3,'UNION LIBRE','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(63,NULL,'CC','865559060','BUGALAGRANDE','JULIANA','MEJIA GONZALEZ','BUGALAGRANDE','1989-07-12','Femenino','3301708777','mejiajuliana@correo.test','INDEPENDIENTE','CALLE 53 # 78-72','EL BOSQUE','BUGALAGRANDE',4,'CASADO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(64,NULL,'CC','536530306','CAICEDONIA','SAMANTHA','MORENO RENGIFO','CAICEDONIA','1987-01-15','Femenino','3153006674','morenosamantha@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 64 # 25-63','SAN JOSE','CAICEDONIA',5,'CASADO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(65,NULL,'CC','1097759339','RIOFRIO','OSCAR DAVID','HERNANDEZ CASTRO','RIOFRIO','1999-11-05','Masculino','3052447315','hernandezoscar@correo.test','EMPLEADO(A)','CALLE 51 # 74-58','BELALCAZAR','RIOFRIO',2,'CASADO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(66,NULL,'CC','679112349','ZARZAL','MIGUEL ANGEL','VARGAS RENGIFO','ZARZAL','2004-10-08','Masculino','3105912649','vargasmiguel@correo.test','OPERARIO(A)','CALLE 86 # 84-3','LOS ALAMOS','ZARZAL',4,'UNION LIBRE','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(67,NULL,'CC','1163288387','TRUJILLO','SAMUEL','RUIZ AGUDELO','TRUJILLO','2001-08-02','Masculino','3685050368','ruizsamuel@correo.test','INDEPENDIENTE','CALLE 62 # 36-61','SANTA ELENA','TRUJILLO',4,'CASADO(A)','FAMISANAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(68,NULL,'CC','570597085','TRUJILLO','LINA MARCELA','ARANGO ZAPATA','TRUJILLO','1986-09-06','Femenino','3219083607','arangolina@correo.test','INDEPENDIENTE','CALLE 25 # 11-82','LA FLORIDA','TRUJILLO',5,'SOLTERO(A)','FAMISANAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(69,NULL,'CC','535231798','CARTAGO','LEIDY TATIANA','SUAREZ JIMENEZ','CARTAGO','1996-03-15','Femenino','3466007878','suarezleidy@correo.test','OPERARIO(A)','CALLE 17 # 71-11','VILLA DEL PRADO','CARTAGO',3,'UNION LIBRE','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(70,NULL,'CC','891964330','CALI','JHON ALEXANDER','MONTOYA ROJAS','CALI','1997-12-20','Masculino','3644069352','montoyajhon@correo.test','INDEPENDIENTE','CALLE 73 # 69-97','LA INDEPENDENCIA','CALI',3,'UNION LIBRE','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(71,NULL,'CC','750967721','TRUJILLO','WILMER','MUÑOZ GIRALDO','TRUJILLO','1998-03-24','Masculino','3222252710','muñozwilmer@correo.test','AMA DE CASA','CALLE 70 # 68-87','VILLA CLAUDIA','TRUJILLO',6,'UNION LIBRE','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(72,NULL,'CC','775166098','SEVILLA','BRIGITTE','GOMEZ SANCHEZ','SEVILLA','1997-03-19','Femenino','3888850982','gomezbrigitte@correo.test','OPERARIO(A)','CALLE 51 # 29-98','EL JAZMIN','SEVILLA',6,'SOLTERO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(73,NULL,'CC','993808012','CARTAGO','CATALINA','SANCHEZ GOMEZ','CARTAGO','2002-10-24','Femenino','3648905826','sanchezcatalina@correo.test','OPERARIO(A)','CALLE 24 # 80-56','EL JARDIN','CARTAGO',5,'UNION LIBRE','FAMISANAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(74,NULL,'TI','1012680206','TULUA','SEBASTIAN','ZAPATA MORALES','TULUA','2009-12-17','Masculino','3205270015','zapatasebastian@correo.test','ESTUDIANTE','CALLE 87 # 3-98','VILLA CLAUDIA','TULUA',2,'SOLTERO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(75,NULL,'CC','213976734','BUGA','FELIPE','ORTIZ BOTERO','BUGA','2000-09-11','Masculino','3968344611','ortizfelipe@correo.test','INDEPENDIENTE','CALLE 55 # 14-42','EL BOSQUE','BUGA',1,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(76,NULL,'CC','1172685258','TULUA','ANA SOFIA','DIAZ LONDOÑO','TULUA','1998-08-15','Femenino','3721577410','diazana@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 74 # 2-92','LA FLORIDA','TULUA',4,'UNION LIBRE','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(77,NULL,'CC','900816840','SAN PEDRO','STIVEN','ECHEVERRI CORREA','SAN PEDRO','1998-12-02','Masculino','3921829255','echeverristiven@correo.test','AMA DE CASA','CALLE 48 # 1-11','LA INDEPENDENCIA','SAN PEDRO',4,'UNION LIBRE','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(78,NULL,'TI','1084469687','CARTAGO','ESTEFANIA','MORENO TORRES','CARTAGO','2010-01-27','Femenino','3102652544','morenoestefania@correo.test','COMERCIANTE','CALLE 83 # 97-83','LA GRANJA','CARTAGO',6,'SOLTERO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(79,39,'CC','461648897','TRUJILLO','YESICA','GIRALDO CASTILLO','TRUJILLO','1994-12-17','Femenino','3771674204','giraldoyesica@correo.test','INDEPENDIENTE','CALLE 29 # 24-63','LAS AMERICAS','TRUJILLO',5,'CASADO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(80,NULL,'CC','795819795','ANDALUCIA','CAMILA','TORRES TABORDA','ANDALUCIA','1996-03-05','Femenino','3813830104','torrescamila@correo.test','COMERCIANTE','CALLE 43 # 82-89','CENTRO','ANDALUCIA',6,'CASADO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(81,NULL,'CC','894846630','PALMIRA','SEBASTIAN','ECHEVERRI HERNANDEZ','PALMIRA','1995-01-02','Masculino','3957934181','echeverrisebastian@correo.test','ESTUDIANTE','CALLE 63 # 70-79','SAN JOSE','PALMIRA',3,'CASADO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(82,NULL,'CC','348174739','TRUJILLO','NICOLAS','GALLEGO TORRES','TRUJILLO','1999-12-12','Masculino','3954827601','gallegonicolas@correo.test','COMERCIANTE','CALLE 42 # 82-86','BELALCAZAR','TRUJILLO',4,'SOLTERO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(83,NULL,'CC','886502699','RIOFRIO','DIEGO ARMANDO','ROJAS MORALES','RIOFRIO','1987-11-15','Masculino','3625074529','rojasdiego@correo.test','EMPLEADO(A)','CALLE 52 # 75-9','EL JAZMIN','RIOFRIO',1,'CASADO(A)','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(84,NULL,'TI','1033937221','SEVILLA','ANGIE PAOLA','BRAVO LOPEZ','SEVILLA','2009-08-09','Femenino','3655215166','bravoangie@correo.test','INDEPENDIENTE','CALLE 12 # 39-5','LAS AMERICAS','SEVILLA',5,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(85,NULL,'CC','1088400784','TRUJILLO','FELIPE','TABORDA RAMIREZ','TRUJILLO','1988-09-19','Masculino','3257815316','tabordafelipe@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 35 # 97-83','EL BOSQUE','TRUJILLO',6,'CASADO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(86,NULL,'CC','193924094','RIOFRIO','FELIPE','MORENO ALVAREZ','RIOFRIO','1994-02-01','Masculino','3699138531','morenofelipe@correo.test','EMPLEADO(A)','CALLE 4 # 43-19','VILLA DEL PRADO','RIOFRIO',1,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(87,NULL,'CC','1049218823','CARTAGO','FABIAN','HENAO CARDONA','CARTAGO','1988-06-22','Masculino','3731120551','henaofabian@correo.test','EMPLEADO(A)','CALLE 7 # 52-37','BELALCAZAR','CARTAGO',3,'UNION LIBRE','SALUD TOTAL','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(88,NULL,'CC','9559996','BUGALAGRANDE','MAYERLY','GOMEZ VALENCIA','BUGALAGRANDE','1998-12-24','Femenino','3085543255','gomezmayerly@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 23 # 63-97','EL JARDIN','BUGALAGRANDE',2,'CASADO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(89,NULL,'CC','592170475','TRUJILLO','VANESSA','LONDOÑO TABORDA','TRUJILLO','2001-11-11','Femenino','3370125808','londoñovanessa@correo.test','OPERARIO(A)','CALLE 1 # 14-90','EL BOSQUE','TRUJILLO',5,'SOLTERO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(90,NULL,'CC','1030744227','CALI','DAVID','SALAZAR VALENCIA','CALI','1987-04-22','Masculino','3294999780','salazardavid@correo.test','INDEPENDIENTE','CALLE 42 # 95-77','LAS AMERICAS','CALI',2,'UNION LIBRE','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(91,NULL,'CC','842373924','ANDALUCIA','LUZ DARY','ORTIZ PALACIOS','ANDALUCIA','1992-01-12','Femenino','3068219479','ortizluz@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 72 # 69-18','EL JAZMIN','ANDALUCIA',6,'SOLTERO(A)','SALUD TOTAL','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(92,NULL,'TI','1007865553','PALMIRA','ALEJANDRO','TABORDA FLOREZ','PALMIRA','2009-11-05','Masculino','3170122210','tabordaalejandro@correo.test','ESTUDIANTE','CALLE 77 # 34-39','LA GRANJA','PALMIRA',5,'SOLTERO(A)','SALUD TOTAL','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(93,NULL,'CC','213443713','SAN PEDRO','CATALINA','YEPES ALVAREZ','SAN PEDRO','2003-04-23','Femenino','3489923533','yepescatalina@correo.test','EMPLEADO(A)','CALLE 7 # 11-60','LA GRANJA','SAN PEDRO',3,'SOLTERO(A)','MUTUAL SER','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(94,NULL,'CC','534849279','BUGALAGRANDE','FELIPE','RESTREPO OSPINA','BUGALAGRANDE','2003-03-20','Masculino','3185180576','restrepofelipe@correo.test','COMERCIANTE','CALLE 12 # 31-65','LA FLORIDA','BUGALAGRANDE',3,'SOLTERO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(95,NULL,'CC','979406362','CAICEDONIA','CAMILA','MEJIA RENGIFO','CAICEDONIA','1993-10-18','Femenino','3179362594','mejiacamila@correo.test','AMA DE CASA','CALLE 24 # 14-4','LA INDEPENDENCIA','CAICEDONIA',2,'CASADO(A)','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(96,NULL,'CC','774396563','BUGALAGRANDE','CARLOS ANDRES','YEPES GONZALEZ','BUGALAGRANDE','2000-02-01','Masculino','3287512057','yepescarlos@correo.test','OPERARIO(A)','CALLE 88 # 80-78','LOS ALAMOS','BUGALAGRANDE',1,'UNION LIBRE','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(97,NULL,'CC','1101797082','ZARZAL','SEBASTIAN','RENGIFO PEREZ','ZARZAL','1997-07-08','Masculino','3095886632','rengifosebastian@correo.test','ESTUDIANTE','CALLE 63 # 54-76','SAN JOSE','ZARZAL',3,'CASADO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(98,NULL,'CC','941450414','CARTAGO','CAMILO ANDRES','BOTERO RENGIFO','CARTAGO','2002-05-06','Masculino','3652752743','boterocamilo@correo.test','INDEPENDIENTE','CALLE 12 # 36-69','VILLA CLAUDIA','CARTAGO',5,'UNION LIBRE','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(99,NULL,'CC','468458160','TRUJILLO','CRISTIAN CAMILO','ALVAREZ ZAPATA','TRUJILLO','1989-02-19','Masculino','3093151196','alvarezcristian@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 30 # 96-99','VILLA DEL PRADO','TRUJILLO',2,'CASADO(A)','NUEVA EPS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(100,NULL,'CC','202574220','CARTAGO','YESICA','CASTILLO ECHEVERRI','CARTAGO','1989-03-20','Femenino','3430145196','castilloyesica@correo.test','INDEPENDIENTE','CALLE 28 # 4-60','LA FLORIDA','CARTAGO',3,'SOLTERO(A)','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(101,NULL,'CC','525805050','BUGALAGRANDE','ANA SOFIA','PALACIOS JIMENEZ','BUGALAGRANDE','1986-02-07','Femenino','3064724460','palaciosana@correo.test','OPERARIO(A)','CALLE 63 # 42-33','EL BOSQUE','BUGALAGRANDE',6,'UNION LIBRE','SANITAS','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(102,NULL,'CC','614207040','PALMIRA','DIANA CAROLINA','PATIÑO JIMENEZ','PALMIRA','2004-07-04','Femenino','3931454851','patiñodiana@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 38 # 11-96','VILLA DEL PRADO','PALMIRA',6,'CASADO(A)','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(103,NULL,'CC','605405543','TULUA','ANDRES FELIPE','JIMENEZ HENAO','TULUA','1988-10-04','Masculino','3438253534','jimenezandres@correo.test','EMPLEADO(A)','CALLE 3 # 33-69','LA GRANJA','TULUA',1,'SOLTERO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(104,NULL,'CC','215171053','SAN PEDRO','CARLOS ANDRES','DIAZ ROMERO','SAN PEDRO','1986-03-05','Masculino','3076264214','diazcarlos@correo.test','AUXILIAR ADMINISTRATIVO','CALLE 23 # 42-1','LA INDEPENDENCIA','SAN PEDRO',4,'SOLTERO(A)','COMPENSAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(105,NULL,'CC','580103130','ZARZAL','LAURA VALENTINA','VIAFARA MORALES','ZARZAL','1994-03-21','Femenino','3281270980','viafaralaura@correo.test','COMERCIANTE','CALLE 28 # 4-72','LAS AMERICAS','ZARZAL',1,'UNION LIBRE','COOMEVA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(106,NULL,'CC','138889979','SAN PEDRO','DANIEL','GONZALEZ VIAFARA','SAN PEDRO','1986-01-25','Masculino','3428960427','gonzalezdaniel@correo.test','INDEPENDIENTE','CALLE 19 # 40-74','BELALCAZAR','SAN PEDRO',6,'CASADO(A)','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(107,NULL,'CC','1126153209','TRUJILLO','CARLOS ANDRES','FLOREZ MARIN','TRUJILLO','1986-02-11','Masculino','3288637539','florezcarlos@correo.test','ESTUDIANTE','CALLE 28 # 78-81','CENTRO','TRUJILLO',3,'UNION LIBRE','SURA','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28'),(108,NULL,'CC','912256683','TULUA','YESICA','OSPINA MORENO','TULUA','1997-10-10','Femenino','3743484242','ospinayesica@correo.test','AMA DE CASA','CALLE 75 # 94-97','EL BOSQUE','TULUA',4,'UNION LIBRE','FAMISANAR','NINGUNA','NINGUNA',1,NULL,'manual','2026-09-06 02:57:28');
/*!40000 ALTER TABLE `estudiantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fichas_inscripcion`
--

DROP TABLE IF EXISTS `fichas_inscripcion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fichas_inscripcion` (
  `finc_id` int unsigned NOT NULL AUTO_INCREMENT,
  `estu_id` int unsigned NOT NULL,
  `prog_id` smallint unsigned DEFAULT NULL,
  `jornada` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechainscripcion` date DEFAULT NULL,
  `padr_vive` tinyint(1) DEFAULT NULL,
  `padr_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padr_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_vive` tinyint(1) DEFAULT NULL,
  `madr_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `madr_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_es` enum('padre','madre','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_parentesco` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acud_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estudio_tipo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estudio_titulo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estudio_institucion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estudio_aniofin` year DEFAULT NULL,
  `finc_estado` enum('aspirante','matriculado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aspirante',
  `fechacreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`finc_id`),
  UNIQUE KEY `uq_finc_estu` (`estu_id`),
  KEY `fk_finc_prog` (`prog_id`),
  CONSTRAINT `fk_finc_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_finc_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Datos familiares y estudios anteriores — AC-FO-02';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fichas_inscripcion`
--

LOCK TABLES `fichas_inscripcion` WRITE;
/*!40000 ALTER TABLE `fichas_inscripcion` DISABLE KEYS */;
INSERT INTO `fichas_inscripcion` VALUES (55,55,1,'SEMANA','2026-01-31',0,'JORGE LUIS','MEJIA PALACIOS','CONTADOR(A)','AGROINDUSTRIAS DEL CAUCA','3302481211','CALLE 1 # 30-65','LA GRANJA','CAICEDONIA',0,'YULY ANDREA','BRAVO VELASQUEZ','AGRICULTOR','INDEPENDIENTE','3112992423','CALLE 1 # 25-73','LAS AMERICAS','CARTAGO','otro','ABUELO(A)','TATIANA','CORREA LOPEZ','CONTADOR(A)','INDEPENDIENTE','3501865656','CALLE 38 # 52-62','SANTA ELENA','TRUJILLO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2008,'aspirante','2026-09-06 02:57:28'),(56,56,1,'SEMANA','2026-01-31',1,'FELIPE','SUAREZ QUINTERO','CONDUCTOR','AGROINDUSTRIAS DEL CAUCA','3066087407','CALLE 79 # 31-58','LA GRANJA','ZARZAL',1,'YULIANA','ROJAS LOPEZ','CONDUCTOR','COMERCIALIZADORA EL VALLE','3666031834','CALLE 43 # 37-75','VILLA DEL PRADO','RIOFRIO','otro','TIO(A)','JULIANA','CASTILLO DIAZ','MECANICO','ALCALDIA MUNICIPAL','3786538194','CALLE 21 # 6-59','EL JAZMIN','BUGA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2013,'aspirante','2026-09-06 02:57:28'),(57,57,1,'SEMANA','2026-01-31',0,'STIVEN','TABORDA CASTILLO','ENFERMERA(O)','CONFECCIONES SANTA FE','3441535957','CALLE 13 # 58-72','SAN JOSE','ANDALUCIA',1,'LAURA VALENTINA','BOTERO VIAFARA','ADMINISTRADOR(A)','HOGAR','3282231988','CALLE 55 # 44-81','LA GRANJA','CARTAGO','madre','Madre','LAURA VALENTINA','BOTERO VIAFARA','ADMINISTRADOR(A)','HOGAR','3282231988','CALLE 55 # 44-81','LA GRANJA','CARTAGO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SAN JOSE',2010,'aspirante','2026-09-06 02:57:28'),(58,58,1,'SEMANA','2026-01-31',1,'MIGUEL ANGEL','HERNANDEZ SANCHEZ','OPERARIO(A)','ALCALDIA MUNICIPAL','3978856811','CALLE 27 # 27-28','BELALCAZAR','SEVILLA',0,'MARIA JOSE','VALENCIA MEDINA','INDEPENDIENTE','TRANSPORTES TULUA','3078345822','CALLE 12 # 8-94','LA GRANJA','BUGALAGRANDE','padre','Padre','MIGUEL ANGEL','HERNANDEZ SANCHEZ','OPERARIO(A)','ALCALDIA MUNICIPAL','3978856811','CALLE 27 # 27-28','BELALCAZAR','SEVILLA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2017,'aspirante','2026-09-06 02:57:28'),(59,59,1,'SEMANA','2026-01-31',1,'CAMILO ANDRES','VARGAS FLOREZ','CONTADOR(A)','INDEPENDIENTE','3773704967','CALLE 25 # 15-52','SAN JOSE','CALI',1,'PAULA ANDREA','OSPINA VARGAS','DOCENTE','AGROINDUSTRIAS DEL CAUCA','3143130454','CALLE 71 # 67-22','VILLA DEL PRADO','PALMIRA','padre','Padre','CAMILO ANDRES','VARGAS FLOREZ','CONTADOR(A)','INDEPENDIENTE','3773704967','CALLE 25 # 15-52','SAN JOSE','CALI','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2009,'aspirante','2026-09-06 02:57:28'),(60,60,1,'SABADOS','2026-01-31',1,'KEVIN ANDRES','ECHEVERRI VIAFARA','ADMINISTRADOR(A)','TRANSPORTES TULUA','3789622609','CALLE 84 # 42-39','EL JAZMIN','CARTAGO',1,'VANESSA','QUINTERO GIRALDO','COMERCIANTE','TRANSPORTES TULUA','3271381180','CALLE 21 # 4-34','LAS AMERICAS','CARTAGO','otro','ESPOSO(A)','MELANY','SANCHEZ ROMERO','ENFERMERA(O)','CONFECCIONES SANTA FE','3957823487','CALLE 44 # 32-50','EL JARDIN','CALI','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2012,'aspirante','2026-09-06 02:57:28'),(61,61,2,'SABADOS','2026-01-31',0,'CARLOS ANDRES','HENAO MEDINA','ADMINISTRADOR(A)','TRANSPORTES TULUA','3685707838','CALLE 12 # 61-77','LAS AMERICAS','ANDALUCIA',1,'BRIGITTE','GIRALDO JIMENEZ','VENDEDOR(A)','TRANSPORTES TULUA','3485033118','CALLE 79 # 92-2','BELALCAZAR','ZARZAL','madre','Madre','BRIGITTE','GIRALDO JIMENEZ','VENDEDOR(A)','TRANSPORTES TULUA','3485033118','CALLE 79 # 92-2','BELALCAZAR','ZARZAL','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2021,'aspirante','2026-09-06 02:57:28'),(62,62,2,'SABADOS','2026-01-31',0,'FABIAN','ALVAREZ SANCHEZ','AGRICULTOR','SUPERMERCADOS LA 15','3444066151','CALLE 53 # 93-72','LA INDEPENDENCIA','TULUA',1,'LEIDY TATIANA','CASTRO BRAVO','ENFERMERA(O)','ALCALDIA MUNICIPAL','3894411250','CALLE 11 # 26-35','LAS AMERICAS','TRUJILLO','padre','Padre','FABIAN','ALVAREZ SANCHEZ','AGRICULTOR','SUPERMERCADOS LA 15','3444066151','CALLE 53 # 93-72','LA INDEPENDENCIA','TULUA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2020,'aspirante','2026-09-06 02:57:28'),(63,63,2,'SABADOS','2026-01-31',1,'FABIAN','LONDOÑO SALAZAR','COMERCIANTE','SUPERMERCADOS LA 15','3742535139','CALLE 39 # 13-28','EL BOSQUE','SAN PEDRO',0,'LEIDY TATIANA','TORRES BRAVO','ENFERMERA(O)','ALCALDIA MUNICIPAL','3358766081','CALLE 1 # 66-85','EL BOSQUE','TRUJILLO','padre','Padre','FABIAN','LONDOÑO SALAZAR','COMERCIANTE','SUPERMERCADOS LA 15','3742535139','CALLE 39 # 13-28','EL BOSQUE','SAN PEDRO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2000,'aspirante','2026-09-06 02:57:28'),(64,64,2,'SABADOS','2026-01-31',1,'SEBASTIAN','TABORDA TORRES','CONTADOR(A)','AGROINDUSTRIAS DEL CAUCA','3006428217','CALLE 18 # 18-38','SAN JOSE','TRUJILLO',1,'EMILY','RENGIFO MORENO','AGRICULTOR','COMERCIALIZADORA EL VALLE','3969793137','CALLE 72 # 91-80','EL JARDIN','TRUJILLO','padre','Padre','SEBASTIAN','TABORDA TORRES','CONTADOR(A)','AGROINDUSTRIAS DEL CAUCA','3006428217','CALLE 18 # 18-38','SAN JOSE','TRUJILLO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SAN JOSE',2005,'aspirante','2026-09-06 02:57:28'),(65,65,2,'SABADOS','2026-01-31',1,'ANDRES FELIPE','YEPES ALVAREZ','AGRICULTOR','AGROINDUSTRIAS DEL CAUCA','3327309524','CALLE 34 # 3-76','BELALCAZAR','ANDALUCIA',1,'LINA MARCELA','RODRIGUEZ LONDOÑO','ENFERMERA(O)','SUPERMERCADOS LA 15','3757063044','CALLE 31 # 72-61','SAN JOSE','CALI','otro','ESPOSO(A)','LUZ DARY','TABORDA MARTINEZ','CONDUCTOR','ALCALDIA MUNICIPAL','3690882058','CALLE 8 # 40-15','CENTRO','CAICEDONIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2017,'aspirante','2026-09-06 02:57:28'),(66,66,2,'SABADOS','2026-01-31',1,'ALEJANDRO','DIAZ GIRALDO','OPERARIO(A)','HOGAR','3078891396','CALLE 78 # 26-43','LA INDEPENDENCIA','TRUJILLO',1,'LAURA VALENTINA','OSPINA MONTOYA','AGRICULTOR','COMERCIALIZADORA EL VALLE','3165750141','CALLE 75 # 57-1','LA INDEPENDENCIA','ANDALUCIA','otro','CUÑADO(A)','SANTIAGO','DIAZ JIMENEZ','OPERARIO(A)','CONFECCIONES SANTA FE','3253499534','CALLE 88 # 37-27','VILLA DEL PRADO','SEVILLA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2008,'aspirante','2026-09-06 02:57:28'),(67,67,2,'SABADOS','2026-01-31',1,'SANTIAGO','AGUDELO LONDOÑO','ADMINISTRADOR(A)','SUPERMERCADOS LA 15','3051048604','CALLE 18 # 58-5','LA FLORIDA','PALMIRA',1,'ALEJANDRA','PALACIOS SUAREZ','CONTADOR(A)','INDEPENDIENTE','3861205801','CALLE 2 # 62-80','EL JARDIN','TULUA','padre','Padre','SANTIAGO','AGUDELO LONDOÑO','ADMINISTRADOR(A)','SUPERMERCADOS LA 15','3051048604','CALLE 18 # 58-5','LA FLORIDA','PALMIRA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2007,'aspirante','2026-09-06 02:57:28'),(68,68,2,'SEMANA','2026-01-31',1,'ANDRES FELIPE','TORRES RENGIFO','ADMINISTRADOR(A)','ALCALDIA MUNICIPAL','3404781236','CALLE 71 # 92-96','EL JAZMIN','SAN PEDRO',1,'LAURA VALENTINA','MARIN GONZALEZ','CONTADOR(A)','INDEPENDIENTE','3549243347','CALLE 30 # 74-7','LAS AMERICAS','ANDALUCIA','padre','Padre','ANDRES FELIPE','TORRES RENGIFO','ADMINISTRADOR(A)','ALCALDIA MUNICIPAL','3404781236','CALLE 71 # 92-96','EL JAZMIN','SAN PEDRO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2019,'aspirante','2026-09-06 02:57:28'),(69,69,2,'SEMANA','2026-01-31',1,'MATEO','TORRES TABORDA','DOCENTE','AGROINDUSTRIAS DEL CAUCA','3666472968','CALLE 60 # 73-68','EL JARDIN','TRUJILLO',1,'LAURA VALENTINA','LONDOÑO ROJAS','VENDEDOR(A)','CONFECCIONES SANTA FE','3765574293','CALLE 1 # 42-68','SAN JOSE','CAICEDONIA','otro','TIO(A)','MIGUEL ANGEL','RESTREPO QUINTERO','MECANICO','CONFECCIONES SANTA FE','3432297917','CALLE 42 # 67-66','VILLA DEL PRADO','TULUA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2022,'aspirante','2026-09-06 02:57:28'),(70,70,2,'SEMANA','2026-01-31',1,'STIVEN','MORALES GONZALEZ','COMERCIANTE','INDEPENDIENTE','3334965943','CALLE 46 # 5-14','BELALCAZAR','ZARZAL',1,'ISABELLA','PATIÑO MUÑOZ','OPERARIO(A)','COMERCIALIZADORA EL VALLE','3997416824','CALLE 65 # 37-97','SAN JOSE','BUGALAGRANDE','otro','ESPOSO(A)','MARIANA','MEJIA GONZALEZ','CONDUCTOR','AGROINDUSTRIAS DEL CAUCA','3484204752','CALLE 64 # 61-21','BELALCAZAR','BUGA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE CIUDAD TULUA',2021,'aspirante','2026-09-06 02:57:28'),(71,71,2,'SABADOS','2026-01-31',0,'YEISON','PALACIOS TABORDA','CONTADOR(A)','SUPERMERCADOS LA 15','3260043107','CALLE 61 # 11-17','SANTA ELENA','ANDALUCIA',1,'VALERIA','MORALES PALACIOS','DOCENTE','SUPERMERCADOS LA 15','3327788457','CALLE 19 # 36-60','VILLA CLAUDIA','ANDALUCIA','padre','Padre','YEISON','PALACIOS TABORDA','CONTADOR(A)','SUPERMERCADOS LA 15','3260043107','CALLE 61 # 11-17','SANTA ELENA','ANDALUCIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE CIUDAD TULUA',2009,'aspirante','2026-09-06 02:57:28'),(72,72,2,'SABADOS','2026-01-31',1,'RICARDO','MEDINA CASTILLO','OPERARIO(A)','HOGAR','3094057656','CALLE 77 # 52-92','LA INDEPENDENCIA','TRUJILLO',1,'DANIELA','AGUDELO RAMIREZ','DOCENTE','CONFECCIONES SANTA FE','3975554203','CALLE 28 # 70-80','EL JARDIN','CARTAGO','madre','Madre','DANIELA','AGUDELO RAMIREZ','DOCENTE','CONFECCIONES SANTA FE','3975554203','CALLE 28 # 70-80','EL JARDIN','CARTAGO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2005,'aspirante','2026-09-06 02:57:28'),(73,73,2,'SABADOS','2026-01-31',1,'ALEJANDRO','LOPEZ RUIZ','CONTADOR(A)','SUPERMERCADOS LA 15','3250135687','CALLE 84 # 70-7','LOS ALAMOS','TRUJILLO',1,'LEIDY JOHANNA','RAMIREZ PEREZ','ADMINISTRADOR(A)','INDEPENDIENTE','3061611650','CALLE 26 # 54-97','LOS ALAMOS','TULUA','otro','TIO(A)','SAMUEL','SALAZAR YEPES','INDEPENDIENTE','TRANSPORTES TULUA','3348409960','CALLE 20 # 77-13','LA GRANJA','CALI','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2015,'aspirante','2026-09-06 02:57:28'),(74,74,2,'SABADOS','2026-01-31',0,'DIEGO ARMANDO','ALVAREZ SUAREZ','DOCENTE','HOGAR','3088628700','CALLE 70 # 40-93','BELALCAZAR','BUGA',1,'GABRIELA','LOPEZ CASTILLO','MECANICO','INDEPENDIENTE','3767562415','CALLE 22 # 42-7','LAS AMERICAS','CALI','padre','Padre','DIEGO ARMANDO','ALVAREZ SUAREZ','DOCENTE','HOGAR','3088628700','CALLE 70 # 40-93','BELALCAZAR','BUGA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2023,'aspirante','2026-09-06 02:57:28'),(75,75,2,'SABADOS','2026-01-31',1,'FABIAN','GOMEZ AGUDELO','CONDUCTOR','HOGAR','3740279105','CALLE 82 # 77-40','VILLA DEL PRADO','TRUJILLO',1,'CAMILA','TORRES GONZALEZ','AGRICULTOR','AGROINDUSTRIAS DEL CAUCA','3838198418','CALLE 59 # 1-15','VILLA CLAUDIA','TULUA','padre','Padre','FABIAN','GOMEZ AGUDELO','CONDUCTOR','HOGAR','3740279105','CALLE 82 # 77-40','VILLA DEL PRADO','TRUJILLO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2003,'aspirante','2026-09-06 02:57:28'),(76,76,2,'SABADOS','2026-01-31',0,'FABIAN','CASTRO JIMENEZ','ENFERMERA(O)','SUPERMERCADOS LA 15','3362559599','CALLE 31 # 25-5','EL JARDIN','TRUJILLO',1,'VANESSA','ALVAREZ ARANGO','ENFERMERA(O)','ALCALDIA MUNICIPAL','3906692295','CALLE 53 # 22-33','CENTRO','SEVILLA','madre','Madre','VANESSA','ALVAREZ ARANGO','ENFERMERA(O)','ALCALDIA MUNICIPAL','3906692295','CALLE 53 # 22-33','CENTRO','SEVILLA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE CIUDAD TULUA',2018,'aspirante','2026-09-06 02:57:28'),(77,77,2,'SABADOS','2026-01-31',1,'SAMUEL','DIAZ VELASQUEZ','MECANICO','INDEPENDIENTE','3130883390','CALLE 37 # 78-30','LA GRANJA','ZARZAL',1,'ALEJANDRA','OSPINA URIBE','AGRICULTOR','CONFECCIONES SANTA FE','3806701959','CALLE 88 # 58-89','EL JAZMIN','SEVILLA','padre','Padre','SAMUEL','DIAZ VELASQUEZ','MECANICO','INDEPENDIENTE','3130883390','CALLE 37 # 78-30','LA GRANJA','ZARZAL','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2008,'aspirante','2026-09-06 02:57:28'),(78,78,2,'SABADOS','2026-01-31',0,'JHON ALEXANDER','BRAVO VARGAS','COMERCIANTE','INDEPENDIENTE','3506567893','CALLE 36 # 48-28','LOS ALAMOS','SAN PEDRO',1,'LINA MARCELA','LONDOÑO SANCHEZ','AMA DE CASA','INDEPENDIENTE','3803342010','CALLE 70 # 8-65','SANTA ELENA','SEVILLA','madre','Madre','LINA MARCELA','LONDOÑO SANCHEZ','AMA DE CASA','INDEPENDIENTE','3803342010','CALLE 70 # 8-65','SANTA ELENA','SEVILLA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE CIUDAD TULUA',2022,'aspirante','2026-09-06 02:57:28'),(79,79,2,'SABADOS','2026-01-31',1,'FABIAN','VALENCIA RUIZ','AMA DE CASA','CONFECCIONES SANTA FE','3458797765','CALLE 37 # 11-46','VILLA DEL PRADO','CARTAGO',1,'BRIGITTE','BEDOYA VELASQUEZ','ADMINISTRADOR(A)','SUPERMERCADOS LA 15','3830985349','CALLE 12 # 82-27','BELALCAZAR','ANDALUCIA','madre','Madre','BRIGITTE','BEDOYA VELASQUEZ','ADMINISTRADOR(A)','SUPERMERCADOS LA 15','3830985349','CALLE 12 # 82-27','BELALCAZAR','ANDALUCIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2011,'aspirante','2026-09-06 02:57:28'),(80,80,2,'SABADOS','2026-01-31',0,'FELIPE','ROMERO TABORDA','ADMINISTRADOR(A)','COMERCIALIZADORA EL VALLE','3513526452','CALLE 18 # 70-47','CENTRO','CAICEDONIA',1,'ANA SOFIA','CORREA GALLEGO','COMERCIANTE','INDEPENDIENTE','3689988156','CALLE 32 # 42-81','LA FLORIDA','BUGA','madre','Madre','ANA SOFIA','CORREA GALLEGO','COMERCIANTE','INDEPENDIENTE','3689988156','CALLE 32 # 42-81','LA FLORIDA','BUGA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2005,'aspirante','2026-09-06 02:57:28'),(81,81,2,'SABADOS','2026-01-31',0,'DANIEL','HENAO MARIN','AMA DE CASA','SUPERMERCADOS LA 15','3168547092','CALLE 7 # 22-17','EL BOSQUE','TRUJILLO',1,'ALEJANDRA','LOPEZ BRAVO','INDEPENDIENTE','TRANSPORTES TULUA','3136412725','CALLE 56 # 18-73','EL BOSQUE','RIOFRIO','madre','Madre','ALEJANDRA','LOPEZ BRAVO','INDEPENDIENTE','TRANSPORTES TULUA','3136412725','CALLE 56 # 18-73','EL BOSQUE','RIOFRIO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2022,'aspirante','2026-09-06 02:57:28'),(82,82,2,'SABADOS','2026-01-31',1,'DAVID','BEDOYA VARGAS','AMA DE CASA','TRANSPORTES TULUA','3807723913','CALLE 26 # 59-67','EL JARDIN','ZARZAL',0,'LAURA VALENTINA','MUÑOZ ROMERO','INDEPENDIENTE','TRANSPORTES TULUA','3451199992','CALLE 74 # 13-18','SAN JOSE','TULUA','madre','Madre','LAURA VALENTINA','MUÑOZ ROMERO','INDEPENDIENTE','TRANSPORTES TULUA','3451199992','CALLE 74 # 13-18','SAN JOSE','TULUA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2019,'aspirante','2026-09-06 02:57:28'),(83,83,2,'SABADOS','2026-01-31',1,'ALEJANDRO','OSPINA SALAZAR','CONTADOR(A)','COMERCIALIZADORA EL VALLE','3840370567','CALLE 52 # 14-72','SANTA ELENA','ZARZAL',1,'MARIANA','HENAO CASTRO','VENDEDOR(A)','CONFECCIONES SANTA FE','3854134404','CALLE 7 # 36-15','EL JARDIN','TULUA','madre','Madre','MARIANA','HENAO CASTRO','VENDEDOR(A)','CONFECCIONES SANTA FE','3854134404','CALLE 7 # 36-15','EL JARDIN','TULUA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2022,'aspirante','2026-09-06 02:57:28'),(84,84,2,'SABADOS','2026-01-31',1,'KEVIN ANDRES','ECHEVERRI MONTOYA','OPERARIO(A)','INDEPENDIENTE','3033921372','CALLE 41 # 13-91','LA GRANJA','TULUA',1,'VIVIANA','ORTIZ RENGIFO','CONDUCTOR','AGROINDUSTRIAS DEL CAUCA','3259068374','CALLE 18 # 64-19','LA GRANJA','SAN PEDRO','madre','Madre','VIVIANA','ORTIZ RENGIFO','CONDUCTOR','AGROINDUSTRIAS DEL CAUCA','3259068374','CALLE 18 # 64-19','LA GRANJA','SAN PEDRO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2019,'aspirante','2026-09-06 02:57:28'),(85,85,2,'SABADOS','2026-01-31',1,'FELIPE','SALAZAR AGUDELO','AMA DE CASA','HOGAR','3297515830','CALLE 61 # 89-85','VILLA DEL PRADO','CAICEDONIA',1,'VIVIANA','HERNANDEZ ROJAS','COMERCIANTE','HOGAR','3584999539','CALLE 45 # 51-30','LOS ALAMOS','BUGALAGRANDE','padre','Padre','FELIPE','SALAZAR AGUDELO','AMA DE CASA','HOGAR','3297515830','CALLE 61 # 89-85','VILLA DEL PRADO','CAICEDONIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2018,'aspirante','2026-09-06 02:57:28'),(86,86,2,'SABADOS','2026-01-31',1,'BRAYAN STEVEN','DIAZ VALENCIA','DOCENTE','COMERCIALIZADORA EL VALLE','3562214894','CALLE 7 # 74-33','EL JAZMIN','ZARZAL',0,'BRIGITTE','BEDOYA ZAPATA','AMA DE CASA','CONFECCIONES SANTA FE','3556630854','CALLE 52 # 59-88','VILLA DEL PRADO','BUGA','padre','Padre','BRAYAN STEVEN','DIAZ VALENCIA','DOCENTE','COMERCIALIZADORA EL VALLE','3562214894','CALLE 7 # 74-33','EL JAZMIN','ZARZAL','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2015,'aspirante','2026-09-06 02:57:28'),(87,87,2,'SABADOS','2026-01-31',1,'JHON ALEXANDER','VIAFARA MARIN','AMA DE CASA','INDEPENDIENTE','3676717261','CALLE 49 # 81-37','LA GRANJA','TRUJILLO',1,'MARIA JOSE','ALVAREZ MORENO','DOCENTE','AGROINDUSTRIAS DEL CAUCA','3079585204','CALLE 38 # 12-68','VILLA CLAUDIA','ZARZAL','otro','TIO(A)','NICOLAS','HERNANDEZ VELASQUEZ','VENDEDOR(A)','INDEPENDIENTE','3021717773','CALLE 29 # 84-54','EL BOSQUE','BUGA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2019,'aspirante','2026-09-06 02:57:28'),(88,88,2,'SABADOS','2026-01-31',0,'ANDRES FELIPE','FLOREZ ARANGO','VENDEDOR(A)','CONFECCIONES SANTA FE','3143753196','CALLE 40 # 88-87','VILLA DEL PRADO','ANDALUCIA',0,'ESTEFANIA','QUINTERO RUIZ','MECANICO','TRANSPORTES TULUA','3654659289','CALLE 27 # 65-86','VILLA CLAUDIA','RIOFRIO','madre','Madre','ESTEFANIA','QUINTERO RUIZ','MECANICO','TRANSPORTES TULUA','3654659289','CALLE 27 # 65-86','VILLA CLAUDIA','RIOFRIO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE NORMAL SUPERIOR',2013,'aspirante','2026-09-06 02:57:28'),(89,89,2,'SABADOS','2026-01-31',1,'JHONATAN','GONZALEZ RUIZ','VENDEDOR(A)','ALCALDIA MUNICIPAL','3876480666','CALLE 75 # 19-94','SAN JOSE','RIOFRIO',1,'LAURA VALENTINA','PATIÑO SANCHEZ','CONTADOR(A)','INDEPENDIENTE','3299827546','CALLE 48 # 37-87','VILLA DEL PRADO','ANDALUCIA','madre','Madre','LAURA VALENTINA','PATIÑO SANCHEZ','CONTADOR(A)','INDEPENDIENTE','3299827546','CALLE 48 # 37-87','VILLA DEL PRADO','ANDALUCIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2017,'aspirante','2026-09-06 02:57:28'),(90,90,2,'SABADOS','2026-01-31',1,'JULIAN','SUAREZ BRAVO','CONTADOR(A)','TRANSPORTES TULUA','3714359520','CALLE 58 # 72-33','EL JAZMIN','PALMIRA',1,'EMILY','JIMENEZ VIAFARA','AMA DE CASA','SUPERMERCADOS LA 15','3139403221','CALLE 67 # 19-69','CENTRO','CARTAGO','madre','Madre','EMILY','JIMENEZ VIAFARA','AMA DE CASA','SUPERMERCADOS LA 15','3139403221','CALLE 67 # 19-69','CENTRO','CARTAGO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2020,'aspirante','2026-09-06 02:57:28'),(91,91,2,'SABADOS','2026-01-31',1,'STIVEN','HENAO AGUDELO','OPERARIO(A)','COMERCIALIZADORA EL VALLE','3145344926','CALLE 54 # 34-18','VILLA CLAUDIA','SAN PEDRO',1,'ISABELLA','HERNANDEZ MORALES','INDEPENDIENTE','HOGAR','3355702768','CALLE 43 # 41-42','BELALCAZAR','CAICEDONIA','madre','Madre','ISABELLA','HERNANDEZ MORALES','INDEPENDIENTE','HOGAR','3355702768','CALLE 43 # 41-42','BELALCAZAR','CAICEDONIA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2017,'aspirante','2026-09-06 02:57:28'),(92,92,2,'SABADOS','2026-01-31',0,'JORGE LUIS','MEJIA MARIN','DOCENTE','COMERCIALIZADORA EL VALLE','3970846315','CALLE 79 # 21-86','SAN JOSE','SAN PEDRO',0,'MARIA JOSE','ZAPATA MEDINA','MECANICO','COMERCIALIZADORA EL VALLE','3722755588','CALLE 19 # 36-84','EL BOSQUE','CALI','padre','Padre','JORGE LUIS','MEJIA MARIN','DOCENTE','COMERCIALIZADORA EL VALLE','3970846315','CALLE 79 # 21-86','SAN JOSE','SAN PEDRO','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2019,'aspirante','2026-09-06 02:57:28'),(93,93,2,'SABADOS','2026-01-31',1,'SEBASTIAN','JIMENEZ SANCHEZ','OPERARIO(A)','CONFECCIONES SANTA FE','3346817005','CALLE 3 # 40-63','LOS ALAMOS','TULUA',1,'NATALIA','SUAREZ QUINTERO','MECANICO','SUPERMERCADOS LA 15','3770245152','CALLE 19 # 4-24','LA FLORIDA','SEVILLA','padre','Padre','SEBASTIAN','JIMENEZ SANCHEZ','OPERARIO(A)','CONFECCIONES SANTA FE','3346817005','CALLE 3 # 40-63','LOS ALAMOS','TULUA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2023,'aspirante','2026-09-06 02:57:28'),(94,94,2,'SABADOS','2026-01-31',0,'JUAN PABLO','RESTREPO VARGAS','CONTADOR(A)','HOGAR','3472237751','CALLE 12 # 96-26','EL BOSQUE','ANDALUCIA',1,'YULY ANDREA','BRAVO CASTILLO','MECANICO','INDEPENDIENTE','3076601910','CALLE 38 # 4-90','LA INDEPENDENCIA','BUGALAGRANDE','otro','ESPOSO(A)','OSCAR DAVID','DIAZ RUIZ','DOCENTE','HOGAR','3661484956','CALLE 43 # 49-76','LA INDEPENDENCIA','SEVILLA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2018,'aspirante','2026-09-06 02:57:28'),(95,95,2,'SABADOS','2026-01-31',0,'CARLOS ANDRES','MONTOYA RUIZ','OPERARIO(A)','SUPERMERCADOS LA 15','3117966300','CALLE 77 # 90-53','EL JAZMIN','ANDALUCIA',1,'GABRIELA','MARTINEZ MARIN','COMERCIANTE','AGROINDUSTRIAS DEL CAUCA','3002792891','CALLE 83 # 77-69','LAS AMERICAS','TRUJILLO','padre','Padre','CARLOS ANDRES','MONTOYA RUIZ','OPERARIO(A)','SUPERMERCADOS LA 15','3117966300','CALLE 77 # 90-53','EL JAZMIN','ANDALUCIA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2002,'aspirante','2026-09-06 02:57:28'),(96,96,2,'SABADOS','2026-01-31',1,'DIEGO ARMANDO','PEREZ HENAO','DOCENTE','INDEPENDIENTE','3283908507','CALLE 53 # 84-98','BELALCAZAR','CAICEDONIA',1,'ALEJANDRA','HERNANDEZ ROJAS','CONDUCTOR','COMERCIALIZADORA EL VALLE','3944890428','CALLE 48 # 52-13','SANTA ELENA','SAN PEDRO','otro','HERMANO(A)','CRISTIAN CAMILO','GIRALDO HENAO','MECANICO','AGROINDUSTRIAS DEL CAUCA','3475379316','CALLE 12 # 11-66','EL JAZMIN','ZARZAL','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2005,'aspirante','2026-09-06 02:57:28'),(97,97,2,'SABADOS','2026-01-31',0,'SANTIAGO','BOTERO VELASQUEZ','VENDEDOR(A)','CONFECCIONES SANTA FE','3310428690','CALLE 76 # 75-98','LA FLORIDA','CARTAGO',1,'MAYERLY','PALACIOS LOPEZ','CONTADOR(A)','INDEPENDIENTE','3195631406','CALLE 22 # 7-8','LAS AMERICAS','RIOFRIO','madre','Madre','MAYERLY','PALACIOS LOPEZ','CONTADOR(A)','INDEPENDIENTE','3195631406','CALLE 22 # 7-8','LAS AMERICAS','RIOFRIO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2015,'aspirante','2026-09-06 02:57:28'),(98,98,1,'SABADOS','2026-01-31',1,'JULIAN','PATIÑO VALENCIA','VENDEDOR(A)','HOGAR','3102959207','CALLE 81 # 7-93','EL JARDIN','BUGA',1,'ANA SOFIA','PEREZ AGUDELO','DOCENTE','AGROINDUSTRIAS DEL CAUCA','3851862485','CALLE 68 # 29-30','EL JAZMIN','PALMIRA','padre','Padre','JULIAN','PATIÑO VALENCIA','VENDEDOR(A)','HOGAR','3102959207','CALLE 81 # 7-93','EL JARDIN','BUGA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2011,'aspirante','2026-09-06 02:57:28'),(99,99,1,'SABADOS','2026-01-31',1,'JHON ALEXANDER','JIMENEZ MORENO','CONDUCTOR','AGROINDUSTRIAS DEL CAUCA','3219810791','CALLE 6 # 44-36','VILLA DEL PRADO','CALI',1,'ANGIE PAOLA','JIMENEZ ZAPATA','VENDEDOR(A)','AGROINDUSTRIAS DEL CAUCA','3752406007','CALLE 31 # 87-79','EL JAZMIN','BUGA','otro','ABUELO(A)','JULIAN','BOTERO SUAREZ','MECANICO','CONFECCIONES SANTA FE','3376767918','CALLE 13 # 98-22','SANTA ELENA','TRUJILLO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2016,'aspirante','2026-09-06 02:57:28'),(100,100,1,'SABADOS','2026-01-31',1,'CAMILO ANDRES','ROMERO HERNANDEZ','COMERCIANTE','ALCALDIA MUNICIPAL','3569944245','CALLE 84 # 55-3','LAS AMERICAS','CARTAGO',1,'VIVIANA','MORENO CARDONA','ADMINISTRADOR(A)','INDEPENDIENTE','3863706331','CALLE 63 # 3-24','EL JAZMIN','ZARZAL','madre','Madre','VIVIANA','MORENO CARDONA','ADMINISTRADOR(A)','INDEPENDIENTE','3863706331','CALLE 63 # 3-24','EL JAZMIN','ZARZAL','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2015,'aspirante','2026-09-06 02:57:28'),(101,101,1,'SABADOS','2026-01-31',1,'YEISON','CASTILLO RESTREPO','CONDUCTOR','TRANSPORTES TULUA','3083360716','CALLE 85 # 41-99','CENTRO','SEVILLA',1,'CAMILA','PATIÑO OSPINA','ADMINISTRADOR(A)','TRANSPORTES TULUA','3789616078','CALLE 57 # 74-64','LA GRANJA','SAN PEDRO','madre','Madre','CAMILA','PATIÑO OSPINA','ADMINISTRADOR(A)','TRANSPORTES TULUA','3789616078','CALLE 57 # 74-64','LA GRANJA','SAN PEDRO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SAN JOSE',2011,'aspirante','2026-09-06 02:57:28'),(102,102,1,'SABADOS','2026-01-31',1,'JULIAN','CASTRO ROJAS','DOCENTE','TRANSPORTES TULUA','3248538216','CALLE 14 # 66-5','LA INDEPENDENCIA','ZARZAL',0,'MAYERLY','TORRES LOPEZ','CONTADOR(A)','AGROINDUSTRIAS DEL CAUCA','3551617707','CALLE 71 # 78-63','VILLA CLAUDIA','CAICEDONIA','padre','Padre','JULIAN','CASTRO ROJAS','DOCENTE','TRANSPORTES TULUA','3248538216','CALLE 14 # 66-5','LA INDEPENDENCIA','ZARZAL','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE NUESTRA SEÑORA DEL ROSARIO',2009,'aspirante','2026-09-06 02:57:28'),(103,103,1,'SABADOS','2026-01-31',1,'FELIPE','CORREA LONDOÑO','OPERARIO(A)','CONFECCIONES SANTA FE','3912599858','CALLE 72 # 37-93','LA FLORIDA','CALI',1,'CATALINA','MEJIA RESTREPO','OPERARIO(A)','INDEPENDIENTE','3993213057','CALLE 16 # 31-46','EL JAZMIN','ZARZAL','madre','Madre','CATALINA','MEJIA RESTREPO','OPERARIO(A)','INDEPENDIENTE','3993213057','CALLE 16 # 31-46','EL JAZMIN','ZARZAL','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE MARCO FIDEL SUAREZ',2004,'aspirante','2026-09-06 02:57:28'),(104,104,1,'SABADOS','2026-01-31',1,'SEBASTIAN','LONDOÑO MEDINA','VENDEDOR(A)','COMERCIALIZADORA EL VALLE','3073252517','CALLE 62 # 92-45','LAS AMERICAS','CALI',1,'VALERIA','MONTOYA PALACIOS','CONTADOR(A)','CONFECCIONES SANTA FE','3411136798','CALLE 74 # 8-74','VILLA DEL PRADO','TRUJILLO','padre','Padre','SEBASTIAN','LONDOÑO MEDINA','VENDEDOR(A)','COMERCIALIZADORA EL VALLE','3073252517','CALLE 62 # 92-45','LAS AMERICAS','CALI','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2009,'aspirante','2026-09-06 02:57:28'),(105,105,1,'SEMANA','2026-01-31',1,'SAMUEL','BEDOYA URIBE','ENFERMERA(O)','INDEPENDIENTE','3830909131','CALLE 13 # 2-74','LAS AMERICAS','BUGA',1,'TATIANA','VIAFARA ARANGO','MECANICO','INDEPENDIENTE','3409777652','CALLE 74 # 14-12','EL JARDIN','CARTAGO','padre','Padre','SAMUEL','BEDOYA URIBE','ENFERMERA(O)','INDEPENDIENTE','3830909131','CALLE 13 # 2-74','LAS AMERICAS','BUGA','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2015,'aspirante','2026-09-06 02:57:28'),(106,106,1,'SEMANA','2026-01-31',1,'ANDRES FELIPE','PALACIOS MONTOYA','ADMINISTRADOR(A)','AGROINDUSTRIAS DEL CAUCA','3654822433','CALLE 21 # 96-71','LA FLORIDA','CARTAGO',1,'YULY ANDREA','GOMEZ PALACIOS','OPERARIO(A)','SUPERMERCADOS LA 15','3711939634','CALLE 13 # 83-63','LA INDEPENDENCIA','TULUA','madre','Madre','YULY ANDREA','GOMEZ PALACIOS','OPERARIO(A)','SUPERMERCADOS LA 15','3711939634','CALLE 13 # 83-63','LA INDEPENDENCIA','TULUA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','COLEGIO SANTA LUCIA',2008,'aspirante','2026-09-06 02:57:28'),(107,107,1,'SEMANA','2026-01-31',1,'CAMILO ANDRES','MUÑOZ RODRIGUEZ','AGRICULTOR','AGROINDUSTRIAS DEL CAUCA','3420543956','CALLE 16 # 76-91','EL JARDIN','SEVILLA',1,'MARIANA','PEREZ VELASQUEZ','CONDUCTOR','ALCALDIA MUNICIPAL','3975254779','CALLE 35 # 90-39','LAS AMERICAS','TRUJILLO','otro','HERMANO(A)','JULIAN','PALACIOS DIAZ','VENDEDOR(A)','INDEPENDIENTE','3445761062','CALLE 79 # 96-6','BELALCAZAR','SAN PEDRO','BACHILLERATO ACADEMICO','BACHILLER ACADEMICO','IE SANTA TERESITA',2014,'aspirante','2026-09-06 02:57:28'),(108,108,1,'SEMANA','2026-01-31',0,'YEISON','LONDOÑO VIAFARA','ENFERMERA(O)','AGROINDUSTRIAS DEL CAUCA','3526504767','CALLE 80 # 34-90','VILLA DEL PRADO','SEVILLA',1,'JOHANA','TORRES FLOREZ','AMA DE CASA','HOGAR','3290199542','CALLE 43 # 37-39','LA FLORIDA','SAN PEDRO','padre','Padre','YEISON','LONDOÑO VIAFARA','ENFERMERA(O)','AGROINDUSTRIAS DEL CAUCA','3526504767','CALLE 80 # 34-90','VILLA DEL PRADO','SEVILLA','BACHILLERATO TECNICO','BACHILLER ACADEMICO','IE SAN JOSE',2013,'aspirante','2026-09-06 02:57:28');
/*!40000 ALTER TABLE `fichas_inscripcion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grmoestudiantes`
--

DROP TABLE IF EXISTS `grmoestudiantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grmoestudiantes` (
  `grmo_id` int unsigned NOT NULL,
  `estu_id` int unsigned NOT NULL,
  PRIMARY KEY (`grmo_id`,`estu_id`),
  KEY `fk_grmo_estu_estu` (`estu_id`),
  CONSTRAINT `fk_grmo_estu_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_grmo_estu_grmo` FOREIGN KEY (`grmo_id`) REFERENCES `gruposmodulos` (`grmo_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estudiantes asignados a cada módulo específico — reemplaza grseestudiantes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grmoestudiantes`
--

LOCK TABLES `grmoestudiantes` WRITE;
/*!40000 ALTER TABLE `grmoestudiantes` DISABLE KEYS */;
INSERT INTO `grmoestudiantes` VALUES (3,66),(3,77),(3,79);
/*!40000 ALTER TABLE `grmoestudiantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gruposemestres`
--

DROP TABLE IF EXISTS `gruposemestres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gruposemestres` (
  `grse_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_id` smallint unsigned NOT NULL,
  `coho_id` smallint unsigned DEFAULT NULL,
  `peri_id` smallint unsigned NOT NULL,
  `grse_codigo` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grse_semestre` tinyint unsigned NOT NULL,
  `fechainicio` date DEFAULT NULL,
  `fechafin` date DEFAULT NULL,
  `grse_activo` tinyint(1) NOT NULL DEFAULT '1',
  `grse_jornada` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Semana',
  PRIMARY KEY (`grse_id`),
  UNIQUE KEY `uq_grse_codigo` (`grse_codigo`),
  KEY `fk_grse_prog` (`prog_id`),
  KEY `fk_grse_peri` (`peri_id`),
  KEY `fk_grse_coho` (`coho_id`),
  CONSTRAINT `fk_grse_coho` FOREIGN KEY (`coho_id`) REFERENCES `cohortes` (`coho_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_grse_peri` FOREIGN KEY (`peri_id`) REFERENCES `periodos` (`peri_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grse_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grupos semestre (cohorte cursando un período)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gruposemestres`
--

LOCK TABLES `gruposemestres` WRITE;
/*!40000 ALTER TABLE `gruposemestres` DISABLE KEYS */;
INSERT INTO `gruposemestres` VALUES (1,2,8,5,'MD_2026-2_S3_SAB',3,'2026-08-08','2026-12-19',1,'Sabados'),(2,2,1,5,'MD_2026-2_S1_SAB',1,'2026-08-08','2026-12-20',1,'Sabados'),(3,2,8,5,'MD_2026-2_S2_SEM',2,'2026-08-08','2026-12-19',1,'Semana'),(4,1,9,5,'ASO_2026-2_S2_SAB',2,'2026-08-17','2026-08-23',1,'Sabados'),(5,1,7,5,'ASO_2026-2_S1_SEM',1,'2026-08-17','2026-12-17',1,'Semana'),(6,1,7,5,'ASO_2026-2_S1_SAB',1,'2026-08-08','2026-12-19',1,'Sabados'),(8,6,10,5,'ASO2016_2026-2_S3_SAB',3,'2026-08-08','2026-12-19',1,'Sabados'),(9,5,11,5,'AMD2016_2026-2_S4_SAB',4,'2026-08-08','2026-12-19',1,'Sabados'),(10,6,10,5,'ASO2016_2026-2_S2_SEM',2,'2026-08-08','2026-12-19',1,'Semana');
/*!40000 ALTER TABLE `gruposemestres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gruposmodulos`
--

DROP TABLE IF EXISTS `gruposmodulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gruposmodulos` (
  `grmo_id` int unsigned NOT NULL AUTO_INCREMENT,
  `grse_id` smallint unsigned NOT NULL,
  `modu_id` smallint unsigned NOT NULL,
  `doce_id` smallint unsigned NOT NULL,
  `grmo_horario` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechainicio` date DEFAULT NULL,
  `fechafin` date DEFAULT NULL,
  `grmo_activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`grmo_id`),
  UNIQUE KEY `uq_grmo_grse_modu` (`grse_id`,`modu_id`),
  KEY `fk_grmo_modu` (`modu_id`),
  KEY `fk_grmo_doce` (`doce_id`),
  CONSTRAINT `fk_grmo_doce` FOREIGN KEY (`doce_id`) REFERENCES `docentes` (`doce_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grmo_grse` FOREIGN KEY (`grse_id`) REFERENCES `gruposemestres` (`grse_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_grmo_modu` FOREIGN KEY (`modu_id`) REFERENCES `modulos` (`modu_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Docente asignado a cada módulo dentro de un grupo semestre';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gruposmodulos`
--

LOCK TABLES `gruposmodulos` WRITE;
/*!40000 ALTER TABLE `gruposmodulos` DISABLE KEYS */;
INSERT INTO `gruposmodulos` VALUES (1,1,1,1,'8:00 A 11:00','2026-08-08','2026-09-12',1),(2,1,3,1,'8:00 A 11:00','2026-08-08','2026-09-08',1),(3,2,18,3,'','2026-08-08','2026-12-20',1),(5,2,19,3,'','2026-08-08','2026-11-15',1),(6,2,22,3,'','2026-08-08','2026-11-15',1),(7,2,23,3,'','2026-08-08','2026-11-15',1),(8,2,25,3,'','2026-08-08','2026-12-12',1),(10,4,1,2,'','2026-08-08','2026-10-31',1);
/*!40000 ALTER TABLE `gruposmodulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `horariosgrupo`
--

DROP TABLE IF EXISTS `horariosgrupo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `horariosgrupo` (
  `hora_id` int unsigned NOT NULL AUTO_INCREMENT,
  `grse_id` smallint unsigned NOT NULL,
  `hora_diasemana` tinyint unsigned NOT NULL,
  `hora_horainicio` time NOT NULL,
  `hora_horafin` time DEFAULT NULL,
  `hora_aula` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`hora_id`),
  KEY `fk_hora_grse` (`grse_id`),
  CONSTRAINT `fk_hora_grse` FOREIGN KEY (`grse_id`) REFERENCES `gruposemestres` (`grse_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `horariosgrupo`
--

LOCK TABLES `horariosgrupo` WRITE;
/*!40000 ALTER TABLE `horariosgrupo` DISABLE KEYS */;
/*!40000 ALTER TABLE `horariosgrupo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `matriculas`
--

DROP TABLE IF EXISTS `matriculas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matriculas` (
  `matr_id` int unsigned NOT NULL AUTO_INCREMENT,
  `estu_id` int unsigned NOT NULL,
  `prog_id` smallint unsigned NOT NULL,
  `peri_id` smallint unsigned NOT NULL,
  `coho_id` smallint unsigned DEFAULT NULL,
  `matr_estado` enum('aspirante','matriculado','retirado','graduado','cursado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aspirante',
  `matr_estado_academico` enum('Activo','Aplazamiento','Retirado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo',
  `matr_semestre` tinyint unsigned NOT NULL DEFAULT '1',
  `matr_folio` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `matr_numero` int unsigned DEFAULT NULL,
  `matr_matriculadopor` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechainscripcion` date DEFAULT NULL,
  `fechamatricula` date DEFAULT NULL,
  `matr_observacion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`matr_id`),
  UNIQUE KEY `uq_matr_estu_peri_prog` (`estu_id`,`peri_id`,`prog_id`),
  UNIQUE KEY `uq_matr_numero` (`matr_numero`),
  KEY `fk_matr_prog` (`prog_id`),
  KEY `fk_matr_peri` (`peri_id`),
  KEY `coho_id` (`coho_id`),
  CONSTRAINT `fk_matr_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_matr_peri` FOREIGN KEY (`peri_id`) REFERENCES `periodos` (`peri_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_matr_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `matriculas_ibfk_1` FOREIGN KEY (`coho_id`) REFERENCES `cohortes` (`coho_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inscripción y matrícula por período — AC-FO-02 y AC-FO-09';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `matriculas`
--

LOCK TABLES `matriculas` WRITE;
/*!40000 ALTER TABLE `matriculas` DISABLE KEYS */;
INSERT INTO `matriculas` VALUES (2,79,2,5,1,'matriculado','Activo',1,NULL,NULL,NULL,'2026-09-05','2026-09-06',NULL),(3,66,2,5,1,'matriculado','Activo',1,NULL,NULL,NULL,'2026-09-05','2026-09-06',NULL),(4,77,2,5,1,'matriculado','Activo',1,NULL,NULL,NULL,'2026-09-05','2026-09-06',NULL);
/*!40000 ALTER TABLE `matriculas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos` (
  `modu_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_id` smallint unsigned NOT NULL,
  `modu_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modu_sigla` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modu_orden` tinyint unsigned NOT NULL DEFAULT '1',
  `modu_activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`modu_id`),
  UNIQUE KEY `uq_modu_sigla_prog` (`prog_id`,`modu_sigla`),
  CONSTRAINT `fk_modu_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Módulos académicos por programa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

LOCK TABLES `modulos` WRITE;
/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES (1,1,'Anatomía y Fisiología General','ASO-AF1',1,1),(2,1,'Biología Oral','ASO-BIO',1,1),(3,1,'Ética y Humanización en Salud','ASO-ETI',1,1),(4,1,'Inglés Técnico I','ASO-ING1',1,1),(5,1,'Anatomía y Fisiología Oral','ASO-AF2',2,1),(6,1,'Radiología Oral Básica','ASO-RAD',2,1),(7,1,'Microbiología e Higiene Oral','ASO-MIC',2,1),(8,1,'Asistencia en Odontología General','ASO-AOG',2,1),(9,1,'Asistencia en Ortodoncia','ASO-ORT',3,1),(10,1,'Asistencia en Endodoncia','ASO-END',3,1),(11,1,'Asistencia en Cirugía Oral','ASO-CIR',3,1),(12,1,'Inglés Técnico II','ASO-ING2',3,1),(13,1,'Asistencia en Periodoncia','ASO-PER',4,1),(14,1,'Asistencia en Odontopediatría','ASO-ODP',4,1),(15,1,'Salud Pública y Epidemiología Oral','ASO-SPE',4,1),(16,1,'Gestión Administrativa en Salud','ASO-GAS',4,1),(17,1,'Práctica Clínica Integradora','ASO-PCI',4,1),(18,2,'INTRODUCCION A LA MECANICA DENTAL','MD-IMD',1,1),(19,2,'MORFOLOGIA - TEORIA','MD-MORF-T',1,1),(20,2,'Biología General','MD-BIO',4,1),(21,2,'PROTESIS TOTAL - TEORIA','MD-PRTOT-T',2,1),(22,2,'BIOMATERIALES DENTALES -TEORIA','MD-BIOM-T',1,1),(23,2,'BIOMATERIALES DENTALES - PRACTICA','MD-BIOM-P',1,1),(24,2,'Prótesis Parcial Removible I','MD-PPR1',4,1),(25,2,'MORFOLOGIA - PRACTICA','MD-MORF-P',1,1),(26,2,'Prótesis Parcial Removible II','MD-PPR2',3,1),(27,2,'Prótesis Total','MD-PTO',3,1),(28,2,'Prótesis Fija I','MD-PFI1',3,1),(29,2,'PROTESIS TOTAL - PRACTICA','MD-PRTOT-P',2,1),(30,2,'ANATOCLUSION E HISTORIA CLINICA','MD-ANHC',1,1),(31,2,'Prótesis Fija II','MD-PFI2',4,1),(32,2,'Ortodoncia de Laboratorio','MD-ORT',4,1),(33,2,'Implantología Protésica','MD-IMP',4,1),(34,2,'Gestión y Administración de Laboratorio','MD-GAL',4,1),(35,2,'Higiene y Bioseguridad','MD-HBS',4,1),(36,2,'Práctica de Laboratorio Integradora','MD-PLI',4,1);
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notasn3`
--

DROP TABLE IF EXISTS `notasn3`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notasn3` (
  `non3_id` int unsigned NOT NULL AUTO_INCREMENT,
  `acn3_id` int unsigned NOT NULL,
  `estu_id` int unsigned NOT NULL,
  `non3_valor` decimal(3,1) DEFAULT NULL,
  `fechaactualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`non3_id`),
  UNIQUE KEY `uq_non3_acn3_estu` (`acn3_id`,`estu_id`),
  KEY `fk_non3_estu` (`estu_id`),
  CONSTRAINT `fk_non3_acn3` FOREIGN KEY (`acn3_id`) REFERENCES `actividadesn3` (`acn3_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_non3_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `notasn3_chk_1` CHECK ((`non3_valor` between 0.0 and 5.0))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nota de cada estudiante por actividad de N3 (nota configurable)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notasn3`
--

LOCK TABLES `notasn3` WRITE;
/*!40000 ALTER TABLE `notasn3` DISABLE KEYS */;
INSERT INTO `notasn3` VALUES (7,9,79,4.5,'2026-09-06 04:27:03'),(8,10,79,3.0,'2026-09-06 04:27:09'),(9,11,79,4.0,'2026-09-06 04:27:17'),(10,9,66,2.6,'2026-09-06 04:27:25'),(11,10,66,3.4,'2026-09-06 04:27:27'),(12,11,66,2.8,'2026-09-06 04:27:33'),(13,9,77,5.0,'2026-09-06 04:27:37'),(14,10,77,2.0,'2026-09-06 04:27:45'),(15,11,77,3.0,'2026-09-06 04:27:48');
/*!40000 ALTER TABLE `notasn3` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos`
--

DROP TABLE IF EXISTS `periodos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `periodos` (
  `peri_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `peri_codigo` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `peri_anio` year NOT NULL,
  `peri_semestre` tinyint unsigned NOT NULL,
  `fechainicio` date DEFAULT NULL,
  `fechafin` date DEFAULT NULL,
  `peri_activo` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`peri_id`),
  UNIQUE KEY `uq_peri_codigo` (`peri_codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos`
--

LOCK TABLES `periodos` WRITE;
/*!40000 ALTER TABLE `periodos` DISABLE KEYS */;
INSERT INTO `periodos` VALUES (1,'2025-1',2025,1,'2025-01-20','2025-06-20',0),(2,'2025-2',2025,2,'2025-07-21','2025-12-12',0),(3,'2026-1',2026,1,'2026-01-19','2026-06-19',0),(5,'2026-2',2026,2,'2026-08-08','2026-12-20',1),(8,'2027-1',2027,1,'2027-01-01','2027-06-01',0);
/*!40000 ALTER TABLE `periodos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programa_modulos`
--

DROP TABLE IF EXISTS `programa_modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programa_modulos` (
  `prmo_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_id` smallint unsigned NOT NULL,
  `modu_id` smallint unsigned NOT NULL,
  `prmo_semestre_sugerido` tinyint unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`prmo_id`),
  UNIQUE KEY `uq_prmo_prog_modu` (`prog_id`,`modu_id`),
  KEY `fk_prmo_modu` (`modu_id`),
  CONSTRAINT `fk_prmo_modu` FOREIGN KEY (`modu_id`) REFERENCES `modulos` (`modu_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_prmo_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Semestre sugerido por módulo según programa — flexible al armar grupos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programa_modulos`
--

LOCK TABLES `programa_modulos` WRITE;
/*!40000 ALTER TABLE `programa_modulos` DISABLE KEYS */;
/*!40000 ALTER TABLE `programa_modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programas`
--

DROP TABLE IF EXISTS `programas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programas` (
  `prog_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_nombre` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prog_sigla` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `prog_resolucion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prog_fechaaprobacion` date DEFAULT NULL,
  `prog_fechavencimiento` date DEFAULT NULL,
  `prog_duracion_semestres` tinyint unsigned NOT NULL DEFAULT '4',
  `prog_descripcion` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `prog_activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`prog_id`),
  UNIQUE KEY `uq_prog_sigla` (`prog_sigla`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programas académicos ofertados por la EMDB';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programas`
--

LOCK TABLES `programas` WRITE;
/*!40000 ALTER TABLE `programas` DISABLE KEYS */;
INSERT INTO `programas` VALUES (1,'2026 AUXILIAR EN SALUD ORAL','ASO','310-59.2.0032','2026-01-15','2031-01-15',4,NULL,1),(2,'2026 TECNICO EN MECANICA DENTAL','MD','310-59.2.0033','2026-01-15','2031-01-15',5,NULL,1),(5,'2016 AUXILIAR EN MECANICA DENTAL','AMD2016','310-054.483','2016-06-27','2023-05-12',4,'Tuvo una duración de 7 años, debido a que tuvimos activa la certificación del calidad Icontec.',1),(6,'2016 AUXILIAR EN SALUD ORAL','ASO2016','310-054.346','2016-05-12','2023-05-12',3,NULL,1);
/*!40000 ALTER TABLE `programas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requisitos_estudiante`
--

DROP TABLE IF EXISTS `requisitos_estudiante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisitos_estudiante` (
  `reqe_id` int unsigned NOT NULL AUTO_INCREMENT,
  `matr_id` int unsigned NOT NULL,
  `reqp_id` smallint unsigned NOT NULL,
  `reqe_estado` enum('pendiente','entregado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `reqe_fecha` date DEFAULT NULL,
  `reqe_fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reqe_id`),
  UNIQUE KEY `uq_reqe_matr_reqp` (`matr_id`,`reqp_id`),
  KEY `fk_reqe_reqp` (`reqp_id`),
  CONSTRAINT `fk_reqe_matr` FOREIGN KEY (`matr_id`) REFERENCES `matriculas` (`matr_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reqe_reqp` FOREIGN KEY (`reqp_id`) REFERENCES `requisitos_programa` (`reqp_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Estado de requisitos documentales entregados por matrcula';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisitos_estudiante`
--

LOCK TABLES `requisitos_estudiante` WRITE;
/*!40000 ALTER TABLE `requisitos_estudiante` DISABLE KEYS */;
INSERT INTO `requisitos_estudiante` VALUES (16,2,30,'pendiente',NULL,'2026-09-06 03:02:58'),(17,2,31,'pendiente',NULL,'2026-09-06 03:02:58'),(18,2,32,'pendiente',NULL,'2026-09-06 03:02:58'),(19,2,33,'entregado','2026-09-05','2026-09-06 03:06:46'),(20,2,34,'pendiente',NULL,'2026-09-06 03:02:58'),(21,2,35,'pendiente',NULL,'2026-09-06 03:02:58'),(22,2,36,'pendiente',NULL,'2026-09-06 03:02:58'),(23,2,37,'pendiente',NULL,'2026-09-06 03:02:58'),(31,3,30,'pendiente',NULL,'2026-09-06 03:03:17'),(32,3,31,'pendiente',NULL,'2026-09-06 03:03:17'),(33,3,32,'pendiente',NULL,'2026-09-06 03:03:17'),(34,3,33,'pendiente',NULL,'2026-09-06 03:03:17'),(35,3,34,'pendiente',NULL,'2026-09-06 03:03:17'),(36,3,35,'pendiente',NULL,'2026-09-06 03:03:17'),(37,3,36,'pendiente',NULL,'2026-09-06 03:03:17'),(38,3,37,'pendiente',NULL,'2026-09-06 03:03:17'),(46,4,30,'pendiente',NULL,'2026-09-06 03:03:32'),(47,4,31,'pendiente',NULL,'2026-09-06 03:03:32'),(48,4,32,'pendiente',NULL,'2026-09-06 03:03:32'),(49,4,33,'pendiente',NULL,'2026-09-06 03:03:32'),(50,4,34,'pendiente',NULL,'2026-09-06 03:03:32'),(51,4,35,'pendiente',NULL,'2026-09-06 03:03:32'),(52,4,36,'pendiente',NULL,'2026-09-06 03:03:32'),(53,4,37,'pendiente',NULL,'2026-09-06 03:03:32');
/*!40000 ALTER TABLE `requisitos_estudiante` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requisitos_programa`
--

DROP TABLE IF EXISTS `requisitos_programa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisitos_programa` (
  `reqp_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `prog_id` smallint unsigned NOT NULL,
  `reqp_nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reqp_descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reqp_activo` tinyint(1) NOT NULL DEFAULT '1',
  `reqp_fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reqp_id`),
  KEY `fk_reqp_prog` (`prog_id`),
  CONSTRAINT `fk_reqp_prog` FOREIGN KEY (`prog_id`) REFERENCES `programas` (`prog_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catlogo de requisitos documentales configurable por programa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisitos_programa`
--

LOCK TABLES `requisitos_programa` WRITE;
/*!40000 ALTER TABLE `requisitos_programa` DISABLE KEYS */;
INSERT INTO `requisitos_programa` VALUES (23,1,'Fotocopia del Diploma de Bachiller',NULL,1,'2026-09-04 13:42:37'),(24,1,'Fotocopia del Acta de grado Bachiller',NULL,1,'2026-09-04 13:42:49'),(25,1,'Fotocopia de Documento de identidad',NULL,1,'2026-09-04 13:43:04'),(26,1,'Certificado Médico',NULL,1,'2026-09-04 13:53:55'),(27,1,'Certificado Vacuna Tétano',NULL,1,'2026-09-04 13:54:09'),(28,1,'Certificado Vacuna Hepatitis B',NULL,1,'2026-09-04 13:54:25'),(29,1,'Constancia de afiliación a EPS',NULL,1,'2026-09-04 13:54:39'),(30,2,'Fotocopia del Diploma de Bachiller',NULL,1,'2026-09-04 13:55:12'),(31,2,'Fotocopia del Acta de grado Bachiller',NULL,1,'2026-09-04 13:55:24'),(32,2,'Fotocopia de Documento de identidad',NULL,1,'2026-09-04 13:55:35'),(33,2,'5 fotos 3×4',NULL,1,'2026-09-04 13:55:51'),(34,2,'Certificado Médico',NULL,1,'2026-09-04 13:56:03'),(35,2,'Certificado Vacuna Tétano',NULL,1,'2026-09-04 13:56:17'),(36,2,'Certificado Vacuna Hepatitis B',NULL,1,'2026-09-04 13:56:28'),(37,2,'Constancia de afiliación a EPS',NULL,1,'2026-09-04 13:56:40'),(38,1,'5 fotos 3×4',NULL,1,'2026-09-04 13:57:14');
/*!40000 ALTER TABLE `requisitos_programa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `role_id` tinyint unsigned NOT NULL AUTO_INCREMENT,
  `role_nombre` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_role_nombre` (`role_nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de roles de acceso al sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador'),(2,'Coordinador'),(3,'Docente'),(4,'Estudiante');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `solicitudes_actualizacion`
--

DROP TABLE IF EXISTS `solicitudes_actualizacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitudes_actualizacion` (
  `soac_id` int unsigned NOT NULL AUTO_INCREMENT,
  `estu_id` int unsigned NOT NULL,
  `soac_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `soac_generado_en` datetime NOT NULL,
  `soac_expira_en` datetime NOT NULL,
  `soac_estado` enum('generado','recibido','aprobado','descartado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generado',
  `soac_recibido_en` datetime DEFAULT NULL,
  `soac_resuelto_en` datetime DEFAULT NULL,
  `soac_resuelto_por` int unsigned DEFAULT NULL,
  `soac_campos_modificados` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `soac_estu_expedidoen` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_ciudadnac` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_fechanacimiento` date DEFAULT NULL,
  `soac_estu_sexo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_ocupacion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_estrato` tinyint unsigned DEFAULT NULL,
  `soac_estu_estadocivil` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_eps` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_discapacidad` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_estu_multiculturalidad` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_prog_id` smallint unsigned DEFAULT NULL,
  `soac_ficha_jornada` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_fechainscripcion` date DEFAULT NULL,
  `soac_ficha_padr_vive` tinyint(1) DEFAULT NULL,
  `soac_ficha_padr_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_padr_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_vive` tinyint(1) DEFAULT NULL,
  `soac_ficha_madr_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_madr_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_es` enum('padre','madre','otro') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_parentesco` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_nombres` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_apellidos` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_profesion` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_empresa` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_telefono` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_direccion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_barrio` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_acud_ciudad` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_estudio_tipo` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_estudio_titulo` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_estudio_institucion` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `soac_ficha_estudio_aniofin` year DEFAULT NULL,
  `fechacreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`soac_id`),
  UNIQUE KEY `uq_soac_token` (`soac_token`),
  KEY `fk_soac_estu` (`estu_id`),
  KEY `fk_soac_resuelto_por` (`soac_resuelto_por`),
  CONSTRAINT `fk_soac_estu` FOREIGN KEY (`estu_id`) REFERENCES `estudiantes` (`estu_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_soac_resuelto_por` FOREIGN KEY (`soac_resuelto_por`) REFERENCES `usuarios` (`usua_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitudes de actualizaciÃ³n de datos vÃ­a link pÃºblico â€” espejo editable de estudiantes+fichas_inscripcion, pendiente de aprobaciÃ³n del coordinador';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `solicitudes_actualizacion`
--

LOCK TABLES `solicitudes_actualizacion` WRITE;
/*!40000 ALTER TABLE `solicitudes_actualizacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `solicitudes_actualizacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `usua_id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint unsigned NOT NULL,
  `usua_login` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usua_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usua_nombre` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usua_passwordhash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `usua_activo` tinyint(1) NOT NULL DEFAULT '1',
  `usua_ultimo_acceso` datetime DEFAULT NULL,
  `fechacreacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`usua_id`),
  UNIQUE KEY `uq_usua_email` (`usua_email`),
  UNIQUE KEY `uq_usua_login` (`usua_login`),
  KEY `fk_usua_role` (`role_id`),
  CONSTRAINT `fk_usua_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credenciales de acceso al sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,NULL,'admin@emdb.edu.co','Administrador','$2y$12$QT8nad.2sgsm4rbRmCHUcOlyTXuT0kc0ft.BWv6/lwJ3Qo5HY8WiS',1,'2026-09-05 23:07:47','2026-07-25 21:03:18'),(2,1,NULL,'admin@correo.com','','$2y$12$PsvjzJXg4GiCI.bVLRD7XORf4WIN9Kq.hCQ9L8L9CzrxvPKH6lJOq',1,'2026-09-05 22:07:21','2026-07-25 22:47:36'),(3,2,NULL,'coordinador@correo.com','usuario coordinador','$2y$12$q1aeEV/UdULxLwSulwsg3.zNE76bi7JKwj0FtqTZPRK1ZtWqSCpr6',1,'2026-09-05 23:29:31','2026-07-25 22:48:29'),(5,3,NULL,'fabian.cardona@correo.test','','$2y$12$BdvxYT0Z6QVNtJTrwqC0HuMJe0sVa9K8P90xPDsSG3kNLYvuLltJ.',1,'2026-09-04 00:50:34','2026-08-08 13:42:30'),(6,3,NULL,'camilo.rodriguez@correo.test','','$2y$12$85oN.xalkjmE.2h7N20WVOnd/Ey2Bi18JXUyX7DypamXbYX0tteDS',1,NULL,'2026-08-12 14:12:12'),(7,3,NULL,'sofia.romero@correo.test','','$2y$12$Ysi5XSf78uC26/VqjbR2uuFnVDcieSuRVTWZ7Rnsgb.0c7A3VuqHW',1,'2026-09-04 10:34:35','2026-08-14 14:44:37'),(8,3,NULL,'maria.gomez@correo.test','','$2y$12$FjY.wRAqOQ20nsMMCcUYHecM0/8jUH2LdILhBcAVVTohIKgkm/b06',1,'2026-08-28 10:51:53','2026-08-14 15:15:52'),(24,1,NULL,'nuevousu@correo.com','nuevo usuario','$2y$12$Yg9Ceu5xy3WoNhsCoiq27uv3o9w5gX9SyOFyUi2oWTzicULAprBZC',1,NULL,'2026-08-23 16:10:30'),(26,2,NULL,'arangolaura@correo.test','LAURA VALENTINA ARANGO ORTIZ','$2y$12$jGYE.vhccrc.ki5N6CdC6.PUnHJHSsI1xZvYpXk/S9GqJrRQInR7i',1,NULL,'2026-08-23 16:57:50'),(28,3,NULL,'jorge.alvarez@correo.test','','$2y$12$i1e2k.nODMtP1.nvvahFbuXk90pUQHkj8RCSYCBMACS8t0.wMuxI6',1,NULL,'2026-08-28 14:50:30'),(39,4,'461648897','giraldoyesica@correo.test','','$2y$12$cAO1qVbQj7i81ce5ilplCOqqOAAVncghbW4AKPQfJhmybJgu04.1y',1,'2026-09-05 23:28:46','2026-09-06 03:08:25');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'emdb_academica'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-06 14:58:17
