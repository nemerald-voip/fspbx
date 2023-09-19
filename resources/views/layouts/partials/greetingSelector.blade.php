<label for="{{$id}}" class="form-label">Greeting</label>
<div class="d-flex flex-row">
    <div class="w-100">
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
    <div id="{{$id}}_play_pause_wrapper" @if($value == null) class="d-none" @endif>
        <button type="button" class="btn btn-light" id="{{$id}}_play_button" title="Play"><i class="uil uil-play"></i> </button>
        <button type="button" class="btn btn-light" id="{{$id}}_pause_button" title="Pause"><i class="uil uil-pause"></i> </button>
    </div>
    <audio id="{{$id}}_audio_file" @if ($value) src="{{ route('getRecordings', ['filename' => $value] ) }}" @endif ></audio>
</div>
@if($hint ?? false)
    <span class="help-block"><small>{{$hint}}</small></span>
@endif
<div id="{{$id}}_err" class="text-danger error_message"></div>
@push('scripts')
    <script>
        $(document).ready(function () {
            const greetingBaseUrl = '{{ route('getRecordings', ['filename' => '/'] ) }}/';
            const greetingPauseButton = $('#{{$id}}_pause_button');
            const greetingPlayButton = $('#{{$id}}_play_button');
            const greetingPlayPauseWrapper = $('#{{$id}}_play_pause_wrapper');
            initGreetingPreview(document.getElementById('{{$id}}_audio_file'));
            $('#{{$id}}').on('change', function (e) {
                if(e.target.value === '' || e.target.value === 'disabled') {
                    greetingPlayPauseWrapper.addClass('d-none');
                } else {
                    greetingPlayPauseWrapper.removeClass('d-none');
                    document.getElementById('{{$id}}_audio_file').setAttribute('src', greetingBaseUrl+e.target.value);
                    initGreetingPreview(document.getElementById('{{$id}}_audio_file'));
                }
            })
            function initGreetingPreview(audioElement) {
                greetingPauseButton.hide();
                greetingPlayButton.click(function(){
                    $(this).hide();
                    greetingPauseButton.show();
                    audioElement.play();
                    audioElement.addEventListener('ended', function() {
                        greetingPauseButton.hide();
                        greetingPlayButton.show();
                    });
                });
                greetingPauseButton.click(function(){
                    $(this).hide();
                    greetingPlayButton.show();
                    audioElement.pause();
                });
            }
        });
    </script>
@endpush
