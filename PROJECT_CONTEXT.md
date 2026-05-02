# PROJECT_CONTEXT.md — app_academica_emdb
> Archivo de contexto para Claude IA. Pegar al inicio de cada nuevo chat.
> Última actualización: 2026-05-01
> Versión: 4 — actualizado al cierre del Sprint 0

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

- PHP 8.0 + MySQL 8.0 + JavaScript ES6 + Bootstrap 5.3 + jQuery 3.7 + DataTables 1.13
- Arquitectura MVC — 3 archivos por módulo: `_view.php` / `_mdl.php` / `_ctrl.js`
- Servidor local: XAMPP. BD: `emdb_academica`
- Generación PDF: dompdf (Composer). Control versiones: Git + GitHub
- Repositorio GitHub: `https://github.com/solartejoseluis/app_academica_emdb` (público)
- Acceso local: `http://localhost/app_academica_emdb`
- Alias WSL: `academica` → `cd /mnt/c/xampp/htdocs/app_academica_emdb`

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
- Formularios: inscripción `AC-FO-02`, matrícula `AC-FO-09`, notas `GA-FO-04`
- Escala: 0.0 a 5.0 con un decimal — `DECIMAL(3,1)` en BD
- Programas: ASO (17 módulos) y MD (19 módulos)

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

---

## Tablas de la BD (14 tablas — todas creadas)

```
1.  roles              — role_id, role_nombre
2.  programas          — prog_id, prog_nombre, prog_sigla, prog_resolucion
3.  periodos           — peri_id, peri_codigo, peri_anio, peri_semestre
4.  usuarios           — usua_id, role_id(FK), usua_email, usua_passwordhash, usua_activo
5.  cohortes           — coho_id, prog_id(FK), coho_codigo, fechainicio
6.  docentes           — doce_id, usua_id(FK), doce_nombres, doce_apellidos, doce_sigla
7.  estudiantes        — estu_id, usua_id(FK), coho_id(FK), estu_nombres, estu_apellidos
8.  modulos            — modu_id, prog_id(FK), modu_nombre, modu_sigla, modu_orden
9.  matriculas         — matr_id, estu_id(FK), prog_id(FK), peri_id(FK), matr_estado
10. gruposemestres     — grse_id, prog_id(FK), peri_id(FK), grse_codigo, grse_semestre
11. grseestudiantes    — grse_id(FK), estu_id(FK)  [tabla puente N:M]
12. gruposmodulos      — grmo_id, grse_id(FK), modu_id(FK), doce_id(FK)
13. calificaciones     — cali_id, grmo_id(FK), estu_id(FK), N1-N4, sup_N1/N2/N4, definitiva
14. horariosgrupo      — hora_id, grse_id(FK), hora_diasemana, hora_horainicio
```

Seeds cargados: 4 roles, 2 programas, 3 períodos, 36 módulos (17 ASO + 19 MD), 1 usuario admin.
Stored procedure: `sp_calcular_definitiva` + triggers AFTER INSERT/UPDATE en calificaciones.

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
| 1.3 | Módulo `04_grupos` — cohortes, grupos | ⬜ |

**Sprint 2 — Núcleo académico**

| Item | Descripción | Estado |
|---|---|---|
| 2.1 | Módulo `05_calificaciones` — registro notas GA-FO-04 | ⬜ |
| 2.2 | Módulo `06_reportes` — consulta + PDF/Excel | ⬜ |
| 2.3 | Módulo `07_coordinador` — dashboard | ⬜ |

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
| Credencial login estudiantes | usua_login = número de documento. Sin correo institucional por ahora — implementación futura. Clave generada automáticamente: 4 letras apellido + año nacimiento. |

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
