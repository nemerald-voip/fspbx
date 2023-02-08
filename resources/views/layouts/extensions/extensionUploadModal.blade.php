<!-- Extension upload modal -->
<div id="extension-upload-modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="extension-upload-modalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="extension-upload-modalLabel">Import Extensions</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body">
                <!--begin::Dropzone-->
                <div class="dropzone dropzone-queue mb-2" id="file_dropzone">
                    <!--begin::Controls-->
                    <div class="dz-message needsclick dropzone-select">
                        <i class="h1 text-muted mdi mdi-cloud-upload-outline"></i>
                        <h3>Drop files here or click to upload.</h3>
                        <div class="mb-1"><a class="dropzone-select btn btn-sm btn-primary me-2">Browse files</a></div>
                        <span class="text-muted font-13">Supported file types: .csv, .xls, .xlsx</span>

                    </div>
                    <!--end::Controls-->

                    <!--begin::Items-->
                    <div class="dropzone-items row">
                        <div class="dropzone-item" style="display:none">
                            <!--begin::File-->
                            <div class="card mb-2 shadow-none border">
                                <div class="p-2">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="avatar-sm">
                                                <span class="avatar-title bg-light text-secondary rounded">
                                                    <i class="h1 mdi mdi-file-outline "></i>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="dropzone-filename col" title="">
                                            <strong><span data-dz-name>some_image_file_name.jpg</span></strong>
                                            (<span data-dz-size>340kb</span>)
                                        </div>

                                        <div class="col-auto">
                                            <!-- Button -->
                                            <a class="dropzone-delete btn btn-link btn-lg text-danger" data-dz-remove>
                                                <i class="uil uil-multiply"></i>
                                            </a>
                                        </div>
                                    </div>
                                    {{-- <div class="text-danger dropzone-error" data-dz-errormessage></div> --}}
                                </div>
                            </div>
                            <!--end::File-->

                        </div>
                    </div>
                    <!--end::Items-->
                </div>
                <!--end::Dropzone-->
            
                <!--begin::Hint-->
                {{-- <span class="form-text text-muted">Max file size is 5MB and max number of files is 5.</span> --}}
                <!--end::Hint-->
                <div class="text-danger files_err error_message"></div>


            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" id="importExtensionsSubmit" class="btn btn-primary">Next</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
