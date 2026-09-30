{{-- email-template
version: 1.0.0
language: pt-br
category: voicemail
subcategory: transcription
format: html
layout: standard
subject: Mensagem de voz de {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: Notificação de mensagem de voz com transcrição
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<p>Você tem uma nova mensagem de voz:</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>De:</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Para a caixa postal:</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Recebido:</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Duração:</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<p><strong>Prévia da mensagem de voz:</strong></p>
<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">{{ $attributes['message_text'] }}</td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>Ouça esta mensagem de voz pelo telefone ou abra o arquivo de áudio anexo. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>Ouça esta mensagem de voz pelo telefone ou use o <a href="{{ $attributes['voicemail_download_url'] }}">link de download seguro</a>. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.</p>
@else
    <p>Ouça esta mensagem de voz pelo telefone. Você também pode entrar na sua conta para ouvir e gerenciar suas mensagens de voz.</p>
@endif
<p>Se você tiver alguma dúvida, escreva para nossa equipe de atendimento ao cliente. (Respondemos rapidamente.)</p>
@endsection
