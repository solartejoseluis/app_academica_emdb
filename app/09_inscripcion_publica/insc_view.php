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
                <div class="mb-4">
                    <h4 class="mb-2">FICHA DE INSCRIPCIÓN — código AC-FO-02</h4>
                    <div class="alert alert-info mb-0">
                        Diligencia esta ficha con tus datos personales y los de tu
                        núcleo familiar. Todos los campos marcados son obligatorios;
                        si tu padre, tu madre o tu acudiente no aplican, desmarca la
                        casilla correspondiente y esos campos se ocultarán
                        automáticamente. <strong>Ten cuidado: si cierras o recargas
                        esta página sin enviar el formulario, los datos ingresados se
                        perderán</strong> — no hay un código para retomarlo más
                        tarde, así que completa la ficha en una sola sesión. Revisa
                        la información antes de pulsar "Enviar Inscripción".
                    </div>
                </div>

                <h6 class="seccion-titulo">1. Datos del Aspirante</h6>
                <div class="row">
                    <div class="col-md-2 mb-3">
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
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Número de documento <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="npt_estu_numerodoc" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Expedido en</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_expedidoen" autocomplete="off">
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
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" class="form-control" id="npt_fechanacimiento">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Ciudad de nacimiento</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ciudadnac" autocomplete="off">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Sexo</label>
                        <select class="form-select" id="slct_estu_sexo">
                            <option value="">-- Seleccionar --</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Femenino">Femenino</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="npt_estu_telefono" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" id="npt_estu_email" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
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
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Dirección (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ciudad" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">EPS</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_eps" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ocupación</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estu_ocupacion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
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
                </div>
                <div class="row">
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

                <label class="form-label d-block seccion-titulo" id="lbl_multiculturalidad">2. Multiculturalidad</label>
                <div class="mb-3">
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

                <h6 class="seccion-titulo">3. Información sobre Familiares</h6>

                <h6 class="text-muted border-bottom pb-2 mb-2">Padre</h6>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="npt_padr_vive">
                            <label class="form-check-label" for="npt_padr_vive">¿Vive?</label>
                        </div>
                    </div>
                </div>
                <div id="bloque_padre_campos" class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombres</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_nombres" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_apellidos" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Profesión</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_profesion" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Empresa</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_empresa" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Dirección (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Teléfono (Personal)</label>
                        <input type="text" class="form-control" id="npt_padr_telefono" autocomplete="off">
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Madre</h6>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="npt_madr_vive">
                            <label class="form-check-label" for="npt_madr_vive">¿Vive?</label>
                        </div>
                    </div>
                </div>
                <div id="bloque_madre_campos" class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombres</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_nombres" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_apellidos" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Profesión</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_profesion" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Empresa</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_empresa" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Dirección (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Teléfono (Personal)</label>
                        <input type="text" class="form-control" id="npt_madr_telefono" autocomplete="off">
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Acudiente / Persona de contacto</h6>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Es</label>
                        <select class="form-select" id="slct_acud_es">
                            <option value="">-- Seleccionar --</option>
                            <option value="padre">Padre</option>
                            <option value="madre">Madre</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="bloque_acud_parentesco">
                        <label class="form-label">Parentesco</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_parentesco" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombres</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_nombres" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellidos</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_apellidos" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Profesión</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_profesion" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Empresa</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_empresa" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Dirección (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad (Residencia)</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Teléfono (Personal)</label>
                        <input type="text" class="form-control" id="npt_acud_telefono" autocomplete="off">
                    </div>
                </div>

                <h6 class="seccion-titulo">4. Estudios anteriores</h6>
                <p class="text-muted small">Escriba la última certificación que ha
                    recibido, en una institución formal (bachiller, técnico,
                    universitario, etc.).</p>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tipo</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estudio_tipo" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estudio_titulo" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Año de finalización</label>
                        <input type="number" class="form-control" id="npt_estudio_aniofin" min="1900" max="2100" maxlength="4">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Institución</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estudio_institucion" autocomplete="off">
                    </div>
                </div>

                <h6 class="seccion-titulo">5. Programa técnico al que ingresa</h6>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Programa</label>
                        <select class="form-select" id="slct_finc_prog_id">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Jornada</label>
                        <select class="form-select" id="npt_jornada">
                            <option value="">-- Seleccionar --</option>
                            <option value="SEMANA">Semana</option>
                            <option value="SABADOS">Sábados</option>
                        </select>
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
                    <p>Gracias por inscribirte en la Escuela de Mecánica Dental
                        Bolaños. Tu ficha de inscripción fue recibida
                        correctamente.</p>
                    <p class="mb-0">La coordinación revisará tu inscripción y se
                        pondrá en contacto contigo para confirmar tu matrícula y los
                        siguientes pasos a seguir.</p>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="insc_ctrl.js"></script>
</body>
</html>
