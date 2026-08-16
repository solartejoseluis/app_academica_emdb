// Sidebar de ayuda compartido — se engancha al evento show.bs.offcanvas del
// offcanvas #offcanvasAyuda (definido en navbar.php, o duplicado en la vista
// cuando el rol no pasa por navbar.php, ej. Docente en 05_calificaciones).
// Cada _view.php que lo incluye debe declarar `const MODULO_ACTUAL = '...'`
// ANTES de este <script>, con el mismo valor que ayud_seccion en BD.

document.addEventListener('DOMContentLoaded', function () {
    const offcanvasEl = document.getElementById('offcanvasAyuda');
    if (!offcanvasEl) return;

    offcanvasEl.addEventListener('show.bs.offcanvas', function () {
        const contenedor = document.getElementById('contenido_ayuda_offcanvas');
        if (!contenedor) return;

        if (typeof MODULO_ACTUAL === 'undefined' || !MODULO_ACTUAL) {
            contenedor.innerHTML = '<p class="text-muted small">No hay ayuda disponible para esta página.</p>';
            return;
        }

        contenedor.innerHTML = '<div class="text-muted small">Cargando...</div>';

        $.ajax({
            type: 'POST',
            url: '../10_ayudas/ayud_mdl.php?accion=listar_por_modulo',
            data: { ayud_seccion: MODULO_ACTUAL },
            dataType: 'json',
            success: function (r) {
                if (r.status !== 'ok' || !r.data || !r.data.length) {
                    contenedor.innerHTML = '<p class="text-muted small">Aún no hay ayuda disponible para esta sección.</p>';
                    return;
                }
                let html = '';
                r.data.forEach(function (a) {
                    html += `
                        <div class="mb-3 pb-3 border-bottom">
                            <h6 class="fw-bold">${escaparHtml(a.ayud_titulo)}</h6>
                            <div style="white-space: pre-wrap">${escaparHtml(a.ayud_contenido)}</div>
                        </div>
                    `;
                });
                contenedor.innerHTML = html;
            },
            error: function () {
                contenedor.innerHTML = '<p class="text-danger small">Error al cargar la ayuda.</p>';
            }
        });
    });
});

function escaparHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto;
    return div.innerHTML;
}
