{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: not-authorized
format: html
layout: standard
subject: Adresse e-mail non autorisée
description: Adresse e-mail non autorisée
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>L’envoi de votre fax au {{ $attributes['fax_destination'] }} a échoué</h1>
<p>L’adresse e-mail ci-dessous n’est pas autorisée à envoyer des fax. Contactez votre administrateur système.</p>
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
