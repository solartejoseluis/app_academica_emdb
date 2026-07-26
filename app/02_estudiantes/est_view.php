<?php
session_start();
require_once '../01_login/check_session.php';

if ($_SESSION['role_id'] !== 1 && $_SESSION['role_id'] !== 2) {
    header('Location: ../01_login/login_view.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudiantes — EMDB Académica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/app_academica_emdb/app/00_files/estilos.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php if (in_array((int)$_SESSION['role_id'], [1, 2])): ?>
<?php require_once '../00_files/navbar.php'; ?>
<?php else: ?>
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand fw-bold">EMDB Académica</span>
    <div class="d-flex align-items-center gap-3">
        <span class="text-light small"><?= htmlspecialchars($_SESSION['usua_email']) ?></span>
        <a href="/app_academica_emdb/app/01_login/logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid mt-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Gestión de Estudiantes</h5>
        <button class="btn btn-primary btn-sm" id="btn_nuevo_aspirante">+ Nuevo Aspirante</button>
    </div>

    <ul class="nav nav-tabs mb-0" id="tabEstudiantes">
        <li class="nav-item">
            <a class="nav-link active" id="tab-aspirantes" data-bs-toggle="tab" href="#pane-aspirantes">Aspirantes</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="tab-matriculados" data-bs-toggle="tab" href="#pane-matriculados">Matriculados</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="pane-aspirantes">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <table id="tbl_aspirantes" class="table table-hover table-bordered w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>Foto</th>
                                <th>#</th>
                                <th>Nombres</th>
                                <th>Apellidos</th>
                                <th>Documento</th>
                                <th>Teléfono</th>
                                <th>Fecha Inscripción</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="pane-matriculados">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <table id="tbl_matriculados" class="table table-hover table-bordered w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>Foto</th>
                                <th>#</th>
                                <th>Nombres</th>
                                <th>Apellidos</th>
                                <th>Documento</th>
                                <th>Programa</th>
                                <th>Período</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Aspirante / Editar Estudiante -->
<div class="modal fade" id="mdl_estudiante" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mdl_estudiante_titulo">Nuevo Aspirante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="npt_estu_id" value="">

                <div id="bloque_foto_estudiante" class="mb-3 d-none">
                    <label class="form-label d-block">Foto del estudiante</label>
                    <img id="img_preview_foto" src="" alt="Foto del estudiante" class="d-none mb-2" style="max-width:120px;max-height:150px;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="file" class="form-control form-control-sm" id="npt_foto_estudiante" accept="image/jpeg,image/png" style="max-width:300px;">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btn_subir_foto">Subir Foto</button>
                    </div>
                </div>

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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_estudiante">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Completar Matrícula -->
<div class="modal fade" id="mdl_matricular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Completar Matrícula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="npt_estu_id_matricular" value="">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Programa <span class="text-danger">*</span></label>
                        <select class="form-select" id="slct_prog_id">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Período <span class="text-danger">*</span></label>
                        <select class="form-select" id="slct_peri_id">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Folio</label>
                        <input type="text" class="form-control" id="npt_matr_folio" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Número</label>
                        <input type="text" class="form-control" id="npt_matr_numero" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cohorte</label>
                        <select class="form-select" id="slct_coho_id">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Requisitos entregados</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_copiadiploma"><label class="form-check-label" for="npt_req_copiadiploma">Copia diploma</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_actagrado"><label class="form-check-label" for="npt_req_actagrado">Acta de grado</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_documento"><label class="form-check-label" for="npt_req_documento">Documento identidad</label></div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_carnetsalud"><label class="form-check-label" for="npt_req_carnetsalud">Carné de salud</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_examenmedico"><label class="form-check-label" for="npt_req_examenmedico">Examen médico</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_fotos"><label class="form-check-label" for="npt_req_fotos">Fotos</label></div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_carpeta"><label class="form-check-label" for="npt_req_carpeta">Carpeta</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_vacunastetano"><label class="form-check-label" for="npt_req_vacunastetano">Vacuna tétano</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" id="npt_req_hepatitisb"><label class="form-check-label" for="npt_req_hepatitisb">Hepatitis B</label></div>
                    </div>
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Crear acceso al sistema</h6>
                <div class="mb-2">
                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_acceso" id="radio_automatica" value="automatica"><label class="form-check-label" for="radio_automatica">Generar clave automática</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_acceso" id="radio_manual" value="manual"><label class="form-check-label" for="radio_manual">Asignar clave manual</label></div>
                    <div class="form-check"><input class="form-check-input" type="radio" name="tipo_acceso" id="radio_no" value="no" checked><label class="form-check-label" for="radio_no">No crear acceso</label></div>
                </div>
                <div class="mb-3 d-none" id="bloque_clave_manual">
                    <label class="form-label">Clave</label>
                    <input type="text" class="form-control" id="npt_clave_manual" placeholder="Ingrese la clave" autocomplete="off">
                </div>
                <div class="alert alert-success d-none" id="div_clave_generada">
                    Clave asignada: <strong id="spn_clave_valor"></strong>
                    <div class="mt-1 small text-muted">Anote esta clave — no se volverá a mostrar.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success d-none" id="btn_cerrar_matricula">Cerrar y actualizar lista</button>
                <button type="button" class="btn btn-primary" id="btn_confirmar_matricula">Confirmar Matrícula</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ficha Familiar (AC-FO-02) -->
<div class="modal fade" id="mdl_ficha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ficha Familiar (AC-FO-02)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="npt_estu_id_ficha" value="">

                <div class="alert alert-info mb-3">
                    <strong>Instrucciones:</strong> Diligencie esta ficha con los datos
                    actualizados del aspirante y su núcleo familiar. Complete todos los
                    campos visibles — si el padre o la madre no viven, desmarque la
                    casilla correspondiente y esos campos se ocultarán automáticamente.
                    Al finalizar, pulse "Guardar Ficha".
                </div>

                <h6 class="text-muted border-bottom pb-2 mb-2">Información general</h6>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Programa</label>
                        <select class="form-select" id="slct_finc_prog_id">
                            <option value="">-- Seleccionar --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jornada</label>
                        <select class="form-select" id="npt_jornada">
                            <option value="">-- Seleccionar --</option>
                            <option value="SEMANA">Semana</option>
                            <option value="SABADOS">Sábados</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de inscripción</label>
                        <input type="date" class="form-control" id="npt_fechainscripcion">
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
                    <div class="col-md-12">
                        <label class="form-label">Institución</label>
                        <input type="text" class="form-control texto-mayus" id="npt_estudio_institucion" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-danger" id="btn_descargar_ficha_pdf">📄 Descargar PDF</button>
                <button type="button" class="btn btn-primary" id="btn_guardar_ficha">Guardar Ficha</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="est_ctrl.js"></script>
</body>
</html>
