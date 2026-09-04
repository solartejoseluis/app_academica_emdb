# PROJECT_CONTEXT.md — app_academica_emdb
> Archivo de contexto para Claude IA. Pegar al inicio de cada nuevo chat.
> Última actualización: 2026-09-03
> Versión: 93 — agrega el backend Admin del catálogo de requisitos por programa (commit `c4b81f2`), sobre el esquema creado en la v92: 5 `case` nuevos en `est_mdl.php` (`listar_requisitos_programa`, `crear_requisito_programa`, `editar_requisito_programa`, `eliminar_requisito_programa`, `reactivar_requisito_programa`), todos guardados con `role_id === 1` estricto — no `in_array([1,2])` como el resto de `02_estudiantes` — porque la configuración del catálogo es exclusiva de Admin; el Coordinador solo gestionará el checklist por matrícula en una etapa posterior. `crear_requisito_programa` y `reactivar_requisito_programa` corren dentro de una transacción PDO (`INSERT`/`UPDATE` + backfill + `commit()`, con `rollBack()` en el `catch`); `eliminar_requisito_programa` es borrado lógico (`reqp_activo = 0`), no `DELETE` físico, porque `requisitos_estudiante` tiene una FK `ON DELETE RESTRICT` hacia `requisitos_programa` que un `DELETE` real rompería en cuanto existiera una sola fila de historial. Nueva función compartida `backfillRequisitoAMatriculasActivas(PDO $pdo, int $prog_id, int $reqp_id): void` en `helpers.php` — inserta una fila `'pendiente'` en `requisitos_estudiante` por cada matrícula `matriculado` del programa que aún no tenga ese requisito, vía `INSERT ... SELECT ... WHERE NOT EXISTS (...)`, idempotente por diseño (ejecutarla dos veces nunca duplica filas). Verificado con `curl` contra Docker vivo, autenticando con cookie-jar (mismo patrón de la Fase 2.11.C): `crear_requisito_programa` sobre `prog_id=1` (ASO, 9 matrículas activas) produjo exactamente 9 filas `'pendiente'`, confirmado con SQL directo; un ciclo desactivar→reactivar sobre el mismo `reqp_id` conservó las mismas 9 filas sin duplicar nada; una sesión de Coordinador fue rechazada con "Sin autorización" en los 5 endpoints. Datos de prueba eliminados al terminar. Deuda técnica menor documentada: `editar_requisito_programa`/`eliminar_requisito_programa` no verifican la existencia previa del `reqp_id` antes del `UPDATE`. Etapa 2.12.B (de 10) del roadmap "Gestión de requisitos de estudiantes"; `est_view.php`/`est_ctrl.js` sin tocar todavía (Etapa 2.12.E).

---

## Qué es este proyecto

Proyecto de grado — Ingeniería de Sistemas, UNAD CEAD Palmira, curso 202016907.
Estudiante: Jose Luis Solarte Orozco — CC 76.322.816 — Tuluá, Valle del Cauca.
Tutor: Daniel Andrés Guzmán Arevalo. Director: Rubén Darío Ordóñez.
Modalidad: Individual. Meta: graduarse en 2026.

**Título:**
*"Prototipo de Software para la Automatización de Procesos de Inscripción, Matrícula y
Registro de Calificaciones mediante Metodología CDIO en el Contexto del ODS 4"*

**Institución beneficiaria:**
Escuela de Mecánica Dental Bolaños (EMDB), Tuluá, Valle del Cauca.
~80 estudiantes activos, 8 docentes, 1 coordinadora académica. N=89.

**Problema que resuelve:**
80 planillas individuales de Google Sheets por semestre. 10–14 horas de configuración
manual al inicio de cada período. Docentes tardan hasta 5+ días en registrar notas.
Estudiantes dependen de WhatsApp para conocer calificaciones. Sin trazabilidad digital.

---

## Stack tecnológico

- PHP 8.5 + MySQL 8.0 + JavaScript ES6 + Bootstrap 5.3 + jQuery 3.7 + DataTables 1.13
- Arquitectura MVC — 3 archivos por módulo: `_view.php` / `_mdl.php` / `_ctrl.js`
- Servidor local: Docker (Fedora 44) — `docker-compose.yml` con 3 servicios: `app` (PHP 8.5 + Apache, build `docker/Dockerfile`, puerto 8120→8080), `db` (`mysql:8.0`, `network_mode: service:app`, puerto 3310→3306, importa `database/emdb_academica.sql` automáticamente), `phpmyadmin` (puerto 8121→80). BD: `emdb_academica`
- Zona horaria del entorno Docker: America/Bogota (UTC-5, offset fijo, sin horario de verano) desde el commit 7216637 — TZ en `app`/`db`, `--default-time-zone=-05:00` en MySQL, `date.timezone` en PHP, `SET time_zone` en `pdo.php`
- PHP 8.5 en desarrollo (no 8.0) por paridad con producción — el hosting cPanel de `escuelamdb.com` ya corre PHP 8.5
- Generación PDF: dompdf (Composer). Control versiones: Git + GitHub
- Procesamiento de imágenes: extensión GD de PHP (agregada al `docker/Dockerfile`) — redimensionado y miniaturas de foto de estudiante
- Repositorio GitHub: `https://github.com/solartejoseluis/app_academica_emdb` (público)
- Acceso local: `http://localhost:8120/app_academica_emdb`
- Alias bash (Fedora): `academica` → cd al proyecto; `c` → clear

**Entorno anterior (histórico):** desarrollo local originalmente en Windows con XAMPP (Apache + PHP 8.0 + MySQL 8.0), ruta `C:/xampp/htdocs/app_academica_emdb`, alias WSL `academica` → `cd /mnt/c/xampp/htdocs/app_academica_emdb`, acceso `http://localhost/app_academica_emdb`. Migrado a Fedora 44/Docker el 2026-07-25.

---

## Documentación base del proyecto

| Archivo | Propósito | Estado |
|---|---|---|
| `CLAUDE.md` | Convenciones de código para Claude Code | ✅ En repo |
| `README.md` | Descripción del sistema para humanos | ✅ En repo |
| `CHANGELOG.md` | Historial de cambios — orden descendente | ✅ En repo |
| `PROJECT_CONTEXT.md` | Este archivo — contexto para Claude IA | ✅ En repo |
| `database/emdb_academica.sql` | DDL completo 14 tablas + seeds | ✅ En repo |

---

## Documento maestro académico (UNAD)

| Versión | Contenido | Estado |
|---|---|---|
| v01 (Fase 3) | Documento base entregado | ✅ Calificado 75/100 |
| v02 (Fase 4) | Delimitación §5, OE simplificados, Resultados §12, 21 refs | ✅ |
| v03 (Fase 4) | + Análisis diagnóstico §7.1.4 + 4 diagramas UML en §11.1 | ✅ |
| v04 (Fase 4) | + §8.2.3 Scrum, numeración corregida, wireframes ok, Anexo 2 eliminado | ✅ Listo para entrega |

**Archivo actual:** `Actividad_4_consolidado_v04.docx`
**Entrega Fase 4:** 12 de mayo de 2026
**Estado documento:** Completo — listo para entregar

---

## Diagramas UML en §11.1 del documento

| Diagrama | Estado |
|---|---|
| Casos de uso (4 actores, 18 UC) | ✅ En documento v04 |
| Clases (12 entidades, relaciones) | ✅ En documento v04 |
| Secuencia (registro calificaciones) | ✅ En documento v04 |
| Arquitectura MVC + despliegue | ✅ En documento v04 |

---

## Reglas de negocio críticas — calificaciones EMDB

- Estructura: N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = 100%
- Supletorios: solo N1, N2 y N4. **N3 nunca tiene supletorio — nunca.**
- Supletorio se activa únicamente si nota original = 0.0
- Nota Final: siempre se calcula con la fórmula estándar, sin importar si aprueba o no
- Habilitación: entrada manual (0.0-5.0), se activa únicamente si Nota Final < 3.0, un solo intento (sin ciclo de re-habilitación)
- Definitiva: valor oficial — copia de Nota Final si aprueba, o de Habilitación si esta fue registrada; NULL si reprueba sin habilitación
- Formularios: inscripción `AC-FO-02`, matrícula `AC-FO-09`, notas `GA-FO-04`
- Escala: 0.0 a 5.0 con un decimal — `DECIMAL(3,1)` en BD
- Programas: ASO (17 módulos) y MD (19 módulos)

---

## Exportación PDF — reglas de formato (ítem 2.4)

- Formato GA-FO-04 en PDF es un documento de **solo lectura** — sin instrucciones de diligenciamiento (no es una planilla física para llenar a mano)
- Sin columna de "Total Faltas de Asistencia" — no se registra en el sistema actualmente
- Boletín individual del estudiante solo accesible para su propio `usua_id` — mismo filtro de seguridad que la acción `mis_notas`, con respuesta 403 genérica si el `grmo_id` no existe o pertenece a otro estudiante (no se distingue un caso del otro)

---

## Convenciones de BD (reglas propias del estudiante)

- Tablas: minúsculas, plural, sin prefijo (`estudiantes`, `calificaciones`)
- Campos: prefijo 4 letras del nombre de tabla (`estu_id`, `doce_id`, `cali_id`)
- FK: mismo nombre que la PK referenciada
- Fechas: `fecha` + tipo sin guión bajo (`fechainicio`, `fechanacimiento`)
- Sin ñ en nombres: `anio` no `año`
- Booleanos: `TINYINT(1)` con 0/1
- Calificaciones: `DECIMAL(3,1)`
- Timestamps: `TIMESTAMP DEFAULT current_timestamp()`

---

## Roles del sistema

| role_id | Nombre | Destino post-login |
|---|---|---|
| 1 | Administrador | `08_admin/admin_view.php` |
| 2 | Coordinador | `07_coordinador/coordinador_view.php` |
| 3 | Docente | `05_calificaciones/calificaciones_view.php` |
| 4 | Estudiante | `06_reportes/reportes_view.php` |

**Login:** sin selector de rol en pantalla. El sistema detecta el rol por la BD.
**Sesión:** role_id se guarda como `(int)` — todos los módulos usan `!==` para comparar.

---

## Estructura de módulos

```
app_academica_emdb/
  app/
    00_connect/        — pdo.php (singleton PDO local)
    00_selects/        — SELECTs reutilizables para dropdowns
    00_img/            — logo, iconos
    01_login/          — login_view.php / login_mdl.php / check_session.php / logout.php
    02_estudiantes/    — CRUD estudiantes + matrícula (AC-FO-02, AC-FO-09)
    03_docentes/       — CRUD docentes
    04_grupos/         — Cohortes, grupos semestre, grupos módulo
    05_calificaciones/ — Registro notas GA-FO-04 (módulo crítico)
    06_reportes/       — Consulta estudiante + PDF/Excel
    07_coordinador/    — Dashboard seguimiento
    08_admin/          — Gestión usuarios ✅ IMPLEMENTADO
  database/
    emdb_academica.sql — DDL completo
  CLAUDE.md / README.md / CHANGELOG.md / PROJECT_CONTEXT.md / index.php
```

**Filtros disponibles en 02_estudiantes (pestaña Matriculados, solo roles 1/2):** fila de 4 selects — Programa, Período, Grupo, Módulo — sobre `tbl_matriculados`, aplicados vía `WHERE`/`EXISTS` dinámico en `listar_matriculados` (commit `b0e6660`, 2026-08-20). Cualquier combinación se envía junta en cada `ajax.reload()` del DataTable; no hay cascada entre los 4 selects (cada uno se puebla de forma independiente, igual que los filtros de `05_calificaciones`).

---

## Tablas de la BD (17 tablas — todas creadas)

```
1.  roles              — role_id, role_nombre
2.  programas          — prog_id, prog_nombre, prog_sigla, prog_resolucion, prog_fechaaprobacion, prog_fechavencimiento, prog_duracion_semestres, prog_descripcion, prog_activo
3.  periodos           — peri_id, peri_codigo, peri_anio, peri_semestre
4.  usuarios           — usua_id, role_id(FK), usua_email, usua_passwordhash, usua_activo
5.  cohortes           — coho_id, prog_id(FK), coho_codigo, fechainicio
6.  docentes           — doce_id, usua_id(FK), doce_nombres, doce_apellidos, doce_sigla
7.  estudiantes        — estu_id, usua_id(FK), coho_id(FK), estu_nombres, estu_apellidos, estu_foto, estu_expedidoen, estu_ciudadnac, estu_ocupacion, estu_estadocivil, estu_discapacidad, estu_multiculturalidad
8.  modulos            — modu_id, prog_id(FK), modu_nombre, modu_sigla, modu_orden
9.  matriculas         — matr_id, estu_id(FK), prog_id(FK), peri_id(FK), coho_id(FK), matr_estado, matr_estado_academico, matr_semestre
10. gruposemestres     — grse_id, prog_id(FK), peri_id(FK), grse_codigo, grse_semestre
11. grmoestudiantes    — grmo_id(FK), estu_id(FK)  [tabla puente N:M]
12. gruposmodulos      — grmo_id, grse_id(FK), modu_id(FK), doce_id(FK)
13. calificaciones     — cali_id, grmo_id(FK), estu_id(FK), N1-N4, sup_N1/N2/N4, cali_nota_final, cali_habilitacion, cali_definitiva
14. horariosgrupo      — hora_id, grse_id(FK), hora_diasemana, hora_horainicio
15. fichas_inscripcion — finc_id, estu_id(FK), datos familiares (padre/madre/acudiente), estudios anteriores, código temporal
16. requisitos_programa   — reqp_id, prog_id(FK), reqp_nombre, reqp_descripcion, reqp_activo
17. requisitos_estudiante — reqe_id, matr_id(FK), reqp_id(FK), reqe_estado, reqe_fecha  [UNIQUE(matr_id, reqp_id)]
```

Seeds cargados: 4 roles, 2 programas, 3 períodos, 36 módulos (17 ASO + 19 MD), 1 usuario admin.
Stored procedure `sp_calcular_definitiva` y triggers AFTER INSERT/UPDATE eliminados del DDL (commit `304127b`, 2026-07-19) — MySQL no permite `UPDATE` de una tabla desde su propio trigger. Cálculo de `cali_nota_final`/`cali_definitiva` vive en PHP, `app/05_calificaciones/calificaciones_mdl.php` (case `guardar_nota`).

---

## Estado diagnóstico (OE1) — encuestas aplicadas 29-abr-2026

### Coordinadora (n=1) — ✅ Aplicada y analizada en §8.1.4.1

| Indicador | Estimado | Confirmado |
|---|---|---|
| Horas config. planillas/semestre | 12h | 10-14h ✅ |
| Horas verificación notas/semana | 6h | 2-4h |
| Horas generación reporte | 4-6h | <2h |
| Frecuencia errores (1-5) | Alta | 3/5 ocasional |
| Necesidad sistema centralizado | Alta | 4/5 |
| Participación validación TRL5 | — | ✅ Confirmada verbalmente |

### Docentes (n=5) — ✅ Aplicada y analizada en §8.1.4.2

| Indicador | Resultado |
|---|---|
| Facilidad Google Sheets | 5.0/5 |
| Satisfacción sistema actual | 5.0/5 |
| Disposición capacitación | 4.0/5 |
| Importancia tiempo real | 4.4/5 |
| Usarían nuevo sistema | 4 de 5 (80%) |
| Tardan >3 días en registrar | 3 de 5 (60%) |

### Estudiantes (n=25) — ⬜ Pendiente presencial EMDB

---

## Ruta completa — estado actual

### OE1 — DIAGNOSTICAR (Pre-Scrum)

| # | Actividad | Estado | Pendiente |
|---|---|---|---|
| 1 | Revisión documental 2 semestres | ✅ En doc | — |
| 2 | Entrevistas coordinador/operador | ⚠️ | Evidencia física (grabación/consentimiento) |
| 3 | Cuestionario coordinadora | ✅ | — |
| 4 | Cuestionario docentes n=5 | ✅ | — |
| 5 | Cuestionario estudiantes n=25 | ⬜ | Visita presencial EMDB |
| 6 | Observación participante 2×4h | ⚠️ | Diario de campo escrito |
| 7 | Tabulación y análisis | 🔄 | Completar con datos estudiantes |
| 8 | Diagnóstico consolidado | 🔄 | §8.1.4.4 completo tras estudiantes |

### OE2 — DISEÑAR

| # | Actividad | Estado | Pendiente |
|---|---|---|---|
| 1 | Modelado UML (4 diagramas) | ✅ En doc v04 | — |
| 2 | Arquitectura MVC + despliegue | ✅ En doc v04 | — |
| 3 | BD MySQL normalizada + DDL | ✅ | Script en repo |
| 4 | Wireframes baja fidelidad | ✅ En doc v04 | Acta validación coordinadora |
| 5 | Mockups media fidelidad | ⬜ | Capturas sistema real |
| 6 | Matriz de trazabilidad req→comp | ⬜ | Generar tabla |

### OE3 — IMPLEMENTAR (Sprint 0 + Sprint 1 + Sprint 2)

**Sprint 0 — Infraestructura base ✅ COMPLETADO**

| Item | Descripción | Estado |
|---|---|---|
| 0.1 | Repositorio GitHub + estructura + 4 archivos doc | ✅ 2026-05-01 |
| 0.2 | Script DDL + BD `emdb_academica` creada | ✅ 2026-05-01 |
| 0.3 | Seeds: roles, programas, módulos ASO y MD | ✅ 2026-05-01 |
| 0.4 | Módulo `01_login` con bcrypt + check_session | ✅ 2026-05-01 |
| 0.5 | CRUD `08_admin` — gestión usuarios | ✅ 2026-05-01 |

**Sprint 1 — Gestión de actores**

| Item | Descripción | Estado |
|---|---|---|
| 1.1 | Módulo `03_docentes` — CRUD | ✅ 2026-05-01 |
| 1.2 | Módulo `02_estudiantes` — CRUD + matrícula | ✅ 2026-05-01 |
| 1.3 | Módulo `04_grupos` — cohortes, grupos | ✅ 2026-05-05 |

**Sprint 2 — Núcleo académico**

| Item | Descripción | Estado |
|---|---|---|
| 2.1 | Módulo `05_calificaciones` — registro notas GA-FO-04 | ✅ 2026-05-05 |
| 2.2 | Módulo `06_reportes` — consulta notas estudiante + exportación Excel coordinador | ✅ 2026-05-07 |
| 2.3 | Módulo `07_coordinador` — dashboard | ✅ 2026-05-07 |
| 2.4 | Exportación PDF — reporte de grupo GA-FO-04 (coordinador) + boletín individual (estudiante) | ✅ 2026-07-05 |
| 2.5 | Rediseño `05_calificaciones` — Nota Final siempre calculada, Habilitación y Definitiva como valor oficial recalculado | ✅ 2026-07-04 |
| 2.6 | Ficha Familiar AC-FO-02 completa — captura de datos familiares, foto de estudiante, indicador de completitud, PDF con foto en tamaño Oficio | ✅ 2026-07-26 |

### Formulario público de inscripción (plan de 5 fases)

> Planeado en sesión 2026-07-27. Objetivo: permitir que un aspirante
> sin cuenta diligencie su propia inscripción desde la web, quedando
> pendiente de revisión y aprobación del coordinador.

| Fase | Descripción | Estado |
|---|---|---|
| 0 | Credenciales Google reCAPTCHA v2 ("No soy un robot") para escuelamdb.com + localhost | ✅ 2026-07-25 |
| 1 | Pantalla interna de datos familiares (AC-FO-02) — coordinador diligencia fichas_inscripcion | ✅ 2026-07-26 (Ficha Familiar) |
| 2 | Migración de esquema — estudiantes.estu_origen ENUM('manual','web') DEFAULT 'manual' | ✅ 2026-07-27 (commit 7702fe2) |
| 3 | Formulario público — módulo `09_inscripcion_publica/`, sin sesión, verificación reCAPTCHA en servidor, detección de duplicados por documento, genera `finc_codigotemporal` | ✅ 2026-08-01 — Paso 1 (b5626c2, b34143d) y Paso 2 (c60cb1b) completos |
| 4 | Integración — listado de Aspirantes muestra origen (Manual/Web) para que el coordinador sepa qué revisar | ✅ 2026-08-07 (commit f704901) |
| 5 | Pruebas extremo a extremo + documentación | ✅ 2026-08-07 (commit 1531593) — pruebas detectaron hallazgo crítico (indicador binario de ficha completa daba falso positivo para aspirantes web que abandonaban tras el Paso 1); corregido con Ficha Familiar obligatoria + indicador de 3 estados |

Diseño acordado para la Fase 3: reutilizar el campo
`finc_codigotemporal` (ya existente en `fichas_inscripcion`, sin usar
hasta ahora) para permitir que el aspirante retome el formulario sin
necesidad de una cuenta completa.

**⚠️ Superado por la unificación del commit `0e098bb` (2026-08-24) — ver
sección siguiente.** El diseño de 2 pasos descrito en esta tabla
(Fase 3: `finc_codigotemporal`, `fam_view.php`/`fam_mdl.php`/`fam_ctrl.js`)
ya no existe en el código actual — se reemplazó por un solo formulario
de una sola pantalla con un solo guardado transaccional. Esta tabla se
conserva como registro histórico de lo que se implementó y por qué
(incluyendo el hallazgo crítico de la Fase 5 que motivó el indicador de
3 estados, que sigue vigente sin cambios); no reinterpretar como el
diseño vigente.

### Unificación del formulario público de inscripción en un solo paso

**Commit `0e098bb` (2026-08-24).** Reemplaza por completo el diseño de
2 pasos de la tabla anterior (Fases 0–5, 2026-07-25 a 2026-08-07) por
un solo formulario de una sola pantalla:

- `insc_view.php` absorbe el HTML completo de `fam_view.php` (ahora
  eliminado) en 5 secciones numeradas con la clase `.seccion-titulo`
  (mismo diseño visual del modal interno "Ficha de Inscripción" de
  `02_estudiantes`, commit `f088466`, aplicado aquí por primera vez
  fuera de ese módulo): 1. Datos del Aspirante, 2. Multiculturalidad,
  3. Información sobre Familiares, 4. Estudios anteriores, 5. Programa
  técnico al que ingresa. Eliminada toda la UI de búsqueda por código
  (`#bloque_codigo`, `#npt_codigo_busqueda`, `#btn_buscar_codigo`,
  `#error_codigo`); reCAPTCHA reubicado antes del único botón de envío;
  `#bloque_confirmacion` reescrito sin ninguna referencia a código.
- `insc_ctrl.js` absorbe toda la lógica de `fam_ctrl.js` (ahora
  eliminado): cascada Padre/Madre/Acudiente, carga del catálogo de
  Programas (movida a `insc_mdl.php?accion=listar_programas`), nueva
  función `marcarCampoLleno()` con listener delegado (mismo patrón de
  `f088466`). Un solo handler de envío hace un solo POST a
  `insc_mdl.php?accion=crear_aspirante`; el callback ya no lee
  `response.codigo`.
- `insc_mdl.php` fusiona la lógica de `fam_mdl.php` (ahora eliminado)
  dentro del case `crear_aspirante`: todas las validaciones de ambos
  formularios originales corren antes de una sola transacción PDO —
  `INSERT INTO estudiantes` + `INSERT` completo (35 campos) en
  `fichas_inscripcion`, sin `UPDATE` posterior. Mismo principio ya
  documentado para `guardar_completo` en `02_estudiantes` ("dos
  transacciones individualmente correctas no garantizan atomicidad
  entre sí") aplicado aquí por segunda vez en el proyecto.
  `generarCodigoTemporal()` y su lógica de reintento eliminados.
- Columna `finc_codigotemporal` y su `UNIQUE KEY` eliminadas de
  `database/emdb_academica.sql`. **`ALTER TABLE fichas_inscripcion
  DROP COLUMN finc_codigotemporal;` ya se ejecutó manualmente contra el
  contenedor Docker el 2026-08-24** — confirmado con `DESCRIBE
  fichas_inscripcion`. No queda pendiente ninguna migración de esquema
  para este cambio.
- **Confirmado sin cambios:** el formulario público sigue sin generar
  clave de acceso al sistema — esa tarea nunca vivió en el formulario
  público (verificado por grep de `generarClaveAuto`/`clave_generada`/
  `usua_passwordhash` en `09_inscripcion_publica/`, sin resultados); es
  y sigue siendo exclusiva del coordinador vía `matricular` en
  `est_mdl.php`. `calcularEstadoFicha()` no se tocó — el indicador de 3
  estados sigue siendo válido sin cambios de código (`incompleta` ya no
  tiene ruta de origen desde el flujo público, pero sigue siendo
  alcanzable por edición manual posterior del coordinador).

Verificado en navegador: 15/15 puntos de prueba.

### Unificación de ficha de estudiante (plan de 6 fases A–F)

> Planeado en sesión 2026-08-22. Objetivo: fusionar los botones
> "Editar" y "Ficha" del estudiante en una sola experiencia y agregar
> los datos institucionales de matrícula (folio, fecha, número) rumbo
> a la exportación PDF de la Hoja de Matrícula (formato AC-FO-09).

| Fase | Descripción | Estado |
|---|---|---|
| A | Botón "📝 Datos Estudiante" — unifica "Editar"/"Ficha", adopta el indicador de completitud (`estado_ficha`) en `tablaMatriculados` y `tablaAspirantes` de `02_estudiantes` | ✅ 2026-08-23 (commit `38e1809`) |
| B | Columna Edad en Matriculados — formato "17 (23 sep)", resaltado celeste `#cfe2ff` para menores de 18 | ✅ 2026-08-23 (commit `7cef01a`) |
| C1 | Fusión de modales "Editar Estudiante" + "Ficha Familiar" — backend: case `guardar_completo` (1 transacción PDO para estudiante + ficha, Ficha Familiar obligatoria siempre) + case `obtener_completo` (LEFT JOIN); cases antiguos sin llamadores, pendientes de limpieza | ✅ 2026-08-23 (commit `42e4175`) |
| C2 | Fusión de modales "Editar Estudiante" + "Ficha Familiar" — frontend: modal único `modal-xl`, botón "Guardar" único, foto arriba, ficha debajo, botón PDF trasladado al final del modal | ✅ 2026-08-23 (commit `76f90c5`) |
| D | Configuración institucional en `08_admin` — número de matrícula inicial, Director, Secretario | ✅ 2026-08-23 (commit `25530cc`) |
| E | Columnas nuevas en `matriculas` — folio, fecha, observaciones, número | ✅ 2026-08-23 (commit `afdcfc1`) |
| F1 | `usua_nombre` en `usuarios` — prerequisito para "Matriculado por" en el PDF | ✅ 2026-08-23 (commit `c34b780`) |
| F2 | Exportación PDF Hoja de Matrícula, formato AC-FO-09 | ✅ 2026-08-23 (commit `ffb6a6c`) |

**Plan completo (A–F) cerrado el 2026-08-23.** Las 6 fases (A, B, C1+C2, D, E, F1+F2) quedaron implementadas y verificadas en navegador — ver CHANGELOG.md para el detalle de cada commit.

**Deuda técnica de C1/C2 resuelta (commit `5de9a9e`, 2026-08-23):** los 4 cases sin llamadores (`guardar`, `obtener`, `obtener_ficha`, `guardar_ficha`) y el código JS asociado (`abrirFicha()`, handler `btn_guardar_ficha`) — documentados como pendientes de limpieza desde `42e4175`/`76f90c5` — fueron eliminados.

**Reestructuración posterior del modal (commit `f088466`, 2026-08-24):** fuera del alcance original de las Fases A–F, pero aplicada directamente sobre el mismo modal unificado `#mdl_estudiante`. Título del modal en modo creación renombrado de "Nuevo Aspirante" a "Ficha de Inscripción" (modo edición conserva "Editar Estudiante" sin cambios); nuevo bloque de encabezado "FICHA DE INSCRIPCIÓN — código AC-FO-02" con instrucciones generales y advertencia explícita de pérdida de datos al cerrar sin guardar, en reemplazo del bloque de instrucciones antiguo de "Ficha Familiar (AC-FO-02)"; las 5 secciones del modal quedaron numeradas con nueva clase visual `.seccion-titulo` (fondo azul, `00_files/estilos.css`) — sección 3 renombrada de "Ficha Familiar (AC-FO-02)" a "Información sobre Familiares", sección 5 renombrada de "Información general" a "Programa técnico al que ingresa" y reubicada al final del modal (antes intercalada justo antes de Padre/Madre/Acudiente); nueva clase `.campo-lleno` (fondo verde claro) aplicada dinámicamente vía nueva función `marcarCampoLleno()`, enganchada con listener delegado sobre todos los inputs/selects/textareas de `#mdl_estudiante` — capa visual independiente de `marcarValidacion()`/`is-invalid`/`is-valid`, sin modificarla.

Se evaluó agregar `#bloque_foto_estudiante` también al modo creación (para no depender de una segunda visita al modal) y se descartó: el endpoint `subir_foto` requiere un `estu_id` ya existente en BD, que no existe hasta que el aspirante se guarda por primera vez — agregarlo al modo creación habría requerido rediseñar el flujo de guardado (crear registro parcial antes de completar el formulario), fuera del alcance de este cambio. La foto permanece exclusiva del modo edición, sin cambios de comportamiento.

Sin cambios en lógica de guardado, payload AJAX, `CAMPOS_ESTUDIANTE` ni `CAMPOS_FICHA_SIEMPRE`. Verificado en navegador: 8/8 puntos de prueba.

**Edición manual de `matr_numero` en "Editar Matrícula" (commit `59775e4`, 2026-08-24):** extiende el comportamiento de `matr_numero` introducido en la Fase F2 (asignación automática al generar la Hoja de Matrícula). Antes de este commit, `matr_numero` solo podía tomar valor mediante esa asignación automática — el modal "Editar Matrícula de..." lo mostraba como texto informativo de solo lectura (`#spn_editar_matr_numero`), sin forma de corregirlo manualmente si un coordinador necesitaba ajustarlo. Ahora el modal tiene un campo editable real (`#npt_editar_matr_numero`, `type="number"`, `min="1"`), junto a Folio y Fecha de matrícula. El case `editar_matricula` de `est_mdl.php` valida unicidad contra otras matrículas antes del `UPDATE` (mismo patrón ya usado para `doce_sigla`), devolviendo un mensaje específico ("El número de matrícula ya está asignado a otro estudiante.") en vez de depender del catch genérico de `PDOException`. `pdf_hoja_matricula.php` también mejora su manejo de errores: el `catch` de la asignación automática ahora distingue una colisión real de unicidad (`PDOException` con `SQLSTATE 23000`) del resto de errores genéricos.

**Conclusión de diagnóstico — por qué no hay riesgo de desincronización (no reabrir esta pregunta):** el mecanismo de asignación automática de `pdf_hoja_matricula.php` no usa un contador cacheado en `configuracion` — `matr_numero_inicial` es únicamente un piso mínimo ("no bajar de este número"), no un valor que se incremente y persista entre asignaciones. Cada vez que se necesita un número nuevo, el código relee `MAX(matr_numero) FROM matriculas` en vivo y calcula `GREATEST(MAX+1, matr_numero_inicial)`. Como no existe ningún contador separado que deba mantenerse sincronizado con los valores reales de la tabla, permitir la edición manual de `matr_numero` en "Editar Matrícula" no puede desalinear la asignación automática — cualquier valor editado manualmente ya queda reflejado en el siguiente `MAX()` que se calcule. El único mecanismo de protección necesario es la unicidad (ya cubierta por el `UNIQUE KEY uq_matr_numero` del esquema + la validación explícita agregada en `editar_matricula` + el manejo de `SQLSTATE 23000` en la asignación automática).

No se tocó el modal "Completar Matrícula" (`matricular`) — el cambio aplica exclusivamente a "Editar Matrícula". Verificado en navegador: 8/8 puntos de prueba.

### OE4 — VALIDAR TRL5 (Sprint Review)

| # | Actividad | Estado |
|---|---|---|
| 1 | Instalación servidor institucional EMDB | ⬜ |
| 2 | Migración datos históricos 2 semestres | ⬜ |
| 3 | Capacitación usuarios 2h | ⬜ |
| 4 | Pre-test tiempos sistema actual | 🔄 Parcial (encuestas) |
| 5 | Operación continua 2 semanas | ⬜ |
| 6 | Pruebas funcionales usuarios reales | ⬜ |
| 7 | Escala SUS (≥68 puntos) | ⬜ |
| 8 | Video ≤10 min con cámara encendida | ⬜ |
| 9 | Actas de validación firmadas | ⬜ |

### OE5 — EVALUAR (Cierre)

| # | Actividad | Estado |
|---|---|---|
| 1 | Prueba t de Student (p < 0.05) | ⬜ |
| 2 | Análisis SUS | ⬜ |
| 3 | Correlación Pearson | ⬜ |
| 4 | Resultados y conclusiones en doc | ⬜ |
| 5 | Link GitHub en doc (Anexo) | ⬜ |
| 6 | Link video en doc (Anexo) | ⬜ |

---

## Estado entregables Fase 4 (deadline: 12 mayo 2026)

| Criterio | Peso | Estado |
|---|---|---|
| Documento maestro completo | 40 pts | ✅ v04 listo |
| Participación crítica en foro | 10 pts | ⚠️ Consulta enviada al tutor |
| Prototipo funcional TRL5 en GitHub + video | 100 pts | 🔄 Sprint 0 completo |
| **Total** | **150 pts** | |

---

## Commits en GitHub

| Hash | Descripción | Fecha |
|---|---|---|
| `fa4b330` | Phase 0.1: estructura base y documentación inicial | 2026-05-01 |
| `c2fdcdc` | Phase 0.3-0.4: estructura MVC + módulo login con autenticación bcrypt | 2026-05-01 |
| `5fc9f4a` | Phase 0.5: módulo admin completo — CRUD usuarios con roles y DataTables | 2026-05-01 |
| `5dfd52b` | Phase 1.1: módulo 03_docentes + 08_admin restringido a roles admin y coordinador | 2026-05-01 |
| `dc792e2` | Phase 1.2: DDL — estudiantes, matriculas, fichas_inscripcion, usua_login en usuarios | 2026-05-01 |
| `724dc68` | Phase 1.2: módulo 02_estudiantes — gestión aspirantes y matriculados con creación automática de usuario | 2026-05-01 |
| `4dc841a` | Phase 1.3: DDL — grupos rediseñado, programa_modulos, grmoestudiantes, coho_jornada, grmo_horario | 2026-05-05 |
| `1a96050` | Phase 1.3: módulo 04_grupos — cohortes, grupos semestre, módulos y asignación de estudiantes | 2026-05-05 |
| `70f5b89` | docs: corregir nombres campos calificaciones en CLAUDE.md + regla cierre tarea | 2026-05-05 |
| `76f2c3e` | Phase 2.1: módulo 05_calificaciones — registro notas GA-FO-04 con autosave, supletorios y definitiva en tiempo real | 2026-05-05 |
| `e8a06a8` | Phase 2.2: módulo 06_reportes — consulta notas estudiante + reporte por grupo con exportación Excel | 2026-05-07 |
| `e7b73e3` | Phase 2.3: módulo 07_coordinador — dashboard seguimiento + semáforo y preselección en 05_calificaciones | 2026-05-07 |
| `f45420b` | navbar compartido roles 1 y 2 en todos los módulos | 2026-05-07 |
| `bc2587a` | fix modal editar grupo — Guardar y Cerrar | 2026-05-07 |
| `3aaf8c1` | mejoras UX: mayúsculas automáticas + validación numérica calificaciones | 2026-05-07 |
| `58396d1` | rediseño Nota Final / Habilitación / Definitiva en 05_calificaciones + fix badges no actualizados tras autosave | 2026-07-04 |
| `5fe5f1f` | creación de .gitignore — excluye vendor/ y .claude/settings.local.json | 2026-07-04 |
| `a575cf0` | instalación dompdf vía Composer (composer.json, composer.lock) + .htaccess de producción versionado | 2026-07-04 |
| `fa6e685` | alineación 06_reportes con Nota Final/Habilitación/Definitiva — fix badgeEstado() mostraba "En curso" a reprobados sin habilitación | 2026-07-05 |
| `906b219` | nuevo endpoint pdf_grupo.php — exportación PDF reporte de grupo GA-FO-04 (coordinador/admin) | 2026-07-05 |
| `b4f60f2` | nuevo endpoint pdf_boletin.php — boletín individual PDF (estudiante), acceso restringido a propio usua_id | 2026-07-05 |
| `7755f7c` | botones de descarga PDF en reportes_view.php/reportes_ctrl.js para ambos roles — cierra ítem 2.4 | 2026-07-05 |
| `75504eb` | fix: valida sesión, rol y ownership en guardar_nota (05_calificaciones) — docente restringido a sus propios grupos, coordinador/admin sin restricción, otros roles rechazados | 2026-07-19 |
| `04ec8b0` | fix: valida sesión, rol y ownership en listar_calificaciones (05_calificaciones) — mismo criterio que guardar_nota, hallazgo detectado al revisar ese commit | 2026-07-19 |
| `304127b` | fix: elimina stored procedure sp_calcular_definitiva y triggers obsoletos del DDL — corrige reinstalación limpia de la BD (cálculo ya vivía en PHP desde 58396d1) | 2026-07-19 |
| `be827c5` | chore: elimina 6 stubs muertos de Fase 0 (estudiantes_*/docentes_* en 02_estudiantes/03_docentes, nunca reemplazados por los archivos reales est_*/doc_*) y normaliza fin de línea de .gitignore (CRLF → LF, sin cambio de contenido) | 2026-07-19 |
| `d6169e6` | feat: migra entorno de desarrollo local a Docker (Fedora 44) — docker-compose.yml y Dockerfile nuevos, pdo.php host localhost→127.0.0.1 | 2026-07-25 |
| `d406166` | fix: corrige hash del usuario admin en seed SQL — reemplaza el hash de 'password' por el real de Admin@2026, elimina el parche manual (UPDATE) post-import | 2026-07-25 |
| `590e64e` | docs: registra auditoría de compatibilidad PHP 8.1-8.5 en deuda técnica — 22 archivos .php sin hallazgos, dompdf probado en runtime sin warnings | 2026-07-25 |
| `115c8ed` | chore: ignora recaptcha_config.php — credenciales sensibles de reCAPTCHA fuera de git | 2026-07-25 |
| `fe397ad` | feat: validación obligatoria y formato de documento en modal Nuevo Aspirante — 13 campos obligatorios, marcarValidacion()/CAMPOS_ESTUDIANTE reutilizable, documento con puntos de miles | 2026-07-26 |
| `e29b7b1` | fix: corrige validación visual y comportamiento del select Acudiente en Ficha Familiar — validación en vivo en 33 campos de mdl_ficha, Parentesco se oculta con Padre/Madre | 2026-07-26 |
| `67683ab` | feat: valida formato de correo electrónico en minúsculas en modal Nuevo Aspirante | 2026-07-26 |
| `1b699f5` | feat: carga y miniatura de foto del estudiante — extensión GD en Dockerfile, estu_foto, primer upload de archivos del proyecto vía FormData | 2026-07-26 |
| `2355ab1` | feat: indicador rojo/verde de ficha completa en tablas de estudiantes — LEFT JOIN fichas_inscripcion, definición simple por existencia de fila | 2026-07-26 |
| `8fe6c99` | feat: agrega 6 campos faltantes del formato AC-FO-02 al modal Nuevo Aspirante — expedido en, ciudad de nacimiento, ocupación, estado civil, discapacidad, multiculturalidad | 2026-07-26 |
| `15021e0` | feat: descarga en PDF de la Ficha Familiar (AC-FO-02) con foto del estudiante — pdf_ficha.php nuevo, fixes de Dompdf (cellmap, table-cell, chroot) | 2026-07-26 |
| `dc18116` | feat: nombre del estudiante en modal Ficha y tamaño Oficio en el PDF — botón de descarga trasladado a columna Acciones | 2026-07-26 |
| `7702fe2` | fase 2 formulario público — agrega estu_origen ENUM('manual','web') a estudiantes | 2026-07-27 |
| `b5626c2` | fase 3-a — agrega UNIQUE a finc_codigotemporal | 2026-08-01 |
| `b34143d` | fase 3-b — Paso 1 formulario público (módulo 09_inscripcion_publica, insc_view/mdl/ctrl) | 2026-08-01 |
| `c60cb1b` | fase 3-c — Paso 2 formulario público (módulo 09_inscripcion_publica, fam_view/mdl/ctrl) | 2026-08-01 |
| `920bcfe` | fix: valida sesión y rol en los 9 case de est_mdl.php (02_estudiantes) — mismo patrón de calificaciones_mdl.php | 2026-08-07 |
| `29b6ca6` | refactor: ajustes de formularios en 02_estudiantes — elimina Folio/Número de Completar Matrícula, reubica "Expedido en" en Nuevo Aspirante | 2026-08-07 |
| `54d4514` | fix: valida sesión y rol en doc_mdl, grupos_mdl, coordinador_mdl y admin_mdl — mismo hallazgo de est_mdl.php | 2026-08-07 |
| `35831fe` | feat: CRUD de períodos académicos como pestaña en 04_grupos — crear/editar, sin eliminar | 2026-08-07 |
| `f704901` | feat: muestra origen (Manual/Web) en listado de Aspirantes — Fase 4 formulario público | 2026-08-07 |
| `8ada1df` | feat: agrega contexto de curso/docente al reporte y export Excel en 06_reportes | 2026-08-07 |
| `1531593` | feat: Ficha Familiar obligatoria + indicador de 3 estados en Aspirantes/Matriculados | 2026-08-07 |
| `7216637` | fix: fija zona horaria America/Bogota (UTC-5) en Docker y conexión PDO | 2026-08-08 |
| `75907a3` | feat: muestra nombre completo del módulo e inicial del docente en select de Reportes | 2026-08-08 |
| `7d72327` | feat: eliminar aspirante desde el modal Editar (02_estudiantes) — DELETE físico, bloqueo de matriculados en servidor | 2026-08-08 |
| `6ef7e85` | feat: CRUD de módulos en 04_grupos — backend (listar/guardar/eliminar/toggle_estado en grupos_mdl.php) | 2026-08-08 |
| `e7e6b3b` | feat: CRUD de módulos en 04_grupos — pestaña, tabla y modales en grupos_view.php (frontend HTML, sin JS) | 2026-08-08 |
| `0eca14e` | feat: CRUD de módulos en 04_grupos — conecta grupos_ctrl.js (listar, crear/editar, eliminar condicionado, desactivar/activar) | 2026-08-08 |
| `0eddadf` | fix: valida sesión en listar_grupos (05_calificaciones) — hallazgo colateral detectado al verificar el fix de est_mdl.php | 2026-08-15 |
| `5bef1ef` | refactor: mueve jornada de cohorte a grupo semestre (4 etapas: schema, migración de datos, backend, frontend, reportes/PDFs) | 2026-08-15 |
| `f46d9d9` | fix: precarga módulo/docente al editar + permite cambiar módulo + eliminar módulo-grupo con confirmación (04_grupos) | 2026-08-15 |
| `5611153` | feat: eliminar/desactivar cohorte con verificación de dependencias (estudiantes + gruposemestres), botón condicional Eliminar/Desactivar/Activar (04_grupos) | 2026-08-15 |
| `7fe1151` | feat: divide la fila de contexto del Excel exportado en 2 filas reales, vía customize del botón excelHtml5 (06_reportes) | 2026-08-15 |
| `7eba870` | feat: período activo (peri_activo, uno solo a la vez) + conteo de grupos por docente en período seleccionado (04_grupos + 03_docentes) | 2026-08-15 |
| `2dd4a55` | feat: eliminar/desactivar docente + corrige desincronización de doce_activo/usua_activo (KPI de 07_coordinador) | 2026-08-15 |
| `b489fa5` | feat: texto de ayuda para siglas (docente/módulo) + corrige maxlength de doce_sigla y mayúsculas de modu_sigla | 2026-08-16 |
| `6c496e4` | feat: filtros de profesor/programa/período en calificaciones, exclusivos Coordinador/Admin (05_calificaciones) | 2026-08-16 |
| `fc9b56b, f801bdb, 8947710` | feat: sidebar de ayuda con CRUD (10_ayudas) — 3 etapas: esquema+backend, UI de administración, offcanvas + integración piloto (04_grupos, 05_calificaciones) | 2026-08-16 |
| `f7f0ae3` | feat: expande el sidebar de ayuda a los 5 módulos restantes (02_estudiantes, 03_docentes, 06_reportes, 07_coordinador, 08_admin) | 2026-08-16 |
| `3d59f06` | fix: refresco de tabla matriculados al editar + feat: cambiar clave de estudiante matriculado (02_estudiantes) | 2026-08-16 |
| `1edb887` | fix: agrega las 5 secciones faltantes al select del modal de ayuda (10_ayudas) | 2026-08-16 |
| `2780dff` | fix(seguridad): agrega guard de autorización por rol en listar_grupos (05_calificaciones) — cerraba brecha que exponía todos los grupos a roles no autorizados | 2026-08-17 |
| `624b44c` | feat(grupos): preselecciona período activo en modal Nuevo Grupo Semestre y sincroniza tras activar_periodo | 2026-08-17 |
| `04345ea` | feat(grupos): autogenera grse_codigo (readonly) desde programa+período+semestre+jornada y amplía modal a modal-xl | 2026-08-17 |
| `034d663` | fix(grupos): corrige scope de periodoCodigoManual (bug que rompía Editar Período) y simplifica peri_codigo a readonly permanente | 2026-08-17 |
| `cfab7bf` | fix(docentes): valida unicidad de doce_sigla con mensaje claro antes de guardar | 2026-08-17 |
| `3efb73f` | fix(grupos): envuelve eliminar_cohorte en transacción PDO para cerrar ventana de condición de carrera | 2026-08-17 |
| `17f42aa` | feat(grupos): muestra conteo de carga de docente en selector de asignación de módulo | 2026-08-17 |
| `d39ad68` | fix(calificaciones): agrega desempate por nombres en orden alfabético — iguala ORDER BY con reportes_mdl.php/pdf_grupo.php | 2026-08-19 |
| `d62dde6` | feat(estudiantes): migra autenticación de estudiantes al correo real — UNIQUE estu_email, validación de formato/unicidad, matricular usa estu_email real, migrados usua_id 4/15/17 | 2026-08-19 |
| `35d6672` | fix(reportes): elimina DISTINCT redundante que rompía el selector de módulos del estudiante (SQLSTATE 3065, ONLY_FULL_GROUP_BY) | 2026-08-19 |
| `a49b652` | feat(reportes): agrega nota explicativa de supletorios al pie de los PDF — párrafo `.nota-supletorios` en pdf_boletin.php y pdf_grupo.php, texto duplicado en ambos explicando activación de supletorio (solo si nota original = 0.0), N3 sin supletorio, y cálculo de Nota Final/Habilitación/Definitiva; sin lógica nueva, solo HTML/CSS estático, texto aprobado por el usuario | 2026-08-19 |
| `00a22fc` | feat(grupos): agrega CRUD de Programas (listar_programas_crud/guardar/eliminar/toggle_estado) + reordena pestañas de 04_grupos (Programas primero) — prog_vigencia renombrada a prog_fechavencimiento, agrega prog_fechaaprobacion/prog_descripcion/prog_activo | 2026-08-19 |
| `cac382a` | feat(estudiantes): columna de correo en Matriculados + cascada Programa→Cohorte→Período en modal Completar Matrícula — nuevo endpoint listar_cohortes_por_programa (est_mdl.php), Cohorte ahora obligatoria y deshabilitada hasta elegir Programa, abrirMatricular() resetea los 3 selects | 2026-08-20 |
| `b0e6660` | feat(estudiantes): filtros Programa/Período/Grupo/Módulo + columnas Cohorte/Módulos en Matriculados — WHERE/EXISTS dinámico en listar_matriculados, 3 cases nuevos (listar_grupos_filtro, listar_modulos_filtro, listar_modulos_estudiante), modal de detalle de módulos sincronizado con el filtro de período activo | 2026-08-20 |
| `7340d6e` | feat(estudiantes): edición de matrícula (modal Editar Matrícula, cases obtener_matricula/editar_matricula, retiro automático de módulos huérfanos) + fix de causa raíz en listar_estudiantes_disponibles (04_grupos) — ahora valida matr_estado='matriculado' con prog_id/peri_id del grupo semestre, no solo coho_id; cargarCohortesPorPrograma() movida a scope global | 2026-08-20 |
| `a83cb43` | feat(grupos): agrega eliminación de Períodos (case eliminar_periodo, DELETE condicionado igual que Módulos/Cohortes/Programas) — sin alternativa "desactivar" (peri_activo = único activo institucional, no archivado por registro), bloquea también eliminar el período activo aunque esté vacío | 2026-08-20 |
| `ef79791` | feat(estudiantes): precarga Programa y Jornada declarados (fichas_inscripcion) en el modal Completar Matrícula — nuevo case obtener_defaults_matricula, select #slct_jornada_declarada informativo, Programa preseleccionado pero editable, error callback no crítico (console.warn) | 2026-08-22 |
| `38e1809` | feat(estudiantes): unifica botón Editar/Ficha en "📝 Datos Estudiante" — renombra "Editar" en tablaMatriculados y tablaAspirantes, adopta el punto de color y title=tituloFicha que ya tenía "Ficha" (reutiliza estado_ficha/colorFicha/tituloFicha, sin tocar calcularEstadoFicha()); Fase A de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `7cef01a` | feat(estudiantes): agrega columna Edad en Matriculados con resaltado para menores de edad — fechanacimiento agregado al SELECT de listar_matriculados, formato "17 (23 sep)" con edad calculada en JS (ajustada por mes/día) y array local de meses en español, resaltado celeste #cfe2ff embebido en render() si edad < 18, "—" si fechanacimiento es NULL; Fase B de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `42e4175` | feat(estudiantes): agrega endpoint transaccional único guardar_completo/obtener_completo — case guardar_completo unifica creación/edición de estudiante + Ficha Familiar en 1 sola transacción PDO (antes 2 transacciones separadas sin atomicidad real), Ficha Familiar ahora obligatoria siempre; case obtener_completo combina estudiante + ficha en 1 JSON vía LEFT JOIN; cases guardar/guardar_ficha/obtener/obtener_ficha quedan como código muerto; Fase C1 de 6 (A–F) del plan de unificación de ficha de estudiante — backend completo, frontend (C2) pendiente | 2026-08-23 |
| `76f90c5` | feat(estudiantes): fusiona modales Editar Estudiante + Ficha Familiar en uno solo — mdl_estudiante ampliado a modal-xl, contenido de mdl_ficha trasladado dentro (eliminado por completo), abrirEditar() unificada vía obtener_completo, guardado unificado en btn_guardar_estudiante hacia guardar_completo, botón "🖨️ Ficha Inscripción" condicional a estado_ficha === 'completa', botones "📋 Ficha"/"🖨️ PDF" eliminados de las filas; cierra la Fase C completa (C1+C2) de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `25530cc` | feat(admin): agrega configuración institucional — tabla configuracion de fila única (config_id fijo en 1, CHECK constraint) con matr_numero_inicial/director_nombre/secretario_nombre; 2 cases nuevos en admin_mdl.php (obtener_configuracion/guardar_configuracion, guard admin-only); bloque nuevo sin tabs en admin_view.php con texto de ayuda; fix incidental de estilos.css faltante; Fase D de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `afdcfc1` | feat(estudiantes): reactiva folio/fecha/observaciones en Completar y Editar Matrícula, prepara matr_numero para Fase F — matr_numero cambia de VARCHAR(20) a INT UNSIGNED con UNIQUE KEY (múltiples NULL confirmados válidos), fechamatricula deja de fijarse con CURDATE() hardcodeado y se vuelve editable, matr_folio/matr_observacion reactivados en ambos modales (removidos deliberadamente en 29b6ca6, backend intacto desde entonces), matr_numero de solo lectura sin lógica de asignación aún; Fase E de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `c34b780` | feat(admin): agrega usua_nombre a usuarios, prerequisito para "Matriculado por" en Fase F2 — columna nueva entre usua_email/usua_passwordhash, CRUD completo en 08_admin (listar/crear/editar/obtener) con validación server-side simétrica entre crear/editar, propagación a $_SESSION['usua_nombre'] en login_mdl.php; descubierto durante el diagnóstico de Fase F que ningún Admin/Coordinador tenía nombre legible en el esquema; Fase F1 de 6 (A–F) del plan de unificación de ficha de estudiante | 2026-08-23 |
| `ffb6a6c` | feat(reportes): agrega PDF Hoja de Matrícula (AC-FO-09) — nuevo pdf_hoja_matricula.php (06_reportes) con dompdf, Carta portrait, excluye Nivel y checklist de Requisitos; asignación segura de matr_numero bajo concurrencia con SELECT ... FOR UPDATE sobre configuracion (primer uso de este patrón en el proyecto), matriculas resuelto como 1:N vía ORDER BY matr_id DESC LIMIT 1, validación server-side de matr_estado === 'matriculado', botón nuevo en el modal unificado de 02_estudiantes visible solo si matriculado; Fase F2 de 6 (A–F) — cierra el plan completo de unificación de ficha de estudiante | 2026-08-23 |
| `5de9a9e` | refactor(estudiantes): elimina código muerto tras el plan de unificación de ficha de estudiante — 4 cases sin llamadores (guardar/obtener/obtener_ficha/guardar_ficha) eliminados de est_mdl.php, abrirFicha() y handler btn_guardar_ficha eliminados de est_ctrl.js, limpiarFormularioFicha() conservada por tener llamador activo real, comentarios obsoletos actualizados, 587 líneas netas eliminadas; cierra la deuda técnica documentada en 42e4175/76f90c5 | 2026-08-23 |
| `f088466` | refactor(estudiantes): reestructura el modal de creación de estudiante en "Ficha de Inscripción" — título renombrado (edición conserva "Editar Estudiante"), nuevo encabezado con instrucciones y advertencia de pérdida de datos al cerrar sin guardar, 5 secciones numeradas con nueva clase .seccion-titulo, sección 3 renombrada a "Información sobre Familiares", sección 5 renombrada a "Programa técnico al que ingresa" y reubicada al final del modal, nueva clase .campo-lleno vía marcarCampoLleno() con listener delegado; foto evaluada y descartada para modo creación por depender de estu_id existente; fuera del alcance original de las Fases A–F, sobre el mismo modal unificado | 2026-08-24 |
| `59775e4` | feat(estudiantes): habilita edición manual de matr_numero en "Editar Matrícula" — campo antes de solo lectura (#spn_editar_matr_numero) se vuelve editable (#npt_editar_matr_numero); validación de unicidad server-side en editar_matricula (mismo patrón que doce_sigla); pdf_hoja_matricula.php diferencia colisión de unicidad (SQLSTATE 23000) de errores genéricos en el catch de la asignación automática; mecanismo de asignación automática sin cambios — relee MAX(matr_numero) en vivo, sin contador cacheado que desincronizar; no se tocó "Completar Matrícula" | 2026-08-24 |
| `0e098bb` | refactor(inscripcion): unifica el formulario público de inscripción en un solo paso — insc_view.php/insc_ctrl.js/insc_mdl.php absorben por completo fam_view.php/fam_mdl.php/fam_ctrl.js (eliminados), un solo formulario de una pantalla con 5 secciones .seccion-titulo/.campo-lleno (mismo diseño que f088466), un solo submit hacia un solo case crear_aspirante con una sola transacción PDO (INSERT estudiantes + INSERT completo fichas_inscripcion, sin UPDATE posterior); finc_codigotemporal y su UNIQUE KEY eliminados del esquema (ALTER TABLE ya ejecutado en Docker); formulario público sigue sin generar clave de acceso (confirmado que nunca lo hizo); calcularEstadoFicha() sin cambios; verificado 15/15 pruebas | 2026-08-24 |
| `b6cc503` | feat(docentes): permite editar usua_email (usuario de acceso) en "Editar Docente" — quita readonly de abrirEditar(), label aclara que es el identificador de login, valida unicidad excluyendo el propio usua_id (mismo patrón que doce_sigla), agrega usua_email a ambos UPDATE usuarios de la rama de edición | 2026-08-28 |
| `7ba02bc` | feat(docentes): cablea doce_cedula completo — columna ampliada VARCHAR(15)→VARCHAR(20), nuevo campo opcional en modal (sin selector de tipo de documento), validación de unicidad condicionada a valor no vacío (mismo criterio que matr_numero), NULL si llega vacío, agregado a case 'obtener'; case 'listar' sin cambios | 2026-08-28 |
| `0e0a508` | fix(grupos): corrige truncamiento SQL de grse_codigo/coho_codigo — columnas ampliadas VARCHAR(20)→VARCHAR(25) (alineado con convención ya documentada en CLAUDE.md), bug determinístico con siglas de programa largas (ASO2016/AMD2016), validación defensiva mb_strlen()>25 agregada antes de INSERT/UPDATE en guardar_grupo y guardar_cohorte | 2026-08-29 |
| `a39cb24` | feat(grupos): agrega columna "# Estud" en Grupos Semestre (04_grupos) — listar_grupos gana subconsulta escalar total_estudiantes (COUNT(DISTINCT ge.estu_id) vía grmoestudiantes→gruposmodulos, grmo_activo=1, evita inflar el conteo cuando un estudiante está en más de un módulo del mismo grupo); nuevo case listar_estudiantes_grupo; nueva columna "# Estud" con modal de detalle #mdl_estudiantes_grupo; nueva función global verEstudiantesGrupo() (mismo patrón que verModulosEstudiante en 02_estudiantes); filtro grmo_activo=1 documentado como inconsistente con el total_modulos existente en el mismo case, no corregido por estar fuera de alcance | 2026-08-30 |
| `b8a583a` | fix(grupos): filtra grmo_activo=1 en total_modulos de listar_grupos (04_grupos) — corrige la inconsistencia documentada en a39cb24, alineando total_modulos con total_estudiantes (mismo case) y con el resto de conteos de gruposmodulos del proyecto (listar_docentes, doc_mdl.php, coordinador_mdl.php, calificaciones_mdl.php, reportes_mdl.php); cambio de una sola línea; verificado sin impacto en datos locales (0 gruposmodulos inactivos), pendiente verificar en producción | 2026-08-30 |
| `3e551e5` | feat(matriculas): migra coho_id de estudiantes a matriculas + agrega matr_estado_academico — prepara el modelo para que un estudiante curse más de un programa a la vez (cada matrícula con su propia cohorte y condición académica); matriculas gana coho_id (FK → cohortes, ON DELETE SET NULL) y matr_estado_academico (ENUM Activo/Aplazamiento/Retirado, independiente de matr_estado); estudiantes.coho_id se conserva como respaldo sin escrituras nuevas; ALTER + backfill aplicados en vivo (16/16 filas verificadas); listar_matriculados/matricular/obtener_matricula/editar_matricula (est_mdl.php), pdf_hoja_matricula.php y el KPI/guard de eliminación de cohortes (grupos_mdl.php) migrados a leer/escribir coho_id desde matriculas; confirmado por grep que el seed SQL nunca sembró datos transaccionales; UI de multi-programa y wiring de matr_estado_academico pendientes | 2026-08-30 |
| `b3a0296` | feat(reportes): backend de reportes por período/programa (06_reportes, Fase 1 de 3) — configuracion.institucion_nombre nueva (mismo patrón que director_nombre/secretario_nombre); 4 cases nuevos en reportes_mdl.php (mis_programas, mis_periodos, detalle_periodo, buscar_estudiante) + helper resolverEstuIdObjetivo() (role 4 siempre su propio estu_id de sesión, roles 1/2 reciben estu_id por POST); validación de pertenencia de matr_id al estudiante objetivo en cada case; probado con curl incluyendo casos borde (estu_id faltante, matr_id ajeno, estudiante sin matrículas, módulo sin definitiva) | 2026-08-30 |
| `ef429bd` | feat(reportes): reescribe pdf_boletin.php a boletín por período (06_reportes, Fase 2 de 3) — reemplaza el boletín de un solo módulo por uno de TODOS los módulos de un período+programa de un estudiante (matr_id+peri_id, mismo criterio de resolución de estudiante objetivo, 403 genérico); encabezado institucional leído de configuracion.institucion_nombre; Estado del Período replicado del cálculo de detalle_periodo; tabla de 11 columnas por módulo (incluye Habilitación, agregada en un ajuste posterior del mismo commit); bug de institucion_nombre doble-codificada en UTF-8 encontrado y corregido en el camino (ALTER/UPDATE sin --default-character-set=utf8mb4); verificado generando 2 PDFs reales (Aprobado y En Curso) | 2026-08-30 |
| `8d479a1` | feat(reportes): combina Reporte por Grupo + Reporte por Estudiante en pestañas (06_reportes, Fase 3 de 3, FINAL) — cierra el rediseño de 06_reportes; a pedido explícito de Jose Luis, el reemplazo total planeado originalmente se corrigió en este mismo commit para que ambas interfaces convivan como 2 pestañas de nivel superior (solo roles 1/2): "Reporte por Grupo" recuperado tal cual de ef429bd, "Reporte por Estudiante" sin cambios respecto al diseño de esta fase; estudiante (role 4) sigue sin pestañas de nivel superior; 0 funciones JS duplicadas verificado por grep; DataTables/Buttons/JSZip gateados por $es_coordinador; verificado con curl (HTTP 200 sin warnings, balance de <div> 38/38) — interacción entre pestañas dejada para prueba en navegador | 2026-08-30 |
| `f5c21e5` | feat(estudiantes): matrícula a segundo programa + fix de total_modulos cruzado entre programas (02_estudiantes) — cierra el ciclo de 3e551e5 (nunca se comiteó el paso intermedio, todo en un solo commit); botón "➕ Matricular en otro programa" en tablaMatriculados + contexto de matrículas previas en #mdl_matricular + sección "Programas matriculados" en #mdl_estudiante + case matriculas_estudiante + obtener_completo con array matriculas; matricular/editar_matricula bloquean duplicar un programa ya matriculado en otro período; fix eliminar_aspirante (fetch() no determinista → fetchAll()+foreach); fix total_modulos/listar_modulos_estudiante (contaban módulos de todos los programas del estudiante, corregido cruzando por prog_id de la matrícula de la fila) — verificado con estu_id=12 (MD+ASO) real | 2026-08-30 |
| `1529832` | feat(estudiantes): indicador visual "N programas" en Matriculados (02_estudiantes) — cierra la última pieza pendiente del ciclo de matrícula a segundo programa, evaluada y descartada explícitamente en f5c21e5; 2 columnas nuevas en listar_matriculados (total_matriculas_estudiante, COUNT correlacionado solo por estu_id sin filtrar por prog_id/peri_id de la fila — a propósito lo opuesto de total_modulos; detalle_matriculas_estudiante, GROUP_CONCAT "SIGLA — Estado"); badge "🎓 N programas" en tablaMatriculados visible solo si hay 2+ matrículas, con el detalle en el title; tablaAspirantes sin cambios por diseño; sin endpoint nuevo, detalle agregado traído en la misma query en vez de AJAX por fila o reutilizar matriculas_estudiante; verificado con curl (estu_id=12, MD+ASO) y en navegador | 2026-08-31 |
| `53d5ec5` | feat(estudiantes): agrega conteo de programas activos matriculados a listar_matriculados (02_estudiantes) — 2 columnas aditivas: programas_activos_totales (COUNT(*) de programas con prog_activo=1) y programas_matriculados_activos (COUNT(DISTINCT prog_id) de matrículas 'matriculado' entre programas con prog_activo=1, misma condición de matr_estado ya usada en matricular/editar_matricula); preparación para deshabilitar "Matricular en otro programa" en el dropdown de Acción (frontend en 4fb2257); sin cambios en JOINs/WHERE ni columnas existentes, sigue una fila por matrícula | 2026-08-31 |
| `4fb2257` | feat(estudiantes): agrupa botones de Acción en dropdown con ícono de tuerca (tablaMatriculados, 02_estudiantes) — reemplaza 3 botones en línea por un único botón bi-gear-fill (CDN Bootstrap Icons 1.11.3 nuevo en est_view.php) que despliega un dropdown con 4 acciones: Datos Estudiante y Matrícula sin cambios; "🖨️ Hoja de Matrícula" NUEVA (antes solo accesible dentro del modal Datos Estudiante); "➕ Matricular en otro programa" deshabilitada con tooltip (`<li tabindex="0" title="...">`) cuando programas_matriculados_activos >= programas_activos_totales (columnas de 53d5ec5); tablaAspirantes sin tocar; columna Acción 360px→70px | 2026-08-31 |
| `9346a76` | feat(estudiantes): agrupa botones de Acción en dropdown con ícono de tuerca (tablaAspirantes, 02_estudiantes) — reemplaza 2 botones en línea (Matricular, 📝 Datos Estudiante) por el mismo botón bi-gear-fill + dropdown ya usado en tablaMatriculados (4fb2257), con 3 acciones: Matricular y Datos Estudiante sin cambios (esAspirante=true, punto de color de estado_ficha recalculado localmente, sin compartirse con tablaMatriculados); "🖨️ Ficha de Inscripción" NUEVA (antes solo accesible dentro del modal Datos Estudiante vía #btn_ficha_inscripcion_pdf, condicionado a estado_ficha==='completa'; el ítem del dropdown queda siempre habilitado, sin esa validación en frontend — Opción A, mismo criterio que Hoja de Matrícula); a diferencia de 53d5ec5+4fb2257, un solo commit de frontend puro, sin fase de backend, porque estado_ficha ya viajaba en listar_aspirantes; tablaMatriculados no se tocó; columna Acción 300px→70px; verificado en navegador 7/7 pruebas, incluida descarga de Ficha de Inscripción con ficha incompleta (comportamiento esperado) | 2026-09-01 |
| `e1c232d` | feat(docentes): agrupa botones de Acciones en dropdown con ícono de tuerca (tablaDocentes, 03_docentes) — reemplaza los 2 botones en línea (Editar + uno de Eliminar/Desactivar/Activar, mutuamente excluyentes) por el mismo botón bi-gear-fill + dropdown ya usado en 02_estudiantes; a diferencia de tablaMatriculados/tablaAspirantes, esta columna nunca acumuló 3+ botones simultáneos — se aplicó por decisión explícita de uniformidad visual, no por saturación; lógica if/else if/else sobre doce_activo/total_grupos_historico preservada sin ningún cambio, solo cambió el HTML (botón en línea → dropdown-item); sin funcionalidad nueva (a diferencia de los 2 casos anteriores, que sumaron un ítem antes solo accesible en otro modal); CDN de Bootstrap Icons agregado a doc_view.php por primera vez; columna Acciones 190px→70px; sin cambios de backend; verificado en navegador 5/5 pruebas, incluidos los 3 casos de la lógica condicional | 2026-09-01 |
| `c985188` | feat(matriculas): agrega matr_semestre con validación y corrige badge de programas (02_estudiantes) — nueva columna matriculas.matr_semestre (1-N, default 1) validada contra programas.prog_duracion_semestres en matricular/editar_matricula; ENUM matr_estado ampliado con 'cursado' (reservado para la futura acción "avanzar de semestre", ningún endpoint lo escribe aún); select de semestre en ambos modales de matrícula, poblado desde data-duracion de las opciones de programa vía nueva función global poblarSelectSemestre(); corrige bug preexistente del badge "🎓 N programas" (COUNT(*) → COUNT(DISTINCT prog_id), evita sobre-contar cuando un estudiante tiene 2+ matrículas del mismo programa en períodos distintos); precedido de un diagnóstico de solo lectura sobre alternativas de diseño (nueva fila vs. actualizar fila existente); verificado con curl+SQL directo contra el contenedor Docker vivo (3 pruebas); pendiente como próximos pasos: columna "Semestre" en tablaMatriculados, pestañas Per. Actual/Per. Anteriores, reportes "Semestre: N/M", y la acción "avanzar de semestre" en sí | 2026-09-02 |
| `8884cb4` | feat(estudiantes): agregar columna Semestre a tablaMatriculados (02_estudiantes) — primera pieza visible en la UI de la base sentada en c985188 (matr_semestre existía en BD y en los modales, pero no en ningún listado); listar_matriculados agrega matr_semestre y prog_duracion_semestres al SELECT (mismo JOIN existente hacia programas, sin JOIN nuevo); nuevo <th>Semestre</th> entre "Programa" y "Período", nueva columna en la misma posición del array columns (60px); render() muestra "N/M" con fallback '—', resaltado #cfe2ff (mismo estilo que Edad) si matr_semestre === prog_duracion_semestres; comparación con == en vez de === en JS por los campos numéricos llegando como strings vía JSON de PDO; verificado con curl (SELECT correcto) y con 2 filas reales editadas temporalmente en MySQL para forzar ambas ramas del render, revertidas sin dejar datos de prueba; verificación visual en navegador pendiente (sin herramienta de navegador disponible en la sesión); pendiente: pestañas Per. Actual/Per. Anteriores, semestre en reportes/boletín/vista docente, acción "avanzar de semestre" | 2026-09-02 |
| `0aaa4e9` | feat(estudiantes): dividir Matriculados en pestañas Per. Actual / Per. Anteriores (02_estudiantes) — segunda pieza visible del roadmap de "semestre en la UI" tras c985188 y 8884cb4; listar_periodos agrega peri_activo, listar_matriculados agrega m.peri_id (faltante pese a que la especificación original lo daba por existente); pestaña única dividida en "Matriculados (Per. Actual)" (input readonly con el período activo, sin selector) y "Matriculados (Per. Anteriores)" (select con todos los períodos excepto el activo, sin opción "Todos", preselecciona el más reciente); dos tablas independientes (tbl_matriculados_actual/tbl_matriculados_anteriores) con filtros propios; columns extraído a crearColumnasMatriculados() global (primer caso del proyecto de dos DataTable con columnas idénticas); verModulosEstudiante() recibe peri_id como 4º parámetro explícito en vez de leer el selector único que dejó de existir; las 5 acciones existentes (guardar estudiante, subir foto, matricular, cerrar matrícula, editar matrícula) ahora recargan ambas tablas; 4 decisiones por ambigüedad documentadas en CHANGELOG.md (m.peri_id faltante, sin "Todos" en Anteriores, edge case de cero períodos anteriores con value="0", doble carga inicial por carrera cargarTablas()/cargarPeriodos() corregida con reload forzado); verificado con curl contra el contenedor Docker vivo + 6/6 pruebas en navegador confirmadas por Jose Luis; pendiente: semestre en reportes/boletín/vista del docente, y la acción "avanzar de semestre" en sí | 2026-09-02 |
| `27a3915` | feat(reportes): agregar semestre del estudiante en reporte y boletín PDF (06_reportes) — tercera pieza visible del roadmap de "semestre en la UI" tras c985188/8884cb4/0aaa4e9; mis_programas y detalle_periodo (reportes_mdl.php) agregan matr_semestre y prog_duracion_semestres al SELECT (mismo JOIN existente hacia programas, sin JOIN nuevo); reportes_ctrl.js agrega formatoSemestre() (formato "N/M", fallback '—') y muestra "Semestre" en el bloque de contexto de cargarProgramas() junto a Programa/Cohorte/Períodos realizados/Estado actual (reparto de columnas ajustado de 4/3/3/2 a 4/2/2/2/2); pdf_boletin.php extiende el mismo SELECT y muestra "Semestre: N/M" en el par de celdas vacías ya reservadas en la fila de "Período" de la tabla de contexto, sin fila nueva (antipatrón de Dompdf con colspan); 05_calificaciones queda fuera de alcance por decisión explícita (grse_semestre del grupo se conserva sin cambios, no se agrega matr_semestre ahí para evitar ruido entre dos valores que deberían coincidir); verificado en navegador por Jose Luis 3/3 pruebas; único punto pendiente del roadmap de semestre ahora: la acción "avanzar de semestre" | 2026-09-02 |
| `0aef564` | feat(estudiantes): matricular al siguiente semestre (02_estudiantes) — pieza final del roadmap de "semestre en la UI", CIERRA el roadmap completo (c985188/8884cb4/0aaa4e9/27a3915); validarSemestrePrograma() extraída como función reutilizable en est_mdl.php (antes duplicada en matricular/editar_matricula), usada también por el nuevo case; listar_matriculados agrega matr_estado_academico y aprobado_periodo_actual (subconsulta que replica de forma consciente la lógica de promedio de detalle_periodo, solo para UI); nuevo case 'avanzar_semestre' (solo coordinador/administrador) con validaciones en cadena — matr_estado='matriculado', matr_estado_academico='Activo', límite de semestre, período destino distinto, aprobación RECALCULADA con query directa (no confía en aprobado_periodo_actual), sin duplicado en destino — transacción PDO: fila anterior → matr_estado='cursado', INSERT de la nueva con matr_semestre+1 y mismo coho_id heredado; frontend: crearColumnasMatriculados(esPeriodoActual) gana un parámetro booleano (no viola la advertencia de CLAUDE.md contra funciones compartidas con parámetros condicionales — solo 1 de 13 columnas difiere), nuevo ítem de dropdown "⏩ Matricular al sgte. sem." visible solo en Per. Actual con 4 motivos de deshabilitado en cascada, nuevo modal #mdl_avanzar_semestre con selector de período destino poblado clonando las opciones ya excluidas del activo de #slct_filtro_matr_peri_id_anteriores (sin exponer periodoActivoId fuera de su scope); backend probado con curl 10/10 casos, datos de prueba revertidos sin dejar rastro; verificado en navegador por Jose Luis 6/6 puntos | 2026-09-02 |
| `3ba9f99` | feat(db): agrega tabla solicitudes_actualizacion — Fase 1/5 de link de actualización de datos (02_estudiantes) — nueva tabla de staging (database/emdb_academica.sql) para que un estudiante actualice su ficha vía link público sin sesión, pendiente de aprobación del coordinador; 63 columnas: 10 de control/auditoría (soac_id, estu_id FK→estudiantes CASCADE, soac_token UNIQUE, soac_generado_en/expira_en, soac_estado ENUM('generado','recibido','aprobado','descartado'), soac_recibido_en/resuelto_en, soac_resuelto_por FK→usuarios SET NULL, soac_campos_modificados), 17 espejo de estudiantes (prefijo soac_estu_, NULLABLE, excluye tipo/número de documento e identidad/sistema) y 35 espejo de fichas_inscripcion (prefijo soac_ficha_, excluye PK/FK/estado/auditoría); 3 decisiones de diseño — soac_ficha_prog_id sin FK (valor sin validar hasta aprobar), soac_estado como borrado lógico en vez de DELETE físico, tabla ubicada al final del .sql (sección "BLOQUE 6D", mismo patrón que ayudas/configuracion); CREATE TABLE aislado aplicado contra Docker vivo sin re-ejecutar el seed completo; footer del .sql actualizado 18→19 tablas; verificado con DESCRIBE/KEY_COLUMN_USAGE/SHOW INDEX + grep de soac_ en todo app/ (0 colisiones); **Fase 1 de 5 — solo esquema, sin PHP/JS todavía**: faltan Fase 2 (backend de generación de token), Fase 3 (formulario público), Fase 4 (frontend del dropdown/badge + aprobar/descartar) y Fase 5 (documentación de cierre) | 2026-09-02 |
| `03e36fc` | feat(estudiantes): backend de link de actualización de datos — Fase 2/5 (02_estudiantes) — sincronizarEmailUsuario() nueva en 00_files/helpers.php (extraída del patrón de doc_mdl.php commit b6cc503); fix de bug real en guardar_completo (editar correo de un estudiante con usua_id ahora sincroniza usuarios.usua_email, antes solo tocaba estudiantes.estu_email); 4 cases nuevos en est_mdl.php solo role_id IN (1,2) — generar_link_actualizacion (elegibilidad matriculado+Activo+período actual, invalida solicitud previa activa automáticamente, token de 64 hex chars, expira 24h), estado_solicitud_actualizacion (la más reciente, null si nunca tuvo una), aprobar_actualizacion (aplica solo campos soac_* no nulos sin sobrescribir con NULL, calcula soac_campos_modificados, sincroniza usua_email si cambió, rollback total ante colisión), descartar_actualizacion (borrado lógico); listar_matriculados agrega soac_estado_activo para el badge de la Fase 4; bug encontrado y corregido en pruebas — aprobar_actualizacion no validaba estudiantes.estu_email (UNIQUE KEY propia) antes del UPDATE; 11 casos probados con curl contra Docker vivo, datos de prueba revertidos a su valor original; sin cambios en est_view.php/est_ctrl.js; **Fase 2 de 5**: faltan Fase 3 (formulario público), Fase 4 (frontend) y Fase 5 (documentación de cierre) | 2026-09-02 |
| `b739a60` | feat(actualizacion-datos): formulario público de actualización — Fase 3/5 — nuevo módulo 11_actualizacion_datos/ (3 archivos), sin sesión (mismo criterio que 09_inscripcion_publica/), sin cambios en 02_estudiantes/; actualizar_mdl.php con 3 cases — validar_token (existencia+estado 'generado'+expiración, responde {status,motivo} en vez del envelope estándar, precarga valores vigentes vía LEFT JOIN igual que obtener_completo), guardar_actualizacion (re-valida el token igual que validar_token, escribe solo en columnas soac_*/soac_ficha_* nunca en estudiantes/fichas_inscripcion directamente, UPDATE condicional WHERE soac_estado='generado' previene doble submit), listar_programas (catálogo público duplicado de insc_mdl.php, agregado como necesidad real); actualizar_view.php página completa no modal con las 5 secciones de #mdl_estudiante menos documento (solo lectura), 3 mensajes de error según motivo, confirmación final; actualizar_ctrl.js reutiliza por copia (no import) funciones de est_ctrl.js/insc_ctrl.js, sin reCAPTCHA; bug corregido durante el desarrollo (exit dentro de foreach cortaba el script en la primera iteración, corregido a bandera+break); 7 casos probados con curl sin sesión, tokens de prueba resueltos sin solicitudes activas colgadas; verificado en navegador por Jose Luis 4/4 puntos; **Fase 3 de 5**: faltan Fase 4 (frontend) y Fase 5 (documentación de cierre) | 2026-09-02 |
| `70c45ba` | feat(estudiantes): frontend de link de actualización de datos — Fase 4/5 (02_estudiantes) — conecta el backend (Fase 2) y el formulario público (Fase 3) desde tablaMatriculados; nuevo ítem de dropdown "🔗 Generar link actualizar datos" solo en Per. Actual, deshabilitado si matr_estado_academico !== 'Activo'; modal #mdl_actualizacion_datos con Modo 1 (ver y aprobar, abre #mdl_estudiante en modo revisión) y Modo 2 (generar link + botón Copiar); modo revisión de #mdl_estudiante REUTILIZADO vía abrirRevisionActualizacion()/poblarModalRevision(), campos propuestos resaltados con .campo-actualizado (amarillo), formulario deshabilitado, botones Aprobar/Descartar; restaurarModoNormalEstudiante() revierte todo; 3 badges independientes coexistentes — 🎓 N programas, 🔔 Link enviado/Pendiente aprobación (soac_estado_activo), ✅ Actualizado [fecha] permanente (soac_fecha_ultima_aprobacion, pedido posterior implementado en el mismo ciclo); listar_matriculados agrega ambos campos; fix heredado de la Fase 2 en la URL de generar_link_actualizacion (único cambio en est_mdl.php, 11_actualizacion_datos/ intacto); verificado en navegador por Jose Luis 8+4 pasos, todos correctos; **Fase 4 de 5**: falta únicamente la Fase 5 (documentación de cierre) | 2026-09-03 |
| `6abbdae` | chore: resuelve 4 pendientes menores de Deuda técnica en un solo commit — (1) centraliza configuracion.institucion_nombre en pdf_grupo.php/pdf_hoja_matricula.php/pdf_ficha.php (mismo patrón que pdf_boletin.php, ef429bd); (2) aprobar_actualizacion (est_mdl.php) cambia SELECT * FROM estudiantes por columnas explícitas derivadas de $mapaEstudiantes (prerequisito del punto 3); (3) elimina estudiantes.coho_id + FK fk_estu_coho (sin escrituras desde 3e551e5, sin lectores tras el punto 2), reflejado en database/emdb_academica.sql con el comentario de matriculas corregido; (4) elimina case mis_modulos/mis_notas de reportes_mdl.php (código muerto sin llamador, re-verificado antes de eliminar); probado contra Docker vivo — 4 PDFs verificados con pdftotext, aprobar_actualizacion probado con solicitud simulada y datos revertidos, listar_matriculados/obtener_completo probados tras el DROP COLUMN sin errores, php -l limpio en los 6 archivos | 2026-09-03 |
| `ca744d0` | feat(estudiantes): reemplaza columna Correo por Información en tablaMatriculados (02_estudiantes) — traslada los 3 badges (🎓 N programas, 🔔 Link/Pendiente, ✅ Actualizado) que vivían junto al nombre a una columna visual dedicada sin campo en BD; columna estu_apellidos simplificada sin render; correo deja de mostrarse en esta tabla, sigue disponible en la ficha del estudiante (obtener_completo) sin cambios en est_mdl.php | 2026-09-03 |
| `7052723` | fix(estudiantes): elimina duplicación de filas en tablaMatriculados por condición de carrera (02_estudiantes) — separa tablaMatriculadosActual/tablaMatriculadosAnteriores de cargarTablas() a nueva función cargarTablasMatriculados(), invocada una sola vez desde el success de cargarPeriodos() cuando periodoActivoId y el select de Anteriores ya tienen su valor definitivo; elimina el auto-load prematuro de ambas tablas con peri_id sin resolver que competía con el .ajax.reload(null, false) posterior y producía filas duplicadas al mostrar 50+ registros; cargarTablas() ahora solo construye tablaAspirantes; sin cambios en est_mdl.php (SQL ya estaba correcto, confirmado por diagnóstico); reportado por Jose Luis con fila #1 = fila #17 al mostrar 50 registros; verificado en navegador: 2 peticiones a listar_matriculados en vez de 4, sin filas repetidas en ninguna pestaña, filtros y badges de Información funcionando correctamente | 2026-09-03 |

---

## Decisiones tomadas (no reabrir)

| Decisión | Detalle |
|---|---|
| Login sin selector de rol | El sistema detecta el rol automáticamente por BD |
| Aspirante y Estudiante misma tabla | `matriculas.matr_estado` maneja el ciclo — revisar en TRL6 |
| `DECIMAL(3,1)` para notas | Escala 0.0-5.0 colombiana — no cambiar a DECIMAL(5,2) |
| N3 sin supletorio | Regla institucional invariable |
| 3 formularios separados por rol | Coordinadora, docentes y estudiantes — no uno con lógica condicional |
| cast `(int)` en login_mdl.php para role_id | PDO devuelve strings; todos los módulos usan `!==` para comparar roles |
| `logout.php` en `01_login/` | Centralizado ahí para que todos los navbars apunten al mismo archivo |
| `abrirEditar()` global en admin_ctrl.js | DataTables render inline no puede llamar funciones dentro de `$(document).ready` |
| Commits manuales desde CMD | Jose Luis hace git add/commit/push. Claude Code nunca ejecuta git |
| Separación de dominios por módulo | 08_admin gestiona solo admin y coordinador. Docentes se crean exclusivamente desde 03_docentes. Estudiantes se crearán exclusivamente desde 02_estudiantes. Sin cruces entre módulos. |
| Credencial login estudiantes | usua_login = número de documento. usua_email = correo real del estudiante (estu_email, UNIQUE), no el correo sintético {numerodoc}@emdb.local usado hasta el 2026-08-19 (commit d62dde6). Clave generada automáticamente: 4 letras apellido + año nacimiento. El correo de acceso se muestra junto a la clave en el modal de confirmación de matrícula. |
| Estructura grupos rediseñada | grseestudiantes eliminada — reemplazada por grmoestudiantes (estudiante vinculado a módulo específico, no al semestre completo). programa_modulos para semestre sugerido por módulo según programa. coho_id en gruposemestres para trazabilidad completa. |
| Triggers MySQL eliminados | sp_calcular_definitiva y triggers AFTER INSERT/UPDATE eliminados — MySQL no permite UPDATE en trigger de la misma tabla. Cálculo de definitiva implementado en PHP en calificaciones_mdl.php. |
| Subida de archivos vía FormData | Primera vez en el proyecto (foto de estudiante) — usa `FormData` + `processData`/`contentType: false`, distinto del patrón de objeto plano usado en el resto de la app. |
| Indicador de ficha completa (simple) | Definido como existencia de fila en `fichas_inscripcion`, no validación campo por campo. |
| Jornada es atributo de grupo semestre, no de cohorte | La jornada (Semana/Sábados) es un atributo de grupo semestre, no de cohorte — una cohorte puede tener grupos semestre con jornadas distintas. |
| Edición manual de `matr_numero` no desincroniza la asignación automática | La asignación automática (`pdf_hoja_matricula.php`) no usa un contador cacheado en `configuracion` — `matr_numero_inicial` es solo un piso mínimo. Cada asignación relee `MAX(matr_numero) FROM matriculas` en vivo, así que un valor editado manualmente desde "Editar Matrícula" (commit `59775e4`) ya queda reflejado en el siguiente cálculo. No reabrir esta pregunta ni agregar un mecanismo de resincronización — no hace falta. |
| Formulario público de inscripción es de un solo paso, sin código de retomar | El diseño original de 2 pasos (Fase 3 del roadmap histórico, `finc_codigotemporal`) se eliminó por completo en el commit `0e098bb` (2026-08-24) — `fam_view.php`/`fam_mdl.php`/`fam_ctrl.js` ya no existen, y la columna `finc_codigotemporal` ya no existe en el esquema. No reintroducir un mecanismo de "retomar después" sin decisión explícita de Jose Luis — el formulario actual es de una sola pantalla, un solo guardado transaccional. |
| El formulario público de inscripción nunca genera clave de acceso al sistema | Confirmado por diagnóstico (grep de `generarClaveAuto`/`clave_generada`/`usua_passwordhash` en `09_inscripcion_publica/`, sin resultados) antes y después del commit `0e098bb` — la generación de clave es y siempre fue tarea exclusiva del coordinador vía `matricular` en `est_mdl.php`. No confundir con `finc_codigotemporal` (un puente técnico para retomar el Paso 2, nunca una credencial), ya eliminado. |

---

## Análisis pendiente (no resolver sin decisión)

| Ítem | Detalle |
|---|---|
| `fichas_inscripcion.prog_id`/`jornada` sin FK/constraint contra `matriculas.prog_id`/`peri_id` | Son dos datos independientes que se sincronizan solo por convención de captura, no por esquema — nada impide que diverjan (mismo tipo de brecha que el antipatrón "filtrar por campo proxy" ya documentado en CLAUDE.md para `estudiantes.coho_id` vs matrícula real, commit `7340d6e`). Desde el commit `ef79791` (2026-08-22) el modal Completar Matrícula precarga `prog_id` de `fichas_inscripcion` como sugerencia editable — mitiga el síntoma (el coordinador ya no parte de cero) pero no cierra la brecha de fondo: si el aspirante cambió de programa entre la Ficha y la matrícula y el coordinador no corrige el select precargado, `fichas_inscripcion` y `matriculas` quedan con `prog_id` distintos sin que nada lo detecte. Pendiente de decisión futura: ¿vale la pena un chequeo de consistencia visual (advertencia, no bloqueo) si difieren al guardar? No implementar sin decisión explícita de Jose Luis. |
| `case 'obtener'` de `doc_mdl.php` (03_docentes) usa `INNER JOIN` entre `docentes` y `usuarios` | Un docente con `usua_id NULL` (sin acceso al sistema) haría que este `JOIN` no devuelva ninguna fila — el endpoint respondería "Docente no encontrado" en vez de abrir el modal "Editar Docente" con el campo de contraseña oculto. Detectado como efecto colateral al implementar el cambio de contraseña opcional en edición (commit `35c312a`, 2026-08-25). Hoy no hay ningún docente en esa condición en la BD (`SELECT COUNT(*) FROM docentes WHERE usua_id IS NULL` → 0), así que no es un bug activo — es una decisión de diseño pendiente, no una limpieza mecánica (mismo criterio ya aplicado a `matriculas.matr_matriculadopor`, commit `5de9a9e`). Pendiente de decisión futura: ¿cambiar a `LEFT JOIN` si en algún momento se permite crear/editar docentes sin usuario asociado? No implementar sin decisión explícita de Jose Luis. |
| `doc_view.php`, `est_view.php` y `grupos_view.php` (03_docentes / 02_estudiantes / 04_grupos) tienen un `<nav>` fallback (`navbar-dark bg-dark px-3`, patrón "Docente/rol sin navbar.php") inalcanzable en la práctica | Los tres archivos comparten el mismo guard de sesión (`if ($_SESSION['role_id'] !== 1 && $_SESSION['role_id'] !== 2) { redirect; }`), que rechaza cualquier rol distinto de Admin/Coordinador **antes** de llegar al `<body>` — la rama `else` con el `<nav>` fallback nunca se ejecuta para ningún otro rol, es código muerto en los tres casos. Detectado al implementar el bloque "Último acceso" en el navbar (commit `ac96f07`, 2026-08-25): se agregó primero a `doc_view.php` asumiendo que era el nav real de Docente, y se descubrió que Docente (`role_id=3`) ni siquiera puede acceder a esa vista — su destino real tras login es `05_calificaciones/calificaciones_view.php` (único de los tres con guard `!== 1 && !== 2 && !== 3`, que sí permite Docente); el cambio se revirtió de `doc_view.php` y se aplicó ahí en su lugar. `est_view.php` y `grupos_view.php` quedan con el mismo tipo de código muerto sin verificar si algún otro rol debería alcanzarlos. Pendiente de decisión futura: ¿eliminar esos tres bloques `<nav>` fallback (nunca alcanzables con los guards actuales) o ampliar el guard de esas vistas si en algún momento un rol adicional necesita entrar? No implementar sin decisión explícita de Jose Luis. |
| ~~UI de segunda matrícula / multi-programa~~ (RESUELTO 2026-08-30, commit `f5c21e5`; indicador visual RESUELTO 2026-08-31, commit `1529832`) | El esquema ya permitía que un estudiante tuviera más de una fila en `matriculas` desde `3e551e5`, pero no existía ninguna UI para que el coordinador matriculara deliberadamente a un estudiante ya matriculado en un segundo programa. Implementado en `f5c21e5`: botón "➕ Matricular en otro programa" en `tablaMatriculados` (reutiliza `abrirMatricular()`/`#mdl_matricular`), contexto de matrículas previas en ese modal, sección "Programas matriculados" en el modal Datos Estudiante, validaciones que bloquean duplicar el mismo programa en `matricular`/`editar_matricula`, y fix del bug real de `total_modulos` que mezclaba módulos entre programas (ver CHANGELOG.md). El indicador visual tipo "2 programas" en las filas de Matriculados, evaluado y descartado en `f5c21e5`, se retomó a pedido explícito de Jose Luis y **sí se implementó finalmente** en el commit `1529832` (2026-08-31): badge "🎓 N programas" junto al apellido en `tablaMatriculados`, visible solo cuando el estudiante tiene 2+ matrículas, con el detalle (`"SIGLA — Estado"` por cada una) en el `title` — `tablaAspirantes` queda sin cambios por diseño, ese caso nunca aplica ahí. Ítem cerrado por completo, sin pendientes conocidos. |
| `matr_estado_academico` se muestra en la UI pero sigue sin ningún mecanismo de edición (commit `3e551e5`, 2026-08-30 → actualizado en `b3a0296`, 2026-08-30) | La columna se agregó con su default `'Activo'` como preparación de esquema para la funcionalidad de multi-programa. Desde `b3a0296` (case `mis_programas` de `reportes_mdl.php`) **sí se lee y se muestra** — visible como "Estado actual" (badge de solo lectura) en la pestaña "Reporte por Estudiante" de `06_reportes`, tanto para el propio estudiante como para el coordinador consultando a otro. Lo que sigue pendiente es la escritura: ningún `case` de `est_mdl.php` ni de ningún otro `_mdl.php` permite todavía cambiar su valor (`SELECT` sí, `UPDATE` no en ningún lado). Pendiente: diseñar dónde y cómo se edita — la futura pestaña "Novedades" (Activo/Aplazamiento/Retirado por período) es el candidato natural. No implementar sin decisión explícita de Jose Luis. |
| `mis_modulos` y `mis_notas` de `reportes_mdl.php` quedan sin llamador desde el frontend actual (commit `8d479a1`, 2026-08-30) | Estos 2 `case` alimentaban la interfaz "Mis Notas" del estudiante (un módulo a la vez, sin noción de período) que existía antes del rediseño de `06_reportes` — reemplazada por completo por "Reporte por Estudiante" (`mis_programas`/`mis_periodos`/`detalle_periodo`). `grupos_para_reporte` y `reporte_grupo` sí siguen con llamador activo (alimentan la pestaña "Reporte por Grupo", recuperada intacta en el mismo commit). Limpieza de código muerto diferida a una fase futura aparte — mismo criterio ya aplicado en otros ciclos del proyecto (ver `5de9a9e`). No implementar sin decisión explícita de Jose Luis. |
| Nombre institucional sin centralizar en 3 de los 4 PDF del proyecto (commit `ef429bd`, 2026-08-30) | `pdf_boletin.php` ya lee `configuracion.institucion_nombre` en vez de hardcodear el nombre — pero `pdf_grupo.php`, `pdf_ficha.php` y `pdf_hoja_matricula.php` siguen con `"Escuela de Mecánica Dental Bolaños (EMDB)"` hardcodeado inline, cada uno por separado. Deliberadamente fuera de alcance de la Fase 2 de `06_reportes` (que solo tocó `pdf_boletin.php`). Pendiente de decisión futura: ¿vale la pena unificar los 4 archivos para que lean todos de `configuracion`? No implementar sin decisión explícita de Jose Luis. |

---

## Flujo de trabajo establecido
Claude IA (chat)          Claude Code              Jose Luis
──────────────────────────────────────────────────────────────
Analiza contexto      →
Genera prompt         →   Ejecuta en código
←   Entrega resultados
Jose Luis pega resultados acá
Claude IA analiza y decide →
Genera prompt         →   Aplica cambios
←   Entrega resultados
Jose Luis pega resultados acá
Claude IA sugiere pruebas
← Prueba en navegador
Jose Luis reporta resultados
Claude IA verifica ✅
Entrega comandos git  →                         ← git add / commit / push
Jose Luis pega hash acá
Claude IA identifica docs a actualizar
Genera prompt docs    →   Actualiza archivos
← git add / commit / push

**Reglas:**
- Claude Code solo ejecuta código. El análisis y los prompts los genera Claude IA.
- Claude Code nunca hace commits. Jose Luis los hace desde CMD tras revisar.
- SIEMPRE dos prompts separados: Prompt 1 diagnóstico (solo lectura), Prompt 2 modificación.
- Si el diagnóstico es limpio y no hay opciones que elegir, Claude IA lo indica y pasa directo al Prompt 2.
- El ciclo completo por cada tarea es: Diagnóstico → Análisis → Implementación → Pruebas → Commit → Documentación.

---

## Cómo usar este archivo

**Al iniciar un nuevo chat:**
Pegar el contenido completo como primer mensaje:
*"Aquí está el contexto completo de mi proyecto. Continuamos desde donde quedamos."*
Luego indicar la tarea específica del día.

**Al completar un hito:**
Cambiar ⬜ → 🔄 → ✅ con fecha de completado.

**Al descubrir algo nuevo:**
Agregar en la sección correspondiente. Si cambia una regla de negocio,
actualizar también el CLAUDE.md para que Claude Code lo refleje en el código.

**Leyenda:**
- ✅ Completado
- 🔄 En progreso / parcial
- ⚠️ Bloqueado / requiere acción
- ⬜ Pendiente
