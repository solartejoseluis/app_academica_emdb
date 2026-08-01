<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Familiares — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
</head>
<body class="bg-light" data-codigo-auto="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>">

<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">Escuela de Mecánica Dental Bolaños — Datos Familiares</span>
</nav>

<div class="container mt-4 mb-5">
    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <div id="bloque_codigo">
                <h5 class="mb-3">Continuar inscripción</h5>
                <p class="text-muted">Ingresa el código de acceso que recibiste al completar tus datos personales.</p>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <input type="text" class="form-control" id="npt_codigo_busqueda"
                               value="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>"
                               placeholder="Código de acceso" autocomplete="off">
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="button" class="btn btn-primary" id="btn_buscar_codigo">Continuar</button>
                    </div>
                </div>
                <div id="error_codigo" class="text-danger small d-none"></div>
            </div>

            <div id="bloque_ficha" class="d-none">
                <h5>Hola, <span id="nombre_estudiante_ficha"></span></h5>
                <p class="text-muted">Completa los datos de tu núcleo familiar para terminar la inscripción.</p>
                <input type="hidden" id="npt_codigo_ficha">

                <h6 class="text-muted border-bottom pb-2 mb-2">Programa e inscripción</h6>
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
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="npt_padr_telefono" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control texto-mayus" id="npt_padr_direccion" autocomplete="off">
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
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="npt_madr_telefono" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control texto-mayus" id="npt_madr_direccion" autocomplete="off">
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
                        <label class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="npt_acud_telefono" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_barrio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_ciudad" autocomplete="off">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control texto-mayus" id="npt_acud_direccion" autocomplete="off">
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Estudios anteriores</h6>
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

                <button type="button" class="btn btn-primary" id="btn_guardar_ficha">Guardar Datos Familiares</button>
            </div>

            <div id="bloque_confirmacion_ficha" class="d-none">
                <div class="alert alert-success">
                    <h5 class="alert-heading">¡Datos familiares guardados!</h5>
                    <p class="mb-0">Datos familiares guardados. El coordinador revisará tu inscripción.</p>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="fam_ctrl.js"></script>
</body>
</html>
