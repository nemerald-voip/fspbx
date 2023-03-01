<div class="modal fade" id="confirmClearDestinationModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body p-4">
                <div class="text-center">
                    {{-- <i class=" dripicons-question h1 text-danger"></i> --}}
                    <i class="uil uil-times-circle h1 text-danger"></i>
                    <h3 class="mt-3">Are you sure?</h3>
                    <p class="mt-3">The destination will be cleared and the forwarding option will be disabled.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light me-2" data-bs-dismiss="modal">Cancel</button>
                <a href="javascript:performConfirmedClearDestinationAction();" class="btn btn-danger me-2">Clear</a>
            </div>
        </div>
    </div>
</div>
