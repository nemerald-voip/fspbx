{{-- email-template
version: 1.2.0
language: en-us
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: Email Not Authorized
description: Unauthorized email-to-fax sender notification
--}}
@extends('emails.email_layout')

@section('content')
<!-- Start Content-->

<h1>Your fax message to {{ $attributes['fax_destination'] }} has failed</h1>
<p>The email below is not authorized to send faxes. Please, contact your system administrator.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>{{ $attributes['from'] }}</strong> 
        </tr>
      </table>
    </td>
  </tr>
</table>

@endsection
