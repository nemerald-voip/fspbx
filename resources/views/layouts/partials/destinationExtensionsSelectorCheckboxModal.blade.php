@php
    /**
     * @property array $extensions
     * @property string $label
     */
@endphp
<div id="addDestinationBarMultiple" class="my-1">
    <a href="javascript:addDestinationMultipleModalShow();"
       class="btn btn-success">
        <i class="mdi mdi-plus"
           data-bs-container="#tooltip-container-actions"
           data-bs-toggle="tooltip"
           data-bs-placement="bottom"
           title="{{ $label }}"></i> {{ $label }}
    </a>
</div>
<div class="modal fade" id="addDestinationMultipleModal" role="dialog"
     aria-labelledby="addDestinationMultipleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDestinationMultipleModalLabel">{{ $label }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Select one or more extensions to be added.</p>
                <form method="POST" id="addDestinationMultipleForm" action="#" class="form">
                    <div class="mb-3">
                        <input class="form-control" type="text" name="destination_multiple_search" placeholder="Search" value="" />
                    </div>
                    <div class="mb-3">
                        <input class="form-control" type="button" onclick="triggerDestinationAll()" name="destination_multiple_search_select_all" value="Select All Extensions" />
                    </div>
                    <div class="destination_multiple_wrapper">
                        <ul id="destinationMultipleListExtensions">
                            @php
                                foreach ($extensions as $extension) {
                                    print '';
                                    /*print '<div class="row"><div class="col-md-12 mb-1">
                                           <input class="form-control" type="text"
                                           placeholder="Extension, voicemail, phone, etc..."
                                           name="destination_multiple[]"
                                           value="" /></div></div>';*/

                                }
                                /*for($i = 0; $i < 15; $i++) {
                                    print '<div class="row"><div class="col-md-12 mb-1">
                                           <input class="form-control" type="text"
                                           placeholder="Extension, voicemail, phone, etc..."
                                           name="destination_multiple[]"
                                           value="" /></div></div>';
                                }*/
                            @endphp
                        </ul>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="{{ $destinationTargetOnClick }}"
                        class="btn btn-success">Add<span id="destinationMultipleSelectedCountWrapper"></span>
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
<style>
    #addDestinationMultipleModal .destination_multiple_wrapper {
        max-height: 300px;
        overflow-y: scroll;
        padding: 0.6em;
        border: 1px solid #dee2e6;
    }
    #addDestinationMultipleModal .destination_multiple_wrapper label {
        width: 100%;
    }
    #addDestinationMultipleModal ul {
        padding-left: 0;
        font-size: 1.3em;
        margin-bottom: 0;
    }
    #addDestinationMultipleModal ul li {
        list-style: none;
        line-height: 1.6em;
        padding-bottom: 0.5em;
    }
    #addDestinationMultipleModal ul li:last-child {
        padding-bottom: 0;
    }
</style>
<script>
    const destinationMultipleListExtensions = document.getElementById('destinationMultipleListExtensions')
    const destinationMultipleSelectedCountWrapper = document.getElementById('destinationMultipleSelectedCountWrapper')
    let destinations = [
        @foreach ($extensions as $extension)
            {
                label: "@if($extension->effective_caller_id_name) {{$extension->effective_caller_id_name}} @else Extension @endif - {{ $extension->extension}}",
                checked: false,
                value: "{{ $extension->extension_uuid }}"
            },
        @endforeach
    ];

    renderDestinations(destinations)
    countSelected()

    function triggerDestinationAll() {
        for(let i = 0; i < destinations.length; i++) {
            destinations[i].checked = true
        }
        renderDestinations(destinations)
        countSelected()
    }
    function addDestinationMultipleModalShow() {
        $('#addDestinationMultipleModal').modal('show');
    }

    function renderDestinations(data) {
        destinationMultipleListExtensions.innerHTML = '';
        for(let i = 0; i < data.length; i++) {
            let el = document.createElement('input')
            el.type = 'checkbox'
            el.name = 'destination_multiple[]'
            el.value = data[i].value
            el.classList.add('form-check-input')
            el.classList.add('action_checkbox')
            if(data[i].checked) {
                el.checked = true
            }
            el.onclick = function (event) {
                data[i].checked = event.target.checked
                countSelected()
            }
            let ellabel = document.createElement('label')
            ellabel.classList.add('form-check-label')
            ellabel.innerText = data[i].label
            let elli = document.createElement('li')
            ellabel.prepend(el)
            elli.append(ellabel)
            destinationMultipleListExtensions.append(elli)
        }
        countSelected()
    }

    function countSelected() {
        let destinationMultipleSelectedCount = 0
        for(let i = 0; i < destinations.length; i++) {
            if(destinations[i].checked) {
                destinationMultipleSelectedCount++
            }
        }
        destinationMultipleSelectedCountWrapper.innerText = (destinationMultipleSelectedCount > 0) ? ` (${destinationMultipleSelectedCount})` : ''
    }

</script>
