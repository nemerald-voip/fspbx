{{-- email-template
version: 1.0.0
language: ru
category: authentication
subcategory: reset-password
format: html
layout: standard
subject: Сброс пароля {{ config('app.name', 'FS PBX') }}
description: Сброс пароля
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<p>Здравствуйте{{ isset($attributes['name']) ? ' ' . $attributes['name'] : '' }},</p>

<p>Вы получили это письмо, потому что поступил запрос на сброс пароля вашей учётной записи в {{ config('app.name', 'Laravel') }}.</p>

<p><a href="{{ $attributes['url'] ?? '' }}">Сбросить пароль</a></p>

<p>Ссылка для сброса пароля истечёт через {{ $attributes['expire_minutes'] ?? '' }} минут.</p>

<p>Если вы не запрашивали сброс пароля, никаких действий не требуется.</p>

<p>Если у вас есть вопросы, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">напишите нашей службе поддержки</a>.</p>

<p>Берегите свои данные, <br>
Команда {{ config('app.name', 'Laravel') }}</p>

@endsection
