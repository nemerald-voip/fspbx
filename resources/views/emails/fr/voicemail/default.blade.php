{{-- email-template
version: 1.0.0
language: fr
category: voicemail
subcategory: default
format: html
layout: standard
subject: Message vocal de {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: Notification de nouveau message vocal
--}}
@extends('emails.fr.email_layout')

@section('content')
<p>Vous avez un nouveau message vocal :</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>De :</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Pour la boîte vocale :</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Reçu :</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Durée :</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>Écoutez ce message vocal sur votre téléphone ou en ouvrant le fichier audio joint. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>Écoutez ce message vocal sur votre téléphone ou utilisez le <a href="{{ $attributes['voicemail_download_url'] }}">lien de téléchargement sécurisé</a>. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.</p>
@else
    <p>Écoutez ce message vocal sur votre téléphone. Vous pouvez également vous connecter à votre compte pour écouter et gérer vos messages vocaux.</p>
@endif
@endsection
