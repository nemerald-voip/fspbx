<div class="row">
    <div class="col-md-8">
        <div class="mb-3">
            <label for="timeout_action" class="form-label">If not answered, calls will be sent</label>
            <div class="row">
                <div class="col-md-4 col-sm-4" wire:ignore>
                    <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                        id="timeoutCategorySelect">
                        @foreach ($timeoutDestinationsByCategory as $key => $value)
                            <option value="{{ $key }}">{{ $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-4" wire:ignore>
                    <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                        id="timeoutDestinationSelect">
                        @foreach ($timeoutDestinationsByCategory[$timeoutCategory] as $destination)
                            <option value="{{ $destination['id'] }}">{{ $destination['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <script>
                // document.addEventListener("DOMContentLoaded", () => {
                $('#timeoutCategorySelect').select2();
                $('#timeoutDestinationSelect').select2();
                $('#timeoutCategorySelect').on('change', function(e) {
                    var data = $('#timeoutCategorySelect').select2("val");
                    @this.set('timeoutCategory', data);
                });
                // });
            </script>
            <div id="timeout_data_err" class="text-danger error_message"></div>
        </div>
    </div>
</div>
