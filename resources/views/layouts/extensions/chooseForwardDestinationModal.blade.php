<div id="ForwardDestinationModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="ForwardDestinationModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="ForwardDestinationModalLabel">Choose Destination</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs nav-justified nav-bordered">
                    <li class="nav-item">
                        <a href="#choose-extension" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                            <i class="mdi mdi-home-variant d-md-none d-block"></i>
                            <span class="d-none d-md-block">Extension</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#choose-phone-number" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                            <i class="mdi mdi-account-circle d-md-none d-block"></i>
                            <span class="d-none d-md-block">Custom Phone Number</span>
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane show active" id="choose-extension">
                        <div class="row">
                            <div class="my-4 col-md-6 offset-md-3">
                                <label for="extension_destination_popup" class="form-label">Select Extension <span class="text-danger">*</span></label>
                                <select id="extension_destination_popup" data-toggle="select2" title="Extensions list" name="extension_destination_popup">
                                    <option value="">Choose</option>
                                    @foreach ($extensions as $extension)
                                        <option value="{{ $extension->extension }}">
                                            {{ $extension->extension }} ({{ implode(" / ", [$extension->effective_caller_id_name, $extension->outbound_caller_id_number]) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="choose-phone-number">
                        <div class="row">
                            <div class="my-4 col-md-6 offset-md-3">
                                <label for="number_destination_popup" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input class="form-control" type="text" placeholder="Enter numbers" id="number_destination_popup" name="number_destination_popup" value="" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input id="forward_destination_type" type="hidden" name="type" value="" />
                <button id="ForwardDestinationModalAction" type="button" class="btn btn-success">Select</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
