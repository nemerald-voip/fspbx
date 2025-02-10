@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Fax service alert</h1>
<p>{{ $attributes["pendingFaxes"] ?? ''}} faxes have been pending for longer than {{ $attributes["waitTimeThreshold"] ?? ''}} minutes. Check fax queue service status.</p>
<!-- Action -->

<p>Thanks,
<br>{{ config('app.name', 'Laravel') }} Team</p>


@endsection