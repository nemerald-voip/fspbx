{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: invalid-destination
format: html
layout: standard
subject: Ошибка отправки факса: неверный номер {{ $invalid_number }}
description: Ошибка отправки факса: неверный номер
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Отправка факса на номер {{ $attributes['invalid_number'] }} завершилась ошибкой</h1>
<p>Номер назначения не является допустимым номером телефона США.</p>
<!-- Action -->

@endsection
