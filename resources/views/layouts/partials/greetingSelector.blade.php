<label for="{{$id}}" class="form-label">Greeting</label>
<div class="d-flex flex-row">
    <div class="w-100 me-1">
        <select class="select2 form-control"
                data-toggle="select2"
                data-placeholder="Choose ..."
                id="{{$id}}"
                name="{{$id}}">
            <option value="disabled">Disabled</option>
            @if (!$allRecordings->isEmpty())
                <optgroup label="Recordings">
                    @foreach ($allRecordings as $recording)
                        <option value="{{ $recording->recording_filename }}"
                                @if($recording->recording_filename == $value)
                                    selected
                                @endif>
                            {{ $recording->recording_name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
    </div>
    <button disabled="true" type="button" class="btn btn-light me-1 @if($value == null) d-none @endif" id="{{$id}}_play_pause_button" title="Play/Pause"><i class="uil uil-play"></i></button>
    <button type="button" class="btn btn-light" id="{{$id}}_manage_greeting_button" title="Manage greetings"><i class="uil uil-cog"></i> </button>
    <audio id="{{$id}}_audio_file" @if ($value) src="{{ route('recordings.file', ['filename' => $value] ) }}" @endif ></audio>
</div>
<div class="modal fade" id="{{$id}}_manage_greeting_modal" role="dialog"
     aria-labelledby="{{$id}}_manage_greeting_modal" aria-hidden="true">
    <div class="modal-dialog w-75" style="max-width: initial;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Greetings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="{{$id}}_manage_greeting_modal_body"></div>
                <div class="border border-dark-subtle p-3">
                    <h5 class="modal-title mb-3">Create New Greeting</h5>
                    <div class="mb-2">
                        <label for="{{$id}}_new_greeting_name" class="form-label">Name</label>
                        <input type="text" id="{{$id}}_new_greeting_name" class="form-control" value="" />
                    </div>
                    <div class="mb-2">
                        <label for="{{$id}}_new_greeting_description" class="form-label">Description</label>
                        <textarea class="form-control" id="{{$id}}_new_greeting_description" rows="2"></textarea>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label for="{{$id}}_new_greeting_filename" class="form-label">Upload File</label>
                            <input type="file" id="{{$id}}_new_greeting_filename" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="{{$id}}_new_greeting_filename_record" class="form-label">Or Record a New One</label>
    <div>TODO: recording feature</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success">Save</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@if($hint ?? false)
    <span class="help-block"><small>{{$hint}}</small></span>
@endif
<div id="{{$id}}_err" class="text-danger error_message"></div>
@if($inlineScripts ?? true)
    @push('scripts')
        <script>
            $(document).ready(function () {
                const greetingPlayPauseButton = $('#{{$id}}_play_pause_button');
                const greetingManageButton = $('#{{$id}}_manage_greeting_button');
                const greetingManageModal = $('#{{$id}}_manage_greeting_modal');
                const greetingManageModalBody = $('#{{$id}}_manage_greeting_modal_body');
                const audioElement = document.getElementById('{{$id}}_audio_file');
                $('#{{$id}}').on('change', function (e) {
                    greetingPlayPauseButton.attr('disabled', true)
                    if(e.target.value === '' || e.target.value === 'disabled') {
                        greetingPlayPauseButton.addClass('d-none');
                    } else {
                        greetingPlayPauseButton.removeClass('d-none');
                        document.getElementById('{{$id}}_audio_file').setAttribute('src', '{{ route('recordings.file', ['filename' => '/'] ) }}/'+e.target.value);
                        audioElement.load();
                    }
                })
                greetingManageButton.on('click', function () {
                   greetingManageModal.modal('show');
                });
                greetingManageModal.on('shown.bs.modal', function(){
                    $.ajax({
                        type : "GET",
                        url : '{{ route('recordings.index' ) }}'
                    }).done(function(response) {
                        if(response.collection.length > 0) {
                            let tb = $('<table>');
                            tb.addClass('table');
                            tb.append('<thead><tr><th>Name</th><th>Description</th><th>Action</th></tr></thead>')
                            tb.append('<tbody>')
                            $.each(response.collection, function (i, item) {
                                let tr = $('<tr>').attr('data-uuid', item.id).
                                attr('data-filename', item.filename).
                                append(`<td>${item.name}</td><td>${item.description}</td><td>
<a href="" class="action-icon">
<i class="uil uil-play-circle" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Play"></i>
</a>
<a href="javascript:confirmDeleteAction('{{ route('recordings.destroy', ':id' ) }}','${item.id}');" class="action-icon"><i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i></a>
</td>`)
                                tb.append(tr)
                            })
                            console.log(tb)
                            greetingManageModalBody.append(tb);
                        }
                    });
                });
                greetingManageModal.on('hidden.bs.modal', function(){
                    greetingManageModalBody.empty()
                });
                greetingPlayPauseButton.click(function () {
                    if (audioElement.paused) {
                        console.log('Audio paused. Start')
                        greetingPlayPauseButton.find('i').removeClass('uil-play').addClass('uil-pause')
                        audioElement.play();
                    } else {
                        console.log('Audio playing. Pause')
                        greetingPlayPauseButton.find('i').removeClass('uil-pause').addClass('uil-play')
                        audioElement.currentTime = 0;
                        audioElement.pause();
                    }
                });
                audioElement.addEventListener('ended', (event) => {
                    console.log('Audio ended '+event.target.src)
                    greetingPlayPauseButton.find('i').removeClass('uil-pause').addClass('uil-play')
                })
                audioElement.addEventListener('canplay', (event) => {
                    console.log('Audio loaded '+event.target.src)
                    greetingPlayPauseButton.attr('disabled', false)
                    greetingPlayPauseButton.find('i').removeClass('uil-pause').addClass('uil-play')
                })
            });
        </script>
    @endpush
@endif
