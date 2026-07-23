{{-- email-template
version: 1.1.0
language: en-us
category: voicemail
subcategory: default
format: html
layout: standard
subject: Voicemail from {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: New voicemail notification
--}}
@extends('emails.email_layout')

@section('content')
<p>You have a new voice message:</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>From:</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>To mailbox:</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Received:</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Length:</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>Listen to this voicemail over your phone or by using the <a href="{{ $attributes['voicemail_download_url'] }}">secure download link</a>. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
@else
    <p>Listen to this voicemail over your phone. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
@endif
@endsection
