<!-- MODAL DATOS DE CONTACTO -->
<div class="modal fade" id="ModalDatosContacto" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <form id="formDatosContacto">

            <div class="modal-content">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        Datos de contacto
                    </h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>
                </div>

                <div class="modal-body">

                    <input type="hidden"
                           name="idempleado"
                           id="contacto_idempleado">

                    <h6 class="fw-bold mb-3">Información de contacto</h6>

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label for="contacto_email" class="form-label">
                                Email
                            </label>
                            <input type="email"
                                   class="form-control"
                                   id="contacto_email"
                                   name="email"
                                   maxlength="180">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="contacto_telefono" class="form-label">
                                Teléfono
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_telefono"
                                   name="telefono"
                                   maxlength="40">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="contacto_celular" class="form-label">
                                Celular
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_celular"
                                   name="celular"
                                   maxlength="40">
                        </div>

                    </div>

                    <hr>

                    <h6 class="fw-bold mb-3">Domicilio</h6>

                    <div class="row">

                        <div class="col-md-8 mb-3">
                            <label for="contacto_domicilio" class="form-label">
                                Domicilio
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_domicilio"
                                   name="domicilio"
                                   maxlength="220">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="contacto_codigo_postal" class="form-label">
                                Código postal
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_codigo_postal"
                                   name="codigo_postal"
                                   maxlength="20">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="contacto_localidad" class="form-label">
                                Localidad
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_localidad"
                                   name="localidad"
                                   maxlength="120">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="contacto_provincia" class="form-label">
                                Provincia
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_provincia"
                                   name="provincia"
                                   maxlength="120">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="contacto_entre_calles" class="form-label">
                                Entre calles
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="contacto_entre_calles"
                                   name="entre_calles"
                                   maxlength="220">
                        </div>

                        <div class="col-12 mb-3">
                            <label for="contacto_referencia" class="form-label">
                                Referencia del domicilio
                            </label>
                            <textarea class="form-control"
                                      id="contacto_referencia"
                                      name="referencia_domicilio"
                                      rows="2"
                                      maxlength="500"></textarea>
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="btnGuardarContacto">
                        <i class="fas fa-save"></i> Guardar
                    </button>

                </div>

            </div>

        </form>
    </div>
</div>