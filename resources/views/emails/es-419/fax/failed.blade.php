{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: failed
format: html
layout: standard
subject: Error al enviar el fax a {{ $fax_destination }}
description: Notificación de error al enviar un fax
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>El envío de tu fax a {{ $attributes['fax_destination'] }} falló</h1>
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
