# Informe de Estrategia de Verificación y Pruebas

> Generado por diagnóstico de solo lectura sobre el repositorio
> `app_academica_emdb` — 2026-09-07. Fuentes: `git log --all`,
> `CHANGELOG.md` (6643 líneas, 150 entradas de commit/fase útiles),
> `PROJECT_CONTEXT.md`, `CLAUDE.md`, `README.md`. Ningún archivo del
> proyecto fue modificado para producir este informe.

## 0. Nota metodológica previa — importante para el comité

El prompt que originó este informe pide justificar la estrategia de
verificación **"como decisión metodológica dentro de Solo XP"**. Se
buscó explícitamente la cadena "Solo XP"/"XP" (Extreme Programming) en
`CLAUDE.md`, `PROJECT_CONTEXT.md` y `README.md`: **no aparece en
ningún lugar del repositorio.** Lo que sí está documentado como
metodología del proyecto es:

- `README.md:241` — **"Metodología de desarrollo: CDIO (Concebir,
  Diseñar, Implementar, Operar)"**.
- `PROJECT_CONTEXT.md:17` (título del proyecto) — *"...mediante
  Metodología CDIO en el Contexto del ODS 4"*.
- `CHANGELOG.md` (commit `0.1.0`, 2026-02-15, propuesta aprobada) —
  **"Metodología: CDIO + Scrum. Meta de validación: TRL 5"**.
- `CLAUDE.md`, sección "Ciclo de trabajo con Claude IA" — un ciclo
  fijo de 6 fases por tarea (Diagnóstico → Análisis → Implementación →
  Pruebas → Commit → Documentación), replicado también en
  `PROJECT_CONTEXT.md` ("Flujo de trabajo establecido").

Ninguno de los tres documentos usa el término "Solo XP" para nombrar
esta metodología. Lo que sí es consistente con prácticas típicas de
*Extreme Programming* aplicadas en solitario (ciclos cortos,
retroalimentación inmediata del "cliente" — aquí Jose Luis como
usuario/director en el rol de Product Owner, verificación funcional en
cada entrega en vez de un plan de pruebas separado) es el **patrón de
trabajo observado**, no una etiqueta que el propio proyecto se haya
puesto. **Recomendación para la sustentación:** si el comité espera
ver "Solo XP" citado textualmente en el repositorio, no lo va a
encontrar — la justificación debe presentarse como "el patrón de
trabajo documentado es consistente con prácticas de Solo XP dentro de
un marco CDIO+Scrum declarado", no como una cita literal del proyecto.

---

## 1. Resumen cuantitativo

- **Total de entradas registradas en CHANGELOG.md:** 150 (excluyendo el
  placeholder vacío `[Unreleased]`). De estas, 3 son encabezados de
  fase/cierre de roadmap sin commit propio (agrupan varios commits ya
  contados aparte); 147 corresponden a uno o más commits identificados
  por hash.
- **Entradas con verificación documentada (algún método explícito o
  mención genérica de "verificado"/"probado"):** 88 de 150 (**58,7 %**).
- **Entradas sin ninguna mención de verificación:** 62 de 150 (41,3 %)
  — concentradas sobre todo en la primera mitad del proyecto (Sprints
  0-2, julio-agosto 2026), antes de que el ciclo de 6 fases con
  "Pruebas" como fase obligatoria quedara consolidado en `CLAUDE.md`.
- **Métodos de verificación empleados** (una entrada puede usar más de
  uno; conteo de commits que mencionan cada método, sobre las 150
  entradas):

| Método | Commits que lo mencionan |
|---|---|
| `curl` (contra endpoints PHP, con o sin Docker) | 35 |
| Navegador manual, ejecutado y reportado por Jose Luis | 33 |
| Docker — "contenedor vivo" / "Docker local" explícito | 20 |
| Mención genérica de "verificado"/"probado" sin método explícito | 18 |
| `php -l` (lint de sintaxis) | 13 |
| **Playwright** (headless, automatizado) | **12** |
| Consulta SQL directa contra la BD (verificación de datos) | 5 |
| `grep` estático (verificación de código, no de comportamiento en ejecución) | 5 |
| `pdftotext` (verificación de contenido de PDF generado) | 2 |

**Lectura para el comité:** de los 88 commits con verificación
documentada, **35 usan `curl`** (la herramienta que el prompt original
menciona para backend) y **12 usan Playwright** (la herramienta que el
prompt original menciona para frontend) — ambos aparecen exclusivamente
en la segunda mitad del proyecto (a partir de 2026-08-15 para `curl` en
seguridad, y exclusivamente entre el 2026-09-03 y 2026-09-05 para
Playwright, concentrado en las Fases 2.12 y 2.14). **Playwright no es
la herramienta dominante de todo el proyecto — es una práctica que se
adoptó puntualmente en la recta final**, y así lo dice el propio
CHANGELOG (ver commit `f3c0ca4` en la sección 3): *"Playwright headless
(instalado puntualmente en esta sesión, sin quedar integrado como
dependencia del proyecto)"*. No hay ningún `package.json` ni
dependencia de Playwright versionada en el repo (confirmado en
`CLAUDE.md`, sección "Frontend stack": *"Sin `package.json`, sin
lockfile, sin build step"*).

---

## 2. Evidencia por feature (tabla completa, 150 entradas de CHANGELOG.md — orden cronológico descendente)

| Feature/Fase | Fecha | Método de verificación | Commit (hash) |
|---|---|---|---|
| Fase 2.16 — Instrumentación de métricas técnicas para validación TRL5 (OE4) — 2026-09-06 | — | mención genérica de verificación (sin método explícito) | `— (sección, sin commit propio)` |
| Exportación Excel/PDF del reporte de grupo habilitada para el Docente | 2026-09-06 | Navegador manual (Jose Luis); curl | `0501479` |
| chore: reemplaza el seed público con dataset anonimizado (`database/emdb_academica.sql`) | 2026-09-06 | curl | `300cfc1` |
| chore: script estandarizado de export de BD para hosting (`scripts/export_para_hosting.sh`) | 2026-09-06 | No documentado | `40b28c8` |
| Cierre del roadmap "N3 configurable por actividades" (Fase 2.14, A-H) — 2026-09-05 | — | No documentado | `— (sección, sin commit propio)` |
| feat(calificaciones): columna N3 de solo lectura en tabla principal — Fase 2.14.F | 2026-09-05 | Playwright | `84448da` |
| feat(calificaciones): autosave de la tabla de registro N3 — Fase 2.14.E3.2 | 2026-09-05 | Playwright | `5c9ee42` |
| feat(calificaciones): tabla dinámica de registro N3 — estructura y carga — Fase 2.14.E3.1 | 2026-09-05 | Playwright | `b827d14` |
| feat(calificaciones): JS del modal Configurar actividades N3 — Fase 2.14.E2 | 2026-09-05 | Playwright | `352a4f1` |
| feat(calificaciones): estructura HTML de modales N3 — Fase 2.14.E1 | 2026-09-05 | Navegador manual (Jose Luis) | `0cbb2cb` |
| feat(calificaciones): cálculo derivado de N3 + integración con Nota Final — Fase 2.14.D | 2026-09-05 | mención genérica de verificación (sin método explícito) | `24a43c7` |
| feat(calificaciones): backend de notas por actividad N3 — Fase 2.14.C | 2026-09-05 | Docker (contenedor vivo); curl | `953a50f` |
| feat(calificaciones): backend CRUD de actividades N3 — Fase 2.14.B | 2026-09-05 | Docker (contenedor vivo); curl | `7dadd80` |
| feat(calificaciones): agrega esquema BD para N3 configurable — Fase 2.14.A | 2026-09-05 | No documentado | `f592885` |
| feat: frontend de gestión de claves, cierra Phase 2.13 | 2026-09-04 | Navegador manual (Jose Luis); curl | `ef295f7` |
| feat: backend de gestión de claves | 2026-09-04 | Docker (contenedor vivo); curl | `183e9b1` |
| feat: barra de pendientes y detalle de requisitos en la vista del Estudiante | 2026-09-04 | Playwright; curl | `ac76748` |
| feat: backend de resumen de requisitos para la vista del Estudiante | 2026-09-04 | Docker (contenedor vivo); curl | `b745774` |
| feat: lista informativa de requisitos en el modal de matrícula | 2026-09-04 | Playwright; SQL directo; curl | `b84eda2` |
| style: ajustar ancho de columnas Edad/Programa/Semestre | 2026-09-04 | Playwright | `4386b67` |
| feat: wiring de guardado del checklist de requisitos por matrícula | 2026-09-04 | Playwright; SQL directo | `a5ef1e5` |
| feat: listado real de requisitos dentro del modal de matrícula | 2026-09-04 | Playwright | `9196f18` |
| feat: dropdown + modal (estructura) para requisitos por matrícula | 2026-09-03 | Playwright | `caa3cff` |
| feat: columna "Requisitos" de solo lectura en tablaMatriculados | 2026-09-03 | Navegador manual (Jose Luis); Playwright | `8450a24` |
| feat: ficha "Configurar Requisitos" para Admin | 2026-09-03 | Navegador manual (Jose Luis); Playwright; SQL directo; curl | `f3c0ca4` |
| feat: backend Coordinador para checklist de requisitos por matrícula | 2026-09-03 | Docker (contenedor vivo); SQL directo; curl | `350c614` |
| feat: asignar requisitos automáticamente al crear una matrícula nueva | 2026-09-03 | Docker (contenedor vivo); SQL directo; curl | `f14331d` |
| feat: backend Admin del catálogo de requisitos por programa | 2026-09-03 | Docker (contenedor vivo); curl | `c4b81f2` |
| refactor: reemplazar 9 columnas req_* fijas por esquema configurable | 2026-09-03 | No documentado | `ea03618` |
| fix(estudiantes): elimina duplicación de filas en tablaMatriculados | 2026-09-03 | Docker (contenedor vivo); Navegador manual (Jose Luis) | `7052723` |
| feat(estudiantes): reemplaza columna Correo por Información | 2026-09-03 | No documentado | `ca744d0` |
| chore: resuelve 4 pendientes menores (PDFs, SELECT *, coho_id, código muerto) | 2026-09-03 | Docker (contenedor vivo); curl; grep estático; pdftotext; php -l | `6abbdae` |
| feat(estudiantes): frontend de link de actualización de datos — Fase 4/5 | 2026-09-03 | Navegador manual (Jose Luis); curl; php -l | `70c45ba` |
| feat(actualizacion-datos): formulario público de actualización — Fase 3/5 | 2026-09-02 | Navegador manual (Jose Luis); curl | `b739a60` |
| feat(estudiantes): backend de link de actualización de datos — Fase 2/5 | 2026-09-02 | Docker (contenedor vivo); curl | `03e36fc` |
| feat(db): agrega tabla solicitudes_actualizacion — Fase 1/5 | 2026-09-02 | Docker (contenedor vivo) | `3ba9f99` |
| feat(estudiantes): matricular al siguiente semestre | 2026-09-02 | Docker (contenedor vivo); Navegador manual (Jose Luis); curl | `0aef564` |
| feat(reportes): agregar semestre del estudiante en reporte y boletín PDF | 2026-09-02 | Navegador manual (Jose Luis) | `27a3915` |
| feat(estudiantes): dividir Matriculados en pestañas Per. Actual/Anteriores | 2026-09-02 | Docker (contenedor vivo); Navegador manual (Jose Luis); curl; php -l | `0aaa4e9` |
| feat(estudiantes): agregar columna Semestre a tablaMatriculados | 2026-09-02 | Docker (contenedor vivo); Navegador manual (Jose Luis); curl; grep estático; php -l | `8884cb4` |
| feat(matriculas): agrega matr_semestre con validación | 2026-09-02 | Docker (contenedor vivo); curl; grep estático; php -l | `c985188` |
| feat(docentes): agrupa Acciones de tablaDocentes en dropdown | 2026-09-01 | Navegador manual (Jose Luis) | `e1c232d` |
| feat(estudiantes): agrupa Acción de Aspirantes en dropdown | 2026-09-01 | Navegador manual (Jose Luis) | `9346a76` |
| feat(estudiantes): deshabilita "Matricular en otro programa" + agrupa Acción en dropdown | 2026-08-31 | No documentado | `53d5ec5, 4fb2257` |
| feat(estudiantes): indicador visual "N programas" en Matriculados | 2026-08-31 | Navegador manual (Jose Luis); curl | `1529832` |
| feat(estudiantes): matrícula a segundo programa + fix de total_modulos | 2026-08-30 | curl; php -l | `f5c21e5` |
| feat(reportes): combina Reporte por Grupo + por Estudiante en pestañas (Fase 3/3) | 2026-08-30 | Navegador manual (Jose Luis); curl; grep estático; php -l | `8d479a1` |
| feat(reportes): reescribe pdf_boletin.php a boletín por período (Fase 2/3) | 2026-08-30 | pdftotext; php -l | `ef429bd` |
| feat(reportes): backend de reportes por período/programa (Fase 1/3) | 2026-08-30 | Docker (contenedor vivo); curl; php -l | `b3a0296` |
| feat(matriculas): migra coho_id de estudiantes a matriculas | 2026-08-30 | Docker (contenedor vivo); Navegador manual (Jose Luis); grep estático; php -l | `3e551e5` |
| fix(grupos): filtra grmo_activo=1 en total_modulos | 2026-08-30 | php -l | `b8a583a` |
| feat(grupos): agrega columna "# Estud" en Grupos Semestre | 2026-08-30 | Navegador manual (Jose Luis) | `a39cb24` |
| fix(grupos): corrige truncamiento SQL de grse_codigo/coho_codigo | 2026-08-29 | Navegador manual (Jose Luis); curl | `0e0a508` |
| feat(docentes): cablea doce_cedula completo | 2026-08-28 | No documentado | `7ba02bc` |
| feat(docentes): permite editar el correo electrónico en "Editar Docente" | 2026-08-28 | No documentado | `b6cc503` |
| refactor: unifica disposición de campos Sección 1 Ficha de Inscripción | 2026-08-26 | No documentado | `3672a0e` |
| feat: registro y visualización de "último acceso" del usuario | 2026-08-25 | php -l | `ac96f07` |
| feat(docentes): habilita cambio de contraseña en "Editar Docente" | 2026-08-25 | Navegador manual (Jose Luis) | `35c312a` |
| chore(ayudas): sincroniza contenido de 10_ayudas | 2026-08-24 | Navegador manual (Jose Luis) | `7374a0f` |
| refactor(inscripcion): unificar formulario público en un solo paso | 2026-08-24 | Docker (contenedor vivo); Navegador manual (Jose Luis) | `0e098bb` |
| feat(estudiantes): habilita edición manual de matr_numero | 2026-08-24 | Navegador manual (Jose Luis) | `59775e4` |
| refactor(estudiantes): reestructura modal de creación "Ficha de Inscripción" | 2026-08-24 | Navegador manual (Jose Luis) | `f088466` |
| refactor: elimina código muerto tras plan de unificación de ficha | 2026-08-23 | Navegador manual (Jose Luis) | `5de9a9e` |
| feat: agrega PDF Hoja de Matrícula (AC-FO-09) | 2026-08-23 | Navegador manual (Jose Luis) | `ffb6a6c` |
| feat: agrega usua_nombre a usuarios | 2026-08-23 | Docker (contenedor vivo); Navegador manual (Jose Luis) | `c34b780` |
| feat: reactiva folio/fecha/observaciones en matrícula | 2026-08-23 | Navegador manual (Jose Luis) | `afdcfc1` |
| feat: agrega configuración institucional | 2026-08-23 | Navegador manual (Jose Luis) | `25530cc` |
| feat: fusiona modales Editar Estudiante + Ficha Familiar | 2026-08-23 | Navegador manual (Jose Luis) | `76f90c5` |
| feat: agrega endpoint transaccional único guardar_completo/obtener_completo | 2026-08-23 | mención genérica de verificación (sin método explícito) | `42e4175` |
| feat: agrega columna Edad en Matriculados | 2026-08-23 | No documentado | `7cef01a` |
| feat(02_estudiantes): unifica botón Editar/Ficha | 2026-08-23 | No documentado | `38e1809` |
| feat(02_estudiantes): precarga Programa y Jornada declarados | 2026-08-22 | No documentado | `ef79791` |
| feat(grupos): eliminación de Períodos (DELETE condicionado) | 2026-08-20 | mención genérica de verificación (sin método explícito) | `a83cb43` |
| feat(estudiantes): edición de matrícula + fix Asignación Estudiantes | 2026-08-20 | No documentado | `7340d6e` |
| feat(estudiantes): filtros Programa/Período/Grupo/Módulo | 2026-08-20 | No documentado | `b0e6660` |
| feat(estudiantes): columna de correo + cascada Programa→Cohorte→Período | 2026-08-20 | mención genérica de verificación (sin método explícito) | `cac382a` |
| feat(grupos): CRUD de Programas | 2026-08-19 | No documentado | `00a22fc` |
| feat(reportes): nota explicativa de supletorios en PDF | 2026-08-19 | mención genérica de verificación (sin método explícito) | `a49b652` |
| fix(reportes): elimina DISTINCT redundante | 2026-08-19 | No documentado | `35d6672` |
| feat(estudiantes): migra autenticación al correo real | 2026-08-19 | No documentado | `d62dde6` |
| fix(calificaciones): desempate por nombres alfabético | 2026-08-19 | No documentado | `d39ad68` |
| feat(grupos): conteo de carga de docente en selector | 2026-08-17 | php -l | `17f42aa` |
| fix(grupos): transacción PDO en eliminar_cohorte | 2026-08-17 | No documentado | `3efb73f` |
| fix(docentes): valida unicidad de doce_sigla | 2026-08-17 | mención genérica de verificación (sin método explícito) | `cfab7bf` |
| fix(grupos): corrige scope de periodoCodigoManual | 2026-08-17 | No documentado | `034d663` |
| feat(grupos): autogenerar grse_codigo | 2026-08-17 | No documentado | `04345ea` |
| feat(grupos): preseleccionar período activo | 2026-08-17 | No documentado | `624b44c` |
| fix(seguridad): guard de autorización por rol en listar_grupos | 2026-08-17 | curl | `2780dff` |
| fix: agrega 5 secciones faltantes al select de ayuda | 2026-08-16 | Navegador manual (Jose Luis) | `1edb887` |
| fix: refresco de matriculados + feat: cambiar clave estudiante | 2026-08-16 | mención genérica de verificación (sin método explícito) | `3d59f06` |
| feat: expande sidebar de ayuda a 5 módulos | 2026-08-16 | Navegador manual (Jose Luis) | `f7f0ae3` |
| feat: sidebar de ayuda con CRUD (10_ayudas) — 3 etapas | 2026-08-16 | curl | `fc9b56b, f801bdb, 8947710` |
| feat: filtros de profesor/programa/período en calificaciones | 2026-08-16 | No documentado | `6c496e4` |
| feat: texto de ayuda para siglas | 2026-08-16 | No documentado | `b489fa5` |
| feat: eliminar/desactivar docente + fix doce_activo | 2026-08-15 | mención genérica de verificación (sin método explícito) | `2dd4a55` |
| feat: período activo + conteo de grupos por docente | 2026-08-15 | No documentado | `7eba870` |
| feat: divide fila de contexto del Excel exportado | 2026-08-15 | No documentado | `7fe1151` |
| feat: eliminar/desactivar cohorte | 2026-08-15 | No documentado | `5611153` |
| fix: precarga módulo/docente al editar | 2026-08-15 | No documentado | `f46d9d9` |
| refactor: mueve jornada de cohorte a grupo semestre | 2026-08-15 | No documentado | `5bef1ef` |
| fix: valida sesión en listar_grupos (05_calificaciones) | 2026-08-15 | No documentado | `0eddadf` |
| feat: CRUD completo de módulos en 04_grupos | 2026-08-08 | Navegador manual (Jose Luis); curl | `6ef7e85, e7e6b3b, 0eca14e` |
| feat: eliminar aspirante desde modal Editar | 2026-08-08 | curl | `7d72327` |
| feat: nombre completo del módulo + inicial docente en Reportes | 2026-08-08 | No documentado | `75907a3` |
| fix: fija zona horaria America/Bogota en Docker y PDO | 2026-08-08 | No documentado | `7216637` |
| feat: Ficha Familiar obligatoria + indicador 3 estados | 2026-08-07 | curl | `1531593` |
| feat: contexto de curso/docente en reporte/Excel | 2026-08-07 | No documentado | `8ada1df` |
| feat: muestra origen (Manual/Web) en Aspirantes | 2026-08-07 | No documentado | `f704901` |
| feat: CRUD de períodos académicos | 2026-08-07 | No documentado | `35831fe` |
| fix: valida sesión y rol en doc_mdl/grupos_mdl/coordinador_mdl/admin_mdl | 2026-08-07 | No documentado | `54d4514` |
| refactor: ajustes de formularios en 02_estudiantes | 2026-08-07 | No documentado | `29b6ca6` |
| fix: valida sesión y rol en los 9 case de est_mdl.php | 2026-08-07 | No documentado | `920bcfe` |
| feat: Paso 2 del formulario público de inscripción | 2026-08-01 | mención genérica de verificación (sin método explícito) | `c60cb1b` |
| feat: Paso 1 del formulario público de inscripción | 2026-08-01 | curl | `b34143d` |
| feat: agrega estu_origen a estudiantes | 2026-07-27 | Docker (contenedor vivo) | `7702fe2` |
| feat: nombre del estudiante en modal Ficha + tamaño Oficio PDF | 2026-07-26 | No documentado | `dc18116` |
| feat: descarga en PDF de la Ficha Familiar (AC-FO-02) | 2026-07-26 | No documentado | `15021e0` |
| feat: agrega 6 campos faltantes AC-FO-02 | 2026-07-26 | No documentado | `8fe6c99` |
| feat: indicador rojo/verde de ficha completa | 2026-07-26 | No documentado | `2355ab1` |
| feat: carga y miniatura de foto del estudiante | 2026-07-26 | No documentado | `1b699f5` |
| feat: valida formato de correo en minúsculas | 2026-07-26 | No documentado | `67683ab` |
| fix: corrige validación select Acudiente | 2026-07-26 | No documentado | `e29b7b1` |
| feat: validación obligatoria de documento | 2026-07-26 | No documentado | `fe397ad` |
| chore: ignora recaptcha_config.php | 2026-07-25 | No documentado | `115c8ed` |
| docs: auditoría de compatibilidad PHP 8.1-8.5 | 2026-07-25 | No documentado | `590e64e` |
| fix: corrige hash del usuario admin en seed SQL | 2026-07-25 | mención genérica de verificación (sin método explícito) | `d406166` |
| migración del entorno local a Docker (Fedora 44) | 2026-07-25 | mención genérica de verificación (sin método explícito) | `d6169e6` |
| chore: elimina stubs muertos de Fase 0 | 2026-07-19 | No documentado | `be827c5` |
| fix: elimina SP y triggers obsoletos de calificaciones | 2026-07-19 | curl | `304127b` |
| fix: valida sesión, rol y ownership en guardar_nota | 2026-07-19 | curl | `75504eb` |
| fix: valida sesión, rol y ownership en listar_calificaciones | 2026-07-19 | curl | `04ec8b0` |
| botones de descarga PDF en 06_reportes | 2026-07-05 | mención genérica de verificación (sin método explícito) | `7755f7c` |
| nuevo endpoint pdf_boletin.php | 2026-07-05 | mención genérica de verificación (sin método explícito) | `b4f60f2` |
| nuevo endpoint pdf_grupo.php | 2026-07-05 | mención genérica de verificación (sin método explícito) | `906b219` |
| alineación de 06_reportes con Nota Final/Habilitación/Definitiva | 2026-07-05 | mención genérica de verificación (sin método explícito) | `fa6e685` |
| rediseño Nota Final/Habilitación/Definitiva | 2026-07-04 | mención genérica de verificación (sin método explícito) | `58396d1` |
| creación de .gitignore | 2026-07-04 | No documentado | `5fe5f1f` |
| instalación dompdf + .htaccess de producción | 2026-07-04 | No documentado | `a575cf0` |
| mejoras UX: mayúsculas + validación numérica calificaciones | 2026-05-07 | No documentado | `3aaf8c1` |
| fix: botón Guardar y Cerrar en modal editar grupo | 2026-05-07 | No documentado | `bc2587a` |
| navbar compartido para roles 1 y 2 | 2026-05-07 | No documentado | `f45420b` |
| Phase 2.3: módulo 07_coordinador | 2026-05-07 | No documentado | `e7b73e3` |
| Phase 2.2: módulo 06_reportes | 2026-05-07 | No documentado | `e8a06a8` |
| Sprint 2 iniciado — Módulo 05_calificaciones | 2026-05-05 | No documentado | `0.7.0` |
| Sprint 1 completado — Módulo 04_grupos | 2026-05-05 | No documentado | `0.6.0` |
| Sprint 1 — Módulo 02_estudiantes | 2026-05-01 | No documentado | `0.5.0` |
| Sprint 1 iniciado — Módulo 03_docentes + ajuste 08_admin | 2026-05-01 | No documentado | `0.4.0` |
| Sprint 0 completado — Infraestructura base | 2026-05-01 | No documentado | `0.3.0` |
| Sprint 0 preparación — Diseño y planificación | 2026-04-30 | No documentado | `0.2.0` |
| Propuesta aprobada | 2026-02-15 | mención genérica de verificación (sin método explícito) | `0.1.0` |

---

## 3. Ejemplos trazables destacados

### 3.1 — Verificación en 3 niveles combinando curl + Playwright + revisión humana (commit `f3c0ca4`, 2026-09-03)

> *"Verificado en 3 niveles: `curl` para los flujos de creación y
> edición del catálogo (payload simulado, confirmado con
> `listar_requisitos_programa` antes/después); Playwright headless
> (instalado puntualmente en esta sesión, sin quedar integrado como
> dependencia del proyecto) para confirmar visualmente que el select
> de la nueva pestaña solo lista programas activos, mediante la
> desactivación reversible de un programa real y su reactivación
> inmediata al terminar; y en navegador por Jose Luis, el ciclo
> completo agregar → editar → desactivar → reactivar → matricular un
> estudiante con esos requisitos ya asignados."*

Este es el ejemplo más explícito del repositorio de una estrategia
combinada — cada nivel cubre lo que los otros no pueden: `curl` valida
el contrato del endpoint (payload/respuesta), Playwright valida el
comportamiento visual reproducible sin depender de que un humano esté
disponible, y la revisión de Jose Luis valida la experiencia de
usuario end-to-end.

### 3.2 — Backend crítico probado con 10 casos vía curl contra Docker vivo (commit `183e9b1`, 2026-09-04)

> *"verificado 10/10 casos con curl contra Docker vivo (crear
> automática/manual/no-op, numerodoc duplicado, sin correo, cambiar
> clave automática/manual/vacío, sin sesión, estudiante inexistente),
> incluida verificación de hashes con `password_verify()` y
> confirmación de que no quedan filas huérfanas — conteo de usuarios
> role_id=4 confirmado igual antes/después (11)."*

Ejemplo de prueba de caja negra sistemática contra un endpoint que
maneja contraseñas: cubre casos felices, casos de error (duplicados,
datos faltantes, sesión ausente) y una verificación de efectos
secundarios en la base de datos (conteo antes/después), no solo el
código de respuesta HTTP.

### 3.3 — Prueba de seguridad activa (simulación de ataque), no solo verificación funcional (commit `0501479`, 2026-09-06)

Documentado en `PROJECT_CONTEXT.md` (líneas 750-756): *"login de
Coordinador/Admin sin regresión en `06_reportes`; docente descarga su
propio grupo en Excel y PDF, verificado [...] prueba simulando a un
docente real contra el grupo de otro docente."* — es decir, no solo se
verificó el camino feliz (un docente exporta su propio grupo), sino que
se intentó activamente que un docente accediera al grupo de **otro**
docente para confirmar que el control de *ownership* (`docentes.usua_id
= sesión`) lo bloquea. Esto excede una prueba funcional común y se
acerca a una prueba de control de acceso dirigida.

### 3.4 — Reintroducción de un bug real detectado solo por prueba funcional, no por inspección de código (commit `1531593`, 2026-08-07)

`PROJECT_CONTEXT.md:288`: *"Pruebas extremo a extremo + documentación
[...] pruebas detectaron hallazgo crítico (indicador binario de ficha
completa daba falso positivo para aspirantes web que abandonaban tras
el Paso 1); corregido con Ficha Familiar obligatoria + indicador de 3
estados."* Este es el caso más citable para argumentar valor de la
verificación funcional frente a un comité: el defecto (falso positivo
en un indicador de negocio) no era detectable por un test unitario de
una función aislada — solo emergió al recorrer el flujo completo con
datos reales de un aspirante que abandona a mitad de camino.

### 3.5 — Verificación de artefactos binarios (PDF) con herramienta externa, no solo inspección visual (commits `ef429bd` y `6abbdae`, 2026-08-30 y 2026-09-03)

`ef429bd`: boletín rediseñado, *"verificado generando 2 PDFs reales
(Aprobado y En Curso)"*. `6abbdae`: *"4 PDFs verificados con
`pdftotext`"* — extracción de texto del PDF generado por Dompdf para
confirmar que el contenido (no solo que el archivo se genera sin
excepción) es el esperado, algo que PHPUnit no podría verificar sin un
mecanismo adicional de comparación de binarios/renderizado.

---

## 4. Metodología aplicada (basada solo en lo documentado en el repositorio)

El proyecto declara **CDIO + Scrum** como metodología formal desde la
propuesta aprobada (commit `0.1.0`, `CHANGELOG.md`) y **CDIO** como
metodología de desarrollo en `README.md`. Sobre esa base, `CLAUDE.md`
("Ciclo de trabajo con Claude IA") y `PROJECT_CONTEXT.md` ("Flujo de
trabajo establecido") documentan un **ciclo operativo fijo de 6 fases
por tarea**: Diagnóstico (solo lectura, sin cambios) → Análisis (Claude
IA plantea opciones, Jose Luis decide) → Implementación (Claude Code
aplica cambios) → Pruebas (Claude IA sugiere qué probar, **Jose Luis
ejecuta en navegador y reporta resultados**) → Commit (Jose Luis
ejecuta desde su terminal tras revisar) → Documentación (commit
separado del código). La regla explícita "Nunca combinar diagnóstico y
modificación en un mismo prompt" y la exigencia de que **la fase de
Pruebas ocurra antes del Commit, con reporte humano de resultados**,
es la base documental que sostiene por qué la verificación de este
proyecto es predominantemente funcional (ejecutar el sistema real y
observar su comportamiento) y no basada en una suite de tests
automatizados de unidad.

No hay en `CLAUDE.md` ni en `PROJECT_CONTEXT.md` ninguna sección que
declare explícitamente "por qué no usamos PHPUnit" ni que compare
PHPUnit contra pruebas funcionales — esa justificación **no está
documentada en el repositorio** y tendría que argumentarse ante el
comité como una decisión derivada de las restricciones reales del
proyecto que sí están documentadas: (a) el stack declarado en
"Frontend stack" es deliberadamente "sin `package.json`, sin lockfile,
sin build step" — introducir PHPUnit habría sido la primera dependencia
de testing formal en un proyecto que optó explícitamente por no tener
tooling de build; (b) el ciclo de 6 fases pone la verificación en manos
de Jose Luis como único evaluador humano disponible (rol de
"XP customer" en un equipo de una sola persona), no de una suite
mantenida en paralelo al código; (c) la naturaleza del proyecto — un
sistema CRUD con lógica de negocio concentrada en consultas SQL
parametrizadas y flujos completos de UI (modales, DataTables, PDFs) —
hace que gran parte de los defectos reales documentados (ver ejemplo
3.4) se manifiesten solo en la integración end-to-end, no en unidades
de código aisladas testeables de forma económica con PHPUnit sin mocks
de sesión/PDO que el propio proyecto evita por convención (ver
"Nunca confiar en el estado del cliente..." en `CLAUDE.md`).

---

## Limitaciones de este informe

- El conteo de "features/módulos implementados" usa las 150 entradas
  de `CHANGELOG.md` como unidad, tal como pidió el prompt — esto
  **no** equivale 1:1 a los ~48 ítems de roadmap listados en la sección
  "Estado del roadmap" de `CLAUDE.md` (que agrupan varios commits por
  ítem); ambas unidades de conteo son válidas pero miden cosas
  distintas y no deben mezclarse en la sustentación sin aclarar cuál se
  está citando.
- La clasificación de "método de verificación" se hizo por
  coincidencia de palabras clave (`curl`, `Playwright`, `navegador`,
  `Docker`, `verificado`/`probado`, `php -l`, `pdftotext`, `grep`) sobre
  el texto de cada entrada de `CHANGELOG.md` — es una extracción
  automatizada fiel al texto documentado, no una re-verificación de que
  la prueba descrita efectivamente ocurrió como se narra.
- 62 de 150 entradas (41,3 %) no mencionan ningún método de
  verificación — esto se reporta como "No documentado", no como
  "sin verificar": es posible que algunas de esas tareas sí se
  probaran informalmente sin que quedara registrado en el mensaje de
  commit correspondiente, pero no hay evidencia trazable de ello en el
  repositorio.
