# CHANGELOG — app_academica_emdb
> Historial de cambios del Sistema de Gestión Académica EMDB
> Formato: [Semver] — Fecha | Orden: más reciente arriba

---

## [be827c5] — 2026-07-19 — chore: elimina stubs muertos de Fase 0 y normaliza fin de línea de .gitignore

### Archivos modificados
- app/02_estudiantes/estudiantes_view.php — eliminado (stub sin uso desde Fase 0)
- app/02_estudiantes/estudiantes_mdl.php — eliminado (stub sin uso desde Fase 0)
- app/02_estudiantes/estudiantes_ctrl.js — eliminado (stub sin uso desde Fase 0)
- app/03_docentes/docentes_view.php — eliminado (stub sin uso desde Fase 0)
- app/03_docentes/docentes_mdl.php — eliminado (stub sin uso desde Fase 0)
- app/03_docentes/docentes_ctrl.js — eliminado (stub sin uso desde Fase 0)
- .gitignore — normalización de fin de línea (CRLF → LF), sin cambio de contenido

### Decisiones
- Los 6 archivos eliminados eran placeholders de Fase 0 ("Pendiente de implementación") en `02_estudiantes` y `03_docentes`, nunca reemplazados cuando se crearon los archivos reales en uso (`est_view.php`/`est_mdl.php`/`est_ctrl.js` y `doc_view.php`/`doc_mdl.php`/`doc_ctrl.js`) — quedaron como código muerto desde Phase 1.1/1.2
- Antes de borrar se verificó por grep en todo el repo (`.php`, `.js`, `.html`) que ningún archivo referenciaba los nombres stub, y que `navbar.php` apunta correctamente a los archivos reales (`est_view.php`, `doc_view.php`)
- El cambio en `.gitignore` es solo normalización de fin de línea (CRLF → LF) — el contenido (2 entradas: `vendor/` y `.claude/settings.local.json`) no cambió

### Pruebas realizadas
- `grep -rn` de los 6 nombres stub en todo el repositorio antes de borrar: cero coincidencias ✅
- Confirmado que `est_view.php`, `est_mdl.php`, `est_ctrl.js`, `doc_view.php`, `doc_mdl.php`, `doc_ctrl.js` no fueron tocados ✅
- `git diff -- .gitignore` revisado con `cat -A`: única diferencia es `^M$` (CRLF) vs `$` (LF), mismo contenido de texto ✅

---

## [304127b] — 2026-07-19 — fix: elimina SP y triggers obsoletos de calificaciones en el DDL

### Archivos modificados
- database/emdb_academica.sql — BLOQUE 7 (stored procedure `sp_calcular_definitiva`) y BLOQUE 8 (triggers `trg_calificaciones_after_update`/`trg_calificaciones_after_insert`) eliminados; reemplazados por un comentario que explica el motivo (MySQL no permite que un trigger ejecute `UPDATE` sobre la misma tabla que lo disparó, ni directamente ni vía un procedure invocado desde el trigger) y remite al cálculo real en `app/05_calificaciones/calificaciones_mdl.php`, case `guardar_nota`. Referencia residual `CALL sp_calcular_definitiva(1);` en el bloque de verificación manual también eliminada. Numeración del resto de bloques (incluido `BLOQUE 9: DATOS SEMILLA`) sin cambios.

### Decisiones
- El SP y los triggers eran obsoletos desde el commit `58396d1` (2026-07-04) — el cálculo de `cali_nota_final`/`cali_definitiva` ya vivía en PHP desde entonces. El script `.sql` versionado no reflejaba ese cambio: una reinstalación limpia de la BD con el SP/triggers activos fallaba al guardar la primera nota, con el error de MySQL "Can't update table ... in stored function/trigger because it is already used by statement which invoked this stored function/trigger".
- Cambio acotado únicamente al `.sql` — no se tocó `calificaciones_mdl.php` en este commit (su lógica ya era correcta desde el rediseño de julio).
- Antes de eliminar el SP se verificó por revisión de código que la fórmula y las reglas de supletorio en PHP (N1/N2/N4 con supletorio solo si la nota original = 0.0, N3 nunca) replican exactamente la lógica que tenía `sp_calcular_definitiva` — no se perdió ninguna regla de negocio al retirarlo.

### Pruebas realizadas
- Revisión de código: fórmula y reglas de supletorio comparadas línea por línea entre el SP eliminado y `calificaciones_mdl.php` ✅
- Diff completo del `.sql` revisado — ninguna otra sección (CREATE TABLE, seeds) modificada ✅
- Validación end-to-end indirecta: los scripts curl temporales de los commits `75504eb` y `04ec8b0` (login + pruebas a/b/c) ejecutaron INSERT/UPDATE reales sobre la tabla `calificaciones` de la BD local ya corregida, sin el error de MySQL que producía el SP/trigger — confirma que el DDL corregido permite guardar notas correctamente ✅

---

## [75504eb] — 2026-07-19 — fix: valida sesión, rol y ownership en guardar_nota

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php — case `guardar_nota`: agregada validación de sesión activa, rol y ownership del `grmo_id` al inicio del case, antes de cualquier escritura en `calificaciones`

### Decisiones
- Criterio de sesión activa idéntico al de `check_session.php`: `isset($_SESSION['usua_id'])` — sin sesión, responde `{"status":"error","message":"Sesión no válida"}`
- Docente (`role_id === 3`): ownership del `grmo_id` validado con el mismo join ya usado en `listar_grupos` (`gruposmodulos.doce_id → docentes.usua_id`) — si el grupo no le pertenece, responde `{"status":"error","message":"No autorizado para este grupo"}` y no escribe nada
- Coordinador/Admin (`role_id` 1 o 2): sin restricción de ownership, igual que en `listar_grupos`
- Cualquier otro rol (ej. `role_id = 4`, estudiante): rechazado con `{"status":"error","message":"Sin autorización"}`, mismo mensaje que ya usa `reportes_mdl.php` para el mismo caso
- Cierra el ítem de deuda técnica documentado en CLAUDE.md: `guardar_nota` no validaba sesión/rol antes de escribir en BD

### Pruebas realizadas
- Verificación por script curl temporal (`/tmp/test_guardar_nota.sh`, borrado al terminar): login real contra la BD local + 3 pruebas automatizadas — (a) docente guardando en su grupo propio → PASA; (b) mismo docente con `grmo_id` no autorizado → PASA (mensaje exacto "No autorizado para este grupo"); (c) sin cookies de sesión → PASA (mensaje exacto "Sesión no válida") ✅
- Valor de prueba escrito en `cali_n1` durante la prueba (a) restaurado manualmente a su valor original tras la verificación ✅

---

## [04ec8b0] — 2026-07-19 — fix: valida sesión, rol y ownership en listar_calificaciones

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php — case `listar_calificaciones`: agregada la misma validación de sesión, rol y ownership aplicada en `guardar_nota` (commit `75504eb`), al inicio del case, sin cambiar la estructura de datos que devuelve la consulta cuando es válida

### Decisiones
- Mismo criterio exacto que `guardar_nota`: sesión activa vía `isset($_SESSION['usua_id'])`, ownership de docente vía join `gruposmodulos.doce_id → docentes.usua_id`, roles 1/2 sin restricción, cualquier otro rol rechazado con "Sin autorización"
- Hallazgo detectado como efecto colateral al revisar `guardar_nota`: `listar_calificaciones` exponía la planilla de notas de cualquier `grmo_id` (incluyendo notas de otros docentes) sin ninguna validación de sesión ni rol
- Con este commit, los 3 cases de `calificaciones_mdl.php` (`listar_grupos`, `guardar_nota`, `listar_calificaciones`) quedan cubiertos por la misma capa de validación

### Pruebas realizadas
- Verificación por script curl temporal (`/tmp/test_listar_calificaciones.sh`, borrado al terminar): login real contra la BD local + 3 pruebas automatizadas — (a) docente consultando su grupo propio → PASA (`"status":"ok"` con datos); (b) mismo docente con `grmo_id` no autorizado → PASA (mensaje exacto "No autorizado para este grupo"); (c) sin cookies de sesión → PASA (mensaje exacto "Sesión no válida") ✅
- Prueba de solo lectura — sin escritura en BD, sin necesidad de restaurar datos ✅

---

## [7755f7c] — 2026-07-05 — botones de descarga PDF en 06_reportes — cierra ítem 2.4

### Archivos modificados
- app/06_reportes/reportes_view.php — bloque `info_grupo` (vista estudiante) ampliado con botón "Descargar Boletín PDF", visible solo cuando ya hay notas cargadas
- app/06_reportes/reportes_ctrl.js — botón "Descargar PDF" agregado al arreglo `buttons` de DataTables (junto a `'excel'`) en la vista de coordinador; handler delegado para el botón de boletín del estudiante, ambos con `window.open(url, '_blank')`

### Decisiones
- Botón del coordinador integrado directamente en la configuración de DataTables Buttons (no un botón HTML estático) — así hereda el mismo criterio de visibilidad que el botón de Excel: solo existe cuando la tabla se inicializa con datos
- Botón del estudiante usa un atributo `data-grmo` en el propio botón para no depender de leer el `<select>` en el momento del clic
- Sin librería de íconos nueva — se usó el emoji 📄 como texto, igual que el resto del proyecto (sin Bootstrap Icons ni Font Awesome)
- Con este commit queda completo el ítem 2.4 del roadmap (exportación PDF: reporte de grupo + boletín individual)

### Pruebas realizadas
- Botón de boletín (estudiante) aparece solo tras cargar notas de un módulo válido, y descarga el PDF correcto en nueva pestaña ✅
- Botón PDF (coordinador) aparece junto al de Excel solo tras cargar un reporte con datos, y descarga el PDF del grupo correcto en nueva pestaña ✅
- Cambiar de módulo/grupo y volver a cargar actualiza correctamente el `grmo_id` usado por cada botón ✅
- URLs de descarga verificadas sin sesión activa — redirigen a login, no exponen PDF ✅

---

## [b4f60f2] — 2026-07-05 — nuevo endpoint pdf_boletin.php — boletín individual PDF (estudiante)

### Archivos modificados
- app/06_reportes/pdf_boletin.php — archivo nuevo: genera el boletín de calificaciones de un estudiante en un módulo, en PDF

### Decisiones
- Acceso restringido a `role_id === 4` únicamente
- Mismo filtro de seguridad que la acción `mis_notas` de `reportes_mdl.php`: `WHERE ge.grmo_id = ? AND est.usua_id = ?` — si el `grmo_id` no existe o pertenece a otro estudiante, la query no devuelve fila en ambos casos, indistinguibles entre sí
- Responde **403 genérico** ("No autorizado") cuando no hay fila — no revela si el `grmo_id` existe o no para otro estudiante
- Formato de página `portrait` (a diferencia de `pdf_grupo.php`, en `landscape`) — al ser una sola fila de datos no necesita el ancho de una tabla de grupo
- Reutiliza el mismo patrón de `pdf_grupo.php`: helpers `fmtNota()`/`colorSemaforo()` duplicados (no se creó un archivo compartido — consistente con el resto del proyecto, que no comparte lógica PHP entre módulos), y el mismo cálculo de 3 casos de Estado (En curso / Reprobado — pendiente habilitación / Aprobado o Reprobado) ya usado en `badgeEstado()` de `reportes_ctrl.js`
- Nombre de archivo: `Boletin_[documento]_[modu_sigla]_[fecha].pdf`

### Pruebas realizadas
- Boletín propio se descarga correctamente con datos completos ✅
- Estados "En curso", "Reprobado — pendiente habilitación" y "Aprobado" verificados con datos reales ✅
- Intento de acceder al `grmo_id` de otro estudiante → 403 "No autorizado" ✅
- `grmo_id` inexistente → mismo 403 "No autorizado" (no distinguible del caso anterior) ✅
- Acceso con rol distinto a estudiante o sin sesión → redirige a login ✅

---

## [906b219] — 2026-07-05 — nuevo endpoint pdf_grupo.php — reporte de grupo en PDF (coordinador/admin)

### Archivos modificados
- app/06_reportes/pdf_grupo.php — archivo nuevo: genera el reporte de calificaciones de todos los estudiantes de un grupo módulo, en PDF, formato institucional GA-FO-04

### Decisiones
- Acceso restringido a `role_id` 1 y 2 (coordinador/admin), vía `check_session.php` + validación de rol
- Sin instrucciones de diligenciamiento — el PDF es de solo lectura, no una planilla física para llenar a mano
- Sin bloque "ACUMULADOS" ni columna de "Total Faltas de Asistencia" — no se registra en el sistema actualmente
- Fórmula mostrada una sola vez en el encabezado: "N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = Nota Final"
- Columnas de notas iguales al modelo ya usado en `05_calificaciones`/`06_reportes`: N1, Sup N1, N2, Sup N2, N3, N4, Sup N4, Nota Final, Habilitación, Definitiva
- Leyenda de colores con círculos CSS (`border-radius:50%`), no emoji — dompdf no renderiza emoji Unicode correctamente (se veían como casillas vacías)
- Colores de semáforo iguales a los ya usados en el resto del sistema: `#d4edda` verde pálido / `#f8d7da` rojo pálido
- Formato de página `landscape` — necesario por la cantidad de columnas de la tabla de estudiantes
- Nombre de archivo: `GA-FO-04_[codigo_grupo]_[fecha].pdf`

### Pruebas realizadas
- PDF se genera y descarga correctamente para un grupo con estudiantes en distintos estados (aprobado, reprobado sin habilitación, reprobado con habilitación) ✅
- Colores de semáforo consistentes con la vista web ✅
- Leyenda con círculos CSS se renderiza correctamente (a diferencia de los emoji iniciales) ✅
- Acceso con rol docente o estudiante, o sin sesión → redirige a login ✅

---

## [fa6e685] — 2026-07-05 — alineación de 06_reportes con el modelo Nota Final / Habilitación / Definitiva

### Archivos modificados
- app/06_reportes/reportes_mdl.php — `cali_nota_final` y `cali_habilitacion` agregados al SELECT de `reporte_grupo` y `mis_notas`
- app/06_reportes/reportes_ctrl.js — `badgeEstado()` corregido para distinguir 3 casos; `badgeDefinitiva()` corregido para mostrar `cali_nota_final` en vez de `—` cuando hay resultado bruto sin definitiva oficial; nueva función `badgeNotaFinal()`
- app/06_reportes/reportes_view.php — nueva columna "Nota Final" en `tbl_mis_notas` y `tbl_reporte`, junto a "Definitiva"

### Bug corregido
- `badgeEstado()` solo distinguía 2 casos (sin definitiva = "En curso" / con definitiva = Aprobado o Reprobado). Tras el rediseño de `05_calificaciones`, un estudiante con las 4 notas completas que reprueba sin habilitación tiene `cali_definitiva = NULL` — el reporte del coordinador y "Mis Notas" del estudiante lo mostraban incorrectamente como **"En curso"**, cuando en realidad ya reprobó y está pendiente de habilitación. Información activamente incorrecta, no solo incompleta.

### Decisiones
- 3 casos en `badgeEstado()`: sin `cali_nota_final` (En curso, con o sin notas parciales) / `cali_nota_final` presente pero `cali_definitiva` null (Reprobado — pendiente habilitación) / `cali_definitiva` presente (Aprobado o Reprobado)
- `badgeDefinitiva()` ya no oculta el resultado bruto de la fórmula con `—` cuando existe `cali_nota_final` pero no hay definitiva oficial todavía

### Pruebas realizadas
- Estudiante sin notas → "En curso" ✅
- Estudiante con notas parciales (no las 4) → sigue "En curso" ✅
- Estudiante aprobado → Nota Final y Definitiva iguales, "Aprobado" ✅
- Estudiante reprobado sin habilitación → Nota Final visible en rojo, Definitiva ya no oculta el valor, "Reprobado — pendiente habilitación" ✅
- Estudiante reprobado que aprueba con habilitación → Definitiva = habilitación, "Aprobado" ✅
- Habilitación que también reprueba → "Reprobado" (sin el sufijo, porque ya hay definitiva oficial) ✅

---

## [58396d1] — 2026-07-04 — rediseño Nota Final / Habilitación / Definitiva en calificaciones

### Archivos modificados
- database/emdb_academica.sql — `cali_definitiva_original` renombrada a `cali_nota_final` en el CREATE TABLE; bloques de migración `ALTER TABLE` agregados para bases de datos ya creadas
- app/05_calificaciones/calificaciones_mdl.php — `guardar_nota` calcula y persiste `cali_nota_final` siempre (sin importar si aprueba); `cali_definitiva` rediseñada como valor oficial recalculado en cada guardado; envelope de respuesta incluye ambos campos
- app/05_calificaciones/calificaciones_view.php — nueva columna "Nota Final" entre Sup N4 y Habilitación; "Definitiva" movida al final de la fila; texto de ayuda actualizado con las 3 reglas
- app/05_calificaciones/calificaciones_ctrl.js — `construirFila()` muestra Nota Final y Definitiva como badges independientes con semáforo; callback de autosave corregido; `actualizarVisibilidadHabilitacion()` recibe `cali_nota_final` directamente del servidor

### Decisiones
- `cali_nota_final`: siempre se calcula con la fórmula N1(20%)+N2(20%)+N3(20%)+N4(40%), sin importar si el estudiante aprueba o no — separa el resultado bruto de la fórmula del valor oficial
- `cali_definitiva`: valor oficial recalculado en cada guardado — copia de `cali_nota_final` si es ≥ 3.0; copia de `cali_habilitacion` si `cali_nota_final` < 3.0 y esta fue registrada; `NULL` en cualquier otro caso
- Eliminada la lógica de "guardar solo la primera vez" (`cali_definitiva_original`) — ya no aplica bajo el nuevo modelo, que recalcula sin bloqueos

### Bug corregido
- calificaciones_ctrl.js: el guard `if (def !== null && def !== undefined)` impedía actualizar el badge "Definitiva" cuando el valor llegaba `null` (caso legítimo tras el rediseño: reprobado sin habilitación) — el badge quedaba congelado con el valor anterior hasta recargar la página completa. Ahora se maneja explícitamente mostrando `—` con color neutro (`bg-secondary`)
- `actualizarVisibilidadHabilitacion()` leía el valor desde el DOM (`badge.text()`) en vez de la respuesta del servidor, propagando datos obsoletos cuando el bug anterior dejaba el badge sin actualizar — corregido para recibir `cali_nota_final` directamente de la respuesta AJAX

---

## [5fe5f1f] — 2026-07-04 — creación de .gitignore

### Archivos modificados
- .gitignore — archivo nuevo: excluye `vendor/` y `.claude/settings.local.json`

### Decisiones
- `vendor/` excluido por ser dependencia de Composer (dompdf), regenerable vía `composer install`
- `.claude/settings.local.json` excluido por ser configuración local de permisos de Claude Code, no código del proyecto

---

## [a575cf0] — 2026-07-04 — instalación dompdf vía Composer + .htaccess de producción

### Archivos modificados
- composer.json — archivo nuevo: dependencia `dompdf/dompdf` para exportación PDF
- composer.lock — archivo nuevo: versiones bloqueadas de dompdf y sus dependencias
- .htaccess — versionado por primera vez en el repositorio: `RewriteEngine Off` — fix de interferencia con configuración de WordPress en el hosting de producción

### Decisiones
- dompdf instalado como base para el ítem 2.4 (exportación PDF: GA-FO-04 por módulo para coordinador + boletín individual para estudiante) — la implementación de la generación de PDF en sí queda todavía pendiente
- `.htaccess` de producción versionado para evitar que reglas de reescritura de otro CMS (WordPress) presentes en el hosting interfieran con las rutas de la aplicación

---

## [3aaf8c1] — 2026-05-07 — mejoras UX: mayúsculas automáticas + validación numérica calificaciones

### Archivos modificados
- app/00_files/estilos.css — archivo nuevo: clase .texto-mayus { text-transform: uppercase }
- app/02_estudiantes/est_view.php — clase texto-mayus en nombres, apellidos, ciudad, dirección, barrio, eps
- app/02_estudiantes/est_mdl.php — strtoupper(trim()) en estu_nombres, apellidos, ciudad, dirección, barrio, eps
- app/03_docentes/doc_view.php — clase texto-mayus en nombres, apellidos, sigla
- app/03_docentes/doc_mdl.php — strtoupper(trim()) en doce_nombres, apellidos, sigla
- app/04_grupos/grupos_view.php — clase texto-mayus en coho_codigo, grse_codigo, grmo_horario
- app/04_grupos/grupos_mdl.php — strtoupper(trim()) en coho_codigo, grse_codigo, grmo_horario
- app/05_calificaciones/calificaciones_ctrl.js — validación isNaN antes del AJAX: alert + campo vacío + foco
- app/05_calificaciones/calificaciones_mdl.php — is_numeric() antes del cast (float): evita que letras se guarden como 0.0

### Decisiones
- CSS text-transform: uppercase para feedback visual inmediato mientras el usuario digita
- strtoupper(trim()) en PHP como capa de seguridad — garantiza mayúsculas en BD independiente del frontend
- Campos excluidos de mayúsculas: emails, passwords, documentos, teléfonos, fechas, claves manuales, filtros de búsqueda
- Validación numérica en dos capas: JS (UX) + PHP (integridad de BD)
- (float)"f" en PHP devuelve 0.0 sin error — is_numeric() cierra este gap

### Pruebas realizadas
- Texto en minúsculas se convierte a mayúsculas en tiempo real en los 3 módulos ✅
- Datos guardados en BD en mayúsculas ✅
- Letra "f" en campo de nota: alert + campo vacío + foco regresa ✅
- Número válido (3.5): guarda normalmente ✅

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
