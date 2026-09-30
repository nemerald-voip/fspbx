{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: E-mail não autorizado
description: E-mail não autorizado
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>O envio do seu fax para {{ $attributes['fax_destination'] }} falhou</h1>
<p>O endereço de e-mail abaixo não está autorizado a enviar faxes. Entre em contato com o administrador do sistema.</p>
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
