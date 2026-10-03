// Funciones JS compartidas entre módulos (equivalente de helpers.php).
// Cargar en la vista ANTES de su _ctrl.js.

// Texto de presentación de gruposemestres.grse_jornada. El valor guardado
// ('Sabados', sin tilde) no cambia: es el que usan los selects y la fórmula
// de grse_codigo. Mismo criterio que etiquetaJornada() en helpers.php.
function etiquetaJornada(valor) {
    if (valor === null || valor === undefined || valor === '') return 'Semana';
    if (valor === 'Sabados') return 'Sábados';
    return valor;
}
