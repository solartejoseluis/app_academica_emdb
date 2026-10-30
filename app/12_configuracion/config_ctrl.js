$(document).ready(function () {

    cargarConfiguracion();

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
