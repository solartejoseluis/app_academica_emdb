-- Actualización de contenido de ayuda desactualizado (02_estudiantes)
-- + nueva entrada de Configuración Institucional (08_admin)
-- Generado 2026-08-24 — corrige entradas sin tocar desde el 16-18 de agosto

-- ayud_id 28 — Registrar un aspirante nuevo
UPDATE ayudas SET
  ayud_titulo = '¿Cómo registro un aspirante nuevo?',
  ayud_contenido = 'Ve a "+ Nuevo Estudiante" para abrir la Ficha de Inscripción (AC-FO-02), un único modal con 5 secciones: Datos del Estudiante, Multiculturalidad, Información sobre Familiares, Estudios anteriores, y Programa técnico al que ingresa. Completa todas las secciones y guarda — todo se registra en una sola operación, ya no son pasos separados. El aspirante queda en estado "aspirante", sin matrícula activa todavía.'
WHERE ayud_id = 28;

-- ayud_id 29 — Ficha Familiar (AC-FO-02)
UPDATE ayudas SET
  ayud_titulo = '¿Qué es la Ficha Familiar y cuándo se completa?',
  ayud_contenido = 'Las secciones "Información sobre Familiares" y "Estudios anteriores" de la Ficha de Inscripción reúnen los datos del padre, madre o acudiente, y los estudios previos del aspirante. El sistema no permite matricular a un estudiante hasta que todos los campos obligatorios de estas secciones estén completos — verás un indicador de estado (sin iniciar / incompleta / completa) en el listado de aspirantes. Para editar esta información en un estudiante ya existente, usa el botón "📝 Datos Estudiante", que abre el mismo modal con todo precargado.'
WHERE ayud_id = 29;

-- ayud_id 30 — Matricular a un estudiante
UPDATE ayudas SET
  ayud_titulo = '¿Cómo matriculo a un estudiante?',
  ayud_contenido = 'Una vez la Ficha Familiar está completa, usa la opción de matrícula. Selecciona Programa, Cohorte y Período (los tres son obligatorios), completa el Folio, la Fecha de matrícula y las Observaciones si aplica, y define la clave de acceso: automática (apellido + fecha de nacimiento) o manual. Al guardar, puedes generar la Hoja de Matrícula en PDF (AC-FO-09) desde el mismo modal — el número de matrícula se asigna automáticamente la primera vez que generas ese documento.'
WHERE ayud_id = 30;

-- ayud_id 31 — Eliminar aspirante/estudiante
UPDATE ayudas SET
  ayud_titulo = '¿Puedo eliminar un aspirante o estudiante?',
  ayud_contenido = 'Solo se puede eliminar un aspirante que no tenga matrícula activa. Un estudiante ya matriculado no puede eliminarse — la información académica debe conservarse. Si necesitas corregir datos de una matrícula existente (Programa, Cohorte, Período, Folio, número de matrícula), usa "Editar Matrícula" en vez de eliminar y volver a registrar.'
WHERE ayud_id = 31;

-- ayud_id 44 — Cambiar clave de acceso
UPDATE ayudas SET
  ayud_titulo = '¿Cómo cambio la clave de acceso de un estudiante ya matriculado?',
  ayud_contenido = 'Abre "📝 Datos Estudiante" sobre el estudiante matriculado. En la parte inferior del modal encontrarás el bloque "Cambiar clave de acceso" (solo visible si el estudiante ya tiene acceso al sistema). Elige "Generar clave automática" o "Asignar clave manual"; si dejas ambas opciones sin marcar, la clave actual no se modifica.'
WHERE ayud_id = 44;

-- Nueva entrada — Configuración Institucional (08_admin)
INSERT INTO ayudas (ayud_seccion, ayud_titulo, ayud_contenido, ayud_orden, ayud_activo)
VALUES (
  '08_admin',
  '¿Qué es la Configuración Institucional?',
  'Debajo de la tabla de usuarios encontrarás el bloque "Configuración Institucional", con tres campos: número de matrícula inicial, nombre del Director y nombre del Secretario(a). Estos datos alimentan la Hoja de Matrícula en PDF (AC-FO-09) que se genera desde el módulo de Estudiantes. El número de matrícula inicial solo aplica hacia adelante — cambiarlo no afecta las matrículas que ya fueron generadas.',
  6,
  1
);
