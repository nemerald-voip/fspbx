{{-- email-template
version: 1.0.0
language: es-419
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: Correo electrónico no autorizado
description: Correo electrónico no autorizado
--}}
@extends('emails.es-419.email_layout')

@section('content')
<!-- Start Content-->

<h1>El envío de tu fax a {{ $attributes['fax_destination'] }} falló</h1>
<p>La dirección de correo electrónico de abajo no está autorizada para enviar faxes. Contacta al administrador del sistema.</p>
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
