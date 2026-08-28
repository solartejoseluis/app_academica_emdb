# CHANGELOG — app_academica_emdb
> Historial de cambios del Sistema de Gestión Académica EMDB
> Formato: [Semver] — Fecha | Orden: más reciente arriba

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
