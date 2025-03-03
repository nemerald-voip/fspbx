@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Fax Service Alert</h1>

@if(isset($attributes["pendingFaxes"]))
    <p>{{ $attributes["pendingFaxes"] }} faxes have been pending for longer than {{ $attributes["waitTimeThreshold"] }} minutes. Check the fax queue service status.</p>
@endif

@if(isset($attributes["failedFaxes"]))
    <p>{{ $attributes["failedFaxes"] }} out of {{ $attributes["totalChecked"] }} recently processed faxes have failed ({{ $attributes["failureRate"] }}% failure rate).</p>
    <p>This indicates a potential issue with the fax service.</p>
@endif

<!-- Action -->
<p>Thanks,<br>{{ config('app.name', 'Laravel') }} Team</p>

@endsection
