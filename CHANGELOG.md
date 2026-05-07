# CHANGELOG — app_academica_emdb
> Historial de cambios del Sistema de Gestión Académica EMDB
> Formato: [Semver] — Fecha | Orden: más reciente arriba

---

## [bc2587a] — 2026-05-07 — fix: botón Guardar y Cerrar en modal editar grupo

### Archivos modificados
- app/04_grupos/grupos_ctrl.js — agregado hide() del modal en bloque else (modo edición)
- app/04_grupos/grupos_view.php — texto botón cambiado de "Guardar Grupo" a "Guardar y Cerrar"

### Bug corregido
- bootstrap.Modal.getInstance('#mdl_grupo') fallaba silenciosamente — getInstance requiere elemento DOM, no selector string
- En modo edición el modal no se cerraba después de guardar
- Botón renombrado a "Guardar y Cerrar" para indicar el comportamiento esperado

---

## [f45420b] — 2026-05-07 — navbar compartido para roles 1 y 2 en todos los módulos

### Archivos modificados
- app/00_files/navbar.php — nuevo componente Bootstrap navbar-expand-lg para roles 1 y 2; ítem ⚙️ Usuarios condicional solo para role_id=1
- app/02_estudiantes/est_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/03_docentes/doc_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/04_grupos/grupos_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/05_calificaciones/calificaciones_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/06_reportes/reportes_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/07_coordinador/coordinador_view.php — reemplaza navbar inline por require_once navbar.php (condicional)
- app/08_admin/admin_view.php — reemplaza navbar inline por require_once navbar.php (directo, role 1 exclusivo)

### Decisiones
- Patrón condicional: `<?php if (in_array($role_id, [1, 2])): ?> require navbar.php <?php else: ?>` navbar simple con solo email y cerrar sesión
- href de Cerrar Sesión actualizado a ruta absoluta `/app_academica_emdb/app/01_login/logout.php` en vistas que usaban ruta relativa
- Docentes (role 3) y Estudiantes (role 4) mantienen navbar mínimo por diseño

### Pruebas realizadas
- Roles 1 y 2 ven navbar completo con todos los módulos ✅
- Role 3 (docente) ve navbar mínimo en calificaciones ✅
- Role 4 (estudiante) ve navbar mínimo en reportes ✅
- ⚙️ Usuarios solo visible para role 1 ✅

---

## [e7b73e3] — 2026-05-07 — Phase 2.3: módulo 07_coordinador + ajustes 05_calificaciones

### Archivos modificados
- app/07_coordinador/coordinador_mdl.php — acción resumen_dashboard (conteos + estado notas por grupo)
- app/07_coordinador/coordinador_view.php — 3 tarjetas resumen + tabla estado notas con DataTables
- app/07_coordinador/coordinador_ctrl.js — cargarDashboard, construirFilaEstado, botón Ver Notas → 05_calificaciones?grmo_id=X
- app/05_calificaciones/calificaciones_ctrl.js — semáforo rojo/amarillo/verde por rango, foco en error, preselección por ?grmo_id=
- app/05_calificaciones/calificaciones_view.php — clases CSS semaforo-rojo/amarillo/verde

### Decisiones
- Dashboard solo roles 1 y 2
- Semáforo: rojo 0.0-2.9, amarillo 3.0-3.9, verde 4.0-5.0 (reglamento institucional GA-FO-04)
- Botón Ver Notas en dashboard pasa grmo_id por GET a calificaciones_view.php
- Al recibir ?grmo_id, calificaciones_ctrl.js simula click en la card correspondiente
- Error de rango: alert + input.val('') + focus devuelto al campo erróneo

### Pruebas realizadas
- Dashboard carga conteos y tabla de estado correctamente ✅
- Botón Ver Notas abre calificaciones con grupo preseleccionado ✅
- Semáforo aplica colores al cargar y al guardar cada nota ✅
- Error fuera de rango devuelve foco al campo con campo vacío ✅

---

## [e8a06a8] — 2026-05-07 — Phase 2.2: módulo 06_reportes

### Archivos modificados
- app/06_reportes/reportes_mdl.php — 4 acciones: grupos_para_reporte, reporte_grupo, mis_modulos, mis_notas
- app/06_reportes/reportes_view.php — vista bifurcada por rol (role 4: mis notas; roles 1-2: reporte por grupo)
- app/06_reportes/reportes_ctrl.js — cargarModulos, cargarGrupos, construirFilaEstudiante, construirFilaReporte

### Decisiones
- Vista única con bifurcación PHP según role_id (no dos archivos separados)
- Exportación Excel nativa DataTables Buttons (sin librería adicional)
- PDF queda pendiente para después del 12 de mayo
- CDNs de Buttons/JSZip cargados condicionalmente solo para roles 1 y 2
- Exportación Excel funcional para roles 1 y 2 (botón DataTables Buttons) ✅
- Exportación PDF pendiente (ítem 2.4): GA-FO-04 por módulo (coordinador) y boletín individual (estudiante)

### Pruebas realizadas
- Role 4: select módulo → notas con badge definitiva y estado ✅
- Role 1/2: select grupo → tabla con exportación Excel ✅

---

## [Unreleased]
- Phase 2.4 — módulo 06_reportes — exportación PDF (GA-FO-04 coordinador + boletín estudiante)
- Phase 3 — validación TRL5: migración datos, pruebas usuarios reales, escala SUS, video demostración

---

## [0.7.0] — 2026-05-05
### Sprint 2 iniciado — Módulo 05_calificaciones

#### Phase 2.1 — Módulo calificaciones (commit: 76f2c3e)
- `app/05_calificaciones/calificaciones_view.php`: panel izquierdo con cards de módulos, planilla inline con columnas N1/Sup N1/N2/Sup N2/N3/N4/Sup N4/Definitiva
- `app/05_calificaciones/calificaciones_mdl.php`: 3 acciones — listar_grupos (filtrado por rol docente vs coordinador/admin), listar_calificaciones (LEFT JOIN para mostrar estudiantes sin notas), guardar_nota (autosave por campo, whitelist, normalización coma→punto, cálculo definitiva en PHP)
- `app/05_calificaciones/calificaciones_ctrl.js`: autosave on blur, normalización coma→punto y entero→decimal, supletorios dinámicos (N3 excluido), definitiva en tiempo real, inputs type=text con select() al focus
- Triggers y SP eliminados de BD — cálculo definitiva migrado a PHP por limitación MySQL (no permite UPDATE en trigger de la misma tabla)

---

## [0.6.0] — 2026-05-05
### Sprint 1 completado — Módulo 04_grupos

#### Phase 1.3 — Módulo grupos (commits: 4dc841a, 1a96050)
- DDL rediseñado: `grseestudiantes` eliminada, reemplazada por `grmoestudiantes` (vínculo estudiante→módulo específico)
- DDL nuevo: tabla `programa_modulos` — semestre sugerido por módulo según programa, flexible al armar grupos
- DDL ampliado: `gruposemestres` con `coho_id` FK, `gruposmodulos` con `grmo_horario`, `fechainicio`, `fechafin`
- DDL ampliado: `cohortes` con `coho_jornada` (Semana/Sabados), `programas` con `prog_vigencia`
- `app/04_grupos/grupos_view.php`: 3 pestañas — Cohortes, Grupos Semestre, Asignación Estudiantes con panel dual
- `app/04_grupos/grupos_mdl.php`: 17 acciones — gestión completa de cohortes, grupos, módulos y asignación masiva de estudiantes
- `app/04_grupos/grupos_ctrl.js`: DataTables para cohortes y grupos, panel dual con checkboxes, filtros, asignar/retirar estudiantes

---

## [0.5.0] — 2026-05-01
### Sprint 1 — Módulo 02_estudiantes

#### Phase 1.2 — Módulo estudiantes (commits: dc792e2, 724dc68)
- DDL ampliado: tabla `estudiantes` expandida a 24 campos (AC-FO-02 completo)
- DDL ampliado: tabla `matriculas` con 9 flags de requisitos documentales (AC-FO-09)
- DDL ampliado: tabla `fichas_inscripcion` nueva — datos familiares, estudios anteriores, código temporal
- DDL ampliado: campo `usua_login` en `usuarios` — credencial por número de documento
- `app/02_estudiantes/est_view.php`: dos pestañas (Aspirantes / Matriculados), modal nuevo aspirante, modal matricular con creación de usuario
- `app/02_estudiantes/est_mdl.php`: 8 acciones — listar_aspirantes, listar_matriculados, listar_programas, listar_periodos, listar_cohortes, guardar, obtener, matricular
- `app/02_estudiantes/est_ctrl.js`: dos DataTables, cargar dropdowns, abrirMatricular() y abrirEditar() globales, flujo clave automática/manual

---

## [0.4.0] — 2026-05-01
### Sprint 1 iniciado — Módulo 03_docentes + ajuste 08_admin

#### Phase 1.1 — Módulo docentes (commit: 5dfd52b)
- `app/03_docentes/doc_view.php`: vista con navbar, DataTables, modal único Nuevo/Editar
- `app/03_docentes/doc_mdl.php`: acciones listar, guardar (INSERT+UPDATE en transacción PDO), obtener — verificación email duplicado antes de beginTransaction
- `app/03_docentes/doc_ctrl.js`: DataTables ajax, abrirEditar() global, usua_email readonly en edición, validarFormulario() reutilizable
- `app/08_admin/admin_mdl.php`: listar y listar_roles filtrados a role_id IN (1,2) — admin solo gestiona administradores y coordinadores

---

## [0.3.0] — 2026-05-01
### Sprint 0 completado — Infraestructura base

#### Phase 0.5 — Módulo admin (commit: 5fc9f4a)
- `app/08_admin/admin_view.php`: panel con navbar, DataTables 1.13, modales Nuevo/Editar usuario
- `app/08_admin/admin_mdl.php`: acciones listar, listar_roles, crear, editar, obtener — todas con envelope `['status'=>'ok/error', ...]`
- `app/08_admin/admin_ctrl.js`: DataTables ajax, CRUD via $.ajax, bridge select↔hidden, `abrirEditar()` declarada global para compatibilidad con render inline de DataTables
- `app/01_login/logout.php`: session_destroy + redirect a login_view.php — centralizado para que todos los navbars apunten al mismo archivo

#### Phase 0.3-0.4 — Login + estructura MVC (commit: c2fdcdc)
- `app/00_connect/pdo.php`: singleton PDO con ERRMODE_EXCEPTION, FETCH_ASSOC, EMULATE_PREPARES false
- `app/01_login/login_view.php`: formulario Bootstrap 5.3 sin selector de rol, alerta de error condicional
- `app/01_login/login_mdl.php`: autenticación con prepared statements + password_verify, cast `(int)` en role_id de sesión para compatibilidad con comparación estricta `!==`
- `app/01_login/check_session.php`: guard que protege todas las vistas, session_start condicional
- `index.php`: redirect a login
- Stubs `_view.php`, `_mdl.php`, `_ctrl.js` creados para módulos 02–08

#### Phase 0.1-0.2 — Repositorio y base de datos (commit: fa4b330)
- Repositorio GitHub creado: `solartejoseluis/app_academica_emdb` (público)
- Alias bash `academica` configurado en WSL Ubuntu → `cd /mnt/c/xampp/htdocs/app_academica_emdb`
- BD `emdb_academica` creada en XAMPP MySQL 8.0
- 14 tablas con FK, convenciones de nomenclatura (prefijo 4 letras, minúsculas, plural)
- Seeds: 4 roles, 2 programas (ASO + MD), 36 módulos (17 ASO + 19 MD), 3 períodos, 1 usuario admin
- Stored procedure `sp_calcular_definitiva` + triggers AFTER INSERT/UPDATE en `calificaciones`
- Archivos de documentación en raíz: CLAUDE.md, README.md, CHANGELOG.md, PROJECT_CONTEXT.md

---

## [0.2.0] — 2026-04-30
### Sprint 0 preparación — Diseño y planificación

- Documento maestro Actividad_4_consolidado_v04.docx completado
- Sección §8.2.3 Implementación Scrum insertada (7 subsecciones, 5 tablas APA7)
- Numeración de secciones corregida: §6 Objetivos, §7 Marco Referencial, §8.1.x, §8.2.x, §11.2.x
- §14. Referencias renumerada (era segundo §13)
- Wireframes corregidos: Ilustraciones 3–6 restauradas desde v02
- Anexo 2 eliminado del cuerpo y del índice
- DDL `database/emdb_academica.sql` generado (538 líneas, 14 tablas, stored procedure, triggers)
- Product Backlog definido: 15 Historias de Usuario con Story Points y Sprint asignado
- 3 formularios Google Forms aplicados: coordinadora (n=1), docentes (n=5), estudiantes (n=25 pendiente)

---

## [0.1.0] — 2026-02-15
### Propuesta aprobada

- Propuesta de Proyecto Aplicado aprobada en modalidad individual
- Título: *"Prototipo de Software para la Automatización de Procesos de Inscripción, Matrícula y Registro de Calificaciones mediante Metodología CDIO en el Contexto del ODS 4"*
- Institución beneficiaria: Escuela de Mecánica Dental Bolaños (EMDB), Tuluá
- Stack definido: PHP 8.0 + MySQL 8.0 + JavaScript ES6 + Bootstrap 5.3 + jQuery 3.7 + DataTables 1.13
- Metodología: CDIO + Scrum. Meta de validación: TRL 5
- Tutor: Daniel Andrés Guzmán Arevalo. Director: Rubén Darío Ordóñez
