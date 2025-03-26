Fax Service Alert

@if(isset($attributes["pendingFaxes"]))
{{ $attributes["pendingFaxes"] }} faxes have been pending for longer than {{ $attributes["waitTimeThreshold"] }} minutes. Check the fax queue service status.
@endif

@if(isset($attributes["failedFaxes"]))
{{ $attributes["failedFaxes"] }} out of {{ $attributes["totalChecked"] }} recently processed faxes have failed ({{ $attributes["failureRate"] }}% failure rate).
This indicates a potential issue with the fax service.
@endif

Thanks,

{{ config('app.name', 'Laravel') }} Team