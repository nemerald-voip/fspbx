{{-- email-template
version: 1.0.0
language: ru
category: fax
subcategory: failed
format: html
layout: standard
subject: Ошибка отправки факса на номер {{ $fax_destination }}
description: Уведомление об ошибке отправки факса
--}}
@extends('emails.ru.email_layout')

@section('content')
<!-- Start Content-->

<h1>Отправка факса на номер {{ $attributes['fax_destination'] }} завершилась ошибкой</h1>
<p>{{ $attributes['email_message'] }}</p>
<!-- Action -->

{{-- <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>{{ $attributes['From'] }}</strong>
        </tr>
      </table>
    </td>
  </tr>
</table> --}}

@endsection
