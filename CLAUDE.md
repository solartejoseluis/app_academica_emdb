# CLAUDE.md — app_academica_emdb
> Última actualización: 2026-08-23 — último commit citado: 5de9a9e

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
    00_files/          — Componentes PHP compartidos: navbar.php (roles 1 y 2), favicon, robots.txt, .htaccess
    01_login/          — Autenticación por sesión y redirección por rol
    02_estudiantes/    — CRUD estudiantes + matrícula a programas
    03_docentes/       — CRUD docentes
    04_grupos/         — Cohortes, grupos semestre, grupos módulo
    05_calificaciones/ — Registro de notas por docente (módulo crítico)
    06_reportes/       — Generación PDF y exportación Excel/DataTables
    07_coordinador/    — Dashboard de seguimiento académico
    08_admin/          — Gestión de usuarios del sistema
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
| `estudiantes` | `estu_` | Datos personales + cohorte de ingreso + foto (`estu_foto`) + campos AC-FO-02 (`estu_expedidoen`, `estu_ciudadnac`, `estu_ocupacion`, `estu_estadocivil`, `estu_discapacidad`, `estu_multiculturalidad`) |
| `programas` | `prog_` | ASO y MD con resolución y fechas de vigencia |
| `modulos` | `modu_` | Asignaturas por programa con sigla |
| `cohortes` | `coho_` | Grupos de admisión: `CH-ASO-2024B` |
| `periodos` | `peri_` | Semestres académicos: `2026A`, `2026B` |
| `matriculas` | `matr_` | Relación estudiante-programa con estado |
| `gruposemestres` | `grse_` | Instancia de programa en período+jornada |
| `grseestudiantes` | — | Tabla puente N:M gruposemestres↔estudiantes |
| `gruposmodulos` | `grmo_` | Módulo + docente + fechas dentro de un grupo semestre |
| `calificaciones` | `cali_` | Notas por estudiante y grupo módulo |
| `horariosgrupo` | `hora_` | Horarios por grupo (solo jornada SEMA) |
| `fichas_inscripcion` | `finc_` | Datos familiares (padre/madre/acudiente) y estudios anteriores — formato AC-FO-02 |

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

### Validación de unicidad contra dos tablas relacionadas antes de guardar (formato + unicidad cruzada)

Cuando un campo de una entidad (ej. `estudiantes.estu_email`) debe ser único a nivel de negocio pero también podría colisionar con un campo equivalente en una tabla relacionada de distinta entidad (ej. `usuarios.usua_email`), validar unicidad contra AMBAS tablas antes de persistir — no solo contra la tabla que se está editando. Patrón:

1. Validar formato primero (`filter_var($valor, FILTER_VALIDATE_EMAIL)`), devolviendo el envelope de error de inmediato si falla — antes de tocar la BD.
2. `SELECT` de unicidad contra la tabla propia (excluyendo el propio ID en la rama editar).
3. `SELECT` de unicidad contra la tabla relacionada — si la entidad ya tiene una fila vinculada (ej. el estudiante ya tiene `usua_id`), excluir también ese ID relacionado para no autobloquear el guardado de un valor que la entidad ya tenía.
4. Solo si ambos chequeos pasan, proceder con el guardado.

Ejemplo: case `guardar` (crear y editar) en `est_mdl.php` y case `registrar` en `insc_mdl.php` (`09_inscripcion_publica`), commit `d62dde6` (2026-08-19) — valida `estu_email` contra `estudiantes.estu_email` y `usuarios.usua_email` antes de crear/editar un aspirante, excluyendo el propio `usua_id` en la rama editar.

### Remover un campo solo de la UI (dejando columna + backend intactos) cuando pertenece a una etapa fuera del alcance actual

Cuando un campo de BD ya tiene su columna y su wiring de backend (`_mdl.php`) funcionando, pero pertenece a una etapa del proceso que todavía no está en el alcance del sprint/fase actual (ej. datos de certificación antes de que exista el flujo de certificación), remover el campo solo de la UI (`_view.php`/`_ctrl.js`) es preferible a eliminar la columna o dejar código muerto sin explicar por qué. La columna sigue recibiendo `NULL`/vacío en cada guardado (el backend no cambia), y el campo queda listo para reactivarse con un cambio de alcance mínimo — solo HTML + JS, sin tocar BD ni PHP — el día en que esa etapa entre en alcance. La condición para que esto valga la pena (en vez de eliminar todo el feature) es que la remoción se documente explícitamente en el commit que la hace, con la razón y la expectativa de reactivación — sin esa nota, un campo sin UI es indistinguible de un descuido o código muerto real.

Ejemplo: `matr_folio`/`matr_numero` en `02_estudiantes` — removidos de la UI de "Completar Matrícula" en el commit `29b6ca6` (2026-08-07) con la decisión explícita registrada en el CHANGELOG ("se mantienen en est_mdl.php y en la tabla `matriculas` como campos opcionales, sin usarse desde la UI... listos para reactivarse sin cambios de backend/BD si se retoman en el futuro"). La Fase E (commit `afdcfc1`, 2026-08-23) confirmó que la estrategia funcionó: reactivar `matr_folio` fue solo agregar el input en ambos modales y el campo en el payload JS, sin tocar el `case 'matricular'` para ese campo específico — el backend ya lo esperaba desde 16 días antes.

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

Ejemplo: case `mis_modulos` en `06_reportes/reportes_mdl.php` — bug preexistente (no introducido en el commit que lo corrigió), corregido en `35d6672` (2026-08-19). Dejaba el selector de módulos del estudiante vacío sin ningún error visible en pantalla.

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

### Vistas con navbar fallback propio (no navbar.php compartido) necesitan sus propios componentes globales duplicados

- **Decisión:** navbar.php solo se incluye para roles 1/2 (Admin/Coordinador). Módulos multi-rol donde un rol adicional (ej. Docente en 05_calificaciones) no pasa por navbar.php tienen su propio `<nav>` fallback definido directamente en su _view.php. Cualquier componente global que se agregue a navbar.php (ej. el offcanvas de ayuda) y que también deba estar disponible para esos roles con navbar fallback debe duplicarse explícitamente en esa rama — no asumir que agregarlo solo a navbar.php cubre a todos los roles del sistema.
- **Nota:** Ejemplo: el offcanvas de ayuda se duplicó en la rama Docente de calificaciones_view.php (commit `8947710`, 2026-08-16), ya que Docente es la audiencia principal de ese módulo piloto y no pasa por navbar.php.
- **Estado:** Activa.

---

## Frontend stack

- Bootstrap 5.3 (CDN)
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
| 5. Commit | Claude IA entrega comandos → Jose Luis ejecuta en CMD | Pega hash de confirmación |
| 6. Documentación | Claude IA identifica → Claude Code actualiza | Commit separado de docs |

**Regla crítica:** Nunca combinar diagnóstico y modificación en un mismo prompt.
Si el diagnóstico no revela problemas, Claude IA lo indica explícitamente antes de pasar a Fase 3.

