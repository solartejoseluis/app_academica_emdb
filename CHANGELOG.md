# CHANGELOG — app_academica_emdb
> Historial de cambios del Sistema de Gestión Académica EMDB
> Formato: [Semver] — Fecha | Orden: más reciente arriba

---

## [350c614] — 2026-09-03 — feat: backend Coordinador para checklist de requisitos por matrícula

### Archivos modificados
- app/02_estudiantes/est_mdl.php

### Cambios
- 2 `case` nuevos en `est_mdl.php`, guardados con el mismo patrón que
  `listar_matriculados`/`matricular` (`in_array($role_id, [1, 2], true)`,
  no `role_id === 1` como el catálogo de la Etapa 2.12.B — aquí sí puede
  operar el Coordinador, no solo el Admin):
  - `listar_requisitos_matricula`: recibe `matr_id`, hace `SELECT
    re.reqe_id, re.reqp_id, rp.reqp_nombre, rp.reqp_descripcion,
    re.reqe_estado, re.reqe_fecha FROM requisitos_estudiante re INNER
    JOIN requisitos_programa rp ON re.reqp_id = rp.reqp_id WHERE
    re.matr_id = ? ORDER BY rp.reqp_nombre ASC`.
  - `actualizar_requisito_estudiante`: recibe `reqe_id` y `reqe_estado`,
    validado con `in_array($reqe_estado, ['pendiente', 'entregado'],
    true)` — cualquier otro valor responde error sin tocar la BD. Si
    `'entregado'`: `UPDATE requisitos_estudiante SET reqe_estado =
    'entregado', reqe_fecha = CURDATE() WHERE reqe_id = ?`. Si
    `'pendiente'`: mismo `UPDATE` pero con `reqe_fecha = NULL` — revertir
    a pendiente borra la fecha de entrega, no la conserva.
- El `JOIN` de `listar_requisitos_matricula` es deliberadamente `INNER`,
  no `LEFT` — toda fila de `requisitos_estudiante` ya referencia un
  `reqp_id` válido por FK, y aunque ese requisito se desactive después en
  el catálogo (`reqp_activo = 0`), debe seguir apareciendo aquí con su
  nombre y descripción: es historial de lo que ya se le pidió al
  estudiante, no debe desaparecer solo porque el catálogo cambió.
- `listar_matriculados` gana 3 subqueries correlacionadas nuevas por
  `m.matr_id` (mismo patrón que las ya existentes
  `total_matriculas_estudiante`/`soac_estado_activo` — subquery en el
  `SELECT`, no un `JOIN` adicional que multiplicaría filas):
  `requisitos_total` (`COUNT(*)`), `requisitos_entregados` (`COUNT(*)`
  filtrado por `reqe_estado = 'entregado'`) y
  `requisitos_ultima_actualizacion` (`MAX(reqe_fecha_actualizacion)`).
  Ninguna columna existente se quitó del `SELECT`.
- Verificado con `curl` contra el contenedor Docker vivo, autenticando
  como Coordinador (`role_id = 2`): (1) `listar_requisitos_matricula`
  sobre una matrícula de prueba trajo correctamente `reqp_nombre`/
  `reqp_descripcion` vía el `INNER JOIN`; (2)
  `actualizar_requisito_estudiante` a `'entregado'` dejó `reqe_fecha` en
  la fecha de hoy, y revertir a `'pendiente'` la dejó en `NULL`,
  confirmado con SQL directo en ambos casos; (3) un intento con
  `reqe_estado='cualquier_otra_cosa'` respondió error sin modificar la
  fila; (4) `listar_matriculados` sin filtros mostró las 3 columnas
  nuevas en las 19 filas de la respuesta — `0`/`0`/`null` en las 9 sin
  ningún requisito asignado, valores correctos en las 10 que sí tenían.
  Datos de prueba (estudiante temporal, matrícula, requisito de catálogo
  temporal) eliminados al terminar.
- Etapa 2.12.D (de 10) del roadmap "Gestión de requisitos de
  estudiantes" — con esta etapa se cierra todo el backend (2.12.0 a
  2.12.D); las etapas restantes (E en adelante) son frontend.

### Decisiones
- `listar_requisitos_matricula` usa `INNER JOIN` en vez de `LEFT JOIN`
  contra `requisitos_programa` por decisión deliberada: un requisito
  desactivado en el catálogo después de habérsele asignado a una
  matrícula debe seguir siendo visible en el historial de esa matrícula,
  con su nombre y descripción — no tiene sentido de negocio que
  desactivar un requisito del catálogo borre silenciosamente su rastro
  de las matrículas que ya lo tenían asignado (`LEFT JOIN` habría dejado
  `reqp_nombre`/`reqp_descripcion` en `NULL` para esas filas en vez de
  mostrarlas).

---

## [f14331d] — 2026-09-03 — feat: asignar requisitos automáticamente al crear una matrícula nueva

### Archivos modificados
- app/02_estudiantes/est_mdl.php

### Cambios
- En `case 'matricular'`, dentro de la rama de creación nueva (el `else`
  que ejecuta el `INSERT INTO matriculas`), inmediatamente después de que
  `$stmtM->execute([...])` inserta la fila: se toma `$matr_id_nuevo =
  $pdo->lastInsertId()` y se inserta automáticamente en
  `requisitos_estudiante` un registro `'pendiente'` por cada requisito
  activo (`reqp_activo = 1`) del `prog_id` que se está matriculando,
  mediante `INSERT INTO requisitos_estudiante (matr_id, reqp_id,
  reqe_estado) SELECT ?, reqp_id, 'pendiente' FROM requisitos_programa
  WHERE prog_id = ? AND reqp_activo = 1`.
- El `INSERT` corre dentro de la transacción PDO ya existente de esta
  `case` (`beginTransaction()`/`commit()` que ya envolvía el
  `INSERT`/`UPDATE` de `matriculas` y la creación de acceso opcional) — no
  se abrió ninguna transacción nueva.
- La rama `$existingMatr` (re-matrícula sobre la misma fila
  estu+prog+peri, que hace `UPDATE` en vez de `INSERT`) no se tocó — sigue
  sin ningún `INSERT` a `requisitos_estudiante`.
- Verificado con `curl` contra el contenedor Docker vivo, autenticando
  como Coordinador (`role_id = 2`, el rol real que matricula en el flujo
  de producción): (1) matriculando a un estudiante de prueba en un
  programa con 2 requisitos activos en el catálogo, la matrícula nueva
  recibió exactamente 2 filas `'pendiente'` con el `matr_id` correcto,
  confirmado con SQL directo; (2) matriculando en un programa sin ningún
  requisito activo en el catálogo, la matrícula se creó sin error y con 0
  filas en `requisitos_estudiante`; (3) re-matriculando sobre la misma
  fila estu+prog+peri (rama `$existingMatr`), el conteo de
  `requisitos_estudiante` de esa matrícula se mantuvo igual antes y
  después, mientras que `matr_observacion` sí se actualizó — confirma que
  se tomó la rama `UPDATE` y no se disparó ningún `INSERT` adicional.
  Datos de prueba (estudiante temporal, 2 matrículas, 2 requisitos de
  catálogo y sus filas de `requisitos_estudiante`) eliminados al terminar.
- Etapa 2.12.C (de 10) del roadmap "Gestión de requisitos de estudiantes".

### Decisiones
- Se confirmó explícitamente con Jose Luis que la rama `$existingMatr` no
  necesita este `INSERT` porque el `matr_id` no cambia en una
  re-matrícula sobre la misma fila — los requisitos ya existen desde la
  creación original de esa matrícula.
- El `INSERT ... SELECT` sobre un catálogo sin requisitos activos
  simplemente no inserta ninguna fila, sin necesidad de ningún manejo
  especial de "caso vacío" — es comportamiento esperado del propio SQL,
  no un error a capturar.

---

## [c4b81f2] — 2026-09-03 — feat: backend Admin del catálogo de requisitos por programa

### Archivos modificados
- app/00_files/helpers.php
- app/02_estudiantes/est_mdl.php

### Cambios
- Nueva función `backfillRequisitoAMatriculasActivas(PDO $pdo, int $prog_id,
  int $reqp_id): void` en `helpers.php` — inserta una fila `'pendiente'` en
  `requisitos_estudiante` por cada matrícula `matr_estado = 'matriculado'`
  del programa indicado que todavía no tenga ese requisito, mediante un
  `INSERT ... SELECT ... WHERE NOT EXISTS (...)` correlacionado contra la
  propia `requisitos_estudiante`. El `NOT EXISTS` la vuelve idempotente:
  ejecutarla dos veces sobre el mismo `prog_id`/`reqp_id` nunca duplica
  filas, incluso sin depender únicamente del `UNIQUE KEY (matr_id, reqp_id)`
  como salvavidas — mismo criterio de "verificar antes, no solo confiar en
  la constraint" ya documentado para otras validaciones de unicidad del
  proyecto.
- 5 `case` nuevos en `est_mdl.php`, todos guardados con `role_id === 1`
  (Admin estricto): `listar_requisitos_programa` (`SELECT` por `prog_id`,
  orden `reqp_activo DESC, reqp_nombre ASC` — los inactivos quedan al
  final), `crear_requisito_programa` (`INSERT` en `requisitos_programa` +
  `backfillRequisitoAMatriculasActivas()` del `reqp_id` recién creado,
  dentro de una única transacción), `editar_requisito_programa` (`UPDATE`
  de `reqp_nombre`/`reqp_descripcion`, sin tocar `reqp_activo`),
  `eliminar_requisito_programa` (`UPDATE requisitos_programa SET
  reqp_activo = 0` — borrado lógico) y `reactivar_requisito_programa`
  (`UPDATE reqp_activo = 1` + `backfillRequisitoAMatriculasActivas()` otra
  vez, también en transacción, para que las matrículas activadas o creadas
  mientras el requisito estaba inactivo también reciban su fila
  `'pendiente'`).
- `crear_requisito_programa` y `reactivar_requisito_programa` corren dentro
  de `$pdo->beginTransaction()`/`commit()`, con `rollBack()` en el
  `catch (PDOException $e)` — mismo patrón ya usado en `case 'matricular'`.
  `editar_requisito_programa`/`eliminar_requisito_programa` no abren
  transacción propia por tratarse de un único `UPDATE` sobre una sola
  tabla.
- Verificado con `curl` contra el contenedor Docker vivo, autenticando con
  cookie-jar (mismo patrón de la Fase 2.11.C): (1)
  `crear_requisito_programa` sobre `prog_id=1` (ASO, 9 matrículas activas)
  produjo exactamente 9 filas `'pendiente'` en `requisitos_estudiante`,
  confirmado con una consulta SQL directa contra el contenedor; (2) un
  ciclo `eliminar_requisito_programa` → `reactivar_requisito_programa`
  sobre el mismo `reqp_id` conservó las mismas 9 filas (`reqe_id` 1-9), sin
  ningún duplicado del segundo backfill; (3) una sesión de Coordinador
  (`role_id = 2`) fue rechazada con `{"status":"error","message":"Sin
  autorización"}` en los 5 endpoints. Datos de prueba (el requisito
  "Copia diploma" y sus 9 filas de backfill) eliminados al terminar, sin
  dejar residuos en la BD de desarrollo.
- Etapa 2.12.B (de 10) del roadmap "Gestión de requisitos de estudiantes".

### Decisiones
- El guard es `role_id === 1` estricto, no `in_array($role_id, [1, 2],
  true)` como el resto de `02_estudiantes` — la configuración del catálogo
  es exclusiva de Admin; el Coordinador solo gestionará el checklist por
  matrícula, en una etapa posterior del roadmap (2.12.D/F).
- `eliminar_requisito_programa` usa borrado lógico (`reqp_activo = 0`) en
  vez de `DELETE` físico porque `requisitos_estudiante` tiene una FK
  `ON DELETE RESTRICT` hacia `requisitos_programa` — un `DELETE` físico
  fallaría en cuanto existiera al menos una fila de historial (que
  `crear_requisito_programa` ya genera de inmediato vía el backfill), y
  además destruiría la trazabilidad de qué se le pidió al estudiante en el
  pasado.
- `editar_requisito_programa` y `eliminar_requisito_programa` no verifican
  la existencia previa del `reqp_id` — un `UPDATE` sobre un `reqp_id`
  inexistente afecta 0 filas y de todas formas responde `'ok'`. Deuda
  técnica menor, no bloqueante, documentada aquí para una decisión futura
  (ej. verificar `rowCount()` antes de responder éxito).

---

## [ea03618] — 2026-09-03 — refactor: reemplazar 9 columnas req_* fijas por esquema configurable de requisitos

### Archivos modificados
- database/emdb_academica.sql
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios
- Elimina de `matriculas` (`database/emdb_academica.sql`) las 9 columnas
  `TINYINT(1)` `req_copiadiploma`, `req_actagrado`, `req_documento`,
  `req_carnetsalud`, `req_examenmedico`, `req_fotos`, `req_carpeta`,
  `req_vacunastetano`, `req_hepatitisb`, que hasta ahora vivían fijas en la
  tabla y solo se diligenciaban desde el propio flujo de matriculación.
- Crea `requisitos_programa` (catálogo configurable por programa, prefijo
  `reqp_`): `reqp_id`, `prog_id` (FK a `programas`, `ON DELETE RESTRICT`),
  `reqp_nombre`, `reqp_descripcion`, `reqp_activo` (borrado lógico, default
  `1`), `reqp_fecha_actualizacion`. Insertada inmediatamente después de
  `programas` en el DDL.
- Crea `requisitos_estudiante` (estado de cada requisito por matrícula,
  prefijo `reqe_`): `reqe_id`, `matr_id` (FK a `matriculas`, `ON DELETE
  CASCADE`), `reqp_id` (FK a `requisitos_programa`, `ON DELETE RESTRICT`),
  `reqe_estado` (`ENUM('pendiente','entregado')`, default `'pendiente'`),
  `reqe_fecha`, `reqe_fecha_actualizacion`, con `UNIQUE KEY (matr_id,
  reqp_id)` — un requisito del catálogo solo puede tener una fila de estado
  por matrícula. Insertada inmediatamente después de `matriculas` en el
  DDL.
- Actualiza el comentario final `-- Tablas creadas` de 19 a 21.
- Retira el flujo legacy en los 3 archivos de `02_estudiantes`, coordinado
  en el mismo commit que el cambio de esquema: `case 'matricular'`
  (`est_mdl.php`) ya no arma el array `$req` desde `$_POST` ni referencia
  ninguna de las 9 columnas en los `UPDATE`/`INSERT` de `matriculas` (sin
  tocar ninguna otra validación o columna de esa `case`); el modal
  `#mdl_matricular` (`est_view.php`) pierde el `<h6>` "Requisitos
  entregados" y los 9 checkboxes, sin ningún reemplazo todavía — el modal
  pasa directo de Programa/Cohorte/Semestre/Jornada/Período a "Datos de
  matrícula"; el handler `btn_confirmar_matricula` (`est_ctrl.js`) ya no
  recolecta esos 9 checkboxes al armar el payload AJAX.
- Etapa 2.12.A+A2 (de 10) del roadmap "Gestión de requisitos de
  estudiantes" — el resto de las etapas (catálogo CRUD para el Admin, UI de
  checklist por matrícula en `tablaMatriculados`, indicador visual, etc.)
  queda pendiente.

### Decisiones
- Se optó por eliminar las 9 columnas legacy sin migrar su historial —
  decisión explícita de Jose Luis — tras confirmar mediante un diagnóstico
  previo de solo lectura que estaban aisladas por completo al flujo de
  matriculación inicial: sin ningún lector en otros listados, reportes,
  PDFs, vistas, índices ni procedimientos almacenados del proyecto (el
  proyecto no usa procedimientos almacenados ni triggers desde el commit
  `304127b`).
- `requisitos_estudiante` se ancla a `matr_id`, no a `estu_id`, para
  soportar correctamente el caso ya vigente en el esquema de un estudiante
  con 2+ matrículas activas en programas distintos (ver "Matrícula a un
  segundo programa requiere un flujo explícito..." en CLAUDE.md) — cada
  matrícula conserva su propio set de requisitos, independiente de
  cualquier otra matrícula del mismo estudiante.
- `reqp_id` se referencia por FK en `requisitos_estudiante` en vez de
  copiar `reqp_nombre`/`reqp_descripcion` en cada fila — una edición futura
  del catálogo (ej. corregir el nombre de un requisito) se refleja sola en
  todas las matrículas que lo tengan, sin necesidad de ningún resync
  manual.

---

## [7052723] — 2026-09-03 — fix(estudiantes): elimina duplicación de filas en tablaMatriculados por condición de carrera (02_estudiantes)

### Archivos modificados
- app/02_estudiantes/est_ctrl.js

### Cambios
- `cargarTablas()` dejó de construir `tablaMatriculadosActual` y
  `tablaMatriculadosAnteriores` — esas 2 definiciones se movieron intactas
  (mismo `ajax`, `dataSrc`, funciones `data:`, `destroy: true`, `language`,
  `columns`) a una nueva función `cargarTablasMatriculados()`.
- `cargarTablasMatriculados()` se invoca una sola vez, dentro del `success`
  de `cargarPeriodos()`, reemplazando las 2 llamadas previas a
  `.ajax.reload(null, false)` — momento en el que `periodoActivoId` y el
  select de Anteriores (`#slct_filtro_matr_peri_id_anteriores`) ya tienen su
  valor definitivo.
- `cargarTablas()` ahora solo construye `tablaAspirantes` (independiente del
  período, sin cambios).

### Decisiones
- Causa raíz descartada en backend: el SQL de `listar_matriculados`
  (`est_mdl.php`) se verificó contra el contenedor Docker vivo sin ningún
  JOIN 1:N indebido y sin filas duplicadas en ningún escenario probado (con
  y sin filtro de período, dataset completo y por estudiante) — confirmado
  en un diagnóstico previo de solo lectura.
- El bug era 100% de cliente: `cargarTablas()` disparaba el auto-load de
  ambas tablas de Matriculados con `peri_id` todavía `null` (antes de que
  `cargarPeriodos()` lo resolviera de forma asíncrona), y esa petición
  prematura competía con el `.ajax.reload()` posterior — la superposición
  entre ambas respuestas hacía que DataTables sumara filas en vez de
  reemplazarlas.
- Confirmado en el navegador con la pestaña Network: **4 peticiones** a
  `listar_matriculados` antes del fix, **2** después (una por tabla).
  Reportado originalmente por Jose Luis: con 50 registros por página, la
  fila #1 aparecía idéntica a la fila #17.

---

## [ca744d0] — 2026-09-03 — feat(estudiantes): reemplaza columna Correo por Información en tablaMatriculados (02_estudiantes)

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios
- Los 2 `<th>Correo</th>` (pestañas "Matriculados (Per. Actual)" y
  "Matriculados (Per. Anteriores)") renombrados a `<th>Información</th>`.
- En `crearColumnasMatriculados()`: la columna `estu_apellidos` se
  simplifica a `{ data: 'estu_apellidos' }` sin `render` (ya no arma los
  badges).
- La columna que antes era `{ data: 'estu_email' }` pasa a `data: null` con
  un `render()` que muestra los 3 badges que antes vivían junto al nombre:
  🎓 N programas (`total_matriculas_estudiante`), 🔔 Link
  enviado/Pendiente aprobación (`soac_estado_activo`), ✅ Actualizado
  [fecha] (`soac_fecha_ultima_aprobacion`) — con fallback `'—'` si no hay
  ninguno.
- `est_mdl.php` sin cambios — `estu_email` sigue en el `SELECT` de
  `listar_matriculados` (sigue usándose en la ficha del estudiante vía
  `obtener_completo`).

### Decisiones
- El correo deja de mostrarse en `tablaMatriculados` por decisión explícita
  de Jose Luis — ya no es necesario en ese listado, y cuando se requiere se
  consulta en la ficha del estudiante (`#mdl_estudiante`).
- Columna "Información" sin `width` fijo, igual que tenía "Correo", para
  que DataTables siga redistribuyendo el espacio automáticamente.

---

## [6abbdae] — 2026-09-03 — chore: resuelve 4 pendientes menores (PDFs, SELECT *, coho_id, código muerto)

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/06_reportes/pdf_ficha.php
- app/06_reportes/pdf_grupo.php
- app/06_reportes/pdf_hoja_matricula.php
- app/06_reportes/reportes_mdl.php
- database/emdb_academica.sql

### Por qué
Resuelve 4 pendientes independientes ya documentados en CLAUDE.md
("Deuda técnica / pendiente antes de producción"), detectados en un
diagnóstico previo de solo lectura (sin commit, mismo día). Los 4
puntos son independientes entre sí salvo el 2→3: el punto 3 requería
primero que el punto 2 eliminara el único lector — aunque incidental,
vía `SELECT *` — de `estudiantes.coho_id`.

### 1. Centraliza el nombre institucional en los 3 PDFs restantes
`pdf_grupo.php`, `pdf_hoja_matricula.php` y `pdf_ficha.php` tenían el
string `"Escuela de Mecánica Dental Bolaños (EMDB)"` hardcodeado en su
`<h1>` — los únicos 3 generadores de PDF del proyecto que no leían
`configuracion.institucion_nombre`, ya centralizado en `pdf_boletin.php`
desde el commit `ef429bd`. Se replicó el mismo patrón tal cual en los
3: `SELECT institucion_nombre FROM configuracion WHERE config_id = 1`
justo antes del bloque HTML, con el mismo fallback al string literal
como default (`?? 'Escuela de Mecánica Dental Bolaños (EMDB)'`) y
`htmlspecialchars()` en el `<h1>`. En `pdf_hoja_matricula.php` las
variables se nombraron `$stmtConf2`/`$configInst` para no colisionar
con el `$stmtConf` que el mismo archivo ya usaba más abajo, en el
bloque de asignación segura de `matr_numero`.

### 2. `SELECT *` → columnas explícitas en `aprobar_actualizacion`
El `case 'aprobar_actualizacion'` (`est_mdl.php`) traía
`SELECT * FROM estudiantes` completo a `$estudianteActual` — incluidas
`coho_id`, `estu_activo`, `estu_foto`, `estu_origen` y `fechacreacion`,
ninguna de las cuales el resto del `case` usa. Se reemplazó por una
lista de columnas derivada dinámicamente del propio `$mapaEstudiantes`
ya existente (`array_column($mapaEstudiantes, 0)`, sin escribirla dos
veces a mano) más `estu_id`/`usua_id` (identidad/sesión, usadas para
el paso de sincronización de correo). Este cambio era el prerequisito
del punto 3: dejaba a `estudiantes.coho_id` sin ningún lector, ni
siquiera incidental.

### 3. Elimina `estudiantes.coho_id` (columna + FK `fk_estu_coho`)
Columna reemplazada por `matriculas.coho_id` desde la migración del
commit `3e551e5` (2026-08-30), conservada desde entonces como
"respaldo histórico sin escrituras nuevas". Confirmado por grep (tras
el punto 2) que no quedaba ningún lector activo en todo `app/` — ni
siquiera vía `SELECT *`. Eliminada contra el contenedor Docker vivo
con `ALTER TABLE estudiantes DROP FOREIGN KEY fk_estu_coho, DROP
COLUMN coho_id`. `database/emdb_academica.sql` actualizado: columna y
`CONSTRAINT fk_estu_coho` quitadas del `CREATE TABLE estudiantes`, y
corregido el comentario de `matriculas` que todavía afirmaba que
"`estudiantes.coho_id` se conserva como respaldo histórico" — ya no es
cierto, la columna no existe.

### 4. Elimina `mis_modulos`/`mis_notas` (código muerto)
Ambos `case` de `reportes_mdl.php` (rol 4, módulos/notas del propio
estudiante) confirmados sin ningún llamador en todo el proyecto —
ningún `_ctrl.js` invoca `accion=mis_modulos` ni `accion=mis_notas`.
Re-verificado con grep inmediatamente antes de eliminar (por si algún
commit posterior al diagnóstico original hubiera agregado un
llamador nuevo — no fue el caso). Eliminados por completo, incluidos
sus comentarios de sección.

### Pruebas realizadas
Todo contra el contenedor Docker vivo, en el orden de los 4 puntos:
- **PDFs:** generados los 4 (grupo, hoja de matrícula, ficha, y de
  paso el boletín para comparar) vía `curl` autenticado y extraídos
  con `pdftotext` — los 4 muestran
  `"Escuela de Mecánica Dental Bolaños (EMDB)"` de forma idéntica,
  confirmando que los 3 nuevos leen el mismo valor de `configuracion`
  que el boletín ya leía.
- **`aprobar_actualizacion`:** insertada una solicitud de prueba
  (`soac_id=12`, estado `'recibido'`, `estu_id=12`) con cambios de
  teléfono/ciudad/correo. `curl` respondió
  `{"status":"ok","campos_aplicados":["Teléfono","Correo electrónico","Ciudad"]}`;
  verificado en BD que `estudiantes` y `usuarios.usua_email`
  (sincronización) se actualizaron y que
  `solicitudes_actualizacion.soac_estado` pasó a `'aprobado'`. Datos
  de prueba (estudiante, usuario, solicitud) revertidos a su estado
  original al terminar.
- **`DROP COLUMN`:** `DESCRIBE estudiantes` confirma la ausencia de
  `coho_id`. `curl` a `listar_matriculados` y `obtener_completo`
  (`estu_id=12`) respondieron `status: ok` con datos completos, sin
  ningún error de columna faltante.
- **`php -l`** sin errores en los 6 archivos modificados.

---

## [70c45ba] — 2026-09-03 — feat(estudiantes): frontend de link de actualización de datos — Fase 4/5

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js
- app/00_files/estilos.css
- app/02_estudiantes/est_mdl.php (solo el fix de URL heredado, ver abajo)

### Por qué
Cuarta fase del feature "Link de actualización de datos de
estudiantes" — conecta el backend de la Fase 2 (`03e36fc`) y el
formulario público de la Fase 3 (`b739a60`) desde la UI del
coordinador en `tablaMatriculados`. Por primera vez el flujo completo
es utilizable de punta a punta por un coordinador real, sin `curl` ni
Postman.

### Ítem de dropdown "🔗 Generar link actualizar datos"
Nuevo `<li>` después de "📝 Datos Estudiante" en el dropdown de
Acciones — visible únicamente cuando `esPeriodoActual === true`
(ausente por completo, ni siquiera deshabilitado, en "Per.
Anteriores" — no aplica por diseño). Deshabilitado con tooltip si
`matr_estado_academico !== 'Activo'`; no revalida `matr_estado`
porque `listar_matriculados` ya lo garantiza `'matriculado'` en el
`WHERE` (mismo criterio que "Hoja de Matrícula" no lo revalida en
frontend).

### Modal `#mdl_actualizacion_datos` ("Actualización de datos")
Al abrir, consulta `estado_solicitud_actualizacion` para decidir el
modo:
- **Modo 1 — "Ver y aprobar actualización"** (si la solicitud más
  reciente está `'recibido'`): abre `#mdl_estudiante` en modo
  revisión.
- **Modo 2 — "Generar link para actualización"** (sin solicitud
  activa, o la más reciente ya resuelta): llama a
  `generar_link_actualizacion` y muestra el link con botón "Copiar"
  (`navigator.clipboard`, con fallback a `document.execCommand('copy')`
  para navegadores sin la API o sin contexto seguro).

### Modo revisión de `#mdl_estudiante` — reutilizado, no un modal nuevo
- `abrirRevisionActualizacion(estu_id, soac_id)`: carga en paralelo el
  estudiante vigente (`obtener_completo`) y la solicitud `'recibido'`
  (`estado_solicitud_actualizacion`).
- `poblarModalRevision(d, s)`: puebla el formulario con los valores
  vigentes (mismo bloque que `abrirEditar()`) y luego sobrescribe cada
  campo con su `soac_estu_*`/`soac_ficha_*` correspondiente cuando no
  es `NULL`, aplicando la clase nueva **`.campo-actualizado`** (fondo
  amarillo `#fff3cd`, distinta de `.campo-lleno` que ya existía en
  verde). Casos especiales sin un simple `.val()`: `padr_vive`/
  `madr_vive` (checkbox), `acud_es` (recalcula visibilidad
  Padre/Madre/Acudiente con los valores ya sobrescritos) y
  `multiculturalidad` (resalta el contenedor de checkboxes completo,
  no un campo individual). Título cambia a "FICHA DE INSCRIPCIÓN —
  código AC-FO-02"; formulario completo deshabilitado (todo
  input/select/textarea del `.modal-body`); se ocultan
  Guardar/Eliminar aspirante/PDFs/foto/cambio de clave; aparecen los 2
  botones nuevos "Aprobar actualización"/"Descartar" (llaman a
  `aprobar_actualizacion`/`descartar_actualizacion` con el `soac_id`,
  cierran el modal y refrescan ambas tablas de Matriculados al
  terminar).
- `restaurarModoNormalEstudiante()`: revierte todo lo anterior — se
  llama tanto en `hidden.bs.modal` de `#mdl_estudiante` (cierre normal
  del modo revisión) como al inicio de `abrirEditar()` (defensivo, por
  si se navegara de un modo a otro sin pasar por el cierre).

### 3 badges independientes en la columna Apellidos de `tablaMatriculados`
Pueden coexistir los tres a la vez, sin ser mutuamente excluyentes:
- 🎓 N programas (ya existente, sin cambios).
- 🔔 "Link enviado" (amarillo, `soac_estado_activo === 'generado'`) /
  🔔 "Pendiente aprobación" (rojo, `=== 'recibido'`).
- ✅ "Actualizado [fecha]" (verde, **permanente, sin ventana de
  tiempo**) — historial de la aprobación más reciente
  (`soac_fecha_ultima_aprobacion`), independiente de si hay algo
  pendiente ahora mismo. **Este badge fue un pedido posterior al
  prompt original de la Fase 4** — se implementó, probó y verificó en
  el mismo ciclo, antes del commit, así que queda documentado como
  parte de esta misma fase, no de una fase aparte.

Formato de fecha del badge verde: reutiliza el mismo patrón "fecha
simple" (`partes[2]/partes[1]/partes[0]` sobre un string
`AAAA-MM-DD`) ya usado en el resumen de "Programas matriculados" de
`abrirEditar()` — no `formatearUltimoAcceso()`, que incluye hora y no
hacía falta aquí (la hora completa queda disponible en el `title` del
badge).

### `listar_matriculados` — 2 campos nuevos (subconsultas escalares)
- `soac_estado_activo`: `soac_estado` de la solicitud más reciente
  **solo si** está en `('generado','recibido')`, `NULL` si no hay
  ninguna activa.
- `soac_fecha_ultima_aprobacion`: `soac_resuelto_en` de la solicitud
  más reciente **en estado `'aprobado'`**, `NULL` si nunca tuvo
  ninguna — subconsulta independiente de la anterior, no filtra por
  si hay algo pendiente ahora mismo.

### Fix heredado de la Fase 2
`generar_link_actualizacion` (`est_mdl.php`) construía la URL como
`/11_actualizacion_datos/actualizar_view.php?token=...` — ruta
provisional escrita en la Fase 2 antes de que el archivo real
existiera. La Fase 3 lo creó bajo `app/11_actualizacion_datos/`, cuya
ruta real servida por Apache es
`/app_academica_emdb/app/11_actualizacion_datos/actualizar_view.php`.
Sin este ajuste de una sola línea, el botón "Copiar" de esta fase
habría copiado un link roto. Único cambio en `est_mdl.php` en esta
fase — `11_actualizacion_datos/` queda intacto.

### Pruebas realizadas
Verificación no visual (sin navegador): `php -l` limpio, JS validado
con un parser real (`esprima`) sin errores de sintaxis, balance de
`<div>` en `est_view.php` (208/208), los 16 IDs nuevos existen
exactamente una vez, y las 55 claves `soac_*`/`soac_ficha_*` usadas en
el mapeo JS de revisión coinciden exactamente con las claves reales
devueltas por `estado_solicitud_actualizacion` (verificado contra una
solicitud `'recibido'` real ya generada por Jose Luis en la Fase 3).

**Verificado en navegador por Jose Luis** — 8 pasos del flujo completo:
generar link desde el dropdown y copiarlo; ver aparecer el badge
amarillo; abrir el formulario público con ese link en otra pestaña y
enviarlo; ver el badge cambiar a rojo; abrir el modal de revisión y
ver los campos propuestos resaltados en amarillo; aprobar y confirmar
que los datos del estudiante cambiaron en `tablaMatriculados`; repetir
el ciclo y confirmar el caso de descartar sin cambios. Más 4 pasos de
verificación del badge "✅ Actualizado": coexistencia visual de los 3
badges sin que se encimen, fecha correcta en el badge verde, `title`
con fecha y hora completas, y ausencia del badge en un estudiante sin
ninguna solicitud en su historial.

### Roadmap completo — "Link de actualización de datos de estudiantes" (4 piezas, cerrado)
`3ba9f99` (esquema) → `03e36fc` (backend) → `b739a60` (formulario
público) → `70c45ba` (frontend, esta entrada).

Feature verificado en navegador de punta a punta: el coordinador
genera el link desde `tablaMatriculados`, lo copia y lo envía
manualmente; el estudiante lo abre sin necesidad de cuenta, revisa y
envía su propuesta; el coordinador la revisa con los campos
resaltados y decide aprobarla o descartarla. La nota de la Fase 2
sobre "URL completa con dominio" (`03e36fc`) queda resuelta por este
mismo frontend, sin necesidad de tocar el backend — la concatenación
`window.location.origin + response.url` evita por completo el riesgo
de host-header injection que motivó dejarla pendiente en su momento.

Sin ningún pendiente abierto de este roadmap salvo un hallazgo puntual
detectado en la revisión de cierre (Fase 5, 2026-09-03, sin commit de
código): `#txt_ultima_actualizacion` en `#mdl_actualizacion_datos`
puede mostrar `'—'` cuando en realidad sí existe una aprobación en el
historial del estudiante, por usar un criterio de búsqueda más simple
que el badge "✅ Actualizado" de la misma fila — no es un bug de
datos ni bloqueante, documentado en CLAUDE.md ("Deuda técnica /
pendiente antes de producción") para una decisión futura de Jose Luis.

---

## [b739a60] — 2026-09-02 — feat(actualizacion-datos): formulario público de actualización — Fase 3/5

### Archivos modificados
- app/11_actualizacion_datos/actualizar_mdl.php (nuevo)
- app/11_actualizacion_datos/actualizar_view.php (nuevo)
- app/11_actualizacion_datos/actualizar_ctrl.js (nuevo)

### Por qué
Tercera fase del feature "Link de actualización de datos de
estudiantes" — sobre el backend ya implementado en la Fase 2
(`03e36fc`). Crea el módulo público donde el estudiante abre su link
(sin sesión) y completa/envía su propuesta de actualización. **Sin
ningún cambio en `02_estudiantes/`** — el dropdown, el badge y el
flujo de aprobar/descartar desde la UI del coordinador van en la
Fase 4.

### Módulo nuevo `11_actualizacion_datos/` — sin sesión
Mismo criterio que `09_inscripcion_publica/`: sin `session_start()` ni
`check_session.php`, accesible por cualquiera con el token en la URL
(`?token=...`).

### `actualizar_mdl.php` — 3 cases
- **`validar_token`**: valida existencia del token, que
  `soac_estado === 'generado'` (un token de un solo uso nunca vuelve a
  ese estado) y que `soac_expira_en` no haya pasado — responde
  `{status, motivo}` con uno de 3 códigos (`invalido`/`usado`/`expirado`),
  deliberadamente distinto del envelope estándar `{status, message}`
  del proyecto, para que el frontend distinga 3 mensajes. Si es válido,
  trae los valores VIGENTES de `estudiantes`+`fichas_inscripcion`
  (`LEFT JOIN`, mismo criterio que `obtener_completo` de `est_mdl.php`)
  — el estudiante edita sobre sus datos actuales, no un formulario
  vacío. `estu_tipodoc`/`estu_numerodoc` viajan de solo lectura, nunca
  como campos editables.
- **`guardar_actualizacion`**: **re-valida el token exactamente igual**
  que `validar_token` — nunca confía en que el frontend ya lo validó
  (pudo expirar o usarse entre que se abrió la página y que se dio
  "Guardar"). Mismos campos/validación condicional que
  `guardar_completo` (Padre/Madre/Acudiente según checkboxes, formato
  de email). Escribe **únicamente** en las columnas `soac_*`/`soac_ficha_*`
  de la fila de la solicitud — **nunca toca `estudiantes` ni
  `fichas_inscripcion` directamente**, esa escritura real ocurre solo
  al aprobar (Fase 2, ya implementada). El `UPDATE` es condicional:
  `WHERE soac_token = ? AND soac_estado = 'generado'` en la misma
  sentencia — previene la condición de carrera de un doble submit
  simultáneo; si `rowCount() === 0`, responde "Este link ya no es
  válido" sin asumir que ya se guardó.
- **`listar_programas`**: catálogo público duplicado tal cual de
  `insc_mdl.php` — esta vista no tiene sesión para llamar al
  `listar_programas` restringido de `est_mdl.php`, y necesita poblar
  `#slct_finc_prog_id`. No estaba en el alcance original de la fase,
  agregado como necesidad funcional real (mismo patrón "catálogos
  compartidos se duplican por módulo" ya documentado en CLAUDE.md).

### `actualizar_view.php`
Página completa, no modal (sin sesión no hay `tablaMatriculados`
detrás que justifique uno) — las 5 secciones de `#mdl_estudiante`
menos tipo/número de documento, mostrados como texto de solo lectura.
Encabezado "Estás actualizando los datos de: [nombres] [apellidos],
documento [tipodoc] [numerodoc]" para que el estudiante confirme que
es su propia ficha. Botón "Guardar actualización" (no "Guardar" a
secas, para dejar claro que es una propuesta pendiente de aprobación).
3 mensajes de error según el `motivo` de `validar_token`
(inválido/expirado — sugiere contactar al coordinador/usado — ya fue
enviado o resuelto). Confirmación final tras guardar, sin permitir
reenvío sin recargar con un nuevo link.

### `actualizar_ctrl.js`
Reutiliza por **copia, no import** — mismo criterio ya documentado en
CLAUDE.md para `insc_view.php`/`insc_ctrl.js` — `marcarValidacion()`,
`validarFormatoEmail()`, `marcarCampoLleno()` y su listener delegado,
y la lógica condicional de mostrar/ocultar Padre/Madre/Acudiente de
`est_ctrl.js`/`insc_ctrl.js`. Un solo submit AJAX a
`guardar_actualizacion` con el token incluido. **Sin `grecaptcha`**
(decisión ya tomada para este formulario).

### Bug encontrado y corregido durante el desarrollo
El primer borrador de la validación de campos de la ficha familiar
usaba un `exit` dentro del `foreach` de `$camposFichaRequeridos` —
cortaba la ejecución completa del script en cuanto encontraba el
primer campo válido en vez de solo el primero `null`, sin llegar
nunca al mensaje de error real. Corregido reemplazándolo por el mismo
patrón de bandera + `break` que ya usa `guardar_completo`
(`$campoFichaFaltante`), con la verificación del error después del
loop.

### Pruebas realizadas
7 casos probados con `curl`, **sin ninguna cookie de sesión**:
`actualizar_view.php` responde `HTTP 200` (contra `HTTP 302` de
`est_view.php` sin sesión, confirmando el contraste); `validar_token`
con token inexistente (`motivo: invalido`), token válido recién
generado vía `generar_link_actualizacion` de la Fase 2 (precarga
correcta de los 41 campos vigentes), token ya `'recibido'`
(`motivo: usado`) y token expirado simulado con `UPDATE` directo de
prueba (`motivo: expirado`); `guardar_actualizacion` exitoso
(verificado en BD: `soac_estado='recibido'`, `soac_recibido_en`
poblado, todos los `soac_*`/`soac_ficha_*` con los valores enviados),
el mismo token dos veces seguidas (segunda rechazada — "Este link ya
fue utilizado"), y sobre un token expirado (rechazado aunque el
formulario ya se hubiera cargado antes de expirar). Los tokens de
prueba quedaron resueltos (`recibido`/`descartado`) sin ninguna
solicitud activa colgada. **Verificado en navegador por Jose Luis:
4/4 puntos** (formulario completo, precarga de datos, guardado
exitoso, mensajes de error de token inválido y ya usado).

**Sigue siendo Fase 3 de 5.** Faltan: Fase 4 (frontend — ítem de
dropdown, badge, aprobar/descartar desde `tablaMatriculados`) y
Fase 5 (documentación de cierre del feature completo).

---

## [03e36fc] — 2026-09-02 — feat(estudiantes): backend de link de actualización de datos — Fase 2/5

### Archivos modificados
- app/00_files/helpers.php
- app/02_estudiantes/est_mdl.php

### Por qué
Segunda fase del feature "Link de actualización de datos de
estudiantes" — sobre el esquema ya creado en la Fase 1 (`3ba9f99`).
Implementa el backend completo: generación de token, consulta de
estado, aprobación con aplicación parcial de campos, y descarte. **Sin
ningún archivo de vista/JS tocado** — probado enteramente con `curl`
autenticado contra el contenedor Docker vivo.

### Cambios — `sincronizarEmailUsuario()` (`00_files/helpers.php`)
Nueva función compartida, extraída del patrón ya usado en
`doc_mdl.php` (`case 'guardar'`, commit `b6cc503`): valida unicidad de
`usuarios.usua_email` (excluyendo el propio `usua_id`) y actualiza.
Lanza `Exception` (no `PDOException`) en caso de colisión, para que el
llamador la capture con su propio mensaje — nunca abre ni cierra
transacción propia, siempre corre dentro de la del llamador.

### Fix de bug real — `case 'guardar_completo'` (`est_mdl.php`)
El hallazgo documentado en el diagnóstico de la Fase 1 quedó
confirmado y corregido: editar el correo de un estudiante con `usua_id`
ahora sincroniza `usuarios.usua_email` en la misma transacción — antes
el `UPDATE` solo tocaba `estudiantes.estu_email`, dejando la credencial
de acceso desactualizada. Se lee `estu_email` ANTES del `UPDATE` (junto
al `usua_id`, en la misma consulta ya existente) para comparar contra
el valor vigente, no contra el recién escrito.

### 4 cases nuevos (`est_mdl.php`) — solo `role_id IN (1,2)`
- **`generar_link_actualizacion`**: valida elegibilidad (`matriculado`
  + `Activo` + período activo, mismo criterio que separa "Per. Actual"
  en `listar_matriculados`); invalida automáticamente cualquier
  solicitud previa `'generado'`/`'recibido'` del mismo estudiante
  (nunca conviven dos activas); genera `soac_token` con
  `bin2hex(random_bytes(32))` (64 hex chars), expira en 24h.
- **`estado_solicitud_actualizacion`**: trae la solicitud MÁS
  RECIENTE del estudiante (`ORDER BY soac_id DESC LIMIT 1`, sin
  filtrar por estado), `null` explícito si nunca tuvo ninguna.
- **`aprobar_actualizacion`**: solo aplica los campos `soac_*` NO
  nulos — un campo `NULL` significa que el estudiante no lo tocó y
  nunca sobrescribe el valor vigente. Calcula
  `soac_campos_modificados` comparando cada valor nuevo no-nulo contra
  el vigente (comparación como `string` para evitar falsos positivos
  de tipo). Sincroniza `usua_email` si cambió (misma función del punto
  anterior). Todo en una transacción con rollback total ante
  cualquier colisión — no se aprueba nada parcialmente.
- **`descartar_actualizacion`**: borrado lógico
  (`soac_estado = 'descartado'`) — rechaza si la solicitud ya está
  resuelta (`'aprobado'`/`'descartado'`).

### `listar_matriculados` — `soac_estado_activo`
Subconsulta escalar (mismo patrón que `aprobado_periodo_actual`) que
trae el `soac_estado` de la solicitud más reciente del estudiante,
`NULL` si no hay ninguna `'generado'`/`'recibido'` activa — único dato
que la Fase 4 necesitará para el badge del dropdown.

### Bug encontrado y corregido durante las pruebas
`aprobar_actualizacion` no validaba `estudiantes.estu_email` (tiene su
propia `UNIQUE KEY uq_estu_email`, independiente de
`usuarios.usua_email`) antes del `UPDATE` genérico — una colisión ahí
lanzaba un `PDOException` crudo de MySQL, capturado por el catch
genérico, perdiendo el mensaje específico. Corregido agregando el
mismo chequeo de unicidad que ya usa `guardar_completo`, justo antes
del `UPDATE`. Verificado con dos pruebas de colisión distintas (contra
`estudiantes` y contra `usuarios`), cada una con su mensaje correcto.

### Decisiones sin instrucción explícita
1. **URL del link como ruta relativa, sin host dinámico** — construida
   literalmente como se pidió
   (`/11_actualizacion_datos/actualizar_view.php?token=...`), sin
   agregar `$_SERVER['HTTP_HOST']`: no hay precedente en el proyecto
   para construir URLs absolutas server-side, y hacerlo sin necesidad
   real introduciría riesgo de host-header injection sin beneficio
   claro en esta fase. Queda pendiente para la Fase 3/4 si se necesita
   una URL completa con dominio.
2. **Ubicación de `sincronizarEmailUsuario()` en `guardar_completo`**:
   justo después del `UPDATE` de `estudiantes`, antes del bloque de
   cambio de clave — interpretado literalmente de "justo después del
   UPDATE de estudiantes y antes del bloque de fichas_inscripcion"
   como inmediatamente después, no en cualquier punto anterior al
   bloque de ficha.

### Pruebas realizadas
11 casos probados con `curl` autenticado contra el contenedor Docker
vivo: elegibilidad (caso exitoso y rechazo por estudiante no activo del
período), invalidación automática de solicitud previa al generar una
nueva, consulta de estado (sin solicitud y con solicitud), aprobación
con aplicación parcial (solo campos no-nulos se escriben, un campo
enviado con el mismo valor vigente no cuenta como "modificado"), doble
colisión de correo con rollback total verificado (contra
`estudiantes.estu_email` y contra `usuarios.usua_email`, cada una con
su mensaje), aprobación exitosa con sincronización de ambos correos,
descarte válido e inválido (sobre solicitud ya resuelta), y el fix de
`guardar_completo` confirmado contra datos reales (el `usua_email`
desincronizado ya existía en la base antes del fix — evidencia directa
del bug). Todos los datos de prueba (teléfonos, direcciones, correos)
revertidos a su valor original exacto, verificado con consulta final
de comparación; las filas de `solicitudes_actualizacion` generadas
durante las pruebas quedan en su estado resuelto final
(`aprobado`/`descartado`) como registro de auditoría legítimo del
propio diseño de borrado lógico — no son datos de prueba a revertir.

**Sigue siendo Fase 2 de 5.** Faltan: Fase 3 (formulario público que
consume el token), Fase 4 (frontend — ítem de dropdown, badge,
aprobar/descartar desde `tablaMatriculados`) y Fase 5 (documentación
de cierre del feature completo).

---

## [3ba9f99] — 2026-09-02 — feat(db): agrega tabla solicitudes_actualizacion — Fase 1/5 de link de actualización de datos

### Archivos modificados
- database/emdb_academica.sql

### Por qué
Primera fase de un feature nuevo en `02_estudiantes`: un link público
(sin sesión) para que un estudiante actualice su propia ficha
(`estudiantes` + `fichas_inscripcion`), quedando pendiente de que el
coordinador apruebe o descarte los cambios desde un nuevo ítem del
dropdown de Acciones de `tablaMatriculados`. Precedido de un
diagnóstico de solo lectura (sin commit) que mapeó el esquema completo
de ambas tablas, el modal `#mdl_estudiante`, el patrón "público pero
seguro" ya usado en `09_inscripcion_publica/`, el `case
'guardar_completo'` (candidato a reutilizar/adaptar en la aprobación),
el patrón de sincronización `usua_email` de `doc_mdl.php`, la
estructura de `tablaMatriculados`, y la convención de nombres del
proyecto (confirmó que `soac_`/`soac_ficha_` no chocan con ningún
prefijo existente).

**Esta es la Fase 1 de 5 — solo esquema.** Ningún archivo PHP/JS se
tocó en este commit. Faltan: Fase 2 (backend de generación de token),
Fase 3 (formulario público que consume el token), Fase 4 (frontend del
dropdown/badge en `tablaMatriculados` + aprobar/descartar), y Fase 5
(documentación de cierre del feature completo). El feature **no está
cerrado** — no confundir esta entrada con un feature terminado.

### Cambios — Esquema (`database/emdb_academica.sql`)
Nueva tabla `solicitudes_actualizacion` (sección "BLOQUE 6D", tras
`configuracion`) — 63 columnas:
- **10 de control/auditoría:** `soac_id` (PK), `estu_id` (FK →
  `estudiantes`, `ON DELETE CASCADE`, mismo criterio que
  `fk_finc_estu`), `soac_token VARCHAR(64)` (`UNIQUE KEY
  uq_soac_token`), `soac_generado_en`/`soac_expira_en DATETIME`,
  `soac_estado ENUM('generado','recibido','aprobado','descartado')`
  default `'generado'`, `soac_recibido_en`/`soac_resuelto_en
  DATETIME`, `soac_resuelto_por` (FK → `usuarios`, `ON DELETE SET
  NULL`), `soac_campos_modificados TEXT`.
- **17 espejo de `estudiantes`** (prefijo `soac_estu_`, excepto
  `soac_fechanacimiento` que no lleva `estu_` porque la columna
  original tampoco lo lleva) — mismo tipo/longitud que el original,
  todas `NULLABLE` aunque el original sea `NOT NULL` (ej.
  `estu_nombres`/`estu_apellidos`). Excluye deliberadamente
  `estu_tipodoc`/`estu_numerodoc` (nunca editables por este medio) y
  las columnas de identidad/sistema (`estu_id`, `usua_id`, `coho_id`,
  `estu_activo`, `estu_foto`, `estu_origen`, `fechacreacion`).
- **35 espejo de `fichas_inscripcion`** (prefijo `soac_ficha_`) — los 3
  campos de programa/jornada/fecha, los 9 de Padre, los 9 de Madre,
  los 10 de Acudiente, los 4 de Estudios anteriores. Excluye `finc_id`,
  `estu_id`, `finc_estado`, `fechacreacion`.
- `fechacreacion TIMESTAMP DEFAULT current_timestamp()` — mismo patrón
  de auditoría base que el resto de tablas del proyecto.

### Decisiones de diseño
1. **`soac_ficha_prog_id` sin FK hacia `programas`** — a diferencia del
   `prog_id` original de `fichas_inscripcion`, que sí tiene
   `fk_finc_prog`. Los valores de esta tabla son propuestas del
   estudiante sin validar todavía — una FK forzaría que el
   `prog_id` propuesto sea válido en el momento de *guardar la
   solicitud* (irrelevante en esa etapa), en vez de validarse recién
   al momento de aprobar, que es cuando realmente importa.
2. **`soac_estado` como borrado lógico, no `DELETE` físico** — una
   solicitud descartada por el coordinador transiciona a
   `soac_estado = 'descartado'` en vez de eliminarse; no existe (ni se
   planea) ningún `DELETE` sobre esta tabla en el flujo normal. Mantiene
   trazabilidad completa de qué se propuso y qué decidió el
   coordinador, mismo criterio de auditoría que otras decisiones
   binarias del proyecto prefieren un flag/estado sobre un borrado
   irreversible.
3. **Tabla ubicada al final del `.sql`, sección "BLOQUE 6D"** — mismo
   patrón que `ayudas` (6B) y `configuracion` (6C): tablas de soporte
   agregadas después del bloque principal de 14 tablas académicas, con
   su propio `DROP TABLE IF EXISTS` + comentario de sección, sin
   renumerar el "Orden de creación" ya documentado en CLAUDE.md (ese es
   un cambio de documentación, no de esquema).

### Aplicación
`CREATE TABLE` aislado ejecutado contra el contenedor Docker vivo
(`docker compose exec db mysql`) — **no** se re-ejecutó el `.sql`
completo, que habría hecho `DROP TABLE IF EXISTS` sobre las 18 tablas
existentes y destruido los datos transaccionales de prueba. Footer del
`.sql` actualizado de "Tablas creadas: 18" a "19".

### Pruebas realizadas
`DESCRIBE solicitudes_actualizacion` — 63 columnas con tipo/nullability
exactos a los especificados. `information_schema.KEY_COLUMN_USAGE` —
ambas FK registradas correctamente. `SHOW INDEX` — `PRIMARY` +
`uq_soac_token` confirmados. Grep de `soac_` y
`solicitudes_actualizacion` en todo `app/` → 0 coincidencias, sin
colisión de nombres. `CREATE TABLE`/`DROP TABLE IF EXISTS` balanceados
19/19 en el `.sql`.

---

## [0aef564] — 2026-09-02 — feat(estudiantes): matricular al siguiente semestre

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Por qué
Pieza final del roadmap de "semestre en la UI" — las 4 piezas
anteriores (`c985188`, `8884cb4`, `0aaa4e9`, `27a3915`) dejaron
`matr_semestre` visible en toda la aplicación pero sin ninguna forma de
avanzarlo: el ENUM `matr_estado` reservaba el valor `'cursado'` desde
`c985188` sin que ningún endpoint lo escribiera todavía. Precedido de
2 diagnósticos de solo lectura (sin commit) que confirmaron el diseño
(nueva fila en `matriculas`, no `UPDATE` de la existente) y el estado
exacto de cada pieza reutilizable (dropdown, `detalle_periodo`,
validación de semestre, `matr_estado_academico`, campos disponibles en
`listar_matriculados`) antes de implementar.

### Cambios — Backend (`est_mdl.php`)
- **`validarSemestrePrograma(PDO $pdo, int $prog_id, int $matr_semestre): ?string`**
  extraída como función reutilizable — antes duplicada literalmente en
  `case 'matricular'` y `case 'editar_matricula'` (mismo `SELECT
  prog_duracion_semestres`, mismo rango, mismo mensaje). Ambos `case`
  reemplazan su bloque inline por una llamada a esta función, sin
  cambio de comportamiento. Ahora la usan 3 lugares: `matricular`,
  `editar_matricula`, y el nuevo `case 'avanzar_semestre'`.
- `case 'listar_matriculados'` agrega:
  - `m.matr_estado_academico` al `SELECT` (no estaba, pese a existir
    en otros `case` del mismo archivo).
  - `aprobado_periodo_actual` — subconsulta escalar correlacionada que
    replica **de forma consciente** la lógica de promedio de
    `detalle_periodo` (`reportes_mdl.php`): promedio de
    `cali_definitiva` de los módulos de ese `prog_id`+`peri_id` para
    ese estudiante, `1` solo si TODAS están pobladas y el promedio es
    `>= 3.0`. Sin filtro por `grmo_activo` (tampoco lo aplica
    `detalle_periodo`). Es solo para decidir qué mostrar en el
    dropdown — nunca se usa para autorizar el avance real.
- **Nuevo `case 'avanzar_semestre'`** — solo `role_id` 1/2
  (coordinador/administrador). Recibe `matr_id` + `peri_id_destino`.
  Validaciones en cadena, cada una con su propio mensaje, antes de
  abrir transacción:
  1. `matr_estado` debe ser `'matriculado'`.
  2. `matr_estado_academico` debe ser `'Activo'`.
  3. `matr_semestre + 1` validado con `validarSemestrePrograma()`
     contra `prog_duracion_semestres` (cubre el caso de último
     semestre).
  4. `peri_id_destino` distinto del `peri_id` actual de la fila.
  5. Aprobación del período actual **recalculada con una query
     directa** en el momento de escribir — no confía en
     `aprobado_periodo_actual` (esa columna es solo para UI) ni en
     ningún valor recibido por POST.
  6. Sin fila duplicada ya existente para `estu_id`+`prog_id`+
     `peri_id_destino` (mismo criterio que el `UNIQUE KEY`).

  Si todas pasan: transacción PDO — `UPDATE matriculas SET matr_estado
  = 'cursado'` sobre la fila actual, luego `INSERT` de la fila nueva
  (`matr_semestre + 1`, `matr_estado = 'matriculado'`,
  `matr_estado_academico = 'Activo'`, mismo `coho_id` heredado,
  `fechamatricula = CURDATE()`) — campos no heredados (`matr_folio`,
  `matr_numero`, `matr_matriculadopor`, `fechainscripcion`,
  `matr_observacion`, `req_*`) quedan en su `NULL`/`DEFAULT 0` de
  columna, igual que una matrícula nueva creada desde cero.

### Cambios — Frontend (`est_view.php` + `est_ctrl.js`)
- `crearColumnasMatriculados(esPeriodoActual)` — nuevo parámetro
  booleano. Las dos llamadas existentes se actualizan:
  `tablaMatriculadosActual` con `true`, `tablaMatriculadosAnteriores`
  con `false`. Ver "Decisiones por ambigüedad" — no viola la
  advertencia ya documentada en CLAUDE.md contra funciones
  compartidas con parámetros condicionales.
- Nuevo ítem de dropdown **"⏩ Matricular al sgte. sem."**, agregado
  solo cuando `esPeriodoActual === true` (nunca en Per. Anteriores),
  entre "✏️ Matrícula" y "🖨️ Hoja de Matrícula". Deshabilitado con
  tooltip (`<li tabindex="0" title="...">` + `<button disabled>`,
  mismo patrón que "➕ Matricular en otro programa") según el primer
  motivo que aplique, en cascada:
  1. `matr_estado !== 'matriculado'` → "Esta matrícula ya no está
     activa."
  2. `matr_estado_academico !== 'Activo'` → "El estudiante no está en
     condición académica Activa."
  3. `matr_semestre >= prog_duracion_semestres` → "El estudiante ya
     está en su último semestre."
  4. `aprobado_periodo_actual != 1` → "El estudiante aún no ha
     aprobado el período actual."
- Nuevo modal `#mdl_avanzar_semestre` (`est_view.php`) — contexto de
  solo lectura (nombre, programa, "Semestre actual: N/M → Nuevo
  semestre: N+1/M") y `<select id="slct_avanzar_peri_id_destino">`.
- Nueva función global `abrirAvanzarSemestre(matr_id, nombreCompleto,
  matrSemestreActual, progDuracionSemestres, progSigla)` — puebla el
  modal sin ninguna llamada AJAX adicional (todos los datos ya viajan
  en la fila). El select de período destino se puebla **clonando** las
  opciones ya calculadas de `#slct_filtro_matr_peri_id_anteriores`
  (todos los períodos excepto el activo, resuelto una sola vez por
  `cargarPeriodos()`) en vez de reimplementar esa exclusión o exponer
  `periodoActivoId` fuera de su scope. Ver "Decisiones por
  ambigüedad".
- Nuevo handler `#btn_confirmar_avanzar_semestre` — `POST` a
  `avanzar_semestre`; si `status === 'ok'`, cierra el modal y recarga
  `tablaMatriculadosActual` **y** `tablaMatriculadosAnteriores` (la
  fila vieja pasa a Per. Anteriores, la nueva aparece en Per. Actual);
  si `status === 'error'`, deja el modal abierto y muestra el mensaje
  del backend.

### Decisiones por ambigüedad
1. **`crearColumnasMatriculados(esPeriodoActual)` no viola la
   advertencia de CLAUDE.md** contra "forzar una función compartida
   con parámetros condicionales para columnas que en realidad son
   distintas" — de las 13 columnas que retorna la función, 12 siguen
   siendo idénticas entre ambas tablas; el parámetro solo afecta un
   `<li>` dentro del `render()` ya existente de la columna Acciones,
   no la estructura de columnas en sí.
2. **`periodoActivoId` no se expuso fuera de su scope.**
   `abrirAvanzarSemestre()` es una función global (invocada por
   `onclick` inline desde el HTML de DataTables), mientras que
   `periodoActivoId` es una variable local a `$(document).ready` —
   inaccesible desde ahí. En vez de promover esa variable a un scope
   más amplio o reimplementar el filtro "todos los períodos excepto el
   activo", la función clona el `<select>` de Per. Anteriores, que ya
   tiene exactamente esa lista resuelta desde `cargarPeriodos()`.

### Pruebas realizadas
Backend probado con `curl` autenticado contra el contenedor Docker
vivo, 10/10 casos: avance exitoso (fila vieja → `cursado`, nueva con
`matr_semestre + 1`); reintento sobre fila ya `cursado`; notas
incompletas; `matr_estado_academico = 'Aplazamiento'` (temporal);
último semestre (temporal); promedio `< 3.0` con notas completas
(temporal); fila duplicada en el destino (simulada); mismo período
como destino; sin sesión. Todos los datos de prueba temporales y los 2
avances reales de prueba se revirtieron sin dejar rastro. Frontend:
página y JS servidos sin errores/warnings, balance de `<div>` correcto,
lógica de habilitado/deshabilitado trazada en Python contra los datos
reales de `listar_matriculados` (17 filas — coincide exactamente con
los 2 estudiantes aprobados). **Verificado en navegador por Jose Luis:
6/6 puntos** (visibilidad condicional del ítem, habilitado/deshabilitado
correcto, datos del modal, selector de período, transición de filas
entre pestañas tras confirmar, bloqueo de reintento sobre fila ya
avanzada).

### Roadmap completo — "semestre en la UI" (5 piezas, cerrado)
`c985188` (esquema + validación) → `8884cb4` (columna en listado) →
`0aaa4e9` (pestañas Per. Actual/Anteriores) → `27a3915` (reporte +
boletín PDF) → `0aef564` (acción de avance, esta entrada).

---

## [27a3915] — 2026-09-02 — feat(reportes): agregar semestre del estudiante en reporte y boletín PDF

### Archivos modificados
- app/06_reportes/reportes_mdl.php
- app/06_reportes/reportes_ctrl.js
- app/06_reportes/pdf_boletin.php

### Por qué
Tercera pieza visible del roadmap de "semestre en la UI" (después de
`c985188` y `8884cb4`/`0aaa4e9`, todos 2026-09-02): `matriculas.matr_semestre`
ya vivía en el esquema, en los modales de matrícula y en la columna
"Semestre" de `tablaMatriculados`, pero el propio estudiante no podía
verlo en su reporte ni en su boletín PDF — los dos únicos lugares donde
consulta directamente su situación académica.

### Cambios — Backend (`reportes_mdl.php`)
- `case 'mis_programas'`: agrega `m.matr_semestre` y
  `p.prog_duracion_semestres` al `SELECT` existente, mismo
  `INNER JOIN programas p ON m.prog_id = p.prog_id` ya usado para
  `prog_nombre`/`prog_sigla` — sin JOIN nuevo.
- `case 'detalle_periodo'`: la consulta previa de pertenencia
  (`SELECT prog_id FROM matriculas WHERE matr_id = ? AND estu_id = ?`)
  se amplía con un `INNER JOIN programas` para traer también
  `matr_semestre`/`prog_duracion_semestres`, agregados al envelope de
  respuesta (`matr_semestre`, `prog_duracion_semestres`, junto a
  `estado_periodo`/`promedio_periodo`/`data`).

### Cambios — Frontend (`reportes_ctrl.js`)
- Nueva función `formatoSemestre(matr_semestre, prog_duracion_semestres)`
  — formato "N/M", fallback `'—'` si falta cualquiera de los dos
  valores. Mismo criterio que `crearColumnasMatriculados()`
  (`02_estudiantes`), sin el resaltado condicional de "último semestre"
  de esa columna — es un atributo de la matrícula (`mis_programas`), no
  del período seleccionado.
- Bloque de contexto de `cargarProgramas()` agrega "Semestre" entre
  "Cohorte" y "Períodos realizados"; reparto de columnas Bootstrap
  ajustado de 4/3/3/2 a 4/2/2/2/2 para dar espacio a la quinta columna.

### Cambios — PDF (`pdf_boletin.php`)
- Mismo `SELECT` de matrícula extendido con `m.matr_semestre` y
  `p.prog_duracion_semestres`.
- Nueva función `fmtSemestre()` (mismo criterio que `formatoSemestre()`
  de `reportes_ctrl.js`).
- "Semestre: N/M" ocupa el par de celdas
  `<td class="etiqueta"></td><td></td>` ya reservadas (vacías) en la
  fila de "Período" de la tabla de contexto — sin agregar fila nueva,
  respetando el antipatrón de Dompdf con `colspan` mezclado ya
  documentado en CLAUDE.md.

### Decisión
`05_calificaciones` (vista del docente) queda fuera de alcance —
decisión explícita, no una omisión: el semestre del grupo
(`grse_semestre`, ya visible ahí como "Sem.N") se conserva sin cambios,
y no se agrega el semestre del estudiante (`matr_semestre`) a esa vista
porque en la lógica de negocio ambos deberían coincidir — mostrar los
dos valores por separado generaría ruido en vez de claridad.

### Pruebas realizadas
Verificado en navegador por Jose Luis: 3/3 pruebas (dato visible en el
reporte del estudiante, no cambia al cambiar de período, boletín PDF
con el layout correcto).

---

## [0aaa4e9] — 2026-09-02 — feat(estudiantes): dividir Matriculados en pestañas Per. Actual / Per. Anteriores

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Por qué
Segunda pieza visible del roadmap de "semestre en la UI" (después de
`c985188` y `8884cb4`, ambos 2026-09-02). Precedido de un diagnóstico de
solo lectura (sin commit) que confirmó: `tablaMatriculados` ya tenía un
`<select>` de período visible sobre una sola tabla, sin distinguir
período activo de históricos; `listar_periodos` no exponía
`peri_activo`; y `listar_matriculados` no tenía `m.peri_id` en su
`SELECT` pese a que `verModulosEstudiante()` lo necesitaba.

### Cambios — Backend (`est_mdl.php`)
- `case 'listar_periodos'`: agrega `peri_activo` al `SELECT` existente
  — el frontend decide con este flag qué mostrar en cada select, sin
  necesidad de un `case`/modo especial nuevo.
- `case 'listar_matriculados'`: agrega `m.peri_id` al `SELECT` — no
  estaba, pese a que la implementación original lo daba por existente
  (ver "Decisiones por ambigüedad" abajo). El resto del `WHERE`/filtros
  queda intacto; ya aceptaba `peri_id` opcional por POST desde antes.

### Cambios — Frontend (`est_view.php` + `est_ctrl.js`)
- Pestaña "Matriculados" (única) dividida en dos pestañas de nivel
  superior: **"Matriculados (Per. Actual)"** — sin `<select>` de
  período, un `<input readonly>` muestra el período activo como
  referencia — y **"Matriculados (Per. Anteriores)"** — `<select>` con
  todos los períodos excepto el activo.
- Cada pestaña tiene su propia tabla (`#tbl_matriculados_actual` /
  `#tbl_matriculados_anteriores`) y su propio bloque de filtros
  Programa/Grupo/Módulo, con ids sufijados `_actual`/`_anteriores`.
- El array `columns` (idéntico entre ambas tablas, incluida la columna
  "Semestre" agregada en `8884cb4`) se extrajo a
  `crearColumnasMatriculados()`, función global que retorna un array
  nuevo en cada llamada — primer caso del proyecto de dos instancias de
  DataTable con la misma estructura de columnas (sin precedente previo
  en 04_grupos/03_docentes, donde cada tabla tiene columnas distintas).
- `verModulosEstudiante()` recibe `peri_id` como 4º parámetro explícito
  (`row.peri_id`, vía el `onclick` inline del botón "Módulos"), en vez
  de leer `$('#slct_filtro_matr_peri_id')` — ese selector único dejó de
  existir al dividirse en dos pestañas con fuentes de período distintas
  (una fija, otra por select).
- Las 5 acciones que antes recargaban una sola tabla (guardar
  estudiante, subir foto, matricular, cerrar matrícula, editar
  matrícula) ahora recargan ambas (`tablaMatriculadosActual` y
  `tablaMatriculadosAnteriores`, con guardas `if (tabla)` por si alguna
  no llegó a inicializarse). Los listeners de cambio de filtro de cada
  pestaña solo recargan su propia tabla.

### Decisiones por ambigüedad
1. **`m.peri_id` faltante en `listar_matriculados`.** La especificación
   de implementación asumía que ya viajaba en la respuesta
   ("`row.peri_id`, ya viene en la respuesta"); al verificar el
   `SELECT` no estaba — solo `pe.peri_codigo`. Se agregó, sin lo cual
   `verModulosEstudiante()` no tendría cómo recibir el período de la
   fila en ninguna de las dos pestañas.
2. **Sin opción "Todos" en el select de "Per. Anteriores".** La
   especificación indicaba que el backend no necesitaría un modo
   especial y que el frontend siempre enviaría `peri_id` explícito. Una
   opción "Todos" con valor vacío habría enviado `peri_id=''`, que
   `listar_matriculados` interpreta como "sin filtro" — el período
   activo se colaría en la pestaña de históricos. Se quitó esa opción y
   se preselecciona el período anterior más reciente al cargar la
   página, garantizando que esa tabla nunca dispare una consulta sin
   `peri_id`.
3. **Edge case de cero períodos anteriores.** No reproducible hoy en
   local (hay 4 períodos sembrados, 3 no-activos), pero si algún día
   solo existe el período activo, el select de "Anteriores" muestra una
   opción `value="0"` ("-- Sin períodos anteriores --") en vez de
   quedar vacío — `0` no coincide con ningún `peri_id` real, así que la
   tabla responde 0 filas en vez de caer en el caso "sin filtro" (que
   mostraría de más).
4. **Doble carga inicial por la carrera entre `cargarTablas()` y
   `cargarPeriodos()`.** Ambas corren en paralelo (mismo patrón ya
   usado en el resto del archivo) — las dos tablas se crean antes de
   que `periodoActivoId` esté resuelto, así que su primer `ajax.data`
   puede salir sin `peri_id` (sin filtro). En cuanto `cargarPeriodos()`
   resuelve, se fuerza `ajax.reload(null, false)` de ambas tablas para
   corregir el estado. Es un round-trip HTTP extra en la carga inicial
   de la página (imperceptible en local), no un bug — el estado final
   siempre es correcto.

### Pruebas realizadas
`php -l` sin errores en los 3 archivos. Backend probado con `curl`
autenticado contra el contenedor Docker vivo: `listar_periodos`
confirma `peri_activo` presente (activo = `2026-2`, `peri_id=5`);
`listar_matriculados` confirma `peri_id` presente en las 17 filas;
filtrado por `peri_id=5` (activo) devuelve las 17 filas, todas
coincidentes; filtrado por `peri_id=3` (2026-1, anterior) devuelve 0
filas (sin datos históricos de matrícula todavía en local — esperable);
filtrado por `peri_id=0` (caso "sin anteriores") devuelve 0 filas, como
se busca; `listar_modulos_estudiante` con `estu_id`+`peri_id` explícitos
responde correctamente. **Verificado en navegador por Jose Luis: 6/6
pruebas pasaron** — pestañas con nombres correctos, período fijo
"2026-2" sin selector en "Per. Actual", filtro correcto en "Per.
Anteriores", columna "Semestre" igual en ambas pestañas, botón
"Módulos" funcionando en ambas con el período correcto de cada fila, y
refresco correcto de ambas tablas tras las 5 acciones (guardar
estudiante, subir foto, matricular, cerrar matrícula, editar
matrícula).

---

## [8884cb4] — 2026-09-02 — feat(estudiantes): agregar columna Semestre a tablaMatriculados

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Por qué
Primera pieza visible en la UI del trabajo de base sentado en `c985188`
(2026-09-02): `matriculas.matr_semestre` existía en el esquema y ya se
capturaba en los modales de matrícula, pero no se mostraba en ningún
listado todavía. Precedido de un diagnóstico de solo lectura (sin
commit) que confirmó que `tablaMatriculados` no usa `columnDefs` —
depende 100% del orden literal del array `columns` frente al
`<thead>` — y que `listar_matriculados` no tenía `matr_semestre` ni
`prog_duracion_semestres` en su `SELECT` pese a que el JOIN hacia
`programas` ya existía (se usa para `prog_sigla`).

### Cambios — Backend (`est_mdl.php`)
- `case 'listar_matriculados'`: agrega `p.prog_duracion_semestres` y
  `m.matr_semestre` al `SELECT` existente, junto a `p.prog_sigla` —
  mismo `INNER JOIN programas p ON m.prog_id = p.prog_id` ya usado,
  sin JOIN nuevo. Único consumidor de este `case` en todo el proyecto
  (confirmado por grep) — adición aditiva sin riesgo de romper otro
  llamador.

### Cambios — Frontend (`est_view.php` + `est_ctrl.js`)
- Nuevo `<th>Semestre</th>` en el `<thead>` de `tbl_matriculados`,
  entre "Programa" y "Período".
- Nueva columna en el array `columns` de `tablaMatriculados`, en la
  misma posición (entre `prog_sigla` y `peri_codigo`) — `width:
  '60px'`, tomado del espacio que ya absorbían las columnas sin
  `width` fijo (Nombres/Apellidos/Documento/Correo/Cohorte), sin
  comprimir ninguna columna con ancho explícito.
- `render()`: texto `"N/M"` (`row.matr_semestre`/
  `row.prog_duracion_semestres`), con fallback `'—'` si cualquiera de
  los dos es nulo/indefinido (mismo criterio que `coho_codigo`,
  extendido a dos campos). Resaltado suave
  (`background-color:#cfe2ff;padding:2px 6px;border-radius:4px;`,
  mismo estilo inline ya usado en la columna Edad) cuando
  `matr_semestre === prog_duracion_semestres` (último semestre del
  programa).

### Decisión — comparación con `==`, no `===`, en JS
PDO/MySQL devuelve columnas numéricas como strings en el JSON del
envelope — una comparación estricta (`row.matr_semestre ===
row.prog_duracion_semestres`) habría fallado silenciosamente
(`"5" === 5` es `false`), dejando el resaltado sin aplicarse nunca
aunque el dato fuera correcto. Se usó `==` deliberadamente, igual que
`== null` (en vez de `=== null`) para el chequeo de nulo, que cubre
`undefined` en un solo chequeo.

### Pruebas realizadas
`php -l` sin errores. Contra el contenedor Docker vivo, vía `curl`
autenticado (sesión de coordinador): `listar_matriculados` devuelve
`matr_semestre`/`prog_duracion_semestres` correctamente para las 17
filas actuales (ej. `CARRILLO PEREZ — ASO — matr_semestre: 1 —
prog_duracion_semestres: 4`). Verificación visual en navegador **no
realizada en la misma sesión del commit** — sin herramienta de
navegador disponible en ese momento (extensión de Chrome sin
conectar); se probaron temporalmente 2 filas reales (`matr_id=1` →
semestre `2/5`, `matr_id=5` → semestre `5/5`, para forzar ambas ramas
del `render()`) directamente en MySQL y se revirtieron de inmediato a
`1` sin dejar datos de prueba. **Confirmada posteriormente por Jose
Luis en navegador:** posición de la columna correcta (entre "Programa"
y "Período"), formato "N/M" correcto, resaltado `#cfe2ff` visible en
el último semestre, sin desplazamiento de las columnas siguientes
(Período/Módulos/Estado/Último acceso/Acciones).

---

## [c985188] — 2026-09-02 — feat(matriculas): agrega matr_semestre con validación y corrige badge de programas

### Archivos modificados
- database/emdb_academica.sql
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Por qué
Precede a la futura acción "avanzar de semestre" (matricular a un
estudiante ya matriculado a su siguiente semestre dentro del mismo
programa), motivada por un diagnóstico previo de solo lectura (sin
commit) sobre alternativas de diseño — nueva fila en `matriculas` por
semestre cursado vs. actualizar la fila existente. El diagnóstico
concluyó que el esquema ya soporta múltiples filas por
`estu_id`+`prog_id` (la `UNIQUE KEY uq_matr_estu_peri_prog` solo exige
`peri_id` distinto) y que el código ya tiene precedente para resolver
"la matrícula vigente" cuando hay varias filas por estudiante
(`ORDER BY matr_id DESC LIMIT 1`, ya usado en `pdf_hoja_matricula.php`
y `case 'obtener_completo'`) — la alternativa de nueva fila rompe
menos código existente. Este commit sienta la base de esquema y UI de
esa alternativa, sin implementar todavía la acción de avance en sí.

### Cambios — Esquema (`database/emdb_academica.sql`)
- Nueva columna `matriculas.matr_semestre` (`TINYINT UNSIGNED NOT NULL
  DEFAULT 1`) — semestre del programa que cursa esa matrícula (1-N).
  Aplicada primero como `ALTER TABLE` contra el contenedor Docker
  vivo, luego reflejada en el seed para que un import limpio quede
  coherente (mismo procedimiento ya usado en migraciones anteriores,
  ej. `3e551e5`).
- `matriculas.matr_estado` (ENUM) ampliado con `'cursado'` — **valor
  reservado, sin uso todavía**: ningún `case` de ningún `_mdl.php`
  del proyecto lo escribe (confirmado por grep). Quedará disponible
  para cuando se implemente "avanzar de semestre": la matrícula del
  semestre anterior transicionaría a este estado en vez de permanecer
  en `'matriculado'`.

### Cambios — Backend (`est_mdl.php`)
- `case 'matricular'` y `case 'editar_matricula'`: reciben
  `matr_semestre` por POST, validado contra
  `programas.prog_duracion_semestres` del `prog_id` de la matrícula
  (`SELECT` previo) — rechaza con `"Semestre inválido para este
  programa"` si es `< 1` o excede la duración. Si no llega por POST,
  se asume `1` explícitamente (no se deja que el `INSERT` dependa en
  silencio del `DEFAULT` de la columna sin pasar por la misma
  validación). Incluido en el `INSERT`, y en ambos `UPDATE` (la
  reactivación de una matrícula existente dentro de `'matricular'`, y
  el guardado normal de `'editar_matricula'`).
- `case 'obtener_matricula'` (alimenta el modal Editar Matrícula) y
  `case 'obtener_completo'` (modal Datos Estudiante): agregan
  `m.matr_semestre` al `SELECT`.
- `case 'listar_programas'`: agrega `prog_duracion_semestres` al
  `SELECT` — necesario para que el frontend sepa hasta qué semestre
  poblar el select, sin una llamada AJAX adicional.
- **Fix de bug preexistente** en `case 'listar_matriculados'`: la
  subconsulta de `total_matriculas_estudiante` (alimenta el badge
  "🎓 N programas", `1529832`) usaba `COUNT(*)` de filas de
  `matriculas` como proxy de "número de programas" — válido mientras
  cada `estu_id`+`prog_id` tenía como máximo una fila, pero
  `matr_semestre` permite ahora varias (una por semestre cursado del
  mismo programa), lo que habría inflado el conteo (ej. un estudiante
  en su 2º semestre del mismo programa habría mostrado "🎓 2
  programas"). Corregido a `COUNT(DISTINCT mt3.prog_id)` — mismo
  criterio ya usado en `programas_matriculados_activos`.

### Cambios — Frontend (`est_view.php` + `est_ctrl.js`)
- Select `#slct_matr_semestre` (modal Completar Matrícula) y
  `#slct_editar_matr_semestre` (modal Editar Matrícula), deshabilitados
  hasta seleccionar un programa.
- `cargarProgramas()` agrega `data-duracion="${prog_duracion_semestres}"`
  a cada `<option>` de programa — evita una llamada AJAX adicional
  para poblar el select de semestre.
- Nueva función global `poblarSelectSemestre(selectorDestino, duracion,
  valorSeleccionado)` (declarada fuera de `$(document).ready`, mismo
  motivo que `cargarCohortesPorPrograma`: la invocan tanto los
  listeners de `change` dentro de `ready` como `abrirMatricular()`/
  `abrirEditarMatricula()` en scope global) — genera las opciones
  `1..duracion`, preselecciona `1` por defecto o el valor recibido.
  Enganchada a los `change` de `#slct_prog_id`/`#slct_editar_prog_id`,
  al reset de `abrirMatricular()`/cierre del modal, y a la precarga de
  `abrirEditarMatricula()`.
- Ambos payloads de guardado (`btn_confirmar_matricula`,
  `btn_guardar_editar_matricula`) incluyen `matr_semestre`, con
  validación de campo vacío en cliente antes del `$.ajax`.

### Decisión — `obtener_completo` recibe la columna sin exponerla en UI
El modal "Datos Estudiante" (`#mdl_estudiante`) no es el modal editable
de matrícula — ese es "Editar Matrícula". Se agregó `m.matr_semestre`
al `SELECT` de `obtener_completo` porque el dato es igual de barato de
traer y puede ser útil para una fase futura, pero deliberadamente
**no** se renderiza en ningún lado del modal — mostrar "Semestre: N"
ahí se acerca a la columna de listado que queda fuera de alcance de
este commit.

### Pruebas realizadas
`php -l` sin errores (detectó y permitió corregir un error real: un
comentario SQL con comillas dobles cerraba prematuramente el string
PHP de `listar_matriculados` — corregido antes de tocar la BD).
Contra el contenedor Docker vivo, vía `curl` autenticado (sesión de
coordinador) + verificación directa en MySQL, con limpieza de los
datos de prueba al finalizar:
- Matricular sin enviar `matr_semestre` → `matr_semestre=1` guardado.
- Editar matrícula a semestre `5` en un programa de duración `4`
  (ASO) → rechazado con `"Semestre inválido para este programa"`,
  fila sin modificar (`matr_semestre` seguía en `1`, confirmando el
  `rollBack()`); edición válida posterior a semestre `3` sí se aplicó.
- Estudiante con 2 matrículas del mismo programa en períodos
  distintos (simulando un avance de semestre) → `listar_matriculados`
  devuelve `total_matriculas_estudiante=1` (antes del fix habría sido
  `2`).

---

## [e1c232d] — 2026-09-01 — feat(docentes): agrupa Acciones de tablaDocentes en dropdown con ícono de tuerca

### Archivos modificados
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js

### Por qué
Aplica a `tablaDocentes` (`03_docentes`) el mismo patrón de dropdown ya
usado en `02_estudiantes` (`tablaMatriculados`, commit `4fb2257`;
`tablaAspirantes`, commit `9346a76`). **Este caso es distinto de los 2
anteriores en una diferencia importante:** la columna "Acciones" de
`tablaDocentes` **nunca acumuló 3+ botones simultáneos** — siempre
tuvo exactamente 2 botones visibles a la vez ("Editar" fijo, más uno
solo de "Eliminar"/"Desactivar"/"Activar", mutuamente excluyentes
según `doce_activo`/`total_grupos_historico`; nunca los tres al mismo
tiempo). No cruzaba el umbral de "3+ acciones" documentado en
CLAUDE.md que originalmente motivó el patrón. Se aplicó igual por
**decisión explícita de Jose Luis de uniformidad visual** en toda la
aplicación — no por saturación de la columna. A diferencia de
Matriculados y Aspirantes (que sumaron un ítem nuevo — Hoja de
Matrícula / Ficha de Inscripción — antes solo accesible dentro de otro
modal), **aquí no se agrega ninguna funcionalidad nueva**: el dropdown
solo reagrupa visualmente las 2 acciones que ya existían como botones
en línea. Sin cambios de backend.

### Cambios
- **`doc_view.php`**: agrega el CDN de Bootstrap Icons (primera vez en
  este archivo — ya estaba cargado en `02_estudiantes/est_view.php`
  desde `4fb2257`) para el ícono `bi-gear-fill` del nuevo botón toggle.
- **`doc_ctrl.js`**: reemplaza los 2 botones en línea de la columna
  "Acciones" de `tablaDocentes` por un único botón de ícono
  (`bi-gear-fill`) que despliega un dropdown de Bootstrap
  (`dropdown-menu-end`, `data-bs-boundary="viewport"`) con 2 ítems:
  - **Editar** — mismo `onclick="abrirEditar(${row.doce_id})"`, sin
    cambio de comportamiento.
  - **Eliminar / Desactivar / Activar** — **la misma estructura
    `if/else if/else` sobre `row.doce_activo` y
    `row.total_grupos_historico` se preservó sin ningún cambio de
    lógica** — solo cambió el HTML generado en cada rama (de `<button>`
    en línea a `<li><button class="dropdown-item">`), conservando
    también el mismo cálculo de `nombreCompleto`
    (`.replace(/'/g, "\\'")`) y los mismos `onclick`
    (`confirmarEliminarDocente`, `toggleEstadoDocente(..., 0/1)`).

Columna "Acciones" reducida de `190px` a `70px`. No se tocó
`doc_mdl.php`, `abrirEditar()`, `confirmarEliminarDocente()`,
`toggleEstadoDocente()`, `validarFormulario()`, ni los modales
`#mdl_docente`/`#mdl_confirmar_eliminar_docente`.

### Pruebas realizadas (confirmadas por Jose Luis en navegador)
1. El botón de ícono (tuerca) abre y cierra el dropdown correctamente
   en cualquier fila de `tablaDocentes`.
2. "Editar" abre el modal `#mdl_docente` en modo edición, igual que
   antes del cambio.
3. Caso `doce_activo == 1 && total_grupos_historico == 0`: el dropdown
   muestra "Eliminar", y el flujo completo (modal de confirmación →
   DELETE) funciona igual que con el botón en línea anterior.
4. Caso `doce_activo == 1 && total_grupos_historico > 0`: el dropdown
   muestra "Desactivar", y el toggle de estado funciona igual que
   antes.
5. Caso `doce_activo == 0`: el dropdown muestra "Activar", y el toggle
   de estado funciona igual que antes.

---

## [9346a76] — 2026-09-01 — feat(estudiantes): agrupa Acción de Aspirantes en dropdown con ícono de tuerca

### Archivos modificados
- app/02_estudiantes/est_ctrl.js

### Por qué
Aplica a `tablaAspirantes` el mismo patrón de dropdown ya implementado en
`tablaMatriculados` (commits `53d5ec5`/`4fb2257`, entrada siguiente de
este archivo): la columna "Acción" tenía 2 botones en línea
("Matricular", "📝 Datos Estudiante"), y la Ficha de Inscripción
(AC-FO-02) solo era descargable abriendo el modal "Datos Estudiante" y
usando `#btn_ficha_inscripcion_pdf` — sin acceso directo desde la fila.
A diferencia del caso de Matriculados, aquí **no hizo falta ninguna
fase de backend**: el campo `estado_ficha` que el punto de color
necesita ya viajaba en cada fila de `listar_aspirantes` desde antes de
este cambio — toda la tarea se resolvió en un solo commit de frontend.

### Cambios
Reemplaza los 2 botones en línea de la columna "Acción" de
`tablaAspirantes` por un único botón de ícono (`bi-gear-fill`,
Bootstrap Icons — ya cargado en `est_view.php` desde `4fb2257`) que
despliega un dropdown de Bootstrap (`dropdown-menu-end`,
`data-bs-boundary="viewport"`) con 3 acciones:
- **Matricular** — mismo `onclick="abrirMatricular(${row.estu_id})"`
  que tenía como botón en línea, sin cambio de comportamiento. Sin
  `title` (tampoco lo tenía antes).
- **📝 Datos Estudiante** — mismo `onclick="abrirEditar(${row.estu_id},
  true)"` (`esAspirante = true`, sin cambio). El punto de color de
  `estado_ficha` se conserva dentro del `dropdown-item`, recalculado
  localmente en este mismo `render()` — declaración independiente,
  **no compartida** con la copia de `tablaMatriculados` (mismo criterio
  ya documentado en CLAUDE.md: reutilizable dentro de una fila, no
  entre tablas).
- **🖨️ Ficha de Inscripción** — ítem **NUEVO**. Antes, la única forma de
  descargar `pdf_ficha.php` era abrir el modal "Datos Estudiante" y usar
  `#btn_ficha_inscripcion_pdf` (visible solo si `estado_ficha ===
  'completa'`). El nuevo ítem del dropdown es un acceso directo de un
  clic — mismo endpoint, mismo parámetro `estu_id` —, **sin esa
  validación en frontend** (decisión explícita, Opción A: mismo
  criterio ya aplicado a "Hoja de Matrícula" en `tablaMatriculados`,
  aunque la condición `estado_ficha` sí estaba disponible en `row`).

Columna "Acción" reducida de `300px` a `70px`. No se tocó
`tablaMatriculados`, `est_mdl.php`, `est_view.php`, ni
`eliminar_aspirante`.

### Pruebas realizadas (confirmadas por Jose Luis en navegador)
1. El botón de ícono (tuerca) abre y cierra el dropdown correctamente
   en cualquier fila de `tablaAspirantes`.
2. "Matricular" abre el modal `#mdl_matricular` igual que antes del
   cambio, sin diferencia de comportamiento.
3. "📝 Datos Estudiante" abre el modal `#mdl_estudiante` en modo
   edición, con el mismo `title` (estado de ficha) que mostraba el
   botón en línea anterior.
4. El punto de color junto a "📝 Datos Estudiante" refleja
   correctamente los 3 estados de `estado_ficha` (sin_iniciar,
   incompleta, completa) probados con distintos aspirantes.
5. "🖨️ Ficha de Inscripción" descarga el PDF correcto para un
   aspirante con ficha **completa**.
6. "🖨️ Ficha de Inscripción" **también** descarga el PDF para un
   aspirante con ficha **incompleta** — comportamiento esperado y
   deliberado (Opción A, sin bloqueo en frontend), verificado
   explícitamente como caso de prueba y no como hallazgo.
7. `tablaMatriculados` sin ningún cambio visual ni de comportamiento
   tras el cambio.

---

## [53d5ec5, 4fb2257] — 2026-08-31 — feat(estudiantes): deshabilita "Matricular en otro programa" sin programas activos disponibles + agrupa Acción de Matriculados en dropdown

### Archivos modificados
- app/02_estudiantes/est_mdl.php (commit `53d5ec5`)
- app/02_estudiantes/est_ctrl.js (commit `4fb2257`)
- app/02_estudiantes/est_view.php (commit `4fb2257`)

### Por qué
Tarea de 2 fases (backend primero, frontend después) sobre la misma
columna "Acción" de `tablaMatriculados`: (1) el botón "➕ Matricular en
otro programa", agregado en `f5c21e5` (2026-08-30), no tenía ninguna
forma de indicar al coordinador que un estudiante ya cursaba todos los
programas activos del sistema — el botón quedaba siempre habilitado
sin importar el caso; (2) con 4 acciones ya acumuladas en esa columna
(Datos Estudiante, Matrícula, Hoja de Matrícula, Matricular en otro
programa), la fila de botones en línea saturaba visualmente la tabla.
Ambos puntos se resolvieron en la misma ronda de trabajo, a pedido
explícito de Jose Luis.

### Cambios — commit `53d5ec5` (backend)
2 columnas aditivas nuevas en `case 'listar_matriculados'`
(`est_mdl.php`), junto a `total_matriculas_estudiante`/
`detalle_matriculas_estudiante`, sin tocar ningún JOIN, el `WHERE`
principal ni ninguna columna existente — la query sigue devolviendo
una fila por matrícula:
- `programas_activos_totales`: `(SELECT COUNT(*) FROM programas WHERE
  prog_activo = 1)` — mismo valor repetido en cada fila, sin relación
  con `estu_id`.
- `programas_matriculados_activos`: `COUNT(DISTINCT mt5.prog_id)`
  correlacionado por `e.estu_id`, con `mt5.matr_estado = 'matriculado'
  AND p3.prog_activo = 1` — replica **exactamente** la misma condición
  de `matr_estado` ya usada en los cases `matricular` y
  `editar_matricula` (solo `'matriculado'`, sin incluir
  aspirante/retirado/graduado), para no introducir una regla de
  negocio distinta a la ya validada en el backend de matrícula.

Preparación pura para el frontend — sin ningún consumidor todavía en
este commit.

### Cambios — commit `4fb2257` (frontend)
Reemplaza los 3 botones en línea de la columna "Acción" de
`tablaMatriculados` (📝 Datos Estudiante, ✏️ Matrícula, ➕ Matricular en
otro programa) por un único botón de ícono (`bi-gear-fill`, Bootstrap
Icons 1.11.3 vía CDN jsdelivr — nuevo `<link>` en `est_view.php`) que
despliega un dropdown de Bootstrap (`dropdown-menu-end`,
`data-bs-boundary="viewport"` para no recortarse en las últimas filas)
con 4 acciones:
- **📝 Datos Estudiante** y **✏️ Matrícula** — mismo `onclick`/`title`
  que tenían como botones en línea, sin cambio de comportamiento. El
  punto de color de `estado_ficha` se conserva dentro del
  `dropdown-item`, recalculado localmente en el mismo `render()` — sin
  compartirse con `tablaAspirantes` (no tocada en este commit).
- **🖨️ Hoja de Matrícula** — ítem **NUEVO**. Antes, la única forma de
  descargar este PDF (`pdf_hoja_matricula.php`, AC-FO-09) era abrir el
  modal "Datos Estudiante" y usar el botón `#btn_hoja_matricula_pdf`
  (visible solo si `matr_estado === 'matriculado'`). El nuevo ítem del
  dropdown es un acceso directo de un clic, **sin esa validación en
  frontend** (decisión explícita) — mismo endpoint, mismo parámetro
  `estu_id`.
- **➕ Matricular en otro programa** — mismo `onclick` que ya tenía,
  ahora **deshabilitado con tooltip** cuando
  `programas_matriculados_activos >= programas_activos_totales`
  (columnas agregadas en `53d5ec5`). Al ser un `<button disabled>`
  dentro de un dropdown, el `title` no dispara por sí solo — se
  envuelve en un `<li tabindex="0" title="...">` (patrón estándar de
  Bootstrap para tooltips sobre elementos deshabilitados) con el texto
  "Este estudiante ya está matriculado en todos los programas activos
  disponibles.".

Columna "Acción" reducida de `360px` a `70px` (un solo control visible
en vez de 3 botones en línea). `tablaAspirantes` y la columna
`total_modulos` no se tocaron.

### Pruebas realizadas
- Dropdown abre/cierra correctamente en filas de cualquier posición de
  la tabla (incluida la última visible, sin recorte por el contenedor).
- "📝 Datos Estudiante" y "✏️ Matrícula" reproducen el comportamiento
  anterior sin cambios.
- "🖨️ Hoja de Matrícula" descarga el PDF correcto para un estudiante
  matriculado.
- "➕ Matricular en otro programa" aparece deshabilitado con el tooltip
  esperado para un estudiante ya matriculado en todos los programas
  activos (caso real: ASO + MD) y habilitado para el resto.
- `tablaAspirantes` sin ningún cambio visual ni de comportamiento.

---

## [1529832] — 2026-08-31 — feat(estudiantes): indicador visual "N programas" en Matriculados

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js

### Por qué
Cierra la última pieza pendiente del ciclo de matrícula a segundo
programa. El commit `f5c21e5` (2026-08-30) implementó toda la
funcionalidad de fondo (botón "➕ Matricular en otro programa",
validaciones de duplicado, fix de `total_modulos` cruzado entre
programas) pero evaluó y descartó explícitamente, en su sección
"Decisiones", agregar un indicador visual tipo "2 programas" en los
listados — cada matrícula seguía apareciendo como una fila
independiente sin nada que las conectara visualmente como la misma
persona. Retomado en esta ronda a pedido explícito de Jose Luis.

### Cambios — 2 columnas nuevas en `case 'listar_matriculados'`
- `total_matriculas_estudiante`: `COUNT(*)` correlacionado **solo por
  `estu_id`**, deliberadamente sin filtrar por `prog_id`/`peri_id` de
  la fila — a propósito lo opuesto de `total_modulos`, que `f5c21e5`
  corrigió para sí filtrar por la matrícula específica de cada fila.
  Aquí el indicador necesita justo lo contrario: reflejar cuántas
  matrículas tiene la persona **en total**, sin importar el programa
  de la fila actual. Documentado con un comentario SQL inline
  explicando la distinción, para que no se confunda con el mismo
  antipatrón que motivó ese fix.
- `detalle_matriculas_estudiante`: `GROUP_CONCAT` con formato
  `"SIGLA — Estado"` de todas las matrículas del estudiante (cualquier
  `matr_estado`), ordenado por `matr_id`.

### Cambios — badge en `tablaMatriculados`
- Badge `🎓 {N} programas` junto al apellido, visible solo cuando
  `total_matriculas_estudiante > 1`, con el detalle agregado como
  `title` (tooltip).
- `tablaAspirantes` queda **sin ningún cambio, confirmado por
  diseño** — no es un olvido: ese `case` (`listar_aspirantes`) no se
  tocó, verificado que no trae ninguna de las 2 columnas nuevas. Un
  aspirante no tiene aún una segunda matrícula que este indicador
  pudiera mostrar.

### Decisión — detalle agregado en la misma query, sin endpoint nuevo
Se evaluaron 2 alternativas para poblar el `title` del badge: (a) un
segundo llamado AJAX por fila con 2+ programas, reutilizando el `case
'matriculas_estudiante'` ya existente desde `f5c21e5`; (b) ampliar
`listar_matriculados` con el detalle agregado vía `GROUP_CONCAT`. Se
eligió (b) — evita N llamadas HTTP adicionales (una por fila con 2+
programas) y es igual de simple de implementar que (a). No se creó
ningún endpoint nuevo; `matriculas_estudiante` queda intacto.

### Pruebas realizadas
- `curl` sobre `listar_matriculados`: `estu_id=12` (2 matrículas
  reales, MD + ASO) devuelve `total_matriculas_estudiante=2` en ambas
  filas, con `detalle_matriculas_estudiante="MD — Matriculado, ASO —
  Matriculado"`; el resto de estudiantes (15 de 17 filas, 1 matrícula)
  devuelve `1`.
- `curl` sobre `listar_aspirantes`: confirmado que no trae ninguna de
  las 2 columnas nuevas — el `case` no se tocó.
- Verificación en navegador (6 puntos): badge visible solo en las 2
  filas de ESTEBAN CARRILLO (`estu_id=12`) con el tooltip correcto;
  sin badge en el resto de Matriculados; sin ningún cambio visual en
  Aspirantes; resto de columnas de Matriculados (`total_modulos`,
  ficha, botones de acción) sin regresiones.

---

## [f5c21e5] — 2026-08-30 — feat(estudiantes): matrícula a segundo programa + fix de total_modulos cruzado entre programas

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js
- app/02_estudiantes/est_view.php

### Por qué
Cierra el ciclo abierto por `3e551e5` (esquema: `matriculas.coho_id` +
`matr_estado_academico`), que ya permitía múltiples matrículas por
estudiante a nivel de esquema/backend pero sin ninguna UI pensada para
ello. Este commit agrupa todo el trabajo de ese ciclo — nunca se comiteó
el paso intermedio por separado — cubriendo tanto la funcionalidad nueva
como dos bugs reales descubiertos en el camino (uno preexistente desde
antes de `3e551e5`, otro introducido por la funcionalidad nueva misma).

### Funcionalidad nueva — matricular a un segundo programa
- Botón **"➕ Matricular en otro programa"** en la columna de acciones de
  `tablaMatriculados`, junto a "📝 Datos Estudiante" y "✏️ Matrícula" —
  reutiliza `abrirMatricular()`/`#mdl_matricular` ya existente, sin
  duplicar modal ni lógica.
- **Contexto de matrículas previas** dentro de `#mdl_matricular`: nuevo
  bloque `#bloque_matriculas_previas` (oculto por defecto) que lista los
  programas ya matriculados del estudiante al abrir el modal — visible
  solo si el estudiante ya tiene 1+ filas en `matriculas` (el caso más
  común, aspirante sin ninguna matrícula, no cambia).
- **Sección "Programas matriculados"** en el modal "Datos Estudiante"
  (`#mdl_estudiante`), junto a los datos básicos: lista cada matrícula
  como `"{prog_sigla} — {Estado} (dd/mm/yyyy)"`.
- Nuevo `case 'matriculas_estudiante'` en `est_mdl.php` (roles 1/2):
  devuelve todas las matrículas de un `estu_id` (`matr_id, prog_nombre,
  prog_sigla, peri_codigo, matr_estado, matr_estado_academico,
  fechamatricula`) — alimenta tanto el contexto del modal de matrícula
  como la sección del modal de datos del estudiante.
- `case 'obtener_completo'` ampliado con un array `matriculas` (misma
  estructura) sin tocar el campo `matr_estado` ya existente (que sigue
  resolviendo la más reciente vía `LIMIT 1`, usado tal cual por el botón
  de Hoja de Matrícula).

### Validaciones nuevas — bloqueo de programa duplicado
- `case 'matricular'`: si el estudiante ya tiene una fila `'matriculado'`
  con el mismo `prog_id` pero **otro** `peri_id`, bloquea antes de tocar
  la lógica de reactivación exacta (`$existingMatr`) con el mensaje "El
  estudiante ya está matriculado en este programa. Use Editar Matrícula
  si desea modificar el período o la cohorte."
- `case 'editar_matricula'`: misma validación, usando `matr_id != ?` en
  vez de `peri_id != ?` (este case sí recibe `matr_id` explícito) — evita
  que una edición deje a un estudiante con 2 matrículas del mismo
  programa.

### Fix — `eliminar_aspirante` no determinista
El chequeo usaba `fetch()` de una sola fila **sin `ORDER BY`** sobre
`matriculas WHERE estu_id = ?` — con 2+ filas (ej. una `'aspirante'` de
un segundo programa y otra `'matriculado'` ya activa), el resultado
dependía del orden arbitrario que devolviera MySQL: a veces bloqueaba con
el mensaje correcto, a veces dejaba pasar la primera fila `'aspirante'`
y fallaba después por el `RESTRICT` de la FK con el mensaje genérico.
Corregido con `fetchAll()` + `foreach` que bloquea si **cualquier** fila
no es `'aspirante'`, y borra **todas** las filas `'aspirante'` (antes solo
borraba una) antes del `DELETE` de `estudiantes`.

### Fix — `total_modulos` contaba módulos de todos los programas del estudiante
Bug real reportado por Jose Luis: un estudiante con 2 matrículas (MD +
ASO) mostraba el **mismo** conteo de módulos en ambas filas de
Matriculados, porque la subconsulta de `total_modulos`
(`listar_matriculados`) y la query de `listar_modulos_estudiante`
(modal de detalle de esa misma columna) correlacionaban **solo por
`estu_id`**, sin cruzar contra el `prog_id`/`peri_id` de la matrícula
específica de la fila.
- `listar_matriculados`: subconsulta ahora exige `gs3.prog_id = m.prog_id`
  (la matrícula de la fila actual); se agrega `m.prog_id AS matr_prog_id`
  al `SELECT` — alias **distinto** de `fi.prog_id` (ya existente, de
  `fichas_inscripcion`, usado por `calcularEstadoFicha()`) para evitar
  una colisión silenciosa de claves duplicadas en `FETCH_ASSOC`.
- `listar_modulos_estudiante`: nuevo parámetro opcional `prog_id` por
  POST; si viene, filtra por `gs.prog_id`; si no, mantiene el
  comportamiento anterior (compatibilidad hacia atrás — confirmado por
  grep que solo hay un llamador en todo el proyecto).
- `est_ctrl.js`: `verModulosEstudiante()` recibe y propaga
  `row.matr_prog_id` desde el botón de `total_modulos` en
  `tablaMatriculados`.

### Decisiones
- No se agregó ningún indicador visual tipo "2 programas" en el listado
  de Matriculados/Aspirantes — cada matrícula sigue apareciendo como una
  fila independiente (comportamiento ya existente, sin cambios); se
  evaluó y se descartó agregar ese indicador en esta ronda.
- El alias `matr_prog_id` (en vez de `prog_id` a secas) fue una decisión
  necesaria, no cosmética — evitar la colisión con `fi.prog_id` ya
  presente en el mismo `SELECT`.

### Pruebas realizadas
- `php -l` sin errores en los 2 archivos PHP modificados en cada etapa de
  este ciclo.
- **Matrícula a segundo programa**: probado con `curl` sobre `estu_id=2`
  — intento de duplicar el mismo programa con otro período bloqueado con
  el mensaje nuevo (sin crear fila); matrícula en programa distinto
  creada correctamente (segunda fila); `matriculas_estudiante` y
  `obtener_completo` devolviendo el array completo; limpieza de la fila
  de prueba tras verificar.
- **`eliminar_aspirante`**: probado con 2 fixtures — estudiante con 2
  filas `'aspirante'` (elimina ambas + el estudiante) y estudiante con 1
  `'aspirante'` + 1 `'matriculado'`, con el orden de `fetch()` de MySQL
  confirmado devolviendo la fila `'aspirante'` primero (el escenario que
  rompía el código viejo) — bloqueado con el mensaje específico correcto,
  nada eliminado.
- **`editar_matricula` duplicado**: fixture con una segunda matrícula
  `'matriculado'` para `estu_id=2`, intento de editar la primera hacia
  ese mismo programa → bloqueado, sin cambios; fixture eliminada después.
- **`total_modulos`**: verificado contra el caso real reportado
  (`estu_id=12`, matriculado en MD y ASO) — antes ambas filas mostraban
  `total_modulos=2`; después, ASO muestra `0` y MD sigue en `2`.
  `listar_modulos_estudiante` con `prog_id=1` (ASO) devuelve `data: []`;
  con `prog_id=2` (MD) devuelve los 2 módulos reales; sin `prog_id`
  (compatibilidad hacia atrás) mantiene el comportamiento anterior.
- HTTP 200 sin warnings/notices en `est_view.php` para rol coordinador en
  cada etapa. Base de datos verificada de vuelta a su estado original
  (16 filas en `matriculas`) después de cada tanda de fixtures de prueba.

---

## [8d479a1] — 2026-08-30 — feat(reportes): combina Reporte por Grupo + Reporte por Estudiante en pestañas (Fase 3 de 3, FINAL)

### Archivos modificados
- app/06_reportes/reportes_view.php
- app/06_reportes/reportes_ctrl.js

### Por qué
Cierra el rediseño de `06_reportes` iniciado en `b3a0296`/`ef429bd`: el
estudiante necesitaba un informe organizado por programa → período →
módulo (en vez de "un módulo a la vez"), y el coordinador necesitaba poder
consultar el reporte de cualquier estudiante (no solo el propio). El plan
original de la Fase 3 reemplazaba por completo la interfaz "Reporte por
Grupo" del coordinador (consulta de todos los estudiantes de un grupo
módulo) con la nueva "Reporte por Estudiante" — un paso intermedio dentro
de este mismo ciclo de trabajo. **A pedido explícito de Jose Luis, ese
reemplazo total se corrigió en el mismo commit final: el resultado
publicado combina AMBAS interfaces como dos pestañas de nivel superior
(solo para roles 1/2), no un reemplazo de una por la otra.**

### Cambios
- **Estudiante (role_id=4):** sin pestañas de nivel superior — pasa
  directo al informe propio (institución + nombre del estudiante resuelto
  server-side + pestañas de programa → período → módulos), igual que en
  el diseño original de esta fase.
- **Coordinador/admin (role_id 1/2):** dos pestañas Bootstrap de nivel
  superior — `Reporte por Grupo` (activa por defecto) y
  `Reporte por Estudiante`.
  - `Reporte por Grupo`: HTML y JS **recuperados tal cual** del commit
    `ef429bd` (anterior a esta fase) — `sel_grupo`, `btn_cargar_reporte`,
    `info_reporte_grupo`, `tbl_reporte` con DataTable + exportación
    Excel/PDF (`cargarGrupos()`, `inicialDocente()`,
    `construirContextoTexto()`, `escaparXml()`,
    `mostrarInfoReporteGrupo()`, `construirFilaReporte()`), sin modificar
    su lógica interna.
  - `Reporte por Estudiante`: buscador de estudiante (debounce 300ms,
    mínimo 3 caracteres, vía `buscar_estudiante`) → `mis_programas` →
    pestañas de programa → `mis_periodos` → select de período (más
    reciente preseleccionado automáticamente) → `detalle_periodo` →
    "Estado del Período" + botón "Descargar Boletín PDF" + tarjetas de
    módulo (11 columnas, reutilizando `badgeNotaFinal()`,
    `badgeDefinitiva()`, `badgeEstado()`, `n()` ya existentes).
  - Nuevos helpers de esta fase: `badgeEstadoAcademico()` (badge de
    `matr_estado_academico`), `badgeEstadoPeriodo()` (mismo esquema de
    color que `badgeEstado()`, aplicado al estado agregado del período que
    ya calcula el backend), `calcularPeriodosRealizados()` (fórmula
    `total_períodos - 1`, "en curso" si da 0).
  - `<link>`/`<script>` de DataTables + Buttons + JSZip reintroducidos,
    gateados por `$es_coordinador` — a diferencia de `ef429bd` (donde el
    DataTables base no estaba gateado), aquí sí se gatea todo el bloque
    porque el estudiante ya no usa ninguna tabla DataTable en el diseño
    final.

### Decisiones
- Cero funciones JS duplicadas: verificado por grep que cada una de las
  19 funciones del archivo final aparece una sola vez; los helpers
  compartidos (`n()`, `badgeNotaFinal()`, `badgeDefinitiva()`,
  `badgeEstado()`) se reutilizan dentro de `construirFilaReporte()`
  (Reporte por Grupo) sin copiarlos de nuevo.
- El nombre del estudiante en el título de "Reporte por Estudiante" se
  resuelve con una consulta SQL directa en la vista cuando `role_id=4`
  (mismo criterio que la resolución server-side de `institucion_nombre`
  ya usada desde `b3a0296`) — evita un endpoint AJAX nuevo solo para un
  dato ya disponible del lado servidor vía `$_SESSION['estu_id']`.
- No se tocó `navbar.php`: el estudiante sigue con su `<nav>` fallback
  propio (mismo patrón ya usado para Docente en `calificaciones_view.php`)
  en vez de ampliar `navbar.php` con enlaces condicionados por rol —
  cambio de arquitectura compartida entre módulos, fuera de alcance de
  este rediseño puntual de `06_reportes`.
- `reportes_mdl.php` no se tocó en esta fase — los 4 `case` viejos
  (`grupos_para_reporte`, `reporte_grupo` siguen con llamador activo desde
  "Reporte por Grupo"; `mis_modulos` y `mis_notas` quedan sin llamador
  desde este frontend, ver "Análisis pendiente" en PROJECT_CONTEXT.md).

### Pruebas realizadas
- `php -l` sin errores en `reportes_view.php`.
- Balance de `{}`/`()`/`[]`/backticks verificado en `reportes_ctrl.js` tras
  cada edición — sin desbalances.
- HTTP 200 sin warnings/notices/deprecated para ambos roles (`curl` con
  sesión simulada).
- Confirmado por grep sobre el HTML servido: el estudiante recibe 0
  ocurrencias de `tabsReportesNivelSuperior`; el coordinador recibe
  exactamente 1 ocurrencia de `tab_reporte_grupo`, `tab_reporte_estudiante`,
  `sel_grupo` y `bloque_buscador_estudiante` (sin duplicación).
- Balance de `<div>`/`</div>` en el HTML renderizado para coordinador:
  38/38.
- Verificación funcional de la interacción entre pestañas (cambiar de
  pestaña, exportar Excel/PDF de "Reporte por Grupo", flujo completo de
  "Reporte por Estudiante" incluyendo búsqueda de un estudiante ajeno)
  dejada explícitamente para pruebas en navegador por Jose Luis.

---

## [ef429bd] — 2026-08-30 — feat(reportes): reescribe pdf_boletin.php a boletín por período (Fase 2 de 3)

### Archivos modificados
- app/06_reportes/pdf_boletin.php

### Por qué
`pdf_boletin.php` generaba el boletín de UN SOLO módulo (`fetch()`,
variables escalares, filtro `WHERE grmo_id = ? AND usua_id = ?`) — diseño
incompatible con el nuevo informe por período de la Fase 3. Se reescribió
por completo para generar el boletín de TODOS los módulos de un período
específico, de un programa específico, de un estudiante específico.

### Cambios
- Parámetros por GET: `matr_id` + `peri_id` (antes `grmo_id`).
- Resolución del estudiante objetivo con el mismo criterio ya usado en
  `reportes_mdl.php` (Fase 1): `role_id=4` siempre es
  `$_SESSION['estu_id']`; roles 1/2 aceptan un `estu_id` adicional por GET
  (coordinador generando el boletín de otro estudiante). Cualquier otro
  `role_id`, o un `matr_id` que no pertenezca al estudiante resuelto:
  `403` genérico — mismo criterio ya documentado para este archivo (no se
  distingue "no existe" de "es de otro estudiante").
- Encabezado institucional leído de `configuracion.institucion_nombre`
  (columna nueva de la Fase 1) en vez de hardcodeado — único de los 4
  archivos PDF del proyecto que ya lo hace así (`pdf_grupo.php`,
  `pdf_ficha.php`, `pdf_hoja_matricula.php` siguen con el hardcodeo, ver
  "Análisis pendiente" en PROJECT_CONTEXT.md).
- Módulos del período traídos con `fetchAll()` (antes `fetch()`), misma
  ruta SQL que `detalle_periodo` (`reportes_mdl.php`).
- "Estado del período" (En Curso / Aprobado / Reprobado + promedio)
  replicado del mismo cálculo de `detalle_periodo` — no se reinventó una
  fórmula distinta.
- Layout: una tabla `.contexto-modulo` (Módulo/Grupo/Docente, celdas
  físicas sin `colspan` — evita el antipatrón de Dompdf ya documentado en
  CLAUDE.md) + una tabla `.ficha` de 11 columnas (N1, Sup N1, N2, Sup N2,
  N3, N4, Sup N4, **Habilitación**, Nota Final, Definitiva, Estado) por
  módulo, apiladas en `foreach`. Tamaño de página sin cambios
  (`letter`/`portrait`).
- Nombre de archivo: `boletin_{apellidos-sin-tildes-ni-espacios}_{periodo}.pdf`.
- **Ajuste posterior (Fase 2b, mismo commit):** la columna "Habilitación"
  se había omitido en la primera versión de la tabla `.ficha` — se
  recuperó entre "Sup N4" y "Nota Final" (mismo orden que tenía la versión
  de un solo módulo, sin `colorSemaforo()`, igual que antes), con el CSS
  de la tabla ajustado (`table-layout: fixed`, `width: 9.09%`, fuente
  8.5px) para que las 11 columnas siguieran cabiendo en Carta/portrait.

### Bug encontrado y corregido en el camino
Al probar el PDF, `institucion_nombre` salía doble-codificado en UTF-8
(`MecÃ¡nica`, `BolaÃ±os`). Causa: el `ALTER`/`UPDATE` de la Fase 1 se
ejecutó vía `mysql -u root` sin forzar `--default-character-set=utf8mb4`
— el cliente usó `latin1` por defecto y MySQL reinterpretó/re-codificó los
bytes UTF-8. Corregido re-ejecutando el `UPDATE` con el charset correcto,
verificado con `HEX()` antes/después. **Diagnóstico aparte confirmó que
`director_nombre`/`secretario_nombre` (mismos campos de `configuracion`)
NO tenían este problema** — sus bytes decodifican correctamente como
UTF-8 simple; lo que parecía corrupción era solo un artefacto de
visualización en una terminal sin el charset correcto, no un problema del
dato en reposo. No se modificaron esos dos campos.

### Decisiones
- Se sigue mostrando `Habilitación` sin `colorSemaforo()` (igual que la
  versión de un solo módulo) — solo Nota Final y Definitiva llevan
  semáforo de color.
- No se tocaron `pdf_grupo.php`, `pdf_ficha.php` ni `pdf_hoja_matricula.php`
  — siguen con el nombre institucional hardcodeado, fuera de alcance de
  esta fase.

### Pruebas realizadas
- `php -l` sin errores.
- 2 PDFs generados y verificados con `pdfinfo`/`pdftotext` contra el
  contenedor vivo: un caso con módulo `Aprobado` (definitiva 4.0,
  "Estado del Período: Aprobado (Promedio: 4.0)") y un caso con módulo
  `En Curso` (coordinador consultando a otro estudiante) — ambos en 1
  página, sin desborde, con las 11 columnas legibles tras el ajuste de
  Fase 2b.
- Verificación de integridad del encoding: `HEX()` antes/después de la
  corrección de `institucion_nombre`.

---

## [b3a0296] — 2026-08-30 — feat(reportes): backend de reportes por período/programa (Fase 1 de 3)

### Archivos modificados
- database/emdb_academica.sql
- app/06_reportes/reportes_mdl.php

### Por qué
Primer paso del rediseño de `06_reportes`: antes de tocar ninguna vista,
se construyó y verificó por separado el backend necesario para el nuevo
informe por programa → período → módulo, y la fuente de datos para
centralizar el nombre institucional en los PDFs (hasta entonces
hardcodeado en 4 archivos distintos).

### Cambios de esquema
- `configuracion` gana `institucion_nombre VARCHAR(150) NOT NULL DEFAULT
  'Escuela de Mecánica Dental Bolaños (EMDB)'` (mismo patrón que
  `director_nombre`/`secretario_nombre`) — `ALTER TABLE` + `UPDATE`
  aplicados en vivo contra el contenedor Docker, y agregada a
  `database/emdb_academica.sql` en el mismo lugar (`AFTER config_id`).

### Cambios de código — 4 `case` nuevos en `reportes_mdl.php`
- Helper `resolverEstuIdObjetivo()`: `role_id=4` → siempre
  `$_SESSION['estu_id']` (ignora cualquier `estu_id` del POST); roles 1/2
  → `estu_id` recibido por POST (obligatorio); cualquier otro rol → `null`.
  Reutilizado por los 4 `case` nuevos y, después, por `pdf_boletin.php`
  (Fase 2).
- `mis_programas`: matrículas del estudiante objetivo (`matr_id`,
  `prog_id`, `prog_nombre`, `prog_sigla`, `matr_estado`,
  `matr_estado_academico`, `coho_id`, `coho_codigo`), `ORDER BY matr_id
  ASC` — fuente de las pestañas de programa.
- `mis_periodos`: recibe `matr_id` (valida que pertenezca al estudiante
  objetivo), devuelve los períodos distintos con módulos asignados para
  el programa de esa matrícula, vía `grmoestudiantes → gruposmodulos →
  gruposemestres` (sin filtrar `grmo_activo` — histórico completo),
  `ORDER BY peri_anio DESC, peri_semestre DESC` (no existe un campo de
  orden explícito en `periodos`, se usa la combinación año+semestre, mismo
  criterio ya usado en `calificaciones_mdl.php`).
- `detalle_periodo`: recibe `matr_id` + `peri_id` (misma validación de
  pertenencia), devuelve los módulos de ese programa+período con notas +
  `estado_modulo` (misma lógica de 3 casos que `badgeEstado()` en
  `reportes_ctrl.js`, replicada en PHP vía `estadoModuloTexto()`) + el
  "Estado del Período" agregado (En Curso si algún módulo no tiene
  `cali_definitiva`; si todos la tienen, promedio aritmético simple decide
  Aprobado/Reprobado).
- `buscar_estudiante` (solo roles 1/2): `LIKE` sobre
  `estu_nombres`/`estu_apellidos`/`estu_numerodoc`, mínimo 3 caracteres,
  máximo 15 resultados.
- Los 4 `case` viejos (`grupos_para_reporte`, `reporte_grupo`,
  `mis_modulos`, `mis_notas`) quedaron intactos, sin tocar.

### Decisiones
- `mis_periodos`/`detalle_periodo` no filtran por `grmo_activo` — se
  quiere histórico completo de períodos cursados, activos o no.
- Validación de pertenencia (`matr_id` debe ser del estudiante objetivo)
  como `SELECT` previo en cada `case`, en vez de confiar en que el
  frontend solo pida lo suyo — mismo principio ya documentado en CLAUDE.md.

### Pruebas realizadas
- `php -l` sin errores.
- Los 4 `case` probados con `curl` contra el contenedor vivo (sesión
  simulada con `www-data` para que Apache pudiera leerla): casos felices
  (estudiante propio, coordinador consultando a otro estudiante) y casos
  borde (`estu_id` faltante para coordinador, `matr_id` ajeno rechazado
  con "Sin autorización sobre esta matrícula", estudiante sin ninguna
  matrícula → `data: []` sin error, módulo sin `cali_definitiva` →
  `"En Curso"`).
- `ALTER TABLE` + `UPDATE` de `configuracion` verificados con `DESCRIBE`
  y `SELECT` antes/después.

---

## [3e551e5] — 2026-08-30 — feat(matriculas): migra coho_id de estudiantes a matriculas + agrega matr_estado_academico

### Archivos modificados
- database/emdb_academica.sql
- app/02_estudiantes/est_mdl.php
- app/06_reportes/pdf_hoja_matricula.php
- app/04_grupos/grupos_mdl.php

### Por qué
Prepara el modelo de datos para que un estudiante pueda cursar más de
un programa a la vez — cada matrícula/programa con su propia cohorte y
su propia condición académica del semestre, en vez de una única
cohorte compartida a nivel de estudiante. Este cambio es solo la base
de datos/backend: la UI para gestionar un segundo programa (crear una
segunda matrícula, alternar entre programas en los listados, etc.)
queda pendiente para una fase futura — ver "Análisis pendiente" en
PROJECT_CONTEXT.md.

### Cambios de esquema
- `matriculas` gana dos columnas nuevas: `coho_id SMALLINT UNSIGNED
  DEFAULT NULL` (FK → `cohortes.coho_id`, `ON DELETE SET NULL`) y
  `matr_estado_academico ENUM('Activo','Aplazamiento','Retirado') NOT
  NULL DEFAULT 'Activo'`. `matr_estado_academico` es un campo
  independiente y nuevo — no reemplaza ni modifica `matr_estado`
  (ciclo de vida del trámite: aspirante/matriculado/retirado/graduado).
  `matr_estado_academico` es la condición académica del semestre en
  curso; `matr_estado` sigue siendo el ciclo de vida del trámite de
  matrícula. Documentado como comentario inline en el DDL.
- `estudiantes.coho_id` **no se eliminó** — queda como respaldo
  histórico de solo lectura, sin ninguna escritura nueva desde este
  commit (confirmado por grep: cero ocurrencias de `UPDATE estudiantes
  SET coho_id` en todo `app/`).
- Aplicado contra el contenedor Docker vivo con `ALTER TABLE` +
  backfill (`UPDATE matriculas m JOIN estudiantes e ON m.estu_id =
  e.estu_id SET m.coho_id = e.coho_id`), verificado 100% consistente:
  las 16 filas de `matriculas` coinciden con el `coho_id` del
  estudiante correspondiente (`SUM(m.coho_id <=> e.coho_id) =
  COUNT(*) = 16`).

### Cambios de código
- `est_mdl.php`: `listar_matriculados` lee la cohorte desde
  `m.coho_id` (antes `e.coho_id`); `matricular` inserta/actualiza
  `coho_id` en la propia fila de `matriculas` (INSERT y rama `UPDATE`
  de `$existingMatr`), elimina el `UPDATE estudiantes SET coho_id`
  que existía antes; `obtener_matricula` lee `m.coho_id` en vez de
  `e.coho_id`; `editar_matricula` agrega `coho_id = ?` al mismo
  `UPDATE matriculas` ya existente, elimina el `UPDATE estudiantes SET
  coho_id` aparte.
- `pdf_hoja_matricula.php`: el `JOIN` hacia `cohortes` pasa de
  `c.coho_id = e.coho_id` a `c.coho_id = m.coho_id`.
- `grupos_mdl.php`: el KPI `total_estudiantes` de `listar_cohortes`
  cuenta `matriculas.coho_id` en vez de `estudiantes.coho_id`; el
  guard de bloqueo de `DELETE` de cohorte (case `eliminar`) verifica
  `COUNT(*) FROM matriculas WHERE coho_id = ?` en vez de `estudiantes`.

### Hallazgo colateral: el seed no siembra datos transaccionales
Durante el ajuste posterior de sincronizar el `.sql` con el backfill
ya aplicado en vivo, se confirmó por grep que `database/emdb_academica.sql`
nunca tuvo ningún `INSERT INTO matriculas` (ni `estudiantes`, ni
`cohortes`, ni `gruposemestres`) — solo siembra esquema + catálogos
fijos (`roles`, `programas`, `periodos`, `modulos`, usuario admin,
`configuracion`). Las 16 filas de `matriculas` usadas para verificar
este backfill se crearon a través de la propia aplicación durante
desarrollo/pruebas, no desde el seed. Por lo tanto no había ningún
`INSERT` que sincronizar en el `.sql` — la inconsistencia potencial
("`down -v && up` pierde los datos de prueba") es preexistente al
proyecto completo, no algo introducido por esta migración. Ya
documentado como nota operativa en CLAUDE.md.

### Decisiones
- Se mantuvo `estudiantes.coho_id` sin eliminar (respaldo histórico),
  en vez de hacer el `DROP COLUMN` en el mismo commit — decisión
  explícita para reducir el radio de cambio de esta migración.
- `matr_estado_academico` se agregó al esquema con su default
  `'Activo'` pero **ningún endpoint todavía lo lee ni lo escribe** —
  fuera del alcance de este commit, que fue exclusivamente esquema +
  relocalización de `coho_id`.

### Pruebas realizadas
- `php -l` sin errores de sintaxis en los 3 archivos PHP modificados.
- `DESCRIBE matriculas` confirma las 2 columnas nuevas con el tipo/
  default correctos tras el `ALTER TABLE`.
- Verificación de backfill: consulta de muestra (5 filas) y consulta
  de integridad completa (16/16 filas coinciden con `estudiantes.coho_id`)
  contra el contenedor Docker vivo.
- Verificado en navegador y comiteado por Jose Luis como commit `3e551e5`.

---

## [b8a583a] — 2026-08-30 — fix(grupos): filtra grmo_activo=1 en total_modulos de listar_grupos (04_grupos)

### Archivos modificados
- app/04_grupos/grupos_mdl.php

### Cambios
- `grupos_mdl.php`, case `listar_grupos`: la subconsulta escalar
  `total_modulos` gana `AND gm.grmo_activo = 1`, alineándola con
  `total_estudiantes` (mismo case, agregada en el commit `a39cb24`) y
  con todos los demás conteos de `gruposmodulos` del proyecto que ya
  filtraban por activo (`listar_docentes` en el propio
  `grupos_mdl.php`, `doc_mdl.php`, `coordinador_mdl.php`,
  `calificaciones_mdl.php`, `reportes_mdl.php`).
- Cambio de una sola línea, sin tocar ningún otro archivo ni ningún
  otro case.

### Contexto del bug
Corrige la inconsistencia ya documentada en CLAUDE.md tras el commit
`a39cb24`: `total_modulos` contaba TODOS los `gruposmodulos` del grupo
semestre (activos e inactivos) sin que el nombre del campo lo
señalizara — a diferencia de `total_grupos_historico` (`doc_mdl.php`),
que sí distingue por nombre cuando la intención real es contar todo
sin filtrar. El diagnóstico previo (grep en todo el proyecto) confirmó
que este era el único caso de este patrón sin filtro no señalizado por
naming.

### Decisiones
- Se optó por corregir `total_modulos` para que sea consistente con
  `total_estudiantes` y con el resto de conteos de `gruposmodulos` del
  proyecto, en vez de dejarlo como comportamiento intencional — no
  había ningún indicio (naming, comentario) de que excluir el filtro
  fuera deliberado en este case.

### Pruebas realizadas
- Verificado contra datos reales en la base local: 0 `gruposmodulos`
  con `grmo_activo = 0` al momento del cambio, por lo tanto sin
  impacto visible en los números mostrados en este entorno.
- Pendiente de verificación en producción cuando aplique el deploy —
  no se descarta que allí sí existan `gruposmodulos` inactivos que
  hagan el número visible antes y después del fix.
- `php -l` sin errores de sintaxis tras el cambio.

---

## [a39cb24] — 2026-08-30 — feat(grupos): agrega columna "# Estud" en Grupos Semestre (04_grupos) con modal de detalle

### Archivos modificados
- app/04_grupos/grupos_mdl.php
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js

### Cambios
- `grupos_mdl.php`, case `listar_grupos`: nueva subconsulta escalar
  `total_estudiantes` agregada al `SELECT` junto a la ya existente
  `total_modulos` — `COUNT(DISTINCT ge.estu_id)` vía
  `grmoestudiantes` → `gruposmodulos`, filtrado por
  `gm.grmo_activo = 1`. El `DISTINCT` es necesario porque un mismo
  estudiante puede estar matriculado en más de un módulo del mismo
  grupo semestre — sin él, el conteo se infla por cada módulo
  adicional en el que aparece el mismo estudiante.
- `grupos_mdl.php`: nuevo case `listar_estudiantes_grupo` — recibe
  `grse_id` por POST, valida que sea un entero > 0, y retorna
  `SELECT DISTINCT e.estu_apellidos, e.estu_nombres` de los
  estudiantes matriculados en cualquier módulo activo
  (`gm.grmo_activo = 1`) de ese grupo semestre, ordenados por
  apellidos y luego nombres. Mismo guard de sesión/rol (`[1, 2]`) que
  el resto del módulo.
- `grupos_view.php`: nueva columna `# Estud` en el `<thead>` de
  `#tbl_grupos`, ubicada inmediatamente después de "Módulos". Nuevo
  modal `#mdl_estudiantes_grupo` (tamaño normal) con título dinámico
  = `grse_codigo`, tabla de una sola columna "Apellidos, Nombres" y
  mensaje "Sin estudiantes asignados" para el caso vacío.
- `grupos_ctrl.js`: nueva columna `total_estudiantes` en
  `tablaGrupos`, renderizada como botón `btn-outline-secondary` que
  invoca `verEstudiantesGrupo(grse_id, grse_codigo)`. Nueva función
  `verEstudiantesGrupo()` agregada en **scope global** (fuera de
  `$(document).ready`, junto a `cargarModulosGrupo()` y las demás
  funciones globales del archivo) — obligatorio porque se invoca vía
  `onclick` inline generado por DataTables. Replica exactamente el
  patrón ya usado en `verModulosEstudiante()` de `02_estudiantes`
  (columna con conteo + link + modal de detalle).

### Decisiones
- Se filtró explícitamente `gm.grmo_activo = 1` en ambas queries
  nuevas (`total_estudiantes` y `listar_estudiantes_grupo`),
  consistente con el patrón ya usado en `est_mdl.php` para contar
  módulos de un estudiante (`listar_modulos_estudiante` /
  `total_modulos` de ese archivo). Esto se decidió en vez de replicar
  la omisión de ese mismo filtro que tiene `total_modulos` en el
  propio case `listar_grupos` de `grupos_mdl.php` — esa inconsistencia
  preexistente queda documentada como observación en "Análisis
  pendiente" de CLAUDE.md, sin corregirse en este commit por estar
  fuera de alcance.

### Pruebas realizadas
- Verificado en navegador: columna "# Estud" visible en la pestaña
  Grupos Semestre ✅, modal abre con el listado correcto sin
  estudiantes duplicados (grupo con estudiantes en más de un módulo)
  ✅, caso de grupo sin estudiantes muestra el mensaje "Sin
  estudiantes asignados" ✅, sin errores en consola del navegador ✅.

---

## [0e0a508] — 2026-08-29 — fix(grupos): corrige truncamiento SQL de grse_codigo/coho_codigo con siglas de programa largas

### Archivos modificados
- database/emdb_academica.sql
- app/04_grupos/grupos_mdl.php
- app/04_grupos/grupos_view.php

### Bug reportado
Al crear un "Nuevo Grupo Semestre" para el programa ASO2016 (sigla de
7 caracteres, más larga que las siglas originales ASO/MD de 3), el
sistema fallaba con `SQLSTATE[22001]: String data, right truncated:
1406 Data too long for column 'grse_codigo' at row 1`. No era un caso
límite ocasional: la fórmula de autogeneración de `grse_codigo`
(`{prog_sigla}_{peri_codigo}_S{semestre}_{jornada_abrev}`) consume
13-14 caracteres fijos, dejando solo 6 de margen para la sigla bajo el
`VARCHAR(20)` original — cualquier grupo semestre creado para ASO2016 o
AMD2016 (7 caracteres cada una) fallaba de forma determinística en
cualquier combinación de período/semestre/jornada.

### Cambios
- `grse_codigo` y `coho_codigo` ampliados de `VARCHAR(20)` a
  `VARCHAR(25)` — alineando el esquema real con la convención ya
  documentada en CLAUDE.md ("Códigos (grupos, cohortes) →
  VARCHAR(25)"), que nunca se había aplicado correctamente en el DDL.
- `grupos_mdl.php`: validación defensiva `mb_strlen($codigo) > 25`
  agregada en `guardar_grupo` (antes del chequeo de duplicados) y en
  `guardar_cohorte` (antes del chequeo de duplicados existente) — si el
  código excede el límite, responde con mensaje específico y `break`
  antes de llegar a MySQL, en vez de depender del error crudo de PDO.
- `grupos_view.php`: `maxlength="25"` agregado a `#coho_codigo` (único
  de los dos campos que se escribe a mano — `#coho_codigo` es texto
  libre guiado por placeholder; `#grse_codigo` es `readonly` y se
  genera por JS, por lo que un `maxlength` ahí no tendría efecto real y
  se omitió deliberadamente).
- `generarSugerenciaCodigoGrupo()` en `grupos_ctrl.js` no se tocó — la
  fórmula de composición era correcta, el problema era exclusivamente
  de ancho de columna.

### Decisiones
- Se descartó limitar `prog_sigla` a menos de 7 caracteres: sería más
  restrictivo que el `VARCHAR(10)` ya usado en producción para dos
  programas reales (ASO2016, AMD2016) actualmente activos.
- Pendiente para producción (`escuelamdb.com`): dos `ALTER TABLE`
  (`gruposemestres.grse_codigo`, `cohortes.coho_codigo`) que se suman
  al de `docentes.doce_cedula` (commit `7ba02bc`) — se recomienda
  aplicar los tres en un solo script en el próximo deploy.

### Pruebas realizadas
- Caso exacto reportado reproducido vía curl (ASO2016, período 2026-2, semestre 3, jornada Semana) → guardado exitosamente tras el fix ✅
- Guardrail defensivo probado con código de 26 caracteres → rechazado con mensaje específico, sin llegar a MySQL ✅
- Verificación en navegador: caso original (ASO2016) ✅, AMD2016 en otra combinación ✅, programa de sigla corta sin regresión ✅, cohorte con sigla larga (CH-ASO2016-2026B) ✅

---

## [7ba02bc] — 2026-08-28 — feat(docentes): cablea doce_cedula completo (crear/editar/obtener)

### Archivos modificados
- database/emdb_academica.sql
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js
- app/03_docentes/doc_mdl.php

### Cambios
- `docentes.doce_cedula` (`UNIQUE KEY uq_doce_cedula`) existía en el
  esquema desde el inicio del proyecto pero no estaba cableado en
  ninguna capa — todas las filas tenían `NULL`. Se cablea completo
  siguiendo el patrón ya usado para `estudiantes.estu_numerodoc`, con
  dos diferencias deliberadas: sin selector de tipo de documento
  (`doce_tipodoc` no existe — los docentes solo usan cédula) y campo
  opcional, no obligatorio (a diferencia de `estu_numerodoc`).
- `ALTER TABLE docentes MODIFY doce_cedula VARCHAR(20)...` — columna
  ampliada de `VARCHAR(15)` a `VARCHAR(20)` para paridad con
  `estu_numerodoc`, aplicado en Docker y reflejado en
  `database/emdb_academica.sql`.
- Nuevo campo "Cédula" en el modal `#mdl_docente` (`doc_view.php`),
  ubicado entre Apellidos y Sigla, sin asterisco de obligatorio.
- `doc_ctrl.js`: mismo listener de formato en vivo (separador de miles)
  que ya tenía `#npt_estu_numerodoc`, aplicado a `#npt_doce_cedula`;
  incluido en el objeto `data` del guardado (despojando el separador);
  precargado en `abrirEditar()` con fallback a cadena vacía si viene
  `null`; reseteado en `limpiarFormulario()`.
- `doc_mdl.php`, case `guardar`: validación de unicidad
  (`SELECT doce_id FROM docentes WHERE doce_cedula = ?`, agregando
  `AND doce_id != ?` en la rama editar) ejecutada SOLO cuando
  `doce_cedula` no llega vacío — mismo criterio ya usado para
  `matr_numero` en `est_mdl.php` (campo opcional, no se valida
  unicidad de un valor ausente). Si el valor llega vacío, se guarda
  como `NULL` (no como cadena vacía) en ambos INSERT/UPDATE, para no
  arriesgar futuras colisiones si MySQL tratara cadenas vacías
  repetidas como iguales bajo la `UNIQUE KEY`.
- `doc_mdl.php`, case `obtener`: `d.doce_cedula` agregado al `SELECT`.
- `case 'listar'` deliberadamente sin cambios — `doce_cedula` no se
  agrega a la tabla de DataTables en este commit (fuera del alcance
  pedido).

### Decisiones
- Decisión de alcance explícita de Jose Luis: sin selector de tipo de
  documento para docentes (a diferencia de estudiantes) y ampliar la
  columna a `VARCHAR(20)` en vez de dejarla en `VARCHAR(15)`.
- Campo opcional por decisión propia (no explícitamente pedida): ya
  existían 5 docentes con `doce_cedula = NULL` antes de este commit;
  hacerlo obligatorio habría forzado completar datos retroactivamente
  sin que se pidiera. Documentado en el chat para que Jose Luis lo
  revierta si prefiere obligatoriedad.
- Pendiente para el despliegue en producción (`escuelamdb.com`): el
  `ALTER TABLE` de este commit solo se aplicó en Docker local — debe
  ejecutarse manualmente en cPanel antes o durante el próximo deploy.

### Pruebas realizadas
- Crear docente sin cédula (dos veces) → ambos quedan NULL, sin conflicto de unicidad ✅
- Crear docente con cédula nueva → guardada correctamente ✅
- Editar docente agregando cédula por primera vez (NULL → valor) → guardada correctamente ✅
- Repetir cédula de otro docente → rechazado con mensaje específico, sin transacción abierta ✅
- Editar docente sin tocar su cédula → no se autobloquea contra sí mismo ✅
- Formato de separador de miles en vivo → correcto, sin puntos residuales al precargar en edición ✅

---

## [b6cc503] — 2026-08-28 — feat(docentes): permite editar el correo electrónico (usuario de acceso) en "Editar Docente"

### Archivos modificados
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js
- app/03_docentes/doc_mdl.php

### Cambios
- El campo de correo electrónico del modal "Editar Docente" (`#npt_usua_email`)
  ya no queda bloqueado en modo edición — antes `abrirEditar()` forzaba
  `.prop('readonly', true)` sobre ese campo en `doc_ctrl.js`; ahora se
  puebla editable, igual que en modo creación.
- Label del campo actualizado de "Correo electrónico" a "Correo
  electrónico (usuario de acceso)" — hace explícito en la UI que ese
  mismo valor es el identificador de login (`login_mdl.php` autentica
  siempre contra `usuarios.usua_email`, sin importar el rol).
- `doc_mdl.php`, rama de edición del case `guardar`: se agrega
  validación de unicidad de `usua_email` (`SELECT usua_id FROM usuarios
  WHERE usua_email = ? AND usua_id != ?`, replicando exactamente el
  patrón ya usado para `doce_sigla` en la misma rama) antes de abrir la
  transacción — si el correo ya pertenece a otro `usua_id`, responde
  `{'status':'error','message':'Ese correo ya está registrado por otro
  usuario'}` sin escribir nada.
- `usua_email = ?` agregado a ambos `UPDATE usuarios` de esa rama (el
  que incluye cambio de contraseña y el que no) — antes esta rama solo
  tocaba `usua_activo` y, condicionalmente, `usua_passwordhash`; el
  valor de `$usua_email` ya se recolectaba en `doc_ctrl.js` y llegaba
  por POST en ambos modos, pero el backend lo ignoraba en edición.

### Decisiones
- Restricción `UNIQUE KEY uq_usua_email` confirmada sobre
  `usuarios.usua_email` — la validación de unicidad server-side es
  obligatoria, no opcional, porque ese campo funciona como credencial
  de acceso para los 4 roles.
- Cambio acotado a los 3 archivos de `03_docentes`; no se tocó
  `08_admin` ni `02_estudiantes`, que administran su propio flujo de
  edición de correo de forma independiente.

### Pruebas realizadas
- Editar docente sin cambiar el correo → sin alteración en BD ✅
- Editar docente con correo nuevo válido → actualizado correctamente ✅
- Intentar correo ya usado por otro usuario → rechazado, sin transacción abierta ✅
- Cambio simultáneo de correo y contraseña → ambos valores persistidos correctamente, sin mezcla de parámetros ✅
- Login con el nuevo correo → acceso exitoso ✅
- Campo visualmente editable en el modal (ya no readonly) ✅

---

## [3672a0e] — 2026-08-26 — refactor: unifica disposición de campos en Sección 1 de la Ficha de Inscripción (AC-FO-02)

### Archivos modificados
- app/09_inscripcion_publica/insc_view.php
- app/02_estudiantes/est_view.php

### Cambios
- Sección "1. Datos del Estudiante/Aspirante": se reordenaron y renombraron
  los campos de residencia — "Dirección", "Barrio" y "Ciudad de residencia"
  ahora aparecen en ese orden, con el sufijo "(Residencia)" en cada label.
- "Ciudad de nacimiento" se reubicó junto a "Fecha de nacimiento".
- En insc_view.php, "Expedido en" se trasladó de la fila EPS/Ocupación a la
  fila del documento (Tipo de documento + Número de documento), igualando
  la disposición que ya existía en est_view.php.
- EPS, Ocupación y Estado civil se consolidaron en una sola fila (3 columnas
  iguales); Discapacidad quedó sola en su propia fila.
- est_view.php: se unificó el texto del label "Tipo/Documento" a
  "Tipo de documento" (igual que insc_view.php).
- Sección "3. Información sobre Familiares" (Padre, Madre, Acudiente/Persona
  de contacto, en ambos archivos): "Dirección", "Barrio" y "Ciudad" se
  reordenaron a ese orden con sufijo "(Residencia)"; "Teléfono" se renombró
  a "Teléfono (Personal)".
- (Aparte) Se confirmó en este mismo commit la eliminación, ya decidida
  previamente, de actualizar_ayudas.sql (archivo sin tracking activo desde
  antes de esta sesión de trabajo).

### Decisiones
- Cambio exclusivamente de vista (labels y clases de columna Bootstrap) — sin
  modificaciones en modelo (fichas_inscripcion, estudiantes) ni en
  controladores JS. Ningún `id`/`name` de input cambió.
- Los encabezados de sección "1. Datos del Aspirante" (insc_view.php) y
  "1. Datos del Estudiante" (est_view.php) se mantienen intencionalmente
  distintos — no se unificaron por decisión explícita del autor.
- Se priorizó llevar insc_view.php a la misma disposición estructural de
  est_view.php en la Sección 1, en vez de mantener divergencias históricas
  entre ambos formularios, ya que comparten el mismo propósito de UI aunque
  tengan backends distintos (insc_mdl.php vs est_mdl.php).

---

## [ac96f07] — 2026-08-25 — feat: registro y visualización de "último acceso" del usuario al sistema

### Archivos modificados
- database/emdb_academica.sql
- app/00_files/helpers.php (nuevo)
- app/01_login/login_mdl.php
- app/00_files/navbar.php
- app/03_docentes/doc_view.php
- app/03_docentes/doc_mdl.php
- app/03_docentes/doc_ctrl.js
- app/05_calificaciones/calificaciones_view.php
- app/06_reportes/reportes_view.php
- app/08_admin/admin_mdl.php
- app/08_admin/admin_view.php
- app/08_admin/admin_ctrl.js
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- Nueva columna `usuarios.usua_ultimo_acceso` (`DATETIME NULL DEFAULT NULL`),
  agregada tanto vía `ALTER TABLE` manual en Docker como en el DDL
  versionado (`database/emdb_academica.sql`).
- Nuevo helper compartido `formatearUltimoAcceso($ultimoAcceso,
  $fechaCreacion)` en `00_files/helpers.php` (primer archivo de funciones
  comunes del proyecto — antes `00_files/` solo tenía `navbar.php`,
  `estilos.css` y `ayuda_sidebar.js`): si `usua_ultimo_acceso` es `NULL`,
  usa `fechacreacion` como fallback; formato final `d/m/Y H:i`; devuelve
  `'—'` si ambos son `NULL`.
- `login_mdl.php` — en el único flujo de autenticación (sirve a los 4
  roles sin duplicar lógica), después de `password_verify()` exitoso y
  antes de la lógica condicional de `doce_id`/`estu_id` que ya existía: se
  guarda en sesión el valor ANTERIOR de `usua_ultimo_acceso`
  (`usua_ultimo_acceso_anterior`) junto con `fechacreacion`, y luego se
  ejecuta `UPDATE usuarios SET usua_ultimo_acceso = NOW() WHERE usua_id =
  ?`. El orden importa: lo que se muestra en el navbar durante la sesión
  activa es el acceso previo, no el que se acaba de generar con este mismo
  login.
- Navbar — bloque `Nombre — Email — Último acceso: dd/mm/aaaa hh:mm`
  agregado en tres puntos, mismo estilo (`text-light small`, mismo
  separador ` — `) en los tres:
  - `navbar.php` (roles 1 y 2).
  - `calificaciones_view.php` (nav fallback real usado por Docente —
    **no** `doc_view.php`: se intentó ahí primero, pero resultó ser
    código inalcanzable para ese rol por el guard de sesión de esa vista,
    que solo permite roles 1/2; revertido en el mismo ciclo de trabajo
    antes de este commit).
  - `reportes_view.php` (nav fallback de Estudiante).
- Tabla Usuarios (`08_admin`) — columna "Último acceso" nueva; calculada
  server-side en el `case 'listar'` de `admin_mdl.php`
  (`$row['ultimo_acceso_fmt'] = formatearUltimoAcceso(...)` por fila,
  mismo patrón ya usado para `estado_ficha` en `est_mdl.php`), consumida
  en `admin_ctrl.js` como columna adicional del DataTable.
- Tabla Matriculados (`02_estudiantes`) — columna "Último acceso" nueva;
  requirió agregar `LEFT JOIN usuarios u ON e.usua_id = u.usua_id` al
  `SELECT` de `listar_matriculados` (`LEFT` porque un estudiante puede no
  tener `usua_id` todavía — a diferencia de Docentes, donde el `usua_id`
  siempre existe).
- Tabla Docentes (`03_docentes`) — columna "Último acceso" nueva,
  reutilizando el `INNER JOIN usuarios` que ya existía en el `case
  'listar'` de `doc_mdl.php` — sin agregar ningún `JOIN` nuevo ahí.

### Decisiones
- Verificación de sintaxis (`php -l`) sin errores en los 13 archivos PHP
  tocados, vía el contenedor `app`.
- Hallazgo colateral detectado durante la implementación, documentado como
  deuda técnica y **no corregido en este commit**: además de
  `doc_view.php`, los archivos `est_view.php` y `grupos_view.php` también
  tienen un `<nav>` fallback con el mismo patrón (`navbar-dark bg-dark
  px-3`) que resulta inalcanzable para Docente por el guard de roles de
  esas vistas (solo permite 1 y 2) — inconsistencia preexistente, no
  introducida por este cambio. Documentado en PROJECT_CONTEXT.md, sección
  "Análisis pendiente". Pendiente de decisión futura: ¿vale la pena
  limpiar ese código muerto o dejarlo así? No implementar sin decisión
  explícita de Jose Luis.

---

## [35c312a] — 2026-08-25 — feat(docentes): habilita cambio de contraseña en "Editar Docente"

### Archivos modificados
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js
- app/03_docentes/doc_mdl.php

### Cambios/Vulnerabilidad corregida
- El campo de contraseña del modal `#mdl_docente` (bloque `bloque_password`)
  ya no se oculta por completo en modo edición — replica el patrón ya usado
  en `08_admin` (`admin_mdl.php`, case `editar`): campo opcional, vacío = no
  se toca el hash, `password_hash()` con bcrypt si viene texto.
- Label del campo cambiado de "Contraseña inicial" a "Contraseña" (sirve
  para ambos modos); el placeholder se ajusta dinámicamente por JS —
  "Contraseña inicial" en creación, "Dejar vacío para no cambiar" en
  edición.
- `abrirEditar()` (`doc_ctrl.js`) condiciona la visibilidad del campo a que
  el docente tenga `usua_id` (acceso al sistema) — si `usua_id` es `NULL`,
  el campo permanece oculto, igual que antes de este cambio.
- `case 'obtener'` en `doc_mdl.php` agrega `d.usua_id` al `SELECT` para que
  el frontend pueda tomar esa decisión.
- `case 'guardar'` (rama edición) en `doc_mdl.php`: si llega
  `usua_password` no vacía, genera
  `password_hash($usua_password, PASSWORD_BCRYPT)` y actualiza
  `usua_passwordhash` en el mismo `UPDATE` que `usua_activo`; si viene
  vacía, mantiene el `UPDATE` original sin tocar el hash. Sin validación de
  longitud mínima (mismo criterio que `08_admin`).

### Decisiones
- `case 'obtener'` sigue usando `INNER JOIN` entre `docentes` y `usuarios`,
  sin cambios en este commit — un docente con `usua_id NULL` causaría
  "Docente no encontrado" antes de llegar a abrir el modal, en vez de
  abrirlo con el campo de contraseña oculto. Hoy no hay ningún docente en
  esa condición en la BD (`SELECT COUNT(*) FROM docentes WHERE usua_id IS
  NULL` → 0) — no es un bug activo. Queda documentado como deuda de diseño
  pendiente en PROJECT_CONTEXT.md (sección "Análisis pendiente"), mismo
  criterio ya usado para `matriculas.matr_matriculadopor` (commit
  `5de9a9e`): una decisión de esquema/diseño aparte, no resuelta por no ser
  mecánica.
- Verificado en navegador por Jose Luis antes de commit.

---

## [7374a0f] — 2026-08-24 — chore(ayudas): sincroniza contenido de 10_ayudas con el estado real del sistema

### Archivos modificados
- actualizar_ayudas.sql (nuevo)
- Datos: tabla `ayudas` (5 UPDATE + 1 INSERT + 5 UPDATE de ayud_orden, vía script)

### Cambios/Vulnerabilidad corregida
- Diagnóstico encontró que las 26 entradas de `ayudas` no se habían actualizado
  desde el 16-18 de agosto, mientras que entre el 19 y el 24 de agosto ocurrieron
  12 commits sustanciales sobre `02_estudiantes` (plan de unificación de ficha de
  estudiante, Fases A-F): modal renombrado a "Ficha de Inscripción", fusión de
  Editar Estudiante + Ficha Familiar en un solo modal, Cohorte obligatoria en la
  cascada de matrícula, folio/fecha/observaciones reactivados, número de matrícula
  editable en "Editar Matrícula", y generación de Hoja de Matrícula en PDF
  (AC-FO-09). Ninguno de estos cambios estaba reflejado en el contenido de ayuda.
- `actualizar_ayudas.sql` corrige 5 entradas de `02_estudiantes` (ayud_id 28, 29,
  30, 31, 44) con el flujo actual, y agrega 1 entrada nueva en `08_admin`
  (ayud_id 49) documentando el bloque "Configuración Institucional" (número de
  matrícula inicial, Director, Secretario), agregado en el commit `25530cc` y
  sin ninguna entrada de ayuda hasta ahora.
- Hallazgo colateral durante la verificación: dos pares de `ayud_orden` duplicados
  en `04_grupos` (ayud_id 45/48 ambos en 2, y 19/20 ambos en 3) — corregidos a una
  secuencia única y consecutiva 0-7, sin cambio de contenido.

### Decisiones
- Tarea puramente de datos, sin cambios de código — el script SQL se versiona en
  git para trazabilidad, aunque su efecto ya se aplicó directamente contra la BD
  en ejecución (mismo patrón ya usado para otros cambios de datos del proyecto,
  ej. commit `25530cc`).
- Verificado en navegador: las 6 entradas modificadas/nuevas se leen correctamente
  en el sidebar de ayuda de sus respectivos módulos.

---

## [0e098bb] — 2026-08-24 — refactor(inscripcion): unificar formulario público en un solo paso

### Archivos modificados
- app/09_inscripcion_publica/insc_view.php
- app/09_inscripcion_publica/insc_ctrl.js
- app/09_inscripcion_publica/insc_mdl.php
- database/emdb_academica.sql

### Archivos eliminados
- app/09_inscripcion_publica/fam_view.php
- app/09_inscripcion_publica/fam_mdl.php
- app/09_inscripcion_publica/fam_ctrl.js

### Cambios/Vulnerabilidad corregida
- **insc_view.php** — reescrito por completo. Pasa de ser solo el Paso 1
  (datos del aspirante + búsqueda por código) a contener el formulario
  público completo en una sola pantalla, con el mismo diseño visual del
  modal interno "Ficha de Inscripción" de `02_estudiantes` (commit
  `f088466`): 5 secciones numeradas con la clase `.seccion-titulo`
  (fondo azul, `00_files/estilos.css`):
  1. Datos del Aspirante (18 campos originales del Paso 1, sin cambios)
  2. Multiculturalidad (8 checkboxes, sin cambios)
  3. Información sobre Familiares (bloque Padre/Madre/Acudiente fusionado
     desde `fam_view.php`, sin cambios de campos)
  4. Estudios anteriores (fusionado desde `fam_view.php`)
  5. Programa técnico al que ingresa (`slct_finc_prog_id`, `npt_jornada`)
  - Eliminados de la UI: `#bloque_codigo`, `#npt_codigo_busqueda`,
    `#btn_buscar_codigo`, `#error_codigo` — ya no existe un código que
    buscar.
  - reCAPTCHA reubicado justo antes del único botón de envío final
    (`#btn_enviar_inscripcion`).
  - `#bloque_confirmacion` reescrito sin ninguna referencia a código —
    mensaje de bienvenida + próximos pasos (contacto de la coordinación
    para confirmar matrícula).
- **insc_ctrl.js** — reescrito por completo. Absorbe toda la lógica de
  `fam_ctrl.js`:
  - `actualizarVisibilidadPadreMadre()`, `actualizarSelectAcudiente()`,
    `MAPEO_ACUDIENTE`, `limpiarYHabilitarCamposAcudiente()`,
    `manejarCambioAcudiente()` — cascada Padre/Madre/Acudiente sin
    cambios de comportamiento.
  - Carga del catálogo de Programas movida a
    `insc_mdl.php?accion=listar_programas` (antes en `fam_mdl.php`).
  - Nueva función `marcarCampoLleno()` con listener delegado —
    `$(document).on('input change', '#form_inscripcion input, select,
    textarea', ...)` — mismo patrón ya usado en `02_estudiantes`
    (commit `f088466`), aplicado por primera vez fuera de ese módulo.
  - Máscaras existentes (documento con puntos de miles, email en
    minúsculas, exclusión mutua de multiculturalidad) intactas.
  - Un solo handler de envío (`#btn_enviar_inscripcion`): valida
    `CAMPOS_ESTUDIANTE` + los campos de Ficha Familiar condicionales
    según Padre/Madre "¿Vive?" y Acudiente, valida multiculturalidad y
    reCAPTCHA, arma un único objeto de datos con todos los campos
    (aspirante + ficha), y hace un solo POST a
    `insc_mdl.php?accion=crear_aspirante`. El callback `success` ya no
    lee `response.codigo` — solo verifica `response.status === 'ok'` y
    alterna `#form_inscripcion`/`#bloque_confirmacion`.
- **insc_mdl.php** — reescrito por completo:
  - Nuevo case `listar_programas` (movido desde `fam_mdl.php`).
  - `case 'crear_aspirante'` ahora valida los 19 campos del aspirante Y
    todos los campos de Ficha Familiar (antes repartidos entre
    `crear_aspirante` y `completar_ficha`) antes de tocar la BD;
    `verificarRecaptcha()` se ejecuta después de que ambos bloques de
    validación pasan y antes de cualquier query. Las validaciones de
    unicidad de documento y de email cruzada contra `estudiantes` y
    `usuarios` se conservan sin cambios.
  - Una sola transacción PDO (`beginTransaction`/`commit`/`rollBack`)
    ejecuta el `INSERT INTO estudiantes` (`estu_origen = 'web'`,
    igual que antes) seguido de un único `INSERT INTO
    fichas_inscripcion` con los 35 campos completos (antes: `INSERT`
    parcial en `crear_aspirante` + `UPDATE` posterior en
    `completar_ficha`, en dos requests HTTP separados sin atomicidad
    real entre sí). `fechainscripcion` se fija con `CURDATE()`.
  - `generarCodigoTemporal()` y su lógica de reintento contra duplicado
    eliminados por completo — ya no hay código que generar.
  - La respuesta de éxito ya no incluye la clave `codigo`.
- **database/emdb_academica.sql** — eliminada la columna
  `finc_codigotemporal VARCHAR(20)` y su `UNIQUE KEY
  uq_finc_codigotemporal` de la definición de `fichas_inscripcion`.
  **`ALTER TABLE fichas_inscripcion DROP COLUMN finc_codigotemporal;`
  ya se ejecutó manualmente contra el contenedor Docker existente el
  2026-08-24** — `DESCRIBE fichas_inscripcion` confirmó la columna
  eliminada. No es un pendiente.

### Decisiones
- **Arquitectura de archivos:** se fusionó todo dentro de `insc_*`,
  eliminando `fam_view.php`/`fam_mdl.php`/`fam_ctrl.js` por completo,
  en vez de mantener dos módulos de archivos separados. Se verificó
  antes (diagnóstico previo) que no hay colisión de `id` entre ambos
  formularios originales — la fusión es conceptualmente correcta porque
  ambos formularios siempre representaron un único dominio (creación de
  aspirante), separado artificialmente en 2 pasos solo para permitir
  "retomar después".
- **Transacción única, no dos transacciones separadas:** aplicando el
  mismo principio ya documentado en CLAUDE.md para `guardar_completo`
  (02_estudiantes, commit `42e4175`) — "dos transacciones
  individualmente correctas no garantizan atomicidad entre sí" — se
  fusionaron los dos INSERTs (antes en 2 requests HTTP separados,
  `crear_aspirante` + `completar_ficha`) en una sola transacción PDO.
  Un aspirante con `estudiantes` guardado pero sin `fichas_inscripcion`
  ya no es un estado alcanzable.
- **`finc_codigotemporal` eliminado, no solo dejado de usar:** columna
  + `UNIQUE KEY` + toda la lógica de generación se removieron por
  completo del esquema y del código — un formulario de un solo paso, un
  solo guardado, no necesita ningún mecanismo de "retomar después".
- **El formulario público sigue sin generar clave de acceso al
  sistema.** Esto no cambió con esta refactorización — se confirmó
  durante el diagnóstico previo (grep de `generarClaveAuto`,
  `clave_generada`, `usua_passwordhash` en todo `09_inscripcion_publica/`,
  sin resultados) que ese mecanismo nunca existió en el formulario
  público: la generación de clave de acceso es y sigue siendo tarea
  exclusiva del coordinador al matricular desde `02_estudiantes` (case
  `matricular` en `est_mdl.php`). El único "código" que el formulario
  entregaba antes de este cambio era `finc_codigotemporal` — un puente
  técnico para retomar el Paso 2, nunca una credencial de acceso — y ya
  no existe.
- **`calcularEstadoFicha()` (est_mdl.php) no se modificó.** El
  indicador de 3 estados (`sin_iniciar`/`incompleta`/`completa`) sigue
  siendo válido sin cambios de código: con guardado transaccional
  único, el estado `incompleta` ya no tiene ruta de origen desde el
  formulario público (solo queda alcanzable si un coordinador edita
  manualmente la ficha después y la deja a medias), pero el cálculo en
  sí no depende de cómo se originó la fila.
- No se tocó `02_estudiantes`, `est_mdl.php`, la lógica de matriculación
  ni el mecanismo de generación de clave — fuera del alcance de este
  cambio.
- Verificado en navegador: 15/15 puntos de prueba.

---

## [59775e4] — 2026-08-24 — feat(estudiantes): habilita edición manual de matr_numero en "Editar Matrícula"

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js
- app/02_estudiantes/est_mdl.php
- app/06_reportes/pdf_hoja_matricula.php

### Cambios/Vulnerabilidad corregida
- Modal "Editar Matrícula de..." (`#mdl_editar_matricula`, `est_view.php`):
  reemplazado el texto informativo de solo lectura `#spn_editar_matr_numero`
  por un campo editable real `#npt_editar_matr_numero` (`type="number"`,
  `min="1"`), ubicado junto a Folio y Fecha de matrícula.
- `est_ctrl.js`: la precarga del modal ahora usa `.val()` en vez de `.text()`
  (input vacío si `matr_numero` es `null`); el payload de `editar_matricula`
  agrega `matr_numero: $('#npt_editar_matr_numero').val() || null`.
- `est_mdl.php`, case `editar_matricula`: recibe y normaliza `matr_numero`
  (`null` si viene vacío). Antes del `UPDATE` principal, agrega una
  validación de unicidad explícita — mismo patrón ya usado para
  `doce_sigla` en `doc_mdl.php` — con `SELECT matr_id FROM matriculas
  WHERE matr_numero = ? AND matr_id != ?`; si encuentra coincidencia,
  responde con mensaje específico "El número de matrícula ya está
  asignado a otro estudiante." en vez de depender del catch genérico de
  `PDOException`. El `UPDATE` incluye `matr_numero` junto a los campos
  ya existentes.
- `pdf_hoja_matricula.php`: el `catch` de la asignación automática de
  `matr_numero` ahora diferencia una colisión real de unicidad
  (`PDOException` con `SQLSTATE 23000`) del resto de errores genéricos,
  devolviendo el mensaje "El número de matrícula ya fue asignado,
  intente nuevamente." en ese caso específico.

### Decisiones
- El mecanismo de asignación automática (`SELECT ... FOR UPDATE` sobre
  `configuracion` + `MAX(matriculas.matr_numero)` + `GREATEST` contra
  `matr_numero_inicial`, introducido en la Fase F2) **no se modificó**.
  Conclusión del diagnóstico previo, dejada explícita para no reabrir
  esta pregunta: ese mecanismo relee `MAX(matr_numero)` en vivo cada vez
  que se ejecuta — no existe un contador cacheado en `configuracion`
  (`matr_numero_inicial` es solo un piso mínimo, no un contador que
  avanza) — por lo que habilitar la edición manual desde "Editar
  Matrícula" no introduce ningún riesgo de desincronización entre ambos
  mecanismos.
- No se tocó el modal "Completar Matrícula" (`matricular`) — el cambio
  aplica exclusivamente a "Editar Matrícula".
- Verificado en navegador: 8/8 puntos de prueba.

---

## [f088466] — 2026-08-24 — refactor(estudiantes): reestructura el modal de creación de estudiante en "Ficha de Inscripción"

### Archivos modificados
- app/00_files/estilos.css
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- Título del modal `#mdl_estudiante` en modo creación cambia de "Nuevo
  Aspirante" a "Ficha de Inscripción" (`est_view.php` y `est_ctrl.js`) —
  el modo edición conserva "Editar Estudiante" sin cambios; ambos modos
  siguen compartiendo el mismo modal, fusionado desde la Fase C del plan
  de unificación de ficha de estudiante.
- Nuevo bloque de encabezado "FICHA DE INSCRIPCIÓN — código AC-FO-02" con
  instrucciones generales, insertado al inicio del `modal-body`, antes de
  `#bloque_foto_estudiante` — incluye advertencia explícita de pérdida de
  datos si el modal se cierra sin guardar.
- Eliminado el bloque de instrucciones antiguo específico de "Ficha
  Familiar (AC-FO-02)", redundante con el nuevo encabezado.
- Las 5 secciones del modal quedan numeradas y reciben la nueva clase
  `.seccion-titulo` (fondo azul, definida en `00_files/estilos.css`):
  1. Datos del Estudiante
  2. Multiculturalidad (`<label id="lbl_multiculturalidad">` conservado
     intacto — solo se le agregó la clase y el número)
  3. Información sobre Familiares (antes "Ficha Familiar (AC-FO-02)")
  4. Estudios anteriores
  5. Programa técnico al que ingresa (antes "Información general" — el
     bloque Programa/Jornada/Fecha de inscripción se reubicó al final
     del modal; antes estaba intercalado justo antes de Padre/Madre/
     Acudiente)
- Nueva clase `.campo-lleno` (fondo verde claro `#d1e7dd`) aplicada
  dinámicamente vía nueva función `marcarCampoLleno()` (`est_ctrl.js`),
  enganchada con un listener delegado — `$(document).on('input change',
  '#mdl_estudiante input, #mdl_estudiante select, #mdl_estudiante
  textarea', ...)` — que cubre también los campos que aparecen/
  desaparecen dinámicamente (Padre/Madre/Acudiente). Capa visual
  independiente de `marcarValidacion()`/`is-invalid`/`is-valid`, sin
  modificarla.

### Decisiones
- Sin cambios en lógica de guardado, payload AJAX, `CAMPOS_ESTUDIANTE`,
  `CAMPOS_FICHA_SIEMPRE` ni en `#bloque_foto_estudiante`.
- Se evaluó agregar la subida de foto también al modo creación y se
  descartó: el endpoint `subir_foto` requiere un `estu_id` ya existente,
  que no existe hasta que el aspirante se guarda por primera vez. La
  foto permanece exclusiva del modo edición, sin cambios.
- Verificado en navegador: 8/8 puntos de prueba.

---

## [5de9a9e] — 2026-08-23 — refactor: elimina código muerto tras el plan de unificación de ficha de estudiante

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- Eliminados los 4 cases `guardar`, `obtener`, `obtener_ficha`,
  `guardar_ficha` de `est_mdl.php` — sin llamadores desde `est_ctrl.js`
  desde la Fase C (documentado como pendiente de limpieza en los commits
  `42e4175` y `76f90c5`).
- Eliminados `abrirFicha()` y el handler `$('#btn_guardar_ficha').click(...)`
  de `est_ctrl.js` — el selector `#btn_guardar_ficha` ya no coincidía con
  ningún elemento del DOM desde que `mdl_ficha` se eliminó en la Fase C2.
- `limpiarFormularioFicha()` se conservó intacta — tiene un llamador
  activo real dentro de `limpiarFormulario()`, parte del flujo vivo de
  "Nuevo Aspirante".
- Actualizados 2 comentarios obsoletos en `est_ctrl.js` que aún
  mencionaban `abrirFicha()`/`mdl_ficha` como si siguieran existiendo.
- 587 líneas netas eliminadas.

### Decisiones
- Diagnóstico exhaustivo previo confirmó 0 llamadores reales en todos
  los casos (`grep` en todo el proyecto, no solo en `02_estudiantes`).
- `matriculas.matr_matriculadopor` (columna sin wiring desde el esquema
  original) queda deliberadamente fuera de esta pasada — es una decisión
  de diseño aparte (columna de esquema, no código confirmado muerto),
  no una limpieza mecánica.
- Verificado en navegador: 7/7 puntos de prueba (creación/edición de
  aspirante, matrícula, edición de matrícula, ambos PDFs, sin errores de
  consola).
- Cierra la deuda técnica documentada durante las Fases C1/C2.

---

## [ffb6a6c] — 2026-08-23 — feat: agrega PDF Hoja de Matrícula (AC-FO-09), cierra el plan de unificación de ficha de estudiante

### Archivos modificados
- app/06_reportes/pdf_hoja_matricula.php (nuevo)
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- Nuevo endpoint `pdf_hoja_matricula.php` (`06_reportes`), mismo patrón que
  `pdf_ficha.php`: dompdf con `isRemoteEnabled=false` y `chroot` a la raíz
  del proyecto para leer la foto local, guard de sesión + rol
  (coordinador/admin), `estu_id` por GET. Tamaño Carta portrait (no
  Oficio), excluye "Nivel" y el checklist de "Requisitos de Matrícula"
  del formato original AC-FO-09.
- Query combinada: `estudiantes` + `fichas_inscripcion` (LEFT JOIN) +
  `matriculas` (INNER JOIN, requiere matrícula) + `programas` +
  `cohortes` (vía `estudiantes.coho_id`, no vía `matriculas`) +
  `periodos` + `configuracion`. Como `matriculas` es 1:N por estudiante,
  se resuelve con `ORDER BY m.matr_id DESC LIMIT 1` — la matrícula más
  reciente.
- Validación server-side de `matr_estado === 'matriculado'` — el backend
  responde con error explícito si no lo está, sin depender de que el
  frontend haya ocultado el botón.
- Asignación seleccionada de `matr_numero` bajo concurrencia (solo si
  aún es `NULL`): transacción PDO con `SELECT matr_numero_inicial FROM
  configuracion WHERE config_id = 1 FOR UPDATE`, luego `SELECT
  MAX(matr_numero) FROM matriculas`, calcula el siguiente valor
  (`matr_numero_inicial` si no hay ninguno asignado aún, o
  `MAX(matr_numero)+1` si es mayor), persiste con `UPDATE` y hace
  `commit()`. Descargas posteriores reutilizan el valor ya persistido.
- `est_mdl.php` (case `obtener_completo`): agrega `m.matr_estado` al
  `SELECT`, vía el mismo `LEFT JOIN matriculas` + `ORDER BY matr_id DESC
  LIMIT 1` por la misma razón (relación 1:N, no 1:1).
- `est_view.php`/`est_ctrl.js`: nuevo botón "🖨️ Hoja de Matrícula" en el
  footer del modal unificado (`#mdl_estudiante`), oculto por defecto,
  visible solo si `matr_estado === 'matriculado'` (mismo criterio que el
  botón de Ficha Inscripción con `estado_ficha`); abre el PDF con
  `window.open(...)`, mismo patrón que `descargarFichaPdf()`.

### Decisiones
- Primer uso del patrón `SELECT ... FOR UPDATE` en el proyecto, aplicado
  sobre la fila única de `configuracion` por ser la más barata de
  lockear.
- `matr_numero` se asigna solo la primera vez (si es `NULL`); descargas
  posteriores reutilizan el valor ya persistido.
- Dirección/barrio/ciudad del bloque genérico interpretados como los del
  estudiante (no del acudiente, que tiene su propio bloque separado).
- "Nombre del padre/madre" simplificado a solo nombre+apellidos, sin
  profesión/empresa/teléfono (formato de matrícula, no la Ficha Familiar
  completa).
- Verificado en navegador: 9/9 puntos de prueba, incluyendo revisión
  visual de que todo el contenido cabe en una sola página Carta.
- Fase F2 de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada. Cierra el plan completo: A, B, C (C1+C2), D, E, F (F1+F2).

---

## [c34b780] — 2026-08-23 — feat: agrega usua_nombre a usuarios, prerequisito para "Matriculado por" en Fase F2

### Archivos modificados
- database/emdb_academica.sql
- app/08_admin/admin_mdl.php
- app/08_admin/admin_view.php
- app/08_admin/admin_ctrl.js
- app/01_login/login_mdl.php

### Cambios/Vulnerabilidad corregida
- Nueva columna `usuarios.usua_nombre` (`VARCHAR(150) NOT NULL DEFAULT ''`),
  agregada entre `usua_email` y `usua_passwordhash` — tanto en el `.sql`
  como manualmente contra Docker (`ALTER TABLE ... ADD COLUMN ... AFTER
  usua_email`). Seed del usuario admin actualizado con `'Administrador'`
  en el `.sql` y con `UPDATE` puntual sobre el registro ya existente en
  Docker; el resto de usuarios existentes quedó con `''` por defecto (sin
  dato previo).
- `admin_mdl.php`: los 4 cases del CRUD de usuarios (`listar`, `crear`,
  `editar`, `obtener`) ahora leen/persisten/devuelven `usua_nombre`. La
  validación de campo requerido se agregó de forma simétrica en `editar`
  (el `case 'crear'` ya la tenía) en un fix posterior al mismo commit —
  el `required` del HTML no protege contra una petición directa al
  endpoint.
- `admin_view.php`/`admin_ctrl.js`: campo "Nombre completo" (`texto-mayus`)
  agregado en ambos modales (Nuevo Usuario/Editar Usuario), con su
  payload de guardado y su precarga en edición.
- `login_mdl.php`: `usua_nombre` agregado al `SELECT` de login y a
  `$_SESSION['usua_nombre']`, junto a los demás valores de sesión ya
  existentes (`usua_id`, `role_id`, `usua_email`).

### Decisiones
- Se descubrió durante el diagnóstico de la Fase F que no existía ningún
  campo de nombre para Admin/Coordinador en el esquema — la decisión
  original de autocompletar "Matriculado por" con el nombre en sesión no
  era implementable sin este cambio previo.
- Validación server-side agregada de forma simétrica en `editar` (el
  `crear` ya la tenía) — el HTML `required` no protege contra peticiones
  directas al endpoint.
- Verificado en navegador: 6/6 puntos de prueba.
- Fase F1 de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada (prerequisito de la Fase F2: PDF Hoja de Matrícula).

---

## [afdcfc1] — 2026-08-23 — feat: reactiva folio/fecha/observaciones en Completar y Editar Matrícula, prepara matr_numero para Fase F

### Archivos modificados
- database/emdb_academica.sql
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- `matr_numero` cambia de `VARCHAR(20)` a `INT UNSIGNED`, con `UNIQUE KEY
  uq_matr_numero` nueva — preparación de tipo para la asignación
  autoincremental de la Fase F, sin implementar aún esa lógica.
- `fechamatricula` deja de fijarse con `CURDATE()` hardcodeado en el case
  `matricular` — ahora se lee de `$_POST['fechamatricula']`, precargada
  con la fecha de hoy pero editable por el coordinador.
- `matr_folio` y `matr_observacion` reactivados en ambos modales
  ("Completar Matrícula" y "Editar Matrícula") tras haber sido removidos
  deliberadamente de la UI en el commit `29b6ca6` (backend nunca se tocó
  entonces, quedó listo para esto).
- `case 'obtener_matricula'`: el `SELECT` ahora trae `matr_folio`,
  `fechamatricula`, `matr_observacion` y `matr_numero` (de solo lectura).
- `case 'editar_matricula'`: el `UPDATE` ahora incluye `matr_folio`,
  `fechamatricula` y `matr_observacion` junto a `prog_id`/`peri_id`.
- `matr_numero` permanece de solo lectura/no editable en ambos modales —
  en "Editar Matrícula" se muestra como texto informativo
  (`#spn_editar_matr_numero`), sin input editable ni lectura desde POST
  en ningún case; sin lógica de asignación automática todavía.

### Decisiones
- Se confirmó con `SELECT` antes del `ALTER TABLE` que ningún registro
  tenía `matr_numero` no vacío — conversión de tipo segura, sin pérdida
  de datos.
- MySQL/InnoDB permite múltiples `NULL` en una columna `UNIQUE` —
  verificado empíricamente: el `ALTER TABLE` con la constraint nueva se
  aplicó sin error sobre las 11 filas existentes, todas con
  `matr_numero` `NULL`.
- `matr_numero` deliberadamente no se lee del POST en ningún case — se
  asignará server-side en la Fase F, nunca enviado por el cliente.
- Verificado en navegador: 11/11 puntos de prueba.
- Fase E de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada.

---

## [25530cc] — 2026-08-23 — feat: agrega configuración institucional (número de matrícula inicial, Director, Secretario)

### Archivos modificados
- database/emdb_academica.sql
- app/08_admin/admin_mdl.php
- app/08_admin/admin_view.php
- app/08_admin/admin_ctrl.js

### Cambios/Vulnerabilidad corregida
- Nueva tabla `configuracion` (BLOQUE 6C del `.sql`) — fila única con
  `config_id` fijo en 1 (`CONSTRAINT chk_configuracion_fila_unica CHECK
  (config_id = 1)`), columnas tipadas `matr_numero_inicial` (INT),
  `director_nombre` y `secretario_nombre` (VARCHAR(150)). Seed 9.7 inserta
  la fila inicial (`config_id=1, matr_numero_inicial=1, nombres vacíos`).
  Aplicada también manualmente contra la BD de Docker en ejecución, ya que
  el `.sql` solo se re-ejecuta si el volumen se recrea desde cero.
- `admin_mdl.php`: 2 cases nuevos, con el mismo guard exacto (sesión +
  `role_id !== 1`) ya usado en los 5 cases existentes de CRUD de usuarios.
  `obtener_configuracion`: `SELECT * FROM configuracion WHERE config_id =
  1`. `guardar_configuracion`: valida en servidor que
  `matr_numero_inicial` sea entero positivo y que `director_nombre`/
  `secretario_nombre` no estén vacíos, luego `UPDATE ... WHERE config_id =
  1` sin transacción (una sola tabla, un solo UPDATE).
- `admin_view.php`: bloque nuevo "Configuración Institucional" debajo de
  la tabla de usuarios, sin modal ni tabs — formulario simple con 3 campos
  y botón "Guardar configuración", con texto de ayuda bajo el campo de
  número de matrícula inicial explicando que solo aplica hacia adelante
  (no afecta matrículas ya generadas).
- `admin_ctrl.js`: precarga los 3 campos al cargar la página
  (`cargarConfiguracion()`), handler de `#btn_guardar_configuracion` con
  validación cliente + `$.ajax` a `guardar_configuracion`, confirmación
  con `alert()` (mismo patrón ya usado en este archivo para crear/editar
  usuario).
- Fix incidental: `admin_view.php` nunca había incluido `estilos.css` (la
  hoja que define `.texto-mayus`) — se agregó al `<head>`, necesario para
  que los campos Director/Secretario se muestren en mayúsculas.

### Decisiones
- Fila única con columnas tipadas fijas, no key-value — consistente con
  el resto del esquema del proyecto, que siempre usa columnas tipadas en
  vez de un esquema genérico.
- Sin tabs en `admin_view.php` — bloque adicional visible de inmediato,
  ya que son solo 3 campos y no ameritan una pestaña separada.
- Primer caso en el proyecto de "un registro de configuración fijo" (sin
  PK variable recibida del cliente, `WHERE config_id = 1` hardcodeado) —
  sin precedente previo en ningún otro módulo.
- Verificado en navegador: persistencia de los 3 campos, validación de
  campos vacíos/número no positivo, fila única confirmada en BD,
  regresión de CRUD de usuarios sin cambios.
- Fase D de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada.

---

## [76f90c5] — 2026-08-23 — feat: fusiona modales Editar Estudiante + Ficha Familiar en uno solo

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- `est_mdl.php`, case `obtener_completo`: agrega `estado_ficha` a la respuesta,
  calculado con la función ya existente `calcularEstadoFicha($row)` (sin
  duplicar lógica) — el frontend lo usa para decidir si mostrar el nuevo
  botón de descarga de PDF.
- `est_view.php`: `mdl_estudiante` ampliado de `modal-lg` a `modal-xl`. Todo
  el contenido del `modal-body` de `mdl_ficha` (Información general, Padre,
  Madre, Acudiente/Persona de contacto, Estudios anteriores) se trasladó
  dentro de `mdl_estudiante`, entre el bloque de multiculturalidad y el
  bloque de cambiar clave. El input oculto redundante `#npt_estu_id_ficha`
  se eliminó — el formulario unificado reutiliza `#npt_estu_id` como única
  referencia de ID. El modal `mdl_ficha` se eliminó por completo. Nuevo
  botón de footer "🖨️ Ficha Inscripción" (mismo ícono que el botón PDF
  anterior), visible únicamente si `estado_ficha === 'completa'`.
- `est_ctrl.js`: `abrirEditar(estu_id, esAspirante)` ahora llama a
  `obtener_completo` (antes `obtener`) y precarga en un solo flujo los
  datos básicos del estudiante y los de la Ficha Familiar (incluyendo la
  visibilidad condicional de los bloques Padre/Madre y la cascada del
  select Acudiente); el marcado de validación de los campos de ficha queda
  diferido a `shown.bs.modal` del modal unificado. `limpiarFormulario()`
  ahora también resetea los campos de ficha y oculta el botón de PDF en
  modo creación. Los handlers `btn_guardar_estudiante` y
  `btn_guardar_ficha` se fusionaron en uno solo sobre
  `#btn_guardar_estudiante`: valida los campos de ambos formularios
  (`CAMPOS_ESTUDIANTE` + `CAMPOS_FICHA_SIEMPRE`/`CAMPOS_FICHA_PADRE`/
  `CAMPOS_FICHA_MADRE`), arma un único payload y lo envía a
  `guardar_completo`. Los botones de fila "📋 Ficha" y "🖨️ PDF" se
  eliminaron de `tablaAspirantes` y `tablaMatriculados` — quedan
  "Matricular" + "📝 Datos Estudiante" (Aspirantes) y "📝 Datos Estudiante"
  + "✏️ Matrícula" (Matriculados).
- Los cases `guardar`, `guardar_ficha`, `obtener`, `obtener_ficha`, la
  función `abrirFicha()` y el handler `btn_guardar_ficha` quedan como
  código muerto, sin ningún llamador activo — no se eliminaron en este
  commit.
  → eliminados en el commit `5de9a9e` (2026-08-23).

### Decisiones
- Payload como objeto plano (no `FormData`) — sigue la convención ya
  documentada en CLAUDE.md de reservar `FormData` exclusivamente para
  subida real de archivos; `guardar_completo` no sube archivos (la foto
  sigue siendo un flujo separado vía `subir_foto`).
- Bug corregido en el camino, antes de mostrar el diff: el marcado de
  validación de los campos de ficha en `abrirEditar()` evaluaba `:visible`
  de forma síncrona, mientras el modal aún estaba en `display:none` —
  siempre reportaba `false` y no marcaba ningún campo. Se corrigió
  diferiéndolo a `$('#mdl_estudiante').one('shown.bs.modal', ...)`, mismo
  patrón que ya usaba el `abrirFicha()` original con su propio modal.
- Ambas tablas (`tablaAspirantes`/`tablaMatriculados`) se refrescan
  incondicionalmente tras guardar, mismo patrón ya establecido en el resto
  del archivo — no se agregó lógica para detectar cuál pestaña está activa.
- Verificado en navegador antes del commit: creación de aspirante nuevo,
  edición de estudiante existente, toggle Padre/Madre, cambio de
  Acudiente, subida de foto, descarga de PDF condicional al
  `estado_ficha`, botones de fila en ambas tablas, regresión de
  Matricular/Eliminar aspirante.
- Cierra la Fase C completa (C1 backend + C2 frontend) del plan de
  unificación de ficha de estudiante (6 fases A–F).

---

## [42e4175] — 2026-08-23 — feat: agrega endpoint transaccional único guardar_completo/obtener_completo

### Archivos modificados
- app/02_estudiantes/est_mdl.php

### Cambios/Vulnerabilidad corregida
- Nuevo `case 'guardar_completo'`: unifica creación y edición de estudiante
  + Ficha Familiar en una sola transacción PDO. Antes eran 2 transacciones
  separadas (`guardar` + `guardar_ficha`), cada una en su propio request
  HTTP, sin atomicidad real entre ambas — si `guardar_ficha` fallaba
  después de que `guardar` ya hubiera hecho `commit()`, el sistema
  quedaba con el estudiante creado/editado pero sin ficha consistente.
- La Ficha Familiar pasa a ser **obligatoria siempre**, sin excepción
  por modo creación/edición — antes solo era obligatoria al diligenciarse
  por separado vía `guardar_ficha`, después de que el estudiante ya
  existiera.
- Rama creación (`estu_id` vacío): mismas validaciones de unicidad y
  mismo INSERT que `guardar` (incluyendo `estu_origen = 'manual'`
  hardcodeado), resolviendo el `estu_id` nuevo con `lastInsertId()`
  para encadenar el guardado de la ficha en la misma transacción.
- Rama edición: mismas validaciones y mismo UPDATE que `guardar`,
  incluyendo el manejo opcional de cambio de clave.
- Ambas ramas confluyen en el mismo bloque de Ficha Familiar — el
  patrón SELECT-previo-para-decidir-INSERT/UPDATE que ya usaba
  `guardar_ficha`, ahora dentro de la misma transacción que el paso
  del estudiante, con un único `commit()`/`rollBack()`.
- Nuevo `case 'obtener_completo'`: combina estudiante + ficha familiar
  en un solo JSON vía `LEFT JOIN fichas_inscripcion` (mismo criterio
  que `pdf_ficha.php`, tolera estudiantes sin ficha diligenciada aún),
  con los mismos nombres de campo que ya usan `obtener`/`obtener_ficha`
  por separado.
- Los cases `guardar`, `guardar_ficha`, `obtener`, `obtener_ficha`
  quedan como código muerto — sin llamadores desde el frontend
  (`est_ctrl.js` no se tocó en este commit), pendientes de limpieza
  en una fase posterior.
  → eliminados en el commit `5de9a9e` (2026-08-23).

### Decisiones
- Ficha Familiar obligatoria también en creación de aspirante nuevo —
  decisión explícita para la Fase C (antes era opcional/diferida hasta
  después de crear el estudiante).
- Los cases antiguos no se eliminan en esta fase, para no romper nada
  mientras el frontend (Fase C2) sigue sin migrar — limpieza programada
  como ítem pendiente.
- Verificado manualmente vía fetch directo desde consola (creación,
  lectura combinada, rollback en validación fallida, duplicado,
  edición) antes de tocar el frontend.
- Fase C1 de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada (backend); Fase C2 (frontend) pendiente.

---

## [7cef01a] — 2026-08-23 — feat: agrega columna Edad en Matriculados con resaltado para menores

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- Nueva columna "Edad" en `tbl_matriculados`, posición 6 (entre "Documento"
  y "Correo"), con formato `"17 (23 sep)"` — edad calculada + fecha de
  cumpleaños abreviada.
- `est_mdl.php`, case `listar_matriculados`: se agrega `e.fechanacimiento`
  al SELECT existente, sin tocar `FROM`/`WHERE`/`ORDER BY`.
- `est_ctrl.js`: la edad se calcula en el `render()` de la columna a
  partir de `fechanacimiento` y la fecha actual, ajustando por mes/día
  (no solo la resta de años) — un estudiante no cumple años hasta que
  el mes/día actual alcanza o supera su mes/día de nacimiento.
- El mes de cumpleaños se abrevia a 3 letras (`ene`...`dic`) con un
  array local declarado en el propio `render()` — no hay extensión
  `intl` ni librería de fechas cargada en el proyecto.
- Si `fechanacimiento` es `NULL` (permitido a nivel de esquema, aunque
  hoy 0 registros lo tienen), la celda muestra `—` — mismo criterio
  defensivo ya usado en `estu_telefono`/`coho_codigo` de este archivo.
- Si la edad calculada es menor a 18, el texto se envuelve en un
  `<span style="background-color:#cfe2ff;padding:2px 6px;border-radius:4px;">`
  embebido directamente en el `render()` — mismo patrón de estilo
  inline ya usado en la Fase A (punto de color de `estado_ficha`), sin
  agregar clases CSS externas nuevas.
- `tablaAspirantes` queda sin cambios — la columna Edad es específica
  de Matriculados.

### Decisiones
- Color celeste (`#cfe2ff`, tipo `bg-info-subtle` de Bootstrap) elegido
  deliberadamente distinto del semáforo amarillo/rojo de
  `05_calificaciones` — evita mezclar semánticas ("dato a tener en
  cuenta" vs. "alerta de problema").
- Sin librería de fechas ni extensión `intl` disponibles en el proyecto
  (confirmado en el diagnóstico previo) — se optó por un array local
  de 12 abreviaturas de mes en español en vez de agregar una
  dependencia nueva.
- Fase B de 6 (A–F) del plan de unificación de ficha de estudiante —
  completada.

---

## [38e1809] — 2026-08-23 — feat(02_estudiantes): unifica botón Editar/Ficha en "📝 Datos Estudiante"

### Archivos modificados
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- El botón "Editar" (texto plano, sin ícono ni indicador de completitud)
  y el botón "📋 Ficha" (con punto de color rojo/amarillo/verde) convivían
  como dos entradas separadas en la misma fila, sin que el estado de la
  Ficha Familiar fuera visible desde el botón que abre los datos del
  estudiante.
- El botón "Editar" se renombra a "📝 Datos Estudiante" y adopta el mismo
  `<span>` de punto de color (`colorFicha`) y el mismo
  `title="${tituloFicha}"` que ya tenía el botón "Ficha" — reutiliza
  `row.estado_ficha` y las constantes `mapaColorFicha`/`mapaTituloFicha`
  ya calculadas en el mismo `render()`, sin duplicar lógica.
- Aplicado en los dos bloques equivalentes de `est_ctrl.js`: la columna
  de acciones de `tablaMatriculados` y la de `tablaAspirantes`.
- `onclick="abrirEditar(${row.estu_id}, ...)"` no cambia — mismo
  comportamiento, mismo modal "Editar Estudiante".
- Sin cambios en `calcularEstadoFicha()` (`est_mdl.php`), ni en
  `abrirEditar()`/`abrirFicha()` — el cambio es puramente de
  renderizado del botón.

### Decisiones
- Ícono elegido: 📝 — evita duplicar el ✏️ ya usado en el botón
  "✏️ Matrícula" de la misma fila.
- El botón "📋 Ficha" se mantiene intacto, con su propio punto de
  color — redundante de forma intencional en esta fase; se retira en
  la Fase C, cuando se fusionen los modales "Editar Estudiante" y
  "Ficha Familiar".
- Fase A de un plan de 6 fases (A–F) para unificar la ficha del
  estudiante: A (este cambio) → B (columna Edad en Matriculados) →
  C (fusión de modales) → D (config institucional en `08_admin`:
  número de matrícula inicial, Director, Secretario) → E (columnas
  nuevas en `matriculas`: folio, fecha, observaciones, número) →
  F (PDF Hoja de Matrícula, formato AC-FO-09).

---

## [ef79791] — 2026-08-22 — feat(02_estudiantes): precarga Programa y Jornada declarados en Completar Matrícula

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- `fichas_inscripcion` (Programa y Jornada declarados por el aspirante en
  la Ficha Familiar) y el modal "Completar Matrícula" no compartían
  ningún dato — el coordinador debía volver a elegir Programa/Cohorte
  desde cero aunque el aspirante ya los hubiera declarado.
- Nuevo case `obtener_defaults_matricula` en `est_mdl.php`: recibe
  `estu_id`, guard de sesión estándar (`isset($_SESSION['usua_id'])` +
  `in_array($role_id, [1, 2], true)`), lee `prog_id`/`jornada` de
  `fichas_inscripcion` con `getConexion()` (mismo patrón que los demás
  20 case del archivo). Devuelve `data: null` (con `status: 'ok'`)
  cuando el aspirante no tiene ficha diligenciada — el modal queda
  como antes, sin precargar nada.
- Nuevo select `#slct_jornada_declarada` (`disabled`, sin input oculto
  pareado) en el modal `mdl_matricular` de `est_view.php` — puramente
  informativo, no sigue el patrón select→input del proyecto porque
  nunca se envía al servidor.
- `abrirMatricular(estu_id)` en `est_ctrl.js` ahora llama a
  `obtener_defaults_matricula` al abrir el modal: si hay `prog_id`,
  preselecciona `#slct_prog_id` y dispara `.trigger('change')` (reutiliza
  la cascada existente de `cargarCohortesPorPrograma()`, sin duplicar
  lógica); si hay `jornada`, la muestra en `#slct_jornada_declarada`.
  Agrega además un callback `error` (no `.fail()`, seguido el patrón ya
  usado en la subida de foto de `est_ctrl.js`) que hace `console.warn()`
  ante un fallo de red — el modal sigue abierto y usable igual que para
  un aspirante sin ficha, sin `alert()` ni limpieza adicional.

### Decisiones
- `matriculas` no gana columna de jornada. El campo es puramente
  informativo/de referencia para el coordinador al crear el Grupo
  Semestre — no se persiste en ningún lado. La jornada operativa del
  sistema sigue siendo exclusivamente `gruposemestres.grse_jornada`
  (decisión ya activa en CLAUDE.md, sin cambios).
- Programa se preselecciona pero queda editable por el coordinador —no
  se bloquea el select ni se fuerza el valor— porque el aspirante pudo
  haber cambiado de programa entre la inscripción (Ficha Familiar) y la
  matrícula real.
- No se modificaron `mdl_ficha`, `guardar_ficha` ni `obtener_ficha` — el
  flujo de captura de la Ficha Familiar queda intacto; este cambio solo
  consume ese dato ya existente desde un endpoint nuevo, de solo lectura.

## [a83cb43] — 2026-08-20 — feat(grupos): agrega eliminación de Períodos (DELETE condicionado, mismo patrón de Módulos/Cohortes/Programas)

### Archivos modificados
- app/04_grupos/grupos_mdl.php
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js

### Cambios/Vulnerabilidad corregida
- La pestaña Períodos de `04_grupos` no tenía ninguna opción de
  eliminación — un período creado por error, o un período de prueba,
  quedaba para siempre en la tabla `periodos` sin forma de retirarlo.
- Nuevo case `eliminar_periodo` en `grupos_mdl.php`, siguiendo el mismo
  patrón "DELETE condicionado" ya usado en `eliminar_modulo`,
  `eliminar_cohorte` y `eliminar_programa`: bloquea el `DELETE` si el
  período tiene `gruposemestres` o `matriculas` asociadas.
- `listar_periodos` ahora incluye dos subconsultas escalares
  (`total_gruposemestres`, `total_matriculas`) para que el frontend
  decida si mostrar el botón Eliminar, mismo criterio ya usado en
  `listar_programas_crud`/`listar_modulos`.
- Nuevo modal de confirmación `mdl_confirmar_eliminar_periodo`
  (`modal-sm`), mismo patrón visual y de nomenclatura de ids que los
  modales equivalentes de Módulos/Cohortes/Programas
  (`spn_nombre_eliminar_periodo`, `btn_confirmar_eliminar_periodo`,
  `periodoIdPendienteEliminar`, `confirmarEliminarPeriodo()`).

### Decisiones
- **Sin alternativa reversible tipo "desactivar", a diferencia de
  Módulos/Cohortes/Programas.** En esos tres, `modu_activo`/
  `coho_activa`/`prog_activo` son flags de archivado por registro
  (cualquier fila puede desactivarse individualmente y reactivarse
  después, sin afectar a las demás). `periodos.peri_activo` tiene una
  semántica completamente distinta: "único período activo
  institucional a la vez" (patrón ya documentado en CLAUDE.md,
  gestionado por `activar_periodo` — desactiva todos, activa uno). No
  existe un `toggle_estado_periodo` boolean por-fila, y no tendría
  sentido crear uno solo para esta función: "desactivar" un período ya
  significa otra cosa en este esquema. El DELETE condicionado de
  Períodos queda entonces **sin alternativa reversible — solo
  bloqueo**, con mensaje explicando la causa (dependientes asociados),
  no una sugerencia de acción alternativa como en los otros tres casos.
- **Salvaguarda adicional: el período activo nunca puede eliminarse,
  incluso si está vacío (sin `gruposemestres` ni `matriculas`).** Esta
  verificación se ejecuta **antes** del conteo de dependientes —
  primero un `SELECT peri_activo FROM periodos WHERE peri_id = ?`; si
  `peri_activo = 1`, el `DELETE` se bloquea de inmediato con el mensaje
  "No se puede eliminar el período activo institucional. Active otro
  período primero.", sin siquiera llegar a contar `gruposemestres`/
  `matriculas`. Necesario porque un período recién creado y activado
  (sin ningún grupo semestre ni matrícula todavía) pasaría el chequeo
  de dependientes limpio, pero eliminarlo dejaría al sistema sin
  ningún período activo institucional — un estado inconsistente que
  rompería cualquier lógica que dependa de "resolver el período activo
  por defecto" (ya documentado en CLAUDE.md).
- En el frontend, el botón Eliminar exige ambas condiciones a la vez
  (`totalDependientes === 0 && row.peri_activo == 0`) — replica en el
  cliente la misma doble verificación del backend, para que el botón
  ni siquiera se muestre en un caso que el servidor rechazaría de
  todas formas.
- Verificado en el mismo ciclo que `eliminar_programa` (commit
  `00a22fc`) sigue íntegro en disco, sin ninguna alteración accidental
  desde su implementación original.

---

## [7340d6e] — 2026-08-20 — feat(estudiantes): edición de matrícula + fix de causa raíz en Asignación Estudiantes

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js
- app/04_grupos/grupos_mdl.php

### Cambios/Vulnerabilidad corregida
- **Bug detectado por el usuario:** un estudiante matriculado en el
  programa/período `ASO-2027-1` aparecía como "disponible" en el panel
  de Estudiantes Disponibles de un módulo perteneciente al grupo
  semestre `MD-2026-2` — un módulo de un programa y período distintos
  al de su matrícula real.
- **Causa raíz:** `listar_estudiantes_disponibles`
  (`04_grupos/grupos_mdl.php`) filtraba únicamente por
  `estudiantes.coho_id`, sin verificar en ningún momento la matrícula
  real del estudiante (`matriculas.prog_id`/`peri_id`). Como `coho_id`
  y la matrícula son datos independientes en el esquema (`matriculas`
  no tiene columna `coho_id` propia), cualquier estudiante con la
  cohorte "correcta" pasaba el filtro sin importar en qué programa o
  período estuviera realmente matriculado.
- **Fix:** la query ahora exige `matr_estado = 'matriculado'` con
  `prog_id`/`peri_id` coincidentes con el grupo semestre del `grmo_id`
  recibido (derivado vía `gruposmodulos → gruposemestres`), sin tocar
  nada del frontend de `04_grupos` — el fix es 100% backend.
- Nuevo modal "Editar Matrícula" en la pestaña Matriculados de
  `02_estudiantes`, para corregir Programa/Cohorte/Período de una
  matrícula ya creada (misma cascada Programa→Cohorte que "Completar
  Matrícula").
- 2 cases nuevos en `est_mdl.php`: `obtener_matricula` (lectura por
  `matr_id`) y `editar_matricula` — transacción que actualiza
  `matriculas.prog_id`/`peri_id` y `estudiantes.coho_id`, y **retira
  automáticamente** al estudiante de cualquier módulo
  (`grmoestudiantes`) cuyo grupo semestre ya no corresponda al nuevo
  programa+período, mediante un `DELETE` con `JOIN` contra
  `gruposmodulos`/`gruposemestres`.

### Decisiones
- `editar_matricula` opera por `matr_id` (no por la tripleta natural
  `estu_id+prog_id+peri_id` que usa `matricular`) — necesario porque
  cambiar `prog_id`/`peri_id` con el patrón de `matricular` hubiera
  creado una fila nueva en vez de modificar la existente, dejando la
  original huérfana sin retirar.
- `cargarCohortesPorPrograma()` se movió de función local (dentro de
  `$(document).ready`) a función global, con un tercer parámetro
  `callback` opcional — necesario porque `abrirEditarMatricula()` se
  invoca desde un `onclick` inline renderizado por el DataTable de
  Matriculados, y una función local hubiera lanzado `ReferenceError`
  al intentar llamarla (mismo patrón de scope ya documentado en
  CLAUDE.md para funciones invocadas desde onclick inline). El
  `callback` permite preseleccionar la cohorte recibida de forma
  determinista tras cargar el catálogo filtrado, sin depender de
  `setTimeout`.
- El llamado ya existente en el modal "Completar Matrícula"
  (`cargarCohortesPorPrograma(prog_id)`, sin segundo/tercer argumento)
  no requirió cambios — `selectorDestino` cae en su valor por defecto
  `'#slct_coho_id'`, preservando el comportamiento original.

---

## [b0e6660] — 2026-08-20 — feat(estudiantes): filtros por Programa/Período/Grupo/Módulo + columnas Cohorte/Módulos en Matriculados

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- La pestaña Matriculados no tenía forma de acotar el listado por
  programa, período, grupo o módulo — con el sistema creciendo en
  número de estudiantes matriculados, revisar un subconjunto obligaba
  a recorrer toda la tabla a ojo. Se agrega una fila de 4 filtros
  (Programa, Período, Grupo, Módulo), visible solo para roles 1/2,
  encima de `tbl_matriculados`.
- Los filtros se envían juntos en cada `ajax.reload()` del DataTable
  (función `data` de la config `ajax`) y se resuelven en el servidor
  con `WHERE`/`EXISTS` dinámico dentro de `listar_matriculados` — mismo
  patrón de "agregar condición solo si el filtro llegó por POST" ya
  usado en `listar_grupos` de `05_calificaciones`. A diferencia de ese
  precedente (filtros de una sola tabla vía `JOIN` directo), Grupo y
  Módulo se resuelven con subconsultas `EXISTS` contra
  `grmoestudiantes`/`gruposmodulos`, porque el vínculo estudiante↔grupo
  vive en esas tablas, no en `matriculas`.
- Nueva columna "Cohorte" (`coho_codigo`), agregada vía
  `LEFT JOIN cohortes co ON e.coho_id = co.coho_id` — antes no se
  mostraba, aunque el dato ya existía en `estudiantes.coho_id`.
- Nueva columna "Módulos": conteo de módulos activos del estudiante
  mediante una subconsulta escalar correlacionada
  (`grmoestudiantes → gruposmodulos → gruposemestres`), filtrado por el
  período seleccionado en el filtro de Período si hay uno activo, o
  contando todos los períodos si no hay filtro. El número es un botón
  que abre el modal `#mdl_modulos_estudiante` con el detalle (módulo,
  grupo, período, docente) de cada asignación.
- 3 cases nuevos en `est_mdl.php`: `listar_grupos_filtro` (catálogo de
  `gruposemestres` activos), `listar_modulos_filtro` (catálogo de
  `modulos` activos) y `listar_modulos_estudiante` (detalle por
  `estu_id`, con el mismo filtro opcional de `peri_id`).

### Decisiones
- El modal de detalle reutiliza exactamente el mismo `peri_id` que está
  seleccionado en el filtro de Período de la tabla al momento de abrir
  el modal — así el número mostrado en la columna "Módulos" y las filas
  del detalle siempre coinciden, sin que el usuario tenga que
  sincronizar manualmente dos filtros distintos.
- `listar_modulos_estudiante` no reutiliza `mis_modulos`
  (`06_reportes/reportes_mdl.php`) porque ese endpoint está diseñado
  para el propio estudiante autenticado (`WHERE est.usua_id = $_SESSION['usua_id']`,
  rol 4 exclusivo) y no expone `doce_nombres`/`doce_apellidos`/
  `peri_codigo` — se implementa un case nuevo, parametrizado por
  `estu_id` y restringido a roles 1/2, en vez de forzar un segundo modo
  sobre un endpoint pensado para otro caso de uso.
- El parámetro de la subconsulta `total_modulos` (cuando hay filtro de
  período) se coloca primero en el array de `execute()`, respetando el
  orden exacto en que aparece su placeholder `?` dentro del `SELECT`
  final — antes de los placeholders del `WHERE` dinámico (prog_id,
  peri_id, grse_id, modu_id, en ese orden).

---

## [cac382a] — 2026-08-20 — feat(estudiantes): columna de correo en Matriculados + cascada Programa→Cohorte→Período en modal de matrícula

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js

### Cambios/Vulnerabilidad corregida
- La tabla de Matriculados no mostraba el correo del estudiante
  (`estu_email`) — se agrega como columna "Correo", entre Documento y
  Programa, tanto en el `<thead>` como en el `SELECT` de
  `listar_matriculados` y en el array `columns` del DataTable.
- El modal "Completar Matrícula" pedía Programa y Período pero el
  select de Cohorte se poblaba con **todas** las cohortes activas del
  sistema, sin filtrar por el programa elegido — un coordinador podía
  matricular a un estudiante en una cohorte de un programa distinto al
  seleccionado. Se reordena el modal a la secuencia Programa → Cohorte
  → Período, con cascada real: Cohorte queda `disabled` hasta elegir
  un Programa y se repuebla vía AJAX al cambiarlo.
- Nuevo case `listar_cohortes_por_programa` en `est_mdl.php`: recibe
  `prog_id` por POST y filtra `cohortes` por `prog_id` y
  `coho_activa = 1` — duplicado deliberado de la query equivalente ya
  existente en `grupos_mdl.php` (mismo criterio ya documentado en
  CLAUDE.md de no reutilizar endpoints cross-módulo para catálogos).
- Cohorte pasa de campo opcional a **obligatorio**: se agrega su propia
  validación (`alert()` + `return false`) en el handler de
  `btn_confirmar_matricula`, junto a las ya existentes de Programa y
  Período.
- `abrirMatricular(estu_id)` ahora resetea y deshabilita los 3 selects
  a su estado inicial en cada apertura del modal — antes podía
  arrastrar la selección de Programa/Cohorte/Período del estudiante
  matriculado anteriormente si el modal no se había cerrado mediante
  el evento `hidden.bs.modal`.

### Decisiones
- Se reemplaza por completo `cargarCohortes()` (sin filtro, llamada
  una sola vez en `document.ready`) por
  `cargarCohortesPorPrograma(prog_id)` (llamada bajo demanda desde el
  listener `change` de `slct_prog_id`) — verificado con `grep` que
  `cargarCohortes()` no tenía ningún otro punto de uso en el archivo
  antes de eliminarla, evitando dejar código muerto.
- El nuevo endpoint `listar_cohortes_por_programa` se implementa
  duplicado en `est_mdl.php` en vez de invocar
  `grupos_mdl.php?accion=listar_cohortes_por_programa` (que ya existía
  con la misma query) — consistente con la decisión arquitectónica
  activa "Catálogos compartidos (programas, períodos, docentes) se
  duplican por módulo, no se centralizan".

---

## [00a22fc] — 2026-08-19 — feat(grupos): agrega CRUD de Programas y reordena pestañas de 04_grupos

### Archivos modificados
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_mdl.php
- app/04_grupos/grupos_view.php
- database/emdb_academica.sql

### Cambios/Vulnerabilidad corregida
- La tabla `programas` no tenía ningún CRUD en la aplicación — Programa
  ASO y MD solo existían por el seed inicial, igual que `periodos` antes
  del commit `35831fe`. Cualquier programa nuevo (o corrección de
  resolución/vigencia) debía insertarse manualmente vía SQL/phpMyAdmin.
- Esquema: `prog_vigencia` renombrada a `prog_fechavencimiento`;
  agregadas `prog_fechaaprobacion` (DATE), `prog_descripcion` (TEXT) y
  `prog_activo` (TINYINT(1) DEFAULT 1).
- Backend (`grupos_mdl.php`): nuevos case `listar_programas_crud`,
  `guardar_programa`, `eliminar_programa`, `toggle_estado_programa`,
  replicando el patrón CRUD ya usado para Módulos. El case
  `listar_programas` (selector simple `prog_id`/`prog_nombre`/
  `prog_sigla`) queda intacto — sigue alimentando los 3 selectores de
  programa que ya dependían de él (Cohortes, Grupos Semestre, Módulos).
- Frontend: nueva pestaña "Programas" (ahora primera y activa por
  defecto), con tabla DataTable, modal CRUD y modal de confirmación de
  borrado, siguiendo el mismo patrón visual de Módulos.
- Pestañas de `04_grupos` reordenadas: Programas, Módulos, Períodos,
  Cohortes, Grupos Semestre, Asignación Estudiantes.

### Decisiones
- `guardar_programa` bloquea el cambio de `prog_sigla` si el programa
  ya tiene módulos, cohortes o matrículas asociadas — evita
  inconsistencia con códigos ya generados (`grse_codigo`, etc.) que
  incluyen la sigla del programa.
- `eliminar_programa` sigue el patrón "DELETE condicionado + alternativa
  reversible" ya documentado en CLAUDE.md: bloquea el DELETE físico si
  hay dependientes en `modulos`, `cohortes` o `matriculas` (misma
  subconsulta de conteo usada en `listar_programas_crud` para decidir
  qué botón de acción mostrar), y el mensaje de error sugiere
  explícitamente desactivar el programa en su lugar
  (`toggle_estado_programa`, sin modal de confirmación, reversible en
  cualquier momento).
- `listar_programas` se mantiene deliberadamente sin tocar para no
  afectar el comportamiento ya validado de los 3 selectores que
  dependen de él — el nuevo listado completo para el DataTable de este
  CRUD usa el nombre `listar_programas_crud`, mismo criterio de
  nombrado ya usado en el archivo entre `listar_modulos` y
  `listar_modulos_por_programa`.

---

## [a49b652] — 2026-08-19 — feat(reportes): agrega nota explicativa de supletorios al pie de los PDF

### Archivos modificados
- app/06_reportes/pdf_boletin.php
- app/06_reportes/pdf_grupo.php

### Cambios/Vulnerabilidad corregida
- Nuevo párrafo `.nota-supletorios` agregado al pie de ambos PDFs (boletín
  individual del estudiante y reporte de grupo GA-FO-04), explicando en
  texto plano cómo funcionan las notas de recuperación: el supletorio se
  activa únicamente si la nota original (N1, N2 o N4) es 0.0 — el
  estudiante no presentó la prueba; N3 nunca tiene supletorio, por
  corresponder a trabajos, actividades y exposiciones en clase; y cómo se
  calculan Nota Final (N1 20% + N2 20% + N3 20% + N4 40%), Habilitación
  (si la Nota Final es menor a 3.0) y Definitiva (valor oficial).
- Mismo texto duplicado en ambos archivos, sin lógica nueva — solo
  HTML/CSS estático (`.nota-supletorios`, agregado al `<style>` de cada
  uno) insertado después de la leyenda de colores ya existente.

### Decisiones
- Texto aprobado directamente por el usuario antes de implementarse.
- Sin lógica de cálculo nueva ni cambio de datos — la fórmula y las
  reglas de supletorio que describe el párrafo ya eran las vigentes
  desde el rediseño de Nota Final/Habilitación/Definitiva (commit
  `58396d1`); este cambio solo las hace explícitas para quien lee el PDF.

---

## [35d6672] — 2026-08-19 — fix(reportes): elimina DISTINCT redundante que rompía el selector de módulos del estudiante

### Archivos modificados
- app/06_reportes/reportes_mdl.php

### Cambios/Vulnerabilidad corregida
- case mis_modulos combinaba SELECT DISTINCT con ORDER BY sobre una
  columna (modu_orden) ausente del SELECT — MySQL 8 lo rechaza bajo
  ONLY_FULL_GROUP_BY (SQLSTATE 3065). El catch genérico silenciaba la
  excepción y el selector de módulos quedaba vacío sin ningún aviso.
  Bug preexistente, no introducido en esta sesión — afectaba a
  cualquier estudiante que intentara ver sus módulos.

### Decisiones
- Cada fila ya es única por gm.grmo_id, así que el DISTINCT era
  redundante; se elimina en vez de agregar modu_orden al SELECT,
  manteniendo el mismo orden de salida sin reintroducir el conflicto
  con ONLY_FULL_GROUP_BY.

---

## [d62dde6] — 2026-08-19 — feat(estudiantes): migra autenticación de estudiantes al correo real

### Archivos modificados
- app/02_estudiantes/est_mdl.php
- app/02_estudiantes/est_ctrl.js
- app/02_estudiantes/est_view.php
- app/09_inscripcion_publica/insc_mdl.php
- database/emdb_academica.sql

### Cambios/Vulnerabilidad corregida
- El acceso de estudiantes matriculados se creaba con un correo
  sintético `{numerodoc}@emdb.local`, no comunicable ni verificable —
  reemplazado por el correo real capturado en `estudiantes.estu_email`.
- case `matricular` (02_estudiantes) ahora usa `estu_email` real para
  crear el usuario; bloquea la creación de acceso con mensaje explícito
  si el estudiante no tiene correo registrado.
- El modal de confirmación de matrícula ahora muestra el correo de
  acceso junto a la clave generada — antes solo mostraba la clave,
  comunicando una credencial incompleta.

### Decisiones
- `UNIQUE KEY uq_estu_email` agregada a `estudiantes.estu_email`.
- Validación de formato con `FILTER_VALIDATE_EMAIL` y de unicidad
  contra **ambas** tablas (`estudiantes.estu_email` y
  `usuarios.usua_email`) antes de guardar/editar un aspirante, en
  02_estudiantes (crear y editar) y en el formulario público
  09_inscripcion_publica — evita que dos aspirantes/estudiantes o un
  aspirante y un usuario ya existente colisionen en el correo.
- En la rama editar, la verificación contra `usuarios` excluye el
  propio `usua_id` del estudiante cuando ya tiene acceso creado
  (`SELECT usua_id FROM estudiantes WHERE estu_id = ?` primero),
  para no autobloquear el guardado de un estudiante que conserva su
  correo actual.
- Migrados manualmente los 3 usuarios existentes (`usua_id` 4, 15, 17)
  del correo sintético a su correo real, ya capturado previamente en
  `estudiantes.estu_email`.
- La lógica de generación de clave (4 letras del apellido + año de
  nacimiento) no cambió — solo el correo usado como `usua_email`/login.

---

## [d39ad68] — 2026-08-19 — fix(calificaciones): agrega desempate por nombres en orden alfabético

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php

### Cambios/Vulnerabilidad corregida
- El `ORDER BY` de `listar_calificaciones` no coincidía con el ya usado
  en `reportes_mdl.php` y `pdf_grupo.php` — dos estudiantes con el
  mismo apellido podían aparecer en orden distinto entre la planilla de
  notas y los reportes/PDF del mismo grupo.

### Decisiones
- Igualado a `ORDER BY estu_apellidos, estu_nombres`, el mismo criterio
  ya usado en `reportes_mdl.php` y `pdf_grupo.php`, sin introducir un
  tercer criterio de orden distinto para este endpoint.

---

## [17f42aa] — 2026-08-17 — feat(grupos): muestra conteo de carga de docente en selector de asignación de módulo

### Archivos modificados
- app/04_grupos/grupos_mdl.php
- app/04_grupos/grupos_ctrl.js

### Cambios/Vulnerabilidad corregida
- El selector de docente en el modal "Asignar Módulo al Grupo" no mostraba
  ninguna indicación de carga académica, a diferencia del listado de
  03_docentes (commit 7eba870) — dificultaba detectar sobrecarga de un
  docente antes de asignarle un módulo más.

### Decisiones
- Replicada la subconsulta escalar total_grupos ya usada en doc_mdl.php
  (filtrada por gs.peri_id = ? AND gm.grmo_activo = 1), resolviendo el
  período activo institucional con el mismo patrón "resolver activo por
  defecto" ya documentado (SELECT peri_id FROM periodos WHERE peri_activo
  = 1 LIMIT 1) — sin total_grupos_historico ni peri_codigo, innecesarios
  para este selector.
- cargarDocentesSelector() no cambió su ciclo de invocación (sigue
  llamándose una sola vez al cargar la página) — el conteo refleja la
  carga en el período activo institucional, no el período específico del
  grupo semestre que se esté editando en ese momento.

Verificado con php -l y balance de llaves/paréntesis en el JS.

---

## [3efb73f] — 2026-08-17 — fix(grupos): envuelve eliminar_cohorte en transacción PDO para cerrar ventana de condición de carrera

### Archivos modificados
- app/04_grupos/grupos_mdl.php

### Cambios/Vulnerabilidad corregida
- case eliminar_cohorte (commit 5611153) hacía DELETE físico con
  verificación previa (SELECT COUNT(*) sobre estudiantes y
  gruposemestres) pero sin transacción PDO — ventana teórica entre el
  COUNT y el DELETE.

### Decisiones
- Replicado el patrón ya usado en eliminar_aspirante (02_estudiantes) y
  en el guardar de doc_mdl.php: beginTransaction() después de los SELECT
  de verificación, commit() tras el DELETE exitoso, catch (PDOException)
  con guard isset($pdo) && $pdo->inTransaction() antes de rollBack().
  Mensaje de error existente sin cambios.

---

## [cfab7bf] — 2026-08-17 — fix(docentes): valida unicidad de doce_sigla con mensaje claro antes de guardar

### Archivos modificados
- app/03_docentes/doc_mdl.php

### Cambios/Vulnerabilidad corregida
- doce_sigla ya tenía UNIQUE KEY en BD (uq_doce_sigla, existente desde el
  DDL original) pero sin chequeo explícito en case guardar — un
  duplicado caía en el catch genérico de PDOException, mostrando "Error
  al guardar el docente" sin indicar la causa real.

### Decisiones
- Agregado SELECT doce_id FROM docentes WHERE doce_sigla = ? (rama
  crear) y la misma consulta con AND doce_id != ? (rama editar, excluye
  el propio registro), replicando el patrón ya usado para modu_sigla en
  grupos_mdl.php. Mensaje específico: "Ya existe un docente con esa
  sigla". Sin cambios en doc_ctrl.js — ya mostraba response.message
  correctamente. Sin duplicados reales en BD verificados antes del
  cambio, y sin necesidad de ALTER TABLE.

---

## [034d663] — 2026-08-17 — fix(grupos): corrige scope de periodoCodigoManual (bug que rompía Editar Período) y simplifica peri_codigo a readonly permanente

### Archivos modificados
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_view.php

### Cambios/Vulnerabilidad corregida
- abrirEditarPeriodo() (función global, invocada vía onclick inline)
  escribía periodoCodigoManual = true, pero esa variable estaba
  declarada dentro del closure de $(document).ready — ReferenceError en
  tiempo de ejecución que interrumpía el callback antes de mostrar el
  modal. "Editar Período" no abría el modal en producción.

### Decisiones
- Se decidió simplificar peri_codigo a readonly permanente (nunca
  editable, siempre 100% autogenerado desde Año + Semestre) en vez de
  solo corregir el scope — elimina por completo periodoCodigoManual
  (declaración, guard, listener input, reset y precarga en modo
  edición), replicando la misma simplificación ya aplicada a grse_codigo
  (commit 04345ea). form-text actualizado, quitando la mención a edición
  manual.

---

## [04345ea] — 2026-08-17 — feat(grupos): autogenerar grse_codigo (readonly) desde programa+período+semestre+jornada y ampliar modal a modal-xl

### Archivos modificados
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_view.php

### Cambios/Vulnerabilidad corregida
- grse_codigo era 100% manual, sin autogenerado ni validación de
  formato — los registros reales existentes eran inconsistentes entre
  sí (ninguno seguía el patrón del placeholder del formulario).
- El modal "Nuevo/Editar Grupo Semestre" (ya en modal-lg) se veía
  apretado: nombres de módulo/docente partidos en 2 líneas, campo Código
  cortado.

### Decisiones
- Autogeneración readonly permanente (no editable), fórmula
  {prog_sigla}_{peri_codigo}_S{grse_semestre}_{jornada_abrev}
  (ej. ASO_2026-2_S2_SEM), disparada por change en programa/período/
  semestre/jornada. prog_sigla expuesto vía data-sigla en el <option>
  del selector de programa (no estaba disponible antes). peri_codigo se
  lee directo del texto visible del <option> de período.
  jornadaMap: Semana→SEM, Sabados→SAB.
- Modal ampliado de modal-lg a modal-xl — el segundo uso de ese tamaño
  en todo el proyecto (el primero es mdl_ficha, Ficha Familiar AC-FO-02).
  La tabla de módulos asignados (w-100, fluida) se beneficia
  automáticamente del ancho extra.

---

## [624b44c] — 2026-08-17 — feat(grupos): preseleccionar período activo en modal Nuevo Grupo Semestre y sincronizar tras activar_periodo

### Archivos modificados
- app/04_grupos/grupos_ctrl.js

### Cambios/Vulnerabilidad corregida
- El modal "Nuevo Grupo Semestre" reseteaba #slct_peri_grupo a vacío en
  vez de preseleccionar el período activo del sistema (peri_activo=1),
  pese a existir ya esa información.
- Tras usar "Marcar como activo" en la pestaña Períodos, el selector de
  período del modal de grupo no reflejaba el cambio hasta recargar la
  página manualmente.

### Decisiones
- cargarPeriodosSelector() y la nueva variable periodoActivoId se
  movieron a scope global (junto a cacheModulos, moduloIdPendienteEliminar,
  cohorteIdPendienteEliminar) — activarPeriodo() es una función global
  invocada vía onclick inline y no tiene acceso a variables/funciones
  declaradas dentro de $(document).ready, mismo patrón de bug ya
  documentado y corregido antes para toggleEstadoCohorte/
  toggleEstadoModulo.
- activarPeriodo() ahora llama a cargarPeriodosSelector() tras el
  reload() de la tabla de períodos.

---

## [2780dff] — 2026-08-17 — fix(seguridad): agregar guard de autorización por rol en listar_grupos

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php

### Cambios/Vulnerabilidad corregida
- case listar_grupos tenía guard de autenticación (isset usua_id) pero
  no de autorización por rol — cualquier role_id distinto de 3 (Docente)
  caía en la rama "Coordinador/Admin: todos los grupos" sin verificar
  que fuera realmente 1 o 2. Un Estudiante (role_id 4) autenticado, o
  una sesión con role_id ausente/corrupto (0), recibía el listado
  completo de todos los grupos con nombres de docentes incluidos.

### Decisiones
- Agregado elseif (!in_array($role_id, [1, 2], true)) { ... Sin
  autorización ..., 'data' => [] ...} entre la rama de Docente y la
  rama de Coordinador/Admin, replicando el patrón ya usado en
  listar_calificaciones y guardar_nota del mismo archivo. Verificado con
  6 pruebas reales vía curl con cookies de sesión (admin, coordinador,
  docente, estudiante, role_id=0, role_id=99) — los 6 casos coincidieron
  con el comportamiento esperado.

---

## [1edb887] — 2026-08-16 — fix: agrega las 5 secciones faltantes al select del modal de ayuda

### Archivos modificados
- app/10_ayudas/ayud_view.php

### Cambios/Vulnerabilidad corregida
- El select "Sección" del modal Nueva/Editar Ayuda solo tenía las 2
  opciones piloto originales (04_grupos, 05_calificaciones) desde la
  Etapa 2 del sidebar de ayuda. Cuando se expandió el contenido real a
  los 5 módulos restantes (commit f7f0ae3, cargado directo por SQL), el
  select nunca se actualizó — un usuario admin no podía crear una nueva
  entrada de ayuda para esas secciones desde el navegador, aunque el
  backend ya las soportaba sin restricción.

### Decisiones
- Agregadas las 5 opciones faltantes (02_estudiantes, 03_docentes,
  06_reportes, 07_coordinador, 08_admin), reordenado el bloque completo
  para seguir el mismo orden del navbar (02 a 08).

Cambio puramente de HTML estático. Probado en navegador: las 7 secciones
aparecen correctamente en el select.

---

## [3d59f06] — 2026-08-16 — fix: refresco de matriculados al editar + feat: cambiar clave de estudiante matriculado

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/02_estudiantes/est_ctrl.js
- app/02_estudiantes/est_mdl.php

### Cambios/Vulnerabilidad corregida
- Al editar un estudiante ya matriculado y guardar, el listado de
  matriculados no se actualizaba automáticamente — solo se recargaba
  tablaAspirantes, nunca tablaMatriculados, a diferencia de los demás
  handlers del módulo (subir_foto, matricular, guardar_ficha) que sí
  recargaban ambas tablas.
- No existía forma de cambiar la clave de acceso de un estudiante ya
  matriculado — todo el manejo de clave vivía exclusivamente dentro del
  flujo de matrícula inicial; una vez creado el acceso, no había ningún
  mecanismo para resetearla.

### Decisiones
- Nuevo bloque opcional "Cambiar clave de acceso" dentro del mismo modal
  de edición de estudiante, visible solo cuando el estudiante ya tiene
  acceso creado — mismas 2 opciones que al matricular (automática vía
  generarClaveAuto(), o manual).
- El backend re-verifica server-side si el estudiante tiene usua_id
  (SELECT propio) antes de aplicar cualquier cambio de clave — no confía
  en el hidden field del cliente para esta decisión.
- Cambio de clave dentro de la misma transacción que ya actualiza los
  demás datos del estudiante; si no se marca ningún radio, no se toca
  usua_passwordhash en absoluto.
- Mismo patrón ya validado en admin_mdl.php (case editar): "vacío = no
  cambiar".

Probado: refresco automático de matriculados, bloque visible solo para
estudiantes con acceso (oculto para aspirantes), clave automática y
manual, validación de clave manual vacía, edición sin tocar la clave,
regresión del flujo de creación de aspirante.

---

## [f7f0ae3] — 2026-08-16 — feat: expande el sidebar de ayuda a los 5 módulos restantes

### Archivos modificados
- app/02_estudiantes/est_view.php
- app/03_docentes/doc_view.php
- app/06_reportes/reportes_view.php
- app/07_coordinador/coordinador_view.php
- app/08_admin/admin_view.php

### Cambios/Vulnerabilidad corregida
- El sidebar de ayuda (implementado como piloto en 04_grupos y
  05_calificaciones, commits fc9b56b/f801bdb/8947710) no estaba disponible
  en el resto de módulos funcionales del proyecto.

### Decisiones
- 4 módulos (02_estudiantes, 03_docentes, 07_coordinador, 08_admin) solo
  requirieron declarar MODULO_ACTUAL + incluir ayuda_sidebar.js — el
  offcanvas ya llegaba a todos sus roles vía navbar.php.
- 06_reportes fue el único caso especial: el guard permite role_id 4
  (Estudiante), que usa su propio navbar fallback en vez de navbar.php —
  se duplicó el botón + offcanvas en esa rama, mismo patrón ya establecido
  con Docente en calificaciones_view.php.
- Todos los módulos funcionales ya tienen el sidebar disponible; solo
  04_grupos y 05_calificaciones tienen contenido real cargado en BD hasta
  el momento — el resto muestra "sin ayuda disponible" hasta que se cargue
  contenido.

Probado en navegador para 4 módulos simples, y con una cuenta de
estudiante de prueba real (creada, matriculada, logueada, verificada por
HTML crudo, y eliminada al final directamente en BD por el orden de FKs,
ya que el proyecto no tiene endpoint de eliminación para estudiantes
matriculados por diseño) para confirmar el caso especial de 06_reportes.

---

## [fc9b56b, f801bdb, 8947710] — 2026-08-16 — feat: sidebar de ayuda con CRUD (10_ayudas) — 3 etapas

### Archivos modificados
- database/emdb_academica.sql
- app/10_ayudas/ayud_mdl.php
- app/10_ayudas/ayud_view.php
- app/10_ayudas/ayud_ctrl.js
- app/00_files/navbar.php
- app/00_files/ayuda_sidebar.js
- app/04_grupos/grupos_view.php
- app/05_calificaciones/calificaciones_view.php

### Cambios/Vulnerabilidad corregida
- No existía ningún mecanismo para mostrar contenido de ayuda/instrucción
  contextual dentro de la aplicación — los usuarios dependían enteramente
  de soporte externo para dudas sobre el manejo de cada sección.

### Decisiones
- Etapa 1 (esquema + backend): nueva tabla ayudas, con ayud_seccion como
  identificador de carpeta de módulo de la app (ej. "04_grupos") —
  deliberadamente distinto de la tabla modulos (asignaturas académicas),
  para evitar colisión de terminología. Múltiples entradas por sección
  (título + contenido cada una), con ayud_orden para apilarlas. Backend
  con guard role_id !== 1 (solo Admin) para todo el CRUD, excepto
  listar_por_modulo — primer endpoint del proyecto sin chequeo de rol
  (solo sesión activa), ya que cualquier rol autenticado debe poder leer
  la ayuda de su sección.
- Etapa 2 (UI de administración): módulo nuevo 10_ayudas, exclusivo de
  Admin, replicando el patrón CRUD estándar del proyecto (mismo criterio
  de acceso ya usado en 08_admin).
- Etapa 3 (offcanvas + integración piloto): primer uso del componente
  offcanvas de Bootstrap 5.3 en el proyecto. Botón "Ayuda" visible para
  todos los roles, contenido cargado dinámicamente según una variable
  MODULO_ACTUAL declarada por cada vista. Piloto en 2 módulos: 04_grupos
  y 05_calificaciones. Ajuste no previsto originalmente:
  calificaciones_view.php tiene un navbar fallback separado para Docente
  (navbar.php solo cubre roles 1/2) — se duplicó el botón/offcanvas en esa
  rama para no dejar sin acceso a la audiencia principal del piloto.
- Contenido de ayuda renderizado con escape de HTML en el frontend,
  previniendo XSS desde el textarea libre del CRUD.

Probado end-to-end en las 3 etapas: backend vía curl con sesiones
separadas (admin/docente reales, incluyendo validación cruzada de rol en
listar_por_modulo), UI de administración completa (crear/editar/cambiar
estado/eliminar), e integración de offcanvas en ambos módulos piloto
(incluyendo el caso especial de Docente en calificaciones).

---

## [6c496e4] — 2026-08-16 — feat: filtros de profesor/programa/período en calificaciones (Coordinador/Admin)

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php
- app/05_calificaciones/calificaciones_view.php
- app/05_calificaciones/calificaciones_ctrl.js

### Cambios/Vulnerabilidad corregida
- El listado de grupos en 05_calificaciones no tenía forma de filtrar por
  profesor, programa ni período, obligando a Coordinador/Admin a revisar
  el listado completo sin ninguna forma de acotarlo.

### Decisiones
- Filtros exclusivos de Coordinador/Admin (envueltos en el mismo
  condicional PHP server-side ya usado en el archivo para el navbar) — la
  rama Docente no se modificó.
- listar_grupos construye el WHERE dinámicamente sobre columnas ya
  disponibles en los JOIN existentes, sin necesidad de ningún JOIN nuevo.
- Se duplicaron 3 catálogos (programas, períodos, docentes) dentro de
  calificaciones_mdl.php en vez de reutilizar los equivalentes de
  04_grupos vía llamada cross-módulo — misma convención ya aplicada en el
  commit 7eba870 (03_docentes replicó su propio listar_periodos por el
  mismo motivo: módulos independientes, evitar acoplamiento frágil entre
  carpetas).

---

## [b489fa5] — 2026-08-16 — feat: texto de ayuda para siglas + corrige maxlength y mayúsculas

### Archivos modificados
- app/03_docentes/doc_view.php
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_mdl.php

### Cambios/Vulnerabilidad corregida
- Los campos de sigla (docente y módulo) no tenían ninguna indicación de
  cómo formarlas correctamente, dejando al usuario sin referencia sobre la
  convención esperada.
- npt_doce_sigla tenía maxlength=10 en el frontend, mientras que la
  columna real doce_sigla es VARCHAR(6) — un valor de 7-10 caracteres
  habría fallado al guardar (error no capturado explícitamente en el
  frontend, solo al hacer el INSERT/UPDATE).
- modu_sigla no se normalizaba a mayúsculas en el backend (a diferencia de
  doce_sigla, que sí lo hacía) — un valor pegado en minúscula quedaba
  guardado tal cual pese a verse en mayúscula por CSS (texto-mayus).

### Decisiones
- Texto de ayuda redactado a partir de la convención real ya usada en los
  datos existentes (confirmada por diagnóstico, no asumida): iniciales de
  nombre+apellido para docentes (ej. María Eugenia Rodríguez → MR),
  prefijo de programa + abreviatura para módulos (ej. ASO-BIO, MD-IMD).
  Redactado como sugerencia, no como regla — ninguno de los dos campos
  tiene validación de formato real en el backend, solo de unicidad.
- maxlength corregido a 6 en npt_doce_sigla.
- Agregado strtoupper() a modu_sigla en grupos_mdl.php, mismo patrón ya
  usado para doce_sigla.

---

## [2dd4a55] — 2026-08-15 — feat: eliminar/desactivar docente + corrige desincronización de doce_activo

### Archivos modificados
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js
- app/03_docentes/doc_mdl.php

### Cambios/Vulnerabilidad corregida
- No existía forma de eliminar un docente desde 03_docentes, solo editar.
- Bug real preexistente detectado durante el análisis: docentes.doce_activo
  (usado por el KPI total_docentes en 07_coordinador) nunca se actualizaba
  desde 03_docentes — solo usuarios.usua_activo cambiaba al editar. Ambos
  campos quedaban desincronizados desde la creación del docente,
  invalidando silenciosamente el KPI del dashboard del coordinador.

### Decisiones
- Regla de eliminación más estricta que en módulos/cohortes: solo se puede
  eliminar un docente que NUNCA haya tenido ningún grupo asignado
  (histórico completo, sin filtro de período ni de grmo_activo) — un
  docente con grupos en períodos pasados solo puede desactivarse.
- Nuevo case eliminar_docente: DELETE en transacción que borra también la
  fila de usuarios asociada explícitamente (mismo patrón que
  eliminar_aspirante, no depende del ON DELETE SET NULL de la FK).
- Nuevo case toggle_estado_docente: sincroniza doce_activo y usua_activo
  al mismo valor en cada cambio, corrigiendo el bug de desincronización.
- listar ahora incluye doce_activo y total_grupos_historico (distinto de
  total_grupos, que sigue siendo el conteo del período seleccionado
  agregado en el commit 7eba870).
- toggleEstadoDocente usa $('#tbl_docentes').DataTable().ajax.reload()
  directamente, aplicando el fix de scope ya documentado para funciones
  invocadas vía onclick inline de DataTables.

Probado end-to-end vía endpoints reales: creación/eliminación con
verificación de cascada de usuario, docente con histórico (solo
desactivar), sincronización de ambos flags en ambas direcciones (0→0 y
1→1, con reversión controlada), KPI de 07_coordinador reflejando el
cambio. Confirmado que 08_admin excluye deliberadamente role_id 3/4
(comportamiento esperado, no bug relacionado).

---

## [7eba870] — 2026-08-15 — feat: período activo (peri_activo) + conteo de grupos por docente

### Archivos modificados
- database/emdb_academica.sql
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_mdl.php
- app/03_docentes/doc_view.php
- app/03_docentes/doc_ctrl.js
- app/03_docentes/doc_mdl.php

### Cambios/Vulnerabilidad corregida
- La tabla periodos no tenía forma de marcar cuál es el período académico
  vigente, lo que impedía calcular indicadores dependientes de "el período
  actual" (ej. carga docente).
- El listado de docentes no mostraba cuántos grupos tenía asignados cada
  uno, obligando a revisarlo módulo por módulo en 04_grupos.

### Decisiones
- Nueva columna peri_activo (uno solo activo a la vez, garantizado por
  transacción de aplicación, no por constraint de BD — ninguna columna de
  este tipo en el proyecto tiene esa garantía a nivel de esquema).
  Migración inicial marcó peri_id=4 (2027-1, el más reciente por
  peri_anio/peri_semestre) como activo por defecto; durante las pruebas se
  reasignó manualmente a 2026-2 (el período con datos reales de
  gruposmodulos) usando el nuevo botón 'Marcar como activo' — 2026-2 quedó
  como el período activo vigente al cierre de esta tarea.
- Nuevo case activar_periodo en grupos_mdl.php: transacción PDO que
  desactiva todos y activa solo el elegido.
- Columna Estado + botón "Marcar como activo" en tbl_periodos, mismo
  patrón visual que cohortes/módulos.
- doc_mdl.php (listar) ahora acepta un peri_id opcional; si no viene,
  resuelve el período activo con una query separada. El conteo de grupos
  usa subconsulta correlacionada (gruposmodulos JOIN gruposemestres,
  filtrando grmo_activo=1), mismo patrón que evitó fan-out en cohortes.
- Se usó bind posicional (?) en vez de placeholder nombrado reutilizado
  (:peri_id dos veces), porque PDO::ATTR_EMULATE_PREPARES=false en
  00_connect/pdo.php hace que reutilizar un placeholder nombrado sea poco
  confiable con prepares nativos de MySQL.
- 03_docentes replica su propio case listar_periodos en vez de reutilizar
  el de 04_grupos — son módulos independientes sin JS/estado compartido.

---

## [7fe1151] — 2026-08-15 — feat: divide la fila de contexto del Excel exportado en 2 filas reales

### Archivos modificados
- app/06_reportes/reportes_ctrl.js

### Cambios/Vulnerabilidad corregida
- La fila de contexto del Excel exportado (Módulo — Grupo — Docente —
  Programa — Período — Jornada) quedaba como una sola línea muy ancha,
  perdida visualmente en la hoja.

### Decisiones
- Se dividió en 2 filas: Fila 1 (Módulo — Grupo — Docente), Fila 2
  (Programa — Período — Jornada), con los encabezados de columna y datos
  de notas arrancando en la fila 3.
- La exportación es 100% client-side (DataTables Buttons + JSZip), sin
  PhpSpreadsheet ni manipulación propia de XML previa en el proyecto — no
  bastaba con cambiar el string de la opción title, se reemplazó por la
  API customize del botón excelHtml5, que edita directamente el XML de la
  hoja (sheet1.xml) ya generado por el plugin: inserta 2 <row> nuevas al
  inicio, reindexa todas las filas y celdas existentes (+2 posiciones), y
  actualiza el rango declarado (dimension) de la hoja.
- construirContextoTexto(g) pasó de devolver un string único a un objeto
  { linea1, linea2 } — confirmado que no se usaba en ningún otro punto del
  archivo (la vista en pantalla usa los spn_ctx_* directamente desde el
  objeto g, de forma independiente).

---

## [5611153] — 2026-08-15 — feat: eliminar/desactivar cohorte con verificación de dependencias

### Archivos modificados
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_mdl.php

### Cambios/Vulnerabilidad corregida
- No existía forma de eliminar una cohorte desde 04_grupos, solo editar.
- Ninguna FK entrante a cohortes es RESTRICT (estudiantes.coho_id y
  gruposemestres.coho_id son ambas ON DELETE SET NULL), por lo que la
  verificación de aplicación es la única protección real contra pérdida de
  trazabilidad al eliminar.

### Decisiones
- Nuevo case eliminar_cohorte: bloquea el DELETE si la cohorte tiene
  estudiantes matriculados o grupos semestre asociados, con mensaje de
  error específico para cada caso.
- listar_cohortes ahora calcula total_estudiantes y total_grupos con
  subconsultas escalares correlacionadas, no LEFT JOIN — dos relaciones
  1:N sobre la misma fila producirían fan-out (conteos inflados) con JOIN
  simultáneo.
- Columna Acciones de tbl_cohortes con lógica de 3 ramas (Eliminar/
  Desactivar/Activar), mismo patrón ya usado en tbl_modulos.
- Nuevo modal de confirmación mdl_confirmar_eliminar_cohorte, replicando
  el patrón de fila de tabla (mdl_confirmar_eliminar_modulo), no el de
  modal anidado (mdl_confirmar_eliminar_grmo) — son flujos de UI distintos.
- Bug propio corregido en la misma iteración: toggleEstadoCohorte llamaba
  a cargarTablaCohortes(), función local al closure de $(document).ready
  e inaccesible desde el scope global donde vive la función (necesario
  para el onclick inline de DataTables) — corregido a
  $('#tbl_cohortes').DataTable().ajax.reload(null, false), mismo patrón
  que toggleEstadoModulo.

---

## [f46d9d9] — 2026-08-15 — fix: precarga módulo/docente al editar + permite cambiar módulo + eliminar módulo-grupo con confirmación

### Archivos modificados
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_mdl.php

### Cambios/Vulnerabilidad corregida
- El modal "Asignar Módulo al Grupo" (mdl_modulo_grupo) no precargaba los
  selects de Módulo ni Docente al editar un módulo-grupo ya asignado —
  aparecían vacíos en "-- Seleccionar --". Causa raíz doble: (a)
  abrirEditarModuloGrupo nunca poblaba las opciones del select de Módulo
  (solo se poblaba en el flujo "Nuevo"), y (b) el endpoint
  listar_modulos_grupo no devolvía modu_id ni doce_id, sin los cuales no
  había nada que preseleccionar.
- No existía forma de eliminar una asignación módulo-grupo desde el modal.

### Decisiones
- listar_modulos_grupo ahora incluye modu_id y doce_id en el SELECT.
- Se extrajo cargarSelectModulosPrograma() como función compartida
  (antes duplicada solo en el flujo "Nuevo"), reutilizada también al editar.
- El título del modal ahora cambia dinámicamente entre "Asignar Módulo al
  Grupo" (nuevo) y "Editar Módulo" (edición).
- guardar_modulo_grupo (rama UPDATE) ahora permite cambiar el módulo
  asignado, con verificación de duplicado grse_id+modu_id excluyendo el
  propio grmo_id — antes el módulo quedaba fijo tras la creación.
- Nuevo case eliminar_modulo_grupo: guard de sesión/rol, bloquea el DELETE
  si el grupo-módulo ya tiene calificaciones registradas (ON DELETE
  RESTRICT como salvavidas final), ON DELETE CASCADE limpia
  automáticamente grmoestudiantes sin necesidad de borrado explícito.
- Botón "Eliminar" visible solo en modo edición, con modal de confirmación
  separado — mismo patrón visual ya usado en eliminar_aspirante
  (02_estudiantes).

---

## [5bef1ef] — 2026-08-15 — refactor: mueve jornada de cohorte a grupo semestre

### Archivos modificados
- app/04_grupos/grupos_view.php
- app/04_grupos/grupos_ctrl.js
- app/04_grupos/grupos_mdl.php
- app/06_reportes/reportes_mdl.php
- app/06_reportes/reportes_ctrl.js
- app/06_reportes/pdf_grupo.php
- app/06_reportes/pdf_boletin.php
- database/emdb_academica.sql

### Cambios/Vulnerabilidad corregida
- Se movió la captura del campo jornada (Semana/Sábados) de la entidad
  cohorte a la entidad grupo semestre. Una cohorte marca el inicio de
  varios grupos semestre, cada uno con su propia jornada — el campo no
  pertenecía al nivel de cohorte.
- Migración en 4 etapas: (1) se agregó grse_jornada a gruposemestres y se
  migraron los datos existentes desde cohortes.coho_jornada (2 grupos
  semestre de CH-ASO-2026B heredaron el valor 'Sabados'), (2) se movió la
  captura del modal de Cohorte al modal de Grupo Semestre, (3) se
  actualizaron todos los puntos de lectura (DataTable de cohortes, select
  de cohorte en el modal de grupo, y los tres puntos de 06_reportes que
  leían coho_jornada), (4) se eliminó la columna coho_jornada de cohortes.

### Decisiones
- No se agregó ENUM ni CHECK constraint en grse_jornada, replicando
  exactamente el tipo original de coho_jornada (VARCHAR(20) DEFAULT
  'Semana') — los valores válidos siguen enforced solo por el <select> del
  frontend, consistente con el resto del proyecto.
- Los grupos semestre ya existentes de una misma cohorte heredaron el mismo
  valor de jornada por copia directa en la migración (ej. los 2 grupos de
  CH-ASO-2026B); a partir de ahí cada grupo queda libre de editarse
  independientemente.
- Se eliminó también el LEFT JOIN cohortes en reportes_mdl.php,
  pdf_grupo.php y pdf_boletin.php, ya que ese JOIN existía únicamente para
  traer coho_jornada y no se usaba para ningún otro campo.

---

## [0eddadf] — 2026-08-15 — fix: valida sesión en listar_grupos (05_calificaciones)

### Archivos modificados
- app/05_calificaciones/calificaciones_mdl.php

### Cambios/Vulnerabilidad corregida
- El case `listar_grupos` no tenía el guard `isset($_SESSION['usua_id'])`
  que sí tienen los demás case del archivo (`listar_calificaciones`,
  `guardar_nota`). Con sesión vacía, `role_id` caía en 0 vía `?? 0`, entraba
  en la rama "Coordinador/Admin: todos los grupos" y exponía sin
  autenticación el listado completo de grupos, módulos, nombre completo de
  docentes y conteo de estudiantes por grupo — accesible por URL directa a
  `calificaciones_mdl.php?accion=listar_grupos` sin pasar por la vista.
- Hallazgo detectado el 2026-08-15 al verificar (y confirmar como ya
  resuelto) el hallazgo previo de seguridad en `est_mdl.php` (commit
  920bcfe, 2026-08-07) — asimetría dentro del mismo archivo
  `calificaciones_mdl.php`, ya que los demás case sí tenían el guard desde
  los commits 75504eb/04ec8b0 (2026-07-19).

### Decisiones
- Fix quirúrgico: se agregó únicamente el guard de sesión, replicando
  carácter por carácter el patrón ya usado en `listar_calificaciones` del
  mismo archivo. La lógica de ownership por rol (docente restringido a sus
  grupos vía `docentes.usua_id`) ya estaba correctamente implementada y no
  se modificó.
- Se incluyó `'data' => []` en el envelope de error por convención de
  uniformidad del proyecto, aunque el frontend (`calificaciones_ctrl.js`)
  ya manejaba `r.data` ausente de forma segura sin ese campo.

---

## [6ef7e85, e7e6b3b, 0eca14e] — 2026-08-08 — feat: CRUD completo de módulos en 04_grupos

### Archivos modificados
- app/04_grupos/grupos_mdl.php (6ef7e85 — backend), app/04_grupos/grupos_view.php (e7e6b3b — frontend HTML), app/04_grupos/grupos_ctrl.js (0eca14e — JS)

### Cambios
- Nueva pestaña "Módulos" en `04_grupos`, roles 1-2, con listar/crear/editar/eliminar y desactivar/activar — mismo patrón de pestañas ya usado para Cohortes, Períodos y Grupos Semestre.
- `eliminar_modulo`: DELETE físico solo si el módulo no tiene historial en `gruposmodulos` (`SELECT COUNT(*) ... WHERE modu_id = ?` antes de borrar); bloqueado con mensaje claro si lo tiene, sugiriendo desactivar en su lugar.
- `toggle_estado_modulo`: alterna `modu_activo` en ambos sentidos, sin confirmación (reversible) — el módulo desactivado desaparece del dropdown de `listar_modulos_por_programa` (ya filtraba por `modu_activo=1`) sin tocar ese endpoint.
- `guardar_modulo` (crear/editar combinados): valida unicidad de `(prog_id, modu_sigla)` vía `SELECT` previo antes de INSERT/UPDATE, respetando `uq_modu_sigla_prog`.
- Implementado en 3 fases con checkpoint de prueba entre cada una: backend verificado con 8 pruebas vía curl (incluyendo bloqueo de rol no autorizado y bloqueo de eliminación con historial), frontend HTML verificado visualmente sin JS conectado, JS verificado con 7 pruebas manuales en navegador (crear, sigla duplicada, editar, eliminar, desactivar/activar con verificación cruzada en el dropdown de Grupos Semestre, gate de roles).

### Decisiones
- DELETE condicionado en vez de desactivación forzosa: se eligió permitir eliminar físicamente solo cuando `gruposmodulos` no tiene filas para ese módulo (caso real: módulo creado por error, nunca usado). Para módulos con historial real, `modu_activo` (ya existente en el esquema) cubre el caso de retirar del plan de estudios vigente sin perder trazabilidad de calificaciones/grupos pasados.
- Sin auditoría en `eliminar_modulo` — mismo patrón ya establecido para `eliminar_aspirante` (commit 7d72327): borrado permanente sin motivo ni registro de quién/cuándo, por decisión de diseño consistente entre ambos módulos.
- `programa_modulos` (FK con RESTRICT hacia `modulos`) se ignoró en la lógica de bloqueo de `eliminar_modulo` porque ningún flujo actual del sistema la puebla — confirmado en el diagnóstico previo a la Fase 1.

---

## [7d72327] — 2026-08-08 — feat: eliminar aspirante desde el modal Editar (02_estudiantes)

### Archivos modificados
- app/02_estudiantes/est_mdl.php, app/02_estudiantes/est_view.php, app/02_estudiantes/est_ctrl.js

### Cambios
- Nuevo endpoint `eliminar_aspirante` en `est_mdl.php` — DELETE físico en transacción PDO, restringido a roles 1 y 2 (coordinador/admin).
- Verifica en servidor que no exista una matrícula real (`matr_estado` distinto de `'aspirante'`) antes de proceder — bloquea con mensaje claro si la hay, sin depender de que el frontend oculte el botón.
- Borra la fila de `matriculas` cuando `matr_estado='aspirante'`; `fichas_inscripcion` se elimina sola por `ON DELETE CASCADE`.
- Botón "Eliminar aspirante" agregado al modal Editar (`est_view.php`), visible solo cuando el modal se abrió desde la tabla de Aspirantes (`abrirEditar(estu_id, esAspirante)`).
- Nuevo modal de confirmación (`mdl_confirmar_eliminar_aspirante`) con texto explícito de irreversibilidad — no existía un patrón de confirmación reutilizable previo en el proyecto.

### Decisiones
- DELETE físico, sin guardar motivo ni registro de auditoría — decisión de diseño confirmada explícitamente, no un descuido.
- `calificaciones` y `grmoestudiantes` mantienen su RESTRICT/CASCADE existente como salvavidas — si un aspirante tuviera datos inesperados ahí, la transacción falla y hace rollback en vez de forzar el borrado.
- Verificado manualmente: aspirante sin matrícula, aspirante con matrícula `'aspirante'` (ambas filas se eliminan correctamente), y bloqueo de un matriculado real tanto desde la UI (botón oculto) como directamente contra el backend vía curl (confirma que el bloqueo no depende solo del frontend).

---

## [75907a3] — 2026-08-08 — feat: muestra nombre completo del módulo e inicial del docente en select de Reportes

### Archivos modificados
- app/06_reportes/reportes_ctrl.js

### Cambios
- `#sel_grupo` (rol coordinador/admin) ahora incluye `modu_nombre` junto a la sigla del módulo en el texto de cada `<option>`.
- Apellido del docente ahora precedido por la inicial del primer nombre (ej. "L.Gómez"), vía nueva función auxiliar `inicialDocente()` que toma el primer token de `doce_nombres` aunque venga con varios nombres.
- Conteo de estudiantes abreviado de "estudiantes" a "est".
- `#sel_modulo` (rol estudiante) sin cambios — ya mostraba `modu_nombre` correctamente antes de este ajuste.

### Decisiones
- Sin cambios en `reportes_mdl.php` ni en la query SQL de `grupos_para_reporte` — `modu_nombre`, `doce_nombres` y `doce_apellidos` ya viajaban en el JSON, solo faltaba usarlos en el texto del `<option>`.
- El `value` del select (`grmo_id`) no se modificó.

---

## [7216637] — 2026-08-08 — fix: fija zona horaria America/Bogota (UTC-5) en Docker y conexión PDO

### Archivos modificados
- docker-compose.yml, docker/Dockerfile, app/00_connect/pdo.php

### Cambios
- Diagnóstico previo detectó que los contenedores `app` y `db` corrían en UTC (sin TZ, sin date.timezone en PHP, MySQL time_zone=SYSTEM), 5 horas adelantados respecto a la hora real de Bogotá, aunque el host Fedora sí estaba correcto.
- TZ=America/Bogota agregado como variable de entorno en los servicios `app` y `db` de docker-compose.yml.
- `command: --default-time-zone=-05:00` agregado al servicio `db` — offset fijo, sin horario de verano.
- docker/Dockerfile: tzdata instalado + enlace de `/etc/localtime`/`/etc/timezone` a America/Bogota; nuevo `date.timezone = America/Bogota` en `conf.d/timezone.ini`, ya que la imagen `php:8.5-apache` no carga ningún php.ini por defecto.
- `app/00_connect/pdo.php`: `SET time_zone = '-05:00'` ejecutado justo después de crear la conexión PDO, para que la sesión de la app siempre use la zona horaria correcta sin depender de la config global del servidor MySQL.

### Decisiones
- Sin corrección de datos existentes — las columnas TIMESTAMP ya almacenaban el instante UTC real; al cambiar el time_zone de sesión, MySQL reinterpreta esos mismos instantes con el offset correcto, sin necesidad de UPDATE.
- Offset fijo `-05:00` en vez de nombre de zona horaria de MySQL (`America/Bogota`) — Colombia no tiene horario de verano, y usar el offset evita depender de que las tablas `mysql.time_zone*` estén cargadas en el servidor.
- Pendiente manual: replicar `SET time_zone = '-05:00'` en `pdo_web.php` en producción — ese archivo no está versionado en git (mantenimiento manual directo en el hosting cPanel).

---

## [1531593] — 2026-08-07 — feat: Ficha Familiar obligatoria + indicador de 3 estados en Aspirantes/Matriculados

### Archivos modificados
- app/09_inscripcion_publica/fam_ctrl.js, app/09_inscripcion_publica/fam_mdl.php, app/02_estudiantes/est_mdl.php, app/02_estudiantes/est_ctrl.js

### Cambios
- Cierra el hallazgo crítico detectado en la Fase 5 (pruebas extremo a extremo): el indicador binario de "ficha completa" daba falso positivo para aspirantes web que abandonaban el formulario tras el Paso 1.
- Ficha Familiar ahora obligatoria en el formulario público (fam_ctrl.js/fam_mdl.php), con validación en dos capas (visual + servidor).
- Misma validación server-side agregada al flujo interno del coordinador (est_mdl.php case guardar_ficha), que antes solo validaba en el JS.
- Nueva función calcularEstadoFicha() clasifica cada registro en sin_iniciar/incompleta/completa según los campos realmente diligenciados, no solo la existencia de la fila. Aplicada en listar_aspirantes y listar_matriculados.
- Indicador visual de 3 colores (rojo/ámbar/verde) en ambas tablas de 02_estudiantes.

### Decisiones
- Sin migración de BD — ninguna columna pasa a NOT NULL, la obligatoriedad vive a nivel de aplicación. Los registros existentes se reclasifican automáticamente al leer los mismos datos, sin tocar filas.
- Probado con 9/9 casos vía curl (validación server-side en ambos endpoints, incluyendo confirmación de que el guard de sesión del commit 54d4514 sigue intacto).

---

## [8ada1df] — 2026-08-07 — feat: agrega contexto de curso/docente al reporte y export Excel en 06_reportes

### Archivos modificados
- app/06_reportes/reportes_mdl.php, app/06_reportes/reportes_view.php, app/06_reportes/reportes_ctrl.js

### Cambios
- Resuelve el pendiente conocido: el export a Excel no incluía datos de curso/docente, a diferencia del PDF equivalente (pdf_grupo.php).
- reporte_grupo ahora devuelve también un bloque de contexto (Módulo, Grupo, Docente, Programa, Período, Jornada), vía el mismo patrón de JOINs ya usado en pdf_grupo.php.
- Nuevo bloque visual de contexto en pantalla, encima de la tabla de notas.
- El botón Excel de DataTables Buttons incluye ese mismo contexto como fila de encabezado en el archivo .xlsx exportado.

### Decisiones
- No fue necesario actualizar el botón Excel "en caliente" — la tabla ya se destruye y reinicializa completa en cada carga de grupo, así que el contexto se pasa directo en la config del botón en el momento de la reinicialización.

---

## [f704901] — 2026-08-07 — feat: muestra origen (Manual/Web) en listado de Aspirantes

### Archivos modificados
- app/02_estudiantes/est_mdl.php, app/02_estudiantes/est_view.php, app/02_estudiantes/est_ctrl.js

### Cambios
- Fase 4 del formulario público de inscripción: nueva columna "Origen" en el listado de Aspirantes, con badge de Bootstrap (Manual/Web), mismo patrón visual ya usado para matr_estado.
- listar_aspirantes ahora incluye estu_origen en el SELECT.

### Decisiones
- Se reutilizó la columna estu_origen ENUM('manual','web') ya existente desde la migración de esquema previa (commit 7702fe2) — no requirió cambios de base de datos.

---

## [35831fe] — 2026-08-07 — feat: CRUD de períodos académicos como pestaña en 04_grupos

### Archivos modificados
- app/04_grupos/grupos_mdl.php, app/04_grupos/grupos_view.php, app/04_grupos/grupos_ctrl.js

### Cambios
- Nueva pestaña "Períodos" en 04_grupos, junto a Cohortes y Grupos Semestre, con crear/editar (sin eliminar).
- listar_periodos ampliado (ahora trae peri_anio, peri_semestre, fechainicio, fechafin) y con 'data' => [] en sus respuestas de error.
- Nuevos case guardar_periodo (upsert con validación de formato AAAA-N, semestre 1/2, y duplicado de código) y obtener_periodo.
- Autogeneración editable del código (AAAA-N) a partir de Año+Semestre en el formulario.

### Decisiones
- No se ubicó dentro de 08_admin: ese módulo está restringido a role_id=1 únicamente, mientras que períodos debe ser accesible para Administrador y Coordinador (rol [1,2]), igual que el resto de 04_grupos.
- Sin función de eliminar, igual que admin_mdl.php: periodos tiene FK con ON DELETE RESTRICT desde matriculas y gruposemestres — no admite borrado libre.
- Resuelve el hallazgo de "gestión de períodos sin CRUD" registrado en CLAUDE.md el 2026-08-07.

---

## [54d4514] — 2026-08-07 — fix: valida sesión y rol en doc_mdl, grupos_mdl, coordinador_mdl y admin_mdl

### Archivos modificados
- app/03_docentes/doc_mdl.php (3 case)
- app/04_grupos/grupos_mdl.php (17 case)
- app/07_coordinador/coordinador_mdl.php (1 case, refactor)
- app/08_admin/admin_mdl.php (5 case)

### Vulnerabilidad corregida
- Mismo hallazgo que est_mdl.php (920bcfe): estos 4 archivos carecían de cualquier verificación de sesión o rol. admin_mdl.php era el más crítico — permitía crear usuarios con cualquier role_id (incluido Administrador) y resetear contraseñas de cualquier usuario sin autenticarse, es decir, escalación de privilegios sin sesión.

### Decisiones
- doc_mdl.php y grupos_mdl.php: mismo patrón que est_mdl.php — guard de sesión + rol in_array($role_id, [1, 2], true). listar_cohortes y listar_grupos incluyen 'data' => [] por dataSrc de DataTables.
- coordinador_mdl.php: resumen_dashboard ya tenía protección parcial (un solo mensaje para sesión inválida y rol no autorizado, dentro del try); se separó en el patrón estándar de dos mensajes distintos, guard movido fuera del try.
- admin_mdl.php: guard restringido a role_id === 1 únicamente (no [1,2]) — admin_view.php ya excluye a Coordinador de este módulo, que maneja creación de usuarios, asignación de roles y reseteo de contraseñas. case listar incluye 'data' => [].

---

## [29b6ca6] — 2026-08-07 — refactor: ajustes de formularios en 02_estudiantes

### Archivos modificados
- app/02_estudiantes/est_view.php, app/02_estudiantes/est_ctrl.js

### Cambios
- Completar Matrícula: eliminados los campos Folio y Número de la UI. Cohorte ahora ocupa el ancho completo de la fila.
- Nuevo Aspirante: "Expedido en" reubicado junto a Tipo de documento y Número de documento (mismo renglón). "Tipo de documento" renombrado a "Tipo/Documento" para ahorrar espacio.

### Decisiones
- matr_folio/matr_numero se mantienen en est_mdl.php y en la tabla `matriculas` como campos opcionales, sin usarse desde la UI — pertenecen a una etapa de certificación fuera del alcance de este proyecto, y así quedan listos para reactivarse sin cambios de backend/BD si se retoman en el futuro.
- El reordenamiento del HTML no requirió tocar el array CAMPOS_ESTUDIANTE en est_ctrl.js, ya que la validación usa selectores por ID, no posición en el DOM.

---

## [920bcfe] — 2026-08-07 — fix: valida sesión y rol en los 9 case de est_mdl.php (02_estudiantes)

### Archivos modificados
- app/02_estudiantes/est_mdl.php — guard de sesión (`$_SESSION['usua_id']`) + guard de rol (`in_array($role_id, [1, 2], true)`) agregado al inicio de los 9 case existentes: listar_aspirantes, listar_matriculados, listar_programas, listar_periodos, listar_cohortes, guardar, obtener, matricular, obtener_ficha, guardar_ficha, subir_foto

### Vulnerabilidad corregida
- Ninguno de los 9 case tenía validación de sesión ni de rol. Cualquiera con la URL podía leer o modificar datos de estudiantes —incluida la creación de credenciales de acceso vía `matricular`— sin sesión activa.

### Decisiones
- Mismo patrón ya usado en `calificaciones_mdl.php` (commits `75504eb`/`04ec8b0`): guard de sesión + guard de rol al inicio de cada case. Sin rama de ownership — `02_estudiantes` es exclusivo de Administrador/Coordinador (role_id 1 y 2), a diferencia de `05_calificaciones` donde el docente está restringido a sus propios grupos.
- `listar_aspirantes` y `listar_matriculados` incluyen `'data' => []` en las respuestas de error para no romper `dataSrc: 'data'` de DataTables en `est_ctrl.js`.

---

## [c60cb1b] — 2026-08-01 — feat: Paso 2 del formulario público de inscripción (Fase 3-C)

### Archivos modificados
- app/09_inscripcion_publica/fam_view.php — archivo nuevo: vista pública sin sesión, acceso por código temporal (vía ?codigo= en URL con autobúsqueda, o entrada manual), formulario de Programa/Jornada + Padre/Madre/Acudiente/Estudios anteriores replicando la estructura del modal interno Ficha Familiar
- app/09_inscripcion_publica/fam_mdl.php — archivo nuevo: consultar_por_codigo (busca por finc_codigotemporal, devuelve datos ya guardados para permitir edición sin bloquear reenvío), listar_programas (endpoint propio de solo lectura, aislado de est_mdl.php), completar_ficha (UPDATE de las 35 columnas familiares localizando la fila por código — nunca por estu_id del cliente —, fechainscripcion fijada en servidor con CURDATE())
- app/09_inscripcion_publica/fam_ctrl.js — archivo nuevo: autobúsqueda si el código llega por URL, precarga del select de programas antes de asignar el valor guardado, misma lógica de visibilidad Padre/Madre/Acudiente que est_ctrl.js

### Decisiones
- Sin reCAPTCHA en este paso — el código de 8 caracteres (~6.5x10^11 combinaciones) ya ofrece protección práctica suficiente sin la fricción de un segundo captcha
- El código temporal es el único credencial aceptado por completar_ficha — estu_id nunca viaja del cliente al servidor en este flujo
- Reingreso permitido sin bloqueo: si el aspirante vuelve con el mismo código, ve sus datos ya guardados y puede editarlos, en vez de un error de "ya completado"
- Solo UPDATE, nunca INSERT — la fila en fichas_inscripcion ya existe desde el Paso 1 (Fase 3-B)

### Pruebas realizadas
- Autobúsqueda por URL con código real generado en el Paso 1 → saludo con nombre correcto ✅
- Visibilidad condicional Padre/Madre/Acudiente idéntica al modal interno (autocompletado readonly al elegir Padre/Madre como acudiente) ✅
- Guardado completo → pantalla de confirmación ✅
- Verificado en phpMyAdmin: 35 campos familiares + fechainscripcion con fecha del día ✅
- Reingreso con mismo código → datos previamente guardados precargados correctamente ✅
- Código inválido → mensaje "Código no válido, verifica que esté bien escrito" ✅

---

## [b34143d] — 2026-08-01 — feat: Paso 1 del formulario público de inscripción (Fase 3-B)

### Archivos modificados
- app/09_inscripcion_publica/insc_view.php — archivo nuevo: vista pública sin sesión (sin check_session.php ni navbar.php), <head> manual con Bootstrap 5.3.3 + estilos.css + script de reCAPTCHA v2, replica los 18 campos del modal "Nuevo Aspirante" (mismos ids, opciones, labels) sin el bloque de foto, widget reCAPTCHA antes del botón "Enviar Inscripción", bloque de confirmación con el código temporal y link a fam_view.php (Fase 3-C)
- app/09_inscripcion_publica/insc_mdl.php — archivo nuevo: case `crear_aspirante` sin check_session.php (intencional). Valida 18 campos → verifica reCAPTCHA vía curl contra la API de Google antes de tocar la BD → verifica documento duplicado (mismo mensaje que est_mdl.php) → transacción: INSERT en estudiantes (estu_origen='web') + genera finc_codigotemporal de 8 caracteres (charset sin ambigüedades, reintento hasta 5 veces ante colisión) + INSERT en fichas_inscripcion
- app/09_inscripcion_publica/insc_ctrl.js — archivo nuevo: validación visual (mismo patrón que est_ctrl.js), validación de grecaptcha.getResponse() antes de enviar, envío AJAX, manejo de confirmación/error
- CLAUDE.md — registrado el hallazgo de curl_close() como deuda técnica resuelta

### Decisiones
- Formulario público en dos pasos separados (decisión explícita del usuario): Paso 1 (este commit) solo datos personales; Paso 2 (Fase 3-C, pendiente) datos familiares vía código temporal
- Sin subida de foto en el formulario público (decisión explícita) — el coordinador la agrega al revisar el registro
- insc_mdl.php no reutiliza nada de est_mdl.php — modelo propio y aislado para no arrastrar por accidente ninguna dependencia de sesión
- Código temporal de 8 caracteres con charset 'ABCDEFGHJKMNPQRSTUVWXYZ23456789' (sin 0/O/I/1/L, para evitar confusión visual al transcribirlo)
- Verificación de reCAPTCHA ANTES de cualquier escritura en BD

### Bug corregido
- curl_close() deprecada desde PHP 8.0, sin efecto, genera warning explícito en PHP 8.5 — el warning se imprimía antes del json_encode() y rompía dataType:'json' en $.ajax (mismo patrón que imagedestroy(), ya documentado). Eliminada de verificarRecaptcha().

### Pruebas realizadas
- Formulario completo enviado con reCAPTCHA resuelto → pantalla de confirmación con código de 8 caracteres, sin errores ✅
- estu_origen='web' confirmado en phpMyAdmin ✅
- Fila correspondiente en fichas_inscripcion con finc_codigotemporal generado, resto de campos familiares en NULL ✅
- Reenvío con mismo número de documento → rechazado con "El número de documento ya está registrado" ✅
- Link "Continuar con datos familiares" → 404 esperado (fam_view.php es Fase 3-C, aún no existe) ✅

---

## [7702fe2] — 2026-07-27 — feat: agrega estu_origen a estudiantes (Fase 2 formulario público de inscripción)

### Archivos modificados
- database/emdb_academica.sql — nueva columna `estu_origen ENUM('manual','web') NOT NULL DEFAULT 'manual'` en la tabla `estudiantes`, agregada entre `estu_foto` y `fechacreacion`
- app/02_estudiantes/est_mdl.php — case `guardar`, rama INSERT: agregado `estu_origen` a la lista de columnas con el literal `'manual'` como valor fijo (no viene de `$_POST`, ya que este formulario es el flujo interno de coordinador/admin). Rama UPDATE no modificada.

### Decisiones
- `estu_origen` distingue si un aspirante fue registrado por el coordinador desde dentro del sistema ('manual') o por sí mismo desde el futuro formulario público ('web', Fase 3)
- Valor fijado únicamente en el INSERT — editar un aspirante existente nunca cambia su origen
- Migración aplicada en dos lugares: `database/emdb_academica.sql` (para que persista si se recrea el volumen db_data) y `ALTER TABLE` manual contra el contenedor Docker activo (no se recreó el volumen)
- Cierra la Fase 2 del plan de 5 fases del "Formulario público de inscripción" (ver sección nueva en CLAUDE.md/PROJECT_CONTEXT.md) — Fase 0 (reCAPTCHA) y Fase 1 (Ficha Familiar interna) ya estaban cerradas previamente; Fases 3-5 pendientes

### Pruebas realizadas
- Aspirante nuevo creado desde el modal existente → estu_origen = 'manual' confirmado en phpMyAdmin ✅
- Edición de aspirante existente → estu_origen permanece 'manual', no se modifica ✅
- Estudiante creado antes de este cambio → estu_origen = 'manual' por el DEFAULT del ALTER TABLE ✅

---

## [dc18116] — 2026-07-26 — feat: nombre del estudiante en modal Ficha y tamaño Oficio en el PDF

### Archivos modificados
- app/02_estudiantes/est_ctrl.js — encabezado de `mdl_ficha` ahora muestra el nombre completo del estudiante, pasado desde la fila de la tabla (`row.estu_nombres`/`estu_apellidos`) sin depender de una consulta adicional al servidor; botón de descarga de PDF trasladado de dentro del modal Ficha hacia la columna Acciones de las tablas Aspirantes/Matriculados, visible solo cuando `tiene_ficha=1` (ícono impresora + PDF); eliminada la limpieza redundante del nombre en `limpiarFormularioFicha()`
- app/02_estudiantes/est_view.php — marcado ajustado para el botón de descarga de PDF reubicado en la columna Acciones
- app/06_reportes/pdf_ficha.php — tamaño de página cambiado de Carta a Oficio Colombia vertical (mismo ancho 612pt, alto 935.43pt = 33cm) vía arreglo de coordenadas explícito en `setPaper()`

### Decisiones
- Nombre del estudiante se obtiene de los datos ya cargados en la fila de la tabla, evitando una consulta adicional al servidor solo para mostrar el encabezado del modal
- Botón de descarga de PDF movido del modal a la columna Acciones — visible únicamente cuando la ficha ya existe (`tiene_ficha=1`)

### Bug corregido
- `limpiarFormularioFicha()` sobrescribía el nombre recién fijado por `abrirFicha()`, porque se ejecuta después dentro del callback AJAX — se eliminó la limpieza redundante de ese campo

---

## [15021e0] — 2026-07-26 — feat: descarga en PDF de la Ficha Familiar (AC-FO-02) con foto del estudiante

### Archivos modificados
- app/06_reportes/pdf_ficha.php — archivo nuevo: replica el diseño del formato oficial AC-FO-02 (datos del estudiante, padre, madre, acudiente, estudios anteriores, líneas de firma, pie con programa/fecha/jornada)
- app/02_estudiantes/est_ctrl.js — botón Descargar PDF agregado al modal de Ficha Familiar, mismo patrón `window.open()` que ya usan `pdf_boletin.php` y `pdf_grupo.php`
- app/02_estudiantes/est_view.php — marcado del botón de descarga en el modal de Ficha Familiar

### Decisiones
- Acceso restringido a roles 1 y 2 (mismo patrón que `pdf_grupo.php`)
- Consulta con LEFT JOIN `fichas_inscripcion` para que aspirantes sin ficha diligenciada aún generen el PDF, con esas secciones en blanco

### Bug corregido
- `colspan` mixto en las tablas de Estudiante/Padre/Madre/Acudiente causaba "Frame not found in cellmap" en Dompdf — se reemplazó por celdas físicas vacías para mantener el mismo número de `<td>` por fila en cada tabla
- `display:table-cell` en un `div` anidado dentro de un `td` real también causaba el mismo error — se removió, ya que el `td` que lo envuelve ya cumple esa función
- Dompdf restringe la lectura de archivos locales al chroot por defecto (su propia carpeta `vendor/`), lo que bloqueaba silenciosamente la foto sin lanzar excepción — se configuró el chroot a la raíz del proyecto

---

## [8fe6c99] — 2026-07-26 — feat: agrega 6 campos faltantes del formato AC-FO-02 al modal Nuevo Aspirante

### Archivos modificados
- app/02_estudiantes/est_view.php — nuevos campos: Expedido en, Ciudad de nacimiento, Ocupación, Estado civil (select), Discapacidad (select con lista fija del formato oficial), Multiculturalidad (checkboxes múltiples)
- app/02_estudiantes/est_ctrl.js — recolección, precarga y validación de los 6 campos nuevos, incluyendo la exclusión mutua de Multiculturalidad
- app/02_estudiantes/est_mdl.php — case `obtener` ampliado para incluir los 6 campos nuevos (requerido para que `abrirEditar()` precargue los datos correctamente)
- database/emdb_academica.sql — `estu_multiculturalidad` ampliado de VARCHAR(60) a VARCHAR(150) para admitir varias categorías combinadas sin truncamiento

### Decisiones
- Discapacidad usa exactamente las 8 opciones y textos del formato AC-FO-02 (incluye salto de numeración 7→9, sin ítem 8, tal como está configurado en la plataforma externa que recibe estos datos)
- Multiculturalidad: exclusión mutua entre "No aplica" y las demás categorías, guardado como texto separado por comas en la misma columna

---

## [2355ab1] — 2026-07-26 — feat: indicador rojo/verde de ficha completa en tablas de estudiantes

### Archivos modificados
- app/02_estudiantes/est_mdl.php — `listar_aspirantes`/`listar_matriculados`: LEFT JOIN `fichas_inscripcion` + campo `tiene_ficha` (aprovecha el índice de la UNIQUE KEY `uq_finc_estu`, sin duplicar filas por ser relación 1:1)
- app/02_estudiantes/est_ctrl.js — botón Ficha ahora muestra un punto de color (verde = existe fila guardada, rojo = pendiente) con tooltip explicativo; ambas tablas se recargan automáticamente tras guardar una ficha, sin necesidad de refrescar la página

### Decisiones
- Definición "simple" del indicador: existencia de fila en `fichas_inscripcion`, no validación campo por campo (decisión documentada en conversación con el usuario)

---

## [1b699f5] — 2026-07-26 — feat: carga y miniatura de foto del estudiante

### Archivos modificados
- docker/Dockerfile — agrega extensión GD (`libpng-dev` `libjpeg-dev` `libfreetype6-dev` + `docker-php-ext-configure`/`install gd`)
- database/emdb_academica.sql — nueva columna `estudiantes.estu_foto VARCHAR(255) DEFAULT NULL`
- .gitignore — excluye `uploads/fotos_estudiantes/*`, con excepción de `.gitkeep` para mantener la carpeta versionada
- uploads/fotos_estudiantes/.gitkeep — archivo nuevo
- app/02_estudiantes/est_mdl.php — nuevo case `subir_foto`: valida contenido real de la imagen con `getimagesize()`, redimensiona a máx. 500x400 con GD manteniendo proporción, comprime a JPEG calidad 80, guarda como `{estu_id}.jpg`
- app/02_estudiantes/est_ctrl.js — flujo de subida de foto vía `FormData`
- app/02_estudiantes/est_view.php — miniatura como primera columna en las tablas de Aspirantes y Matriculados; campo de foto solo visible en modo Editar (no en Nuevo Aspirante, ya que depende de `estu_id`)

### Decisiones
- Primera funcionalidad de subida de archivos del proyecto: usa `FormData` + `processData`/`contentType: false`, distinto del patrón de objeto plano usado en el resto de la app
- Nota operativa: en Docker/Fedora la carpeta `uploads/fotos_estudiantes/` requiere `chmod 777` tras crearse, porque Apache corre como `www-data` (UID distinto al usuario del host que crea la carpeta vía bind mount)

### Bug corregido
- Se removieron las llamadas a `imagedestroy()` (deprecada en PHP 8.5, sin efecto desde PHP 8.0) que rompían el JSON de respuesta

---

## [67683ab] — 2026-07-26 — feat: valida formato de correo electrónico en minúsculas en modal Nuevo Aspirante

### Archivos modificados
- app/02_estudiantes/est_ctrl.js — `npt_estu_email` fuerza minúsculas en vivo (con preservación de la posición del cursor); nueva función `validarFormatoEmail()`: marca `is-invalid` si el campo está vacío o no contiene `@`, `is-valid` en caso contrario

### Decisiones
- Validación aplicada de forma consistente en tres puntos: validación en vivo, `abrirEditar()` (modo edición) y el bloqueo previo al guardado

---

## [e29b7b1] — 2026-07-26 — fix: corrige validación visual y comportamiento del select Acudiente en Ficha Familiar

### Archivos modificados
- app/02_estudiantes/est_ctrl.js — validación visual en vivo (`input`/`change`/`blur`) en los 33 campos de `mdl_ficha`, mismo patrón que `CAMPOS_ESTUDIANTE` de la Fase A; `abrirFicha()`: el marcado de campos llenos ahora corre en el callback `shown.bs.modal` de Bootstrap; `manejarCambioAcudiente()`: el campo Parentesco ahora se oculta por completo (no solo se bloquea) cuando se selecciona Padre o Madre, y reaparece vacío/editable con Otro
- app/02_estudiantes/est_view.php — marcado asociado al comportamiento condicional del select Acudiente

### Decisiones
- Parentesco se excluye de los campos obligatorios al guardar cuando su bloque está oculto (selección Padre/Madre)

### Bug corregido
- `abrirFicha()` marcaba los campos llenos inmediatamente después de `.show()`, pero Bootstrap 5 muestra el modal de forma asíncrona — el chequeo `:visible` daba falso para todo antes de este fix; el marcado se movió al callback `shown.bs.modal`
- `manejarCambioAcudiente()` solo bloqueaba el campo Parentesco en vez de ocultarlo por completo al seleccionar Padre o Madre

---

## [fe397ad] — 2026-07-26 — feat: validación obligatoria y formato de documento en modal Nuevo Aspirante

### Archivos modificados
- app/02_estudiantes/est_ctrl.js — validación visual en vivo (rojo/verde) en los 13 campos del modal, reutilizable vía `marcarValidacion()` y `CAMPOS_ESTUDIANTE` (pensado para reusarse en la Ficha Familiar, Fase B); número de documento con formato de puntos de miles en pantalla, se limpia antes de enviar y guarda solo dígitos en BD; `limpiarFormulario()` y `abrirEditar()` actualizados para limpiar/aplicar las clases de validación visual
- app/02_estudiantes/est_mdl.php — los 13 campos ahora son obligatorios (antes solo nombres/apellidos/documento), validado en el servidor como respaldo
- app/02_estudiantes/est_view.php — label "Ciudad" renombrado a "Ciudad de residencia"

### Decisiones
- Validación reutilizable vía `marcarValidacion()` + `CAMPOS_ESTUDIANTE`, diseñada para reusarse en el modal de Ficha Familiar (Fase B)
- Validación en dos capas: JS (UX) y PHP (respaldo de integridad — el servidor nunca confía en los datos del cliente)

---

## [115c8ed] — 2026-07-25 — chore: ignora recaptcha_config.php (credenciales sensibles, fuera de git)

### Archivos modificados
- .gitignore — agregada la entrada `app/00_connect/recaptcha_config.php`

### Decisiones
- Archivo excluido de git por contener credenciales sensibles (site key / secret key de reCAPTCHA)

---

## [590e64e] — 2026-07-25 — docs: registra auditoría de compatibilidad PHP 8.1-8.5 en deuda técnica

### Archivos modificados
- CLAUDE.md — sección Deuda técnica: se registra el resultado de la auditoría de compatibilidad PHP 8.1-8.5

### Decisiones
- Auditoría estática sobre los 22 archivos `.php` de `app/`: sin hallazgos de propiedades dinámicas, nullable implícito, offsets de string con llaves, ni funciones deprecadas. Código 100% procedural sin type hints, conexión BD 100% vía PDO

### Pruebas realizadas
- Auditoría estática de los 22 archivos `.php` de `app/` ✅
- Prueba en runtime de dompdf (librería de terceros) con `display_errors=1` y `error_reporting=E_ALL`: PDF generado sin ningún warning ni deprecation — compatibilidad con PHP 8.5 confirmada de punta a punta ✅

---

## [d406166] — 2026-07-25 — fix: corrige hash del usuario admin en seed SQL

### Archivos modificados
- database/emdb_academica.sql — INSERT de usuarios: hash de la contraseña admin reemplazado por el hash real y verificado de `Admin@2026` (cost=12), eliminando el parche manual (`UPDATE`) que antes era necesario tras cada import
- CLAUDE.md — documenta explícitamente la credencial del admin sembrado, ya que README.md remite ahí para consultarla

### Bug corregido
- El INSERT de usuarios en `database/emdb_academica.sql` usaba el hash de `password` pese a que el comentario indicaba `Admin@2026`

---

## [d6169e6] — 2026-07-25 — migración del entorno de desarrollo local a Docker (Fedora 44)

### Archivos modificados
- docker-compose.yml — archivo nuevo: define 3 servicios — `app` (build desde `docker/Dockerfile`, imagen base `php:8.5-apache`, puerto 8120→8080, volumen `.:/var/www/html/app_academica_emdb:z`), `db` (`mysql:8.0`, `network_mode: service:app`, puerto 3310→3306, importa `database/emdb_academica.sql` automáticamente vía `/docker-entrypoint-initdb.d`), `phpmyadmin` (puerto 8121→80)
- docker/Dockerfile — archivo nuevo: PHP 8.5 + Apache, extensiones `pdo_mysql`/`mysqli`/`zip`, Composer instalado, Apache configurado para escuchar en el puerto 8080 interno
- app/00_connect/pdo.php — modificado: host del DSN cambiado de `localhost` a `127.0.0.1`

### Decisiones
- Reemplazo del entorno local Windows/XAMPP por Fedora 44/Docker
- PHP 8.5 elegido para el contenedor `app` (en vez de 8.0) por paridad con producción — el hosting cPanel de `escuelamdb.com` ya corre PHP 8.5
- Volumen del proyecto montado en `/var/www/html/app_academica_emdb` (no directo en `/var/www/html`) para preservar las rutas absolutas hardcodeadas en `navbar.php` (`/app_academica_emdb/...`), replicando la estructura de subcarpeta que ya usaban XAMPP y producción
- `db` usa `network_mode: service:app` para compartir red con `app` — por eso `pdo.php` requiere `127.0.0.1` en vez de `localhost`: con red compartida entre contenedores, `localhost` fuerza a PDO a buscar un socket Unix inexistente; `127.0.0.1` fuerza conexión TCP
- Puertos asignados al proyecto: bloque 8120-8129, según registro centralizado de puertos en `~/proyectos/PUERTOS.md` (fuera del repo)
- URL de acceso local actualizada a `http://localhost:8120/app_academica_emdb/`
- Corregida una inconsistencia detectada en las credenciales admin sembradas en `database/emdb_academica.sql`: el hash correspondía realmente a la contraseña `password`, pese a que el comentario del script indicaba `Admin@2026` — credencial admin actualizada a `admin@emdb.edu.co` / `Admin@2026`
- Alias bash creados en Fedora: `academica` (cd al proyecto), `c` (clear)

### Pruebas realizadas
- `docker-compose up` levanta los 3 servicios correctamente ✅
- Aplicación accesible en `http://localhost:8120/app_academica_emdb/` ✅
- phpMyAdmin accesible en el puerto 8121, conexión a la BD `emdb_academica` verificada ✅
- Conexión PDO desde `app` hacia `db` funcional tras el cambio de `localhost` a `127.0.0.1` en el DSN ✅
- Login con credencial admin corregida (`admin@emdb.edu.co` / `Admin@2026`) verificado ✅

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
