<label for="{{$id}}" class="form-label">Greeting</label>
<div class="d-flex flex-row">
    <div class="w-100">
        <select class="select2 form-control"
                data-toggle="select2"
                data-placeholder="Choose ..."
                id="{{$id}}"
                name="{{$id}}">
            <option value=""></option>
            @if (!$allRecordings->isEmpty())
                <optgroup label="Recordings">
                    @foreach ($allRecordings as $recording)
                        <option value="{{ $recording->recording_name }}"
                                @if($recording->recording_name == $ringGroup->ring_group_greeting)
                                    selected
                                @endif>
                            {{ $recording->recording_name }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
    </div>
    <div>
        <button type="button" class="btn btn-light" id="{{$id}}_play_button" title="Play"><i class="uil uil-play"></i> </button>
        <button type="button" class="btn btn-light" id="{{$id}}_pause_button" title="Pause"><i class="uil uil-pause"></i> </button>
    </div>
    <audio id="{{$id}}_audio_file" @if ($value) src="{{ route('getRecordings', ['filename' => $value] ) }}" @endif ></audio>
</div>
<div id="{{$id}}_err" class="text-danger error_message"></div>
@push('scripts')
    <script>
        $(document).ready(function () {
            const greetingPauseButton = $('#{{$id}}_pause_button');
            const greetingPlayButton = $('#{{$id}}_play_button');
            const audioElement = document.getElementById('{{$id}}_audio_file');
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
        });
    </script>
@endpush
