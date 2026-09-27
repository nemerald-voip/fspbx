{{-- email-template
version: 1.0.0
language: pt-br
category: fax
subcategory: failed
format: html
layout: standard
subject: Falha ao enviar fax para {{ $fax_destination }}
description: Notificação de falha no envio de fax
--}}
@extends('emails.pt-br.email_layout')

@section('content')
<!-- Start Content-->

<h1>O envio do seu fax para {{ $attributes['fax_destination'] }} falhou</h1>
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
