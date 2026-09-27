{{-- email-template
version: 1.0.0
language: fr
category: extension
subcategory: welcome
format: html
layout: standard
subject: Votre poste {{ $attributes['extension'] }} est prêt
description: Informations du poste et de la messagerie vocale
--}}
@extends('emails.fr.email_layout')

@section('content')
<h1>Bienvenue, {{ $attributes['recipient_name'] }} !</h1>

<p>Nous avons configuré le poste <strong>{{ $attributes['extension'] }}</strong> pour vous. Conservez cet e-mail pour retrouver les informations de votre téléphone et de votre messagerie vocale.</p>

<table class="attributes" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td class="attributes_content">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td class="attributes_item"><strong>Poste :</strong> {{ $attributes['extension'] }}</td>
        </tr>
        @if (!empty($attributes['direct_numbers']))
          <tr>
            <td class="attributes_item"><strong>Numéros directs :</strong> {{ implode(', ', $attributes['direct_numbers']) }}</td>
          </tr>
        @endif
        <tr>
          <td class="attributes_item"><strong>Boîte vocale :</strong> {{ $attributes['voicemail_id'] }}</td>
        </tr>
        <tr>
          <td class="attributes_item"><strong>Code PIN de messagerie vocale :</strong> {{ $attributes['voicemail_pin'] }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<h2>Configurer votre message d’accueil vocal</h2>
<ol>
  <li>Composez le <strong>*97</strong> sur votre téléphone.</li>
  <li>Saisissez le code PIN de votre messagerie vocale, puis appuyez sur <strong>#</strong>.</li>
  <li>Appuyez sur <strong>5</strong> pour accéder aux options de la messagerie.</li>
  <li>Appuyez sur <strong>1</strong> pour enregistrer votre message d’indisponibilité.</li>
</ol>

@if (!empty($attributes['help_url']))
  <p>Vous trouverez plus d’aide dans le <a href="{{ $attributes['help_url'] }}">centre d’aide</a>.</p>
@endif

@if (!empty($attributes['support_email']))
  <p>Des questions ? Écrivez à <a href="mailto:{{ $attributes['support_email'] }}">{{ $attributes['support_email'] }}</a>.</p>
@endif

<p>Bienvenue,<br>{{ $attributes['app_name'] }}</p>
@endsection
