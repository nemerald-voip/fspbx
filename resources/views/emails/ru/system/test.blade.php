{{-- email-template
version: 1.0.0
language: ru
category: system
subcategory: test
format: html
layout: standard
subject: Тестовое письмо от {{ config('app.name', 'FS PBX') }}
description: Проверка доставки электронной почты
--}}
@extends('emails.ru.email_layout')

@section('content')
<p>Здравствуйте,</p>

<p>Это тестовое письмо от {{ config('app.name', 'FS PBX') }}.</p>

<p>Если вы получили это сообщение, настроенная почтовая служба может отправлять письма.</p>

<p>Отправлено {{ $attributes['sent_at'] ?? now()->toDateTimeString() }}.</p>
@endsection
