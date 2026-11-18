
<!-- MODAL: LISTADO DE GRUPO FAMILIAR -->
<div class="modal fade" id="modalFamiliar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-0">Grupo familiar</h5>
                    <small id="nombreEmpleadoFamiliar"></small>
                </div>
                <button type="button" class="btn-close btn-close-white"
                    data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="familiar_idempleado">

                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-primary"
                        id="btnNuevoFamiliar">
                        <i class="fas fa-plus me-2"></i>Agregar familiar
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre y apellido</th>
                                <th>Parentesco</th>
                                <th>Documento</th>
                                <th>Fecha nacimiento</th>
                                <th>Convive</th>
                                <th>Contacto emergencia</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaFamiliares">
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Sin registros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary"
                    data-bs-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>


<!-- MODAL: FORMULARIO FAMILIAR -->
<div class="modal fade" id="ModalFormFamiliar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form id="formFamiliar">
            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloModalFamiliar">
                        Familiar
                    </h5>
                    <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="idfamiliar" id="idfamiliar">
                    <input type="hidden" name="idempleado" id="idempleadoFamiliar">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" id="familiarNombre"
                                class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" id="familiarApellido"
                                class="form-control" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Parentesco *</label>
                            <select name="parentesco" id="familiarParentesco"
                                class="form-select" required>
                                <option value="">Seleccione</option>
                                <option value="Cónyuge">Cónyuge</option>
                                <option value="Hijo/a">Hijo/a</option>
                                <option value="Padre">Padre</option>
                                <option value="Madre">Madre</option>
                                <option value="Hermano/a">Hermano/a</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Documento</label>
                            <input type="text" name="documento" id="familiarDocumento"
                                class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="date" name="fecha_nacimiento"
                                id="familiarFechaNacimiento" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Género</label>
                            <select name="genero" id="familiarGenero"
                                class="form-select">
                                <option value="">Sin especificar</option>
                                <option value="Femenino">Femenino</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono"
                                id="familiarTelefono" class="form-control">
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" name="convive" value="1"
                            id="familiarConvive" class="form-check-input">
                        <label class="form-check-label" for="familiarConvive">
                            Convive con el
                            <span data-diccionario="empleado_singular"></span>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="contacto_emergencia" value="1"
                            id="familiarEmergencia" class="form-check-input">
                        <label class="form-check-label" for="familiarEmergencia">
                            Contacto de emergencia
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" id="familiarObservaciones"
                            class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"
                        id="btnGuardarFamiliar">Guardar</button>
                </div>

            </div>
        </form>
    </div>
</div>
