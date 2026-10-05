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

// ── Fechas en DataTables ─────────────────────────────────────────────────────
// El servidor entrega la fecha cruda ('Y-m-d H:i:s', 'Y-m-d' o null) y cada
// columna de fecha se declara así:
//   { data: 'campo', type: 'fecha-emdb', render: renderFecha }
// display/filter muestran d/m/Y H:i (o d/m/Y si no hay hora); sort/type usan
// el texto ISO, que ordena bien como texto. Sin new Date(): 'Y-m-d' se
// interpretaría como UTC y podría mostrar el día anterior.
function renderFecha(data, type) {
    const vacio = (data === null || data === undefined || data === '');
    if (type !== 'display' && type !== 'filter') {
        return vacio ? '' : data;
    }
    if (vacio) return type === 'display' ? '—' : '';

    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/.exec(String(data));
    if (!m) return data; // formato inesperado: se muestra tal cual
    const fecha = m[3] + '/' + m[2] + '/' + m[1];
    return m[4] === undefined ? fecha : fecha + ' ' + m[4] + ':' + m[5];
}

// Orden de 'fecha-emdb': las fechas vacías quedan SIEMPRE al final, en
// ascendente y en descendente. No definir 'fecha-emdb-pre': con un -pre,
// DataTables 1.13 compara los valores directamente y no usa estas funciones.
function compararFechaAsc(a, b) {
    if (a === b) return 0;
    if (a === '') return 1;
    if (b === '') return -1;
    return a < b ? -1 : 1;
}
function compararFechaDesc(a, b) {
    if (a === b) return 0;
    if (a === '') return 1;
    if (b === '') return -1;
    return a < b ? 1 : -1;
}

// Hay vistas que cargan este archivo sin DataTables (06_reportes para el
// estudiante): en ese caso el registro se omite.
if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.dataTable) {
    jQuery.fn.dataTable.ext.type.order['fecha-emdb-asc']  = compararFechaAsc;
    jQuery.fn.dataTable.ext.type.order['fecha-emdb-desc'] = compararFechaDesc;
}
