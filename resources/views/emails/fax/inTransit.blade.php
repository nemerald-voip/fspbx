@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your file(s) are now being faxed to {{ $attributes['fax_destination'] }}.</h1>
<p>Additional notifications will follow regarding the outcome of the transmission.</p>
<!-- Action -->

<p>If you have any questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">email our customer success team</a>. (We're lightning quick at replying.)</p>
<p>Thanks,
  <br>{{ config('app.name', 'Laravel') }} Team</p>
<p><strong>P.S.</strong> Need immediate help getting started? The {{ config('app.name', 'Laravel') }} support team is always ready to help! Check out our <a href="{{ $attributes["help_url"] ?? ''}}">help documentation</a>. Or, just reply to this email.</p>


@endsection