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
                        <input type="text" class="form-control" id="npt_estu_nombres" autocomplete="off">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Apellidos <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="npt_estu_apellidos" autocomplete="off">
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
                        <label class="form-label">Ciudad</label>
                        <input type="text" class="form-control" id="npt_estu_ciudad" autocomplete="off">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="npt_estu_direccion" autocomplete="off">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Barrio</label>
                        <input type="text" class="form-control" id="npt_estu_barrio" autocomplete="off">
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
                    <input type="text" class="form-control" id="npt_estu_eps" autocomplete="off">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="est_ctrl.js"></script>
</body>
</html>
