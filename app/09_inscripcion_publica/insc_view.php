<?php
require_once '../00_connect/recaptcha_config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscripción — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">Escuela de Mecánica Dental Bolaños — Inscripción</span>
</nav>

<div class="container mt-4 mb-5">
    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <div id="form_inscripcion">
                <h5 class="mb-3">Formulario de Inscripción</h5>

                <h6 class="text-muted border-bottom pb-2 mb-3">Datos del Estudiante</h6>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipo de documento</label>
                        <select class="form-select" id="slct_estu_tipodoc">
                            <option value="">-- Seleccionar --</option>
                            <option value="CC">CC</option>
                            <option value="TI">TI</option>
                            <option value="CE">CE</option>
                            <option value="PA">PA</option>
                            <option value="NIT">NIT</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Número de documento <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="npt_estu_numerodoc" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombres <span class="text-danger">*</span></label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_nombres" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_apellidos" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" class="form-control" id="npt_fechanacimiento">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sexo</label>
                        <select class="form-select" id="slct_estu_sexo">
                            <option value="">-- Seleccionar --</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="npt_estu_telefono" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="npt_estu_email" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Ciudad de residencia</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ciudad" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Estrato</label>
                        <select class="form-select" id="slct_estu_estrato">
                            <option value="">--</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">EPS</label>
                    <input type="text" class="form-control texto-mayus" id="npt_estu_eps" autocomplete="off">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Expedido en</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_expedidoen" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad de nacimiento</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ciudadnac" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ocupación</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ocupacion" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estado civil</label>
                        <select class="form-select" id="slct_estu_estadocivil">
                            <option value="">-- Seleccionar --</option>
                            <option value="soltero">Soltero/a</option>
                            <option value="casado">Casado/a</option>
                            <option value="union_libre">Unión libre</option>
                            <option value="divorciado">Divorciado/a</option>
                            <option value="viudo">Viudo/a</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Discapacidad</label>
                        <select class="form-select" id="slct_estu_discapacidad">
                            <option value="1) SORDERA PROFUNDA">1) SORDERA PROFUNDA</option>
                            <option value="2) HIPOACUSIA A BAJA AUDICIÓN">2) HIPOACUSIA A BAJA AUDICIÓN</option>
                            <option value="3) BAJA VISIÓN DIAGNOSTICA">3) BAJA VISIÓN DIAGNOSTICA</option>
                            <option value="4) CEGUERA">4) CEGUERA</option>
                            <option value="5) PARÁLISIS CEREBRAL">5) PARÁLISIS CEREBRAL</option>
                            <option value="6) LESIÓN NEUROMUSCULAR">6) LESIÓN NEUROMUSCULAR</option>
                            <option value="7) DEFICIENCIA COGNITIVA(RETARDO EN EL DESARROLLO)">7) DEFICIENCIA COGNITIVA(RETARDO EN EL DESARROLLO)</option>
                            <option value="9) NO APLICA" selected>9) NO APLICA</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block" id="lbl_multiculturalidad">Multiculturalidad</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_no_aplica" data-valor="NO APLICA" checked>
                        <label class="form-check-label" for="chk_multi_no_aplica">No aplica</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_afro" data-valor="AFRODESCENDIENTE">
                        <label class="form-check-label" for="chk_multi_afro">Afrodescendiente</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_cabeza_familia" data-valor="CABEZA DE FAMILIA">
                        <label class="form-check-label" for="chk_multi_cabeza_familia">Cabeza de familia</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_desplazado" data-valor="DESPLAZADO">
                        <label class="form-check-label" for="chk_multi_desplazado">Desplazado</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_indigena" data-valor="INDÍGENA">
                        <label class="form-check-label" for="chk_multi_indigena">Indígena</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_frontera" data-valor="POBLACIÓN DE FRONTERA">
                        <label class="form-check-label" for="chk_multi_frontera">Población de frontera</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_room" data-valor="POBLACIÓN ROOM">
                        <label class="form-check-label" for="chk_multi_room">Población Room</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input chk-multicultural" type="checkbox" id="chk_multi_reinsertado" data-valor="REINSERTADO">
                        <label class="form-check-label" for="chk_multi_reinsertado">Reinsertado</label>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>"></div>
                </div>

                <button type="button" class="btn btn-primary" id="btn_enviar_inscripcion">Enviar Inscripción</button>
            </div>

            <div id="bloque_confirmacion" class="d-none">
                <div class="alert alert-success">
                    <h5 class="alert-heading">¡Inscripción registrada!</h5>
                    <p>Guarda este código — lo necesitarás para continuar con los datos familiares:</p>
                    <p class="fs-4 fw-bold"><span id="codigo_temporal_resultado"></span></p>
                </div>
                <a href="#" id="lnk_continuar_datos_familiares" class="btn btn-primary">Continuar con datos familiares</a>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="insc_ctrl.js"></script>
</body>
</html>
