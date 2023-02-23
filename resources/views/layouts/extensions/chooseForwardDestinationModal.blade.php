<!-- MobileAppModal -->
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
                        <p>...</p>
                    </div>
                    <div class="tab-pane" id="choose-phone-number">
                        <p>...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Select</button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
