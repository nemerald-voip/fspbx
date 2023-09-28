@if ($modalIsOpen)
    <!-- Standard modal -->
    <div id="myOffcanvas" class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasRight" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header">
            <h3>Call Details</h3>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <h5 id="offcanvasRightLabel" class="mb-3">{{ $currentRecord->xml_cdr_uuid }}</h5>
            <table class="table table-sm table-centered mb-0">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Sip Call ID</td>
                        <td>{{ $currentRecord->sip_call_id }}</td>
                    </tr>
                    <tr>
                        <td>Direction</td>
                        <td>{{ $currentRecord->direction }}</td>
                    </tr>
                    <tr>
                        <td>Caller Name</td>
                        <td>{{ $currentRecord->caller_id_name }}</td>
                    </tr>
                    <tr>
                        <td>Caller</td>
                        <td>{{ $currentRecord->caller_id_number }}</td>
                    </tr>
                    <tr>
                        <td>Caller Destination</td>
                        <td>{{ $currentRecord->caller_destination }}</td>
                    </tr>
                    <tr>
                        <td>Source Number</td>
                        <td>{{ $currentRecord->source_number }}</td>
                    </tr>
                    <tr>
                        <td>Destination Number</td>
                        <td>{{ $currentRecord->destination_number }}</td>
                    </tr>
                    <tr>
                        <td>Start Time</td>
                        <td>{{ $currentRecord->start_epoch }}</td>
                    </tr>
                    <tr>
                        <td>Answer Time</td>
                        <td>{{ $currentRecord->answer_epoch }}</td>
                    </tr>
                    <tr>
                        <td>End Time</td>
                        <td>{{ $currentRecord->end_epoch }}</td>
                    </tr>
                    <tr>
                        <td>Duration</td>
                        <td>{{ $currentRecord->duration }}</td>
                    </tr>
                    <tr>
                        <td>Talk Time</td>
                        <td>{{ $currentRecord->billsec }}</td>
                    </tr>
                    <tr>
                        <td>Leg</td>
                        <td>{{ $currentRecord->leg }}</td>
                    </tr>
                    <tr>
                        <td>Originating Leg</td>
                        <td>{{ $currentRecord->originating_leg_uuid }}</td>
                    </tr>
                    <tr>
                        <td>PDD</td>
                        <td>{{ $currentRecord->pdd_ms }}</td>
                    </tr>
                    <tr>
                        <td>RTP Audio in MOS</td>
                        <td>{{ $currentRecord->rtp_audio_in_mos }}</td>
                    </tr>
                    <tr>
                        <td>Last App</td>
                        <td>{{ $currentRecord->last_app }}</td>
                    </tr>
                    <tr>
                        <td>Last Arg</td>
                        <td>{{ $currentRecord->last_arg }}</td>
                    </tr>
                    <tr>
                        <td>Voicemail</td>
                        <td>{{ $currentRecord->voicemail_message }}</td>
                    </tr>
                    <tr>
                        <td>Missed Call</td>
                        <td>{{ $currentRecord->missed_call }}</td>
                    </tr>



                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('open-module', event => {
            // var myModal = new bootstrap.Modal(document.getElementById('standard-modal'), {
            //     backdrop: true
            // });
            // myModal.show();

            var bsOffcanvas = new bootstrap.Offcanvas(document.getElementById('myOffcanvas'));
            bsOffcanvas.show();
        });
    </script>
@endif
