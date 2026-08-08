# CHANGELOG — app_academica_emdb
> Historial de cambios del Sistema de Gestión Académica EMDB
> Formato: [Semver] — Fecha | Orden: más reciente arriba

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
