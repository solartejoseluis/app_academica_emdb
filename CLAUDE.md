# CLAUDE.md — app_academica_emdb
> Última actualización: 2026-08-29 — último commit citado: 0e0a508

## Reglas de documentación

- El detalle de cada ítem completado (commit hash, archivos modificados, qué cambió) va en **CHANGELOG.md**.
- **CLAUDE.md** solo recibe cambios operativos: estado del roadmap, actualizaciones de antipatterns o secciones de arquitectura.
- **Nunca borrar contenido de CLAUDE.md** — solo agregar o actualizar.
- Nunca combinar análisis y modificaciones en un solo prompt. Primero análisis, luego (tras confirmación) modificación.
- Cuando una decisión arquitectónica se ejecuta y queda resuelta, su registro pasa a CHANGELOG.md. La entrada en CLAUDE.md se elimina porque ya no cambia el comportamiento del código.
- **Regla de cierre de tarea:** Una tarea no se considera completa hasta que los 4 documentos del proyecto estén actualizados y commiteados en un commit separado del código: CHANGELOG.md (historial de cambios), CLAUDE.md (estado del roadmap y decisiones), PROJECT_CONTEXT.md (estado de avance y commits) y README.md (estado general del proyecto). Claude IA es responsable de identificar qué documentos requieren actualización y generar los prompts correspondientes antes de dar la tarea por cerrada.

---

## Mapa de secciones

| Contenido nuevo | Sección donde va |
|---|---|
| Nueva convención de BD o HTML/JS | → Convenciones |
| Error cometido que no debe repetirse | → Antipatrones |
| Decisión de diseño con justificación | → Decisiones arquitectónicas |
| Módulo nuevo completado | → Estado del roadmap |
| Cambio en flujo de deploy | → Checklist de deploy |
| Variable de sesión nueva | → Variables de sesión |

---

## Entorno de desarrollo

- **Local:** Docker (Fedora 44) — `docker-compose.yml` en la raíz del proyecto, 3 servicios:
  - `app`: build desde `docker/Dockerfile` (imagen base `php:8.5-apache`), extensiones `pdo_mysql`/`mysqli`/`zip`/`gd`, Composer instalado, Apache escucha en el puerto 8080 interno. Puerto publicado: `8120→8080`
  - `db`: `mysql:8.0`, `network_mode: service:app` (comparte red con `app`), puerto publicado `3310→3306`, importa `database/emdb_academica.sql` automáticamente vía `/docker-entrypoint-initdb.d`
  - `phpmyadmin`: puerto publicado `8121→80`
- **PHP 8.5** en el contenedor `app` (no 8.0) — paridad con producción: el hosting cPanel de `escuelamdb.com` ya corre PHP 8.5
- **Ruta local:** volumen montado en `/var/www/html/app_academica_emdb` (no directo en `/var/www/html`) — necesario porque `navbar.php` usa rutas absolutas hardcodeadas tipo `/app_academica_emdb/...`, replicando la estructura de subcarpeta que ya usaban XAMPP y producción
- **URL local:** `http://localhost:8120/app_academica_emdb/`
- **Puertos:** bloque `8120-8129` asignado a este proyecto, según registro centralizado en `~/proyectos/PUERTOS.md` (fuera del repo)
- **Alias bash (Fedora):** `academica` → cd al proyecto; `c` → clear
- **Nota operativa (uploads):** `uploads/fotos_estudiantes/` requiere `chmod 777` tras crearse en Docker/Fedora — Apache corre como `www-data` (UID distinto al usuario del host que crea la carpeta vía bind mount)
- **Nota operativa (seed sin datos transaccionales):** `database/emdb_academica.sql` solo siembra esquema + catálogos fijos (`roles`, `programas`, `periodos`, `modulos`, usuario admin, `configuracion`) — **no** siembra datos transaccionales (`estudiantes`, `matriculas`, `cohortes`, `gruposemestres`, `gruposmodulos`, `calificaciones`, etc.). Un `docker compose down -v && up` reconstruye el volumen desde cero y pierde todos los datos de prueba transaccionales cargados vía la aplicación — esto es así desde el origen del proyecto, no una consecuencia de la migración de `coho_id` a `matriculas`.
- **Entorno anterior (histórico):** desarrollo local originalmente en Windows con XAMPP (Apache + PHP 8.0 + MySQL 8.0), ruta `C:/xampp/htdocs/app_academica_emdb/`, acceso `http://localhost/app_academica_emdb/`. Migrado a Fedora 44/Docker el 2026-07-25.
- **Conexión DB local:** `app/00_connect/pdo.php` — versionado en git. DSN usa host `127.0.0.1` (no `localhost`) — con `network_mode: service:app`, `localhost` fuerza a PDO a buscar un socket Unix inexistente entre contenedores; `127.0.0.1` fuerza conexión TCP
- **Zona horaria:** el entorno Docker corre en `America/Bogota` (UTC-5, offset fijo, sin horario de verano) desde el commit 7216637 — `TZ` en `docker-compose.yml` (servicios `app` y `db`), `--default-time-zone=-05:00` en MySQL, `date.timezone` en el Dockerfile, y `SET time_zone` en `pdo.php`. Sin esto, los contenedores corren en UTC por defecto y cualquier columna `TIMESTAMP` queda desfasada 5 horas respecto a la hora real de Bogotá.
- **Usuario admin sembrado:** `admin@emdb.edu.co` / `Admin@2026` (definido en database/emdb_academica.sql, sección 9.6)
- **Conexión DB producción:** `app/00_connect/pdo_web.php` — existe **únicamente en el servidor de producción**. Nunca se versiona en git ni existe en el repo local en ningún momento: el estudiante lo crea y lo mantiene manualmente, directamente en el hosting, porque contiene las credenciales reales de la base de datos de producción. No es un descuido ni algo pendiente de agregar al repositorio — es una decisión deliberada.
- **Cambio de entorno:** el código siempre hace `require` sobre `pdo.php` (nunca sobre `pdo_web.php` directamente), así que en producción el renombrado ocurre a mano, en el propio servidor: el `pdo.php` que llega por deploy (con credenciales locales) se renombra a `pdo_local.php` para conservarlo, y el `pdo_web.php` ya existente en el servidor (mantenido manualmente, nunca tocado por el deploy) se renombra a `pdo.php` para que la aplicación lo use.
- **Ambos archivos** usan PDO con `FETCH_ASSOC` y `ERRMODE_EXCEPTION`.
- **Nombre de la base de datos local:** `emdb_academica`
- **Charset:** `utf8mb4` — `utf8mb4_unicode_ci`

---

## Estructura de módulos

```
app_academica_emdb/
  app/
    00_connect/        — Conexión DB: pdo.php (local, versionado) / pdo_web.php (producción, solo en el servidor — nunca en git). También recaptcha_config.php: credenciales reCAPTCHA reales, fuera de git (mismo patrón que pdo_web.php) — preparación para la Fase 3 del formulario público de inscripción, todavía no integrado en ningún formulario
    00_selects/        — Consultas SELECT reutilizables que pueblan dropdowns
    00_img/            — Recursos estáticos: logo, iconos
    00_files/          — Componentes PHP compartidos: navbar.php (roles 1 y 2), helpers.php (funciones PHP compartidas, ej. formatearUltimoAcceso()), favicon, robots.txt, .htaccess
    01_login/          — Autenticación por sesión y redirección por rol
    02_estudiantes/    — CRUD estudiantes + matrícula a programas + gestión de requisitos documentales configurables (catálogo por programa, checklist por matrícula) — roadmap en Phase 2.12
    03_docentes/       — CRUD docentes
    04_grupos/         — Cohortes, grupos semestre, grupos módulo
    05_calificaciones/ — Registro de notas por docente (módulo crítico)
    06_reportes/       — Informe de calificaciones por programa→período→módulo (autoconsulta del estudiante o consulta del coordinador a cualquier estudiante, vía buscador) + reporte por grupo módulo (todos los estudiantes) + exportación Excel/PDF
    07_coordinador/    — Dashboard de seguimiento académico
    08_admin/          — Gestión de usuarios del sistema
    09_inscripcion_publica/ — Formulario público de inscripción para aspirantes sin cuenta (sin sesión), verificación reCAPTCHA server-side, detección de duplicados por documento/correo
    10_ayudas/         — Contenido de ayuda mostrado en el sidebar de cada sección de la aplicación (CRUD en 08_admin)
    11_actualizacion_datos/ — Link público (sin sesión) para que un estudiante actualice su propia ficha (`estudiantes`+`fichas_inscripcion`) vía token de un solo uso, pendiente de aprobación del coordinador desde `tablaMatriculados` (`02_estudiantes`) — roadmap completo, ver Phase 2.11 en "Estado del roadmap"
  uploads/
    fotos_estudiantes/ — Fotos de estudiantes, fuera de app/, excluidas de git salvo .gitkeep
  CLAUDE.md
  README.md
  CHANGELOG.md
```

> El prefijo `00_` identifica carpetas de utilidades compartidas. Los módulos funcionales inician en `01_`.

---

## Arquitectura

### Convención de archivos por módulo

Cada módulo sigue un patrón estricto de tres archivos por dominio funcional:

| Sufijo | Rol |
|---|---|
| `*_view.php` | Vista PHP. Incluye `check_session.php` al inicio. Layout Bootstrap. Datos cargados vía AJAX tras el render. |
| `*_mdl.php` | Modelo PHP. Recibe `$_GET['accion']` mediante `switch`, ejecuta query PDO parametrizada, retorna `json_encode($envelope)`. |
| `*_ctrl.js` | Controlador jQuery. Llama `$.ajax({ type:"POST", url:"*_mdl.php?accion=xxx" })`, parsea el envelope JSON, actualiza DOM o DataTable. |

**Ejemplo para el módulo de estudiantes:**
```
app/02_estudiantes/
  est_view.php
  est_mdl.php
  est_ctrl.js
```

### Responsabilidades por capa

| Capa | Responsabilidad |
|---|---|
| `_view.php` | `session_start()` + `require check_session.php` al inicio. Esqueleto Bootstrap, modales vacíos, inputs ocultos de estado, includes de `<script>`. Sin lógica de negocio. |
| `_ctrl.js` | Inicialización de DataTable, enlace de eventos DOM, recolección de datos de formulario, llamadas AJAX, actualizaciones del DOM con la respuesta del envelope. |
| `_mdl.php` | `session_start()`, `require "../00_connect/pdo.php"`, `switch($_GET['accion'])`, query SQL con parámetros enlazados, `echo json_encode($envelope)`. |

### Flujo de datos

```
_view.php  →  _ctrl.js ($.ajax POST)  →  _mdl.php (?accion=xxx)  →  PDO + params  →  json_encode($envelope)
                   ↑____________________________envelope JSON___________________________________|
```

### Envelope JSON estándar

Todas las respuestas del servidor usan esta estructura — sin excepción:

```php
// Éxito con datos
echo json_encode(['status' => 'ok', 'data' => $rows]);

// Éxito sin datos (INSERT / UPDATE / DELETE)
echo json_encode(['status' => 'ok', 'rows' => $stmt->rowCount()]);

// Error
echo json_encode(['status' => 'error', 'message' => 'Descripción del error']);
```

El controlador **siempre** verifica `response.status === 'ok'` antes de procesar `response.data`.

### Enrutamiento por acción

Todas las operaciones del servidor se despachan mediante `$_GET['accion']` aunque el método HTTP sea POST:

```js
$.ajax({ type: "POST", url: "est_mdl.php?accion=guardar", data: formData })
```

Un archivo `_mdl.php` gestiona todas las acciones de su dominio mediante un `switch`. Agregar una operación = agregar un `case`.

---

## Convenciones de base de datos

### Nombres de tablas

- Minúsculas, plural, sin prefijo `TBL_`
- Ejemplos: `estudiantes`, `docentes`, `calificaciones`, `gruposmodulos`
- Tablas puente (N:M): combinar las primeras 2 letras de cada tabla
  - `grupos_semestre` + `estudiantes` → `grseestudiantes`
- La letra **ñ** se reemplaza por **ni**: `anio`, no `año`
- Sin guiones bajos en nombres de tabla cuando se combinan palabras: `gruposmodulos`, no `grupos_modulos`

### Nombres de campos

- Prefijo con las **primeras 4 letras** del nombre de la tabla
- La clave primaria siempre sigue la regla: `[4letras]_id`
- Las claves foráneas se nombran igual que la PK referenciada

```
Tabla: estudiantes   → prefijo: estu_
  estu_id (PK)
  estu_nombres
  estu_apellidos
  usua_id (FK — igual que usuarios.usua_id)

Tabla: docentes      → prefijo: doce_
  doce_id (PK)
  doce_nombres
  doce_sigla
  usua_id (FK)

Tabla: calificaciones → prefijo: cali_
  cali_id (PK)
  grmo_id (FK — grupos_modulos.grmo_id)
  estu_id (FK — estudiantes.estu_id)
  cali_n1 ... cali_n4
```

### Nombres de campos de fecha

Comenzar con la palabra `fecha` seguida del tipo, sin guión bajo:

```
fechainicio
fechafin
fechanacimiento
fechamatricula
fechaentreganotas
```

### Tipos de datos

| Tipo de información | Tipo SQL | Notas |
|---|---|---|
| Claves primarias | `INT(5) AUTO_INCREMENT` | Todas las tablas |
| Claves foráneas | `INT(5) NOT NULL` | Mismo tipo que la PK referenciada |
| Nombres, apellidos | `VARCHAR(100) NOT NULL` | |
| Texto largo | `VARCHAR(500)` | Observaciones, descripciones |
| Calificaciones 0.0–5.0 | `DECIMAL(3,1)` | Escala colombiana técnica |
| Siglas (módulo, docente) | `VARCHAR(10)` | Códigos cortos únicos |
| Códigos (grupos, cohortes) | `VARCHAR(25)` | Formatos con guiones |
| Fechas | `DATE NOT NULL` | Sin componente horario |
| Timestamps de auditoría | `TIMESTAMP NOT NULL DEFAULT current_timestamp()` | Creación de registros |
| Flags booleanos | `TINYINT(1)` valores `0`/`1` | En PHP: `$row['campo'] == 1` |
| Estados tipo enum | `VARCHAR(20)` | Valores en mayúscula: `'ACTIVO'`, `'RETIRADO'` |
| Contraseñas | `VARCHAR(255) NOT NULL` | Siempre hash bcrypt |

**Nota de esquema (commit `0e0a508`, 2026-08-29):** esta tabla ya
especificaba `VARCHAR(25)` para códigos de grupos/cohortes, pero
`gruposemestres.grse_codigo` y `cohortes.coho_codigo` se crearon como
`VARCHAR(20)` en el DDL original — una desalineación entre
documentación y esquema real que pasó inadvertida hasta que los
programas `ASO2016`/`AMD2016` (siglas de 7 caracteres, más largas que
las originales `ASO`/`MD` de 3) generaron códigos de 21 caracteres y
MySQL los rechazó con `SQLSTATE[22001]`. Corregido ampliando ambas
columnas a `VARCHAR(25)`, alineando por fin el esquema con esta tabla.

### Código autogenerado por concatenación: dimensionar la columna contra el peor caso, no el caso típico

Cuando un código se genera concatenando partes de longitud variable
(ej. sigla de programa + período + semestre + jornada), la columna que
lo almacena no debe dimensionarse pensando en los valores que existen
hoy, sino en el máximo que el resto del sistema permite para cada
parte. Si `prog_sigla` tiene un tope de `VARCHAR(10)` en su propia
columna, la fórmula de concatenación debe soportar una sigla de hasta
10 caracteres, no solo las de 3 que existían al momento de diseñar el
formato — de lo contrario, el sistema funciona correctamente durante
meses y falla de forma determinística en cuanto alguien usa el margen
completo que otra parte del sistema ya le permitía.

Patrón de defensa en dos capas, replicable para cualquier código
autogenerado futuro:
1. La columna se dimensiona contra la suma de los máximos de cada parte
   de la fórmula, no contra el caso observado en los datos actuales.
2. Además, se agrega una validación defensiva server-side
   (`mb_strlen($codigo) > <límite>`) inmediatamente antes de cualquier
   INSERT/UPDATE, que responda con un mensaje específico en vez de
   dejar que MySQL rechace con `SQLSTATE[22001]` — igual de importante
   que la validación de unicidad ya documentada más arriba, porque
   cubre el caso en que la columna se quede corta de nuevo en el
   futuro (ej. si se permite una sigla de 12 caracteres más adelante).

Ejemplo: `grse_codigo` y `coho_codigo` en `04_grupos` (commit
`0e0a508`, 2026-08-29) — `guardar_grupo` y `guardar_cohorte` en
`grupos_mdl.php` ahora validan `mb_strlen($codigo) > 25` antes del
chequeo de duplicados existente.

### Restricciones clave

```sql
-- Calificaciones: rango válido
CHECK (cali_n1 BETWEEN 0.0 AND 5.0)

-- Un registro de notas por estudiante-grupo
UNIQUE (grmo_id, estu_id)

-- Un estudiante matriculado una vez por programa
UNIQUE (estu_id, prog_id)

-- No eliminar módulo con grupos activos
FOREIGN KEY (modu_id) REFERENCES modulos(modu_id) ON DELETE RESTRICT

-- No eliminar grupo con calificaciones registradas
FOREIGN KEY (grmo_id) REFERENCES gruposmodulos(grmo_id) ON DELETE RESTRICT
```

---

## Tablas del sistema

Orden de creación (respetando dependencias FK):

```
1. roles
2. programas
3. cohortes
4. periodos
5. usuarios
6. docentes
7. estudiantes
8. modulos
9. matriculas
10. gruposemestres
11. grseestudiantes   (tabla puente)
12. gruposmodulos
13. calificaciones
14. horariosgrupo
15. fichas_inscripcion (datos familiares y estudios anteriores, AC-FO-02)
```

### Resumen de tablas principales

| Tabla | Prefijo | Propósito |
|---|---|---|
| `roles` | `role_` | Catálogo: Administrador, Coordinador, Docente, Estudiante |
| `usuarios` | `usua_` | Credenciales de acceso. Relacionado 1:1 con docentes o estudiantes |
| `docentes` | `doce_` | Datos personales + sigla única |
| `estudiantes` | `estu_` | Datos personales + foto (`estu_foto`) + campos AC-FO-02 (`estu_expedidoen`, `estu_ciudadnac`, `estu_ocupacion`, `estu_estadocivil`, `estu_discapacidad`, `estu_multiculturalidad`) — **ya no tiene columna `coho_id` propia** (eliminada junto con la FK `fk_estu_coho` en el commit `6abbdae`, 2026-09-03); la cohorte vive exclusivamente en `matriculas.coho_id`, una por matrícula |
| `programas` | `prog_` | ASO y MD con resolución y fechas de vigencia |
| `modulos` | `modu_` | Asignaturas por programa con sigla |
| `cohortes` | `coho_` | Grupos de admisión: `CH-ASO-2024B` |
| `periodos` | `peri_` | Semestres académicos: `2026A`, `2026B` |
| `matriculas` | `matr_` | Relación estudiante-programa con estado (`matr_estado`) — desde el commit `3e551e5` (2026-08-30) también con cohorte propia (`coho_id`, una por matrícula, no por estudiante) y condición académica del semestre (`matr_estado_academico`, independiente de `matr_estado`) |
| `gruposemestres` | `grse_` | Instancia de programa en período+jornada |
| `grseestudiantes` | — | Tabla puente N:M gruposemestres↔estudiantes |
| `gruposmodulos` | `grmo_` | Módulo + docente + fechas dentro de un grupo semestre |
| `calificaciones` | `cali_` | Notas por estudiante y grupo módulo |
| `horariosgrupo` | `hora_` | Horarios por grupo (solo jornada SEMA) |
| `fichas_inscripcion` | `finc_` | Datos familiares (padre/madre/acudiente) y estudios anteriores — formato AC-FO-02 |
| `solicitudes_actualizacion` | `soac_`/`soac_ficha_` | Staging de cambios propuestos por el estudiante a su propia ficha (`estudiantes`+`fichas_inscripcion`) vía link público sin sesión, pendientes de que el coordinador apruebe o descarte desde `tablaMatriculados` — roadmap completo y verificado en navegador (ver Phase 2.11 en "Estado del roadmap"). 3 decisiones de diseño: `soac_ficha_prog_id` sin FK hacia `programas` (valor propuesto sin validar hasta la aprobación, no en el momento de guardar la solicitud); `soac_estado` (`ENUM 'generado'/'recibido'/'aprobado'/'descartado'`) como borrado lógico — una solicitud descartada nunca se elimina, transiciona de estado; tabla ubicada al final del `.sql` en su propia sección "BLOQUE 6D", mismo patrón que `ayudas`/`configuracion` (ver nota debajo) |

**Nota de esquema — tablas fuera del "Orden de creación" numerado de arriba:** la lista `1.` a `15.` de esta sección quedó fija en el diseño original y ya no se ha actualizado con las tablas de soporte agregadas después — ni `ayudas` ni `configuracion` (ambas del bloque de utilidades, sin relación con el dominio académico) aparecen ahí, y `solicitudes_actualizacion` tampoco se agregó por el mismo motivo. Las tres sí están en la tabla "Resumen de tablas principales" de arriba (más `solicitudes_actualizacion`) y en el DDL real (`database/emdb_academica.sql`, comentario "Tablas creadas: 19"). No renumerar la lista `1.`-`15.` para insertar estas tablas — mantenerla como registro del diseño académico original y usar la tabla de resumen como inventario vigente.

**Nota de esquema — obtención de estudiantes de un grupo semestre (commit `a39cb24`, 2026-08-30):** `grseestudiantes` (tabla puente listada arriba, según la convención de nombres N:M de esta misma sección) fue reemplazada en el DDL real por `grmoestudiantes` — el comentario del propio `database/emdb_academica.sql` lo documenta explícitamente ("Reemplaza grseestudiantes para un control más granular"). No existe ninguna relación directa entre `gruposemestres` y `estudiantes`: para obtener los estudiantes de un `grse_id` dado, la ruta es `grmoestudiantes` → `gruposmodulos` (JOIN por `grmo_id`, filtrando `gruposmodulos.grse_id = ?`) — como en `listar_estudiantes_grupo` (`grupos_mdl.php`, `04_grupos`) y `listar_modulos_estudiante` (`est_mdl.php`, `02_estudiantes`). No confundir la fila `grseestudiantes` de la tabla de arriba con una tabla vigente en el esquema.

---

## Reglas de negocio de calificaciones

Estas reglas son invariables y deben respetarse en toda la lógica del módulo `05_calificaciones`:

| Regla | Detalle |
|---|---|
| Estructura de notas | N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = 100% |
| Supletorios | Solo N1, N2 y N4 tienen supletorio. **N3 no tiene supletorio — nunca.** |
| Activación supletorio | El campo supletorio se activa **únicamente si la nota original es 0.0** (estudiante no se presentó) |
| Acumulado | El acumulado usa el supletorio si la nota original es 0.0; de lo contrario usa la nota original |
| Nota definitiva | Calculada en PHP con la fórmula de pesos. El cliente puede mostrar el valor para UX pero el servidor recalcula antes de persistir |
| Escala | 0.0 a 5.0 con un decimal. `DECIMAL(3,1)` en BD |
| Formulario físico | `GA-FO-04` — el diseño digital debe conservar la estructura de este formato |

### Fórmula de nota definitiva (PHP):

```php
// Si nota original es 0.0, usar supletorio (si existe); de lo contrario usar original
$acum_n1 = ($cali_n1 == 0.0 && $cali_sup_n1 !== null) ? $cali_sup_n1 : $cali_n1;
$acum_n2 = ($cali_n2 == 0.0 && $cali_sup_n2 !== null) ? $cali_sup_n2 : $cali_n2;
$acum_n4 = ($cali_n4 == 0.0 && $cali_sup_n4 !== null) ? $cali_sup_n4 : $cali_n4;

$definitiva = round(
    ($acum_n1 * 0.20) +
    ($acum_n2 * 0.20) +
    ($cali_n3 * 0.20) +
    ($acum_n4 * 0.40),
    1
);
```

---

## Roles de usuario

| `role_id` | Nombre | Destino post-login | Acceso |
|---|---|---|---|
| `1` | Administrador | `08_admin` | CRUD usuarios, configuración sistema |
| `2` | Coordinador | `07_coordinador` | Dashboard, seguimiento, reportes, edición planillas |
| `3` | Docente | `05_calificaciones` | Solo sus grupos asignados |
| `4` | Estudiante | `06_reportes` | Solo lectura de sus propias notas |

**Regla:** `check_session.php` verifica sesión activa y rol autorizado al inicio de cada `_view.php`. Sin sesión válida → redirect a `01_login/`. Sin rol autorizado → redirect a su módulo correspondiente.

---

## Variables de sesión

```php
$_SESSION['usua_id']      // ID del usuario autenticado
$_SESSION['usua_email']   // Email del usuario
$_SESSION['role_id']      // Rol del usuario (1=Admin, 2=Coord, 3=Docente, 4=Estudiante)
$_SESSION['doce_id']      // ID del docente (solo si role_id = 3)
$_SESSION['estu_id']      // ID del estudiante (solo si role_id = 4)
$_SESSION['doce_sigla']   // Sigla del docente (para generar códigos de grupo módulo)
```

**Regla:** `usua_id` y `role_id` se renderizan desde PHP en la vista como inputs ocultos cuando se necesitan en el cliente:

```html
<input type="hidden" id="npt_usua_id_actual" value="<?= $_SESSION['usua_id'] ?>">
<input type="hidden" id="npt_role_id_actual" value="<?= $_SESSION['role_id'] ?>">
```

---

## Convenciones HTML/JS

### Prefijos de elementos

| Prefijo | Elemento |
|---|---|
| `npt_` | `<input>` (visibles y ocultos) |
| `slct_` | `<select>` |
| `btn_` | `<button>` |
| `tbl_` | Tablas DataTable |
| `mdl_` | Modales Bootstrap |
| `bloque_` | `<div>` de mostrar/ocultar |
| `spn_` | `<span>` de solo lectura |
| `lnk_` | Enlaces de navegación |
| `frm_` | `<form>` (si se usan) |

**Los nombres de variables JS coinciden exactamente con los nombres de columnas en la BD.**
Ejemplo: `estu_nombres` en JS = `estu_nombres` en SQL = `estu_nombres` en PHP.

### Select → Input bridge

Cada `<select>` tiene un `<input type="hidden">` pareado que almacena el ID seleccionado. El handler `change` del select copia `.val()` al input oculto. El formulario trabaja siempre con inputs, nunca con selects directamente.

```html
<select id="slct_prog_id">...</select>
<input type="hidden" id="npt_prog_id" value="">
```

```js
$('#slct_prog_id').change(function(){
    $('#npt_prog_id').val($(this).val());
});
```

### Select informativo (`disabled`, sin input pareado)

Excepción al patrón "Select → Input bridge" de arriba: cuando un `<select>` muestra un dato de solo referencia visual para quien opera el formulario — no un valor que el formulario vaya a enviar — se marca `disabled` y **no** lleva `<input type="hidden">` pareado. Sin el `change` handler ni el input oculto, ese valor nunca puede llegar al `_mdl.php` por accidente (un select `disabled` ni siquiera se serializa en un submit nativo, y aquí tampoco participa del objeto plano armado a mano). No usar este patrón para ningún select cuyo valor se necesite persistir — para eso sigue aplicando el bridge estándar.

```html
<select class="form-select" id="slct_jornada_declarada" disabled>
    <option value="">-- Seleccionar --</option>
    <option value="SEMANA">Semana</option>
    <option value="SABADOS">Sábados</option>
</select>
<div class="form-text">Informativa — no se guarda. Sirve de referencia.</div>
```

Primer uso: `#slct_jornada_declarada` en el modal "Completar Matrícula" de `02_estudiantes` (commit `ef79791`, 2026-08-22) — muestra la jornada que el aspirante declaró en su Ficha Familiar (`fichas_inscripcion.jornada`) como referencia para el coordinador al crear el Grupo Semestre; `gruposemestres.grse_jornada` sigue siendo la única fuente operativa (ver la decisión "Jornada como atributo de grupo semestre" más abajo).

### Select dependiente poblado vía atributo `data-*` en las opciones de otro select (sin llamada AJAX adicional)

Cuando un `<select>` B debe repoblarse según la opción elegida en un `<select>` A, y el dato necesario para generar las opciones de B ya viaja incluido en la misma respuesta AJAX que pobló A, agregar ese dato como atributo `data-*` en cada `<option>` de A en vez de disparar una segunda llamada AJAX solo para volver a consultarlo. El handler `change` de A lee el atributo con `.find('option:selected').data('...')` y genera las opciones de B en el propio cliente, sin round-trip al servidor.

```js
// Al poblar A, se agrega el dato que B necesitará más adelante
response.data.forEach(function (p) {
    opciones += `<option value="${p.prog_id}" data-duracion="${p.prog_duracion_semestres}">${p.prog_sigla}</option>`;
});
```

```js
$('#slct_prog_id').on('change', function () {
    const duracion = $(this).find('option:selected').data('duracion');
    poblarSelectSemestre('#slct_matr_semestre', duracion); // genera 1..duracion en el cliente
});
```

No usar este patrón si el dato de B no viaja ya en la respuesta que pobló A — en ese caso sí corresponde una llamada AJAX dedicada (ver `cargarCohortesPorPrograma()`, que depende de su propia consulta porque las cohortes no vienen en `listar_programas`).

Primer uso: `data-duracion` en las opciones de `#slct_prog_id`/`#slct_editar_prog_id` (`02_estudiantes`, commit `c985188`, 2026-09-02) — repuebla `#slct_matr_semestre`/`#slct_editar_matr_semestre` con las opciones `1..prog_duracion_semestres` sin ninguna llamada AJAX adicional, vía la nueva función global `poblarSelectSemestre()` (declarada fuera de `$(document).ready` por el mismo motivo que `cargarCohortesPorPrograma` — ver "Funciones de refresh invocadas desde onclick inline..." en Patrones de ingeniería).

### Subida de archivos (FormData)

Única excepción al patrón de objeto plano de datos usado en el resto del proyecto. Se usa exclusivamente cuando el AJAX debe subir un archivo real (ej. foto de estudiante) — nunca para formularios de texto/select normales, que siguen usando el objeto plano estándar.

```js
let formData = new FormData();
formData.append('estu_id', id);
formData.append('foto', $('#npt_foto')[0].files[0]);

$.ajax({
    url: 'est_mdl.php?accion=subir_foto',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    success: function(response) { ... }
});
```

`processData: false` y `contentType: false` son obligatorios: jQuery, por defecto, serializa `data` como querystring y fija `Content-Type: application/x-www-form-urlencoded`, lo que rompe el envío de archivos binarios.

### Encabezado de sección numerado con resaltado de color (`.seccion-titulo`)

Para modales largos con varias secciones temáticas (ej. un formulario que combina datos personales + datos familiares + datos académicos), cada encabezado de sección usa la clase `.seccion-titulo` (fondo azul `#0d6efd`, texto blanco, definida en `00_files/estilos.css`) en vez del patrón anterior `<h6 class="text-muted border-bottom pb-2 mb-3">`. El texto del encabezado se numera (`1. Datos del Estudiante`, `2. Multiculturalidad`, ...) para que el usuario sepa cuántas secciones tiene el formulario y en qué punto va. El tag HTML no es fijo — si el encabezado ya existía como otro elemento con `id` propio referenciado desde JS (ej. `<label id="lbl_multiculturalidad">`), se conserva ese tag y ese `id` intactos, agregando solo la clase y el número; no se fuerza a `<h6>` por consistencia visual si eso implica perder el `id`.

```html
<h6 class="seccion-titulo">1. Datos del Estudiante</h6>
```

Primer uso: las 5 secciones del modal `#mdl_estudiante` ("Ficha de Inscripción") en `02_estudiantes` (commit `f088466`, 2026-08-24). Antes de replicar este patrón en otro módulo, evaluar si el formulario es suficientemente largo/complejo como para justificar la numeración — para modales cortos de 1-2 secciones, el `<h6 class="text-muted border-bottom pb-2 mb-3">` original sigue siendo válido.

Segundo uso, primera vez fuera de `02_estudiantes`: las 5 secciones de `insc_view.php` (`09_inscripcion_publica`, commit `0e098bb`, 2026-08-24) — confirma que el patrón es reutilizable a nivel de proyecto, no exclusivo del modal de estudiante.

### Fondo verde de "campo diligenciado" (`.campo-lleno`) — capa visual independiente de la validación funcional

Distinta de `is-invalid`/`is-valid` (que indican si un campo **cumple** las reglas de negocio — obligatoriedad, formato), la clase `.campo-lleno` (fondo verde claro `#d1e7dd`, definida en `00_files/estilos.css`) indica únicamente que un campo **tiene algún valor**, sin importar si ese valor es válido. Son dos sistemas de retroalimentación visual deliberadamente separados y **no deben fusionarse**: un campo puede estar lleno y a la vez ser inválido (ej. un correo sin `@`), y ambos estados deben poder mostrarse a la vez sin que uno oculte al otro.

```js
function marcarCampoLleno($campo) {
    const valor = ($campo.val() || '').toString().trim();
    $campo.toggleClass('campo-lleno', valor !== '');
}
```

Se engancha con un listener delegado sobre el contenedor del formulario, no por campo individual — necesario para cubrir también los campos que aparecen/desaparecen dinámicamente en el DOM (ej. bloques Padre/Madre/Acudiente):

```js
$(document).on('input change', '#mdl_estudiante input, #mdl_estudiante select, #mdl_estudiante textarea', function() {
    marcarCampoLleno($(this));
});
```

Primer uso: modal `#mdl_estudiante` en `02_estudiantes` (commit `f088466`, 2026-08-24). Antes de replicar este patrón en otro formulario, usar el mismo listener delegado sobre el contenedor (no un `.forEach` fijo sobre un array de selectores, como hace `marcarValidacion()` vía `CAMPOS_ESTUDIANTE`) si el formulario tiene bloques condicionales que aparecen/desaparecen.

Segundo uso, primera vez fuera de `02_estudiantes`: `#form_inscripcion` en `insc_ctrl.js` (`09_inscripcion_publica`, commit `0e098bb`, 2026-08-24), mismo listener delegado sobre el contenedor del formulario — confirma que el patrón es reutilizable a nivel de proyecto, no exclusivo del modal de estudiante.

### Columna "Acción"/"Acciones" de una tabla: agrupar bajo un único botón de ícono + dropdown de Bootstrap (criterio vigente: uniformidad visual del proyecto, no un umbral de cantidad)

**Criterio vigente (desde el commit `e1c232d`, 2026-09-01, tercer uso del patrón):** reemplazar los botones de acción en línea de la columna "Acción"/"Acciones" de una tabla DataTable por un único botón de ícono (`bi bi-gear-fill`, Bootstrap Icons — ver "Frontend stack") que despliega un `<ul class="dropdown-menu">` de Bootstrap con una entrada por acción — **sin importar cuántos botones tenga la columna**, incluso con exactamente 2 (ver tercer uso más abajo, `tablaDocentes`). La razón de aplicarlo ya no es "la columna está saturada", sino uniformidad visual: toda columna de acciones por fila del proyecto se ve y se comporta igual, decisión explícita de Jose Luis.

**Criterio original (primer uso, `4fb2257`, 2026-08-31 — contexto histórico, ya no es la condición que decide si aplicar el patrón):** el patrón nació pensado específicamente para columnas que acumulaban 3 o más botones en línea (ej. "📝 Datos Estudiante", "✏️ Matrícula", "➕ Matricular en otro programa"), como alternativa a seguir agregando botones sueltos a medida que la columna crecía. Ese razonamiento sigue siendo válido como motivación original del patrón y se conserva aquí como registro histórico — pero el tercer uso (`tablaDocentes`, 2 botones, nunca llegó a 3) demuestra que ya no es el criterio que decide si el patrón aplica.

El toggle no lleva `id` fijo — cada fila del `render()` genera el mismo patrón, y Bootstrap resuelve el `data-bs-toggle="dropdown"` por proximidad en el DOM (el `<ul>` inmediatamente adyacente dentro del mismo `<div class="dropdown">`), sin necesidad de un `id` único por fila:

```js
render: function (data, type, row) {
    return `<div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                    <i class="bi bi-gear-fill"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item" type="button" onclick="...">Acción 1</button></li>
                    <li><button class="dropdown-item" type="button" onclick="...">Acción 2</button></li>
                </ul>
            </div>`;
}
```

`dropdown-menu-end` alinea el menú hacia la izquierda del toggle (evita que se salga de la pantalla en la columna más a la derecha de la tabla) y `data-bs-boundary="viewport"` evita que el menú quede recortado por el contenedor con scroll/overflow de la tabla — necesario en las últimas filas visibles de un DataTable largo. Cualquier indicador visual que ya tuviera un botón individual (ej. el punto de color de `estado_ficha`, ver "El indicador de completitud de una fila...") se conserva como `<span>` dentro del `dropdown-item` correspondiente, recalculado localmente en el mismo `render()` — sin compartirse entre tablas distintas (ver esa misma sección sobre reutilización dentro de una fila, no entre tablas).

**Ítem deshabilitado con tooltip dentro del dropdown:** un `<button disabled>` no dispara el evento `mouseover` necesario para mostrar su propio `title` — se envuelve en `<li tabindex="0" title="...">` (el `title` vive en el `<li>`, no en el botón deshabilitado):

```html
<li tabindex="0" title="Motivo por el que está deshabilitado.">
    <button class="dropdown-item disabled" type="button" disabled>➕ Acción</button>
</li>
```

Primer uso: columna "Acción" de `tablaMatriculados` (`02_estudiantes`, commit `4fb2257`, 2026-08-31) — reemplaza 3 botones en línea (360px de ancho) por el patrón de arriba (70px), agregando además un ítem nuevo ("🖨️ Hoja de Matrícula") y el ítem "➕ Matricular en otro programa" deshabilitado con tooltip según la condición documentada en "Hoja de Matrícula en el dropdown no valida `matr_estado` en frontend" (Decisiones arquitectónicas activas). `tablaAspirantes` no se tocó en este commit — seguía con sus 2 botones en línea, por debajo del umbral de 3+ que justificó el patrón en este primer uso. **Nota (superada desde `e1c232d`, ver "Criterio vigente" al inicio de esta sección):** este primer uso recomendaba confirmar que la columna acumulara 3+ acciones antes de replicar el patrón, con 1-2 botones considerados insuficientes para justificarlo — ese umbral ya no aplica; el tercer uso (`tablaDocentes`, más abajo) lo aplicó con solo 2 botones, por uniformidad visual del proyecto.

Segundo uso: columna "Acción" de `tablaAspirantes` (`02_estudiantes`, commit `9346a76`, 2026-09-01) — reemplaza los 2 botones en línea (Matricular, 📝 Datos Estudiante) por el mismo patrón (300px → 70px), agregando el ítem nuevo "🖨️ Ficha de Inscripción" (antes solo accesible dentro del modal "Datos Estudiante" vía `#btn_ficha_inscripcion_pdf`), siempre habilitado sin validar `estado_ficha` en frontend — ver "Ficha de Inscripción en el dropdown de Acción de Aspirantes no valida `estado_ficha` en frontend" (Decisiones arquitectónicas activas) para la justificación, distinta a la de Hoja de Matrícula pese al mismo resultado visual. Nótese la diferencia de secuencia respecto al primer uso: `tablaMatriculados` ya tenía 4 acciones acumuladas cuando se convirtió a dropdown; `tablaAspirantes` cruzó el umbral de 3+ en el mismo commit que la convirtió, porque el tercer ítem ("🖨️ Ficha de Inscripción") se agregó a la vez que se aplicaba el patrón — no había 3 botones en línea esperando a agruparse. A diferencia de `tablaMatriculados` (que sí requirió una fase de backend previa, commit `53d5ec5`), este segundo uso fue un solo commit de frontend puro: el campo `estado_ficha` que el punto de color necesita ya viajaba en cada fila de `listar_aspirantes` desde antes del cambio.

Tercer uso, primera vez fuera de `02_estudiantes`: columna "Acciones" de `tablaDocentes` (`03_docentes`, commit `e1c232d`, 2026-09-01) — reemplaza los 2 botones en línea (Editar fijo + uno solo de Eliminar/Desactivar/Activar, mutuamente excluyentes según `doce_activo`/`total_grupos_historico`) por el mismo patrón (190px → 70px). Este es el primer uso que **nunca acumuló 3+ botones simultáneos** — la columna siempre tuvo exactamente 2 visibles a la vez, nunca los tres botones condicionales al mismo tiempo — y se aplicó de todas formas por decisión explícita de uniformidad visual (ver "Criterio vigente" al inicio de esta sección y la entrada correspondiente en Decisiones arquitectónicas activas). A diferencia de los dos usos anteriores, **no hay ningún indicador visual tipo punto de color** que conservar dentro de un `dropdown-item` (esta tabla nunca tuvo un equivalente a `estado_ficha`) y **no se agrega ninguna funcionalidad nueva** — la lógica `if/else if/else` sobre `doce_activo`/`total_grupos_historico` se preservó sin ningún cambio, solo cambió el HTML generado en cada rama; es agrupación visual pura, sin ítems nuevos ni cambios de backend.

---

## Patrones de ingeniería

### Modal CRUD cycle

Ciclo estándar para operaciones Crear/Editar en todos los módulos:

1. `limpiarFormulario()` — resetear todos los campos del modal a estado conocido
2. `modal.show()` — mostrar el modal
3. `recuperarRegistro(id)` — poblar campos (solo modo edición)
4. Validación inline con `alert()` + `return false`
5. `recolectarDatos()` → objeto JS plano con todos los valores del formulario
6. `$.ajax POST` → `_mdl.php?accion=xxx`
7. `success`: verificar `response.status === 'ok'` → `DataTable.ajax.reload()`

### Marcado de validación con `:visible` dentro de un modal recién abierto debe diferirse a `shown.bs.modal`

`new bootstrap.Modal(el).show()` no es síncrono-completo — el modal sigue en `display:none` durante uno o más ticks mientras la transición de Bootstrap termina. Cualquier código que dependa del selector jQuery `:visible` (ej. un loop que marca campos como `is-invalid` solo si están visibles, para no marcar un bloque Padre/Madre oculto por `actualizarVisibilidadPadreMadre()`) evaluado inmediatamente después de poblar los campos y antes de que el modal termine de mostrarse siempre ve `:visible === false` para todo — el marcado se salta en silencio, sin ningún error visible. La corrección es envolver ese bloque en `$('#mdl_x').one('shown.bs.modal', function () { ... })`, que solo dispara una vez que el modal ya está completamente visible en el DOM.

Ejemplo: `abrirEditar()` en `02_estudiantes` (commit `76f90c5`, 2026-08-23) — el marcado de validación de los campos de la Ficha Familiar (ahora fusionados dentro de `mdl_estudiante`) se detectó saltándose en silencio durante el desarrollo, antes de llegar a pruebas de navegador, y se corrigió diferiéndolo a `shown.bs.modal`. Antes de escribir un loop de marcado de validación (u cualquier otra lógica que inspeccione `:visible`/dimensiones reales) justo después de poblar un modal recién abierto, verificar si ese código corre antes o después del evento `shown.bs.modal`.

### DataTable como vista de listado

Todas las vistas de listado usan DataTable con fuente AJAX:

```js
$('#tbl_estudiantes').DataTable({
    ajax: {
        url: 'est_mdl.php?accion=listar',
        type: 'POST',
        dataSrc: 'data'
    },
    destroy: true,
    language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
});
```

`dataSrc: 'data'` apunta al campo `data` del envelope JSON. Los botones de acción se inyectan vía `columnDefs`.

### `columns` compartido entre varias instancias de DataTable: función global que retorna un array nuevo, nunca una constante por referencia

Cuando dos (o más) instancias de DataTable necesitan exactamente la misma estructura de `columns`, extraerla a una función global que construya y retorne el array **de cero en cada llamada** — nunca una constante (`const columnas = [...]`) reutilizada por referencia entre las inicializaciones. DataTables muta/anota internamente el array de columnas que recibe cada instancia; compartir el mismo objeto entre dos tablas arriesga una mutación cruzada silenciosa que solo se manifiesta con ambas tablas activas a la vez.

```js
// Global (no dentro de $(document).ready) — consumida por dos instancias
function crearColumnasMatriculados() {
    return [
        { data: 'estu_nombres' },
        // ... resto de columnas ...
    ];
}

tablaMatriculadosActual = $('#tbl_matriculados_actual').DataTable({
    columns: crearColumnasMatriculados()
});
tablaMatriculadosAnteriores = $('#tbl_matriculados_anteriores').DataTable({
    columns: crearColumnasMatriculados()   // segunda llamada = segundo array, no el mismo objeto
});
```

Primer uso: `crearColumnasMatriculados()` en `est_ctrl.js` (`02_estudiantes`, commit `0aaa4e9`, 2026-09-02), consumida por `tablaMatriculadosActual`/`tablaMatriculadosAnteriores` al dividir la pestaña "Matriculados" en Per. Actual/Per. Anteriores — ver también "Decisiones arquitectónicas activas" para el contexto completo. Si las columnas difieren aunque sea parcialmente entre las tablas, este patrón no aplica — no forzar una función compartida con parámetros condicionales para columnas que en realidad son distintas.

### DataTable con `ajax.data` dependiente de un valor async debe construirse DESPUÉS de que ese valor esté resuelto, no antes con reload correctivo

Cuando una tabla DataTable usa una función `data:` en su config de `ajax` que lee una variable poblada de forma asíncrona (ej. `periodoActivoId`, resuelto dentro del `success` de otra petición como `cargarPeriodos()`), inicializar esa tabla antes de que la variable esté lista dispara un auto-load con el filtro sin resolver (`null`/vacío). Si después se corrige con `.ajax.reload()` una vez que el valor ya está disponible, ambas peticiones (la prematura y la de reload) pueden superponerse en el tiempo y producir una condición de carrera donde DataTables termina sumando filas del dataset en vez de reemplazarlas limpiamente — **sin que exista ningún `rows.add()` explícito en el código**, el propio plugin gestiona mal la superposición.

La corrección correcta es NO construir esa tabla hasta que la dependencia esté resuelta (ej. separarla en una función aparte e invocarla dentro del `success` que resuelve la dependencia), en vez de inicializar temprano y "corregir" después con reload.

```js
// PROHIBIDO — la tabla se auto-carga con periodoActivoId aún null
function cargarTablas() {
    tablaMatriculadosActual = $('#tbl_x').DataTable({
        ajax: { data: function (d) { d.peri_id = periodoActivoId; } }
    });
}
cargarTablas();       // dispara auto-load con peri_id = null
cargarPeriodos();     // resuelve periodoActivoId más tarde, de forma async
// ... y "corrige" con tablaMatriculadosActual.ajax.reload(null, false) —
// puede superponerse con la petición prematura y duplicar filas.

// CORRECTO — la tabla no existe hasta que la dependencia está resuelta
function cargarTablasMatriculados() {
    tablaMatriculadosActual = $('#tbl_x').DataTable({
        ajax: { data: function (d) { d.peri_id = periodoActivoId; } }
    });
}
function cargarPeriodos() {
    $.ajax({ success: function (response) {
        periodoActivoId = /* ... resuelto de response ... */;
        cargarTablasMatriculados();   // única construcción, ya con el valor listo
    }});
}
```

Caso real: `tablaMatriculadosActual`/`tablaMatriculadosAnteriores` en `02_estudiantes` (commit `7052723`, 2026-09-03) — dependían de `periodoActivoId`, resuelto en `cargarPeriodos()`. Reportado por Jose Luis como filas duplicadas (fila #1 = fila #17 con 50 registros por página); descartado el backend con un diagnóstico de solo lectura (SQL de `listar_matriculados` verificado limpio contra Docker vivo) antes de tocar el frontend. Corregido separando la construcción de ambas tablas a `cargarTablasMatriculados()`, invocada una sola vez dentro del `success` de `cargarPeriodos()` — `cargarTablas()` quedó a cargo únicamente de `tablaAspirantes` (independiente del período).

### Queries PDO siempre parametrizadas

```php
// CORRECTO — siempre
$sql = "SELECT * FROM estudiantes WHERE estu_id = ? AND estu_activo = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_POST['estu_id']]);
$rows = $stmt->fetchAll();

// PROHIBIDO — nunca
$sql = "SELECT * FROM estudiantes WHERE estu_id = $_POST[estu_id]";
```

### Validación en dos capas

1. **Cliente** (`_ctrl.js`): validación inmediata para UX — campos vacíos, formatos.
2. **Servidor** (`_mdl.php`): validación de negocio antes de ejecutar la query. El servidor nunca confía en los datos del cliente.

### Lógica de negocio en el servidor

Los cálculos críticos (nota definitiva, estado de aprobación, acumulados con supletorios) se realizan en PHP, no en JavaScript. El cliente recibe resultados calculados, no datos crudos para calcular.

### DELETE físico con verificación de reglas de negocio en servidor

Cuando una entidad tiene FKs entrantes con distintas políticas de borrado (RESTRICT/CASCADE), el DELETE físico debe:

1. Verificar en el servidor (`_mdl.php`) las condiciones de negocio que permiten el borrado — nunca confiar en que el frontend oculte el botón.
2. Ejecutarse dentro de una transacción PDO (`beginTransaction()` / `commit()` / `rollBack()`), incluyendo el borrado explícito de filas dependientes que el propio RESTRICT no resuelve solo.
3. Dejar que las FK con RESTRICT actúen como salvavidas final — si una tabla inesperada tiene filas asociadas, la excepción PDO debe activar el rollback, nunca forzarse el borrado.

Ejemplo: `eliminar_aspirante` en `02_estudiantes` — verifica `matr_estado` antes de proceder, borra `matriculas` en la misma transacción, y deja que `calificaciones` (con RESTRICT) bloquee la operación si hubiera datos inesperados.

### DELETE condicionado + alternativa reversible con flag existente

Cuando una entidad ya tiene un flag booleano de activo/inactivo (ej. `modu_activo`, `estu_activo`, `grse_activo`) y el DELETE físico puede bloquearse por FKs con historial real de uso, ofrecer una alternativa explícita y reversible en vez de solo bloquear:

1. Intentar el DELETE solo si la entidad no tiene historial en la tabla dependiente relevante — un simple `SELECT COUNT(*)` previo basta si no hay filas hijas que borrar en la misma operación (a diferencia del patrón anterior, aquí no hace falta una transacción PDO si el DELETE es de una sola tabla).
2. Si el DELETE está bloqueado, el mensaje de error debe sugerir explícitamente la alternativa reversible (ej. "no puede eliminarse — puedes desactivarlo en su lugar"), no solo informar el bloqueo.
3. La acción de desactivar/activar (`toggle_estado_*`) es una operación separada, sin modal de confirmación — a diferencia del DELETE, que si es irreversible sí la requiere — porque alternar el flag es reversible en cualquier momento.

Ejemplo: `eliminar_modulo` / `toggle_estado_modulo` en `04_grupos` — un módulo con historial en `gruposmodulos` no puede eliminarse, pero sí desactivarse (`modu_activo=0`), lo que lo excluye de los selects activos (`listar_modulos_por_programa`) sin perder la trazabilidad de calificaciones o grupos pasados que ya lo referencian.

Variación con verificación contra múltiples tablas dependientes: `eliminar_programa` / `toggle_estado_programa` en `04_grupos` (commit `00a22fc`, 2026-08-19) — a diferencia de `eliminar_modulo` (una sola tabla dependiente, `gruposmodulos`), un programa puede tener historial en **tres** tablas independientes (`modulos`, `cohortes`, `matriculas`). El `SELECT COUNT(*)` previo sigue siendo una sola query, pero suma las tres subconsultas de conteo en un solo `total` — si cualquiera de las tres es mayor que cero, el DELETE se bloquea y el mensaje sugiere desactivar (`prog_activo=0`) en su lugar. La misma suma de tres subconsultas se reutiliza en `listar_programas_crud` para decidir en el frontend si mostrar el botón Eliminar o Desactivar.

**Variación sin alternativa reversible — primera vez que el patrón aplica sin "desactivar":** `eliminar_periodo` en `04_grupos` (commit `a83cb43`, 2026-08-20). Módulos, Cohortes y Programas comparten el mismo supuesto: su flag `*_activo` es un archivado reversible **por registro** (cualquier fila individual puede desactivarse y reactivarse sin afectar a las demás). `periodos.peri_activo` no cumple ese supuesto — es un flag de "único activo institucional a la vez" (ver la sección "Flag de único activo" más abajo: `activar_periodo` desactiva TODOS los períodos y activa solo el elegido, dentro de una transacción). No existe ni tendría sentido crear un `toggle_estado_periodo` boolean por-fila solo para ofrecer una alternativa al DELETE — "desactivar" un período ya significa otra cosa en este esquema (dejar de ser el período corriente, no archivarlo). Por eso `eliminar_periodo` es el primer caso del proyecto donde el DELETE condicionado queda **sin alternativa reversible**: si está bloqueado, el mensaje explica la causa (dependientes asociados) sin sugerir ninguna acción alternativa — antes de replicar este patrón en una entidad nueva, verificar primero si su flag `*_activo` es de archivado por-registro (caso común, sí admite alternativa) o de "único activo" (este caso, no admite).

**Matiz adicional — el DELETE condicionado a veces necesita más de una verificación:** además del conteo de dependientes, `eliminar_periodo` agrega una verificación de regla de negocio específica de la entidad, ejecutada **antes** del conteo: si el período es el activo institucional (`peri_activo = 1`), el DELETE se bloquea de inmediato, **incluso si el período está vacío** (sin `gruposemestres` ni `matriculas`). Sin esta salvaguarda, un período recién creado y activado (todavía sin ningún dependiente) pasaría el chequeo de conteo limpio y podría eliminarse, dejando al sistema sin ningún período activo — un estado inconsistente que rompería cualquier lógica que dependa de "resolver el activo por defecto" (mismo patrón relacionado ya documentado en la sección "Flag de único activo" de este archivo). Lección: "verificar dependientes" no siempre es la única condición de bloqueo del patrón — cuando la entidad tiene una regla de negocio propia (como "nunca debe quedar sin un activo"), esa verificación va primero, antes incluso de tocar las tablas dependientes.

### Endpoints `listar_X` que alimentan también un modal de edición

Un endpoint `listar_X` que alimenta tanto una tabla/listado como un modal de edición debe devolver los IDs crudos de las relaciones (ej. `modu_id`, `doce_id`), no solo los valores ya resueltos por JOIN (`modu_nombre`, `doce_nombres`). Sin los IDs, el frontend no tiene con qué preseleccionar los `<select>` del modal de edición.

Caso real: `listar_modulos_grupo` en `04_grupos` solo devolvía nombres resueltos por JOIN, lo que impedía precargar los selects de Módulo/Docente al editar (corregido en el commit `f46d9d9`, 2026-08-15) — agregar `gm.modu_id` y `gm.doce_id` al SELECT resolvió el problema sin afectar el uso existente del endpoint para el listado.

### Subconsultas escalares para contar múltiples relaciones 1:N sobre la misma fila

Cuando una entidad tiene DOS O MÁS tablas dependientes independientes que se necesitan contar en el mismo SELECT (ej. cohortes tiene estudiantes Y gruposemestres), usar subconsultas escalares correlacionadas — `(SELECT COUNT(*) FROM tabla_x WHERE tabla_x.fk = t.id) AS total_x` — en vez de múltiples `LEFT JOIN` + `GROUP BY`. Un `LEFT JOIN` simultáneo a dos tablas 1:N distintas produce fan-out (producto cartesiano parcial): si una fila tiene 3 registros en una tabla y 2 en otra, cada `COUNT()` queda inflado por las filas del otro JOIN, dando conteos falsos. Con UNA sola tabla dependiente, el patrón `LEFT JOIN` + `GROUP BY` sigue siendo válido (ver `listar_modulos`, que solo cuenta `gruposmodulos`).

Ejemplo: `listar_cohortes` en `04_grupos` (commit `5611153`, 2026-08-15) cuenta `total_estudiantes` y `total_grupos` con dos subconsultas independientes, sin `GROUP BY`.

### Funciones de refresh invocadas desde onclick inline deben usar la API de DataTables directamente

Las funciones que se invocan vía `onclick` inline desde HTML generado por DataTables (ej. botones de fila) deben estar declaradas en el scope global (fuera de `$(document).ready`), porque `onclick` inline solo puede llamar funciones accesibles desde `window`. Si esa función necesita refrescar la tabla tras una acción, NO debe llamar a una función `cargarTablaX()` declarada dentro de `$(document).ready` — esa función es local a ese closure y lanza `ReferenceError` en tiempo de ejecución (el error ocurre dentro del callback `success` del AJAX, después de que el backend ya respondió éxito, por lo que el dato persiste en BD pero la UI no se actualiza). En su lugar, usar directamente `$('#tbl_X').DataTable().ajax.reload(null, false)`, que recupera la instancia ya inicializada vía el propio DOM, sin depender de ninguna variable de scope local.

Ejemplo: bug real en `toggleEstadoCohorte` (04_grupos), corregido en el mismo commit `5611153` tras detectarse en pruebas manuales — `toggleEstadoModulo` ya usaba el patrón correcto desde antes.

Variación con función auxiliar (no de refresco de tabla, pero mismo requisito de scope): `cargarCohortesPorPrograma()` en `02_estudiantes` (commit `7340d6e`, 2026-08-20) — originalmente declarada dentro de `$(document).ready` para la cascada Programa→Cohorte del modal "Completar Matrícula". Al agregar el modal "Editar Matrícula", la función global `abrirEditarMatricula()` (invocada vía `onclick` inline desde el botón de fila del DataTable) necesitaba llamarla también para precargar la cohorte del registro editado — se movió a scope global por el mismo motivo que `toggleEstadoModulo`/`toggleEstadoCohorte`. A diferencia de esos ejemplos (que solo refrescan una tabla), aquí la función global necesitaba además **encadenar una acción posterior** (preseleccionar `coho_id` una vez cargadas las opciones): se resolvió agregando un tercer parámetro `callback` opcional a `cargarCohortesPorPrograma(prog_id, selectorDestino, callback)`, invocado al final del `success` del AJAX — evita depender de `setTimeout` (que no garantiza que la respuesta ya haya llegado) para sincronizar una acción que depende de un resultado asíncrono. Los dos llamados ya existentes (`cargarCohortesPorPrograma(prog_id)`, sin argumentos adicionales) no requirieron cambios porque `selectorDestino` y `callback` tienen valores por defecto que preservan el comportamiento original.

### El indicador de completitud de una fila (color + title) puede reutilizarse en más de un botón

Cuando una fila de DataTable calcula un indicador de estado derivado de un campo del `row` (ej. `row.estado_ficha` → `colorFicha`/`tituloFicha` vía `mapaColorFicha`/`mapaTituloFicha`, ya usado para el punto de color del botón "Ficha"), ese cálculo vive una sola vez por fila dentro del mismo `render()` — cualquier otro botón de esa misma fila que necesite mostrar el mismo estado (mismo punto de color, mismo `title`) debe reutilizar las mismas variables locales (`colorFicha`, `tituloFicha`), no volver a calcularlas ni duplicar el mapa. El indicador no es exclusivo del botón que lo originó — es un dato de la fila, no del botón.

Ejemplo: commit `38e1809` (2026-08-23) — el botón "Editar" (renombrado a "📝 Datos Estudiante") en `tablaMatriculados`/`tablaAspirantes` de `est_ctrl.js` adopta el mismo `<span>` de punto de color y el mismo `title="${tituloFicha}"` que ya tenía el botón "Ficha" en la misma fila, sin duplicar `mapaColorFicha`/`mapaTituloFicha` ni tocar `calcularEstadoFicha()` (`est_mdl.php`). Antes de agregar un nuevo indicador visual a un botón de fila, revisar primero si la fila ya expone uno equivalente que pueda reutilizarse.

### Formatear fechas en español (nombre/abreviatura de mes) sin `intl` ni librería de fechas

El proyecto no tiene la extensión `intl` de PHP instalada (confirmado en el contenedor `app`, PHP 8.5.9) ni ninguna librería de fechas cargada en el frontend (`moment`/`dayjs`). Todo el formato de fecha existente antes de este cambio era numérico (`date('d/m/Y')` en PHP, valores crudos de MySQL sin formatear en JS) — no había ningún precedente de nombre/abreviatura de mes en español en el proyecto. Cuando se necesite mostrar un mes en español (nombre completo o abreviado), la solución del proyecto es un array local de 12 posiciones (`['ene', 'feb', ..., 'dic']`, indexado por `Date.getMonth()` en JS o `(int)date('n', $ts) - 1` en PHP) declarado en el punto de uso — no asumir que `Intl.DateTimeFormat('es-CO', ...)` o `IntlDateFormatter` están disponibles sin verificarlo primero.

Ejemplo: commit `7cef01a` (2026-08-23) — columna Edad de `tablaMatriculados` (`est_ctrl.js`), formato `"17 (23 sep)"`, con el array de abreviaturas declarado dentro del propio `render()` de la columna. Relevante para la Fase 2.8.F2 (PDF Hoja de Matrícula, AC-FO-09) si esa exportación necesita mostrar una fecha en español — mismo array reutilizable, sin agregar la extensión `intl` al Dockerfile.

### Dos transacciones PDO individualmente correctas no garantizan atomicidad entre sí

Envolver un guardado en `beginTransaction()`/`commit()`/`rollBack()` solo garantiza atomicidad *dentro* de esa transacción — si dos guardados relacionados (ej. estudiante + su ficha familiar) viven en dos `case` distintos de `_mdl.php`, disparados por dos llamadas `$.ajax` separadas desde el cliente, no hay ninguna garantía de que ambos se completen juntos: cada transacción individual es atómica, pero el par no lo es. Si la segunda llamada falla (red, validación, excepción) después de que la primera ya hizo `commit()`, el sistema queda en un estado parcialmente guardado sin que el servidor lo sepa ni pueda revertirlo. La señal para fusionar dos `case` en uno solo (una única transacción que cubra ambos pasos) es que un guardado a medias sea un estado de negocio inválido — no una preferencia de estilo ni una optimización de menos requests.

Ejemplo: commit `42e4175` (2026-08-23) — `guardar` (estudiantes) y `guardar_ficha` (fichas_inscripcion) en `02_estudiantes` ya usaban transacción cada uno por separado, pero como dos requests HTTP independientes no había atomicidad real entre ambos. Se fusionaron en un solo `case 'guardar_completo'`, con una única transacción que cubre el INSERT/UPDATE de `estudiantes` y el INSERT/UPDATE de `fichas_inscripcion`. Antes de replicar este patrón, verificar primero si el estado intermedio (A guardado, B no) es realmente inválido para el negocio — si no lo es (ej. dos guardados verdaderamente independientes que solo comparten pantalla por conveniencia de UI), mantenerlos separados es preferible y no amerita la fusión.

Segundo caso de uso: `insc_mdl.php` (`09_inscripcion_publica`, commit `0e098bb`, 2026-08-24) — el formulario público de inscripción tenía el mismo problema en su versión de 2 pasos: `crear_aspirante` (INSERT en `estudiantes` + INSERT parcial en `fichas_inscripcion`) y `completar_ficha` (UPDATE del resto de `fichas_inscripcion`) vivían en dos requests HTTP separados, disparados por dos pantallas distintas del formulario público. Se fusionaron en un solo `case 'crear_aspirante'`, con una única transacción que cubre el INSERT en `estudiantes` y el INSERT completo (ya no parcial) en `fichas_inscripcion` — el rediseño eliminó además el mecanismo de "retomar después" (`finc_codigotemporal`) que existía precisamente para tolerar ese estado intermedio, reforzando que dicho estado era inválido para el negocio (un aspirante a medias en un formulario público, sin sesión, es más difícil de recuperar que uno interno editado por el coordinador).

### Personalizar exportación Excel vía customize del botón excelHtml5 (DataTables Buttons)

La exportación a Excel del proyecto es 100% client-side, generada por el plugin DataTables Buttons (buttons.html5.min.js + JSZip) — no hay PhpSpreadsheet ni ninguna librería de generación de .xlsx en el backend. La opción `title` del botón `{ extend: 'excel' }` solo acepta un string plano que se convierte en UNA fila de título; no soporta múltiples líneas como filas independientes.

Para insertar contenido estructurado (múltiples filas, formato específico) antes de los datos de la tabla, usar la opción `customize` del botón: recibe el objeto `xlsx` ya generado por el plugin antes de la descarga, con acceso al XML crudo de la hoja vía `xlsx.xl.worksheets['sheet1.xml']` (un documento parseado con jQuery). Patrón usado:

1. Insertar nuevas `<row>` con `$('row', sheet).eq(0).before(...)`, usando celdas de texto plano `t="inlineStr"` (sin necesidad de mergeCells para texto que se desborda visualmente sobre columnas vacías adyacentes).
2. Reindexar TODAS las filas y celdas existentes tras la inserción — cada `<row r="N">` y cada `<c r="colN">` dentro de ella debe recalcularse (`$('row', sheet).each(...)`, extrayendo la parte de letra de columna con `.replace(/[0-9]/g, '')` y añadiendo el nuevo número de fila). Sin este paso, el archivo queda con referencias desincronizadas y Excel lo reporta como dañado.
3. Actualizar el atributo `ref` de `<dimension>` al final, con la última celda de la última fila tras la reindexación.
4. Escapar `&`, `<`, `>` en cualquier texto libre insertado (nombres de docente, módulo, etc.) antes de insertarlo como `<t>` dentro del XML.

Ejemplo: `reportes_ctrl.js` (commit `7fe1151`, 2026-08-15) — la fila de contexto (Módulo/Grupo/Docente/Programa/Período/Jornada) se dividió en 2 filas reales de la hoja usando este patrón, con los encabezados de columna arrancando en la fila 3.

### Flag de "único activo" (peri_activo, coho_activa, etc.) garantizado por transacción de aplicación, no por constraint de BD

Ningún flag de este tipo en el proyecto (peri_activo, coho_activa, modu_activo, usua_activo, grmo_activo) tiene una constraint de BD que garantice invariantes (ej. "solo uno activo a la vez" en periodos). Esa garantía vive exclusivamente en la capa de aplicación. Patrón para "solo uno activo a la vez": dentro de una transacción PDO (beginTransaction/commit/rollBack), primero UPDATE tabla SET flag = 0 (todos), luego UPDATE tabla SET flag = 1 WHERE id = ? (el elegido).

Patrón relacionado para "resolver el activo por defecto": un endpoint que depende de "el X actual" (ej. listar_docentes necesita saber el período actual para contar grupos) debe aceptar el id como parámetro opcional y, si no viene, resolverlo con una query separada (SELECT id FROM tabla WHERE flag_activo = 1 LIMIT 1) antes del SELECT principal — no asumir que siempre habrá un valor "activo" garantizado por esquema.

Ejemplo: `activar_periodo` en `04_grupos` y el parámetro `peri_id` opcional en `listar` (`03_docentes`), commit `7eba870` (2026-08-15).

### Tabla de configuración global como fila única con CHECK constraint (invariante garantizada por esquema, no por convención de aplicación)

A diferencia del patrón anterior (flags "único activo" cuya invariante vive solo en la capa de aplicación), cuando una tabla existe para guardar un único conjunto de valores globales (ej. parámetros institucionales), la invariante "esta tabla nunca tiene más de una fila" se garantiza directamente en el esquema, no por convención del código PHP:

```sql
CREATE TABLE configuracion (
  config_id  INT(5)  NOT NULL DEFAULT 1,
  -- columnas tipadas del valor global ...
  PRIMARY KEY (config_id),
  CONSTRAINT chk_configuracion_fila_unica CHECK (config_id = 1)
) ...
```

El `CHECK (config_id = 1)` hace imposible un `INSERT` con cualquier otro valor de PK — MySQL rechaza la fila antes de que el código PHP tenga oportunidad de introducir un bug que inserte una segunda fila por accidente. El backend nunca hace `INSERT`, solo `SELECT`/`UPDATE ... WHERE config_id = 1` (id hardcodeado, no recibido del cliente) — primer caso del proyecto de un guardado sobre un registro fijo conocido de antemano, sin PK variable.

Ejemplo: tabla `configuracion` en `08_admin` (commit `25530cc`, 2026-08-23) — `matr_numero_inicial`, `director_nombre`, `secretario_nombre`, seed inicial con `config_id=1`. Antes de replicar este patrón para un nuevo conjunto de valores globales, evaluar primero si conviene una tabla nueva de fila única (este patrón, mejor cuando los valores son pocos y fijos) o agregar columnas a esta misma tabla `configuracion` si el nuevo valor es igual de global e institucional.

### Asignación segura de un contador institucional bajo concurrencia: `SELECT ... FOR UPDATE` sobre la fila de configuración, no sobre la tabla completa

Cuando un valor se calcula como "el siguiente número" a partir de un contador global (ej. un número de matrícula, folio o acta institucional) y dos usuarios podrían disparar la asignación casi simultáneamente, el cálculo (`MAX(...)`+1 u otro) y la escritura deben protegerse contra condición de carrera — sin este cuidado, dos requests concurrentes pueden leer el mismo `MAX` antes de que cualquiera de los dos escriba, y terminar asignando el mismo número dos veces (violando además cualquier `UNIQUE KEY` sobre esa columna, o peor, fallando en silencio si no la hay). Patrón:

1. Dentro de una transacción PDO (`beginTransaction`/`commit`/`rollBack`), lockear con `SELECT ... FOR UPDATE` la fila que sirve de "ancla" del cálculo — no la tabla completa cuyo `MAX()` se está calculando. Si existe una tabla de configuración de fila única (ver patrón anterior), esa fila es el candidato natural: ya es una sola fila, barata de lockear, y cualquier segunda transacción concurrente que intente el mismo `SELECT ... FOR UPDATE` queda bloqueada hasta que la primera haga `commit()`/`rollBack()`.
2. Dentro de la misma transacción, calcular el siguiente valor (ej. `SELECT MAX(columna) FROM tabla_destino`, comparado con el valor base de configuración) y persistirlo con un `UPDATE` normal sobre la fila destino real.
3. Solo asignar si el valor aún no existe (`IS NULL`) — una asignación ya hecha nunca se recalcula ni se sobreescribe en descargas/ejecuciones posteriores.

Ejemplo: `pdf_hoja_matricula.php` (`06_reportes`, commit `ffb6a6c`, 2026-08-23) — `matr_numero` se asigna la primera vez que se genera el PDF de un estudiante matriculado, lockeando `configuracion` (`SELECT matr_numero_inicial FROM configuracion WHERE config_id = 1 FOR UPDATE`) antes de calcular `GREATEST(MAX(matriculas.matr_numero)+1, matr_numero_inicial)` y persistirlo — primer uso de `SELECT ... FOR UPDATE` en el proyecto. Antes de replicar este patrón, confirmar que el valor realmente necesita esta protección — para un valor editado manualmente por un solo coordinador a la vez (como la mayoría de campos del proyecto), la transacción PDO simple ya documentada en otras partes de este archivo sigue siendo suficiente.

### Resolver una relación 1:N no anticipada con `ORDER BY <pk> DESC LIMIT 1` cuando no hay desambiguación explícita

Cuando una query necesita "el" registro relacionado de una tabla que en realidad es 1:N respecto a la entidad consultada (ej. `matriculas` es 1:N por `estu_id` — un estudiante puede tener más de una fila a lo largo del tiempo), y ni el esquema ni la lógica de negocio ofrecen un criterio explícito de cuál usar, el criterio por defecto del proyecto es tomar el más reciente: `ORDER BY <pk_autoincremental> DESC LIMIT 1` (o el `JOIN` equivalente con esa cláusula aplicada a toda la consulta de una sola fila). Esto evita tanto un resultado no determinista (dejar que MySQL devuelva cualquier fila coincidente sin orden explícito) como una duplicación silenciosa de la fila principal cuando se usa `fetch()` esperando una sola fila.

Ejemplo: `case 'obtener_completo'` en `est_mdl.php` y `pdf_hoja_matricula.php` (ambos del commit `ffb6a6c`, 2026-08-23) — ninguno de los dos fue diseñado originalmente para lidiar con más de una matrícula por estudiante; al descubrirse la relación 1:N durante la implementación, ambos usan `LEFT`/`INNER JOIN matriculas m ... ORDER BY m.matr_id DESC LIMIT 1` para quedarse con la matrícula más reciente. Antes de replicar este patrón, evaluar si existe un criterio de negocio más específico que "el más reciente" (ej. "el activo", "el del período actual") — este patrón es el default cuando no lo hay, no una regla absoluta.

### Preferir bind posicional (?) sobre placeholder nombrado reutilizado, por EMULATE_PREPARES=false

`app/00_connect/pdo.php` configura `PDO::ATTR_EMULATE_PREPARES => false` (prepares nativos de MySQL, no emulados por PHP). Reutilizar el mismo placeholder nombrado (ej. `:peri_id`) dos veces en una misma query es un caso poco confiable con prepares nativos. Cuando una query necesita el mismo valor en dos puntos distintos del SQL (ej. una subconsulta de conteo y otra de código legible), usar placeholders posicionales (`?`) y pasar el valor repetido en el array de `execute([$valor, $valor])` — consistente además con el estilo ya usado en la mayoría de queries del proyecto.

Ejemplo: `doc_mdl.php`, case `listar` (commit `7eba870`, 2026-08-15).

### Módulo con CRUD exclusivo de Admin: replicar 08_admin, no el patrón multi-rol

Cuando un módulo nuevo es exclusivo de un solo rol (ej. role_id === 1), replicar el patrón de 08_admin: guard único if ($role_id !== X) en la vista con redirect, y el mismo guard repetido en cada case del _mdl.php — no el patrón in_array($role_id, [1,2]) usado en módulos multi-rol como 04_grupos/05_calificaciones. La vista incluye el navbar compartido sin ningún condicional adicional (el navbar mismo decide qué enlaces mostrar por rol).

Ejemplo: 10_ayudas (commit `f801bdb`, 2026-08-16), replicando 08_admin.

### Endpoint de lectura abierto a todos los roles autenticados (sin chequeo de role_id)

Cuando un dato debe ser legible por cualquier rol pero editable solo por uno (ej. contenido de ayuda: todos lo leen, solo Admin lo edita), el endpoint de lectura usa únicamente isset($_SESSION['usua_id']) como guard, sin chequeo de role_id — distinto de los patrones habituales del proyecto (in_array($role_id, [1,2]) o role_id !== 1). No aplicar este patrón por descuido: documentarlo inline en el código como decisión deliberada, no como guard olvidado.

Ejemplo: listar_por_modulo en ayud_mdl.php (commit `fc9b56b`, 2026-08-16) — primer endpoint del proyecto con este patrón.

### Componente offcanvas de Bootstrap para paneles deslizables compartidos

Para contenido que debe aparecer como panel deslizable disponible desde cualquier página (ej. ayuda contextual), usar el componente offcanvas de Bootstrap 5.3 (ya incluido en bootstrap.bundle.min.js, sin script adicional) en vez de un modal — el offcanvas no bloquea la interacción con el resto de la página. El HTML del offcanvas puede vivir en un archivo compartido (ej. 00_files/navbar.php) e incluirse en cada vista, mientras que el JS que puebla su contenido debe ser un archivo aparte (00_files/ayuda_sidebar.js) para reutilizarse sin duplicar lógica. Cada vista que lo usa declara una variable global de contexto (ej. MODULO_ACTUAL) ANTES de incluir el script compartido, para que este sepa qué contenido cargar.

Ejemplo: #offcanvasAyuda + ayuda_sidebar.js (commit `8947710`, 2026-08-16) — primer uso de offcanvas en el proyecto.

### Nunca confiar en el estado del cliente para decisiones que afectan seguridad/acceso

Cuando el frontend necesita saber si una entidad tiene acceso de login (ej. si un estudiante ya tiene usua_id) para decidir si mostrar una funcionalidad sensible (ej. cambiar clave), el backend debe re-verificar esa condición con una consulta propia — nunca confiar en un hidden field o cualquier dato enviado por el cliente para tomar la decisión real. El frontend puede usar esos valores solo para decidir QUÉ MOSTRAR visualmente; la decisión de QUÉ EJECUTAR debe validarse siempre del lado del servidor, con su propia fuente de verdad.

Ejemplo: en est_mdl.php (case guardar, commit `3d59f06`, 2026-08-16), el cambio de clave de un estudiante matriculado se permite solo tras un SELECT usua_id FROM estudiantes WHERE estu_id = ? ejecutado en el servidor — el hidden field #npt_estu_usua_id_actual del frontend solo se usa para decidir si mostrar el bloque de UI, nunca para autorizar el cambio en sí.

### Resolución de "entidad objetivo" según rol: propio (de sesión) vs. ajeno (explícito, con verificación de pertenencia)

Cuando un mismo endpoint debe servir tanto a un rol que solo puede ver/operar sobre **su propia** entidad (ej. un estudiante consultando sus propias notas) como a un rol que puede operar sobre **cualquier** entidad de ese tipo (ej. un coordinador consultando a cualquier estudiante), la resolución de "sobre cuál entidad estoy operando" no puede ser un simple `$_POST['estu_id']` uniforme para todos los roles — eso permitiría que el rol restringido mintiera sobre su propio ID. Patrón: una función helper centralizada (ej. `resolverEstuIdObjetivo()`) que decide la fuente del ID **según el rol en sesión**, no según lo que llegue en la petición:

```php
function resolverEstuIdObjetivo(): ?int {
    $role_id = (int)($_SESSION['role_id'] ?? 0);
    if ($role_id === 4) {
        // Rol restringido: SIEMPRE su propio ID de sesión, sin importar
        // qué venga en la petición (POST/GET) — se ignora por completo.
        return isset($_SESSION['estu_id']) ? (int)$_SESSION['estu_id'] : null;
    }
    if (in_array($role_id, [1, 2], true)) {
        // Rol con alcance amplio: el ID llega explícito en la petición.
        $estu_id = (int)($_POST['estu_id'] ?? 0);
        return $estu_id > 0 ? $estu_id : null;
    }
    return null;
}
```

Cuando además la operación recibe un identificador de un registro relacionado (ej. `matr_id`, `grmo_id`), **no basta con resolver el `estu_id` objetivo** — hay que verificar explícitamente que ese registro relacionado pertenezca al `estu_id` resuelto (`SELECT ... WHERE matr_id = ? AND estu_id = ?`), y responder con el mismo error genérico tanto si el registro no existe como si pertenece a otro estudiante (mismo criterio ya documentado para `pdf_boletin.php` en "Decisiones arquitectónicas activas" — no distinguir las dos causas). Sin esta segunda verificación, un coordinador (o incluso un estudiante manipulando la petición) podría pasar un `matr_id`/`grmo_id` válido pero ajeno al `estu_id` resuelto y acceder a datos de otro estudiante.

Ejemplo: `resolverEstuIdObjetivo()` en `06_reportes/reportes_mdl.php` (commit `b3a0296`, 2026-08-30), reutilizada tal cual en `pdf_boletin.php` (commit `ef429bd`, mismo día) — los cases `mis_periodos` y `detalle_periodo` verifican además que el `matr_id` recibido pertenezca al `estu_id` ya resuelto (`SELECT prog_id FROM matriculas WHERE matr_id = ? AND estu_id = ?`) antes de continuar. Antes de replicar este patrón en un endpoint nuevo con la misma forma (rol restringido a lo propio + rol con alcance amplio sobre cualquier entidad), evaluar si además hace falta la verificación de pertenencia del registro relacionado — no siempre aplica (depende de si el endpoint recibe algún ID adicional más allá del de la propia entidad).

### Campo de contraseña opcional en edición: vacío no cambia el hash

Cuando un modal de edición incluye un campo de contraseña para una entidad que ya tiene credenciales (`usuarios.usua_passwordhash`), el campo se deja vacío por defecto (nunca precargado con el hash ni con ningún valor) y se trata como opcional: si el usuario no escribe nada, el `UPDATE` no toca `usua_passwordhash`; si escribe un valor, se genera un hash nuevo con `password_hash($valor, PASSWORD_BCRYPT)` y se incluye en el `UPDATE`. La decisión se toma en PHP con un simple `if ($password !== '')`, nunca dejando que un valor vacío se hashee y sobreescriba la contraseña existente. Sin validación de longitud mínima en ninguno de los dos usos conocidos — mismo criterio en ambos, no una omisión de uno solo.

Dos casos de uso conocidos, mismo patrón exacto:
- `admin_mdl.php`, case `editar` (`08_admin`) — primer uso del patrón en el proyecto. Placeholder del input: "Dejar vacío para no cambiar".
- `doc_mdl.php`, case `guardar` rama edición (`03_docentes`, commit `35c312a`, 2026-08-25) — el campo (`#bloque_password` en `#mdl_docente`) estaba oculto por completo en modo edición hasta este commit; ahora se muestra condicionado a que el docente tenga `usua_id` (ver deuda técnica del `INNER JOIN` en `case 'obtener'`, más abajo en "Deuda técnica / pendiente antes de producción"), con el mismo placeholder "Dejar vacío para no cambiar" y el mismo `if ($usua_password !== '')` combinado en el mismo `UPDATE` que ya actualizaba `usua_activo`.

### Registro de "último acceso": guardar el valor anterior en sesión antes de sobreescribir

Cuando una columna de auditoría tipo timestamp (ej. `usua_ultimo_acceso`) se actualiza en cada ocurrencia de una operación (ej. cada login) y ese mismo valor debe mostrarse al usuario como referencia histórica ("tu acceso anterior fue..."), el orden de las operaciones importa: hay que leer y guardar el valor ANTES de ejecutar el `UPDATE` que lo sobreescribe. Si se lee después (o si el `UPDATE` corre primero), el dato mostrado durante toda la sesión sería siempre el de la operación actual — un valor que no tiene ninguna utilidad como referencia, porque coincidiría con el momento presente en vez de reflejar el acceso previo.

Ejemplo: `login_mdl.php` (commit `ac96f07`, 2026-08-25) — en el único flujo de autenticación del proyecto (sirve a los 4 roles sin duplicar lógica), justo después de `password_verify()` exitoso y antes de la lógica condicional de `doce_id`/`estu_id` que ya existía, se guarda `$_SESSION['usua_ultimo_acceso_anterior'] = $usuario['usua_ultimo_acceso']` (el valor leído en el mismo `SELECT` que validó la contraseña, es decir, el estado de la columna ANTES de este login) y solo después se ejecuta `UPDATE usuarios SET usua_ultimo_acceso = NOW() WHERE usua_id = ?`. El navbar y las tablas de listado (`08_admin`, `03_docentes`, `02_estudiantes`) muestran ese valor de sesión (o el resultado ya persistido en BD para otros usuarios, vía `formatearUltimoAcceso()`), nunca el `NOW()` recién escrito por el propio login en curso.

### 00_files/helpers.php — funciones PHP compartidas del proyecto

Antes del commit `ac96f07` (2026-08-25), `00_files/` solo contenía componentes ya documentados en "Estructura de módulos" (`navbar.php`, `estilos.css`, `ayuda_sidebar.js`, además de los estáticos favicon/robots.txt/.htaccess) — ninguno era un archivo de funciones PHP reutilizables. `helpers.php` es el primero de este tipo: `require_once '../00_files/helpers.php'` (o `require_once 'helpers.php'` desde dentro del propio `00_files/`, ej. `navbar.php`) donde se necesite una función compartida entre módulos, en vez de declarar la misma función localmente en cada `_mdl.php`/`_view.php` que la necesite (a diferencia de `calcularEstadoFicha()` en `est_mdl.php`, que sigue siendo local a ese archivo porque ningún otro módulo la necesita).

Primera función: `formatearUltimoAcceso($ultimoAcceso, $fechaCreacion)` — usada en `login_mdl.php` (indirectamente, vía las vistas que leen la sesión), `navbar.php`, `calificaciones_view.php`, `reportes_view.php`, y en los cases `listar` de `admin_mdl.php`/`doc_mdl.php`/`est_mdl.php` (`listar_matriculados`). Este archivo es el lugar natural para cualquier función futura de este tipo (formato compartido, validación compartida, etc.) — antes de duplicar una función en un nuevo `_mdl.php`, verificar primero si ya existe (o si debería vivir) en `helpers.php`.

Segunda función: `sincronizarEmailUsuario(PDO $pdo, ?int $usua_id, string $nuevoEmail): void` (commit `03e36fc`, 2026-09-02) — extraída del patrón ya usado inline en `doc_mdl.php` (case `'guardar'`, commit `b6cc503`): valida unicidad de `usuarios.usua_email` (excluyendo el propio `usua_id`) y actualiza. No abre ni cierra transacción propia — siempre corre dentro de la transacción PDO del llamador. Lanza `Exception` (no `PDOException`) en caso de colisión, para que el llamador la capture con su propio mensaje de error en vez de asumir un formato de respuesta fijo. Usada por `guardar_completo` (fix del bug documentado abajo) y por `case 'aprobar_actualizacion'` (`est_mdl.php`, `02_estudiantes`).

### Validación de unicidad server-side antes de UPDATE/INSERT (SELECT previo, no catch genérico)

Cuando un campo debe ser único dentro de su propia tabla (no contra una tabla relacionada — para eso ver el patrón siguiente) y ese campo puede editarse desde la UI, no basta con dejar que el `UNIQUE KEY` de la BD rechace el duplicado y caiga al `catch (PDOException $e)` genérico: el usuario recibiría un mensaje de error inespecífico sin saber cuál fue la causa real. Patrón: antes del `UPDATE`/`INSERT`, ejecutar un `SELECT <pk> FROM tabla WHERE campo_unico = ?` (agregando `AND <pk> != ?` en la rama editar, para no autobloquear el registro contra sí mismo) — si encuentra una fila, responder con el envelope de error y un mensaje específico, sin llegar a ejecutar la escritura ni depender del catch.

Cuatro casos de uso conocidos, mismo patrón exacto:
- `doce_sigla` en el case `guardar` de `doc_mdl.php` (crear: `SELECT doce_id FROM docentes WHERE doce_sigla = ?`; editar: agrega `AND doce_id != ?`).
- `matr_numero` en el case `editar_matricula` de `est_mdl.php` (commit `59775e4`, 2026-08-24): `SELECT matr_id FROM matriculas WHERE matr_numero = ? AND matr_id != ?`, solo cuando `matr_numero` no es `null` — responde "El número de matrícula ya está asignado a otro estudiante." antes del `UPDATE`.
- `usua_email` en la rama de edición del case `guardar` de `doc_mdl.php` (`03_docentes`, commit `b6cc503`, 2026-08-28): `SELECT usua_id FROM usuarios WHERE usua_email = ? AND usua_id != ?` — habilita la edición del correo (antes bloqueado con `readonly` desde `doc_ctrl.js`) sin arriesgar colisión con el correo de otro usuario, dado que ese campo es también el identificador de login para los 4 roles.
- `doce_cedula` en el case `guardar` de `doc_mdl.php` (`03_docentes`, commit `7ba02bc`, 2026-08-28): `SELECT doce_id FROM docentes WHERE doce_cedula = ?` (editar: agrega `AND doce_id != ?`), ejecutado solo cuando `doce_cedula` no llega vacío — mismo criterio de "campo opcional" ya usado para `matr_numero`, no de "campo obligatorio" como `doce_sigla`/`usua_email`.

**Variante cuando el SELECT previo sería redundante — capturar `SQLSTATE 23000` en el catch:** cuando la unicidad no depende de un valor que el usuario escribe en un formulario sino de un cálculo que ya se protege de otra forma (ej. una asignación automática de contador dentro de una transacción con `SELECT ... FOR UPDATE`, ver "Asignación segura de un contador institucional bajo concurrencia" más abajo), un `SELECT` previo de unicidad sería una verificación redundante con el propio cálculo. Ahí el patrón alternativo es capturar la excepción y verificar `$e->getCode() === '23000'` (SQLSTATE de violación de constraint `UNIQUE`/`CHECK` en MySQL vía PDO) para dar un mensaje específico solo en ese caso, dejando el mensaje genérico para el resto de errores — sin repetir la lógica de cálculo en un SELECT aparte.

Ejemplo: `pdf_hoja_matricula.php` (commit `59775e4`, 2026-08-24) — el `catch` de la asignación automática de `matr_numero` verifica `$e instanceof PDOException && $e->getCode() === '23000'` para devolver "El número de matrícula ya fue asignado, intente nuevamente." en vez del mensaje genérico, cubriendo el caso de una colisión real (dos asignaciones automáticas concurrentes, o un valor recién asignado manualmente vía el campo de "Editar Matrícula") pese a que el `SELECT ... FOR UPDATE` ya protege el cálculo contra condición de carrera.

### Campo opcional con UNIQUE KEY: guardar NULL, nunca cadena vacía

Cuando un campo es opcional (el usuario puede dejarlo en blanco) pero tiene una `UNIQUE KEY` en su columna, el valor vacío debe persistirse como `NULL`, nunca como cadena vacía (`''`). MySQL permite múltiples filas con `NULL` bajo una `UNIQUE KEY` (cada `NULL` se trata como distinto de cualquier otro), pero trataría dos cadenas vacías como el mismo valor y rechazaría la segunda fila que se guardara así — convirtiendo un campo opcional en un campo que solo el primer registro puede dejar en blanco. Patrón en PHP: `$valor !== '' ? $valor : null` al armar los parámetros del `execute()`, tanto en el `INSERT` como en el `UPDATE`.

Ejemplo: `doce_cedula` en `doc_mdl.php` (`03_docentes`, commit `7ba02bc`, 2026-08-28) — campo opcional cableado sin volverlo obligatorio (ya existían 5 docentes con el campo vacío antes de este commit); si se hubiera guardado `''` en vez de `null`, el segundo docente guardado sin cédula habría chocado con el primero contra `uq_doce_cedula`.

### Validación de unicidad contra dos tablas relacionadas antes de guardar (formato + unicidad cruzada)

Cuando un campo de una entidad (ej. `estudiantes.estu_email`) debe ser único a nivel de negocio pero también podría colisionar con un campo equivalente en una tabla relacionada de distinta entidad (ej. `usuarios.usua_email`), validar unicidad contra AMBAS tablas antes de persistir — no solo contra la tabla que se está editando. Patrón:

1. Validar formato primero (`filter_var($valor, FILTER_VALIDATE_EMAIL)`), devolviendo el envelope de error de inmediato si falla — antes de tocar la BD.
2. `SELECT` de unicidad contra la tabla propia (excluyendo el propio ID en la rama editar).
3. `SELECT` de unicidad contra la tabla relacionada — si la entidad ya tiene una fila vinculada (ej. el estudiante ya tiene `usua_id`), excluir también ese ID relacionado para no autobloquear el guardado de un valor que la entidad ya tenía.
4. Solo si ambos chequeos pasan, proceder con el guardado.

Ejemplo: case `guardar` (crear y editar) en `est_mdl.php` y case `registrar` en `insc_mdl.php` (`09_inscripcion_publica`), commit `d62dde6` (2026-08-19) — valida `estu_email` contra `estudiantes.estu_email` y `usuarios.usua_email` antes de crear/editar un aspirante, excluyendo el propio `usua_id` en la rama editar.

**Segundo caso — omitir el paso 3 es un error real, no solo teórico:** `case 'aprobar_actualizacion'` (`est_mdl.php`, `02_estudiantes`, commit `03e36fc`, 2026-09-02) inicialmente solo llamaba a `sincronizarEmailUsuario()` (helper nuevo, ver "00_files/helpers.php" — valida el paso 3, contra `usuarios.usua_email`) sin ejecutar el paso 2 (contra `estudiantes.estu_email`, que tiene su propia `UNIQUE KEY uq_estu_email`, independiente de la de `usuarios`). Detectado en pruebas con `curl`: una colisión contra `estudiantes.estu_email` lanzaba un `PDOException` crudo de MySQL, capturado por el catch genérico del `case`, perdiendo el mensaje de error específico que sí existía para la colisión contra `usuarios`. Corregido agregando el `SELECT` de unicidad contra `estudiantes` explícitamente, antes del `UPDATE`. **Lección generalizable:** al escribir un `UPDATE`/`INSERT` que toque un campo con `UNIQUE KEY` propia, validar esa unicidad explícitamente en el propio `case` — no asumir que una función auxiliar reutilizada (aunque sí valide la unicidad de una tabla relacionada) cubre también la unicidad de la tabla que se está escribiendo directamente.

### Remover un campo solo de la UI (dejando columna + backend intactos) cuando pertenece a una etapa fuera del alcance actual

Cuando un campo de BD ya tiene su columna y su wiring de backend (`_mdl.php`) funcionando, pero pertenece a una etapa del proceso que todavía no está en el alcance del sprint/fase actual (ej. datos de certificación antes de que exista el flujo de certificación), remover el campo solo de la UI (`_view.php`/`_ctrl.js`) es preferible a eliminar la columna o dejar código muerto sin explicar por qué. La columna sigue recibiendo `NULL`/vacío en cada guardado (el backend no cambia), y el campo queda listo para reactivarse con un cambio de alcance mínimo — solo HTML + JS, sin tocar BD ni PHP — el día en que esa etapa entre en alcance. La condición para que esto valga la pena (en vez de eliminar todo el feature) es que la remoción se documente explícitamente en el commit que la hace, con la razón y la expectativa de reactivación — sin esa nota, un campo sin UI es indistinguible de un descuido o código muerto real.

Ejemplo: `matr_folio`/`matr_numero` en `02_estudiantes` — removidos de la UI de "Completar Matrícula" en el commit `29b6ca6` (2026-08-07) con la decisión explícita registrada en el CHANGELOG ("se mantienen en est_mdl.php y en la tabla `matriculas` como campos opcionales, sin usarse desde la UI... listos para reactivarse sin cambios de backend/BD si se retoman en el futuro"). La Fase E (commit `afdcfc1`, 2026-08-23) confirmó que la estrategia funcionó: reactivar `matr_folio` fue solo agregar el input en ambos modales y el campo en el payload JS, sin tocar el `case 'matricular'` para ese campo específico — el backend ya lo esperaba desde 16 días antes.

**Variante — eliminar el mecanismo por completo, no solo de la UI, cuando queda obsoleto por un cambio de diseño más amplio:** el patrón de arriba asume que la etapa que usa el campo va a llegar eventualmente (solo está fuera del alcance actual). Cuando en cambio un mecanismo completo (columna + lógica de generación/consumo) deja de tener sentido porque el diseño que lo motivó fue reemplazado —no pospuesto—, eliminarlo del todo (columna de BD, `_mdl.php`, `_ctrl.js`, `_view.php`) es preferible a dejarlo sin usar. Un campo sin UI pero con backend vivo comunica "listo para reactivarse pronto"; dejar un mecanismo completo sin usar cuando en realidad nunca va a reactivarse es indistinguible de código muerto real, el mismo riesgo que motiva remover código sin llamadores (ver "elimina código muerto tras el plan de unificación de ficha de estudiante", commit `5de9a9e`). Criterio para elegir: si hay expectativa concreta de que la etapa pendiente llegue con el mismo diseño, dejar el backend listo (patrón de arriba); si el mecanismo completo fue reemplazado por un diseño distinto que ya no lo necesita, eliminarlo del todo.

Ejemplo: `finc_codigotemporal` en `fichas_inscripcion` — columna + `UNIQUE KEY` + `generarCodigoTemporal()` (con su lógica de reintento contra duplicado) existían como puente para que un aspirante retomara un formulario público de 2 pasos sin necesidad de cuenta. Al rediseñarse ese formulario como una sola pantalla con un solo guardado transaccional (commit `0e098bb`, 2026-08-24), el mecanismo de "retomar después" dejó de tener sentido por completo (no hay un paso intermedio que retomar) — se eliminó la columna, la constraint y toda la lógica asociada, en vez de dejarla sin usar "por si acaso".

### Modo "solo revisión" reutilizando un modal existente, en vez de crear uno nuevo

Cuando una funcionalidad nueva necesita mostrar un formulario ya existente en un estado de solo lectura/revisión (ej. una propuesta de cambio pendiente de aprobación), reutilizar el mismo modal de edición en vez de duplicar su HTML en un modal aparte — evita mantener dos copias del mismo formulario, con el mismo riesgo de divergencia silenciosa ya documentado en "Formularios duplicados en dos vistas deben mantener su disposición de campos sincronizada". Patrón:

1. Una función que puebla el modal en modo revisión (ej. `poblarModalRevision()`) carga primero los valores VIGENTES exactamente igual que la función de edición normal, y luego los sobrescribe con los valores PROPUESTOS cuando existan, marcando cada campo sobrescrito con una clase CSS distinta de cualquier otro indicador visual ya usado en el modal (ej. `.campo-actualizado`, amarillo, distinta de `.campo-lleno`, verde).
2. Deshabilita todos los `input`/`select`/`textarea` del formulario (revisión = solo lectura, no edición) y oculta cualquier botón/bloque que no aplique en este modo (Guardar, subir foto, cambiar clave, etc.), mostrando en su lugar los botones específicos del modo revisión (ej. Aprobar/Descartar).
3. Una función de reset (ej. `restaurarModoNormalEstudiante()`) revierte todo lo anterior — se llama tanto al cerrar el modal (`hidden.bs.modal`) como, defensivamente, al inicio de la función de apertura en modo normal, para que abrir el modal en modo edición después de haber estado en modo revisión nunca herede campos deshabilitados ni botones equivocados.

Ejemplo: `#mdl_estudiante` en `02_estudiantes` (commit `70c45ba`, 2026-09-03) — reutilizado para el modo de revisión de una solicitud de actualización de datos (Fase 4 de 5 del feature "Link de actualización de datos de estudiantes"), en vez de crear un modal nuevo que duplicara las 5 secciones ya existentes.

---

## Antipatrones a evitar

### ❌ Interpolación directa en SQL

```php
// PROHIBIDO
$sql = "SELECT * FROM calificaciones WHERE estu_id = $_GET[estu_id]";
```

Siempre usar `?` o `:param` y pasar valores en `execute()`.

### ❌ Vistas sin verificación de sesión

```php
// PROHIBIDO — archivos .html sin session_start() ni check de rol
```

Usar `.php` para todas las vistas. Incluir `check_session.php` al inicio de cada una.

### ❌ Lógica de calificaciones en el cliente

```js
// PROHIBIDO — calcular nota definitiva en JS y enviar el resultado
let definitiva = (n1*0.2) + (n2*0.2) + (n3*0.2) + (n4*0.4);
$.ajax({ data: { cali_definitiva: definitiva } }); // el servidor persiste lo que recibe
```

PHP recalcula siempre la nota definitiva antes de persistir. El JS puede mostrarla para UX pero el servidor no confía en ese valor.

### ❌ N3 con supletorio

```php
// PROHIBIDO — N3 nunca tiene supletorio
$sup_n3 = $_POST['sup_nota3']; // Este campo no existe
```

El campo `cali_sup_n3` no existe en la BD. La columna `cali_n3` nunca tiene supletorio (regla institucional).

### ❌ async: false en AJAX

```js
// PROHIBIDO — bloquea el hilo del navegador
$.ajax({ async: false, url: 'mdl.php?accion=xxx' });
```

Usar `.then()` para encadenar llamadas dependientes.

### ❌ Respuestas sin envelope

```php
// PROHIBIDO — payload crudo sin estructura
echo json_encode($rows); // cliente no puede distinguir error de resultado vacío
```

Siempre usar `['status' => 'ok', 'data' => $rows]`.

### ❌ Contraseñas en texto plano

```php
// PROHIBIDO
INSERT INTO usuarios SET usua_password = '$_POST[password]'

// CORRECTO
$hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
// luego INSERT con $hash en execute()
```

### ❌ Copy-paste de bloques de validación

Extraer la validación a una función reutilizable `validarFormulario()` en lugar de repetir el mismo bloque en cada handler de botón.

### ❌ Tablas en mayúscula en el código PHP

```php
// EVITAR — puede causar problemas de compatibilidad en Linux
$sql = "SELECT * FROM ESTUDIANTES";

// CORRECTO — minúsculas consistentes con el esquema
$sql = "SELECT * FROM estudiantes";
```

### ❌ colspan mezclado con celdas físicas en tablas para Dompdf

```php
// PROHIBIDO — mezclar colspan con <td> sueltos en la misma tabla dentro de contenido para Dompdf
echo "<tr><td colspan='2'>Nombre</td><td>Valor</td></tr>";
echo "<tr><td>Campo</td><td>Campo</td><td>Campo</td></tr>"; // incluso si la suma de columnas coincide
```

Dompdf falla con "Frame not found in cellmap" aunque el total de columnas por fila sea matemáticamente correcto. Usar celdas físicas vacías (`<td></td>`) en vez de `colspan` para mantener el mismo número de `<td>` en cada fila de la tabla.

### ❌ display:table-cell anidado dentro de un `<td>` real (Dompdf)

```html
<!-- PROHIBIDO -->
<td>
  <div style="display:table-cell">...</div>
</td>
```

Rompe Dompdf de la misma forma que el antipatrón anterior. `display:table-cell` solo es válido como hijo directo de una fila (`<tr>`) de una tabla real — nunca anidado dentro de un `<td>` que ya cumple esa función.

### ❌ imagedestroy() en PHP 8.1+

```php
// PROHIBIDO — deprecada y sin efecto desde PHP 8.0
$img = imagecreatefromjpeg($ruta);
// ... procesamiento con GD ...
imagedestroy($img);
```

El warning de deprecación se imprime antes del `json_encode()` si `display_errors` está activo, rompiendo la respuesta JSON del envelope. GD libera la memoria automáticamente desde PHP 8.0 — omitir la llamada.

### ❌ SELECT DISTINCT combinado con ORDER BY sobre columna ausente del SELECT (MySQL 8 ONLY_FULL_GROUP_BY)

```php
// PROHIBIDO — MySQL 8 rechaza esta combinación bajo ONLY_FULL_GROUP_BY (SQLSTATE 3065)
$sql = "SELECT DISTINCT gm.grmo_id, modu_nombre
        FROM gruposmodulos gm ...
        ORDER BY modu_orden"; // modu_orden no está en el SELECT
```

Si cada fila ya es única por su propia clave (ej. `gm.grmo_id`), el `DISTINCT` es redundante — quitarlo en vez de agregar la columna del `ORDER BY` al `SELECT`. Mismo riesgo silencioso que `imagedestroy()`/`curl_close()`: la excepción PDO rompe el `json_encode()` del envelope, y sin el detalle del error visible (catch genérico), el frontend recibe una respuesta vacía o inválida sin ningún aviso.

Ejemplo: case `mis_modulos` en `06_reportes/reportes_mdl.php` — bug preexistente (no introducido en el commit que lo corrigió), corregido en `35d6672` (2026-08-19). Dejaba el selector de módulos del estudiante vacío sin ningún error visible en pantalla. **Nota (2026-09-03, commit `6abbdae`):** el propio `case` (junto con `mis_notas`, mismo archivo) fue eliminado por completo como código muerto sin ningún llamador en todo el proyecto — el ejemplo de este antipatrón permanece válido como lección histórica, pero ya no corresponde a un endpoint vigente.

### ❌ Asumir que Dompdf puede leer cualquier archivo local

```php
// PROHIBIDO — sin configurar chroot, Dompdf solo lee dentro de su propio directorio de librería (vendor/)
$dompdf = new Dompdf();
$dompdf->loadHtml($html); // <img src="/ruta/absoluta/fuera/de/vendor/foto.jpg"> falla en silencio, sin excepción
```

Por defecto Dompdf restringe la lectura de archivos locales al chroot de su propia carpeta (`vendor/`). Configurar `setChroot()` a la raíz del proyecto (o al directorio que contiene los archivos a leer) antes de renderizar HTML con rutas de imagen fuera de `vendor/`.

### ❌ Todo campo "activo" que se muestre en un KPI debe actualizarse desde TODOS los puntos que cambian el estado de esa entidad

```php
// PROHIBIDO — actualiza solo un campo "espejo" del estado, dejando el otro desincronizado
$stmtU = $pdo->prepare("UPDATE usuarios SET usua_activo = ? WHERE usua_id = ?");
$stmtU->execute([$usua_activo, $rowDoc['usua_id']]);
// docentes.doce_activo nunca se toca — queda congelado en 1 desde el INSERT
```

Bug real detectado el 2026-08-15: `docentes.doce_activo` se usaba como fuente del KPI `total_docentes` en `07_coordinador` (`SELECT COUNT(*) FROM docentes WHERE doce_activo = 1`), pero `03_docentes` nunca lo actualizaba — el módulo de docentes solo tocaba `usuarios.usua_activo` al editar. Como resultado, `doce_activo` quedaba congelado en `1` desde el INSERT inicial para siempre, sin importar cuántas veces se "desactivara" un docente desde la UI (el único efecto visible era sobre `usua_activo`, un campo distinto). El KPI del coordinador mostraba un número incorrecto de forma silenciosa, sin ningún error visible.

Lección: cuando una entidad tiene el estado duplicado en dos tablas relacionadas (ej. `docentes.doce_activo` y `usuarios.usua_activo` para la misma persona), CUALQUIER endpoint que cambie el estado de esa entidad debe actualizar AMBOS campos en la misma operación — no asumir que un campo "espejo" se mantiene sincronizado solo porque existe. Antes de crear un nuevo campo de estado, buscar (grep) si ya existe un campo equivalente en una tabla relacionada, y si el nuevo código solo actualiza uno de los dos, es una señal de alerta.

Corregido en `toggle_estado_docente` (commit `2dd4a55`, 2026-08-15): actualiza `docentes.doce_activo` y `usuarios.usua_activo` en la misma transacción, siempre al mismo valor.

### ❌ Filtrar por un campo "proxy" en vez del dato de negocio real

```php
// PROHIBIDO — coho_id es un dato independiente de la matrícula real del estudiante
$stmt = $pdo->prepare("
    SELECT e.estu_id, e.estu_nombres, e.estu_apellidos, e.estu_numerodoc
    FROM estudiantes e
    WHERE e.coho_id = ?   -- asume que coho_id garantiza prog_id/peri_id correctos — no lo garantiza
      AND e.estu_id NOT IN (SELECT ge.estu_id FROM grmoestudiantes ge WHERE ge.grmo_id = ?)
");
```

Cuando dos datos están relacionados solo por convención de captura (ej. la cohorte que el coordinador escribe al matricular y el programa/período reales de esa matrícula), y no por una FK ni una columna compartida entre sus tablas, filtrar por uno asumiendo que garantiza el otro es un antipatrón silencioso. `estudiantes.coho_id` y `matriculas.prog_id`/`peri_id` son exactamente ese caso: `matriculas` no tiene columna `coho_id` propia, así que nada en el esquema impide que ambos datos diverjan.

Bug real: `listar_estudiantes_disponibles` en `04_grupos/grupos_mdl.php` filtraba el panel de Estudiantes Disponibles solo por `estudiantes.coho_id`, sin verificar en ningún momento `matriculas.matr_estado`/`prog_id`/`peri_id`. Esto permitió asignar a un estudiante matriculado en `ASO-2027-1` a un módulo del grupo semestre `MD-2026-2` — programa y período distintos a su matrícula real — sin ningún error visible en pantalla. Corregido en el commit `7340d6e` (2026-08-20): la query ahora exige `mt.matr_estado = 'matriculado'` con `mt.prog_id`/`mt.peri_id` coincidentes con el `gruposemestres` del `grmo_id` recibido — el dato de negocio real, no su proxy.

Lección: antes de usar un campo como filtro de pertenencia, verificar si existe una FK o columna compartida que realmente garantice la relación asumida — si la relación depende solo de que dos formularios se llenen de forma consistente, no es una garantía del esquema y puede divergir sin que nada lo impida.

**Principio general (no solo para filtros SQL):** cualquier campo "declarado" en una etapa temprana de un flujo (ej. `fichas_inscripcion.prog_id`/`jornada`, capturado en la inscripción) y su equivalente "operativo" en una etapa posterior (ej. `matriculas.prog_id`, `gruposemestres.grse_jornada`) son la misma clase de riesgo que `coho_id` vs matrícula real — sin FK ni columna compartida entre ambas tablas, nada del esquema impide que diverjan. La forma correcta de usar el dato declarado NO es tratarlo como fuente de verdad (ni para filtrar, ni para auto-guardar sin revisión): es ofrecerlo como **sugerencia editable** (precargar un select que el usuario puede corregir) o como **referencia visual de solo lectura** (ver "Select informativo" en Convenciones HTML/JS), nunca persistirlo automáticamente en la tabla operativa ni asumir que coincide.

Ejemplo aplicado correctamente: `obtener_defaults_matricula` en `02_estudiantes` (commit `ef79791`, 2026-08-22) — precarga `#slct_prog_id` desde `fichas_inscripcion.prog_id` en el modal Completar Matrícula, pero el select queda editable (el coordinador puede corregirlo si el aspirante cambió de programa) y `jornada` se muestra solo como referencia (`#slct_jornada_declarada`, disabled) — ninguno de los dos se persiste o asume como correcto sin que el coordinador lo confirme al guardar. Queda como ítem de análisis pendiente (ver PROJECT_CONTEXT.md) si conviene además advertir cuando el valor guardado en `matriculas` termina divergiendo del declarado — no implementado todavía, no confundir con la mitigación ya aplicada.

---

## Decisiones arquitectónicas activas

### Vistas como `.php` en lugar de `.html`

- **Contexto:** El sistema gestiona datos académicos sensibles (notas, calificaciones, información de estudiantes).
- **Decisión:** Todas las vistas son `_view.php` e incluyen `check_session.php` al inicio.
- **Razón:** Control de acceso real desde el servidor. Apache no sirve el layout a usuarios sin sesión válida.
- **Consecuencia:** Todos los links internos referencian `.php`. Sin excepciones.
- **Excepción documentada — módulos públicos sin sesión:** `09_inscripcion_publica/insc_view.php` y `11_actualizacion_datos/actualizar_view.php` son las únicas vistas del proyecto sin `check_session.php` — deliberado, no un descuido: ambas atienden a alguien sin cuenta en el sistema (un aspirante, un estudiante que solo tiene un link). Diferencia clave entre las dos: `insc_view.php` siempre **crea** un registro nuevo (un aspirante), mientras que `actualizar_view.php` **identifica** un registro ya existente mediante un token de un solo uso en la URL en vez de una sesión — ninguna de las dos necesita saber "quién" hace la petición más allá de esa identificación puntual.
- **Estado:** Activa. Aplicar desde el módulo 01.

### Nota definitiva calculada en servidor

- **Contexto:** La fórmula N1(20%)+N2(20%)+N3(20%)+N4(40%) con lógica de supletorios es la regla crítica del negocio.
- **Decisión:** PHP recalcula siempre antes de persistir. El JS puede calcular para mostrar al usuario pero el servidor no usa ese valor.
- **Razón:** Evitar que manipulación del cliente altere calificaciones en la BD.
- **Estado:** Activa. No negociable.

### Nota Final vs. Definitiva (calificaciones)

- **Contexto:** El módulo de calificaciones necesitaba distinguir entre el resultado bruto de la fórmula de notas y el valor oficial que cuenta para aprobar, incluyendo el caso de habilitación.
- **Decisión:**
  - `cali_nota_final`: siempre se calcula con la fórmula estándar (N1 20% + N2 20% + N3 20% + N4 40%), sin importar si el estudiante aprueba o no. Queda persistida en BD y visible como columna independiente en la planilla.
  - `cali_habilitacion`: entrada manual (0.0–5.0). Se activa únicamente si `cali_nota_final < 3.0`. Un solo intento — no existe ciclo de re-habilitación.
  - `cali_definitiva`: valor oficial, recalculado en cada guardado. Copia de `cali_nota_final` si esta es `>= 3.0`; copia de `cali_habilitacion` si `cali_nota_final < 3.0` y la habilitación fue registrada; `NULL` en cualquier otro caso.
- **Por qué se eliminó `cali_definitiva_original`:** ya no es necesario un campo de auditoría separado para conservar la definitiva pre-habilitación, porque `cali_nota_final` queda siempre visible y persistida — cumple esa función de forma natural, sin duplicar el dato.
- **Estado:** Activa. No negociable (mismo nivel que la regla de N3 sin supletorio).

### Aspirante y Estudiante en la misma tabla (alcance actual)

- **Contexto:** El prototipo TRL5 cubre inscripción, matrícula y calificaciones. El flujo aspirante→estudiante es parte del alcance pero no requiere tablas separadas en esta fase.
- **Decisión:** Una sola tabla `estudiantes`. El campo `matr_estado` en `matriculas` maneja los estados del ciclo.
- **Razón:** Reducir complejidad para el prototipo. Separar en fases posteriores si se requiere.
- **Estado:** Activa para esta fase. Revisar en TRL6.

### Calificaciones como `DECIMAL(3,1)`

- **Contexto:** Escala colombiana de educación técnica: 0.0 a 5.0 con un decimal.
- **Decisión:** `DECIMAL(3,1)` para todos los campos de calificación.
- **Razón:** Precisión exacta sin errores de punto flotante. `DECIMAL(5,2)` sería para escala 0.00-100.00.
- **Estado:** Activa.

### Formato PDF (GA-FO-04 y Boletín) — ítem 2.4

- **Contexto:** La exportación PDF del reporte de grupo (`pdf_grupo.php`) y del boletín individual (`pdf_boletin.php`) debía replicar el formato institucional GA-FO-04, adaptado a un documento generado por el sistema (no una planilla física).
- **Decisión:**
  - Sin instrucciones de diligenciamiento — es un documento de solo lectura generado por el sistema, no una planilla para llenar a mano. Decisión tomada tras revisar el formato físico original.
  - Sin bloque de "acumulados" (nota × porcentaje) — la fórmula se muestra una sola vez en el encabezado ("N1 (20%) + N2 (20%) + N3 (20%) + N4 (40%) = Nota Final"), no repetida por cada nota.
  - Sin columna de faltas de asistencia — no se registra actualmente en el sistema. Pendiente de decisión futura si se necesita.
  - Leyenda de colores con círculos CSS (`<span>` con `background-color` y `border-radius:50%`), **no emoji Unicode** — dompdf no renderiza correctamente los emoji de color; se veían como casillas vacías en el PDF generado.
  - `pdf_grupo.php`: rol restringido a coordinador/admin (`role_id` 1, 2), orientación `landscape`, incluye todos los estudiantes del grupo.
  - `pdf_boletin.php`: rol restringido a estudiante (`role_id` 4), orientación `portrait`, filtro de seguridad `WHERE grmo_id = ? AND usua_id = ?` — responde 403 genérico sin distinguir "el grupo no existe" de "es de otro estudiante".
- **Estado:** Activa. No negociable (mismo nivel que las demás decisiones de esta sección).

### Formato PDF Ficha Familiar (AC-FO-02) — pdf_ficha.php

- **Contexto:** La Ficha Familiar (formato AC-FO-02) necesitaba exportación PDF con el mismo criterio de acceso y patrón que los demás endpoints PDF (`pdf_grupo.php`, `pdf_boletin.php`).
- **Decisión:**
  - Mismo patrón de acceso que `pdf_grupo.php`: restringido a roles 1 y 2 (coordinador/admin).
  - Tamaño de página **Oficio Colombia** (612×935.43pt = 33cm), no Carta — mediante arreglo de coordenadas explícito en `setPaper()`, no el nombre de tamaño estándar, porque Dompdf no trae "Oficio" como tamaño predefinido.
  - LEFT JOIN con `fichas_inscripcion` para que aspirantes sin ficha diligenciada aún generen el PDF, con esas secciones en blanco.
- **Estado:** Activa. Mismo nivel que las demás decisiones de formato PDF de esta sección.

### Contraseñas con bcrypt desde el primer usuario

- **Contexto:** Proyecto de referencia almacenó contraseñas en texto plano, lo que requirió migración posterior.
- **Decisión:** `usua_password VARCHAR(255) NOT NULL`. Hash con `password_hash()`. Verificación con `password_verify()`. Desde el primer INSERT.
- **Estado:** Activa. No negociable.

### Jornada como atributo de grupo semestre, no de cohorte

- **Decisión:** Jornada (Semana/Sábados) es un atributo de `gruposemestres` (`grse_jornada`), NO de `cohortes`. Una cohorte agrupa el momento de inicio de varios grupos semestre; cada grupo semestre define su propia jornada de forma independiente.
- **Nota:** Si en el futuro se necesita mostrar o filtrar por jornada, la fuente de verdad es `grse_jornada` — `cohortes` ya no tiene ningún campo relacionado (columna `coho_jornada` eliminada en el commit `5bef1ef`, 2026-08-15).
- **Estado:** Activa.

### Catálogos compartidos (programas, períodos, docentes) se duplican por módulo, no se centralizan

- **Decisión:** Cada módulo que necesita poblar un select de catálogo (programas, períodos, docentes) implementa su propio endpoint listar_X, en vez de reutilizar el de otro módulo vía llamada AJAX cross-módulo. Existen implementaciones independientes de estos catálogos en 04_grupos, 03_docentes, 05_calificaciones, 02_estudiantes y 09_inscripcion_publica.
- **Nota:** La duplicación (una query SELECT simple, sin lógica de negocio) se prefiere sobre el acoplamiento entre módulos — una llamada cross-módulo dependería de la estructura relativa de carpetas, y rompería silenciosamente si algún módulo se reorganiza. Aplicado explícitamente en 03_docentes (commit 7eba870, 2026-08-15) y en 05_calificaciones (commit 6c496e4, 2026-08-16), ambos con la misma justificación.
- **Ejemplo adicional (catálogo filtrado, no solo un listado plano):** `listar_cohortes_por_programa` — existía en `grupos_mdl.php` (commit `00a22fc`) y se duplicó tal cual en `est_mdl.php` (commit `cac382a`, 2026-08-20) para la cascada Programa→Cohorte del modal "Completar Matrícula" de `02_estudiantes`, en vez de que `est_ctrl.js` llamara a `grupos_mdl.php` directamente. Confirma que la convención aplica igual cuando el endpoint duplicado recibe un parámetro de filtro (`prog_id` por POST), no solo en los `listar_X` sin parámetros ya documentados arriba.
- **Ejemplo adicional (catálogos de gruposemestres/modulos, no solo programas/períodos/docentes):** `listar_grupos_filtro` y `listar_modulos_filtro` en `est_mdl.php` (commit `b0e6660`, 2026-08-20) — implementados como endpoints propios de `02_estudiantes` para poblar los selects de Grupo/Módulo de la fila de filtros de Matriculados, en vez de que `est_ctrl.js` llamara a `grupos_mdl.php` (que ya tiene sus propios `listar_modulos`/`listar_grupos` con forma distinta). A diferencia del ejemplo anterior, aquí no se duplica una query idéntica ya existente en otro módulo — cada endpoint fue diseñado desde cero con la forma exacta que necesita su propio select (`grse_id`/`grse_codigo` y `modu_id`/`modu_sigla`/`modu_nombre`), reforzando que la convención aplica tanto a duplicar queries existentes como a crear un catálogo nuevo propio del módulo cuando el existente en otro módulo no calza igual.
- **Estado:** Activa. Si se detecta que 4+ módulos ya duplican el mismo catálogo con la misma query exacta, reevaluar si conviene extraer un endpoint central (ej. un módulo 00_catalogos) — no antes.

### Formularios duplicados en dos vistas (misma UI, backends distintos) deben mantener su disposición de campos sincronizada

- **Contexto:** La Ficha de Inscripción (AC-FO-02) vive duplicada en dos vistas independientes — `insc_view.php` (formulario público, backend `insc_mdl.php`) y el modal `#mdl_estudiante` de `est_view.php` (backend `est_mdl.php`) — misma relación "misma UI, flujo de datos distinto" ya documentada a nivel de queries de catálogo en "Catálogos compartidos" (sección anterior), pero aquí a nivel de la disposición completa de un formulario. Ninguna de las dos vistas incluye un partial/include compartido — el HTML está duplicado deliberadamente, por el mismo criterio de no acoplar módulos entre carpetas.
- **Hallazgo:** antes del commit `3672a0e` (2026-08-26), ambos formularios habían divergido silenciosamente en orden de campos, agrupación de columnas Bootstrap y texto de labels de la Sección 1 (ej. "Ciudad de residencia" vs "Ciudad (Residencia)", "Expedido en" en distinta fila) — sin que ningún cambio previo lo hubiera señalado como inconsistencia, porque ninguna de las dos vistas rompía nada por sí sola. Un aspirante que completara el formulario público veía un orden de campos distinto al que veía después el coordinador editando el mismo registro.
- **Decisión:** cuando dos vistas independientes representan el mismo formulario/concepto de negocio (aunque no compartan archivo), su disposición de campos (orden, agrupación, texto de label) debe mantenerse sincronizada intencionalmente — un cambio de layout en una debe replicarse en la otra en el mismo commit o ciclo de trabajo, salvo que exista una razón de negocio explícita y documentada para diferir.
- **Ejemplo de diferencia deliberada (no un descuido):** los encabezados "1. Datos del Aspirante" (`insc_view.php`) e "1. Datos del Estudiante" (`est_view.php`) se mantienen distintos por decisión explícita del autor — la audiencia de cada vista es distinta (aspirante autodiligenciando vs. coordinador editando un registro ya existente). Documentado en CHANGELOG.md, commit `3672a0e`.
- **Estado:** Activa desde el commit `3672a0e` (2026-08-26).

### Vistas con navbar fallback propio (no navbar.php compartido) necesitan sus propios componentes globales duplicados

- **Decisión:** navbar.php solo se incluye para roles 1/2 (Admin/Coordinador). Módulos multi-rol donde un rol adicional (ej. Docente en 05_calificaciones) no pasa por navbar.php tienen su propio `<nav>` fallback definido directamente en su _view.php. Cualquier componente global que se agregue a navbar.php (ej. el offcanvas de ayuda) y que también deba estar disponible para esos roles con navbar fallback debe duplicarse explícitamente en esa rama — no asumir que agregarlo solo a navbar.php cubre a todos los roles del sistema.
- **Nota:** Ejemplo: el offcanvas de ayuda se duplicó en la rama Docente de calificaciones_view.php (commit `8947710`, 2026-08-16), ya que Docente es la audiencia principal de ese módulo piloto y no pasa por navbar.php.
- **Estado:** Activa.

### Subida de archivos condicionada a la PK del registro no se replica en el modo "crear" de un flujo crear/editar unificado

- **Contexto:** El modal unificado de `02_estudiantes` (`#mdl_estudiante`, fusionado en la Fase C del plan de unificación de ficha de estudiante) comparte el mismo formulario entre modo creación ("Ficha de Inscripción") y modo edición ("Editar Estudiante"). El bloque de foto (`#bloque_foto_estudiante`) sube el archivo vía el endpoint `subir_foto`, que persiste el archivo asociado a un `estu_id` ya existente en BD.
- **Decisión:** El bloque de foto permanece exclusivo del modo edición — oculto (`d-none`) en modo creación. Se evaluó habilitarlo también en modo creación y se descartó.
- **Razón:** `estu_id` no existe hasta que el registro se guarda por primera vez (`guardar_completo`). Habilitar la subida de foto en modo creación exigiría rediseñar el flujo de guardado (ej. crear un registro parcial antes de completar el formulario, o subir el archivo a un área temporal sin `estu_id` y adjuntarlo después) — un cambio de arquitectura, no de UI, fuera del alcance de un ajuste de layout.
- **Aplicación futura:** cualquier módulo nuevo con un flujo crear/editar unificado sobre el mismo formulario que necesite subir un archivo ligado a la PK del registro enfrenta la misma restricción — no habilitarlo en modo creación sin decidir explícitamente cómo resolver la dependencia de la PK todavía inexistente.
- **Estado:** Activa. Evaluada y descartada explícitamente en el commit `f088466` (2026-08-24).

### Matrícula a un segundo programa requiere un flujo explícito, no una simple omisión de validación

- **Contexto:** Desde el commit `3e551e5` (migración de `coho_id`/`matr_estado_academico` a `matriculas`), el esquema permite que un estudiante tenga más de una fila en `matriculas` — una por programa. El commit `f5c21e5` agregó la UI para matricular deliberadamente a un estudiante ya matriculado en un segundo programa.
- **Decisión:** `case 'matricular'` y `case 'editar_matricula'` (`est_mdl.php`, `02_estudiantes`) bloquean explícitamente crear o editar una matrícula hacia un `prog_id` en el que el estudiante ya tiene otra fila `matr_estado = 'matriculado'` (en cualquier otro período) — mensaje: "El estudiante ya está matriculado en este programa. Use Editar Matrícula si desea modificar el período o la cohorte." La **única** forma soportada de que un estudiante termine con 2 matrículas es el botón dedicado "➕ Matricular en otro programa" (`tablaMatriculados`, reutiliza `abrirMatricular()`/`#mdl_matricular`), que crea una fila nueva para un `prog_id` distinto.
- **Razón:** Sin este bloqueo explícito, `editar_matricula` permitiría — por accidente, no por diseño — que una edición cambiara el `prog_id` de una matrícula existente hacia un programa que el estudiante ya cursa por otra fila, dejando 2 matrículas duplicadas del mismo programa sin que ningún flujo lo hubiera decidido a propósito. La multiplicidad de matrículas debe ser siempre el resultado de una acción deliberada del coordinador (el botón dedicado), nunca un efecto colateral de editar una fila existente.
- **Consecuencia:** Cualquier `case` nuevo que modifique `matriculas.prog_id` (crear o editar) debe replicar este chequeo — `SELECT matr_id FROM matriculas WHERE estu_id = ? AND prog_id = ? AND matr_estado = 'matriculado' AND <matr_id o peri_id> != ?` — antes de persistir.
- **Estado:** Activa. No negociable (mismo nivel que las demás reglas de esta sección) — ver también el fix de `eliminar_aspirante` del mismo commit, que corrigió un chequeo no determinista (`fetch()` sin `ORDER BY`) que dependía de esta misma multiplicidad de filas para manifestarse.

### "Hoja de Matrícula" en el dropdown de Acción no valida `matr_estado` en frontend (a diferencia del botón equivalente dentro de "Datos Estudiante")

- **Contexto:** El PDF Hoja de Matrícula (AC-FO-09, `pdf_hoja_matricula.php`) ya era descargable desde el botón `#btn_hoja_matricula_pdf` dentro del modal "Datos Estudiante" (`#mdl_estudiante`), donde permanece `d-none` salvo que `matr_estado === 'matriculado'` (ver Fase 2.8.F2). El commit `4fb2257` (2026-08-31) agrega un ítem nuevo "🖨️ Hoja de Matrícula" directamente en el dropdown de Acción de `tablaMatriculados`, como acceso directo de un clic al mismo endpoint.
- **Decisión:** El ítem del dropdown **no** replica la validación `matr_estado === 'matriculado'` del botón del modal — queda siempre habilitado, para cualquier fila de `tablaMatriculados`.
- **Razón:** `tablaMatriculados` ya filtra sus filas en el propio SQL de `listar_matriculados` con `m.matr_estado = 'matriculado'` (ver ese `case` en `est_mdl.php`) — toda fila que llega a esta tabla ya cumple la condición que el botón del modal verifica en JS. Duplicar la validación en el dropdown sería redundante contra una condición que el backend ya garantiza para esa tabla específica (a diferencia del modal "Datos Estudiante", que también se abre desde `tablaAspirantes`, donde `matr_estado` sí puede ser distinto de `'matriculado'` y la validación en JS del botón interno sigue siendo necesaria ahí).
- **Consecuencia:** Esta es una decisión consciente, no una inconsistencia pendiente de corregir. Si en el futuro `listar_matriculados` dejara de filtrar por `matr_estado = 'matriculado'` (ej. para mostrar también retirados/graduados), el ítem del dropdown tendría que agregar la misma validación que ya tiene el botón del modal — revisar este supuesto antes de tocar el `WHERE` de ese `case`.
- **Estado:** Activa.

### "Ficha de Inscripción" en el dropdown de Acción de Aspirantes no valida `estado_ficha` en frontend (justificación distinta a "Hoja de Matrícula", mismo resultado visual)

- **Contexto:** El PDF Ficha de Inscripción (AC-FO-02, `pdf_ficha.php`) ya era descargable desde el botón `#btn_ficha_inscripcion_pdf` dentro del modal "Datos Estudiante" (`#mdl_estudiante`), donde permanece `d-none` salvo que `estado_ficha === 'completa'`. El commit `9346a76` (2026-09-01) agrega un ítem nuevo "🖨️ Ficha de Inscripción" directamente en el dropdown de Acción de `tablaAspirantes`, como acceso directo de un clic al mismo endpoint.
- **Decisión:** El ítem del dropdown **no** replica la validación `estado_ficha === 'completa'` del botón del modal — queda siempre habilitado, para cualquier fila de `tablaAspirantes`, sea cual sea su `estado_ficha` (`sin_iniciar`, `incompleta` o `completa`).
- **Razón — distinta de "Hoja de Matrícula", no la misma:** en el caso de Hoja de Matrícula, la validación en frontend era redundante porque el propio SQL de `listar_matriculados` ya garantiza `matr_estado = 'matriculado'` para toda fila de esa tabla — no hay ningún caso real en el que la condición pudiera fallar. Aquí **no aplica el mismo argumento**: `listar_aspirantes` no filtra por `estado_ficha`, así que el campo sí varía libremente entre `sin_iniciar`, `incompleta` y `completa` en las filas de `tablaAspirantes` — la condición que el botón del modal verifica es real y puede ser falsa para cualquier fila dada. Omitir la validación aquí es una **elección consciente de simplicidad/alcance** (Opción A: permitir descargar el PDF de una ficha incompleta, mostrando en el documento las secciones vacías que falten, en vez de bloquear el acceso), no una garantía estructural heredada del backend como en el caso de Matrícula.
- **Consecuencia:** A diferencia de la nota de "Hoja de Matrícula" (donde un cambio futuro en el `WHERE` de `listar_matriculados` obligaría a agregar la validación), aquí no hay ningún cambio de query que invalide esta decisión — es estable mientras la Opción A siga vigente. Si en el futuro se decide bloquear la descarga para fichas incompletas (Opción B, no implementada), el ítem del dropdown tendría que replicar `estado_ficha !== 'completa'` con el mismo criterio que ya usa `#btn_ficha_inscripcion_pdf` en el modal.
- **Estado:** Activa.

### Dropdown de Acciones aplicado incluso sin cruzar el umbral de 3+ botones (tablaDocentes) — decisión consciente de uniformidad visual, no un error de criterio

- **Contexto:** El patrón de dropdown de Acción/Acciones (ver "Columna 'Acción'/'Acciones' de una tabla..." en Convenciones HTML/JS) nació en `tablaMatriculados` (`4fb2257`) para columnas que acumulaban 3 o más botones en línea. La columna "Acciones" de `tablaDocentes` (`03_docentes`) nunca cruzó ese umbral — siempre tuvo exactamente 2 botones visibles a la vez (Editar fijo + uno solo de Eliminar/Desactivar/Activar, mutuamente excluyentes). El commit `e1c232d` (2026-09-01) aplicó el mismo patrón de dropdown ahí de todas formas.
- **Decisión:** El patrón de dropdown se aplica a esta columna pese a no cumplir el criterio de conteo que lo originó.
- **Razón:** Decisión explícita de Jose Luis de **uniformidad visual en toda la aplicación** — que cualquier columna de acciones por fila, en cualquier módulo, se vea y se comporte igual, en vez de que el criterio de conteo produzca una mezcla de columnas con dropdown y columnas con botones en línea según cuántas acciones tenga cada una. No es un error de aplicar el patrón fuera de su criterio original — es un cambio deliberado de criterio, documentado también en la sección de Convenciones HTML/JS ("Criterio vigente" vs. "Criterio original").
- **Consecuencia:** El umbral de "3+ acciones" queda **superado como criterio de decisión** para todo el proyecto desde este commit — no se usa como condición para decidir si una columna de acciones nueva o existente amerita el dropdown. Cualquier columna de acciones por fila que se agregue o se toque a futuro (`04_grupos` y cualquier módulo nuevo) debe usar este patrón por consistencia, sin necesidad de contar primero cuántos botones tiene.
- **Estado:** Activa.

### `matriculas.matr_semestre`: posición del estudiante en su propio plan de estudios, no atributo de grupo semestre

- **Contexto:** El commit `c985188` (2026-09-02) agrega `matriculas.matr_semestre` (`TINYINT UNSIGNED NOT NULL DEFAULT 1`) como preparación de esquema para la futura acción "avanzar de semestre" (matricular a un estudiante ya matriculado a su siguiente semestre dentro del mismo programa). El proyecto ya tenía un campo de semestre — `gruposemestres.grse_semestre` — antes de este commit.
- **Decisión:** `matr_semestre` vive en `matriculas`, no se reutiliza ni se deriva de `gruposemestres.grse_semestre`. Representa el semestre del programa que cursa ESA fila de matrícula (1-N) — la posición del estudiante en su propio plan de estudios — y es independiente del `grse_semestre` de cualquier grupo semestre donde el estudiante curse módulos ese período.
- **Razón:** `grse_semestre` describe al GRUPO, no al estudiante — un estudiante puede terminar cursando módulos dentro de un grupo semestre cuyo `grse_semestre` no coincide con su propio avance real en el programa (la asignación de estudiante a módulo vía `grmoestudiantes` es independiente de `matriculas`, ver "Estructura de módulos" y `grmoestudiantes` en Tablas del sistema). Mismo principio ya aplicado en "Jornada como atributo de grupo semestre, no de cohorte" (`grse_jornada` vive en el grupo, no en la cohorte, porque describen conceptos distintos) — mezclar `matr_semestre` con `grse_semestre` en un solo campo repetiría ese mismo acoplamiento incorrecto.
- **Consecuencia:** `matr_semestre` se valida server-side (`case 'matricular'`/`case 'editar_matricula'`, `est_mdl.php`) contra `programas.prog_duracion_semestres` del `prog_id` de la propia matrícula — **nunca** contra `grse_semestre` de ningún grupo. El frontend (`#slct_matr_semestre`/`#slct_editar_matr_semestre`) valida solo para UX; el servidor nunca confía en ese valor sin revalidar — mismo criterio de "Validación en dos capas" ya documentado en Patrones de ingeniería, no una excepción.
- **Estado:** Activa.

### ENUM `matriculas.matr_estado` ampliado con `'cursado'` — de "reservado, sin uso" a valor con escritor real (commit `0aef564`)

- **Contexto:** El commit `c985188` amplía `matriculas.matr_estado` a `ENUM('aspirante','matriculado','retirado','graduado','cursado')`, adelantando el cambio de esquema que la futura acción "avanzar de semestre" iba a necesitar: la matrícula del semestre anterior transicionaría a `'cursado'` en vez de permanecer en `'matriculado'`. En su momento (`c985188`, 2026-09-02) ningún endpoint lo escribía — confirmado por grep en todo `app/` — y esta nota pedía explícitamente volver a verificar ese grep antes de dar por bueno cualquier diagnóstico sobre `matr_estado`.
- **Cierre de la nota (commit `0aef564`, 2026-09-02):** el nuevo `case 'avanzar_semestre'` (`est_mdl.php`, `02_estudiantes`) es ahora el único escritor de `'cursado'` — `UPDATE matriculas SET matr_estado = 'cursado' WHERE matr_id = ?` sobre la fila que se avanza, dentro de la misma transacción que inserta la fila nueva con `matr_semestre + 1`. El valor deja de ser puramente reservado: cualquier diagnóstico futuro sobre `matr_estado` debe considerar `'cursado'` como un estado real y alcanzable, no solo teórico.
- **Razón original:** adelantar el `ALTER TABLE` en `c985188` ahorró tener que repetirlo el día que se implementó la acción de avance — la columna y el valor del ENUM ya estaban listos, solo faltaba el `case` que los escribiera.
- **Consecuencia:** ninguna acción adicional escribe `'cursado'` — sigue siendo exclusivo de `avanzar_semestre`, tal como se anticipó en `c985188`. Detalle completo de las validaciones de esa acción en CHANGELOG.md (commit `0aef564`).
- **Estado:** Activa — resuelta, con escritor real desde `0aef564`. No queda ningún grep pendiente de repetir.

### Verificación anticipada para UI, recálculo confiable para escritura

- **Contexto:** El nuevo `case 'avanzar_semestre'` (`est_mdl.php`, `02_estudiantes`, commit `0aef564`) necesita saber, en dos momentos distintos, si un estudiante aprobó su período actual: (1) al construir el dropdown de `tablaMatriculados`, para decidir si el ítem "⏩ Matricular al sgte. sem." se muestra habilitado o deshabilitado; (2) al procesar la escritura real, para autorizar o rechazar el avance.
- **Decisión:** son dos cálculos separados y deliberadamente redundantes, no uno reutilizado. `aprobado_periodo_actual` (columna nueva de `listar_matriculados`, subconsulta que replica la lógica de promedio de `detalle_periodo`) alimenta únicamente la decisión de UI. El `case 'avanzar_semestre'` **nunca** confía en esa columna ni en ningún valor recibido por `$_POST` — vuelve a calcular la aprobación con su propia query directa (`SELECT c.cali_definitiva FROM grmoestudiantes ...`) en el momento exacto de escribir, antes de abrir la transacción.
- **Razón:** el valor mostrado en la UI puede quedar desactualizado entre el render de la fila y el clic del coordinador (otro docente registra una nota en el medio, por ejemplo) — confiar en él para autorizar la escritura reabriría la misma clase de vulnerabilidad que "Nunca confiar en el estado del cliente para decisiones que afectan seguridad/acceso" (Patrones de ingeniería) ya prohíbe para datos que sí vienen del cliente. Aquí el dato ni siquiera viene del cliente — viene de una columna de solo lectura calculada en un momento anterior — pero el principio es el mismo: una condición mostrada en la UI es una conveniencia de presentación, nunca una autorización.
- **Principio reutilizable:** cualquier acción futura que (a) muestre en un listado si algo "puede" hacerse, condicionado a un cálculo de negocio no trivial, y (b) luego ejecute esa acción — debe recalcular la condición al momento de escribir, con su propia query, sin importar cuán reciente parezca el valor ya mostrado. Mostrar algo condicionado en la UI no exime de revalidar en el momento de la escritura.
- **Estado:** Activa.

### `columns` de DataTable compartido entre varias instancias: función global que retorna un array NUEVO en cada llamada, nunca una constante compartida por referencia

- **Contexto:** El commit `0aaa4e9` (2026-09-02) dividió la pestaña única "Matriculados" en "Matriculados (Per. Actual)" y "Matriculados (Per. Anteriores)" — misma estructura de columnas exacta (incluida la columna "Semestre" de `8884cb4`) en dos instancias de DataTable distintas (`tablaMatriculadosActual`/`tablaMatriculadosAnteriores`). Es el primer caso del proyecto con dos DataTables cuyas columnas son verdaderamente idénticas — precedentes previos con múltiples `.DataTable()` en un mismo archivo (`04_grupos/grupos_ctrl.js`: Cohortes/Grupos/Períodos/Módulos/Programas; `03_docentes/doc_ctrl.js`) nunca compartieron estructura entre sí, cada uno con su propio array `columns` inline y sin ninguna abstracción.
- **Decisión:** el array `columns` (~140 líneas) se extrajo a una función global `crearColumnasMatriculados()` que **retorna un array nuevo en cada llamada** — no una constante (`const columnasMatriculados = [...]`) reutilizada por referencia entre las dos inicializaciones de DataTable.
- **Razón:** un array de `columns` compartido por referencia entre dos instancias de DataTable es mutable — cada instancia de DataTables reordena/anota internamente el array que recibe (ej. al reordenar columnas por el usuario, o al normalizar opciones internamente), así que pasar el mismo objeto a dos tablas arriesga que una mute el estado que la otra ya está usando, un bug de mutación cruzada difícil de diagnosticar porque solo se manifiesta cuando ambas tablas están inicializadas a la vez. Construir el array de cero en cada llamada elimina el riesgo por completo, al costo despreciable de reconstruir ~140 líneas de definiciones dos veces en la carga de la página.
- **Consecuencia — patrón reutilizable:** la próxima vez que el proyecto necesite una tercera (o más) tabla con exactamente la misma estructura de columnas que otra ya existente, extraer `columns` a una función `crearColumnasX()` en scope global (mismo motivo de scope global que `poblarSelectSemestre()`/`cargarCohortesPorPrograma()` — ver "Funciones de refresh invocadas desde onclick inline..." en Patrones de ingeniería) que retorne un array nuevo cada vez, en vez de duplicar las ~140 líneas inline en cada inicialización o compartir una constante por referencia. Si las columnas difieren aunque sea parcialmente entre las tablas, este patrón no aplica — extraer solo lo que realmente se repite, no forzar una función compartida con parámetros condicionales para columnas que en realidad son distintas.
- **Ejemplo:** `crearColumnasMatriculados()` en `est_ctrl.js` (`02_estudiantes`, commit `0aaa4e9`), consumida por `tablaMatriculadosActual` y `tablaMatriculadosAnteriores` en `cargarTablas()` — ver también la entrada correspondiente en "Patrones de ingeniería" con el fragmento de código.
- **Estado:** Activa.

---

## Frontend stack

- Bootstrap 5.3 (CDN)
- Bootstrap Icons 1.11.3 (CDN, `cdn.jsdelivr.net`) — primera librería de íconos del proyecto; agregada a `02_estudiantes/est_view.php` (commit `4fb2257`, 2026-08-31) para el ícono `bi-gear-fill` del dropdown de Acción de `tablaMatriculados`. Antes de este commit el proyecto no usaba ninguna librería de íconos — todo indicador visual era emoji Unicode (📝, ✏️, 🖨️, etc., ya usados en labels de botones) o CSS puro (puntos de color). Agregar el `<link>` a cualquier otra vista que necesite un ícono de Bootstrap Icons, sin duplicar el CDN si ya está cargado en esa página.
- jQuery 3.7 (CDN)
- DataTables 1.13 (CDN) — todas las vistas de listado
- dompdf (Composer) — generación de PDF
- Sin `package.json`, sin lockfile, sin build step

---

## Checklist de deploy a producción

| Acción antes de cada deploy | Estado |
|---|---|
| Prueba local completa del módulo modificado | ⬜ |
| Git commit con mensaje descriptivo | ⬜ |
| Solo archivos modificados identificados para subir | ⬜ |
| `pdo_web.php` en producción intacto — nunca sobreescribir | ⬜ |
| Verificar que tablas en producción existen y tienen el esquema correcto | ⬜ |
| Verificar en producción: `SHOW PROCEDURE STATUS WHERE Db = 'emdb_academica';` y `SHOW TRIGGERS FROM emdb_academica;` — ambas deben devolver vacío antes de operar con datos reales | ⬜ |

- Nunca subir la carpeta completa — solo los archivos modificados.
- Nunca sobreescribir `pdo_web.php` en producción. Como este archivo no existe en git ni en el repo local, no hay ninguna protección técnica (`.gitignore`, plantilla, etc.) que lo resguarde — la única protección es un recordatorio operativo: al subir archivos modificados manualmente al hosting, verificar explícitamente que `app/00_connect/pdo.php` (el archivo ya renombrado en el servidor, con credenciales de producción) no esté entre los archivos que se suben o sobreescriben.
- Recomendado: mantener una copia de respaldo de `pdo_web.php` fuera del repositorio (gestor de contraseñas, carpeta local no versionada) — si se pierde el acceso al servidor, no hay forma de reconstruirlo desde git, porque nunca estuvo ahí.
- Los nombres de tablas en producción van en **minúsculas** (Linux es case-sensitive).

---

## Deuda técnica / pendiente antes de producción

| Ítem | Detalle |
|---|---|
| ~~Exportación Excel incompleta en `06_reportes`~~ (RESUELTO 2026-08-07, commit 8ada1df) | Exportación a Excel en `06_reportes` no incluía datos de curso ni docente (hallazgo del 2026-07-05). reporte_grupo ahora devuelve también un bloque de contexto (Módulo, Grupo, Docente, Programa, Período, Jornada), incluido como fila de encabezado en el .xlsx exportado — mismo patrón de JOINs ya usado en pdf_grupo.php. |
| ~~Auditoría PHP 8.1-8.5~~ (RESUELTO 2026-07-25) | Auditoría estática completa sobre los 22 archivos .php de app/: sin hallazgos (código 100% procedural, sin type hints, conexión BD 100% vía PDO). Prueba en runtime de dompdf (librería de terceros) con `display_errors=1` y `error_reporting=E_ALL`: PDF generado sin ningún warning ni deprecation. Compatibilidad con PHP 8.5 confirmada de punta a punta. |
| `recaptcha_config.php` sin integrar | Credenciales reCAPTCHA ya creadas (`app/00_connect/recaptcha_config.php`, hallazgo 2026-07-25/26). Es preparación para la Fase 3 del formulario público de inscripción — ver tabla de 5 fases más abajo en "Estado del roadmap". Fases 0-2 cerradas, Fases 3-5 pendientes. |
| ~~curl_close() deprecada~~ (RESUELTO 2026-08-01) | Igual que imagedestroy() (Fase Ficha Familiar), curl_close() no hace nada desde PHP 8.0 y genera warning de deprecación explícito en PHP 8.5 — el warning se imprime antes del json_encode() y rompe dataType:'json' en $.ajax. Eliminada de verificarRecaptcha() en insc_mdl.php. Patrón a vigilar en cualquier código nuevo que use curl. |
| ~~Gestión de períodos académicos sin CRUD~~ (RESUELTO 2026-08-07) | La tabla `periodos` no tenía ningún CRUD en la aplicación — solo existían los 3 períodos sembrados por el seed inicial (2025-1, 2025-2, 2026-1). Cualquier período nuevo debía insertarse manualmente vía SQL/phpMyAdmin. Implementado como pestaña "Períodos" dentro de `04_grupos` (crear/editar, sin eliminar — FK con `ON DELETE RESTRICT` desde `matriculas` y `gruposemestres`). |
| `pdo_web.php` sin `SET time_zone` (producción) | Desde el commit 7216637, `app/00_connect/pdo.php` (local) ejecuta `SET time_zone = '-05:00'` justo tras conectar. `pdo_web.php` — el archivo equivalente en producción, no versionado en git — no tiene ese `SET` porque no pasa por el flujo de deploy. La conexión de producción sigue dependiendo del `time_zone` que tenga configurado el servidor MySQL del hosting cPanel, que probablemente no sea Bogotá. Pendiente: edición manual directa en el servidor de producción, fuera del flujo de git. |
| Eliminar aspirante sin auditoría (decisión, no deuda pendiente) | `eliminar_aspirante` en `02_estudiantes` (commit 7d72327) hace DELETE físico y permanente, sin guardar motivo ni registro de quién/cuándo eliminó. Esto fue una decisión explícita de José Luis (director del proyecto), no un descuido — no agregar soft-delete, motivo, ni tabla de auditoría a esta acción sin consultarlo primero. |
| `case 'obtener'` de `doc_mdl.php` usa `INNER JOIN` entre `docentes` y `usuarios` | Descubierto al implementar el cambio de contraseña opcional en "Editar Docente" (commit `35c312a`, 2026-08-25): un docente con `usua_id NULL` (sin acceso al sistema) haría que este `JOIN` no devuelva ninguna fila — el endpoint respondería "Docente no encontrado" en vez de abrir el modal con el campo de contraseña oculto. Hoy no hay ningún docente en esa condición en la BD (`SELECT COUNT(*) FROM docentes WHERE usua_id IS NULL` → 0), así que no es un bug activo. Pendiente de decisión: cambiar a `LEFT JOIN` si en algún momento se permite crear/editar docentes sin usuario asociado — no implementar sin decisión explícita de Jose Luis. |
| `doc_view.php`, `est_view.php` y `grupos_view.php` tienen un `<nav>` fallback (`navbar-dark bg-dark px-3`) inalcanzable en la práctica | Los tres comparten el mismo guard de sesión (`if ($_SESSION['role_id'] !== 1 && $_SESSION['role_id'] !== 2) { redirect; }`), que rechaza cualquier rol distinto de Admin/Coordinador antes de llegar al `<body>` — la rama `else` con el `<nav>` fallback nunca se ejecuta, es código muerto en los tres archivos. Descubierto al implementar el bloque "Último acceso" en el navbar (commit `ac96f07`, 2026-08-25): se agregó primero a `doc_view.php` asumiendo que era el nav real de Docente, y se confirmó que Docente (`role_id=3`) ni siquiera puede acceder a esa vista — su destino real tras login es `05_calificaciones/calificaciones_view.php` (único con guard `!== 1 && !== 2 && !== 3`, que sí permite Docente); el cambio se revirtió de `doc_view.php` y se aplicó ahí. Pendiente de decisión: ¿eliminar los tres bloques `<nav>` fallback (nunca alcanzables con los guards actuales) o ampliar el guard de esas vistas si algún rol adicional necesita entrar? No implementar sin decisión explícita de Jose Luis. |
| ~~`total_modulos` en el case `listar_grupos` (`grupos_mdl.php`, `04_grupos`) no filtra por `grmo_activo = 1`, a diferencia del `total_estudiantes` agregado al mismo case~~ (RESUELTO 2026-08-30, commit `b8a583a`) | Detectado al implementar `total_estudiantes` (commit `a39cb24`, 2026-08-30): la nueva subconsulta sí filtraba `gm.grmo_activo = 1` — mismo patrón ya usado en `est_mdl.php` para contar módulos de un estudiante — pero `total_modulos`, ya existente en ese mismo `case 'listar_grupos'` desde antes de ese commit, contaba todos los `gruposmodulos` del grupo semestre sin ese filtro, incluyendo módulos inactivos. Era una inconsistencia preexistente entre dos columnas de la misma fila del listado. Corregido en el commit `b8a583a` (2026-08-30): se agregó `AND gm.grmo_activo = 1` a la subconsulta de `total_modulos`, alineándola con `total_estudiantes` y con el resto de conteos de `gruposmodulos` del proyecto (`listar_docentes` en `grupos_mdl.php`, `doc_mdl.php`, `coordinador_mdl.php`, `calificaciones_mdl.php`, `reportes_mdl.php`). Verificado sin impacto en los datos locales (0 `gruposmodulos` con `grmo_activo = 0` al momento del cambio); pendiente confirmar el mismo resultado en producción cuando aplique el deploy. |
| "Última fecha de actualización" en `#mdl_actualizacion_datos` puede mostrar `'—'` cuando en realidad sí existe una aprobación en el historial del estudiante | Descubierto en la revisión de cierre de la Fase 5 del feature "Link de actualización de datos" (2026-09-03, sin commit de código — solo documentación). `abrirActualizacionDatos()` (`est_ctrl.js`, commit `70c45ba`) muestra la fecha solo si la solicitud MÁS RECIENTE del estudiante (`estado_solicitud_actualizacion`, que no filtra por estado) está en `'aprobado'` — si el estudiante tuvo una aprobación y luego otra solicitud más nueva que terminó `'descartado'`, el modal muestra `'—'` aunque sí exista una aprobación real más atrás. El badge "✅ Actualizado" de la misma fila (mismo commit, `soac_fecha_ultima_aprobacion` en `listar_matriculados`) usa una subconsulta dedicada que sí busca la aprobación más reciente sin importar si hay algo más nuevo sin aprobar — en ese escenario específico, el badge de la fila y el texto del modal responden distinto a la misma pregunta ("¿cuándo fue la última actualización aprobada?"). No es un bug de datos (ambos leen `soac_resuelto_en` correctamente, solo difieren en el criterio de búsqueda) ni bloqueante para el uso normal del feature. Pendiente de decisión: alinear `abrirActualizacionDatos()` para que use el mismo criterio que la subconsulta de `listar_matriculados` (agregar un `case` dedicado en `est_mdl.php`, o traer `soac_fecha_ultima_aprobacion` como parte de la respuesta de `estado_solicitud_actualizacion`). No implementar sin decisión explícita de Jose Luis. |

---

## Estado del roadmap

Ver historial completo en CHANGELOG.md.

### Phase 0 — Setup inicial

| Ítem | Descripción | Estado |
|---|---|---|
| 0.1 | Estructura de carpetas y módulo `00_connect` | ⬜ |
| 0.2 | Script DDL completo — crear BD `emdb_academica` | ⬜ |
| 0.3 | Datos de configuración: roles, programas, módulos ASO y MD | ⬜ |
| 0.4 | Módulo `01_login` con bcrypt y `check_session.php` | ⬜ |
| 0.5 | CRUD `08_admin` — gestión de usuarios | ⬜ |

### Phase 1 — Módulos de gestión base

| Ítem | Descripción | Estado |
|---|---|---|
| 1.1 | Módulo `03_docentes` — CRUD docentes | ✅ 2026-05-01 |
| 1.2 | Módulo `02_estudiantes` — CRUD + matrícula a programas | ✅ 2026-05-01 |
| 1.3 | Módulo `04_grupos` — cohortes, grupos semestre, grupos módulo | ✅ 2026-05-05 |

### Phase 2 — Gestión académica (módulo crítico TRL5)

| Ítem | Descripción | Estado |
|---|---|---|
| 2.1 | Módulo `05_calificaciones` — registro notas por docente | ✅ 2026-05-05 |
| 2.2 | Módulo `06_reportes` — consulta estudiante + exportación Excel coordinador | ✅ 2026-05-07 |
| 2.3 | Módulo `07_coordinador` — dashboard seguimiento | ✅ 2026-05-07 |
| 2.4 | Módulo `06_reportes` — exportación PDF (GA-FO-04 por módulo para coordinador + boletín individual para estudiante) | ✅ 2026-07-05 |
| 2.5 | Rediseño `05_calificaciones` — Nota Final siempre calculada, Habilitación y Definitiva como valor oficial recalculado | ✅ 2026-07-04 |
| 2.6 | Ficha Familiar AC-FO-02 — captura completa de datos familiares, foto de estudiante, indicador de completitud, exportación PDF | ✅ 2026-07-26 |

### Phase 2.7 — Formulario público de inscripción (5 fases, plan de sesión 2026-07-27)

> Objetivo: permitir que un aspirante sin cuenta diligencie su propia
> inscripción desde la web, quedando pendiente de revisión y
> aprobación del coordinador. No confundir con Phase 3 (Validación
> TRL5) — es trabajo de desarrollo, no de validación.

| Ítem | Descripción | Estado |
|---|---|---|
| 2.7.0 | Credenciales Google reCAPTCHA v2 ("No soy un robot") para escuelamdb.com + localhost | ✅ 2026-07-25 |
| 2.7.1 | Pantalla interna de datos familiares (AC-FO-02) — coordinador diligencia fichas_inscripcion | ✅ 2026-07-26 (Ficha Familiar) |
| 2.7.2 | Migración de esquema — estudiantes.estu_origen ENUM('manual','web') DEFAULT 'manual' | ✅ 2026-07-27 (commit 7702fe2) |
| 2.7.3 | Formulario público — módulo nuevo `09_inscripcion_publica/`, sin sesión, verificación reCAPTCHA en servidor, detección de duplicados por documento, genera `finc_codigotemporal` | ✅ 2026-08-01 — Paso 1 (b5626c2, b34143d) y Paso 2 (c60cb1b) completos |
| 2.7.4 | Integración — listado de Aspirantes muestra origen (Manual/Web) para que el coordinador sepa qué revisar | ✅ 2026-08-07 (commit f704901) |
| 2.7.5 | Pruebas extremo a extremo + documentación | ✅ 2026-08-07 (commit 1531593) — pruebas detectaron hallazgo crítico (indicador binario de ficha completa daba falso positivo para aspirantes web que abandonaban tras el Paso 1); corregido con Ficha Familiar obligatoria + indicador de 3 estados |

**⚠️ Superado por el commit `0e098bb` (2026-08-24).** El diseño de 2 pasos de esta tabla (`finc_codigotemporal`, `fam_view.php`/`fam_mdl.php`/`fam_ctrl.js` de la Fase 2.7.3) ya no existe en el código actual — se reemplazó por un solo formulario de una sola pantalla con un solo guardado transaccional, absorbido por completo dentro de `insc_view.php`/`insc_ctrl.js`/`insc_mdl.php`. Esta tabla se conserva como registro histórico de lo que se implementó y por qué; no interpretar como el diseño vigente. Ver el patrón resultante documentado en "Dos transacciones PDO individualmente correctas no garantizan atomicidad entre sí" (segundo caso de uso) y en "Remover un campo solo de la UI..." (variante de eliminar el mecanismo por completo), ambas en la sección "Patrones de ingeniería" de este mismo archivo.

**Nota de diseño (Fase 2.7.3):** reutilizar `finc_codigotemporal`
(existente en `fichas_inscripcion`, VARCHAR(20), sin usar hasta ahora)
para permitir que el aspirante retome el formulario sin necesidad de
una cuenta completa. Al construir esta fase, evaluar si el campo
necesita una restricción UNIQUE (hoy no la tiene).

### Phase 2.8 — Unificación de ficha de estudiante (6 fases A–F, plan de sesión 2026-08-22)

| Ítem | Descripción | Estado |
|---|---|---|
| 2.8.A | Botón "📝 Datos Estudiante" — unifica "Editar"/"Ficha", adopta el indicador de completitud (`estado_ficha`) en `tablaMatriculados` y `tablaAspirantes` (`02_estudiantes`) | ✅ 2026-08-23 (commit `38e1809`) |
| 2.8.B | Columna Edad en Matriculados — formato "17 (23 sep)", resaltado celeste `#cfe2ff` para menores de 18 | ✅ 2026-08-23 (commit `7cef01a`) |
| 2.8.C1 | Fusión de modales "Editar Estudiante" + "Ficha Familiar" — backend: case `guardar_completo` (1 transacción PDO para estudiante + ficha, Ficha Familiar obligatoria siempre) + case `obtener_completo` (LEFT JOIN); cases antiguos sin llamadores, pendientes de limpieza | ✅ 2026-08-23 (commit `42e4175`) |
| 2.8.C2 | Fusión de modales "Editar Estudiante" + "Ficha Familiar" — frontend: modal único `modal-xl`, botón "Guardar" único, foto arriba, ficha debajo, botón PDF trasladado al final del modal | ✅ 2026-08-23 (commit `76f90c5`) |
| 2.8.D | Configuración institucional en `08_admin` — número de matrícula inicial, Director, Secretario | ✅ 2026-08-23 (commit `25530cc`) |
| 2.8.E | Columnas nuevas en `matriculas` — folio, fecha, observaciones, número | ✅ 2026-08-23 (commit `afdcfc1`) |
| 2.8.F1 | `usua_nombre` en `usuarios` — prerequisito para "Matriculado por" en el PDF | ✅ 2026-08-23 (commit `c34b780`) |
| 2.8.F2 | Exportación PDF Hoja de Matrícula, formato AC-FO-09 | ✅ 2026-08-23 (commit `ffb6a6c`) |

**Plan completo (A–F) cerrado el 2026-08-23** — las 6 fases (A, B, C1+C2, D, E, F1+F2) quedaron implementadas y verificadas en navegador.

**Deuda técnica de C1/C2 resuelta (commit `5de9a9e`, 2026-08-23):** los 4 cases sin llamadores (`guardar`, `obtener`, `obtener_ficha`, `guardar_ficha`) y el código JS asociado (`abrirFicha()`, handler `btn_guardar_ficha`) — documentados como pendientes de limpieza desde `42e4175`/`76f90c5` — fueron eliminados.

### Phase 2.9 — Mejoras de UI de columnas de acciones por fila (originalmente "post-unificación (02_estudiantes)"; generalizada tras el ítem D, que extiende el mismo patrón a 03_docentes)

> **Nota sobre el título de esta fase (criterio de Claude Code, a revisar por Jose Luis):** el título original de esta fase era "Mejoras de UI post-unificación (02_estudiantes)" — correcto mientras los 3 ítems (A, B, C) vivían en ese único módulo. El ítem D (commit `e1c232d`) aplica el mismo patrón de dropdown a `03_docentes`, un módulo distinto, sin relación con el plan de unificación de ficha de estudiante que le dio nombre a esta fase originalmente. Se optó por generalizar el título en vez de abrir una fase nueva porque los 4 ítems comparten el mismo patrón técnico (dropdown de Acciones) documentado en una sola convención de CLAUDE.md — separarlos en dos fases habría duplicado ese contexto sin necesidad. Si Jose Luis prefiere una fase propia para `03_docentes` (ej. Phase 2.10), mover el ítem D allá sin perder su fecha/commit.

| Ítem | Descripción | Estado |
|---|---|---|
| 2.9.A | Indicador visual "N programas" en `tablaMatriculados` (`02_estudiantes`) — badge `🎓 N programas` cuando el estudiante tiene 2+ matrículas, con detalle en el `title` | ✅ 2026-08-31 (commit `1529832`) |
| 2.9.B | Columna "Acción" de `tablaMatriculados` (`02_estudiantes`) agrupada en dropdown de ícono — reemplaza 3 botones en línea por `bi-gear-fill` + dropdown de Bootstrap con 4 acciones, agrega "🖨️ Hoja de Matrícula" como ítem nuevo (antes solo dentro del modal "Datos Estudiante"), deshabilita "➕ Matricular en otro programa" con tooltip cuando `programas_matriculados_activos >= programas_activos_totales` | ✅ 2026-08-31 (commits `53d5ec5` backend + `4fb2257` frontend) |
| 2.9.C | Columna "Acción" de `tablaAspirantes` (`02_estudiantes`) agrupada en dropdown de ícono — reemplaza 2 botones en línea (Matricular, 📝 Datos Estudiante) por `bi-gear-fill` + dropdown de Bootstrap con 3 acciones, agrega "🖨️ Ficha de Inscripción" como ítem nuevo (antes solo dentro del modal "Datos Estudiante"), siempre habilitado sin validar `estado_ficha` en frontend (Opción A). Solo frontend — sin fase de backend, `estado_ficha` ya viajaba en `listar_aspirantes` | ✅ 2026-09-01 (commit `9346a76`) |
| 2.9.D | Columna "Acciones" de `tablaDocentes` (`03_docentes`, primer uso del patrón fuera de `02_estudiantes`) agrupada en dropdown de ícono — reemplaza 2 botones en línea (Editar + uno de Eliminar/Desactivar/Activar, mutuamente excluyentes) por `bi-gear-fill` + dropdown de Bootstrap con 2 ítems; a diferencia de A-C, esta columna nunca acumuló 3+ botones — se aplicó por decisión explícita de uniformidad visual (ver Decisiones arquitectónicas activas), sin funcionalidad nueva ni indicador visual tipo punto de color que conservar. CDN de Bootstrap Icons agregado a `doc_view.php` por primera vez. Sin cambios de backend | ✅ 2026-09-01 (commit `e1c232d`) |

### Phase 2.10 — Semestre de la matrícula (preparación de esquema + primer consumidor visible)

| Ítem | Descripción | Estado |
|---|---|---|
| 2.10.A | `matriculas.matr_semestre` + ENUM `matr_estado` ampliado con `'cursado'` (reservado, sin uso todavía) — preparación de esquema y validación server-side contra `programas.prog_duracion_semestres` para la futura acción "avanzar de semestre"; detalle completo en "Decisiones arquitectónicas activas" (`matriculas.matr_semestre`... / ENUM `matriculas.matr_estado` ampliado con `'cursado'`...) | ✅ 2026-09-02 (commit `c985188`) |
| 2.10.B | Columna "Semestre" en `tablaMatriculados` (`02_estudiantes`) — primer consumidor real en la UI de `matr_semestre`/`prog_duracion_semestres` (ambos agregados al `SELECT` de `listar_matriculados`), formato "N/M" entre "Programa" y "Período", resaltado `#cfe2ff` en el último semestre (mismo estilo inline que la columna Edad) | ✅ 2026-09-02 (commit `8884cb4`), verificado en navegador |
| 2.10.C | Pestaña única "Matriculados" dividida en "Matriculados (Per. Actual)" (período fijo al activo, sin selector) y "Matriculados (Per. Anteriores)" (select excluyendo el activo) — dos tablas independientes con la columna "Semestre" (2.10.B) visible por igual en ambas; primer consumidor real del patrón de `columns` compartido documentado en "Decisiones arquitectónicas activas" (`crearColumnasMatriculados()`); detalle completo de las 4 decisiones por ambigüedad en CHANGELOG.md | ✅ 2026-09-02 (commit `0aaa4e9`), verificado en navegador |
| 2.10.D | Semestre del estudiante en el reporte propio y en el boletín PDF (`06_reportes`) — `mis_programas`/`detalle_periodo` (`reportes_mdl.php`) agregan `matr_semestre`/`prog_duracion_semestres` al `SELECT` (mismo JOIN existente hacia `programas`); `formatoSemestre()` (`reportes_ctrl.js`) y `fmtSemestre()` (`pdf_boletin.php`) reutilizan el mismo formato "N/M" con fallback `'—'` ya usado en 2.10.B, sin resaltado de "último semestre" (atributo de la matrícula, no de una fila de listado); en el PDF, "Semestre: N/M" ocupa el par de celdas vacías ya reservadas junto a "Período", sin fila nueva (antipatrón de Dompdf con `colspan` mezclado). **Decisión explícita:** `05_calificaciones` (vista del docente) queda fuera de alcance — el semestre del grupo (`grse_semestre`, ya visible como "Sem.N") se conserva sin cambios; no se agrega `matr_semestre` ahí porque ambos valores deberían coincidir en la lógica de negocio, y mostrarlos por separado generaría ruido en vez de claridad. Detalle completo en CHANGELOG.md | ✅ 2026-09-02 (commit `27a3915`), verificado en navegador |
| 2.10.E | Acción "Matricular al siguiente semestre" (`02_estudiantes`) — pieza final del roadmap, cierra el ENUM `'cursado'` reservado desde `c985188` (ver "Decisiones arquitectónicas activas"). `validarSemestrePrograma()` extraída como función reutilizable (antes duplicada en `matricular`/`editar_matricula`, ahora usada también aquí); nuevo `case 'avanzar_semestre'` (solo coordinador/administrador) valida `matr_estado='matriculado'`, `matr_estado_academico='Activo'`, límite de semestre, período destino distinto y sin duplicado, y aprobación **recalculada con query directa** al momento de escribir (ver "Verificación anticipada para UI, recálculo confiable para escritura"); transacción PDO: fila anterior → `'cursado'`, `INSERT` de la nueva con `matr_semestre + 1` y mismo `coho_id` heredado. `crearColumnasMatriculados(esPeriodoActual)` gana un parámetro booleano (no viola la advertencia de esta misma sección contra funciones compartidas con parámetros condicionales — solo 1 de 13 columnas difiere); nuevo ítem de dropdown "⏩ Matricular al sgte. sem." visible solo en Per. Actual con 4 motivos de deshabilitado en cascada; nuevo modal `#mdl_avanzar_semestre` con selector de período destino poblado clonando las opciones de `#slct_filtro_matr_peri_id_anteriores` (sin exponer `periodoActivoId` fuera de su scope). Detalle completo en CHANGELOG.md | ✅ 2026-09-02 (commit `0aef564`), verificado en navegador |

Con 2.10.E, el roadmap completo de "semestre en la UI" queda cerrado
(`c985188` → `8884cb4` → `0aaa4e9` → `27a3915` → `0aef564`) — las 5
piezas, desde el esquema hasta la acción de avance, están
implementadas y verificadas en navegador.

### Phase 2.11 — Link de actualización de datos de estudiantes (5 fases, en curso)

> Objetivo: permitir que un estudiante actualice su propia ficha
> (`estudiantes` + `fichas_inscripcion`) mediante un link público (sin
> sesión), quedando la propuesta pendiente de que el coordinador la
> apruebe o descarte desde un nuevo ítem del dropdown de Acciones de
> `tablaMatriculados` (`02_estudiantes`). Precedido de un diagnóstico
> de solo lectura (sin commit) que mapeó el esquema de ambas tablas, el
> modal `#mdl_estudiante`, el patrón "público pero seguro" de
> `09_inscripcion_publica/`, el `case 'guardar_completo'`, el patrón de
> sincronización `usua_email` de `doc_mdl.php`, la estructura de
> `tablaMatriculados`, y la convención de nombres del proyecto.

| Ítem | Descripción | Estado |
|---|---|---|
| 2.11.A | Esquema — tabla `solicitudes_actualizacion` nueva (`database/emdb_academica.sql`), prefijo `soac_`/`soac_ficha_`: 10 columnas de control/auditoría, 17 espejo de `estudiantes` (excluye tipo/número de documento e identidad/sistema) y 35 espejo de `fichas_inscripcion` (excluye PK/FK/estado/auditoría), todas `NULLABLE`. Detalle completo de las 63 columnas y las 3 decisiones de diseño en CHANGELOG.md | ✅ 2026-09-02 (commit `3ba9f99`) |
| 2.11.B | Backend — 4 `case` nuevos en `est_mdl.php` (solo `role_id IN (1,2)`): `generar_link_actualizacion` (valida elegibilidad matriculado+Activo+período activo, invalida automáticamente cualquier solicitud previa activa, token de 64 hex chars con `bin2hex(random_bytes(32))`, expira en 24h), `estado_solicitud_actualizacion` (la más reciente del estudiante, `null` si nunca tuvo una), `aprobar_actualizacion` (aplica solo campos `soac_*` no nulos sin sobrescribir con `NULL`, calcula `soac_campos_modificados`, sincroniza `usua_email` si cambió, rollback total ante cualquier colisión) y `descartar_actualizacion` (borrado lógico). Nueva función compartida `sincronizarEmailUsuario()` en `helpers.php` (ver esa sección), que además corrige un bug real en `guardar_completo` (editar el correo de un estudiante con `usua_id` ahora sí sincroniza `usuarios.usua_email`). `listar_matriculados` agrega `soac_estado_activo` para el badge de la Fase D. Detalle completo, incluido el bug de `estudiantes.estu_email` encontrado en pruebas, en CHANGELOG.md | ✅ 2026-09-02 (commit `03e36fc`) |
| 2.11.C | Formulario público — módulo nuevo `11_actualizacion_datos/` (`actualizar_mdl.php`/`actualizar_view.php`/`actualizar_ctrl.js`), sin sesión (mismo criterio que `09_inscripcion_publica/`, ver la excepción documentada en "Vistas como `.php` en lugar de `.html`"). 3 `case`: `validar_token` (existencia + estado `'generado'` + expiración, responde `{status, motivo}` en vez del envelope estándar, precarga valores vigentes vía `LEFT JOIN` igual que `obtener_completo`), `guardar_actualizacion` (re-valida el token igual que `validar_token`, escribe solo en columnas `soac_*`/`soac_ficha_*` — nunca toca `estudiantes`/`fichas_inscripcion` directamente —, `UPDATE` condicional `WHERE soac_estado = 'generado'` contra doble submit) y `listar_programas` (catálogo público duplicado de `insc_mdl.php`). Bug encontrado y corregido durante el desarrollo: un `exit` dentro del `foreach` de validación de la ficha familiar cortaba el script en la primera iteración en vez de solo ante el primer campo `null` — corregido a bandera + `break`, mismo patrón que `guardar_completo`. Detalle completo, incluidos los 7 casos probados con `curl` sin sesión, en CHANGELOG.md | ✅ 2026-09-02 (commit `b739a60`), verificado en navegador por Jose Luis 4/4 puntos |
| 2.11.D | Frontend — conecta todo desde `tablaMatriculados` (`02_estudiantes`). Nuevo ítem de dropdown "🔗 Generar link actualizar datos" (tras "📝 Datos Estudiante", solo en Per. Actual, deshabilitado si `matr_estado_academico !== 'Activo'`). Nuevo modal `#mdl_actualizacion_datos` con 2 modos según `estado_solicitud_actualizacion`: "Ver y aprobar actualización" (si `'recibido'`) o "Generar link para actualización" (link + botón Copiar vía `navigator.clipboard`/`execCommand` fallback). Modo revisión de `#mdl_estudiante` **reutilizado, no un modal nuevo** (`abrirRevisionActualizacion()`/`poblarModalRevision()`/`restaurarModoNormalEstudiante()`, ver "Patrón de UI" más abajo) — campos propuestos resaltados con la clase nueva `.campo-actualizado` (amarillo), formulario deshabilitado, botones Aprobar/Descartar. 3 badges independientes y coexistentes en la columna Apellidos: 🎓 N programas (ya existente), 🔔 Link enviado/Pendiente aprobación (`soac_estado_activo`), y ✅ Actualizado [fecha] permanente sin ventana de tiempo (`soac_fecha_ultima_aprobacion`, pedido posterior al prompt original de esta fase, implementado y verificado en el mismo ciclo antes del commit). Fix heredado de la Fase 2: la URL de `generar_link_actualizacion` no tenía el prefijo real `/app_academica_emdb/app/` servido por Apache — único cambio en `est_mdl.php` en esta fase. Detalle completo en CHANGELOG.md | ✅ 2026-09-03 (commit `70c45ba`), verificado en navegador por Jose Luis 8+4 pasos |
| 2.11.E | Documentación de cierre del feature completo — sin código nuevo. Revisión de pendientes: la nota de la Fase 2 sobre "URL completa con dominio" (ver commit `03e36fc`) quedó resuelta por la Fase 4 sin necesidad de tocar el backend — el frontend concatena `window.location.origin + response.url` al mostrar el link, evitando por completo el riesgo de host-header injection que motivó dejarla pendiente. Hallazgo nuevo durante esta revisión, sin resolver: ver "Deuda técnica" (`#txt_ultima_actualizacion` vs. el badge "✅ Actualizado") | ✅ 2026-09-03 |

Con 2.11.E, el roadmap completo de "Link de actualización de datos de
estudiantes" queda cerrado (`3ba9f99` → `03e36fc` → `b739a60` →
`70c45ba`) — las 4 piezas, desde el esquema hasta el frontend del
coordinador, están implementadas y verificadas: backend probado con
`curl` contra el contenedor Docker vivo en cada fase, formulario
público y frontend verificados en navegador por Jose Luis. El feature
es utilizable de punta a punta: el coordinador genera el link desde
`tablaMatriculados`, lo copia y lo envía manualmente; el estudiante lo
abre sin necesidad de cuenta, revisa y envía su propuesta; el
coordinador la revisa con los campos resaltados y decide aprobarla o
descartarla — sin ningún pendiente abierto de este roadmap específico
salvo el hallazgo puntual documentado en "Deuda técnica" (no bloqueante,
una inconsistencia de presentación entre dos partes de la misma UI, no
un bug de datos).

### Phase 2.12 — Gestión de requisitos de estudiantes de matrícula (10 etapas, en curso)

> Objetivo: reemplazar las 9 columnas `req_*` fijas de `matriculas`
> (sin per-programa, sin descripción, sin edición posterior una vez
> creada la matrícula) por un catálogo de requisitos documentales
> configurable por programa (`requisitos_programa`, gestionado por el
> Admin) con un checklist editable por matrícula
> (`requisitos_estudiante`), visible tanto para el coordinador
> (`tablaMatriculados`, `02_estudiantes`) como para el propio
> estudiante (`06_reportes`). Precedido de un diagnóstico de solo
> lectura (sin commit) que confirmó que las 9 columnas legacy estaban
> aisladas al flujo de matriculación inicial, sin ningún lector en
> otros listados, reportes, PDFs, vistas, índices ni procedimientos
> almacenados del proyecto.

| Ítem | Descripción | Estado |
|---|---|---|
| 2.12.0 | Diagnóstico de solo lectura — confirma que las 9 columnas req_* legacy no tienen ningún lector fuera de est_mdl.php/est_view.php/est_ctrl.js | ✅ 2026-09-03 |
| 2.12.A | Esquema — elimina las 9 columnas req_* de matriculas; crea requisitos_programa (catálogo por programa, prefijo reqp_, borrado lógico) y requisitos_estudiante (estado por matrícula, prefijo reqe_, ancla en matr_id) | ✅ 2026-09-03 (commit `ea03618`) |
| 2.12.A2 | Retiro coordinado del flujo legacy en el mismo commit que 2.12.A: case 'matricular' deja de leer/escribir req_*, modal #mdl_matricular pierde los 9 checkboxes sin reemplazo aún, btn_confirmar_matricula deja de recolectarlos | ✅ 2026-09-03 (commit `ea03618`) |
| 2.12.B | Backend Admin — CRUD del catálogo requisitos_programa en est_mdl.php (solo role_id === 1), con backfill automático de requisitos_estudiante en 'pendiente' a las matrículas activas del programa al crear/reactivar un requisito | ✅ 2026-09-03 (commit `c4b81f2`) |
| 2.12.C | Backend Matrícula — case 'matricular' inserta automáticamente en requisitos_estudiante los requisitos activos del programa como 'pendiente' al crear una matrícula nueva | ✅ 2026-09-03 (commit `f14331d`) |
| 2.12.D | Backend Coordinador — nuevos case para listar/actualizar los requisitos de una matrícula puntual, y extender listar_matriculados con conteo x/y y fecha de última actualización para el badge | ✅ 2026-09-03 (commit `350c614`) |
| 2.12.E | Frontend Admin — nueva ficha "Configurar Requisitos" en est_view.php (select de programa + tabla + agregar/editar/borrar) | ✅ 2026-09-03 (commit `f3c0ca4`) |
| 2.12.F1 | Frontend Coordinador — columna "Requisitos" de solo lectura en tablaMatriculadosActual/Anteriores (badge x/y + fecha, después de "Información") | ✅ 2026-09-03 (commit `8450a24`) |
| 2.12.F2 | Frontend Coordinador — ítem "Requisitos" en dropdown Acciones + HTML del modal #mdl_requisitos_matricula (estructura, sin JS de guardado) | ✅ 2026-09-03 (commit `caa3cff`) |
| 2.12.F3 | Frontend Coordinador — JS: listar los requisitos de la matrícula en el modal, tabla con selects de estado editables, mensaje "x de y completados" | ✅ 2026-09-04 (commit `9196f18`) |
| 2.12.F4 | Frontend Coordinador — JS: wiring del guardado (actualizar_requisito_estudiante por fila) + refresco de la columna en la tabla principal al cerrar el modal | ✅ 2026-09-04 (commit `a5ef1e5`) |
| 2.12.G | Ajustes de columnas de tablaMatriculados — Edad/Programa→Prog/Semestre→Sem, ancho al contenido (independiente, sin dependencias) | ✅ 2026-09-04 (commit `4386b67`) |
| 2.12.H | Frontend Matrícula — agrega en #mdl_matricular una lista informativa de solo lectura de los requisitos del programa seleccionado (reemplazo funcional de los 9 checkboxes retirados en 2.12.A2) | ⬜ |
| 2.12.I | Frontend Estudiante — barra superior (rojo/verde con conteo pendiente) + sección de requisitos de solo lectura en reportes_view.php/reportes_ctrl.js, dentro de cada pestaña de programa, antes del selector de período | ⬜ |
| 2.12.J | Documentación de cierre del roadmap completo | ⬜ |

### Phase 3 — Validación TRL5

| Ítem | Descripción | Estado |
|---|---|---|
| 3.1 | Migración datos históricos 2 semestres | ⬜ |
| 3.2 | Pruebas con usuarios reales (coordinador + 5 docentes + 10 estudiantes) | ⬜ |
| 3.3 | Medición tiempos pre/post (prueba t de Student) | ⬜ |
| 3.4 | Aplicación escala SUS (target ≥ 68 puntos) | ⬜ |
| 3.5 | Video demostración ≤ 10 minutos | ⬜ |
| 3.6 | Actas de validación firmadas | ⬜ |

---

## Ciclo de trabajo con Claude IA

Cada tarea sigue este ciclo obligatorio de 6 fases:

| Fase | Responsable | Acción |
|---|---|---|
| 1. Diagnóstico | Claude IA genera prompt → Claude Code ejecuta | Solo lectura, sin cambios |
| 2. Análisis | Claude IA analiza resultados | Plantea opciones si las hay — Jose Luis elige |
| 3. Implementación | Claude IA genera prompt → Claude Code ejecuta | Aplica cambios |
| 4. Pruebas | Claude IA sugiere → Jose Luis ejecuta en navegador | Reporta resultados |
| 5. Commit | Claude IA entrega comandos → Jose Luis ejecuta desde su terminal en Fedora | Pega hash de confirmación |
| 6. Documentación | Claude IA identifica → Claude Code actualiza | Commit separado de docs |

**Regla crítica:** Nunca combinar diagnóstico y modificación en un mismo prompt.
Si el diagnóstico no revela problemas, Claude IA lo indica explícitamente antes de pasar a Fase 3.

