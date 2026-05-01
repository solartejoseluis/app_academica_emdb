# PROJECT_CONTEXT.md — app_academica_emdb
> Archivo de contexto para Claude IA. Pegar al inicio de cada nuevo chat.
> Última actualización: 2026-04-30
> Versión: 2 — actualizado al cierre del chat de arranque del proyecto

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
- Repositorio GitHub: `app_academica_emdb` — **por crear (Phase 0.1 pendiente)**

---

## Documentación base del proyecto (generada en este chat)

| Archivo | Propósito | Estado |
|---|---|---|
| `CLAUDE.md` | Convenciones de código para Claude Code (605 líneas) | ✅ Generado |
| `README.md` | Descripción del sistema para humanos (237 líneas) | ✅ Generado |
| `CHANGELOG.md` | Historial de cambios — estructura lista | ✅ Generado |
| `PROJECT_CONTEXT.md` | Este archivo — contexto para Claude IA | ✅ Este archivo |

Los 4 archivos están descargados y listos para poner en la raíz del repositorio GitHub.

---

## Documento maestro académico (UNAD)

| Versión | Contenido | Estado |
|---|---|---|
| v01 (Fase 3) | Documento base entregado | ✅ Calificado 75/100 |
| v02 (Fase 4) | Delimitación §5, OE simplificados, Resultados §12, 21 refs | ✅ |
| v03 (Fase 4) | + Análisis diagnóstico §7.1.4 + 4 diagramas UML en §11.1 | ✅ Listo |
| v04 | + Análisis estudiantes §7.1.4.3 + §7.1.4.4 completo | ⬜ Pendiente |

**Archivo actual:** `Actividad_4_consolidado_v03.docx`
**Entrega Fase 4:** 12 de mayo de 2026
**Secciones pendientes en doc:** §7.1.4.3 estudiantes, §7.1.4.4 diagnóstico completo,
script DDL en §11, matriz de trazabilidad

---

## Diagramas UML generados e insertados en §11.1

| Diagrama | Evidencia para | Estado |
|---|---|---|
| Casos de uso (4 actores, 18 UC) | OE2.1 | ✅ En documento v03 |
| Clases (12 entidades, relaciones) | OE2.1 | ✅ En documento v03 |
| Secuencia (registro calificaciones) | OE2.1 | ✅ En documento v03 |
| Arquitectura MVC + despliegue | OE2.2 | ✅ En documento v03 |
| Entidad-Relación (mermaid) | OE2.1 + OE2.3 | ✅ Generado (pendiente insertar) |

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
| 1 | Administrador | `08_admin` |
| 2 | Coordinador | `07_coordinador` |
| 3 | Docente | `05_calificaciones` |
| 4 | Estudiante | `06_reportes` |

**Login:** sin selector de rol en pantalla. El sistema detecta el rol por la BD.

---

## Estructura de módulos definida

```
app_academica_emdb/
  app/
    00_connect/        — pdo.php (local) / pdo_web.php (producción)
    00_selects/        — SELECTs reutilizables para dropdowns
    00_img/            — logo, iconos
    01_login/          — Autenticación + check_session.php
    02_estudiantes/    — CRUD estudiantes + matrícula
    03_docentes/       — CRUD docentes
    04_grupos/         — Cohortes, grupos semestre, grupos módulo
    05_calificaciones/ — Registro notas por docente (módulo crítico)
    06_reportes/       — Consulta estudiante + PDF/Excel
    07_coordinador/    — Dashboard seguimiento
    08_admin/          — Gestión usuarios
  CLAUDE.md / README.md / CHANGELOG.md / PROJECT_CONTEXT.md
```

---

## Tablas de la BD (orden de creación)

```
1. roles              — role_id, role_nombre
2. programas          — prog_id, prog_nombre, prog_sigla, prog_resolucion
3. cohortes           — coho_id, coho_codigo, fechainicio
4. periodos           — peri_id, peri_codigo, peri_anio, peri_semestre
5. usuarios           — usua_id, role_id(FK), usua_email, usua_passwordhash
6. docentes           — doce_id, usua_id(FK), doce_nombres, doce_sigla
7. estudiantes        — estu_id, usua_id(FK), coho_id(FK), estu_nombres
8. modulos            — modu_id, prog_id(FK), modu_nombre, modu_sigla
9. matriculas         — matr_id, estu_id(FK), prog_id(FK), matr_estado
10. gruposemestres    — grse_id, prog_id(FK), grse_codigo, fechainicio
11. grseestudiantes   — grse_id(FK), estu_id(FK)  [tabla puente N:M]
12. gruposmodulos     — grmo_id, grse_id(FK), modu_id(FK), doce_id(FK)
13. calificaciones    — cali_id, grmo_id(FK), estu_id(FK), notas N1-N4,
                        supletorios (N1,N2,N4), definitiva
14. horariosgrupo     — hora_id, grse_id(FK), hora_diasemana, hora_horainicio
```

Script DDL pendiente de generar: `database/emdb_academica.sql`

---

## Estado diagnóstico (OE1) — encuestas aplicadas 29-abr-2026

### Coordinadora (n=1) — ✅ Aplicada y analizada en §7.1.4.1

| Indicador | Estimado | Confirmado |
|---|---|---|
| Horas config. planillas/semestre | 12h | 10-14h ✅ |
| Horas verificación notas/semana | 6h | 2-4h |
| Horas generación reporte | 4-6h | <2h |
| Frecuencia errores (1-5) | Alta | 3/5 ocasional |
| Necesidad sistema centralizado | Alta | 4/5 |
| Participación validación TRL5 | — | ✅ Confirmada verbalmente |

### Docentes (n=5) — ✅ Aplicada y analizada en §7.1.4.2

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
| 8 | Diagnóstico consolidado | 🔄 | §7.1.4.4 completo tras estudiantes |

### OE2 — DISEÑAR (Sprint 0)

| # | Actividad | Estado | Pendiente |
|---|---|---|---|
| 1 | Modelado UML (4 diagramas) | ✅ En doc v03 | — |
| 2 | Arquitectura MVC + despliegue | ✅ En doc v03 | — |
| 3 | BD MySQL normalizada + DDL | ⚠️ | Script `emdb_academica.sql` |
| 4 | Wireframes baja fidelidad | ✅ En doc | Acta validación coordinadora |
| 5 | Mockups media fidelidad | ⬜ | Capturas sistema real |
| 6 | Matriz de trazabilidad req→comp | ⬜ | Generar tabla |

### OE3 — IMPLEMENTAR (Sprint 1 + Sprint 2)

**Sprint 1 — Phase 0 + Phase 1 (~1-9 mayo)**

| Item | Descripción | Estado |
|---|---|---|
| 0.1 | Repositorio GitHub + estructura + 4 archivos doc | ⬜ |
| 0.2 | Script DDL + BD `emdb_academica` creada | ⬜ |
| 0.3 | Seeds: roles, programas, módulos ASO y MD | ⬜ |
| 0.4 | Módulo `01_login` con bcrypt + check_session | ⬜ |
| 0.5 | CRUD `08_admin` — gestión usuarios | ⬜ |
| 1.1 | Módulo `03_docentes` — CRUD | ⬜ |
| 1.2 | Módulo `02_estudiantes` — CRUD + matrícula | ⬜ |
| 1.3 | Módulo `04_grupos` — cohortes, grupos | ⬜ |

**Sprint 2 — Phase 2 (~9-11 mayo)**

| Item | Descripción | Estado |
|---|---|---|
| 2.1 | Módulo `05_calificaciones` — registro notas GA-FO-04 | ⬜ |
| 2.2 | Módulo `06_reportes` — consulta + PDF/Excel | ⬜ |
| 2.3 | Módulo `07_coordinador` — dashboard | ⬜ |

**Restricción real:** 2h/noche × ~12 días hábiles restantes ≈ 24h productivas

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
| Documento maestro completo | 40 pts | 🔄 v03 listo, pendiente completar |
| Participación crítica en foro | 10 pts | ⚠️ Consulta enviada al tutor |
| Prototipo funcional TRL5 en GitHub + video | 100 pts | ⬜ No iniciado |
| **Total** | **150 pts** | |

---

## Próximos pasos inmediatos (en orden de prioridad)

1. ⬜ Aplicar encuesta estudiantes n=25 (visita presencial EMDB)
2. ⬜ Completar §7.1.4.3 y §7.1.4.4 con análisis cruzado → doc v04
3. ⬜ Generar script DDL `emdb_academica.sql` completo
4. ⬜ Crear repositorio GitHub `app_academica_emdb`
5. ⬜ Primer commit: estructura + 4 archivos documentación → Phase 0.1 ✅
6. ⬜ Primer prompt para Claude Code: Phase 0.2-0.4 (DDL + login)
7. ⬜ Matriz de trazabilidad requerimientos → componentes

---

## Decisiones tomadas (no reabrir)

| Decisión | Detalle |
|---|---|
| Login sin selector de rol | El sistema detecta el rol automáticamente por BD |
| Aspirante y Estudiante misma tabla | `matriculas.matr_estado` maneja el ciclo — revisar en TRL6 |
| `DECIMAL(3,1)` para notas | Escala 0.0-5.0 colombiana — no cambiar a DECIMAL(5,2) |
| N3 sin supletorio | Regla institucional invariable |
| 3 formularios separados por rol | Coordinadora, docentes y estudiantes — no uno con lógica condicional |
| Opción C para el prototipo | Sistema completo para rol Coordinador como núcleo, luego docente y estudiante |

---

## Flujo de trabajo establecido

```
Claude IA (este archivo)     Claude Code          Jose Luis
─────────────────────────────────────────────────────────────
Analiza contexto         →
Genera prompt            →   Ejecuta en código
                         ←   Produce archivos/commits
Analiza resultado        →
Actualiza PROJECT_CONTEXT →
Genera siguiente prompt  →   Ejecuta siguiente tarea
```

**Regla:** Claude Code solo ejecuta. El análisis, la secuencia y los prompts
los genera Claude IA en el chat. Nunca pedirle a Claude Code que analice.

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

