// Funciones JS compartidas entre módulos (equivalente de helpers.php).
// Cargar en la vista ANTES de su _ctrl.js y de ayuda_sidebar.js.

// Todo texto que viene de la base pasa por aquí antes de concatenarse en
// HTML, sea como contenido o dentro de un atributo entre comillas (title,
// value, data-*). null/undefined -> ''. Única definición del proyecto: no
// declarar otra escaparHtml en un _ctrl.js (la que cargue después pisa a
// esta sin avisar). No sirve para texto dentro de un onclick="...('...')".
function escaparHtml(valor) {
    return String(valor === null || valor === undefined ? '' : valor)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// render de DataTables para columnas de texto de la base: escapa solo al
// mostrar (type 'display') y devuelve el dato crudo para ordenar y filtrar.
// null/undefined pasan tal cual, como en una columna sin render. Única
// definición del proyecto: no declarar otra en un _ctrl.js.
function renderTextoEscapado(data, type) {
    if (type !== 'display' || data === null || data === undefined) return data;
    return escaparHtml(data);
}

// Texto de presentación de gruposemestres.grse_jornada. El valor guardado
// ('Sabados', sin tilde) no cambia: es el que usan los selects y la fórmula
// de grse_codigo. Mismo criterio que etiquetaJornada() en helpers.php.
function etiquetaJornada(valor) {
    if (valor === null || valor === undefined || valor === '') return 'Semana';
    if (valor === 'Sabados') return 'Sábados';
    return valor;
}

// Traducción de DataTables: language: { url: URL_IDIOMA_DATATABLES }. Con
// https explícito porque la URL sin protocolo, desde una página http, recibe
// una redirección 301 sin cabecera CORS y el navegador la bloquea.
const URL_IDIOMA_DATATABLES = 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json';

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

// ── Selects de filtro armados desde las filas recibidas ──────────────────────
// Agrega al select una opción por cada valor distinto presente en las filas
// (gana la primera aparición). No borra las opciones que el select ya tenga.
// El texto va con .text(): nunca se interpreta como HTML. Solo requiere jQuery.
//   selector    — selector jQuery del <select>
//   filas       — arreglo de objetos (las filas de la respuesta)
//   campoValor  — propiedad de la fila que va como value de la opción
//   campoTexto  — propiedad de la fila que va como texto de la opción
//   comparar    — comparador para sort(); recibe objetos { valor, texto }
function poblarFiltro(selector, filas, campoValor, campoTexto, comparar) {
    const vistos = {};
    const opciones = [];
    filas.forEach(function (f) {
        if (vistos[f[campoValor]]) return;
        vistos[f[campoValor]] = true;
        opciones.push({ valor: f[campoValor], texto: f[campoTexto] });
    });
    opciones.sort(comparar);
    const slct = jQuery(selector);
    opciones.forEach(function (o) {
        slct.append(jQuery('<option>').val(o.valor).text(o.texto));
    });
}

// Abre la pestaña de Bootstrap que corresponde a location.hash.
//   mapa — { '#hash': 'selector del botón o enlace de la pestaña' }
// El hash solo se usa como clave del mapa, nunca como selector. Sin hash, con
// un hash desconocido o si la pestaña no se dibujó para este rol, no hace nada.
function activarPestaniaPorHash(mapa) {
    if (!Object.prototype.hasOwnProperty.call(mapa, location.hash)) return;
    const pestania = document.querySelector(mapa[location.hash]);
    if (pestania) {
        bootstrap.Tab.getOrCreateInstance(pestania).show();
    }
}

// Sesión invalidada en el servidor (usuario desactivado): exigirUsuarioActivo()
// en helpers.php responde { status: 'error', codigo: 'sesion_invalida' }.
// El prefiltro envuelve el success de cada $.ajax (incluidas las DataTables)
// para que esa respuesta no llegue al callback original; ajaxSuccess cubre las
// llamadas sin success propio. La bandera evita un alert por cada AJAX
// simultáneo.
let sesionInvalidaAtendida = false;

function esRespuestaSesionInvalida(data) {
    if (typeof data === 'string') {
        if (data.indexOf('sesion_invalida') === -1) return false;
        try { data = JSON.parse(data); } catch (e) { return false; }
    }
    return !!data && typeof data === 'object' && data.codigo === 'sesion_invalida';
}

function atenderSesionInvalida(data) {
    if (sesionInvalidaAtendida) return;
    sesionInvalidaAtendida = true;
    const respuesta = typeof data === 'string' ? JSON.parse(data) : data;
    alert(respuesta.message);
    window.location.href = '/app_academica_emdb/app/01_login/login_view.php';
}

if (typeof jQuery !== 'undefined') {
    jQuery.ajaxPrefilter(function (options) {
        const successOriginal = options.success;
        if (typeof successOriginal !== 'function') return;
        options.success = function (data) {
            if (esRespuestaSesionInvalida(data)) {
                atenderSesionInvalida(data);
                return;
            }
            return successOriginal.apply(this, arguments);
        };
    });
    jQuery(document).ajaxSuccess(function (event, jqXHR, settings, data) {
        if (esRespuestaSesionInvalida(data)) {
            atenderSesionInvalida(data);
        }
    });
}
