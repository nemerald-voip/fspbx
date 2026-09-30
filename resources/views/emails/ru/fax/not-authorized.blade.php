{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: Адрес электронной почты не авторизован
description: Адрес электронной почты не авторизован
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Отправка факса на номер {{ $attributes['fax_destination'] }} завершилась ошибкой</h1>
<p>Указанный ниже адрес электронной почты не имеет права отправлять факсы. Обратитесь к системному администратору.</p>
<!-- Action -->

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>{{ $attributes['from'] }}</strong>
        </tr>
      </table>
    </td>
  </tr>
</table>

@endsection
