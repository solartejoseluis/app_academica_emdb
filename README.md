# Sistema de Gestión Académica — Escuela de Mecánica Dental Bolaños

Sistema web para la automatización de los procesos de inscripción, matrícula y registro de calificaciones de la Escuela de Mecánica Dental Bolaños (Tuluá, Valle del Cauca). Reemplaza 80 planillas manuales de Google Sheets por un sistema centralizado con acceso por roles.

> **Estado actual:** Fase de desarrollo — Fases 0 a 2 completadas (infraestructura, módulos base y gestión académica). Fase 3 (validación TRL5) pendiente.
> Proyecto de grado — Ingeniería de Sistemas, UNAD CEAD Palmira.

---

## Stack tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP | 8.5 |
| Base de datos | MySQL | 8.0 |
| Frontend CSS | Bootstrap | 5.3 (CDN) |
| Frontend íconos | Bootstrap Icons | 1.11.3 (CDN) |
| Frontend JS | jQuery | 3.7 (CDN) |
| Tablas interactivas | DataTables | 1.13 (CDN) |
| Generación PDF | dompdf | vía Composer |
| Procesamiento de imágenes | GD (extensión PHP) | Dockerfile |
| Servidor local | Docker (Fedora 44) | PHP 8.5 + Apache + MySQL 8.0 |
| Zona horaria | America/Bogota | UTC-5, offset fijo (sin horario de verano) |

---

## Cómo correrlo localmente

### Requisitos previos

- Docker y Docker Compose instalados
- Git

### Pasos

**1. Clonar el repositorio**
```bash
git clone https://github.com/solartejoseluis/app_academica_emdb.git
cd app_academica_emdb
```

`docker-compose.yml` y `docker/Dockerfile` ya están incluidos en el repositorio — no hace falta crearlos.

**2. Levantar los contenedores**
```bash
docker compose up -d --build
```

Esto construye la imagen `app` (PHP 8.5 + Apache) y levanta los 3 servicios: `app` (aplicación), `db` (MySQL 8.0 — importa `database/emdb_academica.sql` automáticamente en el primer arranque) y `phpmyadmin`.

**3. Instalar dependencias de Composer**
```bash
docker compose exec app composer install
```

**4. Verificar la conexión**

El archivo `app/00_connect/pdo.php` ya está configurado para el entorno Docker:
```php
$host = '127.0.0.1';
$dbname = 'emdb_academica';
$user = 'root';
$pass = '';
```

> Se usa `127.0.0.1` en vez de `localhost` — con `network_mode: service:app` en `docker-compose.yml`, `localhost` fuerza a PDO a buscar un socket Unix inexistente entre contenedores.

**5. Acceder al sistema**
```
http://localhost:8120/app_academica_emdb/
```

El sistema redirige automáticamente al login. phpMyAdmin queda disponible en `http://localhost:8121/`.

El usuario administrador inicial ya viene sembrado junto con los datos de configuración (roles, programas ASO y MD, módulos) al importarse `database/emdb_academica.sql`. Para las credenciales, consultar `CLAUDE.md` o solicitarlas al responsable del proyecto.

---

## Estructura de módulos

```
app_academica_emdb/
  app/
    00_connect/        — Conexión a BD (local y producción)
    00_selects/        — Consultas SELECT reutilizables para dropdowns
    00_img/            — Recursos estáticos: logo, iconos
    00_files/          — Componentes PHP compartidos: `navbar.php` (navbar unificado roles 1 y 2), `estilos.css` (texto-mayus), favicon, robots.txt, .htaccess
    01_login/          — Autenticación y redirección por rol ✅
    02_estudiantes/    — Registro, matrícula y ficha completa del estudiante (datos personales + Ficha Familiar AC-FO-02 en un solo modal) con foto y descarga en PDF ✅
    03_docentes/       — Gestión del cuerpo docente, incluye reseteo de contraseña y edición de correo de acceso ✅
    04_grupos/         — Programas (CRUD), cohortes, períodos académicos, grupos semestre, catálogo de módulos (CRUD) y asignación de estudiantes ✅
    05_calificaciones/ — Registro de notas por docente (módulo central) ✅
    06_reportes/       — Informe de calificaciones por programa/período/módulo, ya sea autoconsulta del estudiante o consulta del coordinador a cualquier estudiante (con buscador), con boletín PDF por período; reporte por grupo módulo con exportación Excel/PDF (contexto de curso y docente); PDF de Ficha Familiar; PDF de Hoja de Matrícula (AC-FO-09) ✅
    07_coordinador/    — Dashboard de seguimiento académico ✅
    08_admin/          — Gestión de usuarios del sistema ✅
  database/
    emdb_academica.sql — Script DDL completo (16 tablas)
    seeds/             — Datos iniciales de configuración
  CLAUDE.md
  README.md
  CHANGELOG.md
```

---

## Roles de usuario

| Rol | Acceso al sistema | Permisos |
|---|---|---|
| **Administrador** | Panel de administración | CRUD de usuarios, configuración global del sistema |
| **Coordinador** | Dashboard de coordinación | Seguimiento de notas por docente, reportes generales, edición de planillas, generación de PDF |
| **Docente** | Módulo de calificaciones | Solo sus grupos asignados — ingreso y edición de notas |
| **Estudiante** | Módulo de consulta | Solo lectura de sus propias calificaciones en tiempo real |

**Acceso de estudiantes:** al matricular a un estudiante, el sistema crea su acceso usando el correo electrónico real registrado en su ficha (no un correo interno generado) y una clave inicial autogenerada.

**Acceso de docentes:** al crear un docente se genera su acceso con una contraseña inicial obligatoria; Administrador y Coordinador pueden resetearla en cualquier momento desde "Editar Docente" (campo opcional — vacío no cambia la contraseña actual). El correo electrónico también es editable desde ese mismo modal — es el identificador de login del docente, con validación de unicidad antes de guardar.

---

## Modelo de datos

El sistema organiza la información en torno a tres procesos principales:

### Proceso de admisión
```
programas ──< modulos
    │
    └──< cohortes ──< estudiantes ──< matriculas
                           │
                           └── fichas_inscripcion  (1:1)
```
> La cohorte de ingreso ahora se resuelve por cada matrícula, no de forma
> única por estudiante — un estudiante que curse más de un programa técnico
> puede tener una cohorte distinta en cada uno. El diagrama simplifica esta
> relación mostrando el caso más común (un solo programa por estudiante);
> matricular a un estudiante en un segundo programa ya es posible desde la
> interfaz (ver "Estado del proyecto" más abajo).

### Configuración académica por semestre
```
gruposemestres (programa + periodo + jornada)
    │
    └──< gruposmodulos (modulo + docente + fechas)
             │
             └──< grmoestudiantes >── estudiantes
```

### Registro de calificaciones
```
gruposmodulos ──< calificaciones >── estudiantes
                     │
                  nota1 (20%)
                  nota2 (20%)
                  nota3 (20%)  ← sin supletorio
                  nota4 (40%)
                  supletorios (N1, N2, N4)
                  nota_final       ← siempre calculada, apruebe o no
                  habilitación     ← manual, solo si nota_final < 3.0
                  definitiva       ← valor oficial (nota_final o habilitación)
```

### Tablas principales

| Tabla | Descripción |
|---|---|
| `usuarios` | Credenciales de acceso. Vinculado 1:1 con docentes o estudiantes |
| `estudiantes` | Datos personales + cohorte de ingreso |
| `docentes` | Datos personales + cédula (opcional, única) + sigla única para códigos de grupo |
| `programas` | Técnico en ASO y Técnico en MD — con resolución, fecha de aprobación/vencimiento, descripción y estado activo/inactivo |
| `modulos` | Asignaturas de cada programa (17 en ASO, 19 en MD) |
| `cohortes` | Grupos de admisión: formato `CH-ASO-2026A` |
| `gruposemestres` | Instancia de un programa en un período y jornada |
| `gruposmodulos` | Módulo + docente asignado + fechas dentro de un semestre |
| `calificaciones` | Notas N1–N4, supletorios, nota final, habilitación y definitiva por estudiante-grupo |
| `fichas_inscripcion` | Datos familiares (padre/madre/acudiente) y estudios anteriores — formato AC-FO-02 |

---

## Programas técnicos

### Técnico Laboral en Auxiliar en Salud Oral (ASO)
17 módulos — incluyendo Anatoclusión, Biomateriales, Periodoncia, Radiología, Práctica.

### Técnico Laboral en Mecánica Dental (MD)
19 módulos — incluyendo Morfología, Prótesis Total, Prótesis Flexible, Ortopedia, Integradas.

---

## Arquitectura

El sistema sigue el patrón **MVC** con tres archivos por módulo funcional:

```
_view.php   →  Vista PHP con Bootstrap. Verifica sesión al inicio.
_mdl.php    →  Modelo PHP. Recibe acción vía GET, ejecuta query PDO, retorna JSON.
_ctrl.js    →  Controlador jQuery. Llama AJAX, parsea respuesta, actualiza DOM/DataTable.
```

### Flujo de una operación típica

```
Usuario interactúa con la vista
    → _ctrl.js recolecta datos del formulario
    → $.ajax POST a _mdl.php?accion=guardar
    → _mdl.php valida, ejecuta query PDO parametrizada
    → retorna { status: 'ok', data: [...] }
    → _ctrl.js actualiza DataTable o muestra mensaje
```

### Seguridad

- Todas las vistas verifican sesión y rol en el servidor antes de renderizar
- Los endpoints `_mdl.php` de los módulos con datos sensibles (`02_estudiantes`, `03_docentes`, `04_grupos`, `05_calificaciones`, `07_coordinador`, `08_admin`) también validan sesión y rol antes de ejecutar cada acción, no solo las vistas — así una acción no puede invocarse directamente sin pasar por el control de acceso
- Todas las queries usan PDO con parámetros enlazados — sin interpolación directa
- Contraseñas hasheadas con bcrypt (`password_hash` / `password_verify`)
- La nota definitiva se recalcula en el servidor antes de persistir — el cliente no define calificaciones
- Operaciones destructivas (ej. eliminar aspirante en `02_estudiantes`) verifican en el servidor las condiciones de negocio antes de ejecutar el DELETE — no dependen de que el frontend oculte el botón

---

## Contexto del proyecto

**Institución beneficiaria:** Escuela de Mecánica Dental Bolaños, Tuluá, Valle del Cauca

**Problema que resuelve:**
La institución gestionaba 80 planillas individuales de Google Sheets por semestre, requiriendo 10–14 horas de configuración manual al inicio de cada período. Los docentes no tenían un punto centralizado de registro y los estudiantes dependían de WhatsApp para conocer sus calificaciones.

**Impacto esperado (validación TRL5):**
- Reducción del 90%+ en tiempo de configuración semestral de planillas
- Acceso en tiempo real a calificaciones para 80 estudiantes
- Dashboard de seguimiento para la coordinación sin revisar planilla por planilla

**Metodología de desarrollo:** CDIO (Concebir, Diseñar, Implementar, Operar)

**Validación:** TRL5 — operación continua 2 semanas con datos históricos reales, prueba SUS (≥68 puntos) y prueba t de Student (p < 0.05)

---

## Estado del proyecto

| Phase | Descripción | Estado |
|---|---|---|
| Phase 0 | Setup: estructura, BD, login, admin | ✅ Completado |
| Phase 1 | Módulos base: estudiantes, docentes, grupos | ✅ Completado |
| Phase 2 | Gestión académica: calificaciones, reportes, coordinador | ✅ Completado |
| Phase 3 | Validación TRL5 con usuarios reales | ⬜ Pendiente |

> **Formulario público de inscripción:** un aspirante sin cuenta diligencia su inscripción completa (datos personales, familiares y académicos) en una sola pantalla, con verificación reCAPTCHA y detección de duplicados por documento/correo, quedando pendiente de revisión y aprobación del coordinador. Rediseñado a un solo paso el 2026-08-24 — el diseño original en dos pasos (con código temporal para retomar después) fue reemplazado por un único formulario con un solo guardado. El 2026-08-26 se sincronizó además la disposición de campos de la Sección 1 con el modal interno de edición de `02_estudiantes` (mismo formato AC-FO-02, backend propio) — ambos formularios muestran ahora el mismo orden, agrupación y texto de labels.

> **Ficha del estudiante unificada:** los datos personales del estudiante y su Ficha Familiar (AC-FO-02) se editan ahora desde un único modal, con descarga en PDF integrada — antes eran dos pantallas separadas. La matrícula del estudiante (folio, fecha, observaciones, número institucional) y la configuración de la institución (Director, Secretario(a), número de matrícula inicial) alimentan la exportación en PDF de la Hoja de Matrícula (AC-FO-09), con el número de matrícula asignado automáticamente la primera vez que se genera el documento.

> **Grupos Semestre — conteo de estudiantes:** el listado de la pestaña "Grupos Semestre" (`04_grupos`) ahora muestra el número de estudiantes matriculados en cualquiera de sus módulos activos, con un botón que abre un modal de detalle ("Apellidos, Nombres") — mismo patrón ya usado en el conteo de "Módulos" de Matriculados (`02_estudiantes`).

> **Preparación de base de datos — multi-programa:** se amplió el modelo de datos para que un mismo estudiante pueda cursar más de un programa técnico a la vez, cada uno con su propia cohorte de ingreso y su propia condición académica del semestre. La condición académica del semestre ya es visible (de solo lectura) en el informe de calificaciones del estudiante, pero todavía no existe ninguna pantalla para que el coordinador la edite — a diferencia de la matrícula a un segundo programa en sí, que ya sí se gestiona completamente desde la interfaz (ver nota siguiente).

> **Informe de Calificaciones rediseñado:** el estudiante ahora ve sus notas organizadas por programa y por período académico (no módulo por módulo, como antes) — cada período muestra su condición general (en curso, aprobado o reprobado) y permite descargar en un solo PDF todos los módulos cursados en ese período. El coordinador puede consultar el mismo informe para cualquier estudiante mediante un buscador por nombre, apellido o documento, además de conservar el reporte por grupo completo que ya existía (ahora como una pestaña separada dentro de la misma pantalla).

> **Matrícula a un segundo programa técnico:** ya es posible, desde la interfaz, matricular a un estudiante que ya cursa un programa (ej. Mecánica Dental) en un segundo programa distinto (ej. Auxiliar en Salud Oral) — antes esto solo era posible a nivel de base de datos, sin ninguna pantalla que lo permitiera. El coordinador ve, al momento de matricular, qué programas ya cursa el estudiante, y el sistema impide por error matricularlo dos veces en el mismo programa. La ficha del estudiante también muestra ahora un resumen de todos los programas en los que está matriculado. En el listado de Matriculados, un estudiante con más de un programa se identifica de inmediato con una etiqueta junto a su nombre ("🎓 2 programas"), que muestra el detalle al pasar el cursor — antes cada matrícula aparecía como una fila suelta, sin nada que las conectara visualmente como la misma persona.

> **Acciones de Matriculados agrupadas en un menú:** en el listado de Matriculados, los botones de acción por estudiante (antes en línea, uno junto a otro) ahora viven detrás de un único botón con ícono de tuerca, que despliega un menú con las mismas 4 opciones. "Hoja de Matrícula" ahora es accesible con un clic directo desde ese menú, sin necesidad de abrir primero la ficha completa del estudiante. El menú también deshabilita, con una nota explicativa al pasar el cursor, la opción de matricular en otro programa cuando el estudiante ya cursa todos los programas activos disponibles.

> **Acciones de Aspirantes agrupadas en un menú:** el mismo cambio se aplicó al listado de Aspirantes — los 2 botones en línea ("Matricular", "Datos Estudiante") ahora viven detrás del mismo botón con ícono de tuerca, con un menú de 3 opciones. "Ficha de Inscripción" ahora es accesible con un clic directo desde ese menú, sin necesidad de abrir primero la ficha completa del estudiante.

> **Acciones de Docentes agrupadas en un menú:** el listado de Docentes recibió el mismo cambio visual — a diferencia de las dos notas anteriores, aquí no se agrega ninguna funcionalidad nueva ni un acceso directo que antes estuviera escondido en otro lugar. Es una mejora de consistencia visual: la columna de acciones por docente (Editar, más una de Eliminar/Desactivar/Activar según el caso) nunca tuvo tantos botones como para justificar el cambio por sí sola, pero se aplicó igual para que todas las tablas de la aplicación se vean y se comporten de la misma manera.

> **Semestre de la matrícula:** cada matrícula ahora registra en qué semestre del programa va el estudiante, con un límite que depende de la duración real de cada programa técnico (el sistema no deja elegir un semestre que no existe). Ya es visible en el listado de Matriculados, como una columna con formato "3/5" (semestre actual sobre el total del programa) resaltada cuando el estudiante está en su último semestre. Es un paso de preparación para, más adelante, poder matricular directamente a un estudiante a su siguiente semestre dentro del mismo programa cuando apruebe el actual — esa funcionalidad de avance todavía no está implementada.

Ver historial detallado de cambios en [CHANGELOG.md](CHANGELOG.md).

---

## Convenciones de desarrollo

Las convenciones completas de código, base de datos, antipatrones y decisiones arquitectónicas están documentadas en [CLAUDE.md](CLAUDE.md) — ese archivo es la fuente de verdad para el desarrollo.
