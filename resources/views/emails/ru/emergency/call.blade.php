{{-- email-template
version: 1.0.0
language: ru
category: emergency
subcategory: call
format: html
layout: standard
subject: Экстренный вызов с номера {{ $attributes['caller'] }}
description: Уведомление об экстренном вызове
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content -->

<h1>Уведомление об экстренном вызове</h1>

<p>Экстренный вызов совершён с внутреннего номера <strong>{{ $attributes['caller'] }}</strong>.</p>

<p>Немедленно примите необходимые меры.</p>

<p>Спасибо. Берегите себя.</p>

@endsection
