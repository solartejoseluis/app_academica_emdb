# PROJECT_CONTEXT.md — app_academica_emdb
> Archivo de contexto para Claude IA. Pegar al inicio de cada nuevo chat.
> Última actualización: 2026-08-29
> Versión: 68 — Corrección de truncamiento SQL en códigos de grupo/cohorte (04_grupos, commit `0e0a508`): grse_codigo y coho_codigo ampliados de VARCHAR(20) a VARCHAR(25) para alinear el esquema con la convención ya documentada en CLAUDE.md; bug determinístico afectaba a cualquier grupo semestre creado para programas de sigla larga (ASO2016, AMD2016, 7 caracteres) porque la fórmula de autogeneración consumía 13-14 caracteres fijos, dejando solo 6 de margen bajo la columna original; validación defensiva mb_strlen() > 25 agregada en guardar_grupo y guardar_cohorte antes de llegar a MySQL; reproducido y verificado vía curl + navegador + Cableado completo de doce_cedula en "Nuevo/Editar Docente" (03_docentes, commit `7ba02bc`): columna `docentes.doce_cedula` (existía sin uso desde el inicio del proyecto) ampliada de VARCHAR(15) a VARCHAR(20) y cableada en las 3 capas — nuevo campo opcional en el modal, sin selector de tipo de documento (a diferencia de estu_numerodoc), validación de unicidad server-side condicionada a valor no vacío (mismo criterio que matr_numero), guardado como NULL si llega vacío; case 'listar' sin cambios por alcance; pendiente aplicar el ALTER TABLE en producción; verificado 6/6 pruebas en navegador + Edición de correo electrónico en "Editar Docente" (03_docentes, commit `b6cc503`): campo `#npt_usua_email` deja de estar bloqueado en modo edición (antes forzado a readonly en `abrirEditar()`), label actualizado a "Correo electrónico (usuario de acceso)" para hacer explícito que es también el identificador de login; validación de unicidad server-side agregada en la rama de edición de `doc_mdl.php` (mismo patrón que `doce_sigla`, excluye el propio `usua_id`); `usua_email` agregado a ambos UPDATE de `usuarios` en esa rama (con y sin cambio de contraseña simultáneo); verificado 6/6 pruebas en navegador + Mejora de usabilidad en la Ficha de Inscripción, AC-FO-02 (commit `3672a0e`): cambio puramente de presentación (sin tocar modelo ni controladores JS, ningún `id`/`name` de input modificado) en la Sección "1. Datos del Aspirante/Estudiante" de `insc_view.php` y `est_view.php` (`#mdl_estudiante`) — campos de residencia (Dirección/Barrio/Ciudad) reordenados y renombrados con sufijo "(Residencia)", "Ciudad de nacimiento" reubicada junto a "Fecha de nacimiento", EPS/Ocupación/Estado civil consolidados en una sola fila de 3 columnas con Discapacidad aislada en su propia fila; `insc_view.php` alineado a la misma disposición que ya tenía `est_view.php` (traslado de "Expedido en" a la fila del documento, unificación de label "Tipo/Documento" → "Tipo de documento"); Sección "3. Información sobre Familiares" (Padre/Madre/Acudiente, ambos archivos): Dirección/Barrio/Ciudad reordenados con el mismo sufijo "(Residencia)", Teléfono renombrado a "Teléfono (Personal)"; motivación: hacer explícito para quien diligencia el formulario a qué corresponde cada dato (ej. distinguir "Teléfono (Personal)" de un eventual teléfono de otro tipo, o "Dirección (Residencia)" de otra dirección que pudiera tener un familiar); encabezados "1. Datos del Aspirante" (insc_view.php) y "1. Datos del Estudiante" (est_view.php) mantenidos intencionalmente distintos, decisión explícita del autor; mismo commit confirma la eliminación, ya decidida previamente y sin relación con este cambio, de `actualizar_ayudas.sql` + Registro y visualización de "último acceso" del usuario al sistema (commit `ac96f07`): nueva columna `usuarios.usua_ultimo_acceso` (`DATETIME NULL DEFAULT NULL`) en el esquema; nuevo helper compartido `formatearUltimoAcceso($ultimoAcceso, $fechaCreacion)` en `00_files/helpers.php` (primer archivo de funciones comunes del proyecto), formato `d/m/Y H:i` con fallback a `fechacreacion` si `usua_ultimo_acceso` es `NULL`, `'—'` si ambos lo son; `login_mdl.php` guarda en sesión el valor ANTERIOR de `usua_ultimo_acceso` (`usua_ultimo_acceso_anterior` + `usua_fechacreacion`) antes de ejecutar `UPDATE usuarios SET usua_ultimo_acceso = NOW()`, en el único flujo de autenticación de los 4 roles, antes de la lógica condicional de `doce_id`/`estu_id`; bloque "Nombre — Email — Último acceso" agregado en `navbar.php` (roles 1/2), `calificaciones_view.php` (nav real de Docente — no `doc_view.php`, que resultó inalcanzable para ese rol y se revirtió en el mismo ciclo) y `reportes_view.php` (Estudiante); columna "Último acceso" nueva en las tablas de Usuarios (`08_admin`, formateada server-side en `admin_mdl.php`), Matriculados (`02_estudiantes`, requirió `LEFT JOIN usuarios` nuevo en `listar_matriculados` por `usua_id` opcional) y Docentes (`03_docentes`, reutiliza el `INNER JOIN usuarios` ya existente); hallazgo colateral documentado como deuda técnica, no corregido: `est_view.php` y `grupos_view.php` tienen el mismo `<nav>` fallback inalcanzable para Docente que tenía `doc_view.php`, ver "Análisis pendiente" + Habilitación de cambio de contraseña de docente en "Editar Docente" (03_docentes, commit `35c312a`): campo de contraseña de `#mdl_docente` deja de ocultarse en modo edición, replicando el patrón ya usado en `08_admin` (`admin_mdl.php` case `editar` — vacío no cambia el hash, `password_hash()` bcrypt si viene texto); label cambiado a "Contraseña", placeholder dinámico ("Contraseña inicial" en creación, "Dejar vacío para no cambiar" en edición); visibilidad condicionada a que el docente tenga `usua_id` (`abrirEditar()` en `doc_ctrl.js`); `case 'obtener'` de `doc_mdl.php` expone `usua_id` en el SELECT para esa decisión; `case 'guardar'` (rama edición) combina `usua_activo` + `usua_passwordhash` en un solo UPDATE cuando llega contraseña no vacía; sin validación de longitud mínima (mismo criterio que 08_admin); deuda identificada y no resuelta en este commit: `case 'obtener'` sigue con INNER JOIN docentes↔usuarios, por lo que un docente con `usua_id NULL` causaría "Docente no encontrado" en vez de abrir el modal sin el campo — hoy 0 docentes en esa condición, documentado en "Análisis pendiente" + Unificación del formulario público de inscripción en un solo paso (commit `0e098bb`): el flujo de 2 pasos con guardado parcial (insc_view.php/Paso 1 + fam_view.php/Paso 2, puenteados por finc_codigotemporal para "retomar después") se reemplaza por un solo formulario de una sola pantalla con un solo guardado transaccional; insc_view.php absorbe el HTML de fam_view.php en 5 secciones numeradas con el mismo diseño visual .seccion-titulo/.campo-lleno del modal interno de 02_estudiantes (commit f088466); insc_ctrl.js absorbe toda la lógica de fam_ctrl.js (cascada Padre/Madre/Acudiente, catálogo de Programas) en un solo submit AJAX; insc_mdl.php fusiona crear_aspirante + completar_ficha en un solo case con una sola transacción PDO (INSERT estudiantes + INSERT completo de fichas_inscripcion, ya no hay UPDATE posterior) — mismo principio ya documentado para guardar_completo ("dos transacciones individualmente correctas no garantizan atomicidad entre sí"); fam_view.php/fam_mdl.php/fam_ctrl.js eliminados por completo; columna finc_codigotemporal y su UNIQUE KEY eliminados del esquema (ALTER TABLE ya ejecutado manualmente en Docker el 2026-08-24, confirmado por DESCRIBE); el formulario público sigue sin generar clave de acceso al sistema (confirmado por diagnóstico que esa tarea nunca vivió ahí — es y sigue siendo exclusiva del coordinador vía matricular en est_mdl.php); calcularEstadoFicha() no se modificó; verificado en navegador 15/15 pruebas + Edición manual de matr_numero en el modal "Editar Matrícula" de 02_estudiantes (commit `59775e4`): campo antes de solo lectura (`#spn_editar_matr_numero`) se vuelve editable (`#npt_editar_matr_numero`, `type="number"`), con validación de unicidad server-side en el case `editar_matricula` de `est_mdl.php` (mismo patrón que `doce_sigla` en `doc_mdl.php` — SELECT previo excluyendo el propio registro, mensaje específico en vez de catch genérico); `pdf_hoja_matricula.php` mejora el manejo de errores de la asignación automática distinguiendo colisión de unicidad (SQLSTATE 23000) de errores genéricos; el mecanismo de asignación automática (SELECT ... FOR UPDATE sobre configuracion + MAX(matriculas.matr_numero) + GREATEST) no se modificó — confirmado por diagnóstico que relee MAX en vivo en cada asignación, sin contador cacheado que desincronizar con la edición manual; no se tocó el modal "Completar Matrícula" + Reestructuración del modal de creación de estudiante en 02_estudiantes (commit `f088466`), fuera del alcance original de las Fases A–F pero sobre el mismo modal unificado `#mdl_estudiante`: título de creación renombrado de "Nuevo Aspirante" a "Ficha de Inscripción" (edición conserva "Editar Estudiante"), nuevo bloque de encabezado "FICHA DE INSCRIPCIÓN — código AC-FO-02" con instrucciones y advertencia de pérdida de datos al cerrar sin guardar (reemplaza las instrucciones antiguas de "Ficha Familiar (AC-FO-02)"), 5 secciones del modal numeradas con nueva clase `.seccion-titulo` (00_files/estilos.css) — sección 3 renombrada de "Ficha Familiar (AC-FO-02)" a "Información sobre Familiares", sección 5 renombrada de "Información general" a "Programa técnico al que ingresa" y reubicada al final del modal (antes intercalada justo antes de Padre/Madre/Acudiente) —, nueva clase `.campo-lleno` (fondo verde) aplicada dinámicamente vía nueva función `marcarCampoLleno()` con listener delegado sobre los inputs/selects/textareas de `#mdl_estudiante`, capa visual independiente de `marcarValidacion()`/`is-invalid`/`is-valid` sin modificarla; se evaluó agregar `#bloque_foto_estudiante` también al modo creación y se descartó porque la subida de foto requiere un `estu_id` ya existente (el endpoint `subir_foto` no puede ejecutarse antes del primer guardado), la foto permanece exclusiva del modo edición sin cambios; verificado en navegador 8/8 pruebas + Limpieza de código muerto tras el plan de unificación de ficha de estudiante (commit 5de9a9e): elimina los 4 cases sin llamadores (guardar/obtener/obtener_ficha/guardar_ficha) de est_mdl.php y abrirFicha()/handler btn_guardar_ficha de est_ctrl.js, documentados como pendientes de limpieza desde los commits 42e4175/76f90c5; limpiarFormularioFicha() conservada por tener llamador activo real; cierra la deuda técnica de las Fases C1/C2 + Fase F2 del plan de unificación de ficha de estudiante, cierra el plan completo A–F: nuevo pdf_hoja_matricula.php (06_reportes, AC-FO-09) con dompdf Carta portrait, excluye Nivel y checklist de Requisitos, asignación segura de matr_numero bajo concurrencia (SELECT ... FOR UPDATE sobre configuracion, primer uso de este patrón en el proyecto), matriculas resuelto como 1:N por estudiante vía ORDER BY matr_id DESC LIMIT 1 (aplicado también en obtener_completo de est_mdl.php), validación server-side de matr_estado === 'matriculado', botón "🖨️ Hoja de Matrícula" en el modal unificado de 02_estudiantes visible solo si matriculado + Fase F1 del plan de unificación de ficha de estudiante: columna usua_nombre nueva en usuarios (prerequisito de la Fase F2, PDF Hoja de Matrícula) — descubierta como faltante durante el diagnóstico de Fase F (ningún Admin/Coordinador tenía nombre legible, solo usua_email), CRUD completo en 08_admin con validación server-side simétrica entre crear/editar, propagada a $_SESSION['usua_nombre'] en login; fila F del roadmap dividida en F1 (este commit) y F2 (PDF, pendiente), mismo criterio ya usado para dividir C en C1/C2 + Fase E del plan de unificación de ficha de estudiante: reactivación de matr_folio/matr_observacion (removidos deliberadamente de la UI en 29b6ca6, backend intacto desde entonces) en los modales Completar Matrícula y Editar Matrícula de 02_estudiantes, fechamatricula deja de fijarse con CURDATE() hardcodeado y se vuelve editable, matr_numero cambia de VARCHAR(20) a INT UNSIGNED con UNIQUE KEY nueva (múltiples NULL confirmados válidos en MySQL/InnoDB, conversión verificada sin pérdida de datos), matr_numero queda de solo lectura en ambos modales sin lógica de asignación automática (reservada para Fase F) + Fase D del plan de unificación de ficha de estudiante: configuración institucional en 08_admin — tabla `configuracion` nueva de fila única (config_id fijo en 1, CHECK constraint chk_configuracion_fila_unica), columnas tipadas matr_numero_inicial/director_nombre/secretario_nombre (primer caso del proyecto de un registro de configuración fijo, sin PK variable), 2 cases nuevos en admin_mdl.php con guard admin-only replicado del resto del módulo, bloque nuevo sin tabs en admin_view.php con texto de ayuda sobre aplicación hacia adelante, precarga/guardado en admin_ctrl.js, fix incidental de estilos.css faltante en admin_view.php + Fase C2 (frontend) del plan de unificación de ficha de estudiante, cierra la Fase C completa: modal mdl_estudiante ampliado a modal-xl y absorbe todo el contenido de mdl_ficha (eliminado por completo), abrirEditar() precarga estudiante + Ficha Familiar en un solo flujo vía obtener_completo (incluye estado_ficha, visibilidad Padre/Madre, cascada Acudiente), guardado unificado en btn_guardar_estudiante hacia guardar_completo con payload de objeto plano (no FormData, por convención de CLAUDE.md), nuevo botón "🖨️ Ficha Inscripción" condicional a estado_ficha === 'completa', botones de fila "📋 Ficha"/"🖨️ PDF" eliminados de tablaAspirantes/tablaMatriculados, cases/handlers antiguos (guardar/guardar_ficha/obtener/obtener_ficha/abrirFicha/btn_guardar_ficha) quedan como código muerto sin llamadores + Fase C1 (backend) del plan de unificación de ficha de estudiante: nuevo case guardar_completo en est_mdl.php (02_estudiantes) unifica creación/edición de estudiante + Ficha Familiar en una sola transacción PDO (antes 2 transacciones separadas en 2 requests, sin atomicidad real), Ficha Familiar ahora obligatoria siempre sin excepción por modo, nuevo case obtener_completo combina estudiante + ficha en un solo JSON vía LEFT JOIN + Fase B del plan de unificación de ficha de estudiante: columna "Edad" en tbl_matriculados de 02_estudiantes, formato "17 (23 sep)" (edad calculada en JS ajustada por mes/día + cumpleaños abreviado con array local de meses en español, sin intl ni librería de fechas), resaltado celeste #cfe2ff embebido en render() para menores de 18, fechanacimiento agregado al SELECT de listar_matriculados, manejo defensivo de NULL con "—", tablaAspirantes sin cambios + Fase A del plan de unificación de ficha de estudiante: botón "Editar" renombrado a "📝 Datos Estudiante" en tablaMatriculados y tablaAspirantes de 02_estudiantes, adopta el punto de color e indicador de completitud (estado_ficha) que antes solo tenía el botón "Ficha" (botón "Ficha" se mantiene intacto hasta la Fase C) + precarga de Programa y Jornada declarados (fichas_inscripcion) en el modal Completar Matrícula de 02_estudiantes (Programa preseleccionado pero editable, Jornada solo informativa, sin persistirse en matriculas) + eliminación de Períodos en 04_grupos (DELETE condicionado, sin alternativa "desactivar" por la semántica de único-activo de peri_activo, con salvaguarda extra que nunca permite eliminar el período activo) + edición de matrícula (Editar Programa/Cohorte/Período con retiro automático de módulos huérfanos) + fix de causa raíz en Asignación Estudiantes (listar_estudiantes_disponibles ahora valida matrícula real, no solo coho_id) + filtros Programa/Período/Grupo/Módulo + columnas Cohorte/Módulos (con modal de detalle) en Matriculados (02_estudiantes) + columna de correo en Matriculados + cascada Programa→Cohorte→Período (cohorte ahora obligatoria) en modal Completar Matrícula (02_estudiantes) + CRUD de Programas en 04_grupos + reordenamiento de pestañas (Programas, Módulos, Períodos, Cohortes, Grupos Semestre, Asignación Estudiantes) + Ficha Familiar obligatoria + indicador de 3 estados (Fase 5 formulario público) + zona horaria America/Bogota en Docker + select de Reportes con nombre de módulo e inicial de docente + eliminar aspirante desde 02_estudiantes + CRUD completo de módulos en 04_grupos + fix de sesión faltante en listar_grupos (05_calificaciones) + jornada movida de cohorte a grupo semestre (grse_jornada) + fix de precarga/edición/eliminación en modal de módulo-grupo (04_grupos) + eliminar/desactivar cohorte con verificación de dependencias + fila de contexto del Excel exportado dividida en 2 filas + período activo (peri_activo) y conteo de grupos por docente + eliminar/desactivar docente y corrección de doce_activo desincronizado + texto de ayuda para siglas y corrección de maxlength/mayúsculas + filtros de profesor/programa/período en calificaciones + sidebar de ayuda con CRUD (10_ayudas) + sidebar de ayuda expandido a todos los módulos + fix refresco matriculados + cambio de clave para estudiantes matriculados + fix del select de sección en el modal de ayuda + guard de autorización por rol en listar_grupos (05_calificaciones) + período activo preseleccionado y sincronizado en modal Grupo Semestre + grse_codigo y peri_codigo autogenerados readonly permanente + modal Grupo Semestre ampliado a modal-xl + validación de unicidad de doce_sigla + eliminar_cohorte envuelto en transacción PDO + conteo de carga de docente en selector de asignación de módulo + desempate alfabético en listar_calificaciones + autenticación de estudiantes migrada a correo real (estu_email UNIQUE) + fix del selector de módulos del estudiante (DISTINCT + ONLY_FULL_GROUP_BY) + sincronización de contenido de 10_ayudas con el estado real del sistema tras diagnóstico (5 entradas de 02_estudiantes desactualizadas desde el 16-18 de agosto, corregidas contra los cambios de las Fases A-F del plan de unificación de ficha de estudiante; nueva entrada de Configuración Institucional en 08_admin; corrección de dos pares de ayud_orden duplicados en 04_grupos)

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

## Tablas de la BD (15 tablas — todas creadas)

```
1.  roles              — role_id, role_nombre
2.  programas          — prog_id, prog_nombre, prog_sigla, prog_resolucion, prog_fechaaprobacion, prog_fechavencimiento, prog_duracion_semestres, prog_descripcion, prog_activo
3.  periodos           — peri_id, peri_codigo, peri_anio, peri_semestre
4.  usuarios           — usua_id, role_id(FK), usua_email, usua_passwordhash, usua_activo
5.  cohortes           — coho_id, prog_id(FK), coho_codigo, fechainicio
6.  docentes           — doce_id, usua_id(FK), doce_nombres, doce_apellidos, doce_sigla
7.  estudiantes        — estu_id, usua_id(FK), coho_id(FK), estu_nombres, estu_apellidos, estu_foto, estu_expedidoen, estu_ciudadnac, estu_ocupacion, estu_estadocivil, estu_discapacidad, estu_multiculturalidad
8.  modulos            — modu_id, prog_id(FK), modu_nombre, modu_sigla, modu_orden
9.  matriculas         — matr_id, estu_id(FK), prog_id(FK), peri_id(FK), matr_estado
10. gruposemestres     — grse_id, prog_id(FK), peri_id(FK), grse_codigo, grse_semestre
11. grmoestudiantes    — grmo_id(FK), estu_id(FK)  [tabla puente N:M]
12. gruposmodulos      — grmo_id, grse_id(FK), modu_id(FK), doce_id(FK)
13. calificaciones     — cali_id, grmo_id(FK), estu_id(FK), N1-N4, sup_N1/N2/N4, cali_nota_final, cali_habilitacion, cali_definitiva
14. horariosgrupo      — hora_id, grse_id(FK), hora_diasemana, hora_horainicio
15. fichas_inscripcion — finc_id, estu_id(FK), datos familiares (padre/madre/acudiente), estudios anteriores, código temporal
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
