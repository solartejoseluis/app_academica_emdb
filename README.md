# Sistema de Gestión Académica — Escuela de Mecánica Dental Bolaños

Sistema web para la automatización de los procesos de inscripción, matrícula y registro de calificaciones de la Escuela de Mecánica Dental Bolaños (Tuluá, Valle del Cauca). Reemplaza 80 planillas manuales de Google Sheets por un sistema centralizado con acceso por roles.

> **Estado actual:** Fase de desarrollo — Sprint 1 completado. Sprint 2 en progreso.
> Proyecto de grado — Ingeniería de Sistemas, UNAD CEAD Palmira.

---

## Stack tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP | 8.0 |
| Base de datos | MySQL | 8.0 |
| Frontend CSS | Bootstrap | 5.3 (CDN) |
| Frontend JS | jQuery | 3.7 (CDN) |
| Tablas interactivas | DataTables | 1.13 (CDN) |
| Generación PDF | dompdf | vía Composer |
| Servidor local | XAMPP | Apache + PHP + MySQL |

---

## Cómo correrlo localmente

### Requisitos previos

- XAMPP instalado (Apache + PHP 8.0 + MySQL 8.0)
- Git

### Pasos

**1. Clonar el repositorio**
```bash
git clone https://github.com/[usuario]/app_academica_emdb.git C:/xampp/htdocs/app_academica_emdb
```

**2. Crear la base de datos**

Abrir phpMyAdmin (`http://localhost/phpmyadmin`) y ejecutar el script:
```
database/emdb_academica.sql
```

**3. Configurar la conexión**

El archivo de conexión local ya está configurado en:
```
app/00_connect/pdo.php
```

Verificar que los datos coincidan con tu instalación XAMPP:
```php
$host = 'localhost';
$dbname = 'emdb_academica';
$user = 'root';
$pass = '';
```

**4. Acceder al sistema**
```
http://localhost/app_academica_emdb/
```

El sistema redirige automáticamente al login.

**5. Usuario administrador inicial**

Ejecutar el script de datos iniciales:
```
database/seeds/datos_iniciales.sql
```

Esto crea el usuario administrador, los programas ASO y MD, y los módulos de cada programa.

---

## Estructura de módulos

```
app_academica_emdb/
  app/
    00_connect/        — Conexión a BD (local y producción)
    00_selects/        — Consultas SELECT reutilizables para dropdowns
    00_img/            — Recursos estáticos: logo, iconos
    00_files/          — favicon, robots.txt, .htaccess
    01_login/          — Autenticación y redirección por rol ✅
    02_estudiantes/    — Registro, edición y matrícula de estudiantes ✅
    03_docentes/       — Gestión del cuerpo docente ✅
    04_grupos/         — Cohortes, grupos semestre, módulos y asignación de estudiantes ✅
    05_calificaciones/ — Registro de notas por docente (módulo central) 🔄
    06_reportes/       — Consulta notas estudiante + exportación Excel coordinador (PDF pendiente — ítem 2.4) ✅
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

---

## Modelo de datos

El sistema organiza la información en torno a tres procesos principales:

### Proceso de admisión
```
programas ──< modulos
    │
    └──< cohortes ──< estudiantes ──< matriculas
```

### Configuración académica por semestre
```
gruposemestres (programa + periodo + jornada)
    │
    ├──< grseestudiantes >── estudiantes
    │
    └──< gruposmodulos (modulo + docente + fechas)
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
                  nota_definitiva
```

### Tablas principales

| Tabla | Descripción |
|---|---|
| `usuarios` | Credenciales de acceso. Vinculado 1:1 con docentes o estudiantes |
| `estudiantes` | Datos personales + cohorte de ingreso |
| `docentes` | Datos personales + sigla única para códigos de grupo |
| `programas` | Técnico en ASO y Técnico en MD — con resolución vigente |
| `modulos` | Asignaturas de cada programa (17 en ASO, 19 en MD) |
| `cohortes` | Grupos de admisión: formato `CH-ASO-2026A` |
| `gruposemestres` | Instancia de un programa en un período y jornada |
| `gruposmodulos` | Módulo + docente asignado + fechas dentro de un semestre |
| `calificaciones` | Notas N1–N4, supletorios y definitiva por estudiante-grupo |

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
- Todas las queries usan PDO con parámetros enlazados — sin interpolación directa
- Contraseñas hasheadas con bcrypt (`password_hash` / `password_verify`)
- La nota definitiva se recalcula en el servidor antes de persistir — el cliente no define calificaciones

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
| Phase 2 | Gestión académica: calificaciones, reportes, coordinador | 🔄 En progreso |
| Phase 3 | Validación TRL5 con usuarios reales | ⬜ Pendiente |

Ver historial detallado de cambios en [CHANGELOG.md](CHANGELOG.md).

---

## Convenciones de desarrollo

Las convenciones completas de código, base de datos, antipatrones y decisiones arquitectónicas están documentadas en [CLAUDE.md](CLAUDE.md) — ese archivo es la fuente de verdad para el desarrollo.
