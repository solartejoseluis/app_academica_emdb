# CLAUDE.md — app_academica_emdb

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

### ❌ Asumir que Dompdf puede leer cualquier archivo local

```php
// PROHIBIDO — sin configurar chroot, Dompdf solo lee dentro de su propio directorio de librería (vendor/)
$dompdf = new Dompdf();
$dompdf->loadHtml($html); // <img src="/ruta/absoluta/fuera/de/vendor/foto.jpg"> falla en silencio, sin excepción
```

Por defecto Dompdf restringe la lectura de archivos locales al chroot de su propia carpeta (`vendor/`). Configurar `setChroot()` a la raíz del proyecto (o al directorio que contiene los archivos a leer) antes de renderizar HTML con rutas de imagen fuera de `vendor/`.

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
| Exportación Excel incompleta en `06_reportes` | Exportación a Excel en `06_reportes` no incluye datos de curso ni docente — pendiente de revisión (hallazgo del 2026-07-05, no bloqueante). |
| ~~Auditoría PHP 8.1-8.5~~ (RESUELTO 2026-07-25) | Auditoría estática completa sobre los 22 archivos .php de app/: sin hallazgos (código 100% procedural, sin type hints, conexión BD 100% vía PDO). Prueba en runtime de dompdf (librería de terceros) con `display_errors=1` y `error_reporting=E_ALL`: PDF generado sin ningún warning ni deprecation. Compatibilidad con PHP 8.5 confirmada de punta a punta. |
| `recaptcha_config.php` sin integrar | Credenciales reCAPTCHA ya creadas (`app/00_connect/recaptcha_config.php`, hallazgo 2026-07-25/26). Es preparación para la Fase 3 del formulario público de inscripción — ver tabla de 5 fases más abajo en "Estado del roadmap". Fases 0-2 cerradas, Fases 3-5 pendientes. |
| ~~curl_close() deprecada~~ (RESUELTO 2026-08-01) | Igual que imagedestroy() (Fase Ficha Familiar), curl_close() no hace nada desde PHP 8.0 y genera warning de deprecación explícito en PHP 8.5 — el warning se imprime antes del json_encode() y rompe dataType:'json' en $.ajax. Eliminada de verificarRecaptcha() en insc_mdl.php. Patrón a vigilar en cualquier código nuevo que use curl. |
| ~~Gestión de períodos académicos sin CRUD~~ (RESUELTO 2026-08-07) | La tabla `periodos` no tenía ningún CRUD en la aplicación — solo existían los 3 períodos sembrados por el seed inicial (2025-1, 2025-2, 2026-1). Cualquier período nuevo debía insertarse manualmente vía SQL/phpMyAdmin. Implementado como pestaña "Períodos" dentro de `04_grupos` (crear/editar, sin eliminar — FK con `ON DELETE RESTRICT` desde `matriculas` y `gruposemestres`). |

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
| 2.7.5 | Pruebas extremo a extremo + documentación | ⬜ |

**Nota de diseño (Fase 2.7.3):** reutilizar `finc_codigotemporal`
(existente en `fichas_inscripcion`, VARCHAR(20), sin usar hasta ahora)
para permitir que el aspirante retome el formulario sin necesidad de
una cuenta completa. Al construir esta fase, evaluar si el campo
necesita una restricción UNIQUE (hoy no la tiene).

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

