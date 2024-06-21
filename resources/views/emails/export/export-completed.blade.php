@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your report is ready.</h1>

<p>The CSV you requested is ready to be downloaded.</p>

<p>You can download it from the following link:</p>

<p><a href="{{ $attributes['fileUrl'] }}">{{ $attributes['fileUrl'] }}</a></p>


<table class="action" align="center" width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td align="center">
    <table border="0" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
    <td>
    <a href="{{ $attributes['fileUrl'] }}" class="button button-primary" target="_blank" rel="noopener">Download report</a>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>
    </td>
    </tr>
    </table>

<p>Thank you!</p>

@endsection