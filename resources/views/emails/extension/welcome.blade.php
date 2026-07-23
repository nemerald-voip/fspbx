{{-- email-template
version: 1.3.0
language: en-us
category: extension
subcategory: welcome
format: html
layout: standard
subject: Your phone extension {{ $attributes['extension'] }} is ready
description: Extension and voicemail welcome information
--}}
@extends('emails.email_layout')

@section('content')
<h1>Welcome, {{ $attributes['recipient_name'] }}!</h1>

<p>We've set up extension <strong>{{ $attributes['extension'] }}</strong> for you. Keep this email handy for your phone and voicemail details.</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Extension:</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>Direct number{{ count($attributes['direct_numbers']) === 1 ? '' : 's' }}:</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Voicemail mailbox:</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Voicemail PIN:</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>Set up your voicemail greeting</h2>
<ol>
  <li>Dial <strong>*97</strong> from your phone.</li>
  <li>Enter your voicemail PIN, then press <strong>#</strong>.</li>
  <li>Press <strong>5</strong> for mailbox options.</li>
  <li>Press <strong>1</strong> to record your unavailable greeting.</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>More help is available in the <a href="{{ $attributes['help_url'] }}">help center</a>.</p>
@endif

@if (!empty($attributes['support_email']))
  <p>Questions? Email <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>.</p>
@endif

<p>Welcome aboard,<br>{{ $attributes['app_name'] }}</p>
@endsection
