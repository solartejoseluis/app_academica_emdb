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
    03_docentes/       — Gestión del cuerpo docente ✅
    04_grupos/         — Programas (CRUD), cohortes, períodos académicos, grupos semestre, catálogo de módulos (CRUD) y asignación de estudiantes ✅
    05_calificaciones/ — Registro de notas por docente (módulo central) ✅
    06_reportes/       — Consulta notas estudiante + exportación Excel/PDF coordinador (con contexto de curso y docente) + boletín PDF estudiante + PDF de Ficha Familiar ✅
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
| `docentes` | Datos personales + sigla única para códigos de grupo |
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

> **Formulario público de inscripción:** 5 de 5 fases completas (reCAPTCHA, Ficha Familiar interna, migración `estu_origen`, formulario público en dos pasos, columna Origen en listado de Aspirantes, Ficha Familiar obligatoria + indicador de 3 estados con pruebas extremo a extremo).

> **Ficha del estudiante unificada:** los datos personales del estudiante y su Ficha Familiar (AC-FO-02) se editan ahora desde un único modal, con descarga en PDF integrada — antes eran dos pantallas separadas.

Ver historial detallado de cambios en [CHANGELOG.md](CHANGELOG.md).

---

## Convenciones de desarrollo

Las convenciones completas de código, base de datos, antipatrones y decisiones arquitectónicas están documentadas en [CLAUDE.md](CLAUDE.md) — ese archivo es la fuente de verdad para el desarrollo.
