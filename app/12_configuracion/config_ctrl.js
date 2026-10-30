$(document).ready(function () {

    // Sección → pantalla del marco. El hash solo se usa como clave de este
    // mapa, nunca como selector ni como URL. null = panel propio, sin marco.
    const SECCIONES = {
        '#institucion': null,
        '#usuarios':    '../08_admin/admin_view.php?embed=1',
        '#ayudas':      '../10_ayudas/ayud_view.php?embed=1',
        '#requisitos':  '../02_estudiantes/est_view.php?embed=1#config-requisitos',
        '#programas':   '../04_grupos/grupos_view.php?embed=1#programas',
        '#modulos':     '../04_grupos/grupos_view.php?embed=1#modulos',
        '#periodos':    '../04_grupos/grupos_view.php?embed=1#periodos',
        '#cohortes':    '../04_grupos/grupos_view.php?embed=1#cohortes'
    };
    const SECCION_POR_DEFECTO = '#institucion';
    const marco = document.getElementById('ifr_seccion');
    let urlMarco = null;

    cargarConfiguracion();
    abrirSeccion(location.hash);

    // ── Menú lateral ────────────────────────────────────────────────────────
    $('#bloque_menu_config a').on('click', function (e) {
        e.preventDefault();
        const hash = $(this).attr('href');
        if (hash !== location.hash) {
            history.pushState(null, '', hash);
        }
        abrirSeccion(hash);
        if ($('#btn_menu_config').is(':visible')) {
            bootstrap.Collapse.getOrCreateInstance(document.getElementById('bloque_menu_config'), { toggle: false }).hide();
        }
    });

    // Atrás/Adelante y cambios manuales de la URL
    window.addEventListener('hashchange', function () {
        abrirSeccion(location.hash);
    });

    // Si el marco terminó fuera del modo incrustado (sesión caducada: el guard lo
    // llevó al login), esa pantalla se abre en la ventana completa, no en el marco.
    marco.addEventListener('load', function () {
        const ubicacion = marco.contentWindow.location;
        if (ubicacion.href === 'about:blank') return;
        if (new URLSearchParams(ubicacion.search).get('embed') !== '1') {
            window.location.replace(ubicacion.href);
        }
    });

    // ── Guardar configuración institucional ─────────────────────────────────
    $('#btn_guardar_configuracion').click(function () {
        let institucion_nombre  = $('#npt_institucion_nombre').val().trim();
        let matr_numero_inicial = $('#npt_matr_numero_inicial').val();
        let director_nombre     = $('#npt_director_nombre').val().trim();
        let secretario_nombre   = $('#npt_secretario_nombre').val().trim();
        let largoInstitucion    = Array.from(institucion_nombre).length;

        if (largoInstitucion < 3 || largoInstitucion > 150) {
            alert('El nombre de la institución debe tener entre 3 y 150 caracteres.');
            return false;
        }
        if (!matr_numero_inicial || matr_numero_inicial <= 0 || !director_nombre || !secretario_nombre) {
            alert('Todos los campos son requeridos y el número inicial debe ser positivo.');
            return false;
        }

        $.ajax({
            type: 'POST',
            url: 'config_mdl.php?accion=guardar_configuracion',
            data: {
                institucion_nombre: institucion_nombre,
                matr_numero_inicial: matr_numero_inicial,
                director_nombre: director_nombre,
                secretario_nombre: secretario_nombre
            },
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    alert('Configuración guardada correctamente.');
                } else {
                    alert(response.message);
                }
            }
        });
    });

    // ── Funciones ─────────────────────────────────────────────────────────

    function abrirSeccion(hashSolicitado) {
        const conocida = Object.prototype.hasOwnProperty.call(SECCIONES, hashSolicitado);
        const hash = conocida ? hashSolicitado : SECCION_POR_DEFECTO;
        if (!conocida) {
            history.replaceState(null, '', hash);
        }

        $('#bloque_menu_config a').each(function () {
            const activa = $(this).attr('href') === hash;
            $(this).toggleClass('active', activa);
            if (activa) {
                $(this).attr('aria-current', 'true');
            } else {
                $(this).removeAttr('aria-current');
            }
        });

        const url = SECCIONES[hash];
        $('#bloque_institucion').toggleClass('d-none', url !== null);
        $(marco).toggleClass('d-none', url === null);
        // location.replace y no src: asignar src agrega entradas al historial
        // y Atrás retrocedería dentro del marco en vez de cambiar de sección.
        if (url !== null && url !== urlMarco) {
            urlMarco = url;
            marco.contentWindow.location.replace(new URL(url, location.href).href);
        }
    }

    function cargarConfiguracion() {
        $.ajax({
            type: 'POST',
            url: 'config_mdl.php?accion=obtener_configuracion',
            dataType: 'json',
            success: function (response) {
                if (response.status === 'ok') {
                    let c = response.data;
                    $('#npt_institucion_nombre').val(c.institucion_nombre);
                    $('#npt_matr_numero_inicial').val(c.matr_numero_inicial);
                    $('#npt_director_nombre').val(c.director_nombre);
                    $('#npt_secretario_nombre').val(c.secretario_nombre);
                } else {
                    alert(response.message);
                }
            }
        });
    }
});
