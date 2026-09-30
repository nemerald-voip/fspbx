{{-- email-template
version: 1.0.0
language: es-419
category: voicemail
subcategory: transcription
format: html
layout: standard
subject: Mensaje de voz de {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: Notificación de mensaje de voz con transcripción
--}}
@extends('emails.es-419.email_layout')

@section('content')
<p>Tienes un nuevo mensaje de voz:</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>De:</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Para el buzón:</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Recibido:</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Duración:</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<p><strong>Vista previa del mensaje de voz:</strong></p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">{{ $attributes['message_text'] }}</td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>Escucha este mensaje de voz por teléfono o abre el archivo de audio adjunto. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>Escucha este mensaje de voz por teléfono o usa el <a href="{{ $attributes['voicemail_download_url'] }}">enlace de descarga seguro</a>. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.</p>
@else
    <p>Escucha este mensaje de voz por teléfono. También puedes iniciar sesión en tu cuenta para escuchar y administrar tus mensajes de voz.</p>
@endif
<p>Si tienes alguna pregunta, escribe a nuestro equipo de atención al cliente. (Respondemos muy rápido.)</p>
@endsection
