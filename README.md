# Sistema de Gestión Académica — Escuela de Mecánica Dental Bolaños
> **Sistema de Gestión Académica — Escuela de Mecánica Dental Bolaños**\
> Versión de la aplicación: 1.0 (congelada; la etiqueta git `v1.0` se crea al cerrar la Ola 7)\
> Estado: en producción; validación TRL5 realizada del 2026-09-19 al 2026-10-02\
> Documentos: [README.md](README.md) · [CLAUDE.md](CLAUDE.md) · [CHANGELOG.md](CHANGELOG.md) · [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md)

Sistema web de inscripción, matrícula y registro de calificaciones con acceso por roles.

> Versión congelada para la entrega del trabajo de grado.

---

## Contexto

Proyecto aplicado de Ingeniería de Sistemas (UNAD, CEAD Palmira). Automatiza la gestión académica de una escuela de educación para el trabajo de Tuluá (Valle del Cauca), que antes registraba las notas en planillas de hoja de cálculo separadas por grupo.

Metodología de desarrollo: CDIO (Concebir, Diseñar, Implementar, Operar).

---

## Funcionalidades por módulo

| Módulo | Qué hace |
|---|---|
| `01_login` | Inicio de sesión por correo y redirección según el rol. |
| `02_estudiantes` | Aspirantes, matriculados y retirados; ficha completa (AC-FO-02) con foto; matrícula, avance de semestre, retiro, aplazamiento y reincorporación; requisitos documentales; claves de acceso; link de actualización de datos. |
| `03_docentes` | Alta, edición, activación y clave de los docentes. |
| `04_grupos` | Programas, módulos, períodos, cohortes, grupos semestre, grupos módulo y asignación de estudiantes. |
| `05_calificaciones` | Planilla de notas por grupo módulo; N3 por actividades; exportación Excel y PDF del grupo. |
| `06_reportes` | Informe de notas del estudiante por programa y período, boletín PDF, reporte por grupo (Excel y PDF), PDF de Ficha de Inscripción y de Hoja de Matrícula (AC-FO-09). |
| `07_coordinador` | Tablero de seguimiento del estado de las notas por grupo. |
| `08_admin` | Usuarios administradores y coordinadores. |
| `09_inscripcion_publica` | Formulario público de inscripción (sin sesión) con reCAPTCHA v2 y detección de duplicados. |
| `10_ayudas` | Textos de ayuda que aparecen en el panel lateral de cada sección. |
| `11_actualizacion_datos` | Formulario público por link de un solo uso para que un estudiante proponga cambios a su ficha; el coordinador los aprueba o descarta. |
| `12_configuracion` | Panel del administrador: datos institucionales y, en un marco, Usuarios, Ayudas, Requisitos, Programas, Módulos, Períodos y Cohortes. |
| `app/health.php` | Comprobación de disponibilidad: responde 200 si la base de datos contesta y 503 si no, para un monitor externo. |

Las carpetas `00_*` son compartidas: conexión, componentes (`navbar.php`, `helpers.php`, `helpers.js`, `ayuda_sidebar.js`, `estilos.css`) y recursos.

---

## Roles y accesos

| Rol | Entra a | Puede |
|---|---|---|
| Administrador | `08_admin` | Todo lo del coordinador, más el panel `12_configuracion`: usuarios de rol 1 y 2, ayudas, datos institucionales y catálogo de requisitos. |
| Coordinador | `07_coordinador` | Estudiantes, docentes, grupos, todas las planillas de notas (cualquier período) y todos los reportes. |
| Docente | `05_calificaciones` | Solo sus grupos del período activo: registrar notas, configurar actividades N3 y exportar el reporte de su grupo. |
| Estudiante | `06_reportes` | Solo lectura: sus notas por período, su semestre, su boletín PDF y sus requisitos pendientes. |

Los módulos `09_inscripcion_publica` y `11_actualizacion_datos` no usan sesión: el primero crea aspirantes y el segundo identifica al estudiante con un token de un solo uso.

---

## Stack y entornos

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.5 con Apache |
| Base de datos | MySQL 8.0 en local; MariaDB en el hosting |
| Frontend | Bootstrap 5.3.3, Bootstrap Icons 1.11.3, jQuery 3.7 (CDN) |
| Tablas | DataTables 1.13.6, con Buttons 2.3.6 y JSZip 3.10.1 para Excel (CDN) |
| PDF | Dompdf 3.1 (Composer) |
| Imágenes | Extensión GD de PHP |
| Antispam | Google reCAPTCHA v2 |

No hay `package.json` ni paso de compilación del frontend.

| Entorno | Detalle |
|---|---|
| Local | Docker: `app` (PHP 8.5 + Apache + Composer), `db` (MySQL 8.0) y `phpmyadmin`. Zona horaria America/Bogota. |
| Hosting (dev y producción) | cPanel con MariaDB, administrado con phpMyAdmin. Cada entorno tiene sus propias credenciales, fuera del repositorio. |

---

## Instalación local

Requisitos: Docker con Docker Compose, y Git.

**1. Clonar**
```bash
git clone https://github.com/solartejoseluis/app_academica_emdb.git
cd app_academica_emdb
```

**2. Crear `app/00_connect/recaptcha_config.php`**

Está en `.gitignore` y la inscripción pública lo necesita (sin él, `09_inscripcion_publica` falla). Define dos constantes PHP:

- `RECAPTCHA_SITE_KEY`: clave pública de un sitio reCAPTCHA v2 ("No soy un robot").
- `RECAPTCHA_SECRET_KEY`: clave secreta del mismo sitio.

Las claves se obtienen en la consola de administración de Google reCAPTCHA, registrando `localhost` como dominio. Ver la [documentación de reCAPTCHA](https://developers.google.com/recaptcha/docs/display).

**3. Levantar los contenedores**
```bash
docker compose up -d --build
```

**4. Instalar las dependencias de Composer** (obligatorio: `vendor/` está fuera de git y sin él no se generan los PDF)
```bash
docker compose exec -w /var/www/html/app_academica_emdb app composer install
```
El `-w` es necesario: el proyecto se monta en `/var/www/html/app_academica_emdb`, no en el directorio de trabajo del contenedor.

**5. Permiso de la carpeta de fotos**
```bash
chmod 777 uploads/fotos_estudiantes
```
Apache corre en el contenedor con un usuario distinto del que creó la carpeta en el equipo.

**6. Entrar**

| Servicio | Dirección |
|---|---|
| Aplicación | `http://localhost:8120/app_academica_emdb/` (redirige al login) |
| phpMyAdmin | `http://localhost:8121/` |
| MySQL | `127.0.0.1:3310`, usuario `root` sin contraseña (solo local) |

**Base de datos inicial.** MySQL importa `database/emdb_academica.sql` solo la primera vez, con el volumen vacío. `docker compose down -v` borra el volumen y todos los datos cargados desde la aplicación. Respalde antes con `mysqldump`.

El seed trae la estructura completa, los catálogos (roles, programas, módulos, períodos, configuración, ayudas) y datos de ejemplo **anonimizados** (estudiantes, docentes, matrículas y notas ficticios). Las credenciales de los usuarios de ejemplo están en [CLAUDE.md](CLAUDE.md), sección "Entorno de desarrollo".

---

## Estructura de carpetas

```
app_academica_emdb/
  app/
    00_connect/  00_files/  00_img/  00_selects/   — compartidos
    01_login/ … 12_configuracion/                   — módulos
    health.php
  database/
    emdb_academica.sql   — seed anonimizado (24 tablas)
    hosting_deploy/      — respaldos con datos reales, fuera de git
  docker/Dockerfile
  docs/
  scripts/               — migraciones y correcciones de datos
  uploads/fotos_estudiantes/   — fotos, fuera de git
  vendor/                — Composer, fuera de git
  index.php              — redirige al login
  docker-compose.yml  composer.json  composer.lock
  CLAUDE.md  CHANGELOG.md  PROJECT_CONTEXT.md  README.md
```

Cada módulo sigue el patrón `*_view.php` (vista con control de sesión), `*_ctrl.js` (controlador jQuery) y `*_mdl.php` (modelo que responde JSON por `?accion=`).

---

## Modelo de datos

24 tablas, todas en `database/emdb_academica.sql`.

| Dominio | Tablas |
|---|---|
| Personas y acceso | `roles`, `usuarios`, `docentes`, `estudiantes`, `fichas_inscripcion`, `solicitudes_actualizacion` |
| Académico | `programas`, `modulos`, `programa_modulos`, `periodos`, `cohortes`, `gruposemestres`, `gruposmodulos`, `grmoestudiantes`, `horariosgrupo` |
| Matrícula y requisitos | `matriculas`, `requisitos_programa`, `requisitos_estudiante` |
| Calificaciones y N3 | `calificaciones`, `actividadesn3`, `notasn3` |
| Configuración y soporte | `configuracion`, `ayudas`, `metricasdesempeno` |

La cohorte, el semestre y el estado de cada estudiante viven en `matriculas` (una fila por período y programa). Los estudiantes de un grupo módulo se obtienen de `grmoestudiantes`.

---

## Reglas de negocio clave

- **Nota Final** = N1 (20 %) + N2 (20 %) + N3 (20 %) + N4 (40 %), escala 0.0 a 5.0 con un decimal, calculada siempre en el servidor.
- **Definitiva:** la Nota Final si es 3.0 o más; si no, la habilitación, que se registra a mano.
- **Supletorios:** solo N1, N2 y N4, y solo reemplazan una nota original de 0.0. N3 nunca tiene supletorio.
- **N3 por actividades:** el docente define actividades y N3 es su promedio. Ningún endpoint escribe `cali_n3` directamente. En los grupos con N3 histórico sin actividades no se puede crear la primera actividad.
- **Fechas por nota:** el servidor guarda la última fecha en que cambió cada nota. No se muestran en pantalla.
- **Retirado:** una matrícula está retirada si y solo si `matr_estado = 'retirado'`. Sale de las planillas del período activo y no recibe notas.
- **Requisitos:** al avanzar de semestre, la matrícula nueva hereda los requisitos documentales de la anterior.

Las reglas completas están en [CLAUDE.md](CLAUDE.md).

---

## Seguridad

- Consultas PDO con parámetros enlazados, sin interpolar datos en el SQL.
- Contraseñas guardadas como hash bcrypt.
- Control de sesión y de rol en cada vista y en cada endpoint `_mdl.php`.
- Cada petición autenticada comprueba en la base que el usuario siga activo y que su rol no haya cambiado; si no, cierra la sesión.
- Los textos de la base se escapan antes de mostrarse (XSS).
- La inscripción pública usa reCAPTCHA v2, verificado en el servidor; la actualización de datos exige un token de un solo uso con vencimiento.
- Métricas de disponibilidad (`health.php`) y de tiempo de respuesta (`app/00_connect/metrics_prepend.php`, que guarda cada petición en `metricasdesempeno` cuando el `.htaccess` del servidor lo activa).

---

## Despliegue

- Orden en cada entorno (local, dev, producción): respaldo de la base, script de migración, y después el código.
- Los scripts de migración son idempotentes y se verifican con `SHOW COLUMNS`: en el hosting, `information_schema` no es confiable.
- Se suben solo los archivos modificados.
- `app/00_connect/pdo_web.php`, con las credenciales de producción, existe solo en el servidor. Allí se renombra a `pdo.php`.
- El `.htaccess` de la raíz de producción y la línea de métricas (`auto_prepend_file`) no están versionados: no se sobrescriben.
- En producción solo se mira. Toda prueba que escribe datos se hace en local o en dev.

El checklist completo está en [CLAUDE.md](CLAUDE.md), sección "Checklist de deploy a producción".

---

## Scripts

| Script | Propósito |
|---|---|
| `export_para_hosting.sh` | Respaldo de la base local listo para phpMyAdmin del hosting; corrige la colación de MySQL 8 para MariaDB. |
| `migracion_matr_origen_id.sql` | Agrega `matriculas.matr_origen_id` (enlace de una reincorporación con su matrícula de origen). |
| `rollback_matr_origen_id.sql` | Reversa de la migración anterior. |
| `migracion_fechas_por_nota.sql` | Agrega las 8 columnas de fecha por nota a `calificaciones`; incluye su reversa comentada. |
| `backfill_requisitos_sucesoras.sql` | Completa los requisitos de las matrículas creadas al avanzar de semestre antes de que se heredaran. |
| `aplazamiento_reincorporacion_caso1.sql` | Corrección de datos de una reincorporación; la persona se indica con variables al ejecutar. |
| `restaurar_check_staging.sql` / `restaurar_check_produccion.sql` | Históricos (2026-09-08): agregaron los 11 `CHECK` de rango en el hosting. Ya no reflejan el estado de las bases y no se deben volver a ejecutar sin revisarlos (ver la deuda de los `CHECK` en [CLAUDE.md](CLAUDE.md)). |

---

## Datos personales y repositorio público

El repositorio es público. Por eso:

- El seed (`database/emdb_academica.sql`) contiene solo datos anonimizados.
- Los respaldos con datos reales viven en `database/hosting_deploy/`, ignorada por git.
- Nunca se versionan nombres, documentos ni contactos reales: ni en archivos, ni en nombres de archivo, ni en mensajes de commit.
- Los scripts que identifican a una persona usan variables que se completan al ejecutar.

El tratamiento de los datos se rige por la Ley 1581 de 2012 (protección de datos personales).

---

## Documentación del proyecto

- [CLAUDE.md](CLAUDE.md): convenciones, arquitectura, reglas de negocio, decisiones, deuda técnica y checklist de despliegue. Es la fuente de verdad para el desarrollo.
- [CHANGELOG.md](CHANGELOG.md): historial de cambios.
- [PROJECT_CONTEXT.md](PROJECT_CONTEXT.md): estado de avance, ruta de trabajo y commits.
