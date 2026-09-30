{{-- email-template
version: 1.0.0
language: fr
category: fax
subcategory: in-transit
format: html
layout: standard
subject: Envoi du fax au {{ $fax_destination }}
description: Notification de prise en charge d’un fax sortant
--}}
@extends('emails.fr.email_layout')

@section('content')
<!-- Start Content-->

<h1>Vos fichiers sont en cours d’envoi par fax au {{ $attributes['fax_destination'] }}.</h1>
<p>D’autres notifications vous informeront du résultat de la transmission.</p>
<!-- Action -->

<p>Si vous avez des questions, <a href="mailto:{{ $attributes["support_email"] ?? ''}}">contactez notre équipe d’assistance</a>. (Nous répondons très rapidement.)</p>
<p>Merci,
  <br>L’équipe {{ config('app.name', 'Laravel') }}</p>
<p><strong>P.-S.</strong> Besoin d’aide pour démarrer ? L’équipe d’assistance de {{ config('app.name', 'Laravel') }} est toujours prête à vous aider ! Consultez notre <a href="{{ $attributes["help_url"] ?? ''}}">documentation d’aide</a>. Vous pouvez aussi répondre à cet e-mail.</p>


@endsection
