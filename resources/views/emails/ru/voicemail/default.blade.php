{{-- email-template
version: 1.0.0
language: ru
category: voicemail
subcategory: default
format: html
layout: standard
subject: Голосовое сообщение от {{ $caller_id_name }} <{{ $caller_id_number }}> {{ $message_duration }}
description: Уведомление о новом голосовом сообщении
--}}
@extends('emails.ru.email_layout')

@section('content')
<p>У вас новое голосовое сообщение:</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td class="attributes_content">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr><td class="attributes_item"><strong>От:</strong> {{ $attributes['caller_id_name'] }} {{ $attributes['caller_id_number'] }}</td></tr>
                <tr><td class="attributes_item"><strong>В почтовый ящик:</strong> {{ $attributes['dialed_user'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Получено:</strong> {{ $attributes['message_date'] }}</td></tr>
                <tr><td class="attributes_item"><strong>Длительность:</strong> {{ $attributes['message_duration'] }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if($attributes['voicemail_file_mode'] === 'attach')
    <p>Прослушайте голосовое сообщение по телефону или откройте вложенный аудиофайл. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.</p>
@elseif($attributes['voicemail_file_mode'] === 'link' && !empty($attributes['voicemail_download_url']))
    <p>Прослушайте голосовое сообщение по телефону или воспользуйтесь <a href="{{ $attributes['voicemail_download_url'] }}">защищённой ссылкой для скачивания</a>. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.</p>
@else
    <p>Прослушайте голосовое сообщение по телефону. Вы также можете войти в аккаунт, чтобы прослушивать голосовые сообщения и управлять ими.</p>
@endif
@endsection
