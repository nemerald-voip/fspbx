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
                        <input type="text" id="{{$id}}_new_greeting_name" name="greeting_name" class="form-control" value="" />
                    </div>
                    <div class="mb-2">
                        <label for="{{$id}}_new_greeting_description" class="form-label">Description</label>
                        <textarea class="form-control" id="{{$id}}_new_greeting_description" name="greeting_description" rows="2"></textarea>
                    </div>
                    <div class="row mb-2">
                        <div class="col-md-6">
                            <label for="{{$id}}_new_greeting_filename" class="form-label">Upload File</label>
                            <input type="file" id="{{$id}}_new_greeting_filename" name="greeting_filename" accept=".wav" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="{{$id}}_new_greeting_filename_record" class="form-label">Or Record a New One</label>
    <div>TODO: recording feature</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success save-recording-btn">Save</button>
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
<a href="javascript:playCurrentRecording('${item.filename}')" class="action-icon">
<i class="uil uil-play-circle" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Play/Pause"></i>
</a>
<a href="javascript:confirmDeleteAction('{{ route('recordings.destroy', ':id' ) }}','${item.id}');" class="action-icon"><i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i></a>
</td>`)
                                tb.append(tr)
                            })
                            //console.log(tb)
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
                    //greetingPlayPauseButton.find('i').removeClass('uil-pause').addClass('uil-play')
                })

                $('.save-recording-btn').on('click', function(e) {
                    e.preventDefault();
                    //$('.loading').show();

                    //Reset error messages
                    //$('.error_message').text("");

                    var formData = new FormData();
                    formData.append('greeting_filename', document.getElementById('{{$id}}_new_greeting_filename').files[0]);
                    formData.append('greeting_name', $('#{{$id}}_new_greeting_name').val());
                    formData.append('greeting_description', $('#{{$id}}_new_greeting_description').val());

                    $.ajax({
                        type : "POST",
                        url: '{{ route('recordings.store') }}',
                        cache: false,
                        data: formData,
                        processData: false,
                        contentType: false,
                        beforeSend: function() {
                            //Reset error messages
                            /*form.find('.error').text('');

                            $('.error_message').text("");
                            $('.btn').attr('disabled', true);
                            $('.loading').show();*/
                        },
                        complete: function (xhr,status) {
                            /*$('.btn').attr('disabled', false);
                            $('.loading').hide();*/
                        },
                        success: function(result) {
                            //$('.loading').hide();
                            $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                            //window.location.href = "{{ route('devices.index')}}";
                        },
                        error: function(error) {
                            /*$('.loading').hide();
                            $('.btn').attr('disabled', false);
                            if(error.status == 422){
                                if(error.responseJSON.errors) {
                                    $.each( error.responseJSON.errors, function( key, value ) {
                                        if (value != '') {
                                            form.find('#'+key+'_error').text(value);
                                            printErrorMsg(value);
                                        }
                                    });
                                } else {
                                    printErrorMsg(error.responseJSON.message);
                                }
                            } else {
                                printErrorMsg(error.responseJSON.message);
                            }*/
                        }
                    });
                })
            });

            function playCurrentRecording(filename) {
                document.getElementById('{{$id}}_audio_file').setAttribute('src', '{{ route('recordings.file', ['filename' => '/'] ) }}/'+filename);
                document.getElementById('{{$id}}_play_pause_button').click()
            }
        </script>
    @endpush
@endif
